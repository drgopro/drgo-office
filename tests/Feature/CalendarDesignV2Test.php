<?php

namespace Tests\Feature;

use App\Models\CalendarCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 디자인 v2 (가독성 개선) — 기본은 이전 디자인(v1), html.cal-v2 범위 스타일은 opt-in 토글로만 적용 */
class CalendarDesignV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_v2_styles_are_scoped_and_off_unless_opted_in(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($user)->get('/calendar')->assertOk();

        // 기본은 이전 디자인 — localStorage calDesign=v2 로 직접 켠 사람에게만 cal-v2 클래스를 붙임
        $response->assertSee("var v2=false;try{v2=localStorage.getItem('calDesign')==='v2';}", false)
            ->assertDontSee("localStorage.getItem('calDesign')!=='v1'", false)
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

    public function test_menu_has_opt_in_toggle_to_new_design(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('id="calDesignToggle"', false)
            ->assertSee('onclick="toggleCalDesign()"', false)
            ->assertSee('<span id="calDesignToggleLabel">새 디자인으로 보기</span>', false)
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

    public function test_desktop_month_card_chip_and_click_popover_hooks(): void
    {
        // 디자인 'Calendar Dual View' — 데스크탑 월간은 2행 카드 칩(시간·제목 / 상태·담당자), 상세는 클릭 팝오버. 모바일은 기존 그대로
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('function calV2Card(){ return CAL_V2 && window.innerWidth>768; }', false)
            ->assertSee('function buildChipCardHtml', false)
            ->assertSee('function openEventPopover', false)
            ->assertSee('id="calEvPop"', false)
            ->assertSee('id="calLegend"', false)
            ->assertSee('chip.innerHTML=card?buildChipCardHtml(ev):buildChipHtml(ev);', false)
            ->assertSee('if(card)openEventPopover(ev,chip); else openDetailModal(ev);', false)
            // 연일(다일) 바도 2행 카드 — 제목 오버레이가 카드 레이아웃을 그리고 바 높이를 셀 칩과 맞춤
            ->assertSee("ov.className='mday-title-overlay'+(ev.completed_at?' is-completed':'')+(card?' chip-card':'');", false)
            ->assertSee('ov.innerHTML=card?buildChipCardHtml(ev):buildChipHtml(ev);', false);

        $partial = $this->v2Partial();
        $this->assertStringContainsString('@media (min-width: 769px) {', $partial);
        $this->assertStringContainsString('.cal-v2 .event-chip.chip-card { height:calc(42px * var(--cal-fz,1)); flex-direction:row;', $partial);
        // 사용자 스케치: 왼쪽 시간 세로 2줄 / 완료는 글씨 위 체크
        $this->assertStringContainsString('.cal-v2 .chip-card .cc-time { display:flex; flex-direction:column;', $partial);
        $this->assertStringContainsString('.cal-v2 .chip-card.is-completed .chip-title::after {', $partial);
        $this->assertStringContainsString('.cal-v2 .event-chip.multi-day, .cal-v2 .lane-spacer { height:calc(42px * var(--cal-fz,1)); }', $partial);
        $this->assertStringContainsString('.cal-v2 .mday-title-overlay.chip-card { flex-direction:row;', $partial);
        $this->assertStringContainsString('.cal-v2 .ev-pop { position:fixed;', $partial);
        $this->assertStringContainsString('.cal-v2 .cal-legend { order:12;', $partial);
        // 모바일 규칙 블록에는 카드/팝오버가 없음
        preg_match('/@media \(max-width: 768px\) \{(.*)$/s', $partial, $m);
        $this->assertStringNotContainsString('chip-card', $m[1]);
        $this->assertStringNotContainsString('.ev-pop', $m[1]);
    }

    private function v2Partial(): string
    {
        return view('calendar.partials.styles-v2')->render();
    }
}
