<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\ProjectPayment;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * 결제완료/환불 상태 동기화 — 견적서(status) ⇄ 프로젝트 결제 내역(원장) ⇄ 캘린더 일정(표시).
 *
 * 원칙: 돈의 원장은 ProjectPayment 하나뿐이다. 이 서비스는 상태·표시를 맞추고,
 * 원장 기록은 명시된 경우(페이앱 통보 등)에만 중복 방지 가드와 함께 생성한다.
 */
class EstimatePaymentSync
{
    /**
     * 견적서 결제완료 전파.
     * - 프로젝트 연동 시: 같은 견적서의 charge가 없을 때만 결제 내역 자동 기록 (항목 포함)
     * - 캘린더: 이 견적서가 연동된 일정의 결제 여부/금액을 '결제완료'로 표시
     */
    public static function estimatePaid(Estimate $estimate, string $method = '페이앱'): void
    {
        if ($estimate->project_id && (int) $estimate->total_amount > 0
            && ! ProjectPayment::where('estimate_id', $estimate->id)->where('type', 'charge')->exists()) {
            ProjectPayment::create([
                'project_id' => $estimate->project_id,
                'type' => 'charge',
                'estimate_id' => $estimate->id,
                'amount' => (int) $estimate->total_amount,
                'items' => collect($estimate->product_items ?? [])->map(fn ($i, $idx) => [
                    'name' => $i['name'] ?? '항목',
                    'qty' => (int) ($i['qty'] ?? 1),
                    'price' => (int) ($i['sale_price'] ?? 0),
                    'estimate_item_index' => $idx,
                ])->values()->all() ?: null,
                'method' => $method,
                'paid_at' => now()->toDateString(),
                'memo' => "견적서 #{$estimate->display_no} 결제완료 자동 기록",
                'recorded_by' => Auth::id(),
            ]);
        }

        self::syncSchedules($estimate, '결제완료');
    }

    /**
     * 결제취소/전액환불 전파 — 견적서를 '결제 취소'로, 연동 캘린더는 '미결제'로.
     * $recordLedger=true(페이앱 전액환불 통보 등)면 남은 금액의 취소 트랜잭션을
     * 원장에 기록하고 견적서 전 항목에 환불 표시까지 남긴다. 프로젝트 화면에서
     * 이미 환불을 기록한 경우에는 false로 호출해 이중 기록을 막는다.
     */
    public static function estimateCancelled(Estimate $estimate, bool $recordLedger = false): void
    {
        if ($recordLedger) {
            $charge = ProjectPayment::where('estimate_id', $estimate->id)->where('type', 'charge')->first();
            if ($charge) {
                $refunded = (int) ProjectPayment::where('parent_payment_id', $charge->id)
                    ->whereIn('type', ['refund', 'cancel'])->sum('amount'); // 음수
                $remaining = (int) $charge->amount + $refunded;
                if ($remaining > 0) {
                    ProjectPayment::create([
                        'project_id' => $charge->project_id,
                        'parent_payment_id' => $charge->id,
                        'billing_id' => $charge->billing_id,
                        'type' => 'cancel',
                        'estimate_id' => $estimate->id,
                        'amount' => -$remaining,
                        'method' => $charge->method,
                        'paid_at' => now()->toDateString(),
                        'refunded_at' => now(),
                        'memo' => "견적서 #{$estimate->display_no} 결제취소 자동 기록",
                        'recorded_by' => Auth::id(),
                    ]);
                    $charge->billing?->refreshStatus();
                }
            }
            // 전 항목 환불 표시 — 아직 환불되지 않은 잔여분만
            $refunds = collect($estimate->product_items ?? [])->map(function ($i, $idx) {
                $qty = max(0, (int) ($i['qty'] ?? 1) - (int) ($i['refund_qty'] ?? 0));
                $amount = max(0, (int) ($i['subtotal'] ?? 0) - (int) ($i['refund_amount'] ?? 0));

                return ($qty > 0 || $amount > 0) ? ['index' => $idx, 'qty' => $qty, 'amount' => $amount] : null;
            })->filter()->values()->all();
            if ($refunds !== []) {
                $estimate->applyItemRefunds($refunds);
            }
        }

        if ($estimate->status === 'paid') {
            $estimate->forceFill(['status' => 'cancelled'])->save();
        }

        self::syncSchedules($estimate, '미결제');
    }

    /**
     * 항목 환불 합계를 연동 캘린더 일정에 표시용으로 동기화 — 부분환불은 결제 상태를
     * 건드리지 않으므로 환불 기록 직후(프로젝트 환불·주문 내역 수동 체크) 별도로 호출한다.
     */
    public static function syncRefundDisplay(Estimate $estimate): void
    {
        self::linkedSchedules($estimate)->each(function (Schedule $s) use ($estimate) {
            $g = self::withRefund($s->request_data ?? [], $estimate, $changed);
            if ($changed) {
                $s->update(['request_data' => $g]);
            }
        });
    }

    /** 이 견적서가 연동된 캘린더 일정(request_data.estimate_id)의 결제 표시/금액/환불 동기화 */
    private static function syncSchedules(Estimate $estimate, string $paid): void
    {
        self::linkedSchedules($estimate)->each(function (Schedule $s) use ($estimate, $paid) {
            $g = $s->request_data ?? [];
            $changed = false;
            if (($g['paid'] ?? '') !== $paid) {
                $g['paid'] = $paid;
                $changed = true;
            }
            if ($paid === '결제완료') {
                $amt = number_format((int) $estimate->total_amount);
                if (($g['estimate_amount'] ?? '') !== $amt) {
                    $g['estimate_amount'] = $amt;
                    $changed = true;
                }
            }
            $g = self::withRefund($g, $estimate, $refundChanged);
            if ($changed || $refundChanged) {
                $s->update(['request_data' => $g]);
            }
        });
    }

    /** @return Collection<int, Schedule> */
    private static function linkedSchedules(Estimate $estimate)
    {
        return Schedule::where('request_data->estimate_id', $estimate->id)
            ->orWhere('request_data->estimate_id', (string) $estimate->id) // 구데이터 문자열 대비
            ->get();
    }

    /**
     * request_data에 환불 합계(estimate_refund)를 반영한 배열 반환 — 0원이면 키 제거.
     *
     * @param  array<string, mixed>  $g
     * @return array<string, mixed>
     */
    private static function withRefund(array $g, Estimate $estimate, ?bool &$changed): array
    {
        $total = (int) collect($estimate->product_items ?? [])->sum(fn ($i) => (int) ($i['refund_amount'] ?? 0));
        $new = $total > 0 ? number_format($total) : '';
        $changed = ($g['estimate_refund'] ?? '') !== $new;
        if ($new === '') {
            unset($g['estimate_refund']);
        } else {
            $g['estimate_refund'] = $new;
        }

        return $g;
    }
}
