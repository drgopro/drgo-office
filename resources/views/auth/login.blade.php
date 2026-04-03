<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>닥터고블린 오피스</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0f0f0f; color: #f0e8d8; font-family: -apple-system, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { width: 360px; padding: 40px; background: #1a1a1a; border-radius: 16px; border: 1px solid #2a2a2a; }
        .logo { text-align: center; margin-bottom: 32px; }
        .logo h1 { font-size: 18px; font-weight: 700; color: #c8b08a; letter-spacing: 0.1em; }
        .logo p { font-size: 12px; color: #666; margin-top: 4px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; color: #888; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 14px; background: #111; border: 1px solid #333; border-radius: 8px; color: #f0e8d8; font-size: 14px; outline: none; transition: border-color 0.2s; }
        input:focus { border-color: #c8b08a; }
        .error { font-size: 12px; color: #c87a7a; margin-top: 6px; }
        .btn { width: 100%; padding: 12px; background: #c8b08a; color: #1a1207; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 8px; transition: filter 0.2s; }
        .btn:hover { filter: brightness(1.1); }
        .remember { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #888; margin-top: 12px; }
        .remember input { width: auto; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <h1>🟢 DRGO OFFICE</h1>
            <p>닥터고블린컴퍼니 사내 시스템</p>
        </div>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label>아이디</label>
                <input type="text" name="username" value="{{ old('username') }}" autofocus autocomplete="username">
                @error('username')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label>비밀번호</label>
                <input type="password" name="password" autocomplete="current-password">
            </div>
            <label class="remember">
                <input type="checkbox" name="remember"> 로그인 상태 유지
            </label>
            <button type="submit" class="btn">로그인</button>
        </form>
    </div>
</body>
</html>
