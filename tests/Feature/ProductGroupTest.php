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

    public function test_grouped_only_filter_returns_only_group_members(): void
    {
        $this->makeGroup();
        Product::create([
            'sku' => 'MIC-001', 'name' => '단독 마이크', 'category' => '오디오',
            'purchase_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'show_in_estimate' => true,
        ]);

        $all = $this->actingAs($this->admin)->getJson('/api/inventory/products?per_page=50')->assertOk()->json('data');
        $this->assertCount(3, $all);

        $grouped = $this->actingAs($this->admin)->getJson('/api/inventory/products?per_page=50&grouped_only=1')->assertOk()->json('data');
        $names = array_column($grouped, 'name');
        $this->assertCount(2, $grouped);
        $this->assertContains('카메라 X100 블랙', $names);
        $this->assertContains('카메라 X100 화이트', $names);
        $this->assertNotContains('단독 마이크', $names);
    }

    public function test_group_update_renames_and_reconfigures_members(): void
    {
        $group = $this->makeGroup();
        $silver = Product::create([
            'sku' => 'CAM-003', 'name' => '카메라 X100 실버', 'category' => '카메라', 'category_id' => $this->black->category_id,
            'purchase_price' => 100000, 'sale_price' => 170000, 'safety_stock' => 1,
            'is_active' => true, 'show_in_estimate' => true,
        ]);

        // 그룹명 변경 + 화이트 제외 + 실버 편입 + 옵션명 변경
        $this->actingAs($this->admin)->patchJson("/api/inventory/product-groups/{$group->id}", [
            'name' => '카메라 X100 (신형)',
            'items' => [
                ['id' => $this->black->id, 'option_name' => '블랙 에디션'],
                ['id' => $silver->id, 'option_name' => '실버'],
            ],
        ])->assertOk();

        $this->assertSame('카메라 X100 (신형)', $group->fresh()->name);
        $this->assertSame('블랙 에디션', $this->black->fresh()->option_name);
        $this->assertSame($group->id, $silver->fresh()->group_id);
        // 제외된 화이트는 그룹에서 해제되고 제품·재고는 유지
        $white = $this->white->fresh();
        $this->assertNull($white->group_id);
        $this->assertNull($white->option_name);
        $this->assertSame(5, $white->inventory->quantity);
    }

    public function test_group_update_rejects_member_of_other_group(): void
    {
        $group = $this->makeGroup();
        $other = Product::create([
            'sku' => 'MIC-001', 'name' => '마이크 블랙', 'category' => '카메라', 'category_id' => $this->black->category_id,
            'purchase_price' => 10000, 'sale_price' => 20000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => true,
        ]);
        $otherGroup = ProductGroup::create(['name' => '마이크']);
        $other->update(['group_id' => $otherGroup->id, 'option_name' => '블랙']);

        $this->actingAs($this->admin)->patchJson("/api/inventory/product-groups/{$group->id}", [
            'name' => '카메라 X100',
            'items' => [
                ['id' => $this->black->id, 'option_name' => '블랙'],
                ['id' => $other->id, 'option_name' => '마이크'],
            ],
        ])->assertUnprocessable();
        $this->assertSame($otherGroup->id, $other->fresh()->group_id);
    }

    public function test_group_creation_rejects_already_grouped_product(): void
    {
        $group = $this->makeGroup();
        $solo = Product::create([
            'sku' => 'MIC-001', 'name' => '단독 마이크', 'category' => '오디오',
            'purchase_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'show_in_estimate' => true,
        ]);

        // 이미 그룹에 속한 블랙을 새 그룹으로 가져오려 하면 거부
        $this->actingAs($this->admin)->postJson('/api/inventory/product-groups', [
            'name' => '새 그룹',
            'items' => [
                ['id' => $this->black->id, 'option_name' => '블랙'],
                ['id' => $solo->id, 'option_name' => '마이크'],
            ],
        ])->assertUnprocessable();

        $this->assertSame(1, ProductGroup::count());
        $this->assertSame($group->id, $this->black->fresh()->group_id);
        $this->assertNull($solo->fresh()->group_id);
    }

    public function test_products_group_id_filter_returns_only_members(): void
    {
        $group = $this->makeGroup();
        Product::create([
            'sku' => 'MIC-001', 'name' => '단독 마이크', 'category' => '오디오',
            'purchase_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'show_in_estimate' => true,
        ]);

        $rows = $this->actingAs($this->admin)
            ->getJson("/api/inventory/products?per_page=200&group_id={$group->id}")
            ->assertOk()->json('data');
        $skus = array_column($rows, 'sku');
        sort($skus);
        $this->assertSame(['CAM-001', 'CAM-002'], $skus);
    }

    public function test_products_sort_by_header_key(): void
    {
        // 판매가 내림차순 — 화이트(160,000)가 블랙(150,000)보다 먼저
        $rows = $this->actingAs($this->admin)
            ->getJson('/api/inventory/products?per_page=50&sort=sale_price&dir=desc')
            ->assertOk()->json('data');
        $prices = array_map(fn ($p) => (int) $p['sale_price'], $rows);
        $sorted = $prices;
        rsort($sorted);
        $this->assertSame($sorted, $prices);

        // 현재고 오름차순 — 블랙(3)이 화이트(5)보다 먼저
        $rows2 = $this->actingAs($this->admin)
            ->getJson('/api/inventory/products?per_page=50&sort=stock&dir=asc')
            ->assertOk()->json('data');
        $this->assertSame('CAM-001', $rows2[0]['sku']);
    }
}
