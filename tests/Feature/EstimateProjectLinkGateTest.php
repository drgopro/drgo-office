<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 발행(결제요청 생성) 전 프로젝트 연동 필수 — 의뢰자가 연결된 견적서만 대상.
 * 익명(의뢰자 없음) 견적서와 차수(추가 견적)는 예외, 작성 중 저장은 막지 않는다.
 */
class EstimateProjectLinkGateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        // 검토 게이트는 끄고 프로젝트 연동 관문만 검증
        Setting::set('estimate_review.config', json_encode(['gate' => false]));
    }

    private function makeEstimate(array $over = []): Estimate
    {
        return Estimate::create(array_merge([
            'status' => 'completed', 'service_items' => [],
            'product_items' => [['name' => '카메라', 'qty' => 1, 'sale_price' => 100000, 'subtotal' => 100000]],
            'product_total' => 100000, 'service_total' => 0, 'total_amount' => 100000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ], $over));
    }

    public function test_client_estimate_without_project_cannot_be_issued(): void
    {
        $estimate = $this->makeEstimate(['client_id' => $this->client->id]);

        $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/issue")
            ->assertStatus(422)->assertJsonFragment(['message' => '발행할 수 없습니다 — 의뢰자의 프로젝트를 연동해야 발행(결제요청)할 수 있습니다. 견적서에서 프로젝트를 선택한 뒤 다시 시도해주세요.']);
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", ['status' => 'issued'])->assertStatus(422);

        $this->assertSame('completed', $estimate->fresh()->status);
        $this->assertNull($estimate->fresh()->issued_at);
    }

    public function test_client_estimate_with_project_can_be_issued(): void
    {
        $project = Project::create(['client_id' => $this->client->id, 'name' => '세팅', 'stage' => 'consulting']);
        $estimate = $this->makeEstimate(['client_id' => $this->client->id, 'project_id' => $project->id]);

        $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/issue")->assertOk();
        $this->assertSame('issued', $estimate->fresh()->status);
    }

    public function test_linking_project_in_the_same_save_allows_issue_and_unlinking_blocks_it(): void
    {
        $project = Project::create(['client_id' => $this->client->id, 'name' => '세팅', 'stage' => 'consulting']);

        // 프로젝트 연동과 발행을 한 번의 저장으로
        $estimate = $this->makeEstimate(['client_id' => $this->client->id]);
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", ['status' => 'issued', 'project_id' => $project->id])->assertOk();
        $this->assertSame('issued', $estimate->fresh()->status);

        // 같은 저장에서 연동을 해제하면서 발행하려 하면 막힌다
        $linked = $this->makeEstimate(['client_id' => $this->client->id, 'project_id' => $project->id]);
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$linked->id}", ['status' => 'issued', 'project_id' => null])->assertStatus(422);
        $this->assertSame('completed', $linked->fresh()->status);
    }

    public function test_anonymous_estimate_is_exempt(): void
    {
        $estimate = $this->makeEstimate(['client_name' => '전화 문의 고객']);

        $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/issue")->assertOk();
        $this->assertSame('issued', $estimate->fresh()->status);
    }

    public function test_drafts_can_still_be_saved_without_project(): void
    {
        $estimate = $this->makeEstimate(['client_id' => $this->client->id, 'status' => 'created']);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", ['status' => 'completed', 'memo' => '작성 중'])->assertOk();
        $this->assertSame('completed', $estimate->fresh()->status);
    }

    public function test_round_of_already_issued_estimate_is_exempt(): void
    {
        // 규칙 도입 전에 발행·결제된 미연동 견적서의 추가 견적은 막지 않는다
        $parent = $this->makeEstimate(['client_id' => $this->client->id, 'status' => 'paid', 'estimate_no' => 200, 'paid_at' => now()]);
        $roundId = $this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->assertSuccessful()->json('id');

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$roundId}", [
            'product_items' => [['name' => '추가 마이크', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000]],
            'service_items' => [],
            'status' => 'issued',
        ])->assertOk();
        $this->assertSame('issued', Estimate::find($roundId)->status);
    }

    public function test_direct_payment_request_and_direct_paid_mark_use_the_same_gate(): void
    {
        $estimate = $this->makeEstimate(['client_id' => $this->client->id]);

        $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/payapp-request")->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '프로젝트를 연동해야'));
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", ['status' => 'paid'])->assertStatus(422);
        $this->assertSame('completed', $estimate->fresh()->status);
    }

    public function test_already_issued_estimate_without_project_can_still_be_marked_paid(): void
    {
        // 규칙 도입 전에 발행된 미연동 견적서 — 결제완료 기록은 막지 않는다
        $estimate = $this->makeEstimate(['client_id' => $this->client->id, 'status' => 'issued', 'issued_at' => now(), 'estimate_no' => 300]);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", ['status' => 'paid'])->assertOk();
        $this->assertSame('paid', $estimate->fresh()->status);
    }

    public function test_project_of_another_client_does_not_satisfy_the_gate(): void
    {
        $other = Client::create(['nickname' => '다른 의뢰자', 'grade' => 'normal']);
        $otherProject = Project::create(['client_id' => $other->id, 'name' => '남의 프로젝트', 'stage' => 'consulting']);
        $estimate = $this->makeEstimate(['client_id' => $this->client->id, 'project_id' => $otherProject->id]);

        $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/issue")->assertStatus(422);
        $this->assertSame('completed', $estimate->fresh()->status);
    }
}
