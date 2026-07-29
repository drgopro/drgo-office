<?php

namespace Tests\Feature;

use App\Models\RequestItemPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 의뢰 세부 항목 프리셋 (3뎁스 선택지) — 조회는 로그인 전체, CRUD는 관리자 전용 */
class RequestItemPresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_presets_are_listed_for_any_logged_in_user(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($member)->getJson('/api/request-item-presets');

        $res->assertOk();
        $titles = collect($res->json())->pluck('title')->all();
        $this->assertContains('처음 세팅', $titles);
        $this->assertContains('이사 세팅', $titles);
        // children 트리 구조 확인 (2뎁스 분류 → 3뎁스 항목 배열)
        $first = collect($res->json())->firstWhere('title', '처음 세팅');
        $this->assertSame(['컴퓨터 문제해결'], $first['children']['컴퓨터']);
    }

    public function test_admin_can_create_update_delete_preset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->postJson('/api/admin/request-item-presets', [
            'title' => '철거 지원',
            'children' => ['공통' => ['장비 철거', '폐기 지원']],
        ]);
        $res->assertCreated();
        $id = $res->json('id');

        $this->actingAs($admin)->patchJson("/api/admin/request-item-presets/{$id}", [
            'title' => '철거/폐기 지원',
            'children' => ['공통' => ['장비 철거']],
        ])->assertOk();

        $preset = RequestItemPreset::find($id);
        $this->assertSame('철거/폐기 지원', $preset->title);
        $this->assertSame(['장비 철거'], $preset->children['공통']);

        $this->actingAs($admin)->deleteJson("/api/admin/request-item-presets/{$id}")->assertOk();
        $this->assertNull(RequestItemPreset::find($id));
    }

    public function test_member_cannot_manage_presets(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $preset = RequestItemPreset::first();

        $this->actingAs($member)->postJson('/api/admin/request-item-presets', [
            'title' => '멤버 추가 시도',
        ])->assertForbidden();

        $this->actingAs($member)->deleteJson("/api/admin/request-item-presets/{$preset->id}")->assertForbidden();
        $this->assertNotNull(RequestItemPreset::find($preset->id));
    }

    public function test_inactive_presets_are_hidden_from_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $preset = RequestItemPreset::first();
        $preset->update(['is_active' => false]);

        $titles = collect($this->actingAs($admin)->getJson('/api/request-item-presets')->json())->pluck('title');
        $this->assertNotContains($preset->title, $titles);
    }
}
