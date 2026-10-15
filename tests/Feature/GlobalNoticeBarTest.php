<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 전역 공지 띠 — 최신 공지사항 1건을 모든 탭(셸) 상단에 표시 */
class GlobalNoticeBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_notice_appears_as_global_bar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $old = Wiki::create(['title' => '옛 공지', 'type' => 'notice', 'content' => 'x', 'is_draft' => false, 'created_by' => $admin->id]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        Wiki::create(['title' => '최신 공지 제목', 'type' => 'notice', 'content' => 'y', 'is_draft' => false, 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="globalNoticeBar"', $html);
        $bar = $this->barSegment($html);
        $this->assertStringContainsString('최신 공지 제목', $bar);
        $this->assertStringNotContainsString('옛 공지', $bar); // 띠에는 최신 1건만
    }

    /** 전역 띠 마크업 구간만 — 대시보드 공지 카드 등 다른 영역과 분리해 검증 */
    private function barSegment(string $html): string
    {
        $start = strpos($html, 'id="globalNoticeBar"');
        $end = strpos($html, '</div>', $start);

        return substr($html, $start, $end - $start);
    }

    public function test_bar_absent_without_notice_or_with_draft_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Wiki::create(['title' => '임시 공지', 'type' => 'notice', 'content' => 'x', 'is_draft' => true, 'created_by' => $admin->id]);
        Wiki::create(['title' => '일반 문서', 'type' => 'normal', 'content' => 'x', 'is_draft' => false, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertDontSee('id="globalNoticeBar"', false);
    }

    public function test_restricted_notice_hidden_from_other_team_member(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Wiki::create(['title' => '팀한정 공지', 'type' => 'notice', 'content' => 'x', 'is_draft' => false,
            'created_by' => $admin->id, 'allowed_team_ids' => [999]]);
        $member = User::factory()->create(['role' => 'member']);

        $html = $this->actingAs($member)->get('/')->assertOk()->getContent();

        // 열람 권한 없는 공지는 전역 띠로 노출되지 않는다 (띠 자체가 안 그려짐)
        $this->assertStringNotContainsString('id="globalNoticeBar"', $html);
    }
}
