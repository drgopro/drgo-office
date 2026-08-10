<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 채널톡 Open API 클라이언트 — 팀챗(그룹) 메시지 발송.
 *
 * 고객 채팅이 아니라 팀 멤버들이 보는 사내 그룹 채팅방으로 보낸다.
 * 그룹은 이름(@이름 주소 방식) 또는 그룹 ID 어느 쪽이든 설정 가능.
 * 송수신은 storage/logs/channeltalk.log 에 기록해 서버에서 진단 가능.
 */
class ChannelTalkClient
{
    private const API_BASE = 'https://api.channel.io/open/v5';

    private const MAX_LOG_BYTES = 5 * 1024 * 1024;

    public function isConfigured(): bool
    {
        return (string) config('services.channeltalk.access_key') !== ''
            && (string) config('services.channeltalk.access_secret') !== ''
            && (string) config('services.channeltalk.group') !== '';
    }

    /**
     * 팀챗 그룹으로 메시지 발송.
     *
     * @return array{ok:bool, error?:string}
     */
    public function sendGroupMessage(string $text): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => '채널톡 연동 정보가 설정되지 않았습니다 (.env CHANNELTALK_ACCESS_KEY/SECRET/GROUP).'];
        }

        $group = (string) config('services.channeltalk.group');
        // 숫자면 그룹 ID, 아니면 그룹 이름 — @ 접두는 인코딩하면 안 되고 이름만 인코딩
        $groupPath = ctype_digit($group) ? rawurlencode($group) : '@'.rawurlencode(ltrim($group, '@'));
        $url = self::API_BASE.'/groups/'.$groupPath.'/messages';

        try {
            $res = Http::timeout(10)->connectTimeout(5)
                ->withHeaders([
                    'x-access-key' => config('services.channeltalk.access_key'),
                    'x-access-secret' => config('services.channeltalk.access_secret'),
                ])
                ->post($url.'?botName='.rawurlencode((string) config('services.channeltalk.bot_name')), [
                    'blocks' => [
                        ['type' => 'text', 'value' => $text],
                    ],
                ]);
        } catch (\Throwable $e) {
            $this->log('전송 실패', $text, '통신 오류: '.$e->getMessage());

            return ['ok' => false, 'error' => '채널톡 통신 실패: '.mb_substr($e->getMessage(), 0, 120)];
        }

        $this->log(
            $res->successful() ? '전송 성공' : '전송 실패 HTTP '.$res->status(),
            $text,
            'url='.$url."\n".mb_substr($res->body(), 0, 500)
        );

        if (! $res->successful()) {
            return ['ok' => false, 'error' => '채널톡 응답 오류 (HTTP '.$res->status().'): '.mb_substr($res->body(), 0, 200)];
        }

        return ['ok' => true];
    }

    /**
     * 채널톡 매니저 목록 — 이메일(소문자) => [id, name] 맵 (10분 캐시).
     *
     * @return array<string, array{id:string, name:string}>
     */
    public function managers(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('channeltalk.managers', 600, function () {
            try {
                $res = Http::timeout(10)->connectTimeout(5)
                    ->withHeaders([
                        'x-access-key' => config('services.channeltalk.access_key'),
                        'x-access-secret' => config('services.channeltalk.access_secret'),
                    ])
                    ->get(self::API_BASE.'/managers', ['limit' => 500]);

                if (! $res->successful()) {
                    $this->log('매니저 조회 실패 HTTP '.$res->status(), '', mb_substr($res->body(), 0, 300));

                    return [];
                }

                $map = [];
                foreach ($res->json('managers') ?? [] as $m) {
                    if (! empty($m['email'])) {
                        $map[strtolower($m['email'])] = ['id' => (string) $m['id'], 'name' => (string) ($m['name'] ?? '')];
                    }
                }

                return $map;
            } catch (\Throwable $e) {
                $this->log('매니저 조회 실패', '', $e->getMessage());

                return [];
            }
        });
    }

    /**
     * 이메일로 매니저를 찾아 멘션 태그 생성 — 못 찾으면 이름만 일반 텍스트로.
     * 멘션된 매니저는 채널톡이 개인 알림(푸시)을 보낸다.
     */
    public function managerMentionByEmail(?string $email, string $fallbackName): string
    {
        $manager = $email ? ($this->managers()[strtolower($email)] ?? null) : null;
        if (! $manager) {
            return $fallbackName;
        }

        return '<link type="manager" value="'.$manager['id'].'">'.($manager['name'] !== '' ? $manager['name'] : $fallbackName).'</link>';
    }

    /** channeltalk.log 기록 (액세스 키는 기록하지 않음) */
    private function log(string $tag, string $text, string $detail): void
    {
        try {
            $path = storage_path('logs/channeltalk.log');
            if (file_exists($path) && filesize($path) > self::MAX_LOG_BYTES) {
                @unlink($path);
            }
            @file_put_contents($path, sprintf(
                "[%s] %s\nmsg=%s\nres=%s\n\n",
                now()->format('Y-m-d H:i:s'),
                $tag,
                mb_substr($text, 0, 300),
                $detail
            ), FILE_APPEND);
        } catch (\Throwable) {
            // 진단 로그 실패는 무시
        }
    }
}
