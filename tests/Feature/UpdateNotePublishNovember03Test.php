<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 10/29 ~ 11/3 업데이트 노트 발행 마이그레이션 — 위키 '업데이트' 게시판에 1회만 등록되고 롤백 시 제거되는지 검증 */
class UpdateNotePublishNovember03Test extends TestCase
{
    use RefreshDatabase;

    private const TITLE = '업데이트 노트 10/29 ~ 11/3';

    private function migration(): Migration
    {
        return require base_path('database/migrations/2026_11_03_180000_publish_update_note_2026_11_03.php');
    }

    public function test_skips_when_no_users_exist(): void
    {
        $this->migration()->up();

        $this->assertDatabaseMissing('wikis', ['title' => self::TITLE]);
    }

    public function test_publishes_update_note_authored_by_master_once(): void
    {
        User::factory()->create(['role' => 'member']);
        $master = User::factory()->create(['role' => 'master']);

        $this->migration()->up();
        $this->migration()->up(); // 재실행 안전

        $notes = Wiki::where('type', 'update')->where('title', self::TITLE)->get();
        $this->assertCount(1, $notes);

        $note = $notes->first();
        $this->assertSame($master->id, $note->created_by);
        $this->assertFalse((bool) $note->is_draft);
        $this->assertSame('업데이트', $note->category);
        $this->assertStringContainsString('<h2>11/3 (화)</h2>', $note->content);
        $this->assertStringContainsString('<h2>11/2 (월)</h2>', $note->content);
        $this->assertStringContainsString('<h2>10/30 (금)</h2>', $note->content);
        $this->assertStringContainsString('10/29 (목)', $note->content);
        $this->assertStringContainsString('AS 현황판', $note->content);
        $this->assertStringContainsString('장비 렌탈 / 서버 렌탈 탭 분리', $note->content);
        $this->assertStringContainsString('사업자등록증 첨부', $note->content);
        $this->assertStringContainsString('새 배포 안내 띠', $note->content);
    }

    public function test_publishes_even_when_same_title_note_is_soft_deleted(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        $trashed = Wiki::create([
            'title' => self::TITLE,
            'type' => 'update',
            'category' => '업데이트',
            'content' => '<p>보관함에 있는 옛 노트</p>',
            'is_draft' => false,
            'created_by' => $master->id,
            'updated_by' => $master->id,
        ]);
        $trashed->delete();

        $this->migration()->up();

        $this->assertCount(1, Wiki::where('type', 'update')->where('title', self::TITLE)->get());
        $this->assertDatabaseHas('wikis', ['id' => $trashed->id, 'content' => '<p>보관함에 있는 옛 노트</p>']);
    }

    public function test_down_keeps_soft_deleted_copy_of_published_note(): void
    {
        User::factory()->create(['role' => 'master']);
        $this->migration()->up();
        $published = Wiki::where('type', 'update')->where('title', self::TITLE)->firstOrFail();
        $published->delete(); // 관리자가 보관함으로 보낸 상태

        $this->migration()->down();

        $this->assertDatabaseHas('wikis', ['id' => $published->id]);
    }

    public function test_down_removes_published_note(): void
    {
        User::factory()->create(['role' => 'master']);
        $this->migration()->up();

        $this->migration()->down();

        $this->assertDatabaseMissing('wikis', ['title' => self::TITLE]);
    }

    public function test_down_keeps_manually_written_note_with_same_title(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        $manual = Wiki::create([
            'title' => self::TITLE,
            'type' => 'update',
            'category' => '업데이트',
            'content' => '<p>직접 작성한 노트</p>',
            'is_draft' => false,
            'created_by' => $master->id,
            'updated_by' => $master->id,
        ]);

        $this->migration()->up(); // 같은 제목이 있어 건너뜀
        $this->migration()->down();

        $this->assertDatabaseHas('wikis', ['id' => $manual->id, 'content' => '<p>직접 작성한 노트</p>']);
    }
}
