<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\TodoChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 할 일 미니 프로젝트 — 체크리스트, 담당자별 완료(전원 완료 규칙), 미완료 리마인드 */
class TodoMiniProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'kim@drgo.pro', 'display_name' => '김담당']);
        $this->member = User::factory()->create(['role' => 'member', 'email' => 'park@drgo.pro', 'display_name' => '박담당']);
    }

    private function makeTodo(array $assignees, ?string $due = null): Todo
    {
        $todo = Todo::create([
            'title' => '직구 전파인증', 'priority' => 'medium',
            'assignee_id' => $assignees[0], 'created_by' => $this->admin->id,
            'due_date' => $due, 'sort_order' => 1,
        ]);
        $todo->syncAssigneesOrdered($assignees, notify: false);

        return $todo;
    }

    // === 담당자별 완료 (전원 완료 규칙) ===

    public function test_multi_assignee_todo_completes_only_when_everyone_checks(): void
    {
        $todo = $this->makeTodo([$this->admin->id, $this->member->id]);

        // 1명 체크 — 아직 미완료
        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/my-complete")
            ->assertOk()->assertJsonPath('my_completed', true)->assertJsonPath('completed', false);
        $this->assertNull($todo->fresh()->completed_at);

        // 전원 체크 — 자동 완료
        $this->actingAs($this->member)->patchJson("/api/todos/{$todo->id}/my-complete")
            ->assertOk()->assertJsonPath('completed', true);
        $this->assertNotNull($todo->fresh()->completed_at);

        // 한 명이 해제 — 전체 완료도 풀림
        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/my-complete")
            ->assertOk()->assertJsonPath('completed', false);
        $this->assertNull($todo->fresh()->completed_at);
    }

    public function test_my_complete_requires_being_assignee(): void
    {
        $todo = $this->makeTodo([$this->member->id, $this->admin->id]);
        $outsider = User::factory()->create(['role' => 'admin']);

        $this->actingAs($outsider)->patchJson("/api/todos/{$todo->id}/my-complete")
            ->assertStatus(403);
    }

    public function test_overall_complete_syncs_assignee_checks(): void
    {
        $todo = $this->makeTodo([$this->admin->id, $this->member->id]);

        // 관리자 강제 전체 완료 → 담당자별 체크도 완료 처리
        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertSame(2, $todo->assignees()->wherePivotNotNull('completed_at')->count());

        // 전체 완료 해제 → 담당자별 체크도 초기화
        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->assertSame(0, $todo->assignees()->wherePivotNotNull('completed_at')->count());
    }

    public function test_removing_pending_assignee_recomputes_completion(): void
    {
        $todo = $this->makeTodo([$this->admin->id, $this->member->id]);
        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/my-complete")->assertOk();

        // 미완료 담당자(박담당)가 빠지면... 남은 담당자가 1명이라 전원 규칙 대상 아님 — 미완료 유지
        $todo->syncAssigneesOrdered([$this->admin->id], notify: false);
        $this->assertNull($todo->fresh()->completed_at);
    }

    // === 체크리스트 ===

    public function test_checklist_crud_and_progress(): void
    {
        $todo = $this->makeTodo([$this->admin->id]);

        $this->actingAs($this->admin)->postJson("/api/todos/{$todo->id}/checklist", ['title' => '서류 준비'])->assertCreated();
        $this->actingAs($this->admin)->postJson("/api/todos/{$todo->id}/checklist", ['title' => '시험기관 접수'])->assertCreated();

        $first = TodoChecklistItem::where('todo_id', $todo->id)->orderBy('sort_order')->first();
        $this->actingAs($this->admin)->patchJson("/api/todo-checklist/{$first->id}", ['done' => true])
            ->assertOk()->assertJsonPath('done', true);
        $this->assertSame($this->admin->id, $first->fresh()->done_by);

        // 보드 payload에 진행 데이터 포함
        $board = $this->actingAs($this->admin)->getJson('/api/todos')->assertOk()->json('todos');
        $mine = collect($board)->firstWhere('id', $todo->id);
        $this->assertCount(2, $mine['checklist']);
        $this->assertTrue($mine['checklist'][0]['done']);
        $this->assertSame('김담당', $mine['checklist'][0]['done_by']);

        // 삭제
        $this->actingAs($this->admin)->deleteJson("/api/todo-checklist/{$first->id}")->assertOk();
        $this->assertSame(1, TodoChecklistItem::where('todo_id', $todo->id)->count());
    }

    public function test_checklist_can_be_reordered(): void
    {
        $todo = $this->makeTodo([$this->admin->id]);
        foreach (['서류 준비', '접수', '수령'] as $title) {
            $this->actingAs($this->admin)->postJson("/api/todos/{$todo->id}/checklist", ['title' => $title]);
        }
        $ids = TodoChecklistItem::where('todo_id', $todo->id)->orderBy('sort_order')->pluck('id')->all();

        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/checklist-reorder", [
            'ids' => [$ids[2], $ids[0], $ids[1]],
        ])->assertOk();

        $this->assertSame(
            ['수령', '서류 준비', '접수'],
            TodoChecklistItem::where('todo_id', $todo->id)->orderBy('sort_order')->pluck('title')->all()
        );
    }

    public function test_checklist_requires_todo_access(): void
    {
        $todo = $this->makeTodo([$this->admin->id]);
        $stranger = User::factory()->create(['role' => 'member']);

        $this->actingAs($stranger)->postJson("/api/todos/{$todo->id}/checklist", ['title' => 'x'])
            ->assertStatus(403);
    }

    // === 미완료 리마인드 ===

    private function fakeChannel(): void
    {
        config([
            'services.channeltalk.access_key' => 'k', 'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '오피스알림',
        ]);
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => [
                ['id' => 'mgr-1', 'name' => '김담당', 'email' => 'kim@drgo.pro'],
                ['id' => 'mgr-2', 'name' => '박담당', 'email' => 'park@drgo.pro'],
            ]]),
            'api.channel.io/open/v5/groups/*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_reminder_mentions_only_pending_assignees_with_remaining_steps(): void
    {
        $this->fakeChannel();
        $todo = $this->makeTodo([$this->admin->id, $this->member->id], now()->subDays(2)->format('Y-m-d'));
        $todo->checklistItems()->create(['title' => '서류 준비', 'done_at' => now(), 'sort_order' => 1]);
        $todo->checklistItems()->create(['title' => '시험기관 접수', 'sort_order' => 2]);
        // 김담당은 완료 체크함 — 박담당만 리마인드 대상
        $this->actingAs($this->admin)->patchJson("/api/todos/{$todo->id}/my-complete")->assertOk();

        $this->artisan('todos:remind')->expectsOutputToContain('1건')->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '직구 전파인증')
                && str_contains($text, '2일 지났습니다')
                && str_contains($text, '시험기관 접수')      // 남은 단계 안내
                && str_contains($text, 'mgr-2')             // 박담당 멘션
                && ! str_contains($text, 'mgr-1');          // 완료한 김담당은 멘션 제외
        });
    }

    public function test_reminder_skips_completed_held_and_far_future(): void
    {
        $this->fakeChannel();
        // 완료된 할 일 / 보류 중 / 마감 멀리 남음 — 모두 제외
        $done = $this->makeTodo([$this->admin->id], now()->subDay()->format('Y-m-d'));
        $done->update(['completed_at' => now()]);
        $held = $this->makeTodo([$this->admin->id], now()->subDay()->format('Y-m-d'));
        $held->update(['due_held_at' => now()]);
        $this->makeTodo([$this->admin->id], now()->addDays(5)->format('Y-m-d'));

        $this->artisan('todos:remind')->expectsOutputToContain('0건')->assertSuccessful();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/groups/'));
    }
}
