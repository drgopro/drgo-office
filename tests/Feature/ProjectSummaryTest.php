<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_summary_returns_stage_and_payment_totals(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'stage' => 'payment']);

        $charge = ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'amount' => 100000, 'paid_at' => '2026-07-01',
        ]);
        ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'amount' => 50000, 'paid_at' => '2026-07-10',
        ]);
        ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'refund', 'amount' => -20000,
            'parent_payment_id' => $charge->id,
        ]);

        $res = $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/summary");

        $res->assertOk()
            ->assertJsonPath('name', '스튜디오 세팅')
            ->assertJsonPath('stage', '결제/예약')
            ->assertJsonPath('charged_total', 150000)
            ->assertJsonPath('refunded_total', 20000)
            ->assertJsonPath('paid_total', 130000)
            ->assertJsonPath('payments_count', 2);
        $this->assertStringContainsString('2026-07-10', (string) $res->json('last_paid_at'));
    }

    public function test_summary_without_payments(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '신규 상담', 'stage' => 'consulting']);

        $res = $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/summary");

        $res->assertOk()
            ->assertJsonPath('stage', '상담')
            ->assertJsonPath('paid_total', 0)
            ->assertJsonPath('payments_count', 0);
    }

    public function test_summary_falls_back_to_payment_info_when_no_transactions(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => '원격 문제해결',
            'project_type' => 'troubleshoot',
            'stage' => 'visit',
            'payment_info' => ['amount' => 80000, 'paid_at' => '2026-06-15'],
        ]);

        $res = $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/summary");

        $res->assertOk()
            ->assertJsonPath('paid_total', 80000)
            ->assertJsonPath('payments_count', 1)
            ->assertJsonPath('last_paid_at', '2026-06-15');
    }

    public function test_project_show_always_renders_payment_card(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => '원격 문의',
            'project_type' => 'inquiry', // 진행 프로세스에 결제 단계가 없는 유형
            'stage' => 'consulting',
        ]);

        $res = $this->actingAs($this->admin())->get("/projects/{$project->id}");

        $res->assertOk()
            ->assertSee('id="paymentHistoryCard"', false)
            ->assertSee('+ 결제 추가')
            ->assertSee('등록된 결제 내역이 없습니다');
    }

    public function test_calendar_page_renders_project_summary_loader(): void
    {
        $res = $this->actingAs($this->admin())->get('/calendar');

        $res->assertOk()
            ->assertSee('function lsLoadProjectSummary', false)
            ->assertSee('연결 프로젝트 요약');
    }
}
