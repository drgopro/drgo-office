<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 프로젝트 단계 변경(폼 제출) 리다이렉트 — back()이 알림 폴링 GET에 오염된
 * '이전 URL'(/api/notifications)로 이동해 JSON이 화면에 출력되던 버그 회귀 방지.
 */
class ProjectStageRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_form_submit_redirects_to_project_not_previous_url(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'consulting']);

        // 알림 폴링이 세션의 '이전 URL'을 /api/notifications 로 오염시킨 상황 재현
        // (AJAX 헤더 없는 GET — 수정 전 실제 폴링과 동일)
        $this->actingAs($admin)->get('/api/notifications')->assertOk();

        // 완료 처리 폼 제출 — 알림 JSON이 아니라 프로젝트 상세로 돌아와야 한다
        $res = $this->actingAs($admin)->patch("/projects/{$project->id}/stage", ['stage' => 'done']);
        $res->assertRedirect(route('projects.show', $project));

        $fresh = $project->fresh();
        $this->assertSame('done', $fresh->stage);
        $this->assertNotNull($fresh->completed_at);
    }
}
