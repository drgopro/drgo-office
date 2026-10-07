<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * SSL 인증서 만료 검사 — 도메인에 TLS로 접속해 서버 인증서의 만료일을 읽는다.
 * 만료 임박 감시가 목적이므로 체인이 깨져 있어도 읽히도록 검증은 끈다.
 */
class SslCertExpiry
{
    /**
     * @return array{expires_at: CarbonImmutable, days_left: int}|null 접속/파싱 실패 시 null
     */
    public function inspect(string $domain): ?array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $domain,
        ]]);

        $client = @stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
        if (! $client) {
            return null;
        }
        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $cert ? openssl_x509_parse($cert) : null;
        $expiresAt = (int) ($parsed['validTo_time_t'] ?? 0);
        if ($expiresAt <= 0) {
            return null;
        }

        $expires = CarbonImmutable::createFromTimestamp($expiresAt)->setTimezone(config('app.timezone'));

        return [
            'expires_at' => $expires,
            'days_left' => (int) now()->startOfDay()->diffInDays($expires->startOfDay(), false),
        ];
    }
}
