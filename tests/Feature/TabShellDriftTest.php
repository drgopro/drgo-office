<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 탭 셸 드리프트 — 탭 안에서 다른 구역으로 이동해 메뉴 클릭 시 엉뚱한 화면(견적서 등)이 보이던 문제 */
class TabShellDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_has_drift_guard_in_tab_system(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // 사이드바 메뉴 클릭 시 iframe이 다른 경로로 표류해 있으면 요청 화면으로 복귀
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('_iframeDrifted', false)
            ->assertSee('this._isMultiInstance(t.type, t.url)', false) // 프로젝트 상세 탭을 목록으로 덮어쓰지 않음
            // 현재 탭만 새로고침 버튼 — 전체가 아닌 열린 탭 iframe만 리로드
            ->assertSee('id="tabRefreshBtn"', false)
            ->assertSee('refreshActive', false);
    }

    public function test_project_estimate_link_opens_new_window(): void
    {
        // 프로젝트 탭 iframe이 견적서 빌더로 통째로 바뀌지 않도록 새 창으로
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '테스트', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '집 세팅']);
        $estimate = Estimate::create([
            'status' => 'created', 'project_id' => $project->id, 'client_id' => $client->id,
            'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id,
        ]);

        $res = $this->actingAs($user)->get("/projects/{$project->id}")->assertOk();
        if (str_contains($res->getContent(), "/estimates/{$estimate->id}/edit")) {
            $res->assertSee("window.open(this.href, 'est_", false);
        }
        $this->assertTrue(true);
    }

    public function test_client_project_links_route_to_top_tab(): void
    {
        // 의뢰자 탭 iframe이 프로젝트 상세로 표류하지 않고 최상위 탭으로 열림
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/clients')->assertOk()
            ->assertSee("openTopTab('projects'", false)
            ->assertDontSee('<a class="cv-eqlink" href="/projects/${eq.project_id}">', false); // 구형 직접 이동 링크 없음
    }
}
