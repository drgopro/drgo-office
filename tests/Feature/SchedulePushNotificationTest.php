<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleCreated;
use App\Notifications\ScheduleReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SchedulePushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'member'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeSchedule(array $attrs = []): Schedule
    {
        return Schedule::create(array_merge([
            'title' => '방송룸 점검',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'start_time' => now()->addMinutes(60)->format('H:i'),
            'is_all_day' => false,
            'color' => 'blue',
            'notif_minutes' => '60',
            'created_by' => $this->makeUser()->id,
        ], $attrs));
    }

    public function test_구독_등록과_해제(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'akey'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/abc']);

        $this->actingAs($user)->deleteJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/abc']);
    }

    public function test_구독_등록은_로그인_필요(): void
    {
        $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'akey'],
        ])->assertUnauthorized();
    }

    public function test_시작_전_알림_담당자에게_발송_및_중복_방지(): void
    {
        Notification::fake();

        $assigneeUser = $this->makeUser();
        $assignee = Assignee::firstWhere('user_id', $assigneeUser->id);
        $schedule = $this->makeSchedule();
        $schedule->assignees()->attach($assignee->id);

        $this->artisan('schedules:notify')->assertSuccessful();

        Notification::assertSentTo($assigneeUser, ScheduleReminder::class);
        $this->assertNotNull($schedule->fresh()->notified_at);

        // 재실행 시 중복 발송 없음
        Notification::fake();
        $this->artisan('schedules:notify')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_담당자_없으면_등록자에게_발송(): void
    {
        Notification::fake();

        $schedule = $this->makeSchedule();

        $this->artisan('schedules:notify')->assertSuccessful();

        Notification::assertSentTo($schedule->creator, ScheduleReminder::class);
    }

    public function test_발송_시점_이전이면_미발송(): void
    {
        Notification::fake();

        // 시작까지 120분 남음, 알림은 60분 전 → 아직 발송 시점 아님
        $this->makeSchedule(['start_time' => now()->addMinutes(120)->format('H:i')]);

        $this->artisan('schedules:notify')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_알림_미설정_일정은_미발송(): void
    {
        Notification::fake();

        $this->makeSchedule(['notif_minutes' => null]);

        $this->artisan('schedules:notify')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_일정_등록_시_담당자에게_즉시_알림(): void
    {
        Notification::fake();

        $creator = $this->makeUser('master');
        $assigneeUser = $this->makeUser();
        $assignee = Assignee::firstWhere('user_id', $assigneeUser->id);

        $this->actingAs($creator)->postJson('/api/events', [
            'title' => '신규 방문 세팅',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'color' => 'gold',
            'assignees' => [$assignee->id],
        ])->assertCreated();

        Notification::assertSentTo($assigneeUser, ScheduleCreated::class);
    }

    public function test_담당자_없는_일정_등록은_알림_없음(): void
    {
        Notification::fake();

        $creator = $this->makeUser('master');

        $this->actingAs($creator)->postJson('/api/events', [
            'title' => '사내 업무',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'color' => 'blue',
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_종일_일정은_당일_오전9시_발송(): void
    {
        Notification::fake();

        $this->travelTo(now()->setTime(9, 3));
        $this->makeSchedule(['is_all_day' => true, 'start_time' => null]);

        $this->artisan('schedules:notify')->assertSuccessful();

        Notification::assertCount(1);
    }
}
