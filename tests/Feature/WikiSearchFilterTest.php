<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 검색 필터 — 검색어/기간(작성일·수정일)/작성자 */
class WikiSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    private function makeWiki(string $title, array $overrides = [], ?string $updatedAt = null): Wiki
    {
        $wiki = Wiki::create(array_merge([
            'title' => $title,
            'content' => '<p>'.$title.' 내용</p>',
            'category' => '일반',
        ], $overrides));
        if ($updatedAt) {
            $wiki->forceFill(['created_at' => $updatedAt, 'updated_at' => $updatedAt])->save();
        }

        return $wiki;
    }

    /** viewData('wikis')의 제목 목록 */
    private function titles($res): array
    {
        return $res->viewData('wikis')->pluck('title')->all();
    }

    public function test_filters_by_updated_date_range(): void
    {
        $this->makeWiki('6월 문서', [], '2026-06-10 10:00:00');
        $this->makeWiki('7월 문서', [], '2026-07-05 10:00:00');
        $this->makeWiki('8월 문서', [], '2026-08-01 10:00:00');

        $res = $this->actingAs($this->member())->get('/wiki?date_from=2026-07-01&date_to=2026-07-31');

        $res->assertOk();
        $this->assertSame(['7월 문서'], $this->titles($res));
    }

    public function test_filters_by_created_date_field(): void
    {
        // 6월에 작성했지만 7월에 수정된 문서 — 작성일 기준 6월 조회에 포함되어야 함
        $wiki = $this->makeWiki('6월 작성 문서', [], '2026-06-10 10:00:00');
        $wiki->forceFill(['updated_at' => '2026-07-20 09:00:00'])->save();
        $this->makeWiki('7월 작성 문서', [], '2026-07-05 10:00:00');

        $res = $this->actingAs($this->member())
            ->get('/wiki?date_field=created&date_from=2026-06-01&date_to=2026-06-30');

        $res->assertOk();
        $this->assertSame(['6월 작성 문서'], $this->titles($res));
    }

    public function test_filters_by_author(): void
    {
        $kim = User::factory()->create(['role' => 'member', 'display_name' => '김작성']);
        $lee = User::factory()->create(['role' => 'member', 'display_name' => '이작성']);
        $this->makeWiki('김의 문서', ['created_by' => $kim->id]);
        $this->makeWiki('이의 문서', ['created_by' => $lee->id]);

        $res = $this->actingAs($this->member())->get('/wiki?author='.$kim->id);

        $res->assertOk();
        $this->assertSame(['김의 문서'], $this->titles($res));
        // 작성자 옵션에는 문서를 쓴 사용자만 노출
        $this->assertEqualsCanonicalizing([$kim->id, $lee->id], $res->viewData('authors')->pluck('id')->all());
    }

    public function test_search_combines_with_date_filter(): void
    {
        $this->makeWiki('세팅 가이드 v1', [], '2026-06-10 10:00:00');
        $this->makeWiki('세팅 가이드 v2', [], '2026-07-05 10:00:00');
        $this->makeWiki('무관한 문서', [], '2026-07-06 10:00:00');

        $res = $this->actingAs($this->member())
            ->get('/wiki?search=세팅&date_from=2026-07-01&date_to=2026-07-31');

        $res->assertOk();
        $this->assertSame(['세팅 가이드 v2'], $this->titles($res));
    }

    public function test_filter_bar_renders_collapsed_by_default(): void
    {
        $res = $this->actingAs($this->member())->get('/wiki');

        $res->assertOk()
            ->assertSee('id="wikiFilterForm"', false)
            ->assertSee('id="wikiFilterToggle"', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('name="author"', false)
            ->assertSee('작성자 전체')
            // 조회 범위 드롭다운 — 게시판·미분류·카테고리를 필터 바에서 직접 선택
            ->assertSee('id="wfScope"', false)
            ->assertSee('type:meeting', false)
            ->assertSee('(미분류)')
            // 기본은 접힘 상태
            ->assertSee('class="wiki-filter-bar"', false)
            ->assertDontSee('class="wiki-filter-bar open"', false);
    }

    public function test_filter_bar_opens_when_filters_active(): void
    {
        $res = $this->actingAs($this->member())->get('/wiki?search=테스트');

        $res->assertOk()->assertSee('class="wiki-filter-bar open"', false);
    }
}
