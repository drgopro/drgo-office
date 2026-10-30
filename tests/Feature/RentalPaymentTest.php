<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use App\Services\ContractCalendarSync;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 렌탈 결제 현황 — 결제일(갱신일)별 결제됨 체크/해제, 미납·오늘·예정 상태, 캘린더 ✅ 표시 */
class RentalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->master = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '윤민아', 'grade' => 'normal', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createContract(array $extra = []): RentalContract
    {
        $res = $this->actingAs($this->master)->postJson('/api/rental-contracts', array_merge([
            'client_id' => $this->client->id, 'start_date' => '2026-07-18', 'end_date' => null, 'monthly_fee' => 158000, 'status' => 'active',
        ], $extra))->assertCreated();

        return RentalContract::findOrFail($res->json('id'));
    }

    public function test_due_dates_follow_start_day_until_one_month_ahead_and_states_are_derived(): void
    {
        $contract = $this->createContract();

        $this->assertSame(['2026-07-18', '2026-08-18', '2026-09-18', '2026-10-18', '2026-11-18'], $contract->dueDates());

        $list = $this->actingAs($this->master)->getJson('/api/rental-contracts?kind=equipment')->assertOk()->json('0');
        $this->assertSame(['overdue', 'overdue', 'overdue', 'overdue', 'upcoming'], array_column($list['payments'], 'state'));
        $this->assertSame(4, $list['payment_summary']['overdue']);
        $this->assertSame('2026-11-18', $list['payment_summary']['next_due']);

        // 오늘이 결제일이면 due, 말일 시작은 말일 클램프
        $today = $this->createContract(['start_date' => '2026-08-30']);
        $states = collect($today->paymentStatus())->pluck('state', 'due_date')->all();
        $this->assertSame('due', $states['2026-10-30']);
        $this->assertSame('upcoming', $states['2026-11-30']);
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_slice($this->createContract(['start_date' => '2026-01-31'])->dueDates(), 0, 3));
    }

    public function test_pending_has_no_due_dates_and_terminated_stops_at_end_date(): void
    {
        $pending = $this->createContract(['status' => 'pending']);
        $this->assertSame([], $pending->dueDates());

        $ended = $this->createContract(['end_date' => '2026-09-30', 'status' => 'terminated']);
        $this->assertSame(['2026-07-18', '2026-08-18', '2026-09-18'], $ended->dueDates());
    }

    public function test_toggle_marks_paid_and_updates_calendar_title_then_unmarks(): void
    {
        $contract = $this->createContract();
        $group = $contract->calendar_meta['repeat_group'];

        $res = $this->actingAs($this->master)->postJson("/api/rental-contracts/{$contract->id}/payments", ['due_date' => '2026-09-18', 'paid' => true])->assertOk();
        $pay = collect($res->json('payments'))->firstWhere('due_date', '2026-09-18');
        $this->assertSame('paid', $pay['state']);
        $this->assertSame(158000, $pay['amount']);
        $this->assertSame($this->master->display_name, $pay['paid_by']);
        $this->assertSame(3, $res->json('payment_summary.overdue'));
        $this->assertDatabaseHas('rental_payments', ['contract_id' => $contract->id, 'due_date' => '2026-09-18', 'paid_by' => $this->master->id]);

        $sched = Schedule::where('repeat_group', $group)->whereDate('start_date', '2026-09-18')->first();
        $this->assertStringStartsWith(ContractCalendarSync::PAID_PREFIX, $sched->title);
        $this->assertStringStartsWith('렌탈 결제 · 윤민아', Schedule::where('repeat_group', $group)->whereDate('start_date', '2026-08-18')->first()->title);

        // 재동기화(계약 편집)해도 ✅ 유지
        $this->actingAs($this->master)->patchJson("/api/rental-contracts/{$contract->id}", ['memo' => '편집'])->assertOk();
        $group2 = $contract->fresh()->calendar_meta['repeat_group'];
        $this->assertStringStartsWith(ContractCalendarSync::PAID_PREFIX, Schedule::where('repeat_group', $group2)->whereDate('start_date', '2026-09-18')->first()->title);

        // 해제
        $res = $this->actingAs($this->master)->postJson("/api/rental-contracts/{$contract->id}/payments", ['due_date' => '2026-09-18', 'paid' => false])->assertOk();
        $this->assertSame('overdue', collect($res->json('payments'))->firstWhere('due_date', '2026-09-18')['state']);
        $this->assertDatabaseMissing('rental_payments', ['contract_id' => $contract->id]);
        $this->assertStringStartsWith('렌탈 결제 · 윤민아', Schedule::where('repeat_group', $group2)->whereDate('start_date', '2026-09-18')->first()->title);
    }

    public function test_toggle_rejects_non_schedule_dates_and_requires_rental_edit(): void
    {
        $contract = $this->createContract();

        $this->actingAs($this->master)->postJson("/api/rental-contracts/{$contract->id}/payments", ['due_date' => '2026-09-19', 'paid' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('due_date');
        // 아직 멀리 있는 결제일(1개월 넘게 남음)은 체크 불가
        $this->actingAs($this->master)->postJson("/api/rental-contracts/{$contract->id}/payments", ['due_date' => '2026-12-18', 'paid' => true])
            ->assertUnprocessable();

        $viewTeam = Team::create(['name' => '조회팀', 'slug' => 'view-'.uniqid(), 'permissions' => ['rental.view']]);
        $viewer = User::factory()->create(['role' => 'member', 'team_id' => $viewTeam->id]);
        $this->actingAs($viewer)->postJson("/api/rental-contracts/{$contract->id}/payments", ['due_date' => '2026-09-18', 'paid' => true])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/rental-contracts')->assertOk()->assertJsonStructure(['*' => ['payments', 'payment_summary']]);
    }

    public function test_old_ongoing_contract_gets_calendar_mark_and_orphaned_payment_stays_visible(): void
    {
        // 2024년 시작 — 오늘(2026-10-30) 기준 12개월까지 결제 반복이 있어야 ✅ 표시 가능
        $old = $this->createContract(['start_date' => '2024-01-10']);
        $group = $old->calendar_meta['repeat_group'];
        $this->assertSame('2027-10-10', Schedule::where('repeat_group', $group)->max('start_date'));

        $this->actingAs($this->master)->postJson("/api/rental-contracts/{$old->id}/payments", ['due_date' => '2026-10-10', 'paid' => true])->assertOk();
        $this->assertStringStartsWith(ContractCalendarSync::PAID_PREFIX, Schedule::where('repeat_group', $group)->whereDate('start_date', '2026-10-10')->first()->title);

        // 시작일을 바꿔 결제 일정이 달라져도 기존 결제 기록은 paid 로 계속 보이고 해제할 수 있다
        $this->actingAs($this->master)->patchJson("/api/rental-contracts/{$old->id}", ['start_date' => '2024-01-20'])->assertOk();
        $list = $this->actingAs($this->master)->getJson('/api/rental-contracts')->assertOk()->json();
        $row = collect($list)->firstWhere('id', $old->id);
        $orphan = collect($row['payments'])->firstWhere('due_date', '2026-10-10');
        $this->assertSame('paid', $orphan['state']);
        $this->assertContains('2026-10-20', array_column($row['payments'], 'due_date'));

        $this->actingAs($this->master)->postJson("/api/rental-contracts/{$old->id}/payments", ['due_date' => '2026-10-10', 'paid' => false])->assertOk();
        $this->assertDatabaseMissing('rental_payments', ['contract_id' => $old->id]);
        // 일정에 없는 날짜를 새로 체크하는 건 여전히 불가
        $this->actingAs($this->master)->postJson("/api/rental-contracts/{$old->id}/payments", ['due_date' => '2026-10-10', 'paid' => true])->assertUnprocessable();
    }

    public function test_deleting_contract_removes_its_payments(): void
    {
        $contract = $this->createContract();
        $this->actingAs($this->master)->postJson("/api/rental-contracts/{$contract->id}/payments", ['due_date' => '2026-07-18', 'paid' => true])->assertOk();
        $this->actingAs($this->master)->deleteJson("/api/rental-contracts/{$contract->id}")->assertOk();

        // 소프트 삭제라 FK cascade 는 안 타지만 목록에서 빠지고, 복원 시 결제 기록은 남아 있다
        $this->actingAs($this->master)->getJson('/api/rental-contracts')->assertOk()->assertJsonCount(0);
        $this->assertDatabaseHas('rental_payments', ['contract_id' => $contract->id]);
    }

    public function test_page_renders_payment_strip_and_overdue_filter(): void
    {
        $html = $this->actingAs($this->master)->get('/rental-contracts')->assertOk()->getContent();
        // 목록은 결제 요약만, 월별 이력·체크는 '관리' 모달(조회 모드 → '수정'으로 폼 전환)
        $this->assertStringContainsString('function paySummaryHtml', $html);
        $this->assertStringContainsString('function renderContractView', $html);
        $this->assertStringContainsString('id="contractView"', $html);
        $this->assertStringContainsString("onclick=\"setContractMode('edit')\">수정</button>", $html);
        $this->assertStringContainsString('>관리</button>', $html);
        $this->assertStringNotContainsString('expandedPay', $html);
        $this->assertStringContainsString('<option value="overdue">미납 있음</option>', $html);
        $this->assertStringContainsString('id="statOverdue"', $html);
    }
}
