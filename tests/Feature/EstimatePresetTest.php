<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\EstimatePreset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
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

    public function test_estimate_links_client_project_optionally(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
        Project::create(['client_id' => $client->id, 'name' => '끝난 건', 'project_type' => 'visit', 'stage' => 'completed', 'completed_at' => now()]);

        // 연동 후보 — 진행 중 프로젝트만
        $list = $this->actingAs($this->admin)->getJson("/api/estimate-client-projects/{$client->id}")->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame($project->id, $list[0]['id']);

        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        // 프로젝트 없이 저장 가능(선택 사항), 지정하면 연동 저장
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'client_id' => $client->id, 'project_id' => null,
        ])->assertOk();
        $this->assertNull($estimate->fresh()->project_id);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'client_id' => $client->id, 'project_id' => $project->id,
        ])->assertOk();
        $this->assertSame($project->id, $estimate->fresh()->project_id);
    }

    public function test_print_groups_by_root_category_with_subtotals(): void
    {
        $estimate = Estimate::create([
            'status' => 'created',
            'product_items' => [
                ['category' => 'R 시리즈', 'category_root' => '비디오', 'name' => 'EOS R50 V', 'sale_price' => 1000000, 'qty' => 1, 'subtotal' => 1000000],
                ['category' => '웹캠', 'category_root' => '비디오', 'name' => '인스타360 Link 2', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000],
                ['category' => '믹서', 'category_root' => '오디오', 'name' => 'AG06 MK2', 'sale_price' => 315000, 'qty' => 1, 'subtotal' => 315000],
            ],
            'service_items' => [], 'product_total' => 1615000, 'service_total' => 0,
            'total_amount' => 1615000, 'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()->getContent();
        // 1차 대분류 헤더·소계로 묶임 (하위 카테고리별로 쪼개지지 않음)
        $this->assertStringContainsString('비디오 소계', $html);
        $this->assertStringContainsString('오디오 소계', $html);
        $this->assertStringContainsString('1,300,000', $html); // 비디오 소계 금액
        $this->assertStringNotContainsString('웹캠 소계', $html);
        // 저장된 항목 순서 = 출력 순서 (드래그 정렬 반영) — 비디오가 오디오보다 먼저
        $this->assertLessThan(mb_strpos($html, '오디오 소계'), mb_strpos($html, '비디오 소계'));
    }

    public function test_internal_memo_saved_but_hidden_from_client_estimate(): void
    {
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'memo' => '의뢰자 안내 메모', 'internal_memo' => '내부: 할인 15% 협의됨 - 대외비',
        ])->assertOk();
        $this->assertSame('내부: 할인 15% 협의됨 - 대외비', $estimate->fresh()->internal_memo);

        // 내부 인쇄 미리보기·의뢰자 공개 링크 모두 내부 비고 미노출, 일반 메모는 노출
        $print = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()->getContent();
        $this->assertStringContainsString('의뢰자 안내 메모', $print);
        $this->assertStringNotContainsString('대외비', $print);

        $public = $this->get($estimate->fresh()->publicUrl())->assertOk()->getContent();
        $this->assertStringContainsString('의뢰자 안내 메모', $public);
        $this->assertStringNotContainsString('대외비', $public);
    }

    public function test_estimate_products_expose_root_category(): void
    {
        $root = ProductCategory::create(['name' => '비디오', 'code' => 'VID', 'depth' => 1, 'sort_order' => 1]);
        $child = ProductCategory::create(['parent_id' => $root->id, 'name' => 'R 시리즈', 'code' => 'RSER', 'depth' => 2, 'sort_order' => 1]);
        Product::create([
            'sku' => 'VID-001', 'name' => 'EOS R50 V', 'category' => 'R 시리즈', 'category_id' => $child->id,
            'purchase_price' => 900000, 'sale_price' => 1000000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => true,
        ]);

        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/estimate-products')->assertOk()->json();
        $this->assertSame('R 시리즈', $rows[0]['category']);
        $this->assertSame('비디오', $rows[0]['category_root']);
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
