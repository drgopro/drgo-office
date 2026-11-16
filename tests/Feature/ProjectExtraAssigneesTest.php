<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 복수 담당자(대표 + 추가) 지정, 담당자별 보기 집계, 완료 처리자 기록 */
class ProjectExtraAssigneesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kim;

    private User $lee;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'display_name' => '관리자']);
        $this->kim = User::factory()->create(['role' => 'member', 'display_name' => '김태한']);
        $this->lee = User::factory()->create(['role' => 'admin', 'display_name' => '이수호']); // 프로젝트 단계 변경 권한 필요
        $this->client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
    }

    private function makeProject(array $over = []): Project
    {
        return Project::create(array_merge(['client_id' => $this->client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'consulting'], $over));
    }

    public function test_project_can_be_created_with_extra_assignees(): void
    {
        $id = $this->actingAs($this->admin)->postJson("/clients/{$this->client->id}/projects", [
            'name' => '복수 담당 프로젝트', 'project_type' => 'visit',
            'assigned_user_id' => $this->kim->id,
            // 대표 담당자와 겹치는 값은 추가 담당자에서 빠진다
            'extra_assignee_ids' => [$this->lee->id, $this->kim->id],
        ])->assertCreated()->json('id');

        $project = Project::findOrFail($id);
        $this->assertSame($this->kim->id, $project->assigned_user_id);
        $this->assertSame([$this->lee->id], $project->extraAssignees->pluck('id')->all());
        $this->assertSame(['김태한', '이수호'], $project->assigneeNames());
    }

    public function test_extra_assignees_can_be_replaced_and_cleared_on_update(): void
    {
        $project = $this->makeProject(['assigned_user_id' => $this->kim->id]);

        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['extra_assignee_ids' => [$this->lee->id, $this->admin->id]])->assertOk();
        $this->assertEqualsCanonicalizing([$this->lee->id, $this->admin->id], $project->fresh()->extraAssignees->pluck('id')->all());

        // 추가 담당자였던 사람을 대표로 바꾸면 추가 목록에서 빠진다
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['assigned_user_id' => $this->lee->id])->assertOk();
        $this->assertSame([$this->admin->id], $project->fresh()->extraAssignees->pluck('id')->all());

        // 다른 값만 수정하면 추가 담당자는 그대로
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['name' => '이름만 수정'])->assertOk();
        $this->assertSame([$this->admin->id], $project->fresh()->extraAssignees->pluck('id')->all());

        // 빈 배열을 보내면 모두 해제
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['extra_assignee_ids' => []])->assertOk();
        $this->assertSame([], $project->fresh()->extraAssignees->pluck('id')->all());

        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['extra_assignee_ids' => [999999]])->assertUnprocessable();
    }

    public function test_list_shows_all_assignees_and_filter_matches_extra_assignees(): void
    {
        $shared = $this->makeProject(['name' => '함께 맡는 프로젝트', 'assigned_user_id' => $this->kim->id]);
        $shared->syncExtraAssignees([$this->lee->id]);
        $this->makeProject(['name' => '김 단독', 'assigned_user_id' => $this->kim->id]);
        $this->makeProject(['name' => '미지정']);

        $res = $this->actingAs($this->admin)->get('/projects')->assertOk()->assertSee('김태한, 이수호');
        // 담당자별 건수 — 함께 맡은 프로젝트는 두 사람 모두에게 집계, 추가 담당자만 있어도 미지정이 아님
        $counts = collect($res->viewData('assigneeOptions'))->pluck('count', 'name')->all();
        $this->assertSame(['김태한' => 2, '이수호' => 1, '미지정' => 1], $counts);

        $names = fn (string $q) => collect($this->actingAs($this->admin)->get('/projects?'.$q)->viewData('projects')->items())->pluck('name')->sort()->values()->all();
        $this->assertSame(['함께 맡는 프로젝트'], $names('assignee[]='.$this->lee->id));
        $this->assertSame(['김 단독', '함께 맡는 프로젝트'], $names('assignee[]='.$this->kim->id));
        $this->assertSame(['미지정'], $names('assignee[]=none'));
    }

    public function test_completing_records_who_completed_and_reopening_clears_it(): void
    {
        $project = $this->makeProject(['assigned_user_id' => $this->kim->id]);

        $this->actingAs($this->lee)->patchJson("/projects/{$project->id}/stage", ['stage' => 'done'])->assertSuccessful();
        $project->refresh();
        $this->assertSame($this->lee->id, $project->completed_by);
        $this->assertNotNull($project->completed_at);
        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()->assertSee('완료 처리: 이수호');

        // 완료 취소 — 완료자 기록도 지운다
        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'consulting'])->assertSuccessful();
        $project->refresh();
        $this->assertNull($project->completed_by);
        $this->assertNull($project->completed_at);
        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()->assertDontSee('완료 처리:');
    }

    public function test_project_forms_render_extra_assignee_pickers(): void
    {
        $project = $this->makeProject(['assigned_user_id' => $this->kim->id]);
        $project->syncExtraAssignees([$this->lee->id]);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('담당: 김태한, 이수호')
            ->assertSee("assigneeExtraHtml('peExtraAssignees', [{$this->lee->id}])", false)
            ->assertSee("extra_assignee_ids: assigneeExtraValues('peExtraAssignees')", false);
        $this->actingAs($this->admin)->get('/projects')->assertOk()->assertSee("assigneeExtraValues('npExtraAssignees')", false);
        $this->actingAs($this->admin)->get('/clients')->assertOk()->assertSee("assigneeExtraValues('pf-extra-assignees-' + clientId)", false);
    }
}
