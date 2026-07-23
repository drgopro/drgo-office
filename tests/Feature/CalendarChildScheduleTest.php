<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 장기 일정 하위 일정 — 일자별 시간·담당자 (2뎁스 계층) */
class CalendarChildScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    private function longSchedule(): Schedule
    {
        return Schedule::create([
            'title' => '스튜디오 세팅', 'color' => 'gold',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-15',
            'is_all_day' => true,
        ]);
    }

    public function test_dates_mode_creates_one_child_per_date(): void
    {
        $parent = $this->longSchedule();

        $this->actingAs($this->master())
            ->postJson("/api/events/{$parent->id}/children", [
                'dates' => ['2026-07-03', '2026-07-01', '2026-07-06'],
                'start_time' => '10:00', 'end_time' => '13:00',
            ])->assertCreated()
            ->assertJsonCount(3, 'children');

        $children = $parent->children()->get();
        $this->assertCount(3, $children);
        // 날짜순 정렬 + 제목/색상 상속
        $this->assertSame(['2026-07-01', '2026-07-03', '2026-07-06'], $children->pluck('start_date')->map->toDateString()->all());
        $this->assertSame('스튜디오 세팅', $children[0]->title);
        $this->assertSame('gold', $children[0]->color);
        $this->assertFalse($children[0]->is_all_day);
    }

    public function test_range_mode_creates_single_child(): void
    {
        $parent = $this->longSchedule();

        $this->actingAs($this->master())
            ->postJson("/api/events/{$parent->id}/children", [
                'start_date' => '2026-07-04', 'end_date' => '2026-07-06',
                'start_time' => '13:00', 'memo' => '오후 조',
            ])->assertCreated()
            ->assertJsonCount(1, 'children');

        $child = $parent->children()->first();
        $this->assertSame('2026-07-04', $child->start_date->toDateString());
        $this->assertSame('2026-07-06', $child->end_date->toDateString());
        $this->assertSame('오후 조', $child->description);
    }

    public function test_child_outside_parent_range_is_rejected(): void
    {
        $parent = $this->longSchedule();

        $this->actingAs($this->master())
            ->postJson("/api/events/{$parent->id}/children", [
                'dates' => ['2026-07-20'], 'start_time' => '10:00',
            ])->assertStatus(422);

        // 부모 기간 경계일은 허용
        $this->actingAs($this->master())
            ->postJson("/api/events/{$parent->id}/children", [
                'start_date' => '2026-07-01', 'end_date' => '2026-07-15',
                'start_time' => '10:00',
            ])->assertCreated();
    }

    public function test_child_of_child_is_rejected(): void
    {
        $parent = $this->longSchedule();
        $master = $this->master();

        $this->actingAs($master)->postJson("/api/events/{$parent->id}/children", [
            'dates' => ['2026-07-02'], 'start_time' => '09:00',
        ])->assertCreated();

        $child = $parent->children()->first();
        $this->actingAs($master)->postJson("/api/events/{$child->id}/children", [
            'dates' => ['2026-07-02'], 'start_time' => '10:00',
        ])->assertStatus(422);
    }

    public function test_child_assignees_merge_into_parent(): void
    {
        $parent = $this->longSchedule();
        $a = Assignee::create(['name' => '김웅', 'is_active' => true, 'display_order' => 0]);
        $b = Assignee::create(['name' => '박진혁', 'is_active' => true, 'display_order' => 1]);
        $parent->syncAssigneesOrdered([$a->id]);

        $this->actingAs($this->master())->postJson("/api/events/{$parent->id}/children", [
            'dates' => ['2026-07-02'], 'start_time' => '09:00',
            'assignees' => [$b->id],
        ])->assertCreated();

        // 기존 순서 유지 + 새 담당자는 뒤에 추가
        $this->assertSame(['김웅', '박진혁'], $parent->fresh()->assignees->pluck('name')->all());
    }

    public function test_children_list_and_update_and_delete(): void
    {
        $parent = $this->longSchedule();
        $master = $this->master();

        $this->actingAs($master)->postJson("/api/events/{$parent->id}/children", [
            'dates' => ['2026-07-02'], 'start_time' => '09:00',
        ])->assertCreated();
        $child = $parent->children()->first();

        $list = $this->actingAs($master)->getJson("/api/events/{$parent->id}/children")->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame('2026-07-02', $list[0]['start_date']);
        $this->assertSame('09:00', $list[0]['start_time']);

        $this->actingAs($master)->patchJson("/api/events/children/{$child->id}", [
            'start_date' => '2026-07-05', 'start_time' => '14:00', 'memo' => '변경',
        ])->assertOk();
        $child->refresh();
        $this->assertSame('2026-07-05', $child->start_date->toDateString());
        $this->assertSame('변경', $child->description);

        // 부모 범위 밖으로 수정 불가
        $this->actingAs($master)->patchJson("/api/events/children/{$child->id}", [
            'start_date' => '2026-07-20', 'start_time' => '14:00',
        ])->assertStatus(422);

        $this->actingAs($master)->deleteJson("/api/events/children/{$child->id}")->assertOk();
        $this->assertDatabaseMissing('schedules', ['id' => $child->id]);
    }

    public function test_parent_delete_cascades_children(): void
    {
        $parent = $this->longSchedule();
        $this->actingAs($this->master())->postJson("/api/events/{$parent->id}/children", [
            'dates' => ['2026-07-02'], 'start_time' => '09:00',
        ])->assertCreated();
        $childId = $parent->children()->first()->id;

        $parent->forceDelete();
        $this->assertDatabaseMissing('schedules', ['id' => $childId]);
    }

    public function test_children_are_excluded_from_dashboard_count(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $date = now()->startOfMonth()->addDays(3)->toDateString();
        $end = now()->startOfMonth()->addDays(8)->toDateString();

        $parent = Schedule::create(['title' => '장기 세팅', 'start_date' => $date, 'end_date' => $end, 'color' => 'gold']);
        Schedule::create(['parent_id' => $parent->id, 'title' => '장기 세팅', 'start_date' => $date, 'end_date' => $date, 'color' => 'gold', 'start_time' => '10:00']);

        // 부모 1건만 집계 (하위 제외)
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('scheduleThisMonth', 1);
    }

    public function test_events_api_includes_parent_id_for_client_filtering(): void
    {
        $parent = $this->longSchedule();
        Schedule::create(['parent_id' => $parent->id, 'title' => '스튜디오 세팅', 'start_date' => '2026-07-02', 'end_date' => '2026-07-02', 'color' => 'gold', 'start_time' => '10:00']);

        $res = $this->actingAs($this->master())->getJson('/api/events?start=2026-07-01&end=2026-07-31')->assertOk()->json();
        $child = collect($res)->firstWhere('parent_id', $parent->id);
        $this->assertNotNull($child);
    }
}
