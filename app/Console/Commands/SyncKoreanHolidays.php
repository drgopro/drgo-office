<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('holidays:sync {--years=4 : 유지할 연도 수 (작년부터)}')]
#[Description('한국 공휴일(대체공휴일 포함)을 공공데이터 기반 holidays-kr에서 받아 캘린더에 자동 반영')]
class SyncKoreanHolidays extends Command
{
    /** 저장 키 — 캘린더가 KR_HOLIDAYS에 병합해 모든 뷰에 표시 */
    public const SETTING_KEY = 'kr_holidays';

    /**
     * 공공데이터포털(한국천문연구원) 특일정보를 매일 갱신·배포하는 공개 JSON.
     * 형식: {"YYYY-MM-DD": ["이름", ...]} — 대체공휴일은 '대체공휴일'로 정확히 표기된다.
     */
    private const SOURCE_URL = 'https://holidays.hyunbin.page/basic.json';

    public function handle(): int
    {
        try {
            $res = Http::timeout(20)->connectTimeout(5)->get(self::SOURCE_URL);
        } catch (\Throwable $e) {
            Log::warning('공휴일 동기화 실패: '.$e->getMessage());
            $this->error('공휴일 동기화 통신 실패 — 기존 저장값 유지');

            return self::FAILURE;
        }

        $data = $res->successful() ? $res->json() : null;
        if (! is_array($data) || ! $data) {
            Log::warning('공휴일 동기화 응답 오류: HTTP '.$res->status());
            $this->error('공휴일 응답 오류 — 기존 저장값 유지');

            return self::FAILURE;
        }

        $startYear = now()->year - 1;
        $endYear = $startYear + max(1, min(6, (int) $this->option('years'))) - 1;

        $holidays = [];
        foreach ($data as $date => $names) {
            if (! preg_match('/^(\d{4})-\d{2}-\d{2}$/', (string) $date, $m)) {
                continue;
            }
            $year = (int) $m[1];
            if ($year < $startYear || $year > $endYear) {
                continue; // 캘린더 사용 범위만 유지 (2018년부터 전체가 오므로 축소)
            }
            $list = collect(is_array($names) ? $names : [$names])
                ->map(fn ($n) => trim((string) $n))->filter()->unique()->values();
            if ($list->isNotEmpty()) {
                $holidays[$date] = $list->implode('·');
            }
        }

        if (! $holidays) {
            $this->error('가져온 공휴일이 없습니다 — 기존 저장값 유지');

            return self::FAILURE;
        }

        ksort($holidays);
        Setting::set(self::SETTING_KEY, json_encode($holidays, JSON_UNESCAPED_UNICODE));
        $this->info('공휴일 '.count($holidays)."건 저장 ({$startYear}~{$endYear}년)");

        return self::SUCCESS;
    }
}
