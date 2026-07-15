<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 잔금(request_data.balance=O) ↔ 청구(project_billings) 동기화 */
class CalendarBalanceBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '닥터고', 'grade' => 'normal']);
        $this->project = Project::create([
            'client_id' => $client->id, 'name' => '방문 세팅', 'project_type' => 'visit', 'stage' => 'payment',
        ]);
    }

    /** @return array<string, mixed> */
    private function goldPayload(array $gold = []): array
    {
        return [
            'title' => '닥터고 방문',
            'start_date' => '2026-07-21',
            'end_date' => '2026-07-21',
            'is_all_day' => true,
            'color' => 'gold',
            'request_data' => array_merge([
                'client_id' => $this->project->client_id,
                'project_id' => $this->project->id,
                'nickname' => '닥터고', 'name' => '', 'phone' => '',
                'balance' => 'O',
                'balance_amount' => '50,000',
            ], $gold),
        ];
    }

    public function test_balance_creates_billing_linked_to_project(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/events', $this->goldPayload());

        $res->assertCreated();
        $billing = ProjectBilling::first();
        $this->assertNotNull($billing, '잔금이 청구로 등록됨');
        $this->assertSame($this->project->id, $billing->project_id);
        $this->assertSame(50000, $billing->amount);
        $this->assertSame('unpaid', $billing->status);
        // 일정에 청구 id가 기억됨
        $this->assertSame($billing->id, (int) data_get(Schedule::first()->request_data, 'balance_billing_id'));
    }

    public function test_resave_updates_billing_without_duplicating(): void
    {
        $this->actingAs($this->user)->postJson('/api/events', $this->goldPayload())->assertCreated();
        $schedule = Schedule::first();

        // 클라이언트 재저장 — request_data에 balance_billing_id 없이 전송 (실제 폼 동작과 동일)
        $this->actingAs($this->user)->patchJson("/api/events/{$schedule->id}", $this->goldPayload([
            'balance_amount' => '70,000',
        ]))->assertOk();

        $this->assertSame(1, ProjectBilling::count(), '중복 청구 생성 없음');
        $this->assertSame(70000, ProjectBilling::first()->amount, '금액 최신화');
    }

    public function test_balance_off_removes_unpaid_billing(): void
    {
        $this->actingAs($this->user)->postJson('/api/events', $this->goldPayload())->assertCreated();
        $schedule = Schedule::first();

        $this->actingAs($this->user)->patchJson("/api/events/{$schedule->id}", $this->goldPayload([
            'balance' => 'X', 'balance_amount' => '',
        ]))->assertOk();

        $this->assertSame(0, ProjectBilling::count(), '잔금 해제 시 미입금 청구 제거');
    }

    public function test_project_linked_balance_display_does_not_create_billing(): void
    {
        // 결제/잔금이 프로젝트 결제 연동 표시값(balance_source=project)이면 캘린더발 청구를 만들지 않음
        $this->actingAs($this->user)->postJson('/api/events', $this->goldPayload([
            'balance_source' => 'project',
        ]))->assertCreated();

        $this->assertSame(0, ProjectBilling::count(), '프로젝트 연동 값은 이중 청구 생성 안 함');
    }

    public function test_switching_to_project_linked_removes_calendar_billing(): void
    {
        // 수기 잔금으로 청구가 생긴 일정이 프로젝트 연동으로 전환되면 미입금 청구는 정리됨
        $this->actingAs($this->user)->postJson('/api/events', $this->goldPayload())->assertCreated();
        $this->assertSame(1, ProjectBilling::count());
        $schedule = Schedule::first();

        $this->actingAs($this->user)->patchJson("/api/events/{$schedule->id}", $this->goldPayload([
            'balance_source' => 'project',
        ]))->assertOk();

        $this->assertSame(0, ProjectBilling::count());
    }

    public function test_legacy_gold_data_key_is_accepted(): void
    {
        // 배포 전에 열려 있던 구버전 탭이 gold_data 키로 저장해도 request_data로 승계됨
        $payload = $this->goldPayload();
        $payload['gold_data'] = $payload['request_data'];
        unset($payload['request_data']);

        $this->actingAs($this->user)->postJson('/api/events', $payload)->assertCreated();

        $schedule = Schedule::first();
        $this->assertSame($this->project->id, (int) data_get($schedule->request_data, 'project_id'));
        $this->assertSame(1, ProjectBilling::count(), '잔금 청구 자동 생성도 동일 동작');
    }

    public function test_schedule_delete_removes_unpaid_billing(): void
    {
        $this->actingAs($this->user)->postJson('/api/events', $this->goldPayload())->assertCreated();
        $schedule = Schedule::first();

        $this->actingAs($this->user)->deleteJson("/api/events/{$schedule->id}", ['reason' => '테스트 삭제'])->assertOk();

        $this->assertSame(0, ProjectBilling::count());
    }
}
