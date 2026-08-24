<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductMarketPrice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\MarketPriceCrawler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /** 마진률 경고 기준(%) 기본값 — 설정 미저장 시 사용 */
    private const DEFAULT_MARGIN_WARN_PERCENT = 20;

    public function index()
    {
        $marginWarnPercent = (int) Setting::get('inventory_margin_warn_percent', self::DEFAULT_MARGIN_WARN_PERCENT);

        return view('inventory.index', compact('marginWarnPercent'));
    }

    /** 마진률 경고 기준(%) 저장 — 기준 미만 제품은 목록에서 경고 표시 */
    public function updateMarginThreshold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'percent' => 'required|integer|min:0|max:99',
        ]);

        Setting::set('inventory_margin_warn_percent', $validated['percent']);

        return response()->json(['percent' => $validated['percent']]);
    }

    // === 카테고리 ===

    public function categories()
    {
        // 4차까지 노출
        $categories = ProductCategory::with('children.children.children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:product_categories,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
        ]);

        $depth = 1;
        if ($validated['parent_id']) {
            $parent = ProductCategory::findOrFail($validated['parent_id']);
            $depth = $parent->depth + 1;
            if ($depth > 4) {
                return response()->json(['message' => '최대 4단계까지 가능합니다.'], 422);
            }
        }

        // 같은 부모 내 code 중복 검증
        if (ProductCategory::where('parent_id', $validated['parent_id'])->where('code', $validated['code'])->exists()) {
            return response()->json(['message' => '같은 부모 내에 동일한 코드가 이미 있습니다.'], 422);
        }

        $maxSort = ProductCategory::where('parent_id', $validated['parent_id'])->max('sort_order') ?? 0;

        $category = ProductCategory::create([
            ...$validated,
            'depth' => $depth,
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, ProductCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
        ]);

        // 같은 부모 내 code 중복 검증 (자신은 제외)
        $dup = ProductCategory::where('parent_id', $category->parent_id)
            ->where('code', $validated['code'])
            ->where('id', '!=', $category->id)
            ->exists();
        if ($dup) {
            return response()->json(['message' => '같은 부모 내에 동일한 코드가 이미 있습니다.'], 422);
        }

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * 카테고리 이동 — parent_id 변경. 순환·최대 깊이 검증 후 후손 depth 일괄 재계산.
     */
    public function moveCategory(Request $request, ProductCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'new_parent_id' => 'nullable|integer|exists:product_categories,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $newParentId = $validated['new_parent_id'] ?? null;

        // 1) 자기 자신 또는 후손을 부모로 지정 금지
        if ($newParentId === $category->id) {
            return response()->json(['message' => '자기 자신을 부모로 지정할 수 없습니다.'], 422);
        }
        $descendantIds = $category->descendantIds();
        if ($newParentId && in_array($newParentId, $descendantIds, true)) {
            return response()->json(['message' => '후손 카테고리를 부모로 지정할 수 없습니다 (순환 참조).'], 422);
        }

        // 2) 이동 후 최대 깊이 ≤ 4 검증
        $newDepth = 1;
        if ($newParentId) {
            $newParent = ProductCategory::findOrFail($newParentId);
            $newDepth = $newParent->depth + 1;
        }
        $subtreeMaxDelta = $category->maxDepthInSubtree() - $category->depth;
        if ($newDepth + $subtreeMaxDelta > 4) {
            return response()->json(['message' => '이동하면 하위 카테고리가 4차를 넘어갑니다.'], 422);
        }

        DB::transaction(function () use ($category, $newParentId, $newDepth, $validated) {
            $category->parent_id = $newParentId;
            // sort_order: 지정값 우선, 없으면 새 부모 마지막에 추가
            if (isset($validated['sort_order'])) {
                $category->sort_order = $validated['sort_order'];
            } else {
                $maxSort = ProductCategory::where('parent_id', $newParentId)->max('sort_order') ?? 0;
                $category->sort_order = $maxSort + 1;
            }
            $category->save();

            // 자신 + 후손 depth 일괄 재계산
            $category->recalculateDepth($newDepth);
        });

        return response()->json(['message' => '이동되었습니다.', 'category' => $category->fresh()]);
    }

    /**
     * 같은 부모 내 형제 카테고리 순서 일괄 갱신 — 드래그 정렬용
     */
    public function reorderCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:product_categories,id',
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|exists:product_categories,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;

        DB::transaction(function () use ($parentId, $validated) {
            foreach ($validated['ordered_ids'] as $idx => $id) {
                ProductCategory::where('id', $id)
                    ->where('parent_id', $parentId)
                    ->update(['sort_order' => $idx + 1]);
            }
        });

        return response()->json(['message' => '정렬되었습니다.']);
    }

    public function destroyCategory(ProductCategory $category)
    {
        if ($category->children()->exists()) {
            return response()->json(['message' => '하위 카테고리가 있어 삭제할 수 없습니다.'], 422);
        }

        if (Product::where('category_id', $category->id)->exists()) {
            return response()->json(['message' => '이 카테고리를 사용하는 제품이 있어 삭제할 수 없습니다.'], 422);
        }

        $category->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // === 제품 ===

    public function products(Request $request)
    {
        // id_only=1 — 가벼운 ID 리스트만 (견적서 편집의 '삭제된 제품' 마커 판정용)
        if ($request->boolean('id_only')) {
            return response()->json(
                Product::query()->where('is_active', true)->get(['id'])
            );
        }

        $query = Product::with('inventory', 'categoryRelation', 'marketPrices', 'bundleItems.component.inventory', 'group')
            ->where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $ids = $this->getCategoryDescendantIds((int) $categoryId);
            $query->whereIn('category_id', $ids);
        }

        // 재고 수량 필터 — zero(0개) / low(안전재고 이하) / gte·lte(N개 기준)
        // 세트 상품은 자체 재고 대신 조립 가능 수(min(구성품 재고 ÷ 필요 수량)) 기준으로 동일 적용
        $stockOp = (string) $request->query('stock_op');
        if ($stockOp === 'low' || $request->query('low_stock')) { // low_stock은 구버전 파라미터 호환
            $query->whereHas('inventory', function ($q) {
                $q->whereRaw('quantity <= (SELECT safety_stock FROM products WHERE products.id = inventories.product_id)');
            });
        } elseif (in_array($stockOp, ['zero', 'gte', 'lte'], true)) {
            $effectiveStock = '(CASE WHEN products.is_bundle = 1 THEN COALESCE((
                SELECT MIN(FLOOR(COALESCE(ci.quantity, 0) / pbi.quantity))
                FROM product_bundle_items pbi
                LEFT JOIN inventories ci ON ci.product_id = pbi.component_product_id
                WHERE pbi.bundle_product_id = products.id
            ), 0) ELSE COALESCE((SELECT i.quantity FROM inventories i WHERE i.product_id = products.id), 0) END)';
            $val = max(0, (int) $request->query('stock_val', 0));
            match ($stockOp) {
                'zero' => $query->whereRaw("{$effectiveStock} = 0"),
                'gte' => $query->whereRaw("{$effectiveStock} >= ?", [$val]),
                'lte' => $query->whereRaw("{$effectiveStock} <= ?", [$val]),
            };
        }

        // 옵션 그룹 구성원이 목록에서 이웃하도록 그룹 대표 SKU 기준으로 묶어 정렬
        $groupClusterOrder = 'COALESCE((SELECT MIN(p2.sku) FROM products p2 WHERE p2.group_id = products.group_id AND p2.is_active = 1), products.sku)';

        // per_page가 있으면 페이지네이션 응답 (없으면 기존처럼 전체 배열 — 견적서 등 기존 호출부 호환)
        if ($perPage = (int) $request->query('per_page')) {
            $page = $query->orderByRaw($groupClusterOrder)->orderBy('sku')->paginate(min(max($perPage, 1), 200));

            return response()->json([
                'data' => $page->items(),
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ]);
        }

        return response()->json(
            $query->orderByRaw($groupClusterOrder)->orderBy('sku')->get()
        );
    }

    /**
     * 옵션 그룹 생성 — 기존 제품(ID 유지)들을 자식으로 묶는다.
     * 예: '카메라X 블랙'·'카메라X 화이트' → 그룹 '카메라X' (옵션: 블랙/화이트)
     */
    public function storeProductGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'items' => 'required|array|min:1|max:50',
            'items.*.id' => 'required|integer|exists:products,id',
            'items.*.option_name' => 'required|string|max:60',
        ]);

        $group = ProductGroup::create(['name' => $validated['name']]);
        foreach ($validated['items'] as $item) {
            Product::where('id', $item['id'])->update([
                'group_id' => $group->id,
                'option_name' => $item['option_name'],
            ]);
        }

        return response()->json($group->load('products:id,group_id,name,option_name'), 201);
    }

    /** 옵션 그룹 해제 — 자식 제품은 그대로 두고 묶음만 푼다 */
    public function destroyProductGroup(ProductGroup $group): JsonResponse
    {
        $group->products()->update(['group_id' => null, 'option_name' => null]);
        $group->delete();

        return response()->json(['message' => '그룹이 해제되었습니다.']);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|exists:product_categories,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'market_price_url_compuzone' => $this->marketPriceUrlRules('compuzone'),
            'market_price_url_pcfactory' => $this->marketPriceUrlRules('pcfactory'),
            'safety_stock' => 'nullable|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0', // 등록 시 초기 재고 (세트 제외)
            'memo' => 'nullable|string',
            'show_in_estimate' => 'boolean',
            'is_bundle' => 'boolean',
            'bundle_items' => 'required_if:is_bundle,1|nullable|array|max:50',
            'bundle_items.*.product_id' => 'required|integer|exists:products,id',
            'bundle_items.*.quantity' => 'required|integer|min:1|max:999',
        ]);
        $stockQuantity = $validated['stock_quantity'] ?? null;
        unset($validated['stock_quantity']);
        $marketUrls = $this->pullMarketUrls($validated);
        $bundleItems = $this->pullBundleItems($request, $validated);
        if ($bundleItems instanceof JsonResponse) {
            return $bundleItems;
        }

        try {
            $cat = ProductCategory::findOrFail($validated['category_id']);
            $sku = $this->generateSku($cat);

            // NOT NULL 컬럼들은 null을 0으로 강제 (DB는 unsignedBigInteger DEFAULT 0)
            // 입력 누락이든 null이든 0으로 안전 변환
            $validated['purchase_price'] = (int) ($validated['purchase_price'] ?? 0);
            $validated['sale_price'] = (int) ($validated['sale_price'] ?? 0);
            $validated['safety_stock'] = (int) ($validated['safety_stock'] ?? 0);

            $product = Product::create([
                ...$validated,
                'sku' => $sku,
                'category' => $cat->name,
                'is_active' => true,
                'show_in_estimate' => $request->boolean('show_in_estimate'),
                'is_bundle' => $request->boolean('is_bundle'),
            ]);

            // 세트 상품은 자체 재고 없음 — 구성품 재고를 소진 (일반 제품만 inventory 생성)
            if (! $product->is_bundle) {
                Inventory::create([
                    'product_id' => $product->id,
                    'quantity' => (int) ($stockQuantity ?? 0),
                    'last_updated_at' => now(),
                ]);
                if ((int) ($stockQuantity ?? 0) > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'movement_type' => 'in',
                        'quantity' => (int) $stockQuantity,
                        'quantity_after' => (int) $stockQuantity,
                        'user_id' => Auth::id(),
                        'memo' => '제품 등록 초기 재고',
                    ]);
                }
            }

            $this->syncBundleItems($product, $bundleItems);
            $this->syncMarketPriceUrls($product, $marketUrls);

            return response()->json($product->load('inventory', 'categoryRelation', 'marketPrices', 'bundleItems.component.inventory'), 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '제품 등록 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
                'sku_generated' => $sku ?? null,
                'category' => $cat ? ['id' => $cat->id, 'code' => $cat->code, 'depth' => $cat->depth] : null,
            ], 500);
        }
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|exists:product_categories,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'market_price_url_compuzone' => $this->marketPriceUrlRules('compuzone'),
            'market_price_url_pcfactory' => $this->marketPriceUrlRules('pcfactory'),
            'safety_stock' => 'nullable|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0', // 재고 직접 수정 — 다르면 조정(adjust) 이력 기록 (세트 제외)
            'group_id' => 'sometimes|nullable|integer|exists:product_groups,id', // null 전달 시 그룹에서 제외
            'option_name' => 'sometimes|nullable|string|max:60',
            'memo' => 'nullable|string',
            'show_in_estimate' => 'boolean',
            'is_bundle' => 'boolean',
            'bundle_items' => 'required_if:is_bundle,1|nullable|array|max:50',
            'bundle_items.*.product_id' => 'required|integer|exists:products,id',
            'bundle_items.*.quantity' => 'required|integer|min:1|max:999',
        ]);
        $stockQuantity = $validated['stock_quantity'] ?? null;
        unset($validated['stock_quantity']);
        if (array_key_exists('group_id', $validated) && $validated['group_id'] === null) {
            $validated['option_name'] = null; // 그룹 제외 시 옵션명도 정리
        }
        $marketUrls = $this->pullMarketUrls($validated);
        $bundleItems = $this->pullBundleItems($request, $validated, $product);
        if ($bundleItems instanceof JsonResponse) {
            return $bundleItems;
        }

        // 세트 전환 검증 — 다른 세트의 구성품이면 세트가 될 수 없음 (중첩 방지)
        $becomingBundle = $request->boolean('is_bundle');
        if (! $product->is_bundle && $becomingBundle) {
            $parentNames = Product::whereIn('id', $product->bundledIn()->pluck('bundle_product_id'))->pluck('name');
            if ($parentNames->isNotEmpty()) {
                return response()->json(['message' => '다른 세트의 구성품이라 세트로 전환할 수 없습니다.'."\n포함된 세트: ".$parentNames->implode(', ')], 422);
            }
        }

        try {
            $wasBundle = $product->is_bundle;
            $validated['show_in_estimate'] = $request->boolean('show_in_estimate');
            $validated['is_bundle'] = $becomingBundle;
            $cat = ProductCategory::findOrFail($validated['category_id']);

            // NOT NULL 컬럼들은 null을 0으로 강제 (제공된 키에 한해서만 — sometimes 검증과 일관)
            if (array_key_exists('purchase_price', $validated)) {
                $validated['purchase_price'] = (int) ($validated['purchase_price'] ?? 0);
            }
            if (array_key_exists('sale_price', $validated)) {
                $validated['sale_price'] = (int) ($validated['sale_price'] ?? 0);
            }
            if (array_key_exists('safety_stock', $validated)) {
                $validated['safety_stock'] = (int) ($validated['safety_stock'] ?? 0);
            }

            // 카테고리 변경 시 SKU 재생성
            if ($product->category_id !== (int) $validated['category_id']) {
                $validated['sku'] = $this->generateSku($cat);
            }

            $validated['category'] = $cat->name;
            $product->update($validated);

            // 세트 ↔ 일반 전환 시 재고/구성 정리
            if ($wasBundle !== $product->is_bundle) {
                if ($product->is_bundle) {
                    // 일반 → 세트: 자체 재고를 0으로 정리(조정 이력 기록) 후 inventory 제거
                    if ($product->inventory) {
                        $this->adjustStockTo($product, 0, "세트 전환 — 자체 재고 정리 ({$product->name})");
                        $product->inventory()->delete();
                        $product->unsetRelation('inventory');
                    }
                } else {
                    // 세트 → 일반: 구성품 정의 삭제, 자체 재고 0부터 시작
                    $product->bundleItems()->delete();
                    Inventory::firstOrCreate(
                        ['product_id' => $product->id],
                        ['quantity' => 0, 'last_updated_at' => now()]
                    );
                }
            }

            if ($stockQuantity !== null && ! $product->is_bundle) {
                $this->adjustStockTo($product, (int) $stockQuantity, '제품 수정에서 재고 조정');
            }

            $this->syncBundleItems($product, $bundleItems);
            $this->syncMarketPriceUrls($product, $marketUrls);

            return response()->json($product->load('inventory', 'categoryRelation', 'marketPrices', 'bundleItems.component.inventory'));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '제품 수정 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ], 500);
        }
    }

    /**
     * 시세 수동 갱신 — 등록된 모든 판매처(컴퓨존/피씨팩토리)를 순차 조회.
     * 전부 실패하면 422, 하나라도 성공하면 200 (실패 사유는 판매처별 error에 남음).
     */
    public function refreshMarketPrice(Product $product, MarketPriceCrawler $crawler): JsonResponse
    {
        $rows = $product->marketPrices;
        if ($rows->isEmpty()) {
            return response()->json(['message' => '시세 URL이 등록되지 않은 제품입니다.'], 422);
        }

        $ok = 0;
        $errors = [];
        foreach ($rows as $row) {
            if ($crawler->refresh($row)) {
                $ok++;
            } else {
                $errors[] = MarketPriceCrawler::vendorLabel($row->vendor).': '.($row->error ?? '실패');
            }
        }

        $fresh = $product->fresh()->load('inventory', 'categoryRelation', 'marketPrices');
        if ($ok === 0) {
            return response()->json(['message' => implode("\n", $errors), 'product' => $fresh], 422);
        }

        return response()->json($fresh);
    }

    /**
     * 입출고 내역 삭제 (관리자 이상) — ids 선택 삭제 또는 all=1 전체 비우기.
     * 삭제 후 영향받은 제품의 재고를 남은 이력으로 재계산한다
     * (이력이 하나도 없으면 0 — 전체 비우기 시 모든 재고가 0으로 리셋).
     */
    public function destroyMovements(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403, '입출고 내역 삭제는 관리자만 가능합니다.');

        $validated = $request->validate([
            'ids' => 'required_without:all|nullable|array|min:1|max:500',
            'ids.*' => 'integer|exists:stock_movements,id',
            'all' => 'nullable|boolean',
        ]);

        $all = ! empty($validated['all']);
        $affectedIds = $all
            ? StockMovement::distinct()->pluck('product_id')
            : StockMovement::whereIn('id', $validated['ids'])->distinct()->pluck('product_id');

        $deleted = DB::transaction(function () use ($all, $validated, $affectedIds) {
            $count = $all
                ? StockMovement::query()->delete()
                : StockMovement::whereIn('id', $validated['ids'])->delete();

            foreach ($affectedIds as $productId) {
                $this->recalculateStockFromMovements((int) $productId);
            }

            return $count;
        });

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    /**
     * 남은 입출고 이력을 시간순으로 재생해 재고 수량을 다시 계산.
     * in/return은 +, out은 -, adjust는 절대값(quantity_after)으로 확정.
     */
    private function recalculateStockFromMovements(int $productId): void
    {
        // 세트 상품은 자체 재고 없음 — inventory 행을 만들지 않는다
        if (Product::withTrashed()->find($productId)?->is_bundle) {
            return;
        }

        $qty = 0;
        StockMovement::where('product_id', $productId)
            ->orderBy('created_at')->orderBy('id')
            ->get(['movement_type', 'quantity', 'quantity_after'])
            ->each(function ($m) use (&$qty) {
                $qty = match ($m->movement_type) {
                    'in', 'return' => $qty + $m->quantity,
                    'out' => $qty - $m->quantity,
                    'adjust' => (int) $m->quantity_after,
                    default => $qty,
                };
            });

        Inventory::updateOrCreate(
            ['product_id' => $productId],
            ['quantity' => $qty, 'last_updated_at' => now()]
        );
    }

    /** 재고를 목표 수량으로 조정 — 기존 수량과 다르면 adjust 이력을 남기고 갱신 (입출고 내역과 일관) */
    private function adjustStockTo(Product $product, int $target, string $memo): void
    {
        DB::transaction(function () use ($product, $target, $memo) {
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 0, 'last_updated_at' => now()]
            );
            if ($inventory->quantity === $target) {
                return;
            }
            StockMovement::create([
                'product_id' => $product->id,
                'movement_type' => 'adjust',
                'quantity' => abs($target - $inventory->quantity),
                'quantity_after' => $target,
                'user_id' => Auth::id(),
                'memo' => $memo,
            ]);
            $inventory->update(['quantity' => $target, 'last_updated_at' => now()]);
        });
    }

    /**
     * 세트 구성품 입력 검증 — 자기 자신/세트 중첩 금지. 오류 시 JsonResponse 반환.
     *
     * @return array<int, array{product_id:int, quantity:int}>|JsonResponse
     */
    private function pullBundleItems(Request $request, array &$validated, ?Product $product = null): array|JsonResponse
    {
        $items = collect($validated['bundle_items'] ?? [])
            ->map(fn ($i) => ['product_id' => (int) $i['product_id'], 'quantity' => (int) $i['quantity']])
            ->unique('product_id')->values();
        unset($validated['bundle_items']);

        if (! $request->boolean('is_bundle')) {
            return [];
        }
        if ($items->isEmpty()) {
            return response()->json(['message' => '세트 상품은 구성품을 1개 이상 등록해야 합니다.'], 422);
        }
        if ($product && $items->contains('product_id', $product->id)) {
            return response()->json(['message' => '세트에 자기 자신을 포함할 수 없습니다.'], 422);
        }
        $nested = Product::whereIn('id', $items->pluck('product_id'))->where('is_bundle', true)->pluck('name');
        if ($nested->isNotEmpty()) {
            return response()->json(['message' => '세트 안에 다른 세트를 포함할 수 없습니다: '.$nested->implode(', ')], 422);
        }

        return $items->all();
    }

    /** 세트 구성품 동기화 (세트가 아니면 전체 제거) */
    private function syncBundleItems(Product $product, array $items): void
    {
        if (! $product->is_bundle) {
            return;
        }
        DB::transaction(function () use ($product, $items) {
            $product->bundleItems()->delete();
            foreach (array_values($items) as $i => $item) {
                ProductBundleItem::create([
                    'bundle_product_id' => $product->id,
                    'component_product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'sort_order' => $i,
                ]);
            }
        });
    }

    public function destroyProduct(Product $product)
    {
        // 세트에 묶인 구성품은 삭제 차단 — 어떤 세트인지 안내
        $bundleNames = Product::whereIn('id', $product->bundledIn()->pluck('bundle_product_id'))->pluck('name');
        if ($bundleNames->isNotEmpty()) {
            return response()->json([
                'message' => "세트 상품에 포함된 제품이라 삭제할 수 없습니다.\n포함된 세트: ".$bundleNames->implode(', ')."\n세트에서 구성품을 먼저 제거해주세요.",
            ], 422);
        }

        $product->bundleItems()->delete(); // 세트 삭제 시 구성 정의도 정리
        $product->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    /**
     * 전체 편집 — 목록에서 인라인으로 고친 제품들을 일괄 저장.
     * 재고(stock_quantity)는 세트가 아닌 제품만, 값이 달라진 경우 조정 이력을 남긴다.
     */
    public function bulkEditProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.id' => 'required|integer|exists:products,id',
            'items.*.name' => 'sometimes|required|string|max:200',
            'items.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.sale_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.safety_stock' => 'sometimes|nullable|integer|min:0',
            'items.*.stock_quantity' => 'sometimes|nullable|integer|min:0',
        ], [
            'items.*.name.required' => '제품명은 비울 수 없습니다.',
        ]);

        $updated = 0;
        DB::transaction(function () use ($validated, &$updated) {
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);
                if (! $product) {
                    continue;
                }

                $fields = [];
                if (array_key_exists('name', $item)) {
                    $fields['name'] = $item['name'];
                }
                foreach (['purchase_price', 'sale_price', 'safety_stock'] as $f) {
                    if (array_key_exists($f, $item)) {
                        $fields[$f] = (int) ($item[$f] ?? 0);
                    }
                }
                if ($fields) {
                    $product->update($fields);
                }

                if (array_key_exists('stock_quantity', $item) && $item['stock_quantity'] !== null && ! $product->is_bundle) {
                    $this->adjustStockTo($product, (int) $item['stock_quantity'], "전체 편집 — 재고 조정 ({$product->name})");
                }
                $updated++;
            }
        });

        return response()->json(['message' => "{$updated}개 제품이 저장되었습니다.", 'count' => $updated]);
    }

    /**
     * 선택된 제품들의 show_in_estimate 일괄 변경
     */
    public function bulkSetEstimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
            'show_in_estimate' => 'required|boolean',
        ]);

        $count = Product::whereIn('id', $validated['ids'])
            ->update(['show_in_estimate' => $validated['show_in_estimate']]);

        return response()->json([
            'message' => "{$count}개 제품의 견적서 노출이 변경되었습니다.",
            'count' => $count,
        ]);
    }

    /**
     * 선택된 제품들 일괄 삭제
     */
    public function bulkDeleteProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        // 함께 삭제되지 않는 세트에 묶인 구성품이 있으면 차단
        $blocked = ProductBundleItem::whereIn('component_product_id', $validated['ids'])
            ->whereNotIn('bundle_product_id', $validated['ids'])
            ->with('bundle:id,name', 'component:id,name')->get();
        if ($blocked->isNotEmpty()) {
            $detail = $blocked->map(fn ($b) => ($b->component?->name ?? '?')." ← 세트 '".($b->bundle?->name ?? '?')."'")->unique()->implode("\n");

            return response()->json(['message' => "세트 상품에 포함된 제품이 있어 삭제할 수 없습니다.\n{$detail}"], 422);
        }

        ProductBundleItem::whereIn('bundle_product_id', $validated['ids'])->delete();
        $count = Product::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'message' => "{$count}개 제품이 삭제되었습니다.",
            'count' => $count,
        ]);
    }

    // === 견적서 제품 ===

    public function estimateProducts(Request $request)
    {
        $query = Product::with('inventory', 'categoryRelation', 'group')
            ->where('is_active', true)
            ->where('show_in_estimate', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $ids = $this->getCategoryDescendantIds((int) $categoryId);
            $query->whereIn('category_id', $ids);
        }

        $products = $query->orderBy('sku')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category,
                'category_id' => $p->category_id,
                'sale_price' => $p->sale_price,
                'purchase_price' => $p->purchase_price,
                'quantity' => $p->inventory?->quantity ?? 0,
                'safety_stock' => $p->safety_stock,
                'is_low' => $p->safety_stock && ($p->inventory?->quantity ?? 0) <= $p->safety_stock,
                // 옵션 그룹 — 견적서 패널에서 그룹 하나로 묶어 옵션 선택으로 추가
                'group_id' => $p->group_id,
                'group_name' => $p->group?->name,
                'option_name' => $p->option_name,
            ];
        });

        return response()->json($products);
    }

    // === 재고 현황 ===

    public function stock(Request $request)
    {
        $query = Product::with('inventory')
            ->where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->query('low_stock')) {
            $query->whereHas('inventory', function ($q) {
                $q->whereRaw('quantity <= (SELECT safety_stock FROM products WHERE products.id = inventories.product_id)');
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $query->whereIn('category_id', $this->getCategoryDescendantIds((int) $categoryId));
        }

        $mapRow = function ($p) {
            $qty = $p->inventory?->quantity ?? 0;

            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category,
                'quantity' => $qty,
                'safety_stock' => $p->safety_stock,
                'is_low' => $p->safety_stock && $qty <= $p->safety_stock,
                'purchase_price' => $p->purchase_price,
                'sale_price' => $p->sale_price,
            ];
        };

        // per_page가 있으면 페이지네이션 응답 (products와 동일한 형태)
        if ($perPage = (int) $request->query('per_page')) {
            $page = $query->orderBy('sku')->paginate(min(max($perPage, 1), 200));

            return response()->json([
                'data' => collect($page->items())->map($mapRow)->values(),
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ]);
        }

        return response()->json($query->orderBy('sku')->get()->map($mapRow));
    }

    // === 입출고 ===

    public function movements(Request $request)
    {
        $query = StockMovement::with('product', 'user', 'project')
            ->orderBy('created_at', 'desc');

        if ($type = $request->query('type')) {
            $query->where('movement_type', $type);
        }

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        // 제품명/SKU 검색 (삭제된 제품 포함 — 이력 보존)
        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->withTrashed()->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            });
        }

        return response()->json($query->limit(100)->get());
    }

    /**
     * 입출고 등록 모달의 스튜디오(프로젝트) 선택 드롭다운 용.
     */
    public function projectsForMovement(): JsonResponse
    {
        $projects = Project::select('id', 'name', 'status')
            ->whereNull('completed_at')
            ->orderBy('name')
            ->get();

        return response()->json($projects);
    }

    /**
     * 출고 대상 프로젝트를 찾기 위한 의뢰자 검색 — 캘린더처럼 의뢰자를 먼저 찾고
     * 연결된 프로젝트를 고르는 방식. 재고 권한만으로 쓸 수 있게 이름/닉네임과
     * 연결 프로젝트 목록만 반환한다 (연락처 등 상세 정보는 미포함).
     */
    public function clientsForMovement(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $clients = Client::where('status', '!=', 'blacklist')
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('nickname', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('contacts', function ($cq) use ($q) {
                        $cq->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
                    });
            })
            ->with(['projects' => fn ($p) => $p->select('id', 'client_id', 'name', 'stage')
                ->whereNull('completed_at')->orderByDesc('id')])
            ->limit(10)
            ->get(['id', 'name', 'nickname']);

        return response()->json($clients->map(fn (Client $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'nickname' => $c->nickname,
            'projects' => $c->projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stage_label' => $p->stageLabel(),
            ])->values(),
        ]));
    }

    public function storeMovement(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'movement_type' => 'required|in:in,out,adjust,return',
            'quantity' => 'required|integer|min:1',
            'project_id' => 'nullable|exists:projects,id',
            'memo' => 'nullable|string|max:500',
            'force' => 'nullable|boolean', // 구성품 재고 부족 경고 확인 후 진행
        ]);
        $force = (bool) ($validated['force'] ?? false);
        unset($validated['force']);

        // 세트 상품 — 구성품 재고를 함께 소진/복원 (출고·반품만 허용)
        $product = Product::with('bundleItems.component.inventory')->findOrFail($validated['product_id']);
        if ($product->is_bundle) {
            return $this->storeBundleMovement($product, $validated, $force);
        }

        return DB::transaction(function () use ($validated) {
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $validated['product_id']],
                ['quantity' => 0, 'last_updated_at' => now()]
            );

            $change = match ($validated['movement_type']) {
                'in', 'return' => $validated['quantity'],
                'out' => -$validated['quantity'],
                'adjust' => $validated['quantity'] - $inventory->quantity,
            };

            $newQty = $validated['movement_type'] === 'adjust'
                ? $validated['quantity']
                : $inventory->quantity + $change;

            $movement = StockMovement::create([
                ...$validated,
                'quantity' => abs($change),
                'quantity_after' => $newQty,
                'user_id' => Auth::id(),
            ]);

            $inventory->update([
                'quantity' => $newQty,
                'last_updated_at' => now(),
            ]);

            return response()->json($movement->load('product', 'user'), 201);
        });
    }

    /**
     * 세트 상품 입출고 — 구성품 각각에 출고/반품 이력을 생성해 재고를 동기화.
     * 부족 시 force 없이는 409 + shortages 목록으로 응답 (프론트에서 확인 후 재요청).
     */
    private function storeBundleMovement(Product $bundle, array $validated, bool $force): JsonResponse
    {
        if (! in_array($validated['movement_type'], ['out', 'return'], true)) {
            return response()->json(['message' => '세트 상품은 출고/반품만 등록할 수 있습니다. 입고·조정은 구성품에서 관리해주세요.'], 422);
        }
        if ($bundle->bundleItems->isEmpty()) {
            return response()->json(['message' => '구성품이 등록되지 않은 세트입니다. 제품 수정에서 구성품을 먼저 등록해주세요.'], 422);
        }

        $setQty = (int) $validated['quantity'];
        $isOut = $validated['movement_type'] === 'out';

        // 출고 시 부족 구성품 사전 점검 (경고 후 진행 허용 — force)
        if ($isOut && ! $force) {
            $shortages = $bundle->bundleItems->map(function ($item) use ($setQty) {
                $need = $item->quantity * $setQty;
                $have = (int) ($item->component?->inventory?->quantity ?? 0);

                return $have < $need ? [
                    'name' => $item->component?->name ?? '삭제된 제품',
                    'need' => $need,
                    'have' => $have,
                ] : null;
            })->filter()->values();

            if ($shortages->isNotEmpty()) {
                return response()->json([
                    'message' => '구성품 재고가 부족합니다.',
                    'shortages' => $shortages,
                ], 409);
            }
        }

        $movements = DB::transaction(function () use ($bundle, $validated, $setQty, $isOut) {
            $label = sprintf("세트 '%s' ×%d %s", $bundle->name, $setQty, $isOut ? '출고' : '반품');
            $userMemo = trim((string) ($validated['memo'] ?? ''));
            $rows = [];

            foreach ($bundle->bundleItems as $item) {
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $item->component_product_id],
                    ['quantity' => 0, 'last_updated_at' => now()]
                );
                $qty = $item->quantity * $setQty;
                $newQty = $inventory->quantity + ($isOut ? -$qty : $qty);

                $rows[] = StockMovement::create([
                    'product_id' => $item->component_product_id,
                    'movement_type' => $validated['movement_type'],
                    'quantity' => $qty,
                    'quantity_after' => $newQty,
                    'project_id' => $validated['project_id'] ?? null,
                    'memo' => $userMemo !== '' ? "{$label} — {$userMemo}" : $label,
                    'user_id' => Auth::id(),
                ]);
                $inventory->update(['quantity' => $newQty, 'last_updated_at' => now()]);
            }

            return $rows;
        });

        return response()->json([
            'ok' => true,
            'bundle' => $bundle->name,
            'movements' => collect($movements)->map(fn ($m) => $m->load('product'))->values(),
        ], 201);
    }

    // === 헬퍼 ===

    /**
     * 시세 URL 검증 규칙 — 해당 판매처 도메인만 허용.
     *
     * @return array<int, mixed>
     */
    private function marketPriceUrlRules(string $vendorKey): array
    {
        return ['nullable', 'string', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail) use ($vendorKey) {
            if ($value && ! app(MarketPriceCrawler::class)->urlMatchesVendor($value, $vendorKey)) {
                $fail(MarketPriceCrawler::vendorLabel($vendorKey).' 도메인('.MarketPriceCrawler::VENDORS[$vendorKey].') 주소만 등록할 수 있습니다.');
            }
        }];
    }

    /**
     * validated 배열에서 판매처별 시세 URL 입력을 분리해 반환.
     *
     * @return array<string, ?string> vendor => url|null
     */
    private function pullMarketUrls(array &$validated): array
    {
        $urls = [];
        foreach (array_keys(MarketPriceCrawler::VENDORS) as $vendor) {
            $key = 'market_price_url_'.$vendor;
            if (array_key_exists($key, $validated)) {
                $urls[$vendor] = trim((string) $validated[$key]) ?: null;
                unset($validated[$key]);
            }
        }

        return $urls;
    }

    /**
     * 판매처별 시세 URL 반영 — 비우면 행 삭제, 변경 시 시세/오류 리셋, 동일하면 유지.
     */
    private function syncMarketPriceUrls(Product $product, array $marketUrls): void
    {
        foreach ($marketUrls as $vendor => $url) {
            $row = $product->marketPrices()->where('vendor', $vendor)->first();

            if ($url === null) {
                $row?->delete();

                continue;
            }
            if ($row && $row->url === $url) {
                continue;
            }
            ProductMarketPrice::updateOrCreate(
                ['product_id' => $product->id, 'vendor' => $vendor],
                ['url' => $url, 'price' => null, 'checked_at' => null, 'error' => null]
            );
        }
    }

    /**
     * SKU 자동 생성 — 2차 카테고리의 코드를 베이스로 사용 (정책)
     * 예: PCSET-001, PCSET-002, ...  (3·4차 카테고리에 속해도 동일하게 PCSET 사용)
     */
    private function generateSku(ProductCategory $category): string
    {
        $prefix = $category->getSkuBaseCode();

        $lastProduct = Product::withTrashed()
            ->where('sku', 'like', "{$prefix}-%")
            ->where('sku', 'regexp', "^{$prefix}-[0-9]+$")
            ->orderByRaw("CAST(SUBSTRING_INDEX(sku, '-', -1) AS UNSIGNED) DESC")
            ->first();

        $nextNum = 1;
        if ($lastProduct) {
            $lastNum = (int) last(explode('-', $lastProduct->sku));
            $nextNum = $lastNum + 1;
        }

        return $prefix.'-'.str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }

    private function getCategoryDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = ProductCategory::where('parent_id', $categoryId)->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getCategoryDescendantIds($childId));
        }

        return $ids;
    }
}
