<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 플랫폼별 방송국 주소 — 다중선택한 플랫폼 수만큼 입력·저장·조회 */
class ClientStationAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_store_saves_station_addresses_per_platform(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/clients', [
            'nickname' => '고블린', 'grade' => 'normal',
            'platforms' => ['SOOP', '치지직'],
            'station_addresses' => [
                'SOOP' => 'https://www.sooplive.com/station/webstar2k',
                '치지직' => 'https://chzzk.naver.com/1880f4e68490b369888c122c2848ac7b',
                '유튜브' => '  ', // 빈 값은 저장하지 않음
            ],
        ])->assertCreated();

        $client = Client::find($res->json('id') ?? Client::latest('id')->first()->id);
        $this->assertSame([
            'SOOP' => 'https://www.sooplive.com/station/webstar2k',
            '치지직' => 'https://chzzk.naver.com/1880f4e68490b369888c122c2848ac7b',
        ], $client->station_addresses);
    }

    public function test_update_replaces_station_addresses_and_detail_returns_them(): void
    {
        $client = Client::create([
            'nickname' => '고블린', 'grade' => 'normal',
            'platforms' => ['SOOP'],
            'station_addresses' => ['SOOP' => 'https://www.sooplive.com/station/old'],
        ]);

        $this->actingAs($this->admin)->patchJson("/api/clients/{$client->id}", [
            'nickname' => '고블린', 'grade' => 'normal',
            'platforms' => ['유튜브', '틱톡'],
            'station_addresses' => [
                '유튜브' => 'https://www.youtube.com/@drgoblin',
                '틱톡' => 'https://www.tiktok.com/@drgoblin_official',
            ],
        ])->assertOk();

        $this->assertSame([
            '유튜브' => 'https://www.youtube.com/@drgoblin',
            '틱톡' => 'https://www.tiktok.com/@drgoblin_official',
        ], $client->fresh()->station_addresses);

        // 조회(detail)에도 포함 — 탭 조회 뷰가 이 응답으로 렌더
        $this->actingAs($this->admin)->getJson("/api/clients/{$client->id}/detail")
            ->assertOk()
            ->assertJsonPath('station_addresses.유튜브', 'https://www.youtube.com/@drgoblin')
            ->assertJsonPath('station_addresses.틱톡', 'https://www.tiktok.com/@drgoblin_official');
    }

    public function test_all_empty_station_addresses_saved_as_null(): void
    {
        $client = Client::create([
            'nickname' => '고블린', 'grade' => 'normal',
            'station_addresses' => ['SOOP' => 'https://www.sooplive.com/station/x'],
        ]);

        $this->actingAs($this->admin)->patchJson("/api/clients/{$client->id}", [
            'nickname' => '고블린', 'grade' => 'normal',
            'station_addresses' => ['SOOP' => ''],
        ])->assertOk();

        $this->assertNull($client->fresh()->station_addresses);
    }

    public function test_clients_page_renders_station_address_form_scripts(): void
    {
        // 등록 모달 + 수정 폼 + 조회 뷰에 쓰이는 렌더러/수집기와 플랫폼별 예시 placeholder
        $this->actingAs($this->admin)->get('/clients')->assertOk()
            ->assertSee('id="stationAddrWrap-nc"', false)
            ->assertSee('syncStationAddrInputs', false)
            ->assertSee('collectStationAddresses', false)
            ->assertSee('STATION_ADDR_EXAMPLES', false)
            ->assertSee('https://www.sooplive.com/station/', false)
            ->assertSee('https://www.pandalive.co.kr/', false)
            ->assertSee('방송국 주소');
    }
}
