<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarShipIconOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_ship_icon_override_persists_and_clears(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = Schedule::create([
            'title' => '세팅 방문', 'start_date' => '2026-07-15', 'end_date' => '2026-07-15',
            'is_all_day' => true, 'color' => 'gold',
        ]);

        // 수동 지정 (△)
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'ship_icon_override' => 'part',
        ])->assertOk();
        $this->assertSame('part', $schedule->fresh()->ship_icon_override);

        // 자동으로 되돌리기
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'ship_icon_override' => null,
        ])->assertOk();
        $this->assertNull($schedule->fresh()->ship_icon_override);

        // 잘못된 값 거부
        $this->actingAs($user)->postJson("/api/events/{$schedule->id}", [
            'ship_icon_override' => 'weird',
        ])->assertUnprocessable();
    }
}
