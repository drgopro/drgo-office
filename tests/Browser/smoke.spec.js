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

test('캘린더 — 대기함: 접힘 기본 → 보내기 → 흔적 → 배치 → 되돌리기', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);
    await page.goto('/calendar');
    await page.waitForSelector('#daysGrid .day-cell');
    await page.waitForFunction(() => document.querySelector('#benchBtn .bench-cnt'));
    // 재시도 대비 — 이전 실행이 남긴 대기 일정은 보내기 취소로 정리
    await page.evaluate(async () => {
        const r = await benchApi('GET', '/api/bench');
        for (const b of r.data.items) await benchApi('POST', `/api/bench/${b.id}/undo`, { from: 'waiting' });
        await benchLoad(); await loadEvents();
    });

    // 기본 접힘 — 패널도 사용법 버튼도 보이지 않는다
    await expect(page.locator('#calBench')).toBeHidden();
    await expect(page.locator('#benchBtn')).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('button', { name: '대기함 사용법' })).toHaveCount(0);

    // 열면 패널 머리에 사용법 버튼 → 팝업, Esc로 닫힘
    await page.click('#benchBtn');
    await expect(page.locator('#calBench')).toBeVisible();
    await expect(page.locator('#benchBtn')).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('button', { name: '대기함 사용법' }).click();
    await expect(page.locator('.bench-pop.help')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.bench-pop')).toHaveCount(0);
    await page.click('#benchBtn'); // 다시 접기

    // 보내기 — 내용 없이는 저장되지 않는다
    await page.evaluate(() => benchSendOpen(events.find(e => (e.title || '').startsWith('스모크 방문세팅')).id));
    await page.getByRole('button', { name: '보내기', exact: true }).click();
    await expect(page.locator('#benchToast')).toContainText('내용을 적어 주세요');
    await expect(page.locator('.bench-pop')).toBeVisible();
    await page.fill('#benchPopWhy', '고객 장비 도착 지연');
    await page.getByRole('button', { name: '보내기', exact: true }).click();

    // 보류: 달력에서 빠지고 원래 날짜에 흔적, 대기함이 열리고 그 카드가 선택된다
    const card = page.locator('.bench-card[aria-selected="true"]');
    await expect(card).toContainText('스모크 방문세팅');
    await expect(card).toContainText('강남구');
    await expect(page.locator('#benchBtn .bench-cnt').first()).toHaveText('1');
    await expect(page.locator('.day-cell .bench-trace')).toContainText('스모크 방문세팅');
    expect(await page.evaluate(() => events.some(e => (e.title || '').startsWith('스모크 방문세팅')))).toBe(false);

    // 접어도 흔적은 남고, 흔적을 누르면 대기함이 열리며 카드가 선택된다
    await page.click('#benchBtn');
    await expect(page.locator('#calBench')).toBeHidden();
    await page.locator('.day-cell .bench-trace').click();
    await expect(card).toContainText('스모크 방문세팅');

    // 배치 — 시간을 비우면 '시간 미정', 달력에 ↩ 표시
    await card.getByRole('button', { name: '날짜 선택해서 배치' }).click();
    const placeBtn = card.getByRole('button', { name: /시간 미정으로 배치/ });
    await expect(placeBtn).toBeEnabled();
    await placeBtn.click();
    await expect(page.locator('#benchToast')).toContainText('배치했습니다');
    await expect(page.locator('#benchBtn .bench-cnt').first()).toHaveText('0');
    await expect(page.locator('.day-cell .event-chip .bench-mark').first()).toBeVisible();

    // 되돌리기 — 다시 대기함으로
    await page.locator('#benchToast').getByRole('button', { name: '되돌리기' }).click();
    await expect(page.locator('#benchBtn .bench-cnt').first()).toHaveText('1');

    // 뒷정리 — 보내기 취소로 일정을 원래 날짜에 돌려놓는다 (뒤 테스트의 시드 일정 유지)
    await page.evaluate(async () => {
        const r = await benchApi('GET', '/api/bench');
        for (const b of r.data.items) await benchApi('POST', `/api/bench/${b.id}/undo`, { from: 'waiting' });
        await benchLoad(); await loadEvents();
    });
    expect(await page.evaluate(() => events.some(e => (e.title || '').startsWith('스모크 방문세팅')))).toBe(true);
    expect(errors).toEqual([]);
});