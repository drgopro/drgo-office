<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikiSpecialTypeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    public function test_admin_creates_notice_without_category(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);

        $res = $this->actingAs($this->admin())->postJson('/wiki', [
            'title' => '7월 업데이트 안내',
            'type' => 'update',
            'category_id' => $cat->id, // 지정해도 강제 해제되어야 함
            'content' => '<p>내용</p>',
            'is_pinned' => 1,
        ]);

        $res->assertCreated();
        $wiki = Wiki::first();
        $this->assertSame('update', $wiki->type);
        $this->assertNull($wiki->category_id, '공지/업데이트는 카테고리와 분리');
        $this->assertSame('업데이트', $wiki->category);
        $this->assertFalse($wiki->is_pinned);
    }

    public function test_member_cannot_create_or_edit_notice(): void
    {
        $this->actingAs($this->member())->postJson('/wiki', [
            'title' => '공지', 'type' => 'notice', 'content' => '<p>x</p>',
        ])->assertForbidden();

        $notice = Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        $this->actingAs($this->member())->patchJson("/wiki/{$notice->id}", [
            'title' => '수정 시도',
        ])->assertForbidden();
        $this->actingAs($this->member())->deleteJson("/wiki/{$notice->id}")->assertForbidden();

        // 일반 문서는 멤버도 작성 가능 (기존 동작 유지)
        $this->actingAs($this->member())->postJson('/wiki', [
            'title' => '일반 문서', 'content' => '<p>x</p>',
        ])->assertCreated();
    }

    public function test_bulk_move_skips_special_types(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $notice = Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        $normal = Wiki::create(['title' => '일반', 'content' => 'x']);

        $this->actingAs($this->admin())->postJson('/api/wiki/bulk-category', [
            'ids' => [$notice->id, $normal->id],
            'category_id' => $cat->id,
        ])->assertOk();

        $this->assertNull($notice->fresh()->category_id, '공지는 일괄 이동에서 제외');
        $this->assertSame($cat->id, $normal->fresh()->category_id);
    }

    public function test_wiki_index_separates_special_from_uncategorized(): void
    {
        Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        Wiki::create(['title' => '미분류 일반', 'content' => 'x']);

        $res = $this->actingAs($this->member())->get('/wiki');

        $res->assertOk();
        $this->assertSame(1, $res->viewData('uncategorized'), '공지는 미분류 카운트에서 제외');
        $this->assertSame(1, (int) $res->viewData('typeCounts')['notice']);
    }

    public function test_dashboard_shows_notices_latest_first(): void
    {
        $old = Wiki::create(['title' => '옛 공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $new = Wiki::create(['title' => '새 업데이트', 'type' => 'update', 'content' => 'x', 'category' => '업데이트']);
        Wiki::create(['title' => '일반 문서', 'content' => 'x']);

        $res = $this->actingAs($this->member())->get('/');

        $res->assertOk();
        $notices = $res->viewData('wikiNotices');
        $this->assertCount(2, $notices, '일반 문서는 공지 패널에 미포함');
        $this->assertSame('새 업데이트', $notices->first()->title, '최신순');
        $res->assertSee('공지·업데이트');
    }
}
