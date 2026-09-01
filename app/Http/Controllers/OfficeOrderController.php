<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\OfficeOrder;
use App\Models\Product;
use App\Models\ScheduleShipment;
use App\Services\EstimatePaymentSync;
use App\Services\EstimateStockSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 재고 관리 > 주문 내역 — 두 종류의 주문 건을 그룹(1건 → 펼치면 항목)으로 보여준다.
 * 1) 견적서 파생: 빌더 주문/배송에서 '주문완료' 표시된 항목이 하나라도 있는 견적서 (운송장 포함)
 * 2) 직접 주문: 사무실 비품/간식 등 (office_orders, 새 창에서 등록/수정)
 */
class OfficeOrderController extends Controller
{
    private const ITEM_RULES = [
        'items' => 'required|array|min:1|max:100',
        'items.*.name' => 'required|string|max:200',
        'items.*.qty' => 'nullable|integer|min:1|max:9999',
        'items.*.amount' => 'nullable|numeric|min:0', // 구매 금액 (총액)
        'items.*.purchase_source' => 'nullable|string|max:100',
        'items.*.memo' => 'nullable|string|max:500',
    ];

    /** 주문 내역 통합 리스트 — 견적서 파생 + 직접 주문, 최신순 */
    public function index(): JsonResponse
    {
        // 항목 또는 세트 구성품 단위 주문완료 여부 (구성품만 주문돼도 주문 내역에 노출)
        $isOrdered = fn ($i) => ! empty($i['ordered'])
            || collect($i['bundle_items'] ?? [])->contains(fn ($b) => ! empty($b['ordered']));

        // 주문완료 항목이 있는 견적서 — 스냅샷 JSON이라 PHP에서 필터 (전체 로드 방지: 최근 200건)
        $orderedEstimates = Estimate::with(['shipments' => fn ($q) => $q->orderBy('id')])
            ->where('status', '!=', 'temp')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->filter(fn (Estimate $e) => collect($e->product_items ?? [])->contains($isOrdered));

        // 제품 메모(직원용, 판매처 등) — 주문완료 항목의 제품에서 한 번에 조회
        $productMemos = Product::whereIn('id', $orderedEstimates
            ->flatMap(fn (Estimate $e) => collect($e->product_items ?? [])
                ->filter($isOrdered)->pluck('product_id'))
            ->filter()->unique()->values())
            ->pluck('memo', 'id');

        $estimateRows = $orderedEstimates
            ->map(fn (Estimate $e) => [
                'type' => 'estimate',
                'id' => $e->id,
                'no' => $e->display_no,
                'title' => $e->title ?: "견적서 #{$e->display_no}",
                'client' => $e->client_nickname ?: $e->client_name,
                'ship_address' => $e->ship_address, // 배송지 정보 — 내부용 (주문 내역 카드 표시)
                'ship_entrance' => $e->ship_entrance,
                'status' => $e->status,
                'items' => collect($e->product_items ?? [])
                    ->map(fn ($i, $idx) => [
                        'index' => $idx,
                        'name' => $i['name'] ?? '',
                        'qty' => (int) ($i['qty'] ?? 1),
                        // 제품 관리의 메모 (판매처 등 직원용) — 제품명 아래 표시
                        'product_memo' => ! empty($i['product_id']) ? ($productMemos[$i['product_id']] ?? null) : null,
                        // 구매 금액 — 직접 기록한 값. 미기록 시 참고용 기본값(매입가×수량)을 placeholder로
                        'amount' => isset($i['purchase_amount']) ? (int) $i['purchase_amount'] : null,
                        'default_amount' => (int) ($i['purchase_price'] ?? 0) * max(1, (int) ($i['qty'] ?? 1)),
                        'purchase_source' => $i['purchase_source'] ?? '',
                        'memo' => $i['order_memo'] ?? '',
                        'ordered' => ! empty($i['ordered']),
                        'ordered_at' => $i['ordered_at'] ?? null,
                        // 환불/결제취소 기록 — 수동 체크 + 프로젝트 환불 연동 공용
                        'refunded' => ! empty($i['refunded']),
                        'refund_amount' => (int) ($i['refund_amount'] ?? 0),
                        'sale_subtotal' => (int) ($i['subtotal'] ?? 0), // 환불액 기본값(판매가 합계) 참고용
                        // 세트 구성품 — 구성 단위 주문완료/구매처/메모 관리 (직접발송='사무실 발송')
                        'bundle_items' => collect($i['bundle_items'] ?? [])->map(fn ($b) => [
                            'name' => $b['name'] ?? '',
                            'qty' => max(1, (int) ($b['qty'] ?? 1)) * max(1, (int) ($i['qty'] ?? 1)),
                            'price' => (int) ($b['price'] ?? 0), // 구성품 단가 — 부분환불 계산용 참고치
                            'ordered' => ! empty($b['ordered']) || ! empty($i['ordered']), // 세트 전체 주문완료 포함
                            'ordered_at' => $b['ordered_at'] ?? null,
                            'source' => $b['source'] ?? '',
                            'memo' => $b['memo'] ?? '',
                            'refund_qty' => (int) ($b['refund_qty'] ?? 0), // 구성품 부분환불 기록
                            'refund_amount' => (int) ($b['refund_amount'] ?? 0),
                        ])->values()->all(),
                    ])
                    ->filter($isOrdered)
                    ->values(),
                'shipments' => $e->shipments->map(fn (ScheduleShipment $s) => [
                    'carrier_label' => $s->carrierLabel(),
                    'tracking_no' => $s->tracking_no,
                    'tracking_url' => $s->trackingUrl(),
                    'status' => $s->status,
                    'last_event' => $s->last_event,
                    'last_location' => $s->last_location, // 마지막 처리 사업장
                    'checked_at' => $s->checked_at?->format('m/d H:i'), // 마지막 추적 갱신 시각
                    'delivered_at' => $s->delivered_at?->format('m/d H:i'),
                ])->values(),
                'updated_at' => $e->updated_at->format('Y-m-d H:i'),
                // 주문완료 처리 시각 — 항목/구성품 중 가장 최근 ordered_at (견적 수정일 대신 표시)
                'ordered_at' => collect($e->product_items ?? [])
                    ->flatMap(fn ($i) => [$i['ordered_at'] ?? null, ...collect($i['bundle_items'] ?? [])->pluck('ordered_at')])
                    ->filter()->max(),
            ])
            ->values();

        $manualRows = OfficeOrder::with('creator')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (OfficeOrder $o) => [
                'type' => 'manual',
                'id' => $o->id,
                'title' => $o->title,
                'creator' => $o->creator?->display_name,
                'order_date' => ($o->order_date ?? $o->created_at)->format('Y-m-d'),
                'items' => collect($o->items ?? [])->map(fn ($i) => [
                    'name' => $i['name'] ?? '',
                    'qty' => (int) ($i['qty'] ?? 1),
                    'amount' => isset($i['amount']) && $i['amount'] !== '' ? (int) $i['amount'] : null,
                    'purchase_source' => $i['purchase_source'] ?? '',
                    'memo' => $i['memo'] ?? '',
                ])->values(),
                'shipments' => [],
                'updated_at' => $o->updated_at->format('Y-m-d H:i'),
            ]);

        return response()->json(
            $estimateRows->concat($manualRows)->sortByDesc('updated_at')->values()
        );
    }

    /** 직접 주문 등록/수정 새 창 */
    public function createPage()
    {
        return view('inventory.order-edit', ['order' => null]);
    }

    public function editPage(OfficeOrder $order)
    {
        return view('inventory.order-edit', compact('order'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['title' => 'required|string|max:200', 'order_date' => 'nullable|date'] + self::ITEM_RULES);

        $order = OfficeOrder::create([
            'title' => $validated['title'],
            'items' => $this->normalizeItems($request->input('items')),
            'order_date' => $validated['order_date'] ?? now()->toDateString(), // 미지정 시 오늘
            'created_by' => Auth::id(),
        ]);

        return response()->json($order, 201);
    }

    public function update(Request $request, OfficeOrder $order): JsonResponse
    {
        $validated = $request->validate(['title' => 'required|string|max:200', 'order_date' => 'nullable|date'] + self::ITEM_RULES);

        $order->update([
            'title' => $request->input('title'),
            'items' => $this->normalizeItems($request->input('items')),
            'order_date' => $validated['order_date'] ?? $order->order_date ?? now()->toDateString(),
        ]);

        return response()->json($order);
    }

    public function destroy(OfficeOrder $order): JsonResponse
    {
        $order->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    /** 견적서 파생 주문 건의 항목별 구매처/메모 — 스냅샷의 해당 항목에 직접 기록 */
    public function updateEstimateItemNote(Request $request, Estimate $estimate): JsonResponse
    {
        $validated = $request->validate([
            'index' => 'required|integer|min:0',
            'bundle_index' => 'nullable|integer|min:0', // 세트 구성품 단위 환불 체크
            'amount' => 'nullable|numeric|min:0', // 구매 금액 (빈 값이면 미기록으로 초기화)
            'purchase_source' => 'nullable|string|max:100',
            'memo' => 'nullable|string|max:500',
            'refunded' => 'nullable|boolean', // 환불/결제취소 수동 체크
            'refund_qty' => 'nullable|integer|min:0', // 구성품 환불 수량
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $items = $estimate->product_items ?? [];
        $beforeItems = $items; // 직접발송 재고 연동 — 변경 전 스냅샷
        if (! array_key_exists($validated['index'], $items)) {
            return response()->json(['message' => '항목을 찾을 수 없습니다. 목록을 새로고침해 주세요.'], 422);
        }

        // 세트 구성품 — 구성품에 구매처/메모/환불 기록 (항목의 구매 필드는 건드리지 않음)
        if (array_key_exists('bundle_index', $validated) && $validated['bundle_index'] !== null) {
            return $this->updateBundleItemNote($request, $estimate, $items, $validated);
        }

        $items[$validated['index']]['purchase_source'] = $validated['purchase_source'] ?? '';
        $items[$validated['index']]['order_memo'] = $validated['memo'] ?? '';
        if (($validated['amount'] ?? null) !== null && $validated['amount'] !== '') {
            $items[$validated['index']]['purchase_amount'] = (int) $validated['amount'];
        } else {
            unset($items[$validated['index']]['purchase_amount']);
        }
        // 환불/결제취소 수동 체크 — refunded 키가 요청에 있을 때만 갱신 (해제 시 기록 초기화)
        if ($request->has('refunded')) {
            if ($request->boolean('refunded')) {
                $items[$validated['index']]['refunded'] = true;
                $items[$validated['index']]['refund_amount'] = (int) ($validated['refund_amount'] ?? 0);
                $items[$validated['index']]['refunded_at'] = $items[$validated['index']]['refunded_at'] ?? now()->format('Y-m-d H:i');
            } else {
                unset($items[$validated['index']]['refunded'], $items[$validated['index']]['refund_amount'],
                    $items[$validated['index']]['refund_qty'], $items[$validated['index']]['refunded_at']);
            }
        }
        $estimate->forceFill(['product_items' => $items])->save();
        EstimateStockSync::apply($estimate, $beforeItems, $items);
        if ($request->has('refunded')) {
            EstimatePaymentSync::syncRefundDisplay($estimate->fresh());
        }

        return response()->json(['message' => '저장되었습니다.']);
    }

    /**
     * 세트 구성품 단위 기록 — 구매처(source)/메모(memo)와 수동 환불 체크(refund_qty/refund_amount).
     * 항목(부모)의 환불 금액은 구성품 합산으로 재계산해 프로젝트 환불 기록과 같은 구조를 유지하고,
     * 해제 시 구성품 기록을 지운 뒤 남은 합산이 0이면 항목 표시도 함께 초기화한다.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function updateBundleItemNote(Request $request, Estimate $estimate, array $items, array $validated): JsonResponse
    {
        $refunded = $request->boolean('refunded');
        $beforeItems = $items; // 직접발송 재고 연동 — 변경 전 스냅샷
        $idx = (int) $validated['index'];
        $bIdx = (int) $validated['bundle_index'];
        $bundles = $items[$idx]['bundle_items'] ?? [];
        if (! array_key_exists($bIdx, $bundles)) {
            return response()->json(['message' => '세트 구성품을 찾을 수 없습니다. 목록을 새로고침해 주세요.'], 422);
        }

        // 구매처/메모 — 요청에 키가 있을 때만 갱신 (환불만 보내는 구버전 화면 호환)
        if ($request->has('purchase_source')) {
            $bundles[$bIdx]['source'] = trim((string) ($validated['purchase_source'] ?? ''));
        }
        if ($request->has('memo')) {
            $bundles[$bIdx]['memo'] = trim((string) ($validated['memo'] ?? ''));
        }

        $beforeAmount = (int) ($bundles[$bIdx]['refund_amount'] ?? 0);
        if (! $request->has('refunded')) {
            // 환불 상태 변경 없이 구매처/메모만 저장
            $items[$idx]['bundle_items'] = array_values($bundles);
            $estimate->forceFill(['product_items' => $items])->save();
            EstimateStockSync::apply($estimate, $beforeItems, $items);

            return response()->json(['message' => '저장되었습니다.']);
        }
        if ($refunded) {
            $totalQty = max(1, (int) ($bundles[$bIdx]['qty'] ?? 1)) * max(1, (int) ($items[$idx]['qty'] ?? 1));
            $qty = min($totalQty, max(0, (int) ($validated['refund_qty'] ?? $totalQty)));
            $price = (int) ($bundles[$bIdx]['price'] ?? 0);
            $bundles[$bIdx]['refund_qty'] = $qty ?: $totalQty;
            $bundles[$bIdx]['refund_amount'] = ($validated['refund_amount'] ?? null) !== null
                ? (int) $validated['refund_amount']
                : $price * ($qty ?: $totalQty);
        } else {
            unset($bundles[$bIdx]['refund_qty'], $bundles[$bIdx]['refund_amount']);
        }
        $items[$idx]['bundle_items'] = array_values($bundles);

        // 항목 표시 — 구성품 변경분만큼 델타 반영 (세트 전체 환불 기록과 병존 가능하도록 합산 재계산 대신 증감)
        $afterAmount = (int) ($bundles[$bIdx]['refund_amount'] ?? 0);
        $parentAmount = max(0, (int) ($items[$idx]['refund_amount'] ?? 0) + ($afterAmount - $beforeAmount));
        $anyBundleQty = collect($items[$idx]['bundle_items'])->contains(fn ($b) => (int) ($b['refund_qty'] ?? 0) > 0);
        if ($parentAmount > 0 || $anyBundleQty || (int) ($items[$idx]['refund_qty'] ?? 0) > 0) {
            $items[$idx]['refunded'] = true;
            $items[$idx]['refund_amount'] = $parentAmount;
            $items[$idx]['refunded_at'] = $items[$idx]['refunded_at'] ?? now()->format('Y-m-d H:i');
        } else {
            unset($items[$idx]['refunded'], $items[$idx]['refund_amount'], $items[$idx]['refunded_at']);
        }

        $estimate->forceFill(['product_items' => $items])->save();
        EstimateStockSync::apply($estimate, $beforeItems, $items);
        EstimatePaymentSync::syncRefundDisplay($estimate->fresh());

        return response()->json(['message' => '저장되었습니다.']);
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>> */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(fn ($i) => [
            'name' => trim((string) $i['name']),
            'qty' => max(1, (int) ($i['qty'] ?? 1)),
            'amount' => isset($i['amount']) && $i['amount'] !== '' && $i['amount'] !== null ? max(0, (int) $i['amount']) : null,
            'purchase_source' => trim((string) ($i['purchase_source'] ?? '')),
            'memo' => trim((string) ($i['memo'] ?? '')),
        ])->values()->all();
    }
}
