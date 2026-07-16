<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\Process\Process;

#[Signature('db:backup {--keep=14 : 보관 일수 (이보다 오래된 백업 삭제)} {--prune-only : 덤프 없이 오래된 백업만 정리}')]
#[Description('MySQL 데이터베이스를 mysqldump로 백업 (gzip, storage/app/private/backups) 후 오래된 백업 정리')]
class BackupDatabase extends Command
{
    private const BACKUP_DIR = 'backups';

    public function handle(): int
    {
        $keepDays = max(1, (int) $this->option('keep'));

        if (! $this->option('prune-only') && ! $this->dump()) {
            return self::FAILURE;
        }

        $pruned = $this->pruneOldBackups($keepDays);
        if ($pruned > 0) {
            $this->info("보관 기한({$keepDays}일) 지난 백업 {$pruned}개 삭제");
        }

        return self::SUCCESS;
    }

    private function dump(): bool
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("mysql 연결에서만 사용할 수 있습니다. (현재: {$config['driver']})");

            return false;
        }

        Storage::makeDirectory(self::BACKUP_DIR);
        $relative = self::BACKUP_DIR.'/db-'.now()->format('Y-m-d-His').'.sql.gz';
        $fullPath = Storage::path($relative);

        $command = [
            config('database.backup_mysqldump', 'mysqldump'), // PATH에 없으면 .env DB_BACKUP_MYSQLDUMP로 절대경로 지정

            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--single-transaction', // InnoDB 무중단 일관성 스냅샷
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            $config['database'],
        ];

        $gz = gzopen($fullPath, 'wb6');
        if (! $gz) {
            $this->error("백업 파일을 열 수 없습니다: {$fullPath}");

            return false;
        }

        // 비밀번호는 인자 대신 환경변수로 전달 (프로세스 목록 노출 방지)
        $process = new Process($command, null, ['MYSQL_PWD' => $config['password'] ?? '']);
        $process->setTimeout(600);
        $exitCode = $process->run(function (string $type, string $buffer) use ($gz) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
            }
        });
        gzclose($gz);

        if ($exitCode !== 0) {
            Storage::delete($relative);
            $error = trim($process->getErrorOutput()) ?: '알 수 없는 오류';
            Log::error("DB 백업 실패: {$error}");
            $this->error("mysqldump 실패: {$error}");

            return false;
        }

        $size = Number::fileSize(Storage::size($relative), precision: 1);
        $this->info("백업 완료: {$relative} ({$size})");

        return true;
    }

    /** 보관 기한이 지난 백업 파일 삭제. @return int 삭제 수 */
    private function pruneOldBackups(int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $pruned = 0;

        foreach (Storage::files(self::BACKUP_DIR) as $file) {
            if (str_ends_with($file, '.sql.gz') && Storage::lastModified($file) < $cutoff) {
                Storage::delete($file);
                $pruned++;
            }
        }

        return $pruned;
    }
}
