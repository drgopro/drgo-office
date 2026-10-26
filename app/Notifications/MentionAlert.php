<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** @멘션 호출 및 대댓글 알림 — 피드백/위키 공용 */
class MentionAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** 웹푸시 재시도 (database 채널은 viaConnections로 즉시 저장) */
    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
    ) {
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
