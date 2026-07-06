<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduleShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(string $color = 'gold'): Schedule
    {
        return Schedule::create([
            'title' => '방문 세팅',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-06',
            'color' => $color,
            'created_by' => User::factory()->create(['role' => 'member'])->id,
        ]);
    }

    private function deliveredResponse(): array
    {
        return [
            'data' => [
                'track' => [
                    'lastEvent' => [
                        'time' => '2026-07-06T15:00:00+09:00',
                        'description' => '배송완료',
                        'status' => ['code' => 'DELIVERED', 'name' => '배송완료'],
                        'location' => ['name' => '강남2'],
                    ],
                ],
            ],
        ];
    }

    public function test_store_shipment_tracks_immediately(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $response = $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.cjlogistics',
            'tracking_no' => '1234-5678-9012',
        ]);

        $response->assertCreated();
        $shipment = $schedule->shipments()->first();
        $this->assertSame('123456789012', $shipment->tracking_no); // 하이픈 제거
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertStringContainsString('강남2', $shipment->last_event);
    }

    public function test_duplicate_shipment_rejected(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $payload = ['carrier' => 'kr.cjlogistics', 'tracking_no' => '111122223333'];

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", $payload)->assertCreated();
        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", $payload)->assertUnprocessable();
    }

    public function test_invalid_carrier_rejected(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.unknown',
            'tracking_no' => '123',
        ])->assertUnprocessable();
    }

    public function test_tracker_unconfigured_saves_error_status(): void
    {
        config(['services.delivery_tracker.url' => null]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.logen',
            'tracking_no' => '99887766',
        ])->assertCreated();

        $this->assertSame('error', $schedule->shipments()->first()->status);
    }

    public function test_events_api_returns_shipment_counts(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.cjlogistics', 'tracking_no' => '123456789012',
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson('/api/events?start=2026-07-01&end=2026-07-31');

        $response->assertOk();
        $event = collect($response->json())->firstWhere('id', $schedule->id);
        $this->assertSame(1, $event['shipments_count']);
        $this->assertSame(1, $event['shipments_delivered_count']);
    }

    public function test_delete_shipment(): void
    {
        config(['services.delivery_tracker.url' => null]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.kdexp', 'tracking_no' => '5544332211',
        ])->assertCreated();
        $shipment = $schedule->shipments()->first();

        $this->actingAs($user)->deleteJson("/api/schedule-shipments/{$shipment->id}")->assertOk();

        $this->assertSame(0, $schedule->shipments()->count());
    }
}
