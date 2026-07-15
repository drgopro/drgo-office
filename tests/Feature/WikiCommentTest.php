<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikiCommentTest extends TestCase
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

    private function meetingDoc(): Wiki
    {
        return Wiki::create(['title' => '주간 회의록', 'type' => 'meeting', 'content' => 'x', 'category' => '회의록']);
    }

    public function test_member_comments_on_meeting_doc(): void
    {
        $wiki = $this->meetingDoc();
        $member = $this->member();

        $res = $this->actingAs($member)->post("/wiki/{$wiki->id}/comments", [
            'body' => "확인했습니다.\n다음 주 안건에 반영할게요.",
        ]);

        $res->assertRedirect(route('wiki.show', $wiki));
        $comment = WikiComment::first();
        $this->assertSame($wiki->id, $comment->wiki_id);
        $this->assertSame($member->id, $comment->user_id);
        $this->assertSame("확인했습니다.\n다음 주 안건에 반영할게요.", $comment->body);
    }

    public function test_cannot_comment_on_non_meeting_doc(): void
    {
        $normal = Wiki::create(['title' => '일반 문서', 'content' => 'x']);
        $notice = Wiki::create(['title' => '공지', 'type' => 'notice', 'content' => 'x', 'category' => '공지사항']);

        $this->actingAs($this->member())->post("/wiki/{$normal->id}/comments", ['body' => '댓글'])->assertForbidden();
        $this->actingAs($this->admin())->post("/wiki/{$notice->id}/comments", ['body' => '댓글'])->assertForbidden();
        $this->assertSame(0, WikiComment::count());
    }

    public function test_comment_body_is_required(): void
    {
        $wiki = $this->meetingDoc();

        $this->actingAs($this->member())
            ->postJson("/wiki/{$wiki->id}/comments", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_author_edits_own_comment_but_not_others(): void
    {
        $wiki = $this->meetingDoc();
        $author = $this->member();
        $other = $this->member();
        $comment = $wiki->comments()->create(['user_id' => $author->id, 'body' => '원래 내용']);

        $this->actingAs($other)->patch("/wiki-comments/{$comment->id}", ['body' => '탈취 시도'])->assertForbidden();
        $this->assertSame('원래 내용', $comment->fresh()->body);

        $this->actingAs($author)->patch("/wiki-comments/{$comment->id}", ['body' => '수정된 내용'])
            ->assertRedirect(route('wiki.show', $wiki));
        $this->assertSame('수정된 내용', $comment->fresh()->body);
    }

    public function test_admin_edits_any_comment(): void
    {
        $wiki = $this->meetingDoc();
        $comment = $wiki->comments()->create(['user_id' => $this->member()->id, 'body' => '직원 댓글']);

        $this->actingAs($this->admin())->patch("/wiki-comments/{$comment->id}", ['body' => '관리자가 정정'])
            ->assertRedirect(route('wiki.show', $wiki));
        $this->assertSame('관리자가 정정', $comment->fresh()->body);
    }

    public function test_edit_body_is_required(): void
    {
        $wiki = $this->meetingDoc();
        $author = $this->member();
        $comment = $wiki->comments()->create(['user_id' => $author->id, 'body' => '원래 내용']);

        $this->actingAs($author)->patchJson("/wiki-comments/{$comment->id}", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
        $this->assertSame('원래 내용', $comment->fresh()->body);
    }

    public function test_author_deletes_own_comment_but_not_others(): void
    {
        $wiki = $this->meetingDoc();
        $author = $this->member();
        $other = $this->member();
        $comment = $wiki->comments()->create(['user_id' => $author->id, 'body' => '내 댓글']);

        $this->actingAs($other)->delete("/wiki-comments/{$comment->id}")->assertForbidden();
        $this->assertNotNull(WikiComment::find($comment->id));

        $this->actingAs($author)->delete("/wiki-comments/{$comment->id}")->assertRedirect(route('wiki.show', $wiki));
        $this->assertNull(WikiComment::find($comment->id));
    }

    public function test_admin_deletes_any_comment(): void
    {
        $wiki = $this->meetingDoc();
        $comment = $wiki->comments()->create(['user_id' => $this->member()->id, 'body' => '직원 댓글']);

        $this->actingAs($this->admin())->delete("/wiki-comments/{$comment->id}")->assertRedirect(route('wiki.show', $wiki));
        $this->assertNull(WikiComment::find($comment->id));
    }

    public function test_comments_shown_only_on_meeting_show_page(): void
    {
        $wiki = $this->meetingDoc();
        $wiki->comments()->create(['user_id' => $this->member()->id, 'body' => '회의 내용 확인']);
        $normal = Wiki::create(['title' => '일반 문서', 'content' => 'x']);

        $this->actingAs($this->member())->get("/wiki/{$wiki->id}")
            ->assertOk()
            ->assertSee('회의 내용 확인')
            ->assertSee('id="wikiComments"', false);

        $this->actingAs($this->member())->get("/wiki/{$normal->id}")
            ->assertOk()
            ->assertDontSee('id="wikiComments"', false);
    }
}
