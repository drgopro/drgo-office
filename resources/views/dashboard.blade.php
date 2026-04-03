<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>닥터고블린 오피스</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0f0f0f; color: #f0e8d8; font-family: -apple-system, sans-serif; min-height: 100vh; }
        .header { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 14px; font-weight: 700; color: #c8b08a; letter-spacing: 0.1em; }
        .user-info { font-size: 13px; color: #888; display: flex; align-items: center; gap: 16px; }
        .user-info span { color: #f0e8d8; }
        .logout-btn { background: none; border: 1px solid #333; color: #888; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; }
        .logout-btn:hover { border-color: #c8b08a; color: #c8b08a; }
        .content { padding: 40px 24px; text-align: center; color: #444; }
        .content h2 { font-size: 20px; color: #c8b08a; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🟢 DRGO OFFICE</div>
        <div class="user-info">
            <span>{{ Auth::user()->display_name }}</span>
            <span style="color:#555">{{ Auth::user()->role }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">로그아웃</button>
            </form>
        </div>
    </div>
    <div class="content">
        <h2>대시보드 준비 중</h2>
        <p>로그인 성공! 곧 기능이 추가됩니다.</p>
    </div>
</body>
</html>
