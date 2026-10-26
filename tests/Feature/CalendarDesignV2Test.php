<?php

namespace Tests\Feature;

use App\Models\CalendarCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 디자인 v2 (가독성 개선) — html.cal-v2 범위 스타일 + 이전 디자인 복귀 토글 */
class CalendarDesignV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_v2_styles_are_scoped_and_enabled_unless_opted_out(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($user)->get('/calendar')->assertOk();

        // localStorage calDesign=v1 이면 클래스를 붙이지 않아 기존 디자인 유지
        $response->assertSee("localStorage.getItem('calDesign')!=='v1'", false)
            ->assertSee("document.documentElement.classList.add('cal-v2')", false)
            ->assertSee('.cal-v2 .event-chip {', false)
            ->assertSee('.cal-v2 .mc-chip, .cal-v2 .mc-chip.in-lane, .cal-v2 .mc-bar {', false);

        // 모든 v2 규칙은 .cal-v2 범위 안에서만 동작 (v1 화면에 새는 규칙 없음)
        preg_match('/<style>(.*?)<\/style>/s', $this->v2Partial(), $m);
        $rules = array_filter(array_map('trim', preg_split('/\{[^{}]*\}/', preg_replace('/\/\*.*?\*\//s', '', $m[1]))));
        foreach ($rules as $selectorGroup) {
            $selectorGroup = ltrim($selectorGroup, "} \n");
            if (str_starts_with($selectorGroup, '@')) {
                $selectorGroup = trim(preg_replace('/^@(media|container)[^{]*\{/', '', $selectorGroup));
            }
            if ($selectorGroup === '' || $selectorGroup === '}') {
                continue;
            }
            foreach (explode(',', $selectorGroup) as $selector) {
                $this->assertStringStartsWith('.cal-v2 ', trim($selector, " \n}"), "v2 범위 밖 규칙: {$selector}");
            }
        }
    }

    public function test_category_colors_are_generated_for_every_category(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($user)->get('/calendar')->assertOk();

        foreach (array_keys(CalendarCategory::map()) as $key) {
            $response->assertSee(".cal-v2 .event-chip.single.color-{$key} {", false)
                ->assertSee(".cal-v2 .mc-chip.color-{$key}, .cal-v2 .mc-bar.color-{$key} {", false);
        }
    }

    public function test_menu_has_toggle_back_to_previous_design(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('id="calDesignToggle"', false)
            ->assertSee('onclick="toggleCalDesign()"', false)
            ->assertSee('이전 디자인으로 보기')
            ->assertSee("localStorage.setItem('calDesign', CAL_V2?'v1':'v2')", false);
    }

    public function test_v2_rendering_hooks_are_present(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('function isTentativeEv', false)
            ->assertSee('function mcStateCls', false)
            ->assertSee('CAL_V2_ICONS', false)
            ->assertSee("'sched-pill sched-end'", false)
            ->assertSee("calV2MonthAlias=view==='month'&&calMobileV2();", false)
            ->assertSee("if(!calMobileV2()&&currentView==='monthc'&&calV2MonthAlias)", false)
            ->assertSee('.cal-v2 .event-chip.single.multi-day.is-tentative.color-', false);
    }

    public function test_option_icons_survive_narrow_cells(): void
    {
        // 화면이 좁아지면 아이콘(차량 등)이 0px로 눌려 일정 특징을 알 수 없던 문제 — 아이콘은 줄지 않고 담당자·시간이 먼저 빠짐
        $partial = $this->v2Partial();

        $this->assertStringContainsString('.cal-v2 .opt-ic { display:inline-flex; align-items:center; flex-shrink:0; min-width:auto; overflow:visible;', $partial);
        $this->assertStringContainsString('.cal-v2 .day-cell, .cal-v2 .mc-cell, .cal-v2 .mc-bar { container-type:inline-size; }', $partial);
        $this->assertStringContainsString('.cal-v2 .mc-bar .mc-assignee { display:none; }', $partial);
        $this->assertMatchesRegularExpression('/@container \(max-width: 150px\) \{[^@]*\.chip-badges[^@]*sp-short/s', $partial);
        $this->assertMatchesRegularExpression('/@container \(max-width: 110px\) \{[^@]*\.chip-time/s', $partial);
        // 모바일 칩에서도 아이콘을 숨기지 않음
        $this->assertStringNotContainsString('.cal-v2 .mc-chip .opt-ic, .cal-v2 .mc-chip .opt-chip', $partial);
        $this->assertStringContainsString('.cal-v2 .mc-chip .opt-ic, .cal-v2 .mc-bar .opt-ic { color:inherit; }', $partial);
    }

    private function v2Partial(): string
    {
        return view('calendar.partials.styles-v2')->render();
    }
}
