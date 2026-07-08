@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '캘린더 - 닥터고블린 오피스')
{{-- 캘린더 전용 PWA — 홈 화면에 추가 시 캘린더로 시작 --}}
@section('pwa_manifest', '/manifest-calendar.json')
@section('pwa_title', '닥터고블린 캘린더')

@push('styles')
<style>
    /* ── 라이트모드 캘린더 보정 ── */
    [data-theme="light"] .day-cell { background:var(--surface); }
    [data-theme="light"] .day-cell.other-month { background:var(--surface2); opacity:0.6; }
    [data-theme="light"] .day-cell.today .day-num { background:var(--accent); color:#fff !important; }
    [data-theme="light"] .event-chip.single { background:var(--chip-single-bg); color:var(--text); }
    [data-theme="light"] .dt-input { color-scheme:light; }
    [data-theme="light"] .modal { box-shadow:0 8px 40px rgba(0,0,0,0.12); }
    [data-theme="light"] .modal-overlay { background:rgba(0,0,0,0.45); }
    [data-theme="light"] .time-picker-popup { box-shadow:0 8px 32px rgba(0,0,0,0.15); }
    [data-theme="light"] .tp-item.selected { background:rgba(59,94,160,0.15); color:var(--accent); }
    [data-theme="light"] .notif-select { color-scheme:light; }
    /* 라이트모드 버튼 대비 강화 */
    [data-theme="light"] .nav-btn { border-color:#a0a8b4; color:#5a6070; }
    [data-theme="light"] .icon-btn { border-color:#a0a8b4; color:#5a6070; }
    [data-theme="light"] .radio-btn { border-color:#b0b8c4; color:#4a5060; }
    [data-theme="light"] .radio-btn.active { background:var(--accent); border-color:var(--accent); color:#fff; }
    [data-theme="light"] .special-opt-btn { border-color:#b0b8c4; color:#4a5060; background:#f0f1f4; }
    [data-theme="light"] .special-opt-btn.active { background:rgba(59,94,160,0.12); border-color:var(--accent); color:var(--accent); }
    [data-theme="light"] .sched-opt-btn { border-color:#b0b8c4; color:#4a5060; background:#f0f1f4; }
    [data-theme="light"] .filter-btn { border-color:#a0a8b4; color:#4a5060; }
    [data-theme="light"] .add-btn { background:var(--accent); color:#fff; }
    [data-theme="light"] .btn-save { background:var(--accent); color:#fff; }
    [data-theme="light"] .btn-save-top { background:var(--accent); color:#fff; }
    [data-theme="light"] .modal-external-action { background:var(--accent); color:#fff; }
    [data-theme="light"] .modal-external-close { background:#fff; border-color:#a0a8b4; }
    [data-theme="light"] .action-btn { border-color:#a0a8b4; color:#4a5060; background:#f0f1f4; }
    [data-theme="light"] .field-input, [data-theme="light"] .field-textarea { border-color:#b8bcc8; background:#fff; }
    [data-theme="light"] .field-section { background:#f4f5f7; border-color:#d0d4dc; }
    [data-theme="light"] .field-section .field-input, [data-theme="light"] .field-section .field-textarea { background:#fff; }
    [data-theme="light"] .field-section .field-label { color:var(--accent); }
    [data-theme="light"] .datetime-section { background:#f4f5f7; border-color:#d0d4dc; }
    [data-theme="light"] .dt-input { background:#fff; border-color:#b8bcc8; color-scheme:light; }
    [data-theme="light"] .color-dot { border-color:transparent; }
    [data-theme="light"] .span-chip { color:#fff; }
    [data-theme="light"] .tl-event { color:#fff; }
    [data-theme="light"] .day-cell.today .day-num { color:#fff; }
    [data-theme="light"] .tl-day-num.today-num { color:#fff; }
    [data-theme="light"] .tp-confirm-btn { color:#fff; }
    [data-theme="light"] .assignee-chip.selected { color:#fff; }
    [data-theme="light"] .radio-btn.active-green { color:#fff; }

    .cal-header { padding:16px 24px; display:flex; justify-content:space-between; align-items:center; gap:12px 16px; flex-wrap:wrap; border-bottom:1px solid var(--border); background:var(--bg); position:sticky; top:0; z-index:10; }
    .cal-header-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .cal-header-right { flex-wrap:wrap; }
    /* 헤더 버튼: 절대 줄바꿈/찌그러짐 없이 한 줄 유지, 공간 부족 시 그룹 단위로 다음 줄로 래핑 */
    .cal-header .nav-btn, .cal-header .add-btn, .cal-header .view-toggle-btn, .cal-header .month-label { white-space:nowrap; flex-shrink:0; }
    .view-toggle-group { flex-shrink:0; }
    .app-title { font-size:13px; letter-spacing:0.2em; color:var(--accent); text-transform:uppercase; }
    .nav-btn { background:none; border:1px solid var(--border); color:var(--text-muted); cursor:pointer; width:32px; height:32px; border-radius:6px; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); }
    .month-label { font-size:18px; font-weight:500; letter-spacing:0.05em; min-width:180px; text-align:center; }

    /* 일정 검색 */
    .cal-search-wrap { position:relative; flex-shrink:1; min-width:0; }
    .cal-search-input { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:7px 12px; color:var(--text); font-size:12px; outline:none; width:170px; max-width:100%; transition:border-color .15s, width .2s; }
    .cal-search-input:focus { border-color:var(--accent); width:220px; }
    .cal-search-results { position:absolute; top:calc(100% + 6px); right:0; width:340px; max-width:calc(100vw - 24px); max-height:420px; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:var(--card-shadow, 0 8px 32px rgba(0,0,0,0.3)); z-index:60; padding:6px; }
    .cal-sr-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; cursor:pointer; }
    .cal-sr-item:hover { background:var(--surface2); }
    .cal-sr-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .cal-sr-date { font-size:11px; color:var(--text-muted); flex-shrink:0; min-width:74px; font-family:ui-monospace,Menlo,monospace; }
    .cal-sr-title { font-size:12px; font-weight:600; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cal-sr-item.is-completed .cal-sr-title { text-decoration:line-through; opacity:0.55; }
    .cal-sr-sub { font-size:11px; color:var(--text-muted); flex-shrink:0; max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cal-sr-empty { padding:14px; text-align:center; font-size:12px; color:var(--text-muted); }
    .agenda-search-head { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 4px; border-bottom:1px solid var(--border); font-size:13px; }
    .cal-fontsize { display:flex; align-items:center; gap:2px; background:var(--surface2); border-radius:8px; padding:2px; }
    .cal-fz-btn { border:none; background:none; color:var(--text-muted); cursor:pointer; border-radius:6px; padding:4px 9px; font-size:13px; font-weight:700; line-height:1; }
    .cal-fz-btn:hover { background:var(--surface); color:var(--accent); }
    .cal-fontsize #calFontLabel { font-size:11px; color:var(--text-muted); min-width:36px; text-align:center; }
    .view-toggle-group { display:flex; background:var(--surface2); border-radius:8px; padding:2px; gap:2px; }
    .view-toggle-btn { padding:5px 14px; border-radius:6px; font-size:12px; cursor:pointer; border:none; background:none; color:var(--text-muted); transition:all 0.15s; }
    .view-toggle-btn.active { background:var(--surface); color:var(--accent); font-weight:600; }
    /* ── 목록(아젠다) 뷰 ── */
    .agenda-strip { display:flex; gap:4px; max-width:900px; margin:0 auto; padding:12px 12px 6px; }
    .agenda-day-btn { flex:1; min-width:0; display:flex; flex-direction:column; align-items:center; gap:5px; padding:4px 0 16px; border:none; background:none; cursor:pointer; position:relative; }
    .agenda-day-btn .adb-dow { font-size:11px; color:var(--text-muted); }
    /* 날짜는 동그란 칸 */
    .agenda-day-btn .adb-num { font-size:15px; font-weight:700; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:background .15s, color .15s; }
    .agenda-day-btn:hover .adb-num { background:var(--surface2); }
    .agenda-day-btn.active .adb-num { background:var(--accent); color:var(--accent-text); }
    .agenda-day-btn .adb-dow.sun, .agenda-day-btn .adb-num.sun { color:var(--red); }
    .agenda-day-btn .adb-dow.sat, .agenda-day-btn .adb-num.sat { color:#5b8def; }
    .agenda-day-btn.active .adb-num.sun, .agenda-day-btn.active .adb-num.sat { color:var(--accent-text); }
    .agenda-day-btn .adb-dot { width:5px; height:5px; border-radius:50%; background:var(--accent); position:absolute; bottom:5px; }
    .agenda-wrap { max-width:900px; margin:0 auto; padding:8px 16px 40px; }
    .agenda-day { margin-top:14px; }
    .agenda-day:first-child { margin-top:4px; }
    .agenda-date-head { display:flex; align-items:baseline; gap:8px; padding:8px 4px; border-bottom:1px solid var(--border); position:sticky; top:0; background:var(--bg); z-index:2; }
    .agenda-date-head .ad-d { font-size:18px; font-weight:700; }
    .agenda-date-head .ad-dow { font-size:12px; color:var(--text-muted); }
    .agenda-date-head .ad-today { color:var(--accent); }
    .agenda-date-head .ad-sun { color:var(--red); } .agenda-date-head .ad-sat { color:#5b8def; }
    .agenda-item { display:flex; align-items:center; gap:12px; padding:12px 6px; border-bottom:1px solid var(--border); cursor:pointer; transition:background .12s; }
    .agenda-item:hover { background:var(--surface2); }
    .agenda-stripe { width:4px; align-self:stretch; border-radius:2px; flex-shrink:0; min-height:34px; }
    .agenda-main { flex:1; min-width:0; }
    .agenda-title { font-size:14px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .agenda-sub { font-size:12px; color:var(--text-muted); margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .agenda-right { text-align:right; flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
    .agenda-time { font-size:12px; font-weight:600; color:var(--text-muted); }
    .agenda-assignee { font-size:11px; color:var(--text-muted); max-width:90px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .agenda-empty { text-align:center; color:var(--text-muted); padding:60px 20px; font-size:14px; }

    .add-btn { background:var(--accent); color:var(--accent-text); border:none; padding:8px 20px; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; transition:all 0.2s; }
    .add-btn:hover { background:#d4c09a; transform:translateY(-1px); }

    .legend { display:flex; gap:8px; align-items:center; padding:10px 32px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .filter-btn { display:flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; cursor:pointer; border:1px solid var(--border); background:none; font-size:12px; letter-spacing:0.06em; color:var(--text-muted); transition:all 0.18s; user-select:none; flex-shrink:0; }
    .filter-btn:hover { border-color:var(--accent); color:var(--text); }
    .filter-btn .filter-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; transition:all 0.18s; }
    /* 카테고리 색 기반 틴트 — 커스텀 카테고리 포함 모두 동일 적용 */
    .filter-btn.active { color:var(--text); background:color-mix(in srgb, var(--fbtn, var(--accent)) 15%, transparent); border-color:var(--fbtn, var(--accent)); }
    .filter-btn:not(.active) .filter-dot { opacity:0.25; }
    .filter-btn:not(.active) { opacity:0.55; }
    .assignee-filter { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:5px 28px 5px 12px; color:var(--text); font-size:12px; outline:none; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path d='M1 1l4 4 4-4' stroke='%23a09890' stroke-width='1.5' fill='none' stroke-linecap='round'/></svg>"); background-repeat:no-repeat; background-position:right 10px center; }
    .assignee-filter:focus { border-color:var(--accent); }
    .assignee-filter.active-filter { border-color:var(--accent); color:var(--accent); }
    .assignee-filter-chips { display:inline-flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .af-chip { display:inline-flex; align-items:center; gap:5px; background:var(--accent); color:var(--accent-text); border-radius:20px; padding:4px 8px 4px 12px; font-size:12px; font-weight:600; line-height:1; }
    .af-chip button { background:rgba(0,0,0,0.18); color:inherit; border:none; width:16px; height:16px; border-radius:50%; cursor:pointer; font-size:11px; line-height:1; display:inline-flex; align-items:center; justify-content:center; padding:0; }
    .af-chip button:hover { background:rgba(0,0,0,0.35); }
    /* ── 하루 일정 팝오버 ── */
    .day-popover-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.3); z-index:500; }
    .day-popover-overlay.open { display:block; }
    .day-popover { position:fixed; z-index:501; width:300px; max-height:60vh; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,0.4); padding:14px; }
    .day-popover .dp-header { display:flex; justify-content:space-between; align-items:center; font-size:14px; font-weight:700; margin-bottom:10px; position:sticky; top:0; background:var(--surface); }
    .day-popover .dp-close { background:none; border:none; color:var(--text-muted); font-size:16px; cursor:pointer; padding:0 4px; }
    .day-popover .dp-list { display:flex; flex-direction:column; gap:6px; }
    .day-popover .dp-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; cursor:pointer; transition:background 0.12s; }
    .day-popover .dp-item:hover { background:var(--surface2); }
    .day-popover .dp-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .day-popover .dp-info { flex:1; min-width:0; }
    .day-popover .dp-title-row { display:flex; align-items:flex-start; gap:8px; }
    /* 제목: 한 줄 말줄임 대신 폰트 축소 + 2줄까지 줄바꿈 표시 */
    .day-popover .dp-title { font-size:calc(12px * var(--cal-fz,1)); font-weight:500; flex:1; min-width:0; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; word-break:break-all; line-height:1.45; }
    .day-popover .dp-assignee { flex-shrink:0; font-size:calc(11px * var(--cal-fz,1)); font-weight:600; color:var(--text-muted); background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:1px 8px; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .day-popover .dp-meta { font-size:calc(11px * var(--cal-fz,1)); color:var(--text-muted); margin-top:2px; }
    .day-popover .dp-time { font-size:calc(11px * var(--cal-fz,1)); font-weight:600; color:var(--text-muted); flex-shrink:0; min-width:38px; }
    [data-theme="light"] .assignee-filter { background-color:#fff; border-color:#a0a8b4; color:#4a5060; }

    /* ── 월간 뷰 ── */
    .calendar-wrap { padding:20px 32px; }
    /* 좁은 데스크톱: 여백 축소로 그리드 최대화 */
    @media (min-width:769px) and (max-width:1280px) {
        .calendar-wrap { padding:12px 16px; }
        .legend { padding:8px 16px; }
        .timeline-wrap { padding:0 12px 16px; }
    }
    .weekdays { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; margin-bottom:4px; }
    .weekday { text-align:center; font-size:calc(13px * var(--cal-fz,1)); letter-spacing:0.12em; color:var(--text-muted); padding:8px 0; }
    .weekday:first-child { color:var(--red); }
    .weekday:last-child { color:var(--accent2); }
    .days-grid { border:1px solid var(--border); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; gap:1px; background:var(--border); }
    .week-row { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; position:relative; background:var(--border); min-height:calc(110px * var(--cal-fz,1)); }
    .day-cell { background:var(--surface); min-height:0; padding:6px; position:relative; transition:background 0.15s; cursor:default; overflow:hidden; }
    .day-cell:hover { background:var(--surface2); }
    .day-cell.other-month { background:#111; }
    .day-cell.today .day-num { background:var(--accent); color:var(--accent-text) !important; font-weight:700; border-radius:50%; }
    .day-num-row { display:flex; align-items:center; gap:4px; margin-bottom:2px; min-width:0; }
    .day-num { font-size:calc(13px * var(--cal-fz,1)); color:var(--text-muted); position:relative; z-index:1; width:calc(24px * var(--cal-fz,1)); height:calc(24px * var(--cal-fz,1)); flex-shrink:0; display:flex; align-items:center; justify-content:center; }
    .day-num.sun { color:var(--red); }
    .day-num.sat { color:var(--accent2); }
    .holiday-label { font-size:calc(11px * var(--cal-fz,1)); color:var(--red); opacity:0.85; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; letter-spacing:0.02em; line-height:1; }
    .events-list { display:flex; flex-direction:column; gap:2px; }

    /* ── 이벤트 칩 ── */
    .event-chip { border-radius:4px; padding:2px 6px; font-size:calc(12px * var(--cal-fz,1)); white-space:nowrap; overflow:hidden; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; gap:4px; line-height:1.4; height:calc(22px * var(--cal-fz,1)); box-sizing:border-box; min-width:0; }
    /* 다일 이벤트: 셀 padding(6px) 만큼만 보상해 셀 가장자리까지 닿게. 셀 경계 너머는 침범하지 않음 (overflow:hidden 보호) */
    .event-chip.multi-day.day-start { border-radius:4px 0 0 4px; margin-right:-6px; }
    .event-chip.multi-day.day-cont  { border-radius:0; border-left-color:transparent !important; padding-left:0; padding-right:0; margin-left:-6px; margin-right:-6px; }
    .event-chip.multi-day.day-end   { border-radius:0 4px 4px 0; border-left-color:transparent !important; padding-left:0; margin-left:-6px; }
    /* 한 셀이 동시에 시작·끝(=같은 날) — 단일일과 동일하게 */
    .event-chip.multi-day.day-start.day-end { border-radius:4px; margin:0; }
    /* 다일 일정 연속 bar (셀 위에 떠서 칸을 가로질러 이어짐) */
    .mday-bar { z-index:5; padding:2px 8px; font-size:12px; line-height:1.4; overflow:hidden; cursor:pointer; box-sizing:border-box; display:flex; align-items:center; transition:filter .12s; color:var(--accent-text); }
    .mday-bar:hover { filter:brightness(1.12); }
    .mday-bar .mday-bar-label { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; font-weight:500; }
    /* 카테고리별 바 색 — 관리 설정(라벨/색상)과 자동 연동 (커스텀 카테고리 포함) */
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .mday-bar.color-{{ $__ck }} { background:var(--chip-{{ $__ck }}-bg); color:var(--chip-{{ $__ck }}-text); }
    @endforeach
    .mday-bar.color-holiday{ background:var(--chip-red-bg); color:#fff; }
    .event-chip span { min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    /* 제목은 늘어나서 담당자 배지를 우측 끝으로 밀어냄 */
    .event-chip .chip-title { flex:1 1 auto; }
    .event-chip:hover { filter:brightness(1.12); transform:translateX(1px); }
    /* 완료된 일정 — 흐리게 표시 */
    .event-chip.is-completed, .mday-title-overlay.is-completed, .tl-event.is-completed,
    .agenda-item.is-completed, .dp-item.is-completed, .mde-item.is-completed { opacity:0.4; }
    .agenda-item.is-completed .agenda-title, .dp-item.is-completed .dp-title { text-decoration:line-through; }
    .event-chip.single { background:var(--chip-single-bg); color:var(--text); border-left:3px solid var(--accent); }
    /* 다일 레인 정렬용 빈 자리 (보이지 않지만 칩 1행과 동일한 높이) — 채울 단일이 없을 때만 사용 */
    .lane-spacer { height:calc(22px * var(--cal-fz,1)); visibility:hidden; }
    /* 다일 일정 제목 — 바 전체 폭에 걸쳐 셀 경계를 넘어 흐르는 오버레이 (날짜별로 끊지 않음) */
    .mday-title-overlay { position:absolute; z-index:6; pointer-events:none; display:flex; align-items:center; gap:4px;
        padding:0 8px; box-sizing:border-box; font-size:calc(12px * var(--cal-fz,1)); font-weight:500; color:var(--text);
        white-space:nowrap; overflow:hidden; }
    /* 연속 일정: 담당자 배지를 제목이 끝나는 지점 바로 뒤에 표시(바 끝으로 밀지 않음) */
    .mday-title-overlay .chip-title { flex:0 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mday-title-overlay .chip-badges { margin-left:0; flex-shrink:0; }
    /* 단일 칩 틴트 — 카테고리 색에서 자동 계산 (커스텀 포함) */
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .event-chip.single.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 22%, transparent); border-left-color:var(--chip-{{ $__ck }}-bg); }
    @endforeach
    .chip-time { font-size:calc(12px * var(--cal-fz,1)); opacity:0.85; flex-shrink:0; margin-right:3px; }
    .chip-special { font-size:calc(11px * var(--cal-fz,1)); flex-shrink:0; letter-spacing:1px; }
    /* 배송 상태 아이콘 (✕ 미배송 / △ 일부 완료 / ○ 전부 완료) */
    .chip-ship { flex-shrink:0; font-size:calc(11px * var(--cal-fz,1)); font-weight:800; line-height:1; }
    /* 옵션 아이콘 (주/일간·목록·팝업 공용) */
    .ev-opt-ico { flex-shrink:0; font-size:calc(11px * var(--cal-fz,1)); margin-left:4px; letter-spacing:1px; }
    .tl-ev-title { font-weight:600; }
    .tl-ev-assignee { font-size:9px; opacity:0.85; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:1.3; }
    /* 리스트 제목 옆 배송 아이콘 (담당자보다 앞) */
    .agenda-title .chip-ship, .dp-title .chip-ship, .mde-title .chip-ship { margin-left:6px; font-size:13px; }
    /* 이사세팅 출발/도착 2줄 */
    .agenda-move { display:flex; flex-direction:column; gap:1px; font-size:12px; color:var(--text-muted); margin-top:3px; }
    .agenda-move span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dp-info .agenda-move { margin-top:4px; }
    .mde-info .agenda-move { margin-top:3px; }
    .chip-ship.s-none { color:var(--red); }
    .chip-ship.s-part { color:#d78a2e; }
    .chip-ship.s-all { color:var(--green); }
    /* 배송 현황 섹션 */
    .ship-caret { display:inline-block; width:12px; color:var(--text-muted); font-size:11px; }
    .ship-add-row { display:flex; gap:6px; margin-top:8px; align-items:center; }
    .ship-input { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 10px; color:var(--text); font-size:13px; outline:none; }
    .ship-input:focus { border-color:var(--accent); }
    .ship-mini-btn { background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:7px; padding:6px 10px; font-size:12px; cursor:pointer; white-space:nowrap; flex-shrink:0; }
    .ship-mini-btn:hover { border-color:var(--accent); color:var(--accent); }
    .ship-mini-btn.primary { background:var(--accent); color:var(--accent-text); border-color:var(--accent); font-weight:700; }
    .ship-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; margin-bottom:6px; background:var(--surface); flex-wrap:wrap; }
    .ship-item .ship-status-ico { font-weight:800; font-size:13px; flex-shrink:0; }
    .ship-item .ship-carrier { font-size:12px; font-weight:700; flex-shrink:0; }
    .ship-item .ship-no { font-size:12px; font-family:ui-monospace,Menlo,monospace; color:var(--text-muted); flex-shrink:0; }
    .ship-item .ship-no-link { text-decoration:none; color:var(--accent); cursor:pointer; }
    .ship-item .ship-no-link:hover { text-decoration:underline; }
    .ship-item .ship-event { font-size:12px; color:var(--text-muted); flex:1; min-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ship-item .ship-del { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:2px 4px; flex-shrink:0; }
    .ship-item .ship-del:hover { color:var(--red); }
    .ship-empty { font-size:12px; color:var(--text-muted); padding:6px 2px; }
    .sched-icon-badge { flex-shrink:0; font-size:12px; margin-left:3px; display:inline-flex; align-items:center; }
    .chip-badges { display:flex; align-items:center; flex-shrink:0; gap:3px; margin-left:auto; padding-left:6px; }
    .ev-assignee-badge { display:inline-flex; align-items:center; justify-content:center; font-size:calc(10px * var(--cal-fz,1)); font-weight:600; letter-spacing:-0.3px; color:var(--text-muted); white-space:nowrap; flex-shrink:0; line-height:1; padding:1px 5px; border-radius:4px; background:rgba(255,255,255,0.08); }
    [data-theme="light"] .ev-assignee-badge { background:rgba(0,0,0,0.06); color:#4a5060; }
    /* 담당자 전체 명단 hover 툴팁 */
    #calNamesTip { position:fixed; z-index:600; display:none; align-items:center; gap:6px; max-width:calc(100vw - 16px); background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:7px 12px; font-size:12px; line-height:1.5; color:var(--text); box-shadow:0 8px 24px rgba(0,0,0,0.25); pointer-events:none; white-space:nowrap; }
    #calNamesTip.show { display:flex; }
    #calNamesTip .cnt-t { font-weight:700; }
    #calNamesTip .cnt-sep { color:var(--text-muted); }
    #calNamesTip .cnt-n { color:var(--text-muted); }
    /* 날짜 숫자 클릭으로 일별 팝업 열림 */
    .day-num-row { cursor:pointer; }

    /* ── 다일 스판 칩 ── */
    .span-chip-overlay { position:absolute; top:0; left:0; right:0; pointer-events:none; z-index:2; }
    .span-chip { position:absolute; height:22px; font-size:12px; font-weight:500; color:#111; display:flex; align-items:center; overflow:hidden; white-space:nowrap; cursor:pointer; pointer-events:all; box-sizing:border-box; padding:0 7px; transition:filter 0.15s; min-width:0; }
    .span-chip:hover { filter:brightness(1.12); }
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .span-chip.color-{{ $__ck }} { background:var(--chip-{{ $__ck }}-bg); color:var(--chip-{{ $__ck }}-text); font-weight:600; }
    @endforeach
    .span-chip.is-start { border-radius:4px 0 0 4px; }
    .span-chip.is-end { border-radius:0 4px 4px 0; }
    .span-chip.is-solo { border-radius:4px; }

    .more-badge { font-size:calc(11px * var(--cal-fz,1)); color:var(--accent); padding:1px 6px; cursor:pointer; border-radius:3px; transition:all 0.15s; font-weight:600; }
    .more-badge:hover { background:rgba(200,176,138,0.15); }
    .day-cell.expanded { overflow:visible; z-index:10; position:relative; }
    .day-cell.expanded .events-list { position:absolute; top:30px; left:0; right:0; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px; box-shadow:0 6px 24px rgba(0,0,0,0.35); max-height:200px; overflow-y:auto; z-index:11; }

    /* ── 주간/일간 공통 ── */
    .timeline-wrap { padding:0 20px 20px; overflow-x:auto; }
    .timeline-grid { border:1px solid var(--border); border-radius:8px; overflow:hidden; min-width:600px; }

    .tl-header { display:flex; background:var(--surface); border-bottom:1px solid var(--border); }
    .tl-time-col { width:60px; flex-shrink:0; border-right:1px solid var(--border); }
    .tl-day-col { flex:1; text-align:center; padding:10px 6px; border-right:1px solid var(--border); min-width:80px; }
    .tl-day-col:last-child { border-right:none; }
    .tl-day-col.today-col { background:rgba(200,176,138,0.06); }
    .tl-day-name { font-size:11px; color:var(--text-muted); }
    .tl-day-num { font-size:18px; font-weight:700; margin-top:2px; }
    .tl-day-num.today-num { background:var(--accent); color:var(--accent-text); border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; margin:2px auto 0; font-size:16px; }
    .tl-day-num.sun-c { color:var(--red); }
    .tl-day-num.sat-c { color:var(--blue); }

    /* 종일 행 */
    .tl-allday-row { display:flex; border-bottom:1px solid var(--border); }
    .tl-allday-label { width:60px; flex-shrink:0; border-right:1px solid var(--border); font-size:10px; color:var(--text-muted); display:flex; align-items:center; justify-content:center; padding:4px; background:var(--surface); }
    .tl-allday-cell { flex:1; min-height:28px; padding:3px 4px; border-right:1px solid var(--border); background:var(--bg); min-width:80px; }
    .tl-allday-cell:last-child { border-right:none; }
    .tl-allday-cell.today-col { background:rgba(200,176,138,0.04); }
    /* 종일 칩: 셀 폭 안에서 한 줄 말줄임 (세로로 글자 깨짐 방지) */
    .tl-allday-cell .event-chip { display:block; width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; height:auto; min-height:18px; line-height:1.4; }

    /* 시간 슬롯 */
    .tl-body { position:relative; }
    .tl-row { display:flex; }
    .tl-time-label { width:60px; flex-shrink:0; border-right:1px solid var(--border); padding:0 6px; font-size:10px; color:var(--text-muted); text-align:right; height:var(--tl-hh,48px); display:flex; align-items:flex-start; padding-top:4px; background:var(--surface); }
    .tl-slot { flex:1; border-right:1px solid var(--border); border-bottom:1px solid var(--border); height:var(--tl-hh,48px); position:relative; cursor:pointer; background:var(--bg); transition:background 0.1s; min-width:80px; }
    .tl-slot:last-child { border-right:none; }
    .tl-slot:hover { background:var(--surface2); }
    .tl-slot.today-col { background:rgba(200,176,138,0.04); }

    .tl-event { position:absolute; left:2px; right:2px; border-radius:4px; padding:2px 5px; font-size:11px; overflow:hidden; cursor:pointer; z-index:1; transition:filter 0.1s, opacity 0.1s; line-height:1.3; opacity:0.8; }
    .tl-event:hover { filter:brightness(1.15); opacity:1; z-index:3; }
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .tl-event.color-{{ $__ck }} { background:var(--chip-{{ $__ck }}-bg); color:var(--chip-{{ $__ck }}-text); }
    @endforeach

    /* ── 모달 ── */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.72); z-index:200; backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal-wrapper { position:relative; display:flex; align-items:flex-start; gap:8px; max-height:92vh; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:660px; max-height:92vh; overflow-y:auto; animation:modalIn 0.22s ease; }
    .modal-external-btns { position:sticky; top:0; flex-shrink:0; display:flex; flex-direction:column; gap:8px; z-index:1; }
    .modal-external-close { background:var(--surface); border:1px solid var(--border); color:var(--text-muted); width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.3); }
    .modal-external-close:hover { border-color:var(--red); color:var(--red); background:var(--surface2); }
    .modal-external-action { background:var(--accent); color:var(--accent-text); border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.3); letter-spacing:-0.5px; }
    .modal-external-action:hover { filter:brightness(1.1); }
    .modal-external-complete { background:var(--surface); border:1px solid rgba(122,200,160,0.6); color:#2f8f5b; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.3); letter-spacing:-0.5px; }
    .modal-external-complete:hover { background:rgba(122,200,160,0.15); }
    .modal-external-complete.done { background:rgba(122,200,160,0.9); color:#fff; border-color:transparent; }
    @media (max-width:720px) { .modal-external-btns { display:none; } }
    @keyframes modalIn { from{opacity:0;transform:translateY(18px) scale(0.97)} to{opacity:1;transform:translateY(0) scale(1)} }

    .modal-strip { height:4px; border-radius:16px 16px 0 0; background:var(--accent); transition:background 0.3s; }
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .modal-strip.color-{{ $__ck }} { background:var(--chip-{{ $__ck }}-bg); }
    @endforeach
    .modal-strip.color-holiday { background:var(--red); }

    .type-badge { display:inline-flex; align-items:center; gap:5px; font-size:10px; letter-spacing:0.12em; padding:3px 8px; border-radius:4px; border:1px solid; }
    .type-badge.gold   { color:#c8b08a; border-color:rgba(200,176,138,0.35); background:rgba(200,176,138,0.08); }
    .type-badge.teal   { color:#e8894a; border-color:rgba(232,137,74,0.35);  background:rgba(232,137,74,0.08); }
    .type-badge.blue   { color:#8ab4c8; border-color:rgba(138,180,200,0.35); background:rgba(138,180,200,0.08); }
    .type-badge.red    { color:#c87a7a; border-color:rgba(200,122,122,0.35); background:rgba(200,122,122,0.08); }
    .type-badge.green  { color:#7ac87a; border-color:rgba(122,200,122,0.35); background:rgba(122,200,122,0.08); }
    .type-badge.purple { color:#9b70c8; border-color:rgba(155,112,200,0.35); background:rgba(155,112,200,0.08); }

    .modal-header { padding:20px 28px 0; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .modal-date-badge { font-size:11px; color:var(--accent); letter-spacing:0.15em; }
    .modal-title-input { background:none; border:none; font-size:22px; font-weight:500; color:var(--text); width:100%; outline:none; margin-top:4px; resize:none; overflow:hidden; line-height:1.35; min-height:32px; display:block; padding:0; }
    .modal-title-input::placeholder { color:var(--text-muted); }
    .modal-header-btns { display:flex; gap:8px; flex-shrink:0; }
    .icon-btn { background:none; border:1px solid var(--border); color:var(--text-muted); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:15px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; flex-shrink:0; }
    .icon-btn:hover { border-color:var(--accent); color:var(--accent); }
    .icon-btn.close-btn:hover { border-color:var(--red); color:var(--red); }
    .icon-btn.locked { border-color:var(--accent); background:rgba(200,176,138,0.12); color:var(--accent); }
    .btn-save-top { background:var(--accent); color:var(--accent-text); border:none; padding:6px 16px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; white-space:nowrap; }
    .btn-save-top:hover { filter:brightness(1.1); }

    .modal-body { padding:18px 28px 18px; display:flex; flex-direction:column; gap:14px; }
    .modal-footer { padding:0 28px 22px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .btn-delete { background:none; border:1px solid rgba(200,122,122,0.4); color:var(--red); padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; transition:all 0.2s; opacity:0.7; }
    .btn-delete:hover { opacity:1; background:rgba(200,122,122,0.1); }
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:10px 28px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; }
    .btn-save:hover { filter:brightness(1.1); }
    .btn-log { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 14px; border-radius:8px; font-size:12px; cursor:pointer; transition:all 0.2s; }
    .btn-log:hover { border-color:var(--accent); color:var(--accent); }
    .btn-complete { background:none; border:1px solid rgba(122,200,160,0.5); color:#2f8f5b; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s; white-space:nowrap; }
    .btn-complete:hover { background:rgba(122,200,160,0.12); }
    .btn-complete.done { background:rgba(122,200,160,0.18); border-color:rgba(122,200,160,0.7); }

    /* ── 섹션/필드 ── */
    .section-heading { font-size:10px; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-muted); display:flex; align-items:center; gap:10px; margin-bottom:2px; }
    .section-heading::after { content:''; flex:1; height:1px; background:var(--border); }
    .divider { height:1px; background:var(--border); margin:2px 0; }
    .field-section { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:14px 16px; display:flex; flex-direction:column; gap:12px; }
    .field-section .field-label { color:var(--accent); font-weight:600; }
    .field-section .field-input, .field-section .field-textarea { background:var(--surface); }
    .field-group { display:flex; flex-direction:column; gap:5px; }
    .field-row { display:flex; gap:12px; }
    .field-row .field-group { flex:1; }
    .field-label { font-size:10px; letter-spacing:0.2em; color:var(--text-muted); text-transform:uppercase; }
    .field-label .req { color:var(--red); font-weight:700; }
    .field-input, .field-textarea, .field-select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:14px; outline:none; transition:border-color 0.2s; width:100%; box-sizing:border-box; }
    .field-input:focus, .field-textarea:focus { border-color:var(--accent); }
    .field-input::placeholder, .field-textarea::placeholder { color:var(--text-muted); }
    .field-textarea { resize:none; min-height:80px; line-height:1.7; }
    /* 내용 길이만큼 자동 확장(최대 400px 후 스크롤) */
    .field-textarea.autogrow { resize:none; max-height:400px; overflow-y:hidden; }
    .field-input:disabled, .field-textarea:disabled { opacity:0.55; cursor:not-allowed; background:var(--surface); }

    /* ── 라디오 버튼 (pill) ── */
    .radio-group { display:flex; gap:8px; flex-wrap:wrap; }
    .radio-btn { padding:6px 14px; border:1px solid var(--border); border-radius:20px; font-size:12px; cursor:pointer; transition:all 0.2s; user-select:none; letter-spacing:0.05em; color:var(--text-muted); }
    .radio-btn:hover { border-color:var(--accent); color:var(--accent); }
    .radio-btn.active { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:600; }
    .radio-btn.active-red { background:var(--red); border-color:var(--red); color:#fff; }
    .radio-btn.active-green { background:var(--green); border-color:var(--green); color:#111; }

    /* ── 색상 선택 ── */
    .color-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .color-dot { padding:6px 14px; border-radius:20px; cursor:pointer; border:2px solid transparent; transition:all 0.18s; font-size:12px; font-weight:600; letter-spacing:0.03em; user-select:none; white-space:nowrap; }
    /* 유형 선택 pill — 관리 설정 카테고리 색상과 자동 연동 (커스텀 포함) */
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .color-dot[data-color="{{ $__ck }}"] { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 18%, transparent); color:var(--chip-{{ $__ck }}-bg); }
    .color-dot.active[data-color="{{ $__ck }}"] { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 35%, transparent); border-color:var(--chip-{{ $__ck }}-bg); }
    @endforeach
    .color-dot:hover { filter:brightness(1.15); }

    /* ── 일정옵션 pill ── */
    .special-opts { display:flex; gap:7px; flex-wrap:wrap; margin-top:4px; }
    .special-opt-btn { display:flex; align-items:center; gap:6px; padding:7px 12px; border-radius:8px; cursor:pointer; border:1.5px solid var(--border); background:var(--surface2); font-size:12px; transition:all 0.15s; user-select:none; color:var(--text-muted); white-space:nowrap; }
    .special-opt-btn .opt-icon { font-size:15px; flex-shrink:0; }
    .visit-opts { display:flex; flex-wrap:wrap; gap:8px; }
    .visit-opts .visit-opt { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:8px; border:1.5px solid var(--border); background:var(--surface2); font-size:13px; cursor:pointer; color:var(--text); user-select:none; }
    .visit-opts .visit-opt:has(input:checked) { border-color:var(--accent); background:rgba(200,176,138,0.18); color:var(--accent); font-weight:600; }
    .visit-opts .visit-opt input { cursor:pointer; }
    .special-opt-btn:hover { border-color:var(--accent); background:rgba(200,176,138,0.1); color:var(--text); }
    .special-opt-btn.active { border-color:var(--accent); background:rgba(200,176,138,0.18); color:var(--accent); box-shadow:0 0 0 2px rgba(200,176,138,0.2); }

    .sched-opt-btn { display:flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; cursor:pointer; border:1.5px solid var(--border); background:var(--surface2); font-size:12px; font-weight:600; transition:all 0.15s; color:var(--text-muted); white-space:nowrap; user-select:none; }
    .sched-opt-btn .opt-icon { font-size:15px; flex-shrink:0; }
    .sched-opt-btn:hover { border-color:var(--accent); color:var(--text); }
    .sched-opt-btn.active[data-sopt="suggest"] { border-color:#8ab4c8; background:rgba(138,180,200,0.18); color:#8ab4c8; box-shadow:0 0 0 2px rgba(138,180,200,0.18); }
    .sched-opt-btn.active[data-sopt="hope"] { border-color:#c8b08a; background:rgba(200,176,138,0.18); color:var(--accent); box-shadow:0 0 0 2px rgba(200,176,138,0.18); }
    .sched-opt-btn.active[data-sopt="target"] { border-color:#7ac87a; background:rgba(122,200,122,0.18); color:#7ac87a; box-shadow:0 0 0 2px rgba(122,200,122,0.18); }

    /* ── 조건부 필드 ── */
    .conditional-field { overflow:hidden; max-height:0; transition:max-height 0.3s ease; }
    .conditional-field.visible { max-height:120px; }

    /* ── 날짜/시간 ── */
    .datetime-section { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 14px; display:flex; flex-direction:column; gap:9px; }
    .dt-row { display:grid; grid-template-columns:36px 1fr 1fr; align-items:center; gap:8px; }
    .dt-label { font-size:10px; letter-spacing:0.12em; color:var(--text-muted); text-transform:uppercase; }
    .dt-input { background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:9px 12px; color:var(--text); font-size:14px; outline:none; transition:border-color 0.2s; width:100%; color-scheme:dark; cursor:pointer; box-sizing:border-box; }
    .dt-input:focus { border-color:var(--accent); }
    .time-picker-trigger { cursor:pointer; user-select:none; text-align:center; font-size:14px; display:flex; align-items:center; justify-content:center; letter-spacing:0.05em; }
    .time-picker-trigger:hover { border-color:var(--accent); }

    /* ── 타임피커 ── */
    .time-picker-popup { position:fixed; z-index:9999; background:var(--surface2); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.5); display:flex; flex-direction:column; user-select:none; min-width:130px; }
    .tp-header { display:flex; border-bottom:1px solid var(--border); flex-shrink:0; }
    .tp-col-label { flex:1; font-size:10px; color:var(--text-muted); text-align:center; padding:7px 0 6px; letter-spacing:0.12em; background:var(--surface2); }
    .tp-col-label.divider-space { width:1px; flex:none; background:var(--border); }
    .tp-body { display:flex; }
    .tp-col { display:flex; flex-direction:column; flex:1; max-height:210px; overflow-y:auto; scroll-snap-type:y mandatory; scrollbar-width:none; padding:4px 0; }
    .tp-col::-webkit-scrollbar { display:none; }
    .tp-item { padding:8px 0; text-align:center; font-size:15px; color:var(--text-muted); border-radius:6px; cursor:pointer; scroll-snap-align:start; transition:all 0.12s; flex-shrink:0; margin:0 4px; }
    .tp-item:hover { background:rgba(255,255,255,0.06); color:var(--text); }
    .tp-item.selected { background:rgba(200,176,138,0.2); color:var(--accent); font-weight:700; }
    .tp-divider { width:1px; background:var(--border); flex-shrink:0; align-self:stretch; }
    .tp-footer { border-top:1px solid var(--border); padding:7px 8px; flex-shrink:0; display:flex; justify-content:flex-end; }
    .tp-confirm-btn { background:var(--accent); color:var(--accent-text); border:none; border-radius:7px; padding:5px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:opacity 0.15s; }
    .tp-confirm-btn:hover { opacity:0.85; }

    /* ── 토글 스위치 ── */
    .allday-row { display:flex; align-items:center; gap:10px; }
    .toggle-wrap { display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
    .toggle-track { width:36px; height:20px; background:var(--border); border-radius:999px; position:relative; transition:background 0.2s; flex-shrink:0; }
    .toggle-track.on { background:var(--accent); }
    .toggle-thumb { position:absolute; top:3px; left:3px; width:14px; height:14px; border-radius:50%; background:var(--text-muted); transition:all 0.2s; }
    .toggle-track.on .toggle-thumb { left:19px; background:#1a1207; }
    .toggle-label { font-size:11px; letter-spacing:0.1em; color:var(--text-muted); }

    /* ── 알림 ── */
    .notif-row { display:flex; align-items:center; gap:10px; }
    .notif-select { flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 10px; color:var(--text); font-size:13px; outline:none; cursor:pointer; color-scheme:dark; }
    .notif-select:focus { border-color:var(--accent); }

    /* ── 이미지 업로드 ── */
    .img-upload-group { display:flex; flex-direction:column; gap:6px; }
    .img-upload-label { font-size:10px; letter-spacing:0.2em; color:var(--text-muted); text-transform:uppercase; }
    .img-upload-zone { border:1px dashed var(--border); border-radius:8px; padding:12px; text-align:center; cursor:pointer; transition:all 0.2s; position:relative; font-size:11px; color:var(--text-muted); }
    .img-upload-zone:hover, .img-upload-zone.drag-over { border-color:var(--accent); background:rgba(200,176,138,0.04); color:var(--accent); }
    .img-upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
    .img-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:10px; margin-top:6px; }
    .img-item { position:relative; border-radius:8px; overflow:visible; border:1px solid var(--border); background:var(--surface2); display:flex; flex-direction:column; }
    .img-item .img-thumb-wrap { position:relative; aspect-ratio:1; overflow:hidden; border-radius:8px 8px 0 0; }
    .img-item img { width:100%; height:100%; object-fit:cover; cursor:zoom-in; display:block; }
    .img-item .img-fileicon { width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:30px; text-decoration:none; background:var(--surface2); }
    .img-item .img-filename { font-size:10px; color:var(--text-muted); padding:3px 7px 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-top:1px solid var(--border); }
    .img-item .img-note { font-size:11px; padding:3px 6px 5px; background:transparent; border:none; border-top:1px solid var(--border); color:var(--text); width:100%; box-sizing:border-box; resize:none; line-height:1.4; min-height:30px; outline:none; border-radius:0 0 8px 8px; }
    .img-item .img-note::placeholder { color:var(--text-muted); }
    .img-remove { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.75); border:none; color:#fff; width:18px; height:18px; border-radius:50%; cursor:pointer; font-size:10px; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s; z-index:1; }
    .img-item:hover .img-remove { opacity:1; }

    /* ── 잠금 요약 뷰 ── */
    .lock-summary { display:none; padding:0 28px 28px; flex-direction:column; gap:18px; }
    .modal-body.is-locked > *:not(#lockSummary) { display:none !important; }
    .modal-body.is-locked > #lockSummary { display:flex; }
    .ls-section { display:flex; flex-direction:column; gap:10px; }
    .ls-section-title { font-size:11px; color:var(--text-muted); letter-spacing:0.06em; font-weight:600; padding-bottom:6px; border-bottom:1px solid var(--border); }
    .ls-location { font-size:15px; color:var(--text); line-height:1.55; }
    .ls-actions { display:flex; gap:6px; flex-wrap:wrap; margin-top:6px; }
    .ls-action-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 12px; border-radius:18px; font-size:12px; cursor:pointer; transition:all 0.15s; }
    .ls-action-btn:hover { border-color:var(--accent); color:var(--accent); }
    .ls-action-btn.primary { border-color:var(--accent); color:var(--accent); background:rgba(200,176,138,0.08); }
    .ls-time { display:inline-flex; align-items:center; gap:8px; font-size:15px; color:var(--text); font-weight:500; padding:2px 0; }
    .ls-time-icon { font-size:18px; }
    .ls-type-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:18px; background:var(--surface2); border:1px solid var(--border); font-size:13px; color:var(--text); font-weight:500; align-self:flex-start; }
    .ls-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .ls-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:18px; background:var(--surface2); border:1px solid var(--border); font-size:13px; color:var(--text); font-weight:500; }
    .ls-chip-icon { opacity:0.95; }
    .ls-chip-key { color:var(--text-muted); font-weight:400; margin-right:2px; }
    .ls-chip-val { color:var(--text); font-weight:600; }
    .ls-info-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px 12px; }
    .ls-info-cell { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:10px 12px; }
    .ls-info-cell .ls-info-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; letter-spacing:0.04em; }
    .ls-info-cell .ls-info-val { font-size:15px; color:var(--text); font-weight:500; line-height:1.4; word-break:break-all; }
    .ls-info-cell .ls-info-val.ls-empty { color:var(--text-muted); opacity:0.5; font-weight:400; }
    /* 내용 블록: 라벨 아래 박스로 구분감 부여 */
    .ls-text-block { font-size:14px; color:var(--text); line-height:1.7; white-space:pre-wrap; word-break:break-word; padding:12px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; margin-top:4px; }
    .ls-text-block.muted { color:var(--text-muted); font-style:italic; }
    .ls-amount { font-size:26px; font-weight:700; color:var(--text); line-height:1.2; }
    .ls-amount-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
    .ls-saved-pill { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:14px; background:rgba(122,200,122,0.12); border:1px solid rgba(122,200,122,0.35); color:#7ac87a; font-size:11px; font-weight:600; }
    .ls-img-section { display:flex; flex-direction:column; gap:6px; }
    .ls-img-label { font-size:11px; color:var(--text-muted); letter-spacing:0.04em; }
    .ls-img-grid { display:flex; flex-wrap:wrap; gap:8px; }
    .ls-img-grid img { width:88px; height:88px; object-fit:cover; border-radius:8px; border:1px solid var(--border); cursor:zoom-in; }
    .ls-img-empty { font-size:12px; color:var(--text-muted); opacity:0.6; padding:6px 0; }
    .ls-client-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:18px; background:rgba(122,160,200,0.10); border:1px solid rgba(122,160,200,0.30); color:#8ab4c8; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
    @media (max-width:768px) {
        .lock-summary { padding:0 18px 22px; gap:16px; }
        .ls-amount { font-size:22px; }
        .ls-info-grid { grid-template-columns:1fr 1fr; gap:14px 16px; }
        .ls-img-grid img { width:72px; height:72px; }
    }

    /* ── 잠금/잔금 배너 ── */
    .locked-banner { display:none; align-items:center; gap:8px; background:rgba(200,176,138,0.08); border:1px solid rgba(200,176,138,0.25); border-radius:8px; padding:8px 14px; font-size:11px; letter-spacing:0.08em; color:var(--accent); margin:10px 28px 0; }
    .locked-banner.visible { display:flex; }
    .balance-banner { display:none; align-items:center; gap:8px; background:rgba(200,176,138,0.08); border:1px solid rgba(200,176,138,0.25); border-radius:8px; padding:8px 14px; font-size:13px; color:var(--accent); margin:10px 28px 0; }
    .balance-banner.visible { display:flex; }

    /* ── 담당자 ── */
    .assignee-btn { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; border:1px solid var(--border); background:none; color:var(--text-muted); font-size:12px; cursor:pointer; transition:all 0.15s; white-space:nowrap; margin-top:5px; }
    .assignee-btn:hover { border-color:var(--accent); color:var(--accent); }
    .assignee-btn.has-assignee { border-color:rgba(100,160,240,.5); color:var(--accent); background:rgba(100,160,240,.08); }
    .assignee-list { display:flex; flex-wrap:wrap; gap:6px; }
    .assignee-chip { padding:4px 10px; border-radius:20px; border:1px solid var(--border); font-size:12px; cursor:pointer; color:var(--text-muted); transition:all 0.15s; }
    .assignee-chip.selected { background:var(--accent); color:var(--accent-text); border-color:var(--accent); font-weight:600; }
    .assignee-chip:hover { border-color:var(--accent); }
    .assignee-chip.self { border-color:rgba(100,160,240,.5); color:var(--accent); }
    .assignee-chip.self.selected { color:var(--accent-text); }
    .assignee-more { padding:4px 10px; border-radius:20px; border:1px dashed var(--border); background:none; font-size:11px; cursor:pointer; color:var(--text-muted); }
    .assignee-more:hover { border-color:var(--accent); color:var(--accent); }

    /* ── 장소/주소 ── */
    .location-input-wrap { display:flex; flex-direction:column; gap:6px; }
    .addr-search-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 10px; border:1px solid var(--border); border-radius:6px; background:none; color:var(--text-muted); font-size:12px; cursor:pointer; transition:all 0.2s; }
    .addr-search-btn:hover { border-color:var(--accent); color:var(--accent); background:rgba(200,176,138,.1); }
    .route-search-btn { display:none; align-items:center; gap:4px; padding:6px 10px; border:1px solid var(--border); border-radius:6px; background:none; color:var(--text-muted); font-size:12px; cursor:pointer; transition:all 0.2s; }
    .route-search-btn:hover { background:rgba(249,224,0,0.18); border-color:#F9E000; color:#e8cc10; }

    /* ── 공휴일 ── */
    .holiday-btn-wrap { margin-bottom:4px; }
    .holiday-dot { font-size:12px; color:var(--text-muted); cursor:pointer; padding:3px 8px; border-radius:4px; transition:all 0.15s; }
    .holiday-dot:hover { background:rgba(200,122,122,0.1); color:var(--red); }
    .holiday-dot.active { background:rgba(200,122,122,0.18); color:var(--red); border:1px solid rgba(200,122,122,0.35); }

    /* ── 일반 첨부 ── */
    .upload-zone { border:1px dashed var(--border); border-radius:10px; padding:16px; text-align:center; cursor:pointer; transition:all 0.2s; position:relative; }
    .upload-zone:hover, .upload-zone.drag-over { border-color:var(--accent); background:rgba(200,176,138,0.04); }
    .upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }

    /* ── 라이트박스 ── */
    .lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:12px; }
    .lightbox.open { display:flex; }
    .lightbox-img-wrap { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; overflow:visible; }
    .lightbox-img-wrap.dragging { cursor:grabbing; }
    .lightbox-img-wrap.zoomed { cursor:grab; }
    .lightbox-img-wrap:not(.zoomed) { cursor:default; }
    .lightbox-img-wrap img { max-width:90vw; max-height:80vh; border-radius:8px; object-fit:contain; box-shadow:0 4px 32px rgba(0,0,0,0.5); transform-origin:center center; transition:transform 0.15s ease; user-select:none; -webkit-user-drag:none; pointer-events:auto; }
    .lightbox-close { position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.15); border:none; color:#fff; width:40px; height:40px; border-radius:50%; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; z-index:1; }
    .lightbox-close:hover { background:rgba(255,255,255,0.3); }
    .lightbox-zoom-info { position:absolute; bottom:60px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.6); color:#fff; padding:4px 12px; border-radius:20px; font-size:11px; opacity:0; transition:opacity 0.3s; pointer-events:none; }
    .lightbox-zoom-info.show { opacity:1; }
    .lightbox-filename { color:rgba(255,255,255,0.7); font-size:12px; text-align:center; max-width:80vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lightbox-nav { position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.15); border:none; color:#fff; width:44px; height:44px; border-radius:50%; cursor:pointer; font-size:20px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; z-index:1; }
    .lightbox-nav:hover { background:rgba(255,255,255,0.3); }
    .lightbox-nav.prev { left:16px; }
    .lightbox-nav.next { right:16px; }
    .lightbox-hint { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.4); font-size:11px; pointer-events:none; }

    /* ── 액션 버튼 (견적서 첨부 등) ── */
    .action-btn { display:inline-flex; align-items:center; justify-content:center; gap:4px; padding:8px 14px; border-radius:8px; border:1px solid var(--border); background:var(--surface2); color:var(--text); font-size:12px; font-weight:500; cursor:pointer; transition:all 0.2s; }
    .action-btn:hover { border-color:var(--accent); color:var(--accent); background:rgba(200,176,138,0.08); }

    /* ── gold/teal 조건부 ── */
    .gold-only, .teal-only, .common-only { display:none; }

    /* ── 드래그앤드롭 ── */
    .drag-ghost { position:fixed; pointer-events:none; z-index:1000; opacity:0.85; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; white-space:nowrap; box-shadow:0 4px 16px rgba(0,0,0,0.4); max-width:200px; overflow:hidden; text-overflow:ellipsis; }
    .day-cell.drop-target { background:rgba(212,188,150,0.15) !important; box-shadow:inset 0 0 0 2px var(--accent); }
    /* 드래그 기간 선택 — 텍스트 선택처럼 부드러운 밴드(양 끝만 둥글게, 내부 세로선 없음) */
    .day-cell.range-sel { background:color-mix(in srgb, var(--accent) 14%, transparent) !important; transition:background .12s ease;
        box-shadow: inset 0 1px 0 color-mix(in srgb, var(--accent) 38%, transparent), inset 0 -1px 0 color-mix(in srgb, var(--accent) 38%, transparent); }
    .day-cell.range-sel.range-start { border-radius:12px 0 0 12px;
        box-shadow: inset 1px 0 0 color-mix(in srgb, var(--accent) 38%, transparent), inset 0 1px 0 color-mix(in srgb, var(--accent) 38%, transparent), inset 0 -1px 0 color-mix(in srgb, var(--accent) 38%, transparent); }
    .day-cell.range-sel.range-end { border-radius:0 12px 12px 0;
        box-shadow: inset -1px 0 0 color-mix(in srgb, var(--accent) 38%, transparent), inset 0 1px 0 color-mix(in srgb, var(--accent) 38%, transparent), inset 0 -1px 0 color-mix(in srgb, var(--accent) 38%, transparent); }
    .day-cell.range-sel.range-start.range-end { border-radius:12px;
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 38%, transparent); }
    body.range-dragging { user-select:none; cursor:cell; }
    body.dragging { cursor:grabbing !important; user-select:none; }
    body.dragging .event-chip { cursor:grabbing; }
    .event-chip { cursor:pointer; }

    /* ── 모바일 일정 리스트 (네이버 캘린더 스타일) ── */
    .mobile-day-events { display:none; }

    @media (max-width: 768px) {
        /* 헤더 컴팩트 — 네비/뷰토글은 중앙, 이력·휴지통·추가는 우측 */
        .cal-header { padding:12px; gap:10px; flex-wrap:wrap; justify-content:center; }
        .cal-header-left { width:100%; gap:8px; justify-content:center; }
        .cal-header-right { width:100%; gap:8px; justify-content:flex-end; padding-top:4px; border-top:1px solid var(--border); }
        .app-title { display:none; }
        .nav-btn { width:40px; height:40px; }
        .month-label { font-size:16px; min-width:0; }
        .view-toggle-btn { padding:6px 12px; min-height:36px; font-size:11px; }
        .add-btn { padding:8px 14px; font-size:12px; }

        /* 필터바 — 카테고리 칩이 한눈에 보이도록 중앙 정렬 + 줄바꿈 */
        .legend { padding:10px 12px; gap:6px 8px; justify-content:center; flex-wrap:wrap; }
        .filter-btn { padding:6px 11px; min-height:34px; font-size:11.5px; flex:0 0 auto; border-radius:16px; }
        .assignee-filter { font-size:11px; }
        .assignee-filter-chips { width:100%; justify-content:center; }

        /* 주간/일간 타임라인: 가로 스크롤 없이 한 화면에 맞춤 */
        .timeline-wrap { padding:0 6px 16px; overflow-x:hidden; }
        .timeline-grid { min-width:0; }
        .tl-time-col, .tl-allday-label, .tl-time-label { width:32px !important; font-size:9px; padding-left:2px; padding-right:2px; }
        .tl-day-col, .tl-allday-cell, .tl-slot { min-width:0 !important; }
        .tl-day-col { padding:6px 1px; }
        .tl-day-name { font-size:10px; }
        .tl-day-num { font-size:14px; }
        .tl-event { font-size:9px; padding:1px 2px; left:1px; right:1px; line-height:1.2; }
        .tl-allday-cell .event-chip { font-size:9px; }

        /* 월간 그리드 컴팩트 */
        .calendar-wrap { padding:8px 12px; }
        .weekday { font-size:11px; padding:6px 0; }
        .week-row { min-height:84px; }
        /* 간소화 월간뷰: 날짜 + 점만 (일정 상세는 하단 리스트) */
        .day-cell { padding:6px 0 12px; text-align:center; cursor:pointer; }
        .week-row { min-height:54px !important; }
        .day-num-row { justify-content:center; margin-bottom:0; }
        .day-num { margin:0 auto; width:30px; height:30px; font-size:14px; border-radius:50%; transition:background .12s; position:relative; }
        .holiday-label { display:none; } /* 공휴일명 숨김 — 숫자 색으로 구분 */
        /* 일정 있음 점 */
        .day-cell.m-has-ev::after { content:''; display:block; width:5px; height:5px; border-radius:50%; background:var(--accent); margin:3px auto 0; }
        .day-cell.other-month.m-has-ev::after { opacity:0.4; }
        /* 오늘: 연한 원 / 선택: 채운 원 */
        .day-cell.today .day-num { background:var(--surface3); color:var(--text) !important; }
        .day-cell.mobile-selected { background:none; }
        .day-cell.mobile-selected .day-num { background:var(--accent); color:#fff !important; font-weight:700; }
        .day-num { font-size:13px; width:22px; height:22px; font-weight:700; }
        .day-cell.today .day-num { width:22px; height:22px; }
        .day-num-row { margin-bottom:2px; }

        /* 이벤트 칩 → TickTick 스타일 작은 텍스트 바 (월간 그리드 셀에만) */
        .day-cell .events-list { flex-direction:column; gap:1px; align-items:stretch; }
        .day-cell .event-chip { width:100%; min-width:0; height:auto; min-height:14px; border-radius:3px; padding:0 3px; font-size:9px; line-height:1.6; display:flex; align-items:center; gap:2px; overflow:hidden; pointer-events:none; border:none !important; }
        .day-cell .event-chip .chip-title { display:inline; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; min-width:0; }
        .day-cell .event-chip .chip-time { display:inline; font-size:8px; opacity:0.8; flex-shrink:0; }
        .day-cell .event-chip .chip-special, .day-cell .event-chip .chip-badges,
        .day-cell .event-chip .sched-icon-badge, .day-cell .event-chip .ev-assignee-badge { display:none; }
        .day-cell .event-chip.single { border-left:none; color:var(--text); }
        {{-- 모바일 칩: PC처럼 연한 틴트로 (원색 배경은 가독성 저하) --}}
        @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
        .day-cell .event-chip.single.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 32%, transparent); color:var(--text); }
        @endforeach
        .more-badge { font-size:9px; padding:0 3px; pointer-events:none; font-weight:600; color:var(--text-muted); text-align:left; }

        /* 다일 일정: 텍스트 바, 셀 경계까지 연결(padding 3px 보상)
           width:100%면 음수 마진이 오른쪽 끝을 못 당겨 끊겨 보임 → width:auto로 스트레치 */
        .day-cell .event-chip.multi-day { width:auto; flex-basis:auto; align-self:stretch; margin-left:-3px; margin-right:-3px; border-radius:0; }
        .day-cell .event-chip.multi-day.day-start         { border-radius:3px 0 0 3px; margin-left:0; }
        .day-cell .event-chip.multi-day.day-end           { border-radius:0 3px 3px 0; margin-right:0; }
        .day-cell .event-chip.multi-day.day-start.day-end { border-radius:3px; margin:0; }

        /* 선택된 날짜 */
        .day-cell.mobile-selected { background:var(--surface2); }
        .day-cell.mobile-selected .day-num { background:var(--accent); color:#fff; border-radius:50%; font-weight:700; }

        /* 하단 일정 리스트 */
        .mobile-day-events { display:block; padding:12px; border-top:1px solid var(--border); background:var(--surface); }
        .mobile-day-events .mde-header { font-size:13px; font-weight:600; color:var(--accent); margin-bottom:10px; }
        .mobile-day-events .mde-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:6px; cursor:pointer; transition:background 0.15s; min-height:44px; }
        .mobile-day-events .mde-item:hover { background:var(--surface2); }
        .mobile-day-events .mde-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .mobile-day-events .mde-info { flex:1; min-width:0; }
        .mobile-day-events .mde-title-row { display:flex; align-items:flex-start; gap:8px; }
        /* 팝업뷰와 동일: 폰트 축소 + 2줄까지 줄바꿈 */
        .mobile-day-events .mde-title { font-size:12px; font-weight:500; flex:1; min-width:0; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; word-break:break-all; line-height:1.45; }
        .mobile-day-events .mde-assignee { flex-shrink:0; font-size:11px; font-weight:600; color:var(--text-muted); background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:1px 8px; max-width:45%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mobile-day-events .mde-meta { font-size:11px; color:var(--text-muted); margin-top:2px; }
        .mobile-day-events .mde-empty { text-align:center; padding:20px; color:var(--text-muted); font-size:13px; }

        /* 다일 스판 칩 숨김 */
        .span-chip-overlay { display:none; }
        .lane-spacer { display:none; }
        /* 다일 제목 오버레이: 모바일에서도 바 전체 폭으로 흐르게 (크기만 축소) */
        .mday-title-overlay { font-size:9px; padding:0 4px; gap:2px; }
        .mday-title-overlay .chip-badges, .mday-title-overlay .ev-assignee-badge,
        .mday-title-overlay .sched-icon-badge, .mday-title-overlay .chip-special { display:none; }

        /* 모달 모바일 */
        .modal { max-width:95vw; border-radius:12px; }
        .modal-body { padding:14px 16px; }
        .modal-header { padding:16px 16px 0; }
        .modal-footer { padding:0 16px 16px; }
        .icon-btn { width:40px; height:40px; }
        .modal-title-input { font-size:18px; }
        .field-section { padding:12px; }
    }

    @media (max-width: 480px) {
        .cal-header { flex-wrap:wrap; justify-content:center; gap:6px; }
        .cal-header-left { width:100%; justify-content:center; }
        .cal-header-right { width:100%; justify-content:flex-end; }
        .weekday { font-size:10px; letter-spacing:0; }
        .week-row { min-height:60px; }
        .day-num { font-size:14px; width:26px; height:26px; }
    }
</style>
@endpush

@push('styles')
<style>
/* ══════════════════════════════════════════════════════════
   모던 SaaS 리프레시 — 부드러운 테두리·은은한 그림자·둥근 카드
   (기존 스타일 뒤에 로드되어 카세이드에서 우선 적용)
   ══════════════════════════════════════════════════════════ */
:root {
    --card-shadow: 0 4px 24px rgba(0,0,0,0.28);
    --btn-shadow: 0 1px 2px rgba(0,0,0,0.35);
    --border: #2c2c2c;          /* 격자선·테두리 완화 (기존 #3a3a3a) */
}
[data-theme="light"] {
    --border: #e4e7ec;          /* 진한 회색 격자선 → 연한 헤어라인 */
    --surface2: #f1f3f7;
    --card-shadow: 0 1px 2px rgba(16,24,40,0.04), 0 6px 20px rgba(16,24,40,0.07);
    --btn-shadow: 0 1px 2px rgba(16,24,40,0.08);
}

/* ── 월간 그리드: 카드처럼 띄우고 헤어라인 격자 ── */
.days-grid { border:1px solid var(--border); border-radius:16px; box-shadow:var(--card-shadow); }
.timeline-grid { border-radius:14px; box-shadow:var(--card-shadow); }
.day-cell { transition:background 0.18s ease; }
[data-theme="light"] .day-cell.other-month { background:#f6f7f9; opacity:1; }
[data-theme="light"] .day-cell.other-month .day-num,
[data-theme="light"] .day-cell.other-month .holiday-label { opacity:0.5; }

/* ── 버튼: 둥글게 + 은은한 그림자 ── */
.nav-btn, .icon-btn { border-radius:10px; }
.add-btn, .btn-save, .btn-save-top, .modal-external-action { border-radius:10px; box-shadow:var(--btn-shadow); font-weight:600; }
.add-btn:hover, .btn-save:hover, .btn-save-top:hover, .modal-external-action:hover { filter:brightness(1.06); }
.view-toggle-group, .cal-fontsize { border-radius:10px; }
.filter-btn { transition:all 0.16s ease; }

/* ── 라이트모드: 과하게 진했던 테두리들을 부드럽게 ── */
[data-theme="light"] .nav-btn,
[data-theme="light"] .icon-btn,
[data-theme="light"] .filter-btn,
[data-theme="light"] .modal-external-close { border-color:#d5dae3; color:#5a6070; }
[data-theme="light"] .radio-btn,
[data-theme="light"] .special-opt-btn,
[data-theme="light"] .sched-opt-btn,
[data-theme="light"] .action-btn { border-color:#dfe3ea; }
[data-theme="light"] .special-opt-btn,
[data-theme="light"] .sched-opt-btn,
[data-theme="light"] .action-btn { background:#f4f6f9; }
[data-theme="light"] .field-input,
[data-theme="light"] .field-textarea,
[data-theme="light"] .dt-input { border-color:#dfe3ea; }
[data-theme="light"] .field-section,
[data-theme="light"] .datetime-section { border-color:#e6e9ef; background:#f7f8fb; }

/* ── 모달·팝오버: 그림자 톤 정리 ── */
.modal { border-radius:18px; }
.day-popover { border-radius:14px; box-shadow:var(--card-shadow); }
</style>
@endpush

@section('content')
<div class="cal-header">
    <div class="cal-header-left">
        <span class="app-title">Calendar</span>
        <button class="nav-btn" onclick="changeYear(-1)" title="이전 년도">«</button>
        <button class="nav-btn" onclick="changePeriod(-1)">‹</button>
        <div class="month-label" id="periodTitle"></div>
        <button class="nav-btn" onclick="changePeriod(1)">›</button>
        <button class="nav-btn" onclick="changeYear(1)" title="다음 년도">»</button>
        <button class="nav-btn" onclick="goToday()" style="font-size:11px;letter-spacing:0.05em;width:auto;padding:0 10px;">TODAY</button>
        <div class="view-toggle-group">
            <button class="view-toggle-btn active" id="tabMonth" onclick="switchView('month')">월간</button>
            <button class="view-toggle-btn"        id="tabWeek"  onclick="switchView('week')">주간</button>
            <button class="view-toggle-btn"        id="tabDay"   onclick="switchView('day')">일간</button>
            <button class="view-toggle-btn"        id="tabList"  onclick="switchView('list')">목록</button>
        </div>
    </div>
    <div class="cal-header-right" style="display:flex;align-items:center;gap:8px;">
        @if(!Auth::user()->isGuest())
        <div class="cal-search-wrap" id="calSearchWrap">
            <input class="cal-search-input" id="calSearchInput" placeholder="🔍 일정 검색 (Enter)" autocomplete="off"
                onkeydown="if(event.key==='Enter'&&!event.isComposing){event.preventDefault();openSearchListView();}">
        </div>
        @endif
        <div class="cal-fontsize" title="글자 크기 조절">
            <button class="cal-fz-btn" onclick="calFont(-1)" aria-label="글자 작게">A−</button>
            <span id="calFontLabel">100%</span>
            <button class="cal-fz-btn" onclick="calFont(1)" aria-label="글자 크게" style="font-size:15px;">A+</button>
        </div>
        <button class="nav-btn" onclick="location.href='/calendar/history'" title="캘린더 이력 보기" style="font-size:11px;letter-spacing:0.05em;width:auto;padding:0 10px;"><x-icon name="clip" :size="13"/> 캘린더 이력</button>
        @if(Auth::user()->hasPermission('calendar.edit'))
            <button class="nav-btn" onclick="openTrashModal()" title="휴지통" style="font-size:13px;width:auto;padding:0 10px;"><x-icon name="trash" :size="13"/> 휴지통</button>
            <button class="add-btn" onclick="openNewModal()">+ 일정 추가</button>
            @if(Auth::user()->hasPermission('calendar.backup'))
            <div style="position:relative;">
                <button class="nav-btn" onclick="toggleCalMenu()" title="내보내기/가져오기" style="font-size:14px;">⋯</button>
                <div class="cal-menu" id="calMenu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:4px;z-index:20;min-width:160px;box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                    <button onclick="location.href='/api/events/export/json'" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="box" :size="13"/> JSON 백업</button>
                    <button onclick="location.href='/api/events/export/ical'" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="calendar" :size="13"/> iCal 내보내기</button>
                    <div style="height:1px;background:var(--border);margin:4px 0;"></div>
                    <button onclick="document.getElementById('jsonImportInput').click()" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="upload" :size="13"/> JSON 가져오기</button>
                    <button onclick="document.getElementById('icalImportInput').click()" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="upload" :size="13"/> iCal 가져오기</button>
                </div>
            </div>
            <input type="file" id="jsonImportInput" accept=".json" style="display:none" onchange="importFile('json',this)">
            <input type="file" id="icalImportInput" accept=".ics,.ical" style="display:none" onchange="importFile('ical',this)">
            @endif
        @endif
    </div>
</div>

<script>(function(){var s=parseFloat(localStorage.getItem('calFontScale')||'1')||1;document.documentElement.style.setProperty('--cal-fz',s);})();</script>

<div class="legend" id="filterBar">
    @foreach(\App\Models\CalendarCategory::map() as $__k => $__c)
    <button class="filter-btn active f-{{ $__k }}" data-filter="{{ $__k }}" style="--fbtn:var(--chip-{{ $__k }}-bg)" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-{{ $__k }}-bg)"></span>{{ $__c['label'] }}</button>
    @endforeach
    <select class="assignee-filter" id="assigneeFilter" onchange="onAssigneeFilterChange()" title="담당자 추가 (여러 명 선택 가능)">
        <option value="">담당자 추가…</option>
    </select>
    <div id="assigneeFilterChips" class="assignee-filter-chips"></div>
</div>

<!-- 월간 뷰 -->
<div id="monthView">
    <div class="calendar-wrap">
        <div class="weekdays">
            <div class="weekday">일</div><div class="weekday">월</div><div class="weekday">화</div>
            <div class="weekday">수</div><div class="weekday">목</div><div class="weekday">금</div><div class="weekday">토</div>
        </div>
        <div class="days-grid" id="daysGrid"></div>
        <div class="mobile-day-events" id="mobileDayEvents"></div>
    </div>
</div>

{{-- 하루 일정 전체 보기 팝오버 (데스크탑 '+N건 더보기') --}}
<div id="dayPopoverOverlay" class="day-popover-overlay" onclick="closeDayPopover()"></div>
<div id="dayPopover" class="day-popover" style="display:none;">
    <div class="dp-header">
        <span id="dpTitle"></span>
        <button type="button" class="dp-close" onclick="closeDayPopover()">✕</button>
    </div>
    <div id="dpList" class="dp-list"></div>
</div>

<!-- 주간/일간 뷰 -->
<div id="timelineView" style="display:none;">
    <div class="timeline-wrap">
        <div class="timeline-grid" id="timelineGrid"></div>
    </div>
</div>

<!-- 목록(아젠다) 뷰 -->
<div id="listView" style="display:none;">
    <div class="agenda-strip" id="agendaStrip"></div>
    <div class="agenda-wrap" id="agendaWrap"></div>
</div>

<!-- 일정 모달 -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-wrapper">
    <div class="modal" id="modal">
        <div class="modal-strip" id="modalStrip"></div>
        <div class="modal-header">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="modal-date-badge" id="modalDateBadge"></span>
                    <span class="type-badge gold" id="typeBadge">● 방문의뢰</span>
                </div>
                <div class="color-row" id="colorRow" style="margin-bottom:4px;flex-wrap:wrap;">
                    @foreach(\App\Models\CalendarCategory::map() as $__k => $__c)
                    <div class="color-dot{{ $loop->first ? ' active' : '' }}" data-color="{{ $__k }}">{{ $__c['label'] }}</div>
                    @endforeach
                </div>
                <div class="holiday-btn-wrap" style="margin-bottom:4px;">
                    <span class="holiday-dot" id="holidayDot" style="font-size:12px;color:var(--text-muted);cursor:pointer;">📅 공휴일로 지정</span>
                </div>
                <div class="title-wrap" style="display:flex;align-items:flex-start;gap:8px;">
                    <textarea class="modal-title-input" id="modalTitle" placeholder="일정 제목을 입력하세요 *" rows="1" style="flex:1;min-width:0;"></textarea>
                    <span id="modalShipBadge" style="display:none;flex-shrink:0;font-size:16px;font-weight:800;line-height:1.6;" title=""></span>
                </div>
                <button class="assignee-btn" id="assigneeBtn" onclick="toggleAssigneePanel()" title="담당자 지정">
                    <span id="assigneeBtnIcon">👤</span>
                    <span id="assigneeBtnLabel">담당자 지정</span>
                </button>
                <div class="assignee-list" id="assigneeList" style="display:none;margin-top:8px;"></div>
            </div>
            <div class="modal-header-btns">
                <span id="privateModeBadge" style="display:none;font-size:11px;background:#a78bfa22;color:#a78bfa;border:1px solid #a78bfa55;border-radius:6px;padding:2px 8px;font-weight:600;">🔒 개인</span>
                <button class="icon-btn" id="lockBtn" onclick="toggleLock()" title="요약 보기" style="width:auto;padding:0 10px;font-size:12px;white-space:nowrap;">☐ 요약</button>
                <button class="btn-save-top" onclick="saveEvent()">저장</button>
                <button class="icon-btn close-btn" onclick="closeModal()">✕</button>
            </div>
        </div>
        <div class="locked-banner" id="lockedBanner">📄&nbsp; 요약 보기 상태입니다 — 전체 내용을 보거나 수정하려면 '요약'을 해제하세요</div>
        <div id="balanceBanner" style="display:none;align-items:center;gap:8px;background:rgba(200,80,80,0.1);border:1px solid rgba(200,80,80,0.35);border-radius:8px;padding:8px 14px;font-size:12px;letter-spacing:0.05em;color:#e07070;margin:10px 28px 0;">
            <span style="font-size:15px;">💰</span>
            <span id="balanceBannerText">잔금 있음</span>
        </div>

        <div class="modal-body">
            {{-- 잠금 요약 뷰 (isLocked=true 일 때 표시) --}}
            <div id="lockSummary" class="lock-summary"></div>

            {{-- 미팅/내방 옵션 (미팅/내방 카테고리 전용) --}}
            <div class="field-section" id="visitOptsSection" style="display:none;">
                <div class="field-group">
                    <label class="field-label">내방 옵션</label>
                    <div class="visit-opts" id="visitOptsList"></div>
                </div>
            </div>

            {{-- 이사세팅 출발지 (방문의뢰 + 의뢰주제=이사세팅 시 노출) --}}
            <div class="field-section" id="moveFromBlock" style="display:none;">
                <div class="field-group">
                    <label class="field-label" for="moveFromLocation" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <span>🚚 출발지 <span style="font-weight:400;color:var(--text-muted);">(이사 전 장소)</span></span>
                        <label style="display:inline-flex;align-items:center;gap:5px;font-weight:400;font-size:12px;color:var(--text-muted);cursor:pointer;">
                            <input type="checkbox" id="moveNoFrom" onchange="onMoveNoFromToggle()"> 출발지 없음
                        </label>
                    </label>
                    <div class="location-input-wrap" id="moveFromInputWrap">
                        <textarea class="field-input field-textarea" id="moveFromLocation" placeholder="주소 검색 버튼으로 입력하세요" autocomplete="off" rows="2" readonly onclick="searchMoveFrom()" style="min-height:40px;resize:none;cursor:pointer;background:var(--surface2);"></textarea>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="addr-search-btn" onclick="searchMoveFrom()" title="주소 검색">🔍 주소 검색</button>
                            <button type="button" class="addr-search-btn" onclick="clearMoveFrom()" title="주소 지우기">✕ 지우기</button>
                        </div>
                        <input class="field-input" id="moveFromDetail" placeholder="상세주소 (동/호수 등) 직접 입력" autocomplete="off" style="margin-top:2px;">
                    </div>
                    <div id="moveNoFromNote" style="display:none;font-size:12px;color:var(--text-muted);padding:4px 2px;">출발지 없이 도착지(이사 후 장소)만 저장됩니다.</div>
                    <input type="hidden" id="moveFromAddress" value="">
                </div>
            </div>

            {{-- 장소 (이사세팅 시 '도착지'로 라벨 전환) --}}
            <div class="field-section" id="addressBlock">
                <div class="field-group">
                    <label class="field-label" for="modalLocation"><span id="addrBlockLabel">장소</span> <span class="req" id="addrReqMark">*</span></label>
                    <div class="location-input-wrap">
                        <textarea class="field-input field-textarea" id="modalLocation" placeholder="주소 검색 버튼으로 입력하세요" autocomplete="off" rows="2" readonly onclick="searchCalAddr()" style="min-height:40px;resize:none;cursor:pointer;background:var(--surface2);"></textarea>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="addr-search-btn" onclick="searchCalAddr()" title="주소 검색">🔍 주소 검색</button>
                            <button type="button" class="addr-search-btn" onclick="clearCalAddr()" title="주소 지우기">✕ 지우기</button>
                            <span style="font-size:11px;color:var(--text-muted);">도로명은 검색으로만, 상세주소는 아래 직접 입력</span>
                        </div>
                        <input class="field-input" id="modalLocationDetail" placeholder="상세주소 (동/호수 등) 직접 입력" autocomplete="off" style="margin-top:2px;">
                    </div>
                    <input type="hidden" id="modalAddress" value="">
                </div>
            </div>

            {{-- 날짜/시간 --}}
            <div class="datetime-section">
                <div class="allday-row">
                    <div class="toggle-wrap" id="alldayToggle" onclick="toggleAllDay()">
                        <div class="toggle-track" id="alldayTrack"><div class="toggle-thumb"></div></div>
                        <span class="toggle-label">종일</span>
                    </div>
                </div>
                <div id="standardDtRows">
                    <div class="dt-row">
                        <span class="dt-label">시작</span>
                        <input class="dt-input" type="date" id="startDate">
                        <input type="hidden" id="startTime" value="13:00">
                        <div class="time-picker-trigger dt-input" id="startTimeTrigger" onclick="openTimePicker(this,'startTime')">13:00</div>
                    </div>
                    <div class="dt-row">
                        <span class="dt-label">종료</span>
                        <input class="dt-input" type="date" id="endDate">
                        <input type="hidden" id="endTime" value="14:00">
                        <div class="time-picker-trigger dt-input" id="endTimeTrigger" onclick="openTimePicker(this,'endTime')">14:00</div>
                    </div>
                </div>
                <div id="goldDtRow" style="display:none;align-items:center;gap:6px;flex-wrap:wrap;width:100%;">
                    <input class="dt-input" type="date" id="goldStartDate" style="flex:2;min-width:0;">
                    <input type="hidden" id="goldStartTime" value="13:00">
                    <div class="time-picker-trigger dt-input" id="goldStartTimeTrigger" onclick="openTimePicker(this,'goldStartTime')" style="flex:1;min-width:0;">13:00</div>
                    <span style="color:var(--text-muted);font-size:13px;flex-shrink:0;">~</span>
                    <input class="dt-input" type="date" id="goldEndDate" style="flex:2;min-width:0;">
                    <input type="hidden" id="goldEndTime" value="14:00">
                    <div class="time-picker-trigger dt-input" id="goldEndTimeTrigger" onclick="openTimePicker(this,'goldEndTime')" style="flex:1;min-width:0;">14:00</div>
                </div>
            </div>

            {{-- 배송 현황 (방문의뢰·촬영/스튜디오, 저장된 일정만) --}}
            <div class="field-section" id="shipmentSection" style="display:none;">
                <div class="field-group">
                    <label class="field-label" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" onclick="toggleShipmentBody()">
                        <span><span class="ship-caret" id="shipCaret">▸</span> 📦 배송 현황 <span id="shipSummaryBadge" style="font-weight:400;"></span></span>
                        <button type="button" class="ship-mini-btn" onclick="event.stopPropagation();refreshShipments()" title="배송상태 새로고침">🔄 새로고침</button>
                    </label>
                    <div id="shipmentBody" style="display:none;">
                        <div id="shipmentList"></div>
                        <div class="ship-add-row">
                            <select class="ship-input" id="shipCarrier" style="flex:0 0 130px;"></select>
                            <input class="ship-input" id="shipTrackingNo" placeholder="송장번호" inputmode="numeric" style="flex:1;min-width:0;" onkeydown="if(event.key==='Enter'&&!event.isComposing){event.preventDefault();addShipment();}">
                            <button type="button" class="ship-mini-btn primary" onclick="addShipment()">+ 등록</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            {{-- 공통 필드 (비-gold/비-teal) --}}
            <div class="common-only field-section">
                <div class="field-group">
                    <label class="field-label">상세 설명</label>
                    <textarea class="field-textarea" id="commonDesc" placeholder="상세 내용을 입력하세요"></textarea>
                </div>
                <div class="field-group">
                    <label class="field-label">전달사항</label>
                    <textarea class="field-textarea" id="commonHandoverNote" placeholder="전달사항을 입력하세요" style="min-height:60px;"></textarea>
                </div>
            </div>

            {{-- 의뢰자 검색/연결 (사내업무/휴가 제외) --}}
            {{-- 알림 + 반복 (전 유형 공통) --}}
            <div class="field-section" id="notifRepeatSection">
                <div class="field-group" id="notifGroup">
                    <label class="field-label">🔔 알림</label>
                    <div class="notif-row">
                        <select class="notif-select" id="notifSelect">
                            <option value="">알림 없음</option>
                            <option value="0">정시 (일정 시작 시간)</option>
                            <option value="5">5분 전</option>
                            <option value="10">10분 전</option>
                            <option value="15">15분 전</option>
                            <option value="30">30분 전</option>
                            <option value="60">1시간 전</option>
                            <option value="120">2시간 전</option>
                            <option value="1440">하루 전 오전 9시</option>
                        </select>
                        <span class="notif-allday-label" id="notifAlldayLabel" style="display:none;">당일 오전 9시 발송</span>
                    </div>
                </div>
                <div class="field-group" id="repeatGroup">
                    <label class="field-label">🔁 반복</label>
                    <div class="notif-row" style="flex-wrap:wrap;gap:6px;align-items:center;">
                        <select class="notif-select" id="repeatFreq" onchange="onRepeatFreqChange()">
                            <option value="">반복 없음</option>
                            <option value="daily">매일</option>
                            <option value="weekly">매주</option>
                            <option value="monthly">매월</option>
                            <option value="custom">사용자 지정</option>
                        </select>
                        <span id="repeatCustomWrap" style="display:none;align-items:center;gap:4px;">
                            <input type="number" class="field-input" id="repeatInterval" min="1" max="99" value="2" style="width:60px;padding:6px 8px;">
                            <select class="notif-select" id="repeatUnit">
                                <option value="day">일마다</option>
                                <option value="week">주마다</option>
                                <option value="month">개월마다</option>
                            </select>
                        </span>
                        <span id="repeatUntilWrap" style="display:none;align-items:center;gap:4px;">
                            <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;">종료일</span>
                            <input type="date" class="field-input" id="repeatUntil" style="width:140px;padding:6px 8px;">
                        </span>
                    </div>
                    <div id="repeatEditNote" style="display:none;font-size:11px;color:var(--text-muted);margin-top:4px;">반복 설정은 새 일정 등록 시에만 지정할 수 있습니다. 이 일정만 수정됩니다.</div>
                </div>
            </div>

            <div class="field-section" id="clientLinkSection">
            <div class="section-heading" style="margin-bottom:4px;">의뢰자 / 프로젝트</div>
            <div class="field-group">
                <label class="field-label">의뢰자 검색</label>
                <div style="position:relative;">
                    <input class="field-input" id="clientSearchInput" placeholder="이름/닉네임/전화번호로 검색" autocomplete="off" oninput="searchClients(this.value)" onfocus="loadRecentClients()">
                    <div id="clientSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--surface);border:1px solid var(--border);border-radius:0 0 8px 8px;max-height:300px;overflow-y:auto;z-index:10;box-shadow:0 4px 16px rgba(0,0,0,0.15);"></div>
                </div>
            </div>
            <div id="linkedClientInfo" style="display:none;padding:10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-size:11px;color:var(--text-muted);">연결된 의뢰자</span>
                        <div style="font-size:13px;font-weight:600;" id="linkedClientName"></div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <a id="linkedClientLink" href="#" target="_blank" data-always-active style="font-size:11px;padding:3px 10px;text-decoration:none;border:1px solid var(--border);border-radius:6px;color:var(--text-muted);display:inline-flex;align-items:center;">보기</a>
                        <button type="button" onclick="unlinkClient()" style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
                    </div>
                </div>
            </div>
            <div id="projectSelectWrap" style="display:none;" class="field-group">
                <label class="field-label">프로젝트 연결</label>
                <select class="field-input" id="projectSelect" style="cursor:pointer;">
                    <option value="">프로젝트 선택 (선택사항)</option>
                </select>
            </div>
            </div>{{-- /field-section (의뢰자/프로젝트) --}}

            {{-- Gold 템플릿 (방문의뢰) --}}
            <div class="gold-only" style="display:none;flex-direction:column;gap:14px;">
                <div class="section-heading">의뢰자 정보</div>
                <div class="field-row" style="gap:10px;">
                    <div class="field-group"><label class="field-label">의뢰자 닉네임 <span class="req">*</span></label><input class="field-input" id="g_nickname" placeholder="닉네임"></div>
                    <div class="field-group"><label class="field-label">의뢰자 이름 <span class="req">*</span></label><input class="field-input" id="g_name" placeholder="이름"></div>
                    <div class="field-group"><label class="field-label">전화번호 <span class="req">*</span></label><input class="field-input" id="g_phone" placeholder="010-0000-0000"></div>
                </div>

                <div class="field-row">
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">플랫폼 <span class="req">*</span></label>
                        <div id="g_platform_wrap" style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                            <div class="radio-group" id="g_platform_group" style="flex-wrap:nowrap;gap:5px;flex-shrink:0;">
                                <div class="radio-btn" data-val="SOOP">SOOP</div>
                                <div class="radio-btn" data-val="치지직">치지직</div>
                                <div class="radio-btn" data-val="유튜브">유튜브</div>
                                <div class="radio-btn" data-val="틱톡">틱톡</div>
                                <div class="radio-btn" data-val="기타">기타</div>
                            </div>
                            <div class="conditional-field" id="g_platform_etc_wrap" style="margin-top:0;flex:1;min-width:80px;"><input class="field-input" id="g_platform_etc" placeholder="직접 입력" style="font-size:13px;"></div>
                        </div>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field-group" style="flex:0 0 auto;">
                        <label class="field-label">경력 여부</label>
                        <div class="radio-group" id="g_career_group">
                            <div class="radio-btn active" data-val="처음">처음</div>
                            <div class="radio-btn" data-val="초보">초보</div>
                            <div class="radio-btn" data-val="경력">경력</div>
                        </div>
                    </div>
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">유입 경로</label>
                        <div id="g_source_wrap" style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                            <div class="radio-group" id="g_source_group" style="flex-wrap:nowrap;gap:5px;flex-shrink:0;">
                                <div class="radio-btn" data-val="광고">📢 광고</div>
                                <div class="radio-btn" data-val="검색">🔍 검색</div>
                                <div class="radio-btn" data-val="소개">🤝 소개</div>
                            </div>
                            <div class="conditional-field" id="g_source_ref_wrap" style="margin-top:0;flex:1;min-width:100px;"><input class="field-input" id="g_source_ref" placeholder="소개해 준 분 이름" style="font-size:13px;"></div>
                        </div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">방송 주제</label>
                    <div id="g_topic_wrap" style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                        <div class="radio-group" id="g_topic_group" style="flex-wrap:wrap;gap:5px;flex-shrink:0;">
                            <div class="radio-btn" data-val="소통">소통</div>
                            <div class="radio-btn" data-val="게임">게임</div>
                            <div class="radio-btn" data-val="노래">노래</div>
                            <div class="radio-btn" data-val="먹방">먹방</div>
                            <div class="radio-btn" data-val="야외">야외</div>
                            <div class="radio-btn" data-val="버추얼">버추얼</div>
                            <div class="radio-btn" data-val="코인">코인</div>
                            <div class="radio-btn" data-val="주식">주식</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                            <div class="radio-btn" data-val="미정">미정</div>
                        </div>
                        <div class="conditional-field" id="g_topic_etc_wrap" style="margin-top:0;flex:1;min-width:100px;"><input class="field-input" id="g_topic_etc" placeholder="방송 주제를 직접 입력하세요" style="font-size:13px;"></div>
                    </div>
                </div>

                <div class="field-group" style="margin-top:10px;">
                    <label class="field-label">예산 성향</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                        <div class="radio-group" id="g_budget_group" style="flex-wrap:wrap;gap:5px;">
                            <div class="radio-btn" data-val="풍족">풍족</div>
                            <div class="radio-btn" data-val="부족">부족</div>
                            <div class="radio-btn" data-val="모름">모름</div>
                            <div class="radio-btn" data-val="직접입력">직접입력</div>
                        </div>
                        <div class="conditional-field" id="g_budget_etc_wrap" style="margin-top:0;flex:1;min-width:120px;"><input class="field-input" id="g_budget_etc" placeholder="예산 직접 입력" style="font-size:13px;"></div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="section-heading">장비 목록</div>
                <div class="field-group">
                    <label class="field-label">장비 목록</label>
                    <textarea class="field-textarea" id="g_equipment" placeholder="사용 장비를 입력하세요" style="min-height:195px;"></textarea>
                </div>

                <div class="divider"></div>

                <div class="field-group">
                    <div class="field-label">일정 옵션</div>
                    <div class="special-opts" id="schedEventOpts">
                        <div class="special-opt-btn" data-seopt="fast"><span class="opt-icon">←</span>빠른 일정 희망</div>
                        <div class="special-opt-btn" data-seopt="urgent"><span class="opt-icon">🚨</span>긴급 일정</div>
                        <div class="special-opt-btn" data-seopt="after"><span class="opt-icon">→</span><span id="schedAfterLabel">날짜 선택</span> 이후 희망</div>
                    </div>
                    <div id="schedReasonWrap" style="display:none;margin-top:6px;">
                        <input class="field-input" id="schedAfterReason" placeholder="사유 (선택)" style="font-size:13px;">
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">일정 관련 옵션</div>
                    <div class="special-opts" id="scheduleOpts">
                        <div class="sched-opt-btn" data-sopt="suggest"><span class="opt-icon">💬</span>제안</div>
                        <div class="sched-opt-btn" data-sopt="hope"><span class="opt-icon">🙏</span>희망</div>
                        <div class="sched-opt-btn" data-sopt="target"><span class="opt-icon">🎯</span>목표</div>
                    </div>
                    <div class="sched-opt-desc" id="schedOptDesc"></div>
                </div>
                <div class="field-group">
                    <div class="field-label">특수 옵션</div>
                    <div class="special-opts" id="specialOpts">
                        <div class="special-opt-btn" data-opt="car"><span class="opt-icon">🚗</span>차량 이용 필요</div>
                        <div class="special-opt-btn" data-opt="brief"><span class="opt-icon">💼</span>들고 갈 제품 있음</div>
                        <div class="special-opt-btn" data-opt="group"><span class="opt-icon">👥</span>2인필수 작업</div>
                        <div class="special-opt-btn" data-opt="ladder"><span class="opt-icon">▤</span>사다리 필요</div>
                        <div class="special-opt-btn" data-opt="pet"><span class="opt-icon">🐾</span>반려동물 있음</div>
                    </div>
                    <div id="specialReasonWrap" style="display:none;margin-top:6px;">
                        <input class="field-input" id="specialReason" placeholder="특수옵션 사유 (선택)" style="font-size:13px;">
                    </div>
                </div>

                <div class="divider"></div>

                <div class="section-heading">의뢰 내용</div>
                <div class="field-group">
                    <label class="field-label">의뢰 주제</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <div class="radio-group" id="g_req_topic_group">
                            <div class="radio-btn" data-val="처음세팅">처음세팅</div>
                            <div class="radio-btn" data-val="추가세팅">추가세팅</div>
                            <div class="radio-btn" data-val="이사세팅">이사세팅</div>
                            <div class="radio-btn" data-val="렌탈">렌탈</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                        </div>
                        <div class="conditional-field" id="g_req_topic_etc_wrap"><input class="field-input" id="g_req_topic_etc" placeholder="직접 입력"></div>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">의뢰 세부항목</label>
                    <textarea class="field-textarea" id="g_req_detail" placeholder="세부 항목을 입력하세요"></textarea>
                </div>
                <div class="field-group">
                    <label class="field-label">특이사항</label>
                    <textarea class="field-textarea" id="g_special" placeholder="특이사항을 입력하세요" style="min-height:65px;"></textarea>
                </div>

                <div class="divider"></div>

                <div class="section-heading">결제 정보</div>
                <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                    <div class="field-group" style="flex:none;">
                        <div class="field-label">결제 여부</div>
                        <div class="radio-group" id="g_paid_group">
                            <div class="radio-btn active" data-val="미결제">미결제</div>
                            <div class="radio-btn" data-val="결제완료">결제완료</div>
                        </div>
                    </div>
                    <div class="field-group" style="flex:1;min-width:0;">
                        <label class="field-label">결제된 금액</label>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input class="field-input" id="g_estimate_amount" placeholder="금액 입력" type="text" style="flex:1;min-width:0;">
                            <button type="button" id="g_estimate_btn" onclick="extractEstimateAmount()" style="background:none;border:1px solid var(--border);color:var(--text-muted);border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;white-space:nowrap;transition:all 0.2s;flex-shrink:0;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">🔍 추출</button>
                            <span id="g_estimate_status" style="font-size:11px;color:var(--text-muted);white-space:nowrap;"></span>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                    <div class="field-group" style="flex:none;">
                        <div class="field-label">주문 제품</div>
                        <div class="radio-group" id="g_order_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" id="g_delivery_wrap" style="flex:none;display:none;">
                        <div class="field-label">배송완료</div>
                        <div class="radio-group" id="g_delivery_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" style="flex:none;">
                        <div class="field-label">잔금 여부</div>
                        <div class="radio-group" id="g_balance_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" id="g_balance_amount_outer" style="flex:1;min-width:0;">
                        <div class="conditional-field" id="g_balance_amount_wrap">
                            <label class="field-label">잔금 금액</label>
                            <input class="field-input" id="g_balance_amount" placeholder="잔금 금액 (원)" type="text">
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="section-heading">첨부 이미지</div>
                <div class="img-upload-group">
                    <div class="img-upload-label">견적서</div>
                    <div style="display:flex;gap:8px;margin-bottom:6px;">
                        <button type="button" onclick="triggerAttach('quote')" class="action-btn" style="flex:1;">📄 견적서 첨부</button>
                        <button type="button" onclick="openEstimateSearch()" class="action-btn" style="flex:1;">📋 견적서 불러오기</button>
                    </div>
                    <div class="img-upload-zone" id="quoteZone">
                        <input type="file" id="fileQuote" multiple accept="image/*" onchange="handleImgFiles('quote',this.files)">
                        📄 견적서 이미지를 클릭 또는 드래그하여 추가
                    </div>
                    <div class="img-grid" id="quoteGrid"></div>
                    <div id="linkedEstimateInfo" style="display:none;margin-top:8px;padding:10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <span style="font-size:11px;color:var(--text-muted);">연결된 견적서</span>
                                <div style="font-size:13px;font-weight:600;" id="linkedEstimateTitle"></div>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <button type="button" onclick="openLinkedEstimate()" class="estimate-btn" data-always-active style="font-size:11px;padding:3px 10px;border:1px solid var(--border);border-radius:6px;background:none;color:var(--text-muted);cursor:pointer;">보기</button>
                                <button type="button" onclick="unlinkEstimate()" data-always-active style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="img-upload-group">
                    <div class="img-upload-label">레퍼런스</div>
                    <div class="img-upload-zone" id="refZone">
                        <input type="file" id="fileReference" multiple accept="image/*" onchange="handleImgFiles('reference',this.files)">
                        📷 레퍼런스 이미지를 클릭 또는 드래그하여 추가
                    </div>
                    <div class="img-grid" id="refGrid"></div>
                </div>
                <div class="img-upload-group">
                    <div class="img-upload-label">방 사진</div>
                    <div class="img-upload-zone" id="roomZone">
                        <input type="file" id="fileRoom" multiple accept="image/*" onchange="handleImgFiles('room',this.files)">
                        🏠 방 사진을 클릭 또는 드래그하여 추가
                    </div>
                    <div class="img-grid" id="roomGrid"></div>
                </div>
                <div class="img-upload-group">
                    <div class="img-upload-label">첨부 파일</div>
                    <div class="img-upload-zone" id="generalZone">
                        <input type="file" id="fileGeneral" multiple onchange="handleImgFiles('general',this.files)">
                        📎 파일을 클릭 또는 드래그하여 추가 (PDF, 문서 등 가능)
                    </div>
                    <div class="img-grid" id="generalGrid"></div>
                </div>
            </div>

            {{-- Teal 템플릿 (원격/방송룸) --}}
            <div class="teal-only" style="display:none;flex-direction:column;gap:14px;">
                <div class="field-group">
                    <label class="field-label">유형 선택</label>
                    <div class="radio-group" id="teal_mode_group">
                        <div class="radio-btn active" data-val="remote">🖥 원격</div>
                        <div class="radio-btn" data-val="studio">🎙 방송룸 이용</div>
                    </div>
                </div>
                <div id="teal_remote_fields" style="display:flex;flex-direction:column;gap:12px;">
                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">원격 대상자 이름(닉네임) <span class="req">*</span></label>
                            <input class="field-input" id="t_remote_name" placeholder="이름 또는 닉네임">
                        </div>
                        <div class="field-group">
                            <label class="field-label">방송 플랫폼 <span class="req">*</span></label>
                            <input class="field-input" id="t_remote_platform" placeholder="유튜브, 아프리카TV 등">
                        </div>
                    </div>
                    <div class="field-group" style="margin-top:4px;">
                        <label class="field-label">원격 의뢰 내용</label>
                        <textarea class="field-textarea" id="t_remote_content" placeholder="원격으로 진행할 내용을 입력하세요"></textarea>
                    </div>
                </div>
                <div id="teal_studio_fields" style="display:none;flex-direction:column;gap:12px;">
                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">방송룸 이용자 이름(닉네임) <span class="req">*</span></label>
                            <input class="field-input" id="t_studio_name" placeholder="이름 또는 닉네임">
                        </div>
                        <div class="field-group">
                            <label class="field-label">방송 플랫폼 <span class="req">*</span></label>
                            <input class="field-input" id="t_studio_platform" placeholder="유튜브, 아프리카TV 등">
                        </div>
                    </div>
                    <div class="field-group" style="margin-top:4px;">
                        <label class="field-label">방송룸 이용 내용</label>
                        <textarea class="field-textarea" id="t_studio_content" placeholder="방송룸 이용 내용을 입력하세요"></textarea>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">메모 (선택)</label>
                    <textarea class="field-textarea" id="t_desc" placeholder="추가 메모를 입력하세요"></textarea>
                </div>
            </div>

            {{-- 일반 첨부 파일 --}}
            <div id="generalAttachSection">
                <div class="divider" style="margin-bottom:14px;"></div>
                <div class="field-group">
                    <div class="field-label">첨부 파일</div>
                    <div class="upload-zone" id="uploadZone" style="border:1px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;position:relative;">
                        <input type="file" id="generalFileInput" multiple accept="*/*" onchange="handleGeneralFiles(this.files)" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
                        <div style="font-size:22px;margin-bottom:5px;">📎</div>
                        <div style="font-size:12px;color:var(--text-muted);">파일을 <span style="color:var(--accent);">클릭</span>하거나 드래그하여 첨부하세요<br><small style="opacity:0.55">이미지는 미리보기가 지원됩니다</small></div>
                    </div>
                    <div class="img-grid" id="generalAttachGrid" style="margin-top:8px;"></div>
                </div>
            </div>

        </div>{{-- modal-body end --}}

        <div id="reasonField" style="display:none; padding:0 28px 12px;">
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px; letter-spacing:0.04em;">일정 변경 사유 <span style="color:var(--red);">* 날짜/시간 변경 시 필수</span></div>
            <textarea id="modalReason" rows="2" placeholder="예: 의뢰자 요청으로 일정 변경" style="width:100%; padding:8px 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; resize:vertical; box-sizing:border-box; font-family:inherit;"></textarea>
        </div>

        <div class="modal-footer">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">일정 삭제</button>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn-log" id="btnLog" style="display:none" onclick="openHistoryFromEdit()">📋 <span>변경 로그</span></button>
                <button class="btn-complete" id="btnComplete" style="display:none" onclick="toggleCompleteFromDetail()">✓ 완료</button>
                <button class="btn-save" onclick="saveEvent()">저장</button>
            </div>
        </div>
    </div>
    <div class="modal-external-btns">
        <button class="modal-external-close" onclick="closeModal()" title="닫기">✕</button>
        <button class="modal-external-action" id="modalExternalAction" onclick="saveEvent()" title="저장">저장</button>
        <button class="modal-external-complete" id="extCompleteBtn" style="display:none" onclick="toggleCompleteFromDetail()" title="완료">완료</button>
    </div>
    </div>{{-- modal-wrapper end --}}
</div>
<!-- 견적서 검색 모달 -->
<div class="modal-overlay" id="estimateSearchOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:540px; max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header" style="padding:16px 20px 12px; flex-shrink:0;">
            <div style="font-size:16px; font-weight:600;">견적서 불러오기</div>
            <button class="icon-btn close-btn" onclick="document.getElementById('estimateSearchOverlay').style.display='none'">✕</button>
        </div>
        <div style="padding:0 20px 12px; flex-shrink:0;">
            <input class="field-input" id="estimateSearchInput" type="text" placeholder="🔍 의뢰자명, 견적서 번호로 검색..." oninput="searchEstimates(this.value)">
        </div>
        <div id="estimateSearchResults" style="flex:1; overflow-y:auto; padding:0 20px 16px;">
            <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">로딩 중...</div>
        </div>
    </div>
</div>

<!-- 일정 상세 모달 (조회전용) -->
<div class="modal-overlay" id="detailOverlay" style="display:none;" onclick="if(event.target===this) closeDetail()">
    <div class="modal" style="max-width:620px;">
        <div class="modal-strip" id="detailStrip"></div>
        <div class="modal-header" style="padding-bottom:12px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span class="modal-date-badge" id="detailDateBadge"></span>
                    <span class="type-badge" id="detailTypeBadge">● 방문의뢰</span>
                </div>
                <div style="font-size:20px;font-weight:500;margin-top:4px;" id="detailTitle"></div>
                <div id="detailAssignees" style="margin-top:6px;"></div>
            </div>
            <div class="modal-header-btns">
                <button class="icon-btn close-btn" onclick="closeDetail()">✕</button>
            </div>
        </div>
        <div class="modal-body" id="detailBody" style="gap:10px;"></div>
        <div class="modal-footer">
            <div style="display:flex;gap:6px;">
                <button class="btn-delete" onclick="deleteEventFromDetail()">삭제</button>
                <button class="btn-log" style="display:inline-flex;" onclick="openHistoryModal()">📋 수정내역</button>
            </div>
            <div style="display:flex;gap:6px;">
                <button class="btn-log" id="detailCompleteBtn" onclick="toggleCompleteFromDetail()">✓ 완료</button>
                <button class="btn-save" onclick="editFromDetail()">수정</button>
            </div>
        </div>
    </div>
</div>

<!-- 수정내역 모달 -->
<div class="modal-overlay" id="historyOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:500px; max-height:70vh; overflow-y:auto;">
        <div class="modal-header">
            <div class="modal-title">수정내역</div>
            <button class="modal-close" onclick="document.getElementById('historyOverlay').style.display='none'">×</button>
        </div>
        <div id="historyBody"><div style="padding:20px; text-align:center; color:var(--text-muted);">로딩 중...</div></div>
    </div>
</div>

<!-- 삭제 사유 입력 모달 -->
<div class="modal-overlay" id="deleteReasonOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header" style="padding:16px 20px 12px;">
            <div style="font-size:16px; font-weight:600;">일정 삭제</div>
            <button class="modal-close" onclick="document.getElementById('deleteReasonOverlay').style.display='none'">×</button>
        </div>
        <div style="padding:0 20px 12px; color:var(--text-muted); font-size:13px; line-height:1.5;">
            이 일정을 삭제합니다. 휴지통으로 이동되며, <span style="color:var(--text);">삭제 사유</span>를 반드시 입력해야 합니다.
        </div>
        <div style="padding:0 20px 10px; display:none;" id="delSeriesWrap">
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text);">
                <input type="checkbox" id="delSeriesChk" style="width:16px;height:16px;accent-color:var(--red);">
                🔁 반복 일정입니다 — 이후 반복 일정도 함께 삭제
            </label>
        </div>
        <div style="padding:0 20px 16px;">
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">삭제 사유 <span style="color:var(--red);">*</span></div>
            <textarea id="deleteReasonInput" rows="3" placeholder="예: 일정 취소됨, 의뢰자 요청 등" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; resize:vertical; box-sizing:border-box; font-family:inherit;"></textarea>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; padding:0 20px 20px;">
            <button class="nav-btn" onclick="document.getElementById('deleteReasonOverlay').style.display='none'" style="width:auto; padding:6px 14px; font-size:12px;">취소</button>
            <button class="btn-delete" onclick="confirmDeleteEvent()" style="padding:6px 14px;">삭제</button>
        </div>
    </div>
</div>

<!-- 휴지통 모달 -->
<div class="modal-overlay" id="trashOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:640px; max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <div class="modal-title"><x-icon name="trash" :size="16"/> 휴지통</div>
            <button class="modal-close" onclick="document.getElementById('trashOverlay').style.display='none'">×</button>
        </div>
        <div style="padding:10px 16px; border-bottom:1px solid var(--border); display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
            <label style="font-size:12px; color:var(--text-muted); display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="checkbox" id="trashSelectAll" onchange="trashToggleAll(this.checked)"> 전체 선택
            </label>
            <span class="text-muted" id="trashCount" style="font-size:11px; color:var(--text-muted);">0건</span>
            <div style="flex:1;"></div>
            <button class="nav-btn" id="trashRestoreBtn" onclick="trashRestoreSelected()" style="font-size:11px; width:auto; padding:4px 10px;" disabled>선택 복원</button>
            <button class="nav-btn" id="trashClearBtn" onclick="trashEmptySelected()" style="font-size:11px; width:auto; padding:4px 10px; color:var(--red); border-color:var(--red);" disabled>선택 정리</button>
            <button class="nav-btn" onclick="trashEmptyAll()" style="font-size:11px; width:auto; padding:4px 10px; color:var(--red); border-color:var(--red);">전체 비우기</button>
        </div>
        <div id="trashBody" style="flex:1; overflow-y:auto; padding:8px;">
            <div style="padding:30px; text-align:center; color:var(--text-muted);">로딩 중...</div>
        </div>
    </div>
</div>

<!-- 이미지 라이트박스 -->
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <button class="lightbox-nav prev" onclick="lightboxNav(-1)">‹</button>
    <button class="lightbox-nav next" onclick="lightboxNav(1)">›</button>
    <div class="lightbox-img-wrap" id="lightboxWrap">
        <img id="lightboxImg" src="" alt="">
    </div>
    <div class="lightbox-zoom-info" id="lightboxZoomInfo">100%</div>
    <div class="lightbox-filename" id="lightboxFilename"></div>
    <div class="lightbox-hint">스크롤: 확대/축소 · 더블클릭: 원본 크기 · 드래그: 이동</div>
</div>

@endsection

<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const DAYS_KO = ['일','월','화','수','목','금','토'];

// 한국 공휴일 (양력 고정 + 음력 변동 2025~2027)
const KR_HOLIDAYS = {
    // 양력 고정
    '01-01':'신정','03-01':'삼일절','05-05':'어린이날','06-06':'현충일','08-15':'광복절','10-03':'개천절','10-09':'한글날','12-25':'성탄절',
    // 2025 음력 변동
    '2025-01-28':'설날 연휴','2025-01-29':'설날','2025-01-30':'설날 연휴','2025-05-05':'부처님오신날','2025-10-05':'추석 연휴','2025-10-06':'추석','2025-10-07':'추석 연휴','2025-10-08':'대체공휴일',
    // 2026 음력 변동
    '2026-02-16':'설날 연휴','2026-02-17':'설날','2026-02-18':'설날 연휴','2026-05-24':'부처님오신날','2026-09-24':'추석 연휴','2026-09-25':'추석','2026-09-26':'추석 연휴',
    // 2027 음력 변동
    '2027-02-06':'설날 연휴','2027-02-07':'설날','2027-02-08':'설날 연휴','2027-05-13':'부처님오신날','2027-10-14':'추석 연휴','2027-10-15':'추석','2027-10-16':'추석 연휴',
};
function getHoliday(dateStr) {
    if (!dateStr) return null;
    return KR_HOLIDAYS[dateStr] || KR_HOLIDAYS[dateStr.substring(5)] || null;
}

const canEditCalendar = @json(Auth::user()->hasPermission('calendar.edit'));
const isGuestUser = @json(Auth::user()->isGuest());
// 첫 화면(이번 달) 이벤트 서버 주입 — 초기 /api/events 왕복 제거
const INITIAL_EVENTS = @json($initialEvents ?? null);
const CAL_USER_ID = @json(Auth::id());
const CAL_VISIT_OPTIONS = @json(collect(explode("\n", (string) \App\Models\Setting::get('calendar_visit_options', '')))->map(fn ($s) => trim($s))->filter()->values());
const HOURS = Array.from({length:14}, (_,i) => i+9); // 9시~22시

let currentYear, currentMonth, currentWeekStart, currentDay;
let events = [], assignees = [], selectedAssignees = [];
let editingId = null, currentColor = 'gold', currentView = 'month';
let editingOrigDT = null; // 편집 중 일정의 원본 날짜/시간 (변경 사유 필수 판정용)
// 종료일 정규화 — 역전(종료<시작) 데이터는 시작일 하루짜리로 취급
function evEnd(ev){
    const sd=(ev.start_date||'').substring(0,10);
    const ed=(ev.end_date||'').substring(0,10);
    return (ed && ed>=sd) ? ed : sd;
}
// 카테고리 색 — 관리 설정과 연동된 CSS 변수 반환 (커스텀 카테고리 포함)
function chipColor(c){
    if(c==='holiday') return 'var(--chip-red-bg)';
    return (window.CALENDAR_CATEGORIES&&window.CALENDAR_CATEGORIES[c])?`var(--chip-${c}-bg)`:'var(--accent)';
}
// 배송 상태 인라인 아이콘 (송장 없으면 빈 문자열) — 리스트/팝오버 공용
function shipStatusIcon(ev){
    if(!ev || !ev.shipments_count) return '';
    const d=ev.shipments_delivered_count||0, t=ev.shipments_count;
    const [ico,cls]=d===0?['✕','s-none']:(d<t?['△','s-part']:['○','s-all']);
    return `<span class="chip-ship ${cls}" title="배송 ${d}/${t}건 완료">${ico}</span>`;
}
// 일정 옵션(빠름/긴급/AS이후) 아이콘 맵
const SCHED_EVENT_ICONS={fast:'←',urgent:'🚨',after:'→'};
// 특수옵션 + 세부유형 + 일정옵션 아이콘 묶음 — 주/일간·목록·팝업 뷰 공용
function eventOptIconsHtml(ev){
    if(!ev) return '';
    let h='';
    (ev.special_opts||[]).forEach(o=>{ if(SPECIAL_ICONS[o]) h+=`<span class="ev-opt-ico" title="${(typeof SPECIAL_OPT_LABELS!=='undefined'&&SPECIAL_OPT_LABELS[o])||o}">${SPECIAL_ICONS[o]}</span>`; });
    if(ev.sched_opt&&SCHED_ICONS[ev.sched_opt]) h+=`<span class="ev-opt-ico">${SCHED_ICONS[ev.sched_opt]}</span>`;
    (ev.sched_event_opts||[]).forEach(o=>{ if(SCHED_EVENT_ICONS[o]) h+=`<span class="ev-opt-ico">${SCHED_EVENT_ICONS[o]}</span>`; });
    return h;
}
// 이사세팅 여부
function isMoveSetting(ev){
    if(!ev || ev.color!=='gold') return false;
    const rt=(ev.gold_data&&ev.gold_data.req_topic)||'';
    return rt.split(',').map(s=>s.trim()).includes('이사세팅');
}
// 이사세팅 출발/도착 2줄 HTML (리스트용) — 이사세팅 아닐 땐 빈 문자열
function moveAddrLinesHtml(ev){
    if(!isMoveSetting(ev)) return '';
    const g=ev.gold_data||{};
    const from = g.move_no_from ? '없음' : (g.move_from_location||g.move_from_address||'—');
    const to = ev.location||ev.address||'—';
    return `<div class="agenda-move"><span>출발: ${_esc(from)}</span><span>도착: ${_esc(to)}</span></div>`;
}
let expandedDays = new Set();

// ── 초기화 ──────────────────────────────────────────────────────
function init() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth();
    currentWeekStart = getWeekStart(now);
    currentDay = new Date(now); currentDay.setHours(0,0,0,0);
    loadAssignees();
    applyCalFz(); // 저장된 글자 크기 적용(라벨 갱신)
    if (Array.isArray(INITIAL_EVENTS)) {
        events = INITIAL_EVENTS; // 서버 주입분으로 즉시 렌더 (이번 달)
        renderView();
        loadEvents(); // 백그라운드로 라이브 API 대조 — 스냅샷 어긋남으로 인한 월간 누락 방지
    } else {
        renderView();
        loadEvents();
    }
}

function getWeekStart(d) {
    const r = new Date(d); r.setDate(r.getDate()-r.getDay()); r.setHours(0,0,0,0); return r;
}
function fmt(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function todayStr() { return fmt(new Date()); }

// ── 필터 ──
// 실제 렌더된 카테고리 칩(.filter-btn.active)에서 초기화 — 커스텀 카테고리도 누락 없이 매칭
let activeFilters = (function(){
    const keys=[...document.querySelectorAll('#filterBar .filter-btn')].map(b=>b.dataset.filter).filter(Boolean);
    keys.push('holiday'); // 공휴일은 칩이 없어도 항상 포함
    return new Set(keys.length?keys:['gold','teal','blue','red','green','purple','holiday']);
})();
let activeAssigneeIds = new Set(); // 비어있으면 전체, 값이 있으면 해당 담당자들(OR) 만
function toggleFilter(btn){
    const f=btn.dataset.filter;
    if(activeFilters.has(f)){activeFilters.delete(f);btn.classList.remove('active');}
    else{activeFilters.add(f);btn.classList.add('active');}
    renderView();
}
function isFiltered(ev){
    if (!activeFilters.has(ev.color)) return false;
    if (activeAssigneeIds.size > 0) {
        if (!Array.isArray(ev.assignees) || !ev.assignees.some(a => activeAssigneeIds.has(a.id))) return false;
    }
    return true;
}
function populateAssigneeFilter() {
    const sel = document.getElementById('assigneeFilter');
    if (!sel) return;
    // 이미 선택된 담당자는 드롭다운에서 제외
    sel.innerHTML = '<option value="">담당자 추가…</option>'
        + assignees
            .filter(a => !activeAssigneeIds.has(a.id))
            .map(a => `<option value="${a.id}">${(a.name||'').replace(/[<>&"]/g,'')}</option>`).join('');
    renderAssigneeChips();
}
function renderAssigneeChips() {
    const wrap = document.getElementById('assigneeFilterChips');
    if (!wrap) return;
    wrap.innerHTML = [...activeAssigneeIds].map(id => {
        const a = assignees.find(x => x.id === id);
        const name = a ? (a.name || `#${id}`) : `#${id}`;
        return `<span class="af-chip">${name.replace(/[<>&"]/g,'')}<button type="button" title="필터 해제" onclick="removeAssigneeFilter(${id})">✕</button></span>`;
    }).join('');
    const sel = document.getElementById('assigneeFilter');
    if (sel) sel.classList.toggle('active-filter', activeAssigneeIds.size > 0);
}
function onAssigneeFilterChange() {
    const sel = document.getElementById('assigneeFilter');
    const v = sel.value;
    if (v) {
        activeAssigneeIds.add(+v);
        populateAssigneeFilter(); // 선택된 항목을 드롭다운에서 제거 + 칩 갱신
        sel.value = '';
        renderView();
    }
}
function removeAssigneeFilter(id) {
    activeAssigneeIds.delete(id);
    populateAssigneeFilter();
    renderView();
}

// ── 이벤트 칩 생성 헬퍼 ──
const SPECIAL_ICONS={car:'🚗',brief:'💼',group:'👥',ladder:'▤',pet:'🐾'};
const SPECIAL_OPT_LABELS={car:'차량 이용 필요',brief:'들고 갈 제품 있음',group:'2인필수 작업',ladder:'사다리 필요',pet:'반려동물 있음'};
const SCHED_ICONS={suggest:'💬',hope:'🙏',target:'🎯'};
function buildChipHtml(ev){
    let html='';
    const time=ev.start_time?ev.start_time.substring(0,5):'';
    if(time) html+=`<span class="chip-time">${time}</span>`;
    // 특수 아이콘
    const specOpts=ev.special_opts||[];
    specOpts.forEach(o=>{if(SPECIAL_ICONS[o]) html+=`<span class="chip-special">${SPECIAL_ICONS[o]}</span>`;});
    // 제목 (의뢰자 이름은 표시하지 않음). flex:1로 늘어나서 담당자 배지를 우측으로 밀어냄
    const title=isGuestUser?(ev.location||'일정'):(ev.title||'');
    html+=`<span class="chip-title">${title}</span>`;
    // 일정 관련 아이콘
    if(ev.sched_opt&&SCHED_ICONS[ev.sched_opt]) html+=`<span class="sched-icon-badge">${SCHED_ICONS[ev.sched_opt]}</span>`;
    // 배송 상태: 등록만 ✕ / 일부 완료 △ / 전부 완료 ○
    if(ev.shipments_count>0){
        const shD=ev.shipments_delivered_count||0, shT=ev.shipments_count;
        const shCls=shD===0?'s-none':(shD<shT?'s-part':'s-all');
        const shIco=shD===0?'✕':(shD<shT?'△':'○');
        html+=`<span class="chip-ship ${shCls}" title="배송 ${shD}/${shT}건 완료">${shIco}</span>`;
    }
    // 담당자 — chip 우측 정렬. 2명 이상이면 첫 번째 이름 + '+N' (전체 명단은 hover 즉시 툴팁)
    if (ev.assignees && ev.assignees.length) {
        const names = ev.assignees.map(a => (a.name || '').trim()).filter(Boolean);
        if (names.length) {
            const first = names[0];
            const extra = names.length - 1;
            const display = extra > 0 ? `${first} +${extra}` : first;
            const attrEsc = s => s.replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const anames = extra > 0 ? ` data-anames="${attrEsc(names.join('|'))}" data-atitle="${attrEsc(title)}"` : '';
            html += `<span class="chip-badges"><span class="ev-assignee-badge"${anames}>${display}</span></span>`;
        }
    }
    return html;
}

// ── 담당자 전체 명단 툴팁 (hover 즉시 표시) ──
function assigneeNamesOf(ev){ return (ev.assignees||[]).map(a=>(a.name||'').trim()).filter(Boolean); }
const calNamesTip=document.createElement('div');
calNamesTip.id='calNamesTip';
document.body.appendChild(calNamesTip);
document.addEventListener('mouseover',e=>{
    const t=e.target.closest&&e.target.closest('[data-anames]');
    if(!t){ calNamesTip.classList.remove('show'); return; }
    // 제목 + 담당자 전체를 가로 한 줄로
    const tipTitle=t.dataset.atitle||'';
    const tipNames=t.dataset.anames.split('|').join(', ');
    calNamesTip.innerHTML=(tipTitle?`<span class="cnt-t">${_esc(tipTitle)}</span><span class="cnt-sep">·</span>`:'')+`<span class="cnt-n">${_esc(tipNames)}</span>`;
    calNamesTip.classList.add('show');
    const r=t.getBoundingClientRect();
    const tw=calNamesTip.offsetWidth, th=calNamesTip.offsetHeight;
    let left=r.left+r.width/2-tw/2, top=r.top-th-8;
    if(left<8)left=8;
    if(left+tw>window.innerWidth-8)left=window.innerWidth-tw-8;
    if(top<8)top=r.bottom+8;
    calNamesTip.style.left=left+'px';
    calNamesTip.style.top=top+'px';
});
window.addEventListener('scroll',()=>calNamesTip.classList.remove('show'),true);

// ── 뷰 전환 ─────────────────────────────────────────────────────
// ── 캘린더 글자 크기 조절 (노안 대응) ──
const CAL_FZ_KEY='calFontScale';
let calFzScale=parseFloat(localStorage.getItem(CAL_FZ_KEY)||'1')||1;
function applyCalFz(){
    document.documentElement.style.setProperty('--cal-fz', calFzScale);
    const el=document.getElementById('calFontLabel'); if(el) el.textContent=Math.round(calFzScale*100)+'%';
}
function calFont(dir){
    calFzScale=Math.min(2.0, Math.max(0.9, Math.round((calFzScale+dir*0.1)*10)/10));
    localStorage.setItem(CAL_FZ_KEY, calFzScale);
    applyCalFz();
    if(typeof renderView==='function') renderView(); // 다일 제목 오버레이/행 높이 재계산
}

// ── 일정 검색 — Enter 시 목록 뷰 검색만 (자동완성 드롭다운은 리소스 절약 위해 제거) ──
function closeCalSearch(){}
// Enter → 검색 결과를 목록 뷰로 표시
let agendaSearchQuery=null, agendaSearchResults=[];
async function openSearchListView(){
    const q=document.getElementById('calSearchInput').value.trim();
    if(!q) return;
    closeCalSearch();
    document.getElementById('calSearchInput').blur();
    try{
        const res=await fetch(`/api/events/search?q=${encodeURIComponent(q)}&limit=100`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        agendaSearchResults=await res.json();
    }catch(e){ return; }
    if(currentView!=='list') switchView('list'); // switchView가 agendaSearchQuery를 초기화하므로 이후에 설정
    agendaSearchQuery=q;
    renderAgenda();
}
function clearAgendaSearch(){
    agendaSearchQuery=null; agendaSearchResults=[];
    const inp=document.getElementById('calSearchInput'); if(inp) inp.value='';
    renderView(); loadEvents();
}
// 검색 결과 항목 클릭 → 상세 API로 전체 데이터 로드 후 모달 오픈
async function openSearchResultDetail(id){
    try{
        const res=await fetch(`/api/events/${id}/detail`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        openDetailModal(await res.json());
    }catch(e){}
}
function renderAgendaSearch(){
    const strip=document.getElementById('agendaStrip');
    if(strip) strip.style.display='none';
    document.getElementById('periodTitle').textContent=`검색: "${agendaSearchQuery}"`;
    const wrap=document.getElementById('agendaWrap');
    if(!wrap) return;
    const list=agendaSearchResults;
    let html=`<div class="agenda-search-head">
        <span>🔍 <b>"${_esc(agendaSearchQuery)}"</b> 검색 결과 ${list.length}건${list.length>=100?' (최대 100건 표시)':''}</span>
        <button type="button" class="ship-mini-btn" onclick="clearAgendaSearch()">✕ 검색 해제</button>
    </div>`;
    if(!list.length){
        html+='<div class="agenda-empty">검색 결과가 없습니다.</div>';
        wrap.innerHTML=html; return;
    }
    let lastDate=null;
    list.forEach(ev=>{
        const sd=(ev.start_date||'').substring(0,10);
        if(sd!==lastDate){
            lastDate=sd;
            const d=new Date(sd+'T00:00:00');
            const dowCls=d.getDay()===0?'ad-sun':d.getDay()===6?'ad-sat':'';
            html+=`<div class="agenda-date-head" style="margin-top:6px;">
                <span class="ad-d ${dowCls}">${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}</span>
                <span class="ad-dow ${dowCls}">${AGENDA_DOW[d.getDay()]}요일</span>
            </div>`;
        }
        const ed=(ev.end_date||'').substring(0,10);
        const isMulti=ed&&ed!==sd;
        const timeLabel=ev.is_all_day?'종일':(isMulti?'기간':((ev.start_time||'').substring(0,5)||'시간 미정'));
        const sub=[(isMulti?`${sd.slice(5).replace('-','/')}~${ed.slice(5).replace('-','/')}`:''), ev.client_name, ev.location].filter(Boolean).join(' · ');
        html+=`<div class="agenda-item${ev.completed_at?' is-completed':''}" onclick="openSearchResultDetail(${ev.id})">
            <div class="agenda-stripe" style="background:${chipColor(ev.color)}"></div>
            <div style="flex:1;min-width:0;">
                <div class="agenda-title">${_esc(ev.title||'(제목 없음)')}</div>
                ${sub?`<div class="agenda-sub">${_esc(sub)}</div>`:''}
            </div>
            <div class="agenda-time">${timeLabel}</div>
        </div>`;
    });
    wrap.innerHTML=html;
}

// 창 크기 변경 시 그리드 재계산 (행 높이·연속 바 위치)
let calResizeTimer=null;
window.addEventListener('resize',()=>{
    clearTimeout(calResizeTimer);
    calResizeTimer=setTimeout(()=>renderView(),150);
});

function switchView(view) {
    agendaSearchQuery=null; // 뷰 전환 시 검색 결과 모드 해제 (openSearchListView는 호출 후 다시 설정)
    currentView = view;
    const LABEL={month:'월간',week:'주간',day:'일간',list:'목록'};
    document.querySelectorAll('.view-toggle-btn').forEach(b=>b.classList.toggle('active',b.textContent.trim()===LABEL[view]));
    document.getElementById('monthView').style.display    = view==='month' ? '' : 'none';
    document.getElementById('timelineView').style.display = (view==='week'||view==='day') ? '' : 'none';
    document.getElementById('listView').style.display     = view==='list' ? '' : 'none';
    // 글자 크기 조절은 월간 뷰에만 적용되므로 그 외 뷰에서는 버튼 숨김
    const fz=document.querySelector('.cal-fontsize'); if(fz) fz.style.display = view==='month' ? '' : 'none';
    renderView();
    loadEvents();
}

function changeYear(dir) {
    currentYear += dir;
    if (currentView==='week') { currentWeekStart.setFullYear(currentWeekStart.getFullYear()+dir); }
    if (currentView==='day') { currentDay.setFullYear(currentDay.getFullYear()+dir); }
    renderView(); loadEvents();
}

function changePeriod(dir) {
    if (currentView==='list') {
        moveAgendaWeek(dir); // 목록 뷰: 7일 주 이동
        return;
    }
    if (currentView==='month') {
        currentMonth += dir;
        if (currentMonth>11){currentMonth=0;currentYear++;}
        if (currentMonth<0) {currentMonth=11;currentYear--;}
    } else if (currentView==='week') {
        currentWeekStart = new Date(currentWeekStart);
        currentWeekStart.setDate(currentWeekStart.getDate()+dir*7);
    } else {
        currentDay = new Date(currentDay);
        currentDay.setDate(currentDay.getDate()+dir);
    }
    renderView(); loadEvents();
}

function goToday() {
    const now = new Date();
    currentYear=now.getFullYear(); currentMonth=now.getMonth();
    currentWeekStart=getWeekStart(now);
    currentDay=new Date(now); currentDay.setHours(0,0,0,0);
    agendaWeekStart=null; agendaSelectedDate=todayStr(); // 목록 뷰도 오늘 기준으로 복귀
    renderView(); loadEvents();
}

function renderView() {
    if (currentView==='month') renderMonth();
    else if (currentView==='list') renderAgenda();
    else renderTimeline();
}

// ── 이벤트 로드 ─────────────────────────────────────────────────
async function loadEvents() {
    expandedDays.clear();
    let start, end;
    if (currentView==='list') {
        // 목록 뷰: 오늘부터 7일 (+ 다일 일정 겹침 위해 약간 여유)
        const wk=agendaWeekDates();
        start=wk[0]; end=wk[wk.length-1];
    } else if (currentView==='month') {
        // 그리드에 보이는 앞뒤 다른 달 날짜까지 포함(42칸 전체)
        const first=new Date(currentYear,currentMonth,1);
        const gs=new Date(first); gs.setDate(gs.getDate()-first.getDay());
        const ge=new Date(gs); ge.setDate(ge.getDate()+41);
        start=fmt(gs); end=fmt(ge);
    } else if (currentView==='week') {
        start=fmt(currentWeekStart);
        const we=new Date(currentWeekStart); we.setDate(we.getDate()+6); end=fmt(we);
    } else {
        start=end=fmt(currentDay);
    }
    const res=await fetch(`/api/events?start=${start}&end=${end}`);
    events=await res.json();
    renderView();
}

async function loadAssignees() {
    const res=await fetch('/api/assignees');
    if(res.ok) {
        assignees=await res.json();
        populateAssigneeFilter();
    }
}

// 종일 먼저 → 시작시간 오름차순 정렬 (월간 셀·팝오버·모바일 공용)
function sortByTime(list){
    return [...list].sort((a,b)=>{
        const aAll=a.is_all_day?0:1, bAll=b.is_all_day?0:1;
        if(aAll!==bAll) return aAll-bAll;
        return (a.start_time||'99:99').localeCompare(b.start_time||'99:99');
    });
}

// ── 하루 일정 전체 보기 팝오버 ───────────────────────────────────
function openDayPopover(dateStr, anchorEl){
    const pop=document.getElementById('dayPopover');
    const overlay=document.getElementById('dayPopoverOverlay');
    if(!pop||!overlay) return;

    const dayEvs=sortByTime(events.filter(ev=>isFiltered(ev)&&ev.start_date<=dateStr&&(ev.end_date||ev.start_date)>=dateStr));
    const d=new Date(dateStr+'T00:00:00');
    const DAYS=['일','월','화','수','목','금','토'];
    document.getElementById('dpTitle').textContent=`${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS[d.getDay()]}) · ${dayEvs.length}건`;
    // 주소를 도로명까지만 (상세주소·동/호수 등 쉼표 뒷부분 제거)
    const roadOnly=(addr)=>{
        if(!addr) return '';
        return String(addr).split(',')[0].trim();
    };
    document.getElementById('dpList').innerHTML=dayEvs.map(ev=>{
        const title=isGuestUser?(ev.location||'일정'):(ev.title||'(제목 없음)');
        const assignees=(ev.assignees||[]).map(a=>a.name).filter(Boolean).join(', ');
        const time=ev.is_all_day?'종일':((ev.start_time||'').substring(0,5)+((ev.end_time)?'~'+ev.end_time.substring(0,5):''));
        // 담당자는 제목 우측에, 나머지(시간·주소)는 하단 메타에
        const moveHtml=moveAddrLinesHtml(ev);
        const meta=[time, moveHtml?'':roadOnly(ev.location)].filter(Boolean).join(' · ');
        return `<div class="dp-item${ev.completed_at?' is-completed':''}" onclick="closeDayPopover(); openDetailModal(events.find(e=>e.id===${ev.id}))">
            <span class="dp-dot" style="background:${chipColor(ev.color)}"></span>
            <div class="dp-info">
                <div class="dp-title-row">
                    <span class="dp-title">${_esc(title)}${shipStatusIcon(ev)}${eventOptIconsHtml(ev)}</span>
                    ${assignees?`<span class="dp-assignee">${_esc(assignees)}</span>`:''}
                </div>
                ${meta?`<div class="dp-meta">${_esc(meta)}</div>`:''}
                ${moveHtml}
            </div>
        </div>`;
    }).join('');

    // 위치: 앵커 셀 근처, 화면 밖으로 안 나가게 보정
    overlay.classList.add('open');
    pop.style.display='block';
    const r=anchorEl.getBoundingClientRect();
    const pw=300, ph=pop.offsetHeight;
    let left=r.left, top=r.top;
    if(left+pw>window.innerWidth-12) left=window.innerWidth-pw-12;
    if(left<12) left=12;
    if(top+ph>window.innerHeight-12) top=Math.max(12, window.innerHeight-ph-12);
    pop.style.left=left+'px';
    pop.style.top=top+'px';
}
function closeDayPopover(){
    const pop=document.getElementById('dayPopover');
    const overlay=document.getElementById('dayPopoverOverlay');
    if(pop) pop.style.display='none';
    if(overlay) overlay.classList.remove('open');
}

// ── 목록(아젠다) 뷰 ─────────────────────────────────────────────
// 오늘 기준 7일 스트립에서 날짜를 고르고, 선택한 날의 일정을 시간순으로 표시
let agendaSelectedDate = null; // 'YYYY-MM-DD'
const AGENDA_DOW=['일','월','화','수','목','금','토'];
let agendaWeekStart=null; // 7일 스트립의 시작 날짜(Date). null이면 오늘부터.

function agendaWeekDates(){
    // 기준일이 속한 주의 일요일~토요일
    const base = getWeekStart(agendaWeekStart ? new Date(agendaWeekStart) : new Date());
    const arr=[];
    for(let i=0;i<7;i++){ const d=new Date(base); d.setDate(base.getDate()+i); arr.push(fmt(d)); }
    return arr;
}
// 스와이프/화살표로 한 주(일~토) 이동
function moveAgendaWeek(dir){
    const base = getWeekStart(agendaWeekStart ? new Date(agendaWeekStart) : new Date());
    base.setDate(base.getDate()+dir*7);
    agendaWeekStart=base;
    agendaSelectedDate=fmt(base); // 이동한 주의 일요일 선택
    loadEvents(); // 새 주 범위 로드 후 renderAgenda
}
function renderAgenda(){
    // 검색 결과 모드
    if(agendaSearchQuery){ renderAgendaSearch(); return; }
    const stripEl=document.getElementById('agendaStrip');
    if(stripEl) stripEl.style.display='';
    const ts=todayStr();
    const week=agendaWeekDates();
    if(!agendaSelectedDate || !week.includes(agendaSelectedDate)) agendaSelectedDate=week.includes(ts)?ts:week[0];
    const ws=new Date(week[0]+'T00:00:00');
    const we=new Date(week[6]+'T00:00:00');
    document.getElementById('periodTitle').textContent=`${ws.getMonth()+1}.${ws.getDate()} ~ ${we.getMonth()+1}.${we.getDate()}`;

    // 상단 7일 스트립
    const strip=document.getElementById('agendaStrip');
    if(strip){
        strip.innerHTML=week.map(full=>{
            const d=new Date(full+'T00:00:00'); const dow=d.getDay();
            const cls=dow===0?'sun':dow===6?'sat':'';
            const hasEv=events.some(ev=>isFiltered(ev)&&ev.start_date<=full&&(ev.end_date||ev.start_date)>=full);
            return `<button class="agenda-day-btn ${full===agendaSelectedDate?'active':''}" onclick="selectAgendaDate('${full}')">
                <span class="adb-dow ${cls}">${AGENDA_DOW[dow]}</span>
                <span class="adb-num ${cls}">${d.getDate()}</span>
                ${hasEv?'<span class="adb-dot"></span>':''}
            </button>`;
        }).join('');
    }

    // 선택일 일정
    const wrap=document.getElementById('agendaWrap');
    if(!wrap) return;
    const full=agendaSelectedDate;
    const d=new Date(full+'T00:00:00');
    const dayEvs=sortByTime(events.filter(ev=>isFiltered(ev)&&ev.start_date<=full&&(ev.end_date||ev.start_date)>=full));
    const dowCls=full===ts?'ad-today':d.getDay()===0?'ad-sun':d.getDay()===6?'ad-sat':'';

    let html=`<div class="agenda-day"><div class="agenda-date-head">
        <span class="ad-d ${dowCls}">${d.getMonth()+1}.${d.getDate()}</span>
        <span class="ad-dow ${dowCls}">${AGENDA_DOW[d.getDay()]}요일${full===ts?' · 오늘':''}</span>
    </div>`;
    if(!dayEvs.length){
        html+='<div class="agenda-empty">일정이 없습니다.</div>';
    } else {
        dayEvs.forEach(ev=>{
            const isMulti=ev.end_date&&ev.end_date!==ev.start_date;
            const timeLabel=ev.is_all_day?'종일':(isMulti?'기간':((ev.start_time||'').substring(0,5)||'시간 미정'));
            const title=isGuestUser?(ev.location||'일정'):(ev.title||'(제목 없음)');
            const assignees=(ev.assignees||[]).map(a=>a.name).filter(Boolean).join(', ');
            const moveHtml=moveAddrLinesHtml(ev);
            const sub=[ (isMulti?`${ev.start_date.slice(5).replace('-','/')}~${ev.end_date.slice(5).replace('-','/')}`:''), ev.location ].filter(Boolean).join(' · ');
            const subHtml=moveHtml || (sub?`<div class="agenda-sub">${_esc(sub)}</div>`:'');
            html+=`<div class="agenda-item${ev.completed_at?' is-completed':''}" onclick="openDetailModal(events.find(e=>e.id===${ev.id}))">
                <div class="agenda-stripe" style="background:${chipColor(ev.color)}"></div>
                <div class="agenda-main">
                    <div class="agenda-title">${_esc(title)}${shipStatusIcon(ev)}${eventOptIconsHtml(ev)}</div>
                    ${subHtml}
                </div>
                <div class="agenda-right">
                    <span class="agenda-time">${timeLabel}</span>
                    ${assignees?`<span class="agenda-assignee">${_esc(assignees)}</span>`:''}
                </div>
            </div>`;
        });
    }
    html+='</div>';
    wrap.innerHTML=html;
}
function selectAgendaDate(full){ agendaSelectedDate=full; renderAgenda(); }

// ── 월간 뷰 ─────────────────────────────────────────────────────
function renderMonth() {
    document.getElementById('periodTitle').textContent=`${currentYear}년 ${currentMonth+1}월`;
    const grid=document.getElementById('daysGrid'); grid.innerHTML='';
    const firstDay=new Date(currentYear,currentMonth,1).getDay();
    const lastDate=new Date(currentYear,currentMonth+1,0).getDate();
    const prevLast=new Date(currentYear,currentMonth,0).getDate();
    const ts=todayStr();

    // 셀 데이터 생성 — 모든 셀에 실제 날짜 부여(다른 달 포함) → 다일 바가 월 경계에서도 이어짐
    const gridStart=new Date(currentYear,currentMonth,1);
    gridStart.setDate(gridStart.getDate()-firstDay);
    const monthStart=fmt(new Date(currentYear,currentMonth,1));
    let cells=[];
    for(let i=0;i<42;i++){
        const dt=new Date(gridStart); dt.setDate(gridStart.getDate()+i);
        const full=fmt(dt);
        const month=dt.getMonth()===currentMonth?'cur':(full<monthStart?'prev':'next');
        cells.push({date:dt.getDate(), month, full});
    }

    const isMobileCal = window.innerWidth<=768; // 모바일: 점 표시 간소화 모드
    // ── 반응형: 그리드가 화면 높이를 꽉 채우도록 주 행 높이 계산 (110px*배율은 하한) ──
    let rowMin=Math.round(110*calFzScale);
    if(window.innerWidth>768){
        const gTop=grid.getBoundingClientRect().top;
        const avail=window.innerHeight-gTop-24; // 하단 여백
        rowMin=Math.max(rowMin, Math.floor((avail-7)/6)); // 6주 + 1px 경계선
    }
    // 셀 높이에 들어가는 만큼 일정 칩 표시 (기존 고정 3개 → 동적, 최소 3개 보장)
    const chipH=22*calFzScale+2, headH=12+28*calFzScale, badgeH=18;
    const MAX_VISIBLE=Math.max(3, Math.floor((rowMin-headH-badgeH)/chipH));

    // 주 단위로 렌더링
    for(let w=0;w<6;w++){
        const weekCells=cells.slice(w*7, w*7+7);
        const weekStart=weekCells[0].full, weekEnd=weekCells[6].full;

        // ── 이 주에 걸친 다일 일정에 고정 레인(행) 배정 → 바가 같은 행을 유지해 정렬됨 ──
        const _d=v=>(v||'').substring(0,10); // 날짜 정규화 (시간 접미 방어)
        const weekMulti=events.filter(ev=>isFiltered(ev)&&evEnd(ev)!==_d(ev.start_date)&&_d(ev.start_date)<=weekEnd&&evEnd(ev)>=weekStart);
        weekMulti.sort((a,b)=> a.start_date.localeCompare(b.start_date) || b.end_date.localeCompare(a.end_date) || a.id-b.id);
        const laneOf={};
        const lanes=[];
        weekMulti.forEach(ev=>{
            let lane=0;
            while((lanes[lane]||[]).some(r=> !(ev.end_date<r.s||ev.start_date>r.e))) lane++;
            (lanes[lane]=lanes[lane]||[]).push({s:ev.start_date,e:ev.end_date});
            laneOf[ev.id]=lane;
        });
        const LANE_CAP=3; // 표시할 다일 레인 상한 (초과분은 '+N 더보기')

        const weekRow=document.createElement('div');
        weekRow.className='week-row';
        if(!isMobileCal) weekRow.style.minHeight=rowMin+'px';
        for(let d=0;d<7;d++){
            const cell=weekCells[d];
            const div=document.createElement('div');
            div.className='day-cell'+(cell.month!=='cur'?' other-month':'');
            div.dataset.date=cell.full;
            if(cell.full===ts) div.classList.add('today');
            const holiday=getHoliday(cell.full);
            const nc=d===0||holiday?'sun':d===6?'sat':'';
            const holidayHtml=holiday?`<span class="holiday-label">${holiday}</span>`:'';
            div.innerHTML=`<div class="day-num-row"><span class="day-num ${nc}">${cell.date}</span>${holidayHtml}</div>`;

            // 모바일 간소화: 칩/바 없이 일정 유무 점만 표시, 상세는 하단 리스트에서
            if(isMobileCal){
                const hasEv=events.some(ev=>isFiltered(ev)&&_d(ev.start_date)<=cell.full&&evEnd(ev)>=cell.full);
                if(hasEv) div.classList.add('m-has-ev');
                div.addEventListener('click',e=>{
                    if(suppressCellClick){ suppressCellClick=false; return; }
                    selectMobileDay(cell.full);
                });
                weekRow.appendChild(div);
                continue;
            }

            const evList=document.createElement('div');
            evList.className='events-list';

            // 이 날짜를 덮는 다일 일정을 고정 레인에 배치(정렬 유지). 비는 레인은 그날 단일로 채워 빈 칸 방지.
            const coveringByLane={};
            let maxLane=-1;
            weekMulti.forEach(ev=>{
                if(laneOf[ev.id]<LANE_CAP && _d(ev.start_date)<=cell.full && evEnd(ev)>=cell.full){
                    coveringByLane[laneOf[ev.id]]=ev;
                    if(laneOf[ev.id]>maxLane) maxLane=laneOf[ev.id];
                }
            });
            // 캡 초과로 못 그리는 다일(더보기로)
            const hiddenMultiHere=weekMulti.filter(ev=>laneOf[ev.id]>=LANE_CAP&&_d(ev.start_date)<=cell.full&&evEnd(ev)>=cell.full).length;

            // 단일 일정(시간순) — 다른 달 셀(회색)에도 표시
            const singles=sortByTime(events.filter(ev=>isFiltered(ev)&&evEnd(ev)===_d(ev.start_date)&&_d(ev.start_date)===cell.full));
            let si=0; // 단일 큐 인덱스

            // 행 구성: 레인 0..maxLane 은 다일 우선, 빈 레인은 단일로 채움(시간 빠른 단일이 위로). 이후 남은 단일.
            const rows=[];
            for(let L=0;L<=maxLane;L++){
                if(coveringByLane[L]) rows.push({multi:true, ev:coveringByLane[L]});
                else if(si<singles.length) rows.push({multi:false, ev:singles[si++]});
                else rows.push({spacer:true});
            }
            while(si<singles.length) rows.push({multi:false, ev:singles[si++]});

            const isExpanded=expandedDays.has(cell.full);
            if(isExpanded) div.classList.add('expanded');
            const TARGET_ROWS=4;
            const shownRows=isExpanded?rows:rows.slice(0, TARGET_ROWS);
            shownRows.forEach(r=>{
                if(r.spacer){ const sp=document.createElement('div'); sp.className='lane-spacer'; evList.appendChild(sp); return; }
                const ev=r.ev;
                const chip=document.createElement('div');
                if(r.multi){
                    const isStart=_d(ev.start_date)===cell.full||d===0;
                    const isEnd=_d(ev.end_date)===cell.full;
                    let cls=`event-chip single color-${ev.color} multi-day`;
                    cls+= isStart&&isEnd?' day-start day-end':isStart?' day-start':isEnd?' day-end':' day-cont';
                    if(ev.completed_at) cls+=' is-completed';
                    chip.className=cls;
                    chip.innerHTML=''; // 제목은 주 단위 오버레이가 바 전체 폭에 걸쳐 그림 (PC/모바일 공통)
                    chip.dataset.mev=ev.id; // 오버레이 위치 측정용
                    // 오버레이는 pointer-events:none — 담당자 툴팁은 바 조각에서 hover
                    const mnames=assigneeNamesOf(ev);
                    if(mnames.length>1){ chip.dataset.anames=mnames.join('|'); chip.dataset.atitle=isGuestUser?(ev.location||'일정'):(ev.title||''); }
                } else {
                    chip.className=`event-chip single color-${ev.color}`+(ev.completed_at?' is-completed':'');
                    chip.innerHTML=buildChipHtml(ev);
                }
                chip.onclick=e=>{e.stopPropagation();if(!dragEvent&&!isDragging)openDetailModal(ev);};
                chip.onmousedown=e=>{if(e.button===0)dragStart(ev,e);};
                evList.appendChild(chip);
            });

            // 더보기 = (표시 못한 행 중 실제 일정) + (캡 초과 다일)
            const hiddenRealInRows=rows.slice(shownRows.length).filter(r=>!r.spacer).length;
            const hiddenCount=hiddenRealInRows+hiddenMultiHere;
            if(hiddenCount>0){
                const more=document.createElement('div');
                more.className='more-badge';
                more.textContent=`+${hiddenCount}건 더보기`;
                more.onclick=e=>{e.stopPropagation(); openDayPopover(cell.full, div);};
                evList.appendChild(more);
            }

            div.appendChild(evList);
            div.addEventListener('click',e=>{
                if(suppressCellClick){ suppressCellClick=false; return; } // 범위 드래그 직후 클릭 무시
                if(window.innerWidth<=768){
                    selectMobileDay(cell.full);
                } else {
                    if(e.target.closest('.event-chip')||e.target.closest('.more-badge')) return;
                    // 날짜 숫자 클릭: 일정이 있으면 더보기 없이도 그 날 팝업 열기 (게스트 제외 — 일정 내용 비노출)
                    if(!isGuestUser&&e.target.closest('.day-num-row')){
                        const hasEv=events.some(ev=>isFiltered(ev)&&ev.start_date<=cell.full&&(ev.end_date||ev.start_date)>=cell.full);
                        if(hasEv){ openDayPopover(cell.full, div); return; }
                    }
                    if(canEditCalendar&&(e.target===div||e.target.classList.contains('day-num-row')||e.target.classList.contains('day-num'))) openNewModal(cell.full);
                }
            });
            weekRow.appendChild(div);
        }
        grid.appendChild(weekRow);

        // ── 다일 일정 제목 오버레이: 바 전체 폭에 걸쳐 한 번만 출력(셀 경계 넘어 흐름). 주마다 시작 칸에서 다시 출력 ──
        if(!isMobileCal){
            const wrRect=weekRow.getBoundingClientRect();
            weekMulti.forEach(ev=>{
                if(laneOf[ev.id]>=LANE_CAP) return;
                let c1=-1,c2=-1;
                for(let d=0;d<7;d++){ const f=weekCells[d].full; if(f>=_d(ev.start_date)&&f<=evEnd(ev)){ if(c1<0)c1=d; c2=d; } }
                if(c1<0) return;
                const startCell=weekRow.children[c1];
                const chip=startCell&&startCell.querySelector(`.event-chip.multi-day[data-mev="${ev.id}"]`);
                if(!chip) return;
                const chipRect=chip.getBoundingClientRect();
                const endRect=weekRow.children[c2].getBoundingClientRect();
                const ov=document.createElement('div');
                ov.className='mday-title-overlay'+(ev.completed_at?' is-completed':'');
                // 제목 글자색도 카테고리 설정(text_color)과 연동
                if(window.CALENDAR_CATEGORIES&&window.CALENDAR_CATEGORIES[ev.color]) ov.style.color=`var(--chip-${ev.color}-text)`;
                ov.style.top=(chipRect.top-wrRect.top)+'px';
                ov.style.height=chipRect.height+'px';
                ov.style.left=(chipRect.left-wrRect.left)+'px';
                ov.style.width=Math.max(0, (endRect.right-6)-chipRect.left)+'px';
                ov.innerHTML=buildChipHtml(ev); // 제목 + 담당자 배지 등(연속 일정도 담당자 표기)
                weekRow.appendChild(ov);
            });
        }
    }
    // 모바일: 오늘 날짜 자동 선택
    if(window.innerWidth<=768){
        const ts=todayStr();
        const cells=document.querySelectorAll('.day-cell[data-date]');
        const todayCell=[...cells].find(c=>c.dataset.date===ts);
        if(todayCell) selectMobileDay(ts);
    }
}

let mobileSelectedDate = null;
function selectMobileDay(dateStr){
    mobileSelectedDate = dateStr;
    document.querySelectorAll('.day-cell.mobile-selected').forEach(c=>c.classList.remove('mobile-selected'));
    const cell=document.querySelector(`.day-cell[data-date="${dateStr}"]`);
    if(cell) cell.classList.add('mobile-selected');
    renderMobileDayEvents(dateStr);
}

function renderMobileDayEvents(dateStr){
    const container=document.getElementById('mobileDayEvents');
    if(!container) return;
    const dayEvs=events.filter(ev=>isFiltered(ev)&&ev.start_date<=dateStr&&(ev.end_date||ev.start_date)>=dateStr)
        // 카테고리 순이 아니라 시간순 정렬: 종일 먼저, 그 다음 시작시간 오름차순
        .sort((a,b)=>{
            const aAll=a.is_all_day?0:1, bAll=b.is_all_day?0:1;
            if(aAll!==bAll) return aAll-bAll;
            const at=(a.start_time||'99:99'), bt=(b.start_time||'99:99');
            return at.localeCompare(bt);
        });
    const d=new Date(dateStr+'T00:00:00');
    const DAYS_KO_FULL=['일요일','월요일','화요일','수요일','목요일','금요일','토요일'];
    const header=`${d.getMonth()+1}월 ${d.getDate()}일 ${DAYS_KO_FULL[d.getDay()]}`;

    if(!dayEvs.length){
        container.innerHTML=`<div class="mde-header">${header}</div><div class="mde-empty">일정이 없습니다</div>`;
        return;
    }
    const items=dayEvs.map(ev=>{
        const time=ev.is_all_day?'종일':((ev.start_time||'').substring(0,5)||'시간 미정');
        const title=ev.title||'(제목 없음)';
        const assignees=assigneeNamesOf(ev).join(', ');
        const moveHtml=moveAddrLinesHtml(ev);
        return `<div class="mde-item${ev.completed_at?' is-completed':''}" onclick="openDetailModal(events.find(e=>e.id===${ev.id}))">
            <div class="mde-dot" style="background:${chipColor(ev.color)}"></div>
            <div class="mde-info">
                <div class="mde-title-row">
                    <div class="mde-title">${_esc(title)}${shipStatusIcon(ev)}${eventOptIconsHtml(ev)}</div>
                    ${assignees?`<span class="mde-assignee">${_esc(assignees)}</span>`:''}
                </div>
                <div class="mde-meta">${time}${(!moveHtml&&ev.location)?' · '+_esc(ev.location):''}</div>
                ${moveHtml}
            </div>
        </div>`;
    }).join('');
    container.innerHTML=`<div class="mde-header">${header}</div>${items}`;
}

// ── 주간/일간 타임라인 ───────────────────────────────────────────
function renderTimeline() {
    const ts=todayStr();
    let cols=[];
    if(currentView==='week'){
        for(let i=0;i<7;i++){
            const d=new Date(currentWeekStart); d.setDate(d.getDate()+i); cols.push(d);
        }
        const ws=cols[0],we=cols[6];
        document.getElementById('periodTitle').textContent=
            `${ws.getFullYear()}년 ${ws.getMonth()+1}월 ${ws.getDate()}일 ~ ${we.getMonth()+1}월 ${we.getDate()}일`;
    } else {
        cols=[currentDay];
        document.getElementById('periodTitle').textContent=
            `${currentDay.getFullYear()}년 ${currentDay.getMonth()+1}월 ${currentDay.getDate()}일 (${DAYS_KO[currentDay.getDay()]})`;
    }

    const grid=document.getElementById('timelineGrid'); grid.innerHTML='';

    // 헤더
    const header=document.createElement('div');
    header.className='tl-header';
    const th0=document.createElement('div'); th0.className='tl-time-col'; header.appendChild(th0);
    cols.forEach(d=>{
        const dow=d.getDay();
        const isToday=fmt(d)===ts;
        const cell=document.createElement('div');
        cell.className='tl-day-col'+(isToday?' today-col':'');
        const nc=dow===0?'sun-c':dow===6?'sat-c':'';
        cell.innerHTML=`<div class="tl-day-name">${DAYS_KO[dow]}</div>
            <div class="tl-day-num ${nc} ${isToday?'today-num':''}">${d.getDate()}</div>`;
        // 일간 뷰에서 날짜 클릭 → 월간으로 이동
        if(currentView==='day') cell.style.cursor='default';
        header.appendChild(cell);
    });
    grid.appendChild(header);

    // 종일 행
    const alldayRow=document.createElement('div');
    alldayRow.className='tl-allday-row';
    const alldayLabel=document.createElement('div');
    alldayLabel.className='tl-allday-label'; alldayLabel.textContent='종일';
    alldayRow.appendChild(alldayLabel);
    cols.forEach(d=>{
        const ds=fmt(d);
        const isToday=ds===ts;
        const cell=document.createElement('div');
        cell.className='tl-allday-cell'+(isToday?' today-col':'');
        events.filter(ev=>isFiltered(ev)&&ev.is_all_day&&ev.start_date<=ds&&(ev.end_date||ev.start_date)>=ds).forEach(ev=>{
            const chip=document.createElement('div');
            chip.className=`event-chip color-${ev.color}`+(ev.completed_at?' is-completed':'');
            chip.style.marginBottom='2px';
            chip.innerHTML=isGuestUser?_esc((ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):'')):(eventOptIconsHtml(ev)+`<span class="chip-title">${_esc(ev.title||'')}</span>`+shipStatusIcon(ev)+((ev.assignees||[]).length?` <span class="ev-assignee-badge">${_esc(assigneeNamesOf(ev).join(', '))}</span>`:''));
            chip.onclick=()=>openDetailModal(ev);
            cell.appendChild(chip);
        });
        cell.addEventListener('click',e=>{if(e.target===cell)openNewModal(ds);});
        alldayRow.appendChild(cell);
    });
    grid.appendChild(alldayRow);

    // 시간 슬롯
    // 날짜별로 시간 일정의 겹침을 계산해 컬럼(좌우 분할) 배치를 미리 구한다.
    // id → { col, total } (col: 0부터, total: 그 겹침 묶음의 컬럼 수)
    const dayLayouts = {};
    cols.forEach(d=>{
        const ds=fmt(d);
        const items = events
            .filter(ev=>isFiltered(ev) && !ev.is_all_day && ev.start_date===ds && ev.start_time)
            .map(ev=>{
                const [sh,sm]=ev.start_time.split(':').map(Number);
                let eh, em;
                if(ev.end_time){ [eh,em]=ev.end_time.split(':').map(Number); }
                else { eh=sh+1; em=sm; }
                let start=sh*60+sm, end=eh*60+em;
                if(end<=start) end=start+30; // 종료<=시작이면 최소 30분
                return { ev, start, end, col:0, total:1 };
            })
            .sort((a,b)=> a.start-b.start || a.end-b.end);

        const layout={};
        let cluster=[], clusterEnd=-1;
        const flush=(grp)=>{
            const lanes=[]; // 각 레인의 마지막 종료시각
            grp.forEach(it=>{
                let placed=false;
                for(let i=0;i<lanes.length;i++){
                    if(lanes[i]<=it.start){ lanes[i]=it.end; it.col=i; placed=true; break; }
                }
                if(!placed){ it.col=lanes.length; lanes.push(it.end); }
            });
            const total=lanes.length||1;
            grp.forEach(it=> layout[it.ev.id]={col:it.col, total});
        };
        items.forEach(it=>{
            if(cluster.length && it.start>=clusterEnd){ flush(cluster); cluster=[]; clusterEnd=-1; }
            cluster.push(it); clusterEnd=Math.max(clusterEnd, it.end);
        });
        if(cluster.length) flush(cluster);
        dayLayouts[ds]=layout;
    });

    // ── 반응형: 시간대 행 높이를 화면에 맞게 늘림 (48px 하한) ──
    let TL_HH=48;
    if(window.innerWidth>768){
        const gTop=grid.getBoundingClientRect().top;
        const used=grid.offsetHeight; // 헤더 + 종일 행
        TL_HH=Math.max(48, Math.floor((window.innerHeight-gTop-used-28)/HOURS.length));
    }
    grid.style.setProperty('--tl-hh', TL_HH+'px');

    HOURS.forEach(hour=>{
        const row=document.createElement('div');
        row.className='tl-row';
        const label=document.createElement('div');
        label.className='tl-time-label';
        label.textContent=`${hour}:00`;
        row.appendChild(label);
        cols.forEach(d=>{
            const ds=fmt(d);
            const isToday=ds===ts;
            const slot=document.createElement('div');
            slot.className='tl-slot'+(isToday?' today-col':'');
            events.filter(ev=>{
                if(!isFiltered(ev)) return false;
                if(ev.is_all_day) return false;
                if(ev.start_date!==ds) return false;
                const h=ev.start_time?parseInt(ev.start_time.split(':')[0]):null;
                return h===hour;
            }).forEach(ev=>{
                const el=document.createElement('div');
                el.className=`tl-event color-${ev.color}`+(ev.completed_at?' is-completed':'');
                const sm=ev.start_time?parseInt(ev.start_time.split(':')[1]):0;
                const eh=ev.end_time?parseInt(ev.end_time.split(':')[0]):hour+1;
                const em=ev.end_time?parseInt(ev.end_time.split(':')[1]):0;
                const dur=(eh+em/60)-(hour+sm/60);
                el.style.top=`${(sm/60)*TL_HH}px`;
                el.style.height=`${Math.max(dur*TL_HH,20)}px`;
                // 겹치는 일정은 셀을 좌우로 분할해 서로 덮지 않게
                const lo=(dayLayouts[ds]||{})[ev.id];
                if(lo && lo.total>1){
                    const gap=2; // px
                    const widthPct=100/lo.total;
                    el.style.left=`calc(${widthPct*lo.col}% + ${gap}px)`;
                    el.style.width=`calc(${widthPct}% - ${gap*2}px)`;
                    el.style.right='auto';
                }
                el.innerHTML=eventOptIconsHtml(ev)+`<span class="tl-ev-title">${_esc(ev.title||'')}</span>`+shipStatusIcon(ev)+((ev.assignees||[]).length?`<div class="tl-ev-assignee">${_esc(assigneeNamesOf(ev).join(', '))}</div>`:'');
                el.onclick=e=>{e.stopPropagation();openDetailModal(ev);};
                slot.appendChild(el);
            });
            slot.addEventListener('click',e=>{
                if(e.target===slot) openNewModal(ds,`${String(hour).padStart(2,'0')}:00`);
            });
            row.appendChild(slot);
        });
        grid.appendChild(row);
    });
}

// ── 라디오 그룹 헬퍼 ──────────────────────────────────────────
const COLOR_NAMES = (function(){
    const o = {holiday:'공휴일'};
    const cats = window.CALENDAR_CATEGORIES || {};
    Object.keys(cats).forEach(k => { o[k] = cats[k].label; });
    return o;
})();
let isAllDay=false, isLocked=false, linkedEstimateId=null;

function initRadioGroup(gid, opts){
    const g=document.getElementById(gid); if(!g) return;
    const multi=opts?.multi||false;
    g.querySelectorAll('.radio-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
            if(isLocked) return;
            if(multi){btn.classList.toggle('active');}
            else{
                const wasActive=btn.classList.contains('active');
                g.querySelectorAll('.radio-btn').forEach(b=>b.classList.remove('active','active-red','active-green'));
                if(!wasActive) btn.classList.add('active');
            }
            // conditional field 토글
            handleConditional(gid);
            if(opts?.onChange) opts.onChange(getRadio(gid));
        });
    });
}
function getRadio(gid){
    const g=document.getElementById(gid); if(!g) return '';
    const a=g.querySelector('.radio-btn.active');
    return a?a.dataset.val||a.dataset.sopt||a.dataset.seopt||a.dataset.opt:'';
}
function getMultiRadio(gid){
    const g=document.getElementById(gid); if(!g) return [];
    return [...g.querySelectorAll('.radio-btn.active')].map(b=>b.dataset.val||b.dataset.sopt||b.dataset.seopt||b.dataset.opt);
}
function setRadio(gid,val){
    const g=document.getElementById(gid); if(!g) return;
    g.querySelectorAll('.radio-btn').forEach(b=>{
        b.classList.remove('active','active-red','active-green');
        if(b.dataset.val===val||b.dataset.sopt===val||b.dataset.seopt===val||b.dataset.opt===val) b.classList.add('active');
    });
    handleConditional(gid);
}
function setMultiRadio(gid,vals){
    const g=document.getElementById(gid); if(!g||!vals) return;
    const arr=Array.isArray(vals)?vals:vals.split(',').map(v=>v.trim());
    g.querySelectorAll('.radio-btn').forEach(b=>{
        b.classList.toggle('active',arr.includes(b.dataset.val||b.dataset.sopt||b.dataset.seopt||b.dataset.opt));
    });
    handleConditional(gid);
}
function clearRadio(gid){
    const g=document.getElementById(gid); if(!g) return;
    g.querySelectorAll('.radio-btn').forEach(b=>b.classList.remove('active','active-red','active-green'));
}

function handleConditional(gid){
    // 기타 → 직접입력 필드
    const condMap={'g_platform_group':'g_platform_etc_wrap','g_topic_group':'g_topic_etc_wrap','g_budget_group':'g_budget_etc_wrap','g_source_group':'g_source_ref_wrap','g_req_topic_group':'g_req_topic_etc_wrap'};
    if(condMap[gid]){
        const g=document.getElementById(gid);
        const wrap=document.getElementById(condMap[gid]);
        if(!g||!wrap) return;
        const triggerVals=['기타','직접입력','소개'];
        const hasMatch=[...g.querySelectorAll('.radio-btn.active')].some(b=>triggerVals.includes(b.dataset.val));
        wrap.classList.toggle('visible',hasMatch);
    }
    // 의뢰주제 이사세팅 → 출발지/도착지 UI
    if(gid==='g_req_topic_group'){
        updateMoveSettingUI();
    }
    // 주문제품 O → 배송완료
    if(gid==='g_order_group'){
        const v=getRadio('g_order_group');
        document.getElementById('g_delivery_wrap').style.display=v==='O'?'':'none';
    }
    // 잔금 O → 금액
    if(gid==='g_balance_group'){
        const v=getRadio('g_balance_group');
        const cond=document.getElementById('g_balance_amount_wrap');
        if(cond) cond.classList.toggle('visible',v==='O');
        // X 로 바뀌면 입력값 비우기 (저장 시 잔여 데이터 방지)
        if(v!=='O'){
            const amt=document.getElementById('g_balance_amount');
            if(amt) amt.value='';
        }
        updateBalanceBanner();
    }
}

// ── 색상 전환 ──
function setColor(c){
    currentColor=c;
    // color dots
    document.querySelectorAll('.color-dot').forEach(d=>{d.classList.toggle('active',d.dataset.color===c);});
    // strip
    const strip=document.getElementById('modalStrip');
    strip.className='modal-strip'+(c!=='gold'?' color-'+c:'');
    // type badge
    const badge=document.getElementById('typeBadge');
    badge.className='type-badge '+c;
    badge.textContent='● '+(COLOR_NAMES[c]||c);
    // 템플릿 토글
    document.querySelectorAll('.gold-only').forEach(s=>s.style.display=c==='gold'?'flex':'none');
    document.querySelectorAll('.teal-only').forEach(s=>s.style.display=c==='teal'?'flex':'none');
    document.querySelectorAll('.common-only').forEach(s=>s.style.display=(c!=='gold'&&c!=='teal')?'flex':'none');
    // 사내업무(blue)/휴가·개인(red)은 의뢰자 검색 불필요 → 섹션 숨김
    const clientSec=document.getElementById('clientLinkSection');
    if(clientSec) clientSec.style.display=(c==='blue'||c==='red')?'none':'';
    // 사내업무/휴가·개인은 장소가 필수 아님 → 필수 표시(*) 숨김
    const addrReq=document.getElementById('addrReqMark');
    if(addrReq) addrReq.style.display=(c==='blue'||c==='red')?'none':'';
    // gold 전용 날짜 행
    document.getElementById('standardDtRows').style.display=c==='gold'?'none':'';
    document.getElementById('goldDtRow').style.display=c==='gold'?'flex':'none';
    updateBalanceBanner();
    applyVisitOptsUI();
    updateShipmentSectionVisibility();
    if(typeof updateMoveSettingUI==='function') updateMoveSettingUI();
    if(typeof updateReasonFieldVisibility==='function') updateReasonFieldVisibility();
}

// ── 미팅/내방 옵션 ──
function renderVisitOpts(){
    const list=document.getElementById('visitOptsList');
    if(!list) return;
    list.innerHTML=(CAL_VISIT_OPTIONS||[]).map(opt=>{
        const v=String(opt).replace(/"/g,'&quot;'); const t=String(opt).replace(/</g,'&lt;');
        return `<label class="visit-opt"><input type="checkbox" value="${v}" onchange="onVisitOptChange()">${t}</label>`;
    }).join('') || '<span style="font-size:12px;color:var(--text-muted);">관리 → 설정에서 내방 옵션을 추가하세요.</span>';
}
function applyVisitOptsUI(){
    const sec=document.getElementById('visitOptsSection');
    const addr=document.getElementById('addressBlock');
    if(!sec||!addr) return;
    if(currentColor==='purple'){ sec.style.display=''; onVisitOptChange(); }
    else { sec.style.display='none'; addr.style.display=''; }
}
function onVisitOptChange(){
    const addr=document.getElementById('addressBlock');
    if(!addr) return;
    const anyChecked=[...document.querySelectorAll('#visitOptsList input:checked')].length>0;
    addr.style.display=(currentColor==='purple' && anyChecked) ? 'none' : '';
}

// ── 반복 설정 ──
function onRepeatFreqChange(){
    const f=document.getElementById('repeatFreq')?.value||'';
    const cw=document.getElementById('repeatCustomWrap');
    const uw=document.getElementById('repeatUntilWrap');
    if(cw) cw.style.display=f==='custom'?'inline-flex':'none';
    if(uw) uw.style.display=f?'inline-flex':'none';
}

// ── 담당자 ──
let assigneePanelOpen=false;
let assigneeShowAll=false; // 본인 외 나머지 펼침 여부
function toggleAssigneePanel(){
    assigneePanelOpen=!assigneePanelOpen;
    document.getElementById('assigneeList').style.display=assigneePanelOpen?'flex':'none';
    if(assigneePanelOpen){ assigneeShowAll=false; renderAssigneeList(); }
}
function updateAssigneeBtn(){
    const btn=document.getElementById('assigneeBtn');
    const label=document.getElementById('assigneeBtnLabel');
    if(selectedAssignees.length){
        const names=assignees.filter(a=>selectedAssignees.includes(a.id)).map(a=>a.name).join(', ');
        label.textContent=names;
        btn.classList.add('has-assignee');
    }else{
        label.textContent='담당자 지정';
        btn.classList.remove('has-assignee');
    }
}
function renderAssigneeList(){
    const c=document.getElementById('assigneeList');
    if(!assignees.length){c.innerHTML='<div style="font-size:12px;color:var(--text-muted);">등록된 담당자 없음</div>';return;}
    // 로그인 본인을 맨 앞으로 정렬
    const ordered=[...assignees].sort((a,b)=>((a.user_id===CAL_USER_ID)?0:1)-((b.user_id===CAL_USER_ID)?0:1));
    c.innerHTML='';
    let hidden=0;
    ordered.forEach(a=>{
        const isSelf=a.user_id===CAL_USER_ID;
        const isSel=selectedAssignees.includes(a.id);
        const chip=document.createElement('div');
        chip.className='assignee-chip'+(isSel?' selected':'')+(isSelf?' self':'');
        chip.textContent=a.name+(isSelf?' (나)':''); chip.dataset.id=a.id;
        // 접힘 상태: 본인/이미 선택된 담당자만 노출, 나머지는 숨김
        if(!assigneeShowAll && !isSelf && !isSel){ chip.style.display='none'; hidden++; }
        chip.onclick=()=>{
            if(isLocked) return;
            if(selectedAssignees.includes(a.id)){selectedAssignees=selectedAssignees.filter(id=>id!==a.id);}
            else{selectedAssignees.push(a.id);}
            updateAssigneeBtn(); renderAssigneeList();
        };
        c.appendChild(chip);
    });
    // 더보기/접기
    const remaining=ordered.filter(a=>a.user_id!==CAL_USER_ID && !selectedAssignees.includes(a.id)).length;
    if(remaining>0 || assigneeShowAll){
        const more=document.createElement('button');
        more.type='button'; more.className='assignee-more';
        more.textContent=assigneeShowAll?'접기 ▲':`+${remaining}명 더보기 ▼`;
        more.onclick=(e)=>{ e.stopPropagation(); assigneeShowAll=!assigneeShowAll; renderAssigneeList(); };
        c.appendChild(more);
    }
}

// ── 종일 토글 ──
function toggleAllDay(){
    if(isLocked) return;
    isAllDay=!isAllDay;
    document.getElementById('alldayTrack').classList.toggle('on',isAllDay);
    document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display=isAllDay?'none':'');
}

// ── 잠금 ──
function toggleLock(){
    isLocked=!isLocked;
    applyLockUI();
}

// '요약' 토글 — 읽기 요약 뷰 표시만 제어(필드 활성/비활성은 보기/편집 모드가 담당)
function applyLockUI(){
    const btn=document.getElementById('lockBtn');
    btn.textContent=isLocked?'☑ 요약':'☐ 요약';
    btn.classList.toggle('locked',isLocked);
    document.getElementById('lockedBanner').classList.toggle('visible',isLocked);
    const body = document.querySelector('#modalOverlay .modal-body');
    if (!body) return;
    if (isLocked) {
        renderLockSummary();
        body.classList.add('is-locked');
    } else {
        body.classList.remove('is-locked');
    }
}

function _esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
// 금액 포맷: 숫자면 천단위 콤마, 아니면 원문 그대로
function _fmtAmt(v){
    if(v===null||v===undefined) return '';
    const s=String(v).replace(/,/g,'').trim();
    if(s==='') return '';
    return /^\d+$/.test(s) ? Number(s).toLocaleString() : String(v);
}
function _val(id){ const el=document.getElementById(id); return el ? (el.value||'').trim() : ''; }
function _radio(gid){
    const g=document.getElementById(gid); if(!g) return '';
    const a=g.querySelector('.radio-btn.active'); return a ? (a.getAttribute('data-val')||'') : '';
}
function _radioMulti(gid){
    const g=document.getElementById(gid); if(!g) return [];
    return Array.from(g.querySelectorAll('.radio-btn.active')).map(b=>b.getAttribute('data-val'));
}

function renderLockSummary(){
    const container = document.getElementById('lockSummary');
    if (!container) return;
    const color = currentColor || 'gold';
    const location = [_val('modalLocation'), _val('modalLocationDetail')].filter(Boolean).join(' ');
    const addr = document.getElementById('modalAddress')?.value || '';
    const isAll = isAllDay;
    const sDate = _val(color==='gold'?'goldStartDate':'startDate');
    const eDate = _val(color==='gold'?'goldEndDate':'endDate');
    const sTime = _val(color==='gold'?'goldStartTime':'startTime').substring(0,5);
    const eTime = _val(color==='gold'?'goldEndTime':'endTime').substring(0,5);
    const typeLabel = (window.CALENDAR_CATEGORIES?.[color]?.label) || color;

    const linkedClientName = document.getElementById('linkedClientName')?.textContent?.trim() || '';
    const linkedClientLink = document.getElementById('linkedClientLink')?.getAttribute('href') || '';
    const clientChipHtml = linkedClientName
        ? `<a class="ls-client-chip" href="${_esc(linkedClientLink)}" target="_blank"><span>👤</span>${_esc(linkedClientName)}</a>`
        : '';
    // 연결 프로젝트 칩 — projectSelect가 로드돼 있으면 이름 표시, 아니면 번호로라도 표시
    let projectChipHtml = '';
    {
        const psel = document.getElementById('projectSelect');
        const pid = (psel && psel.value) ? psel.value : (linkedProjectId || '');
        if (pid) {
            const opt = psel ? [...psel.options].find(o => o.value == pid) : null;
            const plabel = opt ? opt.textContent : ('프로젝트 #' + pid);
            projectChipHtml = `<a class="ls-client-chip" href="/projects/${pid}" target="_blank" style="max-width:100%;"><span>📁</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${_esc(plabel)}</span></a>`;
        }
    }

    const timeStr = isAll
        ? `${sDate} (종일)`
        : `${sDate}${eDate&&eDate!==sDate?' ~ '+eDate:''}  ${sTime} ~ ${eTime}`;

    // 이사세팅 출발지
    const isMove = color==='gold' && getMultiRadio('g_req_topic_group').includes('이사세팅');
    const mfLoc = [_val('moveFromLocation'), _val('moveFromDetail')].filter(Boolean).join(' ');
    const mfAddr = document.getElementById('moveFromAddress')?.value || '';

    let html = '';
    // 장소 (이사세팅이면 출발지 → 도착지 + 출발→도착 동선 검색)
    if (isMove && (mfLoc || location || mfAddr || addr)) {
        html += `<div class="ls-section">
            <div class="ls-section-title">이사 이동 경로</div>
            <div><div class="ls-info-label">🚚 출발지 (이사 전)</div>
                <div class="ls-location">${document.getElementById('moveNoFrom')?.checked ? '<span style="color:var(--text-muted)">출발지 없음</span>' : (_esc(mfLoc) || '<span style="color:var(--text-muted)">— 미입력 —</span>')}</div>
            </div>
            <div style="margin-top:8px;"><div class="ls-info-label">📍 도착지 (이사 후)</div>
                <div class="ls-location">${_esc(location) || '<span style="color:var(--text-muted)">— 미입력 —</span>'}</div>
            </div>
            <div class="ls-actions" style="margin-top:8px;">
                ${mfAddr ? `<a class="ls-action-btn" href="https://map.kakao.com/?q=${encodeURIComponent(mfAddr)}" target="_blank">🔍 출발지</a>` : ''}
                ${addr ? `<a class="ls-action-btn" href="https://map.kakao.com/?q=${encodeURIComponent(addr)}" target="_blank">🔍 도착지</a>` : ''}
                ${(mfAddr && addr) ? `<a class="ls-action-btn primary" href="https://map.kakao.com/?sName=${encodeURIComponent(mfAddr)}&eName=${encodeURIComponent(addr)}" target="_blank">🗺 출발→도착 동선</a>` : ''}
            </div>
        </div>`;
    } else if (location || addr) {
        // 일반 장소
        html += `<div class="ls-section">
            <div class="ls-section-title">장소</div>
            <div class="ls-location">${_esc(location)}</div>
            ${addr ? `<div class="ls-actions">
                <a class="ls-action-btn" href="https://map.kakao.com/?q=${encodeURIComponent(addr)}" target="_blank">🔍 주소 검색</a>
                <a class="ls-action-btn primary" href="https://map.kakao.com/?sName=출발지&eName=${encodeURIComponent(addr)}" target="_blank">🗺 동선 검색</a>
            </div>` : ''}
        </div>`;
    }

    // 시간 + 카테고리 핀 + 특수 옵션(반려동물 등)
    const specialSel=[...document.querySelectorAll('#specialOpts .special-opt-btn.active')]
        .map(b=>b.dataset.opt).filter(o=>SPECIAL_OPT_LABELS[o]);
    const specialPills=specialSel.map(o=>`<span class="ls-type-pill">${SPECIAL_ICONS[o]||''} ${_esc(SPECIAL_OPT_LABELS[o])}</span>`).join('');
    html += `<div class="ls-section">
        <div class="ls-time"><span class="ls-time-icon">⏰</span><span>${_esc(timeStr)}</span></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;"><span class="ls-type-pill">📌 ${_esc(typeLabel)}</span>${clientChipHtml}${projectChipHtml}</div>
        ${specialPills?`<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">${specialPills}</div>`:''}
    </div>`;

    // 배송 현황 (gold/green, 송장 있을 때만)
    const lsShips=(SHIP_COLORS.includes(color)?(shipCache.shipments||[]):[]);
    if (lsShips.length) {
        const lsDone=lsShips.filter(s=>s.status==='delivered').length;
        html += `<div class="ls-section">
            <div class="ls-section-title">📦 배송 현황 (${lsDone}/${lsShips.length} 완료)</div>
            ${lsShips.map(shipRowHtml).join('')}
        </div>`;
    }

    // Gold 전용 상세 칩 + 섹션
    if (color === 'gold') {
        const platform = _radio('g_platform_group');
        const platformEtc = _val('g_platform_etc');
        const career = _radio('g_career_group');
        const source = _radio('g_source_group');
        const sourceRef = _val('g_source_ref');
        const topic = _radio('g_topic_group');
        const topicEtc = _val('g_topic_etc');
        const budget = _radio('g_budget_group');
        const budgetEtc = _val('g_budget_etc');
        const reqTopic = _radio('g_req_topic_group');
        const reqTopicEtc = _val('g_req_topic_etc');
        const paid = _radio('g_paid_group');
        const orderVal = _radio('g_order_group');
        const balanceVal = _radio('g_balance_group');
        const delivery = _radio('g_delivery_group');

        const chips = [];
        const pushChip = (icon, key, val) => { if (val) chips.push(`<span class="ls-chip"><span class="ls-chip-icon">${icon}</span><span class="ls-chip-key">${key}</span><span class="ls-chip-val">${_esc(val)}</span></span>`); };
        pushChip('🔗', '유입', source ? source + (sourceRef ? ` (${sourceRef})` : '') : '');
        pushChip('🖥', '플랫폼', platform === '기타' ? (platformEtc || '기타') : platform);
        pushChip('🎓', '경력', career);
        pushChip('🎙', '방송주제', topic === '기타' ? (topicEtc || '기타') : topic);
        pushChip('💰', '예산', budget === '직접입력' ? (budgetEtc || '직접입력') : budget);
        pushChip('📋', '의뢰주제', reqTopic === '기타' ? (reqTopicEtc || '기타') : reqTopic);
        pushChip('💳', '결제', paid);
        pushChip('📦', '주문', orderVal);
        pushChip('💵', '잔금', balanceVal);
        if (orderVal === 'O') pushChip('🚛', '수령', delivery || 'X');

        if (chips.length) {
            html += `<div class="ls-chips">${chips.join('')}</div>`;
        }

        // 의뢰자 정보 (비어있어도 영역 표시)
        const nick = _val('g_nickname');
        const name = _val('g_name');
        const phone = _val('g_phone');
        const _cell = (label, v) => `<div class="ls-info-cell"><div class="ls-info-label">${label}</div><div class="ls-info-val${v?'':' ls-empty'}">${v ? _esc(v) : '—'}</div></div>`;
        html += `<div class="ls-section">
            <div class="ls-section-title">의뢰자 정보</div>
            <div class="ls-info-grid">
                ${_cell('의뢰자 닉네임', nick)}
                ${_cell('의뢰자 이름', name)}
                ${_cell('전화번호', phone)}
            </div>
        </div>`;

        // 장비 목록
        const equip = _val('g_equipment');
        html += `<div class="ls-section">
            <div class="ls-section-title">장비 목록</div>
            ${equip ? `<div class="ls-text-block">${_esc(equip)}</div>` : `<div class="ls-text-block muted">— 등록된 장비 없음 —</div>`}
        </div>`;

        // 의뢰 내용
        const reqDetail = _val('g_req_detail');
        const special = _val('g_special');
        html += `<div class="ls-section">
            <div class="ls-section-title">의뢰 내용</div>
            <div><div class="ls-info-label">의뢰 세부항목</div>
                ${reqDetail ? `<div class="ls-text-block">${_esc(reqDetail)}</div>` : `<div class="ls-text-block muted">— 내용 없음 —</div>`}
            </div>
            <div><div class="ls-info-label">특이사항</div>
                ${special ? `<div class="ls-text-block">${_esc(special)}</div>` : `<div class="ls-text-block muted">— 내용 없음 —</div>`}
            </div>
        </div>`;

        // 결제 정보
        const amount = _val('g_estimate_amount');
        const balanceAmount = _val('g_balance_amount');
        const savedFlag = document.getElementById('g_estimate_status')?.textContent?.includes('저장');
        html += `<div class="ls-section">
            <div class="ls-section-title">결제 정보</div>
            <div class="ls-amount-row">
                <div>
                    <div class="ls-info-label">결제된 금액</div>
                    ${amount ? `<div class="ls-amount">${_esc(_fmtAmt(amount))}원</div>` : `<div class="ls-text-block muted">— 미입력 —</div>`}
                </div>
                ${amount && savedFlag ? '<span class="ls-saved-pill">✅ 저장된 금액</span>' : ''}
            </div>
            ${balanceAmount ? `<div style="margin-top:6px;"><div class="ls-info-label">잔금 금액</div><div class="ls-text-block">${_esc(_fmtAmt(balanceAmount))}원</div></div>` : ''}
        </div>`;
    }

    // 공통: 상세 설명
    if (color !== 'gold' && color !== 'teal') {
        const desc = _val('commonDesc');
        if (desc) html += `<div class="ls-section"><div class="ls-section-title">상세 설명</div><div class="ls-text-block">${_esc(desc)}</div></div>`;
        const hNote = _val('commonHandoverNote');
        if (hNote) html += `<div class="ls-section"><div class="ls-section-title">전달사항</div><div class="ls-text-block">${_esc(hNote)}</div></div>`;
    }

    // 첨부 이미지 (모든 카테고리 공통, 비어있어도 영역 표시)
    const imgGroups = [
        { label:'견적서', grid:'quoteGrid' },
        { label:'레퍼런스', grid:'refGrid' },
        { label:'방 사진', grid:'roomGrid' },
        { label:'첨부 파일', grid:'generalGrid' },
    ];
    let imgHtml = '';
    imgGroups.forEach(g => {
        const grid = document.getElementById(g.grid);
        const imgs = grid ? Array.from(grid.querySelectorAll('img')) : [];
        imgHtml += `<div class="ls-img-section">
            <div class="ls-img-label">${g.label}</div>
            ${imgs.length
                ? `<div class="ls-img-grid">${imgs.map(im => `<img src="${_esc(im.src)}" data-full="${_esc(im.dataset.full||im.src)}" alt="${_esc(g.label)}" loading="lazy" decoding="async">`).join('')}</div>`
                : `<div class="ls-img-empty">— 등록된 ${g.label} 없음 —</div>`}
        </div>`;
    });
    html += `<div class="ls-section"><div class="ls-section-title">첨부 이미지</div>${imgHtml}</div>`;

    container.innerHTML = html || '<div class="ls-text-block muted">내용 없음</div>';
}

// ── 잔금 배너 ──
function updateBalanceBanner(){
    const banner=document.getElementById('balanceBanner');
    const text=document.getElementById('balanceBannerText');
    const isGold=currentColor==='gold';
    const balanceOn=getRadio('g_balance_group')==='O';
    const amount=document.getElementById('g_balance_amount')?.value?.trim();
    if(isGold&&balanceOn&&amount){
        text.textContent='잔금 '+amount+' 있음';
        banner.style.display='flex';
    }else{
        banner.style.display='none';
    }
}

// ── 커스텀 타임피커 ──
function openTimePicker(trigger,hiddenId){
    if(isLocked) return;
    // 기존 팝업 제거
    document.querySelectorAll('.time-picker-popup').forEach(p=>p.remove());
    const hidden=document.getElementById(hiddenId);
    const [curH,curM]=(hidden.value||'13:00').split(':').map(Number);
    const popup=document.createElement('div');
    popup.className='time-picker-popup';
    popup.innerHTML=`<div class="tp-header"><div class="tp-col-label">시</div><div class="tp-col-label divider-space"></div><div class="tp-col-label">분</div></div><div class="tp-body"><div class="tp-col" id="_tpH"></div><div class="tp-divider"></div><div class="tp-col" id="_tpM"></div></div><div class="tp-footer"><button class="tp-confirm-btn" id="_tpConfirm">확인</button></div>`;
    document.body.appendChild(popup);
    const hCol=popup.querySelector('#_tpH'),mCol=popup.querySelector('#_tpM');
    for(let h=0;h<24;h++){const d=document.createElement('div');d.className='tp-item'+(h===curH?' selected':'');d.dataset.h=h;d.textContent=String(h).padStart(2,'0');d.onclick=()=>{hCol.querySelectorAll('.tp-item').forEach(i=>i.classList.remove('selected'));d.classList.add('selected');};hCol.appendChild(d);}
    for(let m=0;m<60;m+=10){const d=document.createElement('div');d.className='tp-item'+(m===Math.floor(curM/10)*10?' selected':'');d.dataset.m=m;d.textContent=String(m).padStart(2,'0');d.onclick=()=>{mCol.querySelectorAll('.tp-item').forEach(i=>i.classList.remove('selected'));d.classList.add('selected');};mCol.appendChild(d);}
    // 스크롤 to selected
    setTimeout(()=>{hCol.querySelector('.selected')?.scrollIntoView({block:'center'});mCol.querySelector('.selected')?.scrollIntoView({block:'center'});},50);
    // 위치
    const rect=trigger.getBoundingClientRect();
    popup.style.left=rect.left+'px';
    popup.style.top=(rect.bottom+4)+'px';
    if(rect.bottom+260>window.innerHeight) popup.style.top=(rect.top-260)+'px';
    // 확인
    popup.querySelector('#_tpConfirm').onclick=()=>{
        const sh=hCol.querySelector('.selected')?.dataset.h||'13';
        const sm=mCol.querySelector('.selected')?.dataset.m||'0';
        const val=String(sh).padStart(2,'0')+':'+String(sm).padStart(2,'0');
        hidden.value=val; trigger.textContent=val; popup.remove();

        // 시작 시간을 선택하면 종료 시간을 자동으로 +1시간
        if (hiddenId === 'startTime' || hiddenId === 'goldStartTime') {
            const endIdMap = { startTime: 'endTime', goldStartTime: 'goldEndTime' };
            const endTrigMap = { startTime: 'endTimeTrigger', goldStartTime: 'goldEndTimeTrigger' };
            const endHidden = document.getElementById(endIdMap[hiddenId]);
            const endTrig = document.getElementById(endTrigMap[hiddenId]);
            if (endHidden && endTrig) {
                const endH = (parseInt(sh, 10) + 1) % 24;
                const endVal = String(endH).padStart(2, '0') + ':' + String(sm).padStart(2, '0');
                endHidden.value = endVal;
                endTrig.textContent = endVal;
            }
        }
    };
    // 외부 클릭으로 닫기
    setTimeout(()=>{document.addEventListener('click',function handler(e){if(!popup.contains(e.target)&&e.target!==trigger){popup.remove();document.removeEventListener('click',handler);}});},10);
}

// ── 의뢰자/프로젝트 연동 ──
let linkedClientId=null, linkedProjectId=null;
let clientSearchTimer=null;

function renderClientList(list){
    if(!list.length) return '<div style="padding:10px;font-size:12px;color:var(--text-muted);text-align:center;">결과 없음</div>';
    return list.map(c=>{
        const nick=c.nickname||'';const nm=c.name||'';const ph=c.phone||'';
        return `<div style="padding:8px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;transition:background 0.1s;" onmouseover="this.style.background='var(--surface)'" onmouseout="this.style.background=''">
            <div style="flex:1;cursor:pointer;min-width:0;" onclick="selectClient(${c.id},'${nick.replace(/'/g,"\\'")}','${nm.replace(/'/g,"\\'")}','${ph.replace(/'/g,"\\'")}')">
                <span style="font-weight:600;font-size:13px;">${nick||nm}</span>${nick&&nm?' <span style="color:var(--text-muted);font-size:12px;">('+nm+')</span>':''}
                <span style="color:var(--text-muted);font-size:11px;margin-left:6px;">${ph}</span>
            </div>
            <button onclick="event.stopPropagation();window.open('/clients/${c.id}','_blank')" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:3px 8px;border-radius:6px;font-size:10px;cursor:pointer;flex-shrink:0;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">보기</button>
        </div>`;
    }).join('');
}

function searchClients(query){
    clearTimeout(clientSearchTimer);
    const results=document.getElementById('clientSearchResults');
    if(!query.trim()||query.length<1){
        // 빈 입력 → 최근 의뢰자 목록
        loadRecentClients();
        return;
    }
    clientSearchTimer=setTimeout(async()=>{
        const res=await fetch(`/api/clients/search?q=${encodeURIComponent(query)}`);
        if(!res.ok)return;
        const list=await res.json();
        results.innerHTML=renderClientList(list);
        results.style.display='';
    },250);
}

async function loadRecentClients(){
    const results=document.getElementById('clientSearchResults');
    results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">로딩 중...</div>';
    results.style.display='';
    try{
        const res=await fetch('/api/clients/list?limit=15');
        if(res.ok){const data=await res.json();const list=data.data||data;results.innerHTML=renderClientList(Array.isArray(list)?list.slice(0,15):[]);}
    }catch(e){results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">로드 실패</div>';}
}

// 기존 일정 복원 시 client_id만 있고 이름이 없을 때, API로 닉네임/이름 조회해 표시 갱신
async function restoreLinkedClientName(id){
    try{
        const res=await fetch(`/api/clients/${id}/detail`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        const c=await res.json();
        const label=(c.nickname||c.name)?((c.nickname||c.name)+(c.nickname&&c.name?' ('+c.name+')':'')):`의뢰자 #${id}`;
        const el=document.getElementById('linkedClientName');
        // 현재 표시 중인 대상이 여전히 같은 의뢰자일 때만 갱신
        if(el && linkedClientId===id) el.textContent=label;
    }catch(e){}
}

async function selectClient(id,nickname,name,phone){
    linkedClientId=id;
    document.getElementById('clientSearchResults').style.display='none';
    document.getElementById('clientSearchInput').value='';
    document.getElementById('linkedClientName').textContent=(nickname||name)+(nickname&&name?' ('+name+')':'');
    document.getElementById('linkedClientInfo').style.display='';
    document.getElementById('linkedClientLink').href='/clients/'+id;
    // gold 필드 자동채움
    const gNick=document.getElementById('g_nickname');
    const gName=document.getElementById('g_name');
    const gPhone=document.getElementById('g_phone');
    if(gNick) gNick.value=nickname||'';
    if(gName) gName.value=name||'';
    if(gPhone) gPhone.value=phone||'';
    // teal(원격/방송룸): 모드 미선택이어도 보이도록 양쪽 이름 필드 모두 채움 (빈 칸만)
    if(currentColor==='teal'){
        ['t_remote_name','t_studio_name'].forEach(fid=>{
            const el=document.getElementById(fid);
            if(el&&!el.value.trim()) el.value=nickname||name||'';
        });
    }
    // 제목 비어있으면 의뢰자명으로 보조 채움
    document.getElementById('modalTitle').value=document.getElementById('modalTitle').value||(nickname||name);
    // 프로젝트 목록 로드 + 상세정보(주소/연락처/플랫폼) 연동
    const detail=await loadClientProjects(id);
    applyClientDetail(detail);
}

// 의뢰자 상세 → 일정 폼 매핑 (이미 입력된 값은 덮어쓰지 않음)
function applyClientDetail(d){
    if(!d) return;
    // 연락처 보강 (검색 결과에 전화번호가 없던 경우)
    const gPhone=document.getElementById('g_phone');
    if(gPhone&&!gPhone.value.trim()&&d.phone) gPhone.value=d.phone;
    // 주소 → 장소 (도로명) + 상세주소
    const loc=document.getElementById('modalLocation');
    if(loc&&!loc.value.trim()&&d.address){
        loc.value=d.address;
        document.getElementById('modalAddress').value=d.address;
        const det=document.getElementById('modalLocationDetail');
        if(det&&!det.value.trim()&&d.address_detail) det.value=d.address_detail;
    }
    // teal(원격/방송룸): 플랫폼 텍스트 필드 채움 (양쪽 모드, 기타 직접입력 포함)
    if(currentColor==='teal'){
        const platTxt=[...(d.platforms||[]).filter(Boolean).filter(v=>v!=='기타'), d.platform_etc].filter(Boolean).join(', ');
        if(platTxt){
            ['t_remote_platform','t_studio_platform'].forEach(fid=>{
                const el=document.getElementById(fid);
                if(el&&!el.value.trim()) el.value=platTxt;
            });
        }
    }
    // 방송 주제 — 의뢰자 콘텐츠 유형과 목록 동일 → 그대로 매핑 (빈 경우만, 기타 직접입력 포함)
    const ctypes=(d.content_types||[]).filter(Boolean);
    if((ctypes.length||d.topic_etc)&&!getMultiRadio('g_topic_group').length){
        const KNOWN_TOPICS=['소통','게임','노래','먹방','야외','버추얼','코인','주식','기타','미정'];
        const etcT=[...ctypes.filter(t=>!KNOWN_TOPICS.includes(t)),d.topic_etc].filter(Boolean);
        const pills=[...new Set([...ctypes.map(t=>KNOWN_TOPICS.includes(t)?t:'기타'), ...(etcT.length?['기타']:[])])];
        if(pills.length) setMultiRadio('g_topic_group',pills);
        if(etcT.length){ const e=document.getElementById('g_topic_etc'); if(e&&!e.value.trim()) e.value=etcT.join(', '); }
    }
    // 플랫폼 — 의뢰자 관리 명칭 → 캘린더 pill 매핑
    const plats=(d.platforms||[]).filter(Boolean);
    if(plats.length&&!getMultiRadio('g_platform_group').length){
        const PLAT_MAP={'유튜브':'유튜브','치지직':'치지직','아프리카':'SOOP','SOOP':'SOOP','틱톡':'틱톡'};
        const pills=[...new Set(plats.map(p=>PLAT_MAP[p]||'기타'))];
        setMultiRadio('g_platform_group',pills);
        // 매핑 안 되는 플랫폼(트위치/팬더 등)과 기타 입력값은 '기타' 입력칸에 원문 유지
        const etcNames=[...plats.filter(p=>!PLAT_MAP[p]&&p!=='기타'),d.platform_etc].filter(Boolean);
        if(etcNames.length){
            const e=document.getElementById('g_platform_etc');
            if(e&&!e.value.trim()) e.value=etcNames.join(', ');
        }
    }
}

async function loadClientProjects(clientId){
    const wrap=document.getElementById('projectSelectWrap');
    const sel=document.getElementById('projectSelect');
    try{
        const res=await fetch(`/api/clients/${clientId}/detail`);
        if(!res.ok){wrap.style.display='none';return null;}
        const data=await res.json();
        const projects=data.projects||[];
        if(!projects.length){wrap.style.display='none';return data;}
        sel.innerHTML='<option value="">프로젝트 선택 (선택사항)</option>';
        projects.forEach(p=>{
            const opt=document.createElement('option');
            opt.value=p.id;
            const tags=[...((p.tags&&p.tags.major)||[]),...((p.tags&&p.tags.minor)||[])];
            opt.textContent=`${p.name} (${p.stage||p.type||''})`+(p.created_at?` · ${p.created_at}`:'')+(tags.length?` — #${tags.join(' #')}`:'');
            sel.appendChild(opt);
        });
        // 이전에 연결된 프로젝트가 있으면 선택
        if(linkedProjectId) sel.value=linkedProjectId;
        wrap.style.display='';
        if(isLocked) renderLockSummary(); // 요약 뷰에 프로젝트명 반영
        return data;
    }catch(e){wrap.style.display='none';return null;}
}

function unlinkClient(){
    linkedClientId=null;linkedProjectId=null;
    document.getElementById('linkedClientInfo').style.display='none';
    document.getElementById('linkedClientName').textContent=''; // 잔여 텍스트가 저장 시 client_name으로 새는 것 방지
    document.getElementById('projectSelectWrap').style.display='none';
    document.getElementById('g_nickname').value='';
    document.getElementById('g_name').value='';
    document.getElementById('g_phone').value='';
}

// 검색 외부 클릭 시 닫기
document.addEventListener('click',e=>{
    const results=document.getElementById('clientSearchResults');
    const input=document.getElementById('clientSearchInput');
    if(results&&input&&!results.contains(e.target)&&e.target!==input) results.style.display='none';
});

// ── 이미지 첨부 ──
let pendingAttachments={quote:[],reference:[],room:[],general:[]};
let existingAttachments={quote:[],reference:[],room:[],general:[]};
const GRID_MAP={quote:'quoteGrid',reference:'refGrid',room:'roomGrid',general:'generalGrid'};
const FILE_MAP={quote:'fileQuote',reference:'fileReference',room:'fileRoom',general:'fileGeneral'};

function triggerAttach(type){document.getElementById(FILE_MAP[type]).click();}

function handleImgFiles(type,files){
    if(!files||!files.length) return;
    Array.from(files).forEach(f=>pendingAttachments[type].push({file:f,note:''}));
    renderImgGrid(type);
    // 파일 input 리셋
    const input=document.getElementById(FILE_MAP[type]); if(input) input.value='';
}

// 공통 유형의 '첨부 파일' 섹션(generalAttachSection) 핸들러 — general 타입으로 적재
function handleGeneralFiles(files){
    if(!files||!files.length) return;
    Array.from(files).forEach(f=>pendingAttachments.general.push({file:f,note:''}));
    renderImgGrid('general');
    const inp=document.getElementById('generalFileInput'); if(inp) inp.value='';
}

// general은 두 곳(이미지 그룹 generalGrid + 공통 generalAttachGrid)에 모두 그릴 수 있음
function gridIdsFor(type){
    return type==='general' ? ['generalGrid','generalAttachGrid'] : [GRID_MAP[type]];
}
function renderImgGrid(type){
    const isImage = type!=='general'; // general은 이미지가 아닐 수 있어 미리보기 대신 파일명 위주
    gridIdsFor(type).forEach(gid=>{
        const grid=document.getElementById(gid); if(!grid) return;
        grid.innerHTML='';
        // 기존
        existingAttachments[type].forEach((a,i)=>{
            const isImg=(a.mime_type||'').startsWith('image/');
            const thumb = isImg
                ? `<img src="${a.thumb_url||a.url}" data-full="${a.url}" alt="${a.file_name||''}" loading="lazy" decoding="async">`
                : `<a href="${a.url}" target="_blank" class="img-fileicon">📄</a>`;
            grid.innerHTML+=`<div class="img-item"><div class="img-thumb-wrap">${thumb}<button class="img-remove" onclick="removeExistingAttach('${type}',${i},${a.id})">✕</button></div><div class="img-filename">${a.file_name||''}</div></div>`;
        });
        // 새로 추가된
        pendingAttachments[type].forEach((item,i)=>{
            const div=document.createElement('div');div.className='img-item';
            const wrap=document.createElement('div');wrap.className='img-thumb-wrap';
            if(item.file.type.startsWith('image/')){
                const img=document.createElement('img');img.src=URL.createObjectURL(item.file);img.alt=item.file.name;wrap.appendChild(img);
            } else {
                const ic=document.createElement('div');ic.className='img-fileicon';ic.textContent='📄';wrap.appendChild(ic);
            }
            const rm=document.createElement('button');rm.className='img-remove';rm.textContent='✕';
            rm.onclick=()=>{pendingAttachments[type].splice(i,1);renderImgGrid(type);};
            wrap.appendChild(rm);div.appendChild(wrap);
            const fn=document.createElement('div');fn.className='img-filename';fn.textContent=item.file.name;div.appendChild(fn);
            const note=document.createElement('textarea');note.className='img-note';note.placeholder='주석 입력';note.rows=1;
            note.value=item.note||'';note.oninput=()=>{item.note=note.value;};
            div.appendChild(note);grid.appendChild(div);
        });
    });
}

// 드래그 드롭
[['quoteZone','quote'],['refZone','reference'],['roomZone','room'],['generalZone','general'],['uploadZone','general']].forEach(([zid,type])=>{
    const zone=document.getElementById(zid); if(!zone) return;
    zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag-over');});
    zone.addEventListener('dragleave',()=>zone.classList.remove('drag-over'));
    zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('drag-over');handleImgFiles(type,e.dataTransfer.files);});
});

async function removeExistingAttach(type,idx,id){
    if(!confirm('이 이미지를 삭제하시겠습니까?')) return;
    await fetch(`/api/schedule-attachments/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}});
    existingAttachments[type].splice(idx,1);renderImgGrid(type);
}
async function uploadPendingAttachments(scheduleId){
    const TYPE_LABEL={quote:'견적서',reference:'참고자료',room:'방 사진',general:'첨부 파일'};
    let failedTypes=[];
    for(const type of ['quote','reference','room','general']){
        if(!pendingAttachments[type].length) continue;
        const fd=new FormData();fd.append('attachment_type',type);
        pendingAttachments[type].forEach(item=>fd.append('files[]',item.file));
        try{
            const res=await fetch(`/api/schedules/${scheduleId}/attachments`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:fd});
            if(!res.ok){
                const err=await res.json().catch(()=>({}));
                console.error(`첨부파일 업로드 실패 (${type}):`, res.status, err);
                failedTypes.push((TYPE_LABEL[type]||type)+(err.message?` (${err.message})`:''));
            }else{
                pendingAttachments[type]=[]; // 성공한 타입은 대기열 비움 (재시도/재저장 시 중복 업로드 방지)
            }
        }catch(e){ console.error(`첨부파일 업로드 오류 (${type}):`,e); failedTypes.push(TYPE_LABEL[type]||type); }
    }
    if(failedTypes.length) showCalToast('⚠ '+failedTypes.join(', ')+' 업로드 실패');
}
async function loadExistingAttachments(scheduleId){
    existingAttachments={quote:[],reference:[],room:[],general:[]};
    try{const res=await fetch(`/api/schedules/${scheduleId}/attachments`);if(res.ok){const list=await res.json();list.forEach(a=>{if(existingAttachments[a.attachment_type])existingAttachments[a.attachment_type].push(a);});}}catch(e){}
    ['quote','reference','room','general'].forEach(t=>renderImgGrid(t));
    // 요약 뷰가 켜진 상태라면 첨부 로딩 완료 후 요약을 다시 렌더(비동기로 늦게 도착한 이미지 반영)
    if(isLocked) renderLockSummary();
}
// ── 배송 현황 (송장 추적) — 방문의뢰(gold)·촬영/스튜디오(green), 저장된 일정만 ──
const SHIP_COLORS=['gold','green'];
let shipCache={shipments:[],carriers:{}};
function toggleShipmentBody(force){
    const body=document.getElementById('shipmentBody');
    const caret=document.getElementById('shipCaret');
    if(!body) return;
    const open = force!==undefined ? force : body.style.display==='none';
    body.style.display=open?'':'none';
    if(caret) caret.textContent=open?'▾':'▸';
}
function updateShipmentSectionVisibility(){
    const sec=document.getElementById('shipmentSection');
    if(sec) sec.style.display=(editingId&&SHIP_COLORS.includes(currentColor))?'':'none';
}
async function loadShipments(){
    if(!editingId||!SHIP_COLORS.includes(currentColor)) return;
    try{
        const res=await fetch(`/api/schedules/${editingId}/shipments`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        shipCache=await res.json();
        renderShipments();
        if(isLocked) renderLockSummary(); // 요약 뷰에 배송 현황 반영
    }catch(e){}
}
// 택배사별 실시간 조회 페이지 (송장번호 클릭 시 새 창)
const CARRIER_TRACK_URLS={
    'kr.cjlogistics':'https://trace.cjlogistics.com/next/tracking.html?wblNo=',
    'kr.lotte':'https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo=',
    'kr.hanjin':'https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&schLang=KR&wblnumText2=',
    'kr.logen':'https://www.ilogen.com/web/personal/trace/',
    'kr.epost':'https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1=',
    'kr.kdexp':'https://kdexp.com/service/delivery/etc/delivery.do?barcode=',
};
function shipRowHtml(s){
    const ico=s.status==='delivered'?['○','var(--green)']:(s.status==='error'?['⚠','var(--red)']:['✕','var(--red)']);
    const evTxt=s.status==='delivered'?`배송완료${s.delivered_at?' · '+s.delivered_at:''}`:(s.last_event||'조회 대기');
    const trackUrl=CARRIER_TRACK_URLS[s.carrier];
    const noHtml=trackUrl
        ? `<a class="ship-no ship-no-link" href="${trackUrl}${encodeURIComponent(s.tracking_no)}" target="_blank" rel="noopener" title="택배사 실시간 조회 열기" onclick="event.stopPropagation()">${_esc(s.tracking_no)} ↗</a>`
        : `<span class="ship-no">${_esc(s.tracking_no)}</span>`;
    return `<div class="ship-item">
        <span class="ship-status-ico" style="color:${ico[1]}">${ico[0]}</span>
        <span class="ship-carrier">${_esc(s.carrier_label)}</span>
        ${noHtml}
        <span class="ship-event" title="${_esc(evTxt)}">${_esc(evTxt)}</span>
        <button type="button" class="ship-del" onclick="deleteShipment(${s.id})" title="송장 삭제">✕</button>
    </div>`;
}
// 모달 제목 옆 배송 상태 아이콘 (송장 없으면 미표시)
function updateModalShipBadge(){
    const badge=document.getElementById('modalShipBadge');
    if(!badge) return;
    const ships=shipCache.shipments||[];
    if(!ships.length){ badge.style.display='none'; badge.textContent=''; return; }
    const done=ships.filter(s=>s.status==='delivered').length;
    const [ico,color]=done===0?['✕','var(--red)']:(done<ships.length?['△','#d78a2e']:['○','var(--green)']);
    badge.textContent=ico;
    badge.style.color=color;
    badge.title=`배송 ${done}/${ships.length}건 완료`;
    badge.style.display='';
}
function renderShipments(){
    const list=document.getElementById('shipmentList');
    const sel=document.getElementById('shipCarrier');
    const badge=document.getElementById('shipSummaryBadge');
    if(!list) return;
    if(sel) sel.innerHTML=Object.entries(shipCache.carriers||{}).map(([k,v])=>`<option value="${k}">${v}</option>`).join('');
    const ships=shipCache.shipments||[];
    const done=ships.filter(s=>s.status==='delivered').length;
    if(badge) badge.textContent=ships.length?`(${done}/${ships.length} 완료)`:'';
    list.innerHTML=ships.length?ships.map(shipRowHtml).join(''):'<div class="ship-empty">등록된 송장이 없습니다. 택배사와 송장번호를 입력해 추가하세요.</div>';
    updateModalShipBadge();
}
async function addShipment(){
    if(!editingId) return;
    const carrier=document.getElementById('shipCarrier').value;
    const no=document.getElementById('shipTrackingNo').value.trim();
    if(!no){alert('송장번호를 입력하세요.');return;}
    const res=await fetch(`/api/schedules/${editingId}/shipments`,{
        method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({carrier,tracking_no:no}),
    });
    const data=await res.json().catch(()=>null);
    if(!res.ok){alert((data&&data.message)||(data&&data.errors&&Object.values(data.errors).flat().join('\n'))||'등록 실패');return;}
    document.getElementById('shipTrackingNo').value='';
    shipCache=data; renderShipments(); loadEvents(); // 칩 아이콘 갱신
}
async function deleteShipment(id){
    if(!confirm('이 송장을 삭제하시겠습니까?')) return;
    const res=await fetch(`/api/schedule-shipments/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(!res.ok){alert('삭제 실패');return;}
    shipCache=await res.json(); renderShipments(); loadEvents();
}
async function refreshShipments(){
    if(!editingId) return;
    const res=await fetch(`/api/schedules/${editingId}/shipments/refresh`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(!res.ok){alert('배송상태 갱신 실패');return;}
    shipCache=await res.json(); renderShipments(); loadEvents();
    if(isLocked) renderLockSummary();
}

function resetAttachments(){
    pendingAttachments={quote:[],reference:[],room:[],general:[]};existingAttachments={quote:[],reference:[],room:[],general:[]};
    ['quote','reference','room','general'].forEach(t=>renderImgGrid(t));
}

// ── 견적서 연동 ──
let estimateSearchTimer=null;
function renderEstimateList(list){
    const sm={created:'작성중',editing:'수정중',completed:'완료',paid:'결제완료',hold:'보류'};
    if(!list.length) return '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">결과 없음</div>';
    return list.map(e=>{
        const amt=e.total_amount?Number(e.total_amount).toLocaleString()+'원':'';
        const date=e.created_at?(e.created_at.substring(0,10)):'';
        const name=e.client_nickname||e.client_name||'(이름없음)';
        return `<div style="padding:10px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;transition:background 0.1s;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
            <div style="flex:1;cursor:pointer;min-width:0;" onclick="selectEstimate(${e.id},'${name.replace(/'/g,"\\'")}',${e.total_amount||0})">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="font-size:13px;font-weight:600;">#${e.id}</span>
                    <span style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${name}</span>
                    <span style="font-size:10px;padding:2px 6px;border-radius:4px;background:var(--surface2);color:var(--text-muted);flex-shrink:0;">${sm[e.status]||e.status}</span>
                </div>
                <div style="display:flex;gap:8px;margin-top:3px;font-size:11px;color:var(--text-muted);">
                    ${amt?'<span style="color:var(--accent);">'+amt+'</span>':''}
                    ${date?'<span>'+date+'</span>':''}
                </div>
            </div>
            <button onclick="event.stopPropagation();window.open('/estimates/${e.id}/print','estimate_print','width=900,height=700,scrollbars=yes,resizable=yes')" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;flex-shrink:0;transition:all 0.15s;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">보기</button>
        </div>`;
    }).join('');
}

async function openEstimateSearch(){
    document.getElementById('estimateSearchOverlay').style.display='flex';
    document.getElementById('estimateSearchInput').value='';
    document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">로딩 중...</div>';
    setTimeout(()=>document.getElementById('estimateSearchInput').focus(),50);
    // 최근 목록 자동 로드
    try{
        const res=await fetch('/api/estimates');
        if(res.ok){const data=await res.json();const list=data.data||data;document.getElementById('estimateSearchResults').innerHTML=renderEstimateList(list);}
    }catch(e){document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">로드 실패</div>';}
}
function searchEstimates(query){
    clearTimeout(estimateSearchTimer);
    if(!query.trim()){openEstimateSearch();return;}
    estimateSearchTimer=setTimeout(async()=>{
        const res=await fetch(`/api/estimates?search=${encodeURIComponent(query)}`);if(!res.ok)return;
        const data=await res.json();const list=data.data||data;
        document.getElementById('estimateSearchResults').innerHTML=renderEstimateList(list);
    },300);
}
function selectEstimate(id,name,amount){
    linkedEstimateId=id;
    document.getElementById('linkedEstimateTitle').textContent=`#${id} ${name}`;
    document.getElementById('linkedEstimateInfo').style.display='';
    if(amount) document.getElementById('g_estimate_amount').value=amount.toLocaleString();
    document.getElementById('estimateSearchOverlay').style.display='none';
}
function unlinkEstimate(){linkedEstimateId=null;document.getElementById('linkedEstimateInfo').style.display='none';}
function openLinkedEstimate(){
    if(!linkedEstimateId) return;
    window.open(`/estimates/${linkedEstimateId}/print`,'estimate_print','width=900,height=700,scrollbars=yes,resizable=yes');
}
function extractEstimateAmount(){
    if(!linkedEstimateId){alert('먼저 견적서를 불러와주세요.');return;}
    fetch(`/api/estimates?search=${linkedEstimateId}`).then(r=>r.json()).then(data=>{
        const list=data.data||data;const est=list.find(e=>e.id===linkedEstimateId);
        if(est&&est.total_amount) document.getElementById('g_estimate_amount').value=Number(est.total_amount).toLocaleString();
    });
}

function openHistoryFromEdit(){
    if(!editingId) return;
    openActivityLog('Schedule', editingId, '일정 수정 로그');
}

// ── 폼 초기화 ──
function resetModalForm(){
    // 미팅/내방 옵션 체크 초기화
    document.querySelectorAll('#visitOptsList input:checked').forEach(cb=>cb.checked=false);
    // 라디오 그룹 초기화
    ['g_platform_group','g_career_group','g_source_group','g_topic_group','g_budget_group','g_req_topic_group','g_paid_group','g_order_group','g_delivery_group','g_balance_group','teal_mode_group'].forEach(id=>clearRadio(id));
    // 기본값 세팅
    setRadio('g_career_group','처음');
    setRadio('g_paid_group','미결제');
    setRadio('g_order_group','X');
    setRadio('g_balance_group','X');
    setRadio('teal_mode_group','remote');
    // schedEventOpts / scheduleOpts / specialOpts 초기화
    document.querySelectorAll('#schedEventOpts .special-opt-btn, #scheduleOpts .sched-opt-btn, #specialOpts .special-opt-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('schedReasonWrap').style.display='none';
    document.getElementById('schedAfterReason').value='';
    // 조건부 필드 숨기기
    document.querySelectorAll('.conditional-field').forEach(f=>f.classList.remove('visible'));
    document.getElementById('g_delivery_wrap').style.display='none';
    // 텍스트 초기화
    ['g_nickname','g_name','g_phone','g_platform_etc','g_source_ref','g_topic_etc','g_budget_etc','g_equipment','g_req_topic_etc','g_req_detail','g_special','g_estimate_amount','g_balance_amount','t_remote_name','t_remote_platform','t_remote_content','t_studio_name','t_studio_platform','t_studio_content','t_desc','commonDesc','commonHandoverNote','modalLocation','modalLocationDetail','modalAddress','moveFromLocation','moveFromDetail','moveFromAddress','schedAfterReason'].forEach(id=>{const el=document.getElementById(id);if(el) el.value='';});
    // 의뢰자/프로젝트/견적서/잠금/잔금
    linkedClientId=null;linkedProjectId=null;
    document.getElementById('linkedClientInfo').style.display='none';
    document.getElementById('linkedClientName').textContent=''; // 이전 일정의 의뢰자명이 다음 일정에 새는 것 방지
    document.getElementById('projectSelectWrap').style.display='none';
    document.getElementById('clientSearchInput').value='';
    linkedEstimateId=null;
    document.getElementById('linkedEstimateInfo').style.display='none';
    isLocked=false; document.getElementById('lockBtn').textContent='☐ 요약'; document.getElementById('lockBtn').classList.remove('locked');
    document.getElementById('lockedBanner').classList.remove('visible');
    document.querySelector('#modalOverlay .modal-body')?.classList.remove('is-locked');
    document.getElementById('balanceBanner').classList.remove('visible');
    isAllDay=false; document.getElementById('alldayTrack').classList.remove('on');
    // 이사세팅 출발지 상태 초기화
    { const nf=document.getElementById('moveNoFrom'); if(nf) nf.checked=false; onMoveNoFromToggle(); }
    document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='');
    document.getElementById('notifSelect').value='60';
    // 반복 UI 초기화 (새 일정에서만 노출, 편집 시 loadEventToModal이 숨김)
    {const rf=document.getElementById('repeatFreq'); if(rf){rf.value='';onRepeatFreqChange();}
     const ru=document.getElementById('repeatUntil'); if(ru) ru.value='';
     const ri=document.getElementById('repeatInterval'); if(ri) ri.value='2';
     const rg=document.getElementById('repeatGroup'); if(rg){rg.querySelector('.notif-row').style.display='flex';}
     const rn=document.getElementById('repeatEditNote'); if(rn) rn.style.display='none';}
    // 입력 재활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input').forEach(el=>{el.disabled=false;});
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='';});
    resetAttachments();
    // 배송 현황 초기화 (편집 진입 시 loadShipments가 다시 채움)
    shipCache={shipments:[],carriers:{}};
    const shipList=document.getElementById('shipmentList'); if(shipList) shipList.innerHTML='';
    const shipBadge=document.getElementById('shipSummaryBadge'); if(shipBadge) shipBadge.textContent='';
    updateShipmentSectionVisibility();
    updateModalShipBadge();
    toggleShipmentBody(false); // 배송 현황은 기본 접힘
    assigneePanelOpen=false;
    document.getElementById('assigneeList').style.display='none';
}

// ── 모달 열기 ──
function openNewModal(dateStr,timeStr,endStr){
    editingId=null; selectedAssignees=[]; viewMode=false;
    resetModalForm();
    setEditModeUI();
    setColor('gold');
    document.getElementById('modalTitle').value='';
    // 날짜 — 미지정 시 오늘로 기본 지정 (범위 선택 시 endStr로 종료일 프리필)
    const ds=dateStr||todayStr();
    const de=endStr||ds;
    document.getElementById('startDate').value=ds;
    document.getElementById('endDate').value=de;
    document.getElementById('goldStartDate').value=ds;
    document.getElementById('goldEndDate').value=de;
    // 시간
    const st=timeStr||'13:00';
    const etH=String(Math.min(parseInt(st)+1,23)).padStart(2,'0');
    const et=etH+':00';
    document.getElementById('startTime').value=st;document.getElementById('startTimeTrigger').textContent=st;
    document.getElementById('endTime').value=et;document.getElementById('endTimeTrigger').textContent=et;
    document.getElementById('goldStartTime').value=st;document.getElementById('goldStartTimeTrigger').textContent=st;
    document.getElementById('goldEndTime').value=et;document.getElementById('goldEndTimeTrigger').textContent=et;
    // 날짜 배지
    const d=dateStr?new Date(dateStr):new Date();
    document.getElementById('modalDateBadge').textContent=`${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS_KO[d.getDay()]})`;
    document.getElementById('btnDelete').style.display='none';
    document.getElementById('btnLog').style.display='none';
    updateAssigneeBtn();
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(calRefreshAutoGrow, 0); // 상세 내용 높이 자동 맞춤
    pushCalModalHistory();
    setTimeout(()=>document.getElementById('modalTitle').focus(),50);
}

// ── 상세 모달 ──
const COLOR_LABELS = (function(){
    const o = {holiday:'공휴일'};
    const cats = window.CALENDAR_CATEGORIES || {};
    Object.keys(cats).forEach(k => { o[k] = cats[k].label; });
    return o;
})();
const FIELD_LABELS = {title:'제목',start_date:'시작일',end_date:'종료일',start_time:'시작시간',end_time:'종료시간',is_all_day:'종일',color:'유형',client_name:'의뢰자',address:'주소',location:'장소',description:'상세설명',special_note:'특이사항',handover_note:'전달사항',is_locked:'잠금',is_private:'비공개',gold_data:'의뢰자정보',teal_data:'원격정보',notif_minutes:'알림(분)',sched_opt:'세부유형',sched_event_opts:'세부옵션',special_opts:'특수옵션',sched_after_days:'AS일수',sched_after_date:'AS만료일',sched_after_reason:'AS사유',assignees:'담당자',completed_at:'완료시각'};

// 변경 로그 값을 사람이 읽기 좋게 변환
function fmtLogValue(key, val) {
    if (val === null || val === undefined || val === '') return '—';
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) {
        const d = new Date(val);
        if (isNaN(d.getTime())) return val;
        return d.toLocaleDateString('ko-KR',{timeZone:'Asia/Seoul',year:'numeric',month:'2-digit',day:'2-digit'}).replace(/\.\s?/g,'-').replace(/-$/,'');
    }
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    if (typeof val === 'string' && /^\d{1,2}:\d{2}/.test(val)) return val.slice(0,5);
    if (val === true || val === 'true' || val === 1 || val === '1') return '예';
    if (val === false || val === 'false' || val === 0 || val === '0') return '아니오';
    if (key === 'color' && COLOR_LABELS[val]) return COLOR_LABELS[val];
    if (typeof val === 'object') {
        if (Array.isArray(val)) {
            if (val.length === 0) return '(없음)';
            if (val.every(x => typeof x === 'number')) return `${val.length}명`;
            const names = val.map(o => o?.name || o?.title).filter(Boolean).slice(0,3);
            if (names.length) return names.join(', ') + (val.length > 3 ? ` 외 ${val.length-3}` : '');
            return `(${val.length}개 항목)`;
        }
        if (val.name) return String(val.name);
        if (val.title) return String(val.title);
        return '(상세 변경)';
    }
    return String(val);
}
let detailEvent = null;

let viewMode = false; // true: 상세보기(읽기전용), false: 편집

// 완료 버튼(푸터 + 모달 옆 외부) 상태 갱신. showFooter=true면 푸터 버튼도 노출(보기 모드).
function updateCompleteUI(showFooter){
    const existing=!!editingId;
    const done=!!(detailEvent&&detailEvent.completed_at);
    const f=document.getElementById('btnComplete');
    if(f){
        f.style.display=(showFooter&&existing)?'':'none';
        f.textContent=done?'✓ 완료됨 (해제)':'✓ 완료';
        f.classList.toggle('done',done);
    }
    const ext=document.getElementById('extCompleteBtn');
    if(ext){
        ext.style.display=existing?'flex':'none'; // 보기/편집 모드 모두 노출(기존 일정만)
        ext.textContent=done?'✓':'완료';
        ext.title=done?'완료됨 — 클릭 시 해제':'완료 처리';
        ext.classList.toggle('done',done);
    }
}

function setViewModeUI(){
    // 모든 입력 비활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input, #modalOverlay select').forEach(el=>{el.disabled=true;});
    document.querySelectorAll('#modalOverlay .time-picker-trigger').forEach(el=>{el.style.pointerEvents='none'; el.style.opacity='0.6';});
    document.querySelectorAll('#modalOverlay input[type="date"]').forEach(el=>{el.readOnly=true; el.style.pointerEvents='none'; el.style.opacity='0.6';});
    document.getElementById('alldayToggle').style.pointerEvents='none';
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='none';});
    document.querySelectorAll('#modalOverlay .radio-btn:not([data-always-active])').forEach(b=>{b.style.pointerEvents='none';});
    document.querySelectorAll('#modalOverlay .color-dot').forEach(b=>{b.style.pointerEvents='none';});
    // 읽기 모드: 카테고리는 상단 배지(typeBadge)로 이미 표시되므로 선택 칩 줄 전체 숨김(중복 방지) + 공휴일 지정 버튼 숨김
    const crow=document.getElementById('colorRow'); if(crow) crow.style.display='none';
    const hwrap=document.querySelector('#modalOverlay .holiday-btn-wrap'); if(hwrap) hwrap.style.display='none';
    document.querySelectorAll('#modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn').forEach(b=>{b.style.pointerEvents='none';});
    // 보기/해제 버튼은 항상 활성화
    document.querySelectorAll('#modalOverlay [data-always-active]').forEach(b=>{b.style.pointerEvents='auto';});
    // 보기 모드: '요약'(읽기 요약 뷰)을 기본 ON으로 표시 — 요약 버튼은 항상 노출
    document.getElementById('lockBtn').style.display='';
    applyLockUI();
    document.querySelector('.btn-save-top').style.display='none';
    document.getElementById('btnDelete').style.display='';
    document.getElementById('btnLog').style.display='';
    updateCompleteUI(true); // 푸터 완료 버튼 노출(보기 모드)
    // 외부 버튼을 수정으로
    const extBtn=document.getElementById('modalExternalAction');
    extBtn.textContent='수정';
    extBtn.style.display='';
    extBtn.onclick=()=>{switchToEditMode();};
    // 푸터
    const saveBtn=document.querySelector('.modal-footer .btn-save');
    saveBtn.textContent='수정';
    saveBtn.onclick=()=>{switchToEditMode();};
    // 보기 모드에서는 변경 사유 필드 숨김
    const reasonField=document.getElementById('reasonField');
    if (reasonField) reasonField.style.display='none';
}

function setEditModeUI(){
    // 모든 입력 활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input, #modalOverlay select').forEach(el=>{el.disabled=false;});
    document.querySelectorAll('#modalOverlay .time-picker-trigger').forEach(el=>{el.style.pointerEvents=''; el.style.opacity='';});
    document.querySelectorAll('#modalOverlay input[type="date"]').forEach(el=>{el.readOnly=false; el.style.pointerEvents=''; el.style.opacity='';});
    document.getElementById('alldayToggle').style.pointerEvents='';
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='';});
    document.querySelectorAll('#modalOverlay .radio-btn').forEach(b=>{b.style.pointerEvents='';});
    document.querySelectorAll('#modalOverlay .color-dot').forEach(b=>{b.style.pointerEvents='';});
    // 편집 모드: 카테고리 선택 줄/칩 전체 다시 표시 + 공휴일 지정 버튼 복원
    const crow2=document.getElementById('colorRow'); if(crow2) crow2.style.display='';
    document.querySelectorAll('#colorRow .color-dot').forEach(d=>{ d.style.display=''; });
    const hwrap2=document.querySelector('#modalOverlay .holiday-btn-wrap'); if(hwrap2) hwrap2.style.display='';
    document.querySelectorAll('#modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn').forEach(b=>{b.style.pointerEvents='';});
    // 편집 모드: 요약 해제하여 전체 폼 표시
    isLocked=false; applyLockUI();
    // 버튼 복원
    document.getElementById('lockBtn').style.display='';
    updateCompleteUI(false); // 편집 모드: 푸터 완료 버튼 숨김(외부 완료 버튼은 유지)
    document.querySelector('.btn-save-top').style.display='';
    // 외부 버튼을 저장으로
    const extBtn=document.getElementById('modalExternalAction');
    extBtn.textContent='저장';
    extBtn.onclick=()=>{saveEvent();};
    // 푸터
    const saveBtn=document.querySelector('.modal-footer .btn-save');
    saveBtn.textContent='저장';
    saveBtn.onclick=()=>{saveEvent();};
    // 변경 사유 필드: 수정 모드 + 방문의뢰/원격·방송룸 카테고리만 표시
    updateReasonFieldVisibility();
}

// 변경 사유는 gold(방문의뢰)/teal(원격·방송룸)에서 날짜/시간 변경 시에만 필수
const REASON_COLORS=['gold','teal'];
function updateReasonFieldVisibility(){
    const reasonField=document.getElementById('reasonField');
    if(!reasonField) return;
    const show=!!editingId && !viewMode && REASON_COLORS.includes(currentColor);
    reasonField.style.display=show?'':'none';
    if(!editingId) document.getElementById('modalReason').value='';
}

function openDetailModal(ev) {
    if(isGuestUser) return;
    detailEvent = ev;
    viewMode = true;
    openEditModal(ev);
    // 읽기전용 UI 적용
    setTimeout(()=>{
        setViewModeUI();
        // 모바일: 수정 폼이 아니라 자물쇠(읽기 전용 요약) 뷰로 표시
        if(window.innerWidth<=768){
            renderLockSummary();
            document.querySelector('#modalOverlay .modal-body')?.classList.add('is-locked');
        }
    },0);
}

function switchToEditMode() {
    viewMode = false;
    // 모바일 잠금(읽기전용) 요약 뷰 해제 → 편집 폼 노출
    document.querySelector('#modalOverlay .modal-body')?.classList.remove('is-locked');
    setEditModeUI();
}

function closeDetail() {
    if(viewMode) {
        viewMode = false;
        closeModal();
    } else {
        document.getElementById('detailOverlay').style.display = 'none';
    }
    detailEvent = null;
}

function editFromDetail() {
    if (!detailEvent) return;
    switchToEditMode();
}

function deleteEventFromDetail() {
    if (!detailEvent) return;
    const id = detailEvent.id;
    closeDetail();
    deleteEvent(id);
}

async function openHistoryModal() {
    if (!detailEvent) return;
    document.getElementById('historyOverlay').style.display = 'flex';
    document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">로딩 중...</div>';

    const res = await fetch(`/api/events/${detailEvent.id}/history`, { headers:{'Accept':'application/json'} });
    if (!res.ok) { document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--red);">로드 실패</div>'; return; }
    const data = await res.json();
    // 이전 응답(배열) 호환 + 새 응답({schedule, changes}) 처리
    const items = Array.isArray(data) ? data : (data.changes || []);

    if (!items.length) {
        document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">수정내역이 없습니다.</div>';
        return;
    }

    const ACTION_LABEL = { update:'수정', delete:'삭제', complete:'완료', uncomplete:'완료해제', restore:'복원' };
    const ACTION_COLOR = { update:'var(--accent)', delete:'var(--red)', complete:'var(--green)', uncomplete:'var(--text-muted)', restore:'var(--blue)' };

    document.getElementById('historyBody').innerHTML = items.map(h => {
        const changes = h.changes || {};
        let body = '';
        if (h.action === 'delete' && changes.snapshot) {
            const snap = changes.snapshot;
            const lines = [];
            if (snap.title) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">제목</span> <span style="color:var(--text);">${escHtml(snap.title)}</span></div>`);
            if (snap.start_date) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">일자</span> <span style="color:var(--text);">${escHtml(fmtLogValue('start_date', snap.start_date))}${snap.end_date && snap.end_date !== snap.start_date ? ' ~ '+escHtml(fmtLogValue('end_date', snap.end_date)) : ''}</span></div>`);
            if (snap.client_name) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">의뢰자</span> <span style="color:var(--text);">${escHtml(snap.client_name)}</span></div>`);
            if (snap.location) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">장소</span> <span style="color:var(--text);">${escHtml(snap.location)}</span></div>`);
            body = `<div style="display:flex; flex-direction:column; gap:3px; margin-top:4px; font-size:12px;">${lines.join('')}</div>`;
        } else if (h.action === 'restore' || h.action === 'uncomplete' || (h.action === 'complete' && !Object.keys(changes).length)) {
            body = '';
        } else {
            const rows = Object.entries(changes).filter(([k]) => k !== 'snapshot').map(([key, val]) => {
                const label = (typeof FIELD_LABELS !== 'undefined' && FIELD_LABELS[key]) || key;
                const oldVal = fmtLogValue(key, val.old);
                const newVal = fmtLogValue(key, val.new);
                return `<div style="margin:6px 0;">
                    <div style="font-size:11px; color:var(--text-muted);">${label}</div>
                    <div style="display:flex; gap:6px; margin-top:2px; flex-wrap:wrap; align-items:center;">
                        <span style="padding:2px 8px; border-radius:4px; background:rgba(212,136,136,0.15); color:var(--red); font-size:12px; text-decoration:line-through;">${escHtml(oldVal)}</span>
                        <span style="color:var(--text-muted); font-size:11px;">→</span>
                        <span style="padding:2px 8px; border-radius:4px; background:rgba(136,212,136,0.15); color:var(--green); font-size:12px;">${escHtml(newVal)}</span>
                    </div>
                </div>`;
            }).join('');
            body = rows;
        }

        const reasonHtml = h.reason
            ? `<div style="margin-top:6px; padding:6px 10px; background:var(--surface2); border-left:3px solid var(--accent); border-radius:4px; font-size:12px; color:var(--text);"><span style="font-size:10px; color:var(--text-muted); letter-spacing:0.04em; margin-right:6px;">사유</span>${escHtml(h.reason)}</div>`
            : '';

        return `<div style="padding:12px 0; border-bottom:1px solid var(--border);">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                <span style="font-size:10px; padding:2px 6px; border-radius:3px; background:var(--surface2); color:${ACTION_COLOR[h.action]||'var(--accent)'}; font-weight:600;">${ACTION_LABEL[h.action]||h.action}</span>
                <span style="font-size:12px; font-weight:600;">${escHtml(h.user_name||'-')}</span>
                <span style="font-size:10px; color:var(--text-muted);">${h.created_at}</span>
            </div>
            ${body}
            ${reasonHtml}
        </div>`;
    }).join('');
}

// ── 편집 모달 ──
function openEditModal(ev){
    if(isGuestUser) return;
    editingId=ev.id; selectedAssignees=ev.assignees?ev.assignees.map(a=>a.id):[];
    // 날짜/시간 원본 스냅샷 — 변경 사유 필수 여부 판정용
    editingOrigDT={
        sd:(ev.start_date||'').substring(0,10),
        ed:((ev.end_date||ev.start_date)||'').substring(0,10),
        st:(ev.start_time||'').substring(0,5),
        et:(ev.end_time||'').substring(0,5),
        allday:!!ev.is_all_day,
    };
    resetModalForm();
    setColor(ev.color);
    document.getElementById('modalTitle').value=ev.title||'';
    // 날짜/시간
    const sd=(ev.start_date||'').substring(0,10), ed=(ev.end_date||'').substring(0,10);
    const st=ev.start_time||'13:00', et=ev.end_time||'14:00';
    document.getElementById('startDate').value=sd;document.getElementById('endDate').value=ed;
    document.getElementById('goldStartDate').value=sd;document.getElementById('goldEndDate').value=ed||sd;
    document.getElementById('startTime').value=st;document.getElementById('startTimeTrigger').textContent=st.substring(0,5);
    document.getElementById('endTime').value=et;document.getElementById('endTimeTrigger').textContent=et.substring(0,5);
    document.getElementById('goldStartTime').value=st;document.getElementById('goldStartTimeTrigger').textContent=st.substring(0,5);
    document.getElementById('goldEndTime').value=et;document.getElementById('goldEndTimeTrigger').textContent=et.substring(0,5);
    if(ev.is_all_day){isAllDay=true;document.getElementById('alldayTrack').classList.add('on');document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='none');}
    // 장소
    // address=도로명, location=도로명+상세. 상세주소 분리 복원
    {
        const road = ev.address || ev.location || '';
        let detail = '';
        if (ev.address && ev.location && ev.location.startsWith(ev.address)) {
            detail = ev.location.slice(ev.address.length).trim();
        }
        document.getElementById('modalLocation').value = road;
        document.getElementById('modalAddress').value = ev.address || road;
        const det=document.getElementById('modalLocationDetail'); if(det) det.value = detail;
    }
    // 알림
    if(ev.notif_minutes!==null&&ev.notif_minutes!==undefined) document.getElementById('notifSelect').value=ev.notif_minutes;
    // 편집 중엔 반복 설정 불가 — 안내만 표시
    {const rg=document.getElementById('repeatGroup'); if(rg) rg.querySelector('.notif-row').style.display='none';
     const rn=document.getElementById('repeatEditNote'); if(rn) rn.style.display='block';}
    // 요약: 등록된 일정은 기본적으로 '요약'(읽기 요약 뷰) ON
    if(ev && ev.id){ isLocked=true; setTimeout(applyLockUI,0); }
    // 날짜 배지
    const d=sd?new Date(sd):new Date();
    document.getElementById('modalDateBadge').textContent=`${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS_KO[d.getDay()]})`;
    // 일정옵션
    if(ev.sched_event_opts){const opts=Array.isArray(ev.sched_event_opts)?ev.sched_event_opts:[];opts.forEach(v=>{const b=document.querySelector(`#schedEventOpts [data-seopt="${v}"]`);if(b)b.classList.add('active');});if(opts.includes('after'))document.getElementById('schedReasonWrap').style.display='';}
    if(ev.sched_opt){const b=document.querySelector(`#scheduleOpts [data-sopt="${ev.sched_opt}"]`);if(b)b.classList.add('active');}
    if(ev.special_opts){const opts=Array.isArray(ev.special_opts)?ev.special_opts:[];opts.forEach(v=>{const b=document.querySelector(`#specialOpts [data-opt="${v}"]`);if(b)b.classList.add('active');});
        // 미팅/내방 옵션 체크 복원
        document.querySelectorAll('#visitOptsList input').forEach(cb=>{ cb.checked=opts.includes(cb.value); });
        applyVisitOptsUI();
    }
    if(ev.sched_after_reason) document.getElementById('schedAfterReason').value=ev.sched_after_reason;
    // 공통 필드
    document.getElementById('commonDesc').value=ev.description||'';
    const _ch=document.getElementById('commonHandoverNote'); if(_ch) _ch.value=ev.handover_note||'';
    // gold_data 복원 (Firebase 데이터 구조 호환)
    const g=ev.gold_data||{};
    // client_name 폴백은 방문의뢰(gold)일 때만 — 과거 누수로 오염된 비-gold 일정의 의뢰자명이 폼에 부활하는 것 방지
    document.getElementById('g_nickname').value=g.nickname||(ev.color==='gold'?(ev.client_name||''):'');
    document.getElementById('g_name').value=g.name||'';
    document.getElementById('g_phone').value=g.phone||'';
    // 플랫폼: "SOOP, 유튜브, 직접입력값" → pill 선택 + 기타 입력
    if(g.platform){
        const known=['SOOP','치지직','유튜브','틱톡','기타'];
        const vals=g.platform.split(',').map(v=>v.trim());
        const pillVals=vals.map(v=>known.includes(v)?v:'기타');
        setMultiRadio('g_platform_group',[...new Set(pillVals)]);
        const etcVals=vals.filter(v=>!known.includes(v));
        if(etcVals.length) document.getElementById('g_platform_etc').value=etcVals.join(', ');
        handleConditional('g_platform_group');
    }
    if(g.career) setRadio('g_career_group',g.career);
    // 유입경로: "소개:홍길동" → 소개 선택 + 이름 입력
    if(g.source){
        if(g.source.startsWith('소개:')){
            setRadio('g_source_group','소개');
            document.getElementById('g_source_ref').value=g.source.substring(3);
            handleConditional('g_source_group');
        } else {
            setRadio('g_source_group',g.source);
        }
    }
    // 방송주제: "소통, 게임, 직접입력값" → pill 선택 + 기타 입력
    if(g.topic){
        const known=['소통','게임','노래','먹방','야외','버추얼','코인','주식','기타','미정'];
        // 레거시 '주식/코인' → 주식+코인으로 분해
        const vals=g.topic.split(',').map(v=>v.trim()).flatMap(v=>v==='주식/코인'?['주식','코인']:[v]);
        const pillVals=vals.map(v=>known.includes(v)?v:'기타');
        setMultiRadio('g_topic_group',[...new Set(pillVals)]);
        const etcVals=vals.filter(v=>!known.includes(v));
        if(etcVals.length) document.getElementById('g_topic_etc').value=etcVals.join(', ');
        handleConditional('g_topic_group');
    }
    // 예산: "풍족"/"부족"/"모름" or 직접입력한 값 → pill 선택 + 기타 입력
    if(g.budget){
        const known=['풍족','부족','모름','직접입력'];
        if(known.includes(g.budget)){
            setRadio('g_budget_group',g.budget);
        } else {
            setRadio('g_budget_group','직접입력');
            document.getElementById('g_budget_etc').value=g.budget;
            handleConditional('g_budget_group');
        }
    }
    document.getElementById('g_equipment').value=g.equipment||'';
    // 의뢰주제: "처음세팅, 추가세팅, 직접입력값" → pill 선택 + 기타 입력
    if(g.req_topic){
        const known=['처음세팅','추가세팅','이사세팅','렌탈','기타'];
        const vals=g.req_topic.split(',').map(v=>v.trim());
        const pillVals=vals.map(v=>known.includes(v)?v:'기타');
        setMultiRadio('g_req_topic_group',[...new Set(pillVals)]);
        const etcVals=vals.filter(v=>!known.includes(v));
        if(etcVals.length) document.getElementById('g_req_topic_etc').value=etcVals.join(', ');
        handleConditional('g_req_topic_group');
    }
    document.getElementById('g_req_detail').value=g.req_detail||'';
    // 이사세팅 출발지 복원 (address=도로명, location=도로명+상세)
    {
        const mfRoad=g.move_from_address||'';
        const mfLoc=g.move_from_location||'';
        let mfDetail='';
        if(mfRoad && mfLoc && mfLoc.startsWith(mfRoad)) mfDetail=mfLoc.slice(mfRoad.length).trim();
        const mfl=document.getElementById('moveFromLocation'); if(mfl) mfl.value=mfRoad||mfLoc;
        const mfa=document.getElementById('moveFromAddress'); if(mfa) mfa.value=mfRoad;
        const mfd=document.getElementById('moveFromDetail'); if(mfd) mfd.value=mfDetail;
        const noFrom=document.getElementById('moveNoFrom');
        if(noFrom){ noFrom.checked = !!g.move_no_from; }
        onMoveNoFromToggle();
    }
    updateMoveSettingUI();
    document.getElementById('g_special').value=g.special||'';
    if(g.specialReason) document.getElementById('specialReason').value=g.specialReason;
    if(g.paid) setRadio('g_paid_group',g.paid);
    document.getElementById('g_estimate_amount').value=_fmtAmt(g.estimate_amount||'');
    if(g.order){setRadio('g_order_group',g.order);if(g.order==='O')document.getElementById('g_delivery_wrap').style.display='';handleConditional('g_order_group');}
    if(g.delivery) setRadio('g_delivery_group',g.delivery);
    if(g.balance){setRadio('g_balance_group',g.balance);handleConditional('g_balance_group');}
    document.getElementById('g_balance_amount').value=_fmtAmt(g.balance_amount||'');
    if(g.estimate_id){linkedEstimateId=g.estimate_id;document.getElementById('linkedEstimateTitle').textContent=`#${g.estimate_id}`;document.getElementById('linkedEstimateInfo').style.display='';}
    // 의뢰자/프로젝트 연결 복원
    if(g.client_id){
        linkedClientId=g.client_id;
        document.getElementById('linkedClientName').textContent=g.nickname||g.name||`의뢰자 #${g.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        document.getElementById('linkedClientLink').href='/clients/'+g.client_id;
        linkedProjectId=g.project_id||null;
        loadClientProjects(g.client_id);
        if(!(g.nickname||g.name)) restoreLinkedClientName(g.client_id);
    }
    // 비-gold에서도 gold_data에 저장된 의뢰자 연결 복원
    if(!g.client_id && ev.gold_data && ev.gold_data.client_id){
        linkedClientId=ev.gold_data.client_id;
        document.getElementById('linkedClientName').textContent=ev.gold_data.nickname||ev.gold_data.name||`의뢰자 #${ev.gold_data.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        document.getElementById('linkedClientLink').href='/clients/'+ev.gold_data.client_id;
        linkedProjectId=ev.gold_data.project_id||null;
        loadClientProjects(ev.gold_data.client_id);
        if(!(ev.gold_data.nickname||ev.gold_data.name)) restoreLinkedClientName(ev.gold_data.client_id);
    }
    // teal_data 복원
    const t=ev.teal_data||{};
    if(t.mode){setRadio('teal_mode_group',t.mode);document.getElementById('teal_remote_fields').style.display=t.mode==='remote'?'':'none';document.getElementById('teal_studio_fields').style.display=t.mode==='studio'?'':'none';}
    document.getElementById('t_remote_name').value=t.mode==='remote'?t.name||'':'';
    document.getElementById('t_remote_platform').value=t.mode==='remote'?t.platform||'':'';
    document.getElementById('t_remote_content').value=t.mode==='remote'?t.content||'':'';
    document.getElementById('t_studio_name').value=t.mode==='studio'?t.name||'':'';
    document.getElementById('t_studio_platform').value=t.mode==='studio'?t.platform||'':'';
    document.getElementById('t_studio_content').value=t.mode==='studio'?t.content||'':'';
    document.getElementById('t_desc').value=t.desc||'';
    // 첨부파일
    pendingAttachments={quote:[],reference:[],room:[],general:[]};
    loadExistingAttachments(ev.id);
    // 배송 현황 (gold/green) — editingId 설정 후이므로 섹션 노출 + 목록 로드
    updateShipmentSectionVisibility();
    loadShipments();
    // UI
    document.getElementById('btnDelete').style.display='';
    document.getElementById('btnLog').style.display='';
    updateAssigneeBtn();updateBalanceBanner();
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(calRefreshAutoGrow, 0); // 상세 내용 높이 자동 맞춤
    pushCalModalHistory();
}

// ── 모달이 열렸을 때 브라우저 뒤로가기 → 모달 닫기 ──
let __calModalHistory=false;
function pushCalModalHistory(){
    if(!__calModalHistory){ try{ history.pushState({calModal:1},''); }catch(e){} __calModalHistory=true; }
}
window.addEventListener('popstate',function(){
    const ov=document.getElementById('modalOverlay');
    if(ov && ov.classList.contains('open')){
        __calModalHistory=false; // 이 history 항목은 이미 pop됨
        closeModal();            // 페이지 이동 대신 모달만 닫음
    }
});

function closeModal(){
    document.getElementById('modalOverlay').classList.remove('open');editingId=null;
    // UI로 닫을 때(뒤로가기 아님) push했던 history 항목 소비
    if(__calModalHistory){ __calModalHistory=false; try{ history.back(); }catch(e){} }
    document.querySelectorAll('.time-picker-popup').forEach(p=>p.remove());
    const rf=document.getElementById('reasonField'); if (rf) rf.style.display='none';
    const rm=document.getElementById('modalReason'); if (rm) rm.value='';
    // 상태 복원
    viewMode=false;
    setEditModeUI();
}

// ── 데이터 수집 ──
function collectGoldFields(){
    // 플랫폼 (멀티선택, 기타→직접입력 치환)
    const platSel=getMultiRadio('g_platform_group');
    const platEtc=document.getElementById('g_platform_etc')?.value.trim()||'';
    const platform=platSel.length?platSel.map(v=>v==='기타'?(platEtc||'기타'):v).join(', '):'';
    // 방송주제 (멀티선택, 기타→직접입력 치환)
    const topicSel=getMultiRadio('g_topic_group');
    const topicEtc=document.getElementById('g_topic_etc')?.value.trim()||'';
    const topic=topicSel.length?topicSel.map(v=>v==='기타'?(topicEtc||'기타'):v).join(', '):'';
    // 예산 (직접입력→실제값 치환)
    const budgetSel=getRadio('g_budget_group');
    const budget=budgetSel==='직접입력'?(document.getElementById('g_budget_etc')?.value.trim()||'직접입력'):(budgetSel||'');
    // 유입경로 (소개→소개:이름)
    const sourceSel=getRadio('g_source_group');
    const source=sourceSel==='소개'?'소개:'+(document.getElementById('g_source_ref')?.value.trim()||''):(sourceSel||'');
    // 의뢰주제 (멀티선택, 기타→직접입력 치환)
    const reqTopicSel=getMultiRadio('g_req_topic_group');
    const reqTopicEtc=document.getElementById('g_req_topic_etc')?.value.trim()||'';
    const req_topic=reqTopicSel.length?reqTopicSel.map(v=>v==='기타'?(reqTopicEtc||'기타'):v).join(', '):'';
    // 특수옵션 사유
    const specialReason=document.getElementById('specialReason')?.value.trim()||'';
    return {
        nickname:document.getElementById('g_nickname').value.trim(),
        name:document.getElementById('g_name').value.trim(),
        phone:document.getElementById('g_phone').value.trim(),
        platform, topic, budget, source,
        equipment:document.getElementById('g_equipment').value.trim(),
        req_topic,
        estimate_amount:document.getElementById('g_estimate_amount')?.value.trim()||'',
        req_detail:document.getElementById('g_req_detail').value.trim(),
        special:document.getElementById('g_special').value.trim(),
        specialReason,
        career:getRadio('g_career_group'),
        paid:getRadio('g_paid_group'),
        order:getRadio('g_order_group'),
        delivery:getRadio('g_delivery_group'),
        balance:getRadio('g_balance_group'),
        balance_amount:document.getElementById('g_balance_amount').value.trim(),
        estimate_id:linkedEstimateId,
        client_id:linkedClientId,
        project_id:document.getElementById('projectSelect')?.value||null,
        // 이사세팅 출발지 (도착지는 기존 address/location 사용). '출발지 없음' 체크 시 빈 값
        move_no_from:document.getElementById('moveNoFrom')?.checked||false,
        move_from_address:document.getElementById('moveNoFrom')?.checked?'':(document.getElementById('moveFromAddress')?.value.trim()||''),
        move_from_location:document.getElementById('moveNoFrom')?.checked?'':[document.getElementById('moveFromLocation')?.value.trim(),document.getElementById('moveFromDetail')?.value.trim()].filter(Boolean).join(' '),
    };
}
function collectTealFields(){
    const mode=getRadio('teal_mode_group')||'remote';
    const data={mode};
    if(mode==='remote'){data.name=document.getElementById('t_remote_name').value.trim();data.platform=document.getElementById('t_remote_platform').value.trim();data.content=document.getElementById('t_remote_content').value.trim();}
    else{data.name=document.getElementById('t_studio_name').value.trim();data.platform=document.getElementById('t_studio_platform').value.trim();data.content=document.getElementById('t_studio_content').value.trim();}
    data.desc=document.getElementById('t_desc').value.trim();
    return data;
}

let saveInFlight=false;
async function saveEvent(){
    // 중복 클릭 방지 — 저장 완료 전 재클릭 시 무시 (일정·첨부 중복 등록 방지)
    if(saveInFlight) return;
    saveInFlight=true;
    const saveBtns=document.querySelectorAll('#modalOverlay .btn-save, #modalOverlay .btn-save-top, #modalExternalAction');
    saveBtns.forEach(b=>{b.disabled=true;b.style.opacity='0.6';});
    try{
        await doSaveEvent();
    }finally{
        saveInFlight=false;
        saveBtns.forEach(b=>{b.disabled=false;b.style.opacity='';});
    }
}
async function doSaveEvent(){
    const isGold=currentColor==='gold';
    const sd=isGold?document.getElementById('goldStartDate').value:document.getElementById('startDate').value;
    const ed=isGold?(document.getElementById('goldEndDate').value||document.getElementById('goldStartDate').value):document.getElementById('endDate').value;
    const st=isGold?document.getElementById('goldStartTime').value:document.getElementById('startTime').value;
    const et=isGold?document.getElementById('goldEndTime').value:document.getElementById('endTime').value;
    if(!sd){alert('시작일을 입력하세요.');return;}
    if(ed && ed<sd){alert('종료일이 시작일보다 빠릅니다. 날짜를 확인해주세요.');return;}
    if(!editingId){
        const rf=document.getElementById('repeatFreq')?.value||'';
        const ru=document.getElementById('repeatUntil')?.value||'';
        if(rf&&!ru){alert('반복 종료일을 선택해주세요.');return;}
        if(rf&&ru&&ru<=sd){alert('반복 종료일이 시작일보다 늦어야 합니다.');return;}
    }

    // schedEventOpts 수집
    const schedEventOpts=[...document.querySelectorAll('#schedEventOpts .special-opt-btn.active')].map(b=>b.dataset.seopt);
    const schedOpt=(()=>{const a=document.querySelector('#scheduleOpts .sched-opt-btn.active');return a?a.dataset.sopt:null;})();
    let specialOpts=[...document.querySelectorAll('#specialOpts .special-opt-btn.active')].map(b=>b.dataset.opt);
    // 미팅/내방 옵션(체크박스)도 special_opts에 함께 저장
    if(currentColor==='purple'){ specialOpts=specialOpts.concat([...document.querySelectorAll('#visitOptsList input:checked')].map(i=>i.value)); }

    const data={
        title:document.getElementById('modalTitle').value.trim()||'(제목 없음)',
        start_date:sd, end_date:ed||sd, start_time:isAllDay?null:st, end_time:isAllDay?null:et,
        is_all_day:isAllDay, color:currentColor,
        // 공통 유형은 별도 이름 필드 없음 — 연결된 의뢰자명(있으면)만 보조 저장
        client_name:isGold?document.getElementById('g_nickname').value.trim():(linkedClientId?(document.getElementById('linkedClientName')?.textContent.trim()||''):''),
        // address=도로명(검색), location=도로명+상세주소(표시용)
        address:document.getElementById('modalAddress').value.trim()||document.getElementById('modalLocation').value.trim(),
        location:[document.getElementById('modalLocation').value.trim(), document.getElementById('modalLocationDetail').value.trim()].filter(Boolean).join(' '),
        description:isGold?'':document.getElementById('commonDesc').value.trim(),
        // 전달사항 — 메모 없는 공통 유형에서만 입력(gold/teal은 자체 필드 사용)
        handover_note:(isGold||currentColor==='teal')?null:(document.getElementById('commonHandoverNote')?.value.trim()||null),
        assignees:selectedAssignees,
        notif_minutes:document.getElementById('notifSelect').value||null,
        repeat_freq:(!editingId?(document.getElementById('repeatFreq')?.value||null):null),
        repeat_interval:(!editingId&&document.getElementById('repeatFreq')?.value==='custom')?(document.getElementById('repeatInterval')?.value||1):null,
        repeat_unit:(!editingId&&document.getElementById('repeatFreq')?.value==='custom')?(document.getElementById('repeatUnit')?.value||'day'):null,
        repeat_until:(!editingId&&document.getElementById('repeatFreq')?.value)?(document.getElementById('repeatUntil')?.value||null):null,
        is_locked:isLocked,
        sched_opt:schedOpt,
        sched_event_opts:schedEventOpts,
        special_opts:specialOpts,
        sched_after_reason:document.getElementById('schedAfterReason').value.trim()||null,
        gold_data:isGold?collectGoldFields():(linkedClientId?{client_id:linkedClientId,project_id:document.getElementById('projectSelect')?.value||null,nickname:'',name:'',phone:''}:null),
        teal_data:currentColor==='teal'?collectTealFields():null,
    };

    // 변경 사유: 방문의뢰/원격·방송룸에서 날짜/시간이 바뀔 때만 필수 (백엔드에서도 동일 판정)
    if (editingId) {
        const reasonEl = document.getElementById('modalReason');
        const reasonVal = (reasonEl?.value || '').trim();
        const dtChanged = !editingOrigDT
            || sd !== editingOrigDT.sd
            || (ed||sd) !== editingOrigDT.ed
            || isAllDay !== editingOrigDT.allday
            || (!isAllDay && ((st||'').substring(0,5) !== editingOrigDT.st || (et||'').substring(0,5) !== editingOrigDT.et));
        const needsReason = REASON_COLORS.includes(currentColor) && dtChanged;
        if (needsReason && !reasonVal) {
            if (reasonEl) {
                reasonEl.style.borderColor = 'var(--red)';
                reasonEl.scrollIntoView({behavior:'smooth', block:'center'});
                reasonEl.focus();
                setTimeout(()=>{ reasonEl.style.borderColor=''; }, 3000);
            }
            showCalToast('일정(날짜/시간) 변경 사유를 입력해주세요.');
            return;
        }
        if (reasonVal) data.reason = reasonVal;
    }

    const url=editingId?`/api/events/${editingId}`:'/api/events';
    const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(data)});
    if(res.ok){
        const saved=await res.json();
        const hasFiles=Object.values(pendingAttachments).some(arr=>arr.length);
        if(hasFiles) await uploadPendingAttachments(saved.id);
        closeModal();loadEvents();
    }else{
        const err=await res.json();
        const msg = err.message || (err.errors && Object.values(err.errors).flat().join('\n')) || '저장 실패';
        alert(msg);
    }
}

let pendingDeleteId = null;
function deleteEvent(id){
    const delId=id||editingId;
    if(!delId) return;
    pendingDeleteId = delId;
    // 반복 일정이면 일괄 삭제 옵션 노출
    const target=events.find(e=>e.id===delId)||detailEvent;
    const isRepeat=!!(target&&target.repeat_group);
    document.getElementById('delSeriesWrap').style.display=isRepeat?'block':'none';
    document.getElementById('delSeriesChk').checked=false;
    document.getElementById('deleteReasonInput').value = '';
    document.getElementById('deleteReasonOverlay').style.display = 'flex';
    setTimeout(()=>document.getElementById('deleteReasonInput').focus(), 50);
}
async function confirmDeleteEvent(){
    if (!pendingDeleteId) return;
    const inputEl = document.getElementById('deleteReasonInput');
    const reason = (inputEl.value || '').trim();
    if (!reason) {
        inputEl.style.borderColor = 'var(--red)';
        inputEl.focus();
        setTimeout(()=>{ inputEl.style.borderColor=''; }, 3000);
        showCalToast('삭제 사유를 입력해주세요.');
        return;
    }
    const res = await fetch(`/api/events/${pendingDeleteId}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', 'Accept':'application/json'},
        body: JSON.stringify({ reason, scope: document.getElementById('delSeriesChk')?.checked ? 'future' : 'one' }),
    });
    if (res.ok) {
        document.getElementById('deleteReasonOverlay').style.display='none';
        pendingDeleteId = null;
        closeModal();
        loadEvents();
    } else {
        const err = await res.json().catch(()=>({}));
        const msg = err.message || (err.errors && Object.values(err.errors).flat().join('\n')) || '삭제 실패';
        alert(msg);
    }
}

function searchCalAddr(){
    if (typeof isLocked !== 'undefined' && isLocked) return; // 잠금 상태에선 변경 불가
    new daum.Postcode({oncomplete:function(data){
        // 직접 입력 차단 — 검색된 주소(도로명 우선)로 교체
        const addr=data.roadAddress||data.jibunAddress;
        document.getElementById('modalAddress').value=addr;
        document.getElementById('modalLocation').value=addr;
    }}).open();
}
function clearCalAddr(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    document.getElementById('modalAddress').value='';
    document.getElementById('modalLocation').value='';
}
// 이사세팅 출발지 주소 검색/지우기
function searchMoveFrom(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    new daum.Postcode({oncomplete:function(data){
        const addr=data.roadAddress||data.jibunAddress;
        document.getElementById('moveFromAddress').value=addr;
        document.getElementById('moveFromLocation').value=addr;
    }}).open();
}
function clearMoveFrom(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    document.getElementById('moveFromAddress').value='';
    document.getElementById('moveFromLocation').value='';
}
// '출발지 없음' 토글 — 출발지 입력 숨김/비움, 도착지 라벨은 유지
function onMoveNoFromToggle(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    const none=document.getElementById('moveNoFrom')?.checked;
    const wrap=document.getElementById('moveFromInputWrap');
    const note=document.getElementById('moveNoFromNote');
    if(wrap) wrap.style.display=none?'none':'';
    if(note) note.style.display=none?'':'none';
    if(none){ // 입력값 비움
        ['moveFromLocation','moveFromDetail','moveFromAddress'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    }
}
// 의뢰주제=이사세팅 → 출발지 블록 노출 + 기존 주소 라벨을 '도착지'로
function updateMoveSettingUI(){
    const isMove = currentColor==='gold' && getMultiRadio('g_req_topic_group').includes('이사세팅');
    const mb=document.getElementById('moveFromBlock');
    const lbl=document.getElementById('addrBlockLabel');
    if(mb) mb.style.display=isMove?'':'none';
    // 이사세팅이면 항상 '도착지 (이사 후 장소)' — 출발지 유무와 무관
    if(lbl) lbl.textContent=isMove?'도착지 (이사 후 장소)':'장소';
}

// ── 라디오 그룹 초기화 ──
function initAllRadioGroups(){
    // 멀티 선택: 플랫폼, 방송주제
    initRadioGroup('g_platform_group',{multi:true});
    initRadioGroup('g_topic_group',{multi:true});
    // 단일 선택
    ['g_career_group','g_source_group','g_budget_group','g_paid_group','g_order_group','g_delivery_group','g_balance_group'].forEach(id=>initRadioGroup(id));
    initRadioGroup('g_req_topic_group',{multi:true});
    // teal 모드 전환
    initRadioGroup('teal_mode_group',{onChange:v=>{document.getElementById('teal_remote_fields').style.display=v==='remote'?'':'none';document.getElementById('teal_studio_fields').style.display=v==='studio'?'':'none';}});
    // 색상 dot 클릭
    document.querySelectorAll('.color-dot').forEach(dot=>{dot.addEventListener('click',()=>{if(!isLocked) setColor(dot.dataset.color);});});
    // schedEventOpts (멀티 토글)
    document.querySelectorAll('#schedEventOpts .special-opt-btn').forEach(btn=>{btn.addEventListener('click',()=>{if(isLocked)return;btn.classList.toggle('active');if(btn.dataset.seopt==='after')document.getElementById('schedReasonWrap').style.display=btn.classList.contains('active')?'':'none';});});
    // scheduleOpts (단일)
    document.querySelectorAll('#scheduleOpts .sched-opt-btn').forEach(btn=>{btn.addEventListener('click',()=>{if(isLocked)return;const was=btn.classList.contains('active');document.querySelectorAll('#scheduleOpts .sched-opt-btn').forEach(b=>b.classList.remove('active'));if(!was)btn.classList.add('active');});});
    // specialOpts (멀티 토글)
    document.querySelectorAll('#specialOpts .special-opt-btn').forEach(btn=>{btn.addEventListener('click',()=>{if(isLocked)return;btn.classList.toggle('active');});});
    // 잔금 금액 변경 시 배너 업데이트
    document.getElementById('g_balance_amount')?.addEventListener('input',updateBalanceBanner);
}
// init 시 호출
setTimeout(initAllRadioGroups,0);
document.addEventListener('keydown',e=>{if(e.key==='Escape'){
    // 이미지 라이트박스가 열려 있으면 1단계로 라이트박스만 닫음 (전용 핸들러가 처리) — 일정 모달은 유지
    if(document.getElementById('lightbox')?.classList.contains('open')) return;
    closeModal();closeDetail();document.getElementById('historyOverlay').style.display='none';
}});
// 창 크기 변경 시 월간 다일 bar 위치 재계산(px 기반 overlay)
let __calResizeTimer;
window.addEventListener('resize',()=>{
    clearTimeout(__calResizeTimer);
    __calResizeTimer=setTimeout(()=>{ if(currentView==='month') renderMonth(); },150);
});

// 목록(아젠다) 뷰: 좌우 스와이프로 주 이동
(function(){
    const lv=document.getElementById('listView');
    if(!lv) return;
    let sx=0, sy=0, tracking=false;
    lv.addEventListener('touchstart',e=>{
        if(e.touches.length!==1){tracking=false;return;}
        sx=e.touches[0].clientX; sy=e.touches[0].clientY; tracking=true;
    },{passive:true});
    lv.addEventListener('touchend',e=>{
        if(!tracking) return; tracking=false;
        const t=e.changedTouches[0];
        const dx=t.clientX-sx, dy=t.clientY-sy;
        // 가로 스와이프가 충분하고 세로 이동보다 클 때만
        if(Math.abs(dx)>50 && Math.abs(dx)>Math.abs(dy)*1.5){
            moveAgendaWeek(dx<0?1:-1); // 왼쪽으로 밀면 다음 주
        }
    },{passive:true});
})();

// ── 라이트박스 (이미지 뷰어 + 줌/팬) ──
let lightboxImages=[], lightboxIdx=0;
let lbZoom=1, lbPanX=0, lbPanY=0, lbDragging=false, lbStartX=0, lbStartY=0;
const LB_MIN_ZOOM=0.5, LB_MAX_ZOOM=8;

function lbUpdateTransform(){
    const img=document.getElementById('lightboxImg');
    img.style.transform=`translate(${lbPanX}px,${lbPanY}px) scale(${lbZoom})`;
    img.style.transition=lbDragging?'none':'transform 0.15s ease';
    const wrap=document.getElementById('lightboxWrap');
    wrap.classList.toggle('zoomed',lbZoom>1.05);
}
function lbResetZoom(){ lbZoom=1; lbPanX=0; lbPanY=0; lbUpdateTransform(); }
function lbShowZoomInfo(){
    const info=document.getElementById('lightboxZoomInfo');
    info.textContent=Math.round(lbZoom*100)+'%';
    info.classList.add('show');
    clearTimeout(info._t);
    info._t=setTimeout(()=>info.classList.remove('show'),800);
}

// 원본 프리로드 캐시 (한 번 받은 원본은 즉시 표시)
const lbLoaded=new Set();
function lbPreload(idx){
    const it=lightboxImages[idx];
    if(!it||lbLoaded.has(it.src)) return;
    const im=new Image();
    im.onload=()=>lbLoaded.add(it.src);
    im.src=it.src;
}
// 현재 인덱스 표시: 썸네일 먼저(즉시) → 원본 로드되면 교체, 양옆은 미리 로드
function lbShow(){
    const it=lightboxImages[lightboxIdx];
    const img=document.getElementById('lightboxImg');
    document.getElementById('lightboxFilename').textContent=it.filename||'';
    if(lbLoaded.has(it.src) || !it.thumb || it.thumb===it.src){
        img.src=it.src; // 원본이 캐시됐거나 썸네일이 없으면 바로 원본
    } else {
        img.src=it.thumb; // 썸네일 즉시 표시
        const full=new Image();
        const myIdx=lightboxIdx;
        full.onload=()=>{ lbLoaded.add(it.src); if(lightboxIdx===myIdx) img.src=it.src; };
        full.src=it.src;
    }
    // 양옆 프리로드 — 다음 넘김이 즉시 뜨도록
    if(lightboxImages.length>1){
        lbPreload((lightboxIdx+1)%lightboxImages.length);
        lbPreload((lightboxIdx-1+lightboxImages.length)%lightboxImages.length);
    }
}
function openLightbox(src,filename,images,idx){
    lightboxImages=images||[{src,filename}];
    lightboxIdx=idx||0;
    lbResetZoom();
    lbShow();
    document.getElementById('lightbox').classList.add('open');
    document.querySelector('.lightbox-nav.prev').style.display=lightboxImages.length>1?'':'none';
    document.querySelector('.lightbox-nav.next').style.display=lightboxImages.length>1?'':'none';
}
function closeLightbox(){ document.getElementById('lightbox').classList.remove('open'); lbResetZoom(); }
function lightboxNav(dir){
    lightboxIdx=(lightboxIdx+dir+lightboxImages.length)%lightboxImages.length;
    lbResetZoom();
    lbShow();
}

// 휠 줌
document.getElementById('lightbox').addEventListener('wheel',e=>{
    e.preventDefault();
    const delta=e.deltaY>0?-0.15:0.15;
    lbZoom=Math.min(LB_MAX_ZOOM,Math.max(LB_MIN_ZOOM,lbZoom+delta*lbZoom));
    if(lbZoom<1.05){lbPanX=0;lbPanY=0;}
    lbUpdateTransform(); lbShowZoomInfo();
},{passive:false});

// 더블클릭 줌 토글
document.getElementById('lightboxWrap').addEventListener('dblclick',e=>{
    e.preventDefault();
    if(lbZoom>1.05){lbResetZoom();}
    else{lbZoom=3;lbPanX=0;lbPanY=0;lbUpdateTransform();}
    lbShowZoomInfo();
});

// 드래그 팬
document.getElementById('lightboxWrap').addEventListener('mousedown',e=>{
    if(lbZoom<=1.05) return;
    e.preventDefault(); lbDragging=true; lbStartX=e.clientX-lbPanX; lbStartY=e.clientY-lbPanY;
    document.getElementById('lightboxWrap').classList.add('dragging');
});
document.addEventListener('mousemove',e=>{
    if(!lbDragging) return;
    lbPanX=e.clientX-lbStartX; lbPanY=e.clientY-lbStartY; lbUpdateTransform();
});
document.addEventListener('mouseup',()=>{
    if(!lbDragging) return;
    lbDragging=false;
    document.getElementById('lightboxWrap').classList.remove('dragging');
});

// 배경 클릭으로 닫기 (줌 안되어있을 때만)
document.getElementById('lightbox').addEventListener('click',e=>{
    if(e.target===document.getElementById('lightbox')&&lbZoom<=1.05) closeLightbox();
});

// 키보드
document.addEventListener('keydown',e=>{
    if(!document.getElementById('lightbox').classList.contains('open')) return;
    if(e.key==='Escape') closeLightbox();
    if(e.key==='ArrowLeft') lightboxNav(-1);
    if(e.key==='ArrowRight') lightboxNav(1);
    if(e.key==='+'||e.key==='='){lbZoom=Math.min(LB_MAX_ZOOM,lbZoom*1.3);lbUpdateTransform();lbShowZoomInfo();}
    if(e.key==='-'){lbZoom=Math.max(LB_MIN_ZOOM,lbZoom/1.3);if(lbZoom<1.05){lbPanX=0;lbPanY=0;}lbUpdateTransform();lbShowZoomInfo();}
    if(e.key==='0'){lbResetZoom();lbShowZoomInfo();}
});

// 이미지 그리드 클릭 이벤트 위임
document.addEventListener('click',e=>{
    // 요약 뷰 이미지: 요약 내 모든 이미지를 하나의 앨범으로 (prev/next 표시)
    const lsImg=e.target.closest('.ls-img-grid img');
    if(lsImg){
        e.preventDefault();
        const scope=document.getElementById('lockSummary')||document;
        const all=[...scope.querySelectorAll('.ls-img-grid img')].map(i=>({src:i.dataset.full||i.src,thumb:i.src,filename:i.alt||''}));
        const cur=lsImg.dataset.full||lsImg.src;
        openLightbox(cur, lsImg.alt||'', all, Math.max(0, all.findIndex(i=>i.src===cur)));
        return;
    }
    const img=e.target.closest('.img-item img');
    if(!img) return;
    e.preventDefault();
    const grid=img.closest('.img-grid');
    if(!grid) { openLightbox(img.dataset.full||img.src,img.alt||''); return; }
    const allImgs=[...grid.querySelectorAll('.img-item img')].map(i=>({src:i.dataset.full||i.src,thumb:i.src,filename:i.alt||i.closest('.img-item')?.querySelector('.img-filename')?.textContent||''}));
    const idx=[...grid.querySelectorAll('.img-item img')].indexOf(img);
    openLightbox(img.dataset.full||img.src,'',allImgs,idx);
});

// ── 캘린더 메뉴 (백업/내보내기) ──
function toggleCalMenu(){
    const m=document.getElementById('calMenu');
    m.style.display=m.style.display==='none'?'block':'none';
}
document.addEventListener('click',e=>{
    const m=document.getElementById('calMenu');
    if(m&&!e.target.closest('.cal-menu')&&!e.target.closest('[onclick*="toggleCalMenu"]')) m.style.display='none';
});
async function importFile(type, input){
    if(!input.files[0]) return;
    const fd=new FormData();
    fd.append('file',input.files[0]);
    const res=await fetch(`/api/events/import/${type}`,{
        method:'POST',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:fd
    });
    input.value='';
    if(res.ok){ const d=await res.json(); showCalToast(d.message); loadEvents(); }
    else { const d=await res.json().catch(()=>({})); showCalToast(d.error||'가져오기 실패'); }
}

// ── 드래그앤드롭 (데스크탑 월간뷰) ──
let dragEvent=null, dragGhost=null, dragStartDate=null, dragStartX=0, dragStartY=0, isDragging=false;
const DRAG_COLORS={gold:'var(--chip-gold-bg)',teal:'var(--chip-teal-bg)',blue:'var(--chip-blue-bg)',red:'var(--chip-red-bg)',green:'var(--chip-green-bg)',purple:'var(--chip-purple-bg)'};

function dragStart(ev, e){
    if(window.innerWidth<=768||!canEditCalendar||ev.is_locked) return;
    e.preventDefault();
    dragEvent=ev;
    dragStartDate=ev.start_date;
    dragStartX=e.clientX; dragStartY=e.clientY;
    isDragging=false;
}

function dragActivate(e){
    if(isDragging) return;
    isDragging=true;
    dragGhost=document.createElement('div');
    dragGhost.className='drag-ghost';
    dragGhost.style.background=DRAG_COLORS[dragEvent.color]||'var(--accent)';
    dragGhost.style.color='#fff';
    dragGhost.textContent=dragEvent.title||'(제목 없음)';
    dragGhost.style.left=(e.clientX+12)+'px';
    dragGhost.style.top=(e.clientY-12)+'px';
    document.body.appendChild(dragGhost);
    document.body.classList.add('dragging');
}

document.addEventListener('mousemove',e=>{
    if(!dragEvent) return;
    // 5px 이상 움직여야 드래그 활성화
    if(!isDragging){
        if(Math.abs(e.clientX-dragStartX)+Math.abs(e.clientY-dragStartY)<5) return;
        dragActivate(e);
    }
    dragGhost.style.left=(e.clientX+12)+'px';
    dragGhost.style.top=(e.clientY-12)+'px';
    document.querySelectorAll('.day-cell.drop-target').forEach(c=>c.classList.remove('drop-target'));
    const el=document.elementFromPoint(e.clientX,e.clientY);
    const cell=el?.closest('.day-cell[data-date]');
    if(cell) cell.classList.add('drop-target');
});

document.addEventListener('mouseup',async e=>{
    if(!dragEvent) return;
    document.querySelectorAll('.day-cell.drop-target').forEach(c=>c.classList.remove('drop-target'));
    document.body.classList.remove('dragging');
    if(dragGhost){ dragGhost.remove(); dragGhost=null; }
    if(!isDragging){ dragEvent=null; return; } // 드래그 안 했으면 클릭으로 처리
    isDragging=false;
    const el=document.elementFromPoint(e.clientX,e.clientY);
    const cell=el?.closest('.day-cell[data-date]');
    const targetDate=cell?.dataset.date;
    if(!targetDate||targetDate===dragStartDate){ dragEvent=null; return; }
    // 날짜 차이 계산 (다일 이벤트 대응)
    const diff=Math.round((new Date(targetDate+'T00:00:00')-new Date(dragStartDate+'T00:00:00'))/(1000*60*60*24));
    const ev=dragEvent;
    const newStart=shiftDate(ev.start_date, diff);
    const newEnd=ev.end_date?shiftDate(ev.end_date, diff):newStart;
    dragEvent=null;
    // API 호출 — 변경 사유 필수이므로 드래그 이동 사유를 자동 첨부
    const res=await fetch(`/api/events/${ev.id}`,{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({...buildEventPayload(ev), start_date:newStart, end_date:newEnd, reason:`드래그 이동: ${dragStartDate} → ${targetDate}`})
    });
    if(res.ok){ showCalToast('일정이 이동되었습니다'); loadEvents(); }
    else { const er=await res.json().catch(()=>({})); showCalToast(er.message||'이동 실패'); }
});

function shiftDate(dateStr, days){
    const d=new Date(dateStr+'T00:00:00');
    d.setDate(d.getDate()+days);
    return fmt(d); // 로컬 기준 YYYY-MM-DD (toISOString 사용 시 UTC로 하루 밀림)
}

// ── 월간 뷰: 빈 셀 드래그로 기간 선택 → 새 일정(기간) 생성 ──
let rangeSelecting=false, rangeStartDate=null, rangeEndDate=null, rangeMoved=false, suppressCellClick=false;
function calRangeCells(){ return [...document.querySelectorAll('#daysGrid .day-cell[data-date]')]; }
function highlightRange(){
    if(!rangeStartDate||!rangeEndDate) return;
    const [a,b]=[rangeStartDate,rangeEndDate].sort();
    calRangeCells().forEach(c=>{ const d=c.dataset.date; const inR=d>=a && d<=b;
        c.classList.toggle('range-sel', inR);
        c.classList.toggle('range-start', inR && d===a);
        c.classList.toggle('range-end', inR && d===b); });
}
function clearRangeHighlight(){ calRangeCells().forEach(c=>c.classList.remove('range-sel','range-start','range-end')); }
document.addEventListener('mousedown', e=>{
    if(window.innerWidth<=768 || !canEditCalendar || e.button!==0) return;
    if(dragEvent) return; // 일정 이동 중이면 제외
    if(e.target.closest('.event-chip')||e.target.closest('.more-badge')) return; // 칩/더보기 제외
    if(e.target.closest('.mday-title-overlay')) return;
    const cell=e.target.closest('#daysGrid .day-cell[data-date]');
    if(!cell) return;
    rangeSelecting=true; rangeMoved=false;
    rangeStartDate=cell.dataset.date; rangeEndDate=cell.dataset.date;
});
document.addEventListener('mousemove', e=>{
    if(!rangeSelecting) return;
    const cell=document.elementFromPoint(e.clientX,e.clientY)?.closest('#daysGrid .day-cell[data-date]');
    if(!cell) return;
    if(cell.dataset.date!==rangeEndDate){ rangeEndDate=cell.dataset.date; rangeMoved=true; }
    document.body.classList.add('range-dragging');
    highlightRange();
});
document.addEventListener('mouseup', ()=>{
    if(!rangeSelecting) return;
    rangeSelecting=false;
    document.body.classList.remove('range-dragging');
    clearRangeHighlight();
    if(!rangeMoved) return; // 드래그 안 함 → 기존 클릭(단일일) 처리에 맡김
    const [a,b]=[rangeStartDate,rangeEndDate].sort();
    suppressCellClick=true;
    openNewModal(a, null, b);
});

function buildEventPayload(ev){
    return {
        title:ev.title, start_date:ev.start_date, end_date:ev.end_date||ev.start_date,
        start_time:ev.start_time, end_time:ev.end_time, is_all_day:ev.is_allday||ev.is_all_day||false,
        color:ev.color, client_name:ev.client_name||'', address:ev.address||'', location:ev.location||'',
        description:ev.description||'', notif_minutes:ev.notif_minutes||null,
        is_locked:ev.is_locked||false, is_private:ev.is_private||false,
        sched_opt:ev.sched_opt||null, sched_event_opts:ev.sched_event_opts||[],
        special_opts:ev.special_opts||[], sched_after_reason:ev.sched_after_reason||null,
        gold_data:ev.gold_data||null, teal_data:ev.teal_data||null,
        assignees:ev.assignees?ev.assignees.map(a=>a.id||a):[],
    };
}

function showCalToast(msg){
    const t=document.createElement('div');
    t.style.cssText='position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--accent);color:var(--accent-text);padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:9999;';
    t.textContent=msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),2000);
}

// === 휴지통 ===
let trashItems = [];
async function openTrashModal() {
    document.getElementById('trashOverlay').style.display = 'flex';
    document.getElementById('trashBody').innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-muted);">로딩 중...</div>';
    const res = await fetch('/api/events/trashed', {headers:{'Accept':'application/json'}});
    if (!res.ok) { document.getElementById('trashBody').innerHTML = '<div style="padding:30px; text-align:center; color:var(--red);">불러오기 실패</div>'; return; }
    trashItems = await res.json();
    renderTrash();
}
function renderTrash() {
    const body = document.getElementById('trashBody');
    document.getElementById('trashCount').textContent = `${trashItems.length}건`;
    if (!trashItems.length) {
        body.innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">휴지통이 비어 있습니다.</div>';
        document.getElementById('trashSelectAll').checked = false;
        updateTrashButtons();
        return;
    }
    body.innerHTML = trashItems.map(it => `
        <div style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-bottom:1px solid var(--border);">
            <input type="checkbox" class="trash-check" data-id="${it.id}" onchange="updateTrashButtons()">
            <div style="flex:1; min-width:0;">
                <div style="font-size:13px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escHtml(it.title||'(제목 없음)')}</div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${it.start_date}${it.end_date && it.end_date !== it.start_date ? ' ~ '+it.end_date : ''} · ${escHtml(it.client_name||'')} · ${escHtml(it.creator||'-')} 등록 · ${it.deleted_at} 삭제</div>
            </div>
            <button class="nav-btn" style="font-size:11px; width:auto; padding:3px 8px;" onclick="trashRestoreOne(${it.id})">복원</button>
            <button class="nav-btn" style="font-size:11px; width:auto; padding:3px 8px; color:var(--red); border-color:var(--red);" onclick="trashForceOne(${it.id})">영구삭제</button>
        </div>
    `).join('');
    document.getElementById('trashSelectAll').checked = false;
    updateTrashButtons();
}
function trashToggleAll(checked) {
    document.querySelectorAll('.trash-check').forEach(c => c.checked = checked);
    updateTrashButtons();
}
function updateTrashButtons() {
    const ids = [...document.querySelectorAll('.trash-check:checked')].map(c => +c.dataset.id);
    document.getElementById('trashRestoreBtn').disabled = !ids.length;
    document.getElementById('trashClearBtn').disabled = !ids.length;
}
function getTrashSelectedIds() {
    return [...document.querySelectorAll('.trash-check:checked')].map(c => +c.dataset.id);
}
async function trashRestoreOne(id) {
    const res = await fetch(`/api/events/${id}/restore`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    if (!res.ok) { showCalToast('복원 실패'); return; }
    showCalToast('복원되었습니다');
    openTrashModal();
    loadEvents();
}
async function trashRestoreSelected() {
    const ids = getTrashSelectedIds();
    if (!ids.length || !confirm(`${ids.length}건을 복원합니다. 계속할까요?`)) return;
    for (const id of ids) {
        await fetch(`/api/events/${id}/restore`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    }
    showCalToast(`${ids.length}건 복원 완료`);
    openTrashModal();
    loadEvents();
}
async function trashForceOne(id) {
    if (!confirm('이 일정을 영구 삭제합니다. 복구할 수 없습니다. 계속할까요?')) return;
    const res = await fetch(`/api/events/${id}/force`, {method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    if (!res.ok) { showCalToast('삭제 실패'); return; }
    showCalToast('영구 삭제됨');
    openTrashModal();
}
async function trashEmptySelected() {
    const ids = getTrashSelectedIds();
    if (!ids.length || !confirm(`선택한 ${ids.length}건을 영구 삭제합니다. 복구할 수 없습니다. 계속할까요?`)) return;
    const res = await fetch('/api/events/trash/empty', {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', 'Accept':'application/json'}, body:JSON.stringify({ids})});
    if (!res.ok) { showCalToast('실패'); return; }
    showCalToast(`${ids.length}건 영구 삭제 완료`);
    openTrashModal();
}
async function trashEmptyAll() {
    if (!confirm('휴지통 전체를 비웁니다. 모든 항목이 영구 삭제되며 복구할 수 없습니다. 계속할까요?')) return;
    const res = await fetch('/api/events/trash/empty', {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', 'Accept':'application/json'}, body:JSON.stringify({})});
    if (!res.ok) { showCalToast('실패'); return; }
    showCalToast('휴지통이 비워졌습니다');
    openTrashModal();
}
function escHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// === 일정 완료 토글 ===
async function toggleCompleteFromDetail() {
    if (!detailEvent) return;
    const id = detailEvent.id;
    const isCompleted = !!detailEvent.completed_at;
    const url = `/api/events/${id}/${isCompleted ? 'uncomplete' : 'complete'}`;
    const res = await fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    if (!res.ok) { showCalToast('실패'); return; }
    showCalToast(isCompleted ? '완료 해제' : '완료 처리');
    viewMode=false; closeModal(); detailEvent=null; // 보기/편집 모드 공통으로 편집 모달 닫기
    loadEvents();
}

// ── 상세 내용 자동 높이(최대 400px, 초과 시 스크롤) ──
const CAL_AUTOGROW_IDS = ['commonDesc', 'commonHandoverNote', 't_desc', 't_remote_content', 't_studio_content', 'g_req_detail', 'g_special'];
function calAutoGrow(el) {
    if (!el) return;
    el.style.height = 'auto';
    const h = Math.min(el.scrollHeight, 400);
    el.style.height = h + 'px';
    el.style.overflowY = el.scrollHeight > 400 ? 'auto' : 'hidden';
}
function calRefreshAutoGrow() { CAL_AUTOGROW_IDS.forEach(id => calAutoGrow(document.getElementById(id))); }
CAL_AUTOGROW_IDS.forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.classList.add('autogrow'); el.addEventListener('input', () => calAutoGrow(el)); }
});

renderVisitOpts();
init();
</script>
@endpush
