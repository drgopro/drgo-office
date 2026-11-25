<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFeedback;
use App\Models\User;
use App\Notifications\MentionAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** 프로젝트 피드백 스레드 — 새 글은 담당자, 답글은 스레드 참여자, @태그는 담당자가 아니어도 알림 */
class ProjectFeedbackThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $owner;

    private User $extra;

    private User $outsider;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->author = User::factory()->create(['role' => 'admin', 'display_name' => '작성자']);
        $this->owner = User::factory()->create(['role' => 'admin', 'display_name' => '대표담당']);
        $this->extra = User::factory()->create(['role' => 'admin', 'display_name' => '추가담당']);
        $this->outsider = User::factory()->create(['role' => 'admin', 'display_name' => '바깥사람']);
        $this->project = Project::create(['name' => '스레드 프로젝트', 'assigned_user_id' => $this->owner->id]);
        $this->project->syncExtraAssignees([$this->extra->id]);
    }

    private function postFeedback(User $as, string $content, ?int $parentId = null)
    {
        return $this->actingAs($as)->postJson("/api/projects/{$this->project->id}/memos", ['content' => $content, 'parent_id' => $parentId]);
    }

    public function test_new_post_notifies_all_assignees_but_not_author_or_outsider(): void
    {
        $id = $this->postFeedback($this->author, '설치 일정 확인 부탁드립니다')->assertCreated()->json('id');

        foreach ([$this->owner, $this->extra] as $assignee) {
            Notification::assertSentTo($assignee, MentionAlert::class, fn (MentionAlert $n) => str_contains($n->title, '새 피드백')
                && str_ends_with($n->url, "#pfb-{$id}"));
        }
        Notification::assertNotSentTo($this->author, MentionAlert::class);
        Notification::assertNotSentTo($this->outsider, MentionAlert::class);
    }

    public function test_tag_notifies_non_assignee_and_assignee_gets_only_one_alert(): void
    {
        $this->postFeedback($this->author, '@바깥사람 @대표담당 확인해 주세요')->assertCreated();

        Notification::assertSentTo($this->outsider, MentionAlert::class, fn (MentionAlert $n) => str_contains($n->title, '태그'));
        Notification::assertSentToTimes($this->owner, MentionAlert::class, 1); // 태그 알림 하나로 충분
        Notification::assertSentTo($this->owner, MentionAlert::class, fn (MentionAlert $n) => str_contains($n->title, '태그'));
        Notification::assertSentTo($this->extra, MentionAlert::class, fn (MentionAlert $n) => str_contains($n->title, '새 피드백'));
    }

    public function test_reply_notifies_thread_participants_not_all_assignees(): void
    {
        $threadId = $this->postFeedback($this->owner, '자재 도착했나요?')->json('id');
        $this->postFeedback($this->outsider, '오늘 도착 예정입니다', $threadId)->assertCreated();
        Notification::fake(); // 이전 알림 초기화

        $this->postFeedback($this->author, '확인했습니다', $threadId)->assertCreated()->assertJson(['parent_id' => $threadId]);

        Notification::assertSentTo($this->owner, MentionAlert::class, fn (MentionAlert $n) => str_contains($n->title, '답글')
            && str_ends_with($n->url, "#pfb-{$threadId}"));
        Notification::assertSentTo($this->outsider, MentionAlert::class, fn (MentionAlert $n) => str_contains($n->title, '답글'));
        Notification::assertNotSentTo($this->extra, MentionAlert::class); // 스레드에 참여하지 않은 담당자
        Notification::assertNotSentTo($this->author, MentionAlert::class);
    }

    public function test_reply_must_target_top_level_post_of_same_project(): void
    {
        $threadId = $this->postFeedback($this->author, '글')->json('id');
        $replyId = $this->postFeedback($this->author, '답글', $threadId)->json('id');
        $other = Project::create(['name' => '다른 프로젝트']);
        $otherPost = ProjectFeedback::create(['project_id' => $other->id, 'user_id' => $this->author->id, 'content' => '남의 글']);

        $this->postFeedback($this->author, '답글의 답글', $replyId)->assertStatus(422);
        $this->postFeedback($this->author, '남의 글에 답글', $otherPost->id)->assertStatus(422);
    }

    public function test_duplicate_submit_creates_one_post_and_one_alert(): void
    {
        $this->postFeedback($this->author, '중복 전송')->assertCreated();
        $this->postFeedback($this->author, '중복 전송')->assertCreated();

        $this->assertSame(1, ProjectFeedback::where('content', '중복 전송')->count());
        Notification::assertSentToTimes($this->owner, MentionAlert::class, 1);
    }

    public function test_deleting_post_removes_its_replies(): void
    {
        $threadId = $this->postFeedback($this->author, '지울 글')->json('id');
        $this->postFeedback($this->owner, '답글', $threadId);

        $this->actingAs($this->author)->deleteJson("/api/project-memos/{$threadId}")->assertOk();

        $this->assertSame(0, ProjectFeedback::where('project_id', $this->project->id)->count());
    }

    public function test_project_page_renders_threads_with_reply_box_and_mentions(): void
    {
        $threadId = $this->postFeedback($this->author, '@바깥사람 첫 글')->json('id');
        $this->postFeedback($this->owner, '첫 답글', $threadId);

        $this->actingAs($this->author)->get("/projects/{$this->project->id}")->assertOk()
            ->assertSee('id="pfb-'.$threadId.'"', false)
            ->assertSee('답글 1개')
            ->assertSee('첫 답글')
            ->assertSee('<span class="pfb-mention">@바깥사람</span>', false)
            ->assertSee('id="pfbReplyInput'.$threadId.'"', false)
            ->assertSee('pfbFocusFromHash', false);
    }
}
