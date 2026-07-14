<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 유형별 진행 단계 flow — 단계 검증·자동 진행·렌더링 */
class ProjectTypeFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeProject(string $type, string $stage = 'consulting'): Project
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        return Project::create([
            'client_id' => $client->id,
            'name' => $type.' 프로젝트',
            'project_type' => $type,
            'stage' => $stage,
        ]);
    }

    public function test_update_stage_accepts_flow_codes_and_rejects_others(): void
    {
        $filming = $this->makeProject('filming');

        // 촬영 flow 전용 단계(사전답사) 허용
        $this->actingAs($this->admin())->patchJson("/projects/{$filming->id}/stage", ['stage' => 'survey'])->assertOk();
        $this->assertSame('survey', $filming->fresh()->stage);

        // 문의 flow에 없는 단계(payment)는 거부
        $inquiry = $this->makeProject('inquiry');
        $this->actingAs($this->admin())->patchJson("/projects/{$inquiry->id}/stage", ['stage' => 'payment'])
            ->assertUnprocessable();

        // cancelled는 어떤 유형이든 허용
        $this->actingAs($this->admin())->patchJson("/projects/{$inquiry->id}/stage", [
            'stage' => 'cancelled', 'cancel_reason' => '고객 취소',
        ])->assertOk();
    }

    public function test_save_payment_advances_stage_only_when_flow_has_payment(): void
    {
        // 내방 유형: 결제 저장 시 payment 단계로 자동 진행
        $store = $this->makeProject('store');
        $this->actingAs($this->admin())->postJson("/api/projects/{$store->id}/payment", [
            'amount' => 50000, 'paid_at' => '2026-07-14',
        ])->assertOk();
        $this->assertSame('payment', $store->fresh()->stage);

        // 문의 유형: flow에 결제 단계가 없으므로 단계는 그대로, 결제 기록만 남음
        $inquiry = $this->makeProject('inquiry');
        $this->actingAs($this->admin())->postJson("/api/projects/{$inquiry->id}/payment", [
            'amount' => 30000, 'paid_at' => '2026-07-14',
        ])->assertOk();
        $this->assertSame('consulting', $inquiry->fresh()->stage);
        $this->assertSame(1, $inquiry->payments()->count());
    }

    public function test_show_page_renders_type_flow_stages(): void
    {
        $filming = $this->makeProject('filming');

        $res = $this->actingAs($this->admin())->get("/projects/{$filming->id}");

        $res->assertOk()
            ->assertSee('사전답사')
            ->assertSee('촬영')
            ->assertDontSee('장비파악');
    }

    public function test_rental_flow_ends_at_payment_stage(): void
    {
        $rental = $this->makeProject('rental');

        $this->actingAs($this->admin())->patchJson("/projects/{$rental->id}/stage", ['stage' => 'payment'])->assertOk();
        $this->assertSame('payment', $rental->fresh()->stage);
        $this->assertSame('결제/계약', $rental->fresh()->stageLabel());
    }
}
