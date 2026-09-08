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

    /** 주문 내역 통합 리스트 — 견적서 파생 + 직접 주문, 날짜별 그룹 순. q(제품/의뢰자/구매처/주문명)·from/to(그룹 날짜) 검색 지원 */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        // 항목 또는 세트 구성품 단위 주문완료 여부 (구성품만 주문돼도 주문 내역에 노출)
        $isOrdered = fn ($i) => ! empty($i['ordered'])
            || collect($i['bundle_items'] ?? [])->contains(fn ($b) => ! empty($b['ordered']));
        // 항목 완전 주문 여부 — 미주문 태그 해제 기준: 항목 자체 주문완료거나, 세트면 구성품 전부 처리
        $isFullyOrdered = function ($i) {
            if (! empty($i['ordered'])) {
                return true;
            }
            $bundle = collect($i['bundle_items'] ?? []);

            return $bundle->isNotEmpty() && $bundle->every(fn ($b) => ! empty($b['ordered']));
        };

        // 결제완료 견적서는 자동 등재(주문 버튼 없이도 미주문 상태로 노출) + 주문완료 항목이 있는 견적서.
        // 스냅샷 JSON이라 PHP에서 필터 (전체 로드 방지: 최근 300건)
        $orderedEstimates = Estimate::with(['shipments' => fn ($s) => $s->orderBy('id')])
            ->where('status', '!=', 'temp')
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get()
            ->filter(fn (Estimate $e) => $e->status === 'paid'
                || collect($e->product_items ?? [])->contains($isOrdered));

        // 제품 정보(메모·서비스 분류) — 노출되는 전 항목의 제품에서 한 번에 조회
        $products = Product::with('categoryRelation')->whereIn('id', $orderedEstimates
            ->flatMap(fn (Estimate $e) => collect($e->product_items ?? [])->pluck('product_id'))
            ->filter()->unique()->values())
            ->get()->keyBy('id');
        $productMemos = $products->map(fn ($p) => $p->memo);

        // 제품 미연결(수기·구버전) 항목의 서비스 판정용 — 이름이 정확히 일치하는 제품의 분류를 따른다
        $unlinkedNames = $orderedEstimates
            ->flatMap(fn (Estimate $e) => collect($e->product_items ?? [])
                ->filter(fn ($i) => empty($i['product_id']))->pluck('name'))
            ->filter()->unique()->values();
        $serviceNames = Product::with('categoryRelation')->whereIn('name', $unlinkedNames)->get()
            ->filter(fn ($p) => $p->isService())->pluck('name')->flip();

        // 서비스 항목(세팅비 등) — 실물 주문이 없으므로 주문 내역 대상에서 제외 (주문완료 취급).
        // 스냅샷이 서비스면 서비스, 제품이 연결돼 있으면 '현재 제품 분류'가 우선(스냅샷이 낡은 기존
        // 건도 재분류), 미연결 항목은 같은 이름의 서비스 제품이 있으면 서비스로 판정.
        // ※ 매출 통계는 스냅샷(is_service)을 그대로 쓰므로 여기 판정과 무관하게 불변.
        $isServiceItem = function ($i) use ($products, $serviceNames) {
            if (! empty($i['is_service'])) {
                return true;
            }
            if (! empty($i['product_id']) && isset($products[$i['product_id']])) {
                return $products[$i['product_id']]->isService();
            }

            return isset($serviceNames[trim((string) ($i['name'] ?? ''))]);
        };

        // 장비 항목이 하나도 없는 견적서(서비스만으로 구성)는 주문할 것이 없으므로 표시하지 않는다
        $orderedEstimates = $orderedEstimates->filter(function (Estimate $e) use ($isOrdered, $isServiceItem) {
            $equip = collect($e->product_items ?? [])->reject($isServiceItem);

            return $e->status === 'paid' ? $equip->isNotEmpty() : $equip->contains($isOrdered);
        });

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
                    // 서비스 항목(세팅비 등)은 주문 대상이 아니므로 제외 — 장비만 (reject가 원본 인덱스 보존)
                    ->reject($isServiceItem)
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
                        'replaced' => ! empty($i['replaced']), // 대체된(취소선) 항목 — 주문 대상 아님
                        'replaced_note' => $i['replaced_note'] ?? null,
                        'manual' => ! empty($i['manual']) || empty($i['product_id']), // 수기 입력 항목 (제품 미연결)
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
                    // 결제완료 건은 전 항목 노출(미주문 항목도 주문 내역에서 바로 주문완료 처리),
                    // 그 외(주문 표시로만 등재된 건)는 기존처럼 주문완료 항목만
                    ->filter(fn ($i) => $e->status === 'paid' || $isOrdered($i))
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
                'paid_at' => $e->paid_at?->format('Y-m-d H:i'),
                // 날짜별 그룹 기준 — 결제완료일 우선, 없으면(주문 표시로만 등재) 주문완료일·수정일 순
                'group_date' => $e->paid_at?->format('Y-m-d')
                    ?? (($firstOrdered = collect($e->product_items ?? [])
                        ->flatMap(fn ($i) => [$i['ordered_at'] ?? null, ...collect($i['bundle_items'] ?? [])->pluck('ordered_at')])
                        ->filter()->max()) ? substr($firstOrdered, 0, 10) : $e->updated_at->format('Y-m-d')),
                // 미주문 상태 — 결제완료 건에서 장비 항목이 하나라도 주문 처리 전이면 표시,
                // 전 항목(세트는 구성품 전부)이 주문완료/직접발송돼야 해제 (서비스 항목은 주문완료 취급)
                'unordered' => $e->status === 'paid'
                    && collect($e->product_items ?? [])->reject($isServiceItem)
                        ->reject(fn ($i) => ! empty($i['replaced'])) // 대체된 항목은 주문 대상 아님
                        ->reject(fn ($i) => (int) ($i['subtotal'] ?? 0) < 0) // 음수(할인) 항목도 주문 대상 아님
                        ->contains(fn ($i) => ! $isFullyOrdered($i)),
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
                'group_date' => ($o->order_date ?? $o->created_at)->format('Y-m-d'), // 수동 주문은 주문 작성일 기준
            ]);

        // 검색어 — 주문명/의뢰자/견적번호/제품·구성품명/구매처/메모 (스냅샷 JSON은 한글이
        // 유니코드로 저장돼 SQL LIKE가 안 통하므로 만들어진 행에서 PHP로 매칭)
        $matches = function (array $r) use ($q): bool {
            $hay = [$r['title'] ?? '', $r['client'] ?? '', $r['creator'] ?? '', (string) ($r['no'] ?? '')];
            foreach ($r['items'] ?? [] as $i) {
                $hay[] = $i['name'] ?? '';
                $hay[] = $i['purchase_source'] ?? '';
                $hay[] = $i['memo'] ?? '';
                foreach ($i['bundle_items'] ?? [] as $b) {
                    $hay[] = $b['name'] ?? '';
                    $hay[] = $b['source'] ?? '';
                }
            }

            return collect($hay)->contains(fn ($h) => $h !== '' && mb_stripos((string) $h, $q) !== false);
        };

        return response()->json(
            $estimateRows->concat($manualRows)
                ->when($q !== '', fn ($rows) => $rows->filter($matches))
                // 기간 필터 — 그룹 기준 날짜(결제완료일/주문 작성일)로
                ->when($from !== '', fn ($rows) => $rows->filter(fn ($r) => ($r['group_date'] ?? '') >= $from))
                ->when($to !== '', fn ($rows) => $rows->filter(fn ($r) => ($r['group_date'] ?? '') <= $to))
                ->sortByDesc(fn ($r) => ($r['group_date'] ?? '').' '.$r['updated_at']) // 날짜 그룹이 연속되게
                ->values()
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
            'ordered' => 'nullable|boolean', // 주문 내역에서 직접 주문완료/해제 (직접발송은 구매처 '사무실 발송' 동반)
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

        // 주문완료 토글 — 켜질 때 처리 시각 기록(기존 값 유지), 해제 시 제거 (빌더 저장과 동일 규칙)
        if ($request->has('ordered')) {
            if ($request->boolean('ordered')) {
                $items[$validated['index']]['ordered'] = true;
                $items[$validated['index']]['ordered_at'] = $items[$validated['index']]['ordered_at'] ?? now()->format('Y-m-d H:i');
            } else {
                unset($items[$validated['index']]['ordered'], $items[$validated['index']]['ordered_at']);
            }
        }
        // 기입값(구매처/메모/금액)은 요청에 키가 있을 때만 갱신 — 주문완료/직접발송 버튼만 눌렀을 때
        // 기존 입력을 지우지 않는다 (기존 저장 화면은 모든 키를 항상 보내므로 동작 동일)
        if ($request->has('purchase_source')) {
            $items[$validated['index']]['purchase_source'] = $validated['purchase_source'] ?? '';
        }
        if ($request->has('memo')) {
            $items[$validated['index']]['order_memo'] = $validated['memo'] ?? '';
        }
        if ($request->has('amount')) {
            if (($validated['amount'] ?? null) !== null && $validated['amount'] !== '') {
                $items[$validated['index']]['purchase_amount'] = (int) $validated['amount'];
            } else {
                unset($items[$validated['index']]['purchase_amount']);
            }
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
        // 구성품 주문완료 토글 — 주문 내역의 주문완료/직접발송 버튼 (빌더 저장과 동일 규칙)
        if ($request->has('ordered')) {
            if ($request->boolean('ordered')) {
                $bundles[$bIdx]['ordered'] = true;
                $bundles[$bIdx]['ordered_at'] = $bundles[$bIdx]['ordered_at'] ?? now()->format('Y-m-d H:i');
            } else {
                unset($bundles[$bIdx]['ordered'], $bundles[$bIdx]['ordered_at']);
            }
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
