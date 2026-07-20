<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 데스크탑 위젯용 컴팩트 캘린더 뷰 — 로그인 필수 + 30초 폴링 */
class CalendarWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_requires_login(): void
    {
        $this->get('/calendar/widget')->assertRedirect('/login');
    }

    public function test_widget_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar/widget')
            ->assertOk()
            ->assertSee('캘린더 위젯', false)
            // 카테고리 색상 주입 + 30초 폴링 + 세로 컬러 바
            ->assertSee('const CATS =', false)
            ->assertSee('POLL_MS = 30000', false)
            ->assertSee('wg-bar', false)
            // 일렉트론 프레임리스 창 드래그 영역
            ->assertSee('-webkit-app-region:drag', false);
    }
}
