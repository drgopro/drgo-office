<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAssigneeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_eligible_user_creates_active_assignee(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $assignee = Assignee::firstWhere('user_id', $user->id);

        $this->assertNotNull($assignee);
        $this->assertTrue($assignee->is_active);
        $this->assertSame($user->display_name, $assignee->name);
    }

    public function test_creating_guest_user_does_not_create_assignee(): void
    {
        $user = User::factory()->create(['role' => 'guest', 'is_active' => true]);

        $this->assertNull(Assignee::firstWhere('user_id', $user->id));
    }

    public function test_deactivating_user_deactivates_assignee(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $user->update(['is_active' => false]);

        $assignee = Assignee::firstWhere('user_id', $user->id);
        $this->assertNotNull($assignee);
        $this->assertFalse($assignee->is_active);
    }

    public function test_reactivating_user_reactivates_assignee(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $user->update(['is_active' => false]);

        $user->update(['is_active' => true]);

        $this->assertTrue(Assignee::firstWhere('user_id', $user->id)->is_active);
    }

    public function test_changing_role_to_guest_deactivates_assignee(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $user->update(['role' => 'guest']);

        $this->assertFalse(Assignee::firstWhere('user_id', $user->id)->is_active);
    }

    public function test_assignees_index_does_not_write_and_returns_active_only(): void
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $external = Assignee::create(['name' => '외부 담당자', 'is_active' => true, 'display_order' => 0]);
        Assignee::create(['name' => '비활성 담당자', 'is_active' => false, 'display_order' => 0]);

        $response = $this->actingAs($user)->getJson('/api/assignees');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains($user->display_name));
        $this->assertTrue($names->contains($external->name));
        $this->assertFalse($names->contains('비활성 담당자'));
    }
}
