<?php

namespace Tests\Feature;

use App\Models\Client;
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
}
