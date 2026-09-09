<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적 취소(quote_cancelled) — 결제 취소와 별개의 '진행 무산' 상태.
 * 프로젝트 취소 → 연동 견적서 자동 견적 취소 (상위→하위), 반대 방향 전파는 없음.
 */
class EstimateQuoteCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function makeEstimate(array $attrs = []): Estimate
    {
        return Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'created_by' => $this->admin->id, ...$attrs,
        ]);
    }

    public function test_estimate_can_be_set_to_quote_cancelled(): void
    {
        $estimate = $this->makeEstimate();

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'status' => 'quote_cancelled',
        ])->assertOk();

        $this->assertSame('quote_cancelled', $estimate->fresh()->status);
    }

    public function test_project_cancellation_voids_linked_active_estimates(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
        $active = $this->makeEstimate(['project_id' => $project->id, 'status' => 'issued']);
        $paid = $this->makeEstimate(['project_id' => $project->id, 'status' => 'paid']);
        $other = $this->makeEstimate(['status' => 'created']); // 미연동 — 영향 없음

        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", [
            'stage' => 'cancelled', 'cancel_reason' => '단순 변심',
        ])->assertOk();

        $this->assertSame('quote_cancelled', $active->fresh()->status); // 진행 중 견적 → 자동 견적 취소
        $this->assertSame('paid', $paid->fresh()->status);              // 결제 이력 있는 견적은 보존
        $this->assertSame('created', $other->fresh()->status);
    }

    public function test_quote_cancel_does_not_cancel_parent_project(): void
    {
        // 하위(견적서) 취소가 상위(프로젝트)를 취소시키지 않는다
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
        $estimate = $this->makeEstimate(['project_id' => $project->id, 'status' => 'issued']);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'status' => 'quote_cancelled',
        ])->assertOk();

        $this->assertSame('quote_cancelled', $estimate->fresh()->status);
        $this->assertSame('consulting', $project->fresh()->stage); // 프로젝트는 그대로
        $this->assertNull($project->fresh()->cancelled_at);
    }

    public function test_list_excludes_quote_cancelled_and_cancelled_tab_shows_them(): void
    {
        $this->makeEstimate(['status' => 'created', 'client_nickname' => '일반건']);
        $this->makeEstimate(['status' => 'quote_cancelled', 'client_nickname' => '견적취소건']);
        $this->makeEstimate(['status' => 'cancelled', 'client_nickname' => '결제취소건']);

        // 기본 목록 — 견적 취소 제외 (결제 취소는 기존대로 포함)
        $list = $this->actingAs($this->admin)->getJson('/api/estimates')->assertOk()->json();
        $nicknames = array_column($list, 'client_nickname');
        $this->assertContains('일반건', $nicknames);
        $this->assertContains('결제취소건', $nicknames);
        $this->assertNotContains('견적취소건', $nicknames);

        // 취소 탭 — 견적 취소 + 결제 취소만
        $cancelled = $this->actingAs($this->admin)->getJson('/api/estimates?view=cancelled')->assertOk()->json();
        $cNames = array_column($cancelled, 'client_nickname');
        $this->assertSame(['견적취소건', '결제취소건'], collect($cNames)->sort()->values()->all());
    }

    public function test_multi_status_filter_with_comma(): void
    {
        $this->makeEstimate(['status' => 'created', 'client_nickname' => 'A']);
        $this->makeEstimate(['status' => 'paid', 'client_nickname' => 'B']);
        $this->makeEstimate(['status' => 'hold', 'client_nickname' => 'C']);

        $res = $this->actingAs($this->admin)->getJson('/api/estimates?status=paid,hold')->assertOk()->json();
        $this->assertSame(['B', 'C'], collect(array_column($res, 'client_nickname'))->sort()->values()->all());
    }
}
