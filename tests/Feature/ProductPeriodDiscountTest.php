<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\Schedule;
use App\Models\User;
use App\Services\EstimatePaymentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 제품 기간 할인 — 재고 관리에서 할인액/기간 설정, 견적 빌더에 할인가 자동 적용 */
class ProductPeriodDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->cat = ProductCategory::create(['name' => '마이크', 'code' => 'MIC', 'depth' => 1, 'sort_order' => 1]);
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::create([
            'sku' => 'MIC-'.str_pad((string) (Product::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => '젠하이저 EW-D 835-S SET', 'category' => '마이크', 'category_id' => $this->cat->id,
            'purchase_price' => 1000000, 'sale_price' => 1400000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => true,
            ...$attrs,
        ]);
    }

    public function test_discount_fields_save_via_product_update(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            'name' => $product->name, 'category_id' => $this->cat->id, 'sale_price' => 1400000,
            'discount_amount' => 310000,
            'discount_starts_at' => '2026-10-14T11:00',
            'discount_ends_at' => '2026-12-31T16:59',
        ])->assertOk();

        $fresh = $product->fresh();
        $this->assertSame(310000, $fresh->discount_amount);
        $this->assertSame('2026-10-14 11:00', $fresh->discount_starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-12-31 16:59', $fresh->discount_ends_at->format('Y-m-d H:i'));

        // 할인 해제 — 0으로 저장
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            'name' => $product->name, 'category_id' => $this->cat->id, 'sale_price' => 1400000,
            'discount_amount' => 0, 'discount_starts_at' => null, 'discount_ends_at' => null,
        ])->assertOk();
        $this->assertSame(0, $product->fresh()->discount_amount);
    }

    public function test_active_discount_respects_period_and_price_cap(): void
    {
        // 기간 내 — 적용
        $active = $this->makeProduct([
            'discount_amount' => 310000,
            'discount_starts_at' => now()->subDay(), 'discount_ends_at' => now()->addDay(),
        ]);
        $this->assertSame(310000, $active->activeDiscountAmount());
        $this->assertSame(1090000, $active->discountedPrice());

        // 기간 없음 — 상시 적용
        $always = $this->makeProduct(['discount_amount' => 100000]);
        $this->assertSame(1300000, $always->discountedPrice());

        // 기간 지남/아직 시작 전 — 미적용
        $expired = $this->makeProduct(['discount_amount' => 310000, 'discount_ends_at' => now()->subMinute()]);
        $future = $this->makeProduct(['discount_amount' => 310000, 'discount_starts_at' => now()->addDay()]);
        $this->assertSame(0, $expired->activeDiscountAmount());
        $this->assertSame(0, $future->activeDiscountAmount());

        // 판매가 초과 할인 — 판매가까지만 (할인가 0원, 음수 방지)
        $over = $this->makeProduct(['discount_amount' => 99999999]);
        $this->assertSame(1400000, $over->activeDiscountAmount());
        $this->assertSame(0, $over->discountedPrice());
    }

    public function test_estimate_catalog_carries_discount_only_while_active(): void
    {
        $this->makeProduct([
            'name' => '할인 중 마이크', 'discount_amount' => 310000,
            'discount_starts_at' => now()->subHour(), 'discount_ends_at' => now()->addWeek(),
        ]);
        $this->makeProduct(['name' => '할인 끝난 마이크', 'discount_amount' => 310000, 'discount_ends_at' => now()->subHour()]);

        $rows = collect($this->actingAs($this->admin)->getJson('/api/inventory/estimate-products')->assertOk()->json());

        $on = $rows->firstWhere('name', '할인 중 마이크');
        $this->assertTrue($on['discount_active']);
        $this->assertSame(310000, $on['discount_amount']);
        $this->assertSame(1090000, $on['discounted_price']);

        $off = $rows->firstWhere('name', '할인 끝난 마이크');
        $this->assertFalse($off['discount_active']);
        $this->assertSame(0, $off['discount_amount']);
        $this->assertSame(1400000, $off['discounted_price']);
    }

    public function test_products_list_filters_currently_discounted(): void
    {
        $this->makeProduct(['name' => '할인 중', 'discount_amount' => 100000]);
        $this->makeProduct(['name' => '할인 아님']);
        $this->makeProduct(['name' => '할인 만료', 'discount_amount' => 100000, 'discount_ends_at' => now()->subHour()]);

        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/products?discount=active&per_page=50')
            ->assertOk()->json('data');

        $this->assertSame(['할인 중'], collect($rows)->pluck('name')->all());
    }

    public function test_discount_changes_are_logged_and_searchable(): void
    {
        $product = $this->makeProduct(['name' => '이력 마이크']);
        $base = ['name' => $product->name, 'category_id' => $this->cat->id, 'sale_price' => 1400000];

        // 설정 → 변경 → 해제 = 이력 3건 (설정 2 + 해제 1), 할인 외 수정은 이력 없음
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            ...$base, 'discount_amount' => 310000, 'discount_ends_at' => '2026-12-31T16:59',
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            ...$base, 'discount_amount' => 200000, 'discount_ends_at' => '2026-12-31T16:59',
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            ...$base, 'discount_amount' => 200000, 'discount_ends_at' => '2026-12-31T16:59', 'memo' => '메모만 수정',
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            ...$base, 'discount_amount' => 0,
        ])->assertOk();

        $logs = $this->actingAs($this->admin)->getJson('/api/inventory/discount-logs?search='.urlencode('이력 마이크'))
            ->assertOk()->json('data');

        $this->assertCount(3, $logs);
        $this->assertSame(['clear', 'set', 'set'], collect($logs)->pluck('action')->all()); // 최신순
        $this->assertSame(200000, $logs[1]['discount_amount']);
        $this->assertSame(1200000, $logs[1]['discounted_price']);
        $this->assertNotNull($logs[1]['user_name'], '처리자 이름이 비어있음');
        $this->assertSame($this->admin->display_name ?? $this->admin->username, $logs[1]['user_name']);
        $this->assertSame('2026-12-31 16:59', $logs[1]['ends_at']);

        // 무관한 검색어는 미매칭
        $this->assertCount(0, $this->actingAs($this->admin)->getJson('/api/inventory/discount-logs?search=zzz')->json('data'));
    }

    public function test_logs_filter_by_product_and_mark_expired_period(): void
    {
        $a = $this->makeProduct(['name' => '만료 상품']);
        $b = $this->makeProduct(['name' => '다른 상품']);
        $base = fn (Product $p) => ['name' => $p->name, 'category_id' => $this->cat->id, 'sale_price' => 1400000];

        // 기간이 이미 지난 할인 설정 → 이력에 '기간 종료(expired)'로 표시
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$a->id}", [
            ...$base($a), 'discount_amount' => 100000,
            'discount_starts_at' => now()->subDays(3)->format('Y-m-d\TH:i'), 'discount_ends_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$b->id}", [
            ...$base($b), 'discount_amount' => 50000, 'discount_ends_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ])->assertOk();

        // 제품별 필터 — a의 이력만
        $logs = $this->actingAs($this->admin)->getJson("/api/inventory/discount-logs?product_id={$a->id}")
            ->assertOk()->json('data');
        $this->assertCount(1, $logs);
        $this->assertTrue($logs[0]['expired']);

        // 진행 중인 할인은 expired 아님 + 제품 목록에 이력 건수 포함
        $logsB = $this->actingAs($this->admin)->getJson("/api/inventory/discount-logs?product_id={$b->id}")->json('data');
        $this->assertFalse($logsB[0]['expired']);
        // 목록 로드가 만료 할인을 즉시 정리하므로 a는 설정 + 기간 종료 = 2건
        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/products?per_page=50')->json('data');
        $this->assertSame(2, collect($rows)->firstWhere('id', $a->id)['discount_logs_count']);
    }

    public function test_expired_discounts_are_cleared_by_command_with_expire_log(): void
    {
        $expired = $this->makeProduct(['name' => '만료될 상품', 'discount_amount' => 310000,
            'discount_starts_at' => now()->subWeek(), 'discount_ends_at' => now()->subHour()]);
        $running = $this->makeProduct(['name' => '진행 중 상품', 'discount_amount' => 100000,
            'discount_ends_at' => now()->addDay()]);
        $always = $this->makeProduct(['name' => '상시 할인 상품', 'discount_amount' => 50000]);

        $this->artisan('products:expire-discounts')->assertSuccessful();

        // 만료 건만 초기화(체크 해제 상태) — 기간 없는 상시 할인과 진행 중 할인은 유지
        $fresh = $expired->fresh();
        $this->assertSame(0, $fresh->discount_amount);
        $this->assertNull($fresh->discount_ends_at);
        $this->assertSame(100000, $running->fresh()->discount_amount);
        $this->assertSame(50000, $always->fresh()->discount_amount);

        // 이력에 '기간 종료(expire)' 행 — 종료된 할인 값 스냅샷 보존, 처리자 없음(자동)
        $log = $expired->discountLogs()->latest('id')->first();
        $this->assertSame('expire', $log->action);
        $this->assertSame(310000, $log->discount_amount);
        $this->assertNull($log->user_id);

        // 다시 실행해도 중복 기록 없음
        $this->artisan('products:expire-discounts')->assertSuccessful();
        $this->assertSame(1, $expired->discountLogs()->count());
    }

    public function test_expired_discount_clears_immediately_on_list_load(): void
    {
        // 스케줄을 기다리지 않고 — 제품 목록을 여는 순간 만료 할인 해제 (모달 체크 해제 상태)
        $expired = $this->makeProduct(['name' => '방금 만료', 'discount_amount' => 300000,
            'discount_starts_at' => now()->subHours(2), 'discount_ends_at' => now()->subMinute()]);

        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/products?per_page=50')->assertOk()->json('data');

        $row = collect($rows)->firstWhere('id', $expired->id);
        $this->assertSame(0, (int) $row['discount_amount']);
        $this->assertNull($row['discount_ends_at']);
        $this->assertSame('expire', $expired->discountLogs()->latest('id')->first()->action);

        // 견적 빌더 카탈로그도 동일
        $expired2 = $this->makeProduct(['name' => '빌더 만료', 'discount_amount' => 10000, 'discount_ends_at' => now()->subMinute()]);
        $est = collect($this->actingAs($this->admin)->getJson('/api/inventory/estimate-products')->json());
        $this->assertFalse($est->firstWhere('name', '빌더 만료')['discount_active']);
        $this->assertSame(0, $expired2->fresh()->discount_amount);
    }

    public function test_print_shows_limited_period_notice(): void
    {
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'total_amount' => 0, 'created_by' => $this->admin->id,
        ]);
        $items = [
            ['name' => '기간할인 마이크', 'sale_price' => 1090000, 'qty' => 1, 'subtotal' => 1090000,
                'deal_type' => 'discount', 'original_price' => 1400000, 'discount_rate' => 22,
                'deal_starts_at' => '2026-10-14 11:00', 'deal_ends_at' => '2026-12-31 16:59'],
            ['name' => '수동할인 케이블', 'sale_price' => 9000, 'qty' => 1, 'subtotal' => 9000,
                'deal_type' => 'discount', 'original_price' => 10000, 'discount_rate' => 10],
        ];
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $items, 'service_items' => [],
        ])->assertOk();

        // 기간 스냅샷 저장 + 출력물 하단 문구
        $this->assertSame('2026-12-31 16:59', $estimate->fresh()->product_items[0]['deal_ends_at']);
        $html = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()->getContent();
        $this->assertStringContainsString('2026년 10월 14일 11시 ~ 2026년 12월 31일 16시 59분까지 기간 한정 할인이 적용된 금액입니다.', $html);
        // 기간 없는 수동 할인은 기존 문구 유지
        $this->assertStringContainsString('재방문 세팅비 할인 또는 이벤트 할인이 적용된 금액입니다.', $html);
    }

    public function test_unlocked_estimate_syncs_period_discount_and_calendar_meta_total(): void
    {
        $product = $this->makeProduct(['name' => '동기화 마이크']);
        // 정가 시절에 담긴 견적서 (미잠금)
        $estimate = Estimate::create([
            'status' => 'created', 'created_by' => $this->admin->id,
            'product_items' => [[
                'product_id' => $product->id, 'name' => $product->name,
                'sale_price' => 1400000, 'purchase_price' => 1000000, 'qty' => 1, 'subtotal' => 1400000,
            ]],
            'service_items' => [], 'product_total' => 1400000, 'total_amount' => 1400000,
        ]);

        // 이후 제품에 기간 할인 설정 → 캘린더 메타 조회(ID 검색)가 동기화를 거쳐 할인 총액을 반환
        $product->update(['discount_amount' => 310000, 'discount_ends_at' => now()->addWeek()]);
        $row = collect($this->actingAs($this->admin)->getJson("/api/estimates?search={$estimate->id}")->assertOk()->json())
            ->firstWhere('id', $estimate->id);
        $this->assertSame(1090000, (int) $row['total_amount']);

        $item = $estimate->fresh()->product_items[0];
        $this->assertSame('discount', $item['deal_type']);
        $this->assertTrue($item['auto_deal']);
        $this->assertSame(1400000, (int) $item['original_price']);

        // 할인이 끝나면 정가로 복원 (자동 적용분만 — 수동 특가/할인은 보호)
        $product->update(['discount_amount' => 0, 'discount_ends_at' => null]);
        $estimate->fresh()->syncSnapshotPrices();
        $synced = $estimate->fresh();
        $this->assertSame(1400000, (int) $synced->total_amount);
        $this->assertArrayNotHasKey('deal_type', $synced->product_items[0]);

        // 수동 특가 항목은 할인 동기화가 건드리지 않는다
        $manual = Estimate::create([
            'status' => 'created', 'created_by' => $this->admin->id,
            'product_items' => [[
                'product_id' => $product->id, 'name' => $product->name,
                'sale_price' => 999000, 'qty' => 1, 'subtotal' => 999000,
                'deal_type' => 'special', 'original_price' => 1400000,
            ]],
            'service_items' => [], 'product_total' => 999000, 'total_amount' => 999000,
        ]);
        $product->update(['discount_amount' => 310000]);
        $manual->fresh()->syncSnapshotPrices();
        $this->assertSame(999000, (int) $manual->fresh()->product_items[0]['sale_price']);

        // 잠금 상태(결제완료)는 불변
        $paid = Estimate::create([
            'status' => 'paid', 'created_by' => $this->admin->id,
            'product_items' => [[
                'product_id' => $product->id, 'name' => $product->name,
                'sale_price' => 1400000, 'qty' => 1, 'subtotal' => 1400000,
            ]],
            'service_items' => [], 'product_total' => 1400000, 'total_amount' => 1400000,
        ]);
        $paid->fresh()->syncSnapshotPrices();
        $this->assertSame(1400000, (int) $paid->fresh()->product_items[0]['sale_price']);
    }

    public function test_total_change_propagates_to_auto_charge_and_paid_schedule(): void
    {
        $product = $this->makeProduct(['name' => '원장 마이크']);
        $project = Project::create(['name' => '원장 테스트', 'stage' => 'consult']);
        $estimate = Estimate::create([
            'status' => 'created', 'created_by' => $this->admin->id, 'project_id' => $project->id,
            'product_items' => [['product_id' => $product->id, 'name' => $product->name,
                'sale_price' => 1400000, 'purchase_price' => 1000000, 'qty' => 1, 'subtotal' => 1400000]],
            'service_items' => [], 'product_total' => 1400000, 'total_amount' => 1400000,
        ]);
        // 자동 기록 원장 + 수동 기록(선금) 원장 + 결제완료 일정
        $auto = ProjectPayment::create(['project_id' => $project->id, 'type' => 'charge',
            'estimate_id' => $estimate->id, 'amount' => 1400000, 'paid_at' => '2026-10-08',
            'memo' => "견적서 #{$estimate->id} 결제완료 자동 기록"]);
        $manual = ProjectPayment::create(['project_id' => $project->id, 'type' => 'charge',
            'estimate_id' => $estimate->id, 'amount' => 500000, 'paid_at' => '2026-10-08', 'memo' => '선금']);
        $schedule = Schedule::create(['title' => '결제 일정', 'start_date' => '2026-10-08', 'end_date' => '2026-10-08',
            'request_data' => ['paid' => '결제완료', 'estimate_ids' => [$estimate->id], 'estimate_amount' => '1,400,000']]);

        // 제품에 할인 설정 → 동기화 시 원장(자동)·일정 금액이 할인 총액으로
        $product->update(['discount_amount' => 310000, 'discount_ends_at' => now()->addWeek()]);
        $estimate->fresh()->syncSnapshotPrices();

        $this->assertSame(1090000, (int) $auto->fresh()->amount);
        $this->assertSame(1090000, (int) collect($auto->fresh()->items)->sum(fn ($i) => $i['qty'] * $i['price']));
        $this->assertSame(500000, (int) $manual->fresh()->amount, '수동 기록(선금)은 불변');
        $this->assertSame('1,090,000', $schedule->fresh()->request_data['estimate_amount']);

        // 결제 내역 API를 열어도 어긋난 자동 원장을 따라잡는다 (과거 데이터 회복 경로)
        $auto->forceFill(['amount' => 1469000])->save();
        $this->actingAs($this->admin)->getJson("/api/projects/{$project->id}/payments")->assertOk();
        $this->assertSame(1090000, (int) $auto->fresh()->amount);

        // 환불 하위 기록이 달린 자동 원장은 보존
        ProjectPayment::create(['project_id' => $project->id, 'type' => 'refund',
            'parent_payment_id' => $auto->id, 'estimate_id' => $estimate->id, 'amount' => -100000, 'paid_at' => '2026-10-09']);
        $auto->refresh()->forceFill(['amount' => 1469000])->save();
        EstimatePaymentSync::estimateTotalsChanged($estimate->fresh());
        $this->assertSame(1469000, (int) $auto->fresh()->amount);
    }

    public function test_discount_amount_is_capped_to_sale_price_on_save(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$product->id}", [
            'name' => $product->name, 'category_id' => $this->cat->id, 'sale_price' => 500000,
            'discount_amount' => 9000000,
        ])->assertOk();

        $this->assertSame(500000, $product->fresh()->discount_amount);
    }
}
