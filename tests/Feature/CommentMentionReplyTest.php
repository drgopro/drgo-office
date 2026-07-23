<?php

namespace Tests\Feature;

use App\Models\FeedbackPost;
use App\Models\User;
use App\Models\Wiki;
use App\Notifications\MentionAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** 피드백·위키 회의록 댓글 — @멘션 호출 알림 + 대댓글(1뎁스) */
class CommentMentionReplyTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        return User::factory()->create(['role' => 'member', 'display_name' => $name, 'username' => 'u_'.md5($name)]);
    }

    private function feedbackPost(User $author): FeedbackPost
    {
        return FeedbackPost::create(['type' => 'feature', 'title' => '테스트 요청', 'page' => '기타', 'created_by' => $author->id]);
    }

    public function test_feedback_reply_saves_parent_and_notifies_parent_author(): void
    {
        Notification::fake();
        $a = $this->member('김웅');
        $b = $this->member('박진혁');
        $post = $this->feedbackPost($a);
        $parent = $post->comments()->create(['user_id' => $a->id, 'body' => '첫 의견']);

        $res = $this->actingAs($b)->postJson("/api/feedback/{$post->id}/comments", [
            'body' => '답글입니다', 'parent_id' => $parent->id,
        ])->assertCreated();

        $this->assertSame($parent->id, $res->json('parent_id'));
        Notification::assertSentTo($a, MentionAlert::class);
    }

    public function test_feedback_reply_to_reply_is_rejected(): void
    {
        $a = $this->member('김웅');
        $post = $this->feedbackPost($a);
        $parent = $post->comments()->create(['user_id' => $a->id, 'body' => '첫 의견']);
        $reply = $post->comments()->create(['user_id' => $a->id, 'parent_id' => $parent->id, 'body' => '답글']);

        $this->actingAs($a)->postJson("/api/feedback/{$post->id}/comments", [
            'body' => '대대댓글', 'parent_id' => $reply->id,
        ])->assertStatus(422);
    }

    public function test_feedback_mention_notifies_mentioned_user(): void
    {
        Notification::fake();
        $a = $this->member('김웅');
        $b = $this->member('박진혁');
        $post = $this->feedbackPost($a);

        $this->actingAs($a)->postJson("/api/feedback/{$post->id}/comments", [
            'body' => '@박진혁 확인 부탁해요',
        ])->assertCreated();

        Notification::assertSentTo($b, MentionAlert::class, function (MentionAlert $n) {
            return str_contains($n->title, '언급');
        });
    }

    public function test_mention_matches_name_with_josa_suffix(): void
    {
        Notification::fake();
        $a = $this->member('김웅');
        $b = $this->member('박진혁');
        $post = $this->feedbackPost($a);

        $this->actingAs($a)->postJson("/api/feedback/{$post->id}/comments", [
            'body' => '@박진혁님이 봐주세요',
        ])->assertCreated();

        Notification::assertSentTo($b, MentionAlert::class);
    }

    public function test_deleting_comment_removes_replies(): void
    {
        $a = $this->member('김웅');
        $post = $this->feedbackPost($a);
        $parent = $post->comments()->create(['user_id' => $a->id, 'body' => '첫 의견']);
        $reply = $post->comments()->create(['user_id' => $a->id, 'parent_id' => $parent->id, 'body' => '답글']);

        $this->actingAs($a)->deleteJson("/api/feedback-comments/{$parent->id}")->assertOk();

        $this->assertDatabaseMissing('feedback_comments', ['id' => $reply->id]);
    }

    public function test_wiki_meeting_reply_and_mention(): void
    {
        Notification::fake();
        $a = $this->member('김웅');
        $b = $this->member('박진혁');
        $wiki = Wiki::create(['title' => '주간 회의', 'content' => '<p>회의록</p>', 'type' => 'meeting', 'created_by' => $a->id]);
        $parent = $wiki->comments()->create(['user_id' => $b->id, 'body' => '의견']);

        $this->actingAs($a)->post("/wiki/{$wiki->id}/comments", [
            'body' => '@박진혁 답글 확인', 'parent_id' => $parent->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('wiki_comments', ['wiki_id' => $wiki->id, 'parent_id' => $parent->id]);
        // 멘션 알림 1건 (답글 알림은 멘션과 중복되지 않게 생략됨)
        Notification::assertSentToTimes($b, MentionAlert::class, 1);
    }

    public function test_wiki_reply_to_reply_is_rejected(): void
    {
        $a = $this->member('김웅');
        $wiki = Wiki::create(['title' => '주간 회의', 'content' => '<p>회의록</p>', 'type' => 'meeting', 'created_by' => $a->id]);
        $parent = $wiki->comments()->create(['user_id' => $a->id, 'body' => '의견']);
        $reply = $wiki->comments()->create(['user_id' => $a->id, 'parent_id' => $parent->id, 'body' => '답글']);

        $this->actingAs($a)->post("/wiki/{$wiki->id}/comments", [
            'body' => '대대댓글', 'parent_id' => $reply->id,
        ])->assertStatus(422);
    }

    public function test_mention_users_endpoint_lists_active_members(): void
    {
        $a = $this->member('김웅');
        User::factory()->create(['role' => 'member', 'display_name' => '퇴사자', 'is_active' => false]);

        $names = $this->actingAs($a)->getJson('/api/mention-users')->assertOk()->json();
        $names = collect($names)->pluck('name');
        $this->assertTrue($names->contains('김웅'));
        $this->assertFalse($names->contains('퇴사자'));
    }
}
