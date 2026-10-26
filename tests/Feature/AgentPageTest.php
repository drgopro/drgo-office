<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AI 에이전트 작업 현황 (/agent, 시연용 샘플) — master/admin 전용, 마이페이지에서 진입 */
class AgentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_agent_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/agent')->assertOk()
            ->assertSee('drgo-office 작업 현황')
            ->assertSee('시연용 샘플 데이터')
            ->assertSee('id="scene"', false);
    }

    public function test_master_can_view_agent_page(): void
    {
        $master = User::factory()->create(['role' => 'master']);

        $this->actingAs($master)->get('/agent')->assertOk();
    }

    public function test_member_and_guest_are_forbidden(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $guest = User::factory()->create(['role' => 'guest']);

        $this->actingAs($member)->get('/agent')->assertForbidden();
        $this->actingAs($guest)->get('/agent')->assertForbidden();
    }

    public function test_unauthenticated_is_redirected_to_login(): void
    {
        $this->get('/agent')->assertRedirect('/login');
    }

    public function test_profile_shows_link_only_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin)->get('/profile')->assertOk()->assertSee('작업 현황 열기');
        $this->actingAs($member)->get('/profile')->assertOk()->assertDontSee('작업 현황 열기');
    }
}
