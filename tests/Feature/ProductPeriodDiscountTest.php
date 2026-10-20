<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
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
        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/products?per_page=50')->json('data');
        $this->assertSame(1, collect($rows)->firstWhere('id', $a->id)['discount_logs_count']);
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
