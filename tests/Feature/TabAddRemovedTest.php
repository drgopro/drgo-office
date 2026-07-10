<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabAddRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_bar_renders_without_add_button_and_menu(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk();
        $response->assertSee('id="tabStrip"', false);
        $response->assertDontSee('tabAddBtn', false);
        $response->assertDontSee('id="tabMenu"', false);
        $response->assertDontSee('closeMenu', false);
    }
}
