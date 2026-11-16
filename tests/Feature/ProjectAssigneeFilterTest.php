<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 목록 담당자별 보기 — 담당자 필터 칩(담당자별 건수) + 미지정 */
class ProjectAssigneeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kim;

    private User $lee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'display_name' => '관리자']);
        $this->kim = User::factory()->create(['role' => 'member', 'display_name' => '김태한']);
        $this->lee = User::factory()->create(['role' => 'member', 'display_name' => '이수호']);

        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '김 진행 1', 'stage' => 'consulting', 'assigned_user_id' => $this->kim->id]);
        Project::create(['client_id' => $client->id, 'name' => '김 진행 2', 'stage' => 'consulting', 'assigned_user_id' => $this->kim->id]);
        Project::create(['client_id' => $client->id, 'name' => '김 완료', 'stage' => 'done', 'assigned_user_id' => $this->kim->id]);
        Project::create(['client_id' => $client->id, 'name' => '이 진행', 'stage' => 'consulting', 'assigned_user_id' => $this->lee->id]);
        Project::create(['client_id' => $client->id, 'name' => '미지정 진행', 'stage' => 'consulting']);
    }

    /** @return array<int, string> */
    private function names(string $query): array
    {
        $res = $this->actingAs($this->admin)->get('/projects?'.$query)->assertOk();

        return collect($res->viewData('projects')->items())->pluck('name')->sort()->values()->all();
    }

    public function test_assignee_filter_narrows_list_including_unassigned(): void
    {
        $this->assertSame(['김 진행 1', '김 진행 2'], $this->names('assignee[]='.$this->kim->id));
        $this->assertSame(['이 진행'], $this->names('assignee[]='.$this->lee->id));
        $this->assertSame(['미지정 진행'], $this->names('assignee[]=none'));
        $this->assertSame(['미지정 진행', '이 진행'], $this->names('assignee[]=none&assignee[]='.$this->lee->id));
        // 콤마 구분도 지원
        $this->assertSame(['김 진행 1', '김 진행 2', '이 진행'], $this->names('assignee='.$this->kim->id.','.$this->lee->id));
        // 완료 탭과 함께
        $this->assertSame(['김 완료'], $this->names('status=done&assignee[]='.$this->kim->id));
        // 잘못된 값은 무시 — 전체 표시
        $this->assertCount(4, $this->names('assignee[]=abc'));
    }

    public function test_assignee_chips_show_counts_for_current_tab(): void
    {
        $res = $this->actingAs($this->admin)->get('/projects')->assertOk();

        $options = collect($res->viewData('assigneeOptions'));
        // 진행 탭 기준 건수, 많은 순, 미지정은 맨 뒤
        $this->assertSame(['김태한', '이수호', '미지정'], $options->pluck('name')->all());
        $this->assertSame([2, 1, 1], $options->pluck('count')->all());
        $res->assertSee('name="assignee[]"', false)->assertSee('담당자');

        // 다른 필터를 걸어도 칩의 건수는 탭 기준 그대로 (담당자를 바꿔 가며 볼 수 있게)
        $filtered = $this->actingAs($this->admin)->get('/projects?assignee[]='.$this->lee->id)->assertOk();
        $this->assertSame([2, 1, 1], collect($filtered->viewData('assigneeOptions'))->pluck('count')->all());
        $filtered->assertSee('name="assignee[]" value="'.$this->lee->id.'" checked', false);

        // 완료 탭은 완료 건 기준
        $done = $this->actingAs($this->admin)->get('/projects?status=done')->assertOk();
        $this->assertSame(['김태한'], collect($done->viewData('assigneeOptions'))->pluck('name')->all());
    }
}
