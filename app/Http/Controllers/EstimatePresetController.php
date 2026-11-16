<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\EstimatePreset;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 견적 프리셋 CRUD — 품목은 장바구니와 동일한 스냅샷 구조로 저장하고,
 * 견적서에서 불러올 때 클라이언트가 현재 판매가로 갱신한다.
 */
class EstimatePresetController extends Controller
{
    private const ITEM_RULES = [
        'items' => 'required|array|min:1|max:200',
        'items.*.product_id' => 'nullable|integer',
        'items.*.sku' => 'nullable|string|max:100',
        'items.*.category' => 'nullable|string|max:100',
        'items.*.category_root' => 'nullable|string|max:100',
        'items.*.name' => 'required|string|max:200',
        'items.*.purchase_price' => 'nullable|numeric|min:0',
        'items.*.sale_price' => 'nullable|numeric', // 음수 허용 — 할인(차감) 항목
        'items.*.qty' => 'nullable|integer|min:1|max:999',
        'items.*.time_required' => 'nullable|string|max:50',
        'items.*.use_time' => 'nullable|boolean',
        'items.*.manual' => 'nullable|boolean',
        // 세트 구성품 스냅샷 — 빌더 접기/펼치기 표시용
        'items.*.bundle_items' => 'nullable|array|max:50',
        'items.*.bundle_items.*.name' => 'required|string|max:200',
        'items.*.bundle_items.*.qty' => 'nullable|integer|min:1|max:999',
        'items.*.bundle_items.*.price' => 'nullable|numeric|min:0',
    ];

    /** 프리셋 만들기 — 견적서 편집과 동일한 레이아웃의 새 창 */
    public function create()
    {
        return view('estimates.preset-edit', ['preset' => null]);
    }

    /** 프리셋 수정 페이지 */
    public function editPage(EstimatePreset $preset)
    {
        return view('estimates.preset-edit', compact('preset'));
    }

    /**
     * 프리셋을 견적서 양식으로 출력 — 표준 견적 안내용 이미지를 견적서를 만들지 않고 바로 저장.
     * 저장하지 않는 임시 견적서 객체에 프리셋 품목(현재 판매가 반영)을 담아 견적서 인쇄 화면을 그대로 쓴다.
     */
    public function print(EstimatePreset $preset)
    {
        $items = $this->itemsAtCurrentPrices($preset->items ?? []);
        $total = (int) collect($items)->sum('subtotal');

        $estimate = new Estimate([
            'title' => $preset->title,
            'product_items' => $items,
            'service_items' => [],
            'product_total' => $total,
            'service_total' => 0,
            'total_amount' => $total,
            'currency' => 'KRW',
            'status' => 'draft',
            'validity_days' => 3,
        ]);
        $estimate->created_at = $estimate->updated_at = now();

        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone', 'seller_stamp_path',
        ]);

        return view('estimates.print', [
            'estimate' => $estimate,
            'settings' => $settings,
            'rounds' => collect(),
            'presetMode' => true,
        ]);
    }

    /**
     * 프리셋 품목을 현재 판매가(기간 할인 포함)로 갱신 — 견적서에서 프리셋을 불러올 때와 같은 금액이 되도록.
     * 수기 입력 품목·삭제된 제품은 저장된 가격 그대로 둔다.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemsAtCurrentPrices(array $items): array
    {
        $products = Product::whereIn('id', collect($items)->pluck('product_id')->filter()->unique())->get()->keyBy('id');

        return collect($items)->map(function (array $item) use ($products) {
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $product = (! empty($item['product_id']) && empty($item['manual'])) ? $products->get($item['product_id']) : null;
            if ($product) {
                $discount = $product->activeDiscountAmount();
                $item['sale_price'] = $discount > 0 ? $product->discountedPrice() : (int) $product->sale_price;
                if ($discount > 0) {
                    $item['deal_type'] = 'discount';
                    $item['original_price'] = (int) $product->sale_price;
                }
            }
            $item['qty'] = $qty;
            $item['subtotal'] = (int) ($item['sale_price'] ?? 0) * $qty;

            return $item;
        })->all();
    }

    public function index(): JsonResponse
    {
        return response()->json(
            EstimatePreset::with('creator')
                ->orderBy('sort_order') // 0 = 미정렬(새 프리셋) → 맨 위, 이후 드래그로 지정한 순서
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (EstimatePreset $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'items' => $p->items,
                    'item_count' => count($p->items ?? []),
                    'total' => collect($p->items ?? [])->sum(fn ($i) => ((int) ($i['sale_price'] ?? 0)) * ((int) ($i['qty'] ?? 1))),
                    'creator' => $p->creator?->display_name,
                    'updated_at' => $p->updated_at->format('Y-m-d H:i'),
                ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['title' => 'required|string|max:200'] + self::ITEM_RULES);

        $preset = EstimatePreset::create([
            'title' => $validated['title'],
            'items' => $this->normalizeItems($validated['items']),
            'created_by' => Auth::id(),
        ]);

        return response()->json($preset, 201);
    }

    public function update(Request $request, EstimatePreset $preset): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'items' => 'sometimes|array|min:1|max:200',
        ] + collect(self::ITEM_RULES)->except('items')->all());

        if (isset($validated['items'])) {
            $validated['items'] = $this->normalizeItems($validated['items']);
        }
        $preset->update($validated);

        return response()->json($preset);
    }

    public function destroy(EstimatePreset $preset): JsonResponse
    {
        $preset->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    /** 프리셋 순서 저장 — 빌더 프리셋 패널 드래그 정렬. ids 순서대로 1..N 부여 */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        foreach (array_values($validated['ids']) as $i => $id) {
            EstimatePreset::where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['message' => '순서가 저장되었습니다.']);
    }

    /** 수량·소계 정규화 — 저장본은 항상 일관된 스냅샷 형태 유지 */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(function ($i) {
            $qty = max(1, (int) ($i['qty'] ?? 1));
            $price = (int) ($i['sale_price'] ?? 0);

            return [
                'product_id' => $i['product_id'] ?? null,
                'sku' => $i['sku'] ?? null,
                'category' => $i['category'] ?? null,
                'category_root' => $i['category_root'] ?? $i['category'] ?? null,
                'name' => $i['name'],
                'purchase_price' => (int) ($i['purchase_price'] ?? 0),
                'sale_price' => $price,
                'qty' => $qty,
                'time_required' => $i['time_required'] ?? '',
                'use_time' => (bool) ($i['use_time'] ?? false),
                'subtotal' => $price * $qty,
                'manual' => (bool) ($i['manual'] ?? false),
                // 세트 구성품 스냅샷 유지 (빌더 접기/펼치기 표시용)
                'bundle_items' => collect($i['bundle_items'] ?? [])
                    ->map(fn ($b) => ['name' => (string) ($b['name'] ?? ''), 'qty' => max(1, (int) ($b['qty'] ?? 1)), 'price' => (int) ($b['price'] ?? 0)])
                    ->filter(fn ($b) => $b['name'] !== '')->values()->all(),
            ];
        })->values()->all();
    }
}
