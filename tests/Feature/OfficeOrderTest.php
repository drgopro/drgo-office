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

    public function test_paid_estimate_auto_appears_unordered_with_all_items(): void
    {
        // 결제완료되면 주문 버튼 없이도 자동 등재 — 전 항목 노출, 미주문 표시, 결제완료일로 그룹
        $estimate = Estimate::create([
            'status' => 'created', 'title' => '캠 세팅', 'client_nickname' => '고블린',
            'product_items' => [
                ['name' => '카메라', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000],
                ['name' => '조명', 'sale_price' => 50000, 'qty' => 2, 'subtotal' => 100000],
            ],
            'service_items' => [], 'product_total' => 200000, 'service_total' => 0, 'total_amount' => 200000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $estimate->update(['status' => 'paid']); // 모델 훅이 paid_at 기록

        $this->assertNotNull($estimate->fresh()->paid_at);

        $row = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json('0');
        $this->assertSame('estimate', $row['type']);
        $this->assertTrue($row['unordered']);
        $this->assertCount(2, $row['items']); // 미주문 항목도 전부 노출
        $this->assertFalse($row['items'][0]['ordered']);
        $this->assertSame(now()->format('Y-m-d'), $row['group_date']); // 결제완료일 기준 그룹
        $this->assertNotNull($row['paid_at']);
    }

    public function test_item_note_endpoint_toggles_ordered_with_timestamp(): void
    {
        // 수기 항목(product_id 없음) — 직접발송 재고 연동 없이 주문 토글만 검증
        $estimate = Estimate::create([
            'status' => 'created', 'title' => '캠 세팅', 'client_nickname' => '고블린',
            'product_items' => [
                ['name' => '카메라', 'sale_price' => 100000, 'qty' => 2, 'subtotal' => 200000, 'ordered' => true],
                ['name' => '마이크', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000],
            ],
            'service_items' => [], 'product_total' => 250000, 'service_total' => 0, 'total_amount' => 250000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $estimate->update(['status' => 'paid']);

        // 미주문 항목(index 1) 주문완료 — 시각 기록, 기존 기입값(구매처 등) 유지
        $items = $estimate->fresh()->product_items;
        $items[1]['purchase_source'] = '컴퓨존';
        $estimate->forceFill(['product_items' => $items])->save();

        $this->actingAs($this->admin)
            ->patchJson("/api/inventory/office-orders/estimate/{$estimate->id}/item-note", ['index' => 1, 'ordered' => 1])
            ->assertOk();
        $fresh = $estimate->fresh()->product_items;
        $this->assertTrue((bool) $fresh[1]['ordered']);
        $this->assertNotEmpty($fresh[1]['ordered_at']);
        $this->assertSame('컴퓨존', $fresh[1]['purchase_source']); // 버튼만 눌러도 기입값 보존

        // 직접발송 — ordered + 구매처 '사무실 발송'
        $this->actingAs($this->admin)
            ->patchJson("/api/inventory/office-orders/estimate/{$estimate->id}/item-note", ['index' => 0, 'ordered' => 1, 'purchase_source' => '사무실 발송'])
            ->assertOk();
        $fresh = $estimate->fresh()->product_items;
        $this->assertSame('사무실 발송', $fresh[0]['purchase_source']);

        // 해제 — ordered/ordered_at 제거
        $this->actingAs($this->admin)
            ->patchJson("/api/inventory/office-orders/estimate/{$estimate->id}/item-note", ['index' => 1, 'ordered' => 0])
            ->assertOk();
        $fresh = $estimate->fresh()->product_items;
        $this->assertArrayNotHasKey('ordered', $fresh[1]);
        $this->assertArrayNotHasKey('ordered_at', $fresh[1]);
    }

    public function test_paid_at_cleared_when_payment_reverted(): void
    {
        $estimate = $this->makeOrderedEstimate();
        $estimate->update(['status' => 'paid']);
        $this->assertNotNull($estimate->fresh()->paid_at);

        $estimate->update(['status' => 'completed']); // 결제완료 해제 → 기록 제거
        $this->assertNull($estimate->fresh()->paid_at);

        $estimate->update(['status' => 'paid']);
        $estimate->update(['status' => 'cancelled']); // 결제취소는 기록 보존
        $this->assertNotNull($estimate->fresh()->paid_at);
    }

    public function test_order_page_renders_date_groups_and_order_buttons(): void
    {
        $this->actingAs($this->admin)->get('/inventory')->assertOk()
            ->assertSee('ordDateLabel', false)      // 날짜 그룹 헤더
            ->assertSee('markItemOrdered', false)   // 항목 주문완료/직접발송 버튼
            ->assertSee('markBundleOrdered', false) // 구성품 단위 버튼
            ->assertSee('미주문', false);
    }

    public function test_service_items_excluded_from_order_list(): void
    {
        // 서비스 항목(세팅비 등)은 실물 주문이 없음 — 주문내역에는 장비만, 서비스는 주문완료 취급
        $mixed = Estimate::create([
            'status' => 'created', 'title' => '세팅 + 장비', 'client_nickname' => '고블린',
            'product_items' => [
                ['name' => '기본 세팅비', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000, 'is_service' => true],
                ['name' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000, 'is_service' => false],
            ],
            'service_items' => [], 'product_total' => 400000, 'service_total' => 0, 'total_amount' => 400000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $mixed->update(['status' => 'paid']);
        // 서비스만으로 구성된 결제완료 건 — 주문할 것이 없으므로 미표시
        $serviceOnly = Estimate::create([
            'status' => 'created', 'title' => '원격 세팅만', 'client_nickname' => '홍길동',
            'product_items' => [['name' => '원격 세팅비', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000, 'is_service' => true]],
            'service_items' => [], 'product_total' => 50000, 'service_total' => 0, 'total_amount' => 50000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $serviceOnly->update(['status' => 'paid']);

        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json();

        $this->assertCount(1, $rows); // 서비스만인 견적서는 제외
        $this->assertSame($mixed->id, $rows[0]['id']);
        $this->assertCount(1, $rows[0]['items']); // 장비(카메라)만
        $this->assertSame('카메라', $rows[0]['items'][0]['name']);
        $this->assertSame(1, $rows[0]['items'][0]['index']); // 원본 인덱스 보존 (item-note 저장용)
        $this->assertTrue($rows[0]['unordered']); // 서비스는 주문완료 취급 — 장비 기준 미주문
    }

    public function test_existing_items_reclassified_by_current_product_classification(): void
    {
        // 기존에 넘어온 건 — 스냅샷이 is_service:false여도 제품관리의 현재 분류가 서비스면 제외
        $svc = Product::create(['sku' => 'SVC-2', 'name' => '야외방송 세팅', 'category' => '서비스',
            'purchase_price' => 0, 'sale_price' => 200000, 'service_kind' => 'service', 'is_active' => true, 'show_in_estimate' => true]);
        $linked = Estimate::create([
            'status' => 'created', 'title' => '연결 건', 'client_nickname' => '길동92',
            'product_items' => [
                ['product_id' => $svc->id, 'name' => '야외방송 세팅', 'sale_price' => 200000, 'qty' => 1, 'subtotal' => 200000, 'is_service' => false],
                ['name' => '삼각대', 'sale_price' => 30000, 'qty' => 1, 'subtotal' => 30000],
            ],
            'service_items' => [], 'product_total' => 230000, 'service_total' => 0, 'total_amount' => 230000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $linked->update(['status' => 'paid']);
        // 제품 미연결 수기 항목 — 같은 이름의 서비스 제품이 있으면 서비스로 판정
        $manual = Estimate::create([
            'status' => 'created', 'title' => '수기 건', 'client_nickname' => '길동93',
            'product_items' => [['name' => '야외방송 세팅', 'sale_price' => 200000, 'qty' => 1, 'subtotal' => 200000]],
            'service_items' => [], 'product_total' => 200000, 'service_total' => 0, 'total_amount' => 200000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $manual->update(['status' => 'paid']);

        $rows = collect($this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json());

        $linkedRow = $rows->firstWhere('id', $linked->id);
        $this->assertCount(1, $linkedRow['items']); // 세팅은 제외, 삼각대만
        $this->assertSame('삼각대', $linkedRow['items'][0]['name']);
        $this->assertNull($rows->firstWhere('id', $manual->id)); // 서비스만 남는 건은 미표시
    }

    public function test_legacy_item_without_snapshot_uses_product_service_kind(): void
    {
        // 구버전 스냅샷(is_service 키 없음) — 제품의 서비스 분류(service_kind)로 판정
        $svc = Product::create(['sku' => 'SVC-1', 'name' => '방문 세팅비', 'category' => '서비스',
            'purchase_price' => 0, 'sale_price' => 100000, 'service_kind' => 'service', 'is_active' => true, 'show_in_estimate' => true]);
        $estimate = Estimate::create([
            'status' => 'created', 'title' => '구버전 견적', 'client_nickname' => '고블린',
            'product_items' => [
                ['product_id' => $svc->id, 'name' => '방문 세팅비', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000],
                ['name' => '조명', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000],
            ],
            'service_items' => [], 'product_total' => 150000, 'service_total' => 0, 'total_amount' => 150000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
        $estimate->update(['status' => 'paid']);

        $row = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders')->assertOk()->json('0');
        $this->assertCount(1, $row['items']);
        $this->assertSame('조명', $row['items'][0]['name']);
    }

    public function test_builder_offers_equipment_service_kind_selection(): void
    {
        // 수기 입력 시 장비/서비스 선택 + 담은 항목의 전환 토글
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk()
            ->assertSee('id="miKind"', false)      // 데스크탑 수기 입력 분류 셀렉트
            ->assertSee('id="mmKind"', false)      // 모바일 시트 분류 셀렉트
            ->assertSee('toggleItemKind', false);  // 담은 항목 장비/서비스 전환
    }

    public function test_order_list_search_by_product_client_and_date(): void
    {
        $a = $this->makeOrderedEstimate(); // 카메라·마이크 / 고블린
        Estimate::create([
            'status' => 'created', 'title' => '조명 구축', 'client_nickname' => '홍길동',
            'product_items' => [['name' => '조명 세트', 'sale_price' => 1, 'qty' => 1, 'subtotal' => 1, 'ordered' => true]],
            'service_items' => [], 'product_total' => 1, 'service_total' => 0, 'total_amount' => 1,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        // 제품명 검색 — 스냅샷 JSON에서 매칭
        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders?q=조명 세트')->json();
        $this->assertCount(1, $rows);
        $this->assertSame('조명 구축', $rows[0]['title']);

        // 의뢰자 검색
        $rows = $this->actingAs($this->admin)->getJson('/api/inventory/office-orders?q=고블린')->json();
        $this->assertCount(1, $rows);
        $this->assertSame($a->id, $rows[0]['id']);

        // 기간 필터 — 그룹 날짜 밖이면 제외
        $past = now()->subDays(10)->format('Y-m-d');
        $rows = $this->actingAs($this->admin)->getJson("/api/inventory/office-orders?from={$past}&to={$past}")->json();
        $this->assertCount(0, $rows);
    }

    public function test_order_page_renders_sheet_view(): void
    {
        $this->actingAs($this->admin)->get('/inventory')->assertOk()
            ->assertSee('orderSheetBody', false)   // 주문 시트 테이블
            ->assertSee('sheetMarkOrdered', false) // 시트 주문완료/직발
            ->assertSee('ordSearch', false)        // 검색 입력
            ->assertSee('setOrderView', false);    // 카드/시트 전환
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
