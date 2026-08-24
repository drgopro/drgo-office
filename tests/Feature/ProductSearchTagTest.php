<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 숨은 검색 태그 — 제품명에 없는 단어(브랜드 한글명 등)로도 검색 매칭 */
class ProductSearchTagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $cat = ProductCategory::create(['name' => '오디오', 'code' => 'AUD', 'depth' => 1, 'sort_order' => 1]);
        $this->product = Product::create([
            'sku' => 'AUD-001', 'name' => '[YAMAHA] AG06 MK2', 'category' => '오디오', 'category_id' => $cat->id,
            'purchase_price' => 274050, 'sale_price' => 315000, 'safety_stock' => 0,
            'search_tags' => '야마하, 오디오믹서, mixer', 'is_active' => true, 'show_in_estimate' => true,
        ]);
    }

    public function test_products_and_estimate_lists_match_by_hidden_tag(): void
    {
        // 제품명에 '야마하'가 없어도 태그로 매칭
        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/products?search='.urlencode('야마하'))->assertOk()->json();
        $this->assertCount(1, $rows);
        $this->assertSame($this->product->id, $rows[0]['id']);

        $est = $this->actingAs($this->admin)->getJson('/api/inventory/estimate-products?search='.urlencode('오디오믹서'))->assertOk()->json();
        $this->assertCount(1, $est);
        $this->assertSame('야마하, 오디오믹서, mixer', $est[0]['search_tags']); // 견적 패널 화면 내 필터용

        // 무관한 검색어는 미매칭
        $none = $this->actingAs($this->admin)->getJson('/api/inventory/products?search='.urlencode('소니'))->assertOk()->json();
        $this->assertCount(0, $none);
    }

    public function test_tags_can_be_saved_via_product_update(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$this->product->id}", [
            'name' => $this->product->name, 'category_id' => $this->product->category_id,
            'search_tags' => '야마하, yamaha, 믹서',
        ])->assertOk();

        $this->assertSame('야마하, yamaha, 믹서', $this->product->fresh()->search_tags);
    }
}
