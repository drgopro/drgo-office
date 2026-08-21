<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 게시물 열람 권한(팀 제한) + 수정 권한 + 통계 팀 권한 */
class WikiPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Team $teamA;

    private Team $teamB;

    private User $author;

    private User $teamAMember;

    private User $teamBMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teamA = Team::create(['name' => '콘텐츠기획', 'slug' => 'content', 'permissions' => []]);
        $this->teamB = Team::create(['name' => '기술팀', 'slug' => 'tech', 'permissions' => []]);
        $this->author = User::factory()->create(['role' => 'member', 'team_id' => $this->teamB->id]);
        $this->teamAMember = User::factory()->create(['role' => 'member', 'team_id' => $this->teamA->id]);
        $this->teamBMember = User::factory()->create(['role' => 'member', 'team_id' => $this->teamB->id]);
    }

    private function makeRestrictedWiki(array $attrs = []): Wiki
    {
        return Wiki::create(array_merge([
            'title' => '팀A 전용 문서', 'type' => 'normal', 'content' => '<p>내용</p>',
            'allowed_team_ids' => [$this->teamA->id],
            'created_by' => $this->author->id, 'updated_by' => $this->author->id,
        ], $attrs));
    }

    // === 열람 권한 ===

    public function test_restricted_wiki_visible_only_to_allowed_team_author_and_admin(): void
    {
        $wiki = $this->makeRestrictedWiki();

        // 허용 팀(A) 소속 — 열람 가능
        $this->actingAs($this->teamAMember)->get("/wiki/{$wiki->id}")->assertOk();
        // 다른 팀(B) 소속 — 차단
        $this->actingAs($this->teamBMember)->get("/wiki/{$wiki->id}")->assertForbidden();
        // 작성자(팀B지만 본인 글) — 열람 가능
        $this->actingAs($this->author)->get("/wiki/{$wiki->id}")->assertOk();
        // 관리자 — 항상 열람 가능
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk();
    }

    public function test_restricted_wiki_hidden_from_index_list(): void
    {
        $this->makeRestrictedWiki(['title' => '비밀 문서제목']);
        Wiki::create(['title' => '공개 문서제목', 'type' => 'normal', 'content' => '<p>x</p>',
            'created_by' => $this->author->id, 'updated_by' => $this->author->id]);

        // 목록은 @json 페이로드로 렌더 — 유니코드 이스케이프된 제목으로 검사
        $secret = trim((string) json_encode('비밀 문서제목'), '"');
        $public = trim((string) json_encode('공개 문서제목'), '"');
        $html = $this->actingAs($this->teamBMember)->get('/wiki')->assertOk()->getContent();
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringContainsString($public, $html);

        $htmlA = $this->actingAs($this->teamAMember)->get('/wiki')->assertOk()->getContent();
        $this->assertStringContainsString($secret, $htmlA);
    }

    public function test_allowed_team_ids_saved_on_create_and_cleared_when_empty(): void
    {
        $res = $this->actingAs($this->author)->postJson('/wiki', [
            'title' => '제한 문서', 'content' => '<p>x</p>', 'type' => 'normal',
            'allowed_team_ids' => [$this->teamA->id],
        ])->assertCreated();
        $wiki = Wiki::find($res->json('id'));
        $this->assertSame([$this->teamA->id], array_map('intval', $wiki->allowed_team_ids));

        // 빈 배열로 수정 → 전체 공개(null)
        $this->actingAs($this->author)->patchJson("/wiki/{$wiki->id}", ['allowed_team_ids' => []])->assertSuccessful();
        $this->assertNull($wiki->fresh()->allowed_team_ids);
    }

    public function test_restricted_meeting_blocks_comments_from_other_team(): void
    {
        $wiki = $this->makeRestrictedWiki(['type' => 'meeting']);

        $this->actingAs($this->teamBMember)
            ->postJson("/wiki/{$wiki->id}/comments", ['content' => '댓글'])
            ->assertForbidden();
    }

    // === 수정 권한 (게스트) ===

    public function test_guest_cannot_create_update_or_delete_wiki(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $wiki = Wiki::create(['title' => '공개 문서', 'type' => 'normal', 'content' => '<p>x</p>',
            'created_by' => $this->author->id, 'updated_by' => $this->author->id]);

        $this->actingAs($guest)->postJson('/wiki', ['title' => 'x', 'content' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($guest)->patchJson("/wiki/{$wiki->id}", ['title' => 'y'])->assertForbidden();
        $this->actingAs($guest)->deleteJson("/wiki/{$wiki->id}")->assertForbidden();
        // 열람 페이지에는 수정/삭제 버튼이 렌더되지 않음
        $html = $this->actingAs($guest)->get("/wiki/{$wiki->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('onclick="toggleEdit()"', $html);
    }

    // === 통계 팀 권한 ===

    public function test_stats_requires_team_permission(): void
    {
        // 권한 없는 팀 멤버 — 차단
        $this->actingAs($this->teamBMember)->get('/marketing-report')->assertForbidden();

        // stats.view 권한을 가진 팀 — 허용
        $statsTeam = Team::create(['name' => '경영지원', 'slug' => 'biz', 'permissions' => ['stats.view']]);
        $statsMember = User::factory()->create(['role' => 'member', 'team_id' => $statsTeam->id]);
        $this->actingAs($statsMember)->get('/marketing-report')->assertOk();

        // 관리자 — 항상 허용
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/marketing-report')->assertOk();
    }
}
