<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectBilling;
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

        // 미수 청구가 연동돼 있으면 실제 결제된 금액으로 확정하고 charge와 연결 → 청구 상태 '결제됨'
        $billing = ProjectBilling::where('estimate_id', $estimate->id)->first();
        $charge = ProjectPayment::where('estimate_id', $estimate->id)->where('type', 'charge')->first();
        if ($billing && $charge) {
            if ($billing->payments()->count() === 0 && (int) $billing->amount !== (int) $charge->amount) {
                $billing->update(['amount' => (int) $charge->amount]); // 청구액을 결제된 금액으로
            }
            if (! $charge->billing_id) {
                $charge->update(['billing_id' => $billing->id]);
            }
            $billing->refreshStatus();
        }

        self::syncSchedules($estimate, '결제완료');
    }

    /**
     * 견적서 삭제 전파 — 프로젝트·캘린더에 남은 연동 흔적 정리.
     * - 입금 이력 없는 미수 청구 삭제 (입금·결제된 청구는 기록 보존)
     * - 프로젝트 견적/계약 카드(stage_data.estimate.estimate_ids)에서 제거
     * - 연동 캘린더 일정의 estimate_id/estimate_ids에서 제거 + 환불 표시 재계산
     *   (결제 여부·금액 표시는 이력이므로 유지)
     */
    public static function estimateDeleted(Estimate $estimate): void
    {
        ProjectBilling::where('estimate_id', $estimate->id)
            ->where('status', 'unpaid')
            ->whereDoesntHave('payments')
            ->delete();

        if ($estimate->project_id && ($project = Project::find($estimate->project_id))) {
            $sdata = $project->stage_data ?? [];
            $ids = $sdata['estimate']['estimate_ids'] ?? null;
            if (is_array($ids)) {
                $kept = collect($ids)->filter(fn ($v) => (int) $v !== (int) $estimate->id)->values()->all();
                if (count($kept) !== count($ids)) {
                    $sdata['estimate']['estimate_ids'] = $kept;
                    $project->update(['stage_data' => $sdata]);
                }
            }
        }

        self::linkedSchedules($estimate)->each(function (Schedule $s) use ($estimate) {
            $g = $s->request_data ?? [];
            if (is_array($g['estimate_ids'] ?? null)) {
                $g['estimate_ids'] = collect($g['estimate_ids'])
                    ->filter(fn ($v) => (int) $v !== (int) $estimate->id)->values()->all();
            }
            if ((int) ($g['estimate_id'] ?? 0) === (int) $estimate->id) {
                $g['estimate_id'] = ($g['estimate_ids'] ?? [])[0] ?? null;
            }
            if (empty($g['estimate_id'])) {
                unset($g['estimate_id']);
            }
            if (empty($g['estimate_ids'])) {
                unset($g['estimate_ids']);
            }
            $g = self::withRefund($g, $changed);
            $s->update(['request_data' => $g]);
        });
    }

    /**
     * 견적서 ↔ 프로젝트 청구 동기화 — 프로젝트에 연동된 미결제 견적서를 '미수 청구'로 자동 등록.
     * - 미결제 상태(작성중/발행완료 등): 청구 생성, 입금 전이면 금액·프로젝트를 견적서 최신값으로 유지
     * - 결제완료: 건드리지 않음 (estimatePaid가 charge 연결로 '결제됨' 처리)
     * - 결제취소·연동 해제·임시: 입금 이력이 없는 청구만 제거
     */
    public static function syncProjectBilling(Estimate $estimate): void
    {
        $billing = ProjectBilling::where('estimate_id', $estimate->id)->first();
        if ($estimate->status === 'paid') {
            return;
        }

        $active = $estimate->project_id
            && (int) $estimate->total_amount > 0
            && ! in_array($estimate->status, ['paid', 'cancelled', 'temp'], true);

        if ($active) {
            if ($billing) {
                // 입금 전(미입금)일 때만 금액/프로젝트 최신화 — 입금이 시작된 청구는 건드리지 않음
                if ($billing->paidTotal() === 0 && $billing->status === 'unpaid'
                    && ((int) $billing->amount !== (int) $estimate->total_amount || (int) $billing->project_id !== (int) $estimate->project_id)) {
                    $billing->update([
                        'project_id' => $estimate->project_id,
                        'amount' => (int) $estimate->total_amount,
                    ]);
                }
            } else {
                ProjectBilling::create([
                    'project_id' => $estimate->project_id,
                    'estimate_id' => $estimate->id,
                    'amount' => (int) $estimate->total_amount,
                    'billed_at' => now()->format('Y-m-d'),
                    'status' => 'unpaid',
                    'memo' => "견적서 #{$estimate->display_no} 연동 청구",
                    'created_by' => Auth::id(),
                ]);
            }
        } elseif ($billing && $billing->payments()->count() === 0 && $billing->status !== 'paid') {
            $billing->delete();
        }
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
     * 여러 견적서가 연동된 일정은 전체 연동 견적서의 환불 합계로 표시된다.
     */
    public static function syncRefundDisplay(Estimate $estimate): void
    {
        self::linkedSchedules($estimate)->each(function (Schedule $s) {
            $g = self::withRefund($s->request_data ?? [], $changed);
            if ($changed) {
                $s->update(['request_data' => $g]);
            }
        });
    }

    /** 이 견적서가 연동된 캘린더 일정(estimate_id/estimate_ids)의 결제 표시/금액/환불 동기화 */
    private static function syncSchedules(Estimate $estimate, string $paid): void
    {
        self::linkedSchedules($estimate)->each(function (Schedule $s) use ($paid) {
            $g = $s->request_data ?? [];
            $changed = false;
            if (($g['paid'] ?? '') !== $paid) {
                $g['paid'] = $paid;
                $changed = true;
            }
            if ($paid === '결제완료') {
                // 여러 견적서 연동 시 표시 금액은 전체 합계
                $ids = self::scheduleEstimateIds($g);
                $amt = number_format((int) Estimate::whereIn('id', $ids)->sum('total_amount'));
                if (($g['estimate_amount'] ?? '') !== $amt) {
                    $g['estimate_amount'] = $amt;
                    $changed = true;
                }
            }
            $g = self::withRefund($g, $refundChanged);
            if ($changed || $refundChanged) {
                $s->update(['request_data' => $g]);
            }
        });
    }

    /** @return Collection<int, Schedule> */
    private static function linkedSchedules(Estimate $estimate)
    {
        return Schedule::where(function ($q) use ($estimate) {
            $q->where('request_data->estimate_id', $estimate->id)
                ->orWhere('request_data->estimate_id', (string) $estimate->id) // 구데이터 문자열 대비
                ->orWhereJsonContains('request_data->estimate_ids', $estimate->id)
                ->orWhereJsonContains('request_data->estimate_ids', (string) $estimate->id);
        })->get();
    }

    /**
     * 일정에 연동된 모든 견적서 id — 단일 estimate_id(하위 호환) + 다중 estimate_ids.
     *
     * @param  array<string, mixed>  $g
     * @return array<int, int>
     */
    public static function scheduleEstimateIds(array $g): array
    {
        return collect([$g['estimate_id'] ?? null])
            ->merge(is_array($g['estimate_ids'] ?? null) ? $g['estimate_ids'] : [])
            ->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * request_data에 연동 견적서 전체의 환불 합계(estimate_refund)를 반영한 배열 반환 — 0원이면 키 제거.
     *
     * @param  array<string, mixed>  $g
     * @return array<string, mixed>
     */
    private static function withRefund(array $g, ?bool &$changed): array
    {
        $ids = self::scheduleEstimateIds($g);
        $total = $ids === [] ? 0 : (int) Estimate::whereIn('id', $ids)->get()
            ->sum(fn (Estimate $e) => collect($e->product_items ?? [])->sum(fn ($i) => (int) ($i['refund_amount'] ?? 0)));
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
