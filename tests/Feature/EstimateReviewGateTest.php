<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 검토 게이트 — 검토 미완료 견적서의 발행 전환·출력 잠금.
 * 발행/결제 이후 상태의 출력은 허용(기존 견적서 보호), 설정에서 게이트를 끌 수 있다.
 */
class EstimateReviewGateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);

        $pc = Product::create(['sku' => 'PC-1', 'name' => '송출용 PC', 'category' => 'PC', 'sale_price' => 1000000, 'is_active' => 1]);

        // 걸리는 규칙 없는 최소 설정 — 게이트 전이 자체를 검증
        Setting::set('estimate_review.config', json_encode([
            'gate' => true,
            'checks' => ['video' => false, 'audio' => false, 'lens' => false, 'battery' => false, 'mount' => false, 'spec' => false],
            'roles' => [], 'rules' => [],
            'categoryRolesByPath' => [], 'productRolesByName' => [],
            'specTemplatesByPath' => [], 'specsByName' => [], 'specsByPath' => [],
        ]));

        $this->estimate = Estimate::create([
            'status' => 'completed', 'service_items' => [],
            'product_items' => [['product_id' => $pc->id, 'name' => '송출용 PC', 'qty' => 1, 'sale_price' => 1000000, 'subtotal' => 1000000]],
            'product_total' => 1000000, 'service_total' => 0, 'total_amount' => 1000000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    private function completeReview(): void
    {
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/review")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/review/finish")->assertOk();
    }

    public function test_issue_transition_blocked_until_review_done(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", ['status' => 'issued'])
            ->assertStatus(422);
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/issue")
            ->assertStatus(422);

        $this->completeReview();

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", ['status' => 'issued'])
            ->assertOk();
        $this->assertSame('issued', $this->estimate->fresh()->status);
    }

    public function test_issue_blocked_when_items_changed_after_review(): void
    {
        $this->completeReview();

        // 검토 후 품목 수량 변경과 함께 발행 시도 → 지문 불일치
        $items = $this->estimate->fresh()->product_items;
        $items[0]['qty'] = 2;
        $items[0]['subtotal'] = 2000000;
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'status' => 'issued', 'product_items' => $items,
        ])->assertStatus(422);
    }

    public function test_print_blocked_before_review_but_allowed_after_issue(): void
    {
        $this->actingAs($this->admin)->get("/estimates/{$this->estimate->id}/print")
            ->assertStatus(423)->assertSee('검토가 끝나지 않은 견적서');

        $this->completeReview();
        $this->actingAs($this->admin)->get("/estimates/{$this->estimate->id}/print")->assertOk();

        // 발행/결제 이후 상태는 검토 기록 없이도 출력 허용 (기존 견적서 보호)
        $this->estimate->forceFill(['status' => 'issued', 'review_state' => null])->save();
        $this->actingAs($this->admin)->get("/estimates/{$this->estimate->id}/print")->assertOk();
    }

    public function test_gate_can_be_turned_off_in_settings(): void
    {
        $cfg = json_decode(Setting::get('estimate_review.config'), true);
        $cfg['gate'] = false;
        Setting::set('estimate_review.config', json_encode($cfg));

        $this->actingAs($this->admin)->get("/estimates/{$this->estimate->id}/print")->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", ['status' => 'issued'])->assertOk();
    }
}
