<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 직접발송(사무실 발송) 재고 연동 — 직접발송 체크 시 -n, 해제/환불 시 +n. 주문완료만으로는 불변 */
class EstimateStockSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->product = Product::create([
            'sku' => 'CAM-001', 'name' => '카메라 X100', 'category' => '카메라',
            'purchase_price' => 100000, 'sale_price' => 150000,
            'is_active' => true, 'show_in_estimate' => true,
        ]);
        Inventory::create(['product_id' => $this->product->id, 'quantity' => 10, 'last_updated_at' => now()]);
    }

    private function makeEstimate(array $item): Estimate
    {
        return Estimate::create([
            'status' => 'created',
            'product_items' => [$item],
            'service_items' => [],
            'total_amount' => (int) ($item['subtotal'] ?? 0),
            'created_by' => $this->admin->id,
        ]);
    }

    private function baseItem(array $extra = []): array
    {
        return array_merge([
            'product_id' => $this->product->id, 'sku' => 'CAM-001', 'category' => '카메라', 'category_root' => '카메라',
            'name' => '카메라 X100', 'purchase_price' => 100000, 'sale_price' => 150000,
            'qty' => 2, 'subtotal' => 300000,
        ], $extra);
    }

    private function saveItems(Estimate $estimate, array $items): void
    {
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $items, 'service_items' => [], 'status' => 'created',
        ])->assertOk();
    }

    public function test_direct_ship_deducts_and_uncheck_restores(): void
    {
        $estimate = $this->makeEstimate($this->baseItem());

        // 주문완료만 — 재고 불변
        $this->saveItems($estimate, [$this->baseItem(['ordered' => true, 'purchase_source' => '테크노마트'])]);
        $this->assertSame(10, $this->product->inventory->fresh()->quantity);

        // 직접발송 — 수량 2 차감 + 출고 기록
        $this->saveItems($estimate, [$this->baseItem(['ordered' => true, 'purchase_source' => '사무실 발송'])]);
        $this->assertSame(8, $this->product->inventory->fresh()->quantity);
        $movement = StockMovement::where('product_id', $this->product->id)->latest('id')->first();
        $this->assertSame(['out', 2, 8], [$movement->movement_type, $movement->quantity, $movement->quantity_after]);
        $this->assertStringContainsString('직접발송', $movement->memo);

        // 같은 상태로 다시 저장 — 중복 차감 없음
        $this->saveItems($estimate, [$this->baseItem(['ordered' => true, 'purchase_source' => '사무실 발송'])]);
        $this->assertSame(8, $this->product->inventory->fresh()->quantity);

        // 직접발송 해제 — 복원
        $this->saveItems($estimate, [$this->baseItem(['ordered' => false, 'purchase_source' => ''])]);
        $this->assertSame(10, $this->product->inventory->fresh()->quantity);
        $this->assertSame('return', StockMovement::where('product_id', $this->product->id)->latest('id')->value('movement_type'));
    }

    public function test_refund_restores_stock(): void
    {
        $estimate = $this->makeEstimate($this->baseItem());
        $this->saveItems($estimate, [$this->baseItem(['ordered' => true, 'purchase_source' => '사무실 발송'])]);
        $this->assertSame(8, $this->product->inventory->fresh()->quantity);

        // 부분환불 1개 기록(프로젝트 환불 경로) — +1 복원
        $estimate->fresh()->applyItemRefunds([['index' => 0, 'qty' => 1, 'amount' => 150000]]);
        $this->assertSame(9, $this->product->inventory->fresh()->quantity);

        // 주문 내역 수동 환불 체크 해제 — 환불 기록이 사라지므로 다시 차감
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$estimate->id}/item-note", [
            'index' => 0, 'purchase_source' => '사무실 발송', 'memo' => '', 'refunded' => false,
        ])->assertOk();
        $this->assertSame(8, $this->product->inventory->fresh()->quantity);
    }

    public function test_bundle_component_direct_ship_and_refund(): void
    {
        $component = Product::create([
            'sku' => 'MIC-001', 'name' => '마이크 M1', 'category' => '오디오',
            'purchase_price' => 10000, 'sale_price' => 20000, 'is_active' => true, 'show_in_estimate' => true,
        ]);
        Inventory::create(['product_id' => $component->id, 'quantity' => 5, 'last_updated_at' => now()]);
        $bundle = Product::create([
            'sku' => 'SET-001', 'name' => '방송 세트', 'category' => '세트',
            'purchase_price' => 0, 'sale_price' => 500000, 'is_active' => true, 'show_in_estimate' => true, 'is_bundle' => true,
        ]);
        ProductBundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $component->id, 'quantity' => 2, 'sort_order' => 1]);

        $item = [
            'product_id' => $bundle->id, 'sku' => 'SET-001', 'category' => '세트', 'category_root' => '세트',
            'name' => '방송 세트', 'purchase_price' => 0, 'sale_price' => 500000, 'qty' => 1, 'subtotal' => 500000,
            'bundle_items' => [['name' => '마이크 M1', 'qty' => 2, 'price' => 20000]],
        ];
        $estimate = $this->makeEstimate($item);

        // 구성품 직접발송 — 구성 수량 2 차감
        $shipped = $item;
        $shipped['bundle_items'][0]['ordered'] = true;
        $shipped['bundle_items'][0]['source'] = '사무실 발송';
        $this->saveItems($estimate, [$shipped]);
        $this->assertSame(3, $component->inventory->fresh()->quantity);
        $this->assertSame(10, $this->product->inventory->fresh()->quantity); // 무관 제품 불변

        // 구성품 부분환불 1개 — +1 복원
        $estimate->fresh()->applyItemRefunds([['index' => 0, 'bundle_index' => 0, 'qty' => 1, 'amount' => 20000]]);
        $this->assertSame(4, $component->inventory->fresh()->quantity);
    }

    public function test_manual_item_and_delete_restore(): void
    {
        // 수기 항목(product_id 없음)은 재고 무관
        $estimate = $this->makeEstimate(['product_id' => null, 'name' => '수기 품목', 'category' => '기타', 'sale_price' => 1000, 'qty' => 3, 'subtotal' => 3000]);
        $this->saveItems($estimate, [['product_id' => null, 'name' => '수기 품목', 'category' => '기타', 'sale_price' => 1000, 'qty' => 3, 'subtotal' => 3000, 'ordered' => true, 'purchase_source' => '사무실 발송', 'manual' => true]]);
        $this->assertSame(10, $this->product->inventory->fresh()->quantity);

        // 직접발송 차감 후 견적서 삭제 — 복원
        $e2 = $this->makeEstimate($this->baseItem());
        $this->saveItems($e2, [$this->baseItem(['ordered' => true, 'purchase_source' => '사무실 발송'])]);
        $this->assertSame(8, $this->product->inventory->fresh()->quantity);
        $this->actingAs($this->admin)->deleteJson("/api/estimates/{$e2->id}")->assertOk();
        $this->assertSame(10, $this->product->inventory->fresh()->quantity);
    }
}
