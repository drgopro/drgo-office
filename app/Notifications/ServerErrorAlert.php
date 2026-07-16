<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** 서버 에러 발생 알림 — 관리자에게 알림 벨 + 웹푸시 (동일 에러 1시간 스로틀) */
class ServerErrorAlert extends Notification
{
    public function __construct(
        public string $exceptionClass,
        public string $message,
        public string $location,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    private function buildTitle(): string
    {
        return '🚨 서버 에러: '.class_basename($this->exceptionClass);
    }

    private function buildBody(): string
    {
        $msg = mb_substr($this->message ?: '(메시지 없음)', 0, 120);

        return "{$msg}\n{$this->location}";
    }

    /** 상단 알림 리스트(database 채널) 저장용 */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->buildTitle(),
            'body' => $this->buildBody(),
            'url' => '/admin',
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->buildTitle())
            ->body($this->buildBody())
            ->icon('/icon-192.png')
            ->badge('/favicon-96x96.png')
            ->tag('server-error-alert')
            ->data(['url' => '/admin'])
            ->options(['TTL' => 86400]);
    }
}
