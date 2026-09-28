<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

/**
 * 직접발송(사무실 발송) 재고 연동 — 견적서에 담기거나 주문완료된 것만으로는 재고를
 * 건드리지 않고, 주문/배송에서 '직접발송'으로 표시된 수량만 사무실 재고에서 차감한다.
 * 환불/결제취소가 기록되거나 직접발송을 해제하면 그만큼 복원(+n)한다.
 *
 * 스냅샷 변경 전/후의 '직접발송 순수량 맵'을 비교해 차이만 입출고로 기록하므로
 * 같은 상태를 여러 번 저장해도 중복 차감되지 않는다. 돈의 원장(ProjectPayment)과
 * 무관하게 재고 수량과 입출고 내역만 다룬다.
 */
class EstimateStockSync
{
    /**
     * 직접발송 순수량 맵 — product_id => (직접발송 수량 − 환불 수량).
     * 수기 항목(product_id 없음)은 재고 개념이 없으므로 제외.
     * 세트는 구성품 단위로 계산: 부모가 직접발송이면 전 구성품, 아니면 직접발송 표시된 구성품만.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<int, int>
     */
    public static function netShippedMap(?array $items): array
    {
        $items = $items ?? [];
        $bundleParentIds = collect($items)
            ->filter(fn ($i) => ! empty($i['product_id']) && ! empty($i['bundle_items']))
            ->pluck('product_id')->unique()->values();
        $bundleProducts = $bundleParentIds->isEmpty()
            ? collect()
            : Product::with('bundleItems.component')->whereIn('id', $bundleParentIds)->get()->keyBy('id');

        $map = [];
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if (! $pid) {
                continue;
            }
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $parentDirect = ! empty($item['ordered']) && ($item['purchase_source'] ?? '') === '사무실 발송';
            $bundles = is_array($item['bundle_items'] ?? null) ? $item['bundle_items'] : [];

            if (empty($bundles)) {
                if (! $parentDirect) {
                    continue;
                }
                // 환불 수량 — 수량 없이 환불 체크만 된 경우(전액 취소)는 전량 복원으로 간주
                $refund = (int) ($item['refund_qty'] ?? 0);
                if ($refund === 0 && ! empty($item['refunded'])) {
                    $refund = $qty;
                }
                $net = max(0, $qty - min($qty, $refund));
                if ($net > 0) {
                    $map[$pid] = ($map[$pid] ?? 0) + $net;
                }

                continue;
            }

            // 세트 — 스냅샷 구성품을 현재 세트 구성과 이름으로 매칭해 구성품 제품 재고를 다룬다
            $product = $bundleProducts->get($pid);
            if (! $product) {
                continue;
            }
            $components = $product->bundleItems->keyBy(fn ($bi) => $bi->component?->name ?? '');
            foreach ($bundles as $b) {
                $cid = (int) ($components->get($b['name'] ?? '')?->component_product_id ?? 0);
                if (! $cid) {
                    continue;
                }
                $direct = $parentDirect || (! empty($b['ordered']) && ($b['source'] ?? '') === '사무실 발송');
                if (! $direct) {
                    continue;
                }
                $total = max(1, (int) ($b['qty'] ?? 1)) * $qty;
                // 구성품 환불은 구성품에 기록된 수량만 신뢰 (부모의 금액만 기록된 부분환불로 과복원하지 않음)
                $net = max(0, $total - min($total, (int) ($b['refund_qty'] ?? 0)));
                if ($net > 0) {
                    $map[$cid] = ($map[$cid] ?? 0) + $net;
                }
            }
        }

        return $map;
    }

    /**
     * 스냅샷 변경 전/후를 비교해 차이만 재고에 반영한다.
     * 늘어난 순수량은 출고(out), 줄어든 순수량은 반품(return)으로 입출고 내역에 남는다.
     *
     * @param  array<int, array<string, mixed>>|null  $oldItems
     * @param  array<int, array<string, mixed>>|null  $newItems
     */
    public static function apply(Estimate $estimate, ?array $oldItems, ?array $newItems): void
    {
        $before = self::netShippedMap($oldItems);
        $after = self::netShippedMap($newItems);
        $pids = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($pids as $pid) {
            $delta = ($after[$pid] ?? 0) - ($before[$pid] ?? 0);
            if ($delta === 0) {
                continue;
            }
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $pid],
                ['quantity' => 0, 'last_updated_at' => now()],
            );
            $newQty = (int) $inventory->quantity - $delta;
            StockMovement::create([
                'product_id' => $pid,
                'movement_type' => $delta > 0 ? 'out' : 'return',
                'quantity' => abs($delta),
                'quantity_after' => $newQty,
                'user_id' => Auth::id(),
                'memo' => '견적서 #'.$estimate->display_no.($delta > 0 ? ' 직접발송' : ' 직접발송 복원 (환불/해제)'),
            ]);
            $inventory->update(['quantity' => $newQty, 'last_updated_at' => now()]);
        }
    }

    /** 견적서 삭제 시 — 직접발송으로 나가 있던 순수량을 전부 복원 */
    public static function release(Estimate $estimate): void
    {
        self::apply($estimate, $estimate->product_items, []);
    }

    /**
     * 항목이 직접발송인지 — 부모 기준. 세트 구성품은 componentDirect()로 판단.
     *
     * @param  array<string, mixed>  $item
     */
    public static function isDirect(array $item): bool
    {
        return ! empty($item['ordered']) && ($item['purchase_source'] ?? '') === '사무실 발송';
    }

    /**
     * 사무실 반품 입고 — 직접발송이 아닌(거래처 주문) 항목을 환불하면서 미사용 제품을
     * 사무실 재고로 받을 때 호출. 직접발송 항목은 apply()가 환불 시 자동 복원하므로
     * 이중 입고를 막기 위해 건너뛴다. 수기 항목(product_id 없음)도 재고 개념이 없어 제외.
     *
     * @param  array<int, array{index: int, bundle_index?: int|null, qty: int}>  $entries
     */
    public static function officeReturn(Estimate $estimate, array $entries): void
    {
        $items = $estimate->product_items ?? [];

        foreach ($entries as $entry) {
            $idx = (int) ($entry['index'] ?? -1);
            $qty = max(0, (int) ($entry['qty'] ?? 0));
            if ($qty === 0 || ! array_key_exists($idx, $items)) {
                continue;
            }
            $item = $items[$idx];
            $parentDirect = self::isDirect($item);

            if (isset($entry['bundle_index']) && $entry['bundle_index'] !== null) {
                // 세트 구성품 — 세트 구성에서 이름으로 구성품 제품을 매칭
                $bundle = $item['bundle_items'][(int) $entry['bundle_index']] ?? null;
                if (! $bundle) {
                    continue;
                }
                $direct = $parentDirect || (! empty($bundle['ordered']) && ($bundle['source'] ?? '') === '사무실 발송');
                if ($direct) {
                    continue;
                }
                $product = Product::with('bundleItems.component')->find((int) ($item['product_id'] ?? 0));
                $pid = (int) ($product?->bundleItems
                    ->first(fn ($bi) => ($bi->component?->name ?? '') === ($bundle['name'] ?? ''))
                    ?->component_product_id ?? 0);
            } else {
                if ($parentDirect) {
                    continue;
                }
                $pid = (int) ($item['product_id'] ?? 0);
            }
            if (! $pid) {
                continue;
            }

            $inventory = Inventory::firstOrCreate(
                ['product_id' => $pid],
                ['quantity' => 0, 'last_updated_at' => now()],
            );
            $newQty = (int) $inventory->quantity + $qty;
            StockMovement::create([
                'product_id' => $pid,
                'movement_type' => 'return',
                'quantity' => $qty,
                'quantity_after' => $newQty,
                'user_id' => Auth::id(),
                'memo' => '견적서 #'.$estimate->display_no.' 사무실 반품 입고 (환불 · 거래처 주문분)',
            ]);
            $inventory->update(['quantity' => $newQty, 'last_updated_at' => now()]);
        }
    }
}
