<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 수기 항목 음수 금액 — 재방문 할인 등 차감 항목을 -금액으로 기록하면 합계에서 차감 */
class EstimateNegativeItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_negative_manual_item_saves_and_reduces_total(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => [
                ['name' => '카메라 본체', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000],
                ['name' => '재방문 할인 (미사용 제품 대체)', 'sale_price' => -50000, 'qty' => 1, 'subtotal' => -50000, 'manual' => true],
            ],
        ])->assertOk();

        $fresh = $this->estimate->fresh();
        $this->assertSame(-50000, (int) $fresh->product_items[1]['sale_price']);
        $this->assertSame(250000, (int) $fresh->product_total);
        $this->assertSame(250000, (int) $fresh->total_amount);

        // 항목별 필드 구성이 달라도(첫 항목에 category 없음) validated()의 키 재조립로
        // 순서가 뒤섞여 JSON 객체로 저장되지 않아야 한다 — 연속 배열 + 입력 순서 유지
        $this->assertTrue(array_is_list($fresh->product_items));
        $this->assertSame('카메라 본체', $fresh->product_items[0]['name']);
    }

    public function test_builder_manual_price_input_allows_negative(): void
    {
        $res = $this->actingAs($this->admin)->get("/estimates/{$this->estimate->id}/edit")->assertOk();
        // 수기 입력 판매가 칸에 min="0" 제한이 없어야 음수 입력 가능
        $this->assertStringNotContainsString('id="miPrice" type="number" min="0"', $res->getContent());
        $res->assertSee('음수=할인', false);
    }
}
