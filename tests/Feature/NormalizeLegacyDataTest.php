<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** data:normalize — 플랫폼·경력 표기 혼재 정리 (dry-run 기본, --apply 반영, 미지 값 보존) */
class NormalizeLegacyDataTest extends TestCase
{
    use RefreshDatabase;

    private function messyData(): array
    {
        $client = Client::create([
            'nickname' => '혼재의뢰자', 'grade' => 'normal',
            'platforms' => ['SOOP', '팬더티비', '킥'], 'career' => '신규',
        ]);
        $schedule = Schedule::create([
            'title' => '혼재 일정', 'start_date' => '2026-07-01', 'end_date' => '2026-07-01',
            'color' => 'gold', 'is_all_day' => true,
            'request_data' => ['platform' => 'soop, 아프리카', 'career' => '신규', 'nickname' => '혼재의뢰자'],
        ]);

        return [$client, $schedule];
    }

    public function test_dry_run_reports_but_does_not_change(): void
    {
        [$client, $schedule] = $this->messyData();

        $this->artisan('data:normalize')
            ->expectsOutputToContain('플랫폼 표기 변경 1건')
            ->assertSuccessful();

        $this->assertSame(['SOOP', '팬더티비', '킥'], $client->fresh()->platforms);
        $this->assertSame('신규', $client->fresh()->career);
        $this->assertSame('soop, 아프리카', $schedule->fresh()->request_data['platform']);
    }

    public function test_apply_normalizes_and_preserves_unknown_values(): void
    {
        [$client, $schedule] = $this->messyData();

        $this->artisan('data:normalize', ['--apply' => true])->assertSuccessful();

        $fresh = $client->fresh();
        $this->assertSame(['SOOP', '팬더', '킥'], $fresh->platforms); // 킥(미지 값)은 보존
        $this->assertSame('처음', $fresh->career);

        $g = $schedule->fresh()->request_data;
        $this->assertSame('SOOP', $g['platform']); // soop·아프리카 모두 SOOP → 중복 제거
        $this->assertSame('처음', $g['career']);
    }
}
