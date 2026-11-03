<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 데스크탑 헤더 정렬 — 1행 연.월과 2행 뷰 버튼 묶음이 화면 정중앙 */
class CalendarHeaderAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_button_and_legend_are_taken_out_of_centered_groups(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $html = $this->actingAs($user)->get('/calendar')->assertOk()->getContent();

        // 1행: 오늘 버튼이 [오늘 ‹ 연.월 ›] 묶음 폭에서 빠져(절대배치) 연.월이 정중앙
        $this->assertStringContainsString('.cal-header .cal-center-nav .cal-today-btn { position:absolute; right:100%;', $html);
        $this->assertStringContainsString('.cal-header .cal-center-nav { position:absolute; left:50%;', $html);
        // 2행: 범례가 흐름에서 빠져 우측 끝 절대배치, 뷰 버튼+주 수는 auto 마진으로 정중앙
        $this->assertStringContainsString('.cal-header .cal-legend { position:absolute; right:24px;', $html);
        $this->assertStringContainsString('.view-toggle-group { order:10; margin-left:auto; }', $html);
        $this->assertStringContainsString('#monthWeeksCtl { order:11; margin-right:auto;', $html);
    }
}
