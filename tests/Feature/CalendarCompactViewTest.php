<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 컴팩트 월간 뷰 — 네이버 캘린더식 고밀도 그리드 (균등 6주 셀 + 칩 + "+N") */
class CalendarCompactViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_renders_compact_month_view(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('id="tabMonthC"', false)
            ->assertSee("switchView('monthc')", false)
            ->assertSee('id="monthCompactView"', false)
            ->assertSee('function renderMonthCompact', false)
            ->assertSee('mc-chip', false)
            ->assertSee('mc-bar', false)
            ->assertSee('data-mcmore', false);
    }

    public function test_wheel_month_navigation_only_after_second_scroll_at_edge(): void
    {
        // 휠만 굴리면 바로 달이 넘어가던 문제 — 스크롤 끝에 닿은 뒤 새 휠 동작에서만 이동, 그 전엔 페이지 스크롤 유지
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('function wheelAtEdge', false)
            ->assertSee('if(!wheelAtEdge(sc,dir)){ armedDir=0; return; }', false)
            // 뷰 안의 자체 스크롤 영역에서는 달을 넘기지 않음 / 버튼·뷰 전환으로 화면이 바뀌면 예고 무효
            ->assertSee('if(wheelInsideInnerScroller(e.target, el)){ armedDir=0; return; }', false)
            ->assertSee('if(armedDir===dir&&armedKey===key&&gap>WHEEL_GESTURE_GAP){', false)
            ->assertSee('if(now-movedAt<WHEEL_MOVE_COOLDOWN){ e.preventDefault(); return; }', false)
            ->assertSee('sc.scrollTop = dir>0 ? 0 : sc.scrollHeight;', false)
            ->assertDontSee('if(now-lock<400||Math.abs(e.deltaY)<15) return;', false);
    }

    public function test_mobile_bottom_sheet_is_included(): void
    {
        // 모바일: 전체화면 그리드 + 하단 바를 올리면 선택일 리스트 (네이버 모바일 방식)
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('id="mcSheet"', false)
            ->assertSee('mcSheetHandle', false)
            ->assertSee('function mcSelectDay', false)
            ->assertSee("addEventListener('touchend'", false);
    }
}
