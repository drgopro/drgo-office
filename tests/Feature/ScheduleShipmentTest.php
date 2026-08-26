<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Schedule;
use App\Models\ScheduleShipment;
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
        $this->assertSame('배송완료', $shipment->last_event);
        $this->assertSame('강남2', $shipment->last_location); // 사업장 위치는 별도 필드
        $response->assertJsonPath('shipments.0.last_location', '강남2');
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

    public function test_coupang_carrier_accepted(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $res = $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.coupangls',
            'tracking_no' => '55550001112223',
        ]);

        $res->assertCreated();
        $this->assertSame('쿠팡', $schedule->shipments()->first()->carrierLabel());
        $this->assertArrayHasKey('kr.coupangls', $res->json('carriers'));
    }

    public function test_opaque_tracker_internal_error_is_translated(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake([
            'tracker.test*' => Http::response(['errors' => [['message' => 'Internal error']]]),
            'www.coupangls.com/*' => Http::response('blocked', 403),
        ]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.coupangls',
            'tracking_no' => '10325790701100',
        ])->assertCreated();

        $shipment = $schedule->shipments()->first();
        $this->assertSame('error', $shipment->status);
        $this->assertStringContainsString('직접 확인', $shipment->last_event);
        $this->assertStringNotContainsString('Internal error', $shipment->last_event);
    }

    public function test_coupang_direct_fallback_parses_tracking_page(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        $html = <<<'HTML'
<div class="tracking-detail"><table><thead><tr><th>시각</th><th>위치</th><th>상태</th></tr></thead><tbody>
<tr><td>2026-07-29 09:10:00</td><td>안양HUB</td><td>집화</td></tr>
<tr><td>2026-07-30 14:22:00</td><td>강남캠프</td><td>배송완료</td></tr>
</tbody></table></div>
HTML;
        Http::fake([
            'tracker.test*' => Http::response(['errors' => [['message' => 'Internal error']]]),
            'www.coupangls.com/*' => Http::response($html),
        ]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.coupangls',
            'tracking_no' => '10325790701100',
        ])->assertCreated();

        $shipment = $schedule->shipments()->first();
        $this->assertSame('delivered', $shipment->status);
        $this->assertSame('배송완료', $shipment->last_event);
        $this->assertSame('강남캠프', $shipment->last_location); // 조회 페이지의 위치 컬럼
        $this->assertNotNull($shipment->delivered_at);
    }

    public function test_coupang_direct_fallback_unregistered_waybill(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake([
            'tracker.test*' => Http::response(['errors' => [['message' => 'Internal error']]]),
            'www.coupangls.com/*' => Http::response('<div>운송장 미등록</div>'),
        ]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.coupangls',
            'tracking_no' => '10325790701100',
        ])->assertCreated();

        $shipment = $schedule->shipments()->first();
        $this->assertSame('pending', $shipment->status);
        $this->assertStringContainsString('미등록', $shipment->last_event);
    }

    public function test_self_hosted_url_preferred_over_hosted_api_key(): void
    {
        // 셀프호스팅 URL이 있으면 API 키가 남아 있어도 셀프호스팅을 사용 (비용 0원)
        config([
            'services.delivery_tracker.url' => 'http://tracker.test',
            'services.delivery_tracker.client_id' => 'cid123',
            'services.delivery_tracker.client_secret' => 'sec456',
        ]);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.coupangls',
            'tracking_no' => '10325790701100',
        ])->assertCreated();

        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'http://tracker.test')
            && empty($req->header('Authorization')));

        $this->assertSame('delivered', $schedule->shipments()->first()->status);
    }

    public function test_hosted_api_used_when_only_api_key_configured(): void
    {
        config([
            'services.delivery_tracker.url' => null,
            'services.delivery_tracker.client_id' => 'cid123',
            'services.delivery_tracker.client_secret' => 'sec456',
        ]);
        Http::fake(['apis.tracker.delivery/*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments", [
            'carrier' => 'kr.coupangls',
            'tracking_no' => '10325790701100',
        ])->assertCreated();

        // 공식 API로 TRACKQL-API-KEY 헤더와 함께 호출됐는지 확인
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://apis.tracker.delivery/graphql')
            && $req->header('Authorization')[0] === 'TRACKQL-API-KEY cid123:sec456');

        $this->assertSame('delivered', $schedule->shipments()->first()->status);
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

    /** @param array<string, mixed> $attrs */
    private function makeDeliveredShipment(Schedule $schedule, array $attrs = []): ScheduleShipment
    {
        return $schedule->shipments()->create(array_merge([
            'carrier' => 'kr.cjlogistics',
            'tracking_no' => '999888777666',
            'status' => 'delivered',
            'last_event' => '배송완료',
            'last_location' => null,
            'delivered_at' => now()->subDay(), // 배송완료 후 2일 이내만 위치 백필 대상
            'checked_at' => now()->subDay(),
        ], $attrs));
    }

    public function test_refresh_backfills_location_for_delivered_shipment(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $shipment = $this->makeDeliveredShipment($schedule);

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments/refresh")->assertOk();

        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status);
        $this->assertSame('강남2', $shipment->last_location);
    }

    public function test_backfill_failure_keeps_delivered_status(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response(['errors' => [['message' => 'NOT_FOUND: 조회 기간 만료']]])]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $shipment = $this->makeDeliveredShipment($schedule);

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments/refresh")->assertOk();

        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status); // 만료 응답이 완료 상태를 덮지 않음
        $this->assertSame('배송완료', $shipment->last_event);
        $this->assertNull($shipment->last_location);
    }

    public function test_backfill_skips_recently_checked_shipment(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $this->makeDeliveredShipment($schedule, ['checked_at' => now()->subMinutes(10)]);

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments/refresh")->assertOk();

        Http::assertNothingSent(); // 6시간 이내 재조회 안 함
    }

    public function test_tracking_stops_15_days_after_delivery(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        // 배송완료 후 20일 지난 위치 없는 송장 — 더 이상 실시간 조회하지 않음
        $done = $this->makeDeliveredShipment($schedule, ['delivered_at' => now()->subDays(20)]);

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments/refresh")->assertOk();
        Http::assertNothingSent();
        $this->assertNull($done->fresh()->last_location);

        // 등록이 오래돼도 아직 미완료(배송 중)인 송장은 계속 추적
        $old = $schedule->shipments()->create([
            'carrier' => 'kr.cjlogistics', 'tracking_no' => '111222333444',
            'status' => 'in_transit', 'last_event' => '간선 상차',
        ]);
        $old->forceFill(['created_at' => now()->subDays(20)])->saveQuietly();

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments/refresh")->assertOk();
        Http::assertSentCount(1);
        $this->assertSame('delivered', $old->fresh()->status); // fake 응답으로 완료 갱신됨
    }

    private function makeLinkedEstimate(Schedule $schedule): Estimate
    {
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $schedule->created_by,
        ]);
        $schedule->forceFill(['request_data' => ['estimate_id' => $estimate->id]])->save();

        return $estimate;
    }

    public function test_schedule_shipments_include_linked_estimate_shipments(): void
    {
        config(['services.delivery_tracker.url' => null]);
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $estimate = $this->makeLinkedEstimate($schedule);

        // 견적서 송장 + 일정에 직접 등록된 과거 송장 + 양쪽 중복 송장
        $estimate->shipments()->create(['carrier' => 'kr.cjlogistics', 'tracking_no' => '111111111111', 'status' => 'in_transit']);
        $schedule->shipments()->create(['carrier' => 'kr.lotte', 'tracking_no' => '222222222222', 'status' => 'pending']);
        $schedule->shipments()->create(['carrier' => 'kr.cjlogistics', 'tracking_no' => '111111111111', 'status' => 'pending']); // 중복 — 견적서 쪽만 남음

        $data = $this->actingAs($user)->getJson("/api/schedules/{$schedule->id}/shipments")->assertOk()->json();

        $this->assertSame($estimate->id, $data['estimate_id']);
        $this->assertCount(2, $data['shipments']);
        $bySource = collect($data['shipments'])->keyBy('source');
        $this->assertSame('222222222222', $bySource['schedule']['tracking_no']);
        $this->assertSame('111111111111', $bySource['estimate']['tracking_no']);
    }

    public function test_schedule_merges_shipments_from_multiple_linked_estimates(): void
    {
        config(['services.delivery_tracker.url' => null]);
        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $first = $this->makeLinkedEstimate($schedule);
        $second = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $schedule->created_by,
        ]);
        $schedule->forceFill(['request_data' => ['estimate_id' => $first->id, 'estimate_ids' => [$first->id, $second->id]]])->save();

        $first->shipments()->create(['carrier' => 'kr.cjlogistics', 'tracking_no' => '111111111111', 'status' => 'in_transit']);
        $second->shipments()->create(['carrier' => 'kr.lotte', 'tracking_no' => '222222222222', 'status' => 'pending']);

        $data = $this->actingAs($user)->getJson("/api/schedules/{$schedule->id}/shipments")->assertOk()->json();

        $this->assertCount(2, $data['shipments']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $data['estimate_ids']);
        $this->assertEqualsCanonicalizing(['111111111111', '222222222222'], collect($data['shipments'])->pluck('tracking_no')->all());
    }

    public function test_schedule_refresh_also_refreshes_estimate_shipments(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        $user = User::factory()->create(['role' => 'master']);
        $schedule = $this->makeSchedule();
        $estimate = $this->makeLinkedEstimate($schedule);
        $shipment = $estimate->shipments()->create(['carrier' => 'kr.cjlogistics', 'tracking_no' => '333333333333', 'status' => 'in_transit']);

        $this->actingAs($user)->postJson("/api/schedules/{$schedule->id}/shipments/refresh")->assertOk();

        $this->assertSame('delivered', $shipment->fresh()->status);
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
