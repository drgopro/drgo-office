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
        .wmnav { position:sticky; top:0; z-index:9000; display:flex; align-items:center; gap:6px; height:46px; padding:0 12px; background:#1f2b40; color:#dfe6f2; box-shadow:0 1px 0 rgba(0,0,0,.15); }
        .wmnav-title { font-size:13px; font-weight:800; color:#fff; white-space:nowrap; margin-right:4px; }
        .wmnav button, .wmnav a { flex-shrink:0; height:30px; padding:0 10px; border-radius:8px; border:1px solid rgba(255,255,255,.18); background:transparent; color:#dfe6f2; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
        .wmnav button:hover, .wmnav a:hover { background:rgba(255,255,255,.08); }
        .wmnav .arrow { width:30px; padding:0; justify-content:center; font-size:15px; }
        .wmnav .sep { width:1px; height:20px; background:rgba(255,255,255,.18); flex-shrink:0; margin:0 4px; }
        .wmnav-trail { display:flex; gap:6px; overflow-x:auto; scrollbar-width:none; flex:1; min-width:0; }
        .wmnav-trail a { max-width:220px; }
        .wmnav-trail a span { overflow:hidden; text-overflow:ellipsis; }
        .wmnav-trail a small { font-size:11px; font-weight:600; color:#93a3c2; }
        .wmnav-trail a.on { background:#fff; color:#1f2b40; border-color:#fff; }
        .wmnav-trail a.on small { color:#5f6b85; }
        @media (max-width: 640px) { .wmnav-title { display:none; } }
    </style>
</head>
<body style="margin:0; background:var(--bg, #f4f5f7);">
<nav class="wmnav" aria-label="작성 모드 이동">
    <span class="wmnav-title">작성 모드</span>
    <button type="button" class="arrow" onclick="history.back()" title="뒤로" aria-label="뒤로">‹</button>
    <button type="button" class="arrow" onclick="history.forward()" title="앞으로" aria-label="앞으로">›</button>
    <a href="/calendar/write" title="캘린더 열기">캘린더</a>
    <span class="sep"></span>
    <div class="wmnav-trail" id="wmTrail"></div>
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

    // 최근 연 화면 — 이 창에서 연 의뢰자·프로젝트·캘린더 (최근 8개, 창을 닫으면 초기화)
    const KEY = 'drgo_wm_trail';
    const here = location.pathname + location.search;
    const kindOf = p => p.startsWith('/clients') ? '의뢰자' : p.startsWith('/projects') ? '프로젝트' : p.startsWith('/calendar') ? '캘린더' : '';
    const keyOf = p => p.replace(/\?.*$/, '').replace(/^\/calendar\/write.*/, '/calendar/write');
    function load() { try { return JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
    function save(list) { try { sessionStorage.setItem(KEY, JSON.stringify(list.slice(0, 8))); } catch (e) {} }
    function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function render() {
        const box = document.getElementById('wmTrail');
        if (!box) { return; }
        box.innerHTML = load().map(t => `<a href="${esc(t.u)}" class="${keyOf(t.u) === keyOf(here) ? 'on' : ''}" title="${esc(t.k + ' ' + t.l)}"><small>${esc(t.k)}</small><span>${esc(t.l)}</span></a>`).join('');
    }
    function remember(label) {
        const kind = kindOf(location.pathname);
        if (!kind) { return; }
        const list = load().filter(t => keyOf(t.u) !== keyOf(here));
        list.unshift({ u: here, k: kind, l: label || (kind === '캘린더' ? '전체 일정' : '불러오는 중') });
        save(list);
        render();
        if (label) { document.title = label + ' — 작성 모드'; }
    }
    remember('');
    // 화면이 그려진 뒤 이름을 읽어 목록 이름을 채운다
    let tries = 0;
    const timer = setInterval(() => {
        const name = (document.querySelector('.client-pane.active .detail-name')?.firstChild?.textContent
            || document.getElementById('projectNameDisplay')?.textContent
            || (location.pathname.startsWith('/calendar') ? '전체 일정' : '') || '').trim();
        if (name || ++tries > 20) { clearInterval(timer); if (name) { remember(name); } }
    }, 250);
})();
</script>
</body>
</html>
