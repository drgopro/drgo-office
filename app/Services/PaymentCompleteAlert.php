<?php

namespace App\Services;

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
    /** @param array<string, mixed> $payload 페이앱 feedback 원본 (pay_type 등 표기용) */
    public static function estimatePaid(Estimate $estimate, array $payload = []): void
    {
        try {
            $group = trim((string) Setting::get('payment_alert_group', ''));
            if ($group === '') {
                return; // 톡방 미설정 — 기능 꺼짐
            }

            // 같은 견적서의 중복 통보(페이앱 재시도가 거의 동시에 도착하는 경우) 방어 —
            // Cache::add는 원자적이라 1시간 내 두 번째 호출은 발송하지 않는다
            if (! Cache::add("payment.alert.sent.{$estimate->id}", 1, 3600)) {
                return;
            }

            /** @var ChannelTalkClient $ct */
            $ct = app(ChannelTalkClient::class);

            $managerIds = json_decode((string) Setting::get('payment_alert_managers', '[]'), true);
            $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
                ->get()
                ->map(fn (User $u) => $ct->managerMention($u->email, $u->display_name))
                ->implode(' ');

            $client = $estimate->client_nickname ?: $estimate->client?->nickname;
            $payType = self::payTypeLabel((string) ($payload['pay_type'] ?? ''));

            $lines = [
                '[결제완료] 견적서 #'.$estimate->id.($client ? ' · '.$client : ''),
                '금액 '.number_format((int) $estimate->total_amount).'원 · 페이앱'.($payType !== '' ? " ({$payType})" : ''),
                url("/estimates/{$estimate->id}/edit"),
            ];
            if ($mentions !== '') {
                $lines[] = $mentions;
            }

            $res = $ct->sendGroupMessage(implode("\n", $lines), $group);
            if (! ($res['ok'] ?? false)) {
                Log::warning('결제완료 채널톡 알림 실패: '.($res['error'] ?? '알 수 없음')." (견적서 #{$estimate->id})");
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
