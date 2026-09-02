<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 장비 항목 즉석 추가 — 프로젝트 페이지에서 입력 방식(토글/체크박스/수기입력 등)을 골라 필드 정의 생성 */
class EquipmentFieldAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_toggle_equipment_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/admin/project-fields', [
            'label' => '캡처보드', 'type' => 'toggle',
            'section' => 'equipment', 'subsection' => '주변기기',
            'has_quantity' => true, 'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('type', 'toggle')
            ->assertJsonPath('section', 'equipment');

        $f = ProjectFieldDefinition::first();
        $this->assertNotEmpty($f->key);
        $this->assertTrue($f->has_quantity);
    }

    public function test_member_cannot_add_equipment_field(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->postJson('/api/admin/project-fields', [
            'label' => '캡처보드', 'type' => 'toggle', 'section' => 'equipment',
        ])->assertForbidden();
    }

    public function test_invalid_type_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/admin/project-fields', [
            'label' => '캡처보드', 'type' => 'switch', 'section' => 'equipment',
        ])->assertStatus(422);
    }

    public function test_toggle_value_saves_into_project_custom_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ProjectFieldDefinition::create(['key' => 'capture_board', 'label' => '캡처보드', 'type' => 'toggle', 'section' => 'equipment']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '테스트 프로젝트']);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'custom_data' => ['capture_board' => true],
        ])->assertOk();

        $this->assertTrue((bool) $project->fresh()->custom_data['capture_board']);
    }

    public function test_client_detail_shows_toggle_on_and_hides_toggle_off(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ProjectFieldDefinition::create(['key' => 'capture_board', 'label' => '캡처보드', 'type' => 'toggle', 'section' => 'equipment']);
        ProjectFieldDefinition::create(['key' => 'prompter', 'label' => '프롬프터', 'type' => 'toggle', 'section' => 'equipment']);
        ProjectFieldDefinition::create(['key' => 'chroma', 'label' => '크로마키', 'type' => 'toggle', 'section' => 'equipment', 'has_quantity' => true]);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '테스트 프로젝트', 'custom_data' => [
            'capture_board' => true,
            'prompter' => false,                       // 없음 → 표시 안 함
            'chroma' => ['value' => false, 'qty' => 1], // 수량형 없음 → 표시 안 함
        ]]);

        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $labels = collect($res['last_project_equipment']['fields'] ?? [])->pluck('label');
        $this->assertTrue($labels->contains('캡처보드'));
        $this->assertFalse($labels->contains('프롬프터'));
        $this->assertFalse($labels->contains('크로마키'));
    }

    public function test_client_detail_exposes_per_project_equipment(): void
    {
        // 한 의뢰자가 여러 장소(프로젝트)에 세팅 — 캘린더가 연결 프로젝트 기준으로 쓰도록 프로젝트별 장비 제공
        $admin = User::factory()->create(['role' => 'admin']);
        ProjectFieldDefinition::create(['key' => 'capture_board', 'label' => '캡처보드', 'type' => 'toggle', 'section' => 'equipment']);
        ProjectFieldDefinition::create(['key' => 'prompter', 'label' => '프롬프터', 'type' => 'toggle', 'section' => 'equipment']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $p1 = Project::create(['client_id' => $client->id, 'name' => '집 세팅', 'custom_data' => ['capture_board' => true]]);
        $p2 = Project::create(['client_id' => $client->id, 'name' => '작업실 세팅', 'custom_data' => ['prompter' => true]]);
        $p3 = Project::create(['client_id' => $client->id, 'name' => '장비 미입력 세팅']);
        $p1->forceFill(['created_at' => now()->subDays(2)])->save();
        $p2->forceFill(['created_at' => now()->subDay()])->save();

        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $byId = collect($res['projects'])->keyBy('id');
        $this->assertSame(['캡처보드'], collect($byId[$p1->id]['equipment']['fields'])->pluck('label')->all());
        $this->assertSame(['프롬프터'], collect($byId[$p2->id]['equipment']['fields'])->pluck('label')->all());
        $this->assertNull($byId[$p3->id]['equipment']); // 장비 없는 프로젝트는 null

        // 폴백(last_project_equipment) — 최신(미입력)은 장비가 없으므로 장비 있는 가장 최근(작업실) 기준
        $this->assertSame('작업실 세팅', $res['last_project_equipment']['project_name']);

        // 캘린더 JS — 연결 프로젝트 우선 로직 렌더 확인
        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('sel.equipment', false)
            ->assertSee('최신 프로젝트 기준', false);

        // 의뢰자 상세 — 프로젝트 선택 탭 렌더 확인
        $this->actingAs($admin)->get('/clients')->assertOk()
            ->assertSee('cvEqSelect', false)
            ->assertSee('cv-eqtabs', false);
    }

    public function test_project_page_contains_item_edit_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '테스트 프로젝트']);

        $this->actingAs($admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSee('장비 항목 편집')
            ->assertSee('pfaOverlay', false)
            ->assertSee('__equip_items', false)
            ->assertSee('pcfToggleChange', false)
            // 저장 상태 배지 + 수동 저장 버튼 (자동 저장 확인 UI)
            ->assertSee('pcfSaveBadge', false)
            ->assertSee('pcfSaveNow', false);
    }

    public function test_local_equipment_item_applies_only_to_its_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $clientA = Client::create(['name' => 'A의뢰자', 'grade' => 'normal']);
        $clientB = Client::create(['name' => 'B의뢰자', 'grade' => 'normal']);
        $projectA = Project::create(['client_id' => $clientA->id, 'name' => 'A프로젝트']);
        Project::create(['client_id' => $clientB->id, 'name' => 'B프로젝트', 'custom_data' => ['other' => 'x']]);

        // 프로젝트 A에만 항목 정의 + 값 저장 (custom_data 경유 — 전역 필드 정의 생성 없음)
        $this->actingAs($admin)->patchJson("/api/projects/{$projectA->id}", [
            'custom_data' => [
                '__equip_items' => [[
                    'key' => 'loc_test1', 'label' => '캡처보드', 'type' => 'toggle',
                    'subsection' => '주변기기', 'has_quantity' => false,
                ]],
                'loc_test1' => true,
            ],
        ])->assertOk();

        $this->assertSame(0, ProjectFieldDefinition::count()); // 전역 정의는 생성되지 않음

        // A 의뢰자 장비 연동에는 표시
        $resA = $this->actingAs($admin)->getJson("/api/clients/{$clientA->id}/detail")->assertOk()->json();
        $labelsA = collect($resA['last_project_equipment']['fields'] ?? [])->pluck('label');
        $this->assertTrue($labelsA->contains('캡처보드'));

        // B 의뢰자에는 없음 (프로젝트별 적용)
        $resB = $this->actingAs($admin)->getJson("/api/clients/{$clientB->id}/detail")->assertOk()->json();
        $labelsB = collect($resB['last_project_equipment']['fields'] ?? [])->pluck('label');
        $this->assertFalse($labelsB->contains('캡처보드'));
    }
}
