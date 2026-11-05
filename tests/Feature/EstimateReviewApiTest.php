<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 검토 API — 실행/답변/완료 흐름과 답변 보존(7.4).
 * 판정 자체는 Unit\ReviewEngineTest가 담당하고, 여기서는 상태 저장·전이만 본다.
 */
class EstimateReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $estimate;

    private Product $pc;

    private Product $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master', 'display_name' => '검토자']);

        $this->pc = Product::create(['sku' => 'PC-1', 'name' => '송출용 PC', 'category' => 'PC', 'sale_price' => 1000000, 'is_active' => 1]);
        $this->monitor = Product::create(['sku' => 'MON-1', 'name' => '사무용 모니터', 'category' => 'PC', 'sale_price' => 200000, 'is_active' => 1]);

        // 최소 설정 — 컴퓨터에는 모니터가 필요
        Setting::set('estimate_review.config', json_encode([
            'version' => 1,
            'checks' => ['video' => false, 'audio' => false, 'lens' => false, 'battery' => false, 'mount' => false, 'spec' => false],
            'roles' => [['id' => 'pc', 'label' => '컴퓨터', 'group' => '컴퓨터'], ['id' => 'monitor', 'label' => '모니터', 'group' => '컴퓨터']],
            'rules' => [[
                'type' => 'need', 'group' => '컴퓨터', 'id' => 'pc-monitor', 'on' => ['pc'], 'need' => ['monitor'],
                'mode' => 'perUnit', 'amt' => 1, 'what' => '모니터', 'why' => '화면이 필요합니다.', 'active' => true,
            ]],
            'categoryRolesByPath' => [],
            'productRolesByName' => ['송출용 PC' => [['pc', 1]], '사무용 모니터' => [['monitor', 1]]],
            'specTemplatesByPath' => [],
            'specsByName' => [],
            'specsByPath' => [],
        ], JSON_UNESCAPED_UNICODE));

        $this->estimate = Estimate::create([
            'status' => 'created', 'service_items' => [],
            'product_items' => [[
                'product_id' => $this->pc->id, 'name' => '송출용 PC', 'qty' => 1,
                'sale_price' => 1000000, 'subtotal' => 1000000,
            ]],
            'product_total' => 1000000, 'service_total' => 0, 'total_amount' => 1000000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    private function runReview(?array $items = null)
    {
        return $this->actingAs($this->admin)->postJson(
            "/api/estimates/{$this->estimate->id}/review",
            $items === null ? [] : ['product_items' => $items],
        );
    }

    public function test_run_detects_missing_monitor_and_stores_state(): void
    {
        $res = $this->runReview()->assertOk();
        $res->assertJsonPath('findings.0.key', 'pc-monitor:1:0');
        $res->assertJsonPath('findings.0.kind', 'miss');
        $res->assertJsonPath('done', false);

        $state = $this->estimate->fresh()->review_state;
        $this->assertSame(['pc-monitor:1:0'], $state['keys']);
        $this->assertSame($res->json('fingerprint'), $state['fingerprint']);
    }

    public function test_run_passes_when_monitor_included(): void
    {
        $items = [
            ['product_id' => $this->pc->id, 'name' => '송출용 PC', 'qty' => 1, 'sale_price' => 1000000],
            ['product_id' => $this->monitor->id, 'name' => '사무용 모니터', 'qty' => 1, 'sale_price' => 200000],
        ];
        $res = $this->runReview($items)->assertOk();
        $this->assertSame([], $res->json('findings'));
        $this->assertSame(1, $res->json('counts.passed'));
    }

    public function test_answer_requires_prior_run_and_persists(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/answer", ['key' => 'pc-monitor:1:0', 'type' => 'ex', 'reason' => '고객 보유'])
            ->assertStatus(422);

        $this->runReview()->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/answer", ['key' => 'pc-monitor:1:0', 'type' => 'ex', 'reason' => '고객 보유', 'note' => '기존 모니터 사용'])
            ->assertOk();

        $state = $this->estimate->fresh()->review_state;
        $this->assertSame('고객 보유', $state['answers']['pc-monitor:1:0']['reason']);
        $this->assertSame('검토자', $state['answers']['pc-monitor:1:0']['by']);
    }

    public function test_same_key_answer_survives_rerun_and_is_pruned_when_resolved(): void
    {
        $this->runReview();
        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/answer", ['key' => 'pc-monitor:1:0', 'type' => 'ex', 'reason' => '고객 보유']);

        // 같은 발견이면 답변 유지 (7.4)
        $res = $this->runReview()->assertOk();
        $this->assertArrayHasKey('pc-monitor:1:0', (array) $res->json('answers'));

        // 모니터를 추가해 발견이 사라지면 답변도 정리
        $res = $this->runReview([
            ['product_id' => $this->pc->id, 'name' => '송출용 PC', 'qty' => 1, 'sale_price' => 1000000],
            ['product_id' => $this->monitor->id, 'name' => '사무용 모니터', 'qty' => 1, 'sale_price' => 200000],
        ])->assertOk();
        $this->assertSame([], (array) $res->json('answers'));
    }

    public function test_finish_requires_all_answers_and_matching_saved_items(): void
    {
        $this->runReview();
        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/finish")
            ->assertStatus(422); // 미답변

        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/answer", ['key' => 'pc-monitor:1:0', 'type' => 'ex', 'reason' => '고객 보유']);

        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/finish")
            ->assertOk()->assertJsonPath('reviewer', '검토자');

        $this->assertTrue($this->estimate->fresh()->review_state['done']);
    }

    public function test_finish_rejects_when_reviewed_items_differ_from_saved(): void
    {
        // 저장본과 다른 품목으로 검토 → 걸린 것 없음 → 완료 시도는 지문 불일치로 거절
        $this->runReview([
            ['product_id' => $this->pc->id, 'name' => '송출용 PC', 'qty' => 1, 'sale_price' => 1000000],
            ['product_id' => $this->monitor->id, 'name' => '사무용 모니터', 'qty' => 1, 'sale_price' => 200000],
        ])->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/estimates/{$this->estimate->id}/review/finish")
            ->assertStatus(422);
    }

    public function test_response_never_exposes_purchase_price(): void
    {
        $content = $this->runReview()->assertOk()->getContent();
        $this->assertStringNotContainsString('purchase_price', $content);
    }
}
