<?php

namespace Tests\Feature;

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
        Http::fake([
            'date.nager.at/api/v3/PublicHolidays/*/KR' => Http::sequence()
                ->push([['date' => '2025-10-08', 'localName' => '대체공휴일', 'name' => 'Substitute Holiday']])
                ->push([['date' => '2026-01-01', 'localName' => '새해', 'name' => "New Year's Day"],
                    ['date' => '2026-05-24', 'localName' => '부처님 오신 날', 'name' => 'Buddha Day'],
                    ['date' => '2026-05-25', 'localName' => '대체공휴일', 'name' => 'Substitute Holiday']])
                ->push([])->push([]),
        ]);

        $this->artisan('holidays:sync')->assertSuccessful();

        $stored = json_decode(Setting::get('kr_holidays'), true);
        $this->assertSame('대체공휴일', $stored['2025-10-08']);
        $this->assertSame('대체공휴일', $stored['2026-05-25']);
        $this->assertSame('새해', $stored['2026-01-01']);
    }

    public function test_sync_failure_keeps_previous_data(): void
    {
        Setting::set('kr_holidays', json_encode(['2026-01-01' => '새해']));
        Http::fake(['date.nager.at/*' => Http::response('err', 500)]);

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
}
