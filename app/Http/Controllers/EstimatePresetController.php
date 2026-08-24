<?php

namespace App\Http\Controllers;

use App\Models\EstimatePreset;
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
        'items.*.sale_price' => 'nullable|numeric|min:0',
        'items.*.qty' => 'nullable|integer|min:1|max:999',
        'items.*.time_required' => 'nullable|string|max:50',
        'items.*.use_time' => 'nullable|boolean',
        'items.*.manual' => 'nullable|boolean',
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

    public function index(): JsonResponse
    {
        return response()->json(
            EstimatePreset::with('creator')
                ->orderByDesc('updated_at')
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
            ];
        })->values()->all();
    }
}
