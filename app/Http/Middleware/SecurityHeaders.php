<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 내부 오피스 보안 헤더 — 모든 웹 응답에 적용.
 *
 * - X-Robots-Tag: 검색엔진 색인 금지 (robots.txt 차단과 이중 방어).
 *   고객 정보·상담 내용이 담긴 내부 시스템이므로 공개 견적서 포함 전 페이지 비색인.
 * - Referrer-Policy no-referrer: 공개 견적서의 난수 토큰 URL이 외부 링크 클릭 시
 *   Referer 헤더로 유출되지 않도록.
 * - 나머지: MIME 스니핑/클릭재킹 방지, HTTPS 고정(HSTS).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        // camera=(self): 장비 위치 QR 스캔용 — camera=()로 전면 차단하면 권한 프롬프트
        // 자체가 뜨지 않는다 (동일 출처 + 탭 iframe allow="camera" 위임으로만 사용)
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
