<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduleRepeatTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    private function basePayload(array $extra = []): array
    {
        return array_merge([
            'title' => '주간 회의',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'color' => 'blue',
        ], $extra);
    }

    public function test_매주_반복_생성(): void
    {
        $this->actingAs($this->master())->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-08-31',
        ]))->assertCreated();

        // 8/3, 8/10, 8/17, 8/24, 8/31 = 5건, 그룹 공유
        $this->assertSame(5, Schedule::count());
        $this->assertSame(1, Schedule::query()->distinct('repeat_group')->count('repeat_group'));
        $this->assertSame('2026-08-31', Schedule::orderByDesc('start_date')->first()->start_date->format('Y-m-d'));
    }

    public function test_매일_반복은_기간_유지(): void
    {
        $this->actingAs($this->master())->postJson('/api/events', $this->basePayload([
            'end_date' => '2026-08-04', // 2일짜리 일정
            'repeat_freq' => 'daily',
            'repeat_until' => '2026-08-05',
        ]))->assertCreated();

        $copies = Schedule::orderBy('start_date')->get();
        $this->assertSame(3, $copies->count());
        // 복제분도 2일 기간 유지 (8/5 시작 → 8/6 종료)
        $this->assertSame('2026-08-06', $copies[2]->end_date->format('Y-m-d'));
    }

    public function test_매월_반복은_말일_오버플로_방지(): void
    {
        $this->actingAs($this->master())->postJson('/api/events', $this->basePayload([
            'start_date' => '2026-08-31',
            'end_date' => '2026-08-31',
            'repeat_freq' => 'monthly',
            'repeat_until' => '2026-10-31',
        ]))->assertCreated();

        $dates = Schedule::orderBy('start_date')->pluck('start_date')->map(fn ($d) => $d->format('Y-m-d'))->all();
        // 9월은 31일이 없어 9/30으로 보정
        $this->assertSame(['2026-08-31', '2026-09-30', '2026-10-31'], $dates);
    }

    public function test_사용자지정_2주마다_반복(): void
    {
        $this->actingAs($this->master())->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'custom',
            'repeat_interval' => 2,
            'repeat_unit' => 'week',
            'repeat_until' => '2026-09-01',
        ]))->assertCreated();

        $dates = Schedule::orderBy('start_date')->pluck('start_date')->map(fn ($d) => $d->format('Y-m-d'))->all();
        $this->assertSame(['2026-08-03', '2026-08-17', '2026-08-31'], $dates);
    }

    public function test_반복_시_담당자도_복제되고_알림은_1회만(): void
    {
        Notification::fake();

        $assigneeUser = User::factory()->create(['role' => 'member']);
        $assignee = Assignee::firstWhere('user_id', $assigneeUser->id);

        $this->actingAs($this->master())->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-08-17',
            'assignees' => [$assignee->id],
        ]))->assertCreated();

        $this->assertSame(3, Schedule::count());
        Schedule::all()->each(fn ($s) => $this->assertSame([$assignee->id], $s->assignees()->pluck('assignees.id')->all()));
        Notification::assertCount(1);
    }

    public function test_수정_시_반복_지정하면_이후_복제_생성(): void
    {
        $user = $this->master();
        $schedule = Schedule::create([
            'title' => '주간 회의',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'color' => 'blue',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'title' => '주간 회의(정례화)',
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-08-24',
        ])->assertOk();

        // 8/3(원본) + 8/10, 8/17, 8/24 = 4건, 그룹 공유
        $this->assertSame(4, Schedule::count());
        $this->assertNotNull($schedule->fresh()->repeat_group);
        $this->assertSame('주간 회의(정례화)', Schedule::where('start_date', '2026-08-24')->first()->title);
    }

    public function test_이미_반복_그룹인_일정_수정은_반복_재생성_안함(): void
    {
        $user = $this->master();
        $this->actingAs($user)->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-08-17',
        ]))->assertCreated();
        $this->assertSame(3, Schedule::count());

        $second = Schedule::where('start_date', '2026-08-10')->first();
        $this->actingAs($user)->postJson("/api/events/{$second->id}", [
            'title' => '변경된 회의',
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-09-30',
        ])->assertOk();

        // 반복 재생성 없이 제목만 변경
        $this->assertSame(3, Schedule::count());
        $this->assertSame('변경된 회의', $second->fresh()->title);
    }

    public function test_반복_종료일_없으면_검증_실패(): void
    {
        $this->actingAs($this->master())->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'weekly',
        ]))->assertUnprocessable();
    }

    public function test_반복_일괄_삭제는_이후_일정만(): void
    {
        $user = $this->master();
        $this->actingAs($user)->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-08-31',
        ]))->assertCreated();

        // 3번째(8/17)부터 일괄 삭제 → 8/3, 8/10만 남음
        $third = Schedule::where('start_date', '2026-08-17')->first();
        $this->actingAs($user)->deleteJson("/api/events/{$third->id}", [
            'reason' => '반복 취소',
            'scope' => 'future',
        ])->assertOk()->assertJson(['deleted' => 3]);

        $this->assertSame(2, Schedule::count());
        $this->assertSame(['2026-08-03', '2026-08-10'], Schedule::orderBy('start_date')->pluck('start_date')->map(fn ($d) => $d->format('Y-m-d'))->all());
    }

    public function test_scope_one은_한_건만_삭제(): void
    {
        $user = $this->master();
        $this->actingAs($user)->postJson('/api/events', $this->basePayload([
            'repeat_freq' => 'weekly',
            'repeat_until' => '2026-08-17',
        ]))->assertCreated();

        $second = Schedule::where('start_date', '2026-08-10')->first();
        $this->actingAs($user)->deleteJson("/api/events/{$second->id}", [
            'reason' => '해당 주만 취소',
            'scope' => 'one',
        ])->assertOk();

        $this->assertSame(2, Schedule::count());
    }
}
