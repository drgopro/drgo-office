<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 일정 담당자 선택 순서 보존 — 먼저 선택한 담당자가 앞에 표시 */
class CalendarAssigneeOrderTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    public function test_assignee_order_follows_selection_order(): void
    {
        $a = Assignee::create(['name' => '김웅', 'is_active' => true, 'display_order' => 0]);
        $b = Assignee::create(['name' => '박진혁', 'is_active' => true, 'display_order' => 1]);
        $c = Assignee::create(['name' => '조유신', 'is_active' => true, 'display_order' => 2]);

        // 박진혁 → 김웅 → 조유신 순으로 선택 (id 순서와 다름)
        $this->actingAs($this->master())->postJson('/api/events', [
            'title' => '순서 테스트', 'start_date' => '2026-07-22', 'end_date' => '2026-07-22',
            'is_all_day' => true, 'color' => 'blue',
            'assignees' => [$b->id, $a->id, $c->id],
        ])->assertCreated();

        $names = Schedule::first()->assignees->pluck('name')->all();
        $this->assertSame(['박진혁', '김웅', '조유신'], $names);

        // 이벤트 조회 API에서도 동일 순서
        $res = $this->actingAs($this->master())->getJson('/api/events?start=2026-07-22&end=2026-07-22');
        $apiNames = collect($res->json()[0]['assignees'])->pluck('name')->all();
        $this->assertSame(['박진혁', '김웅', '조유신'], $apiNames);
    }

    public function test_update_reorders_assignees(): void
    {
        $a = Assignee::create(['name' => '김웅', 'is_active' => true, 'display_order' => 0]);
        $b = Assignee::create(['name' => '박진혁', 'is_active' => true, 'display_order' => 1]);
        $master = $this->master();

        $this->actingAs($master)->postJson('/api/events', [
            'title' => '재정렬', 'start_date' => '2026-07-23', 'end_date' => '2026-07-23',
            'is_all_day' => true, 'color' => 'blue',
            'assignees' => [$a->id, $b->id],
        ])->assertCreated();
        $schedule = Schedule::first();

        // 순서 변경: 박진혁을 먼저 선택
        $this->actingAs($master)->patchJson("/api/events/{$schedule->id}", [
            'title' => '재정렬', 'start_date' => '2026-07-23', 'end_date' => '2026-07-23',
            'is_all_day' => true, 'color' => 'blue',
            'assignees' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(['박진혁', '김웅'], $schedule->fresh()->assignees->pluck('name')->all());
    }
}
