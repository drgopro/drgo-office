<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 목록 페이징 — per_page(10/20/50) + page, 파라미터 없으면 기존 flat 배열 유지 */
class EstimatePaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        for ($i = 1; $i <= 25; $i++) {
            Estimate::create([
                'status' => 'created', 'client_nickname' => "의뢰자{$i}",
                'product_items' => [], 'service_items' => [],
                'product_total' => 0, 'service_total' => 0, 'total_amount' => $i * 1000,
                'created_by' => $this->admin->id,
            ])->forceFill(['created_at' => now()->subMinutes(25 - $i)])->save(); // 최신순 정렬 검증용
        }
    }

    public function test_paginated_response_with_per_page(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/api/estimates?per_page=10&page=1')->assertOk()->json();
        $this->assertCount(10, $res['data']);
        $this->assertSame(25, $res['total']);
        $this->assertSame(3, $res['last_page']);
        $this->assertSame('의뢰자25', $res['data'][0]['client_nickname']); // 최신순

        $res3 = $this->actingAs($this->admin)->getJson('/api/estimates?per_page=10&page=3')->assertOk()->json();
        $this->assertCount(5, $res3['data']);

        $res50 = $this->actingAs($this->admin)->getJson('/api/estimates?per_page=50')->assertOk()->json();
        $this->assertCount(25, $res50['data']);
        $this->assertSame(1, $res50['last_page']);
    }

    public function test_invalid_per_page_falls_back_to_ten(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/api/estimates?per_page=999')->assertOk()->json();
        $this->assertCount(10, $res['data']);
        $this->assertSame(10, $res['per_page']);
    }

    public function test_without_per_page_returns_flat_array_for_legacy_callers(): void
    {
        // 캘린더 등 기존 화면 호환 — per_page 없으면 배열 그대로
        $res = $this->actingAs($this->admin)->getJson('/api/estimates')->assertOk()->json();
        $this->assertTrue(array_is_list($res));
        $this->assertCount(25, $res);
    }
}
