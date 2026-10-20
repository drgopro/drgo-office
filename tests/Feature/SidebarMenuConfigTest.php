<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SidebarMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 좌측 사이드바 메뉴 구성 — 관리 페이지에서 순서/섹션 라벨/구분선 조정 */
class SidebarMenuConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_layout_and_sidebar_follows_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 캘린더를 맨 앞으로 + 사용자 지정 섹션 라벨
        $rows = [
            ['t' => 'label', 'text' => '자주 쓰는 메뉴'],
            ['t' => 'menu', 'k' => 'calendar'],
            ['t' => 'gap'],
            ['t' => 'menu', 'k' => 'dashboard'],
        ];
        $this->actingAs($admin)->postJson('/api/admin/sidebar-menu', ['rows' => $rows])->assertOk();

        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();
        $nav = substr($html, strpos($html, 'id="mainNav"'), strpos($html, 'nav-mobile-only') - strpos($html, 'id="mainNav"'));

        $this->assertStringContainsString('자주 쓰는 메뉴', $nav);
        $this->assertLessThan(strpos($nav, '대시보드'), strpos($nav, '캘린더'), '저장한 순서대로 캘린더가 먼저');
        // 저장에서 빠진 메뉴도 사라지지 않고 아래에 이어진다
        $this->assertStringContainsString('공지사항', $nav);
        $this->assertStringContainsString('연차', $nav);
    }

    public function test_layout_sanitizes_unknown_keys_and_reset_restores_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set(SidebarMenu::SETTING_KEY, json_encode([
            ['t' => 'menu', 'k' => 'calendar'],
            ['t' => 'menu', 'k' => 'no-such-menu'], // 무시
            ['t' => 'menu', 'k' => 'calendar'], // 중복 무시
            ['t' => 'label', 'text' => '  '], // 빈 라벨 무시
        ]));

        $layout = SidebarMenu::layout();
        $this->assertSame('calendar', $layout[0]['k']);
        $this->assertCount(count(SidebarMenu::registry()), collect($layout)->where('t', 'menu'), '레지스트리 전 메뉴가 정확히 1회씩');

        $this->actingAs($admin)->postJson('/api/admin/sidebar-menu/reset')->assertOk();
        $this->assertSame(SidebarMenu::defaultLayout(), SidebarMenu::layout());
    }

    public function test_member_cannot_change_layout(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->postJson('/api/admin/sidebar-menu', ['rows' => [['t' => 'gap']]])->assertForbidden();
        $this->actingAs($member)->getJson('/api/admin/sidebar-menu')->assertForbidden();
    }

    public function test_visible_rows_prune_empty_sections_for_restricted_user(): void
    {
        // 권한 적은 사용자 — 섹션의 메뉴가 전부 숨겨지면 라벨도 숨고, 구분선이 겹치지 않는다
        Setting::set(SidebarMenu::SETTING_KEY, json_encode([
            ['t' => 'label', 'text' => '관리 전용'],
            ['t' => 'menu', 'k' => 'deposits'],
            ['t' => 'menu', 'k' => 'marketing-report'],
            ['t' => 'gap'],
            ['t' => 'label', 'text' => '공통'],
            ['t' => 'menu', 'k' => 'calendar'],
            ['t' => 'menu', 'k' => 'notices'],
        ]));
        $guest = User::factory()->create(['role' => 'guest']);

        $rows = SidebarMenu::visibleRows($guest);

        $this->assertFalse(collect($rows)->contains(fn ($r) => ($r['text'] ?? '') === '관리 전용'), '빈 섹션 라벨 숨김');
        $this->assertSame('공통', collect($rows)->firstWhere('t', 'label')['text']);
        $this->assertSame('label', $rows[0]['t'], '선두 구분선 없이 시작');
    }
}
