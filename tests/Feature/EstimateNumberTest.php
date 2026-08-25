<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 표시 번호 — 생성(temp) 시점엔 번호를 쓰지 않고 첫 실제 저장 때 발급.
 * 만들고 버린 견적서 때문에 번호가 건너뛰지 않고, unique 제약이 중복을 막는다.
 */
class EstimateNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function createTemp(): int
    {
        return $this->actingAs($this->admin)->postJson('/api/estimates')->assertCreated()->json('id');
    }

    public function test_temp_estimate_has_no_number_until_first_save(): void
    {
        $id = $this->createTemp();
        $this->assertNull(Estimate::find($id)->estimate_no);

        // 첫 저장 → 번호 발급 (1번부터), 응답에 display_no 포함
        $res = $this->actingAs($this->admin)->patchJson("/api/estimates/{$id}", [
            'product_items' => [['name' => '카메라', 'sale_price' => 1000, 'qty' => 1, 'subtotal' => 1000]],
        ])->assertOk();
        $this->assertSame(1, Estimate::find($id)->estimate_no);
        $this->assertSame(1, $res->json('display_no'));
    }

    public function test_abandoned_temps_do_not_consume_numbers(): void
    {
        // temp 3개 생성, 그중 2개만 저장 — 버려진 temp가 있어도 번호는 1, 2로 연속
        $a = $this->createTemp();
        $this->createTemp(); // 버려짐
        $b = $this->createTemp();

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$a}", ['memo' => '저장 A'])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$b}", ['memo' => '저장 B'])->assertOk();

        $this->assertSame(1, Estimate::find($a)->estimate_no);
        $this->assertSame(2, Estimate::find($b)->estimate_no);

        // 재저장해도 번호 불변
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$a}", ['memo' => '수정'])->assertOk();
        $this->assertSame(1, Estimate::find($a)->estimate_no);
    }

    public function test_numbers_continue_after_existing_backfilled_estimates(): void
    {
        // 백필된 기존 견적서(번호=id)와 공존 — 새 발급은 최대 번호 다음부터
        Estimate::create([
            'estimate_no' => 41, 'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $id = $this->createTemp();
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$id}", ['memo' => '새 견적'])->assertOk();
        $this->assertSame(42, Estimate::find($id)->estimate_no);
    }

    public function test_list_shows_and_searches_by_display_number(): void
    {
        Estimate::create([
            'estimate_no' => 77, 'status' => 'created', 'client_nickname' => '고블린',
            'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $rows = $this->actingAs($this->admin)->getJson('/api/estimates?search=77')->assertOk()->json();
        $this->assertCount(1, $rows);
        $this->assertSame(77, $rows[0]['display_no']);

        // 번호 미발급 구버전 행은 id로 폴백 표시
        $legacy = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $this->assertSame($legacy->id, $legacy->display_no);
    }
}
