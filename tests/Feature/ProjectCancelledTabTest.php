<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 목록 진행/취소 탭 — 취소된 프로젝트(stage=cancelled)만 모아 보기 */
class ProjectCancelledTabTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '진행중세팅건', 'stage' => 'consulting', 'status' => 'active']);
        Project::create([
            'client_id' => $client->id, 'name' => '취소 프로젝트', 'stage' => 'cancelled', 'status' => 'active',
            'cancel_reason' => '가격 부담', 'cancel_detail' => '타사 견적 선택', 'cancelled_at' => now()->subDay(),
            'cancelled_from_stage' => 'estimate',
        ]);
        Project::create([
            'client_id' => $client->id, 'name' => '완료세팅건', 'stage' => 'done', 'status' => 'active',
            'completed_at' => now()->subDays(2),
        ]);
    }

    public function test_default_list_excludes_cancelled_and_done_with_tab_counts(): void
    {
        $this->actingAs($this->admin)->get('/projects')
            ->assertOk()
            ->assertSee('진행중세팅건')
            ->assertDontSee('취소 프로젝트')
            ->assertDontSee('완료세팅건')
            // 탭 3종 + 건수 배지 (진행 1 · 완료 1 · 취소 1)
            ->assertSee('pst-count pst-count-active', false)
            ->assertSee('pst-count pst-count-done', false)
            ->assertSee('status=done', false)
            ->assertSee('status=cancelled', false)
            // 필터 칩 열 분리 — 줄바꿈된 칩이 라벨 오른쪽 열 안에서 정렬 (단계·보고·유형 3곳)
            ->assertSee('filter-chips', false);
    }

    public function test_all_tab_shows_every_project_including_cancelled(): void
    {
        $this->actingAs($this->admin)->get('/projects?status=all')
            ->assertOk()
            ->assertSee('진행중세팅건')
            ->assertSee('완료세팅건')
            ->assertSee('취소 프로젝트')
            ->assertSee('status=all', false) // 전체 탭 링크
            // 전체 탭에도 단계 필터 노출 — 완료·취소 칩 포함
            ->assertSee('data-stage="done"', false)
            ->assertSee('data-stage="cancelled"', false);
    }

    public function test_all_tab_stage_filter_narrows_to_selected_stage(): void
    {
        // 전체 탭 + 단계 필터 '완료' → 완료 건만
        $this->actingAs($this->admin)->get('/projects?status=all&stage[]=done')
            ->assertOk()
            ->assertSee('완료세팅건')
            ->assertDontSee('진행중세팅건')
            ->assertDontSee('취소 프로젝트');

        // 전체 탭 + 단계 필터 '취소'
        $this->actingAs($this->admin)->get('/projects?status=all&stage[]=cancelled')
            ->assertOk()
            ->assertSee('취소 프로젝트')
            ->assertDontSee('완료세팅건');
    }

    public function test_done_tab_shows_only_completed_with_completed_date(): void
    {
        $this->actingAs($this->admin)->get('/projects?status=done')
            ->assertOk()
            ->assertSee('완료세팅건')
            ->assertDontSee('진행중세팅건')
            ->assertDontSee('취소 프로젝트')
            ->assertSee('완료일')
            ->assertSee(now()->subDays(2)->format('Y.m.d'));
    }

    public function test_has_report_view_spans_all_tabs_except_cancelled(): void
    {
        // 대시보드 '최근 방문보고 > 전체' 진입 — 방문보고는 완료 건이 많아 탭 분리 없이 노출
        Project::where('name', '완료세팅건')->update(['visit_report_updated_at' => now()]);

        $this->actingAs($this->admin)->get('/projects?has_report=1')
            ->assertOk()
            ->assertSee('완료세팅건');
    }

    public function test_cancelled_tab_shows_only_cancelled_with_reason_and_date(): void
    {
        $this->actingAs($this->admin)->get('/projects?status=cancelled')
            ->assertOk()
            ->assertSee('취소 프로젝트')
            ->assertDontSee('진행중세팅건')
            ->assertSee('가격 부담')
            ->assertSee('타사 견적 선택')
            ->assertSee('취소 사유')
            ->assertSee('취소일')
            ->assertSee('견적/계약 중'); // 취소 직전 단계
    }

    public function test_cancelled_tab_search_keeps_tab(): void
    {
        $this->actingAs($this->admin)->get('/projects?status=cancelled&search=취소')
            ->assertOk()
            ->assertSee('취소 프로젝트');

        // 검색어가 진행 건만 매칭하면 취소 탭에서는 빈 결과
        $this->actingAs($this->admin)->get('/projects?status=cancelled&search=진행')
            ->assertOk()
            ->assertSee('취소된 프로젝트가 없습니다.');
    }

    public function test_restore_clears_cancel_records_and_returns_to_previous_stage(): void
    {
        $cancelled = Project::where('name', '취소 프로젝트')->firstOrFail();

        // 취소 탭에 복구 버튼 렌더
        $this->actingAs($this->admin)->get('/projects?status=cancelled')
            ->assertOk()->assertSee('restoreProject', false)->assertSee('↩ 복구', false);

        // 복구 — 취소 직전 단계로 + 취소 기록 초기화
        $this->actingAs($this->admin)->patchJson("/projects/{$cancelled->id}/stage", ['stage' => 'estimate'])
            ->assertOk();
        $fresh = $cancelled->fresh();
        $this->assertSame('estimate', $fresh->stage);
        $this->assertNull($fresh->cancel_reason);
        $this->assertNull($fresh->cancel_detail);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNull($fresh->cancelled_from_stage);
    }

    public function test_stage_filter_is_ignored_on_cancelled_tab(): void
    {
        // 남아 있는 stage 파라미터가 취소 조건과 AND로 충돌해 빈 결과가 되지 않아야 한다
        $this->actingAs($this->admin)->get('/projects?status=cancelled&stage[]=consulting')
            ->assertOk()
            ->assertSee('취소 프로젝트');
    }
}
