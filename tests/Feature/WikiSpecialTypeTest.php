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
        $this->assertTrue($wiki->is_pinned, '게시판 글도 고정 가능');
    }

    public function test_pin_toggle_works_for_special_and_normal_types(): void
    {
        $member = $this->member();

        // 회의록 글 고정 → 수정을 반복해도 고정 유지
        $meeting = Wiki::create(['title' => '회의록', 'type' => 'meeting', 'content' => 'x', 'category' => '회의록']);
        $this->actingAs($member)->patchJson("/wiki/{$meeting->id}", ['is_pinned' => 1])->assertOk();
        $this->assertTrue($meeting->fresh()->is_pinned);
        $this->actingAs($member)->patchJson("/wiki/{$meeting->id}", ['title' => '내용만 수정'])->assertOk();
        $this->assertTrue($meeting->fresh()->is_pinned, '다른 필드 수정 시 고정 유지');
        $this->actingAs($member)->patchJson("/wiki/{$meeting->id}", ['is_pinned' => 0])->assertOk();
        $this->assertFalse($meeting->fresh()->is_pinned);

        // 일반 문서 고정
        $normal = Wiki::create(['title' => '일반', 'content' => 'x']);
        $this->actingAs($member)->patchJson("/wiki/{$normal->id}", ['is_pinned' => 1])->assertOk();
        $this->assertTrue($normal->fresh()->is_pinned);
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

    public function test_member_creates_meeting_doc_without_category(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);

        $res = $this->actingAs($this->member())->postJson('/wiki', [
            'title' => '7월 2주차 회의록',
            'type' => 'meeting',
            'category_id' => $cat->id, // 지정해도 강제 해제되어야 함
            'content' => '<p>내용</p>',
        ]);

        $res->assertCreated();
        $wiki = Wiki::first();
        $this->assertSame('meeting', $wiki->type);
        $this->assertNull($wiki->category_id, '회의록은 카테고리와 분리');
        $this->assertSame('회의록', $wiki->category);
    }

    public function test_member_can_edit_and_delete_meeting_doc(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $meeting = Wiki::create(['title' => '회의록', 'type' => 'meeting', 'content' => 'x', 'category' => '회의록']);

        $this->actingAs($this->member())->patchJson("/wiki/{$meeting->id}", [
            'title' => '회의록 수정',
            'category_id' => $cat->id,
        ])->assertOk();

        $fresh = $meeting->fresh();
        $this->assertSame('회의록 수정', $fresh->title);
        $this->assertNull($fresh->category_id, '회의록은 카테고리 변경 요청을 무시');
        $this->assertSame('회의록', $fresh->category);

        $this->actingAs($this->member())->deleteJson("/wiki/{$meeting->id}")->assertOk();
        $this->assertNull(Wiki::find($meeting->id));
    }

    public function test_meeting_count_included_in_type_counts(): void
    {
        Wiki::create(['title' => '주간 회의', 'type' => 'meeting', 'content' => 'x', 'category' => '회의록']);

        $res = $this->actingAs($this->member())->get('/wiki');

        $res->assertOk();
        $this->assertSame(1, (int) $res->viewData('typeCounts')['meeting']);
    }

    public function test_member_changes_doc_type_between_normal_and_meeting(): void
    {
        $member = $this->member();
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $doc = Wiki::create(['title' => '회의 내용', 'content' => 'x', 'category_id' => $cat->id, 'category' => '매뉴얼']);

        // 일반 → 회의록: 카테고리 강제 해제
        $this->actingAs($member)->patchJson("/wiki/{$doc->id}", ['type' => 'meeting'])->assertOk();
        $fresh = $doc->fresh();
        $this->assertSame('meeting', $fresh->type);
        $this->assertNull($fresh->category_id);
        $this->assertSame('회의록', $fresh->category);

        // 회의록 → 일반: 카테고리 지정 가능
        $this->actingAs($member)->patchJson("/wiki/{$doc->id}", ['type' => 'normal', 'category_id' => $cat->id])->assertOk();
        $fresh = $doc->fresh();
        $this->assertSame('normal', $fresh->type);
        $this->assertSame($cat->id, $fresh->category_id);
        $this->assertSame('매뉴얼', $fresh->category);
    }

    public function test_member_cannot_change_type_to_admin_only(): void
    {
        $doc = Wiki::create(['title' => '일반', 'content' => 'x']);

        $this->actingAs($this->member())->patchJson("/wiki/{$doc->id}", ['type' => 'notice'])->assertForbidden();
        $this->assertSame('normal', $doc->fresh()->type);
    }

    public function test_admin_converts_notice_to_normal(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $notice = Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);

        $this->actingAs($this->admin())->patchJson("/wiki/{$notice->id}", ['type' => 'normal', 'category_id' => $cat->id])->assertOk();
        $fresh = $notice->fresh();
        $this->assertSame('normal', $fresh->type);
        $this->assertSame($cat->id, $fresh->category_id);
    }

    public function test_admin_bulk_moves_docs_across_types_and_categories(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $notice = Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        $normal = Wiki::create(['title' => '일반', 'content' => 'x']);

        // 카테고리로 이동 — 특수 유형도 일반 문서로 전환되며 이동
        $this->actingAs($this->admin())->postJson('/api/wiki/bulk-category', [
            'ids' => [$notice->id, $normal->id],
            'category_id' => $cat->id,
        ])->assertOk();
        $this->assertSame('normal', $notice->fresh()->type);
        $this->assertSame($cat->id, $notice->fresh()->category_id);
        $this->assertSame($cat->id, $normal->fresh()->category_id);

        // 회의록 게시판으로 이동 — 카테고리 해제
        $this->actingAs($this->admin())->postJson('/api/wiki/bulk-category', [
            'ids' => [$normal->id],
            'type' => 'meeting',
        ])->assertOk();
        $this->assertSame('meeting', $normal->fresh()->type);
        $this->assertNull($normal->fresh()->category_id);
        $this->assertSame('회의록', $normal->fresh()->category);
    }

    public function test_member_bulk_move_skips_admin_only_docs_and_cannot_target_them(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $notice = Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        $normal = Wiki::create(['title' => '일반', 'content' => 'x']);

        // 공지/업데이트 대상 지정은 관리자만
        $this->actingAs($this->member())->postJson('/api/wiki/bulk-category', [
            'ids' => [$normal->id], 'type' => 'notice',
        ])->assertForbidden();

        // 회의록으로 이동 시 공지 문서는 건너뜀
        $this->actingAs($this->member())->postJson('/api/wiki/bulk-category', [
            'ids' => [$notice->id, $normal->id], 'type' => 'meeting',
        ])->assertOk();
        $this->assertSame('notice', $notice->fresh()->type, '공지는 일반 직원 이동에서 제외');
        $this->assertSame('meeting', $normal->fresh()->type);
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

    public function test_dashboard_shows_notice_and_update_panels_separately(): void
    {
        $old = Wiki::create(['title' => '옛 공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        Wiki::create(['title' => '새 공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);
        Wiki::create(['title' => '업데이트 글', 'type' => 'update', 'content' => 'x', 'category' => '업데이트']);
        Wiki::create(['title' => '일반 문서', 'content' => 'x']);

        $res = $this->actingAs($this->member())->get('/');

        $res->assertOk();
        $notices = $res->viewData('wikiNoticeList');
        $updates = $res->viewData('wikiUpdateList');
        $this->assertCount(2, $notices, '공지 패널은 공지만');
        $this->assertSame('새 공지', $notices->first()->title, '최신순');
        $this->assertCount(1, $updates, '업데이트 패널은 업데이트만');
        $this->assertSame('업데이트 글', $updates->first()->title);
    }
}
