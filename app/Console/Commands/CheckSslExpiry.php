<?php

namespace App\Console\Commands;

use App\Services\ChannelTalkClient;
use App\Services\SslCertExpiry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('ssl:check-expiry {--days=7 : 알림 시작 잔여 일수} {--domains=office.drgo.pro,drgo.pro : 검사할 도메인 (콤마 구분)}')]
#[Description('SSL 인증서 만료 감시 — 만료 N일 전부터 매일 채널톡 팀챗으로 교체 알림 (수동 설치 인증서 깜빡임 방지)')]
class CheckSslExpiry extends Command
{
    public function handle(SslCertExpiry $ssl, ChannelTalkClient $channelTalk): int
    {
        $threshold = max(1, (int) $this->option('days'));
        $domains = array_filter(array_map('trim', explode(',', (string) $this->option('domains'))));

        $warnings = [];
        foreach ($domains as $domain) {
            $info = $ssl->inspect($domain);
            if ($info === null) {
                // 일시적 네트워크 문제일 수 있어 알림 대신 로그만 — 만료 임박이면 다음날 다시 잡힌다
                Log::warning("SSL 만료 감시: {$domain} 인증서를 읽지 못했습니다.");
                $this->warn("{$domain} — 인증서 확인 실패");

                continue;
            }

            $date = $info['expires_at']->format('Y-m-d');
            $this->info("{$domain} — 만료 {$date} (D-{$info['days_left']})");

            if ($info['days_left'] < 0) {
                $warnings[] = "{$domain} 인증서가 만료되었습니다! (만료일 {$date}) — 즉시 교체가 필요합니다.";
            } elseif ($info['days_left'] <= $threshold) {
                $warnings[] = "{$domain} 인증서가 {$info['days_left']}일 후({$date}) 만료됩니다. 갱신한 인증서로 교체해 주세요.";
            }
        }

        if ($warnings === []) {
            return self::SUCCESS;
        }

        if (! $channelTalk->isConfigured()) {
            Log::warning('SSL 만료 임박 — 채널톡 미설정으로 알림을 보내지 못했습니다: '.implode(' / ', $warnings));

            return self::SUCCESS;
        }

        $res = $channelTalk->sendGroupMessage("[SSL 인증서 알림]\n".implode("\n", $warnings));
        if (! ($res['ok'] ?? false)) {
            Log::warning('SSL 만료 알림 발송 실패: '.($res['error'] ?? '알 수 없음'));

            return self::FAILURE;
        }
        $this->warn('만료 임박 — 채널톡 알림 발송');

        return self::SUCCESS;
    }
}
