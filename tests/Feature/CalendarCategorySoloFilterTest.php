<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarCategorySoloFilterTest extends TestCase
{
    use RefreshDatabase;

    /** 사이드 필터 — 체크박스(토글)와 라벨 클릭(만 보기) 컨트롤이 렌더링되는지 */
    public function test_calendar_page_renders_solo_filter_controls(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($user)->get('/calendar');

        $res->assertOk()
            ->assertSee('function csSoloCat(k)', false)
            ->assertSee('cs-check', false)      // 카테고리 체크박스
            ->assertSee("csSoloCat('", false)   // 라벨 클릭 = 만 보기
            ->assertSee('이 카테고리만 보기');
    }
}
