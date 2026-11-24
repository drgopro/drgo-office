<?php

namespace Tests\Feature;

use App\Jobs\SendChannelTalkGroupMessage;
use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChannelTalkClient;
use App\Services\PaymentCompleteAlert;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 채널톡 해지 — 연동 스위치가 꺼져 있으면(기본값) 키가 남아 있어도 채널톡 API를 호출하지 않는다 */
class ChannelTalkDisconnectedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.enabled' => false,
            'services.channeltalk.access_key' => 'left-over-key',
            'services.channeltalk.access_secret' => 'left-over-secret',
            'services.channeltalk.group' => '남은그룹',
        ]);
        Http::fake();
    }

    public function test_switch_is_off_by_default(): void
    {
        $this->assertFalse((bool) env('CHANNELTALK_ENABLED', false));
    }

    public function test_client_never_calls_api_when_disabled(): void
    {
        $client = app(ChannelTalkClient::class);

        $this->assertFalse($client->isConfigured());
        $this->assertFalse($client->sendGroupMessage('테스트')['ok']);
        $this->assertFalse($client->listGroups()['ok']);
        $this->assertSame([], $client->managers());

        Http::assertNothingSent();
    }

    public function test_queued_message_is_dropped_without_api_call(): void
    {
        SendChannelTalkGroupMessage::dispatch('결제 알림', '결제방', '테스트');

        Http::assertNothingSent();
    }

    public function test_payment_alert_is_skipped(): void
    {
        Setting::set('payment_alert_group', '결제방');
        $estimate = Estimate::create([
            'status' => 'paid', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 10000, 'validity_days' => 3,
        ]);

        PaymentCompleteAlert::estimatePaid($estimate);

        Http::assertNothingSent();
    }

    public function test_channeltalk_only_schedules_do_not_run(): void
    {
        $schedule = app(Schedule::class);
        $due = collect($schedule->events())
            ->filter(fn ($e) => $e->filtersPass($this->app))
            ->map(fn ($e) => (string) $e->command)->implode("\n");

        foreach (['drgo:watch-boards', 'schedules:channeltalk-digest', 'todos:remind', 'marketing:consent-digest', 'ssl:check-expiry'] as $command) {
            $this->assertStringNotContainsString($command, $due);
        }
        $this->assertStringContainsString('shipments:refresh', $due); // 다른 예약 작업은 그대로

        config(['services.channeltalk.enabled' => true]);
        $dueOn = collect($schedule->events())
            ->filter(fn ($e) => $e->filtersPass($this->app))
            ->map(fn ($e) => (string) $e->command)->implode("\n");
        $this->assertStringContainsString('drgo:watch-boards', $dueOn);
    }

    public function test_admin_alert_settings_show_disconnected_notice(): void
    {
        $admin = User::factory()->create(['role' => 'master']);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('채널톡 연동이 해제되어 있어 이 알림은 발송되지 않습니다.');
    }
}
