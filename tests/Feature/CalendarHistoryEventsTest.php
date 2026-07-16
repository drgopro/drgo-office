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
