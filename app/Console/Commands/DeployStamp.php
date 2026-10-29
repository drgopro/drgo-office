<?php

namespace App\Console\Commands;

use App\Services\AppVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deploy:stamp')]
#[Description('배포 완료 스탬프 기록 — 배포 스크립트 마지막에 실행. 열린 화면의 "새로운 업데이트" 안내 기준이 된다')]
class DeployStamp extends Command
{
    public function handle(AppVersion $version): int
    {
        $path = $version->stampPath();
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("스탬프 디렉터리를 만들 수 없습니다: {$dir}");

            return self::FAILURE;
        }

        // 임시 파일에 쓰고 rename으로 교체 — 폴링 요청이 쓰는 도중의 빈/부분 파일을 읽지 않도록 원자적으로 갱신
        $stamp = ($version->gitCommit() ?? 'unknown').'@'.now()->format('YmdHis');
        $tmp = $path.'.'.getmypid().'.tmp';
        if (@file_put_contents($tmp, $stamp.PHP_EOL) === false || ! @rename($tmp, $path)) {
            @unlink($tmp);
            $this->error("스탬프를 기록할 수 없습니다: {$path}");

            return self::FAILURE;
        }

        $this->info("배포 스탬프 기록: {$stamp} → {$path}");

        return self::SUCCESS;
    }
}
