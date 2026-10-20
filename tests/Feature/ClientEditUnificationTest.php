<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 수정/등록 — 구(舊) 폼 페이지를 기준 화면(상세 수정 모드·등록 모달)으로 통합 */
class ClientEditUnificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_edit_page_redirects_to_detail_edit_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '유라', 'grade' => 'normal']);

        $this->actingAs($admin)->get("/clients/{$client->id}/edit")
            ->assertRedirect("/clients?open={$client->id}&edit=1");
    }

    public function test_legacy_create_page_redirects_to_list_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients/create')->assertRedirect('/clients?new=1');
    }

    public function test_index_handles_edit_and_new_deep_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients')->assertOk()
            ->assertSee("urlParams.get('edit')", false)
            ->assertSee("urlParams.get('new')", false);
    }

    public function test_legacy_show_page_redirects_to_detail_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '유라', 'grade' => 'normal']);

        // 캘린더/프로젝트 등 어디서 /clients/{id}를 열어도 기준 화면(상세 탭)으로
        $this->actingAs($admin)->get("/clients/{$client->id}")
            ->assertRedirect("/clients?open={$client->id}");
    }
}
