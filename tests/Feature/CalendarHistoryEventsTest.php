<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarHistoryEventsTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    /** start_date를 두 번 옮긴 일정 (2026-07-06 → 07-13 → 07-20) */
    private function movedTwiceSchedule(User $editor): Schedule
    {
        $schedule = Schedule::create([
            'title' => '세팅 일정',
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-20',
            'color' => 'gold',
        ]);

        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => $editor->id,
            'action' => 'update',
            'changes' => ['start_date' => ['old' => '2026-07-06', 'new' => '2026-07-13']],
        ])->forceFill(['created_at' => now()->subHours(2)])->save();

        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => $editor->id,
            'action' => 'update',
            'changes' => ['start_date' => ['old' => '2026-07-13', 'new' => '2026-07-20']],
        ])->forceFill(['created_at' => now()->subHour()])->save();

        return $schedule;
    }

    public function test_only_last_modified_shadow_is_shown(): void
    {
        $member = $this->member();
        $this->movedTwiceSchedule($member);

        $res = $this->actingAs($member)->getJson('/api/events/history?start=2026-07-01&end=2026-07-31');

        $res->assertOk();
        $chips = collect($res->json());

        $this->assertCount(1, $chips->where('state', 'active'), '현재 위치 chip 1건');
        $modified = $chips->where('state', 'modified')->values();
        $this->assertCount(1, $modified, '변경 흔적은 마지막 1건만');
        $this->assertSame('2026-07-13', $modified[0]['display_start_date'], '마지막 변경의 이전 위치');

        // 로그는 모두 DB에 보존
        $this->assertSame(2, ScheduleChange::count());
    }

    public function test_change_log_returns_move_and_delete_sentences(): void
    {
        $member = $this->member();
        $schedule = $this->movedTwiceSchedule($member); // update 로그 2건 (start_date 이동)

        // 제목만 바꾼 update — 이동이 아니므로 로그에서 제외
        ScheduleChange::create([
            'schedule_id' => $schedule->id, 'user_id' => $member->id, 'action' => 'update',
            'changes' => ['title' => ['old' => 'A', 'new' => 'B']],
        ]);

        $deleted = Schedule::create(['title' => '취소된 방문', 'start_date' => '2026-07-25', 'end_date' => '2026-07-25', 'color' => 'gold']);
        ScheduleChange::create([
            'schedule_id' => $deleted->id, 'user_id' => $member->id, 'action' => 'delete',
            'changes' => [], 'reason' => '의뢰자 요청',
        ]);
        $deleted->delete();

        $res = $this->actingAs($member)->getJson('/api/events/change-log');

        $res->assertOk();
        $items = collect($res->json());
        $this->assertCount(3, $items, '이동 2건 + 삭제 1건 (제목 변경은 제외)');

        $del = $items->firstWhere('kind', 'delete');
        $this->assertStringContainsString("'취소된 방문' 일정을 삭제했습니다", $del['text']);
        $this->assertSame('의뢰자 요청', $del['reason']);

        $move = $items->firstWhere('kind', 'move');
        $this->assertStringContainsString('옮겼습니다', $move['text']);
        $this->assertStringContainsString('07-13 → 07-20', $move['text']);
    }

    public function test_unchanged_and_completed_schedules_are_hidden(): void
    {
        $member = $this->member();
        Schedule::create(['title' => '단순 등록 일정', 'start_date' => '2026-07-10', 'end_date' => '2026-07-10', 'color' => 'blue']);
        Schedule::create(['title' => '완료된 일정', 'start_date' => '2026-07-11', 'end_date' => '2026-07-11', 'color' => 'gold', 'completed_at' => now()]);

        $res = $this->actingAs($member)->getJson('/api/events/history?start=2026-07-01&end=2026-07-31');

        // 이동·삭제 없는 일정(등록만/완료 포함)은 이력에 노출되지 않음
        $res->assertOk();
        $this->assertCount(0, collect($res->json()));
    }

    public function test_deleted_schedule_shows_only_deleted_chip(): void
    {
        $member = $this->member();
        $schedule = $this->movedTwiceSchedule($member);
        $schedule->delete(); // soft delete

        $res = $this->actingAs($member)->getJson('/api/events/history?start=2026-07-01&end=2026-07-31');

        $res->assertOk();
        $chips = collect($res->json());

        $this->assertCount(0, $chips->where('state', 'active'));
        $this->assertCount(0, $chips->where('state', 'modified'), '삭제된 일정은 변경 흔적 미노출');
        $deleted = $chips->where('state', 'deleted')->values();
        $this->assertCount(1, $deleted, '삭제 chip만 노출');
        $this->assertSame('2026-07-20', $deleted[0]['display_start_date'], '삭제 시점 위치');

        $this->assertSame(2, ScheduleChange::count(), '로그는 모두 DB에 보존');
    }
}
