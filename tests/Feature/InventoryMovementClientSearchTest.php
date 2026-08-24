<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 입출고 등록 — 의뢰자 검색으로 연결 프로젝트를 고르는 방식 (캘린더와 동일 흐름) */
class InventoryMovementClientSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_clients_with_active_projects_only(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '엑셀방송', 'name' => '황진선', 'phone' => '010-1111-2222', 'grade' => 'normal', 'status' => 'active']);
        $client->contacts()->create(['name' => '김매니저', 'phone' => '010-3333-4444', 'relation' => '매니저']);
        $active = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
        Project::create(['client_id' => $client->id, 'name' => '끝난 프로젝트', 'project_type' => 'visit', 'stage' => 'completed', 'completed_at' => now()]);

        $res = $this->actingAs($user)->getJson('/api/inventory/movement-clients?q='.urlencode('엑셀'))->assertOk()->json();
        $this->assertCount(1, $res);
        $this->assertSame('황진선', $res[0]['name']);
        $this->assertArrayNotHasKey('phone', $res[0]); // 연락처는 반환하지 않음
        $this->assertCount(1, $res[0]['projects']); // 완료 프로젝트 제외
        $this->assertSame($active->id, $res[0]['projects'][0]['id']);
        $this->assertNotEmpty($res[0]['projects'][0]['stage_label']);

        // 관계자(매니저) 이름으로도 검색 매칭
        $byContact = $this->actingAs($user)->getJson('/api/inventory/movement-clients?q='.urlencode('김매니저'))->assertOk()->json();
        $this->assertCount(1, $byContact);

        // 빈 검색어 — 빈 결과
        $this->assertSame([], $this->actingAs($user)->getJson('/api/inventory/movement-clients?q=')->assertOk()->json());
    }

    public function test_search_requires_inventory_view_permission(): void
    {
        $team = Team::create(['name' => '재고팀', 'slug' => 'inv-team', 'permissions' => ['inventory.view']]);
        $invUser = User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);
        $this->actingAs($invUser)->getJson('/api/inventory/movement-clients?q=a')->assertOk();

        $noPerm = User::factory()->create(['role' => 'staff']);
        $this->actingAs($noPerm)->getJson('/api/inventory/movement-clients?q=a')->assertForbidden();
    }
}
