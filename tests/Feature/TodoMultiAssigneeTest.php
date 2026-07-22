<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 할 일 복수 담당자 — 선택 순서 보존, 첫 번째가 대표(칸반 컬럼 기준) */
class TodoMultiAssigneeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_preserves_selection_order_and_sets_primary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create(['display_name' => '김웅']);
        $b = User::factory()->create(['display_name' => '박진혁']);

        // 박진혁 → 김웅 순으로 선택
        $res = $this->actingAs($admin)->postJson('/api/todos', [
            'title' => '공동 작업', 'priority' => 'medium',
            'assignee_ids' => [$b->id, $a->id],
        ]);
        $res->assertCreated();

        $todo = Todo::first();
        $this->assertSame($b->id, $todo->assignee_id, '첫 번째 선택이 대표 담당자');
        $this->assertSame(['박진혁', '김웅'], $todo->assignees->pluck('display_name')->all());
        $this->assertSame([$b->id, $a->id], $res->json('todo.assignee_ids'));
    }

    public function test_single_assignee_id_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create();

        $this->actingAs($admin)->postJson('/api/todos', [
            'title' => '단일 담당', 'priority' => 'low', 'assignee_id' => $a->id,
        ])->assertCreated();

        $todo = Todo::first();
        $this->assertSame($a->id, $todo->assignee_id);
        $this->assertSame([$a->id], $todo->assignees->pluck('id')->all());
    }

    public function test_member_cannot_include_others_in_assignees(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create();

        $this->actingAs($member)->postJson('/api/todos', [
            'title' => '몰래 지정', 'priority' => 'medium',
            'assignee_ids' => [$member->id, $other->id],
        ])->assertForbidden();
    }

    public function test_secondary_assignee_sees_and_completes_todo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $primary = User::factory()->create(['role' => 'member']);
        $secondary = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin)->postJson('/api/todos', [
            'title' => '함께 처리', 'priority' => 'high',
            'assignee_ids' => [$primary->id, $secondary->id],
        ])->assertCreated();
        $todo = Todo::first();

        // 두 번째 담당자도 보드에서 보이고 완료 처리 가능
        $board = collect($this->actingAs($secondary)->getJson('/api/todos')->json('todos'));
        $this->assertTrue($board->pluck('id')->contains($todo->id));
        $this->actingAs($secondary)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertNotNull($todo->fresh()->completed_at);
    }

    public function test_drag_assign_makes_target_primary_and_keeps_others(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        $this->actingAs($admin)->postJson('/api/todos', [
            'title' => '이관', 'priority' => 'medium', 'assignee_ids' => [$a->id, $b->id],
        ])->assertCreated();
        $todo = Todo::first();

        // c 컬럼으로 드래그 → c가 대표(첫 번째), 기존 담당자 유지
        $this->actingAs($admin)->patchJson("/api/todos/{$todo->id}/assign", ['assignee_id' => $c->id])->assertOk();

        $todo->refresh();
        $this->assertSame($c->id, $todo->assignee_id);
        $this->assertSame([$c->id, $a->id, $b->id], $todo->assignees->pluck('id')->all());
    }
}
