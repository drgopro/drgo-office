<?php

namespace App\Services;

use App\Jobs\SendChannelTalkGroupMessage;
use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 페이앱 결제완료 채널톡 알림 — 관리 > 설정 > 결제 알림에서 지정한
 * 결제완료 톡방(그룹)으로 발송하고 결제완료 담당자를 멘션한다.
 * 알림 실패가 결제 처리(payappFeedback 응답)를 막지 않도록 항상 예외를 삼킨다.
 */
class PaymentCompleteAlert
{
    /**
     * @param  array<string, mixed>  $payload  페이앱 feedback 원본 (pay_type 등 표기용)
     * @param  string  $methodLabel  결제수단 표기 — 페이앱 외 경로(계좌이체 매칭 등)에서 교체
     */
    public static function estimatePaid(Estimate $estimate, array $payload = [], string $methodLabel = '페이앱'): void
    {
        try {
            $group = trim((string) Setting::get('payment_alert_group', ''));
            if ($group === '') {
                return; // 톡방 미설정 — 기능 꺼짐
            }

            // 같은 견적서의 중복 통보(페이앱 재시도가 거의 동시에 도착하는 경우) 방어 —
            // Cache::add는 원자적이라 1시간 내 두 번째 호출은 발송하지 않는다
            $dedupeKey = "payment.alert.sent.{$estimate->id}";
            if (! Cache::add($dedupeKey, 1, 3600)) {
                return;
            }

            $managerIds = json_decode((string) Setting::get('payment_alert_managers', '[]'), true);
            $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
                ->get()
                ->map(fn (User $u) => ChannelTalkClient::deferredMention($u->email, $u->display_name))
                ->implode(' ');

            $client = $estimate->client_nickname ?: $estimate->client?->nickname;
            $payType = self::payTypeLabel((string) ($payload['pay_type'] ?? ''));

            $lines = [
                '[결제완료] 견적서 #'.$estimate->display_no.($client ? ' · '.$client : ''), // 화면 표시 번호 (DB id 아님)
                '금액 '.number_format((int) $estimate->total_amount).'원 · '.$methodLabel.($payType !== '' ? " ({$payType})" : ''),
                url("/estimates/{$estimate->id}/edit"),
            ];
            if ($mentions !== '') {
                $lines[] = $mentions;
            }

            // 외부 전송(멘션 매칭 포함)은 큐로 — 페이앱 feedback 응답이 채널톡 응답을 기다리지 않도록
            try {
                SendChannelTalkGroupMessage::dispatch(implode("\n", $lines), $group, "결제완료 채널톡 알림 (견적서 #{$estimate->id})", $dedupeKey);
            } catch (\Throwable $e) {
                Cache::forget($dedupeKey); // 큐 등록 실패 — 페이앱 재통보 때 다시 시도되도록 중복 잠금 해제
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::warning('결제완료 채널톡 알림 오류: '.$e->getMessage()." (견적서 #{$estimate->id})");
        }
    }

    /**
     * 페이앱 pay_type 표기 — 공식 확인된 코드만 한글로, 모르는 숫자 코드는 표기 생략
     * ("페이앱 (23)"처럼 의미 없는 숫자가 노출되지 않게). 문자열 값은 그대로 표시.
     */
    private static function payTypeLabel(string $raw): string
    {
        $raw = trim($raw);
        $known = ['1' => '신용카드', '2' => '휴대전화'];
        if (isset($known[$raw])) {
            return $known[$raw];
        }

        return ctype_digit($raw) ? '' : $raw;
    }
}
