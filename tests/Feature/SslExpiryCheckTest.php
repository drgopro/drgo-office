<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\SslCertExpiry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** SSL 인증서 만료 감시 — 7일 전부터 관리자 종 알림 + 만료일 캘린더 등록, 여유 있으면 침묵 */
class SslExpiryCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function fakeExpiry(array $daysLeftByDomain): void
    {
        $mock = $this->mock(SslCertExpiry::class);
        $mock->shouldReceive('inspect')->andReturnUsing(function (string $domain) use ($daysLeftByDomain) {
            if (! array_key_exists($domain, $daysLeftByDomain)) {
                return null;
            }
            $days = $daysLeftByDomain[$domain];

            return [
                'expires_at' => CarbonImmutable::now()->addDays($days),
                'days_left' => $days,
            ];
        });
    }

    public function test_no_alert_when_certificates_have_time_left(): void
    {
        $this->fakeExpiry(['office.drgo.pro' => 100, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, Schedule::count());
    }

    public function test_alert_and_calendar_when_expiry_within_threshold(): void
    {
        $this->fakeExpiry(['office.drgo.pro' => 6, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        $date = CarbonImmutable::now()->addDays(6)->format('Y-m-d');
        Notification::assertSentTo($this->admin, AdminAlert::class, fn (AdminAlert $n) => str_contains($n->body, "office.drgo.pro 인증서가 6일 후({$date}) 만료됩니다")
            && ! str_contains($n->body, 'drgo.pro 인증서가 60일'));

        $schedule = Schedule::where('source_type', 'ssl_expiry')->sole();
        $this->assertSame('SSL 인증서 만료 — office.drgo.pro', $schedule->title);
        $this->assertSame($date, $schedule->start_date->format('Y-m-d'));
        Http::assertNothingSent(); // 채널톡 호출 없음
    }

    public function test_calendar_event_registered_only_once_across_daily_runs(): void
    {
        $this->fakeExpiry(['office.drgo.pro' => 5, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();
        $this->artisan('ssl:check-expiry')->assertSuccessful();

        $this->assertSame(1, Schedule::where('source_type', 'ssl_expiry')->count());
    }

    public function test_expired_certificate_alerts_immediately(): void
    {
        $this->fakeExpiry(['office.drgo.pro' => -1, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        Notification::assertSentTo($this->admin, AdminAlert::class, fn (AdminAlert $n) => str_contains($n->body, '만료되었습니다'));
    }

    public function test_unreadable_certificate_logs_only(): void
    {
        $this->fakeExpiry([]); // 모든 도메인 inspect 실패(null)

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        Notification::assertNothingSent(); // 일시 장애 가능성 — 알림 대신 로그만
    }
}
