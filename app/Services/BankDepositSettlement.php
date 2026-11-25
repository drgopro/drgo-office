<?php

namespace App\Services;

use App\Models\BankDeposit;
use App\Models\Estimate;

/** 계좌이체 결제완료 처리 — 매칭된 입금 합계가 견적 금액에 도달한 미결제 견적서를 결제완료로 전환 */
class BankDepositSettlement
{
    /** 결제완료로 바꿀 수 있는 미결제 상태 — 결제완료·취소·견적취소·임시는 건드리지 않는다 */
    public const SETTLEABLE_STATUSES = ['created', 'editing', 'completed', 'issued', 'hold'];

    /** 결제완료로 바꿨으면 true. 미결제 상태가 아니거나 금액 미달·0원이면 아무것도 하지 않는다 */
    public static function settle(Estimate $estimate): bool
    {
        if (! in_array($estimate->status, self::SETTLEABLE_STATUSES, true) || (int) $estimate->total_amount <= 0) {
            return false;
        }
        $deposits = BankDeposit::where('estimate_id', $estimate->id);
        if ((int) (clone $deposits)->sum('amount') < (int) $estimate->total_amount) {
            return false;
        }

        $lastReceivedAt = (clone $deposits)->max('received_at');
        $estimate->update(['status' => 'paid', 'paid_at' => $lastReceivedAt ?? now()]);
        EstimatePaymentSync::estimatePaid($estimate->fresh(), '계좌이체');
        EstimatePaymentSync::syncProjectBilling($estimate->fresh());
        PaymentCompleteAlert::estimatePaid($estimate->fresh(), [], '계좌이체');

        return true;
    }
}
