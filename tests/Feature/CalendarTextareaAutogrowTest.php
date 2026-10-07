<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 일정 모달 textarea 자동 높이 — 요약 뷰로 열린 동안 숨겨진 폼의 textarea는 높이가
 * 0으로 측정되므로, 요약 해제·카테고리 전환으로 보이게 될 때 다시 측정해야
 * 처음부터 내용만큼 늘어나 있다 (김광래 피드백: 칸 고정 현상).
 */
class CalendarTextareaAutogrowTest extends TestCase
{
    use RefreshDatabase;

    public function test_autogrow_refreshes_on_unlock_and_category_switch(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($user)->get('/calendar')->assertOk();

        // 자동 높이 인프라
        $res->assertSee('function calAutoGrow', false)
            ->assertSee('function calRefreshAutoGrow', false);

        // 요약 → 폼 전환(applyLockUI)과 카테고리 전환(setColor) 양쪽에서 재측정
        $content = $res->getContent();
        $this->assertSame(
            4,
            substr_count($content, 'setTimeout(calRefreshAutoGrow'),
            '모달 열기(2곳)·요약 해제·카테고리 전환 네 지점에서 자동 높이를 재측정해야 한다'
        );
    }
}
