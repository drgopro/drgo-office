<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 휴지통 — 더보기(⋯) 메뉴에서 꺼내 헤더 ⋯ 버튼 좌측 버튼으로 노출 */
class CalendarTrashButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_trash_button_renders_in_header_left_of_more_menu(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($user)->get('/calendar')->assertOk()->getContent();

        $this->assertStringContainsString('id="calTrashBtn"', $html);
        $this->assertStringContainsString('#calTrashBtn + #calMoreWrap { margin-left:0; }', $html);

        $trashPos = strpos($html, 'id="calTrashBtn"');
        $moreWrapPos = strpos($html, 'id="calMoreWrap"');
        $this->assertLessThan($moreWrapPos, $trashPos, '휴지통 버튼은 ⋯(calMoreWrap)보다 앞에 있어야 한다');

        $menuStart = strpos($html, 'id="calMenu"');
        $menuEnd = strpos($html, 'jsonImportInput');
        $menuHtml = substr($html, $menuStart, $menuEnd - $menuStart);
        $this->assertStringNotContainsString('openTrashModal', $menuHtml, '⋯ 메뉴 안에는 휴지통 항목이 없어야 한다');
    }

    public function test_trash_button_hidden_without_calendar_edit_permission(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($user)->get('/calendar');

        if ($response->status() === 200) {
            $this->assertStringNotContainsString('id="calTrashBtn"', $response->getContent());
        } else {
            $response->assertRedirect();
        }
    }
}
