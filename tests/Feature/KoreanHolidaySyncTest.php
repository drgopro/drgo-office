<?php

namespace Tests\Feature;

use App\Console\Commands\SyncKoreanHolidays;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 한국 공휴일 자동 동기화 + 캘린더 전 뷰 표시 */
class KoreanHolidaySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_stores_holidays_including_substitutes(): void
    {
        // holidays-kr(공공데이터 기반) — 대체공휴일이 정확한 한글 명칭으로 온다
        $thisYear = now()->year;
        Http::fake([
            '*' => Http::response([
                '2018-01-01' => ['신정'], // 유지 범위(작년~) 밖 — 저장 안 함
                "{$thisYear}-10-03" => ['개천절'],
                "{$thisYear}-10-05" => ['대체공휴일'],
                ($thisYear + 1).'-05-05' => ['어린이날', '부처님 오신 날'], // 같은 날 복수 — 이어 붙임
            ]),
        ]);

        $this->artisan('holidays:sync')->assertSuccessful();

        $stored = json_decode(Setting::get('kr_holidays'), true);
        $this->assertSame('대체공휴일', $stored["{$thisYear}-10-05"]);
        $this->assertSame('개천절', $stored["{$thisYear}-10-03"]);
        $this->assertSame('어린이날·부처님 오신 날', $stored[($thisYear + 1).'-05-05']);
        $this->assertArrayNotHasKey('2018-01-01', $stored);
    }

    public function test_sync_failure_keeps_previous_data(): void
    {
        Setting::set('kr_holidays', json_encode(['2026-01-01' => '새해']));
        Http::fake(['*' => Http::response('err', 500)]); // 모든 소스 실패

        $this->artisan('holidays:sync')->assertFailed();
        $this->assertSame('새해', json_decode(Setting::get('kr_holidays'), true)['2026-01-01']);
    }

    public function test_calendar_merges_synced_holidays_and_shows_in_all_views(): void
    {
        Setting::set('kr_holidays', json_encode(['2026-10-05' => '대체공휴일']));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('2026-10-05', false) // 동기화분이 KR_HOLIDAYS에 병합
            ->assertSee('Object.assign', false)
            ->assertSee('tl-holiday', false) // 주/일간 헤더 표시
            ->assertSee('ad-holiday', false); // 목록 뷰 날짜 헤드 표시
    }

    public function test_transient_failure_recovers_via_retry(): void
    {
        // 첫 2회 실패 후 성공 — 재시도로 회복되어 알람 없이 저장된다
        Http::fakeSequence()
            ->push('', 503)
            ->push('', 503)
            ->push(['2026-01-01' => ['신정']], 200);

        $this->artisan('holidays:sync')->assertExitCode(0);
        $this->assertStringContainsString('신정', (string) Setting::get(SyncKoreanHolidays::SETTING_KEY));
    }

    public function test_nested_year_format_is_flattened(): void
    {
        // 소스가 2026년부터 {연도: {날짜: [이름]}} 중첩 형식으로 바뀜 — 평면/중첩 모두 지원
        $year = now()->year;
        Http::fake(['*' => Http::response([
            (string) $year => [
                "{$year}-01-01" => ['1월 1일'],
                "{$year}-05-05" => ['어린이날', '부처님 오신 날'],
            ],
            '2018' => ['2018-01-01' => ['신정']], // 사용 범위 밖 — 제외
        ])]);

        $this->artisan('holidays:sync')->assertExitCode(0);
        $saved = (string) Setting::get(SyncKoreanHolidays::SETTING_KEY);
        $this->assertStringContainsString("{$year}-05-05", $saved);
        $this->assertStringContainsString('어린이날·부처님 오신 날', $saved);
        $this->assertStringNotContainsString('2018-01-01', $saved);
    }

    public function test_falls_back_to_second_source_when_first_fails(): void
    {
        $year = now()->year;
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response('', 500),
            'holidays.hyunbin.page/*' => Http::response([(string) $year => ["{$year}-01-01" => ['1월 1일']]]),
        ]);

        $this->artisan('holidays:sync')->assertExitCode(0);
        $this->assertStringContainsString("{$year}-01-01", (string) Setting::get(SyncKoreanHolidays::SETTING_KEY));
    }
}
