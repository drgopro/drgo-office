<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectPayment;
use App\Models\Schedule;
use App\Models\User;
use App\Services\EstimatePaymentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 결제완료/환불 4화면 동기화 — 캘린더 ⇄ 견적서 ⇄ 프로젝트 결제 내역 ⇄ 주문 내역.
 * 어느 화면에서 처리해도 나머지가 따라와야 한다.
 */
class PaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $this->project = Project::create(['client_id' => $client->id, 'name' => '스튜디오', 'project_type' => 'visit', 'stage' => 'payment']);
        $this->estimate = Estimate::create([
            'status' => 'issued', 'client_id' => $client->id, 'project_id' => $this->project->id,
            'product_items' => [
                ['product_id' => 1, 'name' => '카메라', 'sale_price' => 100000, 'qty' => 2, 'subtotal' => 200000],
                ['product_id' => 2, 'name' => '마이크', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000],
            ],
            'service_items' => [], 'product_total' => 250000, 'service_total' => 0, 'total_amount' => 250000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    private function linkedSchedule(string $paid = '미결제'): Schedule
    {
        return Schedule::create([
            'title' => '고블린 방문',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
            'color' => 'gold',
            'created_by' => $this->admin->id,
            'request_data' => [
                'client_id' => $this->project->client_id,
                'project_id' => $this->project->id,
                'estimate_id' => $this->estimate->id,
                'paid' => $paid,
            ],
        ]);
    }

    public function test_calendar_paid_promotes_estimate_and_records_charge(): void
    {
        $this->actingAs($this->admin)->postJson('/api/events', [
            'title' => '고블린 방문',
            'start_date' => '2026-08-25', 'end_date' => '2026-08-25',
            'is_all_day' => true, 'color' => 'gold',
            'request_data' => [
                'client_id' => $this->project->client_id,
                'project_id' => $this->project->id,
                'nickname' => '고블린', 'name' => '', 'phone' => '',
                'estimate_id' => $this->estimate->id,
                'paid' => '결제완료',
            ],
        ])->assertCreated();

        // 견적서 결제완료 승격
        $this->assertSame('paid', $this->estimate->fresh()->status);

        // 프로젝트 결제 내역에 charge 자동 기록 (항목 포함)
        $charge = ProjectPayment::where('estimate_id', $this->estimate->id)->where('type', 'charge')->first();
        $this->assertNotNull($charge);
        $this->assertSame(250000, $charge->amount);
        $this->assertSame(0, $charge->items[0]['estimate_item_index']);

        // 일정 재저장해도 charge 중복 생성 없음
        $schedule = Schedule::first();
        $this->actingAs($this->admin)->putJson("/api/events/{$schedule->id}", [
            'title' => $schedule->title,
            'start_date' => '2026-08-25', 'end_date' => '2026-08-25',
            'is_all_day' => true, 'color' => 'gold',
            'request_data' => $schedule->request_data,
        ])->assertOk();
        $this->assertSame(1, ProjectPayment::where('estimate_id', $this->estimate->id)->where('type', 'charge')->count());
    }

    public function test_calendar_paid_promotes_all_linked_estimates(): void
    {
        // 추가 견적 — 한 일정에 견적서 두 개 연동 (estimate_ids)
        $second = Estimate::create([
            'status' => 'issued', 'client_id' => $this->estimate->client_id, 'project_id' => $this->project->id,
            'product_items' => [['product_id' => 3, 'name' => '추가 조명', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
            'service_items' => [], 'product_total' => 100000, 'service_total' => 0, 'total_amount' => 100000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->postJson('/api/events', [
            'title' => '고블린 방문',
            'start_date' => '2026-08-25', 'end_date' => '2026-08-25',
            'is_all_day' => true, 'color' => 'gold',
            'request_data' => [
                'client_id' => $this->project->client_id, 'project_id' => $this->project->id,
                'nickname' => '고블린', 'name' => '', 'phone' => '',
                'estimate_id' => $this->estimate->id,
                'estimate_ids' => [$this->estimate->id, $second->id],
                'paid' => '결제완료',
            ],
        ])->assertCreated();

        // 두 견적서 모두 결제완료 승격 + 각각 charge 기록
        $this->assertSame('paid', $this->estimate->fresh()->status);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame(2, ProjectPayment::where('type', 'charge')->count());

        // 일정 표시 금액은 전체 합계 (250,000 + 100,000)
        $g = Schedule::first()->request_data;
        $this->assertSame('350,000', $g['estimate_amount']);

        // 두 번째 견적서 부분환불 → 일정 환불 표시는 전체 연동 견적서 합산
        $this->estimate->applyItemRefunds([['index' => 1, 'qty' => 1, 'amount' => 50000]]);
        $second->applyItemRefunds([['index' => 0, 'qty' => 1, 'amount' => 100000]]);
        EstimatePaymentSync::syncRefundDisplay($second->fresh());
        $this->assertSame('150,000', data_get(Schedule::first()->request_data, 'estimate_refund'));
    }

    public function test_estimate_manual_paid_syncs_project_charge_and_calendar(): void
    {
        $schedule = $this->linkedSchedule();

        // 빌더 저장과 동일하게 전체 본문 전송 (buildEstimateBody)
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->product_items,
            'status' => 'paid',
        ])->assertOk();

        // 프로젝트 charge 기록
        $charge = ProjectPayment::where('estimate_id', $this->estimate->id)->where('type', 'charge')->first();
        $this->assertNotNull($charge);
        $this->assertSame(250000, $charge->amount);
        $this->assertSame('수동 기록', $charge->method);

        // 캘린더 일정 결제완료 + 금액 자동 기입
        $g = $schedule->fresh()->request_data;
        $this->assertSame('결제완료', $g['paid']);
        $this->assertSame('250,000', $g['estimate_amount']);
    }

    public function test_project_full_refund_cancels_estimate_and_calendar(): void
    {
        $this->estimate->forceFill(['status' => 'paid'])->save();
        $schedule = $this->linkedSchedule('결제완료');
        $charge = ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 250000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        // 전액 환불
        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [
                ['name' => '카메라', 'qty' => 2, 'price' => 100000, 'estimate_item_index' => 0],
                ['name' => '마이크', 'qty' => 1, 'price' => 50000, 'estimate_item_index' => 1],
            ],
            'reason' => '전액 환불',
        ])->assertCreated();

        // 견적서 결제취소 + 캘린더 미결제
        $this->assertSame('cancelled', $this->estimate->fresh()->status);
        $this->assertSame('미결제', data_get($schedule->fresh()->request_data, 'paid'));
    }

    public function test_project_partial_refund_keeps_estimate_paid(): void
    {
        $this->estimate->forceFill(['status' => 'paid'])->save();
        $schedule = $this->linkedSchedule('결제완료');
        $charge = ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 250000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [['name' => '마이크', 'qty' => 1, 'price' => 50000, 'estimate_item_index' => 1]],
            'reason' => '부분 환불',
        ])->assertCreated();

        $this->assertSame('paid', $this->estimate->fresh()->status);
        $this->assertSame('결제완료', data_get($schedule->fresh()->request_data, 'paid'));
        // 부분환불 합계가 캘린더 일정에 표시용으로 기록됨
        $this->assertSame('50,000', data_get($schedule->fresh()->request_data, 'estimate_refund'));
    }

    public function test_manual_refund_check_syncs_calendar_display(): void
    {
        $items = $this->estimate->product_items;
        $items[0]['ordered'] = true;
        $this->estimate->forceFill(['product_items' => $items, 'status' => 'paid'])->save();
        $schedule = $this->linkedSchedule('결제완료');

        // 주문 내역 수동 체크 → 캘린더 일정에 환불 합계 표시
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 1, 'refunded' => true, 'refund_amount' => 50000,
        ])->assertOk();
        $this->assertSame('50,000', data_get($schedule->fresh()->request_data, 'estimate_refund'));

        // 해제 → 표시 제거
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 1, 'refunded' => false,
        ])->assertOk();
        $this->assertNull(data_get($schedule->fresh()->request_data, 'estimate_refund'));
    }

    public function test_full_refund_with_ledger_records_cancel_and_marks_all_items(): void
    {
        $this->estimate->forceFill(['status' => 'paid'])->save();
        $schedule = $this->linkedSchedule('결제완료');
        $charge = ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 250000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);
        // 이미 100,000원 부분 환불된 상태
        ProjectPayment::create([
            'project_id' => $this->project->id, 'parent_payment_id' => $charge->id, 'type' => 'refund',
            'estimate_id' => $this->estimate->id, 'amount' => -100000,
            'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);
        $this->estimate->applyItemRefunds([['index' => 0, 'qty' => 1, 'amount' => 100000]]);

        // 페이앱 전액환불 통보 등 — 잔여액 취소 트랜잭션 + 전 항목 환불 표시
        EstimatePaymentSync::estimateCancelled($this->estimate->fresh(), recordLedger: true);

        $cancel = ProjectPayment::where('parent_payment_id', $charge->id)->where('type', 'cancel')->first();
        $this->assertNotNull($cancel);
        $this->assertSame(-150000, $cancel->amount);

        $fresh = $this->estimate->fresh();
        $this->assertSame('cancelled', $fresh->status);
        // 카메라: 기존 1개 환불 + 잔여 1개 → 총 2개 / 마이크: 1개 전부
        $this->assertSame(2, (int) $fresh->product_items[0]['refund_qty']);
        $this->assertSame(200000, (int) $fresh->product_items[0]['refund_amount']);
        $this->assertSame(1, (int) $fresh->product_items[1]['refund_qty']);
        $this->assertSame(50000, (int) $fresh->product_items[1]['refund_amount']);
        $this->assertSame('미결제', data_get($schedule->fresh()->request_data, 'paid'));
        $this->assertSame('250,000', data_get($schedule->fresh()->request_data, 'estimate_refund'));
    }

    public function test_bundle_component_partial_refund_records_snapshot(): void
    {
        $items = $this->estimate->product_items;
        $items[0]['bundle_items'] = [
            ['name' => '렌즈', 'qty' => 2, 'price' => 30000],
            ['name' => '삼각대', 'qty' => 2, 'price' => 20000],
        ];
        $this->estimate->forceFill(['product_items' => $items, 'status' => 'paid'])->save();
        $charge = ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 250000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        // 세트 구성품(렌즈 1개)만 환불
        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/payments/refund", [
            'parent_payment_id' => $charge->id, 'type' => 'refund',
            'items' => [['name' => '카메라 › 렌즈', 'qty' => 1, 'price' => 30000, 'estimate_item_index' => 0, 'bundle_index' => 0]],
            'reason' => '구성품 불량',
        ])->assertCreated();

        $fresh = $this->estimate->fresh();
        $item = $fresh->product_items[0];
        // 구성품에 환불 수량/금액 기록, 항목에는 금액만 합산 (수량은 세트 단위가 아니므로 증가하지 않음)
        $this->assertSame(1, (int) $item['bundle_items'][0]['refund_qty']);
        $this->assertSame(30000, (int) $item['bundle_items'][0]['refund_amount']);
        $this->assertSame(30000, (int) $item['refund_amount']);
        $this->assertSame(0, (int) ($item['refund_qty'] ?? 0));
        $this->assertTrue((bool) $item['refunded']);
        // 부분환불이므로 견적서는 결제완료 유지
        $this->assertSame('paid', $fresh->status);

        // refund-items API에 구성품 잔여 반영
        $bundle = $this->actingAs($this->admin)
            ->getJson("/api/estimates/{$this->estimate->id}/refund-items")
            ->assertOk()->json('items.0.bundle_items');
        $this->assertSame(1, $bundle[0]['refund_qty']);
    }

    public function test_order_history_manual_bundle_component_refund(): void
    {
        $items = $this->estimate->product_items;
        $items[0]['ordered'] = true;
        $items[0]['bundle_items'] = [
            ['name' => '렌즈', 'qty' => 1, 'price' => 30000],
            ['name' => '삼각대', 'qty' => 1, 'price' => 20000],
        ];
        $this->estimate->forceFill(['product_items' => $items, 'status' => 'paid'])->save();

        // 구성품 체크 — 수량 2(세트 2개 × 구성 1개), 금액 비우면 단가×수량 자동
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 0, 'bundle_index' => 0, 'refunded' => true, 'refund_qty' => 2,
        ])->assertOk();

        $item = $this->estimate->fresh()->product_items[0];
        $this->assertSame(2, (int) $item['bundle_items'][0]['refund_qty']);
        $this->assertSame(60000, (int) $item['bundle_items'][0]['refund_amount']);
        // 부모 항목에 합산 표시
        $this->assertTrue((bool) $item['refunded']);
        $this->assertSame(60000, (int) $item['refund_amount']);

        // 금액 직접 지정
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 0, 'bundle_index' => 1, 'refunded' => true, 'refund_qty' => 1, 'refund_amount' => 15000,
        ])->assertOk();
        $item = $this->estimate->fresh()->product_items[0];
        $this->assertSame(15000, (int) $item['bundle_items'][1]['refund_amount']);
        $this->assertSame(75000, (int) $item['refund_amount']);

        // 해제 — 구성품 기록 초기화 + 부모 합산 차감, 모두 해제되면 부모 표시도 초기화
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 0, 'bundle_index' => 0, 'refunded' => false,
        ])->assertOk();
        $item = $this->estimate->fresh()->product_items[0];
        $this->assertArrayNotHasKey('refund_qty', $item['bundle_items'][0]);
        $this->assertSame(15000, (int) $item['refund_amount']);

        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$this->estimate->id}/item-note", [
            'index' => 0, 'bundle_index' => 1, 'refunded' => false,
        ])->assertOk();
        $item = $this->estimate->fresh()->product_items[0];
        $this->assertArrayNotHasKey('refunded', $item);
    }

    public function test_schedule_save_self_heals_refund_display(): void
    {
        // 표시 기능 배포 전에 기록된 환불 — 일정 request_data에 estimate_refund 없음
        $this->estimate->forceFill(['status' => 'paid'])->save();
        $this->estimate->applyItemRefunds([['index' => 1, 'qty' => 1, 'amount' => 50000]]);
        $schedule = $this->linkedSchedule('결제완료');
        $this->assertNull(data_get($schedule->request_data, 'estimate_refund'));

        // 일정을 다시 저장하면 환불 표시가 채워진다
        $this->actingAs($this->admin)->putJson("/api/events/{$schedule->id}", [
            'title' => $schedule->title,
            'start_date' => '2026-08-25', 'end_date' => '2026-08-25',
            'is_all_day' => true, 'color' => 'gold',
            'request_data' => $schedule->request_data,
        ])->assertOk();
        $this->assertSame('50,000', data_get($schedule->fresh()->request_data, 'estimate_refund'));
    }

    public function test_backfill_migration_fills_existing_refunds(): void
    {
        $this->estimate->forceFill(['status' => 'paid'])->save();
        $this->estimate->applyItemRefunds([['index' => 0, 'qty' => 2, 'amount' => 200000]]);
        $schedule = $this->linkedSchedule('결제완료');
        $this->assertNull(data_get($schedule->request_data, 'estimate_refund'));

        $migration = require database_path('migrations/2026_08_25_235500_backfill_estimate_refund_display.php');
        $migration->up();

        $this->assertSame('200,000', data_get($schedule->fresh()->request_data, 'estimate_refund'));
    }

    public function test_payments_api_exposes_estimate_display_no(): void
    {
        // 실제 id와 표시 번호가 다른 견적서 — 화면에는 표시 번호로 통일
        $this->estimate->forceFill(['estimate_no' => 46])->save();
        ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 250000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        $row = $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/payments")
            ->assertOk()->json('payments.0');
        $this->assertSame($this->estimate->id, $row['estimate_id']);
        $this->assertSame(46, $row['estimate_no']);
    }

    public function test_linked_estimate_creates_unpaid_billing_and_settles_on_payment(): void
    {
        // 프로젝트 연동 + 미결제 저장 → 미수 청구 자동 생성
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->product_items,
        ])->assertOk();
        $billing = ProjectBilling::where('estimate_id', $this->estimate->id)->first();
        $this->assertNotNull($billing);
        $this->assertSame('unpaid', $billing->status);
        $this->assertSame(250000, $billing->amount);
        $this->assertSame($this->project->id, $billing->project_id);

        // 총액 변경 → 입금 전 청구 금액 최신화
        $items = $this->estimate->fresh()->product_items;
        $items[] = ['product_id' => null, 'name' => '추가 항목', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000, 'manual' => true];
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $items,
        ])->assertOk();
        $this->assertSame(300000, $billing->fresh()->amount);

        // 결제완료 → charge가 청구에 연결되고 청구가 결제된 금액으로 확정
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $items, 'status' => 'paid',
        ])->assertOk();
        $billing->refresh();
        $this->assertSame('paid', $billing->status);
        $this->assertSame(300000, $billing->amount);
        $charge = ProjectPayment::where('estimate_id', $this->estimate->id)->where('type', 'charge')->first();
        $this->assertSame($billing->id, $charge->billing_id);
        $this->assertSame(300000, $charge->amount);
    }

    public function test_estimate_linked_billing_cannot_be_completed_manually(): void
    {
        // 연동 청구 생성
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->product_items,
        ])->assertOk();
        $billing = ProjectBilling::where('estimate_id', $this->estimate->id)->first();

        // 수동 완료 처리 시도 → 거부, 상태 유지
        $this->actingAs($this->admin)->patchJson("/api/project-billings/{$billing->id}", [
            'status' => 'paid',
        ])->assertUnprocessable();
        $this->assertSame('unpaid', $billing->fresh()->status);

        // 일반(수기) 청구는 여전히 수동 완료 가능
        $manual = ProjectBilling::create([
            'project_id' => $this->project->id, 'amount' => 30000,
            'billed_at' => now()->format('Y-m-d'), 'status' => 'unpaid', 'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)->patchJson("/api/project-billings/{$manual->id}", [
            'status' => 'paid',
        ])->assertOk();
        $this->assertSame('paid', $manual->fresh()->status);

        // 견적서 결제완료 → 연동 청구는 자동으로 완료
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->fresh()->product_items, 'status' => 'paid',
        ])->assertOk();
        $this->assertSame('paid', $billing->fresh()->status);
    }

    public function test_unlinking_project_removes_clean_billing(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->product_items,
        ])->assertOk();
        $this->assertNotNull(ProjectBilling::where('estimate_id', $this->estimate->id)->first());

        // 프로젝트 연동 해제 → 입금 이력 없는 청구 제거
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->product_items, 'project_id' => null,
        ])->assertOk();
        $this->assertNull(ProjectBilling::where('estimate_id', $this->estimate->id)->first());
    }

    public function test_deleting_estimate_cleans_project_and_calendar_links(): void
    {
        // 연동 청구 + 프로젝트 견적/계약 카드 + 캘린더 일정 연동 상태에서 삭제
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => $this->estimate->product_items,
        ])->assertOk();
        $this->project->update(['stage_data' => ['estimate' => ['estimate_ids' => [$this->estimate->id, 999]]]]);
        $schedule = $this->linkedSchedule();
        $g = $schedule->request_data;
        $g['estimate_ids'] = [$this->estimate->id];
        $schedule->forceFill(['request_data' => $g])->save();

        $this->actingAs($this->admin)->deleteJson("/api/estimates/{$this->estimate->id}")->assertOk();

        // 미수 청구 삭제 + 견적/계약 카드에서 제거 (다른 id는 유지) + 일정 연동 해제
        $this->assertNull(ProjectBilling::where('estimate_id', $this->estimate->id)->first());
        $this->assertSame([999], data_get($this->project->fresh()->stage_data, 'estimate.estimate_ids'));
        $sg = $schedule->fresh()->request_data;
        $this->assertArrayNotHasKey('estimate_id', $sg);
        $this->assertArrayNotHasKey('estimate_ids', $sg);
    }

    public function test_payments_api_reports_unpaid_diff_when_estimate_grew_after_charge(): void
    {
        // 결제 기록(200,000) 후 견적서 총액이 250,000으로 커진 상황 — 차액 50,000 미결제 표시
        ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 200000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);

        $row = $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/payments")
            ->assertOk()->json('payments.0');
        $this->assertSame(250000, $row['estimate_total']);
        $this->assertSame(50000, $row['estimate_unpaid_diff']);

        // 차액만큼 추가 결제를 수동 기록하면 차액 0
        ProjectPayment::create([
            'project_id' => $this->project->id, 'type' => 'charge', 'estimate_id' => $this->estimate->id,
            'amount' => 50000, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->admin->id,
        ]);
        $rows = $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/payments")->json('payments');
        foreach ($rows as $r) {
            $this->assertSame(0, $r['estimate_unpaid_diff']);
        }
    }

    public function test_public_estimate_view_shows_refund_details(): void
    {
        $items = $this->estimate->product_items;
        $items[0]['bundle_items'] = [['name' => '렌즈', 'qty' => 1, 'price' => 30000, 'refund_qty' => 1, 'refund_amount' => 30000]];
        $items[0]['refunded'] = true;
        $items[0]['refund_amount'] = 30000;
        $this->estimate->forceFill(['product_items' => $items, 'status' => 'paid'])->save();

        $res = $this->get($this->estimate->publicUrl())->assertOk();
        // 항목 환불 태그 + 환불된 구성품 + 환불 합계 밴드 (환불 반영 후 금액)
        $res->assertSee('환불 30,000원', false)
            ->assertSee('렌즈 환불 1개', false)
            ->assertSee('환불 합계', false)
            ->assertSee('환불 반영 후 220,000원', false)
            ->assertSee('일부 환불 30,000원', false);
        // 환불 안 된 구성품 이름은 의뢰자 견적서에 비노출 유지
        $this->assertStringNotContainsString('삼각대', $res->getContent());
    }

    public function test_public_estimate_view_hides_refund_ui_when_no_refund(): void
    {
        $this->estimate->forceFill(['status' => 'paid'])->save();
        $res = $this->get($this->estimate->publicUrl())->assertOk();
        $this->assertStringNotContainsString('환불 합계', $res->getContent());
        $this->assertStringNotContainsString('<span class="refund-tag">', $res->getContent());
    }
}
