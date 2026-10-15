<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키/공지 읽음 확인 — 문서 열람 기록 + 상단 '읽음 n명 · 안읽음 n명' 표시 */
class WikiReadStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_document_records_read_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create(['title' => '읽음 테스트', 'type' => 'notice', 'content' => 'x', 'is_draft' => false, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk();
        $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk(); // 재방문해도 1행 유지

        $this->assertSame(1, WikiRead::where('wiki_id', $wiki->id)->where('user_id', $admin->id)->count());
    }

    public function test_read_status_chip_shows_reader_and_unreader_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'display_name' => '관리자A']);
        $member = User::factory()->create(['role' => 'member', 'display_name' => '멤버B']);
        User::factory()->create(['role' => 'member', 'display_name' => '비활성C', 'is_active' => false]); // 집계 제외
        $wiki = Wiki::create(['title' => '읽음 집계', 'type' => 'notice', 'content' => 'x', 'is_draft' => false, 'created_by' => $admin->id]);

        // 관리자가 먼저 읽음 → 멤버 화면: 읽음 2명(본인 포함) · 안읽음 0명
        $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk();
        $html = $this->actingAs($member)->get("/wiki/{$wiki->id}")->assertOk()->getContent();

        $this->assertStringContainsString('읽음 2명', $html);
        $this->assertStringContainsString('안읽음 0명', $html);
        $this->assertStringContainsString('관리자A', $html);
        $this->assertStringContainsString('멤버B', $html);
        $this->assertStringNotContainsString('비활성C', $html);
    }

    public function test_unread_staff_listed_in_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'display_name' => '관리자A']);
        User::factory()->create(['role' => 'member', 'display_name' => '안읽은멤버']);
        $wiki = Wiki::create(['title' => '안읽음 명단', 'type' => 'normal', 'content' => 'x', 'is_draft' => false, 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk()->getContent();

        $this->assertStringContainsString('안읽음 1명', $html);
        $this->assertStringContainsString('안읽은멤버', $html);
    }

    public function test_restricted_document_counts_only_viewable_staff(): void
    {
        $team = Team::create(['name' => '허용팀', 'slug' => 'wrs-team', 'permissions' => []]);
        $admin = User::factory()->create(['role' => 'admin', 'display_name' => '관리자A']);
        $inTeam = User::factory()->create(['role' => 'member', 'display_name' => '팀원B', 'team_id' => $team->id]);
        User::factory()->create(['role' => 'member', 'display_name' => '타팀C']); // 열람 불가 — 집계 제외
        $wiki = Wiki::create(['title' => '제한 문서', 'type' => 'normal', 'content' => 'x', 'is_draft' => false,
            'created_by' => $admin->id, 'allowed_team_ids' => [$team->id]]);

        $html = $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk()->getContent();

        $this->assertStringContainsString('읽음 1명', $html); // 관리자 본인
        $this->assertStringContainsString('안읽음 1명', $html); // 팀원B만
        $this->assertStringContainsString('팀원B', $html);
        $this->assertStringNotContainsString('타팀C', $html);
    }

    public function test_draft_document_shows_no_read_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create(['title' => '임시 문서', 'type' => 'normal', 'content' => 'x', 'is_draft' => true, 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)->get("/wiki/{$wiki->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('id="wrsPanel"', $html);
        $this->assertSame(0, WikiRead::where('wiki_id', $wiki->id)->count());
    }
}
