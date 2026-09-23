<?php

namespace Tests\Feature;

use App\Models\EstimatePreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적 프리셋 순서 변경 — 빌더 프리셋 패널 드래그 정렬 */
class EstimatePresetReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makePreset(string $title): EstimatePreset
    {
        return EstimatePreset::create([
            'title' => $title,
            'items' => [['name' => '품목', 'sale_price' => 1000, 'qty' => 1]],
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_reorder_persists_and_index_returns_saved_order(): void
    {
        $a = $this->makePreset('A');
        $b = $this->makePreset('B');
        $c = $this->makePreset('C');

        // 기본(미정렬): 최근 수정순 — C, B, A
        $titles = collect($this->actingAs($this->admin)->getJson('/api/estimate-presets')->json())->pluck('title');
        $this->assertSame(['C', 'B', 'A'], $titles->all());

        // 드래그 정렬 저장: B, A, C
        $this->actingAs($this->admin)->postJson('/api/estimate-presets/reorder', [
            'ids' => [$b->id, $a->id, $c->id],
        ])->assertOk();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $c->fresh()->sort_order);

        $titles = collect($this->actingAs($this->admin)->getJson('/api/estimate-presets')->json())->pluck('title');
        $this->assertSame(['B', 'A', 'C'], $titles->all());
    }

    public function test_new_preset_appears_first_until_placed(): void
    {
        $a = $this->makePreset('A');
        $b = $this->makePreset('B');
        $this->actingAs($this->admin)->postJson('/api/estimate-presets/reorder', ['ids' => [$a->id, $b->id]])->assertOk();

        // 정렬 이후 새로 만든 프리셋(sort_order=0)은 배치 전까지 맨 위
        $this->makePreset('신규');
        $titles = collect($this->actingAs($this->admin)->getJson('/api/estimate-presets')->json())->pluck('title');
        $this->assertSame(['신규', 'A', 'B'], $titles->all());
    }

    public function test_reorder_requires_edit_permission_and_validates_ids(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->postJson('/api/estimate-presets/reorder', ['ids' => [1]])->assertForbidden();

        $this->actingAs($this->admin)->postJson('/api/estimate-presets/reorder', ['ids' => 'x'])->assertStatus(422);
    }
}
