<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 주소 → 좌표 변환(카카오 로컬 API) 후 지도 길찾기 URL 생성.
 *
 * 카카오/네이버 웹 지도는 이름 기반 길찾기 URL 프리필을 더 이상 지원하지
 * 않아, 좌표 기반 공식 링크로 만들어 준다. KAKAO_REST_API_KEY 미설정 시
 * 422를 반환하고 프론트가 이름 기반 URL로 폴백한다.
 */
class GeocodeController extends Controller
{
    public function routeUrls(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|max:200',
            'to' => 'required|string|max:200',
        ]);

        $key = (string) config('services.kakao.rest_key');
        if ($key === '') {
            return response()->json(['error' => '카카오 REST API 키가 설정되지 않았습니다.'], 422);
        }

        $from = $this->geocode($validated['from'], $key);
        $to = $this->geocode($validated['to'], $key);
        if (! $from || ! $to) {
            return response()->json(['error' => '주소를 좌표로 변환하지 못했습니다.'], 422);
        }

        // 이름의 콤마/슬래시는 링크 구분자와 충돌 — 공백으로 치환
        $name = fn (string $s): string => rawurlencode(str_replace([',', '/'], ' ', $s));

        return response()->json([
            'kakao_url' => sprintf(
                'https://map.kakao.com/link/from/%s,%s,%s/to/%s,%s,%s',
                $name($validated['from']), $from['lat'], $from['lng'],
                $name($validated['to']), $to['lat'], $to['lng'],
            ),
            'naver_url' => sprintf(
                'https://map.naver.com/p/directions/%s,%s,%s/%s,%s,%s/-/car',
                $from['lng'], $from['lat'], $name($validated['from']),
                $to['lng'], $to['lat'], $name($validated['to']),
            ),
        ]);
    }

    /**
     * 카카오 로컬 API 지오코딩 — 주소 검색 우선, 실패 시 키워드(장소명) 검색.
     * 결과는 30일 캐시 (같은 주소 반복 조회 방지).
     *
     * @return ?array{lat:string, lng:string}
     */
    private function geocode(string $query, string $key): ?array
    {
        return Cache::remember('geocode:'.md5($query), now()->addDays(30), function () use ($query, $key) {
            foreach ([
                'https://dapi.kakao.com/v2/local/search/address.json',
                'https://dapi.kakao.com/v2/local/search/keyword.json',
            ] as $endpoint) {
                try {
                    $res = Http::timeout(8)->connectTimeout(4)
                        ->withHeaders(['Authorization' => 'KakaoAK '.$key])
                        ->get($endpoint, ['query' => $query, 'size' => 1]);
                } catch (\Throwable) {
                    continue;
                }
                $doc = $res->json('documents.0');
                if ($res->ok() && ! empty($doc['x']) && ! empty($doc['y'])) {
                    return ['lat' => (string) $doc['y'], 'lng' => (string) $doc['x']];
                }
            }

            return null;
        });
    }
}
