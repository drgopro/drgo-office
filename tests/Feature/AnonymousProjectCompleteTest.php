<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 익명(의뢰자 미연동) 프로젝트 — 진행 단계 바 대신 헤더에서 완료 처리/완료 취소 */
class AnonymousProjectCompleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeAnonProject(array $attrs = []): Project
    {
        return Project::create([
            'client_id' => null, 'name' => '익명 문의 건', 'manual_client_name' => '미상 의뢰자',
            'project_type' => 'inquiry', 'stage' => 'consulting', ...$attrs,
        ]);
    }

    public function test_anon_project_shows_complete_button_and_done_state(): void
    {
        $project = $this->makeAnonProject();

        // 진행 중 — 완료 처리 버튼 노출
        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('anonCompleteProject', false)
            ->assertSee('완료 처리', false);

        // 완료 처리
        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'done'])->assertOk();
        $fresh = $project->fresh();
        $this->assertSame('done', $fresh->stage);
        $this->assertNotNull($fresh->completed_at);

        // 완료 상태 — 완료됨 배지 + 완료 취소 버튼
        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('완료됨', false)
            ->assertSee('anonReopenProject', false);
    }

    public function test_reopen_clears_completed_at(): void
    {
        $project = $this->makeAnonProject(['stage' => 'done', 'completed_at' => now()->subDay()]);

        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'consulting'])->assertOk();

        $fresh = $project->fresh();
        $this->assertSame('consulting', $fresh->stage);
        $this->assertNull($fresh->completed_at); // 되돌리면 완료 시각 제거 — 재완료 시 새 기록
    }

    public function test_linked_project_header_has_no_anon_complete_button(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '일반 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        // 일반 프로젝트는 진행 단계 바 사용 — 헤더 완료 버튼 없음 (JS 함수 정의는 공통 렌더)
        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()
            ->assertDontSee('onclick="anonCompleteProject()"', false);
    }
}
