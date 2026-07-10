<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarRepeatEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'title' => '반복 테스트',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'is_all_day' => true,
            'color' => 'blue',
        ];
    }

    private function createDailyGroup(): Schedule
    {
        $res = $this->actingAs($this->user)->postJson('/api/events', $this->basePayload() + [
            'repeat_freq' => 'daily',
            'repeat_until' => '2026-07-05',
        ]);
        $res->assertCreated();

        return Schedule::findOrFail($res->json('id'));
    }

    public function test_store_persists_repeat_settings_on_base_and_occurrences(): void
    {
        $base = $this->createDailyGroup();

        $this->assertSame('daily', $base->repeat_freq);
        $this->assertSame('2026-07-05', $base->repeat_until->format('Y-m-d'));

        $group = Schedule::where('repeat_group', $base->repeat_group)->orderBy('start_date')->get();
        $this->assertCount(5, $group); // 7/1 ~ 7/5
        $this->assertTrue($group->every(fn ($s) => $s->repeat_freq === 'daily'));
    }

    public function test_update_with_unchanged_repeat_settings_does_not_regenerate(): void
    {
        $base = $this->createDailyGroup();
        $idsBefore = Schedule::where('repeat_group', $base->repeat_group)->pluck('id')->sort()->values();

        $this->actingAs($this->user)->postJson("/api/events/{$base->id}", [
            'title' => '제목만 수정',
            'repeat_freq' => 'daily',
            'repeat_until' => '2026-07-05',
        ])->assertOk();

        $idsAfter = Schedule::where('repeat_group', $base->fresh()->repeat_group)->pluck('id')->sort()->values();
        $this->assertEquals($idsBefore, $idsAfter, '설정 미변경 시 반복 재생성 없음');
    }

    public function test_update_repeat_until_regenerates_future_occurrences(): void
    {
        $base = $this->createDailyGroup();
        $third = Schedule::where('repeat_group', $base->repeat_group)
            ->where('start_date', '2026-07-03')->firstOrFail();
        $oldFourth = Schedule::where('repeat_group', $base->repeat_group)
            ->where('start_date', '2026-07-04')->firstOrFail();

        // 3번째 회차에서 종료일을 7/7로 연장
        $this->actingAs($this->user)->postJson("/api/events/{$third->id}", [
            'repeat_freq' => 'daily',
            'repeat_until' => '2026-07-07',
        ])->assertOk();

        // 지난 회차(7/1, 7/2)는 기존 그룹에 그대로
        $this->assertNull(Schedule::where('start_date', '2026-07-01')->first()->deleted_at);
        $this->assertNull(Schedule::where('start_date', '2026-07-02')->first()->deleted_at);

        // 기존 이후 회차(7/4)는 소프트 삭제
        $this->assertSoftDeleted('schedules', ['id' => $oldFourth->id]);

        // 새 그룹: 7/3 기준 ~ 7/7 (7/3, 7/4, 7/5, 7/6, 7/7)
        $newGroup = Schedule::where('repeat_group', $third->fresh()->repeat_group)
            ->orderBy('start_date')->pluck('start_date')->map(fn ($d) => $d->format('Y-m-d'));
        $this->assertEquals(['2026-07-03', '2026-07-04', '2026-07-05', '2026-07-06', '2026-07-07'], $newGroup->all());
        $this->assertSame('2026-07-07', $third->fresh()->repeat_until->format('Y-m-d'));
    }

    public function test_update_repeat_freq_change_regenerates_with_new_frequency(): void
    {
        $base = $this->createDailyGroup();

        // 첫 회차에서 매일 → 매주, 종료일 7/20
        $this->actingAs($this->user)->postJson("/api/events/{$base->id}", [
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-07-20',
        ])->assertOk();

        $newGroup = Schedule::where('repeat_group', $base->fresh()->repeat_group)
            ->orderBy('start_date')->pluck('start_date')->map(fn ($d) => $d->format('Y-m-d'));
        $this->assertEquals(['2026-07-01', '2026-07-08', '2026-07-15'], $newGroup->all());
    }

    public function test_update_rejects_until_not_after_start(): void
    {
        $base = $this->createDailyGroup();

        $this->actingAs($this->user)->postJson("/api/events/{$base->id}", [
            'repeat_freq' => 'daily',
            'repeat_until' => '2026-07-01', // 시작일과 동일 → 거부
        ])->assertUnprocessable();
    }

    public function test_last_occurrence_saves_fine_with_unchanged_settings(): void
    {
        $base = $this->createDailyGroup();
        $last = Schedule::where('repeat_group', $base->repeat_group)
            ->where('start_date', '2026-07-05')->firstOrFail();

        // 마지막 회차(시작일 == 반복 종료일)를 설정 그대로 저장 — 재생성 없이 통과해야 함
        $this->actingAs($this->user)->postJson("/api/events/{$last->id}", [
            'title' => '마지막 회차 수정',
            'repeat_freq' => 'daily',
            'repeat_until' => '2026-07-05',
        ])->assertOk();

        $this->assertSame('마지막 회차 수정', $last->fresh()->title);
        $this->assertCount(5, Schedule::where('repeat_group', $base->repeat_group)->get());
    }
}
