<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\EstimateStockSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 환불 재고 보정 (임시 점검 페이지) — 과거 로직 결함으로 환불 시 재고 복원/입고가
 * 누락된 건을 견적서×제품 단위로 리스트업하고, 담당자가 확인 후 개별 입고한다.
 *
 * - 직접발송 복원 누락: 스냅샷 기준 기대 순출고량보다 입출고 이력의 순출고가 많은 차이분.
 *   로직 버그로 확실한 누락이라 적용을 권장.
 * - 거래처 주문 반품 미입고: 거래처 주문분(주문완료) 환불인데 '사무실 반품 입고' 이력이
 *   없는 건. 실물 반품을 받았는지는 시스템이 모르므로 담당자가 확인 후 선택 적용.
 */
class RefundRestockAuditController extends Controller
{
    public function page()
    {
        return view('inventory.refund-restock-audit', ['rows' => $this->audit()]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estimate_id' => 'required|integer|exists:estimates,id',
            'product_id' => 'required|integer|exists:products,id',
            'kind' => 'required|in:direct,vendor',
            'qty' => 'required|integer|min:1',
        ]);

        // 현재 감사 결과에서 해당 행을 다시 찾아 수량 검증 — 중복 클릭/이중 적용 방지
        $row = collect($this->audit())->first(fn ($r) => $r['estimate_id'] === (int) $validated['estimate_id']
            && $r['product_id'] === (int) $validated['product_id']
            && $r['kind'] === $validated['kind']);
        if (! $row) {
            return response()->json(['message' => '이미 보정됐거나 대상이 아닙니다. 새로고침 후 다시 확인해 주세요.'], 422);
        }
        if ((int) $validated['qty'] > $row['qty']) {
            return response()->json(['message' => "보정 가능 수량({$row['qty']}개)을 초과합니다."], 422);
        }

        $qty = (int) $validated['qty'];
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $row['product_id']],
            ['quantity' => 0, 'last_updated_at' => now()],
        );
        $newQty = (int) $inventory->quantity + $qty;
        // 메모 패턴을 기존 복원/입고 집계와 같게 유지 — 보정 입고가 재감사에 반영돼 자동 멱등
        StockMovement::create([
            'product_id' => $row['product_id'],
            'movement_type' => 'return',
            'quantity' => $qty,
            'quantity_after' => $newQty,
            'user_id' => Auth::id(),
            'memo' => $validated['kind'] === 'direct'
                ? '견적서 #'.$row['no'].' 직접발송 복원 (환불 누락 보정)'
                : '견적서 #'.$row['no'].' 사무실 반품 입고 (환불 누락 보정)',
        ]);
        $inventory->update(['quantity' => $newQty, 'last_updated_at' => now()]);

        return response()->json(['ok' => true, 'quantity' => $newQty]);
    }

    /** @return array<int, array{kind: string, estimate_id: int, no: string, title: string, client: string, product_id: int, product: string, qty: int, refunded_at: string}> */
    private function audit(): array
    {
        $rows = [];
        Estimate::with('parent')->whereNotNull('product_items')->orderByDesc('id')->chunk(100, function ($estimates) use (&$rows) {
            $refundEstimates = $estimates->filter(fn ($e) => collect($e->product_items ?? [])->contains(fn ($i) => ! empty($i['refunded'])
                || (int) ($i['refund_qty'] ?? 0) > 0
                || collect($i['bundle_items'] ?? [])->contains(fn ($b) => (int) ($b['refund_qty'] ?? 0) > 0)));
            if ($refundEstimates->isEmpty()) {
                return;
            }
            $movementsByNo = $this->movementsByEstimateNo($refundEstimates->map(fn ($e) => (string) $e->display_no)->all());
            // 세트 제품을 청크 단위로 한 번에 읽어 netShippedMap에 넘긴다 — 견적서마다 조회하던 N+1 제거
            $bundleParentIds = $refundEstimates->flatMap(fn ($e) => collect($e->product_items ?? [])
                ->filter(fn ($i) => ! empty($i['product_id']) && ! empty($i['bundle_items']))
                ->pluck('product_id'))->unique()->values();
            $bundleProducts = $bundleParentIds->isEmpty()
                ? collect()
                : Product::with('bundleItems.component')->whereIn('id', $bundleParentIds)->get()->keyBy('id');

            foreach ($refundEstimates as $e) {
                $items = $e->product_items ?? [];
                $no = (string) $e->display_no;
                $meta = [
                    'estimate_id' => $e->id, 'no' => $no,
                    'title' => (string) ($e->title ?: ''),
                    'client' => (string) ($e->client_nickname ?: $e->client_name ?: '-'),
                ];

                // 과거 로직은 세트 부모에만 환불 수량을 기록했으므로, 구성품에 비례 반영한
                // 사본으로 판단한다 (현재 applyItemRefunds와 동일 규칙)
                $normItems = $this->normalizeParentRefunds($items);

                // ── 직접발송 복원 누락 — 기대 순출고(현재 스냅샷) vs 실제 입출고 이력 순출고 ──
                $expected = EstimateStockSync::netShippedMap($normItems, $bundleProducts);
                $actual = [];
                collect($movementsByNo[$no]['direct'] ?? [])
                    ->each(function ($m) use (&$actual) {
                        $actual[$m->product_id] = ($actual[$m->product_id] ?? 0)
                            + ($m->movement_type === 'out' ? (int) $m->quantity : -(int) $m->quantity);
                    });
                foreach ($actual as $pid => $net) {
                    $diff = $net - ($expected[$pid] ?? 0);
                    if ($diff > 0) {
                        $rows[] = $meta + [
                            'kind' => 'direct', 'product_id' => (int) $pid,
                            'qty' => $diff,
                            'refunded_at' => $this->lastRefundedAt($items),
                        ];
                    }
                }

                // ── 거래처 주문 반품 미입고 — 주문완료된 거래처 주문분 환불인데 입고 이력 없음 ──
                $restockedByPid = [];
                collect($movementsByNo[$no]['vendor'] ?? [])
                    ->each(function ($m) use (&$restockedByPid) {
                        $restockedByPid[$m->product_id] = ($restockedByPid[$m->product_id] ?? 0) + (int) $m->quantity;
                    });
                $vendorMissing = []; // pid → 미입고 수량
                foreach ($normItems as $item) {
                    $parentDirect = EstimateStockSync::isDirect($item);
                    foreach ((array) ($item['bundle_items'] ?? []) as $b) {
                        $rq = (int) ($b['refund_qty'] ?? 0);
                        $direct = $parentDirect || (! empty($b['ordered']) && ($b['source'] ?? '') === '사무실 발송');
                        if ($rq <= 0 || $direct || empty($b['ordered'])) {
                            continue; // 직발은 위 유형이 커버, 미주문분은 반품 개념 없음
                        }
                        $pid = EstimateStockSync::componentProductId($item, $b);
                        if ($pid > 0) {
                            $vendorMissing[$pid] = ($vendorMissing[$pid] ?? 0) + $rq;
                        }
                    }
                    // 세트가 아닌 단품 — 거래처 주문분 환불
                    if (empty($item['bundle_items']) && ! $parentDirect && ! empty($item['ordered']) && ! empty($item['product_id'])) {
                        $rq = (int) ($item['refund_qty'] ?? 0);
                        if ($rq > 0) {
                            $vendorMissing[(int) $item['product_id']] = ($vendorMissing[(int) $item['product_id']] ?? 0) + $rq;
                        }
                    }
                }
                foreach ($vendorMissing as $pid => $rq) {
                    $missing = $rq - ($restockedByPid[$pid] ?? 0);
                    if ($missing > 0) {
                        $rows[] = $meta + [
                            'kind' => 'vendor', 'product_id' => (int) $pid,
                            'qty' => $missing,
                            'refunded_at' => $this->lastRefundedAt($items),
                        ];
                    }
                }
            }
        });

        // 제품명은 마지막에 한 번에 조회 — 행마다 조회하던 N+1 제거
        $names = Product::whereIn('id', array_unique(array_column($rows, 'product_id')))->pluck('name', 'id');
        foreach ($rows as $i => $row) {
            $rows[$i]['product'] = $names[$row['product_id']] ?? ('#'.$row['product_id']);
        }

        return $rows;
    }

    /**
     * 견적서 번호들의 직접발송/사무실 반품 입고 이력을 한 쿼리로 읽어 번호·유형별로 묶는다.
     * 메모 패턴은 기존 개별 조회('견적서 #{no} 직접발송%', '견적서 #{no} 사무실 반품 입고%')와 같다.
     *
     * @param  array<int, string>  $nos
     * @return array<string, array{direct?: array<int, StockMovement>, vendor?: array<int, StockMovement>}>
     */
    private function movementsByEstimateNo(array $nos): array
    {
        $movements = StockMovement::query()
            ->select(['id', 'product_id', 'movement_type', 'quantity', 'memo'])
            ->where(function ($q) use ($nos) {
                foreach ($nos as $no) {
                    $q->orWhere('memo', 'like', '견적서 #'.$no.' 직접발송%')
                        ->orWhere('memo', 'like', '견적서 #'.$no.' 사무실 반품 입고%');
                }
            })
            ->get();

        $grouped = [];
        foreach ($movements as $m) {
            if (preg_match('/^견적서 #(\S+) (직접발송|사무실 반품 입고)/u', (string) $m->memo, $match)) {
                $grouped[$match[1]][$match[2] === '직접발송' ? 'direct' : 'vendor'][] = $m;
            }
        }

        return $grouped;
    }

    /**
     * 세트 부모에만 기록된 환불 수량을 구성품 refund_qty에 비례 반영한 사본을 돌려준다.
     * 구성품에 이미 더 큰 기록이 있으면 유지, 총량(구성수량×세트수량)을 넘지 않는다.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeParentRefunds(array $items): array
    {
        foreach ($items as $idx => $item) {
            $parentRefund = (int) ($item['refund_qty'] ?? 0);
            if ($parentRefund <= 0 || empty($item['bundle_items']) || ! is_array($item['bundle_items'])) {
                continue;
            }
            $itemQty = max(1, (int) ($item['qty'] ?? 1));
            foreach ($item['bundle_items'] as $bi => $b) {
                $per = max(1, (int) ($b['qty'] ?? 1));
                $total = $per * $itemQty;
                $items[$idx]['bundle_items'][$bi]['refund_qty'] = min($total, max((int) ($b['refund_qty'] ?? 0), $per * $parentRefund));
            }
        }

        return $items;
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function lastRefundedAt(array $items): string
    {
        return (string) collect($items)->pluck('refunded_at')->filter()->max();
    }
}
