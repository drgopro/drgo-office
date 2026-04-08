@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '마이페이지 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:600px; }
    .page-title { font-size:22px; font-weight:700; margin-bottom:20px; }

    .section-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; margin-bottom:20px; }
    .section-title { font-size:15px; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border); }

    .field-group { margin-bottom:16px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; display:block; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-input:disabled { opacity:0.5; cursor:not-allowed; }
    .field-hint { font-size:11px; color:var(--text-muted); margin-top:4px; }

    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:opacity 0.15s; }
    .btn-save:hover { opacity:0.85; }

    .alert { padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }
    .alert-success { background:#1a2a1a; color:#7ac87a; border:1px solid #2a3a2a; }
    .alert-error { background:#2a1a1a; color:#c87a7a; border:1px solid #3a2a2a; }

    .info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border); font-size:13px; }
    .info-row:last-child { border-bottom:none; }
    .info-label { color:var(--text-muted); }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-title">마이페이지</div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- 계정 정보 --}}
    <div class="section-card">
        <div class="section-title">계정 정보</div>
        <div class="info-row">
            <span class="info-label">아이디</span>
            <span>{{ $user->username }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">역할</span>
            <span>{{ $user->role }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">가입일</span>
            <span>{{ $user->created_at->format('Y.m.d') }}</span>
        </div>
    </div>

    {{-- 프로필 수정 --}}
    <div class="section-card">
        <div class="section-title">프로필 수정</div>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')
            <div class="field-group">
                <label class="field-label">표시 이름</label>
                <input type="text" name="display_name" class="field-input" value="{{ old('display_name', $user->display_name) }}" required>
                @error('display_name')
                    <div class="field-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>
            <div class="field-group">
                <label class="field-label">이메일</label>
                <input type="email" name="email" class="field-input" value="{{ old('email', $user->email) }}">
                @error('email')
                    <div class="field-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn-save">저장</button>
        </form>
    </div>

    {{-- 비밀번호 변경 --}}
    <div class="section-card">
        <div class="section-title">비밀번호 변경</div>
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            @method('PUT')
            <div class="field-group">
                <label class="field-label">현재 비밀번호</label>
                <input type="password" name="current_password" class="field-input" required>
                @error('current_password')
                    <div class="field-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>
            <div class="field-group">
                <label class="field-label">새 비밀번호</label>
                <input type="password" name="password" class="field-input" required>
                <div class="field-hint">8자 이상</div>
                @error('password')
                    <div class="field-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>
            <div class="field-group">
                <label class="field-label">새 비밀번호 확인</label>
                <input type="password" name="password_confirmation" class="field-input" required>
            </div>
            <button type="submit" class="btn-save">비밀번호 변경</button>
        </form>
    </div>
</div>
@endsection
