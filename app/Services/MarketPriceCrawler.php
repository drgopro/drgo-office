<?php

namespace App\Services;

use App\Models\ProductMarketPrice;
use Illuminate\Support\Facades\Http;

/**
 * 판매처(컴퓨존·피씨팩토리 등) 상품 페이지에서 판매가를 추출하는 크롤링 클라이언트.
 *
 * 공식 API가 없어 HTML 파싱에 의존한다. 페이지 구조 변경·차단에 대비해
 * 가격 추출을 다단계 폴백(사이트 범용)으로 방어적으로 작성했고, 실패 시 원본
 * 스니펫을 storage/logs/compuzone.log 에 남겨 서버에서 셀렉터를 보정할 수 있다.
 * 새 판매처 추가는 ALLOWED_HOSTS에 도메인만 등록하면 되고,
 * 사이트별 특수 대응(js-var 등)이 필요하면 이 클래스 안에서만 수정한다.
 */
class MarketPriceCrawler
{
    /** 허용 판매처 루트 도메인 → 라벨 (서브도메인 포함 매칭) */
    public const ALLOWED_HOSTS = [
        'compuzone.co.kr' => '컴퓨존',
        'pc-factory.co.kr' => '피씨팩토리',
    ];

    /** 판매처 키(DB vendor 값) → 루트 도메인 */
    public const VENDORS = [
        'compuzone' => 'compuzone.co.kr',
        'pcfactory' => 'pc-factory.co.kr',
    ];

    /** 판매처 키 → 한글 라벨 */
    public static function vendorLabel(string $vendorKey): string
    {
        return self::ALLOWED_HOSTS[self::VENDORS[$vendorKey] ?? ''] ?? $vendorKey;
    }

    /** URL이 해당 판매처 키의 도메인인지 검사 */
    public function urlMatchesVendor(string $url, string $vendorKey): bool
    {
        $root = self::VENDORS[$vendorKey] ?? null;

        return $root !== null && $this->vendorHost($url) === $root;
    }

    /** 가격 정합성 범위 — 이 밖의 숫자는 가격 후보에서 제외 */
    private const MIN_PRICE = 100;

    private const MAX_PRICE = 100_000_000;

    private const MAX_LOG_BYTES = 5 * 1024 * 1024;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    /** 허용 판매처의 루트 도메인 반환 (미허용 URL이면 null) */
    public function vendorHost(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            return null;
        }
        foreach (array_keys(self::ALLOWED_HOSTS) as $root) {
            if ($host === $root || str_ends_with($host, '.'.$root)) {
                return $root;
            }
        }

        return null;
    }

    /** 지원 판매처(컴퓨존·피씨팩토리) http/https URL만 허용 */
    public function isAllowedUrl(string $url): bool
    {
        return $this->vendorHost($url) !== null;
    }

    /** 허용 판매처 라벨 나열 (검증 메시지용) — "컴퓨존, 피씨팩토리" */
    public static function vendorLabels(): string
    {
        return implode(', ', array_values(self::ALLOWED_HOSTS));
    }

    /**
     * 상품 페이지 1건 조회 → 가격 추출. 절대 throw하지 않는다.
     *
     * @param  bool  $logProbe  true면 성공해도 진단 스니펫을 compuzone.log에 남긴다
     * @return array{price:?int, error:?string, http_status:?int, strategy:?string, candidates?:array<int, array{strategy:string, raw:string, price:?int, context:string, rejected:?string}>}
     */
    public function fetch(string $url, bool $logProbe = false): array
    {
        $vendorHost = $this->vendorHost($url);
        if ($vendorHost === null) {
            return ['price' => null, 'error' => '지원하는 판매처('.self::vendorLabels().') 주소만 조회할 수 있습니다.', 'http_status' => null, 'strategy' => null];
        }
        $vendor = self::ALLOWED_HOSTS[$vendorHost];

        // 연결 차단 대비 폴백 변형: 원본 → www 토글 → http (방화벽이 특정 호스트/포트만 막는 경우)
        $attempts = $this->urlVariants($url);
        $res = null;
        $lastError = null;
        $options = ['force_ip_resolve' => 'v4']; // IPv6 라우팅이 깨진 서버의 접속 타임아웃 방지
        if ($proxy = $this->proxyFor($vendorHost)) {
            $options['proxy'] = $proxy; // 해외 IP 차단 판매처 → 국내 경유 프록시
        }
        foreach ($attempts as $attemptUrl) {
            try {
                $res = Http::timeout(20)->connectTimeout(10)
                    ->retry(2, 700, throw: false)
                    ->withOptions($options)
                    ->withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Accept-Language' => 'ko,ko-KR;q=0.9',
                        'Referer' => "https://www.{$vendorHost}/",
                    ])
                    ->get($attemptUrl);
                break;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->log($attemptUrl, null, null, null, '연결 실패: '.$lastError);
            }
        }
        if ($res === null) {
            return ['price' => null, 'error' => "{$vendor} 연결 실패: ".mb_substr((string) $lastError, 0, 120), 'http_status' => null, 'strategy' => null];
        }

        $status = $res->status();
        if (! $res->ok()) {
            $this->log($url, $status, null, null, mb_substr($res->body(), 0, 400));
            $error = $status === 403
                ? "{$vendor}이(가) 요청을 차단했습니다 (HTTP 403) — 잠시 후 다시 시도해주세요"
                : "{$vendor} 응답 오류 (HTTP {$status})";

            return ['price' => null, 'error' => $error, 'http_status' => $status, 'strategy' => null];
        }

        // 인코딩 변환 전이므로 바이트 기준으로 자른다 (EUC-KR 바이트에 mb_substr 사용 금지)
        // 컴퓨존 상세 페이지는 커서 본품 가격 영역이 뒤쪽에 있을 수 있음 — 넉넉히 자른다
        $html = substr($res->body(), 0, 1500000);

        // 선언된 charset이 UTF-8이 아니면 변환 (컴퓨존은 EUC-KR)
        if (preg_match('/charset=["\']?([\w-]+)/i', $html, $cm) && strcasecmp($cm[1], 'utf-8') !== 0) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $cm[1]);
            if ($converted) {
                $html = $converted;
            }
        }

        $html = $this->normalizeHtml($html);

        $candidates = $this->priceCandidates($html);
        [$price, $strategy] = $this->pickPrice($candidates);

        if ($price === null || $logProbe) {
            $detail = "후보 목록:\n".$this->formatCandidates($candidates)."\n".$this->priceSnippets($html);
            $this->log($url, $status, $price, $strategy, $detail);
        }

        $result = $price === null
            ? ['price' => null, 'error' => '페이지에서 가격을 찾지 못했습니다 (구조 변경 가능성)', 'http_status' => $status, 'strategy' => null, 'candidates' => $candidates]
            : ['price' => $price, 'error' => null, 'http_status' => $status, 'strategy' => $strategy, 'candidates' => $candidates];

        if ($logProbe) {
            $result['html'] = $html; // 진단용 — probe 라우트에서 find 검색 후 제거
        }

        return $result;
    }

    /**
     * 판매처별 시세 행을 갱신해 저장. 성공 시에만 price를 덮어쓴다.
     */
    public function refresh(ProductMarketPrice $row): bool
    {
        if (! $row->url) {
            return false;
        }

        $result = $this->fetch($row->url);
        $row->checked_at = now();

        if ($result['price'] !== null) {
            $row->price = $result['price'];
            $row->error = null;
            $row->save();

            return true;
        }

        $row->error = mb_substr((string) $result['error'], 0, 200);
        $row->save();

        return false;
    }

    /**
     * 컴퓨존 안티 크롤링 대응 정규화.
     * 실제 판매가는 숫자를 HTML 엔티티(&#57; 등)로 숨기고, display:none 요소에
     * 가짜 가격(미끼)을 넣어두는 구조 — 미끼를 제거하고 엔티티 숫자를 복원한다.
     */
    private function normalizeHtml(string $html): string
    {
        // 1) display:none 단순 미끼 요소 제거 (내부에 태그가 없는 것만 — 구조 파괴 방지)
        $html = preg_replace('/<(div|span)[^>]*display\s*:\s*none[^>]*>[^<]*<\/\1>/i', '', $html) ?? $html;

        // 2) 엔티티로 숨긴 숫자 복원 — 숫자·콤마·마침표만 (태그 구조를 바꿀 수 있는 문자는 유지)
        return preg_replace_callback('/&#(?:x([0-9a-fA-F]+)|([0-9]+));/', function (array $m): string {
            $code = $m[1] !== '' ? (int) hexdec($m[1]) : (int) $m[2];

            return (($code >= 48 && $code <= 57) || $code === 44 || $code === 46) ? chr($code) : $m[0];
        }, $html) ?? $html;
    }

    /**
     * 판매처별 경유 프록시 결정 — MARKET_PRICE_PROXY 설정 시,
     * MARKET_PRICE_PROXY_VENDORS(쉼표 구분, 빈 값=전체)에 해당하는 판매처만 적용.
     */
    public function proxyFor(string $vendorHost): ?string
    {
        $proxy = (string) config('services.market_price.proxy');
        if ($proxy === '') {
            return null;
        }
        $vendorKey = array_search($vendorHost, self::VENDORS, true);
        $only = array_filter(array_map('trim', explode(',', (string) config('services.market_price.proxy_vendors'))));

        return (! $only || in_array($vendorKey, $only, true)) ? $proxy : null;
    }

    /**
     * 연결 폴백용 URL 변형 — 원본, www↔apex 토글, http 다운그레이드 순.
     *
     * @return array<int, string>
     */
    private function urlVariants(string $url): array
    {
        $variants = [$url];
        $host = strtolower(parse_url($url)['host'] ?? '');
        if ($host !== '') {
            $toggled = str_starts_with($host, 'www.')
                ? substr($host, 4)
                : 'www.'.$host;
            $variants[] = preg_replace('/^(https?:\/\/)'.preg_quote($host, '/').'/i', '$1'.$toggled, $url) ?? $url;
        }
        if (str_starts_with($url, 'https://')) {
            $variants[] = 'http://'.substr($url, 8);
        }

        return array_values(array_unique($variants));
    }

    /**
     * 판매처 연결 진단 — DNS 조회 + www/apex × 443/80 TCP 접속 테스트.
     * probe 라우트의 &diag=1 에서 사용 (해외 IP 차단 여부 판별용).
     *
     * @return array<string, mixed>
     */
    public function diagnoseConnectivity(string $url): array
    {
        $host = strtolower(parse_url($url)['host'] ?? '');
        if ($host === '') {
            return ['error' => 'URL에서 호스트를 추출할 수 없습니다.'];
        }
        $apex = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $result = [];
        foreach (array_unique([$apex, 'www.'.$apex]) as $h) {
            $records = @dns_get_record($h, DNS_A) ?: [];
            $ips = array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? null, $records)));
            $entry = ['dns_a' => $ips ?: '(조회 실패)'];
            foreach ([443, 80] as $port) {
                $start = microtime(true);
                $sock = @fsockopen($h, $port, $errno, $errstr, 5);
                $ms = (int) ((microtime(true) - $start) * 1000);
                if ($sock) {
                    fclose($sock);
                    $entry["tcp_{$port}"] = "연결 성공 ({$ms}ms)";
                } else {
                    $entry["tcp_{$port}"] = "실패 ({$ms}ms): ".($errstr ?: "errno {$errno}");
                }
            }
            $result[$h] = $entry;
        }

        return $result;
    }

    /** 채택된 첫 후보의 [가격, 전략] — 없으면 [null, null] */
    private function pickPrice(array $candidates): array
    {
        foreach ($candidates as $c) {
            if ($c['rejected'] === null) {
                return [$c['price'], $c['strategy']];
            }
        }

        return [null, null];
    }

    /**
     * 다단계 가격 추출 — 모든 후보를 우선순위 순으로 나열하고 제외 사유를 남긴다.
     * 적립금·배송비·쿠폰 같은 부가 금액이 판매가로 오인되는 것을 부정 문맥 필터로 방어.
     *
     * @return array<int, array{strategy:string, raw:string, price:?int, context:string, rejected:?string}>
     */
    public function priceCandidates(string $html): array
    {
        $candidates = [];
        $add = function (string $strategy, string $raw, string $context) use (&$candidates): void {
            $price = $this->sanitizePrice($raw);
            $rejected = null;
            if ($price === null) {
                $rejected = '정합성 범위 밖 (100원~1억)';
            } elseif (($kw = $this->negativeKeyword($context)) !== null) {
                $rejected = "부가 금액 문맥 ({$kw})";
            }
            $candidates[] = [
                'strategy' => $strategy,
                'raw' => trim($raw),
                'price' => $price,
                'context' => $this->cleanContext($context),
                'rejected' => $rejected,
            ];
        };

        // 1) meta og:price / product:price / itemprop=price (신뢰 소스 — 문맥 필터 없이 정합성만)
        foreach ([
            '/<meta[^>]+(?:property|name)=["\'](?:og|product):price(?::amount)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:og|product):price(?::amount)?["\']/i',
            '/<[^>]+itemprop=["\']price["\'][^>]*content=["\']([^"\']+)["\']/i',
        ] as $pattern) {
            if (preg_match_all($pattern, $html, $m)) {
                foreach ($m[1] as $raw) {
                    $candidates[] = [
                        'strategy' => 'meta', 'raw' => trim($raw),
                        'price' => $p = $this->sanitizePrice($raw),
                        'context' => 'meta 태그',
                        'rejected' => $p === null ? '정합성 범위 밖 (100원~1억)' : null,
                    ];
                }
            }
        }

        // 2) JSON-LD "price" (신뢰 소스)
        if (preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/si', $html, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match('/"price"\s*:\s*"?([\d,.]+)"?/', $block, $m)) {
                    $candidates[] = [
                        'strategy' => 'json-ld', 'raw' => trim($m[1]),
                        'price' => $p = $this->sanitizePrice($m[1]),
                        'context' => 'JSON-LD',
                        'rejected' => $p === null ? '정합성 범위 밖 (100원~1억)' : null,
                    ];
                }
            }
        }

        // 3) 페이지 JS의 본품 가격 변수 — 컴퓨존은 표시 가격을 엔티티로 숨기지만
        //    JS에는 평문 가격이 들어있음 (produc_price / regularPrice / All_Total_Price)
        foreach ([
            'produc_price' => '/var\s+produc_price\s*=\s*["\']([\d,]+)["\']/',
            'regularPrice' => '/regularPrice\s*:\s*["\']?([\d,]+)/',
            'All_Total_Price' => '/All_Total_Price\s*=\s*([\d,]+)\s*;/',
        ] as $name => $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $candidates[] = [
                    'strategy' => 'js-var', 'raw' => trim($m[1]),
                    'price' => $p = $this->sanitizePrice($m[1]),
                    'context' => "JS 변수 {$name}",
                    'rejected' => $p === null ? '정합성 범위 밖 (100원~1억)' : null,
                ];
            }
        }

        // 4) id/class에 prc·price가 들어간 요소 안의 숫자 — 속성명 + 주변 문맥으로 부가 금액 제외
        //    (적립금 숫자는 라벨(적립)이 형제 요소에 있는 경우가 많아 요소 앞 ±200바이트까지 본다)
        if (preg_match_all('/<[^>]+(?:id|class)=["\']([^"\']*(?:prc|price)[^"\']*)["\'][^>]*>([^<]{0,80})</i', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $set) {
                if (! preg_match('/([\d,]{4,})/', $set[2][0], $pm)) {
                    continue;
                }
                $around = substr($html, max(0, $set[0][1] - 200), strlen($set[0][0]) + 300);
                $rejected = null;
                if (preg_match('/point|mileage|save|ship|deliver|coupon/i', $set[1][0], $am)) {
                    $rejected = "부가 금액 속성 ({$am[0]})";
                } elseif (($p = $this->sanitizePrice($pm[1])) === null) {
                    $rejected = '정합성 범위 밖 (100원~1억)';
                } elseif (($kw = $this->negativeKeyword($around, [
                    '적립', '마일리지', '포인트', '배송비',
                    // 추가구성/연관상품 위젯의 다른 상품 가격 (pdtl_sel = 컴퓨존 선택상품 위젯)
                    'pdtl_sel', '리스트 추가', '추가구성', '선택한 상품', '함께 구매', '관련상품', '연관상품',
                ])) !== null) {
                    // 넓은 창(±200B)이라 확실한 부가 금액/타상품 키워드만 본다
                    $rejected = "부가 금액/타상품 문맥 ({$kw})";
                }
                $candidates[] = [
                    'strategy' => 'prc-element', 'raw' => $pm[1],
                    'price' => $this->sanitizePrice($pm[1]),
                    'context' => $this->cleanContext($set[1][0].' | '.$around),
                    'rejected' => $rejected,
                ];
            }
        }

        // 5) 가격 키워드 근방 "1,234,000원" 패턴 (숫자와 원 사이 닫는 태그 허용,
        //    키워드와 숫자 사이 최대 600자 — 중첩 마크업이 긴 가격 테이블 대응)
        if (preg_match_all('/(?:판매가|할인가|카드가|가격)[^가-힣]{0,600}?([\d,]{4,})(?:\s*<[^>]*>)*\s*원/u', $html, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($m as $set) {
                // 앞 30B(적립가격 같은 합성어) + 뒤 80B(원 적립 등) 문맥으로 부가 금액 판별
                $context = substr($html, max(0, $set[1][1] - 30), strlen($set[1][0]) + 110);
                $add('keyword', $set[1][0], $context);
            }
        }

        return array_slice($candidates, 0, 20);
    }

    /**
     * 부가 금액을 나타내는 문맥 키워드 — 걸리면 후보 제외.
     *
     * @param  array<int, string>|null  $keywords  검사할 키워드 (기본: 전체 목록)
     */
    private function negativeKeyword(string $context, ?array $keywords = null): ?string
    {
        $keywords ??= ['적립', '포인트', '마일리지', '배송비', '쿠폰', '할부', '렌탈', '중고', '혜택'];
        foreach ($keywords as $kw) {
            if (str_contains($context, $kw)) {
                return $kw;
            }
        }

        return null;
    }

    /** 로그/응답용 문맥 정리 — 공백 압축 + 바이트 절단으로 깨진 문자 제거 */
    private function cleanContext(string $context): string
    {
        $context = preg_replace('/\s+/', ' ', $context) ?? $context;

        return trim(mb_scrub($context));
    }

    /** 숫자 문자열 → 정합성 검사(100원~1억) 통과한 int, 실패 시 null */
    private function sanitizePrice(string $raw): ?int
    {
        $num = (int) str_replace([',', '.'], '', trim($raw));

        return ($num >= self::MIN_PRICE && $num <= self::MAX_PRICE) ? $num : null;
    }

    /** 후보 목록을 로그용 텍스트로 — 어떤 값이 왜 채택/제외됐는지 한눈에 */
    private function formatCandidates(array $candidates): string
    {
        if (! $candidates) {
            return '(후보 없음)';
        }

        return implode("\n", array_map(
            fn ($c, $i) => sprintf(
                '#%d [%s] %s → %s | 문맥: %s',
                $i + 1, $c['strategy'], $c['raw'],
                $c['rejected'] !== null ? '제외: '.$c['rejected'] : '채택 가능',
                mb_substr($c['context'], 0, 120)
            ),
            $candidates,
            array_keys($candidates)
        ));
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
