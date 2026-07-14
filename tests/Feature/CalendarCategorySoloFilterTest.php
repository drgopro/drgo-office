<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarCategorySoloFilterTest extends TestCase
{
    use RefreshDatabase;

    /** 사이드 필터 카테고리 행에 '만 보기' 버튼과 단독 보기 스크립트가 렌더링되는지 */
    public function test_calendar_page_renders_solo_filter_controls(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($user)->get('/calendar');

        $res->assertOk()
            ->assertSee('function csSoloCat(k)', false)
            ->assertSee('cs-solo', false)
            ->assertSee('만 보기')
            ->assertSee('이 카테고리만 보기');
    }
}
