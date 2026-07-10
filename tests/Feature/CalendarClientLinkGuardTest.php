<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarClientLinkGuardTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    /** @return array<string, mixed> */
    private function basePayload(string $color): array
    {
        return [
            'title' => '테스트 일정',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-10',
            'is_all_day' => true,
            'color' => $color,
        ];
    }

    public function test_store_strips_client_link_for_excluded_colors(): void
    {
        foreach (['blue', 'red'] as $color) {
            $res = $this->actingAs($this->actingUser())->postJson('/api/events', $this->basePayload($color) + [
                'client_name' => '엉뚱한 의뢰자',
                'gold_data' => ['client_id' => 99, 'project_id' => 5, 'nickname' => '', 'name' => '', 'phone' => ''],
            ]);

            $res->assertCreated();
            $schedule = Schedule::find($res->json('id'));
            $this->assertNull($schedule->client_name, "{$color}: client_name은 저장되면 안 됨");
            $this->assertNull($schedule->gold_data, "{$color}: gold_data는 저장되면 안 됨");
        }
    }

    public function test_store_keeps_client_link_for_supported_colors(): void
    {
        $res = $this->actingAs($this->actingUser())->postJson('/api/events', $this->basePayload('teal') + [
            'client_name' => '정상 의뢰자',
            'gold_data' => ['client_id' => 7, 'project_id' => null, 'nickname' => '', 'name' => '', 'phone' => ''],
        ]);

        $res->assertCreated();
        $schedule = Schedule::find($res->json('id'));
        $this->assertSame('정상 의뢰자', $schedule->client_name);
        $this->assertSame(7, $schedule->gold_data['client_id']);
    }

    public function test_update_heals_contaminated_excluded_color_schedule(): void
    {
        // 과거 누수로 오염된 휴가 일정
        $schedule = Schedule::create($this->basePayload('red') + [
            'client_name' => '누수된 의뢰자',
            'gold_data' => ['client_id' => 42, 'nickname' => '누수', 'name' => '홍길동', 'phone' => '010'],
        ]);

        // 제목만 바꿔 저장해도 (드래그 이동 포함 모든 저장 경로) 오염이 제거되어야 함
        $this->actingAs($this->actingUser())->postJson("/api/events/{$schedule->id}", [
            'title' => '휴가 (수정)',
        ])->assertOk();

        $schedule->refresh();
        $this->assertNull($schedule->client_name);
        $this->assertNull($schedule->gold_data);
    }

    public function test_update_does_not_touch_supported_color_links(): void
    {
        $schedule = Schedule::create($this->basePayload('teal') + [
            'client_name' => '유지될 의뢰자',
            'gold_data' => ['client_id' => 7, 'project_id' => null, 'nickname' => '', 'name' => '', 'phone' => ''],
        ]);

        $this->actingAs($this->actingUser())->postJson("/api/events/{$schedule->id}", [
            'title' => '방송 (수정)',
        ])->assertOk();

        $schedule->refresh();
        $this->assertSame('유지될 의뢰자', $schedule->client_name);
        $this->assertSame(7, $schedule->gold_data['client_id']);
    }

    public function test_clean_command_reports_and_fixes_contaminated_rows(): void
    {
        $bad = Schedule::create($this->basePayload('blue') + [
            'client_name' => '오염 의뢰자',
            'gold_data' => ['client_id' => 42],
        ]);
        $good = Schedule::create($this->basePayload('teal') + [
            'client_name' => '정상 의뢰자',
            'gold_data' => ['client_id' => 7, 'project_id' => null, 'nickname' => '', 'name' => '', 'phone' => ''],
        ]);

        // 기본(dry-run): 변경 없음
        $this->artisan('calendar:clean-client-links')
            ->expectsOutputToContain('1건')
            ->assertSuccessful();
        $this->assertSame('오염 의뢰자', $bad->fresh()->client_name);

        // --fix: 오염만 제거, 정상 연동은 유지
        $this->artisan('calendar:clean-client-links', ['--fix' => true])->assertSuccessful();
        $this->assertNull($bad->fresh()->client_name);
        $this->assertNull($bad->fresh()->gold_data);
        $this->assertSame('정상 의뢰자', $good->fresh()->client_name);
        $this->assertSame(7, $good->fresh()->gold_data['client_id']);
    }
}
