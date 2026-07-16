<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DiskSpaceWarning;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

#[Signature('disk:check {--threshold=80 : 경고 사용률(%)} {--path= : 검사할 경로 (기본: 앱 루트)}')]
#[Description('서버 디스크 사용률 점검 — 임계치 초과 시 관리자에게 알림 (알림 벨 + 웹푸시)')]
class CheckDiskSpace extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: base_path();
        $threshold = max(1, min(99, (int) $this->option('threshold')));

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (! $total || $free === false) {
            $this->error("디스크 정보를 읽을 수 없습니다: {$path}");

            return self::FAILURE;
        }

        $usedPercent = (int) round(($total - $free) / $total * 100);
        $freeHuman = Number::fileSize($free, precision: 1);
        $this->info("디스크 사용률 {$usedPercent}% · 남은 용량 {$freeHuman} ({$path})");

        if ($usedPercent < $threshold) {
            return self::SUCCESS;
        }

        Log::warning("디스크 사용률 경고: {$usedPercent}% (임계치 {$threshold}%)");

        $admins = User::whereIn('role', ['master', 'admin'])->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new DiskSpaceWarning($usedPercent, $freeHuman));
            } catch (\Throwable $e) {
                Log::warning('디스크 경고 알림 발송 실패: '.$e->getMessage());
            }
        }
        $this->warn("임계치({$threshold}%) 초과 — 관리자 {$admins->count()}명에게 알림 발송");

        return self::SUCCESS;
    }
}
