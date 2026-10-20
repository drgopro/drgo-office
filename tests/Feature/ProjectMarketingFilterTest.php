<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 마케팅 활용 동의 — 프로젝트 목록 필터 + 의뢰자 상세 동의 횟수 */
class ProjectMarketingFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_filter_narrows_project_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '동의 건', 'marketing_consent' => true]);
        Project::create(['client_id' => $client->id, 'name' => '거부 건', 'marketing_consent' => false]);
        Project::create(['client_id' => $client->id, 'name' => '미확인 건']);

        $titles = fn ($res) => collect($res->viewData('projects')->items())->pluck('name');

        $res = $this->actingAs($admin)->get('/projects?marketing[]=agreed')->assertOk();
        $this->assertSame(['동의 건'], $titles($res)->all());

        $res = $this->actingAs($admin)->get('/projects?marketing[]=denied&marketing[]=unknown')->assertOk();
        $this->assertEqualsCanonicalizing(['거부 건', '미확인 건'], $titles($res)->all());

        // 필터 미적용 — 전체
        $res = $this->actingAs($admin)->get('/projects')->assertOk();
        $this->assertCount(3, $titles($res));

        // 필터 칩 렌더
        $this->actingAs($admin)->get('/projects')->assertSee('name="marketing[]"', false)->assertSee('마케팅');
    }

    public function test_client_detail_exposes_consent_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => 'A', 'marketing_consent' => true]);
        Project::create(['client_id' => $client->id, 'name' => 'B', 'marketing_consent' => true]);
        Project::create(['client_id' => $client->id, 'name' => 'C', 'marketing_consent' => false]);
        Project::create(['client_id' => $client->id, 'name' => 'D']);

        $d = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();

        $this->assertSame(2, $d['marketing_consent_count']);
        $this->assertSame(1, $d['marketing_denied_count']);
    }
}
