<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_renders_for_member(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')->assertOk();
    }

    public function test_side_fab_has_state_icons_including_close(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk();
        // 플로팅 버튼: 깔때기(데스크탑) / 햄버거(모바일) / X(패널 열림) 3종 아이콘
        $response->assertSee('fab-ico-filter', false);
        $response->assertSee('fab-ico-menu', false);
        $response->assertSee('fab-ico-close', false);
        // 열림 상태에서 X 아이콘으로 전환하는 동기화 함수
        $response->assertSee('function csUpdateFab()', false);
        $response->assertSee('#calSideFab.fab-open .fab-ico-close { display:block; }', false);
    }

    public function test_mobile_side_panel_is_left_sliding_drawer(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk();
        // 모바일 리모컨 패널이 좌측 드로어로 슬라이드 (모달형 중앙 배치 아님)
        $response->assertSee('transform:translateX(-105%)', false);
        $response->assertSee('#calSide.mobile-open { transform:translateX(0)', false);
        $response->assertDontSee('#calSide.mobile-open { display:block; position:fixed; left:56px', false);
    }
}
