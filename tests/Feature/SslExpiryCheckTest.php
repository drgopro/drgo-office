<?php

namespace Tests\Feature;

use App\Services\SslCertExpiry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SSL 인증서 만료 감시 — 7일 전부터 채널톡 알림, 여유 있으면 침묵 */
class SslExpiryCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '아웃바운드',
            'services.channeltalk.bot_name' => '오피스봇',
        ]);
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
        Http::fake();
        $this->fakeExpiry(['office.drgo.pro' => 100, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_alert_sent_when_expiry_within_threshold(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $this->fakeExpiry(['office.drgo.pro' => 6, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        $date = CarbonImmutable::now()->addDays(6)->format('Y-m-d');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('아웃바운드').'/messages')
            && str_contains($r['blocks'][0]['value'] ?? '', '[SSL 인증서 알림]')
            && str_contains($r['blocks'][0]['value'] ?? '', "office.drgo.pro 인증서가 6일 후({$date}) 만료됩니다")
            && ! str_contains($r['blocks'][0]['value'] ?? '', 'drgo.pro 인증서가 60일'));
    }

    public function test_expired_certificate_alerts_immediately(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $this->fakeExpiry(['office.drgo.pro' => -1, 'drgo.pro' => 60]);

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r['blocks'][0]['value'] ?? '', '만료되었습니다'));
    }

    public function test_unreadable_certificate_logs_only(): void
    {
        Http::fake();
        $this->fakeExpiry([]); // 모든 도메인 inspect 실패(null)

        $this->artisan('ssl:check-expiry')->assertSuccessful();

        Http::assertNothingSent(); // 일시 장애 가능성 — 알림 대신 로그만
    }
}
