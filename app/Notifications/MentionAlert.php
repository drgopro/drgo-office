<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** @멘션 호출 및 대댓글 알림 — 피드백/위키 공용 */
class MentionAlert extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icon-192.png')
            ->badge('/favicon-96x96.png')
            ->tag($this->tag)
            ->data(['url' => $this->url])
            ->options(['TTL' => 3600]);
    }
}
