<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 상세 견적서 탭 — client_id 연동 생성, temp 제외 표시 */
class ClientEstimateLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_with_client_id_prefills_client_link(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'name' => '홍길동', 'phone' => '010-1234-5678', 'grade' => 'normal']);

        $res = $this->actingAs($user)->postJson('/api/estimates', ['client_id' => $client->id])->assertCreated();
        $estimate = Estimate::find($res->json('id'));
        $this->assertSame($client->id, $estimate->client_id);
        $this->assertSame('고블린', $estimate->client_nickname);
        $this->assertSame('홍길동', $estimate->client_name);

        // client_id 없이도 기존대로 생성
        $this->actingAs($user)->postJson('/api/estimates')->assertCreated();
    }

    public function test_client_tab_lists_saved_estimates_but_hides_temp(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Estimate::create(['client_id' => $client->id, 'status' => 'temp', 'total_amount' => 0, 'created_by' => $user->id]);
        Estimate::create(['client_id' => $client->id, 'status' => 'created', 'total_amount' => 100000, 'created_by' => $user->id]);

        $this->assertSame(1, $client->fresh()->estimates->count()); // temp 제외

        // 상세 페이지 표시 + 목록 페이지 딥링크(client_id 자동 생성) 스크립트 렌더
        $this->actingAs($user)->get("/clients/{$client->id}")->assertOk()->assertSee('견적서 (1건)');
        $this->actingAs($user)->get('/estimates')->assertOk()->assertSee('createEstimate(qcid)', false);
        $this->actingAs($user)->get('/clients')->assertOk()->assertSee('createEstimateForClient', false);
    }
}
