<?php

namespace App\Jobs;

use App\Services\ChannelTalkClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 채널톡 팀챗 그룹 메시지 발송 — 외부 HTTP 호출을 요청 밖(큐)에서 처리한다.
 * 메시지 본문은 호출 시점에 완성해서 넘기고, 여기서는 전송과 재시도만 담당한다.
 * 실패해도 예외를 던지지 않고 재시도 후 경고 로그만 남긴다 (sync 큐에서도 본 동작을 깨지 않도록).
 *
 * 매니저 멘션은 본문에 자리표시자(ChannelTalkClient::deferredMention)로 넣고, 발송 시
 * sendGroupMessage가 실제 멘션 태그로 바꾼다 — 요청 중 채널톡 외부 호출 0회.
 */
class SendChannelTalkGroupMessage implements ShouldQueue
{
    use Queueable;

    /** 재시도 간격(초) — 시도 횟수는 이 배열 길이 + 1 */
    private const RETRY_DELAYS = [10, 60];

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  string|null  $dedupeKey  호출 측 중복 방지 캐시 키 — 최종 실패 시 지워서 재통보 때 다시 보낼 수 있게 한다
     */
    public function __construct(
        public string $text,
        public ?string $group = null,
        public string $context = '채널톡 알림',
        public ?string $dedupeKey = null,
    ) {
        $this->afterCommit();
    }

    public function handle(ChannelTalkClient $client): void
    {
        $res = $client->sendGroupMessage($this->text, $this->group);
        if ($res['ok'] ?? false) {
            return;
        }

        $attempt = $this->attempts();
        if ($this->job && ! $this->job instanceof SyncJob && $attempt < $this->tries) {
            $this->release(self::RETRY_DELAYS[$attempt - 1] ?? 60);

            return;
        }

        Log::warning("{$this->context} 실패: ".($res['error'] ?? '알 수 없음'));
        if ($this->dedupeKey) {
            Cache::forget($this->dedupeKey);
        }
    }
}
