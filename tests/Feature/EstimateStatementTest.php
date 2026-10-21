<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 거래명세서 — 견적서(차수 합산) 기반 공급가액/세액 분리 출력 */
class EstimateStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Setting::set('seller_name', '닥터고블린컴퍼니');
        Setting::set('seller_biz_no', '123-45-67890');
    }

    public function test_statement_renders_vat_split_and_totals(): void
    {
        $estimate = Estimate::create([
            'status' => 'paid', 'created_by' => $this->admin->id,
            'client_nickname' => '테스트거래처', 'client_phone' => '010-1234-5678',
            'product_items' => [
                ['name' => '무선마이크 세트', 'sale_price' => 1090000, 'qty' => 1, 'subtotal' => 1090000, 'remark' => '기간 할인'],
                ['name' => '대체된 품목', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000, 'replaced' => true],
                ['name' => '케이블', 'sale_price' => 11000, 'qty' => 2, 'subtotal' => 22000, 'refund_amount' => 11000],
            ],
            'service_items' => [['name' => '세팅비', 'amount' => 110000]],
            'product_total' => 1112000, 'total_amount' => 1222000,
        ]);

        $html = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/statement")->assertOk()->getContent();

        $this->assertStringContainsString('거래명세서', $html);
        $this->assertStringContainsString('닥터고블린컴퍼니', $html);
        $this->assertStringContainsString('123-45-67890', $html);
        $this->assertStringContainsString('테스트거래처', $html);
        $this->assertStringContainsString('무선마이크 세트', $html);
        // 부가세 분해: 1,090,000 → 공급가액 990,909 / 세액 99,091
        $this->assertStringContainsString('990,909', $html);
        $this->assertStringContainsString('99,091', $html);
        // 대체 품목 제외, 환불 음수 라인 포함, 서비스 항목 포함
        $this->assertStringNotContainsString('대체된 품목', $html);
        $this->assertStringContainsString('환불 — 케이블', $html);
        $this->assertStringContainsString('-11,000', $html);
        $this->assertStringContainsString('세팅비', $html);
        // 합계금액 = 1,090,000 + 22,000 − 11,000 + 110,000 = 1,211,000
        $this->assertStringContainsString('1,211,000', $html);
    }

    public function test_statement_merges_rounds_and_round_opens_parent(): void
    {
        $parent = Estimate::create([
            'status' => 'paid', 'created_by' => $this->admin->id, 'estimate_no' => 20261021,
            'product_items' => [['name' => '본 견적 품목', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
            'service_items' => [], 'product_total' => 100000, 'total_amount' => 100000,
        ]);
        $round = Estimate::create([
            'status' => 'paid', 'created_by' => $this->admin->id,
            'parent_estimate_id' => $parent->id, 'round' => 2,
            'product_items' => [['name' => '추가 납품 품목', 'sale_price' => 55000, 'qty' => 1, 'subtotal' => 55000]],
            'service_items' => [], 'product_total' => 55000, 'total_amount' => 55000,
        ]);

        // 차수 ID로 열어도 부모 기준 합산 한 장
        $html = $this->actingAs($this->admin)->get("/estimates/{$round->id}/statement")->assertOk()->getContent();
        $this->assertStringContainsString('본 견적 품목', $html);
        $this->assertStringContainsString('[2차] 추가 납품 품목', $html);
        $this->assertStringContainsString('155,000', $html); // 합계금액
    }

    public function test_tx_date_is_paid_time_for_paid_and_today_before(): void
    {
        // 결제완료 건 — 결제완료 시점 (paid_at은 상태 전환 시 자동 기록)
        $paid = Estimate::create([
            'status' => 'paid', 'created_by' => $this->admin->id,
            'paid_at' => '2026-10-01 14:00:00',
            'product_items' => [], 'service_items' => [], 'product_total' => 0, 'total_amount' => 0,
        ]);
        $this->actingAs($this->admin)->get("/estimates/{$paid->id}/statement")->assertOk()
            ->assertSee('거래일자: 2026년 10월 01일');

        // 결제 전 단계 — 발행 당일
        $issued = Estimate::create([
            'status' => 'issued', 'created_by' => $this->admin->id,
            'product_items' => [], 'service_items' => [], 'product_total' => 0, 'total_amount' => 0,
        ]);
        $this->actingAs($this->admin)->get("/estimates/{$issued->id}/statement")->assertOk()
            ->assertSee('거래일자: '.now()->format('Y년 m월 d일'));
    }

    public function test_statement_requires_estimates_view_permission(): void
    {
        $estimate = Estimate::create([
            'status' => 'paid', 'created_by' => $this->admin->id,
            'product_items' => [], 'service_items' => [], 'product_total' => 0, 'total_amount' => 0,
        ]);
        $guest = User::factory()->create(['role' => 'guest']);

        $this->actingAs($guest)->get("/estimates/{$estimate->id}/statement")->assertForbidden();
    }
}
