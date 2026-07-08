<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ScheduleReminder extends Notification
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
        $time = $s->is_all_day
            ? '종일'
            : substr((string) $s->start_time, 0, 5).($s->end_time ? '~'.substr((string) $s->end_time, 0, 5) : '');
        $body = collect([$time, $s->location])->filter()->implode(' · ');

        return (new WebPushMessage)
            ->title('📅 '.($s->title ?: '일정 알림'))
            ->body($body ?: '곧 시작하는 일정이 있습니다.')
            ->icon('/icon-192.png')
            ->badge('/favicon-96x96.png')
            ->tag('schedule-'.$s->id)
            ->data(['url' => '/calendar'])
            ->options(['TTL' => 3600]);
    }
}
