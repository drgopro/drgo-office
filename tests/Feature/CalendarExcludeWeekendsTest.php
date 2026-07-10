<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarExcludeWeekendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_exclude_weekends_persists_on_store_and_update(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        // 2026-07-13(월) ~ 2026-07-24(금) 장기 일정, 주말 제외
        $res = $this->actingAs($user)->postJson('/api/events', [
            'title' => '장기 세팅 작업',
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-24',
            'is_all_day' => true,
            'exclude_weekends' => true,
            'color' => 'blue',
        ]);

        $res->assertCreated();
        $schedule = Schedule::find($res->json('id'));
        $this->assertTrue($schedule->exclude_weekends);

        // 해제도 반영
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'exclude_weekends' => false,
        ])->assertOk();
        $this->assertFalse($schedule->fresh()->exclude_weekends);
    }
}
