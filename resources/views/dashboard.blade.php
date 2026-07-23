@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '대시보드 - 닥터고블린 오피스')

@push('styles')
<style>
    .dash-wrap { padding:24px; max-width:1240px; margin:0 auto; }
    .dash-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
    .dash-header h1 { font-size:20px; font-weight:700; }
    .dash-header p { font-size:12px; color:var(--text-muted); margin-top:4px; }

    .section-title { font-size:13px; font-weight:600; color:var(--accent); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .section-title .section-sub { font-size:11px; color:var(--text-muted); font-weight:400; margin-left:auto; }

    /* 전체 2컬럼 (본문 + 우측 레일) */
    .dash-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:16px; align-items:start; }
    .dash-main { display:flex; flex-direction:column; gap:16px; min-width:0; }
    .dash-side { display:flex; flex-direction:column; gap:16px; }
    .side-stack { display:flex; flex-direction:column; gap:16px; } /* 공지+업데이트 묶음 — 그리드에서 한 칸 차지 */
    /* 중간 해상도(~1200px): 사이드 레일을 본문 위 그리드로 재배치 (잔금·휴가·공지+업데이트 순) */
    @media (max-width:1200px) {
        .dash-grid { grid-template-columns:1fr; }
        .dash-side { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); align-items:start; order:-1; }
    }
    @media (max-width:700px) { .dash-side { grid-template-columns:1fr; } }

    /* 공통 카드 */
    .dcard { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .dcard-head { display:flex; align-items:center; gap:8px; padding:11px 16px; background:var(--surface2); border-bottom:1px solid var(--border); font-size:13px; font-weight:700; }
    .dcard-head .dc-sub { font-size:11px; color:var(--text-muted); font-weight:400; }
    .dcard-head .dc-count { font-size:10px; font-weight:700; background:var(--accent); color:var(--accent-text); border-radius:9px; padding:1px 8px; }
    .dcard-head .dc-link { margin-left:auto; font-size:11px; color:var(--accent); text-decoration:none; font-weight:600; white-space:nowrap; }
    .dcard-empty { padding:20px; text-align:center; color:var(--text-muted); font-size:12px; }

    /* 오늘의 일정 */
    .ts-row { display:flex; align-items:center; gap:12px; padding:11px 16px; border-bottom:1px solid var(--border); cursor:pointer; }
    .ts-row:last-child { border-bottom:none; }
    .ts-row:hover { background:var(--surface2); }
    .ts-time { font-size:12.5px; font-weight:700; min-width:86px; white-space:nowrap; }
    .ts-time .ts-end { color:var(--text-muted); font-weight:400; }
    .ts-accent { width:3px; height:30px; border-radius:2px; flex:none; }
    .ts-body { flex:1; min-width:0; }
    .ts-title { font-size:13px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ts-title.done { text-decoration:line-through; color:var(--text-muted); }
    .ts-sub { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .ts-chip { flex:none; font-size:10.5px; font-weight:700; border:1px solid var(--border); border-radius:8px; padding:3px 9px; color:var(--text-muted); background:var(--surface); }

    /* 업무 현황 — 스탯 스트립 */
    .ws-strip { display:grid; grid-template-columns:repeat(4,1fr); }
    .ws-cell { padding:14px 16px; border-right:1px solid var(--border); cursor:pointer; }
    .ws-cell:last-child { border-right:none; }
    .ws-cell:hover { background:var(--surface2); }
    .ws-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .ws-value { font-size:22px; font-weight:800; line-height:1; }
    .ws-sub { font-size:11px; color:var(--text-muted); margin-top:5px; }
    @media (max-width:700px) { .ws-strip { grid-template-columns:repeat(2,1fr); } .ws-cell:nth-child(2) { border-right:none; } }

    /* 주목 프로젝트 */
    .fp-row { display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid var(--border); cursor:pointer; }
    .fp-row:last-child { border-bottom:none; }
    .fp-row:hover { background:var(--surface2); }
    .fp-dday { flex:none; font-size:11.5px; font-weight:800; background:#3A5683; color:#fff; border-radius:7px; padding:4px 10px; }
    .fp-dday.d-urgent { background:var(--red); }
    .fp-client { font-weight:700; font-size:13px; min-width:80px; }
    .fp-desc { flex:1; min-width:0; font-size:12.5px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .fp-chip { flex:none; font-size:10.5px; font-weight:700; border-radius:8px; padding:3px 9px; border:1px solid var(--border); color:var(--text-muted); }
    .fp-chip.confirmed { background:rgba(47,158,68,0.12); color:#2f9e44; border-color:rgba(47,158,68,0.4); }
    .fp-chip.suggest { background:rgba(138,180,200,0.15); color:#5b8aa6; border-color:rgba(138,180,200,0.45); }
    .fp-chip.hope { background:rgba(200,176,138,0.15); color:#a8834a; border-color:rgba(200,176,138,0.5); }
    .fp-chip.target { background:rgba(122,200,122,0.14); color:#4a9a4a; border-color:rgba(122,200,122,0.45); }

    /* 잔금 미수 */
    .ob-row { display:flex; align-items:center; gap:10px; padding:11px 16px; border-bottom:1px solid var(--border); cursor:pointer; }
    .ob-row:hover { background:var(--surface2); }
    .ob-body { flex:1; min-width:0; }
    .ob-label { font-size:13px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ob-sub { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .ob-amt { flex:none; font-size:13.5px; font-weight:800; }
    .ob-total { display:flex; align-items:center; justify-content:space-between; padding:11px 16px; font-size:12.5px; font-weight:700; background:var(--surface2); }
    .ob-total b { font-size:15px; color:var(--red); }

    /* 휴가 (우측) */
    .vc-row { display:flex; align-items:center; gap:10px; padding:11px 16px; border-bottom:1px solid #f0f2f5; cursor:pointer; }
    .vc-row:last-child { border-bottom:none; }
    .vc-row:hover { background:#f7f8fa; }
    .vc-body { flex:1; min-width:0; }
    .vc-name { font-size:13px; font-weight:700; color:var(--d-ink, var(--text)); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vc-sub { font-size:11px; color:var(--d-muted, var(--text-muted)); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vc-chip { flex:none; font-size:10.5px; font-weight:800; border-radius:8px; padding:3px 9px; border:1px solid #dfe4ee; color:#3A5683; background:#f3f5f9; }
    .vc-chip.on { background:#c87a7a; border-color:#c87a7a; color:#fff; }

    /* 나의 할 일 */
    .td-row { display:flex; align-items:center; gap:9px; padding:11px 16px; border-bottom:1px solid #f0f2f5; cursor:pointer; }
    .td-row:last-child { border-bottom:none; }
    .td-row:hover { background:#f7f8fa; }
    .td-dot { flex:none; width:8px; height:8px; border-radius:50%; background:#c9d0dc; }
    .td-dot.high { background:#d64545; }
    .td-dot.medium { background:#e8a13a; }
    .td-dot.low { background:#57ab5a; }
    .td-title { font-size:12.5px; font-weight:600; color:#242a35; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .td-dday { flex:none; margin-left:auto; font-size:10.5px; font-weight:800; border-radius:8px; padding:3px 9px; border:1px solid #dfe4ee; color:#3A5683; background:#f3f5f9; }
    .td-dday.today { background:#fdf1e3; border-color:#f0ddc0; color:#b26a00; }
    .td-dday.over { background:#fdecea; border-color:#f2cfca; color:#c0392b; }

    /* 공지·업데이트 (우측) */
    .nu-row { display:flex; align-items:center; gap:9px; padding:10px 16px; border-bottom:1px solid var(--border); cursor:pointer; }
    .nu-row:last-child { border-bottom:none; }
    .nu-row:hover { background:var(--surface2); }
    .nu-badge { flex:none; font-size:9.5px; font-weight:800; border-radius:6px; padding:2px 7px; }
    .nu-badge.notice { background:#3A5683; color:#fff; }
    .nu-badge.update { background:rgba(58,86,131,0.12); color:#3A5683; }
    .nu-title { flex:1; min-width:0; font-size:12.5px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .nu-date { flex:none; font-size:11px; color:var(--text-muted); }

    /* 상담 현황 — 세그먼트 바 + 단계 카드 (한 컨테이너) */
    .pl-wrap { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; margin-bottom:20px; }
    .pl-head { display:flex; align-items:baseline; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
    .pl-title { font-size:13.5px; font-weight:700; }
    .pl-sub { font-size:11px; color:var(--text-muted); }
    .pl-total { margin-left:auto; font-size:12px; color:var(--text-muted); }
    .pl-total b { color:var(--text); }
    .pl-bar { display:flex; height:8px; border-radius:999px; overflow:hidden; background:var(--surface2); margin-bottom:14px; gap:2px; }
    .pl-bar span { display:block; height:100%; border-radius:999px; min-width:4px; }
    /* 폭이 좁아지면 카드가 자동 줄바꿈 — 고정 5열이 우측 컬럼 밑으로 넘치던 문제 방지 */
    .pl-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; }
    .pl-card { border:1px solid var(--border); border-radius:10px; padding:12px 14px; cursor:pointer; background:var(--surface); transition:all 0.15s; min-width:0; }
    .pl-card:hover { border-color:var(--accent); transform:translateY(-1px); }
    .pl-card .plc-label { font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:6px; margin-bottom:7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pl-card .plc-dot { width:8px; height:8px; border-radius:50%; flex:none; }
    .pl-card .plc-value { font-size:26px; font-weight:800; line-height:1; }
    .pl-card .plc-sub { font-size:10.5px; color:var(--text-muted); margin-top:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    @media (max-width:560px) { .pl-cards { grid-template-columns:repeat(2, 1fr); } }

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

    /* ══ 목업 팔레트 — 웜그레이 배경 + 화이트 카드 + 네이비 포인트 ══ */
    body { background:#f0f1f4; }
    .dash-wrap { --d-navy:#1f2b40; --d-accent:#3A5683; --d-ink:#1c2433; --d-muted:#7d8698; --d-border:#e3e6ec; }
    .dash-header h1 { font-size:22px; letter-spacing:-0.3px; color:var(--d-ink); }
    .dash-header p { color:var(--d-muted); }
    /* 카드 공통 */
    .dcard, .pl-wrap { background:#fff; border-color:var(--d-border); box-shadow:0 1px 2px rgba(16,24,40,0.04); }
    .dcard-head { background:#f7f8fa; border-bottom-color:#edeff3; color:var(--d-ink); font-weight:800; }
    .dcard-head .dc-sub { color:var(--d-muted); }
    .dcard-head .dc-link { color:var(--d-accent); }
    .dcard-head .dc-count { background:#d64545; color:#fff; } /* 건수 배지 — 붉은 계열로 강조 */
    .dcard-empty { color:var(--d-muted); }
    /* 행 구분/hover */
    .ts-row, .fp-row, .ob-row, .nu-row, .consult-item { border-bottom-color:#f0f2f5; }
    .ts-row:hover, .fp-row:hover, .ob-row:hover, .nu-row:hover, .consult-item:hover { background:#f7f8fa; }
    /* 텍스트 톤 */
    .ts-time, .ts-title, .ws-value, .plc-value, .fp-client, .ob-label, .ob-amt, .nu-title, .consult-client, .pl-title { color:var(--d-ink); }
    .ts-sub, .ws-label, .ws-sub, .plc-label, .plc-sub, .fp-desc, .ob-sub, .nu-date, .pl-sub, .consult-content, .consult-meta { color:var(--d-muted); }
    .pl-total { color:var(--d-muted); } .pl-total b { color:var(--d-ink); }
    /* 칩/버튼 네이비 포인트 */
    .ts-chip { color:var(--d-accent); border-color:#dfe4ee; background:#f3f5f9; }
    .consult-badge.in_progress { background:rgba(58,86,131,0.12); color:var(--d-accent); }
    .consult-badge.waiting { background:#eef0f4; color:var(--d-muted); }
    .ws-cell, .pl-card { border-color:var(--d-border); }
    .ws-cell:hover, .pl-card:hover { background:#f7f8fa; border-color:var(--d-accent); }
    .pl-bar { background:#eef0f4; }
    .ob-total { background:#f7f8fa; }
    .ob-total b { color:var(--d-navy); }
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
            <a href="/marketing-report" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('marketing-report','/marketing-report'); else location.href='/marketing-report';" style="background:#1f2b40; color:#fff; padding:9px 18px; border-radius:9px; font-size:12.5px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:5px;"><x-icon name="chart" :size="15"/> 상세 통계 →</a>
        @endif
    </div>

    <div class="dash-grid">
    <div class="dash-main">

    {{-- ① 오늘의 일정 --}}
    <div class="dcard">
        <div class="dcard-head">
            오늘의 일정
            @if($todaySchedules->count())<span class="dc-count">{{ $todaySchedules->count() }}</span>@endif
            <a class="dc-link" href="/calendar" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">캘린더로 이동 →</a>
        </div>
        @forelse($todaySchedules as $s)
            <div class="ts-row" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">
                <span class="ts-time">{{ $s['time'] }}@if($s['time_end'])<span class="ts-end"> – {{ $s['time_end'] }}</span>@endif</span>
                {{-- 카테고리 색상 — 캘린더 관리에서 설정한 실제 색과 동기화 --}}
                <span class="ts-accent" style="background:{{ $catColors[$s['color']] ?? '#8ba3c7' }};"></span>
                <span class="ts-body">
                    <span class="ts-title {{ $s['completed'] ? 'done' : '' }}">{{ $s['title'] }}</span>
                    <div class="ts-sub">{{ $s['category'] }}{{ $s['assignees'] ? ' · 담당 '.$s['assignees'] : '' }}</div>
                </span>
                <span class="ts-chip">{{ $s['category'] }}</span>
            </div>
        @empty
            <div class="dcard-empty">오늘 예정된 일정이 없습니다</div>
        @endforelse
    </div>

    {{-- ② 상담 현황 — 세그먼트 바 + 단계 카드 --}}
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

    {{-- ③ 업무 현황 — 4개 스탯 한 카드 --}}
    <div class="dcard">
        <div class="dcard-head">업무 현황 <span class="dc-sub" style="margin-left:auto;">이번 달</span></div>
        <div class="ws-strip">
            <div class="ws-cell" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('clients','/clients'); else location.href='/clients';">
                <div class="ws-label">의뢰자</div>
                <div class="ws-value">{{ number_format($clientTotal) }}</div>
                <div class="ws-sub">+{{ $clientThisMonth }}</div>
            </div>
            <div class="ws-cell" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">
                <div class="ws-label">일정</div>
                <div class="ws-value">{{ $scheduleThisMonth }}건</div>
                <div class="ws-sub">{{ now()->format('n') }}월</div>
            </div>
            <div class="ws-cell" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('rental-contracts','/rental-contracts'); else location.href='/rental-contracts';">
                <div class="ws-label">렌탈</div>
                <div class="ws-value">{{ $rentalActive ?? 0 }}건</div>
                <div class="ws-sub">월 {{ number_format($rentalMonthlyRevenue ?? 0) }}원</div>
            </div>
            <div class="ws-cell" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('broadcast-room','/broadcast-room'); else location.href='/broadcast-room';">
                <div class="ws-label">방송룸</div>
                <div class="ws-value">{{ $broadcastActive ?? 0 }}건</div>
                <div class="ws-sub">월 {{ number_format($broadcastMonthlyRevenue ?? 0) }}원</div>
            </div>
        </div>
    </div>

    {{-- ④ 주목 프로젝트 — 임박한 목표·제안·확정 일정 --}}
    <div class="dcard">
        <div class="dcard-head">
            주목 프로젝트 <span class="dc-sub">목표 · 제안 일정이 다가오는 건</span>
            <a class="dc-link" href="/calendar" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">전체 보기 →</a>
        </div>
        @forelse($focusSchedules as $f)
            <div class="fp-row" onclick="{{ $f['project_id'] ? "if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('projects','/projects/".$f['project_id']."');}else{location.href='/projects/".$f['project_id']."';}" : "if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('calendar','/calendar');}else{location.href='/calendar';}" }}">
                <span class="fp-dday {{ $f['dday'] <= 3 ? 'd-urgent' : '' }}">{{ $f['dday'] === 0 ? '오늘' : 'D-'.$f['dday'] }}</span>
                <span class="fp-client">{{ $f['client'] }}</span>
                <span class="fp-desc">{{ $f['opt_label'] }} {{ $f['date'] }} · {{ $f['desc'] }}</span>
                <span class="fp-chip {{ $f['opt'] }}">{{ $f['opt_label'] }}</span>
            </div>
        @empty
            <div class="dcard-empty">다가오는 목표·제안·확정 일정이 없습니다</div>
        @endforelse
    </div>

    {{-- ⑤ 최근 이슈 · 상담 진행중 --}}
    <div class="dcard">
        <div class="dcard-head">
            최근 이슈 · 상담 진행중 <span class="dc-sub">우선순위가 높은 항목</span>
            <a class="dc-link" href="/projects" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('projects','/projects'); else location.href='/projects';">전체 보기 →</a>
        </div>
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
            <div class="dcard-empty">대기/진행중 상담이 없습니다</div>
        @endif
    </div>

    </div>{{-- /dash-main --}}

    {{-- 우측 레일 --}}
    <aside class="dash-side">
        {{-- 잔금 미수 --}}
        @if(Auth::user()->hasPermission('projects.view'))
        <div class="dcard">
            <div class="dcard-head">
                잔금 미수
                @if($outstandingCount)<span class="dc-count">{{ $outstandingCount }}</span>@endif
                <a class="dc-link" href="/projects-billing" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('projects','/projects-billing'); else location.href='/projects-billing';">전체 →</a>
            </div>
            @forelse($outstanding as $o)
                <div class="ob-row" onclick="if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('projects','/projects/{{ $o['project_id'] }}');}else{location.href='/projects/{{ $o['project_id'] }}';}">
                    <span class="ob-body">
                        <div class="ob-label">{{ $o['label'] }}</div>
                        <div class="ob-sub">{{ $o['sub'] }}</div>
                    </span>
                    <span class="ob-amt">{{ number_format($o['balance']) }}원</span>
                </div>
            @empty
                <div class="dcard-empty">잔금이 남은 프로젝트가 없습니다</div>
            @endforelse
            @if($outstandingCount)
                <div class="ob-total"><span>합계</span><b>{{ number_format($outstandingTotal) }}원</b></div>
            @endif
        </div>
        @endif

        {{-- 휴가 — 휴가/개인 카테고리, 오늘 ~ D-7 --}}
        <div class="dcard">
            <div class="dcard-head">
                휴가
                @if($vacations->count())<span class="dc-count">{{ $vacations->count() }}</span>@endif
                <span class="dc-sub" style="margin-left:auto;">D-7까지</span>
            </div>
            @forelse($vacations as $v)
                <div class="vc-row" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('calendar','/calendar'); else location.href='/calendar';">
                    <span class="vc-body">
                        <div class="vc-name">{{ $v['name'] }}</div>
                        <div class="vc-sub">{{ $v['title'] }}{{ $v['title'] && $v['period'] ? ' · ' : '' }}{{ $v['period'] }}</div>
                    </span>
                    <span class="vc-chip {{ $v['ongoing'] ? 'on' : '' }}">{{ $v['dday'] }}</span>
                </div>
            @empty
                <div class="dcard-empty">예정된 휴가가 없습니다</div>
            @endforelse
        </div>

        {{-- 나의 할 일 --}}
        <div class="dcard">
            <div class="dcard-head">
                나의 할 일
                @if($myTodoCount)<span class="dc-count">{{ $myTodoCount }}</span>@endif
                <a class="dc-link" href="/todos" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('todos','/todos'); else location.href='/todos';">전체 →</a>
            </div>
            @forelse($myTodos as $t)
                <div class="td-row" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('todos','/todos'); else location.href='/todos';">
                    <span class="td-dot {{ $t['priority'] }}"></span>
                    <span class="td-title">{{ $t['title'] }}</span>
                    @if($t['dday'])
                        <span class="td-dday {{ $t['overdue'] ? 'over' : ($t['dday'] === '오늘 마감' ? 'today' : '') }}">{{ $t['dday'] }}</span>
                    @endif
                </div>
            @empty
                <div class="dcard-empty">등록된 할 일이 없습니다</div>
            @endforelse
        </div>

        {{-- 공지사항 + 업데이트 — 반응형 그리드에서 한 칸을 차지하도록 묶음 --}}
        <div class="side-stack">
        {{-- 공지사항 (최근 2건) --}}
        <div class="dcard">
            <div class="dcard-head">
                공지사항
                <a class="dc-link" href="/wiki?type=notice" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki?type=notice'); else location.href=this.href;">전체 보기 →</a>
            </div>
            @forelse($wikiNoticeList as $w)
                <div class="nu-row" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki/{{ $w->id }}','{{ addslashes($w->title) }}'); else location.href='/wiki/{{ $w->id }}';">
                    <span class="nu-badge notice">공지</span>
                    <span class="nu-title">{{ $w->title }}</span>
                    <span class="nu-date">{{ $w->created_at->format('m.d') }}</span>
                </div>
            @empty
                <div class="dcard-empty">등록된 공지사항이 없습니다</div>
            @endforelse
        </div>

        {{-- 업데이트 (최근 1건) --}}
        <div class="dcard">
            <div class="dcard-head">
                업데이트
                <a class="dc-link" href="/wiki?type=update" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki?type=update'); else location.href=this.href;">전체 보기 →</a>
            </div>
            @forelse($wikiUpdateList as $w)
                <div class="nu-row" onclick="if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('wiki','/wiki/{{ $w->id }}','{{ addslashes($w->title) }}'); else location.href='/wiki/{{ $w->id }}';">
                    <span class="nu-badge update">업뎃</span>
                    <span class="nu-title">{{ $w->title }}</span>
                    <span class="nu-date">{{ $w->created_at->format('m.d') }}</span>
                </div>
            @empty
                <div class="dcard-empty">등록된 업데이트가 없습니다</div>
            @endforelse
        </div>
        </div>{{-- /side-stack --}}
    </aside>
    </div>{{-- /dash-grid --}}

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
