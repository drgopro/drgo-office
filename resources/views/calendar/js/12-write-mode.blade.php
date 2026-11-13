{{-- 작성 모드 — 일정 한 건만 띄우는 독립 창(/calendar/write) + 메인 캘린더의 '↗ 작성 모드' 진입 버튼 --}}
const CAL_WRITE = @json(! empty($calWriteMode));
const CAL_WRITE_NEW_DATE = @json($calWriteNewDate ?? null);
let __calWriteLastId = null;

if (CAL_WRITE) document.body.classList.add('cal-write');

// 일정 창을 닫았을 때 — 빈 캘린더 대신 안내 (창 닫기 / 다시 열기)
function calWriteIdle(msg) {
    let box = document.getElementById('calWriteIdle');
    if (!box) {
        box = document.createElement('div');
        box.id = 'calWriteIdle';
        document.body.appendChild(box);
    }
    box.innerHTML = `<div class="cwi-in"><div class="cwi-msg">${_esc(msg || '일정 창을 닫았습니다.')}</div>
        <div class="cwi-btns"><button class="nav-btn" onclick="window.close()">창 닫기</button>
        ${__calWriteLastId ? `<button class="add-btn" onclick="calWriteReopen()">다시 열기</button>` : `<button class="add-btn" onclick="calWriteHideIdle(); openNewModal(CAL_WRITE_NEW_DATE || todayStr())">새 일정 작성</button>`}</div></div>`;
    box.style.display = 'flex';
}
function calWriteHideIdle() { const b = document.getElementById('calWriteIdle'); if (b) b.style.display = 'none'; }
async function calWriteReopen() {
    calWriteHideIdle();
    const ev = events.find(e => e.id === __calWriteLastId);
    if (ev) { openDetailModal(ev); return; }
    window.__calFocusId = __calWriteLastId;
    await loadEvents();
}

if (CAL_WRITE) {
    const __origClose = closeModal;
    closeModal = function () {
        __origClose.apply(this, arguments);
        if (window.__calWriteKeep) { window.__calWriteKeep = false; return; } // 저장 직후 — 곧 같은 일정을 다시 띄움
        if (__calWriteDeleting) { __calWriteLastId = null; calWriteIdle('일정이 삭제되었습니다.'); return; }
        calWriteIdle('일정 창을 닫았습니다.');
    };
    const __origDetail = openDetailModal;
    openDetailModal = function (ev) {
        calWriteHideIdle();
        if (ev && ev.id) {
            __calWriteLastId = ev.id;
            document.title = `${ev.title || '일정'} · 작성 모드 - 닥터고블린 오피스`;
        }
        return __origDetail.apply(this, arguments);
    };
    const __origNew = openNewModal;
    openNewModal = function () { calWriteHideIdle(); document.title = '새 일정 · 작성 모드 - 닥터고블린 오피스'; return __origNew.apply(this, arguments); };
    // 새 일정 작성 모드 — 초기화(담당자·카테고리 로드) 직후 바로 작성 창
    if (CAL_WRITE_NEW_DATE) setTimeout(() => openNewModal(CAL_WRITE_NEW_DATE), 300);
}
let __calWriteDeleting = false; // 삭제 확정 처리 중 — 그 사이 닫히면 '삭제됨' 안내 (사유 미입력 등 실패 시엔 닫히지 않음)
if (CAL_WRITE) {
    const __origConfirmDel = confirmDeleteEvent;
    confirmDeleteEvent = async function () {
        __calWriteDeleting = true;
        try { return await __origConfirmDel.apply(this, arguments); } finally { __calWriteDeleting = false; }
    };
}

// 메인 캘린더 — 열린 기존 일정을 작성 모드 창으로 (일정 창 헤더의 ↗ 버튼)
(function () {
    if (CAL_WRITE) return;
    const overlay = document.getElementById('modalOverlay');
    const slot = document.getElementById('calWmSlot');
    if (!overlay || !slot) return;
    new MutationObserver(() => setTimeout(() => { // 일정 창 열기 처리(editingId 지정)가 끝난 뒤 판단
        if (!overlay.classList.contains('open') || !editingId) { slot.innerHTML = ''; return; }
        slot.innerHTML = drgoWriteMode.buttonHtml(`/calendar/write?focus=${editingId}`, `schedule-${editingId}`, 'icon-btn cal-wm-btn');
    }, 0)).observe(overlay, { attributes: true, attributeFilter: ['class'] });
})();

// 다른 창(작성 모드 ↔ 메인)에서 일정이 저장·삭제되면 목록 갱신 — 이 창에서 일정 창을 열고 있으면 방해하지 않음
(function () {
    let t = null;
    drgoWriteMode.onMutation(({ path }) => {
        if (!/^\/api\/events(\/|$)/.test(path || '')) return;
        if (document.getElementById('modalOverlay')?.classList.contains('open')) return;
        clearTimeout(t);
        t = setTimeout(() => loadEvents(), 400);
    });
})();

// 상단 '✍ 작성 모드' 메뉴 — 지금 열어 둔 일정
if (!CAL_WRITE) window.drgoWriteContext = () => {
    if (!document.getElementById('modalOverlay')?.classList.contains('open') || !editingId) return null;
    const ev = events.find(e => e.id === editingId) || detailEvent;
    return { kind: '일정', label: (ev && ev.title) || `#${editingId}`, url: `/calendar/write?focus=${editingId}`, key: `schedule-${editingId}` };
};
