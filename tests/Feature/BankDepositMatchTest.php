<?php

namespace Tests\Feature;

use App\Models\BankDeposit;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 입금 내역 ↔ 견적서 수동 매칭 — 후보 추천, 결제완료(계좌이체) 전환, 분할 입금, 해제 */
class BankDepositMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        config([
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '아웃바운드',
            'services.channeltalk.bot_name' => '오피스봇',
        ]);
        Setting::set('payment_alert_group', '견적서결제알림');
    }

    private function makeDeposit(int $amount, string $name = '홍길동'): BankDeposit
    {
        return BankDeposit::create([
            'received_at' => now()->subHour(),
            'amount' => $amount,
            'depositor_name' => $name,
            'bank' => '국민',
            'raw_text' => '입금 '.number_format($amount).'원 '.$name,
            'source' => 'sms',
            'dedup_hash' => md5((string) mt_rand()),
        ]);
    }

    private function makeEstimate(array $attrs = []): Estimate
    {
        return Estimate::create(array_merge([
            'status' => 'issued',
            'product_items' => [['name' => '카메라', 'sale_price' => 500000, 'qty' => 1, 'subtotal' => 500000]],
            'service_items' => [],
            'total_amount' => 500000,
            'client_name' => '홍길동',
            'client_nickname' => '고블린',
            'created_by' => $this->admin->id,
        ], $attrs));
    }

    public function test_candidates_ranked_by_amount_and_name(): void
    {
        $exact = $this->makeEstimate(['estimate_no' => 400]); // 금액+이름 일치
        $nameOnly = $this->makeEstimate(['estimate_no' => 401, 'total_amount' => 300000]); // 이름만
        $this->makeEstimate(['estimate_no' => 402, 'total_amount' => 999000, 'client_name' => '무관', 'client_nickname' => '무관']); // 무관
        $paid = $this->makeEstimate(['estimate_no' => 403, 'status' => 'paid']); // 결제완료 — 제외

        $deposit = $this->makeDeposit(500000, '홍길동');
        $res = $this->actingAs($this->admin)->getJson("/api/bank-deposits/{$deposit->id}/match-candidates");
        $res->assertOk();
        $rows = collect($res->json('candidates'));

        $this->assertSame($exact->id, $rows->first()['id']); // 금액+이름 일치가 1순위
        $this->assertTrue($rows->pluck('id')->contains($nameOnly->id));
        $this->assertFalse($rows->pluck('id')->contains($paid->id));

        // 검색 — 견적서 번호로 직접 찾기
        $byNo = $this->actingAs($this->admin)->getJson("/api/bank-deposits/{$deposit->id}/match-candidates?q=402");
        $this->assertSame([402], collect($byNo->json('candidates'))->pluck('no')->all());
    }

    public function test_match_full_amount_marks_paid_and_alerts(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'stage' => 'consulting']);
        $estimate = $this->makeEstimate(['estimate_no' => 410, 'project_id' => $project->id, 'client_id' => $client->id]);
        $deposit = $this->makeDeposit(500000);

        $res = $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$deposit->id}/match", ['estimate_id' => $estimate->id]);
        $res->assertOk()->assertJsonPath('paid', true);

        $fresh = $estimate->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame($estimate->id, $deposit->fresh()->estimate_id);

        // 프로젝트 결제 원장 — 계좌이체 charge 생성
        $charge = ProjectPayment::where('estimate_id', $estimate->id)->where('type', 'charge')->first();
        $this->assertNotNull($charge);
        $this->assertSame('계좌이체', $charge->method);

        // 채널톡 결제완료 알림 — 계좌이체 표기
        Http::assertSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('견적서결제알림').'/messages')
            && str_contains($r['blocks'][0]['value'] ?? '', '계좌이체')
            && str_contains($r['blocks'][0]['value'] ?? '', '#410'));
    }

    public function test_partial_deposits_accumulate_until_paid(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $estimate = $this->makeEstimate(['estimate_no' => 420]);
        $d1 = $this->makeDeposit(200000);
        $d2 = $this->makeDeposit(300000);

        // 1차 입금 — 매칭만 기록, 결제완료 아님
        $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$d1->id}/match", ['estimate_id' => $estimate->id])
            ->assertOk()->assertJsonPath('paid', false)->assertJsonPath('matched_sum', 200000);
        $this->assertSame('issued', $estimate->fresh()->status);

        // 2차 입금 — 합계 도달, 결제완료 전환
        $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$d2->id}/match", ['estimate_id' => $estimate->id])
            ->assertOk()->assertJsonPath('paid', true);
        $this->assertSame('paid', $estimate->fresh()->status);
    }

    public function test_match_rules_and_unmatch(): void
    {
        $estimate = $this->makeEstimate(['estimate_no' => 430]);
        $deposit = $this->makeDeposit(500000);

        // 결제완료 견적서에는 매칭 불가
        $paid = $this->makeEstimate(['estimate_no' => 431, 'status' => 'paid']);
        $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$deposit->id}/match", ['estimate_id' => $paid->id])
            ->assertStatus(422);

        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$deposit->id}/match", ['estimate_id' => $estimate->id])->assertOk();

        // 이미 매칭된 입금은 재매칭 불가
        $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$deposit->id}/match", ['estimate_id' => $estimate->id])
            ->assertStatus(422);

        // 해제 — 연결만 끊고 견적서 상태는 유지(안내 플래그)
        $this->actingAs($this->admin)->deleteJson("/api/bank-deposits/{$deposit->id}/match")
            ->assertOk()->assertJsonPath('estimate_paid', true);
        $this->assertNull($deposit->fresh()->estimate_id);
        $this->assertSame('paid', $estimate->fresh()->status);
    }

    public function test_deposit_list_includes_matched_estimate(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $estimate = $this->makeEstimate(['estimate_no' => 440]);
        $deposit = $this->makeDeposit(500000);
        $this->actingAs($this->admin)->postJson("/api/bank-deposits/{$deposit->id}/match", ['estimate_id' => $estimate->id])->assertOk();

        $row = collect($this->actingAs($this->admin)->getJson('/api/bank-deposits')->assertOk()->json('data'))
            ->firstWhere('id', $deposit->id);
        $this->assertSame($estimate->id, $row['estimate_id']);
        $this->assertSame(440, $row['estimate']['display_no']);

        // 매칭 UI가 페이지에 렌더
        $this->actingAs($this->admin)->get('/deposits')->assertOk()
            ->assertSee('id="depMatchOverlay"', false)
            ->assertSee('depMatchCell', false)
            ->assertSee('견적서 매칭');
    }
}
