<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use App\Services\PayAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EstimatePayAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.payapp.userid' => 'drgotest',
            'services.payapp.linkkey' => 'test-linkkey',
            'services.payapp.linkval' => 'test-linkval',
        ]);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    private function makeEstimate(array $attrs = []): Estimate
    {
        return Estimate::create([
            'client_name' => '홍길동',
            'client_phone' => '010-1234-5678',
            'product_items' => [['name' => '방송용 PC', 'category' => 'PC', 'sale_price' => 1500000, 'qty' => 1, 'subtotal' => 1500000]],
            'total_amount' => 1500000,
            'status' => 'completed',
            'created_by' => $this->master()->id,
            ...$attrs,
        ]);
    }

    private function fakePayAppSuccess(): void
    {
        Http::fake(['api.payapp.kr/*' => Http::response('state=1&mul_no=98765&payurl=https%3A%2F%2Fwww.payapp.kr%2FL%2Fabcdef')]);
    }

    // === 공개 견적서 (의뢰자용) ===

    public function test_public_view_accessible_by_token_without_login(): void
    {
        $estimate = $this->makeEstimate();
        $url = $estimate->publicUrl();

        $this->assertStringContainsString('/estimate-view/', $url);
        $this->assertStringNotContainsString('/estimate-view/'.$estimate->id, $url); // 순번 ID 미노출

        $this->get($url)->assertOk()->assertSee('견 적 서')->assertSee('홍길동');
    }

    public function test_public_view_rejects_wrong_or_short_token(): void
    {
        $this->makeEstimate()->publicUrl();

        $this->get('/estimate-view/'.str_repeat('f', 64))->assertNotFound(); // 임의 토큰
        $this->get('/estimate-view/1')->assertNotFound(); // ID 추측 시도
    }

    public function test_share_token_is_stable_after_first_generation(): void
    {
        $estimate = $this->makeEstimate();
        $this->assertSame($estimate->publicUrl(), $estimate->fresh()->publicUrl());
    }

    public function test_public_view_shows_pay_button_only_when_requested(): void
    {
        $estimate = $this->makeEstimate();

        // 결제요청 전 — 버튼 없음
        $this->get($estimate->publicUrl())->assertOk()->assertDontSee('결제하기');

        // 결제요청 후 — 결제 버튼 + 페이앱 URL
        $estimate->update(['payapp_payurl' => 'https://www.payapp.kr/L/abcdef']);
        $this->get($estimate->publicUrl())
            ->assertSee('1,500,000원 결제하기')
            ->assertSee('https://www.payapp.kr/L/abcdef', false);

        // 결제 완료 후 — 완료 표시
        $estimate->update(['status' => 'paid']);
        $this->get($estimate->publicUrl())->assertSee('결제가 완료되었습니다')->assertDontSee('결제하기');
    }

    public function test_public_view_hides_internal_edit_link(): void
    {
        $estimate = $this->makeEstimate(['payapp_payurl' => 'https://www.payapp.kr/L/abcdef']);

        $this->get($estimate->publicUrl())->assertDontSee(route('estimates.edit', $estimate), false);
    }

    // === 결제요청 생성/취소 ===

    public function test_payapp_request_stores_payurl(): void
    {
        $this->fakePayAppSuccess();
        $estimate = $this->makeEstimate();

        $this->actingAs($this->master())
            ->postJson("/api/estimates/{$estimate->id}/payapp-request")
            ->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('98765', $fresh->payapp_mul_no);
        $this->assertSame('https://www.payapp.kr/L/abcdef', $fresh->payapp_payurl);
        $this->assertNotNull($fresh->payapp_requested_at);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.payapp.kr')
                && $request['cmd'] === 'payrequest'
                && $request['price'] == 1500000
                && $request['recvphone'] === '01012345678';
        });
    }

    public function test_payapp_request_requires_phone_and_config(): void
    {
        $estimate = $this->makeEstimate(['client_phone' => null]);
        $this->actingAs($this->master())
            ->postJson("/api/estimates/{$estimate->id}/payapp-request")
            ->assertUnprocessable();

        config(['services.payapp.userid' => '']);
        $estimate2 = $this->makeEstimate();
        $this->actingAs($this->master())
            ->postJson("/api/estimates/{$estimate2->id}/payapp-request")
            ->assertUnprocessable();
    }

    public function test_payapp_cancel_clears_payurl(): void
    {
        Http::fake(['api.payapp.kr/*' => Http::response('state=1')]);
        $estimate = $this->makeEstimate(['payapp_mul_no' => '98765', 'payapp_payurl' => 'https://www.payapp.kr/L/abcdef']);

        $this->actingAs($this->master())
            ->postJson("/api/estimates/{$estimate->id}/payapp-cancel")
            ->assertOk();

        $this->assertNull($estimate->fresh()->payapp_payurl);
    }

    // === 결제 결과 통지 (feedback) ===

    private function feedbackPayload(Estimate $estimate, array $overrides = []): array
    {
        return [
            'userid' => 'drgotest',
            'linkkey' => 'test-linkkey',
            'linkval' => 'test-linkval',
            'mul_no' => '98765',
            'pay_state' => '4',
            'price' => (string) $estimate->total_amount,
            'var1' => (string) $estimate->id,
            'var2' => app(PayAppClient::class)->feedbackToken($estimate),
            ...$overrides,
        ];
    }

    public function test_feedback_marks_estimate_paid(): void
    {
        $estimate = $this->makeEstimate(['payapp_mul_no' => '98765']);

        $this->post('/api/payapp/feedback', $this->feedbackPayload($estimate))
            ->assertOk()
            ->assertSee('SUCCESS');

        $fresh = $estimate->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame(4, $fresh->payapp_state);
        $this->assertNotNull($fresh->payapp_paid_at);
    }

    public function test_feedback_rejects_wrong_link_credentials(): void
    {
        $estimate = $this->makeEstimate(['payapp_mul_no' => '98765']);

        $this->post('/api/payapp/feedback', $this->feedbackPayload($estimate, [
            'linkkey' => 'wrong', 'linkval' => 'wrong',
        ]))->assertStatus(400);

        $this->assertSame('completed', $estimate->fresh()->status);
    }

    public function test_feedback_holds_paid_on_price_mismatch(): void
    {
        $estimate = $this->makeEstimate(['payapp_mul_no' => '98765']);

        $this->post('/api/payapp/feedback', $this->feedbackPayload($estimate, ['price' => '10000']))
            ->assertOk();

        $fresh = $estimate->fresh();
        $this->assertNotSame('paid', $fresh->status); // 금액 불일치 → 결제완료 보류
        $this->assertSame(4, $fresh->payapp_state); // 상태 원본은 기록
    }

    public function test_feedback_refund_reverts_paid_status(): void
    {
        $estimate = $this->makeEstimate([
            'payapp_mul_no' => '98765', 'status' => 'paid', 'payapp_paid_at' => now(),
        ]);

        $this->post('/api/payapp/feedback', $this->feedbackPayload($estimate, ['pay_state' => '9']))
            ->assertOk();

        $fresh = $estimate->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNull($fresh->payapp_paid_at);
    }

    public function test_payapp_request_requires_estimates_edit_permission(): void
    {
        $estimate = $this->makeEstimate();
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->postJson("/api/estimates/{$estimate->id}/payapp-request")
            ->assertForbidden();
    }
}
