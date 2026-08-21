<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 제품 등록/수정 모달에서 재고 직접 수정 — 조정(adjust) 이력 자동 기록 */
class ProductStockEditTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private ProductCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->create(['role' => 'master']);
        $this->cat = ProductCategory::create(['name' => '부품', 'code' => 'PT', 'depth' => 1, 'sort_order' => 1]);
    }

    public function test_store_with_initial_stock_records_in_movement(): void
    {
        $res = $this->actingAs($this->master)->postJson('/api/inventory/products', [
            'name' => '케이블', 'category_id' => $this->cat->id, 'stock_quantity' => 7,
        ]);

        $res->assertCreated();
        $product = Product::find($res->json('id'));
        $this->assertSame(7, $product->inventory->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'movement_type' => 'in', 'quantity' => 7, 'quantity_after' => 7,
        ]);
    }

    public function test_update_stock_records_adjust_movement(): void
    {
        $product = Product::create([
            'sku' => 'PT-001', 'name' => '케이블', 'category' => '부품', 'category_id' => $this->cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $product->id, 'quantity' => 10, 'last_updated_at' => now()]);

        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$product->id}", [
            'name' => '케이블', 'category_id' => $this->cat->id, 'stock_quantity' => 4,
        ])->assertOk();

        $this->assertSame(4, $product->inventory->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'movement_type' => 'adjust', 'quantity' => 6, 'quantity_after' => 4,
        ]);

        // 같은 수량으로 저장하면 이력 없음 / null(미입력)이면 변경 없음
        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$product->id}", [
            'name' => '케이블', 'category_id' => $this->cat->id, 'stock_quantity' => 4,
        ])->assertOk();
        $this->actingAs($this->master)->patchJson("/api/inventory/products/{$product->id}", [
            'name' => '케이블', 'category_id' => $this->cat->id,
        ])->assertOk();
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
        $this->assertSame(4, $product->inventory->fresh()->quantity);
    }

    public function test_movements_can_be_searched_by_product_name_or_sku(): void
    {
        foreach ([['PT-101', '케이블'], ['PT-102', '마이크']] as [$sku, $name]) {
            $p = Product::create([
                'sku' => $sku, 'name' => $name, 'category' => '부품', 'category_id' => $this->cat->id,
                'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
                'is_active' => true, 'show_in_estimate' => false,
            ]);
            Inventory::create(['product_id' => $p->id, 'quantity' => 0, 'last_updated_at' => now()]);
            $this->actingAs($this->master)->postJson('/api/inventory/movements', [
                'product_id' => $p->id, 'movement_type' => 'in', 'quantity' => 3,
            ])->assertCreated();
        }

        $byName = $this->actingAs($this->master)->getJson('/api/inventory/movements?search='.urlencode('케이블'));
        $byName->assertOk();
        $this->assertCount(1, $byName->json());
        $this->assertSame('케이블', $byName->json('0.product.name'));

        $bySku = $this->actingAs($this->master)->getJson('/api/inventory/movements?search=PT-102');
        $bySku->assertOk();
        $this->assertCount(1, $bySku->json());
    }

    public function test_movements_can_be_deleted_by_admin_only(): void
    {
        $p = Product::create([
            'sku' => 'PT-201', 'name' => '허브', 'category' => '부품', 'category_id' => $this->cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $p->id, 'quantity' => 0, 'last_updated_at' => now()]);
        foreach ([1, 2, 3] as $qty) {
            $this->actingAs($this->master)->postJson('/api/inventory/movements', [
                'product_id' => $p->id, 'movement_type' => 'in', 'quantity' => $qty,
            ])->assertCreated();
        }
        $ids = StockMovement::pluck('id');

        // 선택 삭제 — 남은 이력(입고 2 + 입고 3)으로 재고 재계산
        $this->actingAs($this->master)->deleteJson('/api/inventory/movements', ['ids' => [$ids[0]]])
            ->assertOk()->assertJsonPath('deleted', 1);
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(5, $p->inventory->fresh()->quantity);

        // 편집 권한이 있어도 member는 삭제 불가 (관리자 이상)
        $team = Team::create(['name' => '재고팀', 'slug' => 'stock-team', 'permissions' => ['inventory.view', 'inventory.edit']]);
        $member = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
        $this->actingAs($member)->deleteJson('/api/inventory/movements', ['ids' => [$ids[1]]])
            ->assertForbidden();

        // 전체 비우기 — 이력이 없어지므로 재고 0으로 리셋
        $this->actingAs($this->master)->deleteJson('/api/inventory/movements', ['all' => true])
            ->assertOk();
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, $p->inventory->fresh()->quantity);
    }

    public function test_adjust_in_history_is_replayed_as_absolute_value(): void
    {
        $p = Product::create([
            'sku' => 'PT-301', 'name' => '모니터암', 'category' => '부품', 'category_id' => $this->cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $p->id, 'quantity' => 0, 'last_updated_at' => now()]);

        // 입고 10 → 조정 4 → 출고 1 (= 3)
        foreach ([['in', 10], ['adjust', 4], ['out', 1]] as [$type, $qty]) {
            $this->actingAs($this->master)->postJson('/api/inventory/movements', [
                'product_id' => $p->id, 'movement_type' => $type, 'quantity' => $qty,
            ])->assertCreated();
        }
        $this->assertSame(3, $p->inventory->fresh()->quantity);

        // 입고 10 삭제 → 남은 이력 재생: 조정 4 → 출고 1 = 3 (조정이 절대값이라 그대로)
        $inId = StockMovement::where('movement_type', 'in')->first()->id;
        $this->actingAs($this->master)->deleteJson('/api/inventory/movements', ['ids' => [$inId]])->assertOk();
        $this->assertSame(3, $p->inventory->fresh()->quantity);

        // 조정 4 삭제 → 남은 이력: 출고 1 = -1
        $adjId = StockMovement::where('movement_type', 'adjust')->first()->id;
        $this->actingAs($this->master)->deleteJson('/api/inventory/movements', ['ids' => [$adjId]])->assertOk();
        $this->assertSame(-1, $p->inventory->fresh()->quantity);
    }

    public function test_bundle_ignores_stock_quantity(): void
    {
        $component = Product::create([
            'sku' => 'PT-002', 'name' => '마이크', 'category' => '부품', 'category_id' => $this->cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $component->id, 'quantity' => 3, 'last_updated_at' => now()]);

        $res = $this->actingAs($this->master)->postJson('/api/inventory/products', [
            'name' => '세트', 'category_id' => $this->cat->id, 'is_bundle' => true,
            'stock_quantity' => 99,
            'bundle_items' => [['product_id' => $component->id, 'quantity' => 1]],
        ]);

        $res->assertCreated();
        $this->assertNull(Product::find($res->json('id'))->inventory, '세트는 재고 입력을 무시');
    }

    // === 전체 편집 (일괄 저장) ===

    private function makeSimpleProduct(string $name, int $qty = 0): Product
    {
        $product = Product::create([
            'sku' => 'PT-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => $name, 'category' => '부품', 'category_id' => $this->cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $product->id, 'quantity' => $qty, 'last_updated_at' => now()]);

        return $product;
    }

    public function test_bulk_edit_updates_fields_and_records_stock_adjust(): void
    {
        $a = $this->makeSimpleProduct('케이블A', 5);
        $b = $this->makeSimpleProduct('케이블B', 3);

        $this->actingAs($this->master)->patchJson('/api/inventory/products/bulk-edit', [
            'items' => [
                ['id' => $a->id, 'name' => '케이블A-개명', 'purchase_price' => 1500, 'sale_price' => 2500, 'stock_quantity' => 9],
                ['id' => $b->id, 'safety_stock' => 4],
            ],
        ])->assertOk()->assertJsonPath('count', 2);

        $a->refresh();
        $this->assertSame('케이블A-개명', $a->name);
        $this->assertSame(1500, (int) $a->purchase_price);
        $this->assertSame(2500, (int) $a->sale_price);
        $this->assertSame(9, $a->inventory->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $a->id, 'movement_type' => 'adjust', 'quantity' => 4, 'quantity_after' => 9,
        ]);
        // 재고를 안 바꾼 제품은 조정 이력 없음
        $this->assertSame(4, (int) $b->fresh()->safety_stock);
        $this->assertSame(0, StockMovement::where('product_id', $b->id)->count());
    }

    public function test_bulk_edit_rejects_empty_name_and_requires_permission(): void
    {
        $a = $this->makeSimpleProduct('케이블C');

        $this->actingAs($this->master)->patchJson('/api/inventory/products/bulk-edit', [
            'items' => [['id' => $a->id, 'name' => '']],
        ])->assertStatus(422);

        $noPerm = User::factory()->create(['role' => 'member']);
        $this->actingAs($noPerm)->patchJson('/api/inventory/products/bulk-edit', [
            'items' => [['id' => $a->id, 'name' => 'x']],
        ])->assertForbidden();
    }
}
