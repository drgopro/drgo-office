<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 렌탈(장비 위치·렌탈 계약)/방송룸 — 팀 관리의 전용 권한으로 분리 */
class RentalBroadcastPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $team = Team::create(['name' => '팀'.uniqid(), 'slug' => 'team-'.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);
    }

    public function test_rental_pages_require_rental_view(): void
    {
        // 기존 접근 경로였던 재고/의뢰자 권한만으로는 더 이상 접근 불가
        $user = $this->userWith(['inventory.view', 'inventory.edit', 'clients.view', 'clients.edit']);
        $this->actingAs($user)->get('/rental-equipment')->assertForbidden();
        $this->actingAs($user)->getJson('/api/rental/board')->assertForbidden();
        $this->actingAs($user)->get('/rental-contracts')->assertForbidden();
        $this->actingAs($user)->get('/broadcast-room')->assertForbidden();

        $viewer = $this->userWith(['rental.view', 'broadcast.view']);
        $this->actingAs($viewer)->getJson('/api/rental-contracts')->assertOk();
        $this->actingAs($viewer)->getJson('/api/broadcast-room/contracts')->assertOk();
        // 조회 권한만으로는 편집 불가
        $this->actingAs($viewer)->postJson('/api/rental-contracts', [])->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/broadcast-room/usages', [])->assertForbidden();
    }

    public function test_admin_always_allowed(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $this->actingAs($admin)->getJson('/api/rental-contracts')->assertOk();
        $this->actingAs($admin)->getJson('/api/broadcast-room/contracts')->assertOk();
    }
}
