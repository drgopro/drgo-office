<?php

namespace App\Jobs;

use App\Services\MattermostClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;

/** Mattermost 웹훅 발송 — 요청 밖(큐)에서 전송·재시도. 실패해도 예외 없이 경고 로그만 남긴다 */
class SendMattermostMessage implements ShouldQueue
{
    use Queueable;

    /** 재시도 간격(초) — 시도 횟수는 이 배열 길이 + 1 */
    private const RETRY_DELAYS = [10, 60];

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public string $url,
        public string $text,
        public string $context = 'Mattermost 알림',
    ) {
        $this->afterCommit();
    }

    public function handle(MattermostClient $client): void
    {
        $res = $client->send($this->url, $this->text);
        if ($res['ok']) {
            return;
        }

        $attempt = $this->attempts();
        if ($this->job && ! $this->job instanceof SyncJob && $attempt < $this->tries) {
            $this->release(self::RETRY_DELAYS[$attempt - 1] ?? 60);

            return;
        }

        Log::warning("{$this->context} 실패: ".($res['error'] ?? '알 수 없음'));
    }
}
