<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적 항목 대체(취소선) — 품절 등으로 제품 변경 시 취소선+사유 표시, 금액은 소계/합계 제외 */
class EstimateReplacedItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function makeEstimateWithReplaced(): Estimate
    {
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [
                ['name' => '단종 카메라', 'category' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000,
                    'replaced' => true, 'replaced_note' => '품절 — ZV-E10으로 대체'],
                ['name' => 'ZV-E10', 'category' => '카메라', 'sale_price' => 350000, 'qty' => 1, 'subtotal' => 350000],
            ],
        ])->assertOk();

        return $estimate->fresh();
    }

    public function test_replaced_item_excluded_from_totals_and_flag_persisted(): void
    {
        $estimate = $this->makeEstimateWithReplaced();

        $this->assertSame(350000, (int) $estimate->product_total); // 대체 항목 300,000원 제외
        $this->assertSame(350000, (int) $estimate->total_amount);
        $this->assertTrue((bool) $estimate->product_items[0]['replaced']);
        $this->assertSame('품절 — ZV-E10으로 대체', $estimate->product_items[0]['replaced_note']);
    }

    public function test_public_view_shows_strikethrough_and_excludes_from_subtotal(): void
    {
        $estimate = $this->makeEstimateWithReplaced();

        $res = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk();
        $res->assertSee('대체');                       // 대체 태그
        $res->assertSee('품절 — ZV-E10으로 대체');       // 사유
        $res->assertSee('line-through', false);        // 취소선
        $res->assertSee(number_format(350000).'원');   // 카테고리 소계 = 대체 제외
    }

    public function test_replaced_item_not_orderable_in_office_orders(): void
    {
        $estimate = $this->makeEstimateWithReplaced();
        $estimate->update(['status' => 'paid']); // 주문내역 자동 등재

        $row = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json('0');
        $items = collect($row['items']);
        $this->assertTrue($items->firstWhere('name', '단종 카메라')['replaced']);
        $this->assertFalse($items->firstWhere('name', 'ZV-E10')['replaced']);
    }
}
