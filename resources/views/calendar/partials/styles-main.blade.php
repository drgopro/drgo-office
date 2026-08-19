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
    .cal-title-xl { font-size:19px; font-weight:800; letter-spacing:-0.01em; margin-right:2px; }
    .cal-header-right { flex-wrap:wrap; }
    /* 헤더 버튼: 절대 줄바꿈/찌그러짐 없이 한 줄 유지, 공간 부족 시 그룹 단위로 다음 줄로 래핑 */
    .cal-header .nav-btn, .cal-header .add-btn, .cal-header .view-toggle-btn, .cal-header .month-label { white-space:nowrap; flex-shrink:0; }
    .view-toggle-group { flex-shrink:0; }
    .app-title { font-size:13px; letter-spacing:0.2em; color:var(--accent); text-transform:uppercase; }
    .nav-btn { background:none; border:1px solid var(--border); color:var(--text-muted); cursor:pointer; width:32px; height:32px; border-radius:6px; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); }
    @keyframes calSpin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
    .month-label { font-size:18px; font-weight:500; letter-spacing:0.05em; min-width:180px; text-align:center; }

    /* 일정 검색 */
    .cal-search-wrap { position:relative; flex-shrink:1; min-width:0; }
    .cal-search-input { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:7px 26px 7px 12px; color:var(--text); font-size:12px; outline:none; width:170px; max-width:100%; transition:border-color .15s, width .2s; }
    /* 검색어 전체 지우기 ✕ — 내용이 있을 때만 표시 */
    .cal-search-clear { display:none; position:absolute; right:6px; top:50%; transform:translateY(-50%); width:18px; height:18px; border:none; border-radius:50%; background:var(--border); color:var(--text); font-size:10px; line-height:1; cursor:pointer; align-items:center; justify-content:center; padding:0; }
    .cal-search-clear.show { display:inline-flex; }
    .cal-search-clear:hover { background:var(--text-muted); color:var(--surface); }
    .cal-search-input:focus { border-color:var(--accent); width:220px; }
    .cal-search-results { position:absolute; top:calc(100% + 6px); right:0; width:340px; max-width:calc(100vw - 24px); max-height:420px; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:var(--card-shadow, 0 8px 32px rgba(0,0,0,0.3)); z-index:60; padding:6px; }
    .cal-sr-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; cursor:pointer; }
    .cal-sr-item:hover { background:var(--surface2); }
    .cal-sr-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .cal-sr-date { font-size:11px; color:var(--text-muted); flex-shrink:0; min-width:74px; font-family:ui-monospace,Menlo,monospace; }
    .cal-sr-title { font-size:12px; font-weight:600; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cal-sr-item.is-completed .cal-sr-title { opacity:0.55; } /* 완료 — 취소선 없이 흐리게 */
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
    .mw-stepper { display:inline-flex; align-items:center; gap:2px; border:1px solid var(--border); border-radius:8px; background:var(--surface); flex-shrink:0; }
    .mw-stepper button { border:none; background:none; color:var(--text-muted); font-size:14px; padding:4px 8px; cursor:pointer; line-height:1; }
    .mw-stepper button:hover { color:var(--accent); }
    .mw-stepper #mwLabel { font-size:12px; color:var(--text); min-width:44px; text-align:center; font-weight:600; }
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
    @supports (height: 100dvh) { .day-popover { max-height:60dvh; } }
    .day-popover .dp-header { display:flex; justify-content:space-between; align-items:center; font-size:14px; font-weight:700; margin-bottom:10px; position:sticky; top:0; background:var(--surface); }
    .day-popover .dp-close { background:none; border:none; color:var(--text-muted); font-size:16px; cursor:pointer; padding:0 4px; }
    .day-popover .dp-list { display:flex; flex-direction:column; gap:6px; }
    .day-popover .dp-item { display:flex; align-items:stretch; gap:10px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; cursor:pointer; transition:background 0.12s; }
    .day-popover .dp-item:hover { background:var(--surface2); }
    /* 카테고리 색상 — 서클 대신 세로 컬러 바 */
    .day-popover .dp-dot { width:4px; min-height:100%; border-radius:2px; flex-shrink:0; align-self:stretch; }
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
    /* 완료 일정은 취소선 없이 흐리게만 (위 opacity 0.4 규칙으로 충분) */
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
    /* ── 일별 리스트(mde) 스타일 — 모바일 하단 리스트/시트 + 데스크탑 컴팩트 하단 리스트 공용 ── */
    .mobile-day-events .mde-header { font-size:13px; font-weight:600; color:var(--accent); margin-bottom:10px; }
    .mobile-day-events .mde-item { display:flex; align-items:flex-start; gap:10px; padding:12px 4px; border:none; border-bottom:1px solid var(--border); border-radius:0; margin-bottom:0; cursor:pointer; transition:background 0.15s; min-height:44px; }
    .mobile-day-events .mde-item:last-child { border-bottom:none; }
    .mobile-day-events .mde-item:hover { background:var(--surface2); }
    .mobile-day-events .mde-time { width:42px; flex-shrink:0; font-size:12px; font-weight:700; color:var(--text-muted); padding-top:1px; }
    .mobile-day-events .mde-bar { width:4px; align-self:stretch; min-height:30px; border-radius:2px; flex-shrink:0; }
    .mobile-day-events .mde-info { flex:1; min-width:0; }
    .mobile-day-events .mde-title-row { display:flex; align-items:flex-start; gap:8px; }
    /* 긴 제목이 잘리지 않도록 폰트 축소 + 3줄까지 줄바꿈 허용 */
    .mobile-day-events .mde-title { font-size:12px; font-weight:600; flex:1; min-width:0; overflow:hidden; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; word-break:break-all; line-height:1.45; }
    .mobile-day-events .mde-assignee { flex-shrink:0; font-size:11px; font-weight:600; color:var(--text-muted); background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:1px 8px; max-width:45%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mobile-day-events .mde-meta { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .mobile-day-events .mde-empty { text-align:center; padding:20px; color:var(--text-muted); font-size:13px; }

    /* ── 컴팩트 월간 뷰 (네이버식 고밀도) ── */
    #monthCompactView { border:1px solid var(--border); border-radius:10px; overflow:hidden; background:var(--surface); }
    /* 데스크탑 컴팩트: 그리드 아래 상시 선택일 리스트 — 높이는 드래그 핸들로 조절 */
    #mcDeskList { display:none; border:1px solid var(--border); border-radius:10px; background:var(--surface); padding:12px 16px; overflow-y:auto; }
    #mcListResizer { display:none; height:14px; align-items:center; justify-content:center; cursor:row-resize; user-select:none; }
    #mcListResizer::before { content:''; width:48px; height:4px; border-radius:2px; background:var(--border); transition:background .15s; }
    #mcListResizer:hover::before { background:var(--text-muted); }
    .mc-weekdays { display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid var(--border); background:var(--surface2); }
    .mc-weekdays span { text-align:center; font-size:11px; font-weight:700; color:var(--text-muted); padding:5px 0; }
    .mc-weekdays span:first-child { color:var(--red); }
    .mc-week { position:relative; display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid var(--border); }
    .mc-week:last-child { border-bottom:none; }
    .mc-cell { min-width:0; padding:1px 2px 2px; border-right:1px solid var(--border); overflow:hidden; display:flex; flex-direction:column; gap:1px; cursor:pointer; }
    .mc-cell:last-child { border-right:none; }
    .mc-cell.other-month { opacity:0.45; }
    .mc-cell.today { background:color-mix(in srgb, var(--accent) 7%, transparent); }
    .mc-cell.today .mc-daynum { background:var(--accent); color:var(--accent-text); border-radius:50%; }
    .mc-daynum-row { display:flex; align-items:center; gap:3px; height:19px; flex-shrink:0; min-width:0; }
    .mc-daynum { font-size:11px; font-weight:600; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .mc-daynum.sun { color:var(--red); } .mc-daynum.sat { color:#5b8def; }
    .mc-holiday { font-size:9px; color:var(--red); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
    /* 바 레인 자리 스페이서 + 레인에 삽입된 칩 — 높이 16 + 셀 gap 1 = 바 피치(17px)와 일치해야 함 */
    .mc-slot { height:16px; flex-shrink:0; }
    .mc-chip.in-lane { height:16px; line-height:14px; }
    .mc-chip { height:16px; line-height:14px; font-size:10.5px; padding:0 3px 0 4px; border-left:3px solid var(--accent); background:var(--chip-single-bg); color:var(--text); border-radius:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer; flex-shrink:0; }
    .mc-chip:hover { filter:brightness(1.15); }
    .mc-chip .mc-time { display:inline-block; min-width:29px; opacity:0.7; font-size:9.5px; margin-right:2px; font-variant-numeric:tabular-nums; }
    .mc-chip.is-completed, .mc-bar.is-completed { opacity:0.4; }
    .mc-more { font-size:10px; font-weight:700; color:var(--text-muted); cursor:pointer; padding:0 4px; flex-shrink:0; line-height:14px; }
    .mc-more:hover { color:var(--accent); }
    .mc-bar { position:absolute; z-index:4; height:15px; line-height:13px; font-size:10px; font-weight:600; padding:0 5px 0 4px; border-radius:3px; border-left:3px solid var(--accent); background:var(--chip-single-bg); color:var(--text); white-space:nowrap; overflow:visible; cursor:pointer; box-sizing:border-box; }
    {{-- 라벨(직계 span)만 블록 처리 — 하위 span(확정 칩 등)까지 block이 되면 칩이 바 폭만큼 늘어짐 --}}
    .mc-bar > span { display:block; overflow:hidden; text-overflow:ellipsis; }
    .mc-bar .opt-chip.sched-end { font-size:9px; line-height:1.3; margin-left:3px; padding:0 3px; }
    {{-- 월간 뷰 단일 칩과 동일한 연한 틴트 (진한 배경은 작은 글씨 가독성 저하) --}}
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .mc-bar.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 22%, transparent); border-left-color:var(--chip-{{ $__ck }}-bg); }
    .mc-chip.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 22%, transparent); border-left-color:var(--chip-{{ $__ck }}-bg); }
    @endforeach
    @media (max-width: 768px) {
        #monthCompactView { margin-bottom:58px; } /* 하단 시트 바에 마지막 주가 가리지 않도록 */
        .mc-chip { height:18px; line-height:16px; font-size:10px; padding-left:3px; border-left-width:2px; }
        .mc-bar { font-size:9.5px; }
        .mc-daynum { font-size:10px; }
        .mc-holiday { display:none; }
    }
    /* 컴팩트 뷰 모바일 하단 시트 — 바를 올리면 선택일 리스트 */
    .mc-backdrop { display:none; position:fixed; inset:0; z-index:59; background:rgba(0,0,0,0.35); }
    .mc-backdrop.show { display:block; }
    .mc-sheet { display:none; position:fixed; left:0; right:0; bottom:0; z-index:60; background:var(--surface); border-top:1px solid var(--border); border-radius:16px 16px 0 0; box-shadow:0 -6px 24px rgba(0,0,0,0.28); height:62vh; height:62dvh; transform:translateY(calc(100% - 50px)); transition:transform .25s ease; }
    .mc-sheet.open { transform:translateY(0); }
    .mc-sheet-handle { height:50px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; cursor:pointer; touch-action:none; user-select:none; }
    .mc-sheet-grip { width:38px; height:4px; border-radius:2px; background:var(--border); }
    #mcSheetLabel { font-size:12.5px; font-weight:700; color:var(--text); }
    .mc-sheet-body { overflow-y:auto; height:calc(100% - 50px); padding:0 12px calc(20px + env(safe-area-inset-bottom)); -webkit-overflow-scrolling:touch; }
    .mc-sheet-body .mobile-day-events { border-top:none; padding:0; background:none; }
    /* 선택일: 테두리 대신 날짜 숫자만 강조 (오늘과 동일한 원형 배지) */
    .mc-cell.selected .mc-daynum { background:var(--accent); color:var(--accent-text); border-radius:50%; }
    @media (min-width: 769px) { .mc-sheet { display:none !important; } }

    .chip-time { font-size:calc(12px * var(--cal-fz,1)); opacity:0.85; flex-shrink:0; margin-right:3px; }
    .chip-special { font-size:calc(11px * var(--cal-fz,1)); flex-shrink:0; letter-spacing:1px; }
    /* 배송 상태 아이콘 (✕ 미배송 / △ 일부 완료 / ○ 전부 완료) */
    .chip-ship { flex-shrink:0; font-size:calc(11px * var(--cal-fz,1)); font-weight:800; line-height:1; }
    /* 옵션 아이콘 (주/일간·목록·팝업 공용) */
    .ev-opt-ico { flex-shrink:0; font-size:calc(11px * var(--cal-fz,1)); margin-left:4px; letter-spacing:1px; }
    .opt-chip { display:inline-flex; align-items:center; flex-shrink:0; font-size:calc(8px * var(--cal-fz,1)); font-weight:700; padding:0 3px; border-radius:4px; background:rgba(127,127,127,0.16); border:1px solid rgba(127,127,127,0.28); color:inherit; margin-left:2px; line-height:1.5; vertical-align:middle; }
    .opt-chip.accent { background:color-mix(in srgb, var(--accent) 16%, transparent); border-color:color-mix(in srgb, var(--accent) 40%, transparent); }
    .opt-chip.urgent { background:#ef444426; border-color:#ef444466; color:#e06c6c; }
    /* 확정 칩 — 연한 틴트 대신 진한 배경으로 눈에 띄게 */
    .opt-chip.confirmed { background:#2f9e44; border-color:#2f9e44; color:#fff; font-weight:800; }
    /* 리스트 계열 뷰: 옵션 칩을 타이틀 앞에 배치 — 폰트 축소 + 여백 방향 전환 */
    .agenda-title .opt-chip, .dp-title .opt-chip, .mde-title .opt-chip { font-size:8px; margin-left:0; margin-right:3px; }
    /* 특수 옵션 아이콘 (차량🚗·제품💼 등) — 제목 앞 */
    .opt-ic { font-size:12px; margin-right:2px; line-height:1; }
    .opt-ic-arrow { color:var(--accent); font-weight:800; } /* 시기 요청 ←/→ 화살표 강조 */
    /* 확정 상태 한 글자 칩 — 제목 끝, 가시성 위해 다른 칩보다 크게 */
    .opt-chip.sched-end, .agenda-title .opt-chip.sched-end, .dp-title .opt-chip.sched-end, .mde-title .opt-chip.sched-end { margin-left:4px; margin-right:0; font-size:calc(11px * var(--cal-fz,1)); padding:0 4px; line-height:1.4; }

    /* ── 세팅 항목 표시 (연결 프로젝트 의뢰 내용 불러오기 — 읽기 전용) ── */
    .rqv-title { font-weight:700; font-size:13px; margin-top:6px; }
    .rqv-cat { font-weight:700; font-size:12px; margin:7px 0 2px 7px; }
    .rqv-item { color:var(--text-muted); font-size:12px; margin-left:15px; line-height:2; }
    .rqv-src { font-size:10.5px; color:var(--text-muted); margin-top:7px; }
    /* 주간 시간대 이벤트 첫 줄: flex로 옵션 칩·제목·배송 아이콘 수직 중앙 정렬 */
    .tl-ev-head { display:flex; align-items:center; gap:3px; min-width:0; }
    .tl-ev-head .opt-chip { margin-left:0; }
    .tl-ev-title { font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
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
    .ship-item .ship-loc { font-size:11px; font-weight:700; color:var(--text-muted); background:var(--surface2); border-radius:999px; padding:2px 8px; flex-shrink:0; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
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
    /* ── 캘린더 사이드 필터 (미니멀 접이식, 데스크탑 전용) ── */
    #calBody { display:flex; align-items:stretch; gap:8px; }
    @media (min-width:1025px) { #calMain .calendar-wrap { padding-left:10px; } }
    #calMain { flex:1; min-width:0; }
    #calSide { width:230px; flex-shrink:0; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px; transition:width 0.15s ease, padding 0.15s ease; }
    #calSideSticky { position:sticky; top:10px; }
    /* 접힘: 슬림 레일 — 토글 버튼 + 카테고리 점 (점 클릭으로 필터 토글 가능) */
    #calSide.collapsed { width:36px; padding:8px 5px; }
    #calSide.collapsed #calSideBody { display:none; }
    #csRail { display:none; }
    #calSide.collapsed #csRail { display:flex; flex-direction:column; align-items:center; gap:9px; margin-top:12px; }
    #csRail .cs-dot { width:10px; height:10px; cursor:pointer; }
    #csRail .cs-dot.off { opacity:0.25; }
    #csRail .cs-dot:hover { transform:scale(1.25); }
    .cs-collapse-btn { display:block; width:22px; height:22px; border-radius:6px; border:1px solid var(--border); background:var(--surface2); color:var(--text-muted); cursor:pointer; font-size:11px; line-height:1; padding:0; }
    .cs-collapse-btn:hover { color:var(--text); border-color:var(--text-muted); }
    #csRail .cs-collapse-btn { margin:0 auto 2px; width:24px; height:24px; border-radius:8px; }
    /* 필터 접힘 시 펼치기 버튼 — 스크롤 위치와 무관하게 브라우저 하단 좌측에 고정 */
    @media (min-width:1025px) {
        #calSide.collapsed #csRail .cs-collapse-btn { position:fixed; left:12px; bottom:18px; z-index:130; width:36px; height:36px; border-radius:10px; font-size:15px; background:var(--surface); box-shadow:0 4px 14px rgba(0,0,0,0.35); }
        #calSide.collapsed #csRail .cs-collapse-btn:hover { border-color:var(--accent); color:var(--accent); }
    }
    #filterBar { display:none; } /* 카테고리/담당자 필터는 사이드 패널로 일원화 */
    #calSideFab { display:none; position:fixed; left:14px; bottom:16px; z-index:601; width:40px; height:40px; border-radius:50%; border:1px solid var(--border); background:var(--surface); color:var(--text); align-items:center; justify-content:center; box-shadow:0 4px 14px rgba(0,0,0,0.25); cursor:pointer; opacity:0.9; }
    #calSideFab:hover { opacity:1; }
    #calSideFab svg { width:19px; height:19px; stroke:currentColor; stroke-width:2; fill:none; stroke-linecap:round; stroke-linejoin:round; }
    @media (max-width:1024px) {
        /* 저해상도: 기본 숨김, 버튼으로 리모컨처럼 띄움 */
        #calSide { display:none; }
        #calSideFab { display:flex; }
        /* 좌측에 붙어 세로 중앙을 따라다니는 리모컨 */
        #calSide.mobile-open { display:block; position:fixed; left:0; top:50%; bottom:auto; transform:translateY(-50%); width:min(300px, 82vw); max-height:min(80vh, 640px); overflow-y:auto; z-index:600; box-shadow:8px 0 32px rgba(0,0,0,0.3); margin-top:0 !important; height:auto !important; border-radius:0 16px 16px 0; animation:csDrawerIn .18s ease-out; }
        @keyframes csDrawerIn { from { transform:translate(-100%, -50%); } to { transform:translate(0, -50%); } }
        #calSide.mobile-open .cs-collapse-btn, #calSide.mobile-open #csRail { display:none; }
        #calSide.mobile-open #calSideBody { display:block; }
    }
    #calSideBackdrop { display:none; position:fixed; inset:0; z-index:599; background:rgba(0,0,0,0.25); }
    #calSideBackdrop.show { display:block; }
    .cs-mini-head { display:flex; justify-content:space-between; align-items:center; font-size:12.5px; font-weight:700; margin-bottom:8px; color:var(--text); }
    .cs-mini-nav { display:flex; gap:4px; }
    .cs-mini-nav button { background:none; border:1px solid var(--border); border-radius:6px; width:22px; height:22px; color:var(--text-muted); cursor:pointer; font-size:12px; line-height:1; }
    .cs-mini-nav button:hover { color:var(--text); border-color:var(--text-muted); }
    .cs-mini-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px 0; text-align:center; }
    .cs-mini-dow { font-size:10px; color:var(--text-muted); margin-bottom:3px; }
    .cs-mini-day { font-size:11px; padding:4px 0; border-radius:7px; cursor:pointer; color:var(--text); }
    .cs-mini-day:hover { background:var(--surface2); }
    .cs-mini-day.dim { color:var(--text-muted); opacity:0.4; }
    .cs-mini-day.sel { box-shadow:inset 0 0 0 1.5px var(--accent); }
    /* 주간 선택: 한 줄 연속 밴드 */
    .cs-mini-day.selr { background:color-mix(in srgb, var(--accent) 14%, transparent); border-radius:0; }
    .cs-mini-day.selr.sel-start { border-radius:7px 0 0 7px; }
    .cs-mini-day.selr.sel-end { border-radius:0 7px 7px 0; }
    .cs-mini-day.selr.today, .cs-mini-day.today { background:var(--accent); border-radius:7px; }
    .cs-mini-day.today { background:var(--accent); color:var(--accent-text,#fff); font-weight:700; }
    [data-theme="light"] .cs-mini-day.today { color:#fff; }
    .cs-divider { height:1px; background:var(--border); margin:12px 0; }
    .cs-sec-title { font-size:11px; color:var(--text-muted); font-weight:700; margin-bottom:6px; letter-spacing:0.03em; }
    .cs-cat { display:flex; align-items:center; gap:9px; padding:6px 8px; border-radius:8px; font-size:12.5px; color:var(--text); user-select:none; }
    .cs-cat:hover { background:var(--surface2); }
    .cs-cat.off .cs-cat-label { opacity:0.45; }
    .cs-cat .cs-cat-label { cursor:pointer; }
    .cs-cat .cs-cat-label:hover { color:var(--accent); }
    /* 카테고리 체크박스 — 켜짐: 카테고리 색 채움 + 흰 체크 / 꺼짐: 테두리만. 클릭=토글 */
    .cs-check { flex-shrink:0; width:17px; height:17px; border-radius:5px; border:1.5px solid var(--cat-c,#bbb); background:var(--surface); display:inline-flex; align-items:center; justify-content:center; transition:all 0.12s; cursor:pointer; }
    .cs-check svg { width:11px; height:11px; fill:none; stroke:#fff; stroke-width:3.2; stroke-linecap:round; stroke-linejoin:round; opacity:0; }
    .cs-check.on { background:var(--cat-c); border-color:var(--cat-c); }
    .cs-check.on svg { opacity:1; }
    .cs-cat.off .cs-check { opacity:0.7; }
    .cs-cat .cs-cat-label { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    /* '만 보기' — 행 hover 시 우측에 표시, 클릭하면 해당 카테고리만 켜기 (재클릭 시 전체 복원) */
    /* (만 보기 버튼 제거 — 라벨 클릭이 단독 보기 역할) */

    /* ── 삭제/변경 흔적 고스트 칩 — 카테고리 색·폰트는 일반 칩과 동일, 빨간 네온 테두리 + 취소선으로 구분 ── */
    .cs-ghost-row { border-top:1px dashed var(--border); margin-top:6px; padding-top:8px; }
    .day-cell .event-chip.ghost-chip { border:1.5px solid #ff4d4f !important; box-shadow:0 0 6px rgba(255,77,79,0.55); cursor:pointer; pointer-events:auto; }
    .day-cell .event-chip.ghost-chip .chip-title { text-decoration:line-through; }
    .day-cell .event-chip.ghost-chip:hover { box-shadow:0 0 10px rgba(255,77,79,0.85); }
    .cs-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
    #calSide .assignee-filter { width:100%; margin-bottom:6px; box-sizing:border-box; }
    #calSide .assignee-filter-chips { width:100%; }

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
    /* 주간 종일 칩: flex 유지로 옵션 칩 수직 중앙 정렬 (말줄임은 chip-title이 담당) */
    .tl-allday-cell .event-chip { display:flex; align-items:center; gap:3px; width:100%; overflow:hidden; height:auto; min-height:18px; line-height:1.4; }

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
    /* overflow-y:auto + wrapper margin:auto — 모달이 화면보다 커져도 상/하단이 잘리지 않고 오버레이 스크롤로 접근 가능 */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.72); z-index:200; backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:20px; overflow-y:auto; }
    .modal-overlay.open { display:flex; }
    .modal-wrapper { position:relative; display:flex; align-items:flex-start; gap:8px; max-height:92vh; margin:auto 0; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:660px; max-height:92vh; overflow-y:auto; animation:modalIn 0.22s ease; }
    /* 모바일 주소창 등으로 100vh가 실제 가시 영역보다 큰 브라우저 대응 — 동적 뷰포트 기준 상한 */
    @supports (height: 100dvh) {
        .modal-wrapper, .modal { max-height: calc(100dvh - 40px); }
    }
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
    /* 보기 모드: 배지 클릭으로 카테고리 빠른 변경 */
    body .type-badge.quick-editable { cursor:pointer; }
    .type-badge.quick-editable:hover { filter:brightness(1.2); box-shadow:0 0 0 2px color-mix(in srgb, currentColor 25%, transparent); }
    .cat-quick-pick { display:flex; flex-wrap:wrap; gap:6px; margin:6px 0 2px; padding:8px; background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.25); }
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
    .sched-opt-btn.active[data-sopt="confirmed"] { border-color:#2f9e44; background:rgba(47,158,68,0.20); color:#2f9e44; box-shadow:0 0 0 2px rgba(47,158,68,0.20); font-weight:700; }
    .sched-opt-sub { font-size:11px; color:var(--text-muted); margin:2px 0 6px; }

    /* ══ 2a 리디자인: 일정 모달 — 웜 뉴트럴 + 네이비 포인트, 섹션 카드, 작성 현황 레일 ══ */
    #modalOverlay { --m-accent:#3A5683; --m-ink:#26251f; --m-muted:#8a887f; --m-card:#ffffff; --m-border:#e7e5e0; --m-soft:#faf9f7; }
    #modalOverlay .modal { max-width:980px; background:#f4f2ee; }
    #modalOverlay .modal-header { background:var(--m-card); border-bottom:1px solid var(--m-border); padding:18px 28px 14px; }
    #modalOverlay .modal-footer { background:var(--m-card); border-top:1px solid var(--m-border); padding:14px 28px 18px; margin-top:6px; }
    #modalOverlay .modal-body { display:grid; grid-template-columns:minmax(0,1fr) 208px; gap:14px; align-items:start; counter-reset:msec; }
    #modalOverlay .modal-body.is-locked { display:flex; flex-direction:column; }
    #modalOverlay .m-main { display:flex; flex-direction:column; gap:14px; min-width:0; }
    /* 섹션 카드 — 흰 배경 + 번호 카운터 */
    #modalOverlay .m-main > .field-section, #modalOverlay .m-main > .datetime-section,
    #modalOverlay .m-main > #generalAttachSection, #modalOverlay .m-main .m-card {
        background:var(--m-card); border:1px solid var(--m-border); border-radius:14px; padding:18px 20px;
        counter-increment:msec; display:flex; flex-direction:column; gap:12px;
    }
    #modalOverlay .m-main .section-heading { font-size:13.5px; font-weight:800; color:var(--m-ink); text-transform:none; letter-spacing:-0.2px; }
    #modalOverlay .m-main .section-heading::after { display:none; }
    #modalOverlay .m-main .section-heading::before {
        content:counter(msec, decimal-leading-zero); width:24px; height:24px; border-radius:8px; flex:none;
        background:color-mix(in srgb, var(--m-accent) 10%, transparent); color:var(--m-accent);
        display:inline-flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:800; letter-spacing:0;
    }
    /* 칩 — pill + 네이비 필 (카테고리 칩·확정상태 단계색은 예외 유지) */
    #modalOverlay .radio-btn { border-radius:999px; background:var(--m-soft); border-color:#dcdad5; color:#55544e; }
    #modalOverlay .radio-btn:hover { border-color:var(--m-accent); color:var(--m-accent); }
    #modalOverlay .radio-btn.active { background:var(--m-accent); border-color:var(--m-accent); color:#fff; }
    #modalOverlay .radio-btn.active-red { background:var(--red); border-color:var(--red); color:#fff; }
    #modalOverlay .radio-btn.active-green { background:var(--green); border-color:var(--green); color:#111; }
    #modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn { border-radius:999px; background:var(--m-soft); border-color:#dcdad5; color:#55544e; }
    #modalOverlay .special-opt-btn.active { border-color:var(--m-accent); background:color-mix(in srgb, var(--m-accent) 10%, transparent); color:var(--m-accent); box-shadow:0 0 0 2px color-mix(in srgb, var(--m-accent) 14%, transparent); }
    #modalOverlay .btn-save-top, #modalOverlay .btn-save, .modal-external-action { background:var(--m-accent); color:#fff; }
    #modalOverlay .field-section .field-label { color:var(--m-accent); }
    /* 우측 작성 현황 레일 */
    #modalOverlay .m-rail { position:sticky; top:0; display:flex; flex-direction:column; gap:10px; }
    #modalOverlay .m-rail-card { background:var(--m-card); border:1px solid var(--m-border); border-radius:14px; padding:16px 16px 12px; display:flex; flex-direction:column; gap:10px; }
    #modalOverlay .m-rail-title { font-weight:700; font-size:11px; color:var(--m-muted); letter-spacing:0.6px; }
    #modalOverlay .m-rail-pct { display:flex; align-items:baseline; gap:8px; }
    #modalOverlay .m-rail-pct b { font-weight:800; font-size:26px; color:var(--m-ink); letter-spacing:-0.5px; }
    #modalOverlay .m-rail-cnt { font-size:11.5px; color:#a8a69e; }
    #modalOverlay .m-rail-bar { height:6px; background:#f0eee9; border-radius:999px; overflow:hidden; }
    #modalOverlay .m-rail-bar > div { height:100%; background:var(--m-accent); border-radius:999px; width:0; transition:width .25s; }
    #modalOverlay .m-rail-nav { display:flex; flex-direction:column; border-top:1px solid #f0eee9; padding-top:6px; }
    #modalOverlay .m-rail-item { display:flex; align-items:center; gap:8px; padding:6px 4px; border-radius:8px; text-decoration:none; cursor:pointer; }
    #modalOverlay .m-rail-item:hover { background:var(--m-soft); }
    #modalOverlay .m-rail-dot { width:20px; height:20px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:9.5px; font-weight:800; flex:none; background:#f0eee9; color:#a8a69e; }
    #modalOverlay .m-rail-item.part .m-rail-dot { background:color-mix(in srgb, var(--m-accent) 12%, transparent); color:var(--m-accent); }
    #modalOverlay .m-rail-item.done .m-rail-dot { background:var(--m-accent); color:#fff; }
    #modalOverlay .m-rail-label { flex:1; font-size:12px; color:#55544e; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    #modalOverlay .m-rail-item.done .m-rail-label { color:#a8a69e; }
    #modalOverlay .m-rail-count { font-size:10.5px; color:#a8a69e; flex:none; }
    #modalOverlay .m-rail-remaining { background:color-mix(in srgb, var(--m-accent) 5%, #fff); border:1px solid color-mix(in srgb, var(--m-accent) 14%, #fff); border-radius:14px; padding:12px 14px; display:flex; flex-direction:column; gap:8px; }
    #modalOverlay .m-rail-rem-title { font-weight:700; font-size:11px; color:var(--m-accent); letter-spacing:0.4px; }
    #modalOverlay .m-rail-rem-chips { display:flex; flex-wrap:wrap; gap:5px; }
    #modalOverlay .m-rail-rem-chip { padding:4px 9px; border-radius:999px; background:#fff; border:1px solid #e0dfda; font-size:11px; color:#55544e; }
    #modalOverlay .m-rail-rem-more { padding:4px 2px; font-size:11px; color:#a8a69e; }
    /* 헤더 — 시안형: 큰 제목 + dashed pill 버튼 */
    #modalOverlay .modal-date-badge { color:var(--m-muted); font-weight:600; letter-spacing:0; font-size:12px; }
    #modalOverlay .modal-title-input { font-size:18px; font-weight:700; letter-spacing:-0.2px; color:var(--m-ink); }
    #modalOverlay .modal-title-input::placeholder { color:#b3b1aa; }
    #modalOverlay .assignee-btn { border:1px dashed #c1bfb8; border-radius:999px; padding:5px 13px; background:transparent; color:#6b6a63; margin-right:6px; }
    #modalOverlay .assignee-btn:hover { border-color:var(--m-accent); color:var(--m-accent); }
    /* 01 카드 안 카테고리 칩 — 선택 시 ✓ */
    #modalOverlay .color-dot { background:var(--m-soft); }
    #modalOverlay .color-dot.active::before { content:'✓ '; font-weight:800; }
    /* 종일 토글 — 네이비 스위치 */
    #modalOverlay .toggle-track.on { background:var(--m-accent); }
    #modalOverlay .toggle-track.on .toggle-thumb { background:#fff; }
    /* 01 카드: datetime 자체 배경 제거(카드 스킨으로 통일) + 병합된 알림/반복 구분선 */
    #modalOverlay .m-main > .datetime-section { background:var(--m-card); border-color:var(--m-border); }
    #modalOverlay .m-main > .datetime-section #notifGroup { border-top:1px solid #f0eee9; padding-top:12px; }
    /* 섹션 헤더 우측 'n/m 작성' 상태 */
    #modalOverlay .m-secstat { margin-left:auto; font-size:11px; font-weight:600; color:#a8a69e; }
    #modalOverlay .m-secstat.done { color:var(--m-accent); }
    /* 요약 뷰 — 2a 스타일 2컬럼 카드 그리드 */
    #modalOverlay .lock-summary { gap:12px; padding:0; } /* modal-body 패딩만 사용 (이중 패딩 제거) */
    /* 요약 모드: 우측 작성 현황 레일(208px)이 숨겨지므로 2열 그리드 해제 — 중간 해상도에서 좌측 쏠림 방지 */
    #modalOverlay .modal-body.is-locked { display:flex; flex-direction:column; }
    #modalOverlay .ls-meta-row { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    #modalOverlay .ls-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:start; }
    #modalOverlay .ls-col { display:flex; flex-direction:column; gap:12px; min-width:0; }
    #modalOverlay .ls-card { background:var(--m-card); border:1px solid var(--m-border); border-radius:14px; padding:16px 18px; }
    #modalOverlay .ls-card-head { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
    #modalOverlay .ls-card-title { font-size:11px; font-weight:700; color:var(--m-muted); letter-spacing:0.4px; }
    #modalOverlay .ls-card-extra { margin-left:auto; font-size:11px; font-weight:700; color:var(--m-accent); }
    #modalOverlay .ls-big { font-size:16px; font-weight:800; color:var(--m-ink); letter-spacing:-0.2px; }
    #modalOverlay .ls-addr { font-size:13.5px; font-weight:700; color:var(--m-ink); margin-top:6px; line-height:1.55; word-break:break-all; }
    #modalOverlay .ls-sub { font-size:12px; color:#a8a69e; margin-top:6px; }
    #modalOverlay .ls-sub-inline { font-size:12px; color:#a8a69e; font-weight:400; }
    #modalOverlay .ls-tiles { display:grid; grid-template-columns:repeat(auto-fill,minmax(104px,1fr)); gap:8px; }
    #modalOverlay .ls-tile { border:1px solid #f0eee9; background:var(--m-soft); border-radius:10px; padding:10px 12px; min-width:0; }
    #modalOverlay .ls-tile-k { font-size:10.5px; color:#a8a69e; font-weight:600; margin-bottom:3px; }
    #modalOverlay .ls-tile-v { font-size:13px; font-weight:700; color:var(--m-ink); word-break:keep-all; }
    #modalOverlay .ls-amount-big { font-size:22px; font-weight:800; color:var(--m-ink); letter-spacing:-0.3px; }
    #modalOverlay .ls-state-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
    #modalOverlay .ls-state { padding:5px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid #e0dfda; color:#a8a69e; background:var(--m-soft); }
    #modalOverlay .ls-state.on { background:var(--m-accent); border-color:var(--m-accent); color:#fff; }
    #modalOverlay .ls-state.mid { border-color:var(--m-accent); color:var(--m-accent); background:color-mix(in srgb, var(--m-accent) 8%, transparent); }
    #modalOverlay .ls-state.warn { border-color:var(--red); color:var(--red); background:rgba(200,80,80,0.08); }
    #modalOverlay .ls-balance-warn { margin-top:10px; font-size:12.5px; font-weight:800; color:var(--red); }
    #modalOverlay .ls-bullets { margin:0; padding-left:18px; display:flex; flex-direction:column; gap:6px; font-size:13px; font-weight:600; color:var(--m-ink); }
    #modalOverlay .ls-tinted { background:color-mix(in srgb, #e8894a 6%, #fff); border-color:color-mix(in srgb, #e8894a 26%, #fff); }
    #modalOverlay .ls-tinted .ls-card-title { color:#c8763a; }
    #modalOverlay .ls-dur { font-size:12px; font-weight:600; color:#a8a69e; margin-left:8px; letter-spacing:0; }
    #modalOverlay .ls-spec-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
    #modalOverlay .ls-spec-chip { padding:5px 11px; border-radius:999px; background:#fff; border:1px solid #e0dfda; font-size:11.5px; font-weight:600; color:#55544e; }
    #modalOverlay .ls-car-reason { margin-top:8px; padding:8px 12px; border-radius:9px; background:color-mix(in srgb, var(--m-accent, #3A5683) 8%, #fff); border:1px solid color-mix(in srgb, var(--m-accent, #3A5683) 25%, #e0dfda); font-size:12.5px; font-weight:600; color:#3b4353; overflow-wrap:anywhere; }
    #modalOverlay .ls-call-btn { flex:none; padding:8px 18px; border-radius:9px; background:var(--m-accent); color:#fff; font-size:12.5px; font-weight:700; text-decoration:none; }
    #modalOverlay .ls-attach-hint { font-weight:500; color:#a8a69e; }
    #modalOverlay .ls-mobile-cta { display:none; width:100%; margin-top:4px; padding:14px 0; border:none; border-radius:12px; background:var(--m-accent); color:#fff; font-size:14.5px; font-weight:800; cursor:pointer; }
    @media (max-width:860px) {
        /* 모바일 요약: 시안 순서의 1컬럼 — 일시장소 → 의뢰자 → 진행상태 → 장비 → 특이사항 → 방송정보 → 결제 → 첨부 */
        #modalOverlay .ls-grid { display:flex; flex-direction:column; gap:12px; align-items:stretch; } /* 기본 그리드의 align-items:start 무효화 — 카드 풀폭 */
        #modalOverlay .ls-col { display:contents; }
        #modalOverlay .ls-c-time { order:1; background:color-mix(in srgb, var(--m-accent) 5%, #fff); border-color:color-mix(in srgb, var(--m-accent) 16%, #fff); }
        #modalOverlay .ls-c-client { order:2; }
        #modalOverlay .ls-c-state { order:3; }
        #modalOverlay .ls-c-equip { order:4; }
        #modalOverlay .ls-c-detail { order:5; }
        #modalOverlay .ls-c-special { order:6; }
        #modalOverlay .ls-c-broadcast { order:0; } /* 방송 정보는 일시·장소보다 위 */
        #modalOverlay .ls-c-reqtopic { order:0; }  /* 의뢰 내용은 방송 정보 바로 다음 (DOM 순서) */
        #modalOverlay .ls-c-pay { order:8; }
        #modalOverlay .ls-c-proj { order:9; }
        #modalOverlay .ls-c-ship { order:10; }
        #modalOverlay .ls-c-desc { order:11; }
        #modalOverlay .ls-c-attach { order:12; }
        /* 첨부 썸네일 가로 스크롤 */
        #modalOverlay .ls-c-attach .ls-img-grid { display:flex; overflow-x:auto; gap:8px; padding-bottom:6px; }
        #modalOverlay .ls-c-attach .ls-img-grid img { flex:none; width:96px; height:96px; object-fit:cover; border-radius:8px; }
        #modalOverlay .ls-mobile-cta { display:block; }
        /* 모바일 여백/크기 정돈 — 카드가 좁아지지 않도록 */
        #modalOverlay .modal-body { padding:12px; gap:12px; }
        #modalOverlay .modal-header { padding:14px 16px 12px; }
        #modalOverlay .modal-footer { padding:12px 16px 14px; }
        #modalOverlay .ls-card { padding:14px; border-radius:12px; }
        #modalOverlay .ls-grid { gap:10px; }
        #modalOverlay .lock-summary { gap:10px; }
        #modalOverlay .ls-meta-row { gap:5px; }
        #modalOverlay .ls-meta-row .ls-type-pill, #modalOverlay .ls-meta-row .ls-client-chip { font-size:11px; padding:4px 9px; }
        #modalOverlay .ls-big { font-size:15px; }
        #modalOverlay .ls-amount-big { font-size:19px; }
        #modalOverlay .m-main > .field-section, #modalOverlay .m-main > .datetime-section,
        #modalOverlay .m-main > #generalAttachSection, #modalOverlay .m-main .m-card { padding:14px; border-radius:12px; }
        #modalOverlay #balanceBanner { margin:10px 12px 0; }
    }
    @media (max-width:980px) {
        #modalOverlay .modal-body { display:flex; flex-direction:column; }
        #modalOverlay .m-rail { display:none !important; }
    }

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

    /* ── 일자별 세부 일정 카드 (장기 일정 하위) ── */
    .lsc-card { margin-top:14px; max-width:680px; }
    .lsc-row { display:flex; align-items:center; gap:10px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:6px; font-size:13px; background:var(--surface); }
    .lsc-row b { flex-shrink:0; font-weight:600; }
    .lsc-time { color:var(--accent); font-weight:700; flex-shrink:0; font-variant-numeric:tabular-nums; }
    .lsc-who { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-muted); }
    .lsc-mini-btn { background:none; border:1px solid var(--border); border-radius:6px; color:var(--text-muted); font-size:11px; padding:3px 9px; cursor:pointer; flex-shrink:0; }
    .lsc-mini-btn:hover { border-color:var(--accent); color:var(--accent); }
    .lsc-mini-btn.danger:hover { border-color:var(--red); color:var(--red); }
    .lsc-empty { font-size:12px; color:var(--text-muted); padding:4px 0 8px; }
    .lsc-form { border-top:1px dashed var(--border); margin-top:10px; padding-top:12px; display:flex; flex-direction:column; gap:10px; }
    .lsc-form-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .lsc-seg { display:inline-flex; border:1px solid var(--border); border-radius:8px; overflow:hidden; }
    .lsc-seg button { border:none; background:none; color:var(--text-muted); font-size:12px; padding:6px 16px; cursor:pointer; transition:all .15s; }
    .lsc-seg button.on { background:var(--accent); color:var(--accent-text); font-weight:700; }
    .lsc-edit-badge { font-size:11px; color:var(--accent); font-weight:700; }
    .lsc-grid { display:grid; grid-template-columns:56px 1fr; gap:9px 10px; align-items:center; }
    .lsc-lab { font-size:11.5px; color:var(--text-muted); font-weight:600; }
    .lsc-inline { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .lsc-inline .field-input { width:auto; flex:0 0 auto; }
    .lsc-tilde { color:var(--text-muted); font-size:13px; }
    .lsc-date-chip { padding:3px 9px; border:1px solid var(--accent); border-radius:999px; font-size:11px; color:var(--accent); white-space:nowrap; }
    .lsc-date-chip a { cursor:pointer; margin-left:2px; }
    .lsc-a-chip { padding:4px 11px; border-radius:999px; font-size:11.5px; cursor:pointer; border:1px solid var(--border); background:none; color:var(--text-muted); transition:all .15s; }
    .lsc-a-chip.on { border-color:var(--accent); background:var(--accent); color:var(--accent-text); font-weight:700; }
    .lsc-memo { grid-column:2; }
    .lsc-actions { display:flex; justify-content:flex-end; gap:6px; }
    .lsc-btn-primary { padding:7px 18px; border-radius:7px; border:none; background:var(--accent); color:var(--accent-text); font-size:12.5px; font-weight:700; cursor:pointer; }
    .lsc-btn-ghost { padding:7px 14px; border-radius:7px; border:1px solid var(--border); background:none; color:var(--text-muted); font-size:12.5px; cursor:pointer; }
    @media (max-width: 768px) {
        .lsc-grid { grid-template-columns:1fr; gap:4px; }
        .lsc-grid .lsc-lab { margin-top:4px; }
        .lsc-memo { grid-column:1; }
        .lsc-row { flex-wrap:wrap; }
        .lsc-who { flex-basis:100%; white-space:normal; }
    }

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
    /* 요약 뷰 확정 상태 퀵 피커 (제/희/목/확) */
    .ls-sopt-pick { display:inline-flex; gap:5px; align-items:center; }
    .ls-sopt-btn { padding:5px 12px; border-radius:999px; border:1.5px solid var(--border); background:none; color:var(--text-muted); font-size:12px; font-weight:700; cursor:pointer; transition:all .12s; }
    .ls-sopt-btn:hover { border-color:var(--accent); color:var(--accent); }
    .ls-sopt-btn.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .ls-sopt-btn.on[data-sopt="confirmed"] { background:#2f9e44; border-color:#2f9e44; color:#fff; }
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
    /* 비이미지 첨부: 파일 아이콘 + 파일명 칩 */
    .ls-file { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; width:88px; height:88px; border-radius:8px; border:1px solid var(--border); background:var(--surface2); text-decoration:none; color:var(--text); font-size:22px; padding:6px; box-sizing:border-box; }
    .ls-file:hover { border-color:var(--accent); }
    .ls-file span { font-size:10px; color:var(--text-muted); max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
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
    /* 삭제/변경 이력 (문장 로그) */
    .cs-changelog-btn { display:block; width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:9px; background:var(--surface); color:var(--text-muted); font-size:12px; font-weight:600; cursor:pointer; text-align:center; }
    .cs-changelog-btn:hover { border-color:var(--accent); color:var(--text); }
    .changelog-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:300; align-items:flex-start; justify-content:center; padding:7vh 16px 16px; }
    .changelog-overlay.open { display:flex; }
    .changelog-modal { width:100%; max-width:860px; background:var(--surface); border:1px solid var(--border); border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
    .changelog-head { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid var(--border); font-size:15.5px; font-weight:700; }
    .changelog-head button { background:none; border:none; color:var(--text-muted); font-size:17px; cursor:pointer; padding:2px 8px; }
    .changelog-body { max-height:64vh; overflow-y:auto; padding:8px 14px 14px; }
    .changelog-item { padding:11px 6px; border-bottom:1px solid var(--surface2); font-size:14.5px; line-height:1.65; }
    .changelog-item:last-child { border-bottom:none; }
    .changelog-item .cl-at { font-size:12.5px; color:var(--text-muted); margin-right:8px; }
    .changelog-item .cl-kind { display:inline-block; font-size:11.5px; font-weight:700; border-radius:7px; padding:2px 9px; margin-right:6px; vertical-align:1px; }
    .changelog-item .cl-kind.move { background:rgba(100,160,240,0.14); color:#4a7fd6; }
    .changelog-item .cl-kind.delete { background:rgba(224,80,80,0.12); color:#cf5454; }
    .changelog-item .cl-reason { display:block; margin-top:3px; font-size:13.5px; color:var(--text-muted); }
    .changelog-empty { color:var(--text-muted); font-size:13px; text-align:center; padding:34px 0; }

    /* 차량 이용 사유 — 모달 하단 고정 배너 */
    .car-reason-banner { position:sticky; bottom:0; z-index:8; display:flex; align-items:center; gap:8px; margin:0 28px 10px; padding:9px 14px; background:color-mix(in srgb, var(--accent) 10%, var(--surface)); border:1px solid color-mix(in srgb, var(--accent) 35%, var(--border)); border-radius:10px; font-size:12.5px; color:var(--text); box-shadow:0 -4px 14px rgba(0,0,0,0.08); }
    .car-reason-banner b { color:var(--accent); flex-shrink:0; }
    .car-reason-banner .crb-ico { flex-shrink:0; }
    .car-reason-banner #carReasonBannerText { min-width:0; overflow-wrap:anywhere; }
    .modal-body.is-locked ~ .car-reason-banner { display:none !important; } /* 요약 뷰에선 요약 카드로 표시 */
    @media (max-width:768px){ .car-reason-banner { margin:0 12px 8px; } }

    .lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:12px; touch-action:none; overscroll-behavior:contain; }
    .lightbox.open { display:flex; }
    .lightbox-img-wrap { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; overflow:visible; touch-action:none; }
    .lightbox-img-wrap.dragging { cursor:grabbing; }
    .lightbox-img-wrap.zoomed { cursor:grab; }
    .lightbox-img-wrap:not(.zoomed) { cursor:default; }
    .lightbox-img-wrap img { max-width:90vw; max-height:80vh; border-radius:8px; object-fit:contain; box-shadow:0 4px 32px rgba(0,0,0,0.5); transform-origin:center center; transition:transform 0.15s ease; user-select:none; -webkit-user-drag:none; pointer-events:auto; }
    .lightbox-close { position:fixed; top:16px; right:16px; background:rgba(0,0,0,0.45); border:1px solid rgba(255,255,255,0.35); color:#fff; width:40px; height:40px; border-radius:50%; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; z-index:20; }
    body.lb-open { overflow:hidden; } /* 라이트박스 열림 — 배경 스크롤 잠금 (닫기 버튼이 스크롤로 밀려나지 않도록) */
    .lightbox-close:hover { background:rgba(255,255,255,0.3); }
    .lightbox-zoom-info { position:absolute; bottom:60px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.6); color:#fff; padding:4px 12px; border-radius:20px; font-size:11px; opacity:0; transition:opacity 0.3s; pointer-events:none; }
    .lightbox-zoom-info.show { opacity:1; }
    .lightbox-filename { color:rgba(255,255,255,0.7); font-size:12px; text-align:center; max-width:80vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lightbox-nav { position:fixed; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.15); border:none; color:#fff; width:44px; height:44px; border-radius:50%; cursor:pointer; font-size:20px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; z-index:10; }
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
        .m-dots { display:flex; gap:2px; justify-content:center; margin-top:3px; }
        .m-dots i { width:5px; height:5px; border-radius:50%; display:block; }
        .day-cell.other-month .m-dots i { opacity:0.4; }
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
        .day-cell .event-chip .sched-icon-badge, .day-cell .event-chip .ev-assignee-badge,
        .day-cell .event-chip .opt-chip { display:none; }
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

        /* 다일 스판 칩 숨김 */
        .span-chip-overlay { display:none; }
        .lane-spacer { display:none; }
        /* 다일 제목 오버레이: 모바일에서도 바 전체 폭으로 흐르게 (크기만 축소) */
        .mday-title-overlay { font-size:9px; padding:0 4px; gap:2px; }
        .mday-title-overlay .chip-badges, .mday-title-overlay .ev-assignee-badge,
        .mday-title-overlay .sched-icon-badge, .mday-title-overlay .chip-special, .mday-title-overlay .opt-chip { display:none; }

        /* 모달 모바일 */
        .modal { max-width:95vw; border-radius:12px; }
        .modal-body { padding:14px 16px; overflow-x:hidden; }
        .modal-header { padding:16px 16px 0; }
        .modal-footer { padding:0 16px 16px; }
        .icon-btn { width:40px; height:40px; }
        .modal-title-input { font-size:18px; }
        .field-section { padding:12px; }

        /* ── 긴 텍스트로 인한 가로 스크롤 방지 — 화면 폭에 맞게 고정 ── */
        html, body { max-width:100vw; overflow-x:hidden; }
        .cal-container, .calendar-wrap, .mobile-day-events, .legend, .modal, .lock-summary { max-width:100%; min-width:0; }
        .mobile-day-events .mde-meta { overflow-wrap:anywhere; }
        .modal-body .field-input, .modal-body textarea, .modal-body select { max-width:100%; min-width:0; }
        /* 요약/카드 내부 텍스트 — 공백 없는 긴 문자열(주소·URL 등)도 강제 줄바꿈 */
        .lock-summary *, .m-card *, .field-section * { overflow-wrap:anywhere; }
        .radio-group { flex-wrap:wrap; }
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
