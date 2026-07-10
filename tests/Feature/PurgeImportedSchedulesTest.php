<?php

namespace Tests\Feature;

use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeImportedSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeImported(string $uid, bool $trashed = false): Schedule
    {
        $s = Schedule::create([
            'title' => '가져온 일정', 'start_date' => '2026-04-23', 'end_date' => '2026-04-23',
            'is_all_day' => true, 'color' => 'gold', 'import_uid' => $uid,
        ]);
        if ($trashed) {
            $s->delete();
        }

        return $s;
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->makeImported('p1@ticktick-backup');

        $this->artisan('calendar:purge-imported')
            ->expectsOutputToContain('1건')
            ->assertSuccessful();

        $this->assertSame(1, Schedule::count());
    }

    public function test_force_hard_deletes_only_imported_including_trashed(): void
    {
        $this->makeImported('p1@ticktick-backup');
        $this->makeImported('p2@ticktick-backup', trashed: true);
        $manual = Schedule::create([
            'title' => '직접 만든 일정', 'start_date' => '2026-04-23', 'end_date' => '2026-04-23',
            'is_all_day' => true, 'color' => 'blue',
        ]);

        $this->artisan('calendar:purge-imported', ['--force' => true])->assertSuccessful();

        // 하드 삭제 → withTrashed로도 없음 → 재가져오기 시 UID 중복에 안 걸림
        $this->assertSame(0, Schedule::withTrashed()->whereNotNull('import_uid')->count());
        $this->assertNotNull($manual->fresh(), '직접 만든 일정은 유지');
    }
}
