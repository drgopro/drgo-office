@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '캘린더 이력 - 닥터고블린 오피스')

@push('styles')
<style>
    .ch-wrap { padding:14px 20px; max-width:1400px; margin:0 auto; }

    /* 헤더 */
    .ch-header { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
    .ch-title { font-size:18px; font-weight:700; letter-spacing:0.04em; }
    .ch-back { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 10px; border-radius:7px; font-size:12px; cursor:pointer; }
    .ch-back:hover { border-color:var(--accent); color:var(--accent); }
    .ch-spacer { flex:1; }
    .ch-nav { background:none; border:1px solid var(--border); color:var(--text-muted); width:32px; height:32px; border-radius:7px; cursor:pointer; font-size:13px; display:inline-flex; align-items:center; justify-content:center; }
    .ch-nav:hover { border-color:var(--accent); color:var(--accent); }
    .ch-period { font-size:14px; font-weight:600; min-width:120px; text-align:center; }

    /* 상태 필터 */
    .ch-filter { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
    .ch-fbtn { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border:1px solid var(--border); background:none; color:var(--text-muted); border-radius:20px; font-size:12px; cursor:pointer; transition:all .15s; }
    .ch-fbtn:hover { border-color:var(--accent); color:var(--accent); }
    .ch-fbtn.active { color:var(--text); border-color:var(--accent); background:var(--surface2); }
    .ch-dot { width:8px; height:8px; border-radius:50%; background:var(--text-muted); }
    .ch-fbtn.active .ch-dot { background:var(--accent); }
    .ch-fbtn[data-state="completed"] .ch-dot { background:var(--green); }
    .ch-fbtn[data-state="modified"] .ch-dot { background:var(--purple); }
    .ch-fbtn[data-state="deleted"] .ch-dot { background:var(--red); }

    /* 캘린더 그리드 */
    .ch-grid-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .ch-weekdays { display:grid; grid-template-columns:repeat(7,1fr); background:var(--surface2); border-bottom:1px solid var(--border); }
    .ch-weekday { padding:8px 0; text-align:center; font-size:11px; color:var(--text-muted); letter-spacing:0.1em; font-weight:600; }
    .ch-days-grid { display:grid; grid-template-columns:repeat(7,1fr); grid-auto-rows:minmax(120px,1fr); }
    .ch-day { border-right:1px solid var(--border); border-bottom:1px solid var(--border); padding:6px; min-height:120px; position:relative; display:flex; flex-direction:column; gap:3px; overflow:hidden; }
    .ch-day:nth-child(7n) { border-right:none; }
    .ch-day-num { font-size:11px; color:var(--text-muted); margin-bottom:2px; }
    .ch-day.today .ch-day-num { color:var(--accent); font-weight:700; }
    .ch-day.other-month { opacity:0.4; }
    .ch-day.sunday .ch-day-num { color:var(--red); }

    /* 일정 칩 — 상태별 시각 구분 */
    .ch-chip { font-size:11px; padding:3px 7px; border-radius:4px; border-left:3px solid var(--accent); background:var(--surface2); cursor:pointer; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; transition:all .12s; position:relative; }
    .ch-chip:hover { filter:brightness(1.18); }
    .ch-chip .ch-marker { display:inline-block; margin-right:3px; font-size:10px; }
    .ch-chip .ch-time { display:inline-block; margin-right:4px; font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; }
    .ch-chip.state-completed .ch-time,
    .ch-chip.state-deleted .ch-time { opacity:0.7; }

    /* 활성 (기본) — 색상 그대로 */
    .ch-chip.color-gold { background:rgba(200,176,138,0.22); border-left-color:var(--chip-gold-bg); }
    .ch-chip.color-teal { background:rgba(232,137,74,0.22); border-left-color:var(--chip-teal-bg); }
    .ch-chip.color-blue { background:rgba(138,180,200,0.22); border-left-color:var(--chip-blue-bg); }
    .ch-chip.color-red { background:rgba(200,122,122,0.22); border-left-color:var(--chip-red-bg); }
    .ch-chip.color-green { background:rgba(122,200,122,0.22); border-left-color:var(--chip-green-bg); }
    .ch-chip.color-purple { background:rgba(180,122,200,0.22); border-left-color:var(--chip-purple-bg); }
    .ch-chip.color-holiday { background:rgba(200,122,122,0.22); border-left-color:var(--red); color:var(--red); font-weight:600; }

    /* 완료 — 회색톤 + 취소선 */
    .ch-chip.state-completed {
        background:var(--surface2) !important;
        border-left-color:var(--green) !important;
        color:var(--text-muted);
        text-decoration:line-through;
        opacity:0.7;
    }
    .ch-chip.state-completed .ch-marker { color:var(--green); }

    /* 변경 — purple bg + border, opacity 60% */
    .ch-chip.state-modified {
        background:rgba(155,112,200,0.18) !important;
        border:1px solid rgba(155,112,200,0.55) !important;
        border-left:3px solid var(--purple) !important;
        opacity:0.6;
    }
    .ch-chip.state-modified .ch-marker { color:var(--purple); }

    /* 삭제 — red bg + border, opacity 60%, 점선 */
    .ch-chip.state-deleted {
        background:rgba(212,136,136,0.18) !important;
        border:1px dashed var(--red) !important;
        border-left:3px dashed var(--red) !important;
        opacity:0.6;
        text-decoration:line-through;
    }
    .ch-chip.state-deleted .ch-marker { color:var(--red); }

    /* 우측 슬라이드 패널 */
    .ch-panel { position:fixed; top:0; right:0; bottom:0; width:420px; max-width:92vw; background:var(--surface); border-left:1px solid var(--border); z-index:90; transform:translateX(100%); transition:transform .25s cubic-bezier(.4,0,.2,1); display:flex; flex-direction:column; }
    .ch-panel.open { transform:translateX(0); }
    .ch-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border); }
    .ch-panel-title { font-size:14px; font-weight:700; }
    .ch-panel-close { background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer; }
    .ch-panel-meta { padding:12px 18px; border-bottom:1px solid var(--border); font-size:12px; color:var(--text-muted); display:flex; flex-direction:column; gap:4px; }
    .ch-panel-body { flex:1; overflow-y:auto; padding:14px 18px; display:flex; flex-direction:column; gap:10px; }
    .ch-panel-empty { padding:30px 0; text-align:center; color:var(--text-muted); font-size:12px; }
    .ch-log-item { padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; font-size:12px; line-height:1.5; }
    .ch-log-head { display:flex; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:wrap; }
    .ch-log-action { font-size:10px; padding:2px 7px; border-radius:3px; font-weight:600; }
    .ch-log-action.update { background:var(--surface); color:var(--accent); }
    .ch-log-action.delete { background:rgba(212,136,136,0.2); color:var(--red); }
    .ch-log-action.complete { background:rgba(122,200,122,0.2); color:var(--green); }
    .ch-log-action.uncomplete { background:var(--surface); color:var(--text-muted); }
    .ch-log-action.restore { background:rgba(138,180,200,0.2); color:var(--blue); }
    .ch-log-user { font-size:12px; font-weight:600; color:var(--text); }
    .ch-log-time { font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; margin-left:auto; }
    .ch-log-diff { display:flex; gap:6px; margin-top:4px; flex-wrap:wrap; align-items:center; }
    .ch-log-key { font-size:10px; color:var(--text-muted); margin-right:4px; }
    .ch-log-old { padding:2px 8px; border-radius:4px; background:rgba(212,136,136,0.15); color:var(--red); font-size:11px; text-decoration:line-through; }
    .ch-log-new { padding:2px 8px; border-radius:4px; background:rgba(136,212,136,0.15); color:var(--green); font-size:11px; }
    .ch-log-arrow { color:var(--text-muted); font-size:10px; }

    @media (max-width:768px) {
        .ch-day { min-height:80px; padding:4px; }
        .ch-day-num { font-size:10px; }
        .ch-chip { font-size:10px; padding:2px 5px; }
        .ch-panel { width:100%; }
    }
</style>
@endpush

@section('content')
<div class="ch-wrap">
    <div class="ch-header">
        <button class="ch-back" onclick="location.href='/calendar'">← 캘린더로</button>
        <span class="ch-title"><x-icon name="clip" :size="16"/> 캘린더 이력</span>
        <div class="ch-spacer"></div>
        <button class="ch-nav" onclick="chChangeMonth(-1)">‹</button>
        <span class="ch-period" id="chPeriod"></span>
        <button class="ch-nav" onclick="chChangeMonth(1)">›</button>
        <button class="ch-back" onclick="chGoToday()">오늘</button>
    </div>

    <div class="ch-filter">
        <button class="ch-fbtn active" data-state="all" onclick="chToggleFilter(this)"><span class="ch-dot"></span>전체</button>
        <button class="ch-fbtn active" data-state="active" onclick="chToggleFilter(this)"><span class="ch-dot"></span>활성</button>
        <button class="ch-fbtn active" data-state="completed" onclick="chToggleFilter(this)"><span class="ch-dot"></span>완료</button>
        <button class="ch-fbtn active" data-state="modified" onclick="chToggleFilter(this)"><span class="ch-dot"></span>변경됨</button>
        <button class="ch-fbtn active" data-state="deleted" onclick="chToggleFilter(this)"><span class="ch-dot"></span>삭제됨</button>
        <div style="flex:1;"></div>
        <span class="text-muted" id="chCount" style="font-size:11px; color:var(--text-muted); align-self:center;"></span>
    </div>

    <div class="ch-grid-wrap">
        <div class="ch-weekdays">
            <div class="ch-weekday" style="color:var(--red);">SUN</div>
            <div class="ch-weekday">MON</div>
            <div class="ch-weekday">TUE</div>
            <div class="ch-weekday">WED</div>
            <div class="ch-weekday">THU</div>
            <div class="ch-weekday">FRI</div>
            <div class="ch-weekday">SAT</div>
        </div>
        <div class="ch-days-grid" id="chDaysGrid"></div>
    </div>
</div>

<!-- 우측 슬라이드 패널: 변경 이력 타임라인 -->
<div class="ch-panel" id="chPanel">
    <div class="ch-panel-head">
        <span class="ch-panel-title" id="chPanelTitle">변경 이력</span>
        <button class="ch-panel-close" onclick="document.getElementById('chPanel').classList.remove('open')">×</button>
    </div>
    <div class="ch-panel-meta" id="chPanelMeta"></div>
    <div class="ch-panel-body" id="chPanelBody">
        <div class="ch-panel-empty">일정을 클릭하면 변경 이력이 표시됩니다.</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const chState = {
    year: new Date().getFullYear(),
    month: new Date().getMonth(),
    chips: [],
    activeStates: new Set(['all','active','completed','modified','deleted']),
};

const FIELD_LABELS = {
    title:'제목', start_date:'시작일', end_date:'종료일',
    start_time:'시작시간', end_time:'종료시간', is_all_day:'종일',
    color:'분류', client_name:'의뢰자', address:'주소',
    location:'장소', description:'설명', is_private:'비공개',
    is_locked:'잠금', notif_minutes:'알림(분)',
    sched_opt:'세부유형', sched_event_opts:'세부옵션',
    special_opts:'특수옵션', sched_after_days:'AS일수',
    sched_after_date:'AS만료일', sched_after_reason:'AS사유',
    gold_data:'방문 상세', teal_data:'원격 상세',
    assignees:'담당자', completed_at:'완료시각',
};
const COLOR_LABELS = {
    gold:'방문의뢰', teal:'원격/방송룸', blue:'사내업무',
    red:'휴가/개인', green:'촬영/스튜디오', purple:'미팅/내방', holiday:'공휴일',
};

function chEsc(s){ return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function chFmtDate(d){ return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
function chFmtTime(t){ return t ? new Date(t).toLocaleString('ko-KR',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '-'; }

// 변경 로그 값을 사람이 읽기 좋게 포맷팅
function chFmtValue(key, val) {
    if (val === null || val === undefined || val === '') return '—';

    // ISO datetime → 한국 시간대 날짜
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) {
        const d = new Date(val);
        if (isNaN(d.getTime())) return val;
        const kst = d.toLocaleDateString('ko-KR', {timeZone:'Asia/Seoul', year:'numeric', month:'2-digit', day:'2-digit'});
        // "2026. 05. 06." → "2026-05-06"
        return kst.replace(/\.\s?/g,'-').replace(/-$/,'');
    }
    // 'YYYY-MM-DD'
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    // 'HH:MM' 또는 'HH:MM:SS'
    if (typeof val === 'string' && /^\d{1,2}:\d{2}/.test(val)) return val.slice(0,5);
    // boolean
    if (val === true || val === 'true' || val === 1 || val === '1') return '예';
    if (val === false || val === 'false' || val === 0 || val === '0') return '아니오';
    // color enum
    if (key === 'color' && COLOR_LABELS[val]) return COLOR_LABELS[val];

    if (typeof val === 'object') {
        if (Array.isArray(val)) {
            if (val.length === 0) return '(없음)';
            if (val.every(x => typeof x === 'number')) return `${val.length}명`;
            const names = val.map(o => o?.name || o?.title).filter(Boolean).slice(0,3);
            if (names.length) return names.join(', ') + (val.length > 3 ? ` 외 ${val.length-3}` : '');
            return `(${val.length}개 항목)`;
        }
        if (val.name) return String(val.name);
        if (val.title) return String(val.title);
        return '(상세 변경)';
    }
    return String(val);
}

function chChangeMonth(delta) {
    chState.month += delta;
    if (chState.month < 0) { chState.month = 11; chState.year--; }
    if (chState.month > 11) { chState.month = 0; chState.year++; }
    chLoad();
}
function chGoToday() {
    const d = new Date();
    chState.year = d.getFullYear(); chState.month = d.getMonth();
    chLoad();
}

function chToggleFilter(btn) {
    const state = btn.dataset.state;
    if (state === 'all') {
        // 전체 토글
        const allOn = chState.activeStates.has('all');
        chState.activeStates.clear();
        if (!allOn) {
            ['all','active','completed','modified','deleted'].forEach(s => chState.activeStates.add(s));
        }
    } else {
        if (chState.activeStates.has(state)) chState.activeStates.delete(state);
        else chState.activeStates.add(state);
        // "전체"는 active+completed+modified+deleted 모두 켜져 있을 때만
        const otherAllOn = ['active','completed','modified','deleted'].every(s => chState.activeStates.has(s));
        if (otherAllOn) chState.activeStates.add('all');
        else chState.activeStates.delete('all');
    }
    document.querySelectorAll('.ch-fbtn').forEach(b => b.classList.toggle('active', chState.activeStates.has(b.dataset.state)));
    chRender();
}

async function chLoad() {
    const first = new Date(chState.year, chState.month, 1);
    const startDay = new Date(first); startDay.setDate(1 - first.getDay());
    const last = new Date(chState.year, chState.month+1, 0);
    const endDay = new Date(last); endDay.setDate(last.getDate() + (6 - last.getDay()));

    const params = new URLSearchParams({ start: chFmtDate(startDay), end: chFmtDate(endDay) });
    const res = await fetch(`/api/events/history?${params}`, {headers:{'Accept':'application/json'}});
    if (!res.ok) { document.getElementById('chDaysGrid').innerHTML = '<div style="padding:40px; text-align:center; color:var(--red); grid-column:1/-1;">로드 실패</div>'; return; }
    chState.chips = await res.json();
    chRender();
}

function chRender() {
    document.getElementById('chPeriod').textContent = `${chState.year}년 ${chState.month+1}월`;

    const grid = document.getElementById('chDaysGrid');
    const first = new Date(chState.year, chState.month, 1);
    const startDay = new Date(first); startDay.setDate(1 - first.getDay());
    const todayStr = chFmtDate(new Date());

    const filtered = chState.chips.filter(c => chState.activeStates.has(c.state));
    document.getElementById('chCount').textContent = `${filtered.length}건 표시 (총 ${chState.chips.length}건)`;

    // 날짜별 묶기 — 인덱스도 같이 저장 (클릭 시 chip 객체 찾기)
    const byDate = {};
    filtered.forEach(c => {
        const idx = chState.chips.indexOf(c);
        const sd = new Date(c.display_start_date), ed = new Date(c.display_end_date || c.display_start_date);
        for (let d = new Date(sd); d <= ed; d.setDate(d.getDate()+1)) {
            const key = chFmtDate(d);
            (byDate[key] ||= []).push({chip:c, idx});
        }
    });

    let html = '';
    for (let i = 0; i < 42; i++) {
        const d = new Date(startDay); d.setDate(startDay.getDate() + i);
        const key = chFmtDate(d);
        const isOther = d.getMonth() !== chState.month;
        const isToday = key === todayStr;
        const isSunday = d.getDay() === 0;
        const items = byDate[key] || [];

        const chips = items.map(({chip:c, idx}) => {
            const stateClass = `state-${c.state}`;
            const colorClass = `color-${c.color}`;
            const marker = c.state === 'completed' ? '✓' : c.state === 'deleted' ? '🗑' : c.state === 'modified' ? '↻' : '';
            const t = (!c.is_all_day && c.start_time) ? String(c.start_time).slice(0,5) : '';
            // 제목만 표기 (의뢰자 이름은 표시하지 않음 — 메인 캘린더와 동일)
            const labelText = c.title || '(제목 없음)';
            const tooltipParts = [labelText];
            if (t) tooltipParts.unshift(t);
            const tooltip = (c.is_shadow ? (c.state === 'deleted' ? '삭제된 일정 — ' : '변경 전 위치 — ') : '') + tooltipParts.join(' ');
            return `<div class="ch-chip ${colorClass} ${stateClass}" onclick="chOpenPanel(${idx})" title="${chEsc(tooltip)}">
                ${marker ? `<span class="ch-marker">${marker}</span>` : ''}${t ? `<span class="ch-time">${t}</span>` : ''}${chEsc(labelText)}
            </div>`;
        }).join('');

        html += `<div class="ch-day ${isOther?'other-month':''} ${isToday?'today':''} ${isSunday?'sunday':''}">
            <div class="ch-day-num">${d.getDate()}</div>
            ${chips}
        </div>`;
    }
    grid.innerHTML = html;
}

async function chOpenPanel(chipIdx) {
    const c = chState.chips[chipIdx];
    if (!c) return;
    const scheduleId = c.schedule_id;
    document.getElementById('chPanel').classList.add('open');
    document.getElementById('chPanelTitle').textContent = c.title || '(제목 없음)';

    const stateLabel = { active:'활성', completed:'완료됨', modified:'변경됨', deleted:'삭제됨' }[c.state] || c.state;
    const shadowNote = c.is_shadow
        ? (c.state === 'deleted' ? '<div style="color:var(--red);">⚠ 이 위치에서 삭제됨</div>' : '<div style="color:var(--purple);">↻ 변경 전 위치 (현재는 다른 날짜)</div>')
        : '';
    document.getElementById('chPanelMeta').innerHTML = `
        <div>📅 ${c.display_start_date}${c.display_end_date && c.display_end_date !== c.display_start_date ? ' ~ '+c.display_end_date : ''}${c.is_all_day ? ' · 종일' : (c.start_time ? ' · '+String(c.start_time).slice(0,5)+(c.end_time?'-'+String(c.end_time).slice(0,5):'') : '')}</div>
        ${c.client_name ? `<div>👤 ${chEsc(c.client_name)}</div>` : ''}
        ${c.location ? `<div>📍 ${chEsc(c.location)}</div>` : ''}
        <div>상태: <span style="color:var(--text);">${stateLabel}</span> · 변경 ${c.changes_count}회${c.completed_at ? ' · 완료 '+chFmtTime(c.completed_at) : ''}${c.deleted_at ? ' · 삭제 '+chFmtTime(c.deleted_at) : ''}</div>
        ${shadowNote}
    `;
    document.getElementById('chPanelBody').innerHTML = '<div class="ch-panel-empty">로딩 중...</div>';

    const res = await fetch(`/api/events/${scheduleId}/history`, {headers:{'Accept':'application/json'}});
    if (!res.ok) { document.getElementById('chPanelBody').innerHTML = '<div class="ch-panel-empty" style="color:var(--red);">로드 실패</div>'; return; }
    const data = await res.json();
    const items = Array.isArray(data) ? data : (data.changes || []);
    if (!items.length) {
        document.getElementById('chPanelBody').innerHTML = '<div class="ch-panel-empty">변경 이력이 없습니다.</div>';
        return;
    }

    const ACTION_LABEL = { update:'수정', delete:'삭제', complete:'완료', uncomplete:'완료해제', restore:'복원' };

    document.getElementById('chPanelBody').innerHTML = items.map(h => {
        const changes = h.changes || {};
        let body = '';

        if (h.action === 'delete' && changes.snapshot) {
            const snap = changes.snapshot;
            const lines = [];
            if (snap.title) lines.push(`<div><span class="ch-log-key">제목</span> <span style="color:var(--text);">${chEsc(snap.title)}</span></div>`);
            if (snap.start_date) lines.push(`<div><span class="ch-log-key">일자</span> <span style="color:var(--text);">${chFmtValue('start_date', snap.start_date)}${snap.end_date && snap.end_date !== snap.start_date ? ' ~ '+chFmtValue('end_date', snap.end_date) : ''}</span></div>`);
            if (snap.client_name) lines.push(`<div><span class="ch-log-key">의뢰자</span> <span style="color:var(--text);">${chEsc(snap.client_name)}</span></div>`);
            if (snap.location) lines.push(`<div><span class="ch-log-key">장소</span> <span style="color:var(--text);">${chEsc(snap.location)}</span></div>`);
            body = `<div style="display:flex; flex-direction:column; gap:3px; margin-top:4px; font-size:11px;">${lines.join('')}</div>`;
        } else {
            const entries = Object.entries(changes).filter(([k]) => k !== 'snapshot');
            if (!entries.length) {
                body = '';
            } else {
                body = entries.map(([key, val]) => {
                    const label = FIELD_LABELS[key] || key;
                    const oldFmt = chFmtValue(key, val.old);
                    const newFmt = chFmtValue(key, val.new);
                    return `<div class="ch-log-diff">
                        <span class="ch-log-key">${label}</span>
                        <span class="ch-log-old">${chEsc(oldFmt)}</span>
                        <span class="ch-log-arrow">→</span>
                        <span class="ch-log-new">${chEsc(newFmt)}</span>
                    </div>`;
                }).join('');
            }
        }

        const reasonHtml = h.reason
            ? `<div style="margin-top:6px; padding:6px 10px; background:var(--surface); border-left:3px solid var(--accent); border-radius:4px; font-size:12px; color:var(--text);"><span style="font-size:10px; color:var(--text-muted); letter-spacing:0.04em; margin-right:6px;">사유</span>${chEsc(h.reason)}</div>`
            : '';
        return `<div class="ch-log-item">
            <div class="ch-log-head">
                <span class="ch-log-action ${h.action}">${ACTION_LABEL[h.action]||h.action}</span>
                <span class="ch-log-user">${chEsc(h.user_name||'-')}</span>
                <span class="ch-log-time">${h.created_at}</span>
            </div>
            ${body}
            ${reasonHtml}
        </div>`;
    }).join('');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('chPanel').classList.remove('open');
});

chLoad();
</script>
@endpush
