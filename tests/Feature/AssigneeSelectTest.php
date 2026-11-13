<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 담당자 지정 — 등록 시 고르지 않으면 작성자, 따로 고르면 그 사람. 수정에서도 바꿀 수 있다. */
class AssigneeSelectTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = User::factory()->create(['role' => 'master']);
        $this->other = User::factory()->create(['role' => 'staff']);
    }

    public function test_client_assignee_defaults_to_writer(): void
    {
        $id = $this->actingAs($this->writer)->postJson('/api/clients', ['nickname' => '기본담당', 'grade' => 'normal'])
            ->assertCreated()->json('id');

        $this->assertSame($this->writer->id, Client::find($id)->assigned_user_id);
    }

    public function test_client_assignee_can_be_chosen_on_create_and_changed_on_update(): void
    {
        $id = $this->actingAs($this->writer)->postJson('/api/clients', [
            'nickname' => '지정담당', 'grade' => 'normal', 'assigned_user_id' => $this->other->id,
        ])->assertCreated()->json('id');
        $this->assertSame($this->other->id, Client::find($id)->assigned_user_id);

        $this->actingAs($this->writer)->patchJson("/api/clients/{$id}", [
            'nickname' => '지정담당', 'grade' => 'normal', 'assigned_user_id' => $this->writer->id,
        ])->assertOk();
        $this->assertSame($this->writer->id, Client::find($id)->assigned_user_id);

        // 담당자 칸을 보내지 않은 수정은 담당자를 건드리지 않는다
        $this->actingAs($this->writer)->patchJson("/api/clients/{$id}", ['nickname' => '지정담당2', 'grade' => 'normal'])->assertOk();
        $this->assertSame($this->writer->id, Client::find($id)->assigned_user_id);
    }

    public function test_client_assignee_must_be_existing_user(): void
    {
        $this->actingAs($this->writer)->postJson('/api/clients', ['nickname' => '없는담당', 'grade' => 'normal', 'assigned_user_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_user_id');
    }

    public function test_project_assignee_default_choice_and_update(): void
    {
        $client = Client::create(['nickname' => '프로젝트담당', 'grade' => 'normal', 'status' => 'active']);

        $this->actingAs($this->writer)->postJson("/clients/{$client->id}/projects", ['name' => '기본', 'project_type' => 'visit'])->assertCreated();
        $this->assertSame($this->writer->id, Project::where('name', '기본')->first()->assigned_user_id);

        $this->actingAs($this->writer)->postJson("/clients/{$client->id}/projects", ['name' => '지정', 'project_type' => 'visit', 'assigned_user_id' => $this->other->id])->assertCreated();
        $project = Project::where('name', '지정')->first();
        $this->assertSame($this->other->id, $project->assigned_user_id);

        $this->actingAs($this->writer)->patchJson("/api/projects/{$project->id}", ['assigned_user_id' => $this->writer->id])->assertOk();
        $this->assertSame($this->writer->id, $project->fresh()->assigned_user_id);
    }
}
