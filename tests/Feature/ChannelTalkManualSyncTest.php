<?php

namespace Tests\Feature;

use App\Models\ChannelTalkUser;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 채널톡 '지금 동기화' 수동 버튼 — 검색에 안 뜨는 최근 상담 고객을 즉시 미러에 반영.
 * 상태별 최신 1페이지만 훑고, 스케줄 동기화 커서는 건드리지 않는다.
 */
class ChannelTalkManualSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '테스트그룹',
        ]);
        cache()->lock('channeltalk.recent-sync', 15)->forceRelease();
    }

    public function test_manual_sync_mirrors_recent_users_and_autolinks(): void
    {
        $client = Client::create(['nickname' => '새고객', 'phone' => '010-7777-6666', 'grade' => 'normal']);

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            if (($q['state'] ?? '') === 'opened') {
                return Http::response(['users' => [
                    ['id' => 'ct-new', 'name' => '방금상담', 'profile' => ['mobileNumber' => '010-7777-6666']],
                ], 'next' => null]);
            }

            return Http::response(['users' => [], 'next' => null]);
        });

        $this->actingAs($this->admin)->postJson('/api/channeltalk/sync-recent')
            ->assertOk()
            ->assertJsonPath('upserted', 1)
            ->assertJsonPath('linked', 1);

        $this->assertSame('ct-new', $client->fresh()->channeltalk_user_id);
        $this->assertSame('01077776666', ChannelTalkUser::where('ct_id', 'ct-new')->value('mobile_digits'));

        // 미러 검색에도 바로 잡힌다
        $this->actingAs($this->admin)->getJson('/api/channeltalk/users?q=방금상담')
            ->assertOk()->assertJsonPath('0.ct_id', 'ct-new');
    }

    public function test_manual_sync_is_throttled(): void
    {
        Http::fake(fn () => Http::response(['users' => [], 'next' => null]));

        $this->actingAs($this->admin)->postJson('/api/channeltalk/sync-recent')->assertOk();
        $this->actingAs($this->admin)->postJson('/api/channeltalk/sync-recent')->assertStatus(429);
    }

    public function test_manual_sync_fails_clearly_when_not_configured(): void
    {
        config(['services.channeltalk.access_key' => null, 'services.channeltalk.access_secret' => null]);

        $this->actingAs($this->admin)->postJson('/api/channeltalk/sync-recent')->assertStatus(422);
    }

    public function test_manual_sync_reports_api_failure(): void
    {
        Http::fake(fn () => Http::response('oops', 500));

        $this->actingAs($this->admin)->postJson('/api/channeltalk/sync-recent')->assertStatus(502);
    }
}
