<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\RentalContract;
use App\Models\RentalPayment;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 대시보드 잔금 미수 — 결제일이 지났는데 체크되지 않은 렌탈 결제는 '렌탈미납'으로 함께 표시 */
class DashboardRentalOverdueTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->master = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['name' => '윤민아', 'nickname' => '민아', 'grade' => 'normal', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overdue_rental_payments_appear_as_rental_unpaid_rows(): void
    {
        // 7/18 시작, 매월 18일 결제 → 7/18·8/18·9/18·10/18 결제일 중 7/18만 결제됨 = 미납 3회
        $contract = RentalContract::create([
            'client_id' => $this->client->id, 'kind' => 'server', 'product_name' => '스트리밍 서버',
            'start_date' => '2026-07-18', 'monthly_fee' => 158000, 'status' => 'active',
        ]);
        RentalPayment::create(['contract_id' => $contract->id, 'due_date' => '2026-07-18', 'amount' => 158000, 'paid_at' => now(), 'paid_by' => $this->master->id]);

        $res = $this->actingAs($this->master)->get('/')->assertOk();
        $res->assertViewHas('outstandingCount', 1);
        $res->assertViewHas('outstandingTotal', 158000 * 3);
        $row = $res->viewData('outstanding')->first();
        $this->assertSame('rental', $row['type']);
        $this->assertSame('윤민아 (민아)', $row['label']);
        $this->assertSame('스트리밍 서버 · 미납 3회 · 결제일 08.18', $row['sub']);
        $this->assertSame('/rental-contracts?kind=server', $row['href']);
        $this->assertSame('rental-contracts', $row['tab']);

        $html = $res->getContent();
        $this->assertStringContainsString('<span class="ob-chip">렌탈미납</span>윤민아 (민아)', $html);
        $this->assertStringContainsString('474,000원', $html);
    }

    public function test_fully_paid_pending_and_upcoming_contracts_are_not_listed(): void
    {
        // 준비중 — 결제 일정 없음
        RentalContract::create(['client_id' => $this->client->id, 'product_name' => 'A', 'start_date' => '2026-07-18', 'monthly_fee' => 10000, 'status' => 'pending']);
        // 이번 달 시작 — 오늘 결제일(due)만 있고 지난 결제일 없음
        RentalContract::create(['client_id' => $this->client->id, 'product_name' => 'B', 'start_date' => '2026-10-30', 'monthly_fee' => 10000, 'status' => 'active']);
        // 전부 결제됨
        $paid = RentalContract::create(['client_id' => $this->client->id, 'product_name' => 'C', 'start_date' => '2026-09-01', 'monthly_fee' => 10000, 'status' => 'active']);
        foreach (['2026-09-01', '2026-10-01'] as $d) {
            RentalPayment::create(['contract_id' => $paid->id, 'due_date' => $d, 'amount' => 10000, 'paid_at' => now(), 'paid_by' => $this->master->id]);
        }

        // 종료일 없는 해지 — 결제일이 끝없이 늘어나므로 미납으로 세지 않는다
        RentalContract::create(['client_id' => $this->client->id, 'product_name' => 'D', 'start_date' => '2026-01-05', 'monthly_fee' => 10000, 'status' => 'terminated']);

        $res = $this->actingAs($this->master)->get('/')->assertOk();
        $res->assertViewHas('outstandingCount', 0);
        $this->assertStringContainsString('잔금이 남은 프로젝트·렌탈이 없습니다', $res->getContent());
    }

    public function test_terminated_contract_with_end_date_counts_unpaid_months_until_end(): void
    {
        // 7/18 ~ 9/20 해지 — 7/18·8/18·9/18 세 번 중 결제 기록 없음 → 미납 3회, 10/18 은 종료 후라 제외
        RentalContract::create(['client_id' => $this->client->id, 'product_name' => '풀세트', 'start_date' => '2026-07-18', 'end_date' => '2026-09-20', 'monthly_fee' => 50000, 'status' => 'terminated']);

        $res = $this->actingAs($this->master)->get('/')->assertOk();
        $res->assertViewHas('outstandingCount', 1);
        $res->assertViewHas('outstandingTotal', 150000);
        $this->assertSame('풀세트 · 미납 3회 · 결제일 07.18', $res->viewData('outstanding')->first()['sub']);
    }

    public function test_rental_rows_hidden_without_rental_view_permission(): void
    {
        RentalContract::create(['client_id' => $this->client->id, 'product_name' => 'A', 'start_date' => '2026-07-18', 'monthly_fee' => 10000, 'status' => 'active']);

        $team = Team::create(['name' => '프로젝트만', 'slug' => 'proj-'.uniqid(), 'permissions' => ['projects.view']]);
        $member = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);

        $res = $this->actingAs($member)->get('/')->assertOk();
        $res->assertViewHas('outstandingCount', 0);
        $this->assertStringNotContainsString('렌탈미납', $res->getContent());
    }
}
