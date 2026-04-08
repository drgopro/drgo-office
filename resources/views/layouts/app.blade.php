<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>닥터고블린 오피스</title>
    <style>
        /* ── 다크 모드 (기본) ── */
        :root, [data-theme="dark"] {
            --bg: #0f0f0f;
            --surface: #1a1a1a;
            --surface2: #222;
            --border: #2a2a2a;
            --text: #f0e8d8;
            --text-muted: rgba(240,232,216,0.45);
            --accent: #c8b08a;
            --red: #c87a7a;
            --blue: #8ab4c8;
            --green: #7ac87a;
            --gold: #c8b08a;
            --teal: #4ecdc4;
            --purple: #9b70c8;
        }
        [data-theme="light"] {
            --bg: #f5f3ef;
            --surface: #ffffff;
            --surface2: #f0ede8;
            --border: #e0d8cc;
            --text: #2a2218;
            --text-muted: rgba(42,34,24,0.5);
            --accent: #8a6a3a;
            --red: #c84040;
            --blue: #2a6a9a;
            --green: #2a8a2a;
            --gold: #8a6a3a;
            --teal: #1a8a82;
            --purple: #6a3a9a;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:-apple-system,sans-serif; min-height:100vh; display:flex; flex-direction:column; transition:background 0.2s, color 0.2s; }

        /* ── 탑 바 ── */
        .topbar { background:var(--surface); border-bottom:1px solid var(--border); display:flex; align-items:center; height:42px; position:sticky; top:0; z-index:200; padding:0 12px; gap:0; }
        .logo { font-size:12px; font-weight:700; color:var(--accent); letter-spacing:0.12em; text-decoration:none; padding:0 12px; flex-shrink:0; }

        /* ── 탭 바 ── */
        .tab-strip { display:flex; align-items:center; flex:1; overflow-x:auto; gap:2px; padding:4px 0; scrollbar-width:none; }
        .tab-strip::-webkit-scrollbar { display:none; }

        .tab-item { display:flex; align-items:center; gap:6px; padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; color:var(--text-muted); background:transparent; border:none; white-space:nowrap; transition:all 0.15s; flex-shrink:0; }
        .tab-item:hover { color:var(--text); background:var(--surface2); }
        .tab-item.active { color:var(--accent); background:var(--surface2); font-weight:600; }
        .tab-item .tab-close { display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px; border-radius:4px; font-size:10px; opacity:0; transition:opacity 0.15s; }
        .tab-item:hover .tab-close { opacity:0.6; }
        .tab-item .tab-close:hover { opacity:1; background:var(--border); }

        .tab-add { display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:6px; border:1px solid var(--border); background:none; color:var(--text-muted); font-size:14px; cursor:pointer; flex-shrink:0; margin-left:4px; transition:all 0.15s; position:relative; }
        .tab-add:hover { border-color:var(--accent); color:var(--accent); }

        /* 탭 추가 드롭다운 */
        .tab-menu { display:none; position:absolute; top:100%; left:0; margin-top:6px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px; min-width:140px; z-index:300; box-shadow:0 4px 12px rgba(0,0,0,0.3); }
        .tab-menu.open { display:block; }
        .tab-menu-item { display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:6px; font-size:12px; color:var(--text-muted); cursor:pointer; border:none; background:none; width:100%; text-align:left; }
        .tab-menu-item:hover { color:var(--text); background:var(--surface2); }
        .tab-menu-item .tab-icon { font-size:13px; }

        /* ── 탑 바 우측 ── */
        .topbar-right { display:flex; align-items:center; gap:8px; flex-shrink:0; margin-left:8px; }
        .topbar-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:4px 8px; border-radius:5px; font-size:11px; cursor:pointer; text-decoration:none; transition:all 0.15s; }
        .topbar-btn:hover { border-color:var(--accent); color:var(--accent); }
        .theme-toggle { background:none; border:1px solid var(--border); color:var(--text-muted); width:28px; height:28px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; transition:all 0.15s; }
        .theme-toggle:hover { border-color:var(--accent); color:var(--accent); }

        /* ── 콘텐츠 영역 ── */
        .main { flex:1; position:relative; }
        .tab-pane { display:none; height:100%; }
        .tab-pane.active { display:block; }
        .tab-loading { display:flex; align-items:center; justify-content:center; height:200px; color:var(--text-muted); font-size:13px; }

        /* ── 모바일 ── */
        @media (max-width: 768px) {
            .topbar { height:40px; padding:0 8px; }
            .logo { font-size:11px; padding:0 8px; }
            .tab-item { padding:4px 8px; font-size:11px; }
            .topbar-right .topbar-btn:not(.theme-toggle) { display:none; }

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
    @stack('styles')
</head>
<body>

<div class="topbar">
    <a href="/" class="logo" onclick="event.preventDefault(); drgoTabs.open('dashboard','/');">DRGO</a>

    <div class="tab-strip" id="tabStrip"></div>

    <div style="position:relative;">
        <button class="tab-add" id="tabAddBtn" title="새 탭">+</button>
        <div class="tab-menu" id="tabMenu">
            @if(!Auth::user()->isGuest())
                <button class="tab-menu-item" onclick="drgoTabs.open('dashboard','/'); drgoTabs.closeMenu();">
                    <span class="tab-icon">📊</span> 대시보드
                </button>
            @endif
            <button class="tab-menu-item" onclick="drgoTabs.open('calendar','/calendar'); drgoTabs.closeMenu();">
                <span class="tab-icon">📅</span> 캘린더
            </button>
            @if(Auth::user()->hasPermission('clients.view'))
                <button class="tab-menu-item" onclick="drgoTabs.open('clients','/clients'); drgoTabs.closeMenu();">
                    <span class="tab-icon">👤</span> 의뢰자
                </button>
            @endif
            @if(Auth::user()->hasPermission('projects.view'))
                <button class="tab-menu-item" onclick="drgoTabs.open('projects','/projects'); drgoTabs.closeMenu();">
                    <span class="tab-icon">📁</span> 프로젝트
                </button>
            @endif
            @if(Auth::user()->hasPermission('inventory.view'))
                <button class="tab-menu-item" onclick="drgoTabs.open('inventory','/inventory'); drgoTabs.closeMenu();">
                    <span class="tab-icon">📦</span> 재고
                </button>
            @endif
            @if(Auth::user()->hasPermission('estimates.view'))
                <button class="tab-menu-item" onclick="drgoTabs.open('estimates','/estimates'); drgoTabs.closeMenu();">
                    <span class="tab-icon">📝</span> 견적서
                </button>
            @endif
            @if(Auth::user()->isAdmin())
                <button class="tab-menu-item" onclick="drgoTabs.open('admin','/admin'); drgoTabs.closeMenu();">
                    <span class="tab-icon">⚙️</span> 관리
                </button>
            @endif
            <button class="tab-menu-item" onclick="drgoTabs.open('profile','/profile'); drgoTabs.closeMenu();">
                <span class="tab-icon">👤</span> 마이페이지
            </button>
        </div>
    </div>

    <div class="topbar-right">
        @if(Auth::user()->isAdmin())
            <a href="#" class="topbar-btn" onclick="event.preventDefault(); drgoTabs.open('admin','/admin');">관리</a>
        @endif
        <button class="theme-toggle" id="themeToggle" title="다크/라이트 모드">🌙</button>
        <a href="#" class="topbar-btn" onclick="event.preventDefault(); drgoTabs.open('profile','/profile');">{{ Auth::user()->display_name }}</a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="topbar-btn" style="color:var(--red); border-color:var(--red);">로그아웃</button>
        </form>
    </div>
</div>

<div class="main" id="tabContent">
    {{-- 서버 렌더링된 현재 페이지 콘텐츠가 첫 탭으로 --}}
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
});

// ── 탭 추가 메뉴 ──
document.getElementById('tabAddBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('tabMenu').classList.toggle('open');
});
document.addEventListener('click', () => document.getElementById('tabMenu').classList.remove('open'));

// ── 탭 시스템 ──
const drgoTabs = {
    tabs: [],
    activeId: null,
    CSRF: document.querySelector('meta[name="csrf-token"]').content,

    ICONS: { dashboard:'📊', calendar:'📅', clients:'👤', projects:'📁', inventory:'📦', estimates:'📝', admin:'⚙️', profile:'👤' },
    LABELS: { dashboard:'대시보드', calendar:'캘린더', clients:'의뢰자', projects:'프로젝트', inventory:'재고', estimates:'견적서', admin:'관리', profile:'마이페이지' },

    init() {
        // 현재 페이지를 첫 탭으로 등록
        const path = window.location.pathname;
        const type = this._typeFromPath(path);
        const id = 'initial';
        this.tabs = [{ id, type, url: path, loaded: true }];
        this.activeId = id;
        this.render();
        this.save();
    },

    open(type, url) {
        // 같은 URL이면 해당 탭으로 전환
        const existing = this.tabs.find(t => t.url === url);
        if (existing) { this.activate(existing.id); return; }

        const id = 'tab-' + Date.now();
        this.tabs.push({ id, type, url, loaded: false });
        this.activate(id);
        this.save();
    },

    activate(id) {
        this.activeId = id;
        const tab = this.tabs.find(t => t.id === id);
        if (!tab) return;

        // 모든 pane 숨기기
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

        if (!tab.loaded) {
            this._load(tab, pane);
        }

        this.render();
        this.save();
    },

    close(id) {
        const idx = this.tabs.findIndex(t => t.id === id);
        if (idx === -1 || this.tabs.length <= 1) return;

        this.tabs.splice(idx, 1);
        const pane = document.getElementById('pane-' + id);
        if (pane) pane.remove();

        if (this.activeId === id) {
            const next = this.tabs[Math.min(idx, this.tabs.length - 1)];
            this.activate(next.id);
        } else {
            this.render();
        }
        this.save();
    },

    async _load(tab, pane) {
        try {
            const sep = tab.url.includes('?') ? '&' : '?';
            const res = await fetch(tab.url + sep + '_tab=1', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            });
            if (!res.ok) throw new Error(res.status);
            const html = await res.text();
            pane.innerHTML = html;
            tab.loaded = true;
            // 실행 가능한 스크립트 활성화
            pane.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                if (oldScript.src) { newScript.src = oldScript.src; }
                else { newScript.textContent = oldScript.textContent; }
                oldScript.replaceWith(newScript);
            });
        } catch (e) {
            pane.innerHTML = '<div class="tab-loading" style="color:var(--red)">로드 실패 — <a href="' + tab.url + '" style="color:var(--accent)">직접 열기</a></div>';
        }
    },

    render() {
        const strip = document.getElementById('tabStrip');
        strip.innerHTML = this.tabs.map(t => {
            const icon = this.ICONS[t.type] || '📄';
            const label = this.LABELS[t.type] || t.type;
            const active = t.id === this.activeId ? 'active' : '';
            const closeBtn = this.tabs.length > 1
                ? `<span class="tab-close" onclick="event.stopPropagation(); drgoTabs.close('${t.id}')">✕</span>`
                : '';
            return `<button class="tab-item ${active}" onclick="drgoTabs.activate('${t.id}')">${icon} ${label} ${closeBtn}</button>`;
        }).join('');
    },

    closeMenu() {
        document.getElementById('tabMenu').classList.remove('open');
    },

    save() {
        const data = { tabs: this.tabs.map(t => ({ type: t.type, url: t.url })), activeIdx: this.tabs.findIndex(t => t.id === this.activeId) };
        sessionStorage.setItem('drgo_tabs', JSON.stringify(data));
    },

    restore() {
        const raw = sessionStorage.getItem('drgo_tabs');
        if (!raw) return false;
        try {
            const data = JSON.parse(raw);
            if (!data.tabs || !data.tabs.length) return false;
            // 현재 페이지 경로
            const currentPath = window.location.pathname;
            // 현재 페이지가 저장된 탭 중 하나면 복원
            const hasCurrentPath = data.tabs.some(t => t.url === currentPath);
            if (!hasCurrentPath) return false;

            // 첫 탭은 이미 서버 렌더링됨 (현재 페이지)
            // 나머지 탭을 추가 (로드는 activate 시)
            data.tabs.forEach((t, i) => {
                if (t.url === currentPath) {
                    // 이미 initial로 존재
                    this.tabs[0].type = t.type;
                    this.tabs[0].url = t.url;
                } else {
                    const id = 'tab-' + Date.now() + '-' + i;
                    this.tabs.push({ id, type: t.type, url: t.url, loaded: false });
                }
            });

            if (data.activeIdx >= 0 && data.tabs[data.activeIdx]) {
                const activeUrl = data.tabs[data.activeIdx].url;
                if (activeUrl !== currentPath) {
                    const tab = this.tabs.find(t => t.url === activeUrl);
                    if (tab) this.activate(tab.id);
                }
            }
            this.render();
            return true;
        } catch { return false; }
    },

    _typeFromPath(path) {
        if (path === '/') return 'dashboard';
        if (path.startsWith('/calendar')) return 'calendar';
        if (path.startsWith('/clients')) return 'clients';
        if (path.startsWith('/projects')) return 'projects';
        if (path.startsWith('/inventory')) return 'inventory';
        if (path.startsWith('/estimates')) return 'estimates';
        if (path.startsWith('/admin')) return 'admin';
        if (path.startsWith('/profile')) return 'profile';
        return 'page';
    }
};

drgoTabs.init();
drgoTabs.restore();
</script>

@stack('scripts')
</body>
</html>
