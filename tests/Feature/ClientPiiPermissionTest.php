<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 연락처·주소 조회 권한(clients.pii) — 없으면 응답에서 마스킹, 저장 시 클로버 방지 */
class ClientPiiPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Client::create([
            'nickname' => '피아이아이', 'name' => '홍길동', 'phone' => '010-1234-5678',
            'address' => '서울시 동작구 장승배기로 142', 'address_detail' => '3층',
            'extra_addresses' => [['address' => '서울시 강남구', 'address_detail' => '']],
            'grade' => 'normal', 'status' => 'active',
        ]);
        $this->client->contacts()->create(['name' => '김실장', 'phone' => '010-9999-8888', 'relation' => '실장']);
    }

    private function userWith(array $permissions): User
    {
        $team = Team::create(['name' => '팀'.uniqid(), 'slug' => 'team-'.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role' => 'staff', 'team_id' => $team->id]);
    }

    public function test_without_pii_permission_phone_and_address_are_masked(): void
    {
        $user = $this->userWith(['clients.view']);

        $detail = $this->actingAs($user)->getJson("/api/clients/{$this->client->id}/detail")->assertOk()->json();
        $this->assertNull($detail['phone']);
        $this->assertNull($detail['address']);
        $this->assertNull($detail['address_detail']);
        $this->assertSame([], $detail['extra_addresses']);
        $this->assertFalse($detail['can_view_pii']);
        $this->assertNull($detail['contacts'][0]['phone']);
        $this->assertSame('김실장', $detail['contacts'][0]['name']); // 이름/관계는 계속 표시

        $list = $this->actingAs($user)->getJson('/api/clients/list')->assertOk()->json('data');
        $this->assertNull($list[0]['phone']);

        $search = $this->actingAs($user)->getJson('/api/clients/search?q='.urlencode('홍길동'))->assertOk()->json();
        $this->assertNull($search[0]['phone']);
    }

    public function test_with_pii_permission_values_are_visible(): void
    {
        $user = $this->userWith(['clients.view', 'clients.pii']);

        $detail = $this->actingAs($user)->getJson("/api/clients/{$this->client->id}/detail")->assertOk()->json();
        $this->assertSame('010-1234-5678', $detail['phone']);
        $this->assertSame('서울시 동작구 장승배기로 142', $detail['address']);
        $this->assertTrue($detail['can_view_pii']);
        $this->assertSame('010-9999-8888', $detail['contacts'][0]['phone']);
    }

    public function test_update_without_pii_does_not_clobber_hidden_fields(): void
    {
        // 편집 권한은 있지만 연락처·주소 조회 권한이 없는 팀 — 화면에 빈 값으로 보이는
        // 연락처/주소가 저장 시 지워지지 않아야 함
        $user = $this->userWith(['clients.view', 'clients.edit']);

        $this->actingAs($user)->patchJson("/api/clients/{$this->client->id}", [
            'nickname' => '피아이아이', 'grade' => 'vip',
            'phone' => null, 'address' => null, 'address_detail' => null, 'extra_addresses' => [],
        ])->assertOk();

        $fresh = $this->client->fresh();
        $this->assertSame('vip', $fresh->grade); // 일반 필드는 저장됨
        $this->assertSame('010-1234-5678', $fresh->phone);
        $this->assertSame('서울시 동작구 장승배기로 142', $fresh->address);
        $this->assertNotEmpty($fresh->extra_addresses);

        // 관계자 수정도 연락처는 유지
        $contact = $this->client->contacts()->first();
        $this->actingAs($user)->patchJson("/api/client-contacts/{$contact->id}", [
            'name' => '김실장', 'phone' => null, 'relation' => '총괄',
        ])->assertOk();
        $this->assertSame('010-9999-8888', $contact->fresh()->phone);
        $this->assertSame('총괄', $contact->fresh()->relation);
    }

    public function test_calendar_events_and_detail_mask_address_and_phone_without_pii(): void
    {
        $schedule = Schedule::create([
            'title' => '방문 세팅', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'is_all_day' => true, 'color' => 'gold', 'client_name' => '홍길동',
            'address' => '서울시 동작구 장승배기로 142', 'location' => '서울 동작구',
            'request_data' => ['nickname' => '홍길동', 'phone' => '010-1234-5678', 'move_from_address' => '서울시 강남구 테헤란로 1'],
            'created_by' => User::factory()->create(['role' => 'master'])->id,
        ]);
        $range = '?start='.now()->subDay()->toDateString().'&end='.now()->addDay()->toDateString();

        // 권한 없음 — 캘린더에서도 주소/연락처 마스킹 (지역명 location은 유지)
        $user = $this->userWith(['calendar.view', 'clients.view']);
        $ev = collect($this->actingAs($user)->getJson('/api/events'.$range)->assertOk()->json())->firstWhere('id', $schedule->id);
        $this->assertNull($ev['address']);
        $this->assertSame('', $ev['request_data']['phone']);
        $this->assertSame('', $ev['request_data']['move_from_address']);
        $this->assertSame('서울 동작구', $ev['location']);

        $detail = $this->actingAs($user)->getJson("/api/events/{$schedule->id}/detail")->assertOk()->json();
        $this->assertNull($detail['address']);
        $this->assertSame('', $detail['request_data']['phone']);

        // 권한 있음 — 그대로 표시
        $piiUser = $this->userWith(['calendar.view', 'clients.view', 'clients.pii']);
        $ev2 = collect($this->actingAs($piiUser)->getJson('/api/events'.$range)->assertOk()->json())->firstWhere('id', $schedule->id);
        $this->assertSame('서울시 동작구 장승배기로 142', $ev2['address']);
        $this->assertSame('010-1234-5678', $ev2['request_data']['phone']);
    }

    public function test_backfill_grants_pii_to_existing_view_teams(): void
    {
        $team = Team::create(['name' => '기존팀', 'slug' => 'legacy', 'permissions' => ['clients.view', 'clients.edit', 'inventory.view']]);
        $none = Team::create(['name' => '무관팀', 'slug' => 'none', 'permissions' => ['calendar.view']]);

        $m = require database_path('migrations/2026_08_21_210000_backfill_rental_broadcast_permissions.php');
        $m->up();

        $perms = $team->fresh()->permissions;
        foreach (['clients.pii', 'rental.view', 'rental.edit', 'broadcast.view', 'broadcast.edit'] as $key) {
            $this->assertContains($key, $perms);
        }
        $this->assertSame(['calendar.view'], $none->fresh()->permissions);
    }
}
