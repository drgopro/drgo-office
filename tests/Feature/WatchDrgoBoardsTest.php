<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** drgo.pro 게시판 감시 — 새 글/답변/댓글 폴링 → 채널톡 팀챗 알림 */
class WatchDrgoBoardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.drgo_board.token' => 'test-token',
            'services.drgo_board.feed_url' => 'https://drgo.pro/board-feed.php',
            'services.drgo_board.boards' => 'free',
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '오피스',
        ]);
    }

    public function test_first_run_initializes_watermark_without_notifying(): void
    {
        Http::fake([
            'drgo.pro/board-feed.php*' => Http::response(['ok' => true, 'board' => 'free', 'max_id' => 100, 'items' => []]),
            'api.channel.io/*' => Http::response([]),
        ]);

        $this->artisan('drgo:watch-boards')->assertSuccessful();

        $this->assertSame('100', (string) Setting::get('drgo_board.free.last_id'));
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'channel.io'));
    }

    public function test_new_items_notify_and_advance_watermark(): void
    {
        Setting::set('drgo_board.free.last_id', 100);
        Http::fake([
            'drgo.pro/board-feed.php*' => Http::response(['ok' => true, 'board' => 'free', 'max_id' => 103, 'items' => [
                ['id' => 101, 'type' => 'post', 'subject' => '마이크 세팅 문의', 'name' => '홍길동', 'datetime' => '2026-09-05 14:23:00', 'secret' => false, 'parent_id' => null, 'parent_subject' => null],
                ['id' => 102, 'type' => 'post', 'subject' => '비밀 문의', 'name' => '김철수', 'datetime' => '2026-09-05 14:30:00', 'secret' => true, 'parent_id' => null, 'parent_subject' => null],
                ['id' => 103, 'type' => 'comment', 'subject' => '', 'name' => '박영희', 'datetime' => '2026-09-05 15:00:00', 'secret' => false, 'parent_id' => 90, 'parent_subject' => '조명 문의'],
            ]]),
            'api.channel.io/*' => Http::response([]),
        ]);

        $this->artisan('drgo:watch-boards')->assertSuccessful();

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'channel.io')) {
                return false;
            }
            $text = $req['blocks'][0]['value'] ?? '';

            return str_contains($text, '마이크 세팅 문의')
                && str_contains($text, 'https://drgo.pro/free/101')
                && str_contains($text, '🔒 비밀글')       // 비밀글 제목 마스킹
                && ! str_contains($text, '비밀 문의')
                && str_contains($text, '조명 문의')        // 댓글 — 원글 제목 표시
                && str_contains($text, 'https://drgo.pro/free/90#c_103');
        });
        $this->assertSame('103', (string) Setting::get('drgo_board.free.last_id'));
    }

    public function test_send_failure_keeps_watermark_for_retry(): void
    {
        Setting::set('drgo_board.free.last_id', 100);
        Http::fake([
            'drgo.pro/board-feed.php*' => Http::response(['ok' => true, 'board' => 'free', 'max_id' => 101, 'items' => [
                ['id' => 101, 'type' => 'post', 'subject' => '문의', 'name' => '홍길동', 'datetime' => '2026-09-05 14:23:00', 'secret' => false],
            ]]),
            'api.channel.io/*' => Http::response(['error' => 'x'], 500),
        ]);

        $this->artisan('drgo:watch-boards')->assertSuccessful();

        $this->assertSame('100', (string) Setting::get('drgo_board.free.last_id')); // 다음 주기에 재시도
    }

    public function test_feed_error_is_silent_and_keeps_watermark(): void
    {
        Setting::set('drgo_board.free.last_id', 100);
        Http::fake(['drgo.pro/board-feed.php*' => Http::response('down', 500)]);

        $this->artisan('drgo:watch-boards')->assertSuccessful();

        $this->assertSame('100', (string) Setting::get('drgo_board.free.last_id'));
    }

    public function test_skips_when_token_missing(): void
    {
        config(['services.drgo_board.token' => '']);
        Http::fake();

        $this->artisan('drgo:watch-boards')->assertSuccessful();

        Http::assertNothingSent();
    }
}
