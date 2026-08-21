<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function seedProducts(int $count): ProductCategory
    {
        $cat = ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 1]);
        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'sku' => sprintf('PART-%03d', $i),
                'name' => "제품 {$i}",
                'category' => '부품',
                'category_id' => $cat->id,
                'purchase_price' => 1000,
                'sale_price' => 2000,
                'safety_stock' => 0,
                'is_active' => true,
                'show_in_estimate' => false,
            ]);
        }

        return $cat;
    }

    public function test_low_stock_filter_returns_only_low_products(): void
    {
        $cat = $this->seedProducts(3);
        $user = User::factory()->create(['role' => 'master']);

        // 1번 제품만 안전재고 이하 (재고 2 ≤ 안전재고 5), 2번은 여유, 3번은 안전재고 미설정
        Product::where('sku', 'PART-001')->update(['safety_stock' => 5]);
        Product::where('sku', 'PART-002')->update(['safety_stock' => 5]);
        Inventory::create(['product_id' => Product::where('sku', 'PART-001')->first()->id, 'quantity' => 2]);
        Inventory::create(['product_id' => Product::where('sku', 'PART-002')->first()->id, 'quantity' => 100]);

        $res = $this->actingAs($user)->getJson('/api/inventory/products?per_page=20&low_stock=1');
        $res->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('PART-001', $res->json('data.0.sku'));
    }

    public function test_per_page_returns_paginated_response(): void
    {
        $this->seedProducts(25);
        $user = User::factory()->create(['role' => 'master']);

        $res = $this->actingAs($user)->getJson('/api/inventory/products?per_page=10&page=2');
        $res->assertOk()
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(10, 'data');

        // SKU 정렬 기준 2페이지 첫 항목 = PART-011
        $this->assertSame('PART-011', $res->json('data.0.sku'));
    }

    public function test_pagination_combines_with_category_filter(): void
    {
        $cat = $this->seedProducts(5);
        $other = ProductCategory::create(['name' => '기타', 'code' => 'ETC', 'depth' => 1, 'sort_order' => 2]);
        Product::create([
            'sku' => 'ETC-001', 'name' => '기타 제품', 'category' => '기타', 'category_id' => $other->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        $user = User::factory()->create(['role' => 'master']);

        $res = $this->actingAs($user)->getJson("/api/inventory/products?per_page=10&category_id={$cat->id}");
        $res->assertOk()->assertJsonPath('total', 5)->assertJsonCount(5, 'data');
    }

    public function test_stock_supports_pagination_and_category_filter(): void
    {
        $cat = $this->seedProducts(15);
        $other = ProductCategory::create(['name' => '기타', 'code' => 'ETC', 'depth' => 1, 'sort_order' => 2]);
        Product::create([
            'sku' => 'ETC-001', 'name' => '기타 제품', 'category' => '기타', 'category_id' => $other->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        $user = User::factory()->create(['role' => 'master']);

        // 페이지네이션 — 2페이지에 나머지 6개 (15+1=16개, 10개씩)
        $res = $this->actingAs($user)->getJson('/api/inventory/stock?per_page=10&page=2');
        $res->assertOk()
            ->assertJsonPath('total', 16)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(6, 'data');

        // 카테고리 필터 + 페이지네이션 결합
        $filtered = $this->actingAs($user)->getJson("/api/inventory/stock?per_page=10&category_id={$cat->id}");
        $filtered->assertOk()->assertJsonPath('total', 15)->assertJsonCount(10, 'data');

        // per_page 없으면 기존 배열 응답 유지
        $plain = $this->actingAs($user)->getJson('/api/inventory/stock');
        $plain->assertOk()->assertJsonCount(16);
    }

    public function test_without_per_page_returns_plain_array(): void
    {
        $this->seedProducts(3);
        $user = User::factory()->create(['role' => 'master']);

        // 기존 호출부 호환 — per_page 없으면 배열 그대로
        $res = $this->actingAs($user)->getJson('/api/inventory/products');
        $res->assertOk()->assertJsonCount(3);
        $this->assertArrayNotHasKey('total', $res->json());
    }

    // === 재고 수량 필터 (zero / gte / lte, 세트는 조립 가능 수 기준) ===

    public function test_stock_quantity_filters(): void
    {
        $cat = $this->seedProducts(3); // PART-001~003
        $user = User::factory()->create(['role' => 'master']);
        $products = Product::orderBy('sku')->get();
        // 재고: 001=0(인벤토리 없음), 002=3, 003=10
        Inventory::create(['product_id' => $products[1]->id, 'quantity' => 3, 'last_updated_at' => now()]);
        Inventory::create(['product_id' => $products[2]->id, 'quantity' => 10, 'last_updated_at' => now()]);

        $names = fn (string $qs) => collect($this->actingAs($user)
            ->getJson('/api/inventory/products?per_page=20&'.$qs)->assertOk()->json('data'))->pluck('sku')->all();

        $this->assertSame(['PART-001'], $names('stock_op=zero'));
        $this->assertSame(['PART-002', 'PART-003'], $names('stock_op=gte&stock_val=3'));
        $this->assertSame(['PART-001', 'PART-002'], $names('stock_op=lte&stock_val=5'));
    }

    public function test_stock_filter_uses_buildable_count_for_bundles(): void
    {
        $cat = $this->seedProducts(2); // 구성품 2개
        $user = User::factory()->create(['role' => 'master']);
        $parts = Product::orderBy('sku')->get();
        Inventory::create(['product_id' => $parts[0]->id, 'quantity' => 6, 'last_updated_at' => now()]);
        Inventory::create(['product_id' => $parts[1]->id, 'quantity' => 9, 'last_updated_at' => now()]);

        // 세트: 구성품1 ×2, 구성품2 ×3 → 조립 가능 수 = min(6/2, 9/3) = 3
        $bundle = Product::create([
            'sku' => 'SET-001', 'name' => '세트', 'category' => '부품', 'category_id' => $cat->id,
            'purchase_price' => 0, 'sale_price' => 0, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false, 'is_bundle' => true,
        ]);
        $bundle->bundleItems()->create(['component_product_id' => $parts[0]->id, 'quantity' => 2]);
        $bundle->bundleItems()->create(['component_product_id' => $parts[1]->id, 'quantity' => 3]);

        $skus = fn (string $qs) => collect($this->actingAs($user)
            ->getJson('/api/inventory/products?per_page=20&'.$qs)->assertOk()->json('data'))->pluck('sku')->all();

        $this->assertContains('SET-001', $skus('stock_op=gte&stock_val=3')); // 조립가능 3 ≥ 3
        $this->assertNotContains('SET-001', $skus('stock_op=gte&stock_val=4'));
        $this->assertNotContains('SET-001', $skus('stock_op=zero'));
    }
}
