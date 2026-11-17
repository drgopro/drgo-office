<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Schedule;
use App\Models\ScheduleBench;
use App\Models\ScheduleChange;
use App\Models\User;
use App\Services\EstimatePaymentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 대기함 API — 명세 10장 '흐름' 중 서버가 책임지는 부분 (보내기·배치 검사·이력·되돌리기) */
class CalendarBenchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 10:00:00');
        $this->user = User::factory()->create(['role' => 'master']);
        $this->actingAs($this->user);
    }

    /** @param  array<string, mixed>  $over */
    private function schedule(array $over = []): Schedule
    {
        return Schedule::create($over + [
            'title' => '윤서진 송파 방문세팅', 'start_date' => '2026-11-19', 'end_date' => '2026-11-19',
            'start_time' => '13:00', 'end_time' => '15:00', 'is_all_day' => false, 'color' => 'gold',
            'address' => '서울 송파구 올림픽로 300', 'sched_opt' => 'confirmed',
        ]);
    }

    private function product(string $name, int $stock, ?int $leadDays): Product
    {
        $product = Product::create(['sku' => 'B-'.md5($name), 'name' => $name, 'sale_price' => 1000, 'is_active' => true, 'order_lead_days' => $leadDays]);
        Inventory::create(['product_id' => $product->id, 'quantity' => $stock, 'last_updated_at' => now()]);

        return $product;
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function estimate(array $items): Estimate
    {
        return Estimate::create([
            'status' => 'created', 'product_items' => $items, 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0, 'validity_days' => 3, 'created_by' => $this->user->id,
        ]);
    }

    /** @param  array<string, mixed>  $over */
    private function send(Schedule $schedule, array $over = []): int
    {
        return $this->postJson("/api/events/{$schedule->id}/bench", $over + ['kind' => 'hold', 'reason' => '고객 장비 도착 지연', 'window_type' => 'asap'])
            ->assertCreated()->json('id');
    }

    /** @return array<int, int> */
    private function calendarIds(): array
    {
        return collect($this->getJson('/api/events?start=2026-11-01&end=2026-12-31')->assertOk()->json())->pluck('id')->all();
    }

    public function test_reason_is_required_to_send(): void
    {
        $schedule = $this->schedule();

        $this->postJson("/api/events/{$schedule->id}/bench", ['kind' => 'hold', 'reason' => '  ', 'window_type' => 'asap'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(0, ScheduleBench::count());
    }

    public function test_hold_leaves_the_calendar_and_keeps_a_trace(): void
    {
        $schedule = $this->schedule();
        $benchId = $this->send($schedule, ['window_type' => 'after', 'window_from' => '2026-11-25', 'due_date' => '2026-12-04']);

        $this->assertNotContains($schedule->id, $this->calendarIds());
        $this->assertNull(Schedule::find($schedule->id)); // 대시보드·알림 등 모든 기본 조회에서 빠진다

        $list = $this->getJson('/api/bench')->assertOk()->json();
        $this->assertSame(1, $list['total']);
        $this->assertSame('2026-11-17', $list['today']);
        $item = $list['items'][0];
        $this->assertSame([$benchId, 'hold', '2026-11-19', '송파구', '11/25 이후 희망'], [$item['id'], $item['kind'], $item['origin_date'], $item['area'], $item['window_label']]);
        $this->assertSame('2026-11-25', $item['earliest']);
        $this->assertSame('benched', ScheduleChange::where('schedule_id', $schedule->id)->value('action'));
    }

    public function test_revisit_keeps_the_original_schedule(): void
    {
        $schedule = $this->schedule(['start_date' => '2026-11-12', 'end_date' => '2026-11-12']);
        $this->send($schedule, ['kind' => 'revisit', 'reason' => '마이크 노이즈 재점검']);

        $this->assertContains($schedule->id, $this->calendarIds());
        $this->assertSame('윤서진 송파 방문세팅 재방문', ScheduleBench::first()->title);
    }

    public function test_only_standalone_visit_and_remote_schedules_can_be_sent(): void
    {
        foreach ([['color' => 'red'], ['repeat_group' => 'g1'], ['is_locked' => true], ['sched_event_opts' => ['no_change']]] as $over) {
            $schedule = $this->schedule($over);
            $this->postJson("/api/events/{$schedule->id}/bench", ['kind' => 'hold', 'reason' => '보류', 'window_type' => 'asap'])->assertUnprocessable();
        }
        $this->assertSame(0, ScheduleBench::count());
    }

    public function test_estimate_products_drive_prep_and_warehouse_check_gates_placement(): void
    {
        $halo = $this->product('amaran Halo 60x', 0, 2);
        $stand = $this->product('PRO-203A 스탠드', 4, 2);
        $estimate = $this->estimate([
            ['product_id' => $halo->id, 'name' => $halo->name, 'qty' => 1],
            ['product_id' => $stand->id, 'name' => $stand->name, 'qty' => 1],
            ['product_id' => $stand->id, 'name' => $stand->name, 'qty' => 2, 'ordered' => true], // 이미 주문완료 — 제외
            ['name' => '수기 항목', 'qty' => 1],
        ]);
        $benchId = $this->send($this->schedule(), ['estimate_ids' => [$estimate->id]]);

        $item = $this->getJson("/api/bench/{$benchId}")->assertOk()->json();
        $this->assertSame('ok', $item['prep']['state']);
        $this->assertSame('3일 내 준비 가능 · 창고 확인 전 1', $item['prep']['label']);
        $this->assertSame('2026-11-21', $item['earliest']);
        $this->assertCount(2, $item['prep']['rows']);

        // 준비일 전 + 창고 확인 전 → 알림 2건, 사유 없이는 409
        $place = ['date' => '2026-11-19', 'sched_opt' => 'suggest'];
        $this->postJson("/api/bench/{$benchId}/place", $place)->assertStatus(409)->assertJsonCount(2, 'alerts');

        // 창고에 있음 + 준비일 이후 → 알림 없음
        $this->putJson("/api/bench/{$benchId}/stock-checks/{$stand->id}", ['result' => 'in_warehouse'])->assertOk();
        $this->assertSame([], $this->getJson("/api/bench/{$benchId}/check?date=2026-11-21")->assertOk()->json('alerts'));

        // 실제 없음 → 주문 경로로 다시 계산
        $this->putJson("/api/bench/{$benchId}/stock-checks/{$stand->id}", ['result' => 'missing'])
            ->assertOk()->assertJsonPath('prep.rows.1.missing', true)->assertJsonPath('prep.rows.1.status', 'ok');
    }

    public function test_override_needs_a_reason_and_is_recorded_with_all_alerts(): void
    {
        $mic = $this->product('오디오테크니카 AT2035', 0, null);
        $schedule = $this->schedule();
        $benchId = $this->send($schedule, ['estimate_ids' => [$this->estimate([['product_id' => $mic->id, 'name' => $mic->name, 'qty' => 1]])->id]]);

        $this->assertSame('ask', $this->getJson("/api/bench/{$benchId}")->json('prep.state'));
        $place = ['date' => '2026-11-20', 'time' => '14:00', 'sched_opt' => 'suggest'];
        $this->postJson("/api/bench/{$benchId}/place", $place + ['override_reason' => '   '])->assertStatus(409);

        $this->postJson("/api/bench/{$benchId}/place", $place + ['override_reason' => '거래처 내일 입고 확정'])
            ->assertOk()->assertJsonPath('overridden', true);

        $schedule = Schedule::findOrFail($schedule->id); // 달력으로 돌아옴
        $this->assertTrue($schedule->bench_override);
        $this->assertNotNull($schedule->bench_placed_at);
        $this->assertSame(['2026-11-20', '14:00', null, false, 'suggest'], [
            $schedule->start_date->format('Y-m-d'), $schedule->start_time, $schedule->end_time, $schedule->is_all_day, $schedule->sched_opt,
        ]);
        $log = ScheduleChange::where('schedule_id', $schedule->id)->where('action', 'override')->firstOrFail();
        $this->assertSame(['준비 여부 미확인: 오디오테크니카 AT2035'], $log->changes['bench']['alerts']);
        $this->assertSame('거래처 내일 입고 확정', $log->changes['bench']['override_reason']);
        $this->assertSame(0, $this->getJson('/api/bench')->json('total'));
    }

    public function test_today_and_past_dates_are_blocked_even_with_override(): void
    {
        $benchId = $this->send($this->schedule());

        foreach (['2026-11-17', '2026-11-10'] as $date) {
            $this->postJson("/api/bench/{$benchId}/place", ['date' => $date, 'sched_opt' => 'confirmed', 'override_reason' => '급함'])->assertUnprocessable();
        }
        $this->assertSame('waiting', ScheduleBench::find($benchId)->status);
    }

    public function test_block_condition_must_be_resolved_before_placing(): void
    {
        $benchId = $this->send($this->schedule(), ['block_condition' => '고객 현장 공사 끝난 뒤']);
        $place = ['date' => '2026-11-20', 'sched_opt' => 'suggest', 'override_reason' => '무시'];

        $this->assertSame('조건 대기', $this->getJson('/api/bench')->json('items.0.flag'));
        $this->postJson("/api/bench/{$benchId}/place", $place)->assertUnprocessable();
        $this->postJson("/api/bench/{$benchId}/resolve-block")->assertOk()->assertJsonPath('blocked', false);
        $this->postJson("/api/bench/{$benchId}/place", $place)->assertOk();
    }

    public function test_empty_time_places_without_time_and_notices_do_not_block(): void
    {
        $assignee = Assignee::create(['name' => '김태한', 'is_active' => true]);
        $leave = Schedule::create(['title' => '김태한 연차', 'start_date' => '2026-12-10', 'end_date' => '2026-12-10', 'color' => 'red']);
        $leave->assignees()->attach($assignee->id);
        $schedule = $this->schedule(['start_date' => '2026-11-12', 'end_date' => '2026-11-12']);
        $benchId = $this->send($schedule, ['kind' => 'revisit', 'reason' => '재점검', 'due_date' => '2026-11-30']);

        // 기한 초과 + 담당 휴가 — 안내만 하고 막지 않는다
        $res = $this->postJson("/api/bench/{$benchId}/place", ['date' => '2026-12-10', 'sched_opt' => 'confirmed', 'assignee_ids' => [$assignee->id]])
            ->assertOk()->assertJsonPath('overridden', false);
        $this->assertSame(['기한 11/30을 넘깁니다.', '김태한 휴가일입니다.'], $res->json('notices'));

        $placed = Schedule::findOrFail($res->json('schedule_id'));
        $this->assertNotSame($schedule->id, $placed->id); // 재방문은 새 일정
        $this->assertSame(['윤서진 송파 방문세팅 재방문', true, null, null, false], [$placed->title, $placed->is_all_day, $placed->start_time, $placed->end_time, $placed->bench_override]);
        $this->assertSame([$assignee->id], $placed->assignees->pluck('id')->all());
        $this->assertSame('placed', ScheduleChange::where('schedule_id', $placed->id)->value('action'));
    }

    public function test_send_place_and_remove_can_each_be_undone(): void
    {
        $schedule = $this->schedule();

        // 보내기 되돌리기 — 원래 날짜로 달력에 복귀
        $benchId = $this->send($schedule);
        $this->postJson("/api/bench/{$benchId}/undo", ['from' => 'waiting'])->assertOk();
        $this->assertContains($schedule->id, $this->calendarIds());
        $this->assertSame(0, ScheduleBench::count());

        // 배치 되돌리기 — 배치 전 날짜·시간으로 되돌리고 다시 대기
        $benchId = $this->send($schedule);
        $this->postJson("/api/bench/{$benchId}/place", ['date' => '2026-11-25', 'sched_opt' => 'suggest'])->assertOk();
        $this->postJson("/api/bench/{$benchId}/undo", ['from' => 'placed'])->assertOk();
        $this->assertNotContains($schedule->id, $this->calendarIds());
        $held = Schedule::withoutGlobalScope('not_benched')->findOrFail($schedule->id);
        $this->assertSame(['2026-11-19', '13:00', 'confirmed', null], [$held->start_date->format('Y-m-d'), $held->start_time, $held->sched_opt, $held->bench_placed_at]);
        $this->assertSame(1, $this->getJson('/api/bench')->json('total'));

        // 빼기 — 보류 일정은 휴지통으로, 되돌리면 다시 대기
        $this->deleteJson("/api/bench/{$benchId}", ['reason' => '고객 취소'])->assertOk();
        $this->assertSame(0, $this->getJson('/api/bench')->json('total'));
        $this->assertTrue(Schedule::onlyTrashed()->whereKey($schedule->id)->exists());
        $this->postJson("/api/bench/{$benchId}/undo", ['from' => 'removed'])->assertOk();
        $this->assertSame(1, $this->getJson('/api/bench')->json('total'));

        // 이미 바뀐 상태를 되돌리려 하면 409
        $this->postJson("/api/bench/{$benchId}/undo", ['from' => 'placed'])->assertStatus(409);
    }

    public function test_held_schedule_keeps_payment_sync_and_takes_the_chosen_estimates_on_placement(): void
    {
        $old = $this->estimate([]);
        $new = $this->estimate([]);
        $schedule = $this->schedule(['request_data' => ['estimate_id' => $old->id, 'estimate_ids' => [$old->id], 'paid' => '미결제']]);
        $benchId = $this->send($schedule, ['estimate_ids' => [$new->id]]);

        // 보류 중에도 결제 표시는 따라온다
        $schedule->update(['request_data' => ['estimate_ids' => [$new->id], 'paid' => '미결제']]);
        $new->update(['total_amount' => 5000]);
        EstimatePaymentSync::estimatePaid($new);
        $this->assertSame('결제완료', Schedule::withoutGlobalScope('not_benched')->find($schedule->id)->request_data['paid']);

        // 배치하면 보낼 때 고른 견적서가 일정에 연결되고, 되돌리면 원래대로
        $schedule->update(['request_data' => ['estimate_id' => $old->id, 'estimate_ids' => [$old->id]]]);
        $this->postJson("/api/bench/{$benchId}/place", ['date' => '2026-11-25', 'sched_opt' => 'suggest'])->assertOk();
        $this->assertSame(['estimate_id' => $new->id, 'estimate_ids' => [$new->id]], Schedule::find($schedule->id)->request_data);
        $this->postJson("/api/bench/{$benchId}/place", ['date' => '2026-11-26', 'sched_opt' => 'suggest'])->assertStatus(409); // 두 번 배치 불가
        $this->postJson("/api/bench/{$benchId}/undo", ['from' => 'placed'])->assertOk();
        $this->assertSame([$old->id], Schedule::withoutGlobalScope('not_benched')->find($schedule->id)->request_data['estimate_ids']);
    }

    public function test_refunded_or_cancelled_bundles_need_no_preparation(): void
    {
        $cam = $this->product('세트 카메라', 0, null);
        $bundle = ['name' => '방송 세트', 'bundle_items' => [['name' => $cam->name, 'qty' => 1]]];
        $estimate = $this->estimate([$bundle + ['qty' => -1], $bundle + ['qty' => 1, 'refunded' => true]]);
        $benchId = $this->send($this->schedule(), ['estimate_ids' => [$estimate->id]]);

        $this->assertSame([], $this->getJson("/api/bench/{$benchId}")->assertOk()->json('prep.rows'));

        $estimate->update(['product_items' => [$bundle + ['qty' => 2]]]);
        $this->assertSame(2, $this->getJson("/api/bench/{$benchId}")->json('prep.rows.0.qty'));
    }

    public function test_undated_item_can_be_created_without_a_schedule(): void
    {
        $this->postJson('/api/bench', ['title' => '마포 원룸 방송 세팅', 'color' => 'gold', 'reason' => '', 'window_type' => 'asap'])->assertUnprocessable();

        $benchId = $this->postJson('/api/bench', [
            'title' => '마포 원룸 방송 세팅', 'color' => 'gold', 'reason' => '신규 의뢰. 12월 첫 주 희망',
            'address' => '서울특별시 마포구 월드컵로 1', 'window_type' => 'between', 'window_from' => '2026-11-30', 'window_to' => '2026-12-05',
        ])->assertCreated()->assertJsonPath('area', '마포구')->assertJsonPath('latest', '2026-12-05')->json('id');

        $id = $this->postJson("/api/bench/{$benchId}/place", ['date' => '2026-12-01', 'sched_opt' => 'confirmed'])->assertOk()->json('schedule_id');
        $this->assertSame('마포 원룸 방송 세팅', Schedule::findOrFail($id)->title);
    }

    public function test_same_area_schedules_are_listed_as_facts_with_window_status(): void
    {
        $halo = $this->product('amaran Halo 60x', 0, 2);
        $benchId = $this->send($this->schedule(['start_date' => '2026-11-12', 'end_date' => '2026-11-12']), [
            'kind' => 'revisit', 'reason' => '조명 추가', 'due_date' => '2026-11-24',
            'estimate_ids' => [$this->estimate([['product_id' => $halo->id, 'name' => $halo->name, 'qty' => 1]])->id],
        ]);
        $this->schedule(['title' => '다온 송파 세팅', 'start_date' => '2026-11-20', 'end_date' => '2026-11-20']);
        $this->schedule(['title' => '새봄 송파 세팅', 'start_date' => '2026-11-24', 'end_date' => '2026-11-24']);
        $this->schedule(['title' => '강남 세팅', 'start_date' => '2026-11-24', 'end_date' => '2026-11-24', 'address' => '서울 강남구 테헤란로 1']);

        $sameArea = $this->getJson("/api/bench/{$benchId}")->assertOk()->json('same_area');
        $this->assertSame([['다온 송파 세팅', 'lead', '제품 준비 전'], ['새봄 송파 세팅', 'ok', '']], array_map(fn (array $s): array => [$s['title'], $s['status'], $s['status_label']], $sameArea));
    }

    public function test_calendar_page_has_collapsed_bench_and_help_only_inside_the_panel(): void
    {
        $html = $this->get('/calendar')->assertOk()->getContent();

        // 기본 접힘 — 패널은 hidden, 상단 버튼은 aria-expanded=false
        $this->assertStringContainsString('<aside id="calBench" class="bench" aria-label="대기함" hidden></aside>', $html);
        $this->assertStringContainsString('id="benchBtn" onclick="benchToggle()" aria-expanded="false"', $html);
        // 사용법 버튼은 패널을 그릴 때만 만들어진다 (접힌 상태의 HTML에는 버튼 요소가 없다)
        $this->assertStringNotContainsString('<button type="button" class="bench-mini" onclick="benchHelp(true)"', strstr($html, '<script>', true) ?: '');
        $this->assertStringContainsString('<template id="benchHelpTpl">', $html);
        // 달력 연동 훅
        foreach (['benchDecorateCell(div, evList, cell.full);', 'function benchDayRowsHtml', '+ benchSideHtml();', 'id="btnBench"', "placed:'대기함 배치'"] as $hook) {
            $this->assertStringContainsString($hook, $html);
        }
    }

    public function test_order_lead_days_can_be_saved_on_a_product(): void
    {
        $product = $this->product('EOS R8', 0, null);
        $form = ['name' => $product->name, 'category_id' => ProductCategory::create(['name' => '방송장비', 'code' => 'BC', 'depth' => 1, 'sort_order' => 1])->id];

        $this->patchJson("/api/inventory/products/{$product->id}", $form + ['order_lead_days' => 7])->assertOk();
        $this->assertSame(7, $product->fresh()->order_lead_days);
        $this->patchJson("/api/inventory/products/{$product->id}", $form + ['order_lead_days' => null])->assertOk();
        $this->assertNull($product->fresh()->order_lead_days);
    }

    public function test_bench_requires_calendar_edit_permission(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guest']));

        $this->getJson('/api/bench')->assertForbidden();
    }
}
