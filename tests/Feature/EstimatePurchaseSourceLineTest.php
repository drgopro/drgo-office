<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 내부 화면 — 주문완료 항목의 구매처/발송처를 제품명 아래 작게 표시 (출력물 미노출) */
class EstimatePurchaseSourceLineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function makeOrderedEstimateWithSource(): Estimate
    {
        return Estimate::create([
            'status' => 'created', 'client_nickname' => '고블린',
            'product_items' => [
                ['name' => '카메라', 'category' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000,
                    'ordered' => true, 'ordered_at' => '2026-09-08 10:00', 'purchase_source' => '컴퓨존'],
            ],
            'service_items' => [], 'product_total' => 300000, 'service_total' => 0, 'total_amount' => 300000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_edit_page_renders_source_line_and_data(): void
    {
        $estimate = $this->makeOrderedEstimateWithSource();

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk()
            ->assertSee('srcLine', false) // 제품명 하단 구매처/발송처 라인 렌더 코드
            // 스냅샷 JSON은 한글을 유니코드 이스케이프로 임베드 — 같은 형태로 매칭
            ->assertSee(trim(json_encode('컴퓨존'), '"'), false);
    }

    public function test_purchase_source_survives_builder_save(): void
    {
        // 주문내역에서 기입한 구매처가 견적서 빌더 저장으로 유실되지 않는다
        $estimate = $this->makeOrderedEstimateWithSource();
        $items = $estimate->product_items;

        $this->actingAs($this->admin)
            ->patchJson("/api/estimates/{$estimate->id}", ['product_items' => $items])
            ->assertOk();

        $this->assertSame('컴퓨존', $estimate->fresh()->product_items[0]['purchase_source']);
    }

    public function test_print_view_does_not_expose_purchase_source(): void
    {
        // 구매처는 내부 정보 — 의뢰자용 출력물에는 노출되지 않는다
        $estimate = $this->makeOrderedEstimateWithSource();

        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()
            ->assertDontSee('컴퓨존');
    }
}
