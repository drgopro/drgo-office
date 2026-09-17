<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 통계 엑셀 출력 권한(stats.export) — admin 이상 + 권한 부여 팀(예: 콘텐츠 기획팀)만 */
class StatsExportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function memberWith(array $permissions): User
    {
        $team = Team::create(['name' => '팀'.uniqid(), 'slug' => 'team-'.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
    }

    public function test_admin_can_export_excel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $range = 'from='.now()->startOfMonth()->format('Y-m-d').'&to='.now()->format('Y-m-d');

        $this->actingAs($admin)->get("/api/dashboard-export/excel?{$range}")->assertOk();
        $this->actingAs($admin)->get("/marketing-report/schedules-export?{$range}")->assertOk();
    }

    public function test_member_without_permission_is_blocked(): void
    {
        // 통계 조회 권한만 있는 팀 — 페이지는 보이지만 엑셀 출력은 차단, 버튼도 숨김
        $member = $this->memberWith(['stats.view']);
        $range = 'from='.now()->startOfMonth()->format('Y-m-d').'&to='.now()->format('Y-m-d');

        $this->actingAs($member)->get("/api/dashboard-export/excel?{$range}")->assertForbidden();
        $this->actingAs($member)->get("/marketing-report/schedules-export?{$range}")->assertForbidden();
        $this->actingAs($member)->get("/marketing-report/schedules-export-raw?{$range}")->assertForbidden();

        $res = $this->actingAs($member)->get('/marketing-report')->assertOk();
        $this->assertStringNotContainsString('drgoDownload(this.href', $res->getContent()); // 출력 버튼 미노출
    }

    public function test_member_with_export_permission_can_export(): void
    {
        // 콘텐츠 기획팀처럼 팀 관리에서 '엑셀 출력' 권한을 부여한 팀
        $member = $this->memberWith(['stats.view', 'stats.export']);
        $range = 'from='.now()->startOfMonth()->format('Y-m-d').'&to='.now()->format('Y-m-d');

        $this->actingAs($member)->get("/api/dashboard-export/excel?{$range}")->assertOk();
        $this->actingAs($member)->get("/marketing-report/schedules-export?{$range}")->assertOk();
        $this->actingAs($member)->get('/marketing-report')->assertOk()->assertSee('drgoDownload(this.href', false);
    }

    public function test_team_admin_page_offers_export_permission(): void
    {
        $master = User::factory()->create(['role' => 'master']);

        $this->actingAs($master)->get('/admin')->assertOk()
            ->assertSee("key: 'stats.export'", false)
            ->assertSee('엑셀 출력');
    }
}
