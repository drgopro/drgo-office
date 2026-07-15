<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 일정 확정 상태(제안/희망/목표/확정) — 확정 단계 추가 + 옵션 섹션 통합 */
class CalendarSchedOptTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    public function test_event_saves_confirmed_sched_opt(): void
    {
        $res = $this->actingAs($this->master())->postJson('/api/events', [
            'title' => '확정 일정',
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-20',
            'is_all_day' => true,
            'color' => 'blue',
            'sched_opt' => 'confirmed',
        ]);

        $res->assertCreated();
        $this->assertSame('confirmed', Schedule::first()->sched_opt);
    }

    public function test_calendar_page_renders_unified_options_with_confirmed(): void
    {
        $res = $this->actingAs($this->master())->get('/calendar');

        $res->assertOk()
            // 확정 버튼 + 확정 상태/시기 요청 서브 라벨 (섹션 통합)
            ->assertSee('data-sopt="confirmed"', false)
            ->assertSee('확정 상태 — 이 날짜가 얼마나 확정적인지 (하나 선택)')
            ->assertSee('시기 요청 (복수 선택 가능)')
            // 확정 칩 라벨·단계 설명
            ->assertSee("confirmed:'확정'", false)
            ->assertSee('의뢰자와 협의가 끝난 확정 일정')
            // 기존의 분리된 '일정 관련 옵션' 라벨은 제거됨
            ->assertDontSee('일정 관련 옵션');
    }

    public function test_calendar_modal_renders_2a_redesign_elements(): void
    {
        $res = $this->actingAs($this->master())->get('/calendar');

        $res->assertOk()
            // 좌측 폼 컬럼 + 우측 작성 현황 레일
            ->assertSee('class="m-main"', false)
            ->assertSee('id="modalRail"', false)
            ->assertSee('작성 현황')
            ->assertSee('남은 항목')
            // 진행률·섹션 카드 그룹핑 JS
            ->assertSee('function updateModalRail', false)
            ->assertSee("className='m-card'", false);
    }
}
