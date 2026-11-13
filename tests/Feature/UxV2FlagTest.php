<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 새 화면 디자인(UX v2) 스위치 — config/ux.php 로 켜고 끄며, 끄면 모두 이전 디자인(롤백) */
class UxV2FlagTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $team = Team::create(['name' => '팀'.uniqid(), 'slug' => 'team-'.uniqid(), 'permissions' => ['clients.view', 'projects.view']]);

        return User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);
    }

    public function test_client_page_ships_switch_and_v2_styles_when_enabled(): void
    {
        config(['ux.v2' => true]);

        $this->actingAs($this->staff())->get('/clients')
            ->assertOk()
            ->assertSee('var allowed = true;', false)
            ->assertSee('id="ux-v2-styles"', false)
            ->assertSee('function renderClientGlance(d)', false);
    }

    public function test_switch_is_forced_off_when_disabled(): void
    {
        config(['ux.v2' => false]);

        $this->actingAs($this->staff())->get('/clients')
            ->assertOk()
            ->assertSee('var allowed = false;', false);
    }

    public function test_project_page_has_glance_row(): void
    {
        config(['ux.v2' => true]);
        $client = Client::create(['nickname' => '요약줄', 'grade' => 'normal', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => '요약 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting']);

        $this->actingAs($this->staff())->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSee('id="ux2Glance"', false)
            ->assertSee('id="uxSwitchSlot"', false);
    }
}
