<?php

namespace Tests\Feature;

use App\Models\FeedbackPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 피드백 우선순위 — 관리자 지정, 전 멤버 노출, 필터·우선순위순 정렬 */
class FeedbackPriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->member = User::factory()->create(['role' => 'member']);
    }

    private function makePost(string $title, ?string $priority = null): FeedbackPost
    {
        return FeedbackPost::create([
            'type' => 'bug', 'title' => $title, 'page' => '캘린더',
            'status' => 'waiting', 'priority' => $priority, 'created_by' => $this->member->id,
        ]);
    }

    public function test_admin_sets_priority_and_member_cannot(): void
    {
        $post = $this->makePost('버그1');

        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/priority", ['priority' => 'high'])
            ->assertOk()->assertJson(['priority' => 'high']);
        $this->assertSame('high', $post->fresh()->priority);

        // null이면 해제
        $this->actingAs($this->admin)->postJson("/api/feedback/{$post->id}/priority", ['priority' => null])->assertOk();
        $this->assertNull($post->fresh()->priority);

        // 멤버는 지정 불가
        $this->actingAs($this->member)->postJson("/api/feedback/{$post->id}/priority", ['priority' => 'low'])
            ->assertForbidden();
    }

    public function test_member_sees_priority_and_can_filter_and_sort(): void
    {
        $this->makePost('낮음 글', 'low');
        $this->makePost('미지정 글');
        $this->makePost('높음 글', 'high');
        $this->makePost('중간 글', 'medium');

        // 멤버에게도 우선순위 라벨 노출
        $res = $this->actingAs($this->member)->getJson('/api/feedback?type=bug&sort=priority')->assertOk();
        $titles = collect($res->json('posts'))->pluck('title')->all();
        $this->assertSame(['높음 글', '중간 글', '낮음 글', '미지정 글'], $titles);
        $this->assertSame('높음', collect($res->json('posts'))->firstWhere('title', '높음 글')['priority_label']);

        // 우선순위 필터
        $res = $this->actingAs($this->member)->getJson('/api/feedback?type=bug&priority=high')->assertOk();
        $this->assertSame(['높음 글'], collect($res->json('posts'))->pluck('title')->all());
    }

    public function test_feedback_page_renders_priority_ui(): void
    {
        // 멤버: 배지·필터·정렬은 보이고, 관리자 지정 바 조건(FB_IS_ADMIN)은 JS 플래그로 제어
        $this->actingAs($this->member)->get('/feedback')->assertOk()
            ->assertSee('id="fbFilterPriority"', false)
            ->assertSee('우선순위순')
            ->assertSee('fbSetPriority', false)
            ->assertSee('fb-prio', false);
    }
}
