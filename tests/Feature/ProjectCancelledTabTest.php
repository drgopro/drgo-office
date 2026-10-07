<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 목록 진행/취소 탭 — 취소된 프로젝트(stage=cancelled)만 모아 보기 */
class ProjectCancelledTabTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '진행중세팅건', 'stage' => 'consulting', 'status' => 'active']);
        Project::create([
            'client_id' => $client->id, 'name' => '취소 프로젝트', 'stage' => 'cancelled', 'status' => 'active',
            'cancel_reason' => '가격 부담', 'cancel_detail' => '타사 견적 선택', 'cancelled_at' => now()->subDay(),
            'cancelled_from_stage' => 'estimate',
        ]);
    }

    public function test_default_list_excludes_cancelled_and_shows_tab_with_count(): void
    {
        $this->actingAs($this->admin)->get('/projects')
            ->assertOk()
            ->assertSee('진행중세팅건')
            ->assertDontSee('취소 프로젝트')
            ->assertSee('취소된 프로젝트', false)
            ->assertSee('pst-count', false); // 카운트 배지 (1건)
    }

    public function test_cancelled_tab_shows_only_cancelled_with_reason_and_date(): void
    {
        $this->actingAs($this->admin)->get('/projects?status=cancelled')
            ->assertOk()
            ->assertSee('취소 프로젝트')
            ->assertDontSee('진행중세팅건')
            ->assertSee('가격 부담')
            ->assertSee('타사 견적 선택')
            ->assertSee('취소 사유')
            ->assertSee('취소일')
            ->assertSee('견적/계약 중'); // 취소 직전 단계
    }

    public function test_cancelled_tab_search_keeps_tab(): void
    {
        $this->actingAs($this->admin)->get('/projects?status=cancelled&search=취소')
            ->assertOk()
            ->assertSee('취소 프로젝트');

        // 검색어가 진행 건만 매칭하면 취소 탭에서는 빈 결과
        $this->actingAs($this->admin)->get('/projects?status=cancelled&search=진행')
            ->assertOk()
            ->assertSee('취소된 프로젝트가 없습니다.');
    }

    public function test_stage_filter_is_ignored_on_cancelled_tab(): void
    {
        // 남아 있는 stage 파라미터가 취소 조건과 AND로 충돌해 빈 결과가 되지 않아야 한다
        $this->actingAs($this->admin)->get('/projects?status=cancelled&stage[]=consulting')
            ->assertOk()
            ->assertSee('취소 프로젝트');
    }
}
