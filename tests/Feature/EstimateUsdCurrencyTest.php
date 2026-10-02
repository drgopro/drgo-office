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
        Http::fake([
            'oapi.koreaexim.go.kr/*' => Http::response(null, 500),
            'www.koreaexim.go.kr/*' => Http::response(null, 500),
        ]);

        $this->actingAs($this->admin)->getJson('/api/exchange-rate/usd')
            ->assertOk()
            ->assertJsonPath('rate', 1390.5)
            ->assertJsonPath('date', '2026-09-30');
    }

    public function test_usd_rate_api_reports_auth_error_code(): void
    {
        // 인증코드 오류(result:3) — 7일 메시지가 아니라 키 문제를 바로 안내
        Http::fake(['*koreaexim.go.kr/*' => Http::response([['result' => 3]], 200)]);

        $res = $this->actingAs($this->admin)->getJson('/api/exchange-rate/usd');
        $res->assertStatus(503);
        $this->assertStringContainsString('인증코드 오류', $res->json('message'));
    }

    public function test_usd_rate_api_falls_back_to_www_domain(): void
    {
        // oapi 도메인이 막힌 환경 — 구 도메인(www)으로 폴백해 성공
        Http::fake([
            'oapi.koreaexim.go.kr/*' => Http::response(null, 500),
            'www.koreaexim.go.kr/*' => Http::response([['cur_unit' => 'USD', 'deal_bas_r' => '1,382.00', 'result' => 1]], 200),
        ]);

        $res = $this->actingAs($this->admin)->getJson('/api/exchange-rate/usd');
        $res->assertOk();
        $this->assertSame(1382.0, (float) $res->json('rate'));
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

        // 목록 — 원화 아래 USD 병기 (currency/usd_rate가 응답에 포함되고 렌더 헬퍼 존재)
        $this->actingAs($this->admin)->getJson('/api/estimates')->assertOk()
            ->assertJsonPath('0.currency', 'USD');
        $this->actingAs($this->admin)->get('/estimates')->assertOk()
            ->assertSee('estUsdSub', false);
    }

    public function test_usd_rate_fixed_at_issue_time(): void
    {
        // 적용 시점 환율(1,370)로 저장돼 있어도, 발행완료 처리 시점의 고시(1,385.20)로 최종 고정
        Http::fake(['*koreaexim.go.kr/*' => Http::response([['cur_unit' => 'USD', 'deal_bas_r' => '1,385.20', 'result' => 1]], 200)]);
        $estimate = $this->makeEstimate(['status' => 'created', 'currency' => 'USD', 'usd_rate' => 1370.00, 'usd_rate_date' => '2026-09-25']);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => $estimate->product_items, 'service_items' => [], 'status' => 'issued',
            'currency' => 'USD', 'usd_rate' => 1370.00, 'usd_rate_date' => '2026-09-25',
        ])->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame(1385.2, (float) $fresh->usd_rate);
        $this->assertSame(now()->format('Y-m-d'), $fresh->usd_rate_date->format('Y-m-d'));

    }

    public function test_issue_keeps_existing_rate_when_fx_api_fails(): void
    {
        // 환율 API 장애 시 — 기존(적용 시점) 환율 유지, 발행은 정상 진행
        Http::fake(['*koreaexim.go.kr/*' => Http::response(null, 500)]);
        $other = $this->makeEstimate(['estimate_no' => 502, 'status' => 'created', 'currency' => 'USD', 'usd_rate' => 1370.00, 'usd_rate_date' => '2026-09-25']);
        $this->actingAs($this->admin)->postJson("/api/estimates/{$other->id}/issue")->assertOk();
        $this->assertSame('issued', $other->fresh()->status);
        $this->assertSame(1370.0, (float) $other->fresh()->usd_rate);
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
