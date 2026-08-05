<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMarketPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketPriceTest extends TestCase
{
    use RefreshDatabase;

    private const COMPUZONE_URL = 'https://www.compuzone.co.kr/product/product_detail.htm?ProductNo=12345';

    private const PCFACTORY_URL = 'https://www.pc-factory.co.kr/shop/product_detail.html?pd_no=179677';

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

    private function addMarketRow(Product $product, string $vendor, string $url, array $extra = []): ProductMarketPrice
    {
        return ProductMarketPrice::create([
            'product_id' => $product->id,
            'vendor' => $vendor,
            'url' => $url,
            ...$extra,
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

    private function fakeCompuzone(string $html): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response($html)]);
    }

    private function refreshEndpoint(Product $product)
    {
        return $this->actingAs($this->master())
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price");
    }

    // === URL 검증/저장 ===

    public function test_market_price_url_rejects_non_vendor_host(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url_compuzone' => 'https://www.coupang.com/vp/products/123',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('market_price_url_compuzone');
    }

    public function test_market_price_url_rejects_vendor_mismatch(): void
    {
        $product = $this->makeProduct();

        // 컴퓨존 칸에 피씨팩토리 주소 → 거부
        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url_compuzone' => self::PCFACTORY_URL,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('market_price_url_compuzone');
    }

    public function test_both_vendor_urls_save_as_rows(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url_compuzone' => self::COMPUZONE_URL,
                'market_price_url_pcfactory' => self::PCFACTORY_URL,
            ]))
            ->assertOk();

        $rows = $product->fresh()->marketPrices;
        $this->assertCount(2, $rows);
        $this->assertSame(self::COMPUZONE_URL, $rows->firstWhere('vendor', 'compuzone')->url);
        $this->assertSame(self::PCFACTORY_URL, $rows->firstWhere('vendor', 'pcfactory')->url);
    }

    public function test_clearing_url_deletes_row_and_changing_resets_price(): void
    {
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL, ['price' => 111000, 'checked_at' => now()]);
        $this->addMarketRow($product, 'pcfactory', self::PCFACTORY_URL, ['price' => 222000, 'checked_at' => now()]);

        // 컴퓨존은 비우고(삭제), 피씨팩토리는 URL 변경(시세 리셋)
        $this->actingAs($this->master())
            ->patchJson("/api/inventory/products/{$product->id}", $this->updatePayload($product, [
                'market_price_url_compuzone' => null,
                'market_price_url_pcfactory' => self::PCFACTORY_URL.'&x=2',
            ]))
            ->assertOk();

        $rows = $product->fresh()->marketPrices;
        $this->assertCount(1, $rows);
        $pcf = $rows->firstWhere('vendor', 'pcfactory');
        $this->assertNull($pcf->price);
        $this->assertNull($pcf->checked_at);
    }

    // === 시세 갱신 ===

    public function test_refresh_updates_both_vendors(): void
    {
        Http::fake([
            '*compuzone.co.kr*' => Http::response('<html><head><meta charset="utf-8"><meta property="product:price:amount" content="949000"></head><body></body></html>'),
            '*pc-factory.co.kr*' => Http::response('<html><head><meta charset="utf-8"><meta property="og:price" content="940000"></head><body></body></html>'),
        ]);
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL);
        $this->addMarketRow($product, 'pcfactory', self::PCFACTORY_URL);

        $this->refreshEndpoint($product)->assertOk();

        $rows = $product->fresh()->marketPrices;
        $this->assertSame(949000, $rows->firstWhere('vendor', 'compuzone')->price);
        $this->assertSame(940000, $rows->firstWhere('vendor', 'pcfactory')->price);
        // 매입가/판매가는 절대 건드리지 않음
        $this->assertSame(100000, $product->fresh()->purchase_price);
        $this->assertSame(150000, $product->fresh()->sale_price);
    }

    public function test_refresh_partial_failure_returns_ok_with_error_stored(): void
    {
        Http::fake([
            '*compuzone.co.kr*' => Http::response('<html><head><meta charset="utf-8"><meta property="product:price:amount" content="949000"></head><body></body></html>'),
            '*pc-factory.co.kr*' => Http::response('blocked', 403),
        ]);
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL);
        $this->addMarketRow($product, 'pcfactory', self::PCFACTORY_URL, ['price' => 930000]);

        $this->refreshEndpoint($product)->assertOk(); // 하나라도 성공하면 200

        $rows = $product->fresh()->marketPrices;
        $this->assertSame(949000, $rows->firstWhere('vendor', 'compuzone')->price);
        $pcf = $rows->firstWhere('vendor', 'pcfactory');
        $this->assertSame(930000, $pcf->price); // 실패 시 기존 시세 유지
        $this->assertStringContainsString('403', $pcf->error);
    }

    public function test_refresh_all_failed_returns_422(): void
    {
        Http::fake(['*compuzone.co.kr*' => Http::response('blocked', 403)]);
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL);

        $this->refreshEndpoint($product)->assertUnprocessable();
    }

    public function test_refresh_without_rows_returns_422(): void
    {
        $product = $this->makeProduct();

        $this->refreshEndpoint($product)->assertUnprocessable();
    }

    public function test_refresh_requires_edit_permission(): void
    {
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->postJson("/api/inventory/products/{$product->id}/refresh-market-price")
            ->assertForbidden();
    }

    // === 파싱 (컴퓨존 실페이지 구조 회귀) ===

    private function assertParsedPrice(string $html, int $expected): void
    {
        $this->fakeCompuzone($html);
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL);

        $this->refreshEndpoint($product)->assertOk();
        $this->assertSame($expected, $product->fresh()->marketPrices->first()->price);
    }

    public function test_parses_keyword_price_text(): void
    {
        $this->assertParsedPrice(
            '<html><head><meta charset="utf-8"></head><body><div>판매가: 2,350,000원</div></body></html>',
            2350000
        );
    }

    public function test_parses_euc_kr_page(): void
    {
        $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=euc-kr"></head>'
            .'<body><div>판매가 <b>987,000</b> 원 안내</div><div class="prc_t">987,000</div></body></html>';
        $this->assertParsedPrice(mb_convert_encoding($html, 'EUC-KR', 'UTF-8'), 987000);
    }

    public function test_parses_js_price_variable(): void
    {
        $this->assertParsedPrice(
            '<html><head><meta charset="utf-8"></head><body><script>function t(){ var produc_price = "949000"; }</script></body></html>',
            949000
        );
    }

    public function test_decodes_entity_price_and_skips_hidden_decoy(): void
    {
        $this->assertParsedPrice(
            '<html><head><meta charset="utf-8"></head><body>'
            .'<div class="pd info_price"><h3 style="top: 29px;">판매가</h3>'
            .'<div class="ct price_inner"><div class="inner_top"></div>'
            .'<div class="price_real"><div style="display:none;">944,500</div>'
            .'&#57;&#52;&#57;,&#48;&#48;&#48;<span class=\'unit\'>원</span></div>'
            .'</div></div></body></html>',
            949000
        );
    }

    public function test_skips_addon_product_widget_price(): void
    {
        $widget = '<!-- 클릭 시 하단 선택한 상품에 리스트 추가 --> <span class="pdtl_sel_info">'
            .'<span class="pdtl_txt">[INTEL] 인텔 Laminar RM1 [CPU쿨러]</span>'
            .'<span class="price">13,200원<span class=\'unit\'></span></span></span>';
        $mainPrice = '<table><tr><th>판매가격</th>'
            .str_repeat('<td class="pad_cell" style="width:10px"></td>', 10)
            .'<td><em id="won_pd">899,000</em>원</td></tr></table>';
        $this->assertParsedPrice(
            '<html><head><meta charset="utf-8"></head><body>'.$widget.$mainPrice.'</body></html>',
            899000
        );
    }

    public function test_skips_mileage_point_amounts(): void
    {
        $this->assertParsedPrice(
            '<html><head><meta charset="utf-8"></head><body>'
            .'<div class="point_price"><span>13,200</span></div>'
            .'<div>적립가격 13,200원</div>'
            .str_repeat('<div>스펙 안내</div>', 20)
            .'<div class="prc_t">880,000</div>'
            .'</body></html>',
            880000
        );
    }

    public function test_pcfactory_refresh_parses_price(): void
    {
        Http::fake(['*pc-factory.co.kr*' => Http::response(
            '<html><head><meta charset="utf-8"></head><body><div class="price_view">판매가 <strong>1,150,000</strong>원</div></body></html>'
        )]);
        $product = $this->makeProduct();
        $this->addMarketRow($product, 'pcfactory', self::PCFACTORY_URL);

        $this->refreshEndpoint($product)->assertOk();
        $this->assertSame(1150000, $product->fresh()->marketPrices->first()->price);
    }

    // === 커맨드 ===

    public function test_command_refreshes_vendor_rows(): void
    {
        Http::fake([
            '*compuzone.co.kr*' => Http::response('<html><head><meta charset="utf-8"><meta property="product:price:amount" content="777000"></head><body></body></html>'),
            '*pc-factory.co.kr*' => Http::response('<html><head><meta charset="utf-8"><meta property="og:price" content="760000"></head><body></body></html>'),
        ]);
        $product = $this->makeProduct();
        $czRow = $this->addMarketRow($product, 'compuzone', self::COMPUZONE_URL);
        $pcfRow = $this->addMarketRow($product, 'pcfactory', self::PCFACTORY_URL);
        $noUrl = $this->makeProduct(['sku' => 'PART-002']);

        $this->artisan('products:refresh-market-prices', ['--sleep' => 0])
            ->expectsOutputToContain('성공 2건')
            ->assertSuccessful();

        $this->assertSame(777000, $czRow->fresh()->price);
        $this->assertSame(760000, $pcfRow->fresh()->price);
        $this->assertCount(0, $noUrl->fresh()->marketPrices);
    }
}
