<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiAttachment;
use App\Models\WikiComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WikiCommentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    private function meetingDoc(): Wiki
    {
        return Wiki::create(['title' => '주간 회의록', 'type' => 'meeting', 'content' => 'x', 'category' => '회의록']);
    }

    public function test_comment_saves_with_attachments(): void
    {
        $wiki = $this->meetingDoc();

        $res = $this->actingAs($this->member())->post("/wiki/{$wiki->id}/comments", [
            'body' => '스크린샷 첨부합니다.',
            'files' => [
                UploadedFile::fake()->image('screen.jpg', 800, 600),
                UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ],
        ]);

        $res->assertRedirect(route('wiki.show', $wiki));
        $comment = WikiComment::first();
        $attachments = $comment->attachments;
        $this->assertCount(2, $attachments);
        // 댓글·게시물 모두에 연결 (고아 정리 제외)
        $this->assertSame($wiki->id, $attachments->first()->wiki_id);
        $this->assertSame($comment->id, $attachments->first()->wiki_comment_id);
        foreach ($attachments as $att) {
            Storage::assertExists($att->file_path);
        }
    }

    public function test_reply_saves_with_attachment(): void
    {
        $wiki = $this->meetingDoc();
        $parent = $wiki->comments()->create(['user_id' => $this->member()->id, 'body' => '부모 댓글']);

        $this->actingAs($this->member())->post("/wiki/{$wiki->id}/comments", [
            'body' => '답글 이미지',
            'parent_id' => $parent->id,
            'files' => [UploadedFile::fake()->image('reply.png')],
        ])->assertRedirect(route('wiki.show', $wiki));

        $reply = WikiComment::where('parent_id', $parent->id)->first();
        $this->assertCount(1, $reply->attachments);
    }

    public function test_rejects_more_than_five_files(): void
    {
        $wiki = $this->meetingDoc();

        $this->actingAs($this->member())->post("/wiki/{$wiki->id}/comments", [
            'body' => '너무 많은 파일',
            'files' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 6)),
        ])->assertSessionHasErrors('files');
    }

    public function test_rejects_dangerous_extension(): void
    {
        $wiki = $this->meetingDoc();

        $this->actingAs($this->member())->post("/wiki/{$wiki->id}/comments", [
            'body' => '위험 파일',
            'files' => [UploadedFile::fake()->create('evil.html', 1, 'text/html')],
        ])->assertSessionHasErrors('files.0');
    }

    public function test_deleting_comment_removes_attachment_rows_and_files(): void
    {
        $wiki = $this->meetingDoc();
        $author = $this->member();

        $this->actingAs($author)->post("/wiki/{$wiki->id}/comments", [
            'body' => '부모', 'files' => [UploadedFile::fake()->image('a.jpg')],
        ]);
        $parent = WikiComment::first();

        $this->actingAs($author)->post("/wiki/{$wiki->id}/comments", [
            'body' => '답글', 'parent_id' => $parent->id, 'files' => [UploadedFile::fake()->image('b.jpg')],
        ]);

        $paths = WikiAttachment::pluck('file_path');
        $this->assertCount(2, $paths);

        // 부모 삭제 → 대댓글 + 양쪽 첨부(행/파일) 모두 정리
        $this->actingAs($author)->delete("/wiki-comments/{$parent->id}");

        $this->assertSame(0, WikiComment::count());
        $this->assertSame(0, WikiAttachment::count());
        foreach ($paths as $path) {
            Storage::assertMissing($path);
        }
    }

    public function test_comment_image_renders_with_thumb_and_viewer_data(): void
    {
        $wiki = $this->meetingDoc();
        $author = $this->member();

        $this->actingAs($author)->post("/wiki/{$wiki->id}/comments", [
            'body' => '이미지 확인', 'files' => [UploadedFile::fake()->image('view.jpg')],
        ]);
        $attachment = WikiAttachment::first();

        $page = $this->actingAs($author)->get("/wiki/{$wiki->id}");
        $page->assertOk()
            ->assertSee("/wiki-files/{$attachment->id}/thumb", false)
            ->assertSee('wikiCattOpen', false);
    }

    public function test_thumb_route_serves_image(): void
    {
        $wiki = $this->meetingDoc();
        $author = $this->member();

        $this->actingAs($author)->post("/wiki/{$wiki->id}/comments", [
            'body' => '썸네일', 'files' => [UploadedFile::fake()->image('t.jpg', 1200, 900)],
        ]);
        $attachment = WikiAttachment::first();

        $this->actingAs($author)->get("/wiki-files/{$attachment->id}/thumb")->assertOk();
    }
}
