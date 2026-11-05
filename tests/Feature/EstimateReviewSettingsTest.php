<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 검토 기준 관리 — 권한(estimates.review_admin), 설정 저장, 저장값이 검토에 반영되는지.
 */
class EstimateReviewSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    private function sampleConfig(array $overrides = []): array
    {
        return array_merge([
            'version' => 1,
            'checks' => ['video' => false, 'audio' => false, 'lens' => false, 'battery' => false, 'mount' => false, 'spec' => false],
            'types' => ['video' => ['HDMI'], 'audio' => ['XLR'], 'lens' => ['RF'], 'battery' => ['LP-E17'], 'mount' => ['보웬스']],
            'roles' => [
                ['id' => 'pc', 'label' => '컴퓨터', 'group' => '컴퓨터'],
                ['id' => 'monitor', 'label' => '모니터', 'group' => '컴퓨터'],
            ],
            'rules' => [[
                'type' => 'need', 'group' => '컴퓨터', 'id' => 'pc-monitor', 'on' => ['pc'], 'need' => ['monitor'],
                'mode' => 'perUnit', 'amt' => 1, 'what' => '모니터', 'why' => '', 'active' => true,
            ]],
            'categoryRolesByPath' => [],
            'productRolesByName' => ['송출용 PC' => [['pc', 1]]],
            'specTemplatesByPath' => [],
            'specsByName' => [],
            'specsByPath' => [],
        ], $overrides);
    }

    public function test_settings_page_requires_review_admin_permission(): void
    {
        $team = Team::create(['name' => '견적팀', 'slug' => 'estimate-team', 'permissions' => ['estimates.view', 'estimates.edit']]);
        $member = User::factory()->create(['role' => 'member', 'team_id' => $team->id]);

        $this->actingAs($member)->get('/estimate-review/settings')->assertForbidden();
        $this->actingAs($member)->postJson('/api/estimate-review/config', ['config' => $this->sampleConfig()])->assertForbidden();

        $this->actingAs($this->admin)->get('/estimate-review/settings')->assertOk()->assertSee('견적서 검토 기준');

        $team->update(['permissions' => ['estimates.view', 'estimates.edit', 'estimates.review_admin']]);
        $this->actingAs($member->fresh())->get('/estimate-review/settings')->assertOk();
    }

    public function test_saved_config_is_used_by_review(): void
    {
        $pc = Product::create(['sku' => 'PC-1', 'name' => '송출용 PC', 'category' => 'PC', 'sale_price' => 1000000, 'is_active' => 1]);
        $estimate = Estimate::create([
            'status' => 'created', 'service_items' => [],
            'product_items' => [['product_id' => $pc->id, 'name' => '송출용 PC', 'qty' => 1, 'sale_price' => 1000000, 'subtotal' => 1000000]],
            'product_total' => 1000000, 'service_total' => 0, 'total_amount' => 1000000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        // 규칙 켜진 설정 저장 → 모니터 누락 발견
        $this->actingAs($this->admin)->postJson('/api/estimate-review/config', ['config' => $this->sampleConfig()])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/review")
            ->assertOk()->assertJsonPath('findings.0.key', 'pc-monitor:1:0');

        // 규칙을 끄고 저장 → 발견 없음
        $cfg = $this->sampleConfig();
        $cfg['rules'][0]['active'] = false;
        $this->actingAs($this->admin)->postJson('/api/estimate-review/config', ['config' => $cfg])->assertOk();
        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$estimate->id}/review")->assertOk();
        $this->assertSame([], $res->json('findings'));
    }

    public function test_save_rejects_malformed_config(): void
    {
        $this->actingAs($this->admin)->postJson('/api/estimate-review/config', ['config' => ['roles' => 'oops']])
            ->assertStatus(422);

        $cfg = $this->sampleConfig();
        $cfg['rules'][0]['type'] = 'weird';
        $this->actingAs($this->admin)->postJson('/api/estimate-review/config', ['config' => $cfg])->assertStatus(422);
    }
}
