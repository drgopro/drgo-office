<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DiskSpaceWarning extends Notification
{
    public function __construct(
        public int $usedPercent,
        public string $freeHuman,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    private function buildTitle(): string
    {
        return "⚠️ 서버 디스크 {$this->usedPercent}% 사용 중";
    }

    private function buildBody(): string
    {
        return "남은 용량 {$this->freeHuman}. 첨부파일 정리 또는 외부 스토리지 이전을 검토하세요.";
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
            ->tag('disk-space-warning')
            ->data(['url' => '/admin'])
            ->options(['TTL' => 86400]);
    }
}
