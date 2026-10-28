<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 일정 탭 + 프로젝트 연동 일정 카드 — 캘린더 일정과의 연결 */
class ClientScheduleTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_schedules_api_matches_linked_id_and_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'name' => '김고블', 'grade' => 'normal']);

        Schedule::create(['title' => 'ID연결 일정', 'color' => 'gold', 'start_date' => '2026-10-21', 'end_date' => '2026-10-21',
            'request_data' => ['client_id' => $client->id]]);
        Schedule::create(['title' => '이름일치 일정', 'color' => 'gold', 'start_date' => '2026-10-22', 'end_date' => '2026-10-22',
            'client_name' => '고블린']);
        Schedule::create(['title' => '무관 일정', 'color' => 'gold', 'start_date' => '2026-10-23', 'end_date' => '2026-10-23',
            'client_name' => '다른사람']);

        $rows = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/schedules")->assertOk()->json();
        $titles = collect($rows)->pluck('title');

        $this->assertTrue($titles->contains('ID연결 일정'));
        $this->assertTrue($titles->contains('이름일치 일정'));
        $this->assertFalse($titles->contains('무관 일정'));
    }

    public function test_client_index_renders_schedule_tab_markup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients')->assertOk()
            ->assertSee('loadClientSchedules', false)
            ->assertSee("'schedules'", false)
            // 날짜(date)를 함께 넘겨야 캘린더가 해당 달로 이동한 뒤 일정을 찾는다 (focus만 넘기면 이번 달 밖은 '찾을 수 없음')
            ->assertSee('/calendar?date=${sc.start_date}&focus=${sc.id}', false);
    }

    public function test_project_linked_schedules_api_filters_by_project_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅 건']);

        Schedule::create(['title' => '프로젝트 연동 일정', 'color' => 'gold', 'start_date' => '2026-10-25', 'end_date' => '2026-10-25',
            'client_name' => '고블린', 'request_data' => ['project_id' => $project->id, 'client_id' => $client->id]]);
        Schedule::create(['title' => '이름만 일치', 'color' => 'gold', 'start_date' => '2026-10-26', 'end_date' => '2026-10-26',
            'client_name' => '고블린']);

        $linked = $this->actingAs($admin)->getJson("/api/projects/{$project->id}/schedules?linked=1")->assertOk()->json();
        $this->assertCount(1, $linked);
        $this->assertSame('프로젝트 연동 일정', $linked[0]['title']);

        // 기존(이름 매칭) 모드는 그대로 — 일정 제안 모달 호환
        $byName = $this->actingAs($admin)->getJson("/api/projects/{$project->id}/schedules")->assertOk()->json();
        $this->assertCount(2, $byName);
    }

    public function test_project_show_renders_linked_schedule_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::create(['name' => '카드 렌더 건']);

        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('id="linkedScheduleCard"', false)
            ->assertSee('fillLinkedSchedules', false)
            ->assertSee('/calendar?date=${sc.start_date}&focus=${sc.id}', false);
    }

    public function test_calendar_focus_fallback_looks_up_schedule_directly_and_404s_when_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = Schedule::create(['title' => '다른 달 일정', 'color' => 'gold', 'start_date' => '2027-03-05', 'end_date' => '2027-03-05']);

        // 캘린더 딥링크 폴백(focusScheduleOutOfRange)이 쓰는 직접 조회 — 표시 범위 밖이어도 날짜를 돌려줘야 이동 가능
        $this->actingAs($admin)->getJson("/api/events/{$schedule->id}/detail")->assertOk()
            ->assertJsonPath('start_date', '2027-03-05');

        // 삭제된 일정은 404 → '삭제되었습니다' 안내
        $schedule->delete();
        $this->actingAs($admin)->getJson("/api/events/{$schedule->id}/detail")->assertNotFound();

        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('focusScheduleOutOfRange', false);
    }

    public function test_calendar_summary_includes_linked_estimate_markup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 요약(잠금) 화면에 연동 견적서 섹션 — 편집/출력 바로 열기
        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('lsLinkedEstimates', false)
            ->assertSee('연동 견적서', false)
            ->assertSee('/estimates/${id}/edit', false);
    }
}
