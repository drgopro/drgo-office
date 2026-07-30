import { defineConfig } from '@playwright/test';

// UI 스모크 테스트 — `php artisan smoke:run`이 SMOKE_BASE(임시 서버)와 함께 실행.
// SMOKE_CHROME이 지정되면 해당 크롬 실행 파일 사용 (미지정 시 playwright 설치 브라우저).
export default defineConfig({
    testDir: './tests/Browser',
    // 운영 서버는 PHP-FPM·MySQL과 자원을 나눠 써서 느림 — 넉넉한 타임아웃 + 일시적 크롬 크래시 재시도
    timeout: 60000,
    expect: { timeout: 10000 },
    retries: 2,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: process.env.SMOKE_BASE || 'http://127.0.0.1:8199',
        headless: true,
        viewport: { width: 1280, height: 950 },
        launchOptions: {
            executablePath: process.env.SMOKE_CHROME || undefined,
            args: ['--no-sandbox', '--no-proxy-server'],
        },
    },
});
