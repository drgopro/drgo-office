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
}
