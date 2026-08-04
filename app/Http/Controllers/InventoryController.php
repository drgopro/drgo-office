<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\CompuzoneClient;
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

        $query = Product::with('inventory', 'categoryRelation')
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

        return response()->json(
            $query->orderBy('sku')->get()
        );
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|exists:product_categories,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'market_price_url' => $this->marketPriceUrlRules(),
            'safety_stock' => 'nullable|integer|min:0',
            'memo' => 'nullable|string',
            'show_in_estimate' => 'boolean',
        ]);

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
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'quantity' => 0,
                'last_updated_at' => now(),
            ]);

            return response()->json($product->load('inventory', 'categoryRelation'), 201);
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
            'market_price_url' => $this->marketPriceUrlRules(),
            'safety_stock' => 'nullable|integer|min:0',
            'memo' => 'nullable|string',
            'show_in_estimate' => 'boolean',
        ]);

        try {
            $validated['show_in_estimate'] = $request->boolean('show_in_estimate');
            $cat = ProductCategory::findOrFail($validated['category_id']);

            // 시세 URL 비움/변경 시 기존 시세 정보 리셋 (다른 제품 가격이 남지 않도록)
            if (array_key_exists('market_price_url', $validated)) {
                $newUrl = $validated['market_price_url'] ?: null;
                $validated['market_price_url'] = $newUrl;
                if ($newUrl !== $product->market_price_url) {
                    $validated['market_price'] = null;
                    $validated['market_price_checked_at'] = null;
                    $validated['market_price_error'] = null;
                }
            }

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

            return response()->json($product->load('inventory', 'categoryRelation'));
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
     * 컴퓨존 시세 수동 갱신 — 성공 시 갱신된 제품, 실패 시 사유와 함께 422.
     */
    public function refreshMarketPrice(Product $product, CompuzoneClient $compuzone): JsonResponse
    {
        if (! $product->market_price_url) {
            return response()->json(['message' => '시세 URL이 등록되지 않은 제품입니다.'], 422);
        }

        if (! $compuzone->refresh($product)) {
            return response()->json([
                'message' => $product->market_price_error ?? '시세 조회에 실패했습니다.',
                'product' => $product->fresh()->load('inventory', 'categoryRelation'),
            ], 422);
        }

        return response()->json($product->fresh()->load('inventory', 'categoryRelation'));
    }

    public function destroyProduct(Product $product)
    {
        $product->delete();

        return response()->json(['message' => '삭제되었습니다.']);
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

        $count = Product::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'message' => "{$count}개 제품이 삭제되었습니다.",
            'count' => $count,
        ]);
    }

    // === 견적서 제품 ===

    public function estimateProducts(Request $request)
    {
        $query = Product::with('inventory', 'categoryRelation')
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

        $products = $query->orderBy('sku')->get()->map(function ($p) {
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
        });

        return response()->json($products);
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

    public function storeMovement(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'movement_type' => 'required|in:in,out,adjust,return',
            'quantity' => 'required|integer|min:1',
            'project_id' => 'nullable|exists:projects,id',
            'memo' => 'nullable|string|max:500',
        ]);

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

    // === 헬퍼 ===

    /**
     * 시세 URL 검증 규칙 — 컴퓨존 도메인만 허용.
     *
     * @return array<int, mixed>
     */
    private function marketPriceUrlRules(): array
    {
        return ['nullable', 'string', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
            if ($value && ! app(CompuzoneClient::class)->isAllowedUrl($value)) {
                $fail('컴퓨존(compuzone.co.kr) 주소만 등록할 수 있습니다.');
            }
        }];
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
