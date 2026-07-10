<?php

namespace Tests\Feature;

use App\Models\BroadcastRoomUsage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastRoomUsageHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_hours_are_positive(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '방송룸의뢰자', 'grade' => 'normal', 'status' => 'active']);

        $res = $this->actingAs($user)->postJson('/api/broadcast-room/usages', [
            'client_id' => $client->id,
            'start_at' => '2026-07-10 14:00',
            'end_at' => '2026-07-10 16:30',
            'fee' => 30000,
        ]);

        $res->assertCreated();
        $usage = BroadcastRoomUsage::first();
        $this->assertSame(2.5, (float) $usage->hours, '2시간 30분 → 2.5 (양수)');

        // 수정 시에도 양수 유지
        $this->actingAs($user)->patchJson("/api/broadcast-room/usages/{$usage->id}", [
            'end_at' => '2026-07-10 15:00',
        ])->assertOk();
        $this->assertSame(1.0, (float) $usage->fresh()->hours);
    }
}
