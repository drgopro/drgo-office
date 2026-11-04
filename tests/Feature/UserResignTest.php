<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 멤버 퇴사 처리 — 기록 보존 + 로그인/접근 차단 + 담당자 목록 제외 */
class UserResignTest extends TestCase
{
    use RefreshDatabase;

    private function resign(User $admin, User $target): void
    {
        $this->actingAs($admin)->patchJson("/api/admin/users/{$target->id}", [
            'role' => $target->role, 'status' => 'resigned',
        ])->assertOk();
    }

    public function test_퇴사_처리_시_상태와_담당자_제외(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $member->syncAssignee();
        $this->assertTrue(Assignee::firstWhere('user_id', $member->id)->is_active);

        $this->resign($admin, $member);

        $member->refresh();
        $this->assertFalse($member->is_active);
        $this->assertNotNull($member->resigned_at);
        $this->assertFalse(Assignee::firstWhere('user_id', $member->id)->is_active);

        // 재저장해도 최초 퇴사일 유지
        $firstResignedAt = $member->resigned_at;
        $this->travel(1)->days();
        $this->resign($admin, $member);
        $this->assertEquals($firstResignedAt, $member->fresh()->resigned_at);

        // 목록 API에 상태/퇴사일 노출
        $row = collect($this->actingAs($admin)->getJson('/api/admin/users')->json())
            ->firstWhere('id', $member->id);
        $this->assertSame('resigned', $row['status']);
        $this->assertNotNull($row['resigned_at']);
    }

    public function test_퇴사자는_로그인_불가(): void
    {
        $member = User::factory()->create([
            'role' => 'member', 'is_active' => false, 'resigned_at' => now(),
            'password' => 'password1234',
        ]);

        $this->post('/login', ['username' => $member->username, 'password' => 'password1234']);
        $this->assertGuest();
    }

    public function test_퇴사자의_열려있는_세션은_즉시_차단(): void
    {
        $member = User::factory()->create(['role' => 'member', 'is_active' => false, 'resigned_at' => now()]);

        $this->actingAs($member)->get('/calendar')->assertRedirect(route('login'));
        $this->assertGuest();

        $member2 = User::factory()->create(['role' => 'member', 'is_active' => false, 'resigned_at' => now()]);
        $this->actingAs($member2)->getJson('/api/notifications')->assertForbidden();
    }

    public function test_복귀_처리_시_접근과_담당자_복구(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member', 'is_active' => false, 'resigned_at' => now()]);
        $member->syncAssignee();

        $this->actingAs($admin)->patchJson("/api/admin/users/{$member->id}", [
            'role' => 'member', 'status' => 'active',
        ])->assertOk();

        $member->refresh();
        $this->assertTrue($member->is_active);
        $this->assertNull($member->resigned_at);
        $this->assertTrue(Assignee::firstWhere('user_id', $member->id)->is_active);
    }

    public function test_본인_계정은_퇴사_처리_불가(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patchJson("/api/admin/users/{$admin->id}", [
            'role' => 'admin', 'status' => 'resigned',
        ])->assertUnprocessable();
        $this->assertNull($admin->fresh()->resigned_at);
    }
}
