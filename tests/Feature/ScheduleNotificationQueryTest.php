<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleCreated;
use App\Notifications\ScheduleReminder;
use App\Notifications\ScheduleUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 일정 알림 제목의 담당자 이름 — 생성 시 한 번만 조회하고 채널·수신자마다 다시 조회하지 않는다.
 */
class ScheduleNotificationQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_names_are_queried_once_per_notification(): void
    {
        $users = User::factory()->count(3)->create();
        $schedule = Schedule::create([
            'title' => '방송룸 점검',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'start_time' => '14:00',
            'is_all_day' => false,
            'color' => 'blue',
            'created_by' => $users[0]->id,
        ]);
        $schedule->assignees()->attach($users->map(fn ($u) => Assignee::firstWhere('user_id', $u->id)->id)->all());
        $expectedNames = $schedule->assignees()->pluck('name')->implode(', ');

        foreach ([ScheduleCreated::class, ScheduleUpdated::class, ScheduleReminder::class] as $class) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $notification = new $class($schedule->fresh());
            foreach ($users as $user) {
                $this->assertStringContainsString($expectedNames, $notification->toArray($user)['title']);
                $notification->toWebPush($user);
            }
            $assigneeQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "assignees"'))->count();
            DB::disableQueryLog();

            $this->assertSame(1, $assigneeQueries, $class);
        }
    }
}
