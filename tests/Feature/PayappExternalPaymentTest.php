<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\PayappPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 페이앱 자체(외부) 결제 — 기본 FEEDBACK URL 웹훅 수집 + 결제현황 병합 표시 */
class PayappExternalPaymentTest extends TestCase
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

    private function externalFeedback(array $overrides = [])
    {
        return $this->post('/api/payapp/feedback', array_merge([
            'userid' => 'drgotest',
            'mul_no' => 'EXT12345',
            'pay_state' => 4,
            'price' => 250000,
            'goodname' => '방송장비 세팅비',
            'buyer' => '홍외부',
            'recvphone' => '01055556666',
            'pay_type' => 'card',
            'card_name' => '신한카드',
            'csturl' => 'https://payapp.kr/cst/xyz',
        ], $overrides));
    }

    public function test_external_payment_feedback_is_recorded(): void
    {
        $this->externalFeedback()->assertOk()->assertSee('SUCCESS');

        $p = PayappPayment::where('mul_no', 'EXT12345')->first();
        $this->assertNotNull($p);
        $this->assertSame(4, $p->pay_state);
        $this->assertSame(250000, $p->price);
        $this->assertSame('홍외부', $p->buyer);
        $this->assertNotNull($p->paid_at);

        // 같은 mul_no 재통지(환불) — 새 행이 아니라 상태 갱신
        $this->externalFeedback(['pay_state' => 9])->assertOk();
        $this->assertSame(1, PayappPayment::count());
        $this->assertSame(9, $p->fresh()->pay_state);
    }

    public function test_external_feedback_rejects_wrong_userid_or_bad_linkkey(): void
    {
        $this->externalFeedback(['userid' => 'attacker'])->assertStatus(400);
        // 연동키가 실려 왔는데 불일치 — 거부
        $this->externalFeedback(['linkkey' => 'wrong-key'])->assertStatus(400);
        $this->assertSame(0, PayappPayment::count());

        // 올바른 연동키 동봉 — 허용
        $this->externalFeedback(['linkkey' => 'test-linkkey'])->assertOk();
        $this->assertSame(1, PayappPayment::count());
    }

    public function test_payapp_list_merges_estimate_and_external_rows(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        Estimate::create([
            'client_name' => '견적의뢰자', 'client_phone' => '01012341234', 'total_amount' => 500000,
            'status' => 'paid', 'payapp_mul_no' => 'MUL1', 'payapp_state' => 4,
            'payapp_requested_at' => now()->subHour(), 'payapp_paid_at' => now()->subHour(),
            'created_by' => $user->id,
        ]);
        $this->externalFeedback()->assertOk();

        $res = $this->actingAs($user)->getJson('/api/payapp-payments')->assertOk();
        $this->assertSame(2, $res->json('total'));
        $this->assertSame(2, $res->json('paid_count'));
        $this->assertSame(750000, $res->json('paid_amount'));

        $rows = collect($res->json('data'))->keyBy('source');
        // 외부 결제 — 견적서 링크 없음, 매출전표 링크·상품명 있음
        $ext = $rows['payapp'];
        $this->assertNull($ext['estimate_url']);
        $this->assertSame('https://payapp.kr/cst/xyz', $ext['receipt_url']);
        $this->assertSame('방송장비 세팅비', $ext['goodname']);
        $this->assertSame('홍외부', $ext['client_name']);
        // 견적서 결제 — 견적서 링크 있음
        $this->assertNotNull($rows['estimate']['estimate_url']);

        // 검색 — 외부 결제 구매자명으로
        $search = $this->actingAs($user)->getJson('/api/payapp-payments?search='.urlencode('홍외부'))->assertOk();
        $this->assertSame(1, $search->json('total'));
        $this->assertSame('payapp', $search->json('data.0.source'));
    }

    public function test_feedback_is_relayed_to_cafe24_once_per_state(): void
    {
        config(['services.payapp.relay_url' => 'https://drgoblinpro.cafe24.com/shop/payapp/payapp_feedbackurl.php']);
        Http::fake(['drgoblinpro.cafe24.com/*' => Http::response('SUCCESS')]);

        $this->externalFeedback()->assertOk();
        $this->externalFeedback()->assertOk(); // 같은 결제·같은 상태 재통지 — 중계는 1회만
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'cafe24.com') && $req['mul_no'] === 'EXT12345');

        $this->externalFeedback(['pay_state' => 9])->assertOk(); // 상태 변경 — 다시 중계
        Http::assertSentCount(2);
    }
}
