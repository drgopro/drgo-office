<?php

namespace App\Support;

/**
 * 표기 정규화 — 마케팅 사양서 코드값 기준.
 * 알 수 없는 값은 null 반환 (호출부가 원본 유지) — 정보 파괴 방지.
 */
class Normalize
{
    /** @var array<string, array<int, string>> 정식 표기 → 변형 표기들 (소문자 비교) */
    private const PLATFORM_VARIANTS = [
        'SOOP' => ['soop', '숲', '아프리카', '아프리카티비', '아프리카tv', 'afreeca', 'afreecatv'],
        '치지직' => ['치지직', 'chzzk'],
        '유튜브' => ['유튜브', '유투브', 'youtube'],
        '틱톡' => ['틱톡', 'tiktok'],
        '팬더' => ['팬더', '팬더티비', '팬더tv', 'panda', 'pandatv'],
        '플렉스' => ['플렉스', 'flex', 'flextv'],
        '팝콘' => ['팝콘', '팝콘티비', 'popkon', 'popkontv'],
        '동시' => ['동시', '동시송출', '멀티'],
    ];

    /** 플랫폼 단일 표기 정규화 — 이미 정식 표기면 그대로, 변형이면 정식 표기, 모르면 null */
    public static function platform(string $raw): ?string
    {
        $v = mb_strtolower(trim($raw));
        if ($v === '') {
            return null;
        }
        foreach (self::PLATFORM_VARIANTS as $canonical => $variants) {
            if (in_array($v, $variants, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /** 경력 정규화 — '신규'는 '처음'으로 통합 (사양서), 그 외 정식 3종은 그대로, 모르면 null */
    public static function career(string $raw): ?string
    {
        $v = trim($raw);

        return match ($v) {
            '신규', '처음' => '처음',
            '초보' => '초보',
            '경력' => '경력',
            default => null,
        };
    }
}
