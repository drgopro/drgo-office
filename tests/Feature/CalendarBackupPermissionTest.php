<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 JSON/iCal 백업·가져오기 — 관리자(master/admin) 전용 */
class CalendarBackupPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_master_can_export(): void
    {
        foreach (['master', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/api/events/export/json')->assertOk();
            $this->actingAs($user)->get('/api/events/export/ical')->assertOk();
        }
    }

    public function test_member_cannot_export_even_with_backup_team_permission(): void
    {
        // 팀 권한에 calendar.backup이 있어도 관리자 미만이면 차단
        $team = Team::create(['name' => '테스트팀', 'slug' => 'test-team', 'permissions' => ['calendar.view', 'calendar.edit', 'calendar.backup']]);
        $member = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);

        $this->actingAs($member)->get('/api/events/export/json')->assertForbidden();
        $this->actingAs($member)->get('/api/events/export/ical')->assertForbidden();
        $this->actingAs($member)->post('/api/events/import/json')->assertForbidden();
        $this->actingAs($member)->post('/api/events/import/ical')->assertForbidden();
    }
}
