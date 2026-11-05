<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 차수(추가 견적) 검토 — 트리 전체(본 견적+다른 차수)를 컨텍스트로 판정하되,
 * 이 차수로 "새로 생긴" 발견만 보고한다. 지문은 트리 기준이라
 * 본 견적 품목이 바뀌면 차수의 검토 완료도 무효가 된다.
 */
class EstimateReviewRoundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $parent;

    private Estimate $round;

    private Product $pc;

    private Product $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->pc = Product::create(['sku' => 'PC-1', 'name' => '송출용 PC', 'category' => 'PC', 'sale_price' => 1000000, 'is_active' => 1, 'show_in_estimate' => 1]);
        $this->monitor = Product::create(['sku' => 'MON-1', 'name' => '사무용 모니터', 'category' => 'PC', 'sale_price' => 200000, 'is_active' => 1, 'show_in_estimate' => 1]);

        Setting::set('estimate_review.config', json_encode([
            'gate' => true,
            'checks' => ['video' => false, 'audio' => false, 'lens' => false, 'battery' => false, 'mount' => false, 'spec' => false],
            'roles' => [['id' => 'pc', 'label' => '컴퓨터', 'group' => '컴퓨터'], ['id' => 'monitor', 'label' => '모니터', 'group' => '컴퓨터']],
            'rules' => [[
                'type' => 'need', 'group' => '컴퓨터', 'id' => 'pc-monitor', 'on' => ['pc'], 'need' => ['monitor'],
                'mode' => 'perUnit', 'amt' => 1, 'what' => '모니터', 'why' => '', 'active' => true,
            ]],
            'categoryRolesByPath' => [], 'specTemplatesByPath' => [], 'specsByName' => [], 'specsByPath' => [],
            'productRolesByName' => ['송출용 PC' => [['pc', 1]], '사무용 모니터' => [['monitor', 1]]],
        ], JSON_UNESCAPED_UNICODE));

        // 본 견적: PC 1 (모니터 누락 상태로 이미 발행됨 — 기존 문제는 본 견적 몫)
        $this->parent = Estimate::create([
            'status' => 'issued', 'service_items' => [],
            'product_items' => [$this->item($this->pc, 1)],
            'product_total' => 1000000, 'service_total' => 0, 'total_amount' => 1000000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $this->round = Estimate::create([
            'status' => 'created', 'service_items' => [], 'product_items' => [],
            'parent_estimate_id' => $this->parent->id, 'round' => 2,
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    private function item(Product $p, int $qty): array
    {
        return ['product_id' => $p->id, 'name' => $p->name, 'qty' => $qty, 'sale_price' => (int) $p->sale_price, 'subtotal' => (int) $p->sale_price * $qty];
    }

    public function test_round_review_ignores_preexisting_findings(): void
    {
        // 본 견적의 모니터 누락은 차수 검토에 나타나지 않는다
        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$this->round->id}/review")->assertOk();
        $this->assertSame([], $res->json('findings'));
        $this->assertSame('round', $res->json('context'));
    }

    public function test_round_review_reports_only_newly_caused_findings(): void
    {
        // 차수에 PC 1대 추가 → 모니터 부족이 1→2로 커짐 = 이 차수가 만든 새 발견
        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$this->round->id}/review", [
            'product_items' => [$this->item($this->pc, 1)],
        ])->assertOk();
        $this->assertSame('pc-monitor:2:0', $res->json('findings.0.key'));
    }

    public function test_round_adding_monitor_resolves_cleanly(): void
    {
        // 차수에 모니터 1대 추가 → 트리 전체가 충족, 발견 없음
        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$this->round->id}/review", [
            'product_items' => [$this->item($this->monitor, 1)],
        ])->assertOk();
        $this->assertSame([], $res->json('findings'));
    }

    public function test_parent_item_change_invalidates_round_review(): void
    {
        // 차수 검토 완료 후 본 견적 품목이 바뀌면 — 트리 지문 불일치로 발행 거부
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->round->id}/review")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->round->id}/review/finish")->assertOk();

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->round->id}", ['status' => 'issued'])->assertOk();
        Estimate::whereKey($this->round->id)->update(['status' => 'created']); // 재발행 시나리오용 되돌림

        $items = $this->parent->product_items;
        $items[0]['qty'] = 2;
        $this->parent->forceFill(['product_items' => $items])->save();

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->round->id}", ['status' => 'issued'])
            ->assertStatus(422);
    }
}
