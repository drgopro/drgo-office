<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 품목 단가 동기화 — 제품 관리에서 판매가가 바뀌면 결제/발행 전
 * 견적서에는 열람 시 반영되고, 발행·결제 완료 시점 가격은 아카이브된다.
 */
class EstimatePriceSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->product = Product::create([
            'sku' => 'CAM-100', 'name' => 'EOS R50', 'category' => '비디오',
            'purchase_price' => 800000, 'sale_price' => 1000000,
            'is_active' => true, 'show_in_estimate' => true,
        ]);
    }

    private function makeEstimate(string $status, array $overrides = []): Estimate
    {
        return Estimate::create([
            'status' => $status,
            'product_items' => [
                ['product_id' => $this->product->id, 'name' => 'EOS R50', 'category' => '비디오',
                    'purchase_price' => 800000, 'sale_price' => 1000000, 'qty' => 2, 'subtotal' => 2000000],
                ['product_id' => null, 'name' => '수동 항목', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000, 'manual' => true],
            ],
            'service_items' => [['name' => '설치비', 'amount' => 100000]],
            'product_total' => 2050000, 'service_total' => 100000, 'total_amount' => 2150000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
            ...$overrides,
        ]);
    }

    public function test_price_change_syncs_on_builder_open_before_payment(): void
    {
        $estimate = $this->makeEstimate('created');
        $this->product->update(['sale_price' => 1200000, 'purchase_price' => 900000]);

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk();

        $fresh = $estimate->fresh();
        $item = $fresh->product_items[0];
        $this->assertSame(1200000, (int) $item['sale_price']);
        $this->assertSame(900000, (int) $item['purchase_price']);
        $this->assertSame(2400000, (int) $item['subtotal']); // 새 단가 × 수량 2
        // 수동 항목은 그대로
        $this->assertSame(50000, (int) $fresh->product_items[1]['sale_price']);
        // 합계 재계산 (제품 2,450,000 + 서비스 100,000)
        $this->assertSame(2450000, (int) $fresh->product_total);
        $this->assertSame(2550000, (int) $fresh->total_amount);
    }

    public function test_price_change_syncs_on_print_and_public_view(): void
    {
        $estimate = $this->makeEstimate('completed');
        $this->product->update(['sale_price' => 1100000]);

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()->assertSee('1,100,000');
        $this->assertSame(1100000, (int) $estimate->fresh()->product_items[0]['sale_price']);

        // 공개 링크(비로그인)에서도 동일
        $this->product->update(['sale_price' => 1150000]);
        $url = parse_url($estimate->fresh()->publicUrl(), PHP_URL_PATH);
        $this->get($url)->assertOk();
        $this->assertSame(1150000, (int) $estimate->fresh()->product_items[0]['sale_price']);
    }

    public function test_issued_paid_cancelled_estimates_keep_archived_prices(): void
    {
        foreach (['issued', 'paid', 'cancelled'] as $status) {
            $estimate = $this->makeEstimate($status);
            $this->product->update(['sale_price' => 1300000 + rand(1, 9)]);

            $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk();

            $fresh = $estimate->fresh();
            $this->assertSame(1000000, (int) $fresh->product_items[0]['sale_price'], "{$status} 상태는 가격 고정이어야 함");
            $this->assertSame(2150000, (int) $fresh->total_amount);
        }
    }

    public function test_deleted_product_items_are_left_untouched(): void
    {
        $estimate = $this->makeEstimate('created');
        $this->product->delete();

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk();

        $this->assertSame(1000000, (int) $estimate->fresh()->product_items[0]['sale_price']);
    }

    public function test_bundle_component_prices_backfilled_even_when_locked(): void
    {
        // 세트 + 구성품 구성
        $mic = Product::create(['sku' => 'MIC-1', 'name' => '세트 마이크', 'category' => '오디오',
            'purchase_price' => 10000, 'sale_price' => 20000, 'is_active' => true, 'show_in_estimate' => false]);
        $set = Product::create(['sku' => 'SET-1', 'name' => '방송 세트', 'category' => '방송장비',
            'purchase_price' => 0, 'sale_price' => 150000, 'is_bundle' => true, 'is_active' => true, 'show_in_estimate' => true]);
        ProductBundleItem::create(['bundle_product_id' => $set->id, 'component_product_id' => $mic->id, 'quantity' => 2]);

        // 가격 필드가 없던 구버전 스냅샷 (결제 완료 = 가격 잠금 상태)
        $estimate = Estimate::create([
            'status' => 'paid',
            'product_items' => [[
                'product_id' => $set->id, 'name' => '방송 세트', 'category' => '방송장비',
                'sale_price' => 150000, 'qty' => 1, 'subtotal' => 150000,
                'bundle_items' => [['name' => '세트 마이크', 'qty' => 2]],
            ]],
            'service_items' => [], 'product_total' => 150000, 'service_total' => 0, 'total_amount' => 150000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $origUpdated = $estimate->updated_at;

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk();

        $fresh = $estimate->fresh();
        $item = $fresh->product_items[0];
        // 누락된 구성품 가격은 백필, 본 품목 단가·합계·updated_at(발행일시)은 불변
        $this->assertSame(20000, (int) $item['bundle_items'][0]['price']);
        $this->assertSame(150000, (int) $item['sale_price']);
        $this->assertSame(150000, (int) $fresh->total_amount);
        $this->assertTrue($fresh->updated_at->equalTo($origUpdated));

        // 잠금 상태에서 이미 기록된 구성품 가격은 제품가가 바뀌어도 불변
        $mic->update(['sale_price' => 99000]);
        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk();
        $this->assertSame(20000, (int) $estimate->fresh()->product_items[0]['bundle_items'][0]['price']);
    }
}
