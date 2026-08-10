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

    public function test_digest_sends_group_message_with_dday_schedules(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['message' => ['id' => '1']])]);
        $schedule = $this->makeSchedule();
        $assignee = Assignee::create(['name' => '김담당', 'display_order' => 1, 'is_active' => true]);
        $schedule->assignees()->attach($assignee->id, ['sort_order' => 1]);

        // 오늘/내일/비공개 일정은 제외 대상
        $this->makeSchedule(['title' => '오늘 일정', 'start_date' => now()->format('Y-m-d')]);
        $this->makeSchedule(['title' => '비공개 일정', 'is_private' => true]);

        $this->artisan('schedules:channeltalk-digest')
            ->expectsOutputToContain('발송 완료 — 1건')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($request->url(), 'api.channel.io/open/v5/groups/')
                && str_contains($request->url(), rawurlencode('@오피스알림'))
                && $request->hasHeader('x-access-key', 'test-key')
                && str_contains($text, 'D-2 일정 알림')
                && str_contains($text, '14:00 [방문의뢰] PC 세팅 방문 — 홍길동 (담당: 김담당)')
                && ! str_contains($text, '오늘 일정')
                && ! str_contains($text, '비공개 일정');
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
}
