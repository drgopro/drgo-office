<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 세팅 장소 — 저장/비우기 + 의뢰자 상세(캘린더 연동)에 노출 */
class ProjectAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_address_can_be_saved_and_cleared(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal', 'address' => '서울 강남구 A로 1']);
        $project = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'stage' => 'consulting']);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'address' => '경기 성남시 분당구 B로 91', 'address_detail' => '403호',
        ])->assertSuccessful();
        $this->assertSame('경기 성남시 분당구 B로 91', $project->fresh()->address);
        $this->assertSame('403호', $project->fresh()->address_detail);

        // 의뢰자 상세(캘린더 연동)에 프로젝트 주소 포함
        $detail = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail");
        $detail->assertOk()
            ->assertJsonPath('projects.0.address', '경기 성남시 분당구 B로 91')
            ->assertJsonPath('projects.0.address_detail', '403호')
            ->assertJsonPath('address', '서울 강남구 A로 1');

        // 비우기
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'address' => null, 'address_detail' => null,
        ])->assertSuccessful();
        $this->assertNull($project->fresh()->address);
    }
}
