<?php

namespace Tests\Feature;

use App\Models\BankDeposit;
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
}
