<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 연동 — 프로젝트 의뢰 내용(custom_data.__req_items) 조회 API */
class ProjectRequestItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_request_items_written_on_project(): void
    {
        $project = Project::create([
            'name' => '의뢰 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
            'custom_data' => ['__req_items' => [
                ['t' => '신규·이사 세팅', 'c' => '오디오', 'd' => '마이크 추가 설치', 'qty' => 3],
                ['t' => '신규·이사 세팅', 'c' => '컴퓨터'], // 필수 키 누락 → 걸러짐
            ]],
        ]);
        $member = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($member)->getJson("/api/projects/{$project->id}/request-items");

        $res->assertOk();
        $this->assertCount(1, $res->json('req_items'));
        $this->assertSame('마이크 추가 설치', $res->json('req_items.0.d'));
        $this->assertSame(3, $res->json('req_items.0.qty'));
    }

    public function test_returns_empty_when_project_has_no_items(): void
    {
        $project = Project::create(['name' => '빈 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting']);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->getJson("/api/projects/{$project->id}/request-items")
            ->assertOk()->assertExactJson(['req_items' => []]);
    }
}
