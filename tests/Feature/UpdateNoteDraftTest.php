<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Services\UpdateNoteGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class UpdateNoteDraftTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGitLog(string $output): void
    {
        // 배열 커맨드는 인자별 따옴표로 이스케이프되어 문자열화되므로 느슨한 패턴으로 매칭
        Process::fake(['*git*log*' => Process::result($output)]);
    }

    public function test_member_cannot_generate_update_note_draft(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->get('/admin/update-note-draft?from=2026-07-30&to=2026-07-30')
            ->assertForbidden();
    }

    public function test_admin_generates_draft_grouped_by_date_and_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->fakeGitLog(implode("\n", [
            "2026-07-30\tfeat(calendar): 요약 뷰에서 확정 상태 바로 지정",
            "2026-07-30\tfix(shipments): 추적기 내부 오류 안내 개선",
            "2026-07-30\trefactor(calendar): 단일 blade를 기능별 파일로 분할",
            "2026-07-29\tfeat(todos): 기한 보류 기능 추가",
        ]));

        $response = $this->actingAs($admin)->get('/admin/update-note-draft?from=2026-07-29&to=2026-07-30');

        $wiki = Wiki::latest('id')->first();
        $this->assertNotNull($wiki);
        $response->assertRedirect(route('wiki.create', ['draft' => $wiki->id]));

        $this->assertTrue($wiki->is_draft);
        $this->assertSame('update', $wiki->type);
        $this->assertSame($admin->id, $wiki->created_by);
        $this->assertSame('업데이트 노트 7/29 ~ 7/30', $wiki->title);

        // 일자별 제목 (최신일 우선)
        $this->assertStringContainsString('<h2>7/30 (목)</h2>', $wiki->content);
        $this->assertStringContainsString('<h2>7/29 (수)</h2>', $wiki->content);
        $this->assertTrue(
            strpos($wiki->content, '7/30 (목)') < strpos($wiki->content, '7/29 (수)'),
            '최신 날짜가 먼저 나와야 합니다'
        );

        // 섹션 분류 + type 라벨
        $this->assertStringContainsString('<h3>📅 캘린더</h3>', $wiki->content);
        $this->assertStringContainsString('<strong>[신규]</strong> 요약 뷰에서 확정 상태 바로 지정', $wiki->content);
        $this->assertStringContainsString('<h3>📦 배송</h3>', $wiki->content);
        $this->assertStringContainsString('<strong>[수정]</strong> 추적기 내부 오류 안내 개선', $wiki->content);
        // refactor는 내부 정비 섹션으로
        $this->assertStringContainsString('🛠 내부 정비', $wiki->content);
        $this->assertStringContainsString('단일 blade를 기능별 파일로 분할', $wiki->content);
    }

    public function test_no_commits_returns_plain_message_without_creating_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->fakeGitLog('');

        $response = $this->actingAs($admin)->get('/admin/update-note-draft?from=2026-01-01&to=2026-01-01');

        $response->assertOk();
        $this->assertStringContainsString('배포 커밋이 없습니다', $response->getContent());
        $this->assertSame(0, Wiki::count());
    }

    public function test_defaults_to_today_and_swaps_reversed_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->fakeGitLog("2026-07-30\tfeat(calendar): 오늘 배포");

        $this->actingAs($admin)->get('/admin/update-note-draft?from=2026-07-30&to=2026-07-28');

        $wiki = Wiki::latest('id')->first();
        $this->assertSame('업데이트 노트 7/28 ~ 7/30', $wiki->title);
    }

    public function test_generator_formats_sections_in_fixed_order(): void
    {
        $generator = new UpdateNoteGenerator;
        $html = $generator->buildHtml([
            ['date' => '2026-07-30', 'subject' => 'feat(data): SOOP 표기 통일'],
            ['date' => '2026-07-30', 'subject' => 'fix(calendar): 칩 정렬 개선'],
            ['date' => '2026-07-30', 'subject' => '형식 없는 커밋 제목'],
        ]);

        // 캘린더 → 시스템 → 기타 순서
        $this->assertTrue(strpos($html, '📅 캘린더') < strpos($html, '🔧 시스템'));
        $this->assertTrue(strpos($html, '🔧 시스템') < strpos($html, '🔹 기타'));
        $this->assertStringContainsString('<li>형식 없는 커밋 제목</li>', $html);
    }
}
