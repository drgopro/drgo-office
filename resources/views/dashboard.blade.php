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

    /* 상담 현황 — 세그먼트 바 + 단계 카드 (한 컨테이너) */
    .pl-wrap { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; margin-bottom:20px; }
    .pl-head { display:flex; align-items:baseline; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
    .pl-title { font-size:13.5px; font-weight:700; }
    .pl-sub { font-size:11px; color:var(--text-muted); }
    .pl-total { margin-left:auto; font-size:12px; color:var(--text-muted); }
    .pl-total b { color:var(--text); }
    .pl-bar { display:flex; height:8px; border-radius:999px; overflow:hidden; background:var(--surface2); margin-bottom:14px; gap:2px; }
    .pl-bar span { display:block; height:100%; border-radius:999px; min-width:4px; }
    .pl-cards { display:grid; grid-template-columns:repeat(5, 1fr); gap:10px; }
    .pl-card { border:1px solid var(--border); border-radius:10px; padding:12px 14px; cursor:pointer; background:var(--surface); transition:all 0.15s; }
    .pl-card:hover { border-color:var(--accent); transform:translateY(-1px); }
    .pl-card .plc-label { font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:6px; margin-bottom:7px; white-space:nowrap; }
    .pl-card .plc-dot { width:8px; height:8px; border-radius:50%; flex:none; }
    .pl-card .plc-value { font-size:26px; font-weight:800; line-height:1; }
    .pl-card .plc-sub { font-size:10.5px; color:var(--text-muted); margin-top:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    @media (max-width:900px) { .pl-cards { grid-template-columns:repeat(2, 1fr); } }

    /* 좌(상담 리스트) / 우(공지·업데이트) 2컬럼 */
    .dash-cols { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:16px; align-items:start; margin-bottom:28px; }
    @media (max-width:900px) { .dash-cols { grid-template-columns:1fr; } }
    .list-more { display:block; text-align:right; font-size:11px; color:var(--accent); text-decoration:none; padding:10px 14px; border-top:1px solid var(--border); }

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
    .pipeline-card.p-as .pc-accent { background:#c87a7a; }
    .pipeline-card.p-done .pc-accent { background:#a09890; }

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
    .consult-item.clickable { cursor:pointer; }
    .consult-item.clickable:hover .consult-content { color:var(--accent); }
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
    /* 위키 위젯 */
    .wiki-widget-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:28px; }
    .wiki-widget { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px 16px; }
    .ww-head { font-size:12px; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
    .ww-head .ww-sub { font-size:10px; color:var(--text-muted); font-weight:400; margin-left:auto; }
    .ww-item { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--border); text-decoration:none; color:var(--text); font-size:13px; }
    .ww-item:last-child { border-bottom:none; }
    .ww-item:hover .ww-title { color:var(--accent); }
    .ww-title { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ww-date { font-size:11px; color:var(--text-muted); flex-shrink:0; }
    .ww-empty { font-size:12px; color:var(--text-muted); padding:12px 0; text-align:center; }
    .ww-more { display:block; text-align:right; font-size:11px; color:var(--accent); text-decoration:none; margin-top:8px; }
    .ww-type-badge { flex-shrink:0; font-size:10px; font-weight:700; padding:2px 7px; border-radius:5px; }
    .ww-type-badge.notice { background:#e8894a22; color:#e8894a; }
    .ww-type-badge.update { background:#7aaec822; color:#5b9bd5; }
    @media (max-width:768px) { .wiki-widget-grid { grid-template-columns:1fr; } }

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
            <h1><x-icon name="home" :size="20"/> 대시보드</h1>
            <p>{{ now()->format('Y년 m월 d일') }} · {{ Auth::user()->display_name }}님 안녕하세요</p>
        </div>
        @if(Auth::user()->isAdmin())
            <a href="/marketing-report" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('marketing-report','/marketing-report'); else location.href='/marketing-report';" style="background:var(--accent); color:var(--accent-text); padding:8px 16px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px;"><x-icon name="chart" :size="15"/> 상세 통계 →</a>
        @endif
    </div>

    {{-- 상담 현황 — 세그먼트 바 + 단계 카드 --}}
    @php
        $plStages = [
            ['key' => 'consulting', 'label' => '상담 중', 'sub' => '초기 상담 단계', 'count' => $pipeline['consulting'], 'go' => 'consulting', 'color' => '#3A5683'],
            ['key' => 'estimate', 'label' => '견적 단계', 'sub' => '장비파악·제안·견적', 'count' => $pipeline['estimate'], 'go' => 'equipment,proposal,estimate', 'color' => '#5c7aa6'],
            ['key' => 'payment', 'label' => '결제 대기', 'sub' => '결제/예약 단계', 'count' => $pipeline['payment'], 'go' => 'payment', 'color' => '#8ba3c7'],
            ['key' => 'visit', 'label' => '세팅 진행', 'sub' => '방문/원격 세팅 진행 중', 'count' => $pipeline['visit'], 'go' => 'visit', 'color' => '#b9c6dc'],
            ['key' => 'as', 'label' => '세팅 완료 · AS', 'sub' => '세팅 완료 후 AS 기간', 'count' => $pipeline['as'] ?? 0, 'go' => 'as', 'color' => '#dfe5ee'],
        ];
        $plSum = max(1, collect($plStages)->sum('count'));
    @endphp
    <div class="pl-wrap">
        <div class="pl-head">
            <span class="pl-title">상담 현황</span>
            <span class="pl-sub">실시간 · 클릭하면 프로젝트 목록으로 이동</span>
            <span class="pl-total">누적 완료 <b>{{ number_format($pipeline['done'] ?? 0) }}건</b></span>
        </div>
        <div class="pl-bar">
            @foreach($plStages as $s)
                @if($s['count'] > 0)
                    <span style="width:{{ round($s['count'] / $plSum * 100, 1) }}%;background:{{ $s['color'] }};" title="{{ $s['label'] }} {{ $s['count'] }}건"></span>
                @endif
            @endforeach
        </div>
        <div class="pl-cards">
            @foreach($plStages as $s)
            <div class="pl-card" onclick="goProjectsByStage('{{ $s['go'] }}')">
                <div class="plc-label"><span class="plc-dot" style="background:{{ $s['color'] }};"></span>{{ $s['label'] }}</div>
                <div class="plc-value">{{ number_format($s['count']) }}</div>
                <div class="plc-sub">{{ $s['sub'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 간단한 현황 --}}
    <div class="section-title"><x-icon name="pin" :size="17"/> 오늘의 현황</div>
    <div class="info-grid">
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('clients','/clients'); else location.href='/clients';">
            <div class="ic-label">의뢰자</div>
            <div class="ic-value">{{ number_format($clientTotal) }}명</div>
            <div class="ic-sub">이번 달 +{{ $clientThisMonth }}</div>
        </div>
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">
            <div class="ic-label">이번 달 일정</div>
            <div class="ic-value">{{ $scheduleThisMonth }}건</div>
            <div class="ic-sub">{{ now()->format('n') }}월 기준</div>
        </div>
        @if(($rentalActive ?? 0) > 0 || ($broadcastActive ?? 0) > 0)
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('rental-contracts','/rental-contracts'); else location.href='/rental-contracts';">
            <div class="ic-label"><x-icon name="home" :size="15"/> 렌탈 진행중</div>
            <div class="ic-value">{{ $rentalActive ?? 0 }}건</div>
            <div class="ic-sub">월 {{ number_format($rentalMonthlyRevenue ?? 0) }}원</div>
        </div>
        <div class="info-card" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('broadcast-room','/broadcast-room'); else location.href='/broadcast-room';">
            <div class="ic-label"><x-icon name="mic" :size="15"/> 방송룸 월계약</div>
            <div class="ic-value">{{ $broadcastActive ?? 0 }}건</div>
            <div class="ic-sub">월 {{ number_format($broadcastMonthlyRevenue ?? 0) }}원</div>
        </div>
        @endif
    </div>

    {{-- 좌: 최근 이슈·상담 진행중 / 우: 공지사항·업데이트 --}}
    <div class="dash-cols">
        <div>
            <div class="section-title">
                <x-icon name="chat" :size="17"/> 최근 이슈 · 상담 진행중
                <span class="section-sub">우선순위가 높은 항목</span>
            </div>
            <div class="consult-list">
                @if($recentConsults->count() > 0)
                    @foreach($recentConsults as $c)
                        {{-- 클릭 시 연결 프로젝트로 이동 (프로젝트 없으면 의뢰자 상세로) --}}
                        <div class="consult-item{{ ($c['project_id'] || $c['client_id']) ? ' clickable' : '' }}"
                            @if($c['project_id'])
                                onclick="if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('projects','/projects/{{ $c['project_id'] }}');}else{location.href='/projects/{{ $c['project_id'] }}';}"
                                title="프로젝트로 이동"
                            @elseif($c['client_id'])
                                onclick="if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openClientDetail({{ $c['client_id'] }});}else{location.href='/clients?open={{ $c['client_id'] }}';}"
                                title="의뢰자 상세로 이동"
                            @endif>
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
                <a class="list-more" href="/projects" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('projects','/projects'); else location.href='/projects';">전체 보기 →</a>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <div class="wiki-widget">
                <div class="ww-head"><span class="ww-type-badge notice">공지사항</span> <span class="ww-sub">최신순</span></div>
                @forelse($wikiNoticeList as $w)
                    <a class="ww-item" href="/wiki/{{ $w->id }}" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki/{{ $w->id }}','{{ addslashes($w->title) }}'); else location.href=this.href;">
                        <span class="ww-title">{{ $w->title }}</span>
                        <span class="ww-date">{{ $w->created_at->format('m.d') }}</span>
                    </a>
                @empty
                    <div class="ww-empty">등록된 공지사항이 없습니다</div>
                @endforelse
                <a class="ww-more" href="/wiki?type=notice" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki?type=notice'); else location.href=this.href;">전체 보기 →</a>
            </div>
            <div class="wiki-widget">
                <div class="ww-head"><span class="ww-type-badge update">업데이트</span> <span class="ww-sub">최신순</span></div>
                @forelse($wikiUpdateList as $w)
                    <a class="ww-item" href="/wiki/{{ $w->id }}" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki/{{ $w->id }}','{{ addslashes($w->title) }}'); else location.href=this.href;">
                        <span class="ww-title">{{ $w->title }}</span>
                        <span class="ww-date">{{ $w->created_at->format('m.d') }}</span>
                    </a>
                @empty
                    <div class="ww-empty">등록된 업데이트가 없습니다</div>
                @endforelse
                <a class="ww-more" href="/wiki?type=update" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki?type=update'); else location.href=this.href;">전체 보기 →</a>
            </div>
        </div>
    </div>

    {{-- 위키 --}}
    <div class="section-title" style="margin-top:28px;"><x-icon name="book" :size="17"/> 위키</div>
    <div class="wiki-widget-grid">
        <div class="wiki-widget">
            <div class="ww-head"><x-icon name="new" :size="15"/> 최신 등록 문서 <span class="ww-sub">최근 3건</span></div>
            @forelse($wikiRecent as $w)
                <a class="ww-item" href="/wiki/{{ $w->id }}" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki/{{ $w->id }}','{{ addslashes($w->title) }}'); else location.href=this.href;">
                    <span class="ww-title">{{ $w->title }}</span>
                    <span class="ww-date">{{ $w->created_at->format('m.d') }}</span>
                </a>
            @empty
                <div class="ww-empty">등록된 문서가 없습니다</div>
            @endforelse
        </div>
        <div class="wiki-widget">
            <div class="ww-head"><x-icon name="books" :size="15"/> 전체 문서 <span class="ww-sub">총 {{ $wikiTotal }}건</span></div>
            @forelse($wikiAll as $w)
                <a class="ww-item" href="/wiki/{{ $w->id }}" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki/{{ $w->id }}','{{ addslashes($w->title) }}'); else location.href=this.href;">
                    <span class="ww-title">{{ $w->is_pinned ? '📌 ' : '' }}{{ $w->title }}</span>
                    <span class="ww-date">{{ $w->updated_at->format('m.d') }}</span>
                </a>
            @empty
                <div class="ww-empty">등록된 문서가 없습니다</div>
            @endforelse
            @if($wikiTotal > 5)
                <a class="ww-more" href="/wiki" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki'); else location.href=this.href;">전체 보기 →</a>
            @endif
        </div>
    </div>

    {{-- 빠른 이동 --}}
    <div class="section-title" style="margin-top:28px;"><x-icon name="link" :size="17"/> 빠른 이동</div>
    <div class="shortcut-grid">
        <a href="/calendar" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href=this.href;">
            <div class="sc-icon"><x-icon name="calendar" :size="26"/></div>
            <div class="sc-label">캘린더</div>
            <div class="sc-sub">일정 확인</div>
        </a>
        <a href="/clients" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('clients','/clients'); else location.href=this.href;">
            <div class="sc-icon"><x-icon name="user" :size="26"/></div>
            <div class="sc-label">의뢰자</div>
            <div class="sc-sub">CRM 관리</div>
        </a>
        <a href="/projects" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('projects','/projects'); else location.href=this.href;">
            <div class="sc-icon"><x-icon name="folder" :size="26"/></div>
            <div class="sc-label">프로젝트</div>
            <div class="sc-sub">전체 목록</div>
        </a>
        <a href="/estimates" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('estimates','/estimates'); else location.href=this.href;">
            <div class="sc-icon"><x-icon name="note" :size="26"/></div>
            <div class="sc-label">견적서</div>
        </a>
        <a href="/inventory" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('inventory','/inventory'); else location.href=this.href;">
            <div class="sc-icon"><x-icon name="box" :size="26"/></div>
            <div class="sc-label">재고</div>
        </a>
        <a href="/rental-equipment" class="shortcut-card" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('rental','/rental-equipment'); else location.href=this.href;">
            <div class="sc-icon"><x-icon name="camera" :size="26"/></div>
            <div class="sc-label">장비 위치</div>
        </a>
    </div>
</div>

<script>
function goProjectsByStage(stage) {
    // stage는 단일('visit') 또는 콤마구분('equipment,proposal,estimate') 모두 가능
    const url = '/projects?stage=' + encodeURIComponent(stage);
    if (window.parent && window.parent.drgoTabs) {
        window.parent.drgoTabs.openNav('projects', url);
    } else {
        location.href = url;
    }
}
</script>
@endsection
