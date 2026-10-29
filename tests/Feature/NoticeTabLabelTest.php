<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 공지/업데이트 게시물을 탭으로 열 때 탭 이름은 게시판 라벨(공지사항/위키)이어야 하며 게시물 제목을 넘기지 않는다 */
class NoticeTabLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_notice_and_update_rows_open_tabs_without_post_title(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $notice = Notice::create(['title' => '대시보드 공지 제목', 'content' => '<p>x</p>', 'is_draft' => false, 'created_by' => $admin->id]);
        $update = Wiki::create([
            'title' => '대시보드 업데이트 제목', 'type' => 'update', 'category' => '업데이트',
            'content' => '<p>x</p>', 'is_draft' => false, 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("openNav('notices','/notices/{$notice->id}');", $html);
        $this->assertStringContainsString("openNav('wiki','/wiki/{$update->id}');", $html);
        $this->assertStringNotContainsString("'/notices/{$notice->id}','대시보드 공지 제목'", $html);
        $this->assertStringNotContainsString("'/wiki/{$update->id}','대시보드 업데이트 제목'", $html);
    }

    public function test_global_notice_bar_opens_tab_without_post_title(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $notice = Notice::create(['title' => '띠 공지 제목', 'content' => '<p>x</p>', 'is_pinned' => true, 'is_draft' => false, 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("drgoTabs.openNav('notices','/notices/{$notice->id}');", $html);
        $this->assertStringNotContainsString("'/notices/{$notice->id}','띠 공지 제목'", $html);
    }
}
