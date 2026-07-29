<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 장비 항목 정의(__equip_items) — 관리자 이상만 변경 가능, 멤버는 값 입력만 */
class ProjectEquipItemsPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** projects.edit 권한이 있는 일반 멤버 */
    private function member(): User
    {
        $team = Team::create(['name' => '편집팀', 'slug' => 'edit-team', 'permissions' => ['projects.view', 'projects.edit']]);

        return User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
    }

    /** @return array{0: Project, 1: array<int, array<string, mixed>>} */
    private function projectWithEquipItems(): array
    {
        $items = [['key' => 'loc_cam', 'label' => '캡처보드', 'type' => 'toggle']];
        $project = Project::create([
            'name' => '장비 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
            'custom_data' => ['__equip_items' => $items, 'loc_cam' => true],
        ]);

        return [$project, $items];
    }

    public function test_admin_can_change_equip_item_definitions(): void
    {
        [$project] = $this->projectWithEquipItems();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'custom_data' => ['__equip_items' => [['key' => 'loc_new', 'label' => '프롬프터', 'type' => 'toggle']]],
        ])->assertOk();

        $this->assertSame('프롬프터', $project->fresh()->custom_data['__equip_items'][0]['label']);
    }

    public function test_member_cannot_change_equip_item_definitions_but_can_save_values(): void
    {
        [$project, $items] = $this->projectWithEquipItems();
        $member = $this->member();

        // 멤버 저장: 값(loc_cam)은 반영, 정의(__equip_items) 변조는 무시되고 기존 유지
        $this->actingAs($member)->patchJson("/api/projects/{$project->id}", [
            'custom_data' => [
                '__equip_items' => [['key' => 'loc_hack', 'label' => '변조 항목', 'type' => 'toggle']],
                'loc_cam' => false,
            ],
        ])->assertOk();

        $fresh = $project->fresh();
        $this->assertSame($items, $fresh->custom_data['__equip_items']);
        $this->assertFalse($fresh->custom_data['loc_cam']);
    }

    public function test_member_cannot_inject_equip_items_when_none_exist(): void
    {
        $project = Project::create(['name' => '빈 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting']);
        $member = $this->member();

        $this->actingAs($member)->patchJson("/api/projects/{$project->id}", [
            'custom_data' => ['__equip_items' => [['key' => 'loc_x', 'label' => '주입', 'type' => 'toggle']]],
        ])->assertOk();

        $this->assertArrayNotHasKey('__equip_items', $project->fresh()->custom_data ?? []);
    }
}
