<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(ProductCategory $cat, string $sku, string $name): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $cat->name,
            'category_id' => $cat->id,
            'purchase_price' => 10000,
            'sale_price' => 20000,
            'safety_stock' => 0,
            'is_active' => true,
            'show_in_estimate' => false,
        ]);
    }

    public function test_products_filter_by_top_category_includes_descendants(): void
    {
        $cpu = ProductCategory::create(['name' => 'CPU', 'code' => 'CPU', 'depth' => 1, 'sort_order' => 1]);
        $cpuIntel = ProductCategory::create(['name' => '인텔', 'code' => 'INT', 'depth' => 2, 'sort_order' => 1, 'parent_id' => $cpu->id]);
        $gpu = ProductCategory::create(['name' => 'GPU', 'code' => 'GPU', 'depth' => 1, 'sort_order' => 2]);

        $this->makeProduct($cpu, 'CPU-001', 'CPU 직속 제품');
        $this->makeProduct($cpuIntel, 'INT-001', '인텔 하위 제품');
        $this->makeProduct($gpu, 'GPU-001', '그래픽카드');

        $user = User::factory()->create(['role' => 'master']);

        // 1차 카테고리 필터 — 하위 카테고리 제품 포함, 다른 카테고리 제외
        $res = $this->actingAs($user)->getJson("/api/inventory/products?category_id={$cpu->id}");
        $res->assertOk();
        $skus = collect($res->json())->pluck('sku')->all();
        $this->assertEqualsCanonicalizing(['CPU-001', 'INT-001'], $skus);

        // 전체 (파라미터 없음) — 모든 제품
        $all = $this->actingAs($user)->getJson('/api/inventory/products');
        $all->assertOk()->assertJsonCount(3);
    }
}
