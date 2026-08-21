<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 추가 주소 (주소 2~4) — 주소 1은 기존 address(메인) 유지 */
class ClientExtraAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->create(['role' => 'master']);
        $this->client = Client::create([
            'nickname' => '에이몬드', 'grade' => 'normal',
            'address' => '서울 동작구 장승배기로 142', 'address_detail' => '3층',
        ]);
    }

    public function test_extra_addresses_save_and_appear_in_detail(): void
    {
        $this->actingAs($this->master)->patchJson("/api/clients/{$this->client->id}", [
            'nickname' => '에이몬드', 'grade' => 'normal',
            'address' => '서울 동작구 장승배기로 142',
            'extra_addresses' => [
                ['address' => '서울 구로구 고척로27길 81-22', 'address_detail' => '지하 스튜디오'],
                ['address' => '', 'address_detail' => '주소 없는 행은 제거'],
                ['address' => '경기 광명시 어딘가 12'],
            ],
        ])->assertOk();

        $saved = $this->client->fresh()->extra_addresses;
        $this->assertCount(2, $saved); // 빈 주소 행 제거
        $this->assertSame('서울 구로구 고척로27길 81-22', $saved[0]['address']);
        $this->assertSame('지하 스튜디오', $saved[0]['address_detail']);

        $detail = $this->actingAs($this->master)->getJson("/api/clients/{$this->client->id}/detail")->assertOk();
        $this->assertCount(2, $detail->json('extra_addresses'));
        $this->assertSame('서울 동작구 장승배기로 142', $detail->json('address')); // 메인 주소 유지
    }

    public function test_extra_addresses_limited_to_three(): void
    {
        $this->actingAs($this->master)->patchJson("/api/clients/{$this->client->id}", [
            'nickname' => '에이몬드', 'grade' => 'normal',
            'extra_addresses' => [
                ['address' => 'A'], ['address' => 'B'], ['address' => 'C'], ['address' => 'D'],
            ],
        ])->assertStatus(422); // 주소 1(메인) 포함 총 4개까지 → 추가는 3개까지
    }

    public function test_empty_extra_addresses_saved_as_null(): void
    {
        $this->client->update(['extra_addresses' => [['address' => 'X', 'address_detail' => '']]]);

        $this->actingAs($this->master)->patchJson("/api/clients/{$this->client->id}", [
            'nickname' => '에이몬드', 'grade' => 'normal', 'extra_addresses' => [],
        ])->assertOk();

        $this->assertNull($this->client->fresh()->extra_addresses);
    }
}
