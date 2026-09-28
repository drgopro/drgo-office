<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\EstimateStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 환불 시 사무실 반품 입고 — 직접발송이 아닌(거래처 주문) 제품을 환불하며
 * '사무실 반품으로 재고 반영'을 선택하면 재고 +qty, 직접발송 항목은 기존 자동 복원 유지.
 */
class EstimateRefundOfficeRestockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->product = Product::create([
            'sku' => 'SPK-001', 'name' => '브리츠 스피커 BZ-SL9', 'category' => '스피커',
            'purchase_price' => 30000, 'sale_price' => 50000,
            'is_active' => true, 'show_in_estimate' => true,
        ]);
        Inventory::create(['product_id' => $this->product->id, 'quantity' => 10, 'last_updated_at' => now()]);
    }

    /**
     * @return array{0: Project, 1: Estimate, 2: ProjectPayment} 거래처 주문 항목 견적서 + 결제
     */
    private function makePaidEstimate(array $itemExtra = []): array
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '환불 재고 테스트', 'stage' => 'consulting']);
        $estimate = Estimate::create([
            'status' => 'paid',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'product_items' => [array_merge([
                'product_id' => $this->product->id, 'sku' => 'SPK-001', 'name' => '브리츠 스피커 BZ-SL9',
                'purchase_price' => 30000, 'sale_price' => 50000, 'qty' => 2, 'subtotal' => 100000,
                'ordered' => true, 'purchase_source' => '테크노마트', // 거래처 주문 (직접발송 아님)
            ], $itemExtra)],
            'service_items' => [],
            'total_amount' => 100000,
            'created_by' => $this->admin->id,
        ]);
        $charge = ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'estimate_id' => $estimate->id,
            'amount' => 100000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        return [$project, $estimate, $charge];
    }

    public function test_refund_items_api_marks_non_direct_items_as_office_restockable(): void
    {
        [, $estimate] = $this->makePaidEstimate();

        $res = $this->actingAs($this->admin)->getJson("/api/estimates/{$estimate->id}/refund-items");
        $res->assertOk()
            ->assertJsonPath('items.0.direct', false)
            ->assertJsonPath('items.0.office_restockable', true);
    }

    public function test_refund_items_api_direct_and_manual_items_not_restockable(): void
    {
        // 직접발송 항목 — 자동 복원 대상이므로 사무실 반품 선택 불가
        [, $direct] = $this->makePaidEstimate(['purchase_source' => '사무실 발송']);
        $this->actingAs($this->admin)->getJson("/api/estimates/{$direct->id}/refund-items")
            ->assertOk()
            ->assertJsonPath('items.0.direct', true)
            ->assertJsonPath('items.0.office_restockable', false);

        // 수기 항목(product_id 없음) — 재고 개념 없음
        [, $manual] = $this->makePaidEstimate(['product_id' => null]);
        $this->actingAs($this->admin)->getJson("/api/estimates/{$manual->id}/refund-items")
            ->assertOk()
            ->assertJsonPath('items.0.office_restockable', false);
    }

    public function test_refund_with_office_restock_increases_inventory(): void
    {
        [$project, $estimate, $charge] = $this->makePaidEstimate();

        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [[
                'name' => '브리츠 스피커 BZ-SL9', 'qty' => 2, 'price' => 50000,
                'estimate_item_index' => 0, 'office_restock' => true,
            ]],
        ])->assertCreated();

        // 거래처 주문분 사무실 반품 — 재고 10 → 12, 반품 입고 기록
        $this->assertSame(12, $this->product->inventory->fresh()->quantity);
        $movement = StockMovement::where('product_id', $this->product->id)->latest('id')->firstOrFail();
        $this->assertSame(['return', 2, 12], [$movement->movement_type, $movement->quantity, $movement->quantity_after]);
        $this->assertStringContainsString('사무실 반품', $movement->memo);

        // 견적서 스냅샷에도 환불 기록
        $this->assertSame(2, (int) ($estimate->fresh()->product_items[0]['refund_qty'] ?? 0));
    }

    public function test_refund_without_office_restock_leaves_inventory(): void
    {
        [$project, , $charge] = $this->makePaidEstimate();

        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [[
                'name' => '브리츠 스피커 BZ-SL9', 'qty' => 2, 'price' => 50000,
                'estimate_item_index' => 0, 'office_restock' => false,
            ]],
        ])->assertCreated();

        $this->assertSame(10, $this->product->inventory->fresh()->quantity);
    }

    public function test_direct_ship_item_is_not_double_restocked(): void
    {
        // 직접발송 항목 — 재고 연동이 이미 차감(10→8)했고, 환불 시 자동 복원만 일어나야 함
        [$project, $estimate, $charge] = $this->makePaidEstimate(['purchase_source' => '사무실 발송']);
        $estimate->fresh(); // 스냅샷은 모델 생성으로 들어갔으므로 수동으로 차감 상태를 만든다
        EstimateStockSync::apply($estimate, [], $estimate->product_items);
        $this->assertSame(8, $this->product->inventory->fresh()->quantity);

        // office_restock=true를 억지로 보내도 직접발송은 자동 복원 1번만 (8 → 10, 12 아님)
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [[
                'name' => '브리츠 스피커 BZ-SL9', 'qty' => 2, 'price' => 50000,
                'estimate_item_index' => 0, 'office_restock' => true,
            ]],
        ])->assertCreated();

        $this->assertSame(10, $this->product->inventory->fresh()->quantity);
    }
}
