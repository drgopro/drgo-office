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
