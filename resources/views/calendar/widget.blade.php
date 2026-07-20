<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>캘린더 위젯 - 닥터고블린 오피스</title>
    <link rel="icon" href="/favicon.ico?v=3" sizes="48x48">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --bg: rgba(17,17,17,0.82);
            --surface: rgba(255,255,255,0.05);
            --surface2: rgba(255,255,255,0.09);
            --border: rgba(255,255,255,0.10);
            --text: #f0ebe2;
            --text-muted: #9a958c;
            --accent: #d4bc96;
        }
        html, body { height:100%; }
        body {
            font-family:'Pretendard Variable', Pretendard, -apple-system, 'Malgun Gothic', sans-serif;
            background:var(--bg); color:var(--text); font-size:13px;
            overflow:hidden; border-radius:14px;
        }
        .wg { display:flex; flex-direction:column; height:100%; }

        /* 헤더 — 일렉트론 프레임리스 창 드래그 영역 */
        .wg-head {
            display:flex; align-items:center; gap:6px; padding:10px 12px 8px;
            -webkit-app-region:drag; user-select:none; flex-shrink:0;
        }
        .wg-head img { width:18px; height:18px; border-radius:5px; }
        .wg-title { font-size:13.5px; font-weight:700; }
        .wg-nav { -webkit-app-region:no-drag; display:flex; align-items:center; gap:2px; margin-left:auto; }
        .wg-nav button {
            background:none; border:1px solid var(--border); color:var(--text-muted); cursor:pointer;
            font-size:12px; padding:2px 8px; border-radius:6px; line-height:1.4;
        }
        .wg-nav button:hover { color:var(--text); background:var(--surface); }
        .wg-clock { font-size:10.5px; color:var(--text-muted); margin-left:6px; }

        /* 월간 그리드 */
        .wg-weekdays { display:grid; grid-template-columns:repeat(7,1fr); padding:0 8px; flex-shrink:0; }
        .wg-weekdays span { text-align:center; font-size:10px; font-weight:700; color:var(--text-muted); padding:3px 0; }
        .wg-weekdays span:first-child { color:#e06c6c; }
        .wg-grid { flex:1; display:grid; grid-template-columns:repeat(7,1fr); gap:2px; padding:0 8px; min-height:0; }
        .wg-cell {
            border-radius:6px; padding:3px 3px 2px; min-height:0; overflow:hidden; cursor:pointer;
            display:flex; flex-direction:column; gap:1px; border:1px solid transparent;
        }
        .wg-cell:hover { background:var(--surface); }
        .wg-cell.sel { background:var(--surface2); border-color:color-mix(in srgb, var(--accent) 50%, transparent); }
        .wg-cell .n { font-size:10px; font-weight:600; color:var(--text-muted); line-height:1.3; flex-shrink:0; }
        .wg-cell.dim .n { opacity:0.35; }
        .wg-cell.today .n {
            color:#111; background:var(--accent); border-radius:5px;
            width:16px; text-align:center; font-weight:800;
        }
        .wg-cell .sun { color:#e06c6c; }
        .wg-ev { display:flex; align-items:center; gap:3px; min-height:11px; overflow:hidden; flex-shrink:0; }
        .wg-ev i { width:3px; height:9px; border-radius:1.5px; flex-shrink:0; }
        .wg-ev b { font-size:9px; font-weight:500; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .wg-ev.done { opacity:0.35; }
        .wg-more { font-size:8.5px; color:var(--text-muted); line-height:1.2; }

        /* 선택일 일정 리스트 */
        .wg-list { flex-shrink:0; max-height:38%; overflow-y:auto; padding:6px 10px 10px; border-top:1px solid var(--border); margin-top:6px; }
        .wg-list::-webkit-scrollbar { width:4px; }
        .wg-list::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.15); border-radius:2px; }
        .wg-list-head { font-size:11px; font-weight:700; color:var(--text-muted); padding:2px 2px 6px; }
        .wg-list-head .d-today { color:var(--accent); }
        .wg-item {
            display:flex; align-items:stretch; gap:8px; padding:6px 8px; border-radius:8px;
            background:var(--surface); border:1px solid var(--border); margin-bottom:4px;
        }
        .wg-item.done { opacity:0.4; }
        .wg-bar { width:4px; border-radius:2px; flex-shrink:0; align-self:stretch; }
        .wg-info { flex:1; min-width:0; }
        .wg-row1 { display:flex; align-items:center; gap:6px; }
        .wg-t { font-size:12px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; }
        .wg-who { font-size:10px; color:var(--text-muted); flex-shrink:0; }
        .wg-meta { font-size:10.5px; color:var(--text-muted); margin-top:1px; }
        .wg-chip {
            display:inline-block; font-size:10px; font-weight:700; padding:0 4px; border-radius:4px;
            line-height:1.5; flex-shrink:0;
            background:color-mix(in srgb, var(--accent) 18%, transparent);
            border:1px solid color-mix(in srgb, var(--accent) 45%, transparent); color:var(--accent);
        }
        .wg-chip.confirmed { background:#2f9e4426; border-color:#2f9e4466; color:#3fae54; }

        .wg-empty { text-align:center; color:var(--text-muted); padding:14px 0 6px; font-size:11.5px; }
        .wg-login { text-align:center; padding:40px 16px; }
        .wg-login a { display:inline-block; margin-top:10px; padding:8px 18px; background:var(--accent); color:#111; border-radius:8px; font-weight:700; text-decoration:none; font-size:13px; }
    </style>
</head>
<body>
<div class="wg" id="wgRoot">
    <div class="wg-head">
        <img src="/icon-192.png?v=3" alt="">
        <span class="wg-title" id="wgMonthLabel"></span>
        <span class="wg-clock" id="wgClock"></span>
        <div class="wg-nav">
            <button type="button" onclick="wgMove(-1)">‹</button>
            <button type="button" onclick="wgToday()">오늘</button>
            <button type="button" onclick="wgMove(1)">›</button>
            <button type="button" onclick="wgLoad()" title="새로고침">↻</button>
        </div>
    </div>
    <div class="wg-weekdays"><span>일</span><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span>토</span></div>
    <div class="wg-grid" id="wgGrid"></div>
    <div class="wg-list" id="wgList"></div>
</div>
<script>
const CATS = @json(\App\Models\CalendarCategory::map(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
const SCHED_CHIP = {suggest:'제', hope:'희', target:'목', confirmed:'확'};
const SCHED_FULL = {suggest:'제안', hope:'희망', target:'목표', confirmed:'확정'};
const POLL_MS = 30000; // 30초 갱신
const MAX_CELL_EV = 3; // 셀당 표시 일정 수 (초과분 +N)

let curY, curM;            // 표시 중인 연/월
let selDate;               // 선택된 날짜 (yyyy-mm-dd)
let events = [];
let loggedOut = false;

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function fmt(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
const DOW = ['일','월','화','수','목','금','토'];
function catColor(key){ return (CATS[key] && CATS[key].color) || '#8a9bb0'; }
function todayStr(){ return fmt(new Date()); }
function evsOn(ds){
    return events.filter(e => e.start_date <= ds && (e.end_date || e.start_date) >= ds)
        .sort((a,b) => (a.is_all_day?'':a.start_time||'99') < (b.is_all_day?'':b.start_time||'99') ? -1 : 1);
}

function gridStart(){
    const first = new Date(curY, curM, 1);
    const s = new Date(first); s.setDate(1 - first.getDay());
    return s;
}
function gridEnd(){
    const s = gridStart(); const e = new Date(s); e.setDate(s.getDate() + 41);
    return e;
}

async function wgLoad(){
    let res;
    try {
        res = await fetch(`/api/events?start=${fmt(gridStart())}&end=${fmt(gridEnd())}`, {headers:{'Accept':'application/json'}, redirect:'manual'});
    } catch(e) { return; } // 네트워크 오류 — 기존 표시 유지, 다음 폴링에서 재시도
    if (res.status === 401 || res.status === 419 || res.type === 'opaqueredirect' || res.redirected) {
        loggedOut = true;
        document.getElementById('wgRoot').innerHTML = `<div class="wg-login">로그인이 필요합니다<br><a href="/login">로그인</a></div>`;
        return;
    }
    if (!res.ok) return;
    events = await res.json();
    render();
}

function render(){
    if (loggedOut) return;
    document.getElementById('wgMonthLabel').textContent = `${curY}년 ${curM+1}월`;
    const grid = document.getElementById('wgGrid');
    const today = todayStr();
    const start = gridStart();
    let html = '';
    const cur = new Date(start);
    let rows = 0;
    for (let w = 0; w < 6; w++) {
        // 이번 주 7일이 전부 다음달이면 종료
        const wkFirst = new Date(cur);
        if (w > 0 && wkFirst.getMonth() !== curM && wkFirst > new Date(curY, curM+1, 0)) break;
        rows++;
        for (let i = 0; i < 7; i++) {
            const ds = fmt(cur);
            const dim = cur.getMonth() !== curM;
            const dayEvs = evsOn(ds);
            const shown = dayEvs.slice(0, MAX_CELL_EV);
            html += `<div class="wg-cell${dim?' dim':''}${ds===today?' today':''}${ds===selDate?' sel':''}" onclick="wgSelect('${ds}')">
                <span class="n${cur.getDay()===0?' sun':''}">${cur.getDate()}</span>
                ${shown.map(e => `<span class="wg-ev${e.completed_at?' done':''}"><i style="background:${catColor(e.color)}"></i><b>${esc(e.title||'')}</b></span>`).join('')}
                ${dayEvs.length > MAX_CELL_EV ? `<span class="wg-more">+${dayEvs.length - MAX_CELL_EV}</span>` : ''}
            </div>`;
            cur.setDate(cur.getDate()+1);
        }
    }
    grid.style.gridTemplateRows = `repeat(${rows},1fr)`;
    grid.innerHTML = html;
    renderList();
}

function renderList(){
    const ds = selDate;
    const d = new Date(ds + 'T00:00:00');
    const dayEvs = evsOn(ds);
    const isToday = ds === todayStr();
    let html = `<div class="wg-list-head"><span class="${isToday?'d-today':''}">${d.getMonth()+1}월 ${d.getDate()}일 (${DOW[d.getDay()]})${isToday?' · 오늘':''}</span> · ${dayEvs.length}건</div>`;
    html += dayEvs.map(e => {
        const time = e.is_all_day ? '종일' : ((e.start_time||'').substring(0,5) + (e.end_time ? '~'+e.end_time.substring(0,5) : ''));
        const who = (e.assignees||[]).map(a => a.name).filter(Boolean);
        const whoTxt = who.length ? (who[0] + (who.length>1 ? ' +'+(who.length-1) : '')) : '';
        const chip = (e.sched_opt && SCHED_CHIP[e.sched_opt])
            ? `<span class="wg-chip ${e.sched_opt==='confirmed'?'confirmed':''}" title="${SCHED_FULL[e.sched_opt]}">${SCHED_CHIP[e.sched_opt]}</span>` : '';
        return `<div class="wg-item${e.completed_at?' done':''}">
            <span class="wg-bar" style="background:${catColor(e.color)}"></span>
            <div class="wg-info">
                <div class="wg-row1"><span class="wg-t">${esc(e.title||'(제목 없음)')}</span>${chip}${whoTxt?`<span class="wg-who">${esc(whoTxt)}</span>`:''}</div>
                <div class="wg-meta">${esc([time, (e.location||'').split(',')[0]].filter(Boolean).join(' · '))}</div>
            </div>
        </div>`;
    }).join('');
    if (!dayEvs.length) html += '<div class="wg-empty">일정이 없습니다</div>';
    document.getElementById('wgList').innerHTML = html;
}

function wgSelect(ds){ selDate = ds; render(); }
function wgMove(dir){
    curM += dir;
    if (curM > 11) { curM = 0; curY++; }
    if (curM < 0) { curM = 11; curY--; }
    wgLoad();
}
function wgToday(){
    const n = new Date();
    curY = n.getFullYear(); curM = n.getMonth(); selDate = todayStr();
    wgLoad();
}

function wgClock(){
    const n = new Date();
    const el = document.getElementById('wgClock');
    if (el) el.textContent = `${String(n.getHours()).padStart(2,'0')}:${String(n.getMinutes()).padStart(2,'0')}`;
    // 자정 넘어가면 오늘 표시 갱신
    if (selDate && fmt(n) !== wgClock._d) { wgClock._d = fmt(n); render(); }
}

wgToday();
wgClock();
setInterval(wgClock, 15000);
setInterval(wgLoad, POLL_MS);
</script>
</body>
</html>
