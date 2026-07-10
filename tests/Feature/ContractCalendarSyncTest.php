<?php

namespace Tests\Feature;

use App\Models\BroadcastRoomContract;
use App\Models\CalendarCategory;
use App\Models\Client;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '윤민아', 'grade' => 'normal', 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'start_date' => '2026-07-18',
            'end_date' => null,
            'monthly_fee' => 158000,
            'status' => 'active',
        ], $extra);
    }

    public function test_rental_contract_creates_calendar_events(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload());
        $res->assertCreated();

        $contract = RentalContract::first();
        $meta = $contract->calendar_meta;
        $this->assertNotNull($meta['start_id']);
        $this->assertNull($meta['end_id'], '진행중 계약은 종료 이벤트 없음');
        $this->assertNotNull($meta['repeat_group']);

        // 시작 이벤트
        $start = Schedule::find($meta['start_id']);
        $this->assertSame('렌탈 시작 · 윤민아', $start->title);
        $this->assertSame('rental_contract', $start->source_type);
        $this->assertSame($contract->id, (int) $start->source_id);

        // '렌탈' 카테고리 자동 생성 + 적용
        $cat = CalendarCategory::where('label', '렌탈')->first();
        $this->assertNotNull($cat);
        $this->assertSame($cat->key, $start->color);

        // 진행중 → 매월 18일 반복 13건 (시작월 포함 12개월)
        $repeats = Schedule::where('repeat_group', $meta['repeat_group'])->orderBy('start_date')->get();
        $this->assertCount(13, $repeats);
        $this->assertSame('2026-07-18', $repeats->first()->start_date->format('Y-m-d'));
        $this->assertSame('2027-07-18', $repeats->last()->start_date->format('Y-m-d'));
        $this->assertSame('렌탈 결제 · 윤민아 (158,000원)', $repeats->first()->title);
    }

    public function test_contract_with_end_date_creates_end_event_and_bounded_repeats(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload([
            'end_date' => '2026-10-31',
        ]))->assertCreated();

        $meta = RentalContract::first()->calendar_meta;
        $this->assertNotNull($meta['end_id']);
        $this->assertSame('렌탈 종료 · 윤민아', Schedule::find($meta['end_id'])->title);

        // 7/18, 8/18, 9/18, 10/18 — 종료일까지만
        $dates = Schedule::where('repeat_group', $meta['repeat_group'])->orderBy('start_date')
            ->pluck('start_date')->map(fn ($d) => $d->format('Y-m-d'));
        $this->assertEquals(['2026-07-18', '2026-08-18', '2026-09-18', '2026-10-18'], $dates->all());
    }

    public function test_update_resyncs_calendar(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload())->assertCreated();
        $contract = RentalContract::first();
        $oldMeta = $contract->calendar_meta;

        $this->actingAs($this->user)->patchJson("/api/rental-contracts/{$contract->id}", [
            'monthly_fee' => 200000,
            'end_date' => '2026-09-30',
        ])->assertOk();

        // 기존 일정 소프트 삭제, 새 일정 생성
        $this->assertSoftDeleted('schedules', ['id' => $oldMeta['start_id']]);
        $newMeta = $contract->fresh()->calendar_meta;
        $this->assertNotSame($oldMeta['repeat_group'], $newMeta['repeat_group']);
        $this->assertStringContainsString('200,000원', Schedule::where('repeat_group', $newMeta['repeat_group'])->first()->title);
        $this->assertCount(3, Schedule::where('repeat_group', $newMeta['repeat_group'])->get()); // 7/18, 8/18, 9/18
    }

    public function test_destroy_removes_linked_schedules(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload())->assertCreated();
        $contract = RentalContract::first();
        $meta = $contract->calendar_meta;

        $this->actingAs($this->user)->deleteJson("/api/rental-contracts/{$contract->id}")->assertOk();

        $this->assertSoftDeleted('schedules', ['id' => $meta['start_id']]);
        $this->assertSame(0, Schedule::where('repeat_group', $meta['repeat_group'])->count());
    }

    public function test_broadcast_contract_syncs_with_own_category(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/broadcast-room/contracts', $this->payload([
            'end_date' => '2026-08-31',
        ]));
        $res->assertCreated();

        $meta = BroadcastRoomContract::first()->calendar_meta;
        $start = Schedule::find($meta['start_id']);
        $this->assertSame('방송룸 계약 시작 · 윤민아', $start->title);
        $this->assertSame('broadcast_contract', $start->source_type);

        $cat = CalendarCategory::where('label', '방송룸 대여')->first();
        $this->assertNotNull($cat);
        $this->assertSame('broadcast_rental', $cat->key);
        $this->assertSame($cat->key, $start->color);
    }

    public function test_calendar_delete_keeps_contract_and_resync_recovers(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload())->assertCreated();
        $contract = RentalContract::first();
        $meta = $contract->calendar_meta;

        // 캘린더에서 시작 이벤트 삭제 (원본은 유지 — 연결만 해제)
        Schedule::find($meta['start_id'])->delete();
        $this->assertNotNull($contract->fresh(), '계약은 유지');

        // 계약 수정 시 재동기화로 복구
        $this->actingAs($this->user)->patchJson("/api/rental-contracts/{$contract->id}", ['memo' => '갱신'])->assertOk();
        $newMeta = $contract->fresh()->calendar_meta;
        $this->assertNull(Schedule::find($newMeta['start_id'])->deleted_at);
    }

    public function test_sync_command_backfills_and_extends(): void
    {
        // 미연동 계약 (직접 생성 — API 안 거침)
        $contract = RentalContract::create($this->payload());

        $this->artisan('contracts:sync-calendar')
            ->expectsOutputToContain('미연동')
            ->assertSuccessful();
        $this->assertNull($contract->fresh()->calendar_meta, 'dry-run은 변경 없음');

        $this->artisan('contracts:sync-calendar', ['--force' => true])->assertSuccessful();
        $meta = $contract->fresh()->calendar_meta;
        $this->assertNotNull($meta['start_id']);
        $this->assertSame(13, Schedule::where('repeat_group', $meta['repeat_group'])->count());
    }
}
