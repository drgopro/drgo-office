<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 환불 재고 보정 (임시 점검 페이지) — 과거 로직 결함으로 누락된 재고 복원/입고를
 * 견적서×제품 단위로 리스트업하고 담당자가 확인 후 개별 적용한다.
 */
class RefundRestockAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** 옛 로직 데이터 재현 — 직발 세트 출고 기록은 있는데 부모에만 환불 수량이 기록되고 복원(return)은 없음 */
    private function makeLegacyMissedRestore(): array
    {
        $cam = Product::create(['sku' => 'A-CAM', 'name' => '카메라', 'sale_price' => 100000, 'is_active' => true]);
        Inventory::create(['product_id' => $cam->id, 'quantity' => 4, 'last_updated_at' => now()]);

        $estimate = Estimate::create([
            'status' => 'paid', 'client_nickname' => '고블린',
            'product_items' => [[
                'product_id' => 999999, 'name' => 'MT-88 세트', 'sale_price' => 93000, 'qty' => 1, 'subtotal' => 93000,
                'refunded' => true, 'refund_qty' => 1, 'refund_amount' => 93000, 'refunded_at' => '2026-10-12 19:52',
                'bundle_items' => [
                    ['product_id' => $cam->id, 'name' => '카메라', 'qty' => 1, 'price' => 93000, 'ordered' => true, 'source' => '사무실 발송'],
                ],
            ]],
            'service_items' => [], 'total_amount' => 93000, 'created_by' => $this->admin->id,
        ]);
        // 직발 출고 이력만 존재 (환불 복원 return 누락 — 옛 버그 상태)
        StockMovement::create([
            'product_id' => $cam->id, 'movement_type' => 'out', 'quantity' => 1, 'quantity_after' => 4,
            'user_id' => $this->admin->id, 'memo' => '견적서 #'.$estimate->display_no.' 직접발송',
        ]);

        return [$estimate, $cam];
    }

    public function test_page_lists_missed_direct_restore_and_apply_restocks(): void
    {
        [$estimate, $cam] = $this->makeLegacyMissedRestore();

        $this->actingAs($this->admin)->get('/inventory/refund-restock-audit')
            ->assertOk()
            ->assertSee('직접발송 복원 누락')
            ->assertSee('카메라')
            ->assertSee('#'.$estimate->display_no, false);

        // 적용 → 재고 +1, 보정 기록
        $this->actingAs($this->admin)->postJson('/api/inventory/refund-restock-audit/apply', [
            'estimate_id' => $estimate->id, 'product_id' => $cam->id, 'kind' => 'direct', 'qty' => 1,
        ])->assertOk();
        $this->assertSame(5, $cam->inventory->fresh()->quantity);

        // 멱등 — 보정 후 같은 건 재적용은 거부되고 목록에서 사라진다
        $this->actingAs($this->admin)->postJson('/api/inventory/refund-restock-audit/apply', [
            'estimate_id' => $estimate->id, 'product_id' => $cam->id, 'kind' => 'direct', 'qty' => 1,
        ])->assertStatus(422);
        $this->actingAs($this->admin)->get('/inventory/refund-restock-audit')
            ->assertOk()->assertSee('보정이 필요한 건이 없습니다.');
    }

    public function test_vendor_component_refund_without_restock_is_listed_for_optional_apply(): void
    {
        $lux = Product::create(['sku' => 'A-LUX', 'name' => '룩스패드', 'sale_price' => 75000, 'is_active' => true]);
        Inventory::create(['product_id' => $lux->id, 'quantity' => 2, 'last_updated_at' => now()]);
        $estimate = Estimate::create([
            'status' => 'paid', 'client_nickname' => '고블린',
            'product_items' => [[
                'product_id' => 999998, 'name' => '룩스패드 세트', 'sale_price' => 93000, 'qty' => 1, 'subtotal' => 93000,
                'bundle_items' => [
                    // 거래처 주문분 환불 — 사무실 반품 입고 이력 없음
                    ['product_id' => $lux->id, 'name' => '룩스패드 단품', 'qty' => 1, 'price' => 75000, 'ordered' => true, 'source' => '컴퓨존', 'refund_qty' => 1],
                ],
            ]],
            'service_items' => [], 'total_amount' => 93000, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get('/inventory/refund-restock-audit')
            ->assertOk()
            ->assertSee('반품 미입고')
            ->assertSee('룩스패드');

        $this->actingAs($this->admin)->postJson('/api/inventory/refund-restock-audit/apply', [
            'estimate_id' => $estimate->id, 'product_id' => $lux->id, 'kind' => 'vendor', 'qty' => 1,
        ])->assertOk();
        $this->assertSame(3, $lux->inventory->fresh()->quantity);

        // 입고 이력이 생겼으므로 재감사에서 제외 (멱등)
        $this->actingAs($this->admin)->get('/inventory/refund-restock-audit')
            ->assertOk()->assertSee('보정이 필요한 건이 없습니다.');
    }

    /** 직발 세트 환불 견적서 — 출고 이력 $outQty개, 복원 이력 $returnQty개 */
    private function makeDirectRefundEstimate(int $estimateNo, Product $product, int $outQty, int $returnQty): Estimate
    {
        $estimate = Estimate::create([
            'estimate_no' => $estimateNo, 'status' => 'paid', 'client_nickname' => '고블린',
            'product_items' => [[
                'product_id' => 999999, 'name' => '세트', 'sale_price' => 10000, 'qty' => $outQty, 'subtotal' => 10000,
                'refunded' => true, 'refund_qty' => $outQty, 'refund_amount' => 10000, 'refunded_at' => '2026-10-12 19:52',
                'bundle_items' => [
                    ['product_id' => $product->id, 'name' => $product->name, 'qty' => 1, 'price' => 10000, 'ordered' => true, 'source' => '사무실 발송'],
                ],
            ]],
            'service_items' => [], 'total_amount' => 10000, 'created_by' => $this->admin->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'movement_type' => 'out', 'quantity' => $outQty, 'quantity_after' => 0,
            'user_id' => $this->admin->id, 'memo' => '견적서 #'.$estimateNo.' 직접발송',
        ]);
        if ($returnQty > 0) {
            StockMovement::create([
                'product_id' => $product->id, 'movement_type' => 'return', 'quantity' => $returnQty, 'quantity_after' => 0,
                'user_id' => $this->admin->id, 'memo' => '견적서 #'.$estimateNo.' 직접발송 복원',
            ]);
        }

        return $estimate;
    }

    public function test_movements_are_matched_to_exact_estimate_number_across_batch(): void
    {
        $cam = Product::create(['sku' => 'A-CAM', 'name' => '카메라', 'sale_price' => 100000, 'is_active' => true]);
        Inventory::create(['product_id' => $cam->id, 'quantity' => 0, 'last_updated_at' => now()]);
        // #7은 복원 완료(대상 아님), #77은 2개 중 1개만 복원 → 1개 누락. 접두어가 겹쳐도 섞이면 안 된다.
        $this->makeDirectRefundEstimate(7, $cam, 2, 2);
        $e77 = $this->makeDirectRefundEstimate(77, $cam, 2, 1);

        $this->actingAs($this->admin)->postJson('/api/inventory/refund-restock-audit/apply', [
            'estimate_id' => $e77->id, 'product_id' => $cam->id, 'kind' => 'direct', 'qty' => 2,
        ])->assertStatus(422)->assertJsonPath('message', '보정 가능 수량(1개)을 초과합니다.');

        $e7 = Estimate::where('estimate_no', 7)->first();
        $this->actingAs($this->admin)->postJson('/api/inventory/refund-restock-audit/apply', [
            'estimate_id' => $e7->id, 'product_id' => $cam->id, 'kind' => 'direct', 'qty' => 1,
        ])->assertStatus(422)->assertJsonPath('message', '이미 보정됐거나 대상이 아닙니다. 새로고침 후 다시 확인해 주세요.');
    }

    public function test_audit_query_count_does_not_grow_with_refund_estimates(): void
    {
        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/inventory/refund-restock-audit')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $products = collect(range(1, 4))->map(fn ($i) => Product::create(['sku' => 'Q-'.$i, 'name' => '제품'.$i, 'sale_price' => 1000, 'is_active' => true]));
        $this->makeDirectRefundEstimate(101, $products[0], 1, 0);
        $countQueries(); // 레이아웃 공용 캐시 예열 — 첫 요청에만 있는 쿼리를 비교에서 제외
        $baseline = $countQueries();

        foreach ([102, 103, 104] as $i => $no) {
            $this->makeDirectRefundEstimate($no, $products[$i + 1], 1, 0);
        }

        $this->assertSame($baseline, $countQueries());
    }

    public function test_requires_inventory_edit_permission(): void
    {
        $team = Team::create(['name' => '조회팀', 'slug' => 'inv-view-only', 'permissions' => ['inventory.view']]);
        $viewer = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);

        $this->actingAs($viewer)->get('/inventory/refund-restock-audit')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/inventory/refund-restock-audit/apply', [
            'estimate_id' => 1, 'product_id' => 1, 'kind' => 'direct', 'qty' => 1,
        ])->assertForbidden();
    }
}
