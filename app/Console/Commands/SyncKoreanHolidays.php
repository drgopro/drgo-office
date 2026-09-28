<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('holidays:sync {--years=4 : 동기화할 연도 수 (작년부터)}')]
#[Description('한국 공휴일(대체공휴일 포함)을 Nager.Date 공개 API에서 받아 캘린더에 자동 반영')]
class SyncKoreanHolidays extends Command
{
    /** 저장 키 — 캘린더가 KR_HOLIDAYS에 병합해 모든 뷰에 표시 */
    public const SETTING_KEY = 'kr_holidays';

    public function handle(): int
    {
        $startYear = now()->year - 1;
        $years = max(1, min(6, (int) $this->option('years')));
        $holidays = [];
        $failed = 0;

        for ($year = $startYear; $year < $startYear + $years; $year++) {
            try {
                $res = Http::timeout(15)->connectTimeout(5)
                    ->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/KR");
            } catch (\Throwable $e) {
                Log::warning("공휴일 동기화 실패 ({$year}): ".$e->getMessage());
                $failed++;

                continue;
            }

            if (! $res->successful() || ! is_array($res->json())) {
                Log::warning("공휴일 동기화 응답 오류 ({$year}): HTTP ".$res->status());
                $failed++;

                continue;
            }

            foreach ($res->json() as $h) {
                $date = (string) ($h['date'] ?? '');
                $name = trim((string) ($h['localName'] ?? $h['name'] ?? ''));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $name !== '') {
                    // 같은 날 복수 항목(예: 어린이날+부처님오신날)은 이름을 이어 붙임
                    $holidays[$date] = isset($holidays[$date]) && ! str_contains($holidays[$date], $name)
                        ? $holidays[$date].'·'.$name
                        : $name;
                }
            }
        }

        if (! $holidays) {
            $this->error('가져온 공휴일이 없습니다 — 기존 저장값 유지');

            return self::FAILURE;
        }

        ksort($holidays);
        Setting::set(self::SETTING_KEY, json_encode($holidays, JSON_UNESCAPED_UNICODE));
        $this->info('공휴일 '.count($holidays)."건 저장 ({$startYear}~".($startYear + $years - 1).'년'.($failed ? ", 실패 {$failed}개 연도" : '').')');

        return self::SUCCESS;
    }
}
