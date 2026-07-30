<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * UI 스모크 테스트 — 임시 sqlite DB에 시드하고 임시 서버를 띄워 Playwright로 핵심 화면을 점검.
 *
 * 사용: php artisan smoke:run
 * 결과는 콘솔 + storage/logs/smoke.log 에 기록 — 웹에서는 /admin/smoke-log 로 확인 (관리자).
 * (시스템 크롬을 쓰려면 SMOKE_CHROME=크롬_실행파일_경로)
 */
class RunSmokeTests extends Command
{
    protected $signature = 'smoke:run {--port=8199}';

    protected $description = 'UI 스모크 테스트 실행 (임시 DB + 임시 서버 + Playwright)';

    private string $log = '';

    /** 콘솔 출력 + 로그 파일 버퍼에 동시 기록 */
    private function say(string $line, string $style = 'info'): void
    {
        $this->{$style === 'error' ? 'error' : 'info'}($line);
        $this->log .= $line."\n";
    }

    public function handle(): int
    {
        $this->log = '── smoke:run '.now()->format('Y-m-d H:i:s')." ──\n";
        $result = self::FAILURE;

        try {
            $result = $this->runSmoke();
        } finally {
            $this->log .= "\n결과: ".($result === self::SUCCESS ? '✅ 통과' : '❌ 실패')
                .' ('.now()->format('Y-m-d H:i:s').")\n";
            @file_put_contents(storage_path('logs/smoke.log'), $this->log);
        }

        return $result;
    }

    private function runSmoke(): int
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
            // 운영 서버의 config:cache가 자식 프로세스에서 env 오버라이드를 무력화하는 것 방지 —
            // 캐시 파일 경로를 존재하지 않는 곳으로 돌려 .env + 위 오버라이드로 부팅시킴
            'APP_CONFIG_CACHE' => $db.'.config.php',
            'APP_ROUTES_CACHE' => $db.'.routes.php',
            'APP_EVENTS_CACHE' => $db.'.events.php',
        ];
        $php = PHP_BINARY;
        $npx = PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';

        // 0. Playwright 브라우저 확인/설치 — SMOKE_CHROME(시스템 크롬 경로) 지정 시 생략.
        if (! getenv('SMOKE_CHROME')) {
            $this->say('0/4 Playwright 브라우저 확인 (최초 1회만 다운로드)');
            $install = new Process([$npx, 'playwright', 'install', 'chromium'], base_path(), null, null, 900);
            $install->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });
            if (! $install->isSuccessful()) {
                $this->say('브라우저 설치 실패 — 수동으로 실행해 주세요: npx playwright install chromium', 'error');

                return self::FAILURE;
            }
        }

        $this->say('1/4 마이그레이션·시드');
        foreach ([
            [$php, 'artisan', 'migrate', '--force', '--no-interaction'],
            [$php, 'artisan', 'db:seed', '--class=Database\\Seeders\\SmokeSeeder', '--force', '--no-interaction'],
        ] as $cmd) {
            $p = new Process($cmd, base_path(), $env, null, 300);
            $p->run();
            if (! $p->isSuccessful()) {
                $this->say($p->getErrorOutput() ?: $p->getOutput(), 'error');

                return self::FAILURE;
            }
        }

        // 안전 점검 — 마이그레이션이 실제로 임시 sqlite에 적용됐는지 확인 (운영 DB 오염 방지 이중 안전장치)
        if (filesize($db) < 10240) {
            $this->say('임시 sqlite에 마이그레이션이 적용되지 않았습니다 — 실행 중단 (config 캐시 확인 필요)', 'error');

            return self::FAILURE;
        }

        $this->say("2/4 임시 서버 기동 (:{$port})");
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
                $this->say('서버가 기동되지 않았습니다: '.$server->getErrorOutput(), 'error');

                return self::FAILURE;
            }

            $this->say('3/4 Playwright 스모크 실행');
            $test = new Process(
                [$npx, 'playwright', 'test', '--config=playwright.config.js'],
                base_path(),
                ['SMOKE_BASE' => "http://127.0.0.1:{$port}"],
                null,
                900,
            );
            $test->setTty(false);
            $test->run(function ($type, $buffer) {
                $this->log .= $buffer;
                $this->output->write($buffer);
            });

            $this->say('4/4 정리');

            // 크롬 구동용 시스템 라이브러리 누락 (Ubuntu 최초 1회) — 원인과 해결책 명시
            if (! $test->isSuccessful() && str_contains($this->log, 'error while loading shared libraries')) {
                $this->say('브라우저 실행에 필요한 시스템 라이브러리가 없습니다. 서버에서 1회 실행해 주세요:', 'error');
                $this->say('  sudo npx playwright install-deps chromium  (Forge Recipe를 root로 실행해도 됩니다)');
            }

            return $test->isSuccessful() ? self::SUCCESS : self::FAILURE;
        } finally {
            $server->stop(3);
            @unlink($db);
        }
    }
}
