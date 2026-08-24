<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\EstimatePreset;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적 프리셋 — 품목 구성 저장/관리 + 수기 품목의 견적서 스냅샷 저장 */
class EstimatePresetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function sampleItems(): array
    {
        return [
            ['product_id' => 1, 'sku' => 'CAM-001', 'category' => '카메라', 'name' => '카메라 X100', 'sale_price' => 150000, 'qty' => 2],
            ['product_id' => null, 'category' => '설치', 'name' => '수기 브라켓', 'sale_price' => 30000, 'qty' => 1, 'manual' => true],
        ];
    }

    public function test_preset_crud_and_normalization(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/estimate-presets', [
            'title' => '스튜디오 기본 세팅', 'items' => $this->sampleItems(),
        ])->assertCreated();
        $id = $res->json('id');

        // 목록 — 합계·품목 수 계산 (150000*2 + 30000)
        $list = $this->actingAs($this->admin)->getJson('/api/estimate-presets')->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame('스튜디오 기본 세팅', $list[0]['title']);
        $this->assertSame(2, $list[0]['item_count']);
        $this->assertSame(330000, $list[0]['total']);
        // 정규화 — subtotal 채워짐, 수기 플래그 유지
        $this->assertSame(300000, $list[0]['items'][0]['subtotal']);
        $this->assertTrue($list[0]['items'][1]['manual']);

        // 수정
        $this->actingAs($this->admin)->patchJson("/api/estimate-presets/{$id}", [
            'title' => '기본 세팅 v2',
            'items' => [['name' => '단일 수기 품목', 'sale_price' => 10000, 'qty' => 3, 'manual' => true]],
        ])->assertOk();
        $fresh = EstimatePreset::find($id);
        $this->assertSame('기본 세팅 v2', $fresh->title);
        $this->assertSame(30000, $fresh->items[0]['subtotal']);

        // 삭제
        $this->actingAs($this->admin)->deleteJson("/api/estimate-presets/{$id}")->assertOk();
        $this->assertSame(0, EstimatePreset::count());
    }

    public function test_preset_write_requires_estimates_edit_permission(): void
    {
        $team = Team::create(['name' => '견적조회팀', 'slug' => 'est-view', 'permissions' => ['estimates.view']]);
        $viewer = User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);

        // 조회는 가능 (불러오기용)
        $this->actingAs($viewer)->getJson('/api/estimate-presets')->assertOk();
        // 생성은 편집 권한 필요
        $this->actingAs($viewer)->postJson('/api/estimate-presets', [
            'title' => 'X', 'items' => [['name' => 'a', 'sale_price' => 1000]],
        ])->assertForbidden();
    }

    public function test_estimate_saves_manual_items_as_snapshot(): void
    {
        // 수기 품목(product_id 없음)도 견적서 product_items에 작성 시점 가격으로 저장
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [
                ['product_id' => null, 'sku' => '', 'category' => '설치', 'name' => '특수 브라켓',
                    'purchase_price' => 0, 'sale_price' => 45000, 'qty' => 2, 'subtotal' => 90000, 'manual' => true],
            ],
            'service_items' => [],
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('특수 브라켓', $fresh->product_items[0]['name']);
        $this->assertSame(45000, $fresh->product_items[0]['sale_price']); // 작성 시점 가격 보존
        $this->assertTrue($fresh->product_items[0]['manual']);
        $this->assertSame(90000, (int) $fresh->total_amount);
        $this->assertSame(0, Product::count()); // 제품 관리에는 추가되지 않음
    }
}
