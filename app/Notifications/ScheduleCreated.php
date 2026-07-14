<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ScheduleCreated extends Notification
{
    public function __construct(public Schedule $schedule) {}

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
        $names = $s->assignees()->pluck('name')->implode(', ');

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
