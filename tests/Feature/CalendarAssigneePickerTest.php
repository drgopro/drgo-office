<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 일정 모달 담당자 패널 — 본인 + 선택됨 + 최근 선택 3명만 접힘 노출, 나머지는 이름 검색 */
class CalendarAssigneePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_assignee_panel_uses_search_and_recent_three(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get('/calendar')->assertOk()->getContent();

        // 검색창 + 최근 선택 저장(최대 3명)
        $this->assertStringContainsString("search.id='assigneeSearch'", $html);
        $this->assertStringContainsString("placeholder='이름으로 담당자 검색'", $html);
        $this->assertStringContainsString("RECENT_ASSIGNEE_KEY='calRecentAssignees:'+CAL_USER_ID", $html); // 사용자별 키
        $this->assertStringContainsString('RECENT_ASSIGNEE_MAX=3', $html);
        // 저장·즉시 변경 시 최근 목록 갱신
        $this->assertStringContainsString('rememberRecentAssignees(selectedAssignees)', $html);
        $this->assertStringContainsString('if(adding) rememberRecentAssignees([a.id])', $html);
        // 접힘 상태 구성: 본인 → 선택 → 최근, 전체 펼치기 버튼은 없음
        $this->assertStringContainsString('visible=[...self, ...selected, ...recents]', $html);
        $this->assertStringNotContainsString('assigneeShowAll', $html);
        $this->assertStringNotContainsString('명 더보기 ▼', $html);
    }
}
