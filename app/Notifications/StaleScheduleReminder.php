<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** 날짜가 지났는데 완료 처리되지 않은 일정 — 상태 정리(완료/보류) 리마인드 */
class StaleScheduleReminder extends Notification
{
    public function __construct(public Schedule $schedule, public int $daysOver) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    private function buildTitle(): string
    {
        return '⏰ 상태 정리 필요 — '.($this->schedule->title ?: '(제목 없음)');
    }

    private function buildBody(): string
    {
        $date = $this->schedule->start_date?->format('m.d') ?? '';

        return "{$date} 일정이 {$this->daysOver}일 지났지만 완료 처리되지 않았습니다. 완료 여부를 정리해주세요.";
    }

    /** @return array<string, string> */
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
            ->tag('stale-schedule-'.$this->schedule->id)
            ->data(['url' => '/calendar'])
            ->options(['TTL' => 3600 * 12]);
    }
}
