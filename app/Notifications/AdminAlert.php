<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** 관리자 확인이 필요한 운영 알림 — 상단 알림(종) + 웹푸시 (채널톡 해지 후 대체 경로) */
class AdminAlert extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/',
        public string $tag = 'admin-alert',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
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
            ->options(['TTL' => 86400]);
    }
}
