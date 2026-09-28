<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 마케팅 활용 동의 — 저장/해제, 목록 체크 표시, 통계 동의 의뢰자 추림 */
class ProjectMarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_consent_can_be_toggled_via_update_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'stage' => 'consulting']);

        $this->assertFalse($project->fresh()->marketing_consent);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => true,
        ])->assertSuccessful();
        $this->assertTrue($project->fresh()->marketing_consent);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => false,
        ])->assertSuccessful();
        $this->assertFalse($project->fresh()->marketing_consent);
    }

    public function test_project_list_shows_consent_badge_only_for_consented_projects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '동의한 프로젝트', 'stage' => 'consulting', 'marketing_consent' => true]);
        Project::create(['client_id' => $client->id, 'name' => '동의 안 한 프로젝트', 'stage' => 'consulting']);

        $res = $this->actingAs($admin)->get('/projects');
        $res->assertOk()->assertSee('✓ 마케팅');
        $this->assertSame(1, substr_count($res->getContent(), '✓ 마케팅'));
    }

    public function test_project_show_renders_consent_chip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'stage' => 'consulting', 'marketing_consent' => true]);

        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('id="mkConsentChip"', false)
            ->assertSee('toggleMarketingConsent', false)
            ->assertSee('✓ 마케팅 활용 동의');
    }

    public function test_marketing_report_lists_consented_clients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consented = Client::create(['nickname' => '동의의뢰자', 'grade' => 'normal']);
        $other = Client::create(['nickname' => '미동의의뢰자', 'grade' => 'normal']);
        // 같은 의뢰자의 동의 프로젝트 2건 → 의뢰자 1명으로 묶여야 함
        Project::create(['client_id' => $consented->id, 'name' => '동의 프로젝트 A', 'stage' => 'consulting', 'marketing_consent' => true]);
        Project::create(['client_id' => $consented->id, 'name' => '동의 프로젝트 B', 'stage' => 'done', 'marketing_consent' => true]);
        Project::create(['client_id' => $other->id, 'name' => '일반 프로젝트', 'stage' => 'consulting']);

        $res = $this->actingAs($admin)->get('/marketing-report');
        $res->assertOk()
            ->assertSee('마케팅 활용 동의 의뢰자')
            ->assertSee('동의의뢰자')
            ->assertSee('동의 프로젝트 A')
            ->assertSee('동의 프로젝트 B')
            ->assertDontSee('미동의의뢰자');

        $clients = $res->viewData('marketingConsentClients');
        $this->assertCount(1, $clients);
        $this->assertSame(2, $res->viewData('marketingConsentProjectCount'));
    }
}
