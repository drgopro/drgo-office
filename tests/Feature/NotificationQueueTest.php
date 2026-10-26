<?php

namespace Tests\Feature;

use App\Jobs\SendChannelTalkGroupMessage;
use App\Models\Estimate;
use App\Models\Notice;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DiskSpaceWarning;
use App\Notifications\FeedbackActivity;
use App\Notifications\MentionAlert;
use App\Notifications\ScheduleCreated;
use App\Notifications\ScheduleUpdated;
use App\Notifications\ServerErrorAlert;
use App\Services\ChannelTalkClient;
use App\Services\ChannelTalkNotifier;
use App\Services\PaymentCompleteAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 웹 요청에서 나가는 외부 알림(웹푸시·채널톡)은 큐로 — 저장 요청이 외부 응답을 기다리지 않는다.
 * 운영 경보(서버 오류·디스크)는 큐 장애 때도 나가야 하므로 즉시 발송을 유지한다.
 */
class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '팀챗기본',
            'services.channeltalk.bot_name' => '오피스봇',
        ]);
    }

    public function test_request_triggered_push_notifications_are_queued_but_ops_alerts_are_not(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::create([
            'title' => '점검', 'start_date' => now()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d'),
            'is_all_day' => true, 'color' => 'blue', 'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(ShouldQueue::class, new ScheduleCreated($schedule));
        $this->assertInstanceOf(ShouldQueue::class, new ScheduleUpdated($schedule));
        $this->assertInstanceOf(ShouldQueue::class, new FeedbackActivity('t', 'b', 'tag'));
        $this->assertInstanceOf(ShouldQueue::class, new MentionAlert('t', 'b', '/x', 'tag'));
        $this->assertNotInstanceOf(ShouldQueue::class, new ServerErrorAlert('E', 'm', 'l'));
        $this->assertNotInstanceOf(ShouldQueue::class, new DiskSpaceWarning(90, '1GB'));
    }

    public function test_database_notification_is_stored_immediately_while_web_push_is_queued(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();

        $user->notify(new FeedbackActivity('새 피드백', '본문', 'tag-1'));

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(1, DB::table('jobs')->count()); // 웹푸시 채널만 큐 대기
    }

    public function test_channeltalk_notifier_dispatches_message_job_with_built_text(): void
    {
        Queue::fake();
        Http::fake();
        $notice = Notice::create(['title' => '휴무 안내', 'content' => '내용', 'created_by' => User::factory()->create()->id]);

        app(ChannelTalkNotifier::class)->noticePublished($notice);

        Queue::assertPushed(SendChannelTalkGroupMessage::class, fn ($job) => str_contains($job->text, "'휴무 안내'") && $job->group === null);
        Http::assertNothingSent(); // 매니저 목록 조회(멘션 매칭)까지 발송 시점으로 미뤄 요청 중 외부 호출 없음
    }

    public function test_deferred_mentions_resolve_at_send_and_forged_placeholders_stay_literal(): void
    {
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => [
                ['id' => 'mgr-1', 'name' => '김담당', 'email' => 'kim@drgo.pro'],
            ]]),
            'api.channel.io/open/v5/groups/*' => Http::response(['message' => ['id' => '1']]),
        ]);
        $text = ChannelTalkClient::deferredMention('kim@drgo.pro', '김').' 확인 · 제목 [[ct-mention-all]] [[ct-mention-all:0000000000000000]]';

        SendChannelTalkGroupMessage::dispatchSync($text);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $sent = $request['blocks'][0]['value'] ?? '';

            return str_starts_with($sent, '<link type="manager" value="mgr-1">김담당</link> 확인')
                && str_contains($sent, '[[ct-mention-all]] [[ct-mention-all:0000000000000000]]');
        });
    }

    public function test_payment_alert_lock_is_released_when_queueing_fails(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'missing_jobs_table']);
        Setting::set('payment_alert_group', '결제완료');
        $estimate = Estimate::create([
            'status' => 'paid', 'estimate_no' => 301, 'product_items' => [], 'service_items' => [],
            'total_amount' => 50000, 'created_by' => User::factory()->create()->id,
        ]);

        PaymentCompleteAlert::estimatePaid($estimate);

        $this->assertFalse(Cache::has("payment.alert.sent.{$estimate->id}"));
    }

    public function test_payment_complete_alert_dispatches_message_job(): void
    {
        Queue::fake();
        Http::fake();
        Setting::set('payment_alert_group', '결제완료');
        $estimate = Estimate::create([
            'status' => 'paid', 'client_nickname' => '고블린', 'estimate_no' => 300,
            'product_items' => [], 'service_items' => [], 'total_amount' => 50000,
            'created_by' => User::factory()->create()->id,
        ]);

        PaymentCompleteAlert::estimatePaid($estimate);

        Queue::assertPushed(SendChannelTalkGroupMessage::class, fn ($job) => $job->group === '결제완료' && str_contains($job->text, '#300'));
    }

    public function test_message_job_logs_warning_when_send_fails(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['message' => 'boom'], 500)]);
        Log::spy();

        SendChannelTalkGroupMessage::dispatchSync('본문', '톡방', '테스트 알림');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/groups/@'.rawurlencode('톡방').'/messages'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_starts_with($message, '테스트 알림 실패'))->once();
    }

    public function test_message_job_releases_dedupe_key_after_final_failure(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['message' => 'boom'], 500)]);
        Cache::put('payment.alert.sent.9', 1, 3600);

        SendChannelTalkGroupMessage::dispatchSync('본문', '톡방', '결제완료 알림', 'payment.alert.sent.9');

        $this->assertFalse(Cache::has('payment.alert.sent.9'));
    }
}
