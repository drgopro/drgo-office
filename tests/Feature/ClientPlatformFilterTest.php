<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 목록 — 플랫폼 필터 */
class ClientPlatformFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_filter_returns_matching_clients_only(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Client::create(['nickname' => '숲고블린', 'grade' => 'normal', 'platforms' => ['SOOP', '유튜브']]);
        Client::create(['nickname' => '치지직고블린', 'grade' => 'vip', 'platforms' => ['치지직']]);
        Client::create(['nickname' => '무플랫폼', 'grade' => 'normal']);

        $res = $this->actingAs($user)->getJson('/api/clients/list?platform=SOOP')->assertOk();
        $this->assertSame(['숲고블린'], collect($res->json('data'))->pluck('nickname')->all());

        // 등급 필터와 조합
        $res = $this->actingAs($user)->getJson('/api/clients/list?platform=치지직&grade=vip')->assertOk();
        $this->assertSame(['치지직고블린'], collect($res->json('data'))->pluck('nickname')->all());
        $this->actingAs($user)->getJson('/api/clients/list?platform=치지직&grade=normal')
            ->assertOk()->assertJsonCount(0, 'data');

        // 필터 미지정 시 전체
        $this->actingAs($user)->getJson('/api/clients/list')->assertOk()->assertJsonCount(3, 'data');

        // 목록 화면에 플랫폼 칩 렌더
        $this->actingAs($user)->get('/clients')->assertOk()
            ->assertSee('setPlatformFilter', false)
            ->assertSee('팬더티비');
    }
}
