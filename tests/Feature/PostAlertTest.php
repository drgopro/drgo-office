<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 게시물 알림(새게시물알림 톡방) + 캘린더 알림 톡방 설정 */
class PostAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'display_name' => '김광래', 'email' => 'kk@drgo.pro']);
        config([
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '아웃바운드',
            'services.channeltalk.bot_name' => '오피스봇',
            'services.drgo_board.token' => 'feed-token',
            'services.drgo_board.boards' => 'free',
            'services.drgo_board.feed_url' => 'https://drgo.pro/board-feed.php',
        ]);
    }

    public function test_board_feed_alert_goes_to_post_group_with_free_managers_mentioned(): void
    {
        // 시드 기본값: 새게시물알림 — Free 담당자 지정
        Setting::set('free_post_alert_managers', json_encode([$this->admin->id]));
        Setting::set('drgo_board.free.last_id', 100); // 워터마크 초기화 이후 상태

        Http::fake([
            'drgo.pro/board-feed.php*' => Http::response(['ok' => true, 'max_id' => 102, 'items' => [
                ['id' => 101, 'type' => 'post', 'subject' => '자유글', 'name' => '방문자', 'datetime' => '2026-09-28 10:00:00'],
                ['id' => 102, 'type' => 'comment', 'parent_id' => 99, 'parent_subject' => '원글', 'name' => '방문자2', 'datetime' => '2026-09-28 10:05:00'],
            ]]),
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => [
                ['id' => 'mgr-9', 'name' => '김광래', 'email' => 'kk@drgo.pro'],
            ]]),
            'api.channel.io/open/v5/groups/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('drgo:watch-boards')->assertSuccessful();

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), '/groups/@'.rawurlencode('새게시물알림').'/messages')) {
                return false;
            }
            $text = $r['blocks'][0]['value'] ?? '';

            return str_contains($text, '자유게시판 새 소식 2건')
                && str_contains($text, '자유글')
                && str_contains($text, '<link type="manager" value="mgr-9">'); // Free 담당자 멘션
        });
        $this->assertSame('102', (string) Setting::get('drgo_board.free.last_id'));
    }

    public function test_wiki_new_post_notifies_post_group_without_mentions(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);

        $this->actingAs($this->admin)->postJson('/wiki', [
            'title' => '새 문서', 'content' => '<p>본문</p>', 'category' => '가이드',
        ]);

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), '/groups/@'.rawurlencode('새게시물알림').'/messages')) {
                return false;
            }
            $text = $r['blocks'][0]['value'] ?? '';

            return str_contains($text, "위키 새 글: '새 문서'")
                && ! str_contains($text, '<link type="manager"'); // 위키 담당자 미지정 — 멘션 없음
        });
    }

    public function test_wiki_new_post_mentions_wiki_managers_separately(): void
    {
        // 위키 전용 톡방 + 담당자 — drgo.pro 게시판 설정과 별도
        Setting::set('wiki_post_alert_group', '위키알림방');
        Setting::set('wiki_post_alert_managers', json_encode([$this->admin->id]));
        Setting::set('free_post_alert_managers', json_encode([])); // Free 쪽은 비움 — 분리 확인
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => [
                ['id' => 'mgr-9', 'name' => '김광래', 'email' => 'kk@drgo.pro'],
            ]]),
            'api.channel.io/*' => Http::response(['ok' => true]),
        ]);

        $this->actingAs($this->admin)->postJson('/wiki', [
            'title' => '멘션 문서', 'content' => '<p>본문</p>',
        ]);

        // 게시물 알림 톡방(새게시물알림)이 아니라 위키 전용 톡방으로 발송
        Http::assertSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('위키알림방').'/messages')
            && str_contains($r['blocks'][0]['value'] ?? '', '<link type="manager" value="mgr-9">'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('새게시물알림').'/messages'));
    }

    public function test_wiki_alert_test_endpoint_and_page_render(): void
    {
        Setting::set('wiki_post_alert_group', '위키알림방');
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => []]),
            'api.channel.io/*' => Http::response(['ok' => true]),
        ]);
        $this->actingAs($this->admin)->postJson('/api/admin/wiki-alert-test')->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('위키알림방').'/messages'));

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('위키 알림')
            ->assertSee('id="wkGroup"', false)
            ->assertSee('wiki-alert-test', false);
    }

    public function test_wiki_draft_and_unset_group_do_not_notify(): void
    {
        Http::fake();

        // 임시저장은 알림 없음
        $this->actingAs($this->admin)->postJson('/wiki', [
            'title' => '초안', 'content' => '<p>.</p>', 'is_draft' => true,
        ]);
        // 톡방 비우면 발행해도 알림 없음
        Setting::set('post_alert_group', '');
        $this->actingAs($this->admin)->postJson('/wiki', [
            'title' => '알림 끔', 'content' => '<p>.</p>',
        ]);

        Http::assertNothingSent();
    }

    public function test_d2_digest_uses_calendar_alert_group(): void
    {
        Setting::set('calendar_alert_group', '캘린더알림방');
        Schedule::create([
            'title' => '방문 세팅', 'start_date' => now()->addDays(2)->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'color' => 'gold', 'is_private' => false,
        ]);

        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('캘린더알림방').'/messages')
            && str_contains($r['blocks'][0]['value'] ?? '', 'D-2 일정 알림'));
    }

    public function test_admin_page_renders_new_alert_settings(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('게시판/위키')
            ->assertSee('id="pbGroup"', false)
            ->assertSee('id="calAlertGroup"', false)
            ->assertSee('post-alert-test', false)
            ->assertSee('calendar-alert-test', false);
    }
}
