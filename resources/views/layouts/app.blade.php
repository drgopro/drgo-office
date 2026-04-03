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

        /* 다크/라이트 토글 */
        .theme-toggle { background:none; border:1px solid var(--border); color:var(--text-muted); width:32px; height:32px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; transition:all 0.15s; }
        .theme-toggle:hover { border-color:var(--accent); color:var(--accent); }

        .main { flex:1; }
    </style>
    @stack('styles')
</head>
<body>

<div class="header">
    <div class="header-left">
        <a href="/" class="logo">🟢 DRGO</a>
        <nav class="nav">
            <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">대시보드</a>
            <a href="/calendar" class="{{ request()->is('calendar*') ? 'active' : '' }}">캘린더</a>
            <a href="/clients" class="{{ request()->is('clients*') ? 'active' : '' }}">의뢰자</a>
            <a href="/projects" class="{{ request()->is('projects*') ? 'active' : '' }}">프로젝트</a>
            <a href="/inventory" class="{{ request()->is('inventory*') ? 'active' : '' }}">재고</a>
        </nav>
    </div>
    <div class="header-right">
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
