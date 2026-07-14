<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자명 확인 불가 — 의뢰자 미연동 프로젝트 생성 */
class ProjectStandaloneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_creates_project_without_client_using_manual_name(): void
    {
        $res = $this->actingAs($this->admin())->postJson('/api/projects', [
            'name' => '이름 미상 원격 문의',
            'project_type' => 'remote',
            'manual_client_name' => '카톡 문의 - 홍**',
        ]);

        $res->assertCreated()->assertJsonPath('success', true);
        $project = Project::first();
        $this->assertNull($project->client_id);
        $this->assertSame('카톡 문의 - 홍**', $project->manual_client_name);
        $this->assertSame('카톡 문의 - 홍**', $project->clientDisplayName());
        $this->assertSame('consulting', $project->stage);
    }

    public function test_manual_name_is_optional(): void
    {
        $res = $this->actingAs($this->admin())->postJson('/api/projects', [
            'name' => '완전 익명 문의',
            'project_type' => 'inquiry',
        ]);

        $res->assertCreated();
        $this->assertNull(Project::first()->manual_client_name);
        $this->assertNull(Project::first()->clientDisplayName());
    }

    public function test_show_page_renders_unlinked_project(): void
    {
        $project = Project::create([
            'name' => '미연동 프로젝트',
            'project_type' => 'remote',
            'stage' => 'consulting',
            'manual_client_name' => '별칭만 아는 의뢰자',
        ]);

        $res = $this->actingAs($this->admin())->get("/projects/{$project->id}");

        $res->assertOk()
            ->assertSee('별칭만 아는 의뢰자')
            ->assertSee('의뢰자명 확인 불가')
            ->assertSee('의뢰자와 연동되지 않은 프로젝트입니다');
    }

    public function test_index_search_matches_manual_client_name(): void
    {
        Project::create([
            'name' => '프로젝트A', 'project_type' => 'remote', 'stage' => 'consulting',
            'manual_client_name' => '유튜버 감자',
        ]);
        Project::create(['name' => '프로젝트B', 'project_type' => 'remote', 'stage' => 'consulting']);

        $res = $this->actingAs($this->admin())->get('/projects?search=감자');

        $res->assertOk()->assertSee('프로젝트A')->assertDontSee('프로젝트B');
    }
}
