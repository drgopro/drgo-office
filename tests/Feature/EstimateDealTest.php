<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 특가/할인 표시 — 스냅샷 전용 가격 표시 (제품 관리 가격 불변) */
class EstimateDealTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    private function makeEstimate(): Estimate
    {
        return Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'total_amount' => 0, 'created_by' => $this->user->id,
        ]);
    }

    public function test_deal_fields_saved_in_snapshot(): void
    {
        $estimate = $this->makeEstimate();
        $items = [
            ['name' => '카메라', 'sale_price' => 990000, 'qty' => 1, 'subtotal' => 990000,
                'deal_type' => 'special', 'original_price' => 1200000],
            ['name' => '세팅비', 'sale_price' => 90000, 'qty' => 1, 'subtotal' => 90000,
                'deal_type' => 'discount', 'original_price' => 100000, 'discount_rate' => 10],
        ];

        $this->actingAs($this->user)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $items, 'service_items' => [],
        ])->assertOk();

        $saved = $estimate->fresh()->product_items;
        $this->assertSame('special', $saved[0]['deal_type']);
        $this->assertSame(1200000, (int) $saved[0]['original_price']);
        $this->assertSame('discount', $saved[1]['deal_type']);
        $this->assertSame(10, (int) $saved[1]['discount_rate']);
    }

    public function test_invalid_deal_type_rejected(): void
    {
        $estimate = $this->makeEstimate();
        $this->actingAs($this->user)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [['name' => 'x', 'sale_price' => 1000, 'qty' => 1, 'subtotal' => 1000, 'deal_type' => 'free']],
            'service_items' => [],
        ])->assertStatus(422);
    }

    public function test_price_sync_skips_deal_items_and_product_price_unchanged(): void
    {
        $product = Product::create([
            'sku' => 'CAM-00001', 'name' => '카메라', 'category' => '장비',
            'purchase_price' => 800000, 'sale_price' => 1200000, 'safety_stock' => 0, 'is_active' => true,
        ]);
        $estimate = $this->makeEstimate();
        $estimate->update(['product_items' => [
            ['product_id' => $product->id, 'name' => '카메라', 'sale_price' => 990000, 'qty' => 1, 'subtotal' => 990000,
                'deal_type' => 'special', 'original_price' => 1200000, 'purchase_price' => 800000],
        ]]);

        // 제품 판매가가 바뀌어도 특가 항목은 동기화로 덮어쓰지 않는다
        $product->update(['sale_price' => 1300000]);
        $estimate->refresh()->syncSnapshotPrices();
        $this->assertSame(990000, (int) $estimate->fresh()->product_items[0]['sale_price']);

        // 반대로 견적서 쪽 특가 지정이 제품 관리 가격을 바꾸지도 않는다
        $this->assertSame(1300000, (int) $product->fresh()->sale_price);
    }

    public function test_print_view_shows_deal_badges_and_notes(): void
    {
        $estimate = $this->makeEstimate();
        $estimate->update([
            'status' => 'issued',
            'product_items' => [
                ['name' => '카메라', 'category' => '장비', 'sale_price' => 990000, 'qty' => 1, 'subtotal' => 990000,
                    'deal_type' => 'special', 'original_price' => 1200000],
                ['name' => '세팅비', 'category' => '서비스', 'sale_price' => 90000, 'qty' => 1, 'subtotal' => 90000,
                    'deal_type' => 'discount', 'original_price' => 100000, 'discount_rate' => 10],
            ],
            'product_total' => 1080000, 'total_amount' => 1080000,
        ]);

        $this->actingAs($this->user)->get("/estimates/{$estimate->id}/print")
            ->assertOk()
            ->assertSee('deal-tag special', false)
            ->assertSee('할인 10%')
            ->assertSee('1,200,000원')            // 정가 취소선
            ->assertSee('단독 특가로 납품')       // 각주
            ->assertSee('이벤트 할인이 적용');
    }

    public function test_print_view_without_deals_has_no_notes(): void
    {
        $estimate = $this->makeEstimate();
        $estimate->update([
            'status' => 'issued',
            'product_items' => [['name' => '카메라', 'category' => '장비', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
            'product_total' => 100000, 'total_amount' => 100000,
        ]);

        $this->actingAs($this->user)->get("/estimates/{$estimate->id}/print")
            ->assertOk()
            ->assertDontSee('단독 특가로 납품');
    }
}
