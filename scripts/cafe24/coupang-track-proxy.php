<?php

/**
 * 카페24(drgo.pro)용 쿠팡 배송조회 중계 — 쿠팡이 해외 서버 IP를 차단하므로
 * 한국 IP인 카페24가 coupangls.com 운송장 모달을 대신 조회해 돌려준다.
 *
 * 동작: https://drgo.pro/coupang-track-proxy.php?key={KEY}&no={송장번호}
 *       → https://www.coupangls.com/web/modal/invoice/{송장번호} 응답을 그대로 반환.
 *
 * 설치 (카페24 FTP/파일관리자):
 *   1) 이 파일을 웹 루트에 coupang-track-proxy.php 로 업로드
 *   2) 새 서버 delivery-tracker의 kr.coupangls 크롤러가 이 주소로 조회하도록 패치
 *      (sed 한 줄 — 오피스 저장소 작업 로그 참고) 후 pm2 restart delivery-tracker
 *
 * KEY는 무단 사용 방지용 — 변경 시 추적기 쪽 URL도 함께 변경할 것.
 */
$KEY = 'dgcp_7k2m9x4qv8w3';

$no = $_GET['no'] ?? '';
if (($_GET['key'] ?? '') !== $KEY || ! preg_match('/^[0-9A-Za-z\-]{5,40}$/', $no)) {
    http_response_code(403);
    exit('denied');
}

$ch = curl_init('https://www.coupangls.com/web/modal/invoice/'.rawurlencode($no));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    // 쿠팡이 비정상 UA를 거를 수 있어 일반 브라우저 UA 사용
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => ['Accept-Language: ko-KR,ko;q=0.9', 'Accept: text/html,application/xhtml+xml'],
]);

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 502;
curl_close($ch);

if ($body === false) {
    http_response_code(502);
    exit('relay error');
}

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
echo $body;
