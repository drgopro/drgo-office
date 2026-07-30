<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\StaleScheduleReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** schedules:remind-stale — 지난 미완료 일정 상태 정리 리마인드 (경과 1·3·7일차) */
class StaleScheduleReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(array $attrs, User $user): Schedule
    {
        $schedule = Schedule::create(array_merge([
            'title' => '지난 일정', 'color' => 'gold', 'is_all_day' => true,
        ], $attrs));
        $assignee = Assignee::firstOrCreate(['name' => $user->display_name], ['user_id' => $user->id]);
        $schedule->assignees()->attach($assignee->id);

        return $schedule;
    }

    public function test_reminds_at_day_1_and_skips_completed_red_and_other_days(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $yesterday = now()->subDay()->format('Y-m-d');
        $target = $this->makeSchedule(['title' => '1일 경과', 'start_date' => $yesterday, 'end_date' => $yesterday], $user);
        $this->makeSchedule(['title' => '완료됨', 'start_date' => $yesterday, 'end_date' => $yesterday, 'completed_at' => now()], $user);
        $this->makeSchedule(['title' => '휴가', 'start_date' => $yesterday, 'end_date' => $yesterday, 'color' => 'red'], $user);
        $d2 = now()->subDays(2)->format('Y-m-d');
        $this->makeSchedule(['title' => '2일 경과(발송일 아님)', 'start_date' => $d2, 'end_date' => $d2], $user);

        $this->artisan('schedules:remind-stale')
            ->expectsOutputToContain('알림 1건 발송')
            ->assertSuccessful();

        Notification::assertSentTo($user, StaleScheduleReminder::class, function ($n) {
            return $n->schedule->title === '1일 경과' && $n->daysOver === 1;
        });
        Notification::assertCount(1);
    }

    public function test_reminds_again_at_day_3(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $d3 = now()->subDays(3)->format('Y-m-d');
        $this->makeSchedule(['title' => '3일 경과', 'start_date' => $d3, 'end_date' => $d3], $user);

        $this->artisan('schedules:remind-stale')->assertSuccessful();

        Notification::assertSentTo($user, StaleScheduleReminder::class, fn ($n) => $n->daysOver === 3);
    }
}
