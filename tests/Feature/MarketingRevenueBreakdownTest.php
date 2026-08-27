<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 통계 매출 지표 — 프로젝트 유형·작업 유형별 세분화 */
class MarketingRevenueBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_grouped_by_project_and_work_type(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        $visit = Project::create([
            'client_id' => $client->id, 'name' => '방문 세팅', 'project_type' => 'visit',
            'work_type' => 'setup', 'stage' => 'payment',
        ]);
        $remote = Project::create([
            'client_id' => $client->id, 'name' => '원격 지원', 'project_type' => 'remote', 'stage' => 'payment',
        ]);

        ProjectPayment::create(['project_id' => $visit->id, 'type' => 'charge', 'amount' => 300000, 'paid_at' => now()->toDateString()]);
        ProjectPayment::create(['project_id' => $visit->id, 'type' => 'charge', 'amount' => 200000, 'paid_at' => now()->toDateString()]);
        ProjectPayment::create(['project_id' => $remote->id, 'type' => 'charge', 'amount' => 100000, 'paid_at' => now()->toDateString()]);

        $res = $this->actingAs($user)->get('/marketing-report');

        $res->assertOk()
            ->assertSee('프로젝트 유형별 매출')
            ->assertSee('작업 유형별 매출')
            ->assertSee('500,000원')      // 방문 합산
            ->assertSee('100,000원')      // 원격
            ->assertSee('└ 세팅', false)  // 방문 하위 작업 유형 분해
            ->assertSee('미지정');         // 원격 프로젝트의 work_type 없음
    }

    public function test_revenue_projects_drilldown(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $visit = Project::create([
            'client_id' => $client->id, 'name' => '부산 스튜디오 세팅', 'project_type' => 'visit', 'stage' => 'payment',
        ]);
        $etc = Project::create([
            'client_id' => $client->id, 'name' => '결제 0원 프로젝트', 'project_type' => 'remote', 'stage' => 'payment',
        ]);

        ProjectPayment::create(['project_id' => $visit->id, 'type' => 'charge', 'amount' => 300000, 'paid_at' => now()->toDateString()]);
        ProjectPayment::create(['project_id' => $visit->id, 'type' => 'refund', 'amount' => -50000, 'paid_at' => now()->toDateString()]);
        ProjectPayment::create(['project_id' => $etc->id, 'type' => 'charge', 'amount' => 100000, 'paid_at' => now()->toDateString()]);
        ProjectPayment::create(['project_id' => $etc->id, 'type' => 'cancel', 'amount' => -100000, 'paid_at' => now()->toDateString()]);

        $res = $this->actingAs($user)->getJson('/api/marketing-report/revenue-projects');

        $res->assertOk()
            ->assertJsonPath('total', 250000)
            ->assertJsonPath('count', 1) // 순매출 0원 프로젝트 제외
            ->assertJsonPath('projects.0.name', '부산 스튜디오 세팅')
            ->assertJsonPath('projects.0.client', '고블린')
            ->assertJsonPath('projects.0.amount', 250000)
            ->assertJsonPath('projects.0.pay_count', 2)
            ->assertJsonPath('projects.0.work', '미지정')
            ->assertJsonCount(2, 'projects.0.payments') // 프로젝트 내 개별 결제 건
            ->assertJsonPath('projects.0.payments.0.label', '환불'); // 최신순 (환불이 나중 기록)
    }

    public function test_revenue_page_renders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/marketing-report/revenue?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertSee('매출 상세')
            ->assertSee('통계로 돌아가기')
            ->assertSee('2026-07-01', false)
            ->assertSee('rvLoad', false);
    }

    public function test_revenue_projects_blocked_for_guest(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);

        $this->actingAs($guest)->getJson('/api/marketing-report/revenue-projects')->assertForbidden();
        $this->actingAs($guest)->get('/marketing-report/revenue')->assertForbidden();
    }

    public function test_refund_reduces_type_revenue(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $visit = Project::create([
            'client_id' => $client->id, 'name' => '방문 세팅', 'project_type' => 'visit', 'stage' => 'payment',
        ]);

        ProjectPayment::create(['project_id' => $visit->id, 'type' => 'charge', 'amount' => 300000, 'paid_at' => now()->toDateString()]);
        ProjectPayment::create(['project_id' => $visit->id, 'type' => 'refund', 'amount' => -100000, 'paid_at' => now()->toDateString()]);

        $this->actingAs($user)->get('/marketing-report')
            ->assertOk()
            ->assertSee('200,000원'); // 환불 차감 반영
    }

    public function test_paid_estimate_with_payment_record_counts_once(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'payment']);

        // 결제완료 견적서 + 자동 기록된 연동 결제 (같은 돈) — 매출은 100만 원 한 번만
        $estimate = Estimate::create([
            'status' => 'paid', 'project_id' => $project->id, 'client_id' => $client->id,
            'product_items' => [], 'service_items' => [], 'product_total' => 1000000, 'total_amount' => 1000000,
            'created_by' => $user->id,
        ]);
        ProjectPayment::create(['project_id' => $project->id, 'type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 1000000, 'paid_at' => now()->toDateString()]);

        $data = $this->actingAs($user)->get('/marketing-report')->assertOk()->viewData('revenueTotal');
        $this->assertSame(1000000, (int) $data);
    }

    public function test_estimate_created_last_month_paid_this_month_not_double_counted(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'payment']);

        // 지난달 만든 견적서가 이번 달에 결제됨 — 지난달 통계에서 견적 전액이 잡히면 이중 집계
        $estimate = Estimate::create([
            'status' => 'paid', 'project_id' => $project->id, 'client_id' => $client->id,
            'product_items' => [], 'service_items' => [], 'product_total' => 500000, 'total_amount' => 500000,
            'created_by' => $user->id,
        ]);
        $estimate->forceFill(['created_at' => now()->subMonth()])->save();
        ProjectPayment::create(['project_id' => $project->id, 'type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 500000, 'paid_at' => now()->toDateString()]);

        // 지난달 기간 조회 — 결제 기록이 있는 견적서는 견적 쪽에서 집계하지 않는다 (0원)
        $from = now()->subMonth()->startOfMonth()->toDateString();
        $to = now()->subMonth()->endOfMonth()->toDateString();
        $lastMonth = $this->actingAs($user)->get("/marketing-report?from={$from}&to={$to}")->assertOk()->viewData('revenueTotal');
        $this->assertSame(0, (int) $lastMonth);

        // 이번 달 기간 조회 — 결제 원장 기준으로 한 번만
        $thisMonth = $this->actingAs($user)->get('/marketing-report')->assertOk()->viewData('revenueTotal');
        $this->assertSame(500000, (int) $thisMonth);
    }

    public function test_unlinked_paid_estimate_counts_with_snapshot_refund_deducted(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        // 프로젝트 미연동 견적서 — 결제 기록이 없으니 견적 금액으로 집계하되 스냅샷 환불은 차감
        Estimate::create([
            'status' => 'paid',
            'product_items' => [['name' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000, 'refunded' => true, 'refund_amount' => 100000]],
            'service_items' => [], 'product_total' => 300000, 'total_amount' => 300000,
            'created_by' => $user->id,
        ]);

        $data = $this->actingAs($user)->get('/marketing-report')->assertOk()->viewData('revenueTotal');
        $this->assertSame(200000, (int) $data);
    }
}
