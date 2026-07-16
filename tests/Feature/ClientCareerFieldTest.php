<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 방송 경력 필드 + 캘린더 연동용 detail 노출 */
class ClientCareerFieldTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_create_client_with_career(): void
    {
        $res = $this->actingAs($this->admin())->postJson('/api/clients', [
            'nickname' => '옴니버', 'grade' => 'normal', 'career' => '경력',
        ]);

        $res->assertCreated();
        $this->assertSame('경력', Client::first()->career);
    }

    public function test_update_career_and_reject_invalid_value(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        $this->actingAs($this->admin())->patchJson("/api/clients/{$client->id}", [
            'nickname' => '고블린', 'grade' => 'normal', 'career' => '초보',
        ])->assertOk();
        $this->assertSame('초보', $client->fresh()->career);

        $this->actingAs($this->admin())->patchJson("/api/clients/{$client->id}", [
            'nickname' => '고블린', 'grade' => 'normal', 'career' => '베테랑',
        ])->assertUnprocessable();
    }

    public function test_detail_exposes_career_budget_style_and_inflow_for_calendar_autofill(): void
    {
        $client = Client::create([
            'nickname' => '옴니버', 'grade' => 'normal',
            'career' => '처음', 'budget_style' => '최고급 사양 선호', 'inflow_source' => 'ad',
        ]);

        $this->actingAs($this->admin())->getJson("/api/clients/{$client->id}/detail")
            ->assertOk()
            ->assertJsonPath('career', '처음')
            ->assertJsonPath('budget_style', '최고급 사양 선호')
            ->assertJsonPath('inflow_source', 'ad');
    }
}
