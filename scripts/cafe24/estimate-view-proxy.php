<?php

/**
 * 카페24(drgo.pro)용 견적서 프록시 — 의뢰자 공개 견적서를 drgo.pro 주소로 서빙.
 *
 * 동작: https://drgo.pro/estimate-view/{토큰} 요청을 받아
 *       https://office.drgo.pro/estimate-view/{토큰} 을 서버에서 대신 조회해 그대로 응답.
 *       (주소창은 drgo.pro로 유지, office 도메인은 노출되지 않음)
 *
 * 설치 (카페24 FTP):
 *   1) 이 파일을 웹 루트에 estimate-view-proxy.php 로 업로드
 *   2) 웹 루트의 .htaccess 에 아래 두 줄 추가 (없으면 새로 생성):
 *        RewriteEngine On
 *        RewriteRule ^estimate-view/([a-f0-9]{64})$ /estimate-view-proxy.php?t=$1 [L,QSA]
 *   3) 오피스 Forge env 에 ESTIMATE_PUBLIC_BASE_URL=https://drgo.pro 추가 후 Deploy
 *
 * 페이앱 결제 후 returnurl POST 리다이렉트도 이 프록시가 그대로 중계한다 (GET/POST 모두 지원).
 */
$ORIGIN = 'https://office.drgo.pro';

$token = $_GET['t'] ?? '';
if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><p style="font-family:sans-serif; padding:40px; text-align:center;">유효하지 않은 견적서 주소입니다.</p>';
    exit;
}

$url = $ORIGIN.'/estimate-view/'.$token;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'drgo-pro-estimate-proxy/1.0',
]);

// 페이앱 returnurl 이 POST 로 리다이렉트하므로 본문까지 그대로 전달
if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    if (! empty($_SERVER['CONTENT_TYPE'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: '.$_SERVER['CONTENT_TYPE']]);
    }
}

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 502;
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html; charset=utf-8';
$error = curl_errno($ch);
curl_close($ch);

if ($error || $body === false) {
    http_response_code(502);
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><p style="font-family:sans-serif; padding:40px; text-align:center;">견적서를 불러오지 못했습니다. 잠시 후 다시 시도해주세요.</p>';
    exit;
}

http_response_code($status);
header('Content-Type: '.$contentType);
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
echo $body;
