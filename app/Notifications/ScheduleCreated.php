<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ScheduleCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /** 웹푸시 재시도 (database 채널은 viaConnections로 즉시 저장) */
    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /** 발송 전에 일정이 영구 삭제되면 조용히 버린다 */
    public bool $deleteWhenMissingModels = true;

    /** 담당자 이름 — 채널(database·WebPush)·수신자마다 다시 조회하지 않도록 생성 시 한 번만 읽는다 */
    private string $assigneeNames;

    public function __construct(public Schedule $schedule)
    {
        $this->assigneeNames = $schedule->assignees()->pluck('name')->implode(', ');
        $this->afterCommit();
    }

    /**
     * 상단 알림 목록(database)은 요청 안에서 즉시 저장하고, 외부 전송(웹푸시)만 큐로 보낸다.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    private function buildTitle(): string
    {
        $s = $this->schedule;
        $date = $s->start_date?->format('n/j');
        $time = $s->is_all_day || ! $s->start_time ? '종일' : substr((string) $s->start_time, 0, 5);
        $names = $this->assigneeNames;

        return '🆕 '.collect([trim(($date ?? '').' '.$time), $s->title ?: '(제목 없음)', $names])->filter()->implode(' - ');
    }

    private function buildBody(): string
    {
        return $this->schedule->location ?: '새 일정에 담당자로 지정되었습니다.';
    }

    /** 상단 알림 리스트(database 채널) 저장용 */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->buildTitle(),
            'body' => $this->buildBody(),
            'url' => '/calendar',
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->buildTitle())
            ->body($this->buildBody())
            ->icon('/icon-192.png')
            ->badge('/favicon-96x96.png')
            ->tag('schedule-created-'.$this->schedule->id)
            ->data(['url' => '/calendar'])
            ->options(['TTL' => 3600]);
    }
}
