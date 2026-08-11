<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 상세 → 목록 복귀 시 보던 게시물의 분류가 선택된 채로 돌아가는지 검증 */
class WikiBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    private function makeWiki(array $attrs = []): Wiki
    {
        return Wiki::create(array_merge([
            'title' => '테스트 문서',
            'type' => 'normal',
            'category' => '미분류',
            'content' => '<p>내용</p>',
            'created_by' => User::factory()->create()->id,
        ], $attrs));
    }

    public function test_back_link_carries_post_category(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $wiki = $this->makeWiki(['category_id' => $cat->id, 'category' => '매뉴얼']);

        $this->actingAs($this->member())->get("/wiki/{$wiki->id}")
            ->assertOk()
            ->assertSee('/wiki?cat='.$cat->id, false);
    }

    public function test_back_link_uses_uncat_for_uncategorized_post(): void
    {
        $wiki = $this->makeWiki();

        $this->actingAs($this->member())->get("/wiki/{$wiki->id}")
            ->assertOk()
            ->assertSee('/wiki?cat=uncat', false);
    }

    public function test_back_link_uses_type_for_special_post(): void
    {
        $wiki = $this->makeWiki(['type' => 'meeting', 'category' => '회의록']);

        $this->actingAs($this->member())->get("/wiki/{$wiki->id}")
            ->assertOk()
            ->assertSee('/wiki?type=meeting', false);
    }

    public function test_destroy_redirects_to_post_category_list(): void
    {
        $cat = WikiCategory::create(['name' => '매뉴얼', 'sort_order' => 1]);
        $user = $this->member();
        $wiki = $this->makeWiki(['category_id' => $cat->id, 'category' => '매뉴얼', 'created_by' => $user->id]);

        $this->actingAs($user)->delete("/wiki/{$wiki->id}")
            ->assertRedirect('/wiki?cat='.$cat->id);
        $this->assertDatabaseMissing('wikis', ['id' => $wiki->id]);
    }
}
