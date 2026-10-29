<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 10월 업데이트 노트 발행 마이그레이션 — 위키 '업데이트' 게시판에 1회만 등록되고 롤백 시 제거되는지 검증 */
class UpdateNotePublishOctoberTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = '업데이트 노트 10월 (10/1 ~ 10/29)';

    private function migration(): Migration
    {
        return require base_path('database/migrations/2026_10_29_170000_publish_update_note_2026_10.php');
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
        $this->assertStringContainsString('<h2>10/29 (목)</h2>', $note->content);
        $this->assertStringContainsString('<h2>10/1 (목)</h2>', $note->content);
        $this->assertStringContainsString('견적서 복제', $note->content);
        $this->assertStringContainsString('포스팅 완료 처리', $note->content);
        $this->assertStringContainsString('거래명세서 발행', $note->content);
        $this->assertStringContainsString('달러(USD) 표시', $note->content);
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
