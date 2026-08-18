<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelTalkDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '오피스알림',
            'services.channeltalk.remind_days' => 2,
        ]);
    }

    private function makeSchedule(array $attrs = []): Schedule
    {
        return Schedule::create([
            'title' => 'PC 세팅 방문',
            'start_date' => $attrs['start_date'] ?? now()->addDays(2)->format('Y-m-d'),
            'end_date' => $attrs['start_date'] ?? now()->addDays(2)->format('Y-m-d'),
            'start_time' => '14:00:00',
            'color' => 'gold',
            'client_name' => '홍길동',
            'is_private' => false,
            ...$attrs,
        ]);
    }

    /** 매니저 목록 + 그룹 메시지 fake (매니저 매칭 시 멘션 태그 검증용) */
    private function fakeChannelApis(): void
    {
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response([
                'managers' => [
                    ['id' => 'mgr-1', 'name' => '김담당', 'email' => 'kim@drgo.pro'],
                ],
            ]),
            'api.channel.io/open/v5/groups/*' => Http::response(['message' => ['id' => '1']]),
        ]);
    }

    public function test_digest_sends_group_message_with_dday_schedules(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['message' => ['id' => '1']])]);
        $schedule = $this->makeSchedule();
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true]);
        $schedule->assignees()->attach($assignee->id, ['sort_order' => 1]);

        // 오늘/내일/비공개/방문·원격 외 카테고리는 제외 대상
        $this->makeSchedule(['title' => '오늘 일정', 'start_date' => now()->format('Y-m-d')]);
        $this->makeSchedule(['title' => '비공개 일정', 'is_private' => true]);
        $this->makeSchedule(['title' => '사내업무 일정', 'color' => 'blue']);

        $this->artisan('schedules:channeltalk-digest')
            ->expectsOutputToContain('발송 완료 — 1건')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($request->url(), '/groups/@'.rawurlencode('오피스알림').'/messages') // @는 인코딩 금지
                && $request->hasHeader('x-access-key', 'test-key')
                && str_contains($text, 'D-2 일정 알림')
                && str_contains($text, '14:00 [방문의뢰] PC 세팅 방문 — 홍길동 (담당: 김담당)')
                && ! str_contains($text, '오늘 일정')
                && ! str_contains($text, '비공개 일정')
                && ! str_contains($text, '사내업무 일정');
        });
    }

    public function test_digest_mentions_assignee_matched_by_email(): void
    {
        $this->fakeChannelApis();
        $user = User::factory()->create(['email' => 'kim@drgo.pro']);
        $schedule = $this->makeSchedule();
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true, 'user_id' => $user->id]);
        $schedule->assignees()->attach($assignee->id, ['sort_order' => 1]);

        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }

            // 이메일 매칭된 담당자는 매니저 멘션 태그로 표시
            return str_contains($request['blocks'][0]['value'] ?? '', '<link type="manager" value="mgr-1">김담당</link>');
        });
    }

    public function test_mention_matches_by_name_without_account_link(): void
    {
        // 계정 연결/이메일 없이 담당자 이름 = 매니저 이름만으로 멘션
        $this->fakeChannelApis();
        $schedule = $this->makeSchedule();
        $assignee = Assignee::create(['name' => '김 담당', 'display_order' => 1, 'is_active' => true]); // 공백 있어도 매칭

        $schedule->syncAssigneesOrdered([$assignee->id]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }

            return str_contains($request['blocks'][0]['value'] ?? '', '<link type="manager" value="mgr-1">김담당</link>');
        });
    }

    public function test_mention_skipped_for_duplicate_manager_names(): void
    {
        // 동명이인 매니저 → 오태그 방지를 위해 멘션 없이 이름만
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response([
                'managers' => [
                    ['id' => 'mgr-1', 'name' => '김담당', 'email' => ''],
                    ['id' => 'mgr-2', 'name' => '김담당', 'email' => ''],
                ],
            ]),
            'api.channel.io/open/v5/groups/*' => Http::response(['message' => ['id' => '1']]),
        ]);
        $schedule = $this->makeSchedule();
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true]);

        $schedule->syncAssigneesOrdered([$assignee->id]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '김담당') && ! str_contains($text, '<link type="manager"');
        });
    }

    // === 담당자 추가/제거 알림 ===

    public function test_assignee_added_sends_mention_notification(): void
    {
        $this->fakeChannelApis();
        $user = User::factory()->create(['email' => 'kim@drgo.pro']);
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true, 'user_id' => $user->id]);
        $schedule = $this->makeSchedule();

        $schedule->syncAssigneesOrdered([$assignee->id]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '🔔')
                && str_contains($text, '<link type="manager" value="mgr-1">김담당</link>')
                && str_contains($text, "'PC 세팅 방문'")
                && str_contains($text, '담당자로 지정');
        });
    }

    public function test_assignee_removed_sends_mention_notification(): void
    {
        $this->fakeChannelApis();
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true]);
        $schedule = $this->makeSchedule();
        $schedule->assignees()->attach($assignee->id, ['sort_order' => 1]);

        $schedule->syncAssigneesOrdered([]); // 전원 제거

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '🔕') && str_contains($text, '담당에서 제외');
        });
    }

    public function test_sync_with_notify_false_sends_nothing(): void
    {
        Http::fake();
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true]);
        $schedule = $this->makeSchedule();

        $schedule->syncAssigneesOrdered([$assignee->id], notify: false); // 백업 가져오기 경로

        Http::assertNothingSent();
    }

    // === 할 일 등록 알림 ===

    public function test_todo_creation_sends_mention_notification(): void
    {
        $this->fakeChannelApis();
        // 할 일 담당자는 User 직접 지정 — 본인 이메일로 매니저 매칭
        $user = User::factory()->create(['email' => 'kim@drgo.pro', 'role' => 'member']);

        $this->actingAs($user)->postJson('/api/todos', [
            'title' => '견적서 발송',
            'priority' => 'medium',
            'assignee_id' => $user->id,
            'due_date' => now()->addDays(4)->format('Y-m-d'),
        ])->assertCreated();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '📌')
                && str_contains($text, '<link type="manager" value="mgr-1">김담당</link>')
                && str_contains($text, "새 할 일: '견적서 발송'")
                && str_contains($text, '마감 '.now()->addDays(4)->format('m/d'));
        });
    }

    public function test_todo_assignee_change_notifies_added_and_removed(): void
    {
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response([
                'managers' => [
                    ['id' => 'mgr-1', 'name' => '김담당', 'email' => 'kim@drgo.pro'],
                    ['id' => 'mgr-2', 'name' => '박담당', 'email' => 'park@drgo.pro'],
                ],
            ]),
            'api.channel.io/open/v5/groups/*' => Http::response(['message' => ['id' => '1']]),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $kim = User::factory()->create(['email' => 'kim@drgo.pro']);
        $park = User::factory()->create(['email' => 'park@drgo.pro']);

        $res = $this->actingAs($admin)->postJson('/api/todos', [
            'title' => '재고 정리', 'priority' => 'medium', 'assignee_id' => $kim->id,
        ])->assertCreated();
        $todoId = $res->json('todo.id');

        // 담당자 교체: 김담당 → 박담당
        $this->actingAs($admin)->patchJson("/api/todos/{$todoId}", [
            'title' => '재고 정리', 'priority' => 'medium', 'assignee_id' => $park->id,
        ])->assertOk();

        // 새 담당자에게 지정 알림
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '📌')
                && str_contains($text, 'mgr-2')
                && str_contains($text, "'재고 정리' 할 일의 담당자로 지정");
        });
        // 빠진 담당자에게 제외 알림
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '🔕')
                && str_contains($text, 'mgr-1')
                && str_contains($text, "'재고 정리' 할 일의 담당에서 제외");
        });
    }

    public function test_digest_skips_when_no_schedules(): void
    {
        Http::fake();

        $this->artisan('schedules:channeltalk-digest')
            ->expectsOutputToContain('일정 없음')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_digest_skips_gracefully_when_not_configured(): void
    {
        config(['services.channeltalk.access_key' => '']);
        Http::fake();

        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_numeric_group_uses_id_path(): void
    {
        config(['services.channeltalk.group' => '123456']);
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $this->makeSchedule();

        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/groups/123456/messages'));
    }

    public function test_admin_test_route_sends_message(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/channeltalk-test')
            ->assertOk()
            ->assertSee('전송 성공');

        Http::assertSent(fn ($request) => str_contains($request['blocks'][0]['value'] ?? '', '연동 테스트'));
    }

    public function test_admin_test_route_requires_admin(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $this->actingAs($member)->get('/admin/channeltalk-test')->assertForbidden();
    }

    // === 위키 공지사항 알림 ===

    public function test_wiki_notice_publish_sends_mention_all(): void
    {
        $this->fakeChannelApis();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/wiki', [
            'title' => '8월 휴무 안내', 'type' => 'notice', 'content' => '<p>내용</p>',
        ])->assertCreated();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '새 공지사항')
                && str_contains($text, '8월 휴무 안내')
                && str_contains($text, '<link type="manager" value="mgr-1">김담당</link>');
        });
    }

    public function test_wiki_notice_draft_notifies_on_publish_only(): void
    {
        $this->fakeChannelApis();
        $admin = User::factory()->create(['role' => 'admin']);

        // 임시저장 — 알림 없음
        $res = $this->actingAs($admin)->postJson('/wiki', [
            'title' => '초안 공지', 'type' => 'notice', 'content' => '<p>내용</p>', 'is_draft' => 1,
        ]);
        $res->assertCreated();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/groups/'));

        // 발행 — 알림 발송
        $this->actingAs($admin)->patchJson('/wiki/'.$res->json('id'), ['is_draft' => 0])->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/groups/')
            && str_contains($request['blocks'][0]['value'] ?? '', '초안 공지'));
    }

    public function test_wiki_normal_post_and_notice_edit_do_not_notify(): void
    {
        $this->fakeChannelApis();
        $admin = User::factory()->create(['role' => 'admin']);

        // 일반 문서 — 알림 없음
        $this->actingAs($admin)->postJson('/wiki', [
            'title' => '일반 문서', 'type' => 'normal', 'content' => '<p>내용</p>',
        ])->assertCreated();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/groups/'));

        // 발행된 공지의 단순 내용 수정 — 추가 알림 없음
        $res = $this->actingAs($admin)->postJson('/wiki', [
            'title' => '공지', 'type' => 'notice', 'content' => '<p>내용</p>',
        ]);
        $sentBefore = 1; // 위 공지 등록으로 1회 발송
        $this->actingAs($admin)->patchJson('/wiki/'.$res->json('id'), ['content' => '<p>수정</p>'])->assertOk();
        $sent = 0;
        Http::recorded(function ($request) use (&$sent) {
            if (str_contains($request->url(), '/groups/')) {
                $sent++;
            }
        });
        $this->assertSame($sentBefore, $sent, '내용 수정으로는 추가 알림이 없어야 함');
    }
}
