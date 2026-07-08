<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FeedbackActivity extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $tag,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icon-192.png')
            ->badge('/favicon-96x96.png')
            ->tag($this->tag)
            ->data(['url' => '/feedback'])
            ->options(['TTL' => 3600]);
    }
}
