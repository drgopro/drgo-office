<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DiskSpaceWarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** 디스크 사용률 점검 — 임계치 초과 시 관리자 알림 */
class DiskCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_admins_when_over_threshold(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $master = User::factory()->create(['role' => 'master']);
        $member = User::factory()->create(['role' => 'member']);

        // 임계치 1% — 실제 디스크 사용률은 항상 이보다 높으므로 경고 발송
        $this->artisan('disk:check', ['--threshold' => 1])->assertSuccessful();

        Notification::assertSentTo($admin, DiskSpaceWarning::class);
        Notification::assertSentTo($master, DiskSpaceWarning::class);
        Notification::assertNotSentTo($member, DiskSpaceWarning::class);
    }

    public function test_no_alert_under_threshold(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);

        $this->artisan('disk:check', ['--threshold' => 99])->assertSuccessful();

        Notification::assertNothingSent();
    }
}
