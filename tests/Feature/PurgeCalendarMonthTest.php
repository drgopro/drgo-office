<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeCalendarMonthTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(string $start, string $end, string $title): Schedule
    {
        return Schedule::create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => $end,
            'is_all_day' => true,
            'color' => 'gold',
        ]);
    }

    public function test_rejects_invalid_month_format(): void
    {
        $this->artisan('calendar:purge-month', ['month' => '2026-13'])->assertFailed();
        $this->artisan('calendar:purge-month', ['month' => 'may'])->assertFailed();
    }

    public function test_dry_run_lists_but_deletes_nothing(): void
    {
        $may = $this->makeSchedule('2026-05-10', '2026-05-10', '5월 일정');

        $this->artisan('calendar:purge-month', ['month' => '2026-05'])
            ->expectsOutputToContain('1건')
            ->assertSuccessful();

        $this->assertNull($may->fresh()->deleted_at);
    }

    public function test_force_soft_deletes_only_target_month(): void
    {
        User::factory()->create(['role' => 'master']); // 이력 기록용 시스템 사용자
        $may = $this->makeSchedule('2026-05-10', '2026-05-10', '5월 일정');
        $june = $this->makeSchedule('2026-06-01', '2026-06-01', '6월 일정');
        $spanning = $this->makeSchedule('2026-04-30', '2026-05-02', '4월말~5월초 걸침');

        $this->artisan('calendar:purge-month', ['month' => '2026-05', '--force' => true])
            ->assertSuccessful();

        $this->assertSoftDeleted('schedules', ['id' => $may->id]);
        $this->assertNull($june->fresh()->deleted_at);
        $this->assertNull($spanning->fresh()->deleted_at, '이전 달에 시작한 걸침 일정은 삭제하지 않음');

        // 변경 이력에 스냅샷 기록
        $change = ScheduleChange::where('schedule_id', $may->id)->where('action', 'delete')->first();
        $this->assertNotNull($change);
        $this->assertSame('월 일괄 삭제 (2026-05)', $change->reason);
        $this->assertSame('5월 일정', data_get($change->changes, 'snapshot.title'));
    }
}
