<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index()
    {
        return view('inventory.index');
    }

    // === 카테고리 ===

    public function categories()
    {
        $categories = ProductCategory::with('children.children')
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
            if ($depth > 3) {
                return response()->json(['message' => '최대 3단계까지 가능합니다.'], 422);
            }
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

        $category->update($validated);

        return response()->json($category);
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
            'safety_stock' => 'nullable|integer|min:0',
            'memo' => 'nullable|string',
            'show_in_estimate' => 'boolean',
        ]);

        $cat = ProductCategory::findOrFail($validated['category_id']);
        $sku = $this->generateSku($cat);

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
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|exists:product_categories,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'memo' => 'nullable|string',
            'show_in_estimate' => 'boolean',
        ]);

        $validated['show_in_estimate'] = $request->boolean('show_in_estimate');
        $cat = ProductCategory::findOrFail($validated['category_id']);

        // 카테고리 변경 시 SKU 재생성
        if ($product->category_id !== (int) $validated['category_id']) {
            $validated['sku'] = $this->generateSku($cat);
        }

        $validated['category'] = $cat->name;
        $product->update($validated);

        return response()->json($product->load('inventory', 'categoryRelation'));
    }

    public function destroyProduct(Product $product)
    {
        $product->delete();

        return response()->json(['message' => '삭제되었습니다.']);
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

    private function generateSku(ProductCategory $category): string
    {
        $prefix = $category->getSkuPrefix();

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
