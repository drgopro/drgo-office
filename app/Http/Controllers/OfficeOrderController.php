<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\OfficeOrder;
use App\Models\ScheduleShipment;
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
        'items.*.purchase_source' => 'nullable|string|max:100',
        'items.*.memo' => 'nullable|string|max:500',
    ];

    /** 주문 내역 통합 리스트 — 견적서 파생 + 직접 주문, 최신순 */
    public function index(): JsonResponse
    {
        // 주문완료 항목이 있는 견적서 — 스냅샷 JSON이라 PHP에서 필터 (전체 로드 방지: 최근 200건)
        $estimateRows = Estimate::with(['shipments' => fn ($q) => $q->orderBy('id')])
            ->where('status', '!=', 'temp')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->filter(fn (Estimate $e) => collect($e->product_items ?? [])->contains(fn ($i) => ! empty($i['ordered'])))
            ->map(fn (Estimate $e) => [
                'type' => 'estimate',
                'id' => $e->id,
                'no' => $e->display_no,
                'title' => $e->title ?: "견적서 #{$e->display_no}",
                'client' => $e->client_nickname ?: $e->client_name,
                'status' => $e->status,
                'items' => collect($e->product_items ?? [])
                    ->map(fn ($i, $idx) => [
                        'index' => $idx,
                        'name' => $i['name'] ?? '',
                        'qty' => (int) ($i['qty'] ?? 1),
                        'purchase_source' => $i['purchase_source'] ?? '',
                        'memo' => $i['order_memo'] ?? '',
                        'ordered' => ! empty($i['ordered']),
                    ])
                    ->filter(fn ($i) => $i['ordered'])
                    ->values(),
                'shipments' => $e->shipments->map(fn (ScheduleShipment $s) => [
                    'carrier_label' => $s->carrierLabel(),
                    'tracking_no' => $s->tracking_no,
                    'status' => $s->status,
                    'last_event' => $s->last_event,
                    'delivered_at' => $s->delivered_at?->format('m/d H:i'),
                ])->values(),
                'updated_at' => $e->updated_at->format('Y-m-d H:i'),
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
                'items' => collect($o->items ?? [])->map(fn ($i) => [
                    'name' => $i['name'] ?? '',
                    'qty' => (int) ($i['qty'] ?? 1),
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
        $validated = $request->validate(['title' => 'required|string|max:200'] + self::ITEM_RULES);

        $order = OfficeOrder::create([
            'title' => $validated['title'],
            'items' => $this->normalizeItems($request->input('items')),
            'created_by' => Auth::id(),
        ]);

        return response()->json($order, 201);
    }

    public function update(Request $request, OfficeOrder $order): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:200'] + self::ITEM_RULES);

        $order->update([
            'title' => $request->input('title'),
            'items' => $this->normalizeItems($request->input('items')),
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
            'purchase_source' => 'nullable|string|max:100',
            'memo' => 'nullable|string|max:500',
        ]);

        $items = $estimate->product_items ?? [];
        if (! array_key_exists($validated['index'], $items)) {
            return response()->json(['message' => '항목을 찾을 수 없습니다. 목록을 새로고침해 주세요.'], 422);
        }

        $items[$validated['index']]['purchase_source'] = $validated['purchase_source'] ?? '';
        $items[$validated['index']]['order_memo'] = $validated['memo'] ?? '';
        $estimate->forceFill(['product_items' => $items])->save();

        return response()->json(['message' => '저장되었습니다.']);
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>> */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(fn ($i) => [
            'name' => trim((string) $i['name']),
            'qty' => max(1, (int) ($i['qty'] ?? 1)),
            'purchase_source' => trim((string) ($i['purchase_source'] ?? '')),
            'memo' => trim((string) ($i['memo'] ?? '')),
        ])->values()->all();
    }
}
