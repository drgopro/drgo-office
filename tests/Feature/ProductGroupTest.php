<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 제품 옵션 그룹 — 기존 제품(ID 유지)들을 블랙/화이트 옵션으로 묶어 견적서에서 구분 추가 */
class ProductGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $black;

    private Product $white;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $cat = ProductCategory::create(['name' => '카메라', 'code' => 'CAM', 'depth' => 1, 'sort_order' => 1]);
        $this->black = Product::create([
            'sku' => 'CAM-001', 'name' => '카메라 X100 블랙', 'category' => '카메라', 'category_id' => $cat->id,
            'purchase_price' => 100000, 'sale_price' => 150000, 'safety_stock' => 1,
            'is_active' => true, 'show_in_estimate' => true,
        ]);
        $this->white = Product::create([
            'sku' => 'CAM-002', 'name' => '카메라 X100 화이트', 'category' => '카메라', 'category_id' => $cat->id,
            'purchase_price' => 100000, 'sale_price' => 160000, 'safety_stock' => 1,
            'is_active' => true, 'show_in_estimate' => true,
        ]);
        Inventory::create(['product_id' => $this->black->id, 'quantity' => 3, 'last_updated_at' => now()]);
        Inventory::create(['product_id' => $this->white->id, 'quantity' => 5, 'last_updated_at' => now()]);
    }

    private function makeGroup(): ProductGroup
    {
        $this->actingAs($this->admin)->postJson('/api/inventory/product-groups', [
            'name' => '카메라 X100',
            'items' => [
                ['id' => $this->black->id, 'option_name' => '블랙'],
                ['id' => $this->white->id, 'option_name' => '화이트'],
            ],
        ])->assertCreated();

        return ProductGroup::firstOrFail();
    }

    public function test_group_creation_keeps_product_ids_and_stock(): void
    {
        $group = $this->makeGroup();

        $black = $this->black->fresh();
        $this->assertSame($group->id, $black->group_id);
        $this->assertSame('블랙', $black->option_name);
        $this->assertSame(3, $black->inventory->quantity); // 재고/ID 그대로

        // 제품 목록·견적서 제품 API에 그룹 정보 포함
        $rows = collect($this->actingAs($this->admin)->getJson('/api/inventory/estimate-products')->assertOk()->json());
        $row = $rows->firstWhere('id', $this->white->id);
        $this->assertSame($group->id, $row['group_id']);
        $this->assertSame('카메라 X100', $row['group_name']);
        $this->assertSame('화이트', $row['option_name']);
    }

    public function test_ungroup_releases_children_without_touching_products(): void
    {
        $group = $this->makeGroup();

        $this->actingAs($this->admin)->deleteJson("/api/inventory/product-groups/{$group->id}")->assertOk();

        $this->assertSame(0, ProductGroup::count());
        $black = $this->black->fresh();
        $this->assertNull($black->group_id);
        $this->assertNull($black->option_name);
        $this->assertSame('카메라 X100 블랙', $black->name);
        $this->assertSame(3, $black->inventory->quantity);
    }

    public function test_single_product_can_leave_group_via_update(): void
    {
        $group = $this->makeGroup();

        $this->actingAs($this->admin)->patchJson("/api/inventory/products/{$this->white->id}", [
            'name' => $this->white->name, 'category_id' => $this->white->category_id, 'group_id' => null,
        ])->assertOk();

        $white = $this->white->fresh();
        $this->assertNull($white->group_id);
        $this->assertNull($white->option_name);
        $this->assertSame($group->id, $this->black->fresh()->group_id); // 나머지는 유지
    }

    public function test_group_endpoints_require_inventory_edit_permission(): void
    {
        $team = Team::create(['name' => '조회팀', 'slug' => 'view-only', 'permissions' => ['inventory.view']]);
        $viewer = User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);

        $this->actingAs($viewer)->postJson('/api/inventory/product-groups', [
            'name' => 'X', 'items' => [['id' => $this->black->id, 'option_name' => '블랙']],
        ])->assertForbidden();
    }
}
