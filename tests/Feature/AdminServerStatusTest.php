<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 관리 페이지 서버 상태 탭 — 디스크 사용량 + 저장 폴더별 용량 */
class AdminServerStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_disk_usage_and_storage_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->getJson('/api/admin/server-status')
            ->assertOk()
            ->assertJsonStructure(['total', 'free', 'used', 'used_percent', 'dirs', 'checked_at']);

        $this->assertGreaterThan(0, $res->json('total'));
        $this->assertIsInt($res->json('used_percent'));
        $this->assertGreaterThanOrEqual(0, $res->json('used_percent'));
        $this->assertLessThanOrEqual(100, $res->json('used_percent'));
        // 폴더 목록 — name/label 구조 (size는 du 미지원 환경에서 null 허용)
        foreach ($res->json('dirs') as $dir) {
            $this->assertArrayHasKey('name', $dir);
            $this->assertArrayHasKey('label', $dir);
            $this->assertArrayHasKey('size', $dir);
        }
    }

    public function test_member_cannot_access_server_status(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->getJson('/api/admin/server-status')->assertForbidden();
    }

    public function test_admin_page_renders_server_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('서버 상태')
            ->assertSee('서버 디스크 사용량')
            ->assertSee('loadServerStatus', false)
            ->assertSee('id="svrDiskBody"', false);
    }
}
