<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 일정 삭제 시 채널톡 아웃바운드 알림 — 삭제자·사유 발송, 비공개 일정 제외 */
class ScheduleDeleteNotifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '테스트그룹',
        ]);
    }

    private function makeSchedule(array $attrs = []): Schedule
    {
        return Schedule::create([
            'title' => '삭제 알림 테스트 일정', 'start_date' => '2026-11-10', 'end_date' => '2026-11-10',
            'color' => 'gold', 'is_all_day' => true,
            ...$attrs,
        ]);
    }

    public function test_일정_삭제_시_채널톡_알림_발송(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['message' => ['id' => 'x']])]);
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = $this->makeSchedule();

        $this->actingAs($admin)->deleteJson("/api/events/{$schedule->id}", [
            'reason' => '의뢰 취소로 삭제',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.channel.io')
                && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), '삭제되었습니다')
                && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), '의뢰 취소로 삭제');
        });
    }

    public function test_비공개_일정은_알림_없음(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = $this->makeSchedule(['is_private' => true, 'created_by' => null]);

        $this->actingAs($admin)->deleteJson("/api/events/{$schedule->id}", [
            'reason' => '비공개 정리',
        ])->assertOk();

        Http::assertNothingSent();
    }
}
