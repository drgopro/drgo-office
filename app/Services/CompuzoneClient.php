<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;

/**
 * 컴퓨존(compuzone.co.kr) 상품 페이지에서 판매가를 추출하는 크롤링 클라이언트.
 *
 * 공식 API가 없어 HTML 파싱에 의존한다. 페이지 구조 변경·차단에 대비해
 * 가격 추출을 다단계 폴백으로 방어적으로 작성했고, 실패 시 원본 스니펫을
 * storage/logs/compuzone.log 에 남겨 서버에서 셀렉터를 보정할 수 있게 한다.
 * 파싱 로직 수정은 이 클래스 안에서만 이루어진다.
 */
class CompuzoneClient
{
    /** 가격 정합성 범위 — 이 밖의 숫자는 가격 후보에서 제외 */
    private const MIN_PRICE = 100;

    private const MAX_PRICE = 100_000_000;

    private const MAX_LOG_BYTES = 5 * 1024 * 1024;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    /** 컴퓨존 도메인(서브도메인 포함) http/https URL만 허용 */
    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && ($host === 'compuzone.co.kr' || str_ends_with($host, '.compuzone.co.kr'));
    }

    /**
     * 상품 페이지 1건 조회 → 가격 추출. 절대 throw하지 않는다.
     *
     * @param  bool  $logProbe  true면 성공해도 진단 스니펫을 compuzone.log에 남긴다
     * @return array{price:?int, error:?string, http_status:?int, strategy:?string}
     */
    public function fetch(string $url, bool $logProbe = false): array
    {
        if (! $this->isAllowedUrl($url)) {
            return ['price' => null, 'error' => '컴퓨존 주소만 조회할 수 있습니다.', 'http_status' => null, 'strategy' => null];
        }

        try {
            $res = Http::timeout(15)->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'ko,ko-KR;q=0.9',
                    'Referer' => 'https://www.compuzone.co.kr/',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            $this->log($url, null, null, null, '연결 실패: '.$e->getMessage());

            return ['price' => null, 'error' => '컴퓨존 연결 실패 (타임아웃/네트워크)', 'http_status' => null, 'strategy' => null];
        }

        $status = $res->status();
        if (! $res->ok()) {
            $this->log($url, $status, null, null, mb_substr($res->body(), 0, 400));
            $error = $status === 403
                ? '컴퓨존이 요청을 차단했습니다 (HTTP 403) — 잠시 후 다시 시도해주세요'
                : "컴퓨존 응답 오류 (HTTP {$status})";

            return ['price' => null, 'error' => $error, 'http_status' => $status, 'strategy' => null];
        }

        // 인코딩 변환 전이므로 바이트 기준으로 자른다 (EUC-KR 바이트에 mb_substr 사용 금지)
        $html = substr($res->body(), 0, 400000);

        // 선언된 charset이 UTF-8이 아니면 변환 (컴퓨존은 EUC-KR)
        if (preg_match('/charset=["\']?([\w-]+)/i', $html, $cm) && strcasecmp($cm[1], 'utf-8') !== 0) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $cm[1]);
            if ($converted) {
                $html = $converted;
            }
        }

        [$price, $strategy] = $this->extractPrice($html);

        if ($price === null || $logProbe) {
            $this->log($url, $status, $price, $strategy, $this->priceSnippets($html));
        }

        if ($price === null) {
            return ['price' => null, 'error' => '페이지에서 가격을 찾지 못했습니다 (구조 변경 가능성)', 'http_status' => $status, 'strategy' => null];
        }

        return ['price' => $price, 'error' => null, 'http_status' => $status, 'strategy' => $strategy];
    }

    /**
     * 제품의 시세를 갱신해 저장. 성공 시에만 market_price를 덮어쓴다.
     * 자동 갱신이 매일 돌기 때문에 활동 로그를 남기지 않는 saveQuietly를 쓴다.
     */
    public function refresh(Product $product): bool
    {
        if (! $product->market_price_url) {
            return false;
        }

        $result = $this->fetch($product->market_price_url);
        $product->market_price_checked_at = now();

        if ($result['price'] !== null) {
            $product->market_price = $result['price'];
            $product->market_price_error = null;
            $product->saveQuietly();

            return true;
        }

        $product->market_price_error = mb_substr((string) $result['error'], 0, 200);
        $product->saveQuietly();

        return false;
    }

    /**
     * 다단계 가격 추출 폴백 체인 — 첫 성공 후보 채택.
     *
     * @return array{0:?int, 1:?string} [가격, 채택 전략]
     */
    private function extractPrice(string $html): array
    {
        // 1) meta og:price / product:price / itemprop=price
        foreach ([
            '/<meta[^>]+(?:property|name)=["\'](?:og|product):price(?::amount)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:og|product):price(?::amount)?["\']/i',
            '/<[^>]+itemprop=["\']price["\'][^>]*content=["\']([^"\']+)["\']/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m) && ($p = $this->sanitizePrice($m[1])) !== null) {
                return [$p, 'meta'];
            }
        }

        // 2) JSON-LD "price"
        if (preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/si', $html, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match('/"price"\s*:\s*"?([\d,.]+)"?/', $block, $m) && ($p = $this->sanitizePrice($m[1])) !== null) {
                    return [$p, 'json-ld'];
                }
            }
        }

        // 3) id/class에 prc·price가 들어간 요소 안의 숫자
        if (preg_match_all('/<[^>]+(?:id|class)=["\'][^"\']*(?:prc|price)[^"\']*["\'][^>]*>([^<]{0,80})</i', $html, $m)) {
            foreach ($m[1] as $text) {
                if (preg_match('/([\d,]{4,})/', $text, $pm) && ($p = $this->sanitizePrice($pm[1])) !== null) {
                    return [$p, 'prc-element'];
                }
            }
        }

        // 4) 가격 키워드 근방 200자 내 "1,234,000원" 패턴
        if (preg_match_all('/(?:판매가|할인가|카드가|가격)[^가-힣]{0,200}?([\d,]{4,})\s*원/u', $html, $m)) {
            foreach ($m[1] as $cand) {
                if (($p = $this->sanitizePrice($cand)) !== null) {
                    return [$p, 'keyword'];
                }
            }
        }

        return [null, null];
    }

    /** 숫자 문자열 → 정합성 검사(100원~1억) 통과한 int, 실패 시 null */
    private function sanitizePrice(string $raw): ?int
    {
        $num = (int) str_replace([',', '.'], '', trim($raw));

        return ($num >= self::MIN_PRICE && $num <= self::MAX_PRICE) ? $num : null;
    }

    /** 가격 마커 주변 ±400자 스니펫 — 서버에서 셀렉터 보정용 */
    private function priceSnippets(string $html): string
    {
        $snippets = [];
        foreach (['판매가', 'og:price', 'itemprop="price"', 'prc', '원'] as $marker) {
            $pos = mb_strpos($html, $marker);
            if ($pos !== false) {
                $snippets[] = "--- [{$marker}] ---\n".mb_substr($html, max(0, $pos - 400), 800);
            }
        }

        return $snippets ? implode("\n", $snippets) : '(가격 마커 없음) head 800자: '.mb_substr($html, 0, 800);
    }

    /** compuzone.log에 진단 기록 append — 5MB 초과 시 초기화 */
    private function log(string $url, ?int $status, ?int $price, ?string $strategy, string $detail): void
    {
        try {
            $path = storage_path('logs/compuzone.log');
            if (file_exists($path) && filesize($path) > self::MAX_LOG_BYTES) {
                @unlink($path);
            }
            $line = sprintf(
                "[%s] url=%s http=%s price=%s strategy=%s\n%s\n\n",
                now()->format('Y-m-d H:i:s'),
                $url,
                $status ?? '-',
                $price ?? '-',
                $strategy ?? '-',
                $detail
            );
            @file_put_contents($path, $line, FILE_APPEND);
        } catch (\Throwable) {
            // 진단 로그 실패는 무시 — 본 동작에 영향 없음
        }
    }
}
