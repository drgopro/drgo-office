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
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $s = $this->schedule;
        $date = $s->start_date?->format('n/j');
        $time = $s->is_all_day || ! $s->start_time ? '종일' : substr((string) $s->start_time, 0, 5);
        $body = collect([trim($date.' '.$time), $s->location])->filter()->implode(' · ');

        return (new WebPushMessage)
            ->title('🆕 새 일정: '.($s->title ?: '(제목 없음)'))
            ->body($body ?: '새 일정에 담당자로 지정되었습니다.')
            ->icon('/icon-192.png')
            ->badge('/favicon-96x96.png')
            ->tag('schedule-created-'.$s->id)
            ->data(['url' => '/calendar'])
            ->options(['TTL' => 3600]);
    }
}
