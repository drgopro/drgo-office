<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 공용 이미지 뷰어 — 확대/축소·스와이프·넘김을 전 게시판에 제공 (캘린더 앨범과 동일) */
class ImageViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_viewer_is_available_on_boards(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        foreach (['/feedback', '/todos', '/'] as $path) {
            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee('window.drgoViewer', false)
                ->assertSee('id="dvw"', false);
        }
    }

    public function test_feedback_and_todos_use_shared_viewer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/feedback')->assertOk()
            ->assertSee('fbLbOpenFrom', false)
            ->assertDontSee('function fbLbOpen(url)', false);

        $this->actingAs($user)->get('/todos')->assertOk()
            ->assertSee('drgoViewer.open', false);
    }

    public function test_wiki_content_images_open_shared_viewer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create(['title' => '이미지 글', 'content' => '<p><img src="/wiki-files/1"></p>', 'category' => '일반']);

        $this->actingAs($user)->get("/wiki/{$wiki->id}")->assertOk()
            ->assertSee('drgoViewer.open(list', false);
    }
}
