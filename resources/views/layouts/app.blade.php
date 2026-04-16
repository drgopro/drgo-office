<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
<script>
(function(){var t=localStorage.getItem('drgo_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();
</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            --chip-gold-bg: #c8a870; --chip-gold-text: #1a1207;
            --chip-blue-bg: #7aaec8; --chip-blue-text: #061825;
            --chip-red-bg: #c87070; --chip-red-text: #200808;
            --chip-green-bg: #70c870; --chip-green-text: #08200a;
            --chip-purple-bg: #9b70c8; --chip-purple-text: #f0eaff;
            --chip-teal-bg: #e8894a; --chip-teal-text: #1a0a00;
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
            --chip-gold-bg: #c8a870; --chip-gold-text: #3a2a10;
            --chip-blue-bg: #5898ba; --chip-blue-text: #0a2838;
            --chip-red-bg: #c87070; --chip-red-text: #380808;
            --chip-green-bg: #58a858; --chip-green-text: #0a280a;
            --chip-purple-bg: #8860b8; --chip-purple-text: #fff;
            --chip-teal-bg: #d07830; --chip-teal-text: #382008;
            --chip-single-bg: #e6e8ee;
        }

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
        .header { background:var(--surface); border-bottom:1px solid var(--border); padding:0 20px; display:flex; justify-content:space-between; align-items:center; height:var(--header-h); position:sticky; top:0; z-index:200; }
        .header-left { display:flex; align-items:center; gap:0; }
        .logo { font-size:13px; font-weight:700; color:var(--accent); letter-spacing:0.15em; text-decoration:none; padding:0 16px 0 0; margin-right:16px; border-right:1px solid var(--border); }

        .nav { display:flex; align-items:center; gap:2px; }
        .nav a { text-decoration:none; color:var(--text-muted); font-size:13px; padding:6px 12px; border-radius:6px; transition:all 0.15s; }
        .nav a:hover { color:var(--text); background:var(--surface2); }
        .nav a.active { color:var(--accent); background:var(--surface2); }

        .header-right { display:flex; align-items:center; gap:12px; font-size:12px; }
        .user-role { color:var(--text-muted); font-size:11px; }
        .logout-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 10px; border-radius:6px; font-size:11px; cursor:pointer; transition:all 0.15s; }
        .logout-btn:hover { border-color:var(--accent); color:var(--accent); }
        .admin-link { font-size:12px; color:var(--text-muted); text-decoration:none; padding:5px 10px; border:1px solid var(--border); border-radius:6px; transition:all 0.15s; }
        .admin-link:hover, .admin-link.active { border-color:var(--accent); color:var(--accent); }
        .theme-toggle { background:none; border:1px solid var(--border); color:var(--text-muted); width:32px; height:32px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; transition:all 0.15s; }
        .theme-toggle:hover { border-color:var(--accent); color:var(--accent); }

        /* 햄버거 / 모바일전용 */
        .menu-toggle { display:none; background:none; border:none; color:var(--text); font-size:20px; cursor:pointer; padding:6px; }
        .nav-overlay { display:none; }
        .nav-mobile-only { display:none; }

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
<script>if (window !== window.top) document.body.classList.add('in-iframe');</script>

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
const drgoTabs = {
    tabs: [],
    activeId: null,

    ICONS: { dashboard:'📊', calendar:'📅', clients:'👤', projects:'📁', inventory:'📦', estimates:'📝', wiki:'📖', admin:'⚙️', profile:'👤' },
    LABELS: { dashboard:'대시보드', calendar:'캘린더', clients:'의뢰자', projects:'프로젝트', inventory:'재고', estimates:'견적서', wiki:'위키', admin:'관리', profile:'마이페이지' },

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

    openNav(type, url) {
        document.getElementById('mainNav').classList.remove('open');
        document.getElementById('navOverlay').classList.remove('open');
        const existing = this.tabs.find(t => t.url === url);
        if (existing) { this.activate(existing.id); return; }
        const id = 'tab-' + Date.now();
        this.tabs.push({ id, type, url, loaded: false });
        this.activate(id);
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
            const label = this.LABELS[t.type] || t.type;
            const cls = t.id === this.activeId ? 'active' : '';
            const close = this.tabs.length > 1
                ? `<span class="tab-close" onclick="event.stopPropagation(); drgoTabs.close('${t.id}')">✕</span>` : '';
            return `<button class="tab-item ${cls}" onclick="drgoTabs.activate('${t.id}')"><span class="tab-icon">${icon}</span>${label}${close}</button>`;
        }).join('');
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
        if (p.startsWith('/admin')) return 'admin';
        if (p.startsWith('/profile')) return 'profile';
        return 'page';
    },

    _save() {
        const data = {
            tabs: this.tabs.map(t => ({ type: t.type, url: t.url })),
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
                    this.tabs.push({ id: 'initial', type: t.type, url: t.url, loaded: true });
                    initialSet = true;
                } else {
                    this.tabs.push({ id: 'tab-r-' + i, type: t.type, url: t.url, loaded: false });
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

@stack('scripts')
</body>
</html>
