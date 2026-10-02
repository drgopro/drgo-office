<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 한국수출입은행 환율 — 견적서 달러 표시용 USD 매매기준율(deal_bas_r).
 * 주말/공휴일에는 고시가 없으므로 최근 영업일로 거슬러 올라가 조회하고,
 * 성공한 값은 Setting에 저장해 API 장애 시 마지막 값으로 폴백한다.
 * 견적서에는 'USD로 적용' 시점의 환율·기준일이 고정 저장된다 (재조회 없음).
 */
class ExchangeRateService
{
    /** oapi가 공식 Open API 도메인 — 환경에 따라 구 도메인(www)만 열리는 경우가 있어 순차 시도 */
    private const API_URLS = [
        'https://oapi.koreaexim.go.kr/site/program/financial/exchangeJSON',
        'https://www.koreaexim.go.kr/site/program/financial/exchangeJSON',
    ];

    /** 수출입은행 result 코드 — 1 외에는 오류 (행마다 들어있음) */
    private const RESULT_ERRORS = [
        2 => 'DATA 코드 오류 (data=AP01 파라미터 확인)',
        3 => '인증코드 오류 — KOREAEXIM_API_KEY 값이 잘못되었거나 현재환율 API 사용 신청이 안 된 키입니다',
        4 => '일일 호출 제한(1,000회) 초과 — 내일 다시 시도하세요',
    ];

    /**
     * @return array{ok: bool, rate?: float, date?: string, error?: string}
     */
    public function usdRate(): array
    {
        $cached = Cache::get('exrate.usd');
        if (is_array($cached)) {
            return ['ok' => true, 'rate' => $cached['rate'], 'date' => $cached['date']];
        }

        $key = (string) config('services.koreaexim.key');
        if ($key === '') {
            return $this->fallback('환율 API 키가 설정되지 않았습니다 (.env KOREAEXIM_API_KEY — 수출입은행 Open API에서 발급).');
        }

        $lastError = '최근 7일 내 고시 환율을 찾지 못했습니다.';
        foreach (self::API_URLS as $url) {
            // 주말·공휴일은 고시가 없어 빈 배열 — 최근 7일까지 거슬러 조회
            for ($i = 0; $i < 7; $i++) {
                $date = now()->subDays($i);
                try {
                    $res = Http::timeout(8)->connectTimeout(5)->get($url, [
                        'authkey' => $key,
                        'searchdate' => $date->format('Ymd'),
                        'data' => 'AP01',
                    ]);
                } catch (\Throwable $e) {
                    $lastError = '환율 API 통신 실패: '.$e->getMessage();

                    continue 2; // 이 도메인은 포기하고 다음 도메인으로
                }
                if (! $res->ok()) {
                    $lastError = '환율 API 응답 오류 (HTTP '.$res->status().')';

                    continue 2;
                }

                $rows = $res->json();
                if (! is_array($rows) || $rows === []) {
                    continue; // 비영업일(고시 없음) — 하루 전으로
                }
                // 오류 코드 응답 — 행마다 result가 있고 1이 아니면 사유를 그대로 안내
                $resultCode = (int) ($rows[0]['result'] ?? ($rows['result'] ?? 1));
                if ($resultCode !== 1) {
                    return $this->fallback('환율 API 오류: '.(self::RESULT_ERRORS[$resultCode] ?? "result={$resultCode}"));
                }

                $usd = collect($rows)->firstWhere('cur_unit', 'USD');
                if ($usd && ! empty($usd['deal_bas_r'])) {
                    $rate = (float) str_replace(',', '', (string) $usd['deal_bas_r']);
                    if ($rate > 0) {
                        $value = ['rate' => $rate, 'date' => $date->format('Y-m-d')];
                        Cache::put('exrate.usd', $value, now()->addHours(6));
                        Setting::set('usd_rate_last', json_encode($value));

                        return ['ok' => true, 'rate' => $rate, 'date' => $value['date']];
                    }
                }
            }
        }

        return $this->fallback($lastError);
    }

    /** API 실패 시 마지막 성공값으로 폴백 — 그것도 없으면 오류 반환 */
    private function fallback(string $error): array
    {
        $last = json_decode((string) Setting::get('usd_rate_last', ''), true);
        if (is_array($last) && ! empty($last['rate'])) {
            return ['ok' => true, 'rate' => (float) $last['rate'], 'date' => (string) $last['date'], 'error' => $error.' — 마지막 고시 환율을 사용합니다.'];
        }

        return ['ok' => false, 'error' => $error];
    }
}
