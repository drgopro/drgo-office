<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use App\Services\EstimateStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 사무실에서 직접발송한 세트상품 환불 — 세트 항목(부모) 단위로 환불해도
 * 직발 구성품 재고가 복원되어야 한다 (김광래 피드백: 세트 환불 시 재고 입고 안 됨).
 */
class EstimateBundleRefundRestockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $cam;

    private Product $mic;

    private Product $bundle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cam = Product::create(['sku' => 'CAM-9', 'name' => '카메라', 'sale_price' => 100000, 'is_active' => true]);
        Inventory::create(['product_id' => $this->cam->id, 'quantity' => 10, 'last_updated_at' => now()]);
        $this->mic = Product::create(['sku' => 'MIC-9', 'name' => '마이크', 'sale_price' => 50000, 'is_active' => true]);
        Inventory::create(['product_id' => $this->mic->id, 'quantity' => 10, 'last_updated_at' => now()]);
        $this->bundle = Product::create(['sku' => 'SET-9', 'name' => 'MT-88 세트', 'sale_price' => 200000, 'is_active' => true, 'is_bundle' => true]);
        ProductBundleItem::create(['bundle_product_id' => $this->bundle->id, 'component_product_id' => $this->cam->id, 'quantity' => 1]);
        ProductBundleItem::create(['bundle_product_id' => $this->bundle->id, 'component_product_id' => $this->mic->id, 'quantity' => 2]);
    }

    /** 세트 1개를 구성품 단위 직접발송으로 처리한 결제완료 견적 + 결제 기록 */
    private function makePaidBundleSetup(): array
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세트 환불 테스트', 'stage' => 'consulting']);
        $estimate = Estimate::create([
            'status' => 'paid', 'client_id' => $client->id, 'project_id' => $project->id,
            'product_items' => [[
                'product_id' => $this->bundle->id, 'name' => 'MT-88 세트', 'sale_price' => 200000,
                'qty' => 1, 'subtotal' => 200000,
                'bundle_items' => [
                    ['name' => '카메라', 'qty' => 1, 'price' => 100000, 'ordered' => true, 'source' => '사무실 발송'],
                    ['name' => '마이크', 'qty' => 2, 'price' => 50000, 'ordered' => true, 'source' => '사무실 발송'],
                ],
            ]],
            'service_items' => [], 'product_total' => 200000, 'total_amount' => 200000,
            'created_by' => $this->admin->id,
        ]);
        // 직접발송 차감 상태 (카메라 10→9, 마이크 10→8)
        EstimateStockSync::apply($estimate, [], $estimate->product_items);
        $charge = ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'estimate_id' => $estimate->id,
            'amount' => 200000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        return [$project, $estimate, $charge];
    }

    public function test_refunding_bundle_parent_row_restores_component_stock(): void
    {
        [$project, $estimate, $charge] = $this->makePaidBundleSetup();
        $this->assertSame(9, $this->cam->inventory->fresh()->quantity);
        $this->assertSame(8, $this->mic->inventory->fresh()->quantity);

        // 환불 모달에서 세트 '부모 행'을 체크해 환불 (bundle_index 없음 — 피드백 재현 경로)
        $refund = $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [['name' => 'MT-88 세트', 'qty' => 1, 'price' => 200000, 'estimate_item_index' => 0]],
        ])->assertCreated();

        // 직발 구성품 재고 복원 (카메라 +1, 마이크 +2)
        $this->assertSame(10, $this->cam->inventory->fresh()->quantity);
        $this->assertSame(10, $this->mic->inventory->fresh()->quantity);

        // 구성품에도 환불 수량이 비례 기록 — 모달 잔여 수량 표시 일관
        $item = $estimate->fresh()->product_items[0];
        $this->assertSame(1, (int) $item['refund_qty']);
        $this->assertSame(1, (int) $item['bundle_items'][0]['refund_qty']);
        $this->assertSame(2, (int) $item['bundle_items'][1]['refund_qty']);

        // 환불내역 삭제 — 복원됐던 재고가 다시 차감되고 구성품 기록도 되돌아간다
        $refundRow = ProjectPayment::where('type', 'refund')->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->deleteJson("/api/projects/{$project->id}/payments/{$refundRow->id}")->assertOk();
        $this->assertSame(9, $this->cam->inventory->fresh()->quantity);
        $this->assertSame(8, $this->mic->inventory->fresh()->quantity);
        $item = $estimate->fresh()->product_items[0];
        $this->assertSame(0, (int) ($item['refund_qty'] ?? 0));
        $this->assertSame(0, (int) ($item['bundle_items'][0]['refund_qty'] ?? 0));
        $this->assertSame(0, (int) ($item['bundle_items'][1]['refund_qty'] ?? 0));
    }

    public function test_office_restock_of_vendor_component_uses_snapshot_product_id(): void
    {
        // 혼합 세트 — MT-88은 직접발송, 룩스패드는 거래처 주문. 스냅샷 표시명이 제품명과
        // 달라도(스냅샷 product_id로) 사무실 반품 입고가 누락되지 않아야 한다 (김광래 피드백)
        $lux = Product::create(['sku' => 'LUX-1', 'name' => '[유쾌한생각] 룩스패드 K22H', 'sale_price' => 75000, 'is_active' => true]);
        Inventory::create(['product_id' => $lux->id, 'quantity' => 5, 'last_updated_at' => now()]);

        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '혼합 세트 환불', 'stage' => 'consulting']);
        $estimate = Estimate::create([
            'status' => 'paid', 'client_id' => $client->id, 'project_id' => $project->id,
            'product_items' => [[
                'product_id' => $this->bundle->id, 'name' => '룩스패드 세트', 'sale_price' => 93000,
                'qty' => 2, 'subtotal' => 186000,
                'bundle_items' => [
                    // 표시명에 '단품' 접미 — 세트 구성 이름 매칭은 실패하지만 product_id로 매칭
                    ['product_id' => $lux->id, 'name' => '[유쾌한생각] 룩스패드 K22H 단품', 'qty' => 1, 'price' => 75000, 'ordered' => true, 'source' => '컴퓨존'],
                    ['name' => '마이크', 'qty' => 2, 'price' => 9000, 'ordered' => true, 'source' => '사무실 발송'],
                ],
            ]],
            'service_items' => [], 'product_total' => 186000, 'total_amount' => 186000,
            'created_by' => $this->admin->id,
        ]);
        $charge = ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'estimate_id' => $estimate->id,
            'amount' => 186000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        // 거래처 주문 구성품(룩스패드) 1개 환불 + 사무실 반품 입고 '예'
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [[
                'name' => '[유쾌한생각] 룩스패드 K22H 단품', 'qty' => 1, 'price' => 75000,
                'estimate_item_index' => 0, 'bundle_index' => 0, 'office_restock' => true,
            ]],
        ])->assertCreated();

        // 이름 매칭 실패에도 product_id로 입고 (5 → 6)
        $this->assertSame(6, $lux->inventory->fresh()->quantity);

        // 환불 모달 목록에서도 '거래처 주문' 표시가 유지 (office_restockable)
        $items = $this->actingAs($this->admin)->getJson("/api/estimates/{$estimate->id}/refund-items")->json('items');
        $this->assertTrue((bool) $items[0]['bundle_items'][0]['office_restockable']);
    }

    public function test_component_level_refund_then_parent_refund_does_not_over_restore(): void
    {
        [$project, $estimate, $charge] = $this->makePaidBundleSetup();

        // 먼저 구성품(마이크) 1개만 환불 → 마이크 +1 (8→9)
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [['name' => '마이크', 'qty' => 1, 'price' => 50000, 'estimate_item_index' => 0, 'bundle_index' => 1]],
        ])->assertCreated();
        $this->assertSame(9, $this->mic->inventory->fresh()->quantity);

        // 이어서 세트 부모 단위 환불 — 구성품 기록은 총량을 넘지 않게 제한 (과복원 방지)
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->fresh()->id, 'type' => 'refund',
            'items' => [['name' => 'MT-88 세트', 'qty' => 1, 'price' => 150000, 'estimate_item_index' => 0]],
        ])->assertCreated();

        // 총 복원량은 출고량을 넘지 않는다 (카메라 10, 마이크 10)
        $this->assertSame(10, $this->cam->inventory->fresh()->quantity);
        $this->assertSame(10, $this->mic->inventory->fresh()->quantity);
        $item = $estimate->fresh()->product_items[0];
        $this->assertSame(1, (int) $item['bundle_items'][0]['refund_qty']);
        $this->assertSame(2, (int) $item['bundle_items'][1]['refund_qty']); // cap: 총 2개
    }
}
