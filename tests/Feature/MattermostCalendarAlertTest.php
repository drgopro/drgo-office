<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\MattermostClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Mattermost 캘린더 알림 — 관리자 설정의 수신 웹훅으로 담당자 지정·일정 삭제·D-2 알림 발송 (채널톡 해지 상태) */
class MattermostCalendarAlertTest extends TestCase
{
    use RefreshDatabase;

    private const HOOK = 'https://mm.example.test/hooks/abc123def456';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.channeltalk.enabled' => false]);
        Http::fake(['mm.example.test/*' => Http::response('ok')]);
        Setting::set(MattermostClient::CALENDAR_WEBHOOK, self::HOOK);
    }

    private function makeSchedule(array $attrs = []): Schedule
    {
        return Schedule::create([
            'title' => 'PC 세팅 방문', 'start_date' => now()->addDays(2)->format('Y-m-d'), 'end_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '14:00:00', 'color' => 'gold', 'client_name' => '홍길동', 'is_private' => false,
            ...$attrs,
        ]);
    }

    private function assertSentToHook(string $needle): void
    {
        Http::assertSent(fn ($r) => $r->url() === self::HOOK && str_contains((string) $r['text'], $needle));
    }

    public function test_d2_digest_posts_to_mattermost_with_assignee_names(): void
    {
        $schedule = $this->makeSchedule();
        $schedule->syncAssigneesOrdered([Assignee::create(['name' => '김기사'])->id], notify: false);

        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();

        $this->assertSentToHook('D-2 일정 알림');
        $this->assertSentToHook('PC 세팅 방문 — 홍길동 (담당: 김기사)');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.channel.io'));
    }

    public function test_assignee_change_posts_to_mattermost(): void
    {
        $schedule = $this->makeSchedule();
        $tech = Assignee::create(['name' => '박설치']);

        $schedule->syncAssigneesOrdered([$tech->id]);

        $this->assertSentToHook('일정 담당자 지정: 박설치');
    }

    public function test_schedule_delete_posts_to_mattermost_but_private_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = $this->makeSchedule();
        $this->actingAs($admin)->deleteJson("/api/events/{$schedule->id}", ['reason' => '의뢰 취소'])->assertOk();
        $this->assertSentToHook('일정이 삭제되었습니다');
        $this->assertSentToHook('사유: 의뢰 취소');

        Http::fake(['mm.example.test/*' => Http::response('ok')]);
        $private = $this->makeSchedule(['is_private' => true, 'created_by' => $admin->id]);
        $this->actingAs($admin)->deleteJson("/api/events/{$private->id}", ['reason' => '정리'])->assertOk();
        Http::assertNothingSent();
    }

    public function test_nothing_sent_without_webhook_and_digest_schedule_runs_only_when_configured(): void
    {
        Setting::set(MattermostClient::CALENDAR_WEBHOOK, '');
        $this->makeSchedule();

        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();
        Http::assertNothingSent();

        $due = fn () => collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => $e->filtersPass($this->app))->map(fn ($e) => (string) $e->command)->implode("\n");
        $this->assertStringNotContainsString('schedules:channeltalk-digest', $due());

        Setting::set(MattermostClient::CALENDAR_WEBHOOK, self::HOOK);
        $this->assertStringContainsString('schedules:channeltalk-digest', $due());
    }

    public function test_admin_test_button_sends_and_reports_failures(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $fail = false;
        Http::swap(new Factory);
        Http::fake(function () use (&$fail) {
            return $fail ? Http::response('Unable to find the webhook', 400) : Http::response('ok');
        });

        $this->actingAs($admin)->postJson('/api/admin/mattermost-calendar-test')->assertOk();
        $this->assertSentToHook('[테스트]');

        $fail = true;
        $this->actingAs($admin)->postJson('/api/admin/mattermost-calendar-test')->assertStatus(502)
            ->assertJson(['message' => '발송 실패: HTTP 400: Unable to find the webhook']);

        Setting::set(MattermostClient::CALENDAR_WEBHOOK, '');
        $this->actingAs($admin)->postJson('/api/admin/mattermost-calendar-test')->assertStatus(422);
    }

    public function test_invalid_webhook_url_is_rejected(): void
    {
        $this->assertFalse(MattermostClient::isValidWebhook('http://mm.example.test/hooks/abc'));
        $this->assertFalse(MattermostClient::isValidWebhook('https://mm.example.test/api/v4/posts'));
        $this->assertTrue(MattermostClient::isValidWebhook(self::HOOK));

        $this->assertFalse(app(MattermostClient::class)->send('https://evil.test/x', 'hi')['ok']);
    }

    public function test_admin_page_has_webhook_field(): void
    {
        $admin = User::factory()->create(['role' => 'master']);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Mattermost 캘린더 알림 웹훅 URL')
            ->assertSee("testAlertRoom('mattermost-calendar-test'", false);
        $this->actingAs($admin)->getJson('/api/settings')->assertOk()
            ->assertJson([MattermostClient::CALENDAR_WEBHOOK => self::HOOK]);
    }

    public function test_assignee_with_mattermost_username_is_tagged_others_shown_by_name(): void
    {
        $kim = User::factory()->create(['display_name' => '김기사', 'mattermost_username' => 'kim.drgo']);
        $schedule = $this->makeSchedule();
        $schedule->syncAssigneesOrdered([
            Assignee::where('user_id', $kim->id)->value('id'), // 계정 생성 시 자동으로 만들어지는 담당자
            Assignee::create(['name' => '외부기사'])->id,
        ], notify: false);

        $this->artisan('schedules:channeltalk-digest')->assertSuccessful();

        $this->assertSentToHook('(담당: @kim.drgo, 외부기사)');
    }

    public function test_assignee_change_tags_mattermost_username(): void
    {
        $park = User::factory()->create(['mattermost_username' => 'park']);
        $schedule = $this->makeSchedule();

        $schedule->syncAssigneesOrdered([Assignee::where('user_id', $park->id)->value('id')]);

        $this->assertSentToHook('일정 담당자 지정: @park');
    }

    public function test_master_sets_mattermost_username_normalized(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        $user = User::factory()->create();

        $this->actingAs($master)->patchJson("/api/admin/users/{$user->id}/account", ['mattermost_username' => '@Kim.Drgo'])->assertOk();
        $this->assertSame('kim.drgo', $user->fresh()->mattermost_username);
        $this->actingAs($master)->getJson('/api/admin/users')->assertOk()->assertJsonFragment(['mattermost_username' => 'kim.drgo']);

        $this->actingAs($master)->patchJson("/api/admin/users/{$user->id}/account", ['mattermost_username' => '김 기사'])->assertStatus(422);

        $this->actingAs($master)->patchJson("/api/admin/users/{$user->id}/account", ['mattermost_username' => null])->assertOk();
        $this->assertNull($user->fresh()->mattermost_username);
    }

    public function test_user_sets_own_mattermost_username_in_profile(): void
    {
        $user = User::factory()->create(['display_name' => '본인']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Mattermost 아이디');
        $this->actingAs($user)->patch('/profile', ['display_name' => '본인', 'mattermost_username' => 'me_01'])->assertRedirect();
        $this->assertSame('me_01', $user->fresh()->mattermost_username);

        $this->actingAs($user)->patch('/profile', ['display_name' => '본인', 'mattermost_username' => 'bad name!'])->assertSessionHasErrors('mattermost_username');
    }
}
