{{-- 독립 창 레이아웃 — tab-content(조각)에 문서 머리(CSRF·뷰포트·제목)를 씌워 단독 창/탭으로 띄운다 (작성 모드).
     의뢰자·프로젝트·캘린더 작성 모드는 창 하나(drgo-write)를 같이 쓰고, 위쪽 이동 바로 서로 오간다. --}}
<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', '닥터고블린 오피스')</title>
    <style>
        .wmnav { position:sticky; top:0; z-index:9000; display:flex; align-items:center; gap:14px; height:52px; padding:0 16px; background:#fff; border-bottom:1px solid #dfe2e7; }
        .wmnav-title { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:800; color:#1f2430; white-space:nowrap; flex-shrink:0; }
        .wmnav-title svg { width:15px; height:15px; fill:none; stroke:currentColor; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
        .wmtabs { display:flex; align-items:stretch; gap:4px; height:38px; padding:3px; background:#f0f2f5; border-radius:11px; min-width:0; }
        .wmtab { position:relative; display:flex; align-items:center; min-width:0; border-radius:8px; }
        .wmtab.on { background:#fff; box-shadow:0 1px 2px rgba(23,26,32,.12), 0 0 0 1px #e1e4e9; }
        .wmtab-go { display:flex; align-items:center; gap:7px; line-height:1.2; height:100%; padding:0 6px 0 12px; border:0; background:none; cursor:pointer; min-width:0; max-width:240px; font:inherit; color:#5f6573; }
        .wmtab-go b { font-size:13px; font-weight:700; color:#3b404a; white-space:nowrap; }
        .wmtab-go span { font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
        .wmtab.on .wmtab-go b, .wmtab.on .wmtab-go span { color:#171a20; }
        .wmtab.on .wmtab-go span { font-weight:600; }
        .wmtab-find { flex-shrink:0; width:26px; height:26px; margin-right:4px; border:0; border-radius:6px; background:none; color:#656b78; cursor:pointer; display:grid; place-items:center; }
        .wmtab-find:hover { background:#e6e8ec; color:#171a20; }
        .wmtab-find svg { width:14px; height:14px; fill:none; stroke:currentColor; stroke-width:2.2; stroke-linecap:round; }
        .wmtab.cal .wmtab-go { padding-right:12px; }
        .wm-pop { display:none; position:absolute; left:0; top:calc(100% + 8px); width:380px; max-width:calc(100vw - 24px); background:#fff; color:#1a1e28; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,.16); padding:10px; z-index:9600; }
        .wm-pop.open { display:block; }
        .wm-pop .wm-pop-new { width:100%; height:34px; margin-bottom:8px; border-radius:8px; border:1px dashed #9aa3b2; background:#f6f7f9; color:#1f2430; font-size:13px; font-weight:700; cursor:pointer; }
        /* 작성 모드 화면 — 넓은 창에서 의뢰자 화면이 끝없이 늘어나지 않게 */
        body .client-pane { max-width:1320px; margin:0 auto; }
        @media (max-width: 720px) {
            .wmnav { padding:0 10px; gap:8px; }
            .wmnav-title span { display:none; }
            .wmtabs { flex:1; }
            .wmtab { flex:1; }
            .wmtab-go { padding:0 4px 0 10px; flex:1; }
            .wmtab-go span { display:none; }
            .wm-pop { position:fixed; left:10px; right:10px; top:58px; width:auto; }
        }
    </style>
</head>
<body style="margin:0; background:var(--bg, #f4f5f7);">
<nav class="wmnav" aria-label="작성 모드 이동">
    <span class="wmnav-title"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg><span>작성 모드</span></span>
    <div class="wmtabs" role="tablist">
        <div class="wmtab" data-kind="clients"><button type="button" class="wmtab-go" role="tab" onclick="wmGo(event,'clients')"><b>의뢰자</b><span></span></button><button type="button" class="wmtab-find" onclick="wmNavPop(event,'clients')" title="다른 의뢰자 찾기 · 새 등록" aria-label="의뢰자 찾기"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg></button><div class="wm-pop" id="wmPopClients"></div></div>
        <div class="wmtab" data-kind="projects"><button type="button" class="wmtab-go" role="tab" onclick="wmGo(event,'projects')"><b>프로젝트</b><span></span></button><button type="button" class="wmtab-find" onclick="wmNavPop(event,'projects')" title="다른 프로젝트 찾기" aria-label="프로젝트 찾기"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg></button><div class="wm-pop" id="wmPopProjects"></div></div>
        <div class="wmtab cal" data-kind="calendar"><button type="button" class="wmtab-go" role="tab" onclick="wmGo(event,'calendar')"><b>캘린더</b></button></div>
    </div>
</nav>
@include('layouts.tab-content')
<script>
// 작성 모드 창 — 의뢰자·프로젝트·캘린더는 이 창 안에서 오간다. 그 밖의 화면(견적서 목록 등)은 메인 오피스 창의 탭으로 보낸다
window.DRGO_STANDALONE = true;
try { window.name = 'drgo-write'; } catch (e) {}
(function () {
    // 메인 화면용 주소 → 작성 모드 주소 (같은 창에서 이동). 대응이 없으면 null
    function toWriteUrl(type, url) {
        try {
            const u = new URL(url, location.origin);
            const p = u.pathname;
            if (/^\/(clients\/(\d+|create)|projects\/\d+)\/write/.test(p) || p === '/calendar/write') { return p + u.search; }
            if (type === 'clients' || p.startsWith('/clients')) {
                const id = u.searchParams.get('open') || (p.match(/^\/clients\/(\d+)/) || [])[1];
                return id ? `/clients/${id}/write` : null;
            }
            if (type === 'projects' || p.startsWith('/projects')) {
                const id = (p.match(/^\/projects\/(\d+)$/) || [])[1];
                return id ? `/projects/${id}/write` : null;
            }
            if (type === 'calendar' || p.startsWith('/calendar')) {
                const q = new URLSearchParams();
                ['date', 'focus'].forEach(k => { if (u.searchParams.get(k)) { q.set(k, u.searchParams.get(k)); } });
                return '/calendar/write' + (q.toString() ? '?' + q : '');
            }
        } catch (e) {}
        return null;
    }
    window.openTopTab = function (type, url, title) {
        const w = toWriteUrl(type, url);
        if (w) { location.href = w; return; }
        try {
            const top = window.opener && !window.opener.closed ? window.opener.top : null;
            if (top && top.drgoTabs && typeof top.drgoTabs.openNav === 'function') { top.drgoTabs.openNav(type, url, title); top.focus(); return; }
        } catch (e) { /* 다른 출처 등 */ }
        window.open(url, '_blank');
    };

    // 의뢰자 ▾ / 프로젝트 ▾ — 기존 건을 찾아 열기·수정 (의뢰자는 새 등록도)
    window.wmNavPop = function (e, kind) {
        e.stopPropagation();
        const pop = document.getElementById(kind === 'clients' ? 'wmPopClients' : 'wmPopProjects');
        if (!pop) { return; }
        const wasOpen = pop.classList.contains('open');
        document.querySelectorAll('.wm-pop').forEach(p => p.classList.remove('open'));
        if (wasOpen) { return; }
        pop.innerHTML = (kind === 'clients' ? '<button type="button" class="wm-pop-new" onclick="location.href=\'/clients/create/write\'">+ 새 의뢰자 등록</button>' : '') + '<div class="wm-pop-s"></div>';
        pop.onclick = ev => ev.stopPropagation();
        drgoWriteMode.mountSearch(pop.querySelector('.wm-pop-s'), [kind]);
        const rel = kind === 'projects' ? relatedProjects() : [];
        if (rel.length) {
            const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const res = pop.querySelector('.wm-s-res');
            res.innerHTML = '<div class="wm-s-empty" style="padding:4px 6px 6px;font-weight:700;color:#1f2430;">이 의뢰자의 프로젝트</div>' + rel.map(p => `<div class="wm-s-row"><span class="k">${p.stage === 'done' ? '완료' : p.stage === 'cancelled' ? '취소' : '진행'}</span><span class="t"><b>${esc(p.name)}</b><span>${esc(p.created_at || '')}</span></span><button type="button" onclick="location.href='/projects/${p.id}/write'">열기</button></div>`).join('');
        }
        pop.classList.add('open');
    };
    document.addEventListener('click', () => document.querySelectorAll('.wm-pop').forEach(p => p.classList.remove('open')));

        // 세 탭 — 이 창에서 마지막으로 본 의뢰자 · 프로젝트 · 캘린더를 기억해 탭으로 오간다 (창을 닫으면 초기화)
    const KEY = 'drgo_wm_last';
    const here = location.pathname + location.search.replace(/[?&]edit=1/, '');
    const kindOf = p => p.startsWith('/clients') ? 'clients' : p.startsWith('/projects') ? 'projects' : p.startsWith('/calendar') ? 'calendar' : '';
    const cur = kindOf(location.pathname);
    function load() { try { return JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } }
    function save(v) { try { sessionStorage.setItem(KEY, JSON.stringify(v)); } catch (e) {} }
    function render() {
        const last = load();
        document.querySelectorAll('.wmtab').forEach(t => {
            const k = t.dataset.kind;
            t.classList.toggle('on', k === cur);
            const sp = t.querySelector('.wmtab-go span');
            if (sp) { sp.textContent = last[k] ? last[k].l : ''; t.querySelector('.wmtab-go').title = last[k] ? last[k].l : (k === 'clients' ? '의뢰자 찾기' : '프로젝트 찾기'); }
        });
    }
    function remember(label) {
        if (!cur) { return; }
        const last = load();
        last[cur] = { u: here, l: label || (last[cur] && last[cur].u === here ? last[cur].l : '') };
        save(last);
        render();
        if (label) { document.title = label + ' — 작성 모드'; }
    }
    // 지금 화면과 이어진 건 — 프로젝트 화면이면 그 의뢰자, 의뢰자 화면이면 그 의뢰자의 프로젝트들
    function relatedClientId() { return window.WM_CLIENT_ID || null; }
    function relatedProjects() {
        try {
            if (typeof CLIENT_FOCUS_ID === 'undefined' || !CLIENT_FOCUS_ID || typeof openClientTabs === 'undefined') { return []; }
            const t = openClientTabs.find(x => x.id === CLIENT_FOCUS_ID);
            return [...((t && t.data && t.data.projects) || [])].sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));
        } catch (e) { return []; }
    }
    function openFind(kind) {
        const btn = document.querySelector(`.wmtab[data-kind="${kind}"] .wmtab-find`);
        if (btn) { wmNavPop({ stopPropagation() {}, currentTarget: btn }, kind); }
    }
    window.wmGo = function (e, kind) {
        e.stopPropagation(); // 이 클릭이 문서까지 올라가 방금 연 찾기 창을 닫지 않게
        if (kind === cur && kind !== 'calendar') { openFind(kind); return; }
        const last = load();
        if (kind === 'calendar') { location.href = (last.calendar && last.calendar.u) || '/calendar/write'; return; }
        if (kind === 'clients' && cur === 'projects' && relatedClientId()) { location.href = `/clients/${relatedClientId()}/write`; return; }
        if (kind === 'projects' && cur === 'clients' && relatedProjects().length) { openFind('projects'); return; }
        if (last[kind] && last[kind].u) { location.href = last[kind].u; return; }
        openFind(kind); // 아직 연 적 없으면 찾기부터
    };
    remember(location.pathname === '/clients/create/write' ? '신규 등록' : (cur === 'calendar' ? '' : ''));
    // 화면이 그려진 뒤 이름을 읽어 탭 이름을 채운다
    let tries = (location.pathname === '/clients/create/write' || cur === 'calendar') ? 99 : 0;
    const timer = setInterval(() => {
        const name = (document.querySelector('.client-pane.active .detail-name')?.firstChild?.textContent
            || document.getElementById('projectNameDisplay')?.textContent || '').trim();
        if (name || ++tries > 20) { clearInterval(timer); if (name) { remember(name); } }
    }, 250);
})();
</script>
</body>
</html>
