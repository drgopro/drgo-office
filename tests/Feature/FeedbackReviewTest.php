<?php

namespace Tests\Feature;

use App\Models\FeedbackPost;
use App\Models\FeedbackReview;
use App\Models\User;
use App\Notifications\FeedbackActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 피드백 AI 검수 — 에이전트 토큰 API(조회·검수 등록·결과 기록) + 관리자 전용 노출·승인 */
class FeedbackReviewTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = ['X-Agent-Token' => 'test-agent-token'];

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent_events.token' => 'test-agent-token']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->member = User::factory()->create(['role' => 'member']);
    }

    private function makePost(string $title = '버그', string $status = 'waiting'): FeedbackPost
    {
        return FeedbackPost::create([
            'type' => 'bug', 'title' => $title, 'page' => '캘린더',
            'status' => $status, 'created_by' => $this->member->id,
        ]);
    }

    private function review(FeedbackPost $post, array $over = []): FeedbackReview
    {
        return FeedbackReview::factory()->create(['feedback_post_id' => $post->id, ...$over]);
    }

    public function test_agent_api_rejects_missing_wrong_or_unconfigured_token(): void
    {
        $post = $this->makePost();

        $this->getJson('/api/agent/feedback?scope=review')->assertUnauthorized();
        $this->getJson('/api/agent/feedback?scope=review', ['X-Agent-Token' => 'nope'])->assertUnauthorized();
        $this->postJson("/api/agent/feedback/{$post->id}/review", ['category' => 'bug', 'summary' => 's'], ['X-Agent-Token' => 'nope'])->assertUnauthorized();
        $this->postJson("/api/agent/feedback/{$post->id}/result", ['result_note' => 'r'])->assertUnauthorized();

        config(['services.agent_events.token' => '']);
        $this->getJson('/api/agent/feedback?scope=review', ['X-Agent-Token' => ''])->assertUnauthorized();
        $this->assertDatabaseCount('feedback_reviews', 0);
    }

    public function test_review_scope_lists_unreviewed_and_revise_posts_only(): void
    {
        $fresh = $this->makePost('미검수');
        $revise = $this->makePost('재검토');
        $this->review($revise, ['decision' => 'revise', 'decision_note' => '다른 방법으로']);
        $pending = $this->makePost('승인 대기');
        $this->review($pending);
        $this->makePost('완료된 글', 'done');
        $fresh->comments()->create(['user_id' => $this->member->id, 'body' => '추가 설명']);

        $res = $this->getJson('/api/agent/feedback?scope=review', self::TOKEN)->assertOk();

        $this->assertSame(['미검수', '재검토'], collect($res->json('posts'))->pluck('title')->all());
        $this->assertSame('추가 설명', $res->json('posts.0.comments.0.body'));
        $this->assertSame('다른 방법으로', $res->json('posts.1.review.decision_note'));
    }

    public function test_approved_scope_lists_only_approved_unapplied_posts(): void
    {
        $approved = $this->makePost('승인됨', 'reviewing');
        $this->review($approved, ['decision' => 'approved', 'decision_note' => '진행']);
        $applied = $this->makePost('반영됨', 'reviewing');
        $this->review($applied, ['decision' => 'approved', 'applied_at' => now()]);
        $this->review($this->makePost('대기'));
        $this->review($this->makePost('보류'), ['decision' => 'hold']);

        $res = $this->getJson('/api/agent/feedback?scope=approved', self::TOKEN)->assertOk();

        $this->assertSame(['승인됨'], collect($res->json('posts'))->pluck('title')->all());
        $this->assertSame('진행', $res->json('posts.0.review.decision_note'));
    }

    public function test_scope_is_required(): void
    {
        $this->getJson('/api/agent/feedback', self::TOKEN)->assertUnprocessable();
    }

    public function test_agent_stores_review_and_resubmission_resets_decision(): void
    {
        $post = $this->makePost();

        $this->postJson("/api/agent/feedback/{$post->id}/review", [
            'category' => 'bug', 'effort' => 'small', 'summary' => '원인 추정', 'proposal' => '수정 방향',
        ], self::TOKEN)->assertCreated()->assertJsonPath('review.decision', 'pending');

        $post->review->update(['decision' => 'revise', 'decision_note' => '다시', 'decided_by' => $this->admin->id, 'decided_at' => now()]);

        $this->postJson("/api/agent/feedback/{$post->id}/review", [
            'category' => 'improvement', 'summary' => '재검수',
        ], self::TOKEN)->assertCreated();

        $this->assertDatabaseCount('feedback_reviews', 1);
        $review = $post->fresh()->review;
        $this->assertSame('improvement', $review->category);
        $this->assertSame('pending', $review->decision);
        $this->assertNull($review->decided_by);
        $this->assertNull($review->effort);
        $this->assertNotNull($review->reviewed_at);
    }

    public function test_agent_cannot_overwrite_decided_or_applied_review_or_review_closed_post(): void
    {
        foreach (['pending', 'approved', 'hold', 'rejected'] as $decision) {
            $post = $this->makePost("결정 {$decision}");
            $this->review($post, ['decision' => $decision, 'summary' => '원본']);

            $this->postJson("/api/agent/feedback/{$post->id}/review", ['category' => 'bug', 'summary' => '덮어쓰기'], self::TOKEN)
                ->assertStatus(409);
            $this->assertSame('원본', $post->fresh()->review->summary);
            $this->assertSame($decision, $post->fresh()->review->decision);
        }

        $done = $this->makePost('완료된 글', 'done');
        $this->postJson("/api/agent/feedback/{$done->id}/review", ['category' => 'bug', 'summary' => 's'], self::TOKEN)
            ->assertStatus(409);
        $this->assertNull($done->fresh()->review);
    }

    public function test_review_validation_rejects_unknown_category(): void
    {
        $post = $this->makePost();

        $this->postJson("/api/agent/feedback/{$post->id}/review", ['category' => 'whatever', 'summary' => 's'], self::TOKEN)
            ->assertUnprocessable();
        $this->postJson("/api/agent/feedback/{$post->id}/review", ['category' => 'bug'], self::TOKEN)
            ->assertUnprocessable();
    }

    public function test_review_is_visible_to_admin_only(): void
    {
        $post = $this->makePost();
        $this->review($post, ['summary' => '관리자만 보는 검수']);

        $this->actingAs($this->admin)->getJson('/api/feedback?type=bug')
            ->assertOk()
            ->assertJsonPath('posts.0.review.summary', '관리자만 보는 검수')
            ->assertJsonPath('review_pending_count', 1);

        $this->actingAs($this->member)->getJson('/api/feedback?type=bug')
            ->assertOk()
            ->assertJsonPath('posts.0.review', null)
            ->assertJsonPath('review_pending_count', null)
            ->assertDontSee('관리자만 보는 검수');
    }

    public function test_review_pending_filter_applies_to_admin_and_is_ignored_for_member(): void
    {
        $this->review($this->makePost('승인 대기'));
        $this->makePost('검수 없음');

        $res = $this->actingAs($this->admin)->getJson('/api/feedback?type=bug&review_pending=1')->assertOk();
        $this->assertSame(['승인 대기'], collect($res->json('posts'))->pluck('title')->all());

        $res = $this->actingAs($this->member)->getJson('/api/feedback?type=bug&review_pending=1')->assertOk();
        $this->assertCount(2, $res->json('posts'));
    }

    public function test_admin_approves_review_and_post_moves_to_reviewing(): void
    {
        $post = $this->makePost();
        $this->review($post);

        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'approved', 'note' => '이대로 진행'])
            ->assertOk()
            ->assertJsonPath('status', 'reviewing')
            ->assertJsonPath('review.decision', 'approved');

        $review = $post->fresh()->review;
        $this->assertSame('이대로 진행', $review->decision_note);
        $this->assertSame($this->admin->id, $review->decided_by);
        $this->assertNotNull($review->decided_at);
    }

    public function test_hold_and_reject_decisions_keep_post_status(): void
    {
        $post = $this->makePost();
        $this->review($post);

        foreach (['hold', 'rejected'] as $decision) {
            $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => $decision])
                ->assertOk()->assertJsonPath('status', 'waiting');
        }
    }

    public function test_withdrawing_approval_reverts_post_status(): void
    {
        foreach (['hold' => 'hold', 'rejected' => 'waiting', 'revise' => 'waiting'] as $decision => $expected) {
            $post = $this->makePost("철회 {$decision}");
            $this->review($post);
            $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'approved'])
                ->assertJsonPath('status', 'reviewing');

            $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => $decision, 'note' => '다시 보자'])
                ->assertOk()->assertJsonPath('status', $expected);
            $this->assertSame($expected, $post->fresh()->status);
        }
    }

    public function test_decision_is_rejected_for_done_or_rejected_post(): void
    {
        foreach (['done', 'rejected'] as $status) {
            $post = $this->makePost("닫힌 글 {$status}", $status);
            $this->review($post);

            $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'approved'])
                ->assertStatus(409);
            $this->assertSame('pending', $post->fresh()->review->decision);
        }
    }

    public function test_revise_requires_note(): void
    {
        $post = $this->makePost();
        $this->review($post);

        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'revise'])
            ->assertUnprocessable();
        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'revise', 'note' => '원인을 다시 확인'])
            ->assertOk();
        $this->assertSame('revise', $post->fresh()->review->decision);
    }

    public function test_member_cannot_decide_and_missing_or_applied_review_is_rejected(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'approved'])->assertNotFound();

        $this->review($post, ['decision' => 'approved', 'applied_at' => now()]);
        $this->actingAs($this->member)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/review-decision", ['decision' => 'hold'])->assertStatus(409);
    }

    public function test_result_requires_approval_then_marks_post_done_and_notifies_author(): void
    {
        Notification::fake();
        $post = $this->makePost();
        $this->review($post);

        $this->postJson("/api/agent/feedback/{$post->id}/result", ['result_note' => '수정 완료'], self::TOKEN)->assertStatus(409);
        $this->assertSame('waiting', $post->fresh()->status);

        $post->review->update(['decision' => 'approved']);
        $this->postJson("/api/agent/feedback/{$post->id}/result", ['result_note' => '수정 완료 (abc123)'], self::TOKEN)
            ->assertOk()->assertJsonPath('status', 'done');

        $post->refresh();
        $this->assertSame('done', $post->status);
        $this->assertNotNull($post->done_at);
        $this->assertSame('수정 완료 (abc123)', $post->review->result_note);
        $this->assertNotNull($post->review->applied_at);
        Notification::assertSentTo($this->member, FeedbackActivity::class);

        // 반영 완료로 닫힌 검수에는 다시 기록할 수 없다
        $this->postJson("/api/agent/feedback/{$post->id}/result", ['result_note' => '중복 기록'], self::TOKEN)->assertStatus(409);
        $this->assertSame('수정 완료 (abc123)', $post->fresh()->review->result_note);
    }

    public function test_result_with_done_false_keeps_post_open(): void
    {
        Notification::fake();
        $post = $this->makePost('부분 반영', 'reviewing');
        $this->review($post, ['decision' => 'approved']);

        $this->postJson("/api/agent/feedback/{$post->id}/result", ['result_note' => '일부만 반영', 'done' => false], self::TOKEN)
            ->assertOk()->assertJsonPath('status', 'reviewing');

        Notification::assertNothingSent();
        $this->assertNull($post->fresh()->review->applied_at);

        // 일부 반영 건은 승인 목록에 남아 후속 작업을 이어갈 수 있고, 이후 완료로 닫을 수 있다
        $res = $this->getJson('/api/agent/feedback?scope=approved', self::TOKEN)->assertOk();
        $this->assertSame(['부분 반영'], collect($res->json('posts'))->pluck('title')->all());
        $this->assertSame('일부만 반영', $res->json('posts.0.review.result_note'));

        $this->postJson("/api/agent/feedback/{$post->id}/result", ['result_note' => '나머지도 반영'], self::TOKEN)
            ->assertOk()->assertJsonPath('status', 'done');
        $this->assertCount(0, $this->getJson('/api/agent/feedback?scope=approved', self::TOKEN)->json('posts'));
    }

    public function test_agent_downloads_attachment_with_token_only(): void
    {
        Storage::fake();
        $post = $this->makePost();
        Storage::put('feedback/1/shot.png', 'png-bytes');
        $attachment = $post->attachments()->create([
            'file_name' => 'shot.png', 'file_path' => 'feedback/1/shot.png', 'mime_type' => 'image/png', 'file_size' => 9,
        ]);

        $this->get("/api/agent/feedback-attachments/{$attachment->id}")->assertUnauthorized();
        $this->get("/api/agent/feedback-attachments/{$attachment->id}", self::TOKEN)->assertOk();

        $res = $this->getJson('/api/agent/feedback?scope=review', self::TOKEN)->assertOk();
        $this->assertStringEndsWith("/api/agent/feedback-attachments/{$attachment->id}", $res->json('posts.0.attachments.0.url'));
    }

    public function test_feedback_page_renders_review_area_for_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/feedback')
            ->assertOk()
            ->assertSee('id="fbReviewPendingBtn"', false)
            ->assertSee('if(FB_IS_ADMIN && p.review) html += fbReviewHtml(p);', false);

        $this->actingAs($this->member)->get('/feedback')
            ->assertOk()
            ->assertDontSee('id="fbReviewPendingBtn"', false);
    }
}
