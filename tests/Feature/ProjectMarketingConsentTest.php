<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 마케팅 활용 동의 (null=미확인/true=동의/false=거부) — 저장, 목록 배지, 통계 추림 */
class ProjectMarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_consent_supports_three_states_via_update_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'stage' => 'consulting']);

        $this->assertNull($project->fresh()->marketing_consent);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => true,
        ])->assertSuccessful();
        $this->assertTrue($project->fresh()->marketing_consent);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => false,
        ])->assertSuccessful();
        $this->assertFalse($project->fresh()->marketing_consent);

        // 미확인으로 되돌리기
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => null,
        ])->assertSuccessful();
        $this->assertNull($project->fresh()->marketing_consent);
    }

    public function test_project_list_shows_consent_and_refusal_badges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '동의한 프로젝트', 'stage' => 'consulting', 'marketing_consent' => true]);
        Project::create(['client_id' => $client->id, 'name' => '거부한 프로젝트', 'stage' => 'consulting', 'marketing_consent' => false]);
        Project::create(['client_id' => $client->id, 'name' => '미확인 프로젝트', 'stage' => 'consulting']);

        $res = $this->actingAs($admin)->get('/projects');
        $res->assertOk()->assertSee('✓ 마케팅')->assertSee('✕ 마케팅');
        $this->assertSame(1, substr_count($res->getContent(), '✓ 마케팅'));
        $this->assertSame(1, substr_count($res->getContent(), '✕ 마케팅'));
    }

    public function test_project_show_renders_consent_dropdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'stage' => 'consulting', 'marketing_consent' => true]);

        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('id="mkConsentSelect"', false)
            ->assertSee('saveMarketingConsent', false)
            ->assertSee('미확인')
            ->assertSee('거부');
    }

    public function test_marketing_report_lists_consented_and_refused_clients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consented = Client::create(['nickname' => '동의의뢰자', 'grade' => 'normal']);
        $refused = Client::create(['nickname' => '거부의뢰자', 'grade' => 'normal']);
        $other = Client::create(['nickname' => '미확인의뢰자', 'grade' => 'normal']);
        // 같은 의뢰자의 동의 프로젝트 2건 → 의뢰자 1명으로 묶여야 함
        Project::create(['client_id' => $consented->id, 'name' => '동의 프로젝트 A', 'stage' => 'consulting', 'marketing_consent' => true]);
        Project::create(['client_id' => $consented->id, 'name' => '동의 프로젝트 B', 'stage' => 'done', 'marketing_consent' => true]);
        Project::create(['client_id' => $refused->id, 'name' => '거부 프로젝트', 'stage' => 'consulting', 'marketing_consent' => false]);
        Project::create(['client_id' => $other->id, 'name' => '일반 프로젝트', 'stage' => 'consulting']);

        $res = $this->actingAs($admin)->get('/marketing-report');
        $res->assertOk()
            ->assertSee('마케팅 활용 동의 의뢰자')
            ->assertSee('동의의뢰자')
            ->assertSee('동의 프로젝트 A')
            ->assertSee('동의 프로젝트 B')
            ->assertSee('거부의뢰자')
            ->assertSee('거부 프로젝트')
            ->assertDontSee('미확인의뢰자');

        $this->assertCount(1, $res->viewData('marketingConsentClients'));
        $this->assertCount(1, $res->viewData('marketingRefusedClients'));
        $this->assertSame(2, $res->viewData('marketingConsentProjectCount'));
    }
}
