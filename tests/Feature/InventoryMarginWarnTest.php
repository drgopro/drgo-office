<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMarginWarnTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_passes_default_margin_warn_percent(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->get('/inventory')
            ->assertOk()
            ->assertViewHas('marginWarnPercent', 20);
    }

    public function test_index_passes_saved_margin_warn_percent(): void
    {
        Setting::set('inventory_margin_warn_percent', 35);
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->get('/inventory')
            ->assertOk()
            ->assertViewHas('marginWarnPercent', 35);
    }

    public function test_save_margin_threshold(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->postJson('/api/inventory/margin-threshold', ['percent' => 30])
            ->assertOk()
            ->assertJson(['percent' => 30]);

        $this->assertSame(30, (int) Setting::get('inventory_margin_warn_percent'));
    }

    public function test_margin_threshold_rejects_out_of_range(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->postJson('/api/inventory/margin-threshold', ['percent' => 100])
            ->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/inventory/margin-threshold', ['percent' => -1])
            ->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/inventory/margin-threshold', ['percent' => 'abc'])
            ->assertUnprocessable();
    }

    public function test_margin_threshold_requires_edit_permission(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->postJson('/api/inventory/margin-threshold', ['percent' => 30])
            ->assertForbidden();
    }
}
