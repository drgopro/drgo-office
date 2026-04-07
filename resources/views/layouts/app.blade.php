<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '닥터고블린 오피스')</title>
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

        /* ── 라이트 모드 ── */
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

        /* 헤더 */
        .header { background:var(--surface); border-bottom:1px solid var(--border); padding:0 20px; display:flex; justify-content:space-between; align-items:center; height:52px; position:sticky; top:0; z-index:100; }
        .header-left { display:flex; align-items:center; gap:0; }
        .logo { font-size:13px; font-weight:700; color:var(--accent); letter-spacing:0.15em; text-decoration:none; padding:0 16px 0 0; margin-right:16px; border-right:1px solid var(--border); }

        /* 네비 */
        .nav { display:flex; align-items:center; gap:2px; }
        .nav a { text-decoration:none; color:var(--text-muted); font-size:13px; padding:6px 12px; border-radius:6px; transition:all 0.15s; }
        .nav a:hover { color:var(--text); background:var(--surface2); }
        .nav a.active { color:var(--accent); background:var(--surface2); }

        /* 헤더 우측 */
        .header-right { display:flex; align-items:center; gap:12px; font-size:12px; }
        .user-name { color:var(--text); }
        .user-role { color:var(--text-muted); font-size:11px; }
        .logout-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 10px; border-radius:6px; font-size:11px; cursor:pointer; transition:all 0.15s; }
        .logout-btn:hover { border-color:var(--accent); color:var(--accent); }
        .admin-link { font-size:12px; color:var(--text-muted); text-decoration:none; padding:5px 10px; border:1px solid var(--border); border-radius:6px; transition:all 0.15s; }
        .admin-link:hover, .admin-link.active { border-color:var(--accent); color:var(--accent); }

        /* 다크/라이트 토글 */
        .theme-toggle { background:none; border:1px solid var(--border); color:var(--text-muted); width:32px; height:32px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; transition:all 0.15s; }
        .theme-toggle:hover { border-color:var(--accent); color:var(--accent); }

        .main { flex:1; }

        /* 햄버거 / 모바일전용 */
        .menu-toggle { display:none; background:none; border:none; color:var(--text); font-size:20px; cursor:pointer; padding:6px; }
        .nav-overlay { display:none; }
        .nav-mobile-only { display:none; }

        /* ── 모바일 반응형 ── */
        @media (max-width: 768px) {
            .header { padding:0 12px; height:48px; }
            .logo { font-size:12px; padding-right:10px; margin-right:10px; }
            .header-right .user-name, .header-right .user-role, .header-right .admin-link { display:none; }
            .header-right { gap:8px; }

            .menu-toggle { display:flex; align-items:center; justify-content:center; }
            .nav { display:none; position:fixed; top:48px; left:0; right:0; bottom:0; background:var(--surface); flex-direction:column; padding:12px; gap:2px; z-index:99; overflow-y:auto; }
            .nav.open { display:flex; }
            .nav a { font-size:15px; padding:12px 16px; border-radius:8px; }
            .nav-overlay { display:none; position:fixed; inset:0; top:48px; background:rgba(0,0,0,0.5); z-index:98; }
            .nav-overlay.open { display:block; }

            /* 모바일 nav 하단에 사용자/관리 표시 */
            .nav-mobile-only { display:none; border-top:1px solid var(--border); margin-top:8px; padding-top:12px; }
            .nav.open .nav-mobile-only { display:block; }
            .nav-mobile-only a, .nav-mobile-only span { display:block; font-size:13px; padding:8px 16px; color:var(--text-muted); text-decoration:none; border-radius:8px; }
            .nav-mobile-only a:hover { color:var(--accent); background:var(--surface2); }
            .nav-mobile-only .mobile-user { font-size:12px; color:var(--text-muted); padding:8px 16px; }

            /* 공통 페이지 */
            .page-wrap { padding:16px !important; }
            .page-header { flex-direction:column; align-items:flex-start !important; gap:10px; }
            .info-grid { grid-template-columns:1fr !important; }
            .info-card.full { grid-column:1 !important; }

            /* 테이블 수평 스크롤 */
            .data-card { overflow-x:auto; -webkit-overflow-scrolling:touch; }
            .data-table { min-width:600px; }
            .data-table th, .data-table td { padding:10px 10px; font-size:12px; white-space:nowrap; }

            /* 모달 */
            .modal { width:95vw !important; max-width:95vw !important; padding:16px !important; }
            .field-row, .field-row-3 { grid-template-columns:1fr !important; }

            /* 탭 바 */
            .tab-bar { flex-wrap:wrap; }
            .tab-btn { font-size:12px; padding:8px 4px; min-width:0; }

            /* 툴바 */
            .toolbar { flex-direction:column; align-items:stretch; }
            .toolbar input[type="text"] { width:100% !important; }

            /* 문서 썸네일 */
            .doc-grid { gap:8px; }
            .doc-thumb-card { width:90px; }
            .doc-thumb-card .thumb-img { width:90px; height:90px; }

            /* 앨범 */
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

<div class="header">
    <div class="header-left">
        <a href="/" class="logo">🟢 DRGO</a>
        <button class="menu-toggle" id="menuToggle" onclick="toggleNav()">☰</button>
        <nav class="nav" id="mainNav">
            <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">대시보드</a>
            <a href="/calendar" class="{{ request()->is('calendar*') ? 'active' : '' }}">캘린더</a>
            <a href="/clients" class="{{ request()->is('clients*') ? 'active' : '' }}">의뢰자</a>
            <a href="/projects" class="{{ request()->is('projects*') ? 'active' : '' }}">프로젝트</a>
            <a href="/inventory" class="{{ request()->is('inventory*') ? 'active' : '' }}">재고</a>
            <a href="/estimates" class="{{ request()->is('estimates*') ? 'active' : '' }}">견적서</a>
            <div class="nav-mobile-only">
                @if(in_array(Auth::user()->role, ['master', 'admin']))
                    <a href="{{ route('admin') }}">관리</a>
                @endif
                <span class="mobile-user">{{ Auth::user()->display_name }} ({{ Auth::user()->role }})</span>
            </div>
        </nav>
        <div class="nav-overlay" id="navOverlay" onclick="toggleNav()"></div>
    </div>
    <div class="header-right">
        @if(in_array(Auth::user()->role, ['master', 'admin']))
            <a href="{{ route('admin') }}" class="admin-link {{ request()->is('admin*') ? 'active' : '' }}">관리</a>
        @endif
        <button class="theme-toggle" id="themeToggle" title="다크/라이트 모드">🌙</button>
        <span class="user-name">{{ Auth::user()->display_name }}</span>
        <span class="user-role">{{ Auth::user()->role }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">로그아웃</button>
        </form>
    </div>
</div>

<div class="main">
    @yield('content')
</div>

<script>
    // 테마 초기화
    const savedTheme = localStorage.getItem('drgo_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.getElementById('themeToggle').textContent = savedTheme === 'dark' ? '🌙' : '☀️';

    // 모바일 네비
    function toggleNav() {
        document.getElementById('mainNav').classList.toggle('open');
        document.getElementById('navOverlay').classList.toggle('open');
        const btn = document.getElementById('menuToggle');
        btn.textContent = document.getElementById('mainNav').classList.contains('open') ? '✕' : '☰';
    }

    // 토글
    document.getElementById('themeToggle').addEventListener('click', function() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('drgo_theme', next);
        this.textContent = next === 'dark' ? '🌙' : '☀️';
    });
</script>

@stack('scripts')
</body>
</html>
