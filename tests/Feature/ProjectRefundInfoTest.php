<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 환불 정보 — 환불 요청/완료 일시 저장 + 환불 행 수정 */
class ProjectRefundInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @return array{0: Project, 1: ProjectPayment} 결제 1건이 등록된 프로젝트 */
    private function makeProjectWithCharge(int $amount = 100000): array
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '환불 테스트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payment", [
            'amount' => $amount, 'paid_at' => '2026-08-01',
        ])->assertOk();

        return [$project, ProjectPayment::where('project_id', $project->id)->where('type', 'charge')->firstOrFail()];
    }

    public function test_refund_stores_requested_and_refunded_datetimes(): void
    {
        [$project, $charge] = $this->makeProjectWithCharge();

        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund', 'amount' => 30000,
            'reason' => '부분 환불', 'refund_requested_at' => '2026-08-20T14:30', 'refunded_at' => '2026-08-22T10:05',
        ])->assertCreated();

        $refund = ProjectPayment::where('type', 'refund')->firstOrFail();
        $this->assertSame('2026-08-20 14:30', $refund->refund_requested_at->format('Y-m-d H:i'));
        $this->assertSame('2026-08-22 10:05', $refund->refunded_at->format('Y-m-d H:i'));

        // 목록 응답에도 포함
        $rows = collect($this->actingAs($this->admin)->getJson("/api/projects/{$project->id}/payments")->assertOk()->json('payments'));
        $row = $rows->firstWhere('type', 'refund');
        $this->assertSame('2026-08-20 14:30', $row['refund_requested_at']);
        $this->assertSame('2026-08-22 10:05', $row['refunded_at']);
    }

    public function test_refund_row_can_be_edited(): void
    {
        [$project, $charge] = $this->makeProjectWithCharge();
        $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund', 'amount' => 30000, 'reason' => '최초 사유',
        ])->assertCreated();
        $refund = ProjectPayment::where('type', 'refund')->firstOrFail();

        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}/payments/{$refund->id}", [
            'amount' => 50000, 'method' => '계좌 환불', 'memo' => '수정된 사유',
            'refund_requested_at' => '2026-08-21T09:00', 'refunded_at' => '2026-08-23T18:45',
        ])->assertOk();

        $fresh = $refund->fresh();
        $this->assertSame(-50000, $fresh->amount); // 양수 입력 → 음수 저장
        $this->assertSame('계좌 환불', $fresh->method);
        $this->assertSame('수정된 사유', $fresh->memo);
        $this->assertSame('2026-08-21 09:00', $fresh->refund_requested_at->format('Y-m-d H:i'));
        $this->assertSame('2026-08-23 18:45', $fresh->refunded_at->format('Y-m-d H:i'));
    }

    public function test_refund_edit_cannot_exceed_refundable_amount(): void
    {
        [$project, $charge] = $this->makeProjectWithCharge(100000);
        // 두 건의 부분 환불 (30,000 + 20,000)
        foreach ([30000, 20000] as $amt) {
            $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/payments/refund", [
                'parent_payment_id' => $charge->id, 'type' => 'refund', 'amount' => $amt,
            ])->assertCreated();
        }
        $first = ProjectPayment::where('type', 'refund')->orderBy('id')->firstOrFail();

        // 다른 환불(20,000)을 고려하면 최대 80,000까지 — 초과 시 422
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}/payments/{$first->id}", [
            'amount' => 90000,
        ])->assertStatus(422);
        $this->assertSame(-30000, $first->fresh()->amount); // 유지

        // 한도 내 수정은 허용
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}/payments/{$first->id}", [
            'amount' => 80000,
        ])->assertOk();
        $this->assertSame(-80000, $first->fresh()->amount);
    }
}
