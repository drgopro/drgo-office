<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStageLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_label_maps_known_codes_to_korean(): void
    {
        $project = new Project(['stage' => 'payment']);
        $this->assertSame('결제/예약', $project->stageLabel());

        $project->stage = 'proposal';
        $this->assertSame('일정제안', $project->stageLabel());
    }

    public function test_stage_label_falls_back_to_raw_code_for_unknown(): void
    {
        $project = new Project(['stage' => 'weird_new_stage']);
        $this->assertSame('weird_new_stage', $project->stageLabel());
    }

    public function test_client_detail_api_includes_korean_stage_label(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '라벨테스트', 'grade' => 'normal', 'status' => 'active']);
        Project::create([
            'name' => '방송룸 2호실 월대여', 'client_id' => $client->id,
            'project_type' => 'remote', 'stage' => 'payment', 'status' => 'active',
        ]);

        $res = $this->actingAs($user)->getJson("/api/clients/{$client->id}/detail");

        $res->assertOk();
        $project = $res->json('projects.0');
        $this->assertSame('payment', $project['stage']);
        $this->assertSame('결제/예약', $project['stage_label']);
    }
}
