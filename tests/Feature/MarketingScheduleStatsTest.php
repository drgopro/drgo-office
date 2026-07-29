<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 마케팅 통계 — 일정 지표(사양서 기준) 섹션 + 취소 사유 기간 코호트 일치 */
class MarketingScheduleStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_stats_section_renders_with_spec_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Schedule::create([
            'title' => '옴니버 방문세팅', 'start_date' => '2026-07-03', 'end_date' => '2026-07-03',
            'color' => 'gold', 'is_all_day' => true, 'client_name' => '옴니버',
            'address' => '서울특별시 강남구 역삼동', 'completed_at' => now(),
            'request_data' => ['nickname' => '옴니버', 'platform' => 'SOOP', 'estimate_amount' => '1,000,000'],
        ]);
        Schedule::create([
            'title' => '사내 회의', 'start_date' => '2026-07-05', 'end_date' => '2026-07-05',
            'color' => 'blue', 'is_all_day' => true,
        ]);

        $res = $this->actingAs($admin)->get('/marketing-report?from=2026-07-01&to=2026-07-31');

        $res->assertOk()
            ->assertSee('일정 지표')
            ->assertSee('총 의뢰 건수')
            ->assertSee('유형별 분포')
            ->assertSee('플랫폼 × 유형 교차')
            ->assertSee('권역별 수요')
            ->assertSee('방문세팅')
            ->assertSee('서울 강남구');
    }

    public function test_cancel_reasons_follow_period_cohort(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 기간 내 유입 + 취소 (사유 집계 대상)
        Project::create(['name' => '7월 취소건', 'project_type' => 'visit', 'stage' => 'cancelled', 'cancel_reason' => '의뢰자 연락 두절'])
            ->forceFill(['created_at' => '2026-07-10 10:00:00'])->saveQuietly();
        // 기간 밖 유입 + 취소 (집계 제외 — 전체값 노출 버그 방지)
        Project::create(['name' => '과거 취소건', 'project_type' => 'visit', 'stage' => 'cancelled', 'cancel_reason' => '일정이 맞지 않음'])
            ->forceFill(['created_at' => '2026-05-01 10:00:00'])->saveQuietly();

        $res = $this->actingAs($admin)->get('/marketing-report?from=2026-07-01&to=2026-07-31');

        $res->assertOk();
        // 기간 내 유입 코호트만 집계 — 취소 카드(cancelled)와 합계 일치
        $this->assertSame(['의뢰자 연락 두절' => 1], $res->viewData('cancelReasons')->toArray());
        $this->assertSame(1, $res->viewData('cancelled'));
    }
}
