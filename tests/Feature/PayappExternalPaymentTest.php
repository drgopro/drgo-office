<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\PayappPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function test_excel_import_backfills_payments_including_cancellations(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        // 페이앱 결제내역 엑셀 형태 재현 — 헤더 + 승인/승인취소/요청취소
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([
            ['결제요청일', '결제상태', '결제금액', '구매자명', '휴대폰번호', '상품명', '결제수단', '결제요청번호'],
            ['2026-08-05 14:20:00', '승인', '150,000', '김구매', '01011112222', '방송 장비A', '신용카드', 'H100'],
            ['2026-08-07 10:00:00', '승인취소', '90,000', '박취소', '01033334444', '방송 장비B', '신용카드', 'H101'],
            ['2026-08-09 09:30:00', '요청취소', '50,000', '이대기', '01055557777', '케이블', '카카오페이', 'H102'],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'payapp_').'.xlsx';
        (new Xlsx($sheet->getParent()))->save($path);

        $res = $this->actingAs($user)->post('/api/payapp-payments/import', [
            'file' => new UploadedFile($path, 'payapp_202608.xlsx', null, null, true),
        ])->assertOk();
        $this->assertSame(3, $res->json('created'));

        $paid = PayappPayment::where('mul_no', 'H100')->first();
        $this->assertSame(4, $paid->pay_state);
        $this->assertSame(150000, $paid->price);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('2026-08-05', $paid->created_at->format('Y-m-d')); // 목록 정렬용 결제일

        $this->assertSame(9, PayappPayment::where('mul_no', 'H101')->value('pay_state')); // 승인취소 → 환불
        $this->assertSame(8, PayappPayment::where('mul_no', 'H102')->value('pay_state')); // 요청취소

        // 재업로드 — 중복 생성 없음
        $res2 = $this->actingAs($user)->post('/api/payapp-payments/import', [
            'file' => new UploadedFile($path, 'payapp_202608.xlsx', null, null, true),
        ])->assertOk();
        $this->assertSame(0, $res2->json('created'));
        $this->assertSame(3, PayappPayment::count());

        // 결제현황 목록에 취소건 포함 표시
        $list = $this->actingAs($user)->getJson('/api/payapp-payments?from=2026-08-01&to=2026-08-31')->assertOk();
        $this->assertSame(3, $list->json('total'));
        $this->assertSame(1, $list->json('paid_count'));
    }
}
