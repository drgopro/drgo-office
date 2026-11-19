<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 채널톡 해지 — 고객 동기화(미러·자동 연동·등록 모달 연동·프로필 반영) 제거 확인 */
class ChannelTalkSyncRemovedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
        ]);
    }

    public function test_sync_command_is_not_registered_or_scheduled(): void
    {
        $this->assertArrayNotHasKey('drgo:sync-channeltalk-users', Artisan::all());

        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");
        $this->assertStringNotContainsString('sync-channeltalk-users', $commands);
    }

    public function test_channeltalk_user_endpoints_are_gone(): void
    {
        $admin = User::factory()->create(['role' => 'master']);

        $this->actingAs($admin)->getJson('/api/channeltalk/users?q=test')->assertNotFound();
        $this->actingAs($admin)->postJson('/api/channeltalk/sync-recent')->assertNotFound();
        $this->actingAs($admin)->getJson('/api/channeltalk/users/ct-1/fresh')->assertNotFound();
    }

    public function test_editing_previously_linked_client_does_not_call_channeltalk(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal', 'channeltalk_user_id' => 'ct-old']);
        Http::fake();

        $this->actingAs($admin)->patchJson("/api/clients/{$client->id}", [
            'nickname' => '고블린수정', 'grade' => 'normal',
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertSame('고블린수정', $client->fresh()->nickname);
    }

    public function test_register_modal_has_no_channeltalk_link_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients')->assertOk()
            ->assertDontSee('채널톡 연동', false)
            ->assertDontSee('ncCtPanel', false);
    }
}
