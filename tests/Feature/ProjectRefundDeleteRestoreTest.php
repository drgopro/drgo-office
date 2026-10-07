<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use App\Services\EstimateStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 환불내역 삭제 시 견적서 복원 — 잘못 환불해 내역을 지우면 견적서의 항목별
 * 환불 기록이 되돌아가 재환불이 가능해야 한다 (김광래 피드백).
 */
class ProjectRefundDeleteRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @return array{0: Project, 1: Estimate, 2: ProjectPayment} */
    private function makePaidSetup(array $itemExtra = []): array
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '환불 복원 테스트', 'stage' => 'consulting']);
        $estimate = Estimate::create([
            'status' => 'paid',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'product_items' => [array_merge([
                'name' => 'HDMI 케이블 3m', 'sale_price' => 4500, 'qty' => 3, 'subtotal' => 13500,
            ], $itemExtra)],
            'service_items' => [],
            'total_amount' => 13500,
            'created_by' => $this->admin->id,
        ]);
        $charge = ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'estimate_id' => $estimate->id,
            'amount' => 13500, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        return [$project, $estimate, $charge];
    }

    private function refundQty(Project $project, ProjectPayment $charge, int $qty): ProjectPayment
    {
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [['name' => 'HDMI 케이블 3m', 'qty' => $qty, 'price' => 4500, 'estimate_item_index' => 0]],
        ])->assertCreated();

        return ProjectPayment::where('type', 'refund')->latest('id')->firstOrFail();
    }

    public function test_deleting_refund_restores_estimate_item_refund_records(): void
    {
        [$project, $estimate, $charge] = $this->makePaidSetup();

        // 2개 잘못 환불 — 스냅샷에 기록되고 잔여 1개
        $refund = $this->refundQty($project, $charge, 2);
        $item = $estimate->fresh()->product_items[0];
        $this->assertSame(2, (int) $item['refund_qty']);
        $remain = collect($this->actingAs($this->admin)->getJson("/api/estimates/{$estimate->id}/refund-items")->json('items'))->first();
        $this->assertSame(2, (int) $remain['refund_qty']);

        // 환불내역 삭제 → 기록 복원 (재환불 가능)
        $this->actingAs($this->admin)->deleteJson("/api/projects/{$project->id}/payments/{$refund->id}")->assertOk();

        $item = $estimate->fresh()->product_items[0];
        $this->assertSame(0, (int) ($item['refund_qty'] ?? 0));
        $this->assertSame(0, (int) ($item['refund_amount'] ?? 0));
        $this->assertArrayNotHasKey('refunded', $item);

        // 환불 모달 잔여 수량도 전량 복원
        $fresh = collect($this->actingAs($this->admin)->getJson("/api/estimates/{$estimate->id}/refund-items")->json('items'))->first();
        $this->assertSame(0, (int) $fresh['refund_qty']);
        $this->assertSame(3, (int) $fresh['qty']);

        // 정정 재환불 — 1개만 다시 환불 가능
        $this->refundQty($project, $charge->fresh(), 1);
        $this->assertSame(1, (int) $estimate->fresh()->product_items[0]['refund_qty']);
    }

    public function test_deleting_charge_also_reverses_child_refund_records(): void
    {
        [$project, $estimate, $charge] = $this->makePaidSetup();
        $this->refundQty($project, $charge, 1);

        // 결제(charge) 삭제 — 자식 환불과 함께 견적서 기록도 복원
        $this->actingAs($this->admin)->deleteJson("/api/projects/{$project->id}/payments/{$charge->id}")->assertOk();

        $item = $estimate->fresh()->product_items[0];
        $this->assertSame(0, (int) ($item['refund_qty'] ?? 0));
        $this->assertArrayNotHasKey('refunded', $item);
    }

    public function test_deleting_refund_re_deducts_direct_ship_stock(): void
    {
        // 직접발송 제품 — 환불 시 복원됐던 재고가, 환불내역 삭제로 다시 차감되어야 한다
        $product = Product::create([
            'sku' => 'HDMI-001', 'name' => 'HDMI 케이블 3m', 'category' => '케이블',
            'purchase_price' => 2000, 'sale_price' => 4500, 'is_active' => true, 'show_in_estimate' => true,
        ]);
        Inventory::create(['product_id' => $product->id, 'quantity' => 10, 'last_updated_at' => now()]);

        [$project, $estimate, $charge] = $this->makePaidSetup([
            'product_id' => $product->id, 'ordered' => true, 'purchase_source' => '사무실 발송',
        ]);
        // 직접발송 차감 상태를 만든다 (10 → 7, 수량 3)
        EstimateStockSync::apply($estimate, [], $estimate->product_items);
        $this->assertSame(7, $product->inventory->fresh()->quantity);

        // 2개 환불 → 재고 +2 (9)
        $refund = $this->refundQty($project, $charge, 2);
        $this->assertSame(9, $product->inventory->fresh()->quantity);

        // 환불내역 삭제 → 복원 취소, 재고 다시 −2 (7)
        $this->actingAs($this->admin)->deleteJson("/api/projects/{$project->id}/payments/{$refund->id}")->assertOk();
        $this->assertSame(7, $product->inventory->fresh()->quantity);
    }
}
