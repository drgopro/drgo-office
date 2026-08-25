<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 항목별 환불/결제취소 — 프로젝트 환불 처리가 견적서 항목에 기록되고,
 * 주문 내역에서 수동 체크할 수 있으며, 빌더 저장에도 기록이 보존된다.
 */
class EstimateRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $this->project = Project::create(['client_id' => $client->id, 'name' => '스튜디오', 'project_type' => 'visit', 'stage' => 'payment']);
        $this->estimate = Estimate::create([
            'status' => 'paid', 'client_id' => $client->id, 'project_id' => $this->project->id,
            'product_items' => [
                ['product_id' => 1, 'name' => '카메라', 'sale_price' => 100000, 'qty' => 2, 'subtotal' => 200000],
                ['product_id' => 2, 'name' => '마이크', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000],
            ],
            'service_items' => [], 'product_total' => 250000, 'service_total' => 0, 'total_amount' => 250000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_refund_items_api_lists_snapshot_with_refund_state(): void
    {
        $items = $this->actingAs($this->admin)
            ->getJson("/api/estimates/{$this->estimate->id}/refund-items")
            ->assertOk()->json('items');

        $this->assertCount(2, $items);
        $this->assertSame('카메라', $items[0]['name']);
        $this->assertSame(100000, $items[0]['sale_price']);
        $this->assertSame(0, $items[0]['refund_qty']);
        $this->assertFalse($items[0]['refunded']);
    }

    public function test_project_refund_marks_estimate_items(): void
    {
        $charge = ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 250000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);
        $origUpdated = $this->estimate->updated_at;

        // 카메라 1개(100,000원) 항목 환불 — 견적서 항목 index 0
        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [['name' => '카메라', 'qty' => 1, 'price' => 100000, 'estimate_item_index' => 0]],
            'reason' => '단순 변심',
        ])->assertCreated();

        // 환불 트랜잭션 생성 (음수)
        $refundRow = ProjectPayment::where('parent_payment_id', $charge->id)->first();
        $this->assertSame(-100000, (int) $refundRow->amount);

        // 견적서 스냅샷에 기록 + 발행일시(updated_at) 불변
        $fresh = $this->estimate->fresh();
        $item = $fresh->product_items[0];
        $this->assertTrue($item['refunded']);
        $this->assertSame(1, (int) $item['refund_qty']);
        $this->assertSame(100000, (int) $item['refund_amount']);
        $this->assertFalse((bool) ($fresh->product_items[1]['refunded'] ?? false));
        $this->assertTrue($fresh->updated_at->equalTo($origUpdated));

        // refund-items에 잔여 수량 반영 (2개 중 1개 환불 → 잔여 1)
        $items = $this->actingAs($this->admin)->getJson("/api/estimates/{$this->estimate->id}/refund-items")->json('items');
        $this->assertSame(1, $items[0]['refund_qty']);

        // 주문 내역에도 노출 (주문완료 항목이어야 리스트에 뜨므로 ordered 세팅 후 확인)
        $itemsRaw = $fresh->product_items;
        $itemsRaw[0]['ordered'] = true;
        $fresh->forceFill(['product_items' => $itemsRaw])->save();
        $row = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->json('0');
        $this->assertTrue($row['items'][0]['refunded']);
        $this->assertSame(100000, $row['items'][0]['refund_amount']);
    }

    public function test_manual_refund_check_in_order_history(): void
    {
        // 수동 체크 — 환불 표시 + 금액
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 1, 'refunded' => true, 'refund_amount' => 50000,
        ])->assertOk();
        $item = $this->estimate->fresh()->product_items[1];
        $this->assertTrue($item['refunded']);
        $this->assertSame(50000, (int) $item['refund_amount']);

        // 해제 — 기록 초기화
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 1, 'refunded' => false,
        ])->assertOk();
        $this->assertArrayNotHasKey('refunded', $this->estimate->fresh()->product_items[1]);
    }

    public function test_builder_save_preserves_refund_fields(): void
    {
        $this->estimate->applyItemRefunds([['index' => 0, 'qty' => 1, 'amount' => 100000]]);
        $this->estimate->update(['status' => 'editing']); // 빌더 저장 검증용 (temp 아님)

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->fresh()->product_items,
        ])->assertOk();

        $item = $this->estimate->fresh()->product_items[0];
        $this->assertTrue((bool) $item['refunded']);
        $this->assertSame(1, (int) $item['refund_qty']);
        $this->assertSame(100000, (int) $item['refund_amount']);
    }
}
