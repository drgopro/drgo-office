<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\OfficeOrder;
use App\Models\Product;
use App\Models\ScheduleShipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 재고 관리 > 주문 내역 — 견적서 주문완료 건 자동 파생 + 직접 주문 CRUD
 * + 견적서 항목별 구매처/메모 기록 + 운송장 노출.
 */
class OfficeOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function makeOrderedEstimate(): Estimate
    {
        return Estimate::create([
            'status' => 'created', 'title' => '스튜디오 구축', 'client_nickname' => '고블린',
            'product_items' => [
                ['product_id' => 1, 'name' => '카메라', 'sale_price' => 100000, 'qty' => 2, 'subtotal' => 200000, 'ordered' => true],
                ['product_id' => 2, 'name' => '마이크', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000], // 주문 전 — 리스트 미포함
            ],
            'service_items' => [], 'product_total' => 250000, 'service_total' => 0, 'total_amount' => 250000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_estimate_with_ordered_item_appears_with_shipments(): void
    {
        $estimate = $this->makeOrderedEstimate();
        ScheduleShipment::create([
            'estimate_id' => $estimate->id, 'carrier' => 'kr.cjlogistics', 'tracking_no' => '123456789',
            'status' => 'in_transit', 'last_event' => '간선 이동 중',
        ]);
        // 주문완료 항목이 없는 견적서는 리스트에 나오지 않는다
        Estimate::create([
            'status' => 'created', 'product_items' => [['name' => '모니터', 'sale_price' => 1, 'qty' => 1, 'subtotal' => 1]],
            'service_items' => [], 'product_total' => 1, 'service_total' => 0, 'total_amount' => 1,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('estimate', $row['type']);
        $this->assertSame('스튜디오 구축', $row['title']);
        $this->assertSame('고블린', $row['client']);
        // 주문완료 항목만, 원본 인덱스 유지
        $this->assertCount(1, $row['items']);
        $this->assertSame('카메라', $row['items'][0]['name']);
        $this->assertSame(0, $row['items'][0]['index']);
        // 운송장 포함
        $this->assertCount(1, $row['shipments']);
        $this->assertSame('123456789', $row['shipments'][0]['tracking_no']);
        // 제품 관리의 메모(판매처 등)가 항목에 붙는다
        Product::create(['sku' => 'CAM-9', 'name' => '카메라', 'category' => '비디오',
            'purchase_price' => 1, 'sale_price' => 2, 'memo' => '판매처: 컴퓨존', 'is_active' => true, 'show_in_estimate' => true]);
        $items = $estimate->product_items;
        $items[0]['product_id'] = Product::where('sku', 'CAM-9')->value('id');
        $estimate->forceFill(['product_items' => $items])->save();
        $row2 = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->json('0');
        $this->assertSame('판매처: 컴퓨존', $row2['items'][0]['product_memo']);
        $this->assertSame('in_transit', $row['shipments'][0]['status']);
        $this->assertStringContainsString('123456789', $row['shipments'][0]['tracking_url']);
    }

    public function test_manual_order_crud_and_grouping(): void
    {
        $created = $this->actingAs($this->admin)->postJson('/api/inventory/office-orders', [
            'title' => '8월 사무실 간식',
            'items' => [
                ['name' => '커피 캡슐', 'qty' => 3, 'amount' => 45000, 'purchase_source' => '쿠팡', 'memo' => '연한 맛'],
                ['name' => '탄산수', 'qty' => 2],
            ],
        ])->assertCreated()->json();

        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json();
        $this->assertCount(1, $rows);
        $this->assertSame('manual', $rows[0]['type']);
        $this->assertSame('8월 사무실 간식', $rows[0]['title']);
        $this->assertCount(2, $rows[0]['items']);
        $this->assertSame('쿠팡', $rows[0]['items'][0]['purchase_source']);
        // 구매 금액 — 입력값 보존, 미입력은 null
        $this->assertSame(45000, $rows[0]['items'][0]['amount']);
        $this->assertNull($rows[0]['items'][1]['amount']);
        // 주문일 미지정 시 오늘로 기본 저장
        $this->assertSame(now()->toDateString(), $rows[0]['order_date']);

        // 주문일 지정 저장
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/{$created['id']}", [
            'title' => '8월 사무실 간식', 'order_date' => '2026-08-20',
            'items' => [['name' => '커피 캡슐', 'qty' => 3]],
        ])->assertOk();
        $this->assertSame('2026-08-20', OfficeOrder::findOrFail($created['id'])->order_date->toDateString());

        // 수정
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/{$created['id']}", [
            'title' => '8월 간식 (수정)', 'items' => [['name' => '커피 캡슐', 'qty' => 5]],
        ])->assertOk();
        $fresh = OfficeOrder::findOrFail($created['id']);
        $this->assertSame('8월 간식 (수정)', $fresh->title);
        $this->assertSame(5, $fresh->items[0]['qty']);

        // 항목 없는 저장은 422
        $this->actingAs($this->admin)->postJson('/api/inventory/office-orders', ['title' => 'X', 'items' => []])
            ->assertStatus(422);

        // 삭제
        $this->actingAs($this->admin)->deleteJson("/api/inventory/office-orders/{$created['id']}")->assertOk();
        $this->assertNull(OfficeOrder::find($created['id']));
    }

    public function test_estimate_item_note_saves_into_snapshot_and_survives_builder_save(): void
    {
        $estimate = $this->makeOrderedEstimate();

        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$estimate->id}/item-note", [
            'index' => 0, 'amount' => 178000, 'purchase_source' => '컴퓨존', 'memo' => '8/26 발주 완료',
        ])->assertOk();

        $item = $estimate->fresh()->product_items[0];
        $this->assertSame('컴퓨존', $item['purchase_source']);
        $this->assertSame('8/26 발주 완료', $item['order_memo']);
        $this->assertSame(178000, $item['purchase_amount']);

        // 리스트에 금액과 참고치(매입가×수량) 노출
        $listed = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json()[0];
        $this->assertSame(178000, $listed['items'][0]['amount']);
        $this->assertArrayHasKey('default_amount', $listed['items'][0]);

        // 빌더 저장(PATCH)이 스냅샷 필드를 유실하지 않는다 (검증 규칙 포함 확인)
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $estimate->fresh()->product_items,
        ])->assertOk();
        $item2 = $estimate->fresh()->product_items[0];
        $this->assertSame('컴퓨존', $item2['purchase_source']);
        $this->assertSame('8/26 발주 완료', $item2['order_memo']);

        // 범위 밖 인덱스는 422
        $this->actingAs($this->admin)->patchJson("/api/inventory/office-orders/estimate/{$estimate->id}/item-note", [
            'index' => 99, 'purchase_source' => 'X',
        ])->assertStatus(422);
    }

    public function test_ordered_at_recorded_on_save_and_shown_in_order_list(): void
    {
        // 주문완료 체크로 저장 → ordered_at 기록 (주문완료 처리 시각)
        $estimate = $this->makeOrderedEstimate();
        $items = $estimate->product_items;
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $items, 'service_items' => [],
        ])->assertOk();
        $saved = $estimate->fresh()->product_items;
        $this->assertNotEmpty($saved[0]['ordered_at']);
        $this->assertArrayNotHasKey('ordered_at', $saved[1]); // 주문 전 항목은 없음
        $firstStamp = $saved[0]['ordered_at'];

        // 재저장해도 최초 처리 시각 유지 (멱등)
        $this->travel(5)->minutes();
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $saved, 'service_items' => [],
        ])->assertOk();
        $this->assertSame($firstStamp, $estimate->fresh()->product_items[0]['ordered_at']);

        // 주문 내역 리스트 — 견적 수정일 대신 쓸 ordered_at 노출
        $row = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()
            ->json()[0];
        $this->assertSame($firstStamp, $row['ordered_at']);

        // 주문완료 해제 → 기록 제거
        $saved[0]['ordered'] = false;
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $saved, 'service_items' => [],
        ])->assertOk();
        $this->assertArrayNotHasKey('ordered_at', $estimate->fresh()->product_items[0]);
    }

    public function test_inventory_page_renders_bulk_save_for_order_card(): void
    {
        // 카드(주문 1건) 단위 일괄 저장 — 버튼과 순차 저장 함수, 공용 본문 빌더 렌더 확인
        $this->actingAs($this->admin)->get('/inventory')->assertOk()
            ->assertSee('saveAllOrderNotes(${o.id}, this)', false)
            ->assertSee('async function saveAllOrderNotes', false)
            ->assertSee('buildItemNoteBody', false)
            ->assertSee('buildBundleNoteBody', false);
    }

    public function test_order_page_requires_inventory_permission(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->getJson('/api/inventory/office-orders')->assertForbidden();
        $this->actingAs($guest)->get('/inventory/orders/new')->assertForbidden();

        $this->actingAs($this->admin)->get('/inventory/orders/new')->assertOk()->assertSee('주문 추가');
        $order = OfficeOrder::create(['title' => 'T', 'items' => [['name' => 'A', 'qty' => 1]], 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin)->get("/inventory/orders/{$order->id}/edit")->assertOk()->assertSee('주문 수정');
    }
}
