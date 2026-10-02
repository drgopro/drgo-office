<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 견적서 달러 표시 — 수출입은행 매매기준율 연동, 저장 시점 환율 고정, 공개 링크 원화/달러 토글 */
class EstimateUsdCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        config(['services.koreaexim.key' => 'test-key']);
    }

    private function makeEstimate(array $attrs = []): Estimate
    {
        return Estimate::create(array_merge([
            'estimate_no' => 500,
            'status' => 'issued',
            'product_items' => [['name' => '카메라', 'sale_price' => 1385200, 'qty' => 1, 'subtotal' => 1385200, 'category' => '카메라']],
            'service_items' => [],
            'product_total' => 1385200,
            'service_total' => 0,
            'total_amount' => 1385200,
            'created_by' => $this->admin->id,
        ], $attrs));
    }

    public function test_usd_rate_api_returns_deal_base_rate_with_business_day_fallback(): void
    {
        // 오늘은 고시 없음(빈 배열) → 전날 고시로 폴백
        Http::fakeSequence()
            ->push([], 200)
            ->push([['cur_unit' => 'USD', 'deal_bas_r' => '1,385.20', 'cur_nm' => '미국 달러']], 200);

        $res = $this->actingAs($this->admin)->getJson('/api/exchange-rate/usd');
        $res->assertOk()
            ->assertJsonPath('rate', 1385.2)
            ->assertJsonPath('date', now()->subDay()->format('Y-m-d'));

        // 성공값은 Setting에 저장 — API 장애 시 마지막 값 폴백용
        $last = json_decode((string) Setting::get('usd_rate_last'), true);
        $this->assertSame(1385.2, (float) $last['rate']);
    }

    public function test_usd_rate_api_falls_back_to_last_known_rate_on_failure(): void
    {
        Setting::set('usd_rate_last', json_encode(['rate' => 1390.5, 'date' => '2026-09-30']));
        Http::fake(['oapi.koreaexim.go.kr/*' => Http::response(null, 500)]);

        $this->actingAs($this->admin)->getJson('/api/exchange-rate/usd')
            ->assertOk()
            ->assertJsonPath('rate', 1390.5)
            ->assertJsonPath('date', '2026-09-30');
    }

    public function test_currency_saved_with_fixed_rate_and_date(): void
    {
        $estimate = $this->makeEstimate(['status' => 'created']);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $estimate->product_items, 'service_items' => [], 'status' => 'created',
            'currency' => 'USD', 'usd_rate' => 1385.20, 'usd_rate_date' => '2026-10-02',
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('USD', $fresh->currency);
        $this->assertSame(1385.2, (float) $fresh->usd_rate);
        $this->assertSame('2026-10-02', $fresh->usd_rate_date->format('Y-m-d'));

        // 빌더 — USD 적용 UI
        $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/edit")->assertOk()
            ->assertSee('USD($)로 적용')
            ->assertSee('id="usdTotalRow"', false);
    }

    public function test_public_view_shows_usd_with_rate_notice_and_toggle(): void
    {
        $estimate = $this->makeEstimate(['currency' => 'USD', 'usd_rate' => 1385.20, 'usd_rate_date' => '2026-10-02', 'payapp_payurl' => 'https://payapp.example/p1']);
        $token = tap($estimate)->publicUrl()->share_token;

        // 기본(저장된 통화 = USD) — 달러 표기 + 기준일 문구 + 토글
        $usd = $this->actingAs($this->admin)->get("/estimate-view/{$token}");
        $usd->assertOk()
            ->assertSee('$1,000.00') // 1,385,200 / 1,385.20
            ->assertSee('2026년 10월 02일 환율 기준')
            ->assertSee('1 USD = 1,385.20원 (매매기준율)')
            ->assertSee('cur-toggle no-print', false)
            ->assertSee('결제는 원화 금액으로 진행됩니다');

        // 원화 토글
        $krw = $this->actingAs($this->admin)->get("/estimate-view/{$token}?cur=KRW");
        $krw->assertOk()
            ->assertSee(number_format(1385200))
            ->assertDontSee('$1,000.00');

        // 환율 미적용 견적서에는 토글 없음
        $plain = $this->makeEstimate(['estimate_no' => 501]);
        $this->actingAs($this->admin)->get('/estimate-view/'.tap($plain)->publicUrl()->share_token)
            ->assertOk()->assertDontSee('cur-toggle no-print');
    }
}
