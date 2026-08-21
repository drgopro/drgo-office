<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\PayappPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 페이앱 결제현황 탭 — 결제 대기 요청 철회 / 결제완료 취소(정산 후 환불 요청 폴백) */
class PayappCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.payapp.userid' => 'drgotest',
            'services.payapp.linkkey' => 'test-linkkey',
            'services.payapp.linkval' => 'test-linkval',
        ]);
        $this->user = User::factory()->create(['role' => 'master']);
    }

    private function makeEstimate(array $overrides = []): Estimate
    {
        return Estimate::create(array_merge([
            'client_name' => '취소테스트', 'client_phone' => '01012341234', 'total_amount' => 300000,
            'status' => 'issued', 'payapp_mul_no' => 'MUL100', 'payapp_state' => 1,
            'payapp_payurl' => 'https://payapp.kr/pay/abc', 'payapp_requested_at' => now()->subHour(),
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_waiting_estimate_request_can_be_cancelled(): void
    {
        Http::fake(['api.payapp.kr/*' => Http::response('state=1')]);
        $estimate = $this->makeEstimate();

        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'estimate', 'id' => $estimate->id])
            ->assertOk()->assertJsonFragment(['message' => '결제요청이 취소되었습니다.']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => $req['cmd'] === 'paycancel' && $req['mul_no'] === 'MUL100');

        $estimate->refresh();
        $this->assertNull($estimate->payapp_mul_no);
        $this->assertNull($estimate->payapp_payurl);
        $this->assertSame(16, (int) $estimate->payapp_state);
    }

    public function test_paid_estimate_is_cancelled_before_settlement(): void
    {
        Http::fake(['api.payapp.kr/*' => Http::response('state=1')]);
        $estimate = $this->makeEstimate([
            'status' => 'paid', 'payapp_state' => 4, 'payapp_paid_at' => now()->subDay(),
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'estimate', 'id' => $estimate->id])
            ->assertOk()->assertJsonFragment(['message' => '결제가 취소되었습니다.']);

        Http::assertSentCount(1);
        $estimate->refresh();
        $this->assertSame('cancelled', $estimate->status);
        $this->assertSame(9, (int) $estimate->payapp_state);
        $this->assertNull($estimate->payapp_paid_at);
        $this->assertNull($estimate->payapp_mul_no);
    }

    public function test_settled_payment_falls_back_to_refund_request(): void
    {
        // 정산 완료 건 — paycancel 거부 후 paycancelreq로 환불 요청 접수
        Http::fake([
            'api.payapp.kr/*' => Http::sequence()
                ->push('state=0&errorMessage=이미 정산된 결제입니다')
                ->push('state=1'),
        ]);
        $estimate = $this->makeEstimate([
            'status' => 'paid', 'payapp_state' => 4, 'payapp_paid_at' => now()->subDays(20),
        ]);

        $res = $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'estimate', 'id' => $estimate->id])
            ->assertOk();
        $this->assertStringContainsString('환불 요청', $res->json('message'));

        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req['cmd'] === 'paycancelreq' && $req['mul_no'] === 'MUL100');

        // 상태는 페이앱이 환불을 처리한 뒤 통지(feedback)로 갱신 — 아직 결제완료 유지
        $estimate->refresh();
        $this->assertSame('paid', $estimate->status);
        $this->assertNotNull($estimate->payapp_paid_at);
    }

    public function test_waiting_cancel_failure_does_not_fall_back(): void
    {
        Http::fake(['api.payapp.kr/*' => Http::response('state=0&errorMessage=취소 불가')]);
        $estimate = $this->makeEstimate();

        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'estimate', 'id' => $estimate->id])
            ->assertStatus(422);

        Http::assertSentCount(1); // 결제 대기 건은 환불 요청 폴백 없음
        $this->assertSame('MUL100', $estimate->fresh()->payapp_mul_no); // 실패 시 유지
    }

    public function test_external_payment_cancel_updates_state(): void
    {
        Http::fake(['api.payapp.kr/*' => Http::response('state=1')]);
        $paid = PayappPayment::create([
            'mul_no' => 'EXT200', 'pay_state' => 4, 'price' => 100000,
            'goodname' => '외부결제', 'buyer' => '홍외부', 'paid_at' => now()->subDay(),
        ]);
        $waiting = PayappPayment::create([
            'mul_no' => 'EXT201', 'pay_state' => 1, 'price' => 50000,
            'goodname' => '외부대기', 'buyer' => '김대기',
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'payapp', 'mul_no' => 'EXT200'])
            ->assertOk()->assertJsonFragment(['message' => '결제가 취소되었습니다.']);
        $this->assertSame(9, $paid->fresh()->pay_state);

        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'payapp', 'mul_no' => 'EXT201'])
            ->assertOk()->assertJsonFragment(['message' => '결제요청이 취소되었습니다.']);
        $this->assertSame(8, $waiting->fresh()->pay_state);
    }

    public function test_imported_and_already_cancelled_rows_are_rejected(): void
    {
        Http::fake();
        PayappPayment::create(['mul_no' => 'IMP-abc123', 'pay_state' => 4, 'price' => 70000, 'buyer' => '엑셀건', 'paid_at' => now()]);
        PayappPayment::create(['mul_no' => 'EXT300', 'pay_state' => 9, 'price' => 80000, 'buyer' => '환불됨']);

        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'payapp', 'mul_no' => 'IMP-abc123'])
            ->assertStatus(422);
        $this->actingAs($this->user)
            ->postJson('/api/payapp-payments/cancel', ['source' => 'payapp', 'mul_no' => 'EXT300'])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_list_exposes_can_cancel_flag(): void
    {
        $this->makeEstimate(); // 결제 대기 — 취소 가능
        PayappPayment::create(['mul_no' => 'IMP-xyz', 'pay_state' => 4, 'price' => 10000, 'buyer' => '엑셀', 'paid_at' => now()]);

        $rows = collect($this->actingAs($this->user)->getJson('/api/payapp-payments')->assertOk()->json('data'));
        $this->assertTrue($rows->firstWhere('source', 'estimate')['can_cancel']);
        $this->assertFalse($rows->firstWhere('source', 'payapp')['can_cancel']); // 엑셀 백필 건
    }
}
