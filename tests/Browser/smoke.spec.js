import { test, expect } from '@playwright/test';

// ── UI 스모크 — 핵심 페이지가 자바스크립트 에러 없이 렌더되는지 확인 ──
// 실행: php artisan smoke:run  (임시 sqlite DB + SmokeSeeder + 임시 서버)

// 외부 CDN 라이브러리(Chart.js, 다음 우편번호) 미로딩으로 인한 에러는 허용 —
// 오프라인 CI 환경에서 CDN이 차단되어도 자체 코드 회귀만 잡는다.
const EXTERNAL_LIB_ERRORS = [/Chart is not defined/, /daum is not defined/, /postcode/i];

/** 페이지 에러 수집기 — 각 테스트 끝에서 0건이어야 함 (외부 CDN 부재 제외) */
function collectErrors(page) {
    const errors = [];
    page.on('pageerror', e => {
        const msg = String(e).slice(0, 200);
        if (!EXTERNAL_LIB_ERRORS.some(re => re.test(msg))) errors.push(msg);
    });
    return errors;
}

async function login(page) {
    await page.goto('/login');
    await page.fill('input[name=username]', 'smoke');
    await page.fill('input[name=password]', 'smoke1234');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit], input[type=submit]')]);
}

test('캘린더 — 월간 뷰 렌더', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/calendar');
    await page.waitForSelector('#daysGrid .day-cell');
    expect(await page.locator('#daysGrid .day-cell').count()).toBeGreaterThanOrEqual(28);
    await expect(page.locator('.event-chip').first()).toBeVisible(); // 시드 일정 칩
    expect(errors).toEqual([]);
});

test('캘린더 — 컴팩트(전체) 뷰 + 하단 리스트', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/calendar');
    await page.waitForSelector('#daysGrid .day-cell');
    await page.click('#tabMonthC');
    await page.waitForSelector('.mc-cell');
    expect(await page.locator('.mc-cell').count()).toBe(42);
    expect(await page.locator('.mc-bar').count()).toBeGreaterThanOrEqual(1); // 연차 다일 바
    await expect(page.locator('#mcDeskList')).toBeVisible(); // 데스크탑 하단 리스트
    expect(errors).toEqual([]);
});

test('캘린더 — 새 일정 모달(방문의뢰) 열기', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/calendar');
    await page.waitForSelector('#daysGrid .day-cell');
    await page.evaluate(() => { openNewModal(fmt(new Date())); setColor('gold'); });
    await expect(page.locator('#modalOverlay')).toBeVisible();
    await expect(page.locator('#scheduleOpts .sched-opt-btn')).toHaveCount(4); // 확정 상태 버튼
    await expect(page.locator('#reqItemsView')).toBeAttached();               // 세팅 항목 표시 영역
    expect(errors).toEqual([]);
});

test('캘린더 — 기존 일정 요약(잠금) 뷰', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/calendar');
    await page.waitForSelector('#daysGrid .day-cell');
    await page.evaluate(async () => {
        await loadEvents();
        openEditModal(events.find(e => e.title.includes('스모크 방문세팅')));
    });
    await page.waitForSelector('#lockSummary .ls-card');
    expect(await page.locator('#lockSummary .ls-card').count()).toBeGreaterThanOrEqual(3);
    await expect(page.locator('#lockSummary .ls-sopt-btn.on')).toHaveText('확'); // 확정 퀵 피커
    expect(errors).toEqual([]);
});

test('프로젝트 상세 — 의뢰 내용 피커 + 추가 정보', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/projects/1'); // 스모크 DB는 빈 DB에 시드하므로 id=1 고정
    await page.waitForSelector('#reqItemPicker .rqp-title');
    await expect(page.locator('#reqItemTags .req-tag')).toHaveCount(1); // 시드된 마이크 추가 설치 ×2
    await expect(page.locator('#customDataCard')).toBeAttached();
    expect(errors).toEqual([]);
});

test('할 일 보드 렌더', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/todos');
    await page.waitForSelector('.todo-card, .todo-lrow');
    await expect(page.getByText('스모크 할 일').first()).toBeVisible();
    expect(errors).toEqual([]);
});

test('마케팅 통계 — 일정 지표 섹션', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/marketing-report');
    await expect(page.getByText('일정 지표').first()).toBeVisible();
    await expect(page.getByText('총 의뢰 건수').first()).toBeVisible();
    expect(errors).toEqual([]);
});

test('대시보드 렌더', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/');
    await expect(page.getByText('스모크 할 일').first()).toBeVisible(); // 나의 할 일 위젯
    expect(errors).toEqual([]);
});
