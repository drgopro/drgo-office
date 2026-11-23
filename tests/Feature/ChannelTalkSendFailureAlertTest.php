<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ServerErrorAlert;
use App\Services\ChannelTalkClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** 채널톡 발송 실패 — 톡방 알림이 조용히 끊기지 않게 관리자에게 오피스 알림 (피드백 #296) */
class ChannelTalkSendFailureAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '오피스알림',
        ]);
        Notification::fake();
    }

    public function test_send_failure_alerts_admins_once_per_throttle_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        Http::fake(['api.channel.io/*' => Http::response(['message' => 'payment required'], 402)]);

        $client = app(ChannelTalkClient::class);
        $this->assertFalse($client->sendGroupMessage('테스트')['ok']);
        $this->assertFalse($client->sendGroupMessage('테스트')['ok']); // 연속 실패 — 알림은 한 번만

        Notification::assertSentToTimes($admin, ServerErrorAlert::class, 1);
        Notification::assertSentTo($admin, ServerErrorAlert::class, fn (ServerErrorAlert $n) => str_contains($n->message, 'HTTP 402'));
        Notification::assertNotSentTo($member, ServerErrorAlert::class);
    }

    public function test_successful_send_and_missing_config_do_not_alert(): void
    {
        User::factory()->create(['role' => 'admin']);
        Http::fake(['api.channel.io/*' => Http::response(['message' => ['id' => '1']])]);

        $this->assertTrue(app(ChannelTalkClient::class)->sendGroupMessage('테스트')['ok']);

        config(['services.channeltalk.access_key' => '']); // 연동 안 한 환경(개발 등)은 알리지 않음
        $this->assertFalse(app(ChannelTalkClient::class)->sendGroupMessage('테스트')['ok']);

        Notification::assertNothingSent();
    }
}
