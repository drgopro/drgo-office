<?php

namespace Tests\Feature;

use App\Models\BankDeposit;
use App\Models\Estimate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankDepositTest extends TestCase
{
    use RefreshDatabase;

    private const KB_SMS = '[KB]08/05 14:32 902002**333 홍길동 입금 500,000 잔액 1,234,567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bank_deposit.token' => 'test-secret']);
    }

    private function ingest(string $text, string $token = 'test-secret')
    {
        return $this->postJson('/api/bank-deposits/ingest', ['text' => $text], ['X-Deposit-Token' => $token]);
    }

    // === 웹훅 수신 ===

    public function test_ingest_rejects_wrong_token(): void
    {
        $this->ingest(self::KB_SMS, 'wrong-token')->assertUnauthorized();
        $this->assertSame(0, BankDeposit::count());
    }

    public function test_ingest_rejects_when_token_not_configured(): void
    {
        config(['services.bank_deposit.token' => '']);
        $this->ingest(self::KB_SMS, '')->assertUnauthorized();
    }

    public function test_ingest_parses_kb_sms(): void
    {
        $this->ingest(self::KB_SMS)->assertCreated();

        $deposit = BankDeposit::first();
        $this->assertSame(500000, $deposit->amount);
        $this->assertSame('홍길동', $deposit->depositor_name);
        $this->assertSame(1234567, $deposit->balance_after);
        $this->assertSame(8, $deposit->received_at->month);
        $this->assertSame(5, $deposit->received_at->day);
        $this->assertSame('14:32', $deposit->received_at->format('H:i'));
        $this->assertSame(self::KB_SMS, $deposit->raw_text);
    }

    public function test_ingest_parses_alternative_format(): void
    {
        $this->ingest('KB국민은행 입금 1,200,000원 08/05 09:10 김철수')->assertCreated();

        $deposit = BankDeposit::first();
        $this->assertSame(1200000, $deposit->amount);
        $this->assertSame('김철수', $deposit->depositor_name);
    }

    public function test_ingest_parses_real_kb_multiline_format(): void
    {
        // 실전 검증된 KB 알림 포맷 (2026-08-11, MacroDroid 알림 트리거 경유)
        $sms = "[Web발신]\n[KB]08/11 14:20\n821337**680\n김지수\n입금\n104,000";
        $this->ingest($sms)->assertCreated();

        $deposit = BankDeposit::first();
        $this->assertSame(104000, $deposit->amount);
        $this->assertSame('김지수', $deposit->depositor_name);
        $this->assertSame('국민은행', $deposit->bank);
        $this->assertSame(8, $deposit->received_at->month);
        $this->assertSame(11, $deposit->received_at->day);
        $this->assertSame('14:20', $deposit->received_at->format('H:i'));
        $this->assertNull($deposit->balance_after);
    }

    public function test_ingest_parses_corporate_depositor_names(): void
    {
        // 법인명 — 특수문자/마스킹/괄호가 섞여도 '입금' 직전 줄을 이름으로 인식
        foreach ([
            '(주)패러블엔터테인먼트' => "[Web발신]\n[KB]08/18 10:00\n821337**680\n(주)패러블엔터테인먼트\n입금\n1,000,000",
            '(주)패러블엔터테인먼*' => "[Web발신]\n[KB]08/18 10:01\n821337**680\n(주)패러블엔터테인먼*\n입금\n1,000,001",
            'A&B미디어' => "[Web발신]\n[KB]08/18 10:02\n821337**680\nA&B미디어\n입금\n1,000,002",
            // 은행이 상호를 자르면서 여는 괄호가 잘리거나 안 닫힌 경우 — 괄호 정리
            '(주)에이치케이코퍼' => "[Web발신]\n[KB]08/18 10:03\n821337**680\n주)에이치케이코퍼\n입금\n1,000,003",
            '조신영(조신몽컴)' => "[Web발신]\n[KB]08/18 10:04\n821337**680\n조신영(조신몽컴\n입금\n1,000,004",
        ] as $expected => $sms) {
            $this->ingest($sms)->assertCreated();
            $this->assertSame($expected, BankDeposit::latest('id')->first()->depositor_name);
        }
    }

    public function test_ingest_detects_various_banks(): void
    {
        $this->ingest('신한 08/11 10:00 입금 50,000원 박영희')->assertCreated();
        $this->assertSame('신한은행', BankDeposit::latest('id')->first()->bank);

        $this->ingest('카카오뱅크 김민수님이 입금 30,000원')->assertCreated();
        $this->assertSame('카카오뱅크', BankDeposit::latest('id')->first()->bank);

        $this->ingest('어디서온지모를 입금 10,000원')->assertCreated();
        $this->assertNull(BankDeposit::latest('id')->first()->bank);
    }

    public function test_ingest_keeps_raw_when_parse_fails(): void
    {
        $this->ingest('입금 관련 알 수 없는 형식의 문자')->assertCreated();

        $deposit = BankDeposit::first();
        $this->assertNull($deposit->amount);
        $this->assertNotNull($deposit->received_at); // 파싱 실패 시 수신 시각
        $this->assertSame('입금 관련 알 수 없는 형식의 문자', $deposit->raw_text);
    }

    public function test_ingest_skips_non_deposit_sms(): void
    {
        $this->ingest('[KB]08/05 14:32 902002**333 홍길동 출금 30,000 잔액 1,000,000')
            ->assertOk()
            ->assertJsonPath('skipped', true);
        $this->assertSame(0, BankDeposit::count());
    }

    public function test_ingest_deduplicates_resent_sms(): void
    {
        $this->ingest(self::KB_SMS)->assertCreated();
        $this->ingest(self::KB_SMS)->assertOk()->assertJsonPath('duplicated', true);
        $this->assertSame(1, BankDeposit::count());
    }

    public function test_ingest_accepts_header_name_variants(): void
    {
        // 포워딩 앱에서 헤더 이름을 X-Deposits-Token(복수형) 등으로 잘못 입력해도 인증
        $this->postJson('/api/bank-deposits/ingest', ['text' => self::KB_SMS], ['X-Deposits-Token' => 'test-secret'])
            ->assertCreated();
        $this->assertSame(1, BankDeposit::count());
    }

    public function test_ingest_accepts_alternative_body_keys_and_raw_fallback(): void
    {
        // message 키
        $this->postJson('/api/bank-deposits/ingest', ['message' => self::KB_SMS], ['X-Deposit-Token' => 'test-secret'])
            ->assertCreated();
        // 알 수 없는 키 → 가장 긴 문자열 값 폴백
        $this->postJson('/api/bank-deposits/ingest', ['from' => '15881688', 'smsBody' => '다른입금 999,000원 08/06 10:00 박영희'], ['X-Deposit-Token' => 'test-secret'])
            ->assertCreated();
        $this->assertSame(2, BankDeposit::count());
    }

    public function test_ingest_get_is_connection_test_only(): void
    {
        $this->getJson('/api/bank-deposits/ingest?token=test-secret')
            ->assertOk()
            ->assertJsonPath('token_valid', true);
        $this->getJson('/api/bank-deposits/ingest?token=wrong')
            ->assertOk()
            ->assertJsonPath('token_valid', false);
        $this->assertSame(0, BankDeposit::count());
    }

    // === 목록 조회 ===

    private function seedDeposits(): void
    {
        foreach ([
            ['2026-08-01 10:00:00', 100000, '홍길동'],
            ['2026-08-03 11:00:00', 200000, '김철수'],
            ['2026-08-05 12:00:00', 300000, '홍길동'],
        ] as $i => [$at, $amount, $name]) {
            BankDeposit::create([
                'received_at' => $at, 'amount' => $amount, 'depositor_name' => $name,
                'raw_text' => "테스트 {$i}", 'source' => 'sms', 'dedup_hash' => "hash-{$i}",
            ]);
        }
    }

    public function test_list_filters_by_period_and_sums(): void
    {
        $this->seedDeposits();
        $user = User::factory()->create(['role' => 'master']);

        $res = $this->actingAs($user)->getJson('/api/bank-deposits?from=2026-08-02&to=2026-08-04&per_page=20');
        $res->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('total_amount', 200000)
            ->assertJsonPath('data.0.depositor_name', '김철수');
    }

    public function test_list_searches_by_name_and_amount(): void
    {
        $this->seedDeposits();
        $user = User::factory()->create(['role' => 'master']);

        $byName = $this->actingAs($user)->getJson('/api/bank-deposits?search='.urlencode('홍길동'));
        $byName->assertOk()->assertJsonPath('total', 2)->assertJsonPath('total_amount', 400000);

        $byAmount = $this->actingAs($user)->getJson('/api/bank-deposits?search=300,000');
        $byAmount->assertOk()->assertJsonPath('total', 1);
    }

    // === 선택 삭제 ===

    public function test_destroy_many_deletes_selected(): void
    {
        $this->seedDeposits();
        $user = User::factory()->create(['role' => 'master']);
        $ids = BankDeposit::orderBy('id')->limit(2)->pluck('id')->all();

        $this->actingAs($user)->deleteJson('/api/bank-deposits', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('deleted', 2);
        $this->assertSame(1, BankDeposit::count());
    }

    public function test_destroy_many_requires_permission(): void
    {
        $this->seedDeposits();
        $memberNoPerm = User::factory()->create(['role' => 'member']);

        $this->actingAs($memberNoPerm)
            ->deleteJson('/api/bank-deposits', ['ids' => [BankDeposit::first()->id]])
            ->assertForbidden();
        $this->assertSame(3, BankDeposit::count());
    }

    // === 권한 ===

    public function test_page_requires_deposits_view_permission(): void
    {
        $memberNoPerm = User::factory()->create(['role' => 'member']);
        $this->actingAs($memberNoPerm)->get('/deposits')->assertForbidden();

        $team = Team::create(['name' => '입금팀', 'slug' => 'deposit-team', 'permissions' => ['deposits.view']]);
        $memberWithPerm = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
        $this->actingAs($memberWithPerm)->get('/deposits')->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/deposits')->assertOk();
    }

    // === 페이앱 결제현황 ===

    /** @param array<string, mixed> $attrs */
    private function makePayappEstimate(User $creator, array $attrs = []): Estimate
    {
        return Estimate::create(array_merge([
            'client_name' => '홍길동',
            'client_phone' => '01012341234',
            'total_amount' => 500000,
            'status' => 'issued',
            'payapp_mul_no' => 'MUL'.uniqid(),
            'payapp_state' => 1,
            'payapp_requested_at' => '2026-08-10 10:00:00',
            'created_by' => $creator->id,
        ], $attrs));
    }

    public function test_payapp_list_shows_requested_estimates_with_status(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $paid = $this->makePayappEstimate($user, [
            'status' => 'paid', 'payapp_state' => 4, 'payapp_paid_at' => '2026-08-10 11:00:00',
        ]);
        $this->makePayappEstimate($user, ['client_name' => '김대기', 'total_amount' => 200000]);
        // 환불 통지 후에는 mul_no가 비워지지만 requested_at으로 목록에 남아야 함
        $this->makePayappEstimate($user, [
            'client_name' => '박환불', 'status' => 'cancelled', 'payapp_state' => 9, 'payapp_mul_no' => null,
        ]);
        Estimate::create(['client_name' => '미요청', 'total_amount' => 1000, 'status' => 'issued', 'created_by' => $user->id]);

        $res = $this->actingAs($user)->getJson('/api/payapp-payments?from=2026-08-01&to=2026-08-31');
        $res->assertOk()
            ->assertJsonPath('total', 3)          // 결제요청 없는 견적서는 제외
            ->assertJsonPath('paid_count', 1)
            ->assertJsonPath('paid_amount', 500000);

        $rows = collect($res->json('data'))->keyBy('client_name');
        $this->assertSame('paid', $rows['홍길동']['status']['key']);
        $this->assertSame('waiting', $rows['김대기']['status']['key']);
        $this->assertSame('refunded', $rows['박환불']['status']['key']);
        $this->assertSame($paid->fresh()->publicUrl(), $rows['홍길동']['estimate_url']);
    }

    public function test_payapp_list_filters_by_status_and_search(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $this->makePayappEstimate($user, ['status' => 'paid', 'payapp_state' => 4, 'payapp_paid_at' => now()]);
        $this->makePayappEstimate($user, ['client_name' => '김대기', 'total_amount' => 200000]);

        $this->actingAs($user)->getJson('/api/payapp-payments?status=paid')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.client_name', '홍길동');
        $this->actingAs($user)->getJson('/api/payapp-payments?status=waiting')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.client_name', '김대기');
        $this->actingAs($user)->getJson('/api/payapp-payments?search='.urlencode('김대기'))
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson('/api/payapp-payments?search=200,000')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.client_name', '김대기');
    }

    public function test_payapp_list_requires_deposits_view_permission(): void
    {
        $memberNoPerm = User::factory()->create(['role' => 'member']);
        $this->actingAs($memberNoPerm)->getJson('/api/payapp-payments')->assertForbidden();
    }
}
