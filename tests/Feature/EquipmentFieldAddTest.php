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

    public function test_project_page_contains_add_item_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '테스트 프로젝트']);

        $this->actingAs($admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSee('장비 항목 추가')
            ->assertSee('pfaOverlay', false)
            ->assertSee('pcfToggleChange', false);
    }
}
