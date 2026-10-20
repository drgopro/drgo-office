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
     * 관보 기준 holidays-kr 공개 JSON — GitHub 원본을 1순위로, 배포 도메인을 폴백으로 사용
     * (배포 도메인 DNS가 간헐적으로 끊긴 사례가 있어 순서를 바꿈).
     * 형식: {"YYYY": {"YYYY-MM-DD": ["이름", ...]}} 중첩 — 구버전 평면 형식({"YYYY-MM-DD": [...]})도 함께 지원한다.
     */
    private const SOURCE_URLS = [
        'https://raw.githubusercontent.com/hyunbinseo/holidays-kr/main/public/basic.json',
        'https://holidays.hyunbin.page/basic.json',
    ];

    public function handle(): int
    {
        $data = null;
        foreach (self::SOURCE_URLS as $url) {
            try {
                // 주 1회 실행 — 일시 장애에 알람이 울리지 않도록 소스별 3회(10초 간격) 재시도
                $res = Http::timeout(20)->connectTimeout(5)
                    ->retry(3, app()->environment('testing') ? 0 : 10000, throw: false)
                    ->get($url);
            } catch (\Throwable $e) {
                Log::warning("공휴일 동기화 실패({$url}): ".$e->getMessage());

                continue;
            }
            $json = $res->successful() ? $res->json() : null;
            if (is_array($json) && $json) {
                $data = $json;
                break;
            }
            Log::warning("공휴일 동기화 응답 오류({$url}): HTTP ".$res->status());
        }
        if ($data === null) {
            $this->error('공휴일 동기화 실패 — 모든 소스 불가, 기존 저장값 유지');

            return self::FAILURE;
        }

        // 중첩 형식({연도: {날짜: [이름]}})이면 평면으로 — 구 평면 형식은 그대로
        $flat = [];
        foreach ($data as $key => $value) {
            if (preg_match('/^\d{4}$/', (string) $key) && is_array($value)) {
                foreach ($value as $d => $names) {
                    $flat[$d] = $names;
                }
            } else {
                $flat[$key] = $value;
            }
        }
        $data = $flat;

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
