<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFieldDefinition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 장비정보 피드백 1~4단계 — 요약/편집 모드, 다중 값, 드롭다운 수기 추가 시
 * 선택지 자동 등록, 의뢰자 대표 장비 연동.
 */
class ProjectEquipmentFeedbackTest extends TestCase
{
    use RefreshDatabase;

    /** projects.edit + clients.edit 권한이 있는 일반 멤버 */
    private function member(array $permissions = ['projects.view', 'projects.edit']): User
    {
        $team = Team::create(['name' => '편집팀', 'slug' => 'equip-edit-team', 'permissions' => $permissions]);

        return User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
    }

    // ── 1단계: 요약 표시 + 편집 모드 분리 ──

    public function test_project_page_contains_summary_and_edit_mode_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '장비 요약 테스트']);

        $this->actingAs($admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSee('togglePcfEditMode', false)
            ->assertSee('renderPcfSummary', false)
            ->assertSee('pcfModeBtn', false)
            // 2단계 다중 값 + 3단계 직접 입력 + 4단계 의뢰자 연동 버튼
            ->assertSee('pcfEntAdd', false)
            ->assertSee('pcfCustomOption', false)
            ->assertSee('pcfPinAsClientEquip', false)
            ->assertSee('현재 장비를 의뢰자에 연동');
    }

    public function test_unlinked_project_hides_client_equip_pin_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::create(['name' => '미연동 프로젝트']);

        $this->actingAs($admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertDontSee('현재 장비를 의뢰자에 연동');
    }

    // ── 2단계: 같은 카테고리 다중 값 저장 ──

    public function test_multi_value_entries_round_trip_and_show_in_client_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ProjectFieldDefinition::create(['key' => 'capture_board', 'label' => '캡처보드', 'type' => 'text', 'section' => 'equipment', 'has_quantity' => true]);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '다중 값 테스트']);

        $entries = [['value' => '외장형', 'qty' => 1], ['value' => '내장형', 'qty' => 2]];
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'custom_data' => ['capture_board' => $entries],
        ])->assertOk();

        $this->assertSame($entries, $project->fresh()->custom_data['capture_board']);

        // 의뢰자 상세 연동에도 엔트리 배열 그대로 전달
        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $field = collect($res['last_project_equipment']['fields'])->firstWhere('label', '캡처보드');
        $this->assertSame('외장형', $field['value'][0]['value']);
        $this->assertSame('내장형', $field['value'][1]['value']);
    }

    public function test_empty_entries_are_filtered_in_client_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ProjectFieldDefinition::create(['key' => 'capture_board', 'label' => '캡처보드', 'type' => 'text', 'section' => 'equipment']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '빈 엔트리', 'custom_data' => [
            'capture_board' => [['value' => '외장형', 'qty' => null], ['value' => '', 'qty' => null]],
        ]]);

        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $field = collect($res['last_project_equipment']['fields'])->firstWhere('label', '캡처보드');
        $this->assertCount(1, $field['value']);

        // 전부 빈 엔트리면 항목 자체가 숨겨짐
        Project::create(['client_id' => $client->id, 'name' => '전부 빈 엔트리', 'custom_data' => [
            'capture_board' => [['value' => '', 'qty' => null]],
        ]]);
        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $this->assertSame('빈 엔트리', $res['last_project_equipment']['project_name']);
    }

    // ── 3단계: 드롭다운 수기 입력 시 선택지 자동 등록 ──

    public function test_member_can_add_option_to_global_equipment_field(): void
    {
        $member = $this->member();
        $field = ProjectFieldDefinition::create([
            'key' => 'mic_type', 'label' => '마이크', 'type' => 'select',
            'section' => 'equipment', 'options' => ['콘덴서'],
        ]);
        $project = Project::create(['name' => '옵션 추가 테스트']);

        $this->actingAs($member)->postJson("/api/projects/{$project->id}/equip-field-options", [
            'key' => 'mic_type', 'value' => '다이나믹',
        ])->assertOk()->assertJsonPath('scope', 'global');

        $this->assertSame(['콘덴서', '다이나믹'], $field->fresh()->options);

        // 중복 추가는 한 번만 유지
        $this->actingAs($member)->postJson("/api/projects/{$project->id}/equip-field-options", [
            'key' => 'mic_type', 'value' => '다이나믹',
        ])->assertOk();
        $this->assertSame(['콘덴서', '다이나믹'], $field->fresh()->options);
    }

    public function test_option_added_to_project_local_item_without_touching_globals(): void
    {
        $member = $this->member();
        $project = Project::create(['name' => '로컬 옵션 테스트', 'custom_data' => [
            '__equip_items' => [['key' => 'loc_cam', 'label' => '캠', 'type' => 'select', 'options' => ['로지텍']]],
        ]]);

        $this->actingAs($member)->postJson("/api/projects/{$project->id}/equip-field-options", [
            'key' => 'loc_cam', 'value' => '소니',
        ])->assertOk()->assertJsonPath('scope', 'local');

        $this->assertSame(['로지텍', '소니'], $project->fresh()->custom_data['__equip_items'][0]['options']);
        $this->assertSame(0, ProjectFieldDefinition::count());
    }

    public function test_unknown_field_key_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::create(['name' => '없는 키']);

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/equip-field-options", [
            'key' => 'no_such_key', 'value' => '값',
        ])->assertNotFound();
    }

    // ── 4단계: 의뢰자 대표 장비 프로젝트 지정 ──

    /** @return array{0: Client, 1: Project, 2: Project} */
    private function clientWithTwoEquippedProjects(): array
    {
        ProjectFieldDefinition::create(['key' => 'capture_board', 'label' => '캡처보드', 'type' => 'toggle', 'section' => 'equipment']);
        ProjectFieldDefinition::create(['key' => 'prompter', 'label' => '프롬프터', 'type' => 'toggle', 'section' => 'equipment']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $old = Project::create(['client_id' => $client->id, 'name' => '집 세팅', 'custom_data' => ['capture_board' => true]]);
        $new = Project::create(['client_id' => $client->id, 'name' => '작업실 세팅', 'custom_data' => ['prompter' => true]]);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $new->forceFill(['created_at' => now()->subDay()])->save();

        return [$client, $old, $new];
    }

    public function test_pinned_project_overrides_latest_in_client_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$client, $old] = $this->clientWithTwoEquippedProjects();

        // 기본값: 최신(작업실) 기준
        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $this->assertSame('작업실 세팅', $res['last_project_equipment']['project_name']);
        $this->assertNull($res['equipment_project_id']);

        // 과거 프로젝트(집)를 대표로 고정
        $this->actingAs($admin)->postJson("/api/clients/{$client->id}/equipment-source", [
            'project_id' => $old->id,
        ])->assertOk()->assertJsonPath('equipment_project_id', $old->id);

        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $this->assertSame('집 세팅', $res['last_project_equipment']['project_name']);
        $this->assertSame($old->id, $res['equipment_project_id']);

        // 해제(null) → 최신 기준 복귀
        $this->actingAs($admin)->postJson("/api/clients/{$client->id}/equipment-source", [
            'project_id' => null,
        ])->assertOk();
        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $this->assertSame('작업실 세팅', $res['last_project_equipment']['project_name']);
    }

    public function test_cannot_pin_other_clients_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$client] = $this->clientWithTwoEquippedProjects();
        $other = Client::create(['nickname' => '다른의뢰자', 'grade' => 'normal']);
        $otherProject = Project::create(['client_id' => $other->id, 'name' => '남의 프로젝트']);

        $this->actingAs($admin)->postJson("/api/clients/{$client->id}/equipment-source", [
            'project_id' => $otherProject->id,
        ])->assertStatus(422);

        $this->assertNull($client->fresh()->equipment_project_id);
    }

    public function test_pinned_project_without_equipment_falls_back_to_latest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$client] = $this->clientWithTwoEquippedProjects();
        $empty = Project::create(['client_id' => $client->id, 'name' => '장비 없는 세팅']);

        $this->actingAs($admin)->postJson("/api/clients/{$client->id}/equipment-source", [
            'project_id' => $empty->id,
        ])->assertOk();

        // 고정 프로젝트에 장비가 없으면 기존 폴백(장비 있는 최신) 유지
        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $this->assertSame('작업실 세팅', $res['last_project_equipment']['project_name']);
    }

    public function test_member_without_clients_edit_cannot_pin(): void
    {
        $member = $this->member(['projects.view', 'projects.edit', 'clients.view']);
        [$client, $old] = $this->clientWithTwoEquippedProjects();

        $this->actingAs($member)->postJson("/api/clients/{$client->id}/equipment-source", [
            'project_id' => $old->id,
        ])->assertForbidden();
    }

    public function test_clients_page_contains_pin_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients')->assertOk()
            ->assertSee('cvEqPin', false)
            ->assertSee('equipment-source', false)
            ->assertSee('대표로 지정', false);
    }
}
