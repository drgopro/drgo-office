<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
<script>
(function(){var t=localStorage.getItem('drgo_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();
</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111111">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icon-192.svg">
    <title>닥터고블린 오피스</title>
    <style>
        /* ── 다크 모드 (기본) ── */
        :root, [data-theme="dark"] {
            --bg: #111111;
            --surface: #1c1c1c;
            --surface2: #272727;
            --surface3: #333333;
            --border: #3a3a3a;
            --text: #f0ebe2;
            --text-muted: #a09890;
            --accent: #d4bc96;
            --accent2: #90bcd4;
            --red: #d48888;
            --blue: #8ab4c8;
            --green: #88d488;
            --gold: #c8b08a;
            --teal: #e8894a;
            --purple: #9b70c8;
            @php($__calCats = \App\Models\CalendarCategory::map())
            @foreach($__calCats as $__k => $__c)
            --chip-{{ $__k }}-bg: {{ $__c['color'] }}; --chip-{{ $__k }}-text: {{ $__c['text_color'] }};
            @endforeach
            --chip-single-bg: #303030;
            --header-h: 48px;
            --tab-h: 36px;
            --chrome-h: calc(var(--header-h) + var(--tab-h) + 2px);
            --full-h: 100vh;
        }
        [data-theme="light"] {
            --bg: #f4f5f7;
            --surface: #ffffff;
            --surface2: #eceef2;
            --surface3: #dfe2e8;
            --border: #b8bcc8;
            --text: #1a1e28;
            --text-muted: #5a6070;
            --accent: #3b5ea0;
            --accent2: #2e6a8a;
            --red: #c03838;
            --blue: #2e6a9a;
            --green: #248a38;
            --gold: #907030;
            --teal: #b85c18;
            --purple: #5c2e90;
            @foreach($__calCats as $__k => $__c)
            --chip-{{ $__k }}-bg: {{ $__c['color'] }}; --chip-{{ $__k }}-text: {{ $__c['text_color'] }};
            @endforeach
            --chip-single-bg: #e6e8ee;
        }

        /* ── 동적 카테고리별 chip 색상 (관리자가 추가한 신규 카테고리도 자동 적용) ──
           기존 calendar/index.blade.php 에 하드코딩된 기본 카테고리 셀렉터가 specificity로 우선하고,
           새로 추가된 카테고리는 아래 규칙으로 색이 입혀진다. */
        @foreach($__calCats as $__k => $__c)
        .event-chip.color-{{ $__k }} { background:{{ $__c['color'] }}38; border-left-color:{{ $__c['color'] }}; }
        .ch-chip.color-{{ $__k }} { background:{{ $__c['color'] }}38; border-left-color:{{ $__c['color'] }}; }
        @endforeach

        /* ── 라이트모드 글로벌 보정 ── */
        [data-theme="light"] .header { background:#fff; border-bottom-color:#c8ccd4; }
        [data-theme="light"] .logo { color:var(--accent); border-right-color:#c8ccd4; }
        [data-theme="light"] .nav a { color:#4a5060; }
        [data-theme="light"] .nav a:hover { color:var(--text); background:#e8eaef; }
        [data-theme="light"] .nav a.active { color:var(--accent); background:#e0e4ec; font-weight:600; }
        [data-theme="light"] .logout-btn { border-color:#a0a8b4; color:#4a5060; }
        [data-theme="light"] .logout-btn:hover { border-color:var(--accent); color:var(--accent); }
        [data-theme="light"] .admin-link { border-color:#a0a8b4; color:#4a5060; }
        [data-theme="light"] .theme-toggle { border-color:#a0a8b4; color:#4a5060; }
        [data-theme="light"] .tab-bar-wrap { background:#e8eaef; border-bottom-color:#c8ccd4; }
        [data-theme="light"] .tab-item { color:#5a6070; }
        [data-theme="light"] .tab-item:hover { color:var(--text); background:#fff; }
        [data-theme="light"] .tab-item.active { color:var(--accent); background:#fff; border-color:#c8ccd4; }
        [data-theme="light"] .tab-add { color:#5a6070; }
        [data-theme="light"] .tab-menu { background:#fff; border-color:#c8ccd4; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        [data-theme="light"] .tab-menu-item { color:#4a5060; }
        [data-theme="light"] .tab-menu-item:hover { color:var(--text); background:#eceef2; }
        [data-theme="light"] .user-role { color:#6b7280; }

        * { margin:0; padding:0; box-sizing:border-box; }

        /* ── 스크롤바 ── */
        ::-webkit-scrollbar { width:8px; height:8px; }
        ::-webkit-scrollbar-track { background:var(--surface); }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; border:2px solid var(--surface); }
        ::-webkit-scrollbar-thumb:hover { background:var(--text-muted); }
        ::-webkit-scrollbar-corner { background:var(--surface); }
        * { scrollbar-width:thin; scrollbar-color:var(--border) var(--surface); }

        body { background:var(--bg); color:var(--text); font-family:-apple-system,sans-serif; min-height:100vh; display:flex; flex-direction:column; transition:background 0.2s, color 0.2s; }

        /* ── 상단 내비게이션 ── */
        .header { background:var(--surface); border-bottom:1px solid var(--border); padding:0 20px; padding-top:env(safe-area-inset-top, 0px); display:flex; justify-content:space-between; align-items:center; height:calc(var(--header-h) + env(safe-area-inset-top, 0px)); position:sticky; top:0; z-index:200; }
        .header-left { display:flex; align-items:center; gap:0; }
        .logo { font-size:13px; font-weight:700; color:var(--accent); letter-spacing:0.15em; text-decoration:none; padding:0 16px 0 0; margin-right:16px; border-right:1px solid var(--border); }

        .nav { display:flex; align-items:center; gap:2px; flex-wrap:nowrap; white-space:nowrap; }
        .nav a { text-decoration:none; color:var(--text-muted); font-size:13px; padding:6px 12px; border-radius:6px; transition:all 0.15s; white-space:nowrap; }
        .nav a:hover { color:var(--text); background:var(--surface2); }
        .nav a.active { color:var(--accent); background:var(--surface2); }

        .header-right { display:flex; align-items:center; gap:12px; font-size:12px; }
        .user-role { color:var(--text-muted); font-size:11px; }
        .logout-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 10px; border-radius:6px; font-size:11px; cursor:pointer; transition:all 0.15s; }
        .logout-btn:hover { border-color:var(--accent); color:var(--accent); }
        .admin-link { font-size:12px; color:var(--text-muted); text-decoration:none; padding:5px 10px; border:1px solid var(--border); border-radius:6px; transition:all 0.15s; }
        .admin-link:hover, .admin-link.active { border-color:var(--accent); color:var(--accent); }
        .theme-toggle { background:none; border:1px solid var(--border); color:var(--text-muted); width:32px; height:32px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; transition:all 0.15s; }
        .qr-fab { background:var(--accent); color:#1a1207; border:none; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; letter-spacing:0.05em; transition:all 0.15s; }
        .qr-fab:hover { filter:brightness(1.1); }
        [data-theme="light"] .qr-fab { color:#fff; }
        @keyframes gqrToastIn { from { opacity:0; transform:translateX(-50%) translateY(20px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }
        .theme-toggle:hover { border-color:var(--accent); color:var(--accent); }

        /* 햄버거 / 모바일전용 */
        .menu-toggle { display:none; background:none; border:none; color:var(--text); font-size:20px; cursor:pointer; padding:6px; }
        .nav-overlay { display:none; }
        .nav-mobile-only { display:none; }

        /* ── 화면이 좁아지면 네비 항목 간격/패딩 축소 (햄버거 전환 전 단계적 압축) ── */
        @media (max-width: 1280px) {
            .nav { gap:1px; }
            .nav a { font-size:12px; padding:5px 9px; }
        }
        @media (max-width: 1100px) {
            .nav a { font-size:12px; padding:5px 7px; }
            .logo { font-size:12px; padding-right:10px; margin-right:10px; letter-spacing:0.1em; }
            .header-right { gap:6px; }
            .header-right .user-role { display:none; }
        }
        /* ── 햄버거 전환 ≤ 980px ── */
        @media (max-width: 980px) {
            .menu-toggle { display:flex; align-items:center; justify-content:center; min-width:40px; min-height:40px; }
            .nav { display:none; position:fixed; top:var(--header-h); left:0; right:0; bottom:0; background:var(--surface); flex-direction:column; padding:12px; gap:2px; z-index:199; overflow-y:auto; }
            .nav.open { display:flex; }
            .nav a { font-size:14px; padding:11px 16px; border-radius:8px; min-height:42px; display:flex; align-items:center; }
            .nav-overlay { display:none; position:fixed; inset:0; top:var(--header-h); background:rgba(0,0,0,0.5); z-index:198; }
            .nav-overlay.open { display:block; }
            .nav-mobile-only { display:none; border-top:1px solid var(--border); margin-top:8px; padding-top:12px; }
            .nav.open .nav-mobile-only { display:block; }
            .nav-mobile-only a, .nav-mobile-only span { display:block; font-size:13px; padding:8px 16px; color:var(--text-muted); text-decoration:none; border-radius:8px; }
            .nav-mobile-only a:hover { color:var(--accent); background:var(--surface2); }
            .nav-mobile-only .mobile-user { font-size:12px; color:var(--text-muted); padding:8px 16px; }
            .header-right .admin-link { display:none; }
        }

        /* ── 탭 바 ── */
        .tab-bar-wrap { background:var(--surface2); border-bottom:1px solid var(--border); display:flex; align-items:center; height:var(--tab-h); padding:0 16px; position:sticky; top:var(--header-h); z-index:190; }
        .tab-strip { display:flex; align-items:center; flex:1; overflow-x:auto; gap:1px; scrollbar-width:none; }
        .tab-strip::-webkit-scrollbar { display:none; }

        .tab-item { display:flex; align-items:center; gap:5px; padding:4px 10px; font-size:12px; cursor:pointer; color:var(--text-muted); background:transparent; border:none; white-space:nowrap; transition:all 0.12s; flex-shrink:0; border-radius:5px 5px 0 0; border:1px solid transparent; border-bottom:none; }
        .tab-item:hover { color:var(--text); background:var(--surface); }
        .tab-item.active { color:var(--accent); background:var(--surface); border-color:var(--border); font-weight:600; position:relative; }
        .tab-item.active::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:1px; background:var(--surface); }
        .tab-item .tab-icon { font-size:11px; }
        .tab-item .tab-close { display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:3px; font-size:9px; opacity:0; transition:opacity 0.12s; margin-left:2px; }
        .tab-item.tab-dragging { opacity:0.4; cursor:grabbing; }
        .tab-item.tab-drag-over { box-shadow:inset 2px 0 0 var(--accent); }
        .tab-item:hover .tab-close { opacity:0.5; }
        .tab-item .tab-close:hover { opacity:1; background:var(--border); }

        .tab-add { display:flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:5px; border:none; background:none; color:var(--text-muted); font-size:14px; cursor:pointer; flex-shrink:0; margin-left:4px; transition:all 0.12s; position:relative; }
        .tab-add:hover { color:var(--accent); background:var(--surface); }

        .tab-menu { display:none; position:absolute; top:100%; left:0; margin-top:4px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px; min-width:130px; z-index:300; box-shadow:0 4px 12px rgba(0,0,0,0.3); }
        .tab-menu.open { display:block; }
        .tab-menu-item { display:flex; align-items:center; gap:7px; padding:6px 10px; border-radius:5px; font-size:12px; color:var(--text-muted); cursor:pointer; border:none; background:none; width:100%; text-align:left; }
        .tab-menu-item:hover { color:var(--text); background:var(--surface2); }

        /* ── 콘텐츠 영역 ── */
        .main { flex:1; position:relative; }
        .tab-pane { display:none; height:100%; }
        .tab-pane.active { display:block; }
        .tab-loading { display:flex; align-items:center; justify-content:center; height:200px; color:var(--text-muted); font-size:13px; }

        /* ── 모바일 ── */
        @media (max-width: 768px) {
            :root { --header-h:44px; --tab-h:32px; }
            .header { padding:0 12px; height:var(--header-h); }
            .logo { font-size:12px; padding-right:10px; margin-right:10px; }
            .header-right .user-role, .header-right .admin-link { display:none; }
            .qr-fab { position:fixed; bottom:24px; right:16px; z-index:210; padding:12px 18px; font-size:13px; border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,0.4); }
            .header-right { gap:4px; }

            .menu-toggle { display:flex; align-items:center; justify-content:center; min-width:44px; min-height:44px; }
            .theme-toggle { width:44px; height:44px; font-size:18px; }
            .logout-btn { min-height:44px; padding:8px 12px; }
            .nav { display:none; position:fixed; top:var(--header-h); left:0; right:0; bottom:0; background:var(--surface); flex-direction:column; padding:12px; gap:2px; z-index:199; overflow-y:auto; }
            .nav.open { display:flex; }
            .nav a { font-size:15px; padding:12px 16px; border-radius:8px; min-height:44px; display:flex; align-items:center; }
            .nav-overlay { display:none; position:fixed; inset:0; top:var(--header-h); background:rgba(0,0,0,0.5); z-index:198; }
            .nav-overlay.open { display:block; }
            .nav-mobile-only { display:none; border-top:1px solid var(--border); margin-top:8px; padding-top:12px; }
            .nav.open .nav-mobile-only { display:block; }
            .nav-mobile-only a, .nav-mobile-only span { display:block; font-size:13px; padding:8px 16px; color:var(--text-muted); text-decoration:none; border-radius:8px; }
            .nav-mobile-only a:hover { color:var(--accent); background:var(--surface2); }
            .nav-mobile-only .mobile-user { font-size:12px; color:var(--text-muted); padding:8px 16px; }

            .tab-bar-wrap { top:var(--header-h); height:var(--tab-h); padding:0 8px; }
            .tab-item { font-size:11px; padding:6px 10px; }
            .tab-item .tab-close { opacity:0.5; position:relative; }
            .tab-item .tab-close::before { content:''; position:absolute; top:-10px; right:-8px; bottom:-10px; left:-8px; }

            .page-wrap { padding:16px !important; }
            .page-header { flex-direction:column; align-items:flex-start !important; gap:10px; }
            .info-grid { grid-template-columns:1fr !important; }
            .info-card.full { grid-column:1 !important; }
            .data-card { overflow-x:auto; -webkit-overflow-scrolling:touch; }
            .data-table { min-width:600px; }
            .data-table th, .data-table td { padding:10px 10px; font-size:12px; white-space:nowrap; }
            .modal { width:95vw !important; max-width:95vw !important; padding:16px !important; }
            .field-row, .field-row-3 { grid-template-columns:1fr !important; }
            .tab-bar { flex-wrap:wrap; }
            .tab-btn { font-size:12px; padding:8px 4px; min-width:0; }
            .toolbar { flex-direction:column; align-items:stretch; }
            .toolbar input[type="text"] { width:100% !important; }
            .doc-grid { gap:8px; }
            .doc-thumb-card { width:90px; }
            .doc-thumb-card .thumb-img { width:90px; height:90px; }
            .album-nav { width:50px; height:120px; }
            .album-nav .nav-circle { width:36px; height:36px; }
            .album-media { max-width:95vw !important; max-height:70vh !important; }
            .album-zoom-controls { bottom:12px; }
        }
        @media (max-width: 480px) {
            .tab-bar { border-radius:8px; padding:3px; }
            .tab-btn { font-size:11px; padding:7px 2px; }
            .data-table { min-width:500px; }
        }
    </style>
    <style>
        /* ── 글로벌 페이지네이션 (vendor.pagination.drgo) ── */
        .drgo-pager { display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap; justify-content:center; }
        .drgo-pager-btn { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 10px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); font-size:12px; font-weight:500; text-decoration:none; line-height:1; cursor:pointer; transition:all 0.15s; box-sizing:border-box; }
        .drgo-pager-btn:hover:not(.disabled):not(.active) { border-color:var(--accent); color:var(--accent); }
        .drgo-pager-btn.active { background:var(--accent); color:#1a1207; border-color:var(--accent); font-weight:700; cursor:default; }
        [data-theme="light"] .drgo-pager-btn.active { color:#fff; }
        .drgo-pager-btn.disabled { opacity:0.35; cursor:not-allowed; }
        .drgo-pager-dots { color:var(--text-muted); padding:0 4px; font-size:12px; }
        .drgo-pager-info { font-size:11px; color:var(--text-muted); margin-left:10px; font-family:"SF Mono",Menlo,monospace; white-space:nowrap; }
        @media (max-width: 600px) {
            .drgo-pager-info { display:none; }
            .drgo-pager-btn { min-width:28px; height:28px; padding:0 6px; font-size:11px; }
        }
    </style>
    <style>
        /* ── 모달 최소화 도크 ── */
        .drgo-modal-dock { position:fixed; bottom:16px; right:16px; display:flex; flex-direction:column-reverse; gap:8px; z-index:9990; pointer-events:none; }
        .drgo-modal-chip { pointer-events:auto; display:flex; align-items:center; gap:8px; padding:8px 12px; background:var(--surface); border:1px solid var(--accent); border-radius:24px; box-shadow:0 4px 14px rgba(0,0,0,0.3); cursor:pointer; max-width:280px; transition:transform 0.12s, box-shadow 0.12s; }
        .drgo-modal-chip:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,0.4); }
        .drgo-modal-chip-icon { font-size:14px; flex-shrink:0; }
        .drgo-modal-chip-title { font-size:12px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; }
        .drgo-modal-chip-close { background:none; border:none; color:var(--text-muted); cursor:pointer; padding:2px 6px; font-size:14px; line-height:1; flex-shrink:0; border-radius:50%; }
        .drgo-modal-chip-close:hover { background:var(--surface2); color:var(--red); }
        [data-theme="light"] .drgo-modal-chip { background:#fff; }

        /* iframe 내부에서는 내비/탭바 숨김 */
        body.in-iframe .header, body.in-iframe .tab-bar-wrap { display:none !important; }
        body.in-iframe .main { height:var(--full-h, 100vh); }
        @supports (height: 100dvh) {
            :root { --full-h: 100dvh; }
            .tab-pane iframe { height: calc(100dvh - var(--chrome-h, 86px)) !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="drgo-modal-dock" id="drgoModalDock" aria-label="최소화된 모달"></div>

<script>
if (window !== window.top) document.body.classList.add('in-iframe');
window.CALENDAR_CATEGORIES = @json($__calCats);

// ── 모달 최소화 도크 (tab-content layout과 동일 API) ──
window.drgoModalMinimize = function(overlayEl, title, icon) {
    if (!overlayEl) return;
    const id = overlayEl.id || ('drgo_modal_' + Date.now());
    if (!overlayEl.id) overlayEl.id = id;
    if (overlayEl.dataset.minimized === '1') return;
    const display = overlayEl.style.display || getComputedStyle(overlayEl).display;
    overlayEl.dataset.prevDisplay = display === 'none' ? 'flex' : display;
    overlayEl.dataset.minimized = '1';
    overlayEl.style.display = 'none';
    const dock = document.getElementById('drgoModalDock');
    if (!dock) return;
    if (dock.querySelector(`[data-target="${id}"]`)) return;
    const chip = document.createElement('div');
    chip.className = 'drgo-modal-chip';
    chip.dataset.target = id;
    chip.title = '클릭하여 복원';
    chip.innerHTML = `
        <span class="drgo-modal-chip-icon">${icon || '🗂'}</span>
        <span class="drgo-modal-chip-title">${(title || '모달').replace(/[<>"']/g, c => ({'<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</span>
        <button class="drgo-modal-chip-close" title="닫기" aria-label="닫기">✕</button>
    `;
    chip.addEventListener('click', e => {
        if (e.target.closest('.drgo-modal-chip-close')) drgoModalCloseFromChip(id);
        else drgoModalRestore(id);
    });
    dock.appendChild(chip);
};
window.drgoModalRestore = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = el.dataset.prevDisplay || 'flex';
    delete el.dataset.minimized;
    const chip = document.querySelector(`#drgoModalDock [data-target="${id}"]`);
    if (chip) chip.remove();
};
window.drgoModalCloseFromChip = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'none';
    delete el.dataset.minimized;
    delete el.dataset.prevDisplay;
    const chip = document.querySelector(`#drgoModalDock [data-target="${id}"]`);
    if (chip) chip.remove();
    if (typeof el.dataset.closeHandler === 'string' && typeof window[el.dataset.closeHandler] === 'function') {
        try { window[el.dataset.closeHandler](); } catch(e) {}
    }
};

// 부모(최상위) 탭 시스템으로 라우팅 — iframe 중첩 방지
window.openTopTab = function(type, url, title) {
    try {
        let w = window;
        for (let i = 0; i < 5 && w !== w.parent; i++) {
            if (w.parent && w.parent.drgoTabs && typeof w.parent.drgoTabs.openNav === 'function') {
                return w.parent.drgoTabs.openNav(type, url, title);
            }
            w = w.parent;
        }
    } catch (e) { /* cross-origin 등 */ }
    if (window.top && window.top !== window) {
        try { window.top.location.href = url; return; } catch (e) {}
    }
    window.location.href = url;
};
</script>

{{-- ── 상단 내비게이션 ── --}}
<div class="header">
    <div class="header-left">
        <a href="/" class="logo">DRGO</a>
        <button class="menu-toggle" id="menuToggle" onclick="toggleNav()">☰</button>
        <nav class="nav" id="mainNav">
            @if(!Auth::user()->isGuest())
                <a href="/" class="{{ request()->is('/') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('dashboard','/');">대시보드</a>
            @endif
            <a href="/calendar" class="{{ request()->is('calendar*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('calendar','/calendar');">캘린더</a>
            @if(Auth::user()->hasPermission('clients.view'))
                <a href="/clients" class="{{ request()->is('clients*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('clients','/clients');">의뢰자</a>
            @endif
            @if(Auth::user()->hasPermission('projects.view'))
                <a href="/projects" class="{{ request()->is('projects*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('projects','/projects');">프로젝트</a>
            @endif
            @if(Auth::user()->hasPermission('estimates.view'))
                <a href="/estimates" class="{{ request()->is('estimates*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('estimates','/estimates');">견적서</a>
            @endif
            @if(Auth::user()->hasPermission('inventory.view'))
                <a href="/inventory" class="{{ request()->is('inventory*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('inventory','/inventory');">재고</a>
            @endif
            <a href="/wiki" class="{{ request()->is('wiki*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('wiki','/wiki');">위키</a>
            @if(Auth::user()->hasPermission('inventory.view'))
                <a href="/rental-equipment" class="{{ request()->is('rental-equipment*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('rental','/rental-equipment');">장비 위치</a>
            @endif
            @if(Auth::user()->hasPermission('clients.view'))
                <a href="/rental-contracts" class="{{ request()->is('rental-contracts*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('rental-contracts','/rental-contracts');">렌탈</a>
                <a href="/broadcast-room" class="{{ request()->is('broadcast-room*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('broadcast-room','/broadcast-room');">방송룸</a>
            @endif
            @if(in_array(Auth::user()->role, ['master','admin','member']))
                <a href="/marketing-report" class="{{ request()->is('marketing-report*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('marketing-report','/marketing-report');">📊 통계</a>
            @endif
            <div class="nav-mobile-only">
                @if(Auth::user()->isAdmin())
                    <a href="#" onclick="event.preventDefault(); drgoTabs.openNav('admin','/admin');">관리</a>
                @endif
                <a href="#" onclick="event.preventDefault(); drgoTabs.openNav('profile','/profile');">마이페이지</a>
                <span class="mobile-user">{{ Auth::user()->display_name }} ({{ Auth::user()->role }})</span>
            </div>
        </nav>
        <div class="nav-overlay" id="navOverlay" onclick="toggleNav()"></div>
    </div>
    <div class="header-right">
        @if(Auth::user()->hasPermission('inventory.edit'))
            <button class="qr-fab" onclick="openGlobalQrScan()" title="QR 스캔">QR</button>
        @endif
        @if(Auth::user()->isAdmin())
            <a href="#" class="admin-link {{ request()->is('admin*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('admin','/admin');">관리</a>
        @endif
        <button class="theme-toggle" id="themeToggle" title="다크/라이트 모드">🌙</button>
        <a href="#" class="admin-link {{ request()->is('profile*') ? 'active' : '' }}" onclick="event.preventDefault(); drgoTabs.openNav('profile','/profile');">{{ Auth::user()->display_name }}</a>
        <span class="user-role">{{ Auth::user()->role }}</span>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="logout-btn">로그아웃</button>
        </form>
    </div>
</div>

{{-- ── 탭 바 ── --}}
<div class="tab-bar-wrap">
    <div class="tab-strip" id="tabStrip"></div>
    <div style="position:relative;">
        <button class="tab-add" id="tabAddBtn" title="새 탭">+</button>
        <div class="tab-menu" id="tabMenu">
            @if(!Auth::user()->isGuest())
                <button class="tab-menu-item" onclick="drgoTabs.openNav('dashboard','/'); drgoTabs.closeMenu();">📊 대시보드</button>
            @endif
            <button class="tab-menu-item" onclick="drgoTabs.openNav('calendar','/calendar'); drgoTabs.closeMenu();">📅 캘린더</button>
            @if(Auth::user()->hasPermission('clients.view'))
                <button class="tab-menu-item" onclick="drgoTabs.openNav('clients','/clients'); drgoTabs.closeMenu();">👤 의뢰자</button>
            @endif
            @if(Auth::user()->hasPermission('projects.view'))
                <button class="tab-menu-item" onclick="drgoTabs.openNav('projects','/projects'); drgoTabs.closeMenu();">📁 프로젝트</button>
            @endif
            @if(Auth::user()->hasPermission('estimates.view'))
                <button class="tab-menu-item" onclick="drgoTabs.openNav('estimates','/estimates'); drgoTabs.closeMenu();">📝 견적서</button>
            @endif
            @if(Auth::user()->hasPermission('inventory.view'))
                <button class="tab-menu-item" onclick="drgoTabs.openNav('inventory','/inventory'); drgoTabs.closeMenu();">📦 재고</button>
            @endif
            <button class="tab-menu-item" onclick="drgoTabs.openNav('wiki','/wiki'); drgoTabs.closeMenu();">📖 위키</button>
            @if(Auth::user()->hasPermission('inventory.view'))
                <button class="tab-menu-item" onclick="drgoTabs.openNav('rental','/rental-equipment'); drgoTabs.closeMenu();">📷 장비 위치</button>
            @endif
            @if(Auth::user()->hasPermission('clients.view'))
                <button class="tab-menu-item" onclick="drgoTabs.openNav('rental-contracts','/rental-contracts'); drgoTabs.closeMenu();">🏠 렌탈</button>
                <button class="tab-menu-item" onclick="drgoTabs.openNav('broadcast-room','/broadcast-room'); drgoTabs.closeMenu();">🎙 방송룸</button>
            @endif
            @if(Auth::user()->isAdmin())
                <button class="tab-menu-item" onclick="drgoTabs.openNav('admin','/admin'); drgoTabs.closeMenu();">⚙️ 관리</button>
            @endif
        </div>
    </div>
</div>

{{-- ── 콘텐츠 영역 ── --}}
<div class="main" id="tabContent">
    <div class="tab-pane active" id="pane-initial">
        @yield('content')
    </div>
</div>

<script>
// ── 테마 ──
const savedTheme = localStorage.getItem('drgo_theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);
document.getElementById('themeToggle').textContent = savedTheme === 'dark' ? '🌙' : '☀️';
document.getElementById('themeToggle').addEventListener('click', function() {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('drgo_theme', next);
    this.textContent = next === 'dark' ? '🌙' : '☀️';
    // iframe 내부에도 테마 전파
    document.querySelectorAll('iframe').forEach(iframe => {
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.documentElement.setAttribute('data-theme', next);
        } catch(e) {}
    });
});

// ── 모바일 네비 ──
function toggleNav() {
    document.getElementById('mainNav').classList.toggle('open');
    document.getElementById('navOverlay').classList.toggle('open');
    const btn = document.getElementById('menuToggle');
    btn.textContent = document.getElementById('mainNav').classList.contains('open') ? '✕' : '☰';
}

// ── 탭 추가 메뉴 ──
document.getElementById('tabAddBtn').addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('tabMenu').classList.toggle('open');
});
document.addEventListener('click', () => document.getElementById('tabMenu').classList.remove('open'));

// ── 탭 시스템 ──
// window.drgoTabs로도 노출 (iframe → parent 접근용)
window.drgoTabs = {
    tabs: [],
    activeId: null,

    ICONS: { dashboard:'📊', calendar:'📅', clients:'👤', projects:'📁', inventory:'📦', estimates:'📝', wiki:'📖', rental:'📷', 'rental-contracts':'🏠', 'broadcast-room':'🎙', 'marketing-report':'📊', admin:'⚙️', profile:'👤' },
    LABELS: { dashboard:'대시보드', calendar:'캘린더', clients:'의뢰자', projects:'프로젝트', inventory:'재고', estimates:'견적서', wiki:'위키', rental:'장비 위치', 'rental-contracts':'렌탈', 'broadcast-room':'방송룸', 'marketing-report':'통계', admin:'관리', profile:'마이페이지' },

    init() {
        // iframe 내부에서는 탭 시스템 비활성화
        if (window !== window.top) return;

        const saved = this._restore();
        if (saved) return;

        const path = window.location.pathname;
        const type = this._typeFromPath(path);
        this.tabs = [{ id: 'initial', type, url: path, loaded: true }];
        this.activeId = 'initial';
        this.render();
        this._save();
    },

    /**
     * 멀티 인스턴스를 허용할 URL 패턴 (각 detail이 독립된 탭으로 열림)
     */
    _isMultiInstance(type, url) {
        // /projects/{id} 형태의 프로젝트 상세는 각각 별도 탭
        if (type === 'projects' && /^\/projects\/\d+/.test(url)) return true;
        return false;
    },

    openNav(type, url, title) {
        document.getElementById('mainNav').classList.remove('open');
        document.getElementById('navOverlay').classList.remove('open');

        // 1) URL이 정확히 일치하는 탭이 있으면 활성화만 (재로드 없음)
        const existing = this.tabs.find(t => t.url === url);
        if (existing) {
            if (title) { existing.title = title; this.render(); this._save(); }
            this.activate(existing.id);
            return;
        }

        const multi = this._isMultiInstance(type, url);

        // 2) 같은 type의 탭이 이미 있으면 그 탭을 재사용해 URL만 갱신
        //    (멀티 인스턴스 URL은 예외 — 새 탭 생성)
        if (!multi) {
            const sameType = this.tabs.find(t => t.type === type);
            if (sameType) {
                sameType.url = url;
                if (title) sameType.title = title;
                const pane = document.getElementById('pane-' + sameType.id);
                const iframe = pane?.querySelector('iframe');
                if (iframe) {
                    iframe.src = url;
                } else if (pane) {
                    sameType.loaded = false;
                    this._load(sameType, pane);
                }
                this.activate(sameType.id);
                this._save();
                return;
            }
        }

        // 3) 새 탭 생성
        const id = 'tab-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6);
        this.tabs.push({ id, type, url, title: title || null, loaded: false });
        this.activate(id);
    },

    /**
     * iframe 내부에서 자신의 탭 타이틀을 갱신할 때 호출
     */
    setActiveTitle(title) {
        const tab = this.tabs.find(t => t.id === this.activeId);
        if (tab) {
            tab.title = title;
            this.render();
            this._save();
        }
    },

    openClientDetail(clientId) {
        const baseUrl = '/clients';
        const existing = this.tabs.find(t => t.url === baseUrl);
        if (existing) {
            this.activate(existing.id);
            const pane = document.getElementById('pane-' + existing.id);
            if (pane) {
                const iframe = pane.querySelector('iframe');
                if (iframe && iframe.contentWindow && iframe.contentWindow.openClient) {
                    iframe.contentWindow.openClient(clientId);
                }
            }
        } else {
            const url = baseUrl + '?open=' + clientId;
            const id = 'tab-' + Date.now();
            this.tabs.push({ id, type: 'clients', url, loaded: false });
            this.activate(id);
        }
    },

    activate(id) {
        this.activeId = id;
        const tab = this.tabs.find(t => t.id === id);
        if (!tab) return;

        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        let pane = document.getElementById('pane-' + id);
        if (!pane) {
            pane = document.createElement('div');
            pane.id = 'pane-' + id;
            pane.className = 'tab-pane';
            pane.innerHTML = '<div class="tab-loading">로딩 중...</div>';
            document.getElementById('tabContent').appendChild(pane);
        }
        pane.classList.add('active');

        if (!tab.loaded) this._load(tab, pane);
        this.render();
        this._save();
        this._updateNav(tab.type);
    },

    close(id) {
        if (this.tabs.length <= 1) return;
        const idx = this.tabs.findIndex(t => t.id === id);
        if (idx === -1) return;

        this.tabs.splice(idx, 1);
        const pane = document.getElementById('pane-' + id);
        if (pane) pane.remove();

        if (this.activeId === id) {
            const next = this.tabs[Math.min(idx, this.tabs.length - 1)];
            this.activate(next.id);
        } else {
            this.render();
            this._save();
        }
    },

    _load(tab, pane) {
        const iframe = document.createElement('iframe');
        iframe.src = tab.url;
        iframe.style.cssText = 'width:100%;height:calc(100vh - var(--chrome-h, 86px));border:none;display:block;';
        iframe.onload = () => { tab.loaded = true; };
        iframe.onerror = () => {
            pane.innerHTML = '<div class="tab-loading" style="color:var(--red)">로드 실패 — <a href="' + tab.url + '" style="color:var(--accent)">직접 열기</a></div>';
        };
        pane.innerHTML = '';
        pane.appendChild(iframe);
    },

    render() {
        const strip = document.getElementById('tabStrip');
        strip.innerHTML = this.tabs.map(t => {
            const icon = this.ICONS[t.type] || '📄';
            const label = t.title || this.LABELS[t.type] || t.type;
            const cls = t.id === this.activeId ? 'active' : '';
            const close = this.tabs.length > 1
                ? `<span class="tab-close" onclick="event.stopPropagation(); drgoTabs.close('${t.id}')">✕</span>` : '';
            const titleAttr = t.title ? ` title="${String(t.title).replace(/"/g, '&quot;')}"` : '';
            return `<button class="tab-item ${cls}" draggable="true" data-tab-id="${t.id}"${titleAttr} onclick="drgoTabs.activate('${t.id}')"><span class="tab-icon">${icon}</span>${label}${close}</button>`;
        }).join('');
        this._bindTabDrag();
    },

    _dragId: null,
    _bindTabDrag() {
        const strip = document.getElementById('tabStrip');
        const items = strip.querySelectorAll('.tab-item');
        items.forEach(el => {
            el.addEventListener('dragstart', e => {
                this._dragId = el.dataset.tabId;
                el.classList.add('tab-dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', el.dataset.tabId); } catch (_) {}
            });
            el.addEventListener('dragend', () => {
                el.classList.remove('tab-dragging');
                strip.querySelectorAll('.tab-drag-over').forEach(x => x.classList.remove('tab-drag-over'));
                this._dragId = null;
            });
            el.addEventListener('dragover', e => {
                if (!this._dragId || el.dataset.tabId === this._dragId) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                el.classList.add('tab-drag-over');
            });
            el.addEventListener('dragleave', () => el.classList.remove('tab-drag-over'));
            el.addEventListener('drop', e => {
                e.preventDefault();
                el.classList.remove('tab-drag-over');
                const fromId = this._dragId;
                const toId = el.dataset.tabId;
                if (!fromId || fromId === toId) return;
                const fromIdx = this.tabs.findIndex(t => t.id === fromId);
                const toIdx = this.tabs.findIndex(t => t.id === toId);
                if (fromIdx < 0 || toIdx < 0) return;
                const [moved] = this.tabs.splice(fromIdx, 1);
                this.tabs.splice(toIdx, 0, moved);
                this.render();
                this._save();
            });
        });
    },

    closeMenu() { document.getElementById('tabMenu').classList.remove('open'); },

    _updateNav(type) {
        const NAV_MAP = { dashboard:'/', calendar:'/calendar', clients:'/clients', projects:'/projects', inventory:'/inventory', estimates:'/estimates', wiki:'/wiki', admin:'/admin', profile:'/profile' };
        document.querySelectorAll('#mainNav > a').forEach(a => {
            const href = a.getAttribute('href');
            a.classList.toggle('active', href === NAV_MAP[type]);
        });
    },

    _typeFromPath(p) {
        if (p === '/') return 'dashboard';
        if (p.startsWith('/calendar')) return 'calendar';
        if (p.startsWith('/clients')) return 'clients';
        if (p.startsWith('/projects')) return 'projects';
        if (p.startsWith('/inventory')) return 'inventory';
        if (p.startsWith('/estimates')) return 'estimates';
        if (p.startsWith('/wiki')) return 'wiki';
        if (p.startsWith('/rental-equipment')) return 'rental';
        if (p.startsWith('/rental-contracts')) return 'rental-contracts';
        if (p.startsWith('/broadcast-room')) return 'broadcast-room';
        if (p.startsWith('/marketing-report')) return 'marketing-report';
        if (p.startsWith('/admin')) return 'admin';
        if (p.startsWith('/profile')) return 'profile';
        return 'page';
    },

    _save() {
        const data = {
            tabs: this.tabs.map(t => ({ type: t.type, url: t.url, title: t.title || null })),
            activeUrl: this.tabs.find(t => t.id === this.activeId)?.url
        };
        sessionStorage.setItem('drgo_tabs', JSON.stringify(data));
    },

    _restore() {
        try {
            const raw = sessionStorage.getItem('drgo_tabs');
            if (!raw) return false;
            const data = JSON.parse(raw);
            if (!data.tabs || !data.tabs.length) return false;

            const currentPath = window.location.pathname;

            // 현재 서버 렌더링된 페이지를 initial 탭으로
            // 저장된 탭 중 현재 페이지와 같은 것을 찾아 initial로 매핑
            this.tabs = [];
            let initialSet = false;

            data.tabs.forEach((t, i) => {
                if (t.url === currentPath && !initialSet) {
                    this.tabs.push({ id: 'initial', type: t.type, url: t.url, title: t.title || null, loaded: true });
                    initialSet = true;
                } else {
                    this.tabs.push({ id: 'tab-r-' + i, type: t.type, url: t.url, title: t.title || null, loaded: false });
                }
            });

            // 현재 페이지가 저장된 탭에 없으면 첫 번째로 추가
            if (!initialSet) {
                const type = this._typeFromPath(currentPath);
                this.tabs.unshift({ id: 'initial', type, url: currentPath, loaded: true });
            }

            // 활성 탭 결정
            const activeTab = this.tabs.find(t => t.url === data.activeUrl);
            this.activeId = activeTab ? activeTab.id : 'initial';

            // initial이 아닌 활성 탭이면 해당 탭 활성화
            if (this.activeId !== 'initial') {
                this.render();
                this.activate(this.activeId);
            } else {
                this.render();
            }

            return true;
        } catch { return false; }
    }
};

drgoTabs.init();
</script>

{{-- ── 활동 로그 모달 (글로벌) ── --}}
<div id="activityLogOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9000;backdrop-filter:blur(3px);align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:100%;max-width:580px;max-height:80vh;display:flex;flex-direction:column;animation:modalIn 0.2s ease;">
        <div style="padding:16px 20px 12px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);flex-shrink:0;">
            <div style="font-size:15px;font-weight:600;" id="activityLogTitle">수정 로그</div>
            <button onclick="document.getElementById('activityLogOverlay').style.display='none'" style="background:none;border:1px solid var(--border);color:var(--text-muted);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <div id="activityLogBody" style="flex:1;overflow-y:auto;padding:12px 20px 20px;">
            <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">로딩 중...</div>
        </div>
    </div>
</div>

<script>
async function openActivityLog(type, id, title) {
    const overlay = document.getElementById('activityLogOverlay');
    const body = document.getElementById('activityLogBody');
    const titleEl = document.getElementById('activityLogTitle');
    titleEl.textContent = (title || '수정 로그');
    body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">로딩 중...</div>';
    overlay.style.display = 'flex';

    const ACTION_L = {create:'생성',update:'수정',delete:'삭제'};
    const ACTION_C = {create:'#22c55e',update:'var(--accent)',delete:'#ef4444'};

    try {
        const res = await fetch(`/api/activity-logs?type=${type}&id=${id}&limit=100`);
        if (!res.ok) throw new Error();
        const logs = await res.json();
        if (!logs.length) { body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px;">수정 이력이 없습니다.</div>'; return; }
        body.innerHTML = logs.map(log => {
            let changesHtml = '';
            if (log.changes && Object.keys(log.changes).length) {
                changesHtml = Object.entries(log.changes).map(([key, val]) => {
                    const oldV = typeof val.old === 'object' ? JSON.stringify(val.old) : (val.old ?? '—');
                    const newV = typeof val.new === 'object' ? JSON.stringify(val.new) : (val.new ?? '—');
                    return `<div style="margin:4px 0 4px 12px;font-size:12px;">
                        <span style="color:var(--text-muted);">${key}:</span>
                        <span style="text-decoration:line-through;color:var(--red);opacity:0.7;">${oldV}</span>
                        → <span style="color:var(--green);">${newV}</span>
                    </div>`;
                }).join('');
            }
            return `<div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;color:${ACTION_C[log.action]||'var(--text-muted)'};border:1px solid;opacity:0.8;">${ACTION_L[log.action]||log.action}</span>
                    <span style="font-size:12px;font-weight:600;">${log.user}</span>
                    <span style="font-size:10px;color:var(--text-muted);margin-left:auto;">${log.created_at}</span>
                </div>
                ${log.summary ? '<div style="font-size:12px;color:var(--text-muted);margin-top:4px;">'+log.summary+'</div>' : ''}
                ${changesHtml}
            </div>`;
        }).join('');
    } catch(e) {
        body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--red);font-size:13px;">로드 실패</div>';
    }
}

// ── 엑셀 가져오기 공통 ──
function openExcelImportModal(type, typeName) {
    let overlay = document.getElementById('excelImportOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'excelImportOverlay';
        overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9000;backdrop-filter:blur(3px);align-items:center;justify-content:center;';
        overlay.onclick = e => { if (e.target === overlay) overlay.style.display = 'none'; };
        overlay.innerHTML = `<div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:100%;max-width:440px;padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;"><div style="font-size:16px;font-weight:700;" id="excelImportTitle">엑셀 가져오기</div><button onclick="document.getElementById('excelImportOverlay').style.display='none'" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button></div>
            <div style="margin-bottom:16px;"><a id="excelTemplateLink" href="#" style="font-size:12px;color:var(--accent);text-decoration:none;">📥 템플릿 다운로드 (.xlsx)</a><div style="font-size:11px;color:var(--text-muted);margin-top:4px;">템플릿을 다운로드하여 데이터를 입력한 후 업로드하세요.</div></div>
            <div style="margin-bottom:16px;"><input type="file" id="excelImportFile" accept=".xlsx,.xls,.csv" style="display:none;"><button onclick="document.getElementById('excelImportFile').click()" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px;color:var(--text);font-size:13px;cursor:pointer;width:100%;text-align:center;">📎 파일 선택 (.xlsx, .csv)</button><div id="excelFileName" style="font-size:12px;color:var(--accent);margin-top:6px;display:none;"></div></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;"><button onclick="document.getElementById('excelImportOverlay').style.display='none'" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">취소</button><button id="excelImportBtn" onclick="submitExcelImport()" style="background:var(--accent);color:#1a1207;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;" disabled>가져오기</button></div>
            <div id="excelImportResult" style="display:none;margin-top:16px;padding:12px;border-radius:8px;font-size:12px;"></div></div>`;
        document.body.appendChild(overlay);
        document.getElementById('excelImportFile').addEventListener('change', function() {
            const n=this.files[0]?.name, el=document.getElementById('excelFileName');
            if(n){el.textContent='📄 '+n;el.style.display='block';document.getElementById('excelImportBtn').disabled=false;}
            else{el.style.display='none';document.getElementById('excelImportBtn').disabled=true;}
        });
    }
    overlay.dataset.type=type;
    document.getElementById('excelImportTitle').textContent=typeName+' 엑셀 가져오기';
    document.getElementById('excelTemplateLink').href='/api/import/template/'+type;
    document.getElementById('excelImportFile').value='';
    document.getElementById('excelFileName').style.display='none';
    document.getElementById('excelImportBtn').disabled=true;
    document.getElementById('excelImportResult').style.display='none';
    overlay.style.display='flex';
}
async function submitExcelImport() {
    const overlay=document.getElementById('excelImportOverlay'), type=overlay.dataset.type;
    const file=document.getElementById('excelImportFile').files[0]; if(!file)return;
    const btn=document.getElementById('excelImportBtn'); btn.disabled=true; btn.textContent='처리 중...';
    const fd=new FormData(); fd.append('file',file);
    const csrf=document.querySelector('meta[name="csrf-token"]')?.content;
    try{
        const res=await fetch('/api/import/'+type,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
        const data=await res.json(); const r=document.getElementById('excelImportResult');
        if(res.ok){r.style.background='rgba(34,197,94,0.1)';r.style.color='var(--green)';let h='✅ '+data.message;if(data.errors?.length)h+='<br><br><span style="color:var(--red);">⚠ 오류:</span><br>'+data.errors.join('<br>');r.innerHTML=h;}
        else{r.style.background='rgba(239,68,68,0.1)';r.style.color='var(--red)';r.innerHTML='❌ '+(data.error||data.message||'가져오기 실패');}
        r.style.display='block';
    }catch(e){alert('오류가 발생했습니다.');}
    btn.disabled=false; btn.textContent='가져오기';
}
</script>

<script>if('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(()=>{});</script>

{{-- 글로벌 QR 스캔 모달 --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<div id="globalQrOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9500;backdrop-filter:blur(4px);flex-direction:column;align-items:center;justify-content:flex-start;padding:20px;overflow-y:auto;">
    <div style="width:100%;max-width:420px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <div style="font-size:18px;font-weight:700;color:#fff;">📷 QR 스캔</div>
            <button onclick="closeGlobalQrScan()" style="background:none;border:1px solid rgba(255,255,255,0.3);color:#fff;width:36px;height:36px;border-radius:8px;cursor:pointer;font-size:16px;">✕</button>
        </div>
        <div id="globalQrReader" style="width:100%;background:#000;border-radius:12px;overflow:hidden;min-height:280px;"></div>
        <div style="margin-top:10px;display:flex;gap:6px;">
            <input id="globalQrManual" placeholder="rental:ID 또는 staff:ID" style="flex:1;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:8px;padding:10px 12px;color:#fff;font-size:13px;outline:none;">
            <button onclick="globalQrHandle(document.getElementById('globalQrManual').value)" style="background:var(--accent);color:#1a1207;border:none;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">확인</button>
        </div>
        {{-- 스캔 상태 표시 --}}
        <div id="gqrStatus" style="margin-top:16px;padding:14px;background:rgba(255,255,255,0.08);border-radius:10px;color:#fff;font-size:13px;display:none;"></div>
        {{-- 액션 버튼 --}}
        <div id="gqrActions" style="margin-top:12px;display:none;gap:8px;flex-wrap:wrap;"></div>
    </div>
</div>

<script>
let gqrScanner=null, gqrItemData=null, gqrStaffData=null;
const CSRF_GQR=document.querySelector('meta[name="csrf-token"]')?.content;

function openGlobalQrScan(){
    gqrItemData=null; gqrStaffData=null;
    document.getElementById('globalQrOverlay').style.display='flex';
    document.getElementById('gqrStatus').style.display='none';
    document.getElementById('gqrActions').style.display='none';
    document.getElementById('globalQrManual').value='';
    startGlobalQr();
}

function closeGlobalQrScan(){
    stopGlobalQr();
    document.getElementById('globalQrOverlay').style.display='none';
    gqrItemData=null; gqrStaffData=null;
}

async function startGlobalQr(){
    try{
        gqrScanner=new Html5Qrcode('globalQrReader');
        await gqrScanner.start({facingMode:'environment'},{fps:10,qrbox:{width:240,height:240}},decoded=>{globalQrHandle(decoded);},()=>{});
    }catch(e){ showGqrStatus('⚠ 카메라를 사용할 수 없습니다. 수동 입력을 이용하세요.','#c87a7a'); }
}

function stopGlobalQr(){
    if(gqrScanner){try{gqrScanner.stop().catch(()=>{});}catch(e){}gqrScanner=null;}
}

async function globalQrHandle(code){
    if(!code) return;
    const res=await fetch('/api/rental/lookup?code='+encodeURIComponent(code.trim()),{headers:{'Accept':'application/json'}});
    if(!res.ok){const err=await res.json().catch(()=>({}));showGqrStatus('❌ '+(err.error||'인식 실패'),'#c87a7a');return;}
    const data=await res.json();

    if(data.type==='item'){
        gqrItemData=data;
        if(gqrStaffData){
            // 직원 먼저 스캔됨 → 바로 대여 처리
            await gqrAssign(gqrItemData.id, gqrStaffData.id, gqrStaffData.name);
        } else {
            showGqrStatus(`🔧 <b>${data.name}</b>${data.serial?' ('+data.serial+')':''}<br>현재: ${data.current_target||'미지정'} · 원위치: ${data.home_target||'없음'}`, '#fff');
            showGqrActions([
                {label:'👤 직원 QR 스캔하여 대여', color:'var(--accent)', action:()=>{ showGqrStatus('👤 직원 QR을 스캔하세요...','#8ab4c8'); hideGqrActions(); }},
                {label:'🏠 반납 (원위치)', color:'var(--green)', action:()=>gqrReturn(data.id)},
                {label:'초기화', color:'var(--border)', action:()=>gqrReset()},
            ]);
        }
    } else if(data.type==='staff'){
        gqrStaffData=data;
        if(gqrItemData){
            // 장비 먼저 스캔됨 → 바로 대여 처리
            await gqrAssign(gqrItemData.id, gqrStaffData.id, gqrStaffData.name);
        } else {
            showGqrStatus(`👤 <b>${data.name}</b>${data.phone?' · '+data.phone:''}<br>보유 장비: ${data.items_count}개`, '#fff');
            showGqrActions([
                {label:'🔧 장비 QR 스캔하여 대여', color:'var(--accent)', action:()=>{ showGqrStatus('🔧 장비 QR을 스캔하세요...','#c8b08a'); hideGqrActions(); }},
                {label:'초기화', color:'var(--border)', action:()=>gqrReset()},
            ]);
        }
    }
}

async function gqrAssign(itemId, targetId, targetName){
    const res=await fetch('/api/rental/assign',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_GQR,'Accept':'application/json'},
        body:JSON.stringify({item_id:itemId, target_id:targetId, memo:'QR 스캔 대여'})
    });
    if(res.ok){
        showGqrStatus(`✅ <b>${gqrItemData.name}</b> → <b>${targetName}</b> 대여 완료!`,'#7ac87a');
        showGqrActions([{label:'📷 다음 장비 스캔', color:'var(--accent)', action:()=>gqrReset()}]);
        showGqrToast(`✅ ${gqrItemData.name} → ${targetName} 대여 처리되었습니다.`);
    } else {
        showGqrStatus('❌ 대여 처리 실패','#c87a7a');
    }
}

async function gqrReturn(itemId){
    const res=await fetch('/api/rental/assign',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_GQR,'Accept':'application/json'},
        body:JSON.stringify({item_id:itemId, return:true, memo:'QR 스캔 반납'})
    });
    if(res.ok){
        showGqrStatus(`✅ <b>${gqrItemData.name}</b> 반납 완료! (원위치 복귀)`,'#7ac87a');
        showGqrActions([{label:'📷 다음 장비 스캔', color:'var(--accent)', action:()=>gqrReset()}]);
        showGqrToast(`✅ ${gqrItemData.name} 반납 처리되었습니다.`);
    } else { showGqrStatus('❌ 반납 실패','#c87a7a'); }
}

function gqrReset(){ gqrItemData=null; gqrStaffData=null; document.getElementById('gqrStatus').style.display='none'; hideGqrActions(); }

function showGqrStatus(html,color){
    const el=document.getElementById('gqrStatus');
    el.innerHTML=html; el.style.display='block'; el.style.color=color||'#fff';
}

function showGqrActions(btns){
    const el=document.getElementById('gqrActions');
    el.style.display='flex';
    el.innerHTML=btns.map(b=>`<button onclick="(${b.action.toString()})()" style="background:${b.color};color:#fff;border:none;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;flex:1;min-width:100px;">${b.label}</button>`).join('');
}

function hideGqrActions(){ document.getElementById('gqrActions').style.display='none'; }

function showGqrToast(msg){
    const el=document.createElement('div');
    el.style.cssText='position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:var(--accent);color:#1a1207;padding:14px 28px;border-radius:12px;font-size:15px;font-weight:700;z-index:99999;box-shadow:0 6px 24px rgba(0,0,0,0.4);animation:gqrToastIn 0.3s ease;white-space:nowrap;';
    el.textContent=msg;
    document.body.appendChild(el);
    setTimeout(()=>{el.style.transition='opacity 0.3s';el.style.opacity='0';setTimeout(()=>el.remove(),300);},2700);
}

</script>

@stack('scripts')
</body>
</html>
