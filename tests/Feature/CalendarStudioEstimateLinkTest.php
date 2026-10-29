<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 스튜디오/촬영 카테고리 — 견적서 연동 블록 렌더 + request_data.estimate_ids 저장/해제 (의뢰자 연결 없이도) */
class CalendarStudioEstimateLinkTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    public function test_calendar_renders_studio_estimate_section_and_shared_link_containers(): void
    {
        $html = $this->actingAs($this->master())->get('/calendar')->assertOk()->getContent();

        $this->assertStringContainsString('id="studioEstimateSection"', $html);
        $this->assertStringContainsString('function isStudioCategory(', $html);
        // gold 첨부 영역과 스튜디오 섹션 양쪽에 공용 컨테이너 — renderLinkedEstimates가 둘 다 채움
        $this->assertSame(2, substr_count($html, 'data-linked-est-box style='));
        $this->assertSame(2, substr_count($html, 'data-linked-est-list style='));
    }

    public function test_green_schedule_saves_and_clears_estimate_ids_without_client_link(): void
    {
        $user = $this->master();
        $this->actingAs($user)->postJson('/api/events', [
            'title' => '스튜디오 촬영', 'start_date' => '2026-08-01', 'end_date' => '2026-08-01',
            'is_all_day' => true, 'color' => 'green',
            'request_data' => ['estimate_id' => 7, 'estimate_ids' => [7, 9]],
        ])->assertCreated();
        $schedule = Schedule::first();
        $this->assertSame([7, 9], $schedule->request_data['estimate_ids']);

        // estimate_ids 키 없이 재저장(구버전 탭) — 서버 보존 가드로 유지
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'request_data' => ['client_id' => null],
        ])->assertOk();
        $this->assertSame([7, 9], $schedule->fresh()->request_data['estimate_ids']);

        // 모두 해제 — 키를 null로 명시하면 비워짐
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'request_data' => ['estimate_id' => null, 'estimate_ids' => null],
        ])->assertOk();
        $this->assertNull($schedule->fresh()->request_data['estimate_ids'] ?? null);
    }
}
