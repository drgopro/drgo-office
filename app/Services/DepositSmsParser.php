<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * 은행 입금 알림 SMS 파서 (국민은행 위주, 포맷 변형 방어적 대응).
 *
 * 예시 포맷:
 *  - "[KB]08/05 14:32 902002**333 홍길동 입금 500,000 잔액 1,234,567"
 *  - "KB국민은행 입금 500,000원 08/05 14:32 홍길동"
 * 파싱에 실패한 필드는 null로 두고 원문은 호출부에서 항상 보관한다.
 */
class DepositSmsParser
{
    /** 이름 후보에서 제외할 키워드 */
    private const STOP_WORDS = ['KB', 'kb', '국민', '국민은행', '스타뱅킹', '입금', '출금', '잔액', '원', '전자금융'];

    /**
     * @return array{received_at:Carbon, amount:?int, depositor_name:?string, balance_after:?int}
     */
    public function parse(string $text): array
    {
        return [
            'received_at' => $this->parseDateTime($text) ?? now(),
            'amount' => $this->parseAmount($text),
            'depositor_name' => $this->parseName($text),
            'balance_after' => $this->parseBalance($text),
        ];
    }

    private function parseDateTime(string $text): ?Carbon
    {
        if (! preg_match('/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})/', $text, $m)) {
            return null;
        }
        $now = now();
        $dt = Carbon::create($now->year, (int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]);

        // 연말/연초 경계: 미래로 하루 이상 앞서면 작년 SMS로 간주
        if ($dt->gt($now->copy()->addDay())) {
            $dt->subYear();
        }

        return $dt;
    }

    private function parseAmount(string $text): ?int
    {
        // '입금' 키워드 뒤/앞에 붙은 금액 우선
        foreach ([
            '/입금\s*:?\s*([\d,]+)\s*원?/u',
            '/([\d,]+)\s*원?\s*입금/u',
        ] as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $amount = (int) str_replace(',', '', $m[1]);
                if ($amount > 0) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function parseBalance(string $text): ?int
    {
        if (preg_match('/잔액\s*:?\s*([\d,]+)/u', $text, $m)) {
            $balance = (int) str_replace(',', '', $m[1]);

            return $balance > 0 ? $balance : null;
        }

        return null;
    }

    private function parseName(string $text): ?string
    {
        // 대괄호 태그·계좌 마스킹·금액·날짜/시각 제거 후 남는 한글/영문 토큰에서 추출
        $clean = preg_replace([
            '/\[[^\]]*\]/u',                 // [KB], [Web발신] 등
            '/[\d*]{6,}[\d*\-]*/u',          // 계좌번호 마스킹 (902002**333)
            '/\d{1,2}\/\d{1,2}\s+\d{1,2}:\d{2}/u', // 날짜 시각
            '/[\d,]+\s*원?/u',               // 금액
        ], ' ', $text) ?? $text;

        foreach (preg_split('/\s+/u', trim($clean)) ?: [] as $token) {
            $token = trim($token, "()[]{}.,:;-_*\u{00A0}");
            if ($token === '' || mb_strlen($token) < 2 || mb_strlen($token) > 20) {
                continue;
            }
            if (in_array($token, self::STOP_WORDS, true)) {
                continue;
            }
            // 은행/서비스명 계열 토큰 제외 (KB국민은행, 스타뱅킹 등)
            if (str_contains($token, '은행') || str_contains($token, '뱅킹') || str_starts_with(strtoupper($token), 'KB')) {
                continue;
            }
            // 한글 또는 영문으로만 구성된 토큰만 이름 후보로 인정
            if (preg_match('/^[가-힣a-zA-Z()]+$/u', $token)) {
                return $token;
            }
        }

        return null;
    }
}
