<?php

namespace App\Console\Commands;

use App\Services\OfficeAlert;
use App\Services\SslCertExpiry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('ssl:check-expiry {--days=7 : 알림 시작 잔여 일수} {--domains=office.drgo.pro,drgo.pro : 검사할 도메인 (콤마 구분)}')]
#[Description('SSL 인증서 만료 감시 — 만료 N일 전부터 매일 관리자 알림(종) + 만료일 캘린더 등록 (수동 설치 인증서 깜빡임 방지)')]
class CheckSslExpiry extends Command
{
    public function handle(SslCertExpiry $ssl): int
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
            } else {
                continue;
            }
            // 만료일에 교체 할 일로 남긴다 — 같은 도메인·만료일은 한 번만 등록
            OfficeAlert::registerOnCalendar([
                'date' => $date,
                'title' => "SSL 인증서 만료 — {$domain}",
                'description' => "{$domain} 인증서 만료일입니다. 갱신한 인증서로 교체했는지 확인해 주세요.",
                'source_type' => 'ssl_expiry',
            ]);
        }

        if ($warnings === []) {
            return self::SUCCESS;
        }

        OfficeAlert::raise('🔒 SSL 인증서 교체 필요', implode("\n", $warnings), '/calendar', 'ssl-expiry');
        $this->warn('만료 임박 — 관리자 알림 발송');

        return self::SUCCESS;
    }
}
