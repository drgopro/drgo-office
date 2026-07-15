<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 오피스 전체 통합 검색 (사이드바 Ctrl+K) */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_across_all_office_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '감자TV', 'name' => '김감자', 'phone' => '010-1111-2222', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '감자 스튜디오 세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
        Schedule::create(['title' => '감자 방문 세팅', 'start_date' => '2026-07-20', 'end_date' => '2026-07-20', 'color' => 'gold']);
        Wiki::create(['title' => '감자 조명 가이드', 'content' => '<p>내용</p>', 'category' => '일반']);
        Estimate::create(['client_nickname' => '감자TV', 'total_amount' => 500000]);

        $res = $this->actingAs($admin)->getJson('/api/global-search?q=감자');

        $res->assertOk();
        $keys = collect($res->json('sections'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['clients', 'projects', 'schedules', 'wiki', 'estimates'], $keys);
        $this->assertSame('감자TV', collect($res->json('sections'))->firstWhere('key', 'clients')['items'][0]['label']);
    }

    public function test_private_schedules_of_others_are_hidden(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create(['role' => 'member']);
        Schedule::create([
            'title' => '비밀 미팅', 'start_date' => '2026-07-20', 'end_date' => '2026-07-20',
            'color' => 'purple', 'is_private' => true, 'created_by' => $owner->id,
        ]);

        $res = $this->actingAs($other)->getJson('/api/global-search?q=비밀');
        $this->assertEmpty(collect($res->json('sections'))->firstWhere('key', 'schedules')['items'] ?? []);

        $res2 = $this->actingAs($owner)->getJson('/api/global-search?q=비밀');
        $this->assertNotEmpty(collect($res2->json('sections'))->firstWhere('key', 'schedules')['items']);
    }

    public function test_layout_renders_search_and_nav_groups(): void
    {
        $res = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/');

        $res->assertOk()
            ->assertSee('side-search', false)
            ->assertSee('id="gsOverlay"', false)
            ->assertSee('워크스페이스')
            ->assertSee('지식 · 자산')
            ->assertSee('Ctrl K');
    }

    public function test_dashboard_renders_consult_pipeline_container(): void
    {
        $res = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/');

        $res->assertOk()
            ->assertSee('상담 현황')
            ->assertSee('누적 완료')
            ->assertSee('class="pl-bar"', false)
            ->assertSee('최근 이슈 · 상담 진행중');
    }
}
