<?php

namespace App\Services;

use App\Jobs\SendMattermostMessage;
use App\Models\Assignee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Mattermost 수신 웹훅 발송 — 채널톡 대체 알림 경로.
 * 웹훅 주소는 그 자체가 채널 쓰기 권한이라 코드·저장소에 두지 않고 관리자 설정(Setting)에 저장한다.
 */
class MattermostClient
{
    /** 캘린더 알림 채널 웹훅 (관리 > 캘린더 > 알림 설정) */
    public const CALENDAR_WEBHOOK = 'mattermost_calendar_webhook';

    public static function webhook(string $key = self::CALENDAR_WEBHOOK): string
    {
        return trim((string) Setting::get($key, ''));
    }

    /** 웹훅 주소 형식 확인 — https 이고 Mattermost 수신 웹훅 경로(/hooks/{id})여야 한다 */
    public static function isValidWebhook(string $url): bool
    {
        return (bool) preg_match('#^https://[^/\s]+/hooks/[A-Za-z0-9]+/?$#', $url);
    }

    /** Mattermost 아이디 규칙 — 영문 소문자·숫자·. _ - (앞의 @는 저장 시 떼어낸다) */
    public const USERNAME_RULE = 'nullable|string|max:64|regex:/^@?[A-Za-z0-9._-]+$/';

    public static function normalizeUsername(?string $username): ?string
    {
        $value = strtolower(ltrim(trim((string) $username), '@'));

        return $value !== '' ? $value : null;
    }

    /** 사용자 표기 — Mattermost 아이디가 있으면 @아이디(개인 알림), 없으면 이름 */
    public static function mention(?User $user, string $fallbackName): string
    {
        $username = $user?->mattermost_username;

        return $username ? '@'.$username : $fallbackName;
    }

    /**
     * 캘린더 담당자(assignee) 목록 표기 — 연결된 오피스 계정의 Mattermost 아이디로 태그.
     *
     * @param  iterable<Assignee>  $assignees
     */
    public static function assigneeMentions(iterable $assignees): string
    {
        return collect($assignees)
            ->map(fn (Assignee $a) => self::mention($a->user, (string) $a->name))
            ->filter()->unique()->implode(', ') ?: '—';
    }

    /** 캘린더 알림 — 웹훅이 설정돼 있을 때만 큐로 발송 (요청이 외부 응답을 기다리지 않도록) */
    public static function calendar(string $text): void
    {
        $url = self::webhook();
        if (self::isValidWebhook($url)) {
            SendMattermostMessage::dispatch($url, $text, 'Mattermost 캘린더 알림');
        }
    }

    /**
     * 즉시 발송.
     *
     * @return array{ok: bool, error?: string}
     */
    public function send(string $url, string $text): array
    {
        if (! self::isValidWebhook($url)) {
            return ['ok' => false, 'error' => 'Mattermost 웹훅 주소 형식이 올바르지 않습니다 (https://.../hooks/...).'];
        }

        try {
            $res = Http::timeout(10)->connectTimeout(5)->asJson()
                ->post($url, ['text' => $text, 'username' => '오피스 알림']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => '통신 실패: '.mb_substr($e->getMessage(), 0, 160)];
        }

        return $res->successful()
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'HTTP '.$res->status().': '.mb_substr($res->body(), 0, 160)];
    }
}
