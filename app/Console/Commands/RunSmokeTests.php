<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * UI 스모크 테스트 — 임시 sqlite DB에 시드하고 임시 서버를 띄워 Playwright로 핵심 화면을 점검.
 *
 * 사용: php artisan smoke:run
 * 사전 준비(1회): npm install && npx playwright install chromium
 * (시스템 크롬을 쓰려면 SMOKE_CHROME=크롬_실행파일_경로)
 */
class RunSmokeTests extends Command
{
    protected $signature = 'smoke:run {--port=8199}';

    protected $description = 'UI 스모크 테스트 실행 (임시 DB + 임시 서버 + Playwright)';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $db = storage_path('framework/testing/smoke.sqlite');
        @mkdir(dirname($db), 0777, true);
        @unlink($db);
        touch($db);

        $env = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $db,
            'APP_URL' => "http://127.0.0.1:{$port}",
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ];
        $php = PHP_BINARY;

        $npx = PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';

        // 0. Playwright 브라우저 확인/설치 — SMOKE_CHROME(시스템 크롬 경로) 지정 시 생략.
        //    이미 설치돼 있으면 즉시 통과하는 멱등 명령이라 매번 실행해도 비용이 없음.
        if (! getenv('SMOKE_CHROME')) {
            $this->info('0/4 Playwright 브라우저 확인 (최초 1회만 다운로드)');
            $install = new Process([$npx, 'playwright', 'install', 'chromium'], base_path(), null, null, 900);
            $install->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });
            if (! $install->isSuccessful()) {
                $this->error('브라우저 설치 실패 — 수동으로 실행해 주세요: npx playwright install chromium');

                return self::FAILURE;
            }
        }

        $this->info('1/4 마이그레이션·시드');
        foreach ([
            [$php, 'artisan', 'migrate', '--force', '--no-interaction'],
            [$php, 'artisan', 'db:seed', '--class=Database\\Seeders\\SmokeSeeder', '--force', '--no-interaction'],
        ] as $cmd) {
            $p = new Process($cmd, base_path(), $env, null, 300);
            $p->run();
            if (! $p->isSuccessful()) {
                $this->error($p->getErrorOutput() ?: $p->getOutput());

                return self::FAILURE;
            }
        }

        $this->info("2/4 임시 서버 기동 (:{$port})");
        $server = new Process([$php, 'artisan', 'serve', '--host=127.0.0.1', "--port={$port}"], base_path(), $env, null, null);
        $server->start();

        try {
            $up = false;
            for ($i = 0; $i < 60 && ! $up; $i++) {
                usleep(500_000);
                $fp = @fsockopen('127.0.0.1', $port, $ec, $em, 1);
                if ($fp) {
                    fclose($fp);
                    $up = true;
                }
            }
            if (! $up) {
                $this->error('서버가 기동되지 않았습니다: '.$server->getErrorOutput());

                return self::FAILURE;
            }

            $this->info('3/4 Playwright 스모크 실행');
            $test = new Process(
                [$npx, 'playwright', 'test', '--config=playwright.config.js'],
                base_path(),
                ['SMOKE_BASE' => "http://127.0.0.1:{$port}"],
                null,
                900,
            );
            $test->setTty(false);
            $test->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            $this->info('4/4 정리');

            return $test->isSuccessful() ? self::SUCCESS : self::FAILURE;
        } finally {
            $server->stop(3);
            @unlink($db);
        }
    }
}
