<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 할 일 — 담당자별 칸반 보드 (CRUD·완료·담당자 변경·첨부) */
class TodoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'member']);
    }

    public function test_board_page_renders_with_todos(): void
    {
        Todo::factory()->create(['title' => '서비스 의뢰 페이지 개편', 'assignee_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/todos')
            ->assertOk()
            ->assertSee('할일 추가')
            ->assertSee('내 것만 보기')
            ->assertSee('완료 보기')
            ->assertSee('서비스 의뢰 페이지 개편');
    }

    public function test_store_creates_todo_with_required_fields(): void
    {
        $assignee = User::factory()->create();

        $res = $this->actingAs($this->user)->postJson('/api/todos', [
            'title' => 'SSL 인증서 갱신',
            'content' => '시그저장소 인증서 만료 전 갱신',
            'priority' => 'high',
            'due_date' => '2026-07-20',
            'assignee_id' => $assignee->id,
        ]);

        $res->assertCreated()->assertJsonPath('todo.title', 'SSL 인증서 갱신');
        $todo = Todo::first();
        $this->assertSame($assignee->id, $todo->assignee_id);
        $this->assertSame($this->user->id, $todo->created_by);
        $this->assertSame('high', $todo->priority);
        $this->assertSame('2026-07-20', $todo->due_date->format('Y-m-d'));
    }

    public function test_store_validates_priority_and_assignee(): void
    {
        $this->actingAs($this->user)->postJson('/api/todos', [
            'title' => 'x', 'priority' => 'urgent', 'assignee_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['priority', 'assignee_id']);
    }

    public function test_anyone_can_update_others_todo(): void
    {
        $todo = Todo::factory()->create(['title' => '원래 제목', 'priority' => 'low']);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}", [
            'title' => '수정된 제목',
            'priority' => 'medium',
            'assignee_id' => $todo->assignee_id,
        ])->assertOk();

        $this->assertSame('수정된 제목', $todo->fresh()->title);
    }

    public function test_assign_moves_todo_to_new_assignee(): void
    {
        $todo = Todo::factory()->create();
        $newAssignee = User::factory()->create();

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/assign", [
            'assignee_id' => $newAssignee->id,
        ])->assertOk();

        $this->assertSame($newAssignee->id, $todo->fresh()->assignee_id);
    }

    public function test_complete_toggles(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertNotNull($todo->fresh()->completed_at);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertNull($todo->fresh()->completed_at);
    }

    public function test_destroy_removes_todo_and_attachment_files(): void
    {
        Storage::fake();
        $todo = Todo::factory()->create();
        $path = UploadedFile::fake()->image('사진.jpg')->store("todos/{$todo->id}");
        $todo->attachments()->create(['file_name' => '사진.jpg', 'file_path' => $path, 'mime_type' => 'image/jpeg', 'file_size' => 100]);

        $this->actingAs($this->user)->deleteJson("/api/todos/{$todo->id}")->assertOk();

        $this->assertSame(0, Todo::count());
        $this->assertSame(0, TodoAttachment::count());
        Storage::assertMissing($path);
    }

    public function test_attachment_upload_and_serve(): void
    {
        Storage::fake();
        $todo = Todo::factory()->create();

        $this->actingAs($this->user)->post("/api/todos/{$todo->id}/attachments", [
            'files' => [UploadedFile::fake()->image('장비점검.png')],
        ])->assertCreated();

        $attachment = TodoAttachment::first();
        $this->assertSame('장비점검.png', $attachment->file_name);
        Storage::assertExists($attachment->file_path);

        $this->actingAs($this->user)->get("/todo-attachments/{$attachment->id}")->assertOk();
    }

    public function test_attachment_delete(): void
    {
        Storage::fake();
        $todo = Todo::factory()->create();
        $path = UploadedFile::fake()->create('문서.pdf', 10)->store("todos/{$todo->id}");
        $attachment = $todo->attachments()->create(['file_name' => '문서.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 100]);

        $this->actingAs($this->user)->deleteJson("/api/todo-attachments/{$attachment->id}")->assertOk();

        $this->assertSame(0, TodoAttachment::count());
        Storage::assertMissing($path);
    }

    public function test_board_json_returns_todos(): void
    {
        Todo::factory()->count(2)->create();
        Todo::factory()->completed()->create();

        $res = $this->actingAs($this->user)->getJson('/api/todos');

        $res->assertOk();
        $this->assertCount(3, $res->json('todos'));
        $this->assertSame(1, collect($res->json('todos'))->where('completed', true)->count());
    }
}
