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
    private const API_URL = 'https://oapi.koreaexim.go.kr/site/program/financial/exchangeJSON';

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

        // 주말·공휴일은 고시가 없어 빈 배열 — 최근 7일까지 거슬러 조회
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i);
            try {
                $res = Http::timeout(8)->connectTimeout(5)->get(self::API_URL, [
                    'authkey' => $key,
                    'searchdate' => $date->format('Ymd'),
                    'data' => 'AP01',
                ]);
            } catch (\Throwable $e) {
                return $this->fallback('환율 API 통신 실패: '.$e->getMessage());
            }
            if (! $res->ok()) {
                return $this->fallback('환율 API 응답 오류 (HTTP '.$res->status().')');
            }

            $usd = collect($res->json() ?: [])->firstWhere('cur_unit', 'USD');
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

        return $this->fallback('최근 7일 내 고시 환율을 찾지 못했습니다.');
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
