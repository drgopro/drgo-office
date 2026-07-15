<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 대시보드 상담 대기/진행중 목록 — 클릭 시 연결 프로젝트/의뢰자로 이동 */
class DashboardConsultLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_consult_item_links_to_project_or_client(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '연치민', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '세팅 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        $project2 = Project::create([
            'client_id' => $client->id, 'name' => '원격 프로젝트', 'project_type' => 'remote', 'stage' => 'consulting',
        ]);

        Consultation::create([
            'client_id' => $client->id, 'project_id' => $project->id,
            'consulted_at' => now(), 'result' => 'in_progress', 'content' => '입금 완료 확인 요청',
        ]);
        Consultation::create([
            'client_id' => $client->id, 'project_id' => $project2->id,
            'consulted_at' => now()->subHour(), 'result' => 'waiting', 'content' => '견적 문의',
        ]);

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()
            ->assertSee("'/projects/{$project->id}'", false)
            ->assertSee("'/projects/{$project2->id}'", false)
            ->assertSee('consult-item clickable', false);
    }

    public function test_dashboard_renders_new_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '옴니버', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '부산 세팅', 'project_type' => 'visit', 'stage' => 'payment']);

        // 오늘의 일정 + 주목 프로젝트(확정 일정) + 잔금 미수
        Schedule::create([
            'title' => '옴니버 방문 세팅', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'color' => 'gold', 'is_all_day' => true,
        ]);
        Schedule::create([
            'title' => '옴니버 확정 방문', 'client_name' => '옴니버',
            'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(5)->toDateString(),
            'color' => 'gold', 'is_all_day' => true, 'sched_opt' => 'confirmed',
        ]);
        ProjectBilling::create([
            'project_id' => $project->id, 'amount' => 550000, 'billed_at' => now()->toDateString(), 'status' => 'unpaid',
        ]);

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()
            ->assertSee('logo-office.png') // 사이드바 이미지 로고
            ->assertSee('오늘의 일정')
            ->assertSee('옴니버 방문 세팅')
            ->assertSee('주목 프로젝트')
            ->assertSee('확정 일정')
            ->assertSee('잔금 미수')
            ->assertSee('550,000원')
            ->assertSee('공지사항')
            ->assertSee('업데이트');
    }

    public function test_vacation_card_shows_upcoming_week_only(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // 오늘 휴가 중 / D-5 예정 / D-10 (표시 제외)
        Schedule::create(['title' => '김웅 여름휴가', 'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'color' => 'red', 'is_all_day' => true]);
        Schedule::create(['title' => '박진혁 휴가', 'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(5)->toDateString(), 'color' => 'red', 'is_all_day' => true]);
        Schedule::create(['title' => '먼 미래 휴가', 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(10)->toDateString(), 'color' => 'red', 'is_all_day' => true]);

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()
            ->assertSee('김웅 여름휴가')
            ->assertSee('휴가 중')
            ->assertSee('박진혁 휴가')
            ->assertSee('D-5')
            ->assertDontSee('먼 미래 휴가');
    }
}
