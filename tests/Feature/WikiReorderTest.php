<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 게시물 수동 정렬 — 카테고리 안에서 중요한 글을 위로 (관리자 전용) */
class WikiReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reorders_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Wiki::create(['title' => '글A', 'content' => 'x', 'category' => '일반']);
        $b = Wiki::create(['title' => '글B', 'content' => 'x', 'category' => '일반']);

        $this->actingAs($admin)->postJson('/api/wiki/reorder', [
            'items' => [
                ['id' => $b->id, 'sort_order' => 0],
                ['id' => $a->id, 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_member_cannot_reorder(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $a = Wiki::create(['title' => '글A', 'content' => 'x', 'category' => '일반']);

        $this->actingAs($member)->postJson('/api/wiki/reorder', [
            'items' => [['id' => $a->id, 'sort_order' => 0]],
        ])->assertForbidden();

        $this->assertNull($a->fresh()->sort_order);
    }

    public function test_doc_list_includes_sort_order_and_reorder_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Wiki::create(['title' => '정렬 대상', 'content' => 'x', 'category' => '일반', 'sort_order' => 3]);

        $this->actingAs($admin)->get('/wiki')
            ->assertOk()
            ->assertSee('"sort_order":3', false)
            ->assertSee('wikiMoveDoc', false)
            ->assertSee('wikiBindDocDrag', false)
            ->assertSee('wikiManualSortActive', false);
    }
}
