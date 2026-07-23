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
        Schedule::create(['title' => '내방 세팅', 'start_date' => $date, 'end_date' => $date, 'color' => 'purple']); // 미팅/내방은 반드시 포함
        Schedule::create(['title' => '사내 회의', 'start_date' => $date, 'end_date' => $date, 'color' => 'blue']);
        Schedule::create(['title' => '연차', 'start_date' => $date, 'end_date' => $date, 'color' => 'red']);
        CalendarCategory::updateOrCreate(['key' => 'design'], ['label' => '디자인', 'color' => '#8888cc', 'text_color' => '#fff', 'sort_order' => 99, 'is_active' => true]);
        Schedule::create(['title' => '로고 제작', 'start_date' => $date, 'end_date' => $date, 'color' => 'design']);
        CalendarCategory::updateOrCreate(['key' => 'rental'], ['label' => '렌탈', 'color' => '#cc88aa', 'text_color' => '#fff', 'sort_order' => 100, 'is_active' => true]);
        Schedule::create(['title' => '렌탈 시작', 'start_date' => $date, 'end_date' => $date, 'color' => 'rental']);

        // gold + teal + purple(미팅/내방) 3건 집계 (blue/red/design/rental 제외)
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('scheduleThisMonth', 3);
    }

    public function test_work_status_splits_total_and_new_clients(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('총 의뢰자')
            ->assertSee('신규 의뢰자')
            ->assertSee('grid-template-columns:repeat(5,1fr)', false);
    }

    public function test_consult_status_cards_wrap_instead_of_overflowing(): void
    {
        // 고정 5열 그리드가 우측 컬럼 밑으로 넘치던 문제 — 자동 줄바꿈 그리드 유지 확인
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('.pl-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));', false);
    }
}
