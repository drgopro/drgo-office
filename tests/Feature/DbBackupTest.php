<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DB 백업 커맨드 — mysqldump gzip + 보관 기한 정리 */
class DbBackupTest extends TestCase
{
    public function test_backup_fails_gracefully_on_non_mysql_connection(): void
    {
        // 테스트 환경은 sqlite — mysql 전용임을 명확히 알리고 실패해야 함
        $this->artisan('db:backup')
            ->expectsOutputToContain('mysql 연결에서만 사용할 수 있습니다')
            ->assertFailed();
    }

    public function test_prune_only_deletes_expired_backups_and_keeps_recent(): void
    {
        Storage::fake('local');
        Storage::makeDirectory('backups');
        Storage::put('backups/db-old.sql.gz', 'old');
        Storage::put('backups/db-new.sql.gz', 'new');
        Storage::put('backups/keep.txt', 'not-a-backup');

        // 오래된 백업 파일로 위장 (15일 전)
        touch(Storage::path('backups/db-old.sql.gz'), now()->subDays(15)->getTimestamp());

        $this->artisan('db:backup', ['--prune-only' => true, '--keep' => 14])->assertSuccessful();

        Storage::assertMissing('backups/db-old.sql.gz');
        Storage::assertExists('backups/db-new.sql.gz');
        Storage::assertExists('backups/keep.txt'); // .sql.gz 외 파일은 건드리지 않음
    }

    public function test_backup_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue(
            $events->contains(fn ($e) => str_contains($e->command ?? '', 'db:backup')),
            'db:backup 스케줄 등록'
        );
    }
}
