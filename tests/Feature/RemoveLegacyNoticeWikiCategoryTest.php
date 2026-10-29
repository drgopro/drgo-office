<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 옛 '공지사항' 위키 카테고리 정리 마이그레이션 — 빈 카테고리만 제거, 사용 중이면 보존 */
class RemoveLegacyNoticeWikiCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require base_path('database/migrations/2026_10_29_180000_remove_legacy_notice_wiki_category.php');
    }

    public function test_removes_empty_legacy_notice_category_and_unlinks_archived_notices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $legacy = WikiCategory::create(['name' => '공지사항', 'sort_order' => 0]);
        $archived = Wiki::create([
            'title' => '이관된 공지', 'type' => 'notice', 'category' => '공지사항', 'category_id' => $legacy->id,
            'content' => '<p>x</p>', 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $archived->delete(); // 공지 이관 후 보관함(소프트 삭제) 상태

        $this->migration()->up();

        $this->assertDatabaseMissing('wiki_categories', ['id' => $legacy->id]);
        $this->assertNull(Wiki::withTrashed()->find($archived->id)->category_id);
        $this->assertNotNull(Wiki::withTrashed()->find($archived->id)->deleted_at); // 보관함 문서는 그대로
    }

    public function test_keeps_notice_named_category_that_still_has_live_documents_or_children(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inUse = WikiCategory::create(['name' => '공지사항', 'sort_order' => 0]);
        Wiki::create([
            'title' => '일반 문서', 'type' => 'normal', 'category' => '공지사항', 'category_id' => $inUse->id,
            'content' => '<p>x</p>', 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $parent = WikiCategory::create(['name' => '공지사항', 'sort_order' => 1]);
        WikiCategory::create(['name' => '하위', 'parent_id' => $parent->id, 'sort_order' => 0]);
        $other = WikiCategory::create(['name' => '업무 가이드', 'sort_order' => 2]);

        $this->migration()->up();

        $this->assertDatabaseHas('wiki_categories', ['id' => $inUse->id]);
        $this->assertDatabaseHas('wiki_categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('wiki_categories', ['id' => $other->id]);
    }

    public function test_keeps_category_holding_archived_normal_documents_and_nested_same_name_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $withArchivedNormal = WikiCategory::create(['name' => '공지사항', 'sort_order' => 0]);
        $archivedNormal = Wiki::create([
            'title' => '보관함 일반 문서', 'type' => 'normal', 'category' => '공지사항', 'category_id' => $withArchivedNormal->id,
            'content' => '<p>x</p>', 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $archivedNormal->delete();
        $root = WikiCategory::create(['name' => '운영', 'sort_order' => 1]);
        $nested = WikiCategory::create(['name' => '공지사항', 'parent_id' => $root->id, 'sort_order' => 0]);

        $this->migration()->up();

        $this->assertDatabaseHas('wiki_categories', ['id' => $withArchivedNormal->id]);
        $this->assertSame($withArchivedNormal->id, Wiki::withTrashed()->find($archivedNormal->id)->category_id); // 분류 유지
        $this->assertDatabaseHas('wiki_categories', ['id' => $nested->id]);
    }

    public function test_wiki_index_tree_no_longer_lists_removed_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        WikiCategory::create(['name' => '공지사항', 'sort_order' => 0]);
        WikiCategory::create(['name' => '업무 가이드', 'sort_order' => 1]);

        $this->migration()->up();

        $names = $this->actingAs($admin)->get('/wiki?type=update')->assertOk()->viewData('tree')->pluck('name')->all();
        $this->assertContains('업무 가이드', $names);
        $this->assertNotContains('공지사항', $names);
    }
}
