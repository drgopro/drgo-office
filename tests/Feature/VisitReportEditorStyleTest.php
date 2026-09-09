<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 방문 보고서 에디터 — 줄 간격 축소 (line-height 1.85 → 1.6, 문단 간격 10px → 6px) */
class VisitReportEditorStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_uses_reduced_line_height(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '테스트', 'project_type' => 'visit']);

        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('line-height:1.6; color:var(--text);', false) // vrEditor 본문 줄 간격
            ->assertDontSee('line-height:1.85', false);               // 이전 값으로 회귀 방지
    }
}
