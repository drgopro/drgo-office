<?php

namespace Tests\Feature;

use App\Models\ChannelTalkUser;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 채널톡 고객 미러 동기화 + 의뢰자 자동 연동 + 등록 모달 '채널톡 연동' 검색 */
class ChannelTalkClientSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '테스트그룹',
        ]);
    }

    public function test_sync_mirrors_users_and_links_client_by_phone(): void
    {
        $client = Client::create(['nickname' => '고블린', 'phone' => '010-1234-5678', 'grade' => 'normal']);
        $paged = false;
        Http::fake(function ($request) use (&$paged) {
            if (! str_contains($request->url(), 'api.channel.io/open/v5/users')) {
                return Http::response([], 404);
            }
            if (! $paged) {
                $paged = true;

                return Http::response([
                    'users' => [
                        ['id' => 'ct-1', 'name' => '고블린', 'profile' => ['mobileNumber' => '+821012345678', 'email' => 'gob@test.com'], 'updatedAt' => 1757400000000],
                        ['id' => 'ct-2', 'profile' => ['name' => '신규상담', 'mobileNumber' => '010-9999-8888']],
                    ],
                    'next' => 'cursor-2',
                ]);
            }

            return Http::response(['users' => [
                ['id' => 'ct-3', 'name' => '전화없음'],
            ], 'next' => null]);
        });

        $this->artisan('drgo:sync-channeltalk-users')->assertSuccessful();

        $this->assertSame(3, ChannelTalkUser::count());
        // +82 정규화 → 010 매칭으로 기존 의뢰자 자동 연동
        $this->assertSame('ct-1', $client->fresh()->channeltalk_user_id);
        $this->assertSame('01012345678', ChannelTalkUser::where('ct_id', 'ct-1')->value('mobile_digits'));
        // profile.name 폴백
        $this->assertSame('신규상담', ChannelTalkUser::where('ct_id', 'ct-2')->value('name'));
        // 끝까지 돌았으면 커서 초기화 (다음 사이클 처음부터)
        $this->assertSame('', (string) Setting::get('channeltalk.users.cursor'));
    }

    public function test_sync_skips_ambiguous_phone_and_keeps_existing_link(): void
    {
        // 같은 번호 의뢰자 2명 — 오연동 방지 위해 자동 연동 안 함
        Client::create(['nickname' => 'A', 'phone' => '010-1111-2222', 'grade' => 'normal']);
        Client::create(['nickname' => 'B', 'phone' => '01011112222', 'grade' => 'normal']);
        // 이미 연동된 의뢰자 — 덮어쓰지 않음
        $linked = Client::create(['nickname' => 'C', 'phone' => '010-3333-4444', 'grade' => 'normal', 'channeltalk_user_id' => 'ct-old']);

        Http::fake(['api.channel.io/*' => Http::response(['users' => [
            ['id' => 'ct-a', 'name' => 'A', 'profile' => ['mobileNumber' => '010-1111-2222']],
            ['id' => 'ct-c', 'name' => 'C', 'profile' => ['mobileNumber' => '010-3333-4444']],
        ], 'next' => null])]);

        $this->artisan('drgo:sync-channeltalk-users')->assertSuccessful();

        $this->assertNull(Client::where('nickname', 'A')->value('channeltalk_user_id'));
        $this->assertNull(Client::where('nickname', 'B')->value('channeltalk_user_id'));
        $this->assertSame('ct-old', $linked->fresh()->channeltalk_user_id);
    }

    public function test_sync_failure_keeps_cursor_for_retry(): void
    {
        Setting::set('channeltalk.users.cursor', 'cursor-keep');
        Http::fake(['api.channel.io/*' => Http::response('error', 500)]);

        $this->artisan('drgo:sync-channeltalk-users')->assertFailed();

        $this->assertSame('cursor-keep', Setting::get('channeltalk.users.cursor'));
    }

    public function test_channeltalk_search_endpoint_matches_phone_and_marks_linked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ChannelTalkUser::create(['ct_id' => 'ct-1', 'name' => '고블린', 'mobile' => '+821012345678', 'mobile_digits' => '01012345678', 'email' => 'gob@test.com']);
        ChannelTalkUser::create(['ct_id' => 'ct-2', 'name' => '다른고객', 'mobile_digits' => '01099998888']);
        Client::create(['nickname' => '기존의뢰자', 'grade' => 'normal', 'channeltalk_user_id' => 'ct-1']);

        // 하이픈 섞인 검색어도 숫자만으로 매칭
        $res = $this->actingAs($admin)->getJson('/api/channeltalk/users?q=1234-5678')->assertOk()->json();
        $this->assertCount(1, $res);
        $this->assertSame('ct-1', $res[0]['ct_id']);
        $this->assertSame('기존의뢰자', $res[0]['linked_client']['label']); // 이미 연동됨 표시

        // 이름 검색
        $res2 = $this->actingAs($admin)->getJson('/api/channeltalk/users?q=다른고객')->assertOk()->json();
        $this->assertSame('ct-2', $res2[0]['ct_id']);
        $this->assertNull($res2[0]['linked_client']);
    }

    public function test_store_json_saves_channeltalk_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/clients', [
            'nickname' => '채널톡의뢰자', 'grade' => 'normal', 'channeltalk_user_id' => 'ct-77',
        ])->assertCreated();

        $this->assertSame('ct-77', Client::where('nickname', '채널톡의뢰자')->value('channeltalk_user_id'));
    }

    public function test_register_modal_renders_channeltalk_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients')->assertOk()
            ->assertSee('채널톡 연동', false)
            ->assertSee('ncCtPanel', false)
            ->assertSee('ctPick', false);
    }
}
