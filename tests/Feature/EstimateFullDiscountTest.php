<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 할인율 100% — 무상 제공 품목(0원)도 할인으로 저장된다. 화면의 할인 입력도 1~100%를 받는다. */
class EstimateFullDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_hundred_percent_discount_item_saves_as_zero(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [
                ['name' => '카메라 본체', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000],
                ['name' => '마이크 (무상 제공)', 'sale_price' => 0, 'qty' => 1, 'subtotal' => 0,
                    'deal_type' => 'discount', 'original_price' => 120000, 'discount_rate' => 100],
            ],
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame(0, (int) $fresh->product_items[1]['sale_price']);
        $this->assertSame(100, (int) $fresh->product_items[1]['discount_rate']);
        $this->assertSame(300000, (int) $fresh->total_amount);
    }

    public function test_builder_discount_prompt_accepts_hundred_percent(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertSee('rate > 0 && rate <= 100', false)
            ->assertDontSee('rate > 0 && rate < 100)', false);
    }
}
