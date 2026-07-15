<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 외부 링크 오픈그래프 미리보기 — 본문 URL의 제목/설명/썸네일 카드용.
 * SSRF 방지를 위해 내부망 주소는 차단하고, 결과는 1일 캐시한다.
 */
class LinkPreviewController extends Controller
{
    public function show(Request $request)
    {
        $validated = $request->validate(['url' => 'required|url|max:2000']);
        $url = $validated['url'];

        if (! $this->isSafeUrl($url)) {
            return response()->json(['error' => '허용되지 않는 주소입니다.'], 422);
        }

        $preview = Cache::remember('link-preview:'.md5($url), now()->addDay(), fn () => $this->fetchPreview($url));

        if (! $preview) {
            return response()->json(['error' => '미리보기를 가져오지 못했습니다.'], 422);
        }

        return response()->json($preview);
    }

    /** http/https + 공인 주소만 허용 (내부망·루프백 차단) */
    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            return false;
        }
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test') || str_ends_with($host, '.internal')) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }

    /** @return array{url:string, host:string, title:string, description:string, image:string}|null */
    private function fetchPreview(string $url): ?array
    {
        try {
            $res = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DrgoOfficeBot/1.0; link preview)'])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $res->ok() || ! str_contains((string) $res->header('Content-Type'), 'text/html')) {
            return null;
        }

        $html = mb_substr($res->body(), 0, 300000);

        // 선언된 charset이 UTF-8이 아니면 변환 (EUC-KR 사이트 등)
        if (preg_match('/charset=["\']?([\w-]+)/i', $html, $cm) && strcasecmp($cm[1], 'utf-8') !== 0) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $cm[1]);
            if ($converted) {
                $html = $converted;
            }
        }

        $meta = function (string $property) use ($html): string {
            foreach ([
                '/<meta[^>]+(?:property|name)=["\']'.preg_quote($property, '/').'["\'][^>]+content=["\']([^"\']*)["\']/i',
                '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']'.preg_quote($property, '/').'["\']/i',
            ] as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
                }
            }

            return '';
        };

        $title = $meta('og:title');
        if ($title === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
            $title = html_entity_decode(trim($tm[1]), ENT_QUOTES | ENT_HTML5);
        }
        if ($title === '') {
            return null;
        }

        $image = $meta('og:image');
        if ($image !== '' && ! str_starts_with($image, 'http')) {
            // 상대 경로 이미지 → 절대 경로로 보정
            $parts = parse_url($url);
            $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
            $image = str_starts_with($image, '/') ? $base.$image : $base.'/'.$image;
        }

        return [
            'url' => $url,
            'host' => parse_url($url, PHP_URL_HOST) ?? '',
            'title' => mb_substr($title, 0, 200),
            'description' => mb_substr($meta('og:description') ?: $meta('description'), 0, 300),
            'image' => $image,
        ];
    }
}
