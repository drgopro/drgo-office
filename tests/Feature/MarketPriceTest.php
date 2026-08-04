<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketPriceTest extends TestCase
{
    use RefreshDatabase;

    private const COMPUZONE_URL = 'https://www.compuzone.co.kr/product/product_detail.htm?ProductNo=12345';

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    private function makeProduct(array $attrs = []): Product
    {
        $cat = ProductCategory::firstOrCreate(
            ['code' => 'PART', 'parent_id' => null],
            ['name' => '부품', 'depth' => 1, 'sort_order' => 1]
        );

        return Product::create([
            'sku' => $attrs['sku'] ?? 'PART-001',
            'name' => '테스트 제품',
            'category' => '부품',
            'category_id' => $cat->id,
            'purchase_price' => 100000,
            'sale_price' => 150000,
            'safety_stock' => 0,
            'is_active' => true,
            'show_in_estimate' => false,
            ...$attrs,
        ]);
    }

    private function updatePayload(Product $product, array $overrides = []): array
    {
        return [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'purchase_price' => $product->purchase_price,
            'sale_price' => $product->sale_price,
            'safety_stock' => $product->safety_stock,
            'show_in_estimate' => $product->show_in_estimate,
            ...$overrides,
        ];
    }

    // === URL 검증 ===

    public function test_market_price_url_rejects_non_compuzone_host(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url' => 'https://www.coupang.com/vp/products/123',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('market_price_url');
    }

    public function test_market_price_url_saves_on_update(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url' => self::COMPUZONE_URL,
            ]))
            ->assertOk();

        $this->assertSame(self::COMPUZONE_URL, $product->fresh()->market_price_url);
    }

    public function test_clearing_url_resets_market_price_fields(): void
    {
        $product = $this->makeProduct([
            'market_price_url' => self::COMPUZONE_URL,
            'market_price' => 123000,
            'market_price_checked_at' => now(),
            'market_price_error' => null,
        ]);

        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url' => null,
            ]))
            ->assertOk();

        $fresh = $product->fresh();
        $this->assertNull($fresh->market_price_url);
        $this->assertNull($fresh->market_price);
        $this->assertNull($fresh->market_price_checked_at);
    }

    // === 시세 갱신(파싱) ===

    public function test_refresh_parses_og_price_meta(): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"><meta property="product:price:amount" content="1540000"></head><body></body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk()
            ->assertJsonPath('market_price', 1540000);

        $fresh = $product->fresh();
        $this->assertSame(1540000, $fresh->market_price);
        $this->assertNull($fresh->market_price_error);
        $this->assertNotNull($fresh->market_price_checked_at);
        // 매입가/판매가는 절대 건드리지 않음
        $this->assertSame(100000, $fresh->purchase_price);
        $this->assertSame(150000, $fresh->sale_price);
    }

    public function test_refresh_parses_keyword_price_text(): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body><div>판매가: 2,350,000원</div></body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(2350000, $product->fresh()->market_price);
    }

    public function test_refresh_parses_euc_kr_page(): void
    {
        $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=euc-kr"></head>'
            .'<body><div>판매가 <b>987,000</b> 원 안내</div><div class="prc_t">987,000</div></body></html>';
        Http::fake(['*compuzone.co.kr*' => Http::response(
            mb_convert_encoding($html, 'EUC-KR', 'UTF-8'), 200, ['Content-Type' => 'text/html; charset=euc-kr']
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(987000, $product->fresh()->market_price);
    }

    public function test_refresh_skips_mileage_point_amounts(): void
    {
        // 적립 포인트(13,200)가 판매가(880,000)보다 HTML상 먼저 나오는 페이지
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body>'
            .'<div class="point_price"><span>13,200</span></div>'
            .'<div>적립가격 13,200원</div>'
            .str_repeat('<div>스펙 안내</div>', 20)
            .'<div class="prc_t">880,000</div>'
            .'</body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(880000, $product->fresh()->market_price);
    }

    public function test_refresh_skips_prc_element_near_mileage_label(): void
    {
        // 숫자는 prc 클래스 요소에 있지만 바로 앞 형제 요소에 적립 라벨이 있는 구조
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body>'
            .'<dl><dt>적립 포인트</dt><dd class="prc_p">13,200</dd></dl>'
            .str_repeat('<div>제품 상세 설명</div>', 20)
            .'<div><em>판매가</em> <strong class="prc_c">1,320,000</strong></div>'
            .'</body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(1320000, $product->fresh()->market_price);
    }

    public function test_refresh_skips_addon_product_widget_price(): void
    {
        // 실제 오탐 사례 재현: 추가구성(선택상품) 위젯의 CPU쿨러 가격(13,200)이 먼저 나오고,
        // 본품 판매가는 마크업 거리가 먼 가격 테이블에 있는 구조
        $widget = '<!-- 클릭 시 하단 선택한 상품에 리스트 추가 --> <span class="pdtl_sel_info">'
            .'<span class="pdtl_txt">[INTEL] 인텔 Laminar RM1 [CPU쿨러]</span>'
            .'<span class="price">13,200원<span class=\'unit\'></span></span></span>';
        $mainPrice = '<table><tr><th>판매가격</th>'
            .str_repeat('<td class="pad_cell" style="width:10px"></td>', 10)
            .'<td><em id="won_pd">899,000</em>원</td></tr></table>';
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body>'.$widget.$mainPrice.'</body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(899000, $product->fresh()->market_price);
    }

    public function test_refresh_reads_price_from_js_variable(): void
    {
        // 컴퓨존 실제 구조: 표시 가격은 엔티티로 숨기고 JS에 평문 가격이 존재
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body><script>'
            .'function every_total_price(){ var produc_price = "949000"; }'
            .'</script></body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(949000, $product->fresh()->market_price);
    }

    public function test_refresh_decodes_entity_price_and_skips_hidden_decoy(): void
    {
        // 컴퓨존 안티 크롤링 실제 구조: display:none 미끼(944,500) + 엔티티 인코딩 실가격(949,000)
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body>'
            .'<div class="pd info_price"><h3 style="top: 29px;">판매가</h3>'
            .'<div class="ct price_inner"><div class="inner_top"></div>'
            .'<div class="price_real"><div style="display:none;">944,500</div>'
            .'&#57;&#52;&#57;,&#48;&#48;&#48;<span class=\'unit\'>원</span></div>'
            .'</div></div></body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(949000, $product->fresh()->market_price);
    }

    public function test_refresh_allows_tags_between_price_and_won(): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body><div>판매가 <span class="big">1,299,000</span>원</div></body></html>'
        )]);
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertOk();

        $this->assertSame(1299000, $product->fresh()->market_price);
    }

    public function test_refresh_blocked_403_keeps_existing_price_and_stores_error(): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response('blocked', 403)]);
        $product = $this->makeProduct([
            'market_price_url' => self::COMPUZONE_URL,
            'market_price' => 999000,
        ]);

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertUnprocessable();

        $fresh = $product->fresh();
        $this->assertSame(999000, $fresh->market_price); // 기존 시세 유지
        $this->assertNotNull($fresh->market_price_error);
        $this->assertStringContainsString('403', $fresh->market_price_error);
    }

    public function test_refresh_without_url_returns_422(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertUnprocessable();
    }

    public function test_refresh_requires_edit_permission(): void
    {
        $product = $this->makeProduct(['market_price_url' => self::COMPUZONE_URL]);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertForbidden();
    }

    // === 커맨드 ===

    public function test_command_refreshes_registered_products(): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"><meta property="product:price:amount" content="777000"></head><body></body></html>'
        )]);
        $a = $this->makeProduct(['sku' => 'PART-001', 'market_price_url' => self::COMPUZONE_URL]);
        $b = $this->makeProduct(['sku' => 'PART-002', 'market_price_url' => self::COMPUZONE_URL.'&x=2']);
        $noUrl = $this->makeProduct(['sku' => 'PART-003']);

        $this->artisan('products:refresh-market-prices', ['--sleep' => 0])
            ->expectsOutputToContain('성공 2건')
            ->assertSuccessful();

        $this->assertSame(777000, $a->fresh()->market_price);
        $this->assertSame(777000, $b->fresh()->market_price);
        $this->assertNull($noUrl->fresh()->market_price);
    }
}
