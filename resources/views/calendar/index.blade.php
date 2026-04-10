@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '캘린더 - 닥터고블린 오피스')

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

    .cal-header { padding:20px 32px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); background:var(--bg); position:sticky; top:0; z-index:10; }
    .cal-header-left { display:flex; align-items:center; gap:16px; }
    .app-title { font-size:13px; letter-spacing:0.2em; color:var(--accent); text-transform:uppercase; }
    .nav-btn { background:none; border:1px solid var(--border); color:var(--text-muted); cursor:pointer; width:32px; height:32px; border-radius:6px; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); }
    .month-label { font-size:18px; font-weight:500; letter-spacing:0.05em; min-width:180px; text-align:center; }

    .view-toggle-group { display:flex; background:var(--surface2); border-radius:8px; padding:2px; gap:2px; }
    .view-toggle-btn { padding:5px 14px; border-radius:6px; font-size:12px; cursor:pointer; border:none; background:none; color:var(--text-muted); transition:all 0.15s; }
    .view-toggle-btn.active { background:var(--surface); color:var(--accent); font-weight:600; }

    .add-btn { background:var(--accent); color:#1a1207; border:none; padding:8px 20px; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; transition:all 0.2s; }
    .add-btn:hover { background:#d4c09a; transform:translateY(-1px); }

    .legend { display:flex; gap:8px; align-items:center; padding:10px 32px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .filter-btn { display:flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; cursor:pointer; border:1px solid var(--border); background:none; font-size:12px; letter-spacing:0.06em; color:var(--text-muted); transition:all 0.18s; user-select:none; flex-shrink:0; }
    .filter-btn:hover { border-color:var(--accent); color:var(--text); }
    .filter-btn .filter-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; transition:all 0.18s; }
    .filter-btn.active { color:var(--text); }
    .filter-btn.active.f-gold   { background:rgba(200,176,138,0.15); border-color:var(--chip-gold-bg); }
    .filter-btn.active.f-teal   { background:rgba(232,137,74,0.15); border-color:var(--chip-teal-bg); }
    .filter-btn.active.f-blue   { background:rgba(138,180,200,0.15); border-color:var(--chip-blue-bg); }
    .filter-btn.active.f-red    { background:rgba(200,122,122,0.15); border-color:var(--chip-red-bg); }
    .filter-btn.active.f-green  { background:rgba(122,200,122,0.15); border-color:var(--chip-green-bg); }
    .filter-btn.active.f-purple { background:rgba(180,122,200,0.15); border-color:var(--chip-purple-bg); }
    .filter-btn:not(.active) .filter-dot { opacity:0.25; }
    .filter-btn:not(.active) { opacity:0.55; }

    /* ── 월간 뷰 ── */
    .calendar-wrap { padding:20px 32px; }
    .weekdays { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; margin-bottom:4px; }
    .weekday { text-align:center; font-size:13px; letter-spacing:0.12em; color:var(--text-muted); padding:8px 0; }
    .weekday:first-child { color:var(--red); }
    .weekday:last-child { color:var(--accent2); }
    .days-grid { border:1px solid var(--border); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; gap:1px; background:var(--border); }
    .week-row { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; position:relative; background:var(--border); min-height:110px; }
    .day-cell { background:var(--surface); min-height:0; padding:6px; position:relative; transition:background 0.15s; cursor:default; overflow:hidden; }
    .day-cell:hover { background:var(--surface2); }
    .day-cell.other-month { background:#111; }
    .day-cell.today .day-num { background:var(--accent); color:#1a1207 !important; font-weight:700; border-radius:50%; }
    .day-num-row { display:flex; align-items:center; gap:4px; margin-bottom:2px; min-width:0; }
    .day-num { font-size:13px; color:var(--text-muted); position:relative; z-index:1; width:24px; height:24px; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
    .day-num.sun { color:var(--red); }
    .day-num.sat { color:var(--accent2); }
    .holiday-label { font-size:11px; color:var(--red); opacity:0.85; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; letter-spacing:0.02em; line-height:1; }
    .events-list { display:flex; flex-direction:column; gap:2px; }

    /* ── 이벤트 칩 ── */
    .event-chip { border-radius:4px; padding:2px 6px; font-size:12px; white-space:nowrap; overflow:hidden; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; gap:4px; line-height:1.4; height:22px; box-sizing:border-box; min-width:0; }
    .event-chip span { min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .event-chip:hover { filter:brightness(1.12); transform:translateX(1px); }
    .event-chip.single { background:var(--chip-single-bg); color:var(--text); border-left:3px solid var(--accent); }
    .event-chip.single.color-gold   { background:rgba(200,176,138,0.22); border-left-color:var(--chip-gold-bg); }
    .event-chip.single.color-teal   { background:rgba(232,137,74,0.22); border-left-color:var(--chip-teal-bg); }
    .event-chip.single.color-blue   { background:rgba(138,180,200,0.22); border-left-color:var(--chip-blue-bg); }
    .event-chip.single.color-red    { background:rgba(200,122,122,0.22); border-left-color:var(--chip-red-bg); }
    .event-chip.single.color-green  { background:rgba(122,200,122,0.22); border-left-color:var(--chip-green-bg); }
    .event-chip.single.color-purple { background:rgba(155,112,200,0.22); border-left-color:var(--chip-purple-bg); }
    .chip-time { font-size:12px; opacity:0.85; flex-shrink:0; margin-right:3px; }
    .chip-special { font-size:11px; flex-shrink:0; letter-spacing:1px; }
    .sched-icon-badge { flex-shrink:0; font-size:12px; margin-left:3px; display:inline-flex; align-items:center; }
    .chip-badges { display:flex; align-items:center; flex-shrink:0; gap:2px; margin-left:2px; }
    .ev-assignee-badge { display:inline-flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; letter-spacing:-0.5px; color:var(--text); white-space:nowrap; flex-shrink:0; margin-left:3px; line-height:1; }

    /* ── 다일 스판 칩 ── */
    .span-chip-overlay { position:absolute; top:0; left:0; right:0; pointer-events:none; z-index:2; }
    .span-chip { position:absolute; height:22px; font-size:12px; font-weight:500; color:#111; display:flex; align-items:center; overflow:hidden; white-space:nowrap; cursor:pointer; pointer-events:all; box-sizing:border-box; padding:0 7px; transition:filter 0.15s; min-width:0; }
    .span-chip:hover { filter:brightness(1.12); }
    .span-chip.color-gold   { background:var(--chip-gold-bg); color:var(--chip-gold-text); font-weight:600; }
    .span-chip.color-teal   { background:var(--chip-teal-bg); color:var(--chip-teal-text); font-weight:600; }
    .span-chip.color-blue   { background:var(--chip-blue-bg); color:var(--chip-blue-text); font-weight:600; }
    .span-chip.color-red    { background:var(--chip-red-bg); color:var(--chip-red-text); font-weight:600; }
    .span-chip.color-green  { background:var(--chip-green-bg); color:var(--chip-green-text); font-weight:600; }
    .span-chip.color-purple { background:var(--chip-purple-bg); color:var(--chip-purple-text); font-weight:600; }
    .span-chip.is-start { border-radius:4px 0 0 4px; }
    .span-chip.is-end { border-radius:0 4px 4px 0; }
    .span-chip.is-solo { border-radius:4px; }
    .lane-spacer { height:24px; margin-bottom:2px; flex-shrink:0; }

    .more-badge { font-size:11px; color:var(--accent); padding:1px 6px; cursor:pointer; border-radius:3px; transition:all 0.15s; font-weight:600; }
    .more-badge:hover { background:rgba(200,176,138,0.15); }
    .day-cell.expanded { overflow:visible; z-index:10; }
    .day-cell.expanded .events-list { position:relative; background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:4px; margin:-4px; box-shadow:0 4px 16px rgba(0,0,0,0.3); }

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
    .tl-day-num.today-num { background:var(--accent); color:#1a1207; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; margin:2px auto 0; font-size:16px; }
    .tl-day-num.sun-c { color:var(--red); }
    .tl-day-num.sat-c { color:var(--blue); }

    /* 종일 행 */
    .tl-allday-row { display:flex; border-bottom:1px solid var(--border); }
    .tl-allday-label { width:60px; flex-shrink:0; border-right:1px solid var(--border); font-size:10px; color:var(--text-muted); display:flex; align-items:center; justify-content:center; padding:4px; background:var(--surface); }
    .tl-allday-cell { flex:1; min-height:28px; padding:3px 4px; border-right:1px solid var(--border); background:var(--bg); min-width:80px; }
    .tl-allday-cell:last-child { border-right:none; }
    .tl-allday-cell.today-col { background:rgba(200,176,138,0.04); }

    /* 시간 슬롯 */
    .tl-body { position:relative; }
    .tl-row { display:flex; }
    .tl-time-label { width:60px; flex-shrink:0; border-right:1px solid var(--border); padding:0 6px; font-size:10px; color:var(--text-muted); text-align:right; height:48px; display:flex; align-items:flex-start; padding-top:4px; background:var(--surface); }
    .tl-slot { flex:1; border-right:1px solid var(--border); border-bottom:1px solid var(--border); height:48px; position:relative; cursor:pointer; background:var(--bg); transition:background 0.1s; min-width:80px; }
    .tl-slot:last-child { border-right:none; }
    .tl-slot:hover { background:var(--surface2); }
    .tl-slot.today-col { background:rgba(200,176,138,0.04); }

    .tl-event { position:absolute; left:2px; right:2px; border-radius:4px; padding:2px 5px; font-size:11px; overflow:hidden; cursor:pointer; z-index:1; transition:filter 0.1s; line-height:1.3; }
    .tl-event:hover { filter:brightness(1.15); z-index:2; }
    .tl-event.color-gold   { background:var(--gold); color:#1a1207; }
    .tl-event.color-teal   { background:var(--teal); color:#1a1207; }
    .tl-event.color-blue   { background:var(--blue); color:#1a1207; }
    .tl-event.color-red    { background:var(--red); color:#fff; }
    .tl-event.color-green  { background:var(--green); color:#1a1207; }
    .tl-event.color-purple { background:var(--purple); color:#fff; }

    /* ── 모달 ── */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.72); z-index:200; backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal-wrapper { position:relative; display:flex; align-items:flex-start; gap:8px; max-height:92vh; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:660px; max-height:92vh; overflow-y:auto; animation:modalIn 0.22s ease; }
    .modal-external-btns { position:sticky; top:0; flex-shrink:0; display:flex; flex-direction:column; gap:8px; z-index:1; }
    .modal-external-close { background:var(--surface); border:1px solid var(--border); color:var(--text-muted); width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.3); }
    .modal-external-close:hover { border-color:var(--red); color:var(--red); background:var(--surface2); }
    .modal-external-action { background:var(--accent); color:#1a1207; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.3); letter-spacing:-0.5px; }
    .modal-external-action:hover { filter:brightness(1.1); }
    @media (max-width:720px) { .modal-external-btns { display:none; } }
    @keyframes modalIn { from{opacity:0;transform:translateY(18px) scale(0.97)} to{opacity:1;transform:translateY(0) scale(1)} }

    .modal-strip { height:4px; border-radius:16px 16px 0 0; background:var(--accent); transition:background 0.3s; }
    .modal-strip.color-teal { background:var(--teal); }
    .modal-strip.color-blue { background:var(--blue); }
    .modal-strip.color-red { background:var(--red); }
    .modal-strip.color-green { background:var(--green); }
    .modal-strip.color-purple { background:var(--purple); }
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
    .btn-save-top { background:var(--accent); color:#1a1207; border:none; padding:6px 16px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; white-space:nowrap; }
    .btn-save-top:hover { filter:brightness(1.1); }

    .modal-body { padding:18px 28px 18px; display:flex; flex-direction:column; gap:14px; }
    .modal-footer { padding:0 28px 22px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .btn-delete { background:none; border:1px solid rgba(200,122,122,0.4); color:var(--red); padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; transition:all 0.2s; opacity:0.7; }
    .btn-delete:hover { opacity:1; background:rgba(200,122,122,0.1); }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:10px 28px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; }
    .btn-save:hover { filter:brightness(1.1); }
    .btn-log { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 14px; border-radius:8px; font-size:12px; cursor:pointer; transition:all 0.2s; }
    .btn-log:hover { border-color:var(--accent); color:var(--accent); }

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
    .field-input, .field-textarea, .field-select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:14px; outline:none; transition:border-color 0.2s; width:100%; box-sizing:border-box; }
    .field-input:focus, .field-textarea:focus { border-color:var(--accent); }
    .field-input::placeholder, .field-textarea::placeholder { color:var(--text-muted); }
    .field-textarea { resize:none; min-height:80px; line-height:1.7; }
    .field-input:disabled, .field-textarea:disabled { opacity:0.55; cursor:not-allowed; background:var(--surface); }

    /* ── 라디오 버튼 (pill) ── */
    .radio-group { display:flex; gap:8px; flex-wrap:wrap; }
    .radio-btn { padding:6px 14px; border:1px solid var(--border); border-radius:20px; font-size:12px; cursor:pointer; transition:all 0.2s; user-select:none; letter-spacing:0.05em; color:var(--text-muted); }
    .radio-btn:hover { border-color:var(--accent); color:var(--accent); }
    .radio-btn.active { background:var(--accent); border-color:var(--accent); color:#1a1207; font-weight:600; }
    .radio-btn.active-red { background:var(--red); border-color:var(--red); color:#fff; }
    .radio-btn.active-green { background:var(--green); border-color:var(--green); color:#111; }

    /* ── 색상 선택 ── */
    .color-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .color-dot { padding:6px 14px; border-radius:20px; cursor:pointer; border:2px solid transparent; transition:all 0.18s; font-size:12px; font-weight:600; letter-spacing:0.03em; user-select:none; white-space:nowrap; }
    .color-dot[data-color="gold"]   { background:rgba(200,176,138,0.18); color:#c8b08a; }
    .color-dot[data-color="teal"]   { background:rgba(232,137,74,0.18); color:#e8894a; }
    .color-dot[data-color="blue"]   { background:rgba(138,180,200,0.18); color:#8ab4c8; }
    .color-dot[data-color="red"]    { background:rgba(200,122,122,0.18); color:#c87a7a; }
    .color-dot[data-color="green"]  { background:rgba(122,200,122,0.18); color:#7ac87a; }
    .color-dot[data-color="purple"] { background:rgba(155,112,200,0.18); color:#9b70c8; }
    .color-dot.active[data-color="gold"]   { background:rgba(200,176,138,0.35); border-color:#c8b08a; }
    .color-dot.active[data-color="teal"]   { background:rgba(232,137,74,0.35); border-color:#e8894a; }
    .color-dot.active[data-color="blue"]   { background:rgba(138,180,200,0.35); border-color:#8ab4c8; }
    .color-dot.active[data-color="red"]    { background:rgba(200,122,122,0.35); border-color:#c87a7a; }
    .color-dot.active[data-color="green"]  { background:rgba(122,200,122,0.35); border-color:#7ac87a; }
    .color-dot.active[data-color="purple"] { background:rgba(155,112,200,0.35); border-color:#9b70c8; }
    .color-dot:hover { filter:brightness(1.15); }

    /* ── 일정옵션 pill ── */
    .special-opts { display:flex; gap:7px; flex-wrap:wrap; margin-top:4px; }
    .special-opt-btn { display:flex; align-items:center; gap:6px; padding:7px 12px; border-radius:8px; cursor:pointer; border:1.5px solid var(--border); background:var(--surface2); font-size:12px; transition:all 0.15s; user-select:none; color:var(--text-muted); white-space:nowrap; }
    .special-opt-btn .opt-icon { font-size:15px; flex-shrink:0; }
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
    .conditional-field.visible { max-height:80px; }

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
    .tp-confirm-btn { background:var(--accent); color:#1a1207; border:none; border-radius:7px; padding:5px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:opacity 0.15s; }
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
    .img-item .img-filename { font-size:10px; color:var(--text-muted); padding:3px 7px 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-top:1px solid var(--border); }
    .img-item .img-note { font-size:11px; padding:3px 6px 5px; background:transparent; border:none; border-top:1px solid var(--border); color:var(--text); width:100%; box-sizing:border-box; resize:none; line-height:1.4; min-height:30px; outline:none; border-radius:0 0 8px 8px; }
    .img-item .img-note::placeholder { color:var(--text-muted); }
    .img-remove { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.75); border:none; color:#fff; width:18px; height:18px; border-radius:50%; cursor:pointer; font-size:10px; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s; z-index:1; }
    .img-item:hover .img-remove { opacity:1; }

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
    .assignee-chip.selected { background:var(--accent); color:#1a1207; border-color:var(--accent); font-weight:600; }
    .assignee-chip:hover { border-color:var(--accent); }

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

    /* ── 모바일 일정 리스트 (네이버 캘린더 스타일) ── */
    .mobile-day-events { display:none; }

    @media (max-width: 768px) {
        /* 헤더 컴팩트 */
        .cal-header { padding:12px; gap:8px; flex-wrap:wrap; }
        .cal-header-left { gap:8px; }
        .app-title { display:none; }
        .nav-btn { width:40px; height:40px; }
        .month-label { font-size:16px; min-width:0; }
        .view-toggle-btn { padding:6px 12px; min-height:36px; font-size:11px; }
        .add-btn { padding:8px 14px; font-size:12px; }

        /* 필터바 컴팩트 */
        .legend { padding:8px 12px; gap:6px; }
        .filter-btn { padding:6px 10px; min-height:36px; font-size:11px; }

        /* 월간 그리드 컴팩트 */
        .calendar-wrap { padding:8px 12px; }
        .weekday { font-size:11px; padding:6px 0; }
        .week-row { min-height:60px; }
        .day-cell { padding:4px 2px; }
        .day-num { font-size:14px; width:28px; height:28px; font-weight:600; }
        .day-cell.today .day-num { width:28px; height:28px; }

        /* 이벤트 칩 → dot 표시 */
        .events-list { flex-direction:row; gap:2px; justify-content:center; flex-wrap:wrap; }
        .event-chip { width:10px; height:10px; min-width:10px; border-radius:50%; padding:0; overflow:hidden; border:none !important; }
        .event-chip span, .event-chip .chip-time, .event-chip .chip-special,
        .event-chip .chip-badges, .event-chip .sched-icon-badge, .event-chip .ev-assignee-badge { display:none; }
        .event-chip.single { border-left:none; }
        .event-chip.single.color-gold   { background:var(--chip-gold-bg); }
        .event-chip.single.color-teal   { background:var(--chip-teal-bg); }
        .event-chip.single.color-blue   { background:var(--chip-blue-bg); }
        .event-chip.single.color-red    { background:var(--chip-red-bg); }
        .event-chip.single.color-green  { background:var(--chip-green-bg); }
        .event-chip.single.color-purple { background:var(--chip-purple-bg); }
        .event-chip { pointer-events:none; }
        .more-badge { font-size:9px; padding:0 2px; pointer-events:none; }

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
        .mobile-day-events .mde-title { font-size:13px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mobile-day-events .mde-meta { font-size:11px; color:var(--text-muted); margin-top:2px; }
        .mobile-day-events .mde-empty { text-align:center; padding:20px; color:var(--text-muted); font-size:13px; }

        /* 다일 스판 칩 숨김 (모바일에서는 dot으로 대체) */
        .span-chip-overlay { display:none; }
        .lane-spacer { display:none; }

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
        .cal-header-right { width:100%; justify-content:center; }
        .weekday { font-size:10px; letter-spacing:0; }
        .week-row { min-height:50px; }
        .day-num { font-size:13px; width:26px; height:26px; }
    }
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
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        @if(Auth::user()->hasPermission('calendar.edit'))
            <button class="add-btn" onclick="openNewModal()">+ 일정 추가</button>
        @endif
    </div>
</div>

<div class="legend" id="filterBar">
    <button class="filter-btn active f-gold" data-filter="gold" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-gold-bg)"></span>방문의뢰</button>
    <button class="filter-btn active f-teal" data-filter="teal" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-teal-bg)"></span>원격/방송룸</button>
    <button class="filter-btn active f-blue" data-filter="blue" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-blue-bg)"></span>사내업무</button>
    <button class="filter-btn active f-red" data-filter="red" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-red-bg)"></span>휴가/개인</button>
    <button class="filter-btn active f-green" data-filter="green" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-green-bg)"></span>촬영/스튜디오</button>
    <button class="filter-btn active f-purple" data-filter="purple" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-purple-bg)"></span>미팅/내방</button>
</div>

<!-- 월간 뷰 -->
<div id="monthView">
    <div class="calendar-wrap">
        <div class="weekdays">
            <div class="weekday">SUN</div><div class="weekday">MON</div><div class="weekday">TUE</div>
            <div class="weekday">WED</div><div class="weekday">THU</div><div class="weekday">FRI</div><div class="weekday">SAT</div>
        </div>
        <div class="days-grid" id="daysGrid"></div>
        <div class="mobile-day-events" id="mobileDayEvents"></div>
    </div>
</div>

<!-- 주간/일간 뷰 -->
<div id="timelineView" style="display:none;">
    <div class="timeline-wrap">
        <div class="timeline-grid" id="timelineGrid"></div>
    </div>
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
                    <div class="color-dot active" data-color="gold">방문의뢰</div>
                    <div class="color-dot" data-color="teal">원격/방송룸</div>
                    <div class="color-dot" data-color="blue">사내업무</div>
                    <div class="color-dot" data-color="red">휴가/개인</div>
                    <div class="color-dot" data-color="green">촬영/스튜디오</div>
                    <div class="color-dot" data-color="purple">미팅/내방</div>
                </div>
                <div class="holiday-btn-wrap" style="margin-bottom:4px;">
                    <span class="holiday-dot" id="holidayDot" style="font-size:12px;color:var(--text-muted);cursor:pointer;">📅 공휴일로 지정</span>
                </div>
                <div class="title-wrap">
                    <textarea class="modal-title-input" id="modalTitle" placeholder="일정 제목을 입력하세요" rows="1"></textarea>
                </div>
                <button class="assignee-btn" id="assigneeBtn" onclick="toggleAssigneePanel()" title="담당자 지정">
                    <span id="assigneeBtnIcon">👤</span>
                    <span id="assigneeBtnLabel">담당자 지정</span>
                </button>
                <div class="assignee-list" id="assigneeList" style="display:none;margin-top:8px;"></div>
            </div>
            <div class="modal-header-btns">
                <span id="privateModeBadge" style="display:none;font-size:11px;background:#a78bfa22;color:#a78bfa;border:1px solid #a78bfa55;border-radius:6px;padding:2px 8px;font-weight:600;">🔒 개인</span>
                <button class="icon-btn" id="lockBtn" onclick="toggleLock()" title="내용 고정">🔓</button>
                <button class="btn-save-top" onclick="saveEvent()">저장</button>
                <button class="icon-btn close-btn" onclick="closeModal()">✕</button>
            </div>
        </div>
        <div class="locked-banner" id="lockedBanner">🔒&nbsp; 이 일정은 고정되어 있습니다 — 수정하려면 자물쇠를 해제하세요</div>
        <div id="balanceBanner" style="display:none;align-items:center;gap:8px;background:rgba(200,80,80,0.1);border:1px solid rgba(200,80,80,0.35);border-radius:8px;padding:8px 14px;font-size:12px;letter-spacing:0.05em;color:#e07070;margin:10px 28px 0;">
            <span style="font-size:15px;">💰</span>
            <span id="balanceBannerText">잔금 있음</span>
        </div>

        <div class="modal-body">
            {{-- 장소 --}}
            <div class="field-section">
                <div class="field-group">
                    <label class="field-label" for="modalLocation">장소</label>
                    <div class="location-input-wrap">
                        <textarea class="field-input field-textarea" id="modalLocation" placeholder="장소를 입력하세요" autocomplete="off" rows="2" style="min-height:40px;resize:none;"></textarea>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button" class="addr-search-btn" onclick="searchCalAddr()" title="주소 검색">🔍 주소 검색</button>
                        </div>
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
                <div id="goldDtRow" style="display:none;align-items:center;gap:6px;flex-wrap:nowrap;width:100%;">
                    <input class="dt-input" type="date" id="goldStartDate" style="flex:2;min-width:0;">
                    <input type="hidden" id="goldStartTime" value="13:00">
                    <div class="time-picker-trigger dt-input" id="goldStartTimeTrigger" onclick="openTimePicker(this,'goldStartTime')" style="flex:1;min-width:0;">13:00</div>
                    <span style="color:var(--text-muted);font-size:13px;flex-shrink:0;">~</span>
                    <input type="hidden" id="goldEndTime" value="14:00">
                    <div class="time-picker-trigger dt-input" id="goldEndTimeTrigger" onclick="openTimePicker(this,'goldEndTime')" style="flex:1;min-width:0;">14:00</div>
                </div>
            </div>

            {{-- 알림 + 옵션 --}}
            <div class="field-section">
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

            {{-- 일정 옵션 --}}
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
                </div>
                <div id="specialReasonWrap" style="display:none;margin-top:6px;">
                    <input class="field-input" id="specialReason" placeholder="특수옵션 사유 (선택)" style="font-size:13px;">
                </div>
            </div>
            </div>{{-- /field-section (알림+옵션) --}}

            <div class="divider"></div>

            {{-- 공통 필드 (비-gold/비-teal) --}}
            <div class="common-only field-section">
                <div class="field-group">
                    <label class="field-label">이름 / 담당자</label>
                    <input class="field-input" id="commonName" placeholder="이름을 입력하세요">
                </div>
                <div class="field-group">
                    <label class="field-label">상세 설명</label>
                    <textarea class="field-textarea" id="commonDesc" placeholder="상세 내용을 입력하세요"></textarea>
                </div>
            </div>

            {{-- 의뢰자 검색/연결 (모든 유형 공통) --}}
            <div class="field-section">
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
                    <div class="field-group"><label class="field-label">의뢰자 닉네임</label><input class="field-input" id="g_nickname" placeholder="닉네임"></div>
                    <div class="field-group"><label class="field-label">의뢰자 이름</label><input class="field-input" id="g_name" placeholder="이름"></div>
                    <div class="field-group"><label class="field-label">전화번호</label><input class="field-input" id="g_phone" placeholder="010-0000-0000"></div>
                </div>

                <div class="field-row">
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">플랫폼</label>
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
                        <div class="radio-group" id="g_topic_group" style="flex-wrap:nowrap;gap:5px;flex-shrink:0;">
                            <div class="radio-btn" data-val="소통">소통</div>
                            <div class="radio-btn" data-val="먹방">먹방</div>
                            <div class="radio-btn" data-val="게임">게임</div>
                            <div class="radio-btn" data-val="야외">야외</div>
                            <div class="radio-btn" data-val="노래">노래</div>
                            <div class="radio-btn" data-val="주식/코인">주식/코인</div>
                            <div class="radio-btn" data-val="기타">기타</div>
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
                        <label class="field-label">견적 총액</label>
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
                            <label class="field-label">원격 대상자 이름(닉네임)</label>
                            <input class="field-input" id="t_remote_name" placeholder="이름 또는 닉네임">
                        </div>
                        <div class="field-group">
                            <label class="field-label">방송 플랫폼</label>
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
                            <label class="field-label">방송룸 이용자 이름(닉네임)</label>
                            <input class="field-input" id="t_studio_name" placeholder="이름 또는 닉네임">
                        </div>
                        <div class="field-group">
                            <label class="field-label">방송 플랫폼</label>
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

        <div class="modal-footer">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">일정 삭제</button>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn-log" id="btnLog" style="display:none" onclick="openHistoryFromEdit()">📋 <span>변경 로그</span></button>
                <button class="btn-save" onclick="saveEvent()">저장</button>
            </div>
        </div>
    </div>
    <div class="modal-external-btns">
        <button class="modal-external-close" onclick="closeModal()" title="닫기">✕</button>
        <button class="modal-external-action" id="modalExternalAction" onclick="saveEvent()" title="저장">저장</button>
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
const canEditCalendar = @json(Auth::user()->hasPermission('calendar.edit'));
const isGuestUser = @json(Auth::user()->isGuest());
const HOURS = Array.from({length:14}, (_,i) => i+9); // 9시~22시

let currentYear, currentMonth, currentWeekStart, currentDay;
let events = [], assignees = [], selectedAssignees = [];
let editingId = null, currentColor = 'gold', currentView = 'month';
let expandedDays = new Set();

// ── 초기화 ──────────────────────────────────────────────────────
function init() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth();
    currentWeekStart = getWeekStart(now);
    currentDay = new Date(now); currentDay.setHours(0,0,0,0);
    loadAssignees();
    renderView();
    loadEvents();
}

function getWeekStart(d) {
    const r = new Date(d); r.setDate(r.getDate()-r.getDay()); r.setHours(0,0,0,0); return r;
}
function fmt(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function todayStr() { return fmt(new Date()); }

// ── 필터 ──
let activeFilters = new Set(['gold','teal','blue','red','green','purple','holiday']);
function toggleFilter(btn){
    const f=btn.dataset.filter;
    if(activeFilters.has(f)){activeFilters.delete(f);btn.classList.remove('active');}
    else{activeFilters.add(f);btn.classList.add('active');}
    renderView();
}
function isFiltered(ev){ return activeFilters.has(ev.color); }

// ── 이벤트 칩 생성 헬퍼 ──
const SPECIAL_ICONS={car:'🚗',brief:'💼',group:'👥',ladder:'▤'};
const SCHED_ICONS={suggest:'💬',hope:'🙏',target:'🎯'};
function buildChipHtml(ev){
    let html='';
    const time=ev.start_time?ev.start_time.substring(0,5):'';
    if(time) html+=`<span class="chip-time">${time}</span>`;
    // 특수 아이콘
    const specOpts=ev.special_opts||[];
    specOpts.forEach(o=>{if(SPECIAL_ICONS[o]) html+=`<span class="chip-special">${SPECIAL_ICONS[o]}</span>`;});
    // 제목
    const title=isGuestUser?(ev.location||'일정'):((ev.client_name?ev.client_name+' ':'')+ev.title);
    html+=`<span>${title}</span>`;
    // 일정 관련 아이콘
    if(ev.sched_opt&&SCHED_ICONS[ev.sched_opt]) html+=`<span class="sched-icon-badge">${SCHED_ICONS[ev.sched_opt]}</span>`;
    // 담당자 배지
    if(ev.assignees&&ev.assignees.length){
        html+='<span class="chip-badges">';
        ev.assignees.forEach(a=>{html+=`<span class="ev-assignee-badge" title="${a.name}">${(a.name||'?')[0]}</span>`;});
        html+='</span>';
    }
    return html;
}

// ── 뷰 전환 ─────────────────────────────────────────────────────
function switchView(view) {
    currentView = view;
    document.querySelectorAll('.view-toggle-btn').forEach(b=>b.classList.toggle('active',b.textContent.includes(view==='month'?'월간':view==='week'?'주간':'일간')));
    document.getElementById('monthView').style.display    = view==='month' ? '' : 'none';
    document.getElementById('timelineView').style.display = view!=='month' ? '' : 'none';
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
    renderView(); loadEvents();
}

function renderView() {
    if (currentView==='month') renderMonth();
    else renderTimeline();
}

// ── 이벤트 로드 ─────────────────────────────────────────────────
async function loadEvents() {
    expandedDays.clear();
    let start, end;
    if (currentView==='month') {
        start=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-01`;
        const last=new Date(currentYear,currentMonth+1,0).getDate();
        end=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${last}`;
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
    if(res.ok) assignees=await res.json();
}

// ── 월간 뷰 ─────────────────────────────────────────────────────
function renderMonth() {
    document.getElementById('periodTitle').textContent=`${currentYear}년 ${currentMonth+1}월`;
    const grid=document.getElementById('daysGrid'); grid.innerHTML='';
    const firstDay=new Date(currentYear,currentMonth,1).getDay();
    const lastDate=new Date(currentYear,currentMonth+1,0).getDate();
    const prevLast=new Date(currentYear,currentMonth,0).getDate();
    const ts=todayStr();

    // 셀 데이터 생성
    let cells=[];
    for(let i=firstDay-1;i>=0;i--) cells.push({date:prevLast-i,month:'prev',full:null});
    for(let d=1;d<=lastDate;d++){
        const full=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({date:d,month:'cur',full});
    }
    const rem=42-cells.length;
    for(let d=1;d<=rem;d++) cells.push({date:d,month:'next',full:null});

    // 주 단위로 렌더링
    for(let w=0;w<6;w++){
        const weekRow=document.createElement('div');
        weekRow.className='week-row';
        for(let d=0;d<7;d++){
            const idx=w*7+d;
            const cell=cells[idx];
            const div=document.createElement('div');
            div.className='day-cell'+(cell.month!=='cur'?' other-month':'');
            if(cell.full) div.dataset.date=cell.full;
            if(cell.full===ts) div.classList.add('today');
            const nc=d===0?'sun':d===6?'sat':'';
            div.innerHTML=`<div class="day-num-row"><span class="day-num ${nc}">${cell.date}</span></div>`;

            if(cell.full){
                // 필터 적용된 이벤트
                const dayEvs=events.filter(ev=>isFiltered(ev)&&ev.start_date<=cell.full&&(ev.end_date||ev.start_date)>=cell.full);
                // 다일 이벤트와 단일 이벤트 분리
                const multiDay=dayEvs.filter(ev=>ev.end_date&&ev.end_date!==ev.start_date);
                const singleDay=dayEvs.filter(ev=>!ev.end_date||ev.end_date===ev.start_date);

                const evList=document.createElement('div');
                evList.className='events-list';

                // 모든 이벤트를 하나의 리스트로 합산 (다일은 시작일에만)
                const allChipEvs = [];
                multiDay.forEach(ev=>{ if(ev.start_date===cell.full) allChipEvs.push(ev); });
                singleDay.forEach(ev=>allChipEvs.push(ev));

                const MAX_VISIBLE = 3;
                const isExpanded = expandedDays.has(cell.full);
                if(isExpanded) div.classList.add('expanded');
                const visibleEvs = isExpanded ? allChipEvs : allChipEvs.slice(0, MAX_VISIBLE);

                visibleEvs.forEach(ev=>{
                    const chip=document.createElement('div');
                    chip.className=`event-chip single color-${ev.color}`;
                    chip.innerHTML=buildChipHtml(ev);
                    chip.onclick=e=>{e.stopPropagation();openDetailModal(ev);};
                    evList.appendChild(chip);
                });

                if(allChipEvs.length > MAX_VISIBLE){
                    const more=document.createElement('div');
                    more.className='more-badge';
                    if(isExpanded){
                        more.textContent='접기';
                        more.onclick=e=>{e.stopPropagation(); expandedDays.delete(cell.full); renderMonth();};
                    } else {
                        more.textContent=`+${allChipEvs.length - MAX_VISIBLE}`;
                        more.onclick=e=>{e.stopPropagation(); expandedDays.add(cell.full); renderMonth();};
                    }
                    evList.appendChild(more);
                }

                div.appendChild(evList);
                div.addEventListener('click',e=>{
                    if(window.innerWidth<=768){
                        selectMobileDay(cell.full);
                    } else {
                        if(e.target.closest('.event-chip')||e.target.closest('.more-badge')) return;
                        if(canEditCalendar&&(e.target===div||e.target.classList.contains('day-num-row')||e.target.classList.contains('day-num'))) openNewModal(cell.full);
                    }
                });
            }
            weekRow.appendChild(div);
        }
        grid.appendChild(weekRow);
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
    const dayEvs=events.filter(ev=>isFiltered(ev)&&ev.start_date<=dateStr&&(ev.end_date||ev.start_date)>=dateStr);
    const d=new Date(dateStr+'T00:00:00');
    const DAYS_KO_FULL=['일요일','월요일','화요일','수요일','목요일','금요일','토요일'];
    const header=`${d.getMonth()+1}월 ${d.getDate()}일 ${DAYS_KO_FULL[d.getDay()]}`;
    const COLOR_MAP={gold:'var(--chip-gold-bg)',teal:'var(--chip-teal-bg)',blue:'var(--chip-blue-bg)',red:'var(--chip-red-bg)',green:'var(--chip-green-bg)',purple:'var(--chip-purple-bg)'};

    if(!dayEvs.length){
        container.innerHTML=`<div class="mde-header">${header}</div><div class="mde-empty">일정이 없습니다</div>`;
        return;
    }
    const items=dayEvs.map(ev=>{
        const time=ev.is_allday?'종일':(ev.start_time||'').substring(0,5);
        const title=ev.title||'(제목 없음)';
        return `<div class="mde-item" onclick="openDetailModal(events.find(e=>e.id===${ev.id}))">
            <div class="mde-dot" style="background:${COLOR_MAP[ev.color]||'var(--accent)'}"></div>
            <div class="mde-info">
                <div class="mde-title">${title}</div>
                <div class="mde-meta">${time}${ev.location?' · '+ev.location:''}</div>
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
        events.filter(ev=>ev.is_all_day&&ev.start_date<=ds&&(ev.end_date||ev.start_date)>=ds).forEach(ev=>{
            const chip=document.createElement('div');
            chip.className=`event-chip color-${ev.color}`;
            chip.style.marginBottom='2px';
            chip.textContent=isGuestUser?(ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):''):((ev.client_name?ev.client_name+' ':'')+ev.title);
            chip.onclick=()=>openDetailModal(ev);
            cell.appendChild(chip);
        });
        cell.addEventListener('click',e=>{if(e.target===cell)openNewModal(ds);});
        alldayRow.appendChild(cell);
    });
    grid.appendChild(alldayRow);

    // 시간 슬롯
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
                if(ev.is_all_day) return false;
                if(ev.start_date!==ds) return false;
                const h=ev.start_time?parseInt(ev.start_time.split(':')[0]):null;
                return h===hour;
            }).forEach(ev=>{
                const el=document.createElement('div');
                el.className=`tl-event color-${ev.color}`;
                const sm=ev.start_time?parseInt(ev.start_time.split(':')[1]):0;
                const eh=ev.end_time?parseInt(ev.end_time.split(':')[0]):hour+1;
                const em=ev.end_time?parseInt(ev.end_time.split(':')[1]):0;
                const dur=(eh+em/60)-(hour+sm/60);
                el.style.top=`${(sm/60)*48}px`;
                el.style.height=`${Math.max(dur*48,20)}px`;
                el.textContent=(ev.client_name?ev.client_name+' ':'')+ev.title;
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
const COLOR_NAMES={gold:'방문의뢰',teal:'원격/방송룸',blue:'사내업무',red:'휴가/개인',green:'촬영/스튜디오',purple:'미팅/내방',holiday:'공휴일'};
let isAllDay=false, isLocked=false, linkedEstimateId=null;

function initRadioGroup(gid, opts){
    const g=document.getElementById(gid); if(!g) return;
    const multi=opts?.multi||false;
    g.querySelectorAll('.radio-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
            if(isLocked) return;
            if(multi){btn.classList.toggle('active');}
            else{g.querySelectorAll('.radio-btn').forEach(b=>b.classList.remove('active','active-red','active-green'));btn.classList.add('active');}
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
    // 주문제품 O → 배송완료
    if(gid==='g_order_group'){
        const v=getRadio('g_order_group');
        document.getElementById('g_delivery_wrap').style.display=v==='O'?'':'none';
    }
    // 잔금 O → 금액
    if(gid==='g_balance_group'){
        const v=getRadio('g_balance_group');
        const cond=document.getElementById('g_balance_cond');
        if(cond) cond.classList.toggle('visible',v==='O');
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
    document.querySelectorAll('.common-only').forEach(s=>s.style.display=(c!=='gold'&&c!=='teal')?'':'none');
    // gold 전용 날짜 행
    document.getElementById('standardDtRows').style.display=c==='gold'?'none':'';
    document.getElementById('goldDtRow').style.display=c==='gold'?'flex':'none';
    updateBalanceBanner();
}

// ── 담당자 ──
let assigneePanelOpen=false;
function toggleAssigneePanel(){
    assigneePanelOpen=!assigneePanelOpen;
    document.getElementById('assigneeList').style.display=assigneePanelOpen?'flex':'none';
    if(assigneePanelOpen) renderAssigneeList();
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
    c.innerHTML='';
    assignees.forEach(a=>{
        const chip=document.createElement('div');
        chip.className='assignee-chip'+(selectedAssignees.includes(a.id)?' selected':'');
        chip.textContent=a.name; chip.dataset.id=a.id;
        chip.onclick=()=>{
            if(isLocked) return;
            if(selectedAssignees.includes(a.id)){selectedAssignees=selectedAssignees.filter(id=>id!==a.id);chip.classList.remove('selected');}
            else{selectedAssignees.push(a.id);chip.classList.add('selected');}
            updateAssigneeBtn();
        };
        c.appendChild(chip);
    });
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
    const btn=document.getElementById('lockBtn');
    btn.textContent=isLocked?'🔒':'🔓';
    btn.classList.toggle('locked',isLocked);
    document.getElementById('lockedBanner').classList.toggle('visible',isLocked);
    // 모든 입력 disable/enable
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input').forEach(el=>{el.disabled=isLocked;});
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display=isLocked?'none':'';});
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
    // 공통 이름 필드도 채움
    const commonName=document.getElementById('commonName');
    if(commonName&&!commonName.value) commonName.value=nickname||name||'';
    // client_name도 채움
    document.getElementById('modalTitle').value=document.getElementById('modalTitle').value||(nickname||name);
    // 프로젝트 목록 로드
    await loadClientProjects(id);
}

async function loadClientProjects(clientId){
    const wrap=document.getElementById('projectSelectWrap');
    const sel=document.getElementById('projectSelect');
    try{
        const res=await fetch(`/api/clients/${clientId}/detail`);
        if(!res.ok){wrap.style.display='none';return;}
        const data=await res.json();
        const projects=data.projects||[];
        if(!projects.length){wrap.style.display='none';return;}
        sel.innerHTML='<option value="">프로젝트 선택 (선택사항)</option>';
        projects.forEach(p=>{
            const opt=document.createElement('option');
            opt.value=p.id;
            opt.textContent=`${p.name} (${p.stage||p.type||''})`;
            sel.appendChild(opt);
        });
        // 이전에 연결된 프로젝트가 있으면 선택
        if(linkedProjectId) sel.value=linkedProjectId;
        wrap.style.display='';
    }catch(e){wrap.style.display='none';}
}

function unlinkClient(){
    linkedClientId=null;linkedProjectId=null;
    document.getElementById('linkedClientInfo').style.display='none';
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
let pendingAttachments={quote:[],reference:[],room:[]};
let existingAttachments={quote:[],reference:[],room:[]};
const GRID_MAP={quote:'quoteGrid',reference:'refGrid',room:'roomGrid'};
const FILE_MAP={quote:'fileQuote',reference:'fileReference',room:'fileRoom'};

function triggerAttach(type){document.getElementById(FILE_MAP[type]).click();}

function handleImgFiles(type,files){
    if(!files||!files.length) return;
    Array.from(files).forEach(f=>pendingAttachments[type].push({file:f,note:''}));
    renderImgGrid(type);
    // 파일 input 리셋
    const input=document.getElementById(FILE_MAP[type]); if(input) input.value='';
}

function renderImgGrid(type){
    const grid=document.getElementById(GRID_MAP[type]); if(!grid) return;
    grid.innerHTML='';
    // 기존
    existingAttachments[type].forEach((a,i)=>{
        grid.innerHTML+=`<div class="img-item"><div class="img-thumb-wrap"><img src="${a.url}" alt="${a.file_name||''}"><button class="img-remove" onclick="removeExistingAttach('${type}',${i},${a.id})">✕</button></div><div class="img-filename">${a.file_name||''}</div></div>`;
    });
    // 새로 추가된
    pendingAttachments[type].forEach((item,i)=>{
        const div=document.createElement('div');div.className='img-item';
        const wrap=document.createElement('div');wrap.className='img-thumb-wrap';
        const img=document.createElement('img');img.src=URL.createObjectURL(item.file);img.alt=item.file.name;wrap.appendChild(img);
        const rm=document.createElement('button');rm.className='img-remove';rm.textContent='✕';
        rm.onclick=()=>{pendingAttachments[type].splice(i,1);renderImgGrid(type);};
        wrap.appendChild(rm);div.appendChild(wrap);
        const fn=document.createElement('div');fn.className='img-filename';fn.textContent=item.file.name;div.appendChild(fn);
        const note=document.createElement('textarea');note.className='img-note';note.placeholder='주석 입력';note.rows=1;
        note.value=item.note||'';note.oninput=()=>{item.note=note.value;};
        div.appendChild(note);grid.appendChild(div);
    });
}

// 드래그 드롭
['quoteZone','refZone','roomZone'].forEach(zid=>{
    const zone=document.getElementById(zid); if(!zone) return;
    const type=zid==='quoteZone'?'quote':zid==='refZone'?'reference':'room';
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
    for(const type of ['quote','reference','room']){
        if(!pendingAttachments[type].length) continue;
        const fd=new FormData();fd.append('attachment_type',type);
        pendingAttachments[type].forEach(item=>fd.append('files[]',item.file));
        await fetch(`/api/schedules/${scheduleId}/attachments`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF},body:fd});
    }
}
async function loadExistingAttachments(scheduleId){
    existingAttachments={quote:[],reference:[],room:[]};
    try{const res=await fetch(`/api/schedules/${scheduleId}/attachments`);if(res.ok){const list=await res.json();list.forEach(a=>{if(existingAttachments[a.attachment_type])existingAttachments[a.attachment_type].push(a);});}}catch(e){}
    ['quote','reference','room'].forEach(t=>renderImgGrid(t));
}
function resetAttachments(){
    pendingAttachments={quote:[],reference:[],room:[]};existingAttachments={quote:[],reference:[],room:[]};
    ['quote','reference','room'].forEach(t=>renderImgGrid(t));
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
    ['g_nickname','g_name','g_phone','g_platform_etc','g_source_ref','g_topic_etc','g_budget_etc','g_equipment','g_req_topic_etc','g_req_detail','g_special','g_estimate_amount','g_balance_amount','t_remote_name','t_remote_platform','t_remote_content','t_studio_name','t_studio_platform','t_studio_content','t_desc','commonName','commonDesc','modalLocation','modalAddress','schedAfterReason'].forEach(id=>{const el=document.getElementById(id);if(el) el.value='';});
    // 의뢰자/프로젝트/견적서/잠금/잔금
    linkedClientId=null;linkedProjectId=null;
    document.getElementById('linkedClientInfo').style.display='none';
    document.getElementById('projectSelectWrap').style.display='none';
    document.getElementById('clientSearchInput').value='';
    linkedEstimateId=null;
    document.getElementById('linkedEstimateInfo').style.display='none';
    isLocked=false; document.getElementById('lockBtn').textContent='🔓'; document.getElementById('lockBtn').classList.remove('locked');
    document.getElementById('lockedBanner').classList.remove('visible');
    document.getElementById('balanceBanner').classList.remove('visible');
    isAllDay=false; document.getElementById('alldayTrack').classList.remove('on');
    document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='');
    document.getElementById('notifSelect').value='60';
    // 입력 재활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input').forEach(el=>{el.disabled=false;});
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='';});
    resetAttachments();
    assigneePanelOpen=false;
    document.getElementById('assigneeList').style.display='none';
}

// ── 모달 열기 ──
function openNewModal(dateStr,timeStr){
    editingId=null; selectedAssignees=[]; viewMode=false;
    resetModalForm();
    setEditModeUI();
    setColor('gold');
    document.getElementById('modalTitle').value='';
    // 날짜
    document.getElementById('startDate').value=dateStr||'';
    document.getElementById('endDate').value=dateStr||'';
    document.getElementById('goldStartDate').value=dateStr||'';
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
    setTimeout(()=>document.getElementById('modalTitle').focus(),50);
}

// ── 상세 모달 ──
const COLOR_LABELS = {gold:'방문의뢰',teal:'원격/방송룸',blue:'사내업무',red:'휴가/개인',green:'촬영/스튜디오',purple:'미팅/내방',holiday:'공휴일'};
const FIELD_LABELS = {title:'제목',start_date:'시작일',end_date:'종료일',start_time:'시작시간',end_time:'종료시간',color:'유형',client_name:'의뢰자',address:'주소',location:'장소',description:'특이사항',is_locked:'잠금',is_private:'비공개',gold_data:'의뢰자정보',teal_data:'원격정보'};
let detailEvent = null;

let viewMode = false; // true: 상세보기(읽기전용), false: 편집

function setViewModeUI(){
    // 모든 입력 비활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input, #modalOverlay select').forEach(el=>{el.disabled=true;});
    document.querySelectorAll('#modalOverlay .time-picker-trigger').forEach(el=>{el.style.pointerEvents='none'; el.style.opacity='0.6';});
    document.querySelectorAll('#modalOverlay input[type="date"]').forEach(el=>{el.readOnly=true; el.style.pointerEvents='none'; el.style.opacity='0.6';});
    document.getElementById('alldayToggle').style.pointerEvents='none';
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='none';});
    document.querySelectorAll('#modalOverlay .radio-btn:not([data-always-active])').forEach(b=>{b.style.pointerEvents='none';});
    document.querySelectorAll('#modalOverlay .color-dot').forEach(b=>{b.style.pointerEvents='none';});
    document.querySelectorAll('#modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn').forEach(b=>{b.style.pointerEvents='none';});
    // 보기/해제 버튼은 항상 활성화
    document.querySelectorAll('#modalOverlay [data-always-active]').forEach(b=>{b.style.pointerEvents='auto';});
    // 잠금 배너 숨기기
    document.getElementById('lockedBanner').classList.remove('visible');
    isLocked=false;
    document.getElementById('lockBtn').textContent='🔓';
    document.getElementById('lockBtn').classList.remove('locked');
    // 버튼 전환
    document.getElementById('lockBtn').style.display='none';
    document.querySelector('.btn-save-top').style.display='none';
    document.getElementById('btnDelete').style.display='';
    document.getElementById('btnLog').style.display='';
    // 외부 버튼을 수정으로
    const extBtn=document.getElementById('modalExternalAction');
    extBtn.textContent='수정';
    extBtn.style.display='';
    extBtn.onclick=()=>{switchToEditMode();};
    // 푸터
    const saveBtn=document.querySelector('.modal-footer .btn-save');
    saveBtn.textContent='수정';
    saveBtn.onclick=()=>{switchToEditMode();};
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
    document.querySelectorAll('#modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn').forEach(b=>{b.style.pointerEvents='';});
    // 버튼 복원
    document.getElementById('lockBtn').style.display='';
    document.querySelector('.btn-save-top').style.display='';
    // 외부 버튼을 저장으로
    const extBtn=document.getElementById('modalExternalAction');
    extBtn.textContent='저장';
    extBtn.onclick=()=>{saveEvent();};
    // 푸터
    const saveBtn=document.querySelector('.modal-footer .btn-save');
    saveBtn.textContent='저장';
    saveBtn.onclick=()=>{saveEvent();};
}

function openDetailModal(ev) {
    if(isGuestUser) return;
    detailEvent = ev;
    viewMode = true;
    openEditModal(ev);
    // 읽기전용 UI 적용
    setTimeout(()=>setViewModeUI(),0);
}

function switchToEditMode() {
    viewMode = false;
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
    if (!detailEvent || !confirm('이 일정을 삭제하시겠습니까?')) return;
    deleteEvent(detailEvent.id);
    closeDetail();
}

async function openHistoryModal() {
    if (!detailEvent) return;
    document.getElementById('historyOverlay').style.display = 'flex';
    document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">로딩 중...</div>';

    const res = await fetch(`/api/events/${detailEvent.id}/history`, { headers:{'Accept':'application/json'} });
    if (!res.ok) { document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--red);">로드 실패</div>'; return; }
    const data = await res.json();

    if (!data.length) {
        document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">수정내역이 없습니다.</div>';
        return;
    }

    document.getElementById('historyBody').innerHTML = data.map(h => {
        const changes = h.changes || {};
        const rows = Object.entries(changes).map(([key, val]) => {
            const label = FIELD_LABELS[key] || key;
            const oldVal = typeof val.old === 'object' ? JSON.stringify(val.old) : (val.old ?? '—');
            const newVal = typeof val.new === 'object' ? JSON.stringify(val.new) : (val.new ?? '—');
            return `<div style="margin:6px 0;">
                <div style="font-size:11px; color:var(--text-muted);">${label}</div>
                <div style="display:flex; gap:6px; margin-top:2px;">
                    <span style="padding:2px 8px; border-radius:4px; background:#2a1a1a; color:var(--red); font-size:12px; text-decoration:line-through;">${oldVal}</span>
                    <span style="padding:2px 8px; border-radius:4px; background:#1a2a1a; color:var(--green); font-size:12px;">${newVal}</span>
                </div>
            </div>`;
        }).join('');

        return `<div style="padding:12px 0; border-bottom:1px solid var(--border);">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                <span style="font-size:10px; padding:2px 6px; border-radius:3px; background:var(--surface2); color:var(--accent); font-weight:600;">수정</span>
                <span style="font-size:12px; font-weight:600;">${h.user_name}</span>
                <span style="font-size:10px; color:var(--text-muted);">${h.created_at}</span>
            </div>
            ${rows}
        </div>`;
    }).join('');
}

// ── 편집 모달 ──
function openEditModal(ev){
    if(isGuestUser) return;
    editingId=ev.id; selectedAssignees=ev.assignees?ev.assignees.map(a=>a.id):[];
    resetModalForm();
    setColor(ev.color);
    document.getElementById('modalTitle').value=ev.title||'';
    // 날짜/시간
    const sd=(ev.start_date||'').substring(0,10), ed=(ev.end_date||'').substring(0,10);
    const st=ev.start_time||'13:00', et=ev.end_time||'14:00';
    document.getElementById('startDate').value=sd;document.getElementById('endDate').value=ed;
    document.getElementById('goldStartDate').value=sd;
    document.getElementById('startTime').value=st;document.getElementById('startTimeTrigger').textContent=st.substring(0,5);
    document.getElementById('endTime').value=et;document.getElementById('endTimeTrigger').textContent=et.substring(0,5);
    document.getElementById('goldStartTime').value=st;document.getElementById('goldStartTimeTrigger').textContent=st.substring(0,5);
    document.getElementById('goldEndTime').value=et;document.getElementById('goldEndTimeTrigger').textContent=et.substring(0,5);
    if(ev.is_all_day){isAllDay=true;document.getElementById('alldayTrack').classList.add('on');document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='none');}
    // 장소
    document.getElementById('modalLocation').value=ev.location||'';
    document.getElementById('modalAddress').value=ev.address||'';
    // 알림
    if(ev.notif_minutes!==null&&ev.notif_minutes!==undefined) document.getElementById('notifSelect').value=ev.notif_minutes;
    // 잠금
    if(ev.is_locked){isLocked=true;document.getElementById('lockBtn').textContent='🔒';document.getElementById('lockBtn').classList.add('locked');document.getElementById('lockedBanner').classList.add('visible');}
    // 날짜 배지
    const d=sd?new Date(sd):new Date();
    document.getElementById('modalDateBadge').textContent=`${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS_KO[d.getDay()]})`;
    // 일정옵션
    if(ev.sched_event_opts){const opts=Array.isArray(ev.sched_event_opts)?ev.sched_event_opts:[];opts.forEach(v=>{const b=document.querySelector(`#schedEventOpts [data-seopt="${v}"]`);if(b)b.classList.add('active');});if(opts.includes('after'))document.getElementById('schedReasonWrap').style.display='';}
    if(ev.sched_opt){const b=document.querySelector(`#scheduleOpts [data-sopt="${ev.sched_opt}"]`);if(b)b.classList.add('active');}
    if(ev.special_opts){const opts=Array.isArray(ev.special_opts)?ev.special_opts:[];opts.forEach(v=>{const b=document.querySelector(`#specialOpts [data-opt="${v}"]`);if(b)b.classList.add('active');});}
    if(ev.sched_after_reason) document.getElementById('schedAfterReason').value=ev.sched_after_reason;
    // 공통 필드
    document.getElementById('commonName').value=ev.client_name||'';
    document.getElementById('commonDesc').value=ev.description||'';
    // gold_data 복원 (Firebase 데이터 구조 호환)
    const g=ev.gold_data||{};
    document.getElementById('g_nickname').value=g.nickname||ev.client_name||'';
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
        const known=['소통','먹방','게임','야외','노래','주식/코인','기타'];
        const vals=g.topic.split(',').map(v=>v.trim());
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
    document.getElementById('g_special').value=g.special||'';
    if(g.specialReason) document.getElementById('specialReason').value=g.specialReason;
    if(g.paid) setRadio('g_paid_group',g.paid);
    document.getElementById('g_estimate_amount').value=g.estimate_amount||'';
    if(g.order){setRadio('g_order_group',g.order);if(g.order==='O')document.getElementById('g_delivery_wrap').style.display='';handleConditional('g_order_group');}
    if(g.delivery) setRadio('g_delivery_group',g.delivery);
    if(g.balance){setRadio('g_balance_group',g.balance);handleConditional('g_balance_group');}
    document.getElementById('g_balance_amount').value=g.balance_amount||'';
    if(g.estimate_id){linkedEstimateId=g.estimate_id;document.getElementById('linkedEstimateTitle').textContent=`#${g.estimate_id}`;document.getElementById('linkedEstimateInfo').style.display='';}
    // 의뢰자/프로젝트 연결 복원
    if(g.client_id){
        linkedClientId=g.client_id;
        document.getElementById('linkedClientName').textContent=g.nickname||g.name||`의뢰자 #${g.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        document.getElementById('linkedClientLink').href='/clients/'+g.client_id;
        linkedProjectId=g.project_id||null;
        loadClientProjects(g.client_id);
    }
    // 비-gold에서도 gold_data에 저장된 의뢰자 연결 복원
    if(!g.client_id && ev.gold_data && ev.gold_data.client_id){
        linkedClientId=ev.gold_data.client_id;
        document.getElementById('linkedClientName').textContent=`의뢰자 #${ev.gold_data.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        document.getElementById('linkedClientLink').href='/clients/'+ev.gold_data.client_id;
        linkedProjectId=ev.gold_data.project_id||null;
        loadClientProjects(ev.gold_data.client_id);
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
    pendingAttachments={quote:[],reference:[],room:[]};
    loadExistingAttachments(ev.id);
    // UI
    document.getElementById('btnDelete').style.display='';
    document.getElementById('btnLog').style.display='';
    updateAssigneeBtn();updateBalanceBanner();
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(){
    document.getElementById('modalOverlay').classList.remove('open');editingId=null;
    document.querySelectorAll('.time-picker-popup').forEach(p=>p.remove());
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

async function saveEvent(){
    const isGold=currentColor==='gold';
    const sd=isGold?document.getElementById('goldStartDate').value:document.getElementById('startDate').value;
    const ed=isGold?document.getElementById('goldStartDate').value:document.getElementById('endDate').value;
    const st=isGold?document.getElementById('goldStartTime').value:document.getElementById('startTime').value;
    const et=isGold?document.getElementById('goldEndTime').value:document.getElementById('endTime').value;
    if(!sd){alert('시작일을 입력하세요.');return;}

    // schedEventOpts 수집
    const schedEventOpts=[...document.querySelectorAll('#schedEventOpts .special-opt-btn.active')].map(b=>b.dataset.seopt);
    const schedOpt=(()=>{const a=document.querySelector('#scheduleOpts .sched-opt-btn.active');return a?a.dataset.sopt:null;})();
    const specialOpts=[...document.querySelectorAll('#specialOpts .special-opt-btn.active')].map(b=>b.dataset.opt);

    const data={
        title:document.getElementById('modalTitle').value.trim()||'(제목 없음)',
        start_date:sd, end_date:ed||sd, start_time:isAllDay?null:st, end_time:isAllDay?null:et,
        is_all_day:isAllDay, color:currentColor,
        client_name:isGold?document.getElementById('g_nickname').value.trim():document.getElementById('commonName').value.trim(),
        address:document.getElementById('modalAddress').value.trim(),
        location:document.getElementById('modalLocation').value.trim(),
        description:isGold?'':document.getElementById('commonDesc').value.trim(),
        assignees:selectedAssignees,
        notif_minutes:document.getElementById('notifSelect').value||null,
        is_locked:isLocked,
        sched_opt:schedOpt,
        sched_event_opts:schedEventOpts,
        special_opts:specialOpts,
        sched_after_reason:document.getElementById('schedAfterReason').value.trim()||null,
        gold_data:isGold?collectGoldFields():(linkedClientId?{client_id:linkedClientId,project_id:document.getElementById('projectSelect')?.value||null,nickname:'',name:'',phone:''}:null),
        teal_data:currentColor==='teal'?collectTealFields():null,
    };

    const url=editingId?`/api/events/${editingId}`:'/api/events';
    const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(data)});
    if(res.ok){
        const saved=await res.json();
        const hasFiles=Object.values(pendingAttachments).some(arr=>arr.length);
        if(hasFiles) await uploadPendingAttachments(saved.id);
        closeModal();loadEvents();
    }else{const err=await res.json();alert('저장 실패: '+JSON.stringify(err));}
}

async function deleteEvent(id){
    const delId=id||editingId;
    if(!delId||!confirm('이 일정을 삭제할까요?')) return;
    const res=await fetch(`/api/events/${delId}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}});
    if(res.ok){closeModal();loadEvents();}
}

function searchCalAddr(){
    new daum.Postcode({oncomplete:function(data){
        const addr=data.roadAddress||data.jibunAddress;
        document.getElementById('modalAddress').value=addr;
        const loc=document.getElementById('modalLocation');
        loc.value=loc.value?loc.value+'\n'+addr:addr;
    }}).open();
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
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeDetail();document.getElementById('historyOverlay').style.display='none';}});

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

function openLightbox(src,filename,images,idx){
    lightboxImages=images||[{src,filename}];
    lightboxIdx=idx||0;
    lbResetZoom();
    document.getElementById('lightboxImg').src=lightboxImages[lightboxIdx].src;
    document.getElementById('lightboxFilename').textContent=lightboxImages[lightboxIdx].filename||'';
    document.getElementById('lightbox').classList.add('open');
    document.querySelector('.lightbox-nav.prev').style.display=lightboxImages.length>1?'':'none';
    document.querySelector('.lightbox-nav.next').style.display=lightboxImages.length>1?'':'none';
}
function closeLightbox(){ document.getElementById('lightbox').classList.remove('open'); lbResetZoom(); }
function lightboxNav(dir){
    lightboxIdx=(lightboxIdx+dir+lightboxImages.length)%lightboxImages.length;
    lbResetZoom();
    document.getElementById('lightboxImg').src=lightboxImages[lightboxIdx].src;
    document.getElementById('lightboxFilename').textContent=lightboxImages[lightboxIdx].filename||'';
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
    const img=e.target.closest('.img-item img');
    if(!img) return;
    e.preventDefault();
    const grid=img.closest('.img-grid');
    if(!grid) { openLightbox(img.src,img.alt||''); return; }
    const allImgs=[...grid.querySelectorAll('.img-item img')].map(i=>({src:i.src,filename:i.alt||i.closest('.img-item')?.querySelector('.img-filename')?.textContent||''}));
    const idx=[...grid.querySelectorAll('.img-item img')].indexOf(img);
    openLightbox(img.src,'',allImgs,idx);
});

init();
</script>
@endpush
