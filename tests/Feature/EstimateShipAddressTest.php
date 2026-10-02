<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 배송지 정보 — 내부용 필드 저장 + 의뢰자용 견적서 미표시 */
class EstimateShipAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_ship_address_saved_and_hidden_from_public_view(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create(['status' => 'created', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [], 'service_items' => [], 'status' => 'created',
            'ship_address' => '서울 강남구 테스트로 12, 101동 1001호',
            'ship_entrance' => '#1234* 경비실 호출',
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('서울 강남구 테스트로 12, 101동 1001호', $fresh->ship_address);
        $this->assertSame('#1234* 경비실 호출', $fresh->ship_entrance);

        // 빌더에는 표시
        $this->actingAs($user)->get("/estimates/{$estimate->id}/edit")
            ->assertOk()->assertSee('배송받을 주소')->assertSee('#1234* 경비실 호출');

        // 의뢰자용 공개 견적서에는 미표시
        $this->get($fresh->publicUrl())
            ->assertOk()->assertDontSee('테스트로 12')->assertDontSee('#1234*');
    }

    public function test_ship_address_detail_saved_and_search_button_rendered(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create(['status' => 'created', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [], 'service_items' => [], 'status' => 'created',
            'ship_address' => '서울 강남구 테스트로 12',
            'ship_address_detail' => '101동 1001호',
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('서울 강남구 테스트로 12', $fresh->ship_address);
        $this->assertSame('101동 1001호', $fresh->ship_address_detail);

        // 빌더 — 주소 검색 버튼 + 상세주소 입력란
        $this->actingAs($user)->get("/estimates/{$estimate->id}/edit")->assertOk()
            ->assertSee('주소 검색')
            ->assertSee('searchShipAddress', false)
            ->assertSee('id="sAddrDetail"', false)
            ->assertSee('101동 1001호');
    }

    public function test_round_inherits_parent_order_and_ship_info_added_later(): void
    {
        // 차수 생성 '후'에 본 견적서에 입력된 수령인/주소/연락처도 차수에 상속
        $user = User::factory()->create(['role' => 'master']);
        $parent = Estimate::create([
            'estimate_no' => 300, 'status' => 'paid', 'product_items' => [], 'service_items' => [],
            'total_amount' => 100000, 'created_by' => $user->id,
        ]);
        $round = Estimate::find($this->actingAs($user)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));
        $this->assertNull($round->ship_name); // 생성 시점엔 부모에도 없음

        // 차수 생성 후 부모에 배송 정보 입력
        $parent->update([
            'client_phone' => '010-1111-2222', 'ship_name' => '김수령', 'ship_phone' => '010-3333-4444',
            'ship_address' => '서울 송파구 배송로 9', 'ship_address_detail' => '202호', 'ship_entrance' => '#7777',
        ]);

        // 차수 빌더를 열면 비어 있는 필드가 부모 값으로 채워져 저장됨
        $this->actingAs($user)->get("/estimates/{$round->id}/edit")->assertOk()->assertSee('김수령');
        $fresh = $round->fresh();
        $this->assertSame('김수령', $fresh->ship_name);
        $this->assertSame('010-3333-4444', $fresh->ship_phone);
        $this->assertSame('서울 송파구 배송로 9', $fresh->ship_address);
        $this->assertSame('202호', $fresh->ship_address_detail);
        $this->assertSame('010-1111-2222', $fresh->client_phone);

        // 차수에 이미 입력된 값은 덮어쓰지 않음
        $fresh->update(['ship_name' => '차수수령인']);
        $this->actingAs($user)->get("/estimates/{$round->id}/edit")->assertOk();
        $this->assertSame('차수수령인', $round->fresh()->ship_name);
    }

    public function test_ship_recipient_fields_saved_and_hidden_from_public_view(): void
    {
        // 배송지 수령인 이름/연락처/요청사항 — 수기 입력 저장 + 의뢰자용 견적서 미노출
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create(['status' => 'created', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [], 'service_items' => [], 'status' => 'created',
            'ship_name' => '김수령', 'ship_phone' => '010-5555-6666', 'ship_note' => '부재 시 문 앞',
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('김수령', $fresh->ship_name);
        $this->assertSame('010-5555-6666', $fresh->ship_phone);
        $this->assertSame('부재 시 문 앞', $fresh->ship_note);

        // 빌더에는 입력 필드 + 저장된 값 표시
        $this->actingAs($user)->get("/estimates/{$estimate->id}/edit")
            ->assertOk()->assertSee('배송 수령인 이름')->assertSee('배송 요청사항')->assertSee('김수령');

        // 의뢰자용 공개 견적서에는 미표시
        $this->get($fresh->publicUrl())
            ->assertOk()->assertDontSee('김수령')->assertDontSee('부재 시 문 앞');
    }

    public function test_negative_totals_are_saved(): void
    {
        // 할인(음수) 항목이 제품 합계보다 커도 저장 — unsigned 컬럼 22003(Out of range) 회귀 방지
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create(['status' => 'created', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/estimates/{$estimate->id}", [
            'status' => 'created',
            'product_items' => [
                ['name' => '케이블', 'sale_price' => 10000, 'qty' => 1, 'subtotal' => 10000],
                ['name' => '재방문 할인', 'sale_price' => -50000, 'qty' => 1, 'subtotal' => -50000],
            ],
            'service_items' => [],
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame(-40000, (int) $fresh->product_total);
        $this->assertSame(-40000, (int) $fresh->total_amount);
    }

    public function test_partial_update_without_items_keeps_totals(): void
    {
        // 배송 정보만 수정하는 부분 저장이 합계를 0으로 덮어쓰지 않는다
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create([
            'status' => 'created',
            'product_items' => [['name' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000]],
            'service_items' => [], 'product_total' => 300000, 'service_total' => 0, 'total_amount' => 300000,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->patchJson("/api/estimates/{$estimate->id}", [
            'ship_name' => '김수령',
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame(300000, (int) $fresh->total_amount);
        $this->assertSame(300000, (int) $fresh->product_total);
        $this->assertSame('김수령', $fresh->ship_name);
        $this->assertCount(1, $fresh->product_items); // 항목도 유지
    }
}
