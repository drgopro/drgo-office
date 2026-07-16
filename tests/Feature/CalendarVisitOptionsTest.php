<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 미팅/내방 옵션 — 관리 설정 주입 + 요약 뷰 장소 표시 */
class CalendarVisitOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_injects_configured_visit_options(): void
    {
        Setting::set('calendar_visit_options', "사무실 방문\n쇼룸 방문");
        $user = User::factory()->create(['role' => 'admin']);

        // @json은 한글을 \uXXXX로 이스케이프하므로 JSON 인코딩된 형태로 검증
        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee(trim((string) json_encode('사무실 방문'), '"'), false)
            ->assertSee(trim((string) json_encode('쇼룸 방문'), '"'), false)
            ->assertSee('id="visitOptsSection"', false);
    }

    public function test_lock_summary_renders_visit_options_as_location(): void
    {
        // 내방 옵션으로 장소를 지정하면 location이 비므로, 요약 뷰가 체크된 옵션을 장소로 표시해야 함
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("visitOptsSel=(color==='purple')", false)
            ->assertSee('#visitOptsList input:checked', false);
    }
}
