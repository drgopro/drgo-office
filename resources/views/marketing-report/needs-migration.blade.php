@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '마이그레이션 필요')

@section('content')
<div style="padding:60px 24px; max-width:600px; margin:0 auto; text-align:center;">
    <div style="font-size:48px; margin-bottom:16px;">⚙️</div>
    <h2 style="font-size:20px; font-weight:700; margin-bottom:12px;">데이터베이스 마이그레이션이 필요합니다</h2>
    <p style="font-size:14px; color:var(--text-muted); line-height:1.7; margin-bottom:24px;">
        마케팅 통계에 필요한 DB 컬럼/테이블이 아직 적용되지 않았습니다.<br>
        운영 서버에서 아래 명령을 실행한 후 다시 접속해주세요.
    </p>
    <div style="background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 18px; font-family:monospace; font-size:13px; display:inline-block;">
        php artisan migrate --force
    </div>
    <p style="font-size:12px; color:var(--text-muted); margin-top:20px;">
        또는 Forge > Sites > Deploy Now 버튼 클릭
    </p>
</div>
@endsection
