<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 상세 — 의뢰자 닉네임 우선 표기, 탭 제목에 의뢰자명, 의뢰자 화면에서 열었을 때 돌아가기 버튼 */
class ProjectClientDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function show(array $client, string $query = ''): string
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $c = Client::create(['grade' => 'normal', ...$client]);
        $project = Project::create(['client_id' => $c->id, 'name' => '게임컴 연결 문제해결', 'project_type' => 'visit', 'stage' => 'consulting']);

        return $this->actingAs($admin)->get("/projects/{$project->id}{$query}")->assertOk()->getContent();
    }

    public function test_header_shows_nickname_first_and_info_card_has_separate_nickname_row(): void
    {
        $html = $this->show(['name' => '성유진', 'nickname' => '진유성']);

        $this->assertStringContainsString('👤 진유성 (성유진)</a>', $html);
        $this->assertMatchesRegularExpression('/<div class="info-label">닉네임<\/div>\s*<div>진유성<\/div>/', $html);
        $this->assertStringNotContainsString('(진유성)</span>', $html);
    }

    public function test_client_with_only_one_of_name_or_nickname(): void
    {
        $html = $this->show(['nickname' => '리솔짱']);
        $this->assertStringContainsString('👤 리솔짱</a>', $html);
        $this->assertMatchesRegularExpression('/<div class="info-label">닉네임<\/div>\s*<div>리솔짱<\/div>/', $html);

        $html = $this->show(['name' => '홍길동', 'nickname' => '']);
        $this->assertStringContainsString('👤 홍길동</a>', $html);
        $this->assertMatchesRegularExpression('/<div class="info-label">닉네임<\/div>\s*<div>-<\/div>/', $html);
    }

    public function test_tab_title_includes_client_display_name(): void
    {
        $html = $this->show(['name' => '성유진', 'nickname' => '진유성']);

        $this->assertStringContainsString("const title = '📁 ' + ".json_encode('진유성 · 게임컴 연결 문제해결'), $html);
    }

    public function test_anonymous_project_tab_title_falls_back_to_project_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::create(['name' => '익명 문의', 'project_type' => 'inquiry', 'stage' => 'consulting']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertStringContainsString("const title = '📁 ' + ".json_encode('익명 문의'), $html);
    }

    public function test_back_to_client_button_only_when_opened_from_client_page(): void
    {
        $this->assertStringContainsString('← 의뢰자로 돌아가기', $this->show(['nickname' => '유나'], '?from=client'));
        $this->assertStringNotContainsString('← 의뢰자로 돌아가기', $this->show(['nickname' => '유나']));
    }

    public function test_client_page_opens_projects_with_origin_marker_and_shell_can_close_caller_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get('/clients')->assertOk()->getContent();

        $this->assertStringContainsString("openTopTab('projects','/projects/\${p.id}?from=client')", $html);
        $this->assertStringContainsString('closeByWindow(sourceWin) {', $html);
    }
}
