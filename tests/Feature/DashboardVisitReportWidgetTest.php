<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 대시보드 '최근 방문보고' 위젯 — 작성 시각 기록 + 프로젝트/보고서 링크 */
class DashboardVisitReportWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeProject(string $name = '캠 세팅'): Project
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        return Project::create(['client_id' => $client->id, 'name' => $name, 'project_type' => 'visit', 'stage' => 'done']);
    }

    public function test_saving_report_stamps_timestamp_and_clearing_unsets_it(): void
    {
        $project = $this->makeProject();

        // 작성 → 시각 기록
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", [
            'visit_report' => '<p>세팅 완료, 조명 위치 조정</p>',
        ])->assertOk();
        $this->assertNotNull($project->fresh()->visit_report_updated_at);

        // 내용 변경 없이 다른 필드만 수정 → 시각 유지
        $stamp = $project->fresh()->visit_report_updated_at;
        $this->travel(5)->minutes();
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['overview' => '메모'])->assertOk();
        $this->assertSame($stamp->toDateTimeString(), $project->fresh()->visit_report_updated_at->toDateTimeString());

        // 비우면 최근 방문보고에서 제외
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", [
            'visit_report' => '<p></p>',
        ])->assertOk();
        $this->assertNull($project->fresh()->visit_report_updated_at);
    }

    public function test_dashboard_lists_recent_reports_with_link(): void
    {
        $project = $this->makeProject('스튜디오 구축');
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", [
            'visit_report' => '<p>방문하여 캠·마이크 세팅을 완료했습니다.</p>',
        ])->assertOk();
        // 보고서 없는 프로젝트 — 목록 미포함
        $this->makeProject('보고서 없는 건');

        // 의뢰자명 / 프로젝트명 / 작성 날짜만 표시 (본문 미리보기 없음)
        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('최근 방문보고')
            ->assertSee('고블린')
            ->assertSee('스튜디오 구축')
            ->assertSee(now()->format('Y.m.d'))
            ->assertDontSee('방문하여 캠·마이크 세팅을 완료했습니다.')
            ->assertSee("/projects/{$project->id}#visitReportCard", false) // 보고서 위치로 링크
            ->assertDontSee('보고서 없는 건');
    }

    public function test_project_list_filters_by_report(): void
    {
        // 대시보드 '전체 →' — 방문보고 작성 건만 필터된 프로젝트 목록
        $with = $this->makeProject('보고 있는 건');
        $with->update(['visit_report' => '<p>보고</p>', 'visit_report_updated_at' => now()]);
        $this->makeProject('보고 없는 건');

        $this->actingAs($this->admin)->get('/projects?has_report=1')->assertOk()
            ->assertSee('보고 있는 건')
            ->assertDontSee('보고 없는 건')
            ->assertSee('방문보고 작성됨'); // 필터 칩 활성 표시

        // 대시보드 전체 링크가 필터 URL로 연결
        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('/projects?has_report=1', false);
    }

    public function test_dashboard_shows_empty_state_without_reports(): void
    {
        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('작성된 방문보고가 없습니다');
    }

    public function test_project_page_expands_report_on_anchor(): void
    {
        // 앵커 진입 시 카드 표시·펼침 스크립트 포함
        $project = $this->makeProject();
        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee("location.hash === '#visitReportCard'", false);
    }
}
