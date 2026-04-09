@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '캘린더 - 닥터고블린 오피스')

@push('styles')
<style>
    :root {
        --gold: #c8b08a;
        --teal: #e8894a;
        --blue: #8ab4c8;
        --red: #c87a7a;
        --green: #7ac87a;
        --purple: #9b70c8;
    }

    .cal-header { padding:12px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); background:var(--surface); }
    .cal-header-left { display:flex; align-items:center; gap:10px; }
    .month-nav { display:flex; align-items:center; gap:8px; }
    .month-nav button { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:all 0.15s; }
    .month-nav button:hover { background:var(--surface2); color:var(--text); }
    .month-title { font-size:15px; font-weight:700; min-width:160px; text-align:center; }
    .nav-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 12px; border-radius:6px; font-size:12px; cursor:pointer; transition:all 0.15s; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); }

    .view-tabs { display:flex; background:var(--surface2); border-radius:8px; padding:2px; gap:2px; }
    .view-tab { padding:5px 14px; border-radius:6px; font-size:12px; cursor:pointer; border:none; background:none; color:var(--text-muted); transition:all 0.15s; }
    .view-tab.active { background:var(--surface); color:var(--accent); font-weight:600; }

    .add-btn { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:filter 0.15s; }
    .add-btn:hover { filter:brightness(1.1); }

    .legend { padding:8px 20px; display:flex; gap:12px; flex-wrap:wrap; border-bottom:1px solid var(--border); background:var(--surface); }
    .legend-item { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--text-muted); }
    .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

    /* ── 월간 뷰 ── */
    .calendar-wrap { padding:16px 20px; }
    .weekdays { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; margin-bottom:4px; }
    .weekday { text-align:center; font-size:11px; color:var(--text-muted); padding:6px 0; letter-spacing:0.08em; }
    .weekday:first-child { color:var(--red); }
    .weekday:last-child { color:var(--blue); }
    .days-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:var(--border); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
    .day-cell { background:var(--bg); min-height:100px; padding:6px; cursor:pointer; transition:background 0.1s; }
    .day-cell:hover { background:var(--surface2); }
    .day-cell.other-month { opacity:0.35; }
    .day-cell.today .day-num { background:var(--accent); color:#1a1207; border-radius:50%; }
    .day-num { font-size:12px; color:var(--text-muted); margin-bottom:4px; width:22px; height:22px; display:flex; align-items:center; justify-content:center; }
    .day-num.sun { color:var(--red); }
    .day-num.sat { color:var(--blue); }

    /* ── 이벤트 칩 ── */
    .event-chip { font-size:11px; padding:2px 6px; border-radius:4px; margin-bottom:2px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:filter 0.1s; }
    .event-chip:hover { filter:brightness(1.15); }
    .event-chip.color-gold   { background:var(--gold); color:#1a1207; }
    .event-chip.color-teal   { background:var(--teal); color:#1a1207; }
    .event-chip.color-blue   { background:var(--blue); color:#1a1207; }
    .event-chip.color-red    { background:var(--red); color:#fff; }
    .event-chip.color-green  { background:var(--green); color:#1a1207; }
    .event-chip.color-purple { background:var(--purple); color:#fff; }
    .event-chip.color-holiday{ background:var(--red); color:#fff; }
    .more-events { font-size:10px; color:var(--text-muted); padding:1px 4px; cursor:pointer; }

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
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:660px; max-height:92vh; overflow-y:auto; animation:modalIn 0.22s ease; }
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
    .btn-log { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 14px; border-radius:8px; font-size:12px; cursor:pointer; transition:all 0.2s; display:none; }
    .btn-log:hover { border-color:var(--accent); color:var(--accent); }

    /* ── 섹션/필드 ── */
    .section-heading { font-size:10px; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-muted); display:flex; align-items:center; gap:10px; margin-bottom:2px; }
    .section-heading::after { content:''; flex:1; height:1px; background:var(--border); }
    .divider { height:1px; background:var(--border); margin:2px 0; }
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

    /* ── gold/teal 조건부 ── */
    .gold-only, .teal-only, .common-only { display:none; }
</style>
@endpush

@section('content')
<div class="cal-header">
    <div class="cal-header-left">
        <div class="month-nav">
            <button onclick="changePeriod(-1)">‹</button>
            <div class="month-title" id="periodTitle"></div>
            <button onclick="changePeriod(1)">›</button>
        </div>
        <button class="nav-btn" onclick="goToday()">오늘</button>
        <div class="view-tabs">
            <button class="view-tab active" id="tabMonth" onclick="switchView('month')">월간</button>
            <button class="view-tab"        id="tabWeek"  onclick="switchView('week')">주간</button>
            <button class="view-tab"        id="tabDay"   onclick="switchView('day')">일간</button>
        </div>
    </div>
    @if(Auth::user()->hasPermission('calendar.edit'))
        <button class="add-btn" onclick="openNewModal()">+ 일정</button>
    @endif
</div>

<div class="legend">
    <div class="legend-item"><div class="legend-dot" style="background:var(--gold)"></div>방문의뢰</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--teal)"></div>원격/방송룸</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div>사내업무</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>휴가/개인</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div>촬영/스튜디오</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--purple)"></div>미팅/내방</div>
</div>

<!-- 월간 뷰 -->
<div id="monthView">
    <div class="calendar-wrap">
        <div class="weekdays">
            <div class="weekday">일</div><div class="weekday">월</div><div class="weekday">화</div>
            <div class="weekday">수</div><div class="weekday">목</div><div class="weekday">금</div><div class="weekday">토</div>
        </div>
        <div class="days-grid" id="daysGrid"></div>
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
    <div class="modal">
        <div class="modal-strip" id="modalStrip"></div>
        <div id="lockedBanner" class="locked-banner">🔒 내용이 고정되어 있습니다. 잠금을 해제해야 수정할 수 있습니다.</div>
        <div id="balanceBanner" class="balance-banner"><span>💰</span><span id="balanceBannerText">잔금 있음</span></div>
        <div class="modal-header">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="modal-date-badge" id="modalDateBadge"></span>
                    <span class="type-badge gold" id="typeBadge">● 방문의뢰</span>
                </div>
                <div class="color-row" id="colorRow">
                    <div class="color-dot active" data-color="gold">방문의뢰</div>
                    <div class="color-dot" data-color="teal">원격/방송룸</div>
                    <div class="color-dot" data-color="blue">사내업무</div>
                    <div class="color-dot" data-color="red">휴가/개인</div>
                    <div class="color-dot" data-color="green">촬영/스튜디오</div>
                    <div class="color-dot" data-color="purple">미팅/내방</div>
                </div>
                <textarea class="modal-title-input" id="modalTitle" placeholder="일정 제목을 입력하세요" rows="1"></textarea>
                <button class="assignee-btn" id="assigneeBtn" onclick="toggleAssigneePanel()">
                    <span id="assigneeBtnIcon">👤</span>
                    <span id="assigneeBtnLabel">담당자 지정</span>
                </button>
                <div class="assignee-list" id="assigneeList" style="display:none;margin-top:8px;"></div>
            </div>
            <div class="modal-header-btns">
                <button class="icon-btn" id="lockBtn" onclick="toggleLock()" title="내용 고정">🔓</button>
                <button class="btn-save-top" onclick="saveEvent()">저장</button>
                <button class="icon-btn close-btn" onclick="closeModal()">✕</button>
            </div>
        </div>

        <div class="modal-body">
            {{-- 장소 --}}
            <div class="field-group">
                <label class="field-label">장소</label>
                <textarea class="field-textarea" id="modalLocation" placeholder="장소를 입력하세요" rows="2" style="min-height:50px;"></textarea>
                <div style="display:flex;gap:6px;margin-top:6px;">
                    <button type="button" class="radio-btn" onclick="searchCalAddr()">🔍 주소 검색</button>
                </div>
                <input type="hidden" id="modalAddress" value="">
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
                <div id="goldDtRow" style="display:none;align-items:center;gap:6px;">
                    <input class="dt-input" type="date" id="goldStartDate">
                    <input type="hidden" id="goldStartTime" value="13:00">
                    <div class="time-picker-trigger dt-input" id="goldStartTimeTrigger" onclick="openTimePicker(this,'goldStartTime')">13:00</div>
                    <span style="color:var(--text-muted);font-size:13px;">~</span>
                    <input type="hidden" id="goldEndTime" value="14:00">
                    <div class="time-picker-trigger dt-input" id="goldEndTimeTrigger" onclick="openTimePicker(this,'goldEndTime')">14:00</div>
                </div>
            </div>

            {{-- 알림 --}}
            <div class="field-group">
                <label class="field-label">🔔 알림</label>
                <div class="notif-row">
                    <select class="notif-select" id="notifSelect">
                        <option value="">알림 없음</option>
                        <option value="0">정시 (일정 시작 시간)</option>
                        <option value="5">5분 전</option>
                        <option value="10">10분 전</option>
                        <option value="15">15분 전</option>
                        <option value="30">30분 전</option>
                        <option value="60" selected>1시간 전</option>
                        <option value="120">2시간 전</option>
                        <option value="1440">하루 전 오전 9시</option>
                    </select>
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
                    <input class="field-input" id="schedAfterReason" placeholder="사유 (선택)">
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">일정 관련 옵션</div>
                <div class="special-opts" id="scheduleOpts">
                    <div class="sched-opt-btn" data-sopt="suggest"><span class="opt-icon">💬</span>제안</div>
                    <div class="sched-opt-btn" data-sopt="hope"><span class="opt-icon">🙏</span>희망</div>
                    <div class="sched-opt-btn" data-sopt="target"><span class="opt-icon">🎯</span>목표</div>
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">특수 옵션</div>
                <div class="special-opts" id="specialOpts">
                    <div class="special-opt-btn" data-opt="car"><span class="opt-icon">🚗</span>차량 이용 필요</div>
                    <div class="special-opt-btn" data-opt="product"><span class="opt-icon">💼</span>들고 갈 제품 있음</div>
                    <div class="special-opt-btn" data-opt="two_person"><span class="opt-icon">👥</span>2인필수 작업</div>
                    <div class="special-opt-btn" data-opt="ladder"><span class="opt-icon">▤</span>사다리 필요</div>
                </div>
            </div>

            <div class="divider"></div>

            {{-- 공통 필드 (비-gold/비-teal) --}}
            <div class="common-only">
                <div class="field-group">
                    <label class="field-label">이름 / 담당자</label>
                    <input class="field-input" id="commonName" placeholder="이름을 입력하세요">
                </div>
                <div class="field-group">
                    <label class="field-label">상세 설명</label>
                    <textarea class="field-textarea" id="commonDesc" placeholder="상세 내용을 입력하세요"></textarea>
                </div>
            </div>

            {{-- Gold 템플릿 (방문의뢰) --}}
            <div class="gold-only">
                <div class="section-heading">의뢰자 정보</div>
                {{-- 의뢰자 검색/연결 --}}
                <div class="field-group">
                    <label class="field-label">의뢰자 검색</label>
                    <div style="position:relative;">
                        <input class="field-input" id="clientSearchInput" placeholder="이름/닉네임/전화번호로 검색" autocomplete="off" oninput="searchClients(this.value)">
                        <div id="clientSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--surface2);border:1px solid var(--border);border-radius:0 0 8px 8px;max-height:200px;overflow-y:auto;z-index:10;"></div>
                    </div>
                </div>
                <div id="linkedClientInfo" style="display:none;padding:10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <span style="font-size:11px;color:var(--text-muted);">연결된 의뢰자</span>
                            <div style="font-size:13px;font-weight:600;" id="linkedClientName"></div>
                        </div>
                        <button type="button" onclick="unlinkClient()" style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
                    </div>
                </div>
                {{-- 프로젝트 선택 --}}
                <div id="projectSelectWrap" style="display:none;" class="field-group">
                    <label class="field-label">프로젝트 연결</label>
                    <select class="field-input" id="projectSelect" style="cursor:pointer;">
                        <option value="">프로젝트 선택 (선택사항)</option>
                    </select>
                </div>
                <div class="field-row" style="gap:10px;">
                    <div class="field-group"><label class="field-label">의뢰자 닉네임</label><input class="field-input" id="g_nickname" placeholder="닉네임"></div>
                    <div class="field-group"><label class="field-label">의뢰자 이름</label><input class="field-input" id="g_name" placeholder="이름"></div>
                    <div class="field-group"><label class="field-label">전화번호</label><input class="field-input" id="g_phone" placeholder="010-0000-0000"></div>
                </div>

                <div class="field-group">
                    <label class="field-label">플랫폼</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <div class="radio-group" id="g_platform_group">
                            <div class="radio-btn" data-val="SOOP">SOOP</div>
                            <div class="radio-btn" data-val="치지직">치지직</div>
                            <div class="radio-btn" data-val="유튜브">유튜브</div>
                            <div class="radio-btn" data-val="틱톡">틱톡</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                        </div>
                        <div class="conditional-field" id="g_platform_etc_wrap"><input class="field-input" id="g_platform_etc" placeholder="직접 입력"></div>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field-group">
                        <label class="field-label">경력 여부</label>
                        <div class="radio-group" id="g_career_group">
                            <div class="radio-btn active" data-val="처음">처음</div>
                            <div class="radio-btn" data-val="초보">초보</div>
                            <div class="radio-btn" data-val="경력">경력</div>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label">유입 경로</label>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <div class="radio-group" id="g_source_group">
                                <div class="radio-btn" data-val="광고">📢 광고</div>
                                <div class="radio-btn" data-val="검색">🔍 검색</div>
                                <div class="radio-btn" data-val="소개">🤝 소개</div>
                            </div>
                            <div class="conditional-field" id="g_source_ref_wrap"><input class="field-input" id="g_source_ref" placeholder="소개해 준 분 이름"></div>
                        </div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">방송 주제</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <div class="radio-group" id="g_topic_group">
                            <div class="radio-btn" data-val="소통">소통</div>
                            <div class="radio-btn" data-val="먹방">먹방</div>
                            <div class="radio-btn" data-val="게임">게임</div>
                            <div class="radio-btn" data-val="야외">야외</div>
                            <div class="radio-btn" data-val="노래">노래</div>
                            <div class="radio-btn" data-val="주식/코인">주식/코인</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                        </div>
                        <div class="conditional-field" id="g_topic_etc_wrap"><input class="field-input" id="g_topic_etc" placeholder="직접 입력"></div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">예산 성향</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <div class="radio-group" id="g_budget_group">
                            <div class="radio-btn" data-val="풍족">풍족</div>
                            <div class="radio-btn" data-val="부족">부족</div>
                            <div class="radio-btn" data-val="모름">모름</div>
                            <div class="radio-btn" data-val="직접입력">직접입력</div>
                        </div>
                        <div class="conditional-field" id="g_budget_etc_wrap"><input class="field-input" id="g_budget_etc" placeholder="예산 직접 입력"></div>
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
                    <div class="field-group">
                        <div class="field-label">결제 여부</div>
                        <div class="radio-group" id="g_paid_group">
                            <div class="radio-btn active" data-val="미결제">미결제</div>
                            <div class="radio-btn" data-val="결제완료">결제완료</div>
                        </div>
                    </div>
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">견적 총액</label>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input class="field-input" id="g_estimate_amount" placeholder="금액 입력" type="text">
                            <button type="button" onclick="extractEstimateAmount()" style="background:none;border:1px solid var(--border);color:var(--text-muted);border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;white-space:nowrap;">🔍 추출</button>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                    <div class="field-group">
                        <div class="field-label">주문 제품</div>
                        <div class="radio-group" id="g_order_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" id="g_delivery_wrap" style="display:none;">
                        <div class="field-label">배송완료</div>
                        <div class="radio-group" id="g_delivery_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">잔금 여부</div>
                        <div class="radio-group" id="g_balance_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" id="g_balance_amount_wrap" style="flex:1;">
                        <div class="conditional-field" id="g_balance_cond">
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
                        <button type="button" onclick="triggerAttach('quote')" class="radio-btn" style="flex:1;text-align:center;">견적서 첨부</button>
                        <button type="button" onclick="openEstimateSearch()" class="radio-btn" style="flex:1;text-align:center;">견적서 불러오기</button>
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
                                <button type="button" onclick="openLinkedEstimate()" class="radio-btn" style="font-size:11px;padding:3px 10px;">보기</button>
                                <button type="button" onclick="unlinkEstimate()" style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
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
            <div class="teal-only">
                <div class="section-heading">원격/방송룸 정보</div>
                <div class="field-group">
                    <label class="field-label">유형 선택</label>
                    <div class="radio-group" id="teal_mode_group">
                        <div class="radio-btn active" data-val="remote">🖥 원격</div>
                        <div class="radio-btn" data-val="studio">🎙 방송룸 이용</div>
                    </div>
                </div>
                <div id="teal_remote_fields">
                    <div class="field-row">
                        <div class="field-group"><label class="field-label">원격 대상자 이름(닉네임)</label><input class="field-input" id="t_remote_name" placeholder="이름 또는 닉네임"></div>
                        <div class="field-group"><label class="field-label">방송 플랫폼</label><input class="field-input" id="t_remote_platform" placeholder="유튜브, SOOP 등"></div>
                    </div>
                    <div class="field-group"><label class="field-label">원격 의뢰 내용</label><textarea class="field-textarea" id="t_remote_content" placeholder="원격으로 진행할 내용을 입력하세요"></textarea></div>
                </div>
                <div id="teal_studio_fields" style="display:none;">
                    <div class="field-row">
                        <div class="field-group"><label class="field-label">방송룸 이용자 이름(닉네임)</label><input class="field-input" id="t_studio_name" placeholder="이름 또는 닉네임"></div>
                        <div class="field-group"><label class="field-label">방송 플랫폼</label><input class="field-input" id="t_studio_platform" placeholder="유튜브, SOOP 등"></div>
                    </div>
                    <div class="field-group"><label class="field-label">방송룸 이용 내용</label><textarea class="field-textarea" id="t_studio_content" placeholder="방송룸 이용 내용을 입력하세요"></textarea></div>
                </div>
                <div class="field-group"><label class="field-label">메모 (선택)</label><textarea class="field-textarea" id="t_desc" placeholder="추가 메모를 입력하세요"></textarea></div>
            </div>

        </div>{{-- modal-body end --}}

        <div class="modal-footer">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">일정 삭제</button>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn-log" id="btnLog" onclick="openHistoryFromEdit()">📋 변경 로그</button>
                <button class="btn-save" onclick="saveEvent()">저장</button>
            </div>
        </div>
    </div>
</div>
<!-- 견적서 검색 모달 -->
<div class="modal-overlay" id="estimateSearchOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:500px; max-height:70vh; overflow-y:auto;">
        <div class="modal-header">
            <div class="modal-title">견적서 불러오기</div>
            <button class="modal-close" onclick="document.getElementById('estimateSearchOverlay').style.display='none'">×</button>
        </div>
        <div class="field-group">
            <input class="field-input" id="estimateSearchInput" type="text" placeholder="의뢰자명 또는 견적서 번호로 검색" oninput="searchEstimates(this.value)">
        </div>
        <div id="estimateSearchResults" style="max-height:400px; overflow-y:auto;">
            <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">검색어를 입력하세요</div>
        </div>
    </div>
</div>

<!-- 일정 상세 모달 (조회전용) -->
<div class="modal-overlay" id="detailOverlay" style="display:none;" onclick="if(event.target===this) closeDetail()">
    <div class="modal" style="max-width:600px; max-height:85vh; overflow-y:auto;">
        <div id="detailHeader" style="margin-bottom:16px;">
            <div style="font-size:11px; color:var(--text-muted);" id="detailDateType"></div>
            <div style="font-size:18px; font-weight:700; margin-top:4px;" id="detailTitle"></div>
        </div>
        <div id="detailBody"></div>
        <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:14px; border-top:1px solid var(--border);">
            <div style="display:flex; gap:6px;">
                <button class="field-input" style="width:auto; padding:6px 14px; cursor:pointer; font-size:12px; color:var(--red); border-color:var(--red);" onclick="deleteEventFromDetail()">삭제</button>
                <button class="field-input" style="width:auto; padding:6px 14px; cursor:pointer; font-size:12px;" onclick="openHistoryModal()">수정내역</button>
            </div>
            <div style="display:flex; gap:6px;">
                <button class="field-input" style="width:auto; padding:6px 14px; cursor:pointer; font-size:12px; color:var(--accent); border-color:var(--accent);" onclick="editFromDetail()">수정</button>
                <button class="field-input" style="width:auto; padding:6px 14px; cursor:pointer; font-size:12px;" onclick="closeDetail()">닫기</button>
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

// ── 뷰 전환 ─────────────────────────────────────────────────────
function switchView(view) {
    currentView = view;
    ['month','week','day'].forEach(v => {
        document.getElementById(`tab${v.charAt(0).toUpperCase()+v.slice(1)}`).classList.toggle('active', v===view);
    });
    document.getElementById('monthView').style.display    = view==='month' ? '' : 'none';
    document.getElementById('timelineView').style.display = view!=='month' ? '' : 'none';
    renderView();
    loadEvents();
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
    const months=['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'];
    document.getElementById('periodTitle').textContent=`${currentYear}년 ${months[currentMonth]}`;
    const grid=document.getElementById('daysGrid'); grid.innerHTML='';
    const firstDay=new Date(currentYear,currentMonth,1).getDay();
    const lastDate=new Date(currentYear,currentMonth+1,0).getDate();
    const prevLast=new Date(currentYear,currentMonth,0).getDate();
    const ts=todayStr();
    let cells=[];
    for(let i=firstDay-1;i>=0;i--) cells.push({date:prevLast-i,month:'prev',full:null});
    for(let d=1;d<=lastDate;d++){
        const full=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({date:d,month:'cur',full});
    }
    const rem=42-cells.length;
    for(let d=1;d<=rem;d++) cells.push({date:d,month:'next',full:null});
    cells.forEach((cell,idx)=>{
        const div=document.createElement('div');
        div.className='day-cell'+(cell.month!=='cur'?' other-month':'');
        if(cell.full===ts) div.classList.add('today');
        const dow=idx%7;
        const nc=dow===0?'sun':dow===6?'sat':'';
        div.innerHTML=`<div class="day-num ${nc}">${cell.date}</div>`;
        if(cell.full){
            const dayEvs=events.filter(ev=>ev.start_date<=cell.full&&(ev.end_date||ev.start_date)>=cell.full);
            dayEvs.slice(0,3).forEach(ev=>{
                const chip=document.createElement('div');
                chip.className=`event-chip color-${ev.color}`;
                chip.textContent=isGuestUser?(ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):''):((ev.client_name?ev.client_name+' ':'')+ev.title);
                chip.onclick=e=>{e.stopPropagation();openDetailModal(ev);};
                div.appendChild(chip);
            });
            if(dayEvs.length>3){
                const more=document.createElement('div');
                more.className='more-events';
                more.textContent=`+${dayEvs.length-3}개 더`;
                div.appendChild(more);
            }
            div.addEventListener('click',()=>openNewModal(cell.full));
        }
        grid.appendChild(div);
    });
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
    document.querySelectorAll('.gold-only').forEach(s=>s.style.display=c==='gold'?'':'none');
    document.querySelectorAll('.teal-only').forEach(s=>s.style.display=c==='teal'?'':'none');
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
        banner.classList.add('visible');
    }else{
        banner.classList.remove('visible');
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

function searchClients(query){
    clearTimeout(clientSearchTimer);
    const results=document.getElementById('clientSearchResults');
    if(!query.trim()||query.length<1){results.style.display='none';return;}
    clientSearchTimer=setTimeout(async()=>{
        const res=await fetch(`/api/clients/search?q=${encodeURIComponent(query)}`);
        if(!res.ok)return;
        const list=await res.json();
        if(!list.length){results.innerHTML='<div style="padding:10px;font-size:12px;color:var(--text-muted);text-align:center;">결과 없음</div>';results.style.display='';return;}
        results.innerHTML=list.map(c=>`<div style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--border);transition:background 0.1s;" onmouseover="this.style.background='var(--surface)'" onmouseout="this.style.background=''" onclick="selectClient(${c.id},'${(c.nickname||'').replace(/'/g,"\\'")}','${(c.name||'').replace(/'/g,"\\'")}','${(c.phone||'').replace(/'/g,"\\'")}')"><span style="font-weight:600;">${c.nickname||c.name}</span>${c.nickname&&c.name?' <span style="color:var(--text-muted);">('+c.name+')</span>':''} <span style="color:var(--text-muted);font-size:11px;margin-left:6px;">${c.phone||''}</span></div>`).join('');
        results.style.display='';
    },250);
}

async function selectClient(id,nickname,name,phone){
    linkedClientId=id;
    document.getElementById('clientSearchResults').style.display='none';
    document.getElementById('clientSearchInput').value='';
    document.getElementById('linkedClientName').textContent=(nickname||name)+(nickname&&name?' ('+name+')':'');
    document.getElementById('linkedClientInfo').style.display='';
    // 필드 자동채움
    document.getElementById('g_nickname').value=nickname||'';
    document.getElementById('g_name').value=name||'';
    document.getElementById('g_phone').value=phone||'';
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
        grid.innerHTML+=`<div class="img-item"><div class="img-thumb-wrap"><img src="${a.url}"><button class="img-remove" onclick="removeExistingAttach('${type}',${i},${a.id})">✕</button></div><div class="img-filename">${a.file_name||''}</div></div>`;
    });
    // 새로 추가된
    pendingAttachments[type].forEach((item,i)=>{
        const div=document.createElement('div');div.className='img-item';
        const wrap=document.createElement('div');wrap.className='img-thumb-wrap';
        const img=document.createElement('img');img.src=URL.createObjectURL(item.file);wrap.appendChild(img);
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
function openEstimateSearch(){
    document.getElementById('estimateSearchOverlay').style.display='flex';
    document.getElementById('estimateSearchInput').value='';
    document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">검색어를 입력하세요</div>';
    setTimeout(()=>document.getElementById('estimateSearchInput').focus(),50);
}
function searchEstimates(query){
    clearTimeout(estimateSearchTimer);
    if(!query.trim()){document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">검색어를 입력하세요</div>';return;}
    estimateSearchTimer=setTimeout(async()=>{
        const res=await fetch(`/api/estimates?search=${encodeURIComponent(query)}`);if(!res.ok)return;
        const data=await res.json();const list=data.data||data;
        if(!list.length){document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">결과 없음</div>';return;}
        document.getElementById('estimateSearchResults').innerHTML=list.map(e=>{
            const sm={created:'작성중',editing:'수정중',completed:'완료',paid:'결제완료',hold:'보류'};
            const amt=e.total_amount?Number(e.total_amount).toLocaleString()+'원':'';
            return `<div style="padding:10px 12px;border-bottom:1px solid var(--border);cursor:pointer;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''" onclick="selectEstimate(${e.id},'${(e.client_nickname||e.client_name||'').replace(/'/g,"\\'")}',${e.total_amount||0})"><div style="display:flex;justify-content:space-between;align-items:center;"><div><span style="font-size:13px;font-weight:600;">#${e.id}</span><span style="font-size:13px;margin-left:8px;">${e.client_nickname||e.client_name||'(이름없음)'}</span></div><div style="display:flex;gap:6px;align-items:center;"><span style="font-size:12px;color:var(--accent);">${amt}</span><span style="font-size:10px;padding:2px 6px;border-radius:4px;background:var(--surface2);color:var(--text-muted);">${sm[e.status]||e.status}</span></div></div></div>`;
        }).join('');
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
function openLinkedEstimate(){if(linkedEstimateId) window.open(`/estimates/${linkedEstimateId}/edit`,'_blank');}
function extractEstimateAmount(){
    if(!linkedEstimateId){alert('먼저 견적서를 불러와주세요.');return;}
    fetch(`/api/estimates?search=${linkedEstimateId}`).then(r=>r.json()).then(data=>{
        const list=data.data||data;const est=list.find(e=>e.id===linkedEstimateId);
        if(est&&est.total_amount) document.getElementById('g_estimate_amount').value=Number(est.total_amount).toLocaleString();
    });
}

function openHistoryFromEdit(){
    if(!editingId) return;
    detailEvent={id:editingId};
    openHistoryModal();
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
    editingId=null; selectedAssignees=[];
    resetModalForm();
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

function openDetailModal(ev) {
    if(isGuestUser) return;
    detailEvent = ev;
    const d = ev;
    const colorLabel = COLOR_LABELS[d.color] || d.color;
    const dateStr = d.start_date + (d.end_date && d.end_date !== d.start_date ? ' ~ ' + d.end_date : '');
    const timeStr = d.start_time ? d.start_time.substring(0,5) + (d.end_time ? ' ~ ' + d.end_time.substring(0,5) : '') : '';

    document.getElementById('detailDateType').textContent = dateStr + '  ' + colorLabel;
    document.getElementById('detailTitle').textContent = d.title || '(제목 없음)';

    let html = '';
    // 기본 정보
    html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">기본 정보</legend>`;
    html += infoRow('시작일', d.start_date) + infoRow('종료일', d.end_date);
    if (timeStr) html += infoRow('시간', timeStr);
    html += infoRow('분류', colorLabel);
    if (d.client_name) html += infoRow('이름/담당자', d.client_name);
    if (d.location) html += infoRow('장소', d.location);
    if (d.address) html += infoRow('주소', d.address);
    html += infoRow('잠금', d.is_locked ? '🔒 잠금됨' : '해제');
    if (d.assignees && d.assignees.length) html += infoRow('담당자', d.assignees.map(a => a.name).join(', '));
    html += `</fieldset>`;

    // gold_data (방문의뢰) — 값은 이제 한글로 저장됨
    const g = d.gold_data;
    if (g && Object.keys(g).length) {
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">의뢰자 정보</legend>`;
        if (g.client_id) html += infoRow('의뢰자', `<a href="/clients/${g.client_id}" target="_blank" style="color:var(--accent);">${g.nickname||g.name||'#'+g.client_id} 보기</a>`);
        if (g.project_id) html += infoRow('프로젝트', `<a href="/projects/${g.project_id}" target="_blank" style="color:var(--accent);">#${g.project_id} 보기</a>`);
        if (g.nickname) html += infoRow('닉네임', g.nickname);
        if (g.name) html += infoRow('이름', g.name);
        if (g.phone) html += infoRow('전화번호', g.phone);
        if (g.platform) html += infoRow('플랫폼', g.platform);
        if (g.career) html += infoRow('경력 여부', g.career);
        if (g.source) html += infoRow('유입 경로', g.source + (g.source_ref ? ' ('+g.source_ref+')' : ''));
        if (g.topic) html += infoRow('방송 주제', g.topic);
        if (g.budget) html += infoRow('예산 성향', g.budget + (g.budget_etc ? ' ('+g.budget_etc+')' : ''));
        html += `</fieldset>`;

        if (g.equipment) {
            html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">장비 목록</legend>`;
            html += `<div style="font-size:13px; white-space:pre-wrap;">${g.equipment}</div>`;
            html += `</fieldset>`;
        }

        if (g.request_topic || g.req_detail) {
            html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">의뢰 내용</legend>`;
            if (g.request_topic) html += infoRow('주제', g.request_topic + (g.req_topic_etc ? ' ('+g.req_topic_etc+')' : ''));
            if (g.req_detail) html += `<div style="font-size:13px; white-space:pre-wrap; margin-top:6px;">${g.req_detail}</div>`;
            html += `</fieldset>`;
        }

        if (g.special) {
            html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">특이사항</legend>`;
            html += `<div style="font-size:13px; white-space:pre-wrap; color:var(--accent);">${g.special}</div>`;
            html += `</fieldset>`;
        }

        // 결제 정보
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">결제 정보</legend>`;
        if (g.paid) html += infoRow('결제 여부', g.paid);
        if (g.estimate_amount) html += infoRow('견적 총액', g.estimate_amount);
        if (g.order) html += infoRow('주문 제품', g.order);
        if (g.order === 'O' && g.delivery) html += infoRow('배송완료', g.delivery);
        if (g.balance) html += infoRow('잔금 여부', g.balance);
        if (g.balance === 'O' && g.balance_amount) html += infoRow('잔금 금액', g.balance_amount);
        if (g.estimate_id) html += infoRow('견적서', `<a href="/estimates/${g.estimate_id}/edit" target="_blank" style="color:var(--accent);">#${g.estimate_id} 보기</a>`);
        html += `</fieldset>`;
    }

    // teal_data (원격/방송룸)
    const t = d.teal_data;
    if (t && Object.keys(t).length) {
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">원격/방송룸 정보</legend>`;
        if (t.mode) html += infoRow('모드', t.mode === 'remote' ? '원격' : '스튜디오');
        if (t.name) html += infoRow('이름', t.name);
        if (t.platform) html += infoRow('플랫폼', t.platform);
        if (t.content) html += infoRow('콘텐츠', t.content);
        if (t.desc) html += infoRow('세부', `<div style="white-space:pre-wrap;">${t.desc}</div>`);
        html += `</fieldset>`;
    }

    // 일정 옵션 표시
    const schedEvOpts = d.sched_event_opts || [];
    const schedOpt = d.sched_opt;
    const specOpts = d.special_opts || [];
    if (schedEvOpts.length || schedOpt || specOpts.length) {
        const SCHED_EV_L={fast:'← 빠른 일정',urgent:'🚨 긴급',after:'→ 날짜 이후'};
        const SCHED_L={suggest:'💬 제안',hope:'🙏 희망',target:'🎯 목표'};
        const SPEC_L={car:'🚗 차량',product:'💼 제품',two_person:'👥 2인',ladder:'▤ 사다리'};
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">일정 옵션</legend>`;
        if (schedEvOpts.length) html += infoRow('일정 옵션', schedEvOpts.map(v=>SCHED_EV_L[v]||v).join(', '));
        if (schedOpt) html += infoRow('일정 관련', SCHED_L[schedOpt]||schedOpt);
        if (specOpts.length) html += infoRow('특수 옵션', specOpts.map(v=>SPEC_L[v]||v).join(', '));
        if (d.sched_after_reason) html += infoRow('사유', d.sched_after_reason);
        html += `</fieldset>`;
    }

    // 비-gold 특이사항 (description)
    if (d.description && d.color !== 'gold') {
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">특이사항</legend>`;
        html += `<div style="font-size:13px; white-space:pre-wrap; color:var(--accent);">${d.description}</div>`;
        html += `</fieldset>`;
    }

    document.getElementById('detailBody').innerHTML = html;
    document.getElementById('detailOverlay').style.display = 'flex';
}

function infoRow(label, value) {
    return `<div style="display:flex; padding:5px 0; border-bottom:1px solid var(--border);">
        <div style="width:100px; font-size:11px; color:var(--text-muted); flex-shrink:0;">${label}</div>
        <div style="font-size:13px; flex:1;">${value || '—'}</div>
    </div>`;
}

function closeDetail() {
    document.getElementById('detailOverlay').style.display = 'none';
    detailEvent = null;
}

function editFromDetail() {
    if (!detailEvent) return;
    closeDetail();
    openEditModal(detailEvent);
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
    // gold_data 복원
    const g=ev.gold_data||{};
    document.getElementById('g_nickname').value=g.nickname||ev.client_name||'';
    document.getElementById('g_name').value=g.name||'';
    document.getElementById('g_phone').value=g.phone||'';
    if(g.platform){const vals=g.platform.split(',').map(v=>v.trim());setMultiRadio('g_platform_group',vals);if(vals.includes('기타'))document.getElementById('g_platform_etc').value=g.platform_etc||'';}
    if(g.career) setRadio('g_career_group',g.career);
    if(g.source){setRadio('g_source_group',g.source);if(g.source==='소개')document.getElementById('g_source_ref').value=g.source_ref||'';}
    if(g.topic){const vals=g.topic.split(',').map(v=>v.trim());setMultiRadio('g_topic_group',vals);if(vals.includes('기타'))document.getElementById('g_topic_etc').value=g.topic_etc||'';}
    if(g.budget){setRadio('g_budget_group',g.budget);if(g.budget==='직접입력')document.getElementById('g_budget_etc').value=g.budget_etc||'';}
    document.getElementById('g_equipment').value=g.equipment||'';
    if(g.request_topic){setRadio('g_req_topic_group',g.request_topic);if(g.request_topic==='기타')document.getElementById('g_req_topic_etc').value=g.req_topic_etc||'';}
    document.getElementById('g_req_detail').value=g.request_detail||g.req_detail||'';
    document.getElementById('g_special').value=g.special||'';
    if(g.paid) setRadio('g_paid_group',g.paid);
    document.getElementById('g_estimate_amount').value=g.estimate_amount||'';
    if(g.order) setRadio('g_order_group',g.order);
    if(g.delivery){document.getElementById('g_delivery_wrap').style.display='';setRadio('g_delivery_group',g.delivery);}
    if(g.balance){setRadio('g_balance_group',g.balance);if(g.balance==='O'){const cond=document.getElementById('g_balance_cond');if(cond)cond.classList.add('visible');}}
    document.getElementById('g_balance_amount').value=g.balance_amount||'';
    if(g.estimate_id){linkedEstimateId=g.estimate_id;document.getElementById('linkedEstimateTitle').textContent=`#${g.estimate_id}`;document.getElementById('linkedEstimateInfo').style.display='';}
    // 의뢰자/프로젝트 연결 복원
    if(g.client_id){
        linkedClientId=g.client_id;
        document.getElementById('linkedClientName').textContent=g.nickname||g.name||`의뢰자 #${g.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        linkedProjectId=g.project_id||null;
        loadClientProjects(g.client_id);
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
}

// ── 데이터 수집 ──
function collectGoldFields(){
    const platform=getMultiRadio('g_platform_group').join(', ');
    const topic=getMultiRadio('g_topic_group').join(', ');
    return {
        nickname:document.getElementById('g_nickname').value.trim(),
        name:document.getElementById('g_name').value.trim(),
        phone:document.getElementById('g_phone').value.trim(),
        platform, platform_etc:document.getElementById('g_platform_etc').value.trim(),
        career:getRadio('g_career_group'),
        source:getRadio('g_source_group'),source_ref:document.getElementById('g_source_ref').value.trim(),
        topic, topic_etc:document.getElementById('g_topic_etc').value.trim(),
        budget:getRadio('g_budget_group'),budget_etc:document.getElementById('g_budget_etc').value.trim(),
        equipment:document.getElementById('g_equipment').value.trim(),
        request_topic:getRadio('g_req_topic_group'),req_topic_etc:document.getElementById('g_req_topic_etc').value.trim(),
        req_detail:document.getElementById('g_req_detail').value.trim(),
        special:document.getElementById('g_special').value.trim(),
        paid:getRadio('g_paid_group'),
        estimate_amount:document.getElementById('g_estimate_amount').value.trim(),
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
        gold_data:isGold?collectGoldFields():null,
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
    ['g_career_group','g_source_group','g_budget_group','g_req_topic_group','g_paid_group','g_order_group','g_delivery_group','g_balance_group'].forEach(id=>initRadioGroup(id));
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

init();
</script>
@endpush
