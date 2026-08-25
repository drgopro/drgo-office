<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 세트 상품 — 구성품 정의, 조립 가능 수, 출고/반품 시 구성품 재고 동기화 */
class BundleProductTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private ProductCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->create(['role' => 'master']);
        $this->cat = ProductCategory::create(['name' => '방송장비', 'code' => 'BC', 'depth' => 1, 'sort_order' => 1]);
    }

    private function makeComponent(string $name, int $stock): Product
    {
        $p = Product::create([
            'sku' => 'BC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => $name,
            'category' => '방송장비',
            'category_id' => $this->cat->id,
            'purchase_price' => 10000,
            'sale_price' => 20000,
            'safety_stock' => 0,
            'is_active' => true,
            'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $p->id, 'quantity' => $stock, 'last_updated_at' => now()]);

        return $p;
    }

    /** @param array<int, array{product_id:int, quantity:int}> $items */
    private function makeBundle(array $items, string $name = '방송 입문 세트')
    {
        return $this->actingAs($this->master)->postJson('/api/inventory/products', [
            'name' => $name,
            'category_id' => $this->cat->id,
            'sale_price' => 150000,
            'is_bundle' => true,
            'bundle_items' => $items,
        ]);
    }

    public function test_bundle_is_created_without_inventory_and_with_items(): void
    {
        $mic = $this->makeComponent('마이크', 5);
        $arm = $this->makeComponent('붐암', 3);

        $res = $this->makeBundle([
            ['product_id' => $mic->id, 'quantity' => 1],
            ['product_id' => $arm->id, 'quantity' => 2],
        ]);

        $res->assertCreated();
        $bundle = Product::find($res->json('id'));
        $this->assertTrue($bundle->is_bundle);
        $this->assertNull($bundle->inventory, '세트는 자체 재고 없음');
        $this->assertSame(2, $bundle->bundleItems()->count());
        // 조립 가능 수 = min(5/1, 3/2) = 1
        $this->assertSame(1, $bundle->fresh()->load('bundleItems.component.inventory')->buildableQuantity());
    }

    public function test_bundle_requires_items_and_rejects_nesting(): void
    {
        $this->makeBundle([])->assertStatus(422);

        $mic = $this->makeComponent('마이크', 5);
        $inner = $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]], '이너 세트');
        $inner->assertCreated();

        // 세트 안에 세트 금지
        $this->makeBundle([['product_id' => $inner->json('id'), 'quantity' => 1]], '중첩 세트')
            ->assertStatus(422);
    }

    public function test_bundle_out_deducts_component_stock(): void
    {
        $mic = $this->makeComponent('마이크', 5);
        $arm = $this->makeComponent('붐암', 4);
        $bundleId = $this->makeBundle([
            ['product_id' => $mic->id, 'quantity' => 1],
            ['product_id' => $arm->id, 'quantity' => 2],
        ])->json('id');

        $this->actingAs($this->master)->postJson('/api/inventory/movements', [
            'product_id' => $bundleId, 'movement_type' => 'out', 'quantity' => 2,
        ])->assertCreated();

        $this->assertSame(3, $mic->inventory->fresh()->quantity); // 5 - 1×2
        $this->assertSame(0, $arm->inventory->fresh()->quantity); // 4 - 2×2
        // 구성품에 출고 이력이 세트 라벨과 함께 남음
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $mic->id, 'movement_type' => 'out', 'quantity' => 2,
        ]);
        $memo = StockMovement::where('product_id', $arm->id)->first()->memo;
        $this->assertStringContainsString('방송 입문 세트', $memo);
    }

    public function test_bundle_out_shortage_warns_then_force_proceeds(): void
    {
        $mic = $this->makeComponent('마이크', 5);
        $arm = $this->makeComponent('붐암', 1);
        $bundleId = $this->makeBundle([
            ['product_id' => $mic->id, 'quantity' => 1],
            ['product_id' => $arm->id, 'quantity' => 2],
        ])->json('id');

        // 부족 — force 없이 409 + 부족 내역
        $res = $this->actingAs($this->master)->postJson('/api/inventory/movements', [
            'product_id' => $bundleId, 'movement_type' => 'out', 'quantity' => 1,
        ]);
        $res->assertStatus(409);
        $this->assertSame('붐암', $res->json('shortages.0.name'));
        $this->assertSame(2, $res->json('shortages.0.need'));
        $this->assertSame(1, $res->json('shortages.0.have'));
        $this->assertSame(5, $mic->inventory->fresh()->quantity, '경고 단계에서는 재고 변화 없음');

        // force — 진행 허용 (음수 재고)
        $this->actingAs($this->master)->postJson('/api/inventory/movements', [
            'product_id' => $bundleId, 'movement_type' => 'out', 'quantity' => 1, 'force' => true,
        ])->assertCreated();
        $this->assertSame(-1, $arm->inventory->fresh()->quantity);
    }

    public function test_bundle_return_restores_component_stock(): void
    {
        $mic = $this->makeComponent('마이크', 3);
        $bundleId = $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]])->json('id');

        $this->actingAs($this->master)->postJson('/api/inventory/movements', [
            'product_id' => $bundleId, 'movement_type' => 'return', 'quantity' => 2,
        ])->assertCreated();
        $this->assertSame(5, $mic->inventory->fresh()->quantity);
    }

    public function test_bundle_rejects_in_and_adjust(): void
    {
        $mic = $this->makeComponent('마이크', 3);
        $bundleId = $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]])->json('id');

        foreach (['in', 'adjust'] as $type) {
            $this->actingAs($this->master)->postJson('/api/inventory/movements', [
                'product_id' => $bundleId, 'movement_type' => $type, 'quantity' => 1,
            ])->assertStatus(422);
        }
    }

    public function test_component_delete_blocked_while_in_bundle(): void
    {
        $mic = $this->makeComponent('마이크', 3);
        $bundleId = $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]])->json('id');

        $this->actingAs($this->master)->deleteJson("/api/inventory/products/{$mic->id}")
            ->assertStatus(422);
        $this->assertNotNull(Product::find($mic->id));

        // 세트 삭제 후에는 구성품 삭제 가능 + 구성 정의도 정리됨
        $this->actingAs($this->master)->deleteJson("/api/inventory/products/{$bundleId}")->assertOk();
        $this->assertSame(0, ProductBundleItem::where('bundle_product_id', $bundleId)->count());
        $this->actingAs($this->master)->deleteJson("/api/inventory/products/{$mic->id}")->assertOk();
    }

    public function test_bundle_items_can_be_updated(): void
    {
        $mic = $this->makeComponent('마이크', 3);
        $arm = $this->makeComponent('붐암', 3);
        $bundleId = $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]])->json('id');

        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$bundleId}", [
            'name' => '방송 입문 세트 v2',
            'category_id' => $this->cat->id,
            'sale_price' => 180000,
            'is_bundle' => true,
            'bundle_items' => [
                ['product_id' => $mic->id, 'quantity' => 2],
                ['product_id' => $arm->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $bundle = Product::with('bundleItems')->find($bundleId);
        $this->assertSame(2, $bundle->bundleItems->count());
        $this->assertSame(2, $bundle->bundleItems->firstWhere('component_product_id', $mic->id)->quantity);
    }

    public function test_normal_product_converts_to_bundle_clearing_own_stock(): void
    {
        $mic = $this->makeComponent('마이크', 5);
        $legacy = $this->makeComponent('구형 세트(일반 등록)', 3); // 자체 재고 3 보유

        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$legacy->id}", [
            'name' => $legacy->name, 'category_id' => $this->cat->id, 'is_bundle' => true,
            'bundle_items' => [['product_id' => $mic->id, 'quantity' => 1]],
        ])->assertOk();

        $legacy = $legacy->fresh()->load('bundleItems.component.inventory');
        $this->assertTrue($legacy->is_bundle);
        $this->assertNull($legacy->inventory, '세트 전환 시 자체 inventory 제거');
        $this->assertSame(5, $legacy->buildableQuantity());
        // 자체 재고 3 → 0 정리 이력이 남음
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $legacy->id, 'movement_type' => 'adjust', 'quantity_after' => 0,
        ]);
    }

    public function test_component_of_bundle_cannot_become_bundle(): void
    {
        $mic = $this->makeComponent('마이크', 5);
        $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]])->assertCreated();

        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$mic->id}", [
            'name' => '마이크', 'category_id' => $this->cat->id, 'is_bundle' => true,
            'bundle_items' => [['product_id' => $this->makeComponent('붐암', 1)->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_bundle_converts_back_to_normal_product(): void
    {
        $mic = $this->makeComponent('마이크', 5);
        $bundleId = $this->makeBundle([['product_id' => $mic->id, 'quantity' => 1]])->json('id');

        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$bundleId}", [
            'name' => '일반 전환', 'category_id' => $this->cat->id, 'is_bundle' => false,
        ])->assertOk();

        $product = Product::with('bundleItems', 'inventory')->find($bundleId);
        $this->assertFalse($product->is_bundle);
        $this->assertSame(0, $product->bundleItems->count(), '구성품 정의 삭제');
        $this->assertSame(0, $product->inventory->quantity, '자체 재고 0부터 시작');
    }

    public function test_products_api_exposes_bundle_items_for_buildable_calc(): void
    {
        $mic = $this->makeComponent('마이크', 6);
        $this->makeBundle([['product_id' => $mic->id, 'quantity' => 2]]);

        $res = $this->actingAs($this->master)->getJson('/api/inventory/products?per_page=50');
        $bundle = collect($res->json('data'))->firstWhere('is_bundle', true);
        $this->assertNotNull($bundle);
        $this->assertSame(2, $bundle['bundle_items'][0]['quantity']);
        $this->assertSame(6, $bundle['bundle_items'][0]['component']['inventory']['quantity']);
    }

    public function test_estimate_products_expose_bundle_components_and_print_hides_them(): void
    {
        $mic = $this->makeComponent('세트용 마이크', 5);
        $arm = $this->makeComponent('세트용 붐암', 3);
        $res = $this->makeBundle([
            ['product_id' => $mic->id, 'quantity' => 1],
            ['product_id' => $arm->id, 'quantity' => 2],
        ], '스트리밍 세트');
        Product::find($res->json('id'))->update(['show_in_estimate' => true]);

        // 견적서 제품 API에 구성품 스냅샷 포함
        $rows = $this->actingAs($this->master)->getJson('/api/inventory/estimate-products')->assertOk()->json();
        $set = collect($rows)->firstWhere('name', '스트리밍 세트');
        $this->assertTrue($set['is_bundle']);
        $this->assertSame(['세트용 마이크', '세트용 붐암'], array_column($set['bundle_items'], 'name'));
        $this->assertSame(2, $set['bundle_items'][1]['qty']);

        // 견적서 저장 시 bundle_items 스냅샷 보존 + 출력물에는 세트 한 줄만 (구성품 미노출)
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->master->id,
        ]);
        $this->actingAs($this->master)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [[
                'product_id' => $set['id'], 'name' => '스트리밍 세트', 'category' => '방송장비',
                'sale_price' => 150000, 'qty' => 1, 'subtotal' => 150000,
                'bundle_items' => $set['bundle_items'],
            ]],
        ])->assertOk();

        $saved = $estimate->fresh()->product_items[0];
        $this->assertSame('세트용 마이크', $saved['bundle_items'][0]['name']);

        $this->actingAs($this->master)->get("/estimates/{$estimate->id}/print")
            ->assertOk()->assertSee('스트리밍 세트')->assertDontSee('세트용 마이크');
    }
}
