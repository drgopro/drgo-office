<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\LeaveGrant;
use App\Models\LeaveUsage;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use App\Services\LeaveLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 연차 관리 — 캘린더 휴가 일정 연동 차감, 부여/사용 요약, 권한 */
class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->member = User::factory()->create(['role' => 'member', 'display_name' => '고블린', 'hire_date' => '2023-05-10']);
    }

    private function memberAssigneeId(): int
    {
        return Assignee::firstWhere('user_id', $this->member->id)->id;
    }

    public function test_red_schedule_with_deduct_creates_usages_skipping_weekends(): void
    {
        // 2026-08-27(목) ~ 2026-08-31(월) — 주말(29토/30일) 제외 3일 차감
        $schedule = Schedule::create([
            'title' => '여름 휴가', 'start_date' => '2026-08-27', 'end_date' => '2026-08-31',
            'color' => 'red', 'is_all_day' => true,
            'request_data' => ['leave_deduct' => 'full'], 'created_by' => $this->member->id,
        ]);
        $schedule->syncAssigneesOrdered([$this->memberAssigneeId()], notify: false);

        $usages = LeaveUsage::where('user_id', $this->member->id)->orderBy('used_on')->get();
        $this->assertSame(['2026-08-27', '2026-08-28', '2026-08-31'], $usages->pluck('used_on')->map->format('Y-m-d')->all());
        $this->assertSame(3.0, (float) $usages->sum('days'));
        $this->assertSame('연차', $usages->first()->type);

        // 반차로 수정 → 0.5일씩 재계산 (멱등)
        $schedule->update(['request_data' => ['leave_deduct' => 'half'], 'end_date' => '2026-08-27']);
        $usages = LeaveUsage::where('user_id', $this->member->id)->get();
        $this->assertCount(1, $usages);
        $this->assertSame(0.5, (float) $usages->first()->days);
        $this->assertSame('반차', $usages->first()->type);

        // 차감 해제 → 기록 제거
        $schedule->update(['request_data' => null]);
        $this->assertSame(0, LeaveUsage::count());
    }

    public function test_schedule_delete_removes_calendar_usages_but_keeps_manual(): void
    {
        $schedule = Schedule::create([
            'title' => '연차', 'start_date' => '2026-08-28', 'end_date' => '2026-08-28',
            'color' => 'red', 'is_all_day' => true, 'request_data' => ['leave_deduct' => 'full'],
        ]);
        $schedule->syncAssigneesOrdered([$this->memberAssigneeId()], notify: false);
        LeaveUsage::create(['user_id' => $this->member->id, 'used_on' => '2026-08-14', 'days' => 1, 'type' => '연차', 'created_by' => $this->admin->id]);
        $this->assertSame(2, LeaveUsage::count());

        $schedule->delete(); // 소프트 삭제
        $this->assertSame(1, LeaveUsage::count());
        $this->assertNull(LeaveUsage::first()->schedule_id);
    }

    public function test_calendar_api_preserves_leave_deduct_for_red(): void
    {
        // red는 의뢰자 연동 정보가 제거되지만 leave_deduct 플래그는 유지돼야 한다
        $res = $this->actingAs($this->admin)->postJson('/api/events', [
            'title' => 'API 휴가', 'start_date' => '2026-09-03', 'end_date' => '2026-09-03',
            'color' => 'red', 'is_all_day' => true,
            'assignees' => [$this->memberAssigneeId()],
            'request_data' => ['leave_deduct' => 'full', 'client_id' => 999],
        ])->assertCreated();

        $schedule = Schedule::find($res->json('id') ?? $res->json('schedule.id') ?? Schedule::where('title', 'API 휴가')->value('id'));
        $this->assertSame(['leave_deduct' => 'full'], $schedule->request_data);
        $this->assertSame(1, LeaveUsage::where('schedule_id', $schedule->id)->where('user_id', $this->member->id)->count());
    }

    public function test_grant_suggestion_by_hire_date(): void
    {
        $this->assertNull(LeaveLedger::suggestGrant(null, 2026));
        $this->assertNull(LeaveLedger::suggestGrant('2027-01-01', 2026));
        $this->assertSame(15.0, LeaveLedger::suggestGrant('2025-03-01', 2026)['days']); // 1년차
        $this->assertSame(16.0, LeaveLedger::suggestGrant('2023-05-10', 2026)['days']); // 3년차 = 15+1
        $this->assertSame(25.0, LeaveLedger::suggestGrant('2000-01-01', 2026)['days']); // 상한
        $this->assertSame(4.0, LeaveLedger::suggestGrant('2026-08-15', 2026)['days']);  // 입사 연도 — 월 발생

        // 회계연도(1/1) 기준 — 입사 이듬해는 비례연차, 이후는 1/1 기산 근속
        $prorated = LeaveLedger::suggestGrant('2025-07-01', 2026, true);
        $this->assertSame(7.5, $prorated['days']); // 전년 재직 184일 → 15×184/365 ≈ 7.56 → 7.5
        $this->assertStringContainsString('비례연차', $prorated['label']);
        $this->assertSame(16.0, LeaveLedger::suggestGrant('2023-05-10', 2026, true)['days']);
        $this->assertStringContainsString('회계연도', LeaveLedger::suggestGrant('2023-05-10', 2026, true)['label']);
        // 미체크 시 이듬해는 기존 근속 계산
        $this->assertSame(15.0, LeaveLedger::suggestGrant('2025-07-01', 2026)['days']);
    }

    public function test_my_leave_page_and_guest_blocked(): void
    {
        LeaveGrant::create(['user_id' => $this->member->id, 'year' => now()->year, 'days' => 16]);
        LeaveUsage::create(['user_id' => $this->member->id, 'used_on' => now()->format('Y-m-d'), 'days' => 1, 'type' => '연차']);

        $this->actingAs($this->member)->get('/leave')->assertOk()
            ->assertSee('내 연차')->assertSee('16일')->assertSee('15일'); // 부여 16, 잔여 15

        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->get('/leave')->assertForbidden();
    }

    public function test_manage_requires_permission_and_apis_work(): void
    {
        // 권한 없는 member → 관리 페이지 접근 불가
        $this->actingAs($this->member)->get('/leave/manage')->assertForbidden();

        // 인사 정보 — admin도 자동 통과 없음, master는 허용
        $this->actingAs($this->admin)->get('/leave/manage')->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/api/leave/users/{$this->member->id}/hire-date", ['hire_date' => '2024-02-01'])->assertForbidden();
        $master = User::factory()->create(['role' => 'master']);
        $this->actingAs($master)->get('/leave/manage')->assertOk()
            ->assertSee('lvHireChanged', false) // 입사일 입력 즉시 제안값 계산·자동 채움
            ->assertSee('function lvSuggest', false);

        // leave.manage 팀 권한 부여 → 접근 가능
        $team = Team::create(['name' => '경영지원', 'slug' => 'mgmt-support', 'permissions' => ['leave.manage']]);
        $staff = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
        $this->actingAs($staff)->get('/leave/manage')->assertOk();

        // 입사일/기산 방식/부여/수동 기록 API
        $this->actingAs($staff)->patchJson("/api/leave/users/{$this->member->id}/hire-date", ['hire_date' => '2024-02-01', 'fiscal_leave' => true])->assertOk();
        $this->assertSame('2024-02-01', $this->member->fresh()->hire_date->format('Y-m-d'));
        $this->assertTrue($this->member->fresh()->fiscal_leave);
        $this->actingAs($staff)->patchJson("/api/leave/users/{$this->member->id}/hire-date", ['hire_date' => '2024-02-01', 'fiscal_leave' => false])->assertOk();
        $this->assertFalse($this->member->fresh()->fiscal_leave);

        $this->actingAs($staff)->putJson("/api/leave/users/{$this->member->id}/grant", ['year' => 2026, 'days' => 15.5, 'note' => '이월 0.5 포함'])->assertOk();
        $this->assertSame(15.5, (float) LeaveGrant::where('user_id', $this->member->id)->where('year', 2026)->first()->days);

        $this->actingAs($staff)->postJson("/api/leave/users/{$this->member->id}/usages", ['used_on' => '2026-05-01', 'days' => 0.5, 'type' => '반차'])->assertOk();
        $manual = LeaveUsage::where('user_id', $this->member->id)->first();

        // 캘린더 연동 기록은 관리 페이지에서 삭제 불가
        $schedule = Schedule::create(['title' => '연차', 'start_date' => '2026-05-07', 'end_date' => '2026-05-07', 'color' => 'red', 'request_data' => ['leave_deduct' => 'full']]);
        $schedule->syncAssigneesOrdered([$this->memberAssigneeId()], notify: false);
        $calUsage = LeaveUsage::whereNotNull('schedule_id')->first();
        $this->actingAs($staff)->deleteJson("/api/leave/usages/{$calUsage->id}")->assertUnprocessable();

        // 수동 기록은 삭제 가능
        $this->actingAs($staff)->deleteJson("/api/leave/usages/{$manual->id}")->assertOk();
        $this->assertNull(LeaveUsage::find($manual->id));
    }
}
