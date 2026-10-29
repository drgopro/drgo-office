<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 일정 수정 로그에 담당자 변경 이력 — 구성원이 바뀔 때만 이름 목록으로 기록, 순서만 바뀌면 기록 없음 */
class CalendarAssigneeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    /** @return array{0: Schedule, 1: Assignee, 2: Assignee, 3: Assignee} */
    private function scheduleWithOneAssignee(User $user): array
    {
        $a = Assignee::create(['name' => '김웅', 'is_active' => true, 'display_order' => 0]);
        $b = Assignee::create(['name' => '박진혁', 'is_active' => true, 'display_order' => 1]);
        $c = Assignee::create(['name' => '조유신', 'is_active' => true, 'display_order' => 2]);

        $this->actingAs($user)->postJson('/api/events', [
            'title' => '세팅', 'start_date' => '2026-07-22', 'end_date' => '2026-07-22',
            'is_all_day' => true, 'color' => 'blue',
            'assignees' => [$a->id],
        ])->assertCreated();

        return [Schedule::first(), $a, $b, $c];
    }

    public function test_adding_and_removing_assignees_is_logged_with_names(): void
    {
        $user = $this->master();
        [$schedule, $a, $b, $c] = $this->scheduleWithOneAssignee($user);
        $this->assertSame(0, ScheduleChange::where('schedule_id', $schedule->id)->where('action', 'update')->count());

        // 추가: 김웅 → 김웅, 박진혁 (제목 등 다른 변경 없이 담당자만)
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'assignees' => [$a->id, $b->id],
        ])->assertOk();

        $log = ScheduleChange::where('schedule_id', $schedule->id)->where('action', 'update')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['old' => ['김웅'], 'new' => ['김웅', '박진혁']], $log->changes['assignees']);
        $this->assertSame($user->id, $log->user_id);

        // 교체: 김웅, 박진혁 → 조유신
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'assignees' => [$c->id],
        ])->assertOk();
        $log = ScheduleChange::where('schedule_id', $schedule->id)->where('action', 'update')->latest('id')->first();
        $this->assertSame(['old' => ['김웅', '박진혁'], 'new' => ['조유신']], $log->changes['assignees']);

        // 전부 해제
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'assignees' => [],
        ])->assertOk();
        $log = ScheduleChange::where('schedule_id', $schedule->id)->where('action', 'update')->latest('id')->first();
        $this->assertSame(['old' => ['조유신'], 'new' => []], $log->changes['assignees']);
        $this->assertSame(3, ScheduleChange::where('schedule_id', $schedule->id)->where('action', 'update')->count());
    }

    public function test_reorder_only_or_same_assignees_is_not_logged(): void
    {
        $user = $this->master();
        [$schedule, $a, $b] = $this->scheduleWithOneAssignee($user);
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", ['assignees' => [$a->id, $b->id]])->assertOk();
        $before = ScheduleChange::where('schedule_id', $schedule->id)->count();

        // 순서만 변경
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", ['assignees' => [$b->id, $a->id]])->assertOk();
        // 동일 구성 재전송 (중복 id 포함)
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", ['assignees' => [$b->id, $a->id, $a->id]])->assertOk();
        // assignees: null 은 '건드리지 않음'(실제 동기화도 생략) — 해제 이력이 생기면 안 됨
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", ['assignees' => null])->assertOk();
        $this->assertCount(2, $schedule->fresh()->assignees);
        // 담당자 키 없이 제목만 수정 — 담당자 diff 없음
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", ['title' => '세팅 (수정)'])->assertOk();

        $this->assertSame($before + 1, ScheduleChange::where('schedule_id', $schedule->id)->count());
        $titleLog = ScheduleChange::where('schedule_id', $schedule->id)->latest('id')->first();
        $this->assertArrayHasKey('title', $titleLog->changes);
        $this->assertArrayNotHasKey('assignees', $titleLog->changes);
    }

    public function test_assignee_change_does_not_require_reason_on_gold_and_shows_in_history(): void
    {
        $user = $this->master();
        $a = Assignee::create(['name' => '김웅', 'is_active' => true, 'display_order' => 0]);
        $schedule = Schedule::create([
            'title' => '방문 세팅', 'start_date' => '2026-07-06', 'end_date' => '2026-07-06',
            'start_time' => '13:00', 'end_time' => '14:00', 'color' => 'gold',
        ]);

        // gold는 날짜/시간 변경에만 사유 필수 — 담당자만 바꿀 때는 사유 없이 저장
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", ['assignees' => [$a->id]])->assertOk();

        $res = $this->actingAs($user)->getJson("/api/events/{$schedule->id}/history");
        $res->assertOk();
        $changes = collect($res->json('changes'));
        $this->assertSame(['old' => [], 'new' => ['김웅']], $changes->first()['changes']['assignees']);
        $this->assertSame('update', $changes->first()['action']);
    }
}
