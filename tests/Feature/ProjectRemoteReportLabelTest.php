<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 원격 프로젝트 — 보고서 라벨만 '원격 보고서'로 (로직은 방문 보고서와 동일) */
class ProjectRemoteReportLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_project_shows_remote_report_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '원격 건', 'project_type' => 'remote', 'stage' => 'done']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertStringContainsString('📋 원격 보고서', $html);
        $this->assertStringNotContainsString('📋 방문 보고서', $html);
    }

    public function test_visit_project_keeps_visit_report_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '방문 건', 'project_type' => 'visit', 'stage' => 'done']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertStringContainsString('📋 방문 보고서', $html);
        $this->assertStringNotContainsString('📋 원격 보고서', $html);
    }
}
