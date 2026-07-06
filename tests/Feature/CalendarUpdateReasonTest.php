<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarUpdateReasonTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(string $color = 'gold'): Schedule
    {
        return Schedule::create([
            'title' => '방문 세팅',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-06',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'color' => $color,
        ]);
    }

    public function test_gold_date_change_requires_reason(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule('gold');

        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'start_date' => '2026-07-08',
            'end_date' => '2026-07-08',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'start_date' => '2026-07-08',
            'end_date' => '2026-07-08',
            'reason' => '의뢰자 요청',
        ])->assertOk();

        $this->assertSame('2026-07-08', $schedule->fresh()->start_date->format('Y-m-d'));
    }

    public function test_gold_time_change_requires_reason(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule('teal');

        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'start_time' => '15:00',
        ])->assertUnprocessable();
    }

    public function test_gold_title_only_change_does_not_require_reason(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule('gold');

        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'title' => '제목만 수정',
        ])->assertOk();

        // 이력은 사유 없이도 기록됨
        $this->assertSame(1, ScheduleChange::where('schedule_id', $schedule->id)->count());
    }

    public function test_non_gold_teal_date_change_does_not_require_reason(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule('blue');

        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-10',
        ])->assertOk();
    }

    public function test_unchanged_datetime_resubmit_does_not_require_reason(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule('gold');

        // 동일한 날짜/시간을 다시 보내도 (포맷 차이 포함) 사유 불필요
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-06',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'title' => '방문 세팅',
        ])->assertOk();
    }
}
