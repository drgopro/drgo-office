<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 작성 모드 — 의뢰자 1명만 띄우는 독립 창(새 창/새 탭) */
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
}
