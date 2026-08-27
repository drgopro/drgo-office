<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\RevenueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * 매출 인식 원장 기록 — '언제의 매출로 볼 것인가'를 revenue_entries에 파생 기록한다.
 *
 * 원칙
 * - 견적서 단위로 전부 지우고 다시 만드는 멱등 재계산 (revenue:rebuild로 전체 재구축 가능)
 * - 인식일: 결제일 기준. 단, 연동 프로젝트가 완료(done)면 프로젝트 완료일로 이동
 * - 환불/취소는 환불일 기준 음수 줄 (완료일로 소급하지 않는다)
 * - 세팅비/장비판매 분리: 스냅샷 항목의 is_service(담은 시점 박제) + 구버전 service_items
 * - 견적서 없는 단순 결제(charge/refund)는 결제 행 1:1(payment_only)로 기록
 */
class RevenueLedger
{
    /** @var array<int, Collection> 견적서별 제품 분류 캐시 — 재계산 1회 동안만 유효 */
    private static array $productCache = [];

    /** rebuild 중인지 — 결제 일시 단서가 전혀 없는 견적의 인식일 폴백이 달라진다 (아래 주석 참고) */
    private static bool $rebuilding = false;

    /** 견적서 단위 재계산 — 결제 이력이 있는 견적서만 원장에 남는다 */
    public static function syncEstimate(?Estimate $estimate): void
    {
        if (! $estimate || ! Schema::hasTable('revenue_entries')) {
            return;
        }
        unset(self::$productCache[$estimate->id]); // 분류가 바뀌었을 수 있으니 매 재계산마다 새로 읽는다
        $prevBase = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        RevenueEntry::where('estimate_id', $estimate->id)->delete();

        $charge = ProjectPayment::where('estimate_id', $estimate->id)->where('type', 'charge')->first();
        // 매출 인식 대상: 결제완료 상태, 또는 결제 이력이 있는 결제취소(전액 환불 흔적을 +/−로 남긴다)
        if (! ($estimate->status === 'paid' || ($estimate->status === 'cancelled' && $charge))) {
            return;
        }

        [$serviceTotal, $productTotal] = self::splitTotals($estimate);
        $total = $serviceTotal + $productTotal;
        if ($total <= 0) {
            return;
        }

        // 인식일 — 프로젝트 완료 시 완료일, 아니면 결제일 (재계산에도 안정적이도록 이전 값 보존 폴백)
        $project = $estimate->project_id ? Project::find($estimate->project_id) : null;
        if ($project && $project->stage === 'done' && $project->completed_at) {
            $recognizedOn = $project->completed_at->toDateString();
        } else {
            // 결제 일시 단서가 전혀 없는 견적(수동 '결제 완료' 처리, 미연동): 실시간 전환이면 오늘이
            // 곧 결제 확인일이지만, rebuild는 과거 데이터를 훑는 중이라 오늘로 몰면 원래 월에서 사라진다
            // → 기존 통계와 같은 기준인 견적서 생성일로 폴백한다.
            $recognizedOn = self::dateOf($charge?->paid_at)
                ?? $estimate->payapp_paid_at?->toDateString()
                ?? self::dateOf($charge?->created_at)
                ?? $prevBase?->recognized_on?->toDateString()
                ?? (self::$rebuilding ? $estimate->created_at?->toDateString() : null)
                ?? now()->toDateString();
        }

        RevenueEntry::create([
            'kind' => 'estimate_paid',
            'estimate_id' => $estimate->id,
            'project_id' => $estimate->project_id,
            'recognized_on' => $recognizedOn,
            'product_amount' => $productTotal,
            'service_amount' => $serviceTotal,
            'amount' => $total,
        ]);

        // 환불/취소 — 결제 원장 행이 있으면 그 행들로(환불일·항목 배분), 없으면(미연동) 스냅샷 기록으로
        $refundRows = ProjectPayment::where('estimate_id', $estimate->id)->whereIn('type', ['refund', 'cancel'])->get();
        if ($refundRows->isNotEmpty()) {
            foreach ($refundRows as $row) {
                [$svc, $prod] = self::splitRefundRow($estimate, $row, $serviceTotal, $productTotal);
                if ($svc + $prod <= 0) {
                    continue;
                }
                RevenueEntry::create([
                    'kind' => 'estimate_refund',
                    'estimate_id' => $estimate->id,
                    'project_id' => $estimate->project_id,
                    'payment_id' => $row->id,
                    'recognized_on' => self::dateOf($row->refunded_at) ?? self::dateOf($row->paid_at) ?? $row->created_at->toDateString(),
                    'product_amount' => -$prod,
                    'service_amount' => -$svc,
                    'amount' => -($svc + $prod),
                ]);
            }
        } else {
            [$svc, $prod, $on] = self::splitSnapshotRefunds($estimate);
            if ($svc + $prod > 0) {
                RevenueEntry::create([
                    'kind' => 'estimate_refund',
                    'estimate_id' => $estimate->id,
                    'project_id' => $estimate->project_id,
                    'recognized_on' => $on ?? now()->toDateString(),
                    'product_amount' => -$prod,
                    'service_amount' => -$svc,
                    'amount' => -($svc + $prod),
                ]);
            }
        }
    }

    /** 결제 행 변경 훅 — 견적 연동이면 견적 단위 재계산, 아니면 단순 결제 1:1 기록 */
    public static function onPaymentChanged(ProjectPayment $payment): void
    {
        if (! Schema::hasTable('revenue_entries')) {
            return;
        }
        if ($payment->estimate_id) {
            self::syncEstimate(Estimate::find($payment->estimate_id));

            return;
        }
        RevenueEntry::updateOrCreate(
            ['kind' => 'payment_only', 'payment_id' => $payment->id],
            [
                'project_id' => $payment->project_id,
                'recognized_on' => self::dateOf($payment->paid_at) ?? $payment->created_at?->toDateString() ?? now()->toDateString(),
                'product_amount' => 0,
                'service_amount' => 0,
                'amount' => (int) $payment->amount,
            ],
        );
    }

    public static function onPaymentDeleted(ProjectPayment $payment): void
    {
        if (! Schema::hasTable('revenue_entries')) {
            return;
        }
        if ($payment->estimate_id) {
            self::syncEstimate(Estimate::find($payment->estimate_id));

            return;
        }
        RevenueEntry::where('kind', 'payment_only')->where('payment_id', $payment->id)->delete();
    }

    /** 프로젝트 완료(done) 전환/해제 — 연동 견적서들의 인식일 이동 */
    public static function onProjectStageChanged(Project $project): void
    {
        if (! Schema::hasTable('revenue_entries')) {
            return;
        }
        Estimate::where('project_id', $project->id)
            ->whereIn('status', ['paid', 'cancelled'])
            ->get()->each(fn ($e) => self::syncEstimate($e));
    }

    /** 전체 재구축 — 배포/롤백/규칙 변경 후 언제든 원장을 처음부터 다시 만든다 */
    public static function rebuild(): int
    {
        if (! Schema::hasTable('revenue_entries')) {
            return 0;
        }
        RevenueEntry::query()->delete();
        $count = 0;
        self::$rebuilding = true;
        try {
            Estimate::whereIn('status', ['paid', 'cancelled'])->orderBy('id')->chunkById(100, function ($estimates) use (&$count) {
                foreach ($estimates as $e) {
                    self::syncEstimate($e);
                    $count++;
                }
            });
            ProjectPayment::whereNull('estimate_id')->orderBy('id')->chunkById(200, function ($payments) use (&$count) {
                foreach ($payments as $p) {
                    self::onPaymentChanged($p);
                    $count++;
                }
            });
        } finally {
            self::$rebuilding = false;
        }

        return $count;
    }

    /** 스냅샷 기준 세팅비/장비 총액 분리 — is_service 항목 + 구버전 service_items = 세팅비 */
    private static function splitTotals(Estimate $estimate): array
    {
        $service = (int) $estimate->service_total;
        $product = 0;
        foreach ($estimate->product_items ?? [] as $item) {
            $sub = (int) ($item['subtotal'] ?? 0);
            if (self::itemIsService($estimate, $item)) {
                $service += $sub;
            } else {
                $product += $sub;
            }
        }
        // 스냅샷 항목 합이 총액 컬럼(product_total+service_total)에 못 미치면(항목 없이 총액만 있는
        // 구버전 견적 등) 잔여를 장비 매출로 보정해 원장 합계가 견적 총액과 어긋나지 않게 한다.
        $columnTotal = (int) $estimate->product_total + (int) $estimate->service_total;
        if ($service + $product < $columnTotal) {
            $product += $columnTotal - ($service + $product);
        }

        return [$service, $product];
    }

    /**
     * 항목의 서비스 여부 — 담을 때 박제된 스냅샷(is_service)이 있으면 그 값을,
     * 분류 도입 전 스냅샷(키 없음)은 현재 제품 분류로 폴백한다.
     * 덕분에 카테고리에 '서비스'만 체크하면 과거 견적서도 재계산 시 따라온다.
     */
    private static function itemIsService(Estimate $estimate, array $item): bool
    {
        if (array_key_exists('is_service', $item)) {
            return (bool) $item['is_service'];
        }
        if (empty($item['product_id'])) {
            return false; // 분류 이전의 수기 항목 — 기본 제품
        }
        // 견적서 단위 캐시 — 재계산 한 번에 제품 조회 1회 (syncEstimate 시작 시 무효화)
        $key = $estimate->id;
        if (! isset(self::$productCache[$key])) {
            $ids = collect($estimate->product_items ?? [])->pluck('product_id')->filter()->unique()->values();
            self::$productCache = [$key => Product::with('categoryRelation.parent.parent')->whereIn('id', $ids)->get()->keyBy('id')];
        }
        $product = self::$productCache[$key]->get($item['product_id']);

        return $product ? $product->isService() : false;
    }

    /** 환불 결제 행의 세팅비/장비 배분 — 항목 연동분은 그 항목의 분류로, 나머지는 총액 비율로 */
    private static function splitRefundRow(Estimate $estimate, ProjectPayment $row, int $serviceTotal, int $productTotal): array
    {
        $refundTotal = -(int) $row->amount; // 음수 저장 → 양수 환불액
        if ($refundTotal <= 0) {
            return [0, 0];
        }
        $items = $estimate->product_items ?? [];
        $svc = 0;
        $prod = 0;
        foreach ($row->items ?? [] as $ri) {
            if (! isset($ri['estimate_item_index'])) {
                continue;
            }
            $target = $items[(int) $ri['estimate_item_index']] ?? null;
            $amount = ((int) ($ri['qty'] ?? 1)) * ((int) ($ri['price'] ?? 0));
            if ($target && self::itemIsService($estimate, $target)) {
                $svc += $amount;
            } else {
                $prod += $amount;
            }
        }
        $rest = $refundTotal - ($svc + $prod);
        if ($rest > 0) {
            // 항목 미연동분 — 견적 총액의 세팅비:장비 비율로 비례 배분
            $base = max(1, $serviceTotal + $productTotal);
            $svcShare = (int) round($rest * $serviceTotal / $base);
            $svc += $svcShare;
            $prod += $rest - $svcShare;
        } elseif ($rest < 0) {
            $prod = max(0, $prod + $rest); // 배분 합이 초과하면 장비 쪽에서 깎아 합계를 맞춘다
        }

        return [$svc, $prod];
    }

    /** 미연동 견적의 스냅샷 환불 합산 — 항목 분류별, 인식일은 마지막 환불 기록 시각 */
    private static function splitSnapshotRefunds(Estimate $estimate): array
    {
        $svc = 0;
        $prod = 0;
        $on = null;
        foreach ($estimate->product_items ?? [] as $item) {
            $amount = (int) ($item['refund_amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            if (self::itemIsService($estimate, $item)) {
                $svc += $amount;
            } else {
                $prod += $amount;
            }
            $at = substr((string) ($item['refunded_at'] ?? ''), 0, 10);
            if ($at && ($on === null || $at > $on)) {
                $on = $at;
            }
        }

        return [$svc, $prod, $on];
    }

    private static function dateOf($value): ?string
    {
        if (! $value) {
            return null;
        }

        return substr(is_string($value) ? $value : $value->format('Y-m-d'), 0, 10) ?: null;
    }
}
