<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 대시보드 상담 대기/진행중 목록 — 클릭 시 연결 프로젝트/의뢰자로 이동 */
class DashboardConsultLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_consult_item_links_to_project_or_client(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '연치민', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '세팅 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        $project2 = Project::create([
            'client_id' => $client->id, 'name' => '원격 프로젝트', 'project_type' => 'remote', 'stage' => 'consulting',
        ]);

        Consultation::create([
            'client_id' => $client->id, 'project_id' => $project->id,
            'consulted_at' => now(), 'result' => 'in_progress', 'content' => '입금 완료 확인 요청',
        ]);
        Consultation::create([
            'client_id' => $client->id, 'project_id' => $project2->id,
            'consulted_at' => now()->subHour(), 'result' => 'waiting', 'content' => '견적 문의',
        ]);

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()
            ->assertSee("'/projects/{$project->id}'", false)
            ->assertSee("'/projects/{$project2->id}'", false)
            ->assertSee('consult-item clickable', false);
    }
}
