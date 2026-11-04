<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConsultationType;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\RevenueEntry;
use App\Models\User;
use App\Models\WorkType;
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

    public function test_revenue_by_client_scale_matrix(): void
    {
        // 의뢰자 유형(개인/스튜디오) × 매출 구분(세팅비/장비판매) 매트릭스 — 매출 인식 원장 기준
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        $personal = Project::create([
            'client_id' => $client->id, 'name' => '개인 세팅', 'project_type' => 'visit',
            'stage' => 'payment', 'client_scale' => 'personal',
        ]);
        $studio = Project::create([
            'client_id' => $client->id, 'name' => '스튜디오 세팅', 'project_type' => 'visit',
            'stage' => 'payment', 'client_scale' => 'studio',
        ]);
        $rentalScale = Project::create([
            'client_id' => $client->id, 'name' => '렌탈 결제', 'project_type' => 'visit',
            'stage' => 'payment', 'client_scale' => 'rental',
        ]);

        RevenueEntry::create([
            'kind' => 'estimate', 'project_id' => $personal->id, 'recognized_on' => now()->toDateString(),
            'service_amount' => 300000, 'product_amount' => 700000, 'amount' => 1000000,
        ]);
        RevenueEntry::create([
            'kind' => 'estimate', 'project_id' => $studio->id, 'recognized_on' => now()->toDateString(),
            'service_amount' => 500000, 'product_amount' => 0, 'amount' => 500000,
        ]);
        RevenueEntry::create([
            'kind' => 'payment_only', 'project_id' => $rentalScale->id, 'recognized_on' => now()->toDateString(),
            'service_amount' => 0, 'product_amount' => 0, 'amount' => 110000,
        ]);
        RevenueEntry::create([ // 프로젝트 미연동 — 미지정 행
            'kind' => 'payment_only', 'recognized_on' => now()->toDateString(),
            'service_amount' => 0, 'product_amount' => 0, 'amount' => 90000,
        ]);

        // 프로젝트에 규모가 비어 있으면 의뢰자 유형(client_type)으로 폴백 — 미지정 최소화
        $ent = Client::create(['nickname' => '엔터사', 'grade' => 'normal', 'client_type' => 'enterprise']);
        $noScale = Project::create([
            'client_id' => $ent->id, 'name' => '규모 미입력', 'project_type' => 'visit', 'stage' => 'payment',
        ]);
        RevenueEntry::create([
            'kind' => 'estimate', 'project_id' => $noScale->id, 'recognized_on' => now()->toDateString(),
            'service_amount' => 40000, 'product_amount' => 0, 'amount' => 40000,
        ]);

        $res = $this->actingAs($user)->get('/marketing-report');

        $res->assertOk()
            ->assertSee('의뢰자 유형 × 매출 구분')
            ->assertSee('개인')->assertSee('스튜디오')->assertSee('미지정')
            ->assertSee('기업')             // 매출 0이어도 행 표시
            ->assertSee('엔터')             // client_type 폴백 행
            ->assertSee('렌탈')             // client_scale=rental도 한글 라벨
            ->assertDontSee('>rental<', false)
            ->assertSee('300,000원')   // 개인 세팅비
            ->assertSee('700,000원')   // 개인 장비판매
            ->assertSee('500,000원')   // 스튜디오 세팅비
            ->assertSee('1,000,000원') // 개인 합계
            ->assertSee('40,000원')    // 엔터 (규모 미입력 → 의뢰자 유형 폴백)
            ->assertSee('90,000원');   // 미지정(수기 결제) 기타
    }

    public function test_project_type_label_falls_back_to_config(): void
    {
        // 관리자 정의(consultation_types)에 없는 기본 유형은 config 라벨로 — 'rental'이 원문 키로 노출되던 문제
        $user = User::factory()->create(['role' => 'admin']);
        ConsultationType::where('key', 'rental')->delete(); // 관리자 정의에서 지워진 상태 재현
        WorkType::where('key', 'rental')->delete(); // 작업 유형도 동일 상황 재현
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $rental = Project::create([
            'client_id' => $client->id, 'name' => '장비 렌탈 건', 'project_type' => 'rental',
            'work_type' => 'rental', 'stage' => 'payment',
        ]);
        ProjectPayment::create(['project_id' => $rental->id, 'type' => 'charge', 'amount' => 150000, 'paid_at' => now()->toDateString()]);

        $this->actingAs($user)->get('/marketing-report')
            ->assertOk()
            ->assertSee('렌탈')
            ->assertDontSee('>rental<', false);
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
