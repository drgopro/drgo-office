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
