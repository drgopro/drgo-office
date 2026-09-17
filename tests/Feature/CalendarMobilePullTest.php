<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 모바일 월간 뷰 — 끝지점에서 당기면 이전/다음 달 이동 */
class CalendarMobilePullTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_renders_mobile_month_pull_handler(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')->assertOk()
            ->assertSee('setupMobileMonthPull', false)
            ->assertSee('calPullHint', false)
            ->assertSee('놓으면 다음 달', false)
            ->assertSee('놓으면 이전 달', false);
    }
}
