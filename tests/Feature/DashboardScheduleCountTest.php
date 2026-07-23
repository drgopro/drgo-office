<?php

namespace Tests\Feature;

use App\Models\CalendarCategory;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 대시보드 업무 현황 — 일정 카운트에서 사내업무/휴가·개인/디자인 제외 */
class DashboardScheduleCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_count_excludes_internal_vacation_design(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $date = now()->startOfMonth()->addDays(3)->toDateString();

        Schedule::create(['title' => '방문 세팅', 'start_date' => $date, 'end_date' => $date, 'color' => 'gold']);
        Schedule::create(['title' => '원격 지원', 'start_date' => $date, 'end_date' => $date, 'color' => 'teal']);
        Schedule::create(['title' => '사내 회의', 'start_date' => $date, 'end_date' => $date, 'color' => 'blue']);
        Schedule::create(['title' => '연차', 'start_date' => $date, 'end_date' => $date, 'color' => 'red']);
        CalendarCategory::updateOrCreate(['key' => 'design'], ['label' => '디자인', 'color' => '#8888cc', 'text_color' => '#fff', 'sort_order' => 99, 'is_active' => true]);
        Schedule::create(['title' => '로고 제작', 'start_date' => $date, 'end_date' => $date, 'color' => 'design']);
        CalendarCategory::updateOrCreate(['key' => 'rental'], ['label' => '렌탈', 'color' => '#cc88aa', 'text_color' => '#fff', 'sort_order' => 100, 'is_active' => true]);
        Schedule::create(['title' => '렌탈 시작', 'start_date' => $date, 'end_date' => $date, 'color' => 'rental']);

        // gold + teal 2건만 집계 (blue/red/design/rental 제외)
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('scheduleThisMonth', 2);
    }
}
