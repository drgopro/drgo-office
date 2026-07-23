<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 모바일 일별 리스트 제목 — 긴 제목이 2줄에서 잘리던 문제 (폰트 축소 + 3줄 허용) */
class CalendarMobileTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_day_list_title_uses_smaller_font_and_three_lines(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('.mobile-day-events .mde-title { font-size:12px;', false)
            ->assertSee('-webkit-line-clamp:3', false);
    }
}
