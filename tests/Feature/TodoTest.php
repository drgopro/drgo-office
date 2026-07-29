<?php

namespace Tests\Feature;

use App\Models\Schedule;
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
        $admin = User::factory()->create(['role' => 'admin']);
        Todo::factory()->create(['title' => '서비스 의뢰 페이지 개편', 'assignee_id' => $admin->id]);

        $this->actingAs($admin)->get('/todos')
            ->assertOk()
            ->assertSee('할일 추가')
            ->assertSee('내 것만 보기')
            ->assertSee('직원 필터')
            ->assertSee('완료 보기')
            ->assertSee('리스트')
            ->assertSee('서비스 의뢰 페이지 개편');
    }

    public function test_member_page_hides_admin_filters_and_sees_only_own(): void
    {
        Todo::factory()->create(['title' => '내 할 일', 'assignee_id' => $this->user->id]);
        Todo::factory()->create(['title' => '다른 사람 할 일']);

        $this->actingAs($this->user)->get('/todos')
            ->assertOk()
            ->assertSee('내 할 일')
            ->assertDontSee('다른 사람 할 일')
            ->assertDontSee('내 것만 보기')
            ->assertDontSee('id="mfilterPanel"', false); // 직원 필터 패널은 관리자 전용
    }

    public function test_store_creates_todo_with_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignee = User::factory()->create();

        $res = $this->actingAs($admin)->postJson('/api/todos', [
            'title' => 'SSL 인증서 갱신',
            'content' => '시그저장소 인증서 만료 전 갱신',
            'priority' => 'high',
            'due_date' => '2026-07-20',
            'assignee_id' => $assignee->id,
        ]);

        $res->assertCreated()->assertJsonPath('todo.title', 'SSL 인증서 갱신');
        $todo = Todo::first();
        $this->assertSame($assignee->id, $todo->assignee_id);
        $this->assertSame($admin->id, $todo->created_by);
        $this->assertSame('high', $todo->priority);
        $this->assertSame('2026-07-20', $todo->due_date->format('Y-m-d'));
    }

    public function test_store_validates_priority_and_assignee(): void
    {
        $this->actingAs($this->user)->postJson('/api/todos', [
            'title' => 'x', 'priority' => 'urgent', 'assignee_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['priority', 'assignee_id']);
    }

    public function test_admin_updates_others_todo_but_member_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $todo = Todo::factory()->create(['title' => '원래 제목', 'priority' => 'low']);

        // 멤버가 타인 할 일을 타인 담당으로 수정 → 차단
        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}", [
            'title' => '탈취', 'priority' => 'medium', 'assignee_id' => $todo->assignee_id,
        ])->assertForbidden();

        $this->actingAs($admin)->patchJson("/api/todos/{$todo->id}", [
            'title' => '수정된 제목', 'priority' => 'medium', 'assignee_id' => $todo->assignee_id,
        ])->assertOk();

        $this->assertSame('수정된 제목', $todo->fresh()->title);
    }

    public function test_member_cannot_create_todo_for_others(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->postJson('/api/todos', [
            'title' => '떠넘기기', 'priority' => 'high', 'assignee_id' => $other->id,
        ])->assertForbidden();
        $this->assertSame(0, Todo::count());

        // 본인 앞으로는 가능
        $this->actingAs($this->user)->postJson('/api/todos', [
            'title' => '내 할 일', 'priority' => 'high', 'assignee_id' => $this->user->id,
        ])->assertCreated();
    }

    public function test_assign_is_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $todo = Todo::factory()->create();
        $newAssignee = User::factory()->create();

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/assign", [
            'assignee_id' => $newAssignee->id,
        ])->assertForbidden();

        $this->actingAs($admin)->patchJson("/api/todos/{$todo->id}/assign", [
            'assignee_id' => $newAssignee->id,
        ])->assertOk();

        $this->assertSame($newAssignee->id, $todo->fresh()->assignee_id);
    }

    public function test_reorder_sets_sort_order_by_given_ids(): void
    {
        $a = Todo::factory()->create(['assignee_id' => $this->user->id]);
        $b = Todo::factory()->create(['assignee_id' => $this->user->id]);
        $c = Todo::factory()->create(['assignee_id' => $this->user->id]);

        $this->actingAs($this->user)->patchJson('/api/todos/reorder', [
            'ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);

        // 보드 응답도 새 순서를 따름
        $ids = collect($this->actingAs($this->user)->getJson('/api/todos')->json('todos'))->pluck('id')->all();
        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_member_cannot_reorder_others_todos(): void
    {
        $mine = Todo::factory()->create(['assignee_id' => $this->user->id]);
        $others = Todo::factory()->create();

        $this->actingAs($this->user)->patchJson('/api/todos/reorder', [
            'ids' => [$others->id, $mine->id],
        ])->assertForbidden();

        // 관리자는 가능
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson('/api/todos/reorder', [
            'ids' => [$others->id, $mine->id],
        ])->assertOk();
    }

    public function test_complete_toggles(): void
    {
        // 멤버는 본인 할 일만 조작 가능 (IDOR 방지)
        $todo = Todo::factory()->create(['assignee_id' => $this->user->id]);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertNotNull($todo->fresh()->completed_at);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertNull($todo->fresh()->completed_at);
    }

    public function test_hold_due_toggles(): void
    {
        $todo = Todo::factory()->create(['assignee_id' => $this->user->id, 'due_date' => now()->addDays(2)]);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/hold-due")
            ->assertOk()->assertJsonPath('due_held', true);
        $this->assertNotNull($todo->fresh()->due_held_at);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/hold-due")
            ->assertOk()->assertJsonPath('due_held', false);
        $this->assertNull($todo->fresh()->due_held_at);
    }

    public function test_hold_due_requires_due_date(): void
    {
        $todo = Todo::factory()->create(['assignee_id' => $this->user->id, 'due_date' => null]);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/hold-due")
            ->assertUnprocessable();
        $this->assertNull($todo->fresh()->due_held_at);
    }

    public function test_member_cannot_hold_others_todo(): void
    {
        $todo = Todo::factory()->create(['due_date' => now()->addDays(2)]);

        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}/hold-due")
            ->assertForbidden();
    }

    public function test_due_date_change_clears_hold(): void
    {
        $todo = Todo::factory()->dueHeld()->create(['assignee_id' => $this->user->id]);

        // 기한 유지 → 보류 유지
        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}", [
            'title' => '제목만 수정', 'priority' => 'medium',
            'due_date' => $todo->due_date->format('Y-m-d'), 'assignee_id' => $this->user->id,
        ])->assertOk();
        $this->assertNotNull($todo->fresh()->due_held_at);

        // 기한 변경 → 보류 해제
        $this->actingAs($this->user)->patchJson("/api/todos/{$todo->id}", [
            'title' => '기한 변경', 'priority' => 'medium',
            'due_date' => now()->addDays(7)->format('Y-m-d'), 'assignee_id' => $this->user->id,
        ])->assertOk();
        $this->assertNull($todo->fresh()->due_held_at);
    }

    public function test_board_json_includes_due_held(): void
    {
        Todo::factory()->dueHeld()->create(['assignee_id' => $this->user->id]);

        $res = $this->actingAs($this->user)->getJson('/api/todos');
        $res->assertOk()->assertJsonPath('todos.0.due_held', true);
    }

    public function test_destroy_removes_todo_and_attachment_files(): void
    {
        Storage::fake();
        $todo = Todo::factory()->create(['assignee_id' => $this->user->id]);
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
        $todo = Todo::factory()->create(['assignee_id' => $this->user->id]);

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
        $todo = Todo::factory()->create(['assignee_id' => $this->user->id]);
        $path = UploadedFile::fake()->create('문서.pdf', 10)->store("todos/{$todo->id}");
        $attachment = $todo->attachments()->create(['file_name' => '문서.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 100]);

        $this->actingAs($this->user)->deleteJson("/api/todo-attachments/{$attachment->id}")->assertOk();

        $this->assertSame(0, TodoAttachment::count());
        Storage::assertMissing($path);
    }

    public function test_todo_links_to_schedule_and_survives_schedule_delete(): void
    {
        $schedule = Schedule::create([
            'title' => '방문 세팅', 'start_date' => '2026-07-20', 'end_date' => '2026-07-20',
            'color' => 'gold', 'is_all_day' => true,
        ]);

        $res = $this->actingAs($this->user)->postJson('/api/todos', [
            'title' => '세팅 전 장비 점검', 'priority' => 'high',
            'assignee_id' => $this->user->id, 'schedule_id' => $schedule->id,
        ]);

        $res->assertCreated()->assertJsonPath('todo.schedule_id', $schedule->id);

        // 일정 소프트 삭제 시 연결 유지 (복구 가능), 완전 삭제 시 연결만 해제되고 할 일은 유지
        $schedule->delete();
        $this->assertSame($schedule->id, Todo::first()->schedule_id);

        $schedule->forceDelete();
        $todo = Todo::first();
        $this->assertNotNull($todo);
        $this->assertNull($todo->schedule_id);
    }

    public function test_board_json_scopes_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Todo::factory()->count(2)->create();
        Todo::factory()->completed()->create(['assignee_id' => $this->user->id]);

        // 관리자는 전체 조회
        $res = $this->actingAs($admin)->getJson('/api/todos');
        $res->assertOk();
        $this->assertCount(3, $res->json('todos'));
        $this->assertSame(1, collect($res->json('todos'))->where('completed', true)->count());

        // 일반 멤버는 본인 것만
        $mine = $this->actingAs($this->user)->getJson('/api/todos');
        $this->assertCount(1, $mine->json('todos'));
    }
}
