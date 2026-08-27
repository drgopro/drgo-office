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
}
