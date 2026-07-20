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
            display:flex; align-items:center; gap:8px; padding:12px 14px 10px;
            -webkit-app-region:drag; user-select:none; flex-shrink:0;
        }
        .wg-head img { width:20px; height:20px; border-radius:5px; }
        .wg-title { font-size:13px; font-weight:700; flex:1; }
        .wg-today { font-size:11px; color:var(--text-muted); }
        .wg-refresh { -webkit-app-region:no-drag; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:2px 4px; border-radius:5px; }
        .wg-refresh:hover { color:var(--text); background:var(--surface); }

        .wg-body { flex:1; overflow-y:auto; padding:0 10px 12px; }
        .wg-body::-webkit-scrollbar { width:4px; }
        .wg-body::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.15); border-radius:2px; }

        .wg-day { margin-top:10px; }
        .wg-day-head { display:flex; align-items:center; gap:6px; font-size:11.5px; font-weight:700; color:var(--text-muted); padding:0 4px 6px; }
        .wg-day-head .d-today { color:var(--accent); }
        .wg-day-head .d-cnt { font-weight:400; }

        .wg-item {
            display:flex; align-items:stretch; gap:8px; padding:7px 8px; border-radius:8px;
            background:var(--surface); border:1px solid var(--border); margin-bottom:5px;
        }
        .wg-item.done { opacity:0.4; }
        .wg-bar { width:4px; border-radius:2px; flex-shrink:0; align-self:stretch; }
        .wg-info { flex:1; min-width:0; }
        .wg-row1 { display:flex; align-items:center; gap:6px; }
        .wg-t { font-size:12.5px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; }
        .wg-who { font-size:10.5px; color:var(--text-muted); flex-shrink:0; }
        .wg-meta { font-size:11px; color:var(--text-muted); margin-top:2px; }
        .wg-chip {
            display:inline-block; font-size:10px; font-weight:700; padding:0 4px; border-radius:4px;
            line-height:1.5; margin-left:4px; flex-shrink:0;
            background:color-mix(in srgb, var(--accent) 18%, transparent);
            border:1px solid color-mix(in srgb, var(--accent) 45%, transparent); color:var(--accent);
        }
        .wg-chip.confirmed { background:#2f9e4426; border-color:#2f9e4466; color:#3fae54; }

        .wg-empty { text-align:center; color:var(--text-muted); padding:26px 0 14px; font-size:12px; }
        .wg-login { text-align:center; padding:40px 16px; }
        .wg-login a { display:inline-block; margin-top:10px; padding:8px 18px; background:var(--accent); color:#111; border-radius:8px; font-weight:700; text-decoration:none; font-size:13px; }
    </style>
</head>
<body>
<div class="wg">
    <div class="wg-head">
        <img src="/icon-192.png?v=3" alt="">
        <span class="wg-title">캘린더</span>
        <span class="wg-today" id="wgClock"></span>
        <button type="button" class="wg-refresh" onclick="wgLoad()" title="새로고침">↻</button>
    </div>
    <div class="wg-body" id="wgBody"><div class="wg-empty">불러오는 중…</div></div>
</div>
<script>
const CATS = @json(\App\Models\CalendarCategory::map(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
const SCHED_CHIP = {suggest:'제', hope:'희', target:'목', confirmed:'확'};
const SCHED_FULL = {suggest:'제안', hope:'희망', target:'목표', confirmed:'확정'};
const DAYS = 7;      // 오늘부터 표시할 일수
const POLL_MS = 30000; // 30초 갱신

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function fmt(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
const DOW = ['일','월','화','수','목','금','토'];

function catColor(key){ return (CATS[key] && CATS[key].color) || '#8a9bb0'; }

async function wgLoad(){
    const body = document.getElementById('wgBody');
    const start = new Date(); const end = new Date(); end.setDate(end.getDate() + DAYS - 1);
    let res;
    try {
        res = await fetch(`/api/events?start=${fmt(start)}&end=${fmt(end)}`, {headers:{'Accept':'application/json'}, redirect:'manual'});
    } catch(e) { return; } // 네트워크 오류 — 기존 표시 유지, 다음 폴링에서 재시도
    if (res.status === 401 || res.status === 419 || res.type === 'opaqueredirect' || res.redirected) {
        body.innerHTML = `<div class="wg-login">로그인이 필요합니다<br><a href="/login">로그인</a></div>`;
        return;
    }
    if (!res.ok) return;
    const events = await res.json();

    let html = '';
    const todayS = fmt(start);
    for (let i = 0; i < DAYS; i++) {
        const d = new Date(); d.setDate(d.getDate() + i);
        const ds = fmt(d);
        const dayEvs = events.filter(e => e.start_date <= ds && (e.end_date || e.start_date) >= ds)
            .sort((a,b) => (a.is_all_day?'':a.start_time||'99') < (b.is_all_day?'':b.start_time||'99') ? -1 : 1);
        if (!dayEvs.length) continue;
        const label = i===0 ? '오늘' : (i===1 ? '내일' : '');
        html += `<div class="wg-day"><div class="wg-day-head">
            <span class="${i===0?'d-today':''}">${d.getMonth()+1}월 ${d.getDate()}일 (${DOW[d.getDay()]})${label?' · '+label:''}</span>
            <span class="d-cnt">${dayEvs.length}건</span></div>`;
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
        html += '</div>';
    }
    body.innerHTML = html || '<div class="wg-empty">예정된 일정이 없습니다</div>';
}

function wgClock(){
    const n = new Date();
    document.getElementById('wgClock').textContent = `${n.getMonth()+1}.${String(n.getDate()).padStart(2,'0')} (${DOW[n.getDay()]}) ${String(n.getHours()).padStart(2,'0')}:${String(n.getMinutes()).padStart(2,'0')}`;
}

wgClock(); wgLoad();
setInterval(wgClock, 15000);
setInterval(wgLoad, POLL_MS);
</script>
</body>
</html>
