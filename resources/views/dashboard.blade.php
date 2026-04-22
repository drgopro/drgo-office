@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '대시보드 - 닥터고블린 오피스')

@push('styles')
<style>
    .dash-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .dash-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
    .dash-header h1 { font-size:20px; font-weight:700; }
    .dash-header p { font-size:12px; color:var(--text-muted); margin-top:4px; }

    .section-title { font-size:13px; font-weight:600; color:var(--accent); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .section-title .section-sub { font-size:11px; color:var(--text-muted); font-weight:400; margin-left:auto; }

    .pipeline-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px; margin-bottom:28px; }
    .pipeline-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px; cursor:pointer; transition:all 0.15s; position:relative; overflow:hidden; }
    .pipeline-card:hover { border-color:var(--accent); transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.12); }
    .pipeline-card .pc-label { font-size:11px; letter-spacing:0.12em; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:4px; }
    .pipeline-card .pc-value { font-size:32px; font-weight:700; line-height:1; }
    .pipeline-card .pc-sub { font-size:11px; color:var(--text-muted); margin-top:6px; }
    .pipeline-card .pc-accent { position:absolute; left:0; top:0; bottom:0; width:4px; }
    .pipeline-card.p-consulting .pc-accent { background:#c8b08a; }
    .pipeline-card.p-estimate .pc-accent { background:#8ab4c8; }
    .pipeline-card.p-payment .pc-accent { background:#e8894a; }
    .pipeline-card.p-visit .pc-accent { background:#7ac87a; }

    .info-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; margin-bottom:28px; }
    .info-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px 16px; cursor:pointer; transition:all 0.15s; }
    .info-card:hover { border-color:var(--accent); }
    .info-card .ic-label { font-size:10px; color:var(--text-muted); letter-spacing:0.12em; text-transform:uppercase; margin-bottom:6px; }
    .info-card .ic-value { font-size:20px; font-weight:700; }
    .info-card .ic-sub { font-size:11px; color:var(--text-muted); margin-top:4px; }

    .consult-list { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .consult-item { display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid var(--border); font-size:13px; }
    .consult-item:last-child { border-bottom:none; }
    .consult-item:hover { background:var(--surface2); }
    .consult-badge { font-size:10px; padding:3px 8px; border-radius:4px; font-weight:600; white-space:nowrap; }
    .consult-badge.in_progress { background:rgba(200,176,138,0.2); color:var(--accent); }
    .consult-badge.waiting { background:rgba(138,180,200,0.2); color:#8ab4c8; }
    .consult-client { font-weight:600; min-width:90px; }
    .consult-content { color:var(--text-muted); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .consult-meta { font-size:11px; color:var(--text-muted); white-space:nowrap; }
    .consult-empty { padding:30px; text-align:center; color:var(--text-muted); font-size:13px; }

    .shortcut-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:10px; }
    .shortcut-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px; cursor:pointer; text-decoration:none; color:var(--text); transition:all 0.15s; }
    .shortcut-card:hover { border-color:var(--accent); transform:translateY(-1px); }
    .shortcut-card .sc-icon { font-size:22px; margin-bottom:4px; }
    .shortcut-card .sc-label { font-size:12px; font-weight:600; }
    .shortcut-card .sc-sub { font-size:10px; color:var(--text-muted); margin-top:2px; }

    @media (max-width:768px) {
        .pipeline-grid { grid-template-columns:1fr 1fr; }
        .pipeline-card .pc-value { font-size:24px; }
    }
</style>
@endpush

@section('content')
<div class="dash-wrap">
    <div class="dash-header">
        <div>
            <h1>🏠 대시보드</h1>
            <p>{{ now()->format('Y년 m월 d일') }} · {{ Auth::user()->display_name }}님 안녕하세요</p>
        </div>
        @if(Auth::user()->isAdmin())
            <a href="/marketing-report" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('marketing-report','/marketing-report'); else location.href='/marketing-report';" style="background:var(--accent); color:#1a1207; padding:8px 16px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">📊 상세 통계 →</a>
        @endif
    </div>

    {{-- 진행 상황 (파이프라인) --}}
    <div class="section-title">
        ⚙️ 진행 상황
        <span class="section-sub">실시간 스냅샷 · 클릭하면 프로젝트 목록으로 이동</span>
    </div>
    <div class="pipeline-grid">
        <div class="pipeline-card p-consulting" onclick="goProjectsByStage('consulting')">
            <div class="pc-accent"></div>
            <div class="pc-label">💬 상담 중</div>
            <div class="pc-value">{{ $pipeline['consulting'] }}</div>
            <div class="pc-sub">초기 상담 단계</div>
        </div>
        <div class="pipeline-card p-estimate" onclick="goProjectsByStage('estimate')">
            <div class="pc-accent"></div>
            <div class="pc-label">📝 견적 단계</div>
            <div class="pc-value">{{ $pipeline['estimate'] }}</div>
            <div class="pc-sub">장비파악·제안·견적</div>
        </div>
        <div class="pipeline-card p-payment" onclick="goProjectsByStage('payment')">
            <div class="pc-accent"></div>
            <div class="pc-label">💰 결제 대기</div>
            <div class="pc-value">{{ $pipeline['payment'] }}</div>
            <div class="pc-sub">결제/예약 단계</div>
        </div>
        <div class="pipeline-card p-visit" onclick="goProjectsByStage('visit')">
            <div class="pc-accent"></div>
            <div class="pc-label">🔧 세팅 진행</div>
            <div class="pc-value">{{ $pipeline['visit'] }}</div>
            <div class="pc-sub">세팅·AS 진행 중</div>
        </div>
    </div>

    {{-- 간단한 현황 --}}
    <div class="section-title">📌 오늘의 현황</div>
    <div class="info-grid">
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('clients','/clients'); else location.href='/clients';">
            <div class="ic-label">의뢰자</div>
            <div class="ic-value">{{ number_format($clientTotal) }}명</div>
            <div class="ic-sub">이번 달 +{{ $clientThisMonth }}</div>
        </div>
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">
            <div class="ic-label">이번 달 일정</div>
            <div class="ic-value">{{ $scheduleThisMonth }}건</div>
        </div>
        @if(($rentalActive ?? 0) > 0 || ($broadcastActive ?? 0) > 0)
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('rental-contracts','/rental-contracts'); else location.href='/rental-contracts';">
            <div class="ic-label">🏠 렌탈 진행중</div>
            <div class="ic-value">{{ $rentalActive ?? 0 }}건</div>
            <div class="ic-sub">월 {{ number_format($rentalMonthlyRevenue ?? 0) }}원</div>
        </div>
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('broadcast-room','/broadcast-room'); else location.href='/broadcast-room';">
            <div class="ic-label">🎙 방송룸 월계약</div>
            <div class="ic-value">{{ $broadcastActive ?? 0 }}건</div>
            <div class="ic-sub">월 {{ number_format($broadcastMonthlyRevenue ?? 0) }}원</div>
        </div>
        @endif
    </div>

    {{-- 상담 대기/진행중 --}}
    <div class="section-title">
        💬 상담 대기 / 진행중
        <span class="section-sub">우선순위가 높은 항목</span>
    </div>
    <div class="consult-list">
        @if($recentConsults->count() > 0)
            @foreach($recentConsults as $c)
                <div class="consult-item">
                    <span class="consult-badge {{ $c['result'] }}">
                        {{ ['in_progress' => '진행중', 'waiting' => '대기'][$c['result']] ?? $c['result'] }}
                    </span>
                    <span class="consult-client">{{ $c['client'] ?? '-' }}</span>
                    <span class="consult-content">{{ $c['content'] }}</span>
                    <span class="consult-meta">{{ $c['consultant'] ?? '-' }} · {{ $c['date'] }}</span>
                </div>
            @endforeach
        @else
            <div class="consult-empty">대기/진행중 상담이 없습니다</div>
        @endif
    </div>

    {{-- 빠른 이동 --}}
    <div class="section-title" style="margin-top:28px;">🔗 빠른 이동</div>
    <div class="shortcut-grid">
        <a href="/calendar" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href=this.href;">
            <div class="sc-icon">📅</div>
            <div class="sc-label">캘린더</div>
            <div class="sc-sub">일정 확인</div>
        </a>
        <a href="/clients" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('clients','/clients'); else location.href=this.href;">
            <div class="sc-icon">👤</div>
            <div class="sc-label">의뢰자</div>
            <div class="sc-sub">CRM 관리</div>
        </a>
        <a href="/projects" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('projects','/projects'); else location.href=this.href;">
            <div class="sc-icon">📁</div>
            <div class="sc-label">프로젝트</div>
            <div class="sc-sub">전체 목록</div>
        </a>
        <a href="/estimates" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('estimates','/estimates'); else location.href=this.href;">
            <div class="sc-icon">📝</div>
            <div class="sc-label">견적서</div>
        </a>
        <a href="/inventory" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('inventory','/inventory'); else location.href=this.href;">
            <div class="sc-icon">📦</div>
            <div class="sc-label">재고</div>
        </a>
        <a href="/rental-equipment" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('rental','/rental-equipment'); else location.href=this.href;">
            <div class="sc-icon">📷</div>
            <div class="sc-label">장비 위치</div>
        </a>
    </div>
</div>

<script>
function goProjectsByStage(stage) {
    const url = '/projects?stage=' + stage;
    if (window.parent && window.parent.drgoTabs) {
        window.parent.drgoTabs.openNav('projects', url);
    } else {
        location.href = url;
    }
}
</script>
@endsection
