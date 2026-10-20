<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 삭제된 게시물 보관함 — 소프트 삭제 + 관리자 전용 복구/영구 삭제 */
class WikiTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_soft_deletes_and_admin_can_restore(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create(['title' => '삭제될 문서', 'content' => 'x', 'created_by' => $admin->id]);

        $this->actingAs($admin)->deleteJson("/wiki/{$wiki->id}")->assertOk();
        $this->assertNotNull(Wiki::withTrashed()->find($wiki->id)->deleted_at, '소프트 삭제 — 보관함에 남는다');

        $rows = $this->actingAs($admin)->getJson('/api/wiki/trash')->assertOk()->json();
        $this->assertSame('삭제될 문서', $rows[0]['title']);

        $this->actingAs($admin)->postJson("/api/wiki/trash/{$wiki->id}/restore")->assertOk();
        $this->assertNull(Wiki::find($wiki->id)->deleted_at);
    }

    public function test_admin_can_force_delete_from_trash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create(['title' => '영구 삭제 문서', 'content' => 'x', 'created_by' => $admin->id]);
        $wiki->delete();

        $this->actingAs($admin)->deleteJson("/api/wiki/trash/{$wiki->id}")->assertOk();
        $this->assertNull(Wiki::withTrashed()->find($wiki->id));
    }

    public function test_member_cannot_access_trash(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $wiki = Wiki::create(['title' => '문서', 'content' => 'x']);
        $wiki->delete();

        $this->actingAs($member)->getJson('/api/wiki/trash')->assertForbidden();
        $this->actingAs($member)->postJson("/api/wiki/trash/{$wiki->id}/restore")->assertForbidden();
        $this->actingAs($member)->deleteJson("/api/wiki/trash/{$wiki->id}")->assertForbidden();
    }

    public function test_trashed_docs_hidden_from_wiki_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create(['title' => '숨을 문서', 'content' => 'x', 'created_by' => $admin->id]);
        $wiki->delete();

        $res = $this->actingAs($admin)->get('/wiki')->assertOk();
        $this->assertFalse(collect($res->viewData('wikis'))->pluck('title')->contains('숨을 문서'));
    }
}
