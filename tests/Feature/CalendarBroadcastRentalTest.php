<?php

namespace Tests\Feature;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\Client;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarBroadcastRentalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '정예원', 'grade' => 'normal', 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    private function eventPayload(array $extra = []): array
    {
        return array_merge([
            'title' => '임시 제목',
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-20',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'is_all_day' => false,
            'color' => 'teal',
            'gold_data' => ['client_id' => $this->client->id, 'project_id' => null, 'nickname' => '', 'name' => '', 'phone' => ''],
        ], $extra);
    }

    public function test_hourly_rental_from_calendar_creates_linked_usage(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/events', $this->eventPayload([
            'broadcast_rental' => ['mode' => 'hourly', 'room_no' => '2', 'fee' => 30000],
        ]));

        $res->assertCreated();
        $schedule = Schedule::find($res->json('id'));
        $this->assertSame('정예원 방송룸 2호실 대여', $schedule->title, '제목 자동 표준화');

        $usage = BroadcastRoomUsage::first();
        $this->assertNotNull($usage);
        $this->assertSame($schedule->id, $usage->schedule_id, '중복 일정 없이 연결');
        $this->assertSame('2', $usage->room_no);
        $this->assertSame(2.0, (float) $usage->hours);
        $this->assertSame(30000, (int) $usage->fee);
        $this->assertSame(1, Schedule::count(), '일정은 1건만 (중복 생성 없음)');
    }

    public function test_monthly_rental_from_calendar_creates_contract_with_synced_events(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/events', $this->eventPayload([
            'start_date' => '2026-07-20', 'end_date' => '2026-10-19',
            'is_all_day' => true, 'start_time' => null, 'end_time' => null,
            'broadcast_rental' => ['mode' => 'monthly', 'room_no' => '1', 'fee' => 500000],
        ]));

        $res->assertCreated();
        $contract = BroadcastRoomContract::first();
        $this->assertNotNull($contract);
        $this->assertSame('1', $contract->room_no);
        $this->assertSame(500000, $contract->monthly_fee);
        $this->assertSame('2026-10-19', $contract->end_date->format('Y-m-d'));

        // 표준 일정 세트 생성 + 응답은 시작 일정
        $meta = $contract->calendar_meta;
        $this->assertSame($res->json('id'), $meta['start_id']);
        $start = Schedule::find($meta['start_id']);
        $this->assertSame('정예원 방송룸 1호실 월대여 시작', $start->title);
        $this->assertSame('정예원 방송룸 1호실 월대여 종료', Schedule::find($meta['end_id'])->title);
        // 결제 반복 없이 시작+종료 2건만, 결제 내역은 시작 일정 내용에
        $this->assertSame(2, Schedule::count());
        $this->assertStringContainsString('월 요금: 500,000원', $start->description);
        $this->assertStringContainsString('결제일: 매월 20일', $start->description);
    }

    public function test_rental_contract_from_calendar(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/events', $this->eventPayload([
            'color' => 'rental',
            'start_date' => '2026-07-18', 'end_date' => '2026-09-17',
            'is_all_day' => true, 'start_time' => null, 'end_time' => null,
            'broadcast_rental' => ['mode' => 'rental', 'fee' => 158000],
        ]));

        $res->assertCreated();
        $contract = RentalContract::first();
        $this->assertNotNull($contract);
        $this->assertSame(158000, $contract->monthly_fee);
        $this->assertSame($this->client->id, $contract->client_id);

        $meta = $contract->calendar_meta;
        $this->assertSame($res->json('id'), $meta['start_id']);
        $this->assertSame('렌탈 시작 · 정예원', Schedule::find($meta['start_id'])->title);
        // 렌탈은 결제일 반복 유지: 7/18, 8/18 + 시작/종료 이벤트
        $this->assertSame(2, Schedule::where('repeat_group', $meta['repeat_group'])->count());
    }

    public function test_rental_requires_linked_client(): void
    {
        $this->actingAs($this->user)->postJson('/api/events', $this->eventPayload([
            'gold_data' => null,
            'broadcast_rental' => ['mode' => 'hourly'],
        ]))->assertUnprocessable();

        $this->assertSame(0, Schedule::count());
        $this->assertSame(0, BroadcastRoomUsage::count());
    }

    public function test_hourly_rental_requires_times(): void
    {
        $this->actingAs($this->user)->postJson('/api/events', $this->eventPayload([
            'is_all_day' => true, 'start_time' => null, 'end_time' => null,
            'broadcast_rental' => ['mode' => 'hourly'],
        ]))->assertUnprocessable();

        $this->assertSame(0, BroadcastRoomUsage::count());
    }
}
