<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 작성 모드 — 의뢰자·프로젝트·캘린더 한 건만 띄우는 독립 창(새 창/새 탭) */
class ClientWriteModeTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Client::create(['nickname' => '작성모드', 'grade' => 'normal', 'status' => 'active']);
    }

    private function userWith(array $permissions): User
    {
        $team = Team::create(['name' => '팀'.uniqid(), 'slug' => 'team-'.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);
    }

    public function test_write_mode_renders_focused_client_without_app_chrome(): void
    {
        $res = $this->actingAs($this->userWith(['clients.view']))
            ->get("/clients/{$this->client->id}/write")
            ->assertOk();

        // 이 의뢰자에 고정된 독립 창 — 앱 헤더(사이드 메뉴·상단 탭) 없는 레이아웃
        $res->assertSee('const CLIENT_FOCUS_ID = '.$this->client->id.';', false);
        $res->assertDontSee('drgoTabs = ', false);
    }

    public function test_main_list_is_not_in_focus_mode(): void
    {
        $this->actingAs($this->userWith(['clients.view']))
            ->get('/clients')
            ->assertOk()
            ->assertSee('const CLIENT_FOCUS_ID = null;', false);
    }

    public function test_write_mode_requires_client_view_permission(): void
    {
        $this->actingAs($this->userWith(['calendar.view']))
            ->get("/clients/{$this->client->id}/write")
            ->assertForbidden();
    }

    public function test_new_client_write_mode_opens_registration_in_standalone_window(): void
    {
        $res = $this->actingAs($this->userWith(['clients.view', 'clients.edit']))
            ->get('/clients/create/write')
            ->assertOk();
        $res->assertSee('const CLIENT_NEW_WRITE = true;', false);
        $res->assertSee('<meta name="csrf-token"', false); // 독립 문서 — 자체 CSRF 메타
        $res->assertDontSee('작성 모드로 열기'); // 이미 작성 모드 창이므로 진입 버튼 없음

        // 등록 권한 없으면 차단
        $this->actingAs($this->userWith(['clients.view']))->get('/clients/create/write')->assertForbidden();
    }

    public function test_project_write_mode_renders_standalone_without_entry_button(): void
    {
        $project = Project::create(['client_id' => $this->client->id, 'name' => '작성모드 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting']);
        $user = $this->userWith(['projects.view', 'clients.view']);

        // 일반 상세(앱 탭)에는 진입 버튼 — 테스트 앱은 요청 간 config가 이어지므로 일반 화면을 먼저 요청
        $this->actingAs($user)->get("/projects/{$project->id}?_tab=1")->assertOk()
            ->assertSee("drgoWriteMode.open('/projects/{$project->id}/write'", false);

        $res = $this->actingAs($user)->get("/projects/{$project->id}/write")->assertOk();
        $res->assertSee('window.DRGO_STANDALONE = true', false);
        $res->assertSee('작성모드 프로젝트');
        $res->assertDontSee("drgoWriteMode.open('/projects/{$project->id}/write'", false);
    }

    public function test_calendar_write_mode_focus_and_new(): void
    {
        $user = $this->userWith(['calendar.view']);
        $schedule = Schedule::create(['title' => '작성모드 일정', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'color' => 'gold', 'created_by' => $user->id]);

        // 일반 캘린더는 작성 모드 아님 (요청 간 config 유지 — 먼저 확인)
        $this->actingAs($user)->get('/calendar')->assertOk()->assertSee('const CAL_WRITE = false;', false);

        $this->actingAs($user)->get("/calendar/write?focus={$schedule->id}")->assertOk()
            ->assertSee('const CAL_WRITE = true;', false)
            ->assertSee('const CAL_WRITE_NEW_DATE = null;', false);

        $this->actingAs($user)->get('/calendar/write?new=1&date=2026-12-24')->assertOk()
            ->assertSee('const CAL_WRITE_NEW_DATE = "2026-12-24";', false);

        // 잘못된 날짜는 오늘로
        $this->actingAs($user)->get('/calendar/write?new=1&date=bad')->assertOk()
            ->assertSee('const CAL_WRITE_NEW_DATE = "'.now()->toDateString().'";', false);
    }

    public function test_top_bar_write_mode_button_and_permission_gated_items(): void
    {
        $editor = $this->userWith(['clients.view', 'clients.edit', 'calendar.edit']);
        $this->actingAs($editor)->get('/')->assertOk()
            ->assertSee('id="wmTop"', false)
            ->assertSee('const WM_CAN_NEW_CLIENT = true;', false)
            ->assertSee('const WM_CAN_NEW_EVENT = true;', false);

        $viewer = $this->userWith(['clients.view']);
        $this->actingAs($viewer)->get('/')->assertOk()
            ->assertSee('const WM_CAN_NEW_CLIENT = false;', false)
            ->assertSee('const WM_CAN_NEW_EVENT = false;', false);
    }

    public function test_write_window_has_shared_navigation_bar(): void
    {
        // 의뢰자·프로젝트·캘린더 작성 모드는 한 창(drgo-write) 안에서 이동 바로 오간다
        $this->actingAs($this->userWith(['clients.view']))
            ->get("/clients/{$this->client->id}/write")
            ->assertOk()
            ->assertSee('class="wmtabs"', false)
            ->assertSee("window.name = 'drgo-write'", false)
            ->assertSee('function toWriteUrl(type, url)', false);

        // 이동 바: 의뢰자·프로젝트를 찾아 열기/수정 (기존 의뢰자 수정은 ?edit=1)
        $this->actingAs($this->userWith(['clients.view']))
            ->get("/clients/{$this->client->id}/write?edit=1")
            ->assertOk()
            ->assertSee("wmNavPop(event,'clients')", false)
            ->assertSee("wmNavPop(event,'projects')", false)
            ->assertSee("get('edit') === '1'", false);
    }
}
