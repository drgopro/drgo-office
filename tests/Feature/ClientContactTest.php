<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 관계자(매니저/실장 등) — CRUD, 10명 제한, 검색 매칭 */
class ClientContactTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '에이몬드', 'name' => '김에이', 'phone' => '01011112222', 'grade' => 'normal']);
    }

    public function test_contact_crud(): void
    {
        $res = $this->actingAs($this->master)->postJson("/api/clients/{$this->client->id}/contacts", [
            'name' => '김실장', 'phone' => '010-9999-8888', 'relation' => '실장', 'memo' => '주 연락 창구',
        ])->assertCreated();
        $id = $res->json('id');

        // 상세 페이로드에 포함
        $detail = $this->actingAs($this->master)->getJson("/api/clients/{$this->client->id}/detail")->assertOk();
        $this->assertSame('김실장', $detail->json('contacts.0.name'));
        $this->assertSame('실장', $detail->json('contacts.0.relation'));

        // 수정
        $this->actingAs($this->master)->patchJson("/api/client-contacts/{$id}", [
            'name' => '김실장', 'relation' => '매니저',
        ])->assertOk();
        $this->assertSame('매니저', ClientContact::find($id)->relation);

        // 삭제
        $this->actingAs($this->master)->deleteJson("/api/client-contacts/{$id}")->assertOk();
        $this->assertSame(0, ClientContact::count());
    }

    public function test_contact_limit_is_ten_per_client(): void
    {
        foreach (range(1, 10) as $i) {
            $this->client->contacts()->create(['name' => "매니저{$i}"]);
        }

        $this->actingAs($this->master)->postJson("/api/clients/{$this->client->id}/contacts", ['name' => '11번째'])
            ->assertStatus(422);
        $this->assertSame(10, $this->client->contacts()->count());
    }

    public function test_client_list_search_matches_contact_name_and_phone(): void
    {
        $this->client->contacts()->create(['name' => '박매니저', 'phone' => '010-7777-6666', 'relation' => '매니저']);
        Client::create(['nickname' => '무관의뢰자', 'grade' => 'normal']);

        $byName = $this->actingAs($this->master)->getJson('/api/clients/list?search='.urlencode('박매니저'))->assertOk();
        $this->assertSame(1, $byName->json('total'));
        $this->assertSame('에이몬드', $byName->json('data.0.nickname'));

        $byPhone = $this->actingAs($this->master)->getJson('/api/clients/list?search=010-7777')->assertOk();
        $this->assertSame(1, $byPhone->json('total'));
    }

    public function test_client_search_api_reports_matched_contact(): void
    {
        $this->client->contacts()->create(['name' => '박매니저', 'phone' => '01077776666', 'relation' => '매니저']);

        $res = $this->actingAs($this->master)->getJson('/api/clients/search?q='.urlencode('박매니저'))->assertOk();
        $this->assertSame($this->client->id, $res->json('0.id'));
        $this->assertSame('박매니저 (매니저)', $res->json('0.matched_contact'));

        // 본인으로 매칭되면 관계자 병기 없음
        $self = $this->actingAs($this->master)->getJson('/api/clients/search?q='.urlencode('에이몬드'))->assertOk();
        $this->assertNull($self->json('0.matched_contact'));
    }

    public function test_contact_write_requires_clients_edit_permission(): void
    {
        $noPerm = User::factory()->create(['role' => 'member']);
        $this->actingAs($noPerm)->postJson("/api/clients/{$this->client->id}/contacts", ['name' => 'x'])
            ->assertForbidden();
    }
}
