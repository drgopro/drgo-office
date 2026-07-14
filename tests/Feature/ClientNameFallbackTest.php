<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 이름/닉네임 중 하나만 있을 때 null 대신 있는 값으로 대체 표시되는지 */
class ClientNameFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function nicknameOnlyClient(): Client
    {
        return Client::create(['nickname' => '고블린', 'name' => null, 'grade' => 'normal']);
    }

    public function test_client_show_page_falls_back_to_nickname(): void
    {
        $client = $this->nicknameOnlyClient();

        $res = $this->actingAs($this->admin())->get("/clients/{$client->id}");

        $res->assertOk();
        $this->assertStringContainsString(
            '<div class="client-name">고블린</div>',
            $res->getContent(),
            '이름이 없으면 닉네임을 대표 이름으로 표시'
        );
    }

    public function test_projects_index_falls_back_to_nickname(): void
    {
        $client = $this->nicknameOnlyClient();
        Project::create(['client_id' => $client->id, 'name' => '테스트 프로젝트']);

        $res = $this->actingAs($this->admin())->get('/projects');

        $res->assertOk()->assertSee('고블린');
        // 이름이 없을 때 빈 링크 + "(닉네임)" 형태가 되지 않아야 함
        $this->assertStringNotContainsString('(고블린)', $res->getContent());
    }

    public function test_rental_and_broadcast_pages_render_fallback_script(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/rental-contracts')
            ->assertOk()
            ->assertSee("c.client_name || c.client_nickname || '-'", false);

        $this->actingAs($admin)->get('/broadcast-room')
            ->assertOk()
            ->assertSee("c.client_name || c.client_nickname || '-'", false);
    }
}
