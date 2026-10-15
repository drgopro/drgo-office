<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\NoticeCategory;
use App\Models\NoticeRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 공지사항 게시판 — 위키에서 분리한 독립 게시판 (틀 복사 1단계) */
class NoticeBoardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_renders_board_without_wiki_special_types(): void
    {
        $admin = $this->admin();
        $cat = NoticeCategory::create(['name' => '일반 공지']);
        Notice::create(['title' => '첫 공지', 'content' => '<p>x</p>', 'category_id' => $cat->id, 'created_by' => $admin->id]);

        $res = $this->actingAs($admin)->get('/notices')->assertOk()
            ->assertSee('공지사항')
            ->assertSee('일반 공지')
            ->assertDontSee('회의록')
            ->assertDontSee('연결도');
        // 문서 목록은 @json(유니코드 이스케이프)으로 내려가 클라이언트 렌더 — 뷰 데이터로 확인
        $this->assertTrue(collect($res->viewData('notices'))->pluck('title')->contains('첫 공지'));
    }

    public function test_admin_can_create_and_member_cannot(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin)->postJson('/notices', [
            'title' => '새 공지', 'content' => '<p>내용</p>', 'is_pinned' => 1, 'pinned_until' => '2026-12-31',
        ])->assertCreated();
        $notice = Notice::where('title', '새 공지')->first();
        $this->assertTrue($notice->is_pinned);
        $this->assertSame('2026-12-31', $notice->pinned_until->format('Y-m-d'));

        $this->actingAs($member)->postJson('/notices', [
            'title' => '멤버 공지', 'content' => '<p>x</p>',
        ])->assertForbidden();
        $this->actingAs($member)->get('/notices/create')->assertForbidden();
    }

    public function test_show_records_read_and_displays_status(): void
    {
        $admin = $this->admin();
        $notice = Notice::create(['title' => '읽음 공지', 'content' => '<p>x</p>', 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)->get("/notices/{$notice->id}")->assertOk()->getContent();

        $this->assertSame(1, NoticeRead::where('notice_id', $notice->id)->count());
        $this->assertStringContainsString('읽음 1명', $html);
        $this->assertStringContainsString('안읽음 0명', $html);
    }

    public function test_expired_pin_sorts_like_normal_post(): void
    {
        $admin = $this->admin();
        $old = Notice::create(['title' => '기한지난 고정', 'content' => '<p>x</p>', 'is_pinned' => true,
            'pinned_until' => now()->subDay()->toDateString(), 'created_by' => $admin->id]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        Notice::create(['title' => '유효 고정', 'content' => '<p>x</p>', 'is_pinned' => true,
            'pinned_until' => now()->addDay()->toDateString(), 'created_by' => $admin->id]);
        Notice::create(['title' => '일반 최신글', 'content' => '<p>x</p>', 'created_by' => $admin->id]);

        $res = $this->actingAs($admin)->get('/notices')->assertOk();
        $titles = collect($res->viewData('notices'))->pluck('title')->values()->all();

        $this->assertSame(['유효 고정', '일반 최신글', '기한지난 고정'], $titles);
    }

    public function test_draft_hidden_from_list_and_restricted_hidden_from_other_team(): void
    {
        $admin = $this->admin();
        Notice::create(['title' => '임시 공지', 'content' => '<p>x</p>', 'is_draft' => true, 'created_by' => $admin->id]);
        Notice::create(['title' => '팀한정 공지', 'content' => '<p>x</p>', 'allowed_team_ids' => [999], 'created_by' => $admin->id]);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin)->get('/notices')->assertOk()->assertDontSee('임시 공지');
        $this->actingAs($member)->get('/notices')->assertOk()->assertDontSee('팀한정 공지');
    }

    public function test_category_crud_api_and_update_destroy(): void
    {
        $admin = $this->admin();
        $catId = $this->actingAs($admin)->postJson('/api/notice-categories', ['name' => '사내 소식'])
            ->assertCreated()->json('id');
        $this->actingAs($admin)->patchJson("/api/notice-categories/{$catId}", ['name' => '사내 공지'])->assertOk();

        $notice = Notice::create(['title' => '수정 공지', 'content' => '<p>x</p>', 'category_id' => $catId, 'created_by' => $admin->id]);
        $this->actingAs($admin)->patchJson("/notices/{$notice->id}", ['title' => '수정됨', 'is_pinned' => 0])->assertOk();
        $this->assertSame('수정됨', $notice->fresh()->title);

        $this->actingAs($admin)->deleteJson("/api/notice-categories/{$catId}")->assertOk();
        $this->assertNull($notice->fresh()->category_id); // 삭제된 카테고리의 공지는 미분류로

        $this->actingAs($admin)->deleteJson("/notices/{$notice->id}")->assertOk();
        $this->assertNull(Notice::find($notice->id));
    }
}
