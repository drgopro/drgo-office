<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 장비 불러오기 — 의뢰자 대표 장비 또는 같은 의뢰자의 이전 프로젝트 장비를
 * 새 프로젝트에 복사하는 기능.
 */
class ProjectEquipmentImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function equipField(string $key, string $label): ProjectFieldDefinition
    {
        return ProjectFieldDefinition::create([
            'key' => $key, 'label' => $label, 'type' => 'text', 'section' => 'equipment', 'is_active' => true,
        ]);
    }

    public function test_sources_lists_client_projects_with_equipment(): void
    {
        $this->equipField('mic', '마이크');
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $old = Project::create(['client_id' => $client->id, 'name' => '이전 세팅', 'custom_data' => ['mic' => 'SM7B']]);
        Project::create(['client_id' => $client->id, 'name' => '장비 없는 건']); // 장비 미입력 — 목록 제외
        $new = Project::create(['client_id' => $client->id, 'name' => '새 프로젝트']);

        $res = $this->actingAs($this->admin())->getJson("/api/projects/{$new->id}/equipment-import-sources")->assertOk();
        $data = $res->json();

        $this->assertSame($old->id, $data['client']['id']);
        $this->assertCount(1, $data['projects']);
        $this->assertSame('이전 세팅', $data['projects'][0]['name']);
        $this->assertSame(1, $data['projects'][0]['count']);
    }

    public function test_sources_prefers_pinned_equipment_project(): void
    {
        $this->equipField('mic', '마이크');
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $oldest = Project::create(['client_id' => $client->id, 'name' => '고정된 건', 'custom_data' => ['mic' => 'NT1'], 'created_at' => now()->subDays(5)]);
        Project::create(['client_id' => $client->id, 'name' => '최신 건', 'custom_data' => ['mic' => 'SM7B']]);
        $client->update(['equipment_project_id' => $oldest->id]);
        $new = Project::create(['client_id' => $client->id, 'name' => '새 프로젝트']);

        $this->actingAs($this->admin())->getJson("/api/projects/{$new->id}/equipment-import-sources")
            ->assertOk()
            ->assertJsonPath('client.id', $oldest->id)
            ->assertJsonCount(2, 'projects');
    }

    public function test_sources_rejects_project_without_client(): void
    {
        $project = Project::create(['name' => '미연동']);

        $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/equipment-import-sources")
            ->assertStatus(422);
    }

    public function test_import_copies_equipment_values_and_local_defs(): void
    {
        $this->equipField('mic', '마이크');
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $source = Project::create([
            'client_id' => $client->id, 'name' => '이전 세팅',
            'custom_data' => [
                'mic' => [['value' => 'SM7B', 'qty' => 2]],
                'local_cam' => '소니 ZV-E10',
                '__equip_items' => [['key' => 'local_cam', 'label' => '카메라', 'type' => 'text']],
                'etc_note' => '장비 아님 — 복사 금지', // 장비 섹션 키가 아니므로 복사 안 됨
            ],
        ]);
        $target = Project::create(['client_id' => $client->id, 'name' => '새 프로젝트', 'custom_data' => ['mic' => '기존값']]);

        $res = $this->actingAs($this->admin())->postJson("/api/projects/{$target->id}/equipment-import", [
            'source_project_id' => $source->id,
        ])->assertOk();

        $this->assertSame(2, $res->json('imported'));
        $target->refresh();
        $custom = $target->custom_data;
        $this->assertSame([['value' => 'SM7B', 'qty' => 2]], $custom['mic']); // 같은 항목은 덮어씀
        $this->assertSame('소니 ZV-E10', $custom['local_cam']);
        $this->assertSame('local_cam', $custom['__equip_items'][0]['key']); // 전용 항목 정의도 복사
        $this->assertArrayNotHasKey('etc_note', $custom);
    }

    public function test_import_rejects_other_clients_project_and_empty_source(): void
    {
        $this->equipField('mic', '마이크');
        $clientA = Client::create(['nickname' => 'A', 'grade' => 'normal']);
        $clientB = Client::create(['nickname' => 'B', 'grade' => 'normal']);
        $otherClients = Project::create(['client_id' => $clientB->id, 'name' => '남의 건', 'custom_data' => ['mic' => 'X']]);
        $empty = Project::create(['client_id' => $clientA->id, 'name' => '빈 건']);
        $target = Project::create(['client_id' => $clientA->id, 'name' => '새 프로젝트']);

        $this->actingAs($this->admin())->postJson("/api/projects/{$target->id}/equipment-import", [
            'source_project_id' => $otherClients->id,
        ])->assertStatus(422);

        $this->actingAs($this->admin())->postJson("/api/projects/{$target->id}/equipment-import", [
            'source_project_id' => $empty->id,
        ])->assertStatus(422);

        // 자기 자신에서 불러오기도 거부
        $this->actingAs($this->admin())->postJson("/api/projects/{$target->id}/equipment-import", [
            'source_project_id' => $target->id,
        ])->assertStatus(422);
    }

    public function test_show_page_renders_import_button_only_for_linked_project(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $linked = Project::create(['client_id' => $client->id, 'name' => '연동 건']);
        $unlinked = Project::create(['name' => '미연동 건']);
        $admin = $this->admin();

        $this->actingAs($admin)->get("/projects/{$linked->id}")->assertOk()
            ->assertSee('openEquipImport', false)
            ->assertSee('id="eqImportOverlay"', false);
        $this->actingAs($admin)->get("/projects/{$unlinked->id}")->assertOk()
            ->assertDontSee('id="eqImportOverlay"', false);
    }
}
