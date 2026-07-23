<?php

namespace Tests\Feature;

use App\Models\FeedbackPost;
use App\Models\User;
use App\Notifications\FeedbackActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackBoardTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    private function makePost(User $author, array $attrs = []): FeedbackPost
    {
        return FeedbackPost::create(array_merge([
            'type' => 'bug',
            'title' => '저장 버튼이 두 번 눌러야 반응함',
            'body' => '재현 경로...',
            'page' => '캘린더',
            'created_by' => $author->id,
        ], $attrs));
    }

    public function test_게스트는_접근_불가(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);

        $this->actingAs($guest)->get('/feedback')->assertForbidden();
        $this->actingAs($guest)->getJson('/api/feedback?type=bug')->assertForbidden();
    }

    public function test_버그_제보는_스크린샷_필수(): void
    {
        $this->actingAs($this->member())->postJson('/api/feedback', [
            'type' => 'bug',
            'title' => '버그입니다',
            'page' => '캘린더',
        ])->assertUnprocessable()->assertJsonValidationErrors(['files']);
    }

    public function test_기능_요청은_스크린샷_없이_등록_가능(): void
    {
        Notification::fake();

        $this->actingAs($this->member())->postJson('/api/feedback', [
            'type' => 'feature',
            'title' => '다크모드 추가해주세요',
            'page' => '기타',
        ])->assertCreated();

        $this->assertDatabaseHas('feedback_posts', ['title' => '다크모드 추가해주세요', 'status' => 'waiting']);
    }

    public function test_영상_첨부_업로드와_인라인_플레이어(): void
    {
        Notification::fake();
        Storage::fake();

        $res = $this->actingAs($this->member())->post('/api/feedback', [
            'type' => 'feature',
            'title' => '영상 첨부 테스트',
            'page' => '기타',
            'files' => [UploadedFile::fake()->create('bug-repro.mp4', 2048, 'video/mp4')],
        ], ['Accept' => 'application/json'])->assertCreated();

        // 영상 여부 플래그 — 프론트가 img 대신 인라인 플레이어(video)로 렌더
        $this->assertTrue($res->json('attachments.0.is_video'));

        // 페이지에 플레이어 렌더 코드 포함
        $this->actingAs($this->member())->get('/feedback')
            ->assertOk()
            ->assertSee('fb-att-vid', false)
            ->assertSee('preload="metadata" controls', false);
    }

    public function test_허용되지_않는_파일_형식은_거부(): void
    {
        $this->actingAs($this->member())->post('/api/feedback', [
            'type' => 'feature',
            'title' => '실행파일 첨부',
            'page' => '기타',
            'files' => [UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_버그_제보_스크린샷_첨부_등록(): void
    {
        Notification::fake();
        Storage::fake();

        $res = $this->actingAs($this->member())->post('/api/feedback', [
            'type' => 'bug',
            'title' => '버그입니다',
            'body' => '재현: ...',
            'page' => '캘린더',
            'files' => [UploadedFile::fake()->image('shot.png', 800, 600)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1, count($res->json('attachments')));
        $this->assertDatabaseHas('feedback_attachments', ['file_name' => 'shot.png']);
    }

    public function test_새_제보는_개발자에게_알림(): void
    {
        Notification::fake();

        $dev = $this->master();
        $this->actingAs($this->member())->postJson('/api/feedback', [
            'type' => 'feature',
            'title' => '알림 테스트',
            'page' => '기타',
        ])->assertCreated();

        Notification::assertSentTo($dev, FeedbackActivity::class);
    }

    public function test_상태_변경은_master만(): void
    {
        $post = $this->makePost($this->member());

        $this->actingAs($this->member())->postJson("/api/feedback/{$post->id}/status", [
            'status' => 'reviewing',
        ])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson("/api/feedback/{$post->id}/status", [
            'status' => 'reviewing',
        ])->assertForbidden();

        Notification::fake();
        $this->actingAs($this->master())->postJson("/api/feedback/{$post->id}/status", [
            'status' => 'reviewing',
        ])->assertOk();

        $this->assertSame('reviewing', $post->fresh()->status);
    }

    public function test_반려는_사유_필수(): void
    {
        $post = $this->makePost($this->member());
        $dev = $this->master();

        $this->actingAs($dev)->postJson("/api/feedback/{$post->id}/status", [
            'status' => 'rejected',
        ])->assertUnprocessable();

        Notification::fake();
        $this->actingAs($dev)->postJson("/api/feedback/{$post->id}/status", [
            'status' => 'rejected',
            'reject_reason' => '재현 불가',
        ])->assertOk();

        $this->assertSame('재현 불가', $post->fresh()->reject_reason);
    }

    public function test_상태_변경_시_작성자에게_알림(): void
    {
        Notification::fake();

        $author = $this->member();
        $post = $this->makePost($author);

        $this->actingAs($this->master())->postJson("/api/feedback/{$post->id}/status", [
            'status' => 'done',
        ])->assertOk();

        Notification::assertSentTo($author, FeedbackActivity::class);
    }

    public function test_완료_반려_후_작성자_수정_불가(): void
    {
        $author = $this->member();
        $post = $this->makePost($author, ['status' => 'done']);

        $this->actingAs($author)->patchJson("/api/feedback/{$post->id}", [
            'title' => '수정 시도', 'page' => '캘린더',
        ])->assertForbidden();

        $this->actingAs($author)->deleteJson("/api/feedback/{$post->id}")->assertForbidden();

        // 개발자는 가능
        $this->actingAs($this->master())->patchJson("/api/feedback/{$post->id}", [
            'title' => '개발자 수정', 'page' => '캘린더',
        ])->assertOk();
    }

    public function test_타인_글_수정_불가(): void
    {
        $post = $this->makePost($this->member());

        $this->actingAs($this->member())->patchJson("/api/feedback/{$post->id}", [
            'title' => '남의 글 수정', 'page' => '캘린더',
        ])->assertForbidden();
    }

    public function test_첨부_추가와_삭제(): void
    {
        Storage::fake();
        $author = $this->member();
        $post = $this->makePost($author);

        // 작성자가 첨부 추가
        $this->actingAs($author)->post("/api/feedback/{$post->id}/attachments", [
            'files' => [UploadedFile::fake()->image('추가스크린샷.png')],
        ])->assertOk();
        $attachment = $post->attachments()->first();
        $this->assertNotNull($attachment);
        Storage::assertExists($attachment->file_path);

        // 타인은 삭제 불가
        $this->actingAs($this->member())->deleteJson("/api/feedback-attachments/{$attachment->id}")->assertForbidden();

        // 작성자는 삭제 가능 (파일도 정리)
        $this->actingAs($author)->deleteJson("/api/feedback-attachments/{$attachment->id}")->assertOk();
        $this->assertSame(0, $post->attachments()->count());
        Storage::assertMissing($attachment->file_path);
    }

    public function test_첨부는_이미지만_허용(): void
    {
        Storage::fake();
        $author = $this->member();
        $post = $this->makePost($author);

        $this->actingAs($author)->post("/api/feedback/{$post->id}/attachments", [
            'files' => [UploadedFile::fake()->create('문서.pdf', 10)],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_관리자는_모든_글_수정_가능(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = $this->makePost($this->member(), ['status' => 'done']); // 잠긴 타인 글

        $this->actingAs($admin)->patchJson("/api/feedback/{$post->id}", [
            'title' => '관리자 수정', 'page' => '캘린더',
        ])->assertOk();

        $this->assertSame('관리자 수정', $post->fresh()->title);
    }

    public function test_의견_등록과_알림(): void
    {
        Notification::fake();

        $author = $this->member();
        $post = $this->makePost($author);
        $commenter = $this->member();

        $this->actingAs($commenter)->postJson("/api/feedback/{$post->id}/comments", [
            'body' => '저도 겪고 있어요 +1',
        ])->assertCreated();

        $this->assertDatabaseHas('feedback_comments', ['body' => '저도 겪고 있어요 +1']);
        Notification::assertSentTo($author, FeedbackActivity::class);
    }

    public function test_의견_중복_전송은_한_건만_저장(): void
    {
        // 등록 버튼 연타·IME Enter 이중 발화 — 10초 내 동일 내용은 저장하지 않고 기존 의견 반환
        $author = $this->member();
        $post = $this->makePost($author);
        $commenter = $this->member();

        $this->actingAs($commenter)->postJson("/api/feedback/{$post->id}/comments", ['body' => '중복 테스트'])->assertCreated();
        $this->actingAs($commenter)->postJson("/api/feedback/{$post->id}/comments", ['body' => '중복 테스트'])->assertOk();

        $this->assertSame(1, $post->comments()->where('body', '중복 테스트')->count());

        // 내용이 다르면 정상 등록, 다른 사용자의 동일 내용도 정상 등록
        $this->actingAs($commenter)->postJson("/api/feedback/{$post->id}/comments", ['body' => '다른 내용'])->assertCreated();
        $this->actingAs($this->member())->postJson("/api/feedback/{$post->id}/comments", ['body' => '중복 테스트'])->assertCreated();

        $this->assertSame(3, $post->comments()->count());
    }

    public function test_목록_필터와_탭별_카운트(): void
    {
        $author = $this->member();
        $this->makePost($author, ['status' => 'waiting']);
        $this->makePost($author, ['status' => 'done', 'title' => '완료된 버그']);
        $this->makePost($author, ['type' => 'feature', 'title' => '기능 요청 하나']);

        $res = $this->actingAs($author)->getJson('/api/feedback?type=bug')->assertOk();
        $this->assertSame(2, count($res->json('posts')));
        $this->assertSame(1, $res->json('counts.waiting'));
        $this->assertSame(1, $res->json('counts.done'));
        $this->assertSame(2, $res->json('tab_totals.bug'));
        $this->assertSame(1, $res->json('tab_totals.feature'));

        $res = $this->actingAs($author)->getJson('/api/feedback?type=bug&status=done')->assertOk();
        $this->assertSame(1, count($res->json('posts')));
        $this->assertSame('완료된 버그', $res->json('posts.0.title'));
    }

    public function test_정렬_오래된순(): void
    {
        $author = $this->member();
        $old = $this->makePost($author, ['title' => '먼저 등록']);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $this->makePost($author, ['title' => '나중 등록']);

        $res = $this->actingAs($author)->getJson('/api/feedback?type=bug&sort=oldest')->assertOk();
        $this->assertSame('먼저 등록', $res->json('posts.0.title'));
    }
}
