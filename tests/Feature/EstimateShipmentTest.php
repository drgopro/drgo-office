<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 견적서 주문/배송 — 운송장 등록·추적 + 주문완료 표시 + 제품 소요시간 사용 설정 */
class EstimateShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    private function deliveredResponse(): array
    {
        return [
            'data' => [
                'track' => [
                    'lastEvent' => [
                        'time' => '2026-08-24T15:00:00+09:00',
                        'description' => '배송완료',
                        'status' => ['code' => 'DELIVERED', 'name' => '배송완료'],
                        'location' => ['name' => '동작2'],
                    ],
                ],
            ],
        ];
    }

    public function test_store_list_and_delete_estimate_shipment(): void
    {
        config(['services.delivery_tracker.url' => 'http://tracker.test']);
        Http::fake(['tracker.test*' => Http::response($this->deliveredResponse())]);

        // 등록 — 즉시 1회 추적, 하이픈 제거
        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/shipments", [
            'carrier' => 'kr.cjlogistics', 'tracking_no' => '1234-5678-9012',
        ])->assertCreated()->json();
        $this->assertCount(1, $res['shipments']);
        $this->assertSame('123456789012', $res['shipments'][0]['tracking_no']);
        $this->assertSame('delivered', $res['shipments'][0]['status']);
        $this->assertArrayHasKey('kr.cjlogistics', $res['carriers']);

        // 중복 등록 거부
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/shipments", [
            'carrier' => 'kr.cjlogistics', 'tracking_no' => '123456789012',
        ])->assertStatus(422);

        // 목록 조회
        $list = $this->actingAs($this->admin)->getJson("/api/estimates/{$this->estimate->id}/shipments")->assertOk()->json();
        $this->assertCount(1, $list['shipments']);

        // 삭제
        $id = $list['shipments'][0]['id'];
        $after = $this->actingAs($this->admin)->deleteJson("/api/estimate-shipments/{$id}")->assertOk()->json();
        $this->assertCount(0, $after['shipments']);
    }

    public function test_estimate_shipment_requires_edit_permission_and_rejects_schedule_shipment(): void
    {
        $team = Team::create(['name' => '견적조회팀', 'slug' => 'est-view-ship', 'permissions' => ['estimates.view']]);
        $viewer = User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);
        $this->actingAs($viewer)->postJson("/api/estimates/{$this->estimate->id}/shipments", [
            'carrier' => 'kr.cjlogistics', 'tracking_no' => '111122223333',
        ])->assertForbidden();

        // 일정 송장은 견적 삭제 엔드포인트로 지울 수 없다
        $schedule = Schedule::create(['title' => '일정', 'start_date' => '2026-08-24', 'end_date' => '2026-08-24', 'color' => 'gold', 'created_by' => $this->admin->id]);
        $scheduleShipment = $schedule->shipments()->create(['carrier' => 'kr.cjlogistics', 'tracking_no' => '999900001111', 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin)->deleteJson("/api/estimate-shipments/{$scheduleShipment->id}")->assertNotFound();
    }

    public function test_ordered_flag_persists_on_items(): void
    {
        // 주문/배송 뷰의 주문완료 표시가 저장·유지된다
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => [
                ['name' => '카메라', 'category' => '비디오', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000, 'ordered' => true, 'use_time' => false],
                ['name' => '마이크', 'category' => '오디오', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000, 'ordered' => false, 'use_time' => true, 'time_required' => '1시간'],
            ],
        ])->assertOk();

        $items = $this->estimate->fresh()->product_items;
        $this->assertTrue($items[0]['ordered']);
        $this->assertFalse($items[1]['ordered']);
        $this->assertTrue($items[1]['use_time']);
        $this->assertSame('1시간', $items[1]['time_required']);
    }

    public function test_product_time_required_settings_flow_to_estimate_api(): void
    {
        $cat = ProductCategory::create(['name' => '설치', 'code' => 'SVC', 'depth' => 1, 'sort_order' => 1]);

        // 제품 저장 시 소요시간 사용 여부·기본값 저장
        $this->actingAs($this->admin)->postJson('/api/inventory/products', [
            'name' => '스튜디오 설치', 'category_id' => $cat->id, 'sale_price' => 100000,
            'time_required' => '2시간', 'use_time_required' => true, 'show_in_estimate' => true,
        ])->assertStatus(201);

        $p = Product::where('name', '스튜디오 설치')->first();
        $this->assertTrue($p->use_time_required);
        $this->assertSame('2시간', $p->time_required);

        // 견적서 제품 API에 노출 — 빌더가 입력폼 표시 여부를 결정
        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/estimate-products')->assertOk()->json();
        $this->assertTrue($rows[0]['use_time_required']);
        $this->assertSame('2시간', $rows[0]['time_required']);
    }
}
