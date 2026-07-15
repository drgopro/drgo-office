<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 청구·잔금 관리 — 청구 체크 → 입금 추적 → 완료 처리 */
class ProjectBillingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeProject(string $type = 'visit'): Project
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        return Project::create([
            'client_id' => $client->id,
            'name' => '테스트 프로젝트',
            'project_type' => $type,
            'stage' => 'consulting',
        ]);
    }

    public function test_billing_created_and_stage_advances_to_payment(): void
    {
        $project = $this->makeProject('visit');

        $res = $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/billings", [
            'amount' => 200000, 'billed_at' => '2026-07-14', 'memo' => '세팅비',
        ]);

        $res->assertCreated()->assertJsonPath('status', 'unpaid');
        $this->assertSame('payment', $project->fresh()->stage, '청구 시 결제 단계로 자동 진행');
    }

    public function test_billing_does_not_advance_stage_for_flow_without_payment(): void
    {
        $project = $this->makeProject('inquiry');

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/billings", [
            'amount' => 50000,
        ])->assertCreated();

        $this->assertSame('consulting', $project->fresh()->stage);
    }

    public function test_linked_payments_track_balance_to_completion(): void
    {
        $project = $this->makeProject();
        $billing = ProjectBilling::create([
            'project_id' => $project->id, 'amount' => 100000, 'billed_at' => '2026-07-01',
        ]);

        // 부분 입금 → partial
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 40000, 'paid_at' => '2026-07-05', 'billing_id' => $billing->id,
        ])->assertOk();
        $this->assertSame('partial', $billing->fresh()->status);
        $this->assertSame(60000, $billing->fresh()->balance());

        // 잔액 전액 입금 → paid (청구 완료)
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 60000, 'paid_at' => '2026-07-10', 'billing_id' => $billing->id,
        ])->assertOk();
        $this->assertSame('paid', $billing->fresh()->status);
        $this->assertSame(0, $billing->fresh()->balance());
    }

    public function test_refund_of_linked_payment_reopens_balance(): void
    {
        $project = $this->makeProject();
        $billing = ProjectBilling::create([
            'project_id' => $project->id, 'amount' => 100000, 'billed_at' => '2026-07-01',
        ]);
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 100000, 'paid_at' => '2026-07-05', 'billing_id' => $billing->id,
        ])->assertOk();
        $this->assertSame('paid', $billing->fresh()->status);

        $charge = ProjectPayment::where('billing_id', $billing->id)->where('type', 'charge')->first();
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund', 'amount' => 30000,
        ])->assertCreated();

        $this->assertSame('partial', $billing->fresh()->status, '환불 시 잔금 재계산');
        $this->assertSame(30000, $billing->fresh()->balance());
    }

    public function test_manual_mark_paid_and_delete(): void
    {
        $project = $this->makeProject();
        $billing = ProjectBilling::create([
            'project_id' => $project->id, 'amount' => 80000, 'billed_at' => '2026-07-01',
        ]);

        // 수동 완료 처리 (잔금이 남아도 종결)
        $this->actingAs($this->admin())->patchJson("/api/project-billings/{$billing->id}", ['status' => 'paid'])
            ->assertOk()->assertJsonPath('billing.status', 'paid');

        // 삭제 — 연결 입금은 보존 (billing_id만 해제)
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 10000, 'billing_id' => $billing->id,
        ])->assertOk();
        $this->actingAs($this->admin())->deleteJson("/api/project-billings/{$billing->id}")->assertOk();
        $this->assertNull(ProjectBilling::find($billing->id));
        $this->assertSame(1, ProjectPayment::where('project_id', $project->id)->where('type', 'charge')->count());
    }

    public function test_payment_with_balance_flag_creates_billing_automatically(): void
    {
        $project = $this->makeProject();

        // 결제 모달의 '잔금 여부 O + 잔금 금액' — 잔금이 청구로 자동 등록되어야 함
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 100000,
            'paid_at' => '2026-07-14',
            'has_balance' => true,
            'balance_amount' => 50000,
            'memo' => '잔금',
        ])->assertOk();

        $billing = ProjectBilling::where('project_id', $project->id)->first();
        $this->assertNotNull($billing, '잔금이 청구로 등록됨');
        $this->assertSame(50000, $billing->amount);
        $this->assertSame('unpaid', $billing->status);
        $this->assertSame('2026-07-14', $billing->billed_at->format('Y-m-d'));

        // 잔금 관리 화면에 노출
        $this->actingAs($this->admin())->get('/projects-billing')
            ->assertOk()
            ->assertSee('50,000원');
    }

    public function test_payment_edit_saves_balance_flag_and_syncs_billing(): void
    {
        $project = $this->makeProject();

        // 잔금 없이 결제 기록 후 — 수정에서 잔금 O + 금액 입력
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 100000, 'paid_at' => '2026-07-14', 'has_balance' => false,
        ])->assertOk();
        $payment = ProjectPayment::where('project_id', $project->id)->first();
        $this->assertSame(0, ProjectBilling::count());

        $this->actingAs($this->admin())->patchJson("/api/projects/{$project->id}/payments/{$payment->id}", [
            'amount' => 100000, 'has_balance' => true, 'balance_amount' => 70000,
        ])->assertOk();

        $info = $project->fresh()->payment_info;
        $this->assertTrue((bool) $info['has_balance'], '잔금 여부 저장');
        $this->assertSame(70000, (int) $info['balance_amount'], '잔금 금액 저장');
        $billing = ProjectBilling::where('project_id', $project->id)->first();
        $this->assertNotNull($billing, '잔금 자동 청구 생성');
        $this->assertSame(70000, $billing->amount);

        // 재수정 — 금액 변경은 기존 청구 재사용 (중복 생성 없음)
        $this->actingAs($this->admin())->patchJson("/api/projects/{$project->id}/payments/{$payment->id}", [
            'amount' => 100000, 'has_balance' => true, 'balance_amount' => 30000,
        ])->assertOk();
        $this->assertSame(1, ProjectBilling::count());
        $this->assertSame(30000, ProjectBilling::first()->amount);

        // 잔금 X로 변경 — 미입금 자동 청구 정리
        $this->actingAs($this->admin())->patchJson("/api/projects/{$project->id}/payments/{$payment->id}", [
            'amount' => 100000, 'has_balance' => false,
        ])->assertOk();
        $this->assertSame(0, ProjectBilling::count());
        $this->assertFalse((bool) $project->fresh()->payment_info['has_balance']);
    }

    public function test_payment_without_balance_flag_creates_no_billing(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 100000, 'has_balance' => false, 'balance_amount' => 0,
        ])->assertOk();

        $this->assertSame(0, ProjectBilling::count());
    }

    public function test_billing_index_lists_outstanding_only(): void
    {
        $project = $this->makeProject();
        ProjectBilling::create(['project_id' => $project->id, 'amount' => 120000, 'billed_at' => '2026-07-01', 'memo' => '방송룸 이용료']);
        ProjectBilling::create(['project_id' => $project->id, 'amount' => 50000, 'billed_at' => '2026-07-02', 'status' => 'paid']);

        $res = $this->actingAs($this->admin())->get('/projects-billing');

        $res->assertOk()
            ->assertSee('잔금 관리')
            ->assertSee('120,000원')
            ->assertSee('방송룸 이용료')
            ->assertDontSee('50,000원');
    }

    public function test_calendar_summary_includes_outstanding_balance(): void
    {
        $project = $this->makeProject();
        $billing = ProjectBilling::create(['project_id' => $project->id, 'amount' => 90000, 'billed_at' => '2026-07-01']);
        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 30000, 'billing_id' => $billing->id,
        ])->assertOk();

        $res = $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/summary");

        $res->assertOk()->assertJsonPath('outstanding_balance', 60000);
    }
}
