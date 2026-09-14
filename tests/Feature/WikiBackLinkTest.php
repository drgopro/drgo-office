<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 ← 목록 복귀 — 게시물 진입 시점의 목록 상태(back)로 돌아가 카테고리 튕김 방지 */
class WikiBackLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_index_doc_click_uses_back_preserving_opener(): void
    {
        Wiki::create(['title' => '문서', 'content' => '<p>본문</p>', 'type' => 'normal', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->get('/wiki')->assertOk()
            ->assertSee('openWikiDoc', false); // 목록 클릭이 back 파라미터를 붙이는 헬퍼 경유
    }

    public function test_back_param_restores_origin_list_state(): void
    {
        $wiki = Wiki::create(['title' => '업데이트 노트 9/9', 'content' => '<p>내용</p>', 'type' => 'update', 'created_by' => $this->admin->id]);

        // 업데이트 게시판에서 진입 — 게시판으로 복귀
        $this->actingAs($this->admin)->get("/wiki/{$wiki->id}?back=".urlencode('type=update'))
            ->assertOk()->assertSee('wiki?type=update', false);

        // 전체 문서에서 진입(back 빈 값) — 전체 문서로 복귀 (문서 자신의 분류로 튕기지 않음)
        $res = $this->actingAs($this->admin)->get("/wiki/{$wiki->id}?back=")->assertOk();
        $this->assertStringNotContainsString('wiki?type=update', $res->getContent());
    }

    public function test_without_back_falls_back_to_document_category(): void
    {
        $cat = WikiCategory::create(['name' => '업무 매뉴얼', 'sort_order' => 1]);
        $wiki = Wiki::create(['title' => '매뉴얼', 'content' => '<p>내용</p>', 'type' => 'normal', 'category_id' => $cat->id, 'created_by' => $this->admin->id]);

        // 위젯·알림 등 목록 밖에서 직접 열람 — 기존처럼 문서 자신의 카테고리로
        $this->actingAs($this->admin)->get("/wiki/{$wiki->id}")
            ->assertOk()->assertSee("wiki?cat={$cat->id}", false);
    }

    public function test_back_param_ignores_unknown_keys(): void
    {
        $wiki = Wiki::create(['title' => '문서', 'content' => '<p>내용</p>', 'type' => 'normal', 'created_by' => $this->admin->id]);

        // 허용 목록 밖 파라미터는 복귀 링크에 전파되지 않음
        $res = $this->actingAs($this->admin)->get("/wiki/{$wiki->id}?back=".urlencode('cat=3&evil=1'))->assertOk();
        preg_match('/<a href="([^"]*)" class="wiki-back">/', $res->getContent(), $m);
        $this->assertStringContainsString('wiki?cat=3', $m[1] ?? '');
        $this->assertStringNotContainsString('evil', $m[1] ?? 'evil');
    }

    public function test_destroy_redirects_to_back_list(): void
    {
        $wiki = Wiki::create(['title' => '지울 노트', 'content' => '<p>내용</p>', 'type' => 'update', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->delete("/wiki/{$wiki->id}", ['back' => 'type=update'])
            ->assertRedirect(route('wiki.index', ['type' => 'update']));
        $this->assertDatabaseMissing('wikis', ['id' => $wiki->id]);
    }
}
