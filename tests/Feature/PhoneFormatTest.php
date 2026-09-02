<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 전화번호 자동 하이픈 — 전역 파셜(partials/phone-format)이 레이아웃과 독립 페이지에 포함되는지 */
class PhoneFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_pages_include_phone_formatter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // layouts.app / layouts.tab-content 공통 include — 대표로 의뢰자·캘린더 페이지 확인
        foreach (['/clients', '/calendar'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk()
                ->assertSee('window.formatPhoneInput', false)
                ->assertSee('isPhoneField', false);
        }
    }

    public function test_standalone_estimate_builder_includes_phone_formatter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/estimates/{$estimate->id}/edit")->assertOk()
            ->assertSee('window.formatPhoneInput', false);
    }
}
