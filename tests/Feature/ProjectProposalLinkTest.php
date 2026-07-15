<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProposalLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(string $title): Schedule
    {
        return Schedule::create([
            'title' => $title,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-10',
            'color' => 'gold',
        ]);
    }

    public function test_proposal_links_and_unlinks_schedules_bidirectionally(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '연동테스트', 'grade' => 'normal', 'status' => 'active']);
        $project = Project::create([
            'name' => '연동 프로젝트', 'client_id' => $client->id,
            'project_type' => 'visit', 'stage' => 'consulting', 'status' => 'active',
        ]);
        $s1 = $this->makeSchedule('일정1');
        $s2 = $this->makeSchedule('일정2');

        // 1) s1 연결
        $this->actingAs($user)->postJson("/api/projects/{$project->id}/stage-data", [
            'key' => 'proposal',
            'data' => ['schedule_ids' => [$s1->id], 'note' => ''],
        ])->assertOk();

        $this->assertSame($project->id, (int) data_get($s1->fresh()->request_data, 'project_id'));
        $this->assertSame($client->id, (int) data_get($s1->fresh()->request_data, 'client_id'));

        // 2) s2로 교체 → s1 연결 해제, s2 연결
        $this->actingAs($user)->postJson("/api/projects/{$project->id}/stage-data", [
            'key' => 'proposal',
            'data' => ['schedule_ids' => [$s2->id], 'note' => ''],
        ])->assertOk();

        $this->assertNull(data_get($s1->fresh()->request_data, 'project_id'));
        $this->assertSame($project->id, (int) data_get($s2->fresh()->request_data, 'project_id'));
    }

    public function test_proposal_does_not_clobber_other_project_link(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '연동테스트2', 'grade' => 'normal', 'status' => 'active']);
        $projectA = Project::create(['name' => 'A', 'client_id' => $client->id, 'project_type' => 'visit', 'stage' => 'consulting', 'status' => 'active']);
        $projectB = Project::create(['name' => 'B', 'client_id' => $client->id, 'project_type' => 'visit', 'stage' => 'consulting', 'status' => 'active']);
        $s = $this->makeSchedule('공용 일정');

        // A에 연결했다가, A의 이전 목록에 있던 일정이 B로 연결된 상태라면 해제하지 않아야 함
        $this->actingAs($user)->postJson("/api/projects/{$projectA->id}/stage-data", [
            'key' => 'proposal', 'data' => ['schedule_ids' => [$s->id]],
        ])->assertOk();
        // B가 같은 일정을 가져감
        $this->actingAs($user)->postJson("/api/projects/{$projectB->id}/stage-data", [
            'key' => 'proposal', 'data' => ['schedule_ids' => [$s->id]],
        ])->assertOk();
        // A에서 선택 해제 저장 → B 연결은 유지되어야 함
        $this->actingAs($user)->postJson("/api/projects/{$projectA->id}/stage-data", [
            'key' => 'proposal', 'data' => ['schedule_ids' => []],
        ])->assertOk();

        $this->assertSame($projectB->id, (int) data_get($s->fresh()->request_data, 'project_id'));
    }
}
