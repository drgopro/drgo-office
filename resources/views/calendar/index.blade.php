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
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <div class="modal-title" id="modalTitleText">새 일정</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>

        {{-- 유형 선택 --}}
        <div class="form-section">
            <div class="section-label">유형</div>
            <div class="pill-group" id="typePills">
                <button class="type-pill active" data-color="gold" onclick="setColor(this)">방문의뢰</button>
                <button class="type-pill" data-color="teal" onclick="setColor(this)">원격</button>
                <button class="type-pill" data-color="blue" onclick="setColor(this)">사내업무</button>
                <button class="type-pill" data-color="red" onclick="setColor(this)">휴가</button>
                <button class="type-pill" data-color="green" onclick="setColor(this)">촬영</button>
                <button class="type-pill" data-color="purple" onclick="setColor(this)">미팅</button>
            </div>
        </div>

        {{-- 일정 옵션 --}}
        <div class="form-section">
            <div class="section-label">일정 옵션</div>
            <div class="field-group">
                <div class="field-label">일정 옵션</div>
                <div class="pill-group" id="schedOptPills">
                    <button class="pill-btn" data-val="quick" onclick="toggleSingle(this,'schedOptPills')">← 빠른 일정 희망</button>
                    <button class="pill-btn" data-val="urgent" onclick="toggleSingle(this,'schedOptPills')">긴급 일정</button>
                    <button class="pill-btn" data-val="after_date" onclick="toggleSingle(this,'schedOptPills')">→ 날짜 선택 이후 희망</button>
                </div>
            </div>
            <div id="afterDateFields" style="display:none;">
                <div class="field-row" style="margin-top:8px;">
                    <div class="field-group">
                        <div class="field-label">이후 날짜</div>
                        <input class="field-input" id="inputAfterDate" type="date">
                    </div>
                    <div class="field-group">
                        <div class="field-label">사유</div>
                        <input class="field-input" id="inputAfterReason" type="text" placeholder="사유 입력">
                    </div>
                </div>
            </div>
            <div class="field-group" style="margin-top:10px;">
                <div class="field-label">일정 관련 옵션</div>
                <div class="pill-group" id="schedEventOptPills">
                    <button class="pill-btn" data-val="suggest" onclick="toggleMulti(this)">제안</button>
                    <button class="pill-btn" data-val="hope" onclick="toggleMulti(this)">희망</button>
                    <button class="pill-btn" data-val="goal" onclick="toggleMulti(this)">목표</button>
                </div>
            </div>
            <div class="field-group" style="margin-top:10px;">
                <div class="field-label">특수 옵션</div>
                <div class="pill-group" id="specialOptPills">
                    <button class="pill-btn" data-val="car" onclick="toggleMulti(this)">차량 이용 필요</button>
                    <button class="pill-btn" data-val="product" onclick="toggleMulti(this)">들고 갈 제품 있음</button>
                    <button class="pill-btn" data-val="two_person" onclick="toggleMulti(this)">2인필수 작업</button>
                    <button class="pill-btn" data-val="ladder" onclick="toggleMulti(this)">사다리 필요</button>
                </div>
            </div>
        </div>

        <hr class="form-divider">

        {{-- 기본 정보 --}}
        <div class="form-section">
            <div class="section-label">기본 정보</div>
            <div class="field-group">
                <div class="field-label">제목</div>
                <input class="field-input" id="inputTitle" type="text" placeholder="일정 제목">
            </div>
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">시작일</div>
                    <input class="field-input" id="inputStartDate" type="date">
                </div>
                <div class="field-group">
                    <div class="field-label">종료일</div>
                    <input class="field-input" id="inputEndDate" type="date">
                </div>
            </div>
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">시작 시간</div>
                    <input class="field-input" id="inputStartTime" type="time" value="13:00">
                </div>
                <div class="field-group">
                    <div class="field-label">종료 시간</div>
                    <input class="field-input" id="inputEndTime" type="time" value="14:00">
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">담당자</div>
                <div class="assignee-list" id="assigneeList">
                    <div style="font-size:12px; color:var(--text-muted);">불러오는 중...</div>
                </div>
            </div>
        </div>

        <hr class="form-divider">

        {{-- 의뢰자 정보 (gold만) --}}
        <div class="gold-only">
            <div class="form-section">
                <div class="section-label">의뢰자 정보</div>
                <div class="field-row-3">
                    <div class="field-group">
                        <div class="field-label">의뢰자 닉네임</div>
                        <input class="field-input" id="inputClientName" type="text" placeholder="닉네임">
                    </div>
                    <div class="field-group">
                        <div class="field-label">의뢰자 이름</div>
                        <input class="field-input" id="inputGoldName" type="text" placeholder="이름">
                    </div>
                    <div class="field-group">
                        <div class="field-label">전화번호</div>
                        <input class="field-input" id="inputGoldPhone" type="tel" placeholder="010-0000-0000">
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">플랫폼</div>
                    <div class="pill-group" id="platformPills">
                        <button class="pill-btn" data-val="soop" onclick="toggleSingle(this,'platformPills')">SOOP</button>
                        <button class="pill-btn" data-val="chzzk" onclick="toggleSingle(this,'platformPills')">치지직</button>
                        <button class="pill-btn" data-val="youtube" onclick="toggleSingle(this,'platformPills')">유튜브</button>
                        <button class="pill-btn" data-val="tiktok" onclick="toggleSingle(this,'platformPills')">틱톡</button>
                        <button class="pill-btn" data-val="etc" onclick="toggleSingle(this,'platformPills')">기타</button>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-group">
                        <div class="field-label">경력 여부</div>
                        <div class="pill-group" id="careerPills">
                            <button class="pill-btn" data-val="first" onclick="toggleSingle(this,'careerPills')">처음</button>
                            <button class="pill-btn" data-val="beginner" onclick="toggleSingle(this,'careerPills')">초보</button>
                            <button class="pill-btn" data-val="experienced" onclick="toggleSingle(this,'careerPills')">경력</button>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">유입 경로</div>
                        <div class="pill-group" id="sourcePills">
                            <button class="pill-btn" data-val="ad" onclick="toggleSingle(this,'sourcePills')">광고</button>
                            <button class="pill-btn" data-val="search" onclick="toggleSingle(this,'sourcePills')">검색</button>
                            <button class="pill-btn" data-val="referral" onclick="toggleSingle(this,'sourcePills')">소개</button>
                        </div>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">방송 주제</div>
                    <div class="pill-group" id="topicPills">
                        <button class="pill-btn" data-val="talk" onclick="toggleSingle(this,'topicPills')">소통</button>
                        <button class="pill-btn" data-val="food" onclick="toggleSingle(this,'topicPills')">먹방</button>
                        <button class="pill-btn" data-val="game" onclick="toggleSingle(this,'topicPills')">게임</button>
                        <button class="pill-btn" data-val="outdoor" onclick="toggleSingle(this,'topicPills')">야외</button>
                        <button class="pill-btn" data-val="song" onclick="toggleSingle(this,'topicPills')">노래</button>
                        <button class="pill-btn" data-val="stock" onclick="toggleSingle(this,'topicPills')">주식/코인</button>
                        <button class="pill-btn" data-val="etc" onclick="toggleSingle(this,'topicPills')">기타</button>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">예산 성향</div>
                    <div class="pill-group" id="budgetPills">
                        <button class="pill-btn" data-val="plenty" onclick="toggleSingle(this,'budgetPills')">풍족</button>
                        <button class="pill-btn" data-val="lack" onclick="toggleSingle(this,'budgetPills')">부족</button>
                        <button class="pill-btn" data-val="unknown" onclick="toggleSingle(this,'budgetPills')">모름</button>
                        <button class="pill-btn" data-val="direct" onclick="toggleSingle(this,'budgetPills')">직접입력</button>
                    </div>
                </div>
            </div>

            <hr class="form-divider">

            {{-- 주소 --}}
            <div class="form-section">
                <div class="section-label">주소</div>
                <div style="display:flex; gap:8px; margin-bottom:6px;">
                    <input class="field-input" id="inputAddress" type="text" placeholder="우편번호 검색 버튼을 눌러주세요" readonly style="flex:1; cursor:pointer;" onclick="searchCalAddr()">
                    <button type="button" onclick="searchCalAddr()" style="background:var(--accent); color:#1a1207; border:none; padding:0 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">검색</button>
                </div>
                <input class="field-input" id="inputAddressDetail" type="text" placeholder="상세주소 (동/호수)">
            </div>

            <hr class="form-divider">

            {{-- 장비 목록 --}}
            <div class="form-section">
                <div class="section-label">장비 목록</div>
                <textarea class="field-input field-textarea" id="inputEquipment" rows="3" placeholder="장비 목록을 입력하세요"></textarea>
            </div>

            <hr class="form-divider">

            {{-- 의뢰 내용 --}}
            <div class="form-section">
                <div class="section-label">의뢰 내용</div>
                <div class="field-group">
                    <div class="field-label">의뢰 주제</div>
                    <div class="pill-group" id="reqTopicPills">
                        <button class="pill-btn" data-val="first_setup" onclick="toggleSingle(this,'reqTopicPills')">처음세팅</button>
                        <button class="pill-btn" data-val="add_setup" onclick="toggleSingle(this,'reqTopicPills')">추가세팅</button>
                        <button class="pill-btn" data-val="move_setup" onclick="toggleSingle(this,'reqTopicPills')">이사세팅</button>
                        <button class="pill-btn" data-val="rental" onclick="toggleSingle(this,'reqTopicPills')">렌탈</button>
                        <button class="pill-btn" data-val="etc" onclick="toggleSingle(this,'reqTopicPills')">기타</button>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">의뢰 세부항목</div>
                    <textarea class="field-input field-textarea" id="inputReqDetail" rows="3" placeholder="세부 항목을 입력하세요"></textarea>
                </div>
            </div>

            <hr class="form-divider">

            {{-- 결제 정보 --}}
            <div class="form-section">
                <div class="section-label">결제 정보</div>
                <div class="field-row">
                    <div class="field-group">
                        <div class="field-label">결제 여부</div>
                        <div class="pill-group" id="paidPills">
                            <button class="pill-btn" data-val="unpaid" onclick="toggleSingle(this,'paidPills')">미결제</button>
                            <button class="pill-btn" data-val="paid" onclick="toggleSingle(this,'paidPills')">결제완료</button>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">견적 총액</div>
                        <div style="display:flex; gap:6px;">
                            <input class="field-input" id="inputEstimateAmount" type="text" placeholder="금액 입력" style="flex:1;">
                            <button type="button" onclick="extractEstimateAmount()" class="pill-btn" style="flex-shrink:0;">추출</button>
                        </div>
                    </div>
                </div>
                <div class="field-row" style="margin-top:10px;">
                    <div class="field-group">
                        <div class="field-label">주문 제품</div>
                        <div class="pill-group" id="deliveryPills">
                            <button class="pill-btn" data-val="no" onclick="toggleSingle(this,'deliveryPills')">X</button>
                            <button class="pill-btn" data-val="yes" onclick="toggleSingle(this,'deliveryPills')">O</button>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">잔금 여부</div>
                        <div class="pill-group" id="balancePills">
                            <button class="pill-btn" data-val="no" onclick="toggleSingle(this,'balancePills')">X</button>
                            <button class="pill-btn" data-val="yes" onclick="toggleSingle(this,'balancePills')">O</button>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="form-divider">

            {{-- 첨부 이미지 --}}
            <div class="form-section">
                <div class="section-label">첨부 이미지</div>
                <div class="attach-category">
                    <div class="attach-category-label">견적서</div>
                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                        <button type="button" onclick="triggerAttach('quote')" class="pill-btn" style="flex:1; text-align:center;">견적서 첨부</button>
                        <button type="button" onclick="openEstimateSearch()" class="pill-btn" style="flex:1; text-align:center;">견적서 불러오기</button>
                    </div>
                    <div class="attach-zone" id="attachQuote"></div>
                    <input type="file" id="fileQuote" multiple accept="image/*" style="display:none" onchange="handleAttach('quote',this)">
                    <div id="linkedEstimateInfo" style="display:none; margin-top:8px; padding:10px; background:var(--surface2); border-radius:8px; border:1px solid var(--border);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-size:11px; color:var(--text-muted);">연결된 견적서</span>
                                <div style="font-size:13px; font-weight:600;" id="linkedEstimateTitle"></div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <button type="button" onclick="openLinkedEstimate()" class="pill-btn" style="font-size:11px; padding:3px 10px;">보기</button>
                                <button type="button" onclick="unlinkEstimate()" style="background:none; border:1px solid var(--red); color:var(--red); padding:3px 10px; border-radius:20px; font-size:11px; cursor:pointer;">해제</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="attach-category">
                    <div class="attach-category-label">레퍼런스</div>
                    <div class="attach-zone" id="attachReference">
                        <div class="attach-add-btn" onclick="triggerAttach('reference')">+</div>
                    </div>
                    <input type="file" id="fileReference" multiple accept="image/*" style="display:none" onchange="handleAttach('reference',this)">
                </div>
                <div class="attach-category">
                    <div class="attach-category-label">방 사진</div>
                    <div class="attach-zone" id="attachRoom">
                        <div class="attach-add-btn" onclick="triggerAttach('room')">+</div>
                    </div>
                    <input type="file" id="fileRoom" multiple accept="image/*" style="display:none" onchange="handleAttach('room',this)">
                </div>
            </div>
        </div>

        {{-- 원격/방송룸 정보 (teal만) --}}
        <div class="teal-only">
            <div class="form-section">
                <div class="section-label">원격/방송룸 정보</div>
                <div class="field-group">
                    <div class="field-label">모드</div>
                    <div class="pill-group" id="tealModePills">
                        <button class="pill-btn" data-val="remote" onclick="toggleSingle(this,'tealModePills')">원격</button>
                        <button class="pill-btn" data-val="studio" onclick="toggleSingle(this,'tealModePills')">스튜디오</button>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-group">
                        <div class="field-label">이름</div>
                        <input class="field-input" id="inputTealName" type="text" placeholder="이름">
                    </div>
                    <div class="field-group">
                        <div class="field-label">플랫폼</div>
                        <input class="field-input" id="inputTealPlatform" type="text" placeholder="플랫폼">
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">콘텐츠</div>
                    <input class="field-input" id="inputTealContent" type="text" placeholder="콘텐츠">
                </div>
                <div class="field-group">
                    <div class="field-label">세부 설명</div>
                    <textarea class="field-input field-textarea" id="inputTealDesc" rows="3" placeholder="세부 설명"></textarea>
                </div>
            </div>
        </div>

        <hr class="form-divider">

        {{-- 특이사항 --}}
        <div class="form-section">
            <div class="section-label">특이사항</div>
            <textarea class="field-input field-textarea" id="inputDesc" rows="3" placeholder="특이사항"></textarea>
        </div>

        <div class="modal-actions">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">삭제</button>
            <button class="btn-cancel" onclick="closeModal()">취소</button>
            <button class="btn-save" onclick="saveEvent()">저장</button>
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

// ── 색상/담당자 ─────────────────────────────────────────────────
function setColor(el){
    document.querySelectorAll('.type-pill').forEach(b=>b.classList.remove('active'));
    el.classList.add('active'); currentColor=el.dataset.color;
    // 조건부 섹션 토글
    document.querySelectorAll('.gold-only').forEach(s=>s.style.display=currentColor==='gold'?'':'none');
    document.querySelectorAll('.teal-only').forEach(s=>s.style.display=currentColor==='teal'?'':'none');
}

// ── pill 토글 헬퍼 ──
function toggleSingle(el, groupId){
    const pills = document.getElementById(groupId).querySelectorAll('.pill-btn');
    const wasActive = el.classList.contains('active');
    pills.forEach(p=>p.classList.remove('active'));
    if(!wasActive) el.classList.add('active');
    // 날짜선택이후 → afterDateFields 토글
    if(groupId==='schedOptPills'){
        const sel = getActivePill('schedOptPills');
        document.getElementById('afterDateFields').style.display = sel==='after_date'?'':'none';
    }
    // 잔금 → balanceAmountField 토글
    if(groupId==='balancePills'){
        const sel = getActivePill('balancePills');
        document.getElementById('balanceAmountField').style.display = sel==='has'?'':'none';
    }
}
function toggleMulti(el){
    el.classList.toggle('active');
}
function getActivePill(groupId){
    const active = document.getElementById(groupId).querySelector('.pill-btn.active');
    return active ? active.dataset.val : null;
}
function getActiveMultiPills(groupId){
    return Array.from(document.getElementById(groupId).querySelectorAll('.pill-btn.active')).map(p=>p.dataset.val);
}
function setPillValue(groupId, val){
    document.getElementById(groupId).querySelectorAll('.pill-btn').forEach(p=>{
        p.classList.toggle('active', p.dataset.val===val);
    });
}
function setMultiPillValues(groupId, vals){
    if(!vals||!vals.length) return;
    document.getElementById(groupId).querySelectorAll('.pill-btn').forEach(p=>{
        p.classList.toggle('active', vals.includes(p.dataset.val));
    });
}
function clearAllPills(groupId){
    document.getElementById(groupId).querySelectorAll('.pill-btn').forEach(p=>p.classList.remove('active'));
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
            if(selectedAssignees.includes(a.id)){selectedAssignees=selectedAssignees.filter(id=>id!==a.id);chip.classList.remove('selected');}
            else{selectedAssignees.push(a.id);chip.classList.add('selected');}
        };
        c.appendChild(chip);
    });
}

// ── 첨부파일 관리 ──
let pendingAttachments = { quote: [], reference: [], room: [] };
let existingAttachments = { quote: [], reference: [], room: [] };

function triggerAttach(type) {
    document.getElementById('file' + type.charAt(0).toUpperCase() + type.slice(1)).click();
}

function handleAttach(type, input) {
    if (!input.files.length) return;
    Array.from(input.files).forEach(f => pendingAttachments[type].push(f));
    input.value = '';
    renderAttachZone(type);
}

function renderAttachZone(type) {
    const zone = document.getElementById('attach' + type.charAt(0).toUpperCase() + type.slice(1));
    zone.innerHTML = '';
    // 기존 첨부
    existingAttachments[type].forEach((a, i) => {
        const thumb = document.createElement('div');
        thumb.className = 'attach-thumb';
        thumb.innerHTML = `<img src="${a.url}"><button class="remove-btn" onclick="removeExistingAttach('${type}',${i},${a.id})">×</button>`;
        zone.appendChild(thumb);
    });
    // 새 첨부
    pendingAttachments[type].forEach((f, i) => {
        const thumb = document.createElement('div');
        thumb.className = 'attach-thumb';
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        thumb.appendChild(img);
        const btn = document.createElement('button');
        btn.className = 'remove-btn';
        btn.textContent = '×';
        btn.onclick = () => { pendingAttachments[type].splice(i, 1); renderAttachZone(type); };
        thumb.appendChild(btn);
        zone.appendChild(thumb);
    });
    // + 버튼
    const addBtn = document.createElement('div');
    addBtn.className = 'attach-add-btn';
    addBtn.textContent = '+';
    addBtn.onclick = () => triggerAttach(type);
    zone.appendChild(addBtn);
}

async function removeExistingAttach(type, idx, id) {
    if (!confirm('이 이미지를 삭제하시겠습니까?')) return;
    await fetch(`/api/schedule-attachments/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
    existingAttachments[type].splice(idx, 1);
    renderAttachZone(type);
}

async function uploadPendingAttachments(scheduleId) {
    for (const type of ['quote', 'reference', 'room']) {
        if (!pendingAttachments[type].length) continue;
        const fd = new FormData();
        fd.append('attachment_type', type);
        pendingAttachments[type].forEach(f => fd.append('files[]', f));
        await fetch(`/api/schedules/${scheduleId}/attachments`, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, body: fd
        });
    }
}

async function loadExistingAttachments(scheduleId) {
    existingAttachments = { quote: [], reference: [], room: [] };
    try {
        const res = await fetch(`/api/schedules/${scheduleId}/attachments`);
        if (res.ok) {
            const list = await res.json();
            list.forEach(a => {
                if (existingAttachments[a.attachment_type]) {
                    existingAttachments[a.attachment_type].push(a);
                }
            });
        }
    } catch (e) { /* ignore */ }
    ['quote', 'reference', 'room'].forEach(t => renderAttachZone(t));
}

function resetAttachments() {
    pendingAttachments = { quote: [], reference: [], room: [] };
    existingAttachments = { quote: [], reference: [], room: [] };
    ['quote', 'reference', 'room'].forEach(t => renderAttachZone(t));
}

// ── 폼 초기화 헬퍼 ──
let linkedEstimateId = null;

function resetModalForm() {
    // pill 그룹 초기화
    ['schedOptPills','schedEventOptPills','specialOptPills','platformPills','careerPills','sourcePills','topicPills','budgetPills','reqTopicPills','paidPills','balancePills','deliveryPills','tealModePills'].forEach(id => clearAllPills(id));
    // 조건부 필드 숨기기
    document.getElementById('afterDateFields').style.display = 'none';
    // gold 텍스트 필드 초기화
    ['inputGoldName','inputGoldPhone','inputEquipment','inputReqDetail','inputEstimateAmount','inputAfterDate','inputAfterReason'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    // teal 텍스트 필드 초기화
    ['inputTealName','inputTealPlatform','inputTealContent','inputTealDesc'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    // 견적서 연결 초기화
    linkedEstimateId = null;
    document.getElementById('linkedEstimateInfo').style.display = 'none';
    resetAttachments();
}

// ── 견적서 연동 ──
let estimateSearchTimer = null;
function openEstimateSearch() {
    document.getElementById('estimateSearchOverlay').style.display = 'flex';
    document.getElementById('estimateSearchInput').value = '';
    document.getElementById('estimateSearchResults').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">검색어를 입력하세요</div>';
    setTimeout(() => document.getElementById('estimateSearchInput').focus(), 50);
}

function searchEstimates(query) {
    clearTimeout(estimateSearchTimer);
    if (!query.trim()) {
        document.getElementById('estimateSearchResults').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">검색어를 입력하세요</div>';
        return;
    }
    estimateSearchTimer = setTimeout(async () => {
        const res = await fetch(`/api/estimates?search=${encodeURIComponent(query)}`);
        if (!res.ok) return;
        const data = await res.json();
        const list = data.data || data;
        if (!list.length) {
            document.getElementById('estimateSearchResults').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">결과 없음</div>';
            return;
        }
        document.getElementById('estimateSearchResults').innerHTML = list.map(e => {
            const statusMap = {created:'작성중',editing:'수정중',completed:'완료',paid:'결제완료',hold:'보류'};
            const statusLabel = statusMap[e.status] || e.status;
            const amount = e.total_amount ? Number(e.total_amount).toLocaleString() + '원' : '';
            return `<div style="padding:10px 12px; border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.1s;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''" onclick="selectEstimate(${e.id},'${(e.client_nickname||e.client_name||'').replace(/'/g,"\\'")}',${e.total_amount||0})">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span style="font-size:13px; font-weight:600;">#${e.id}</span>
                        <span style="font-size:13px; margin-left:8px;">${e.client_nickname || e.client_name || '(이름없음)'}</span>
                    </div>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <span style="font-size:12px; color:var(--accent);">${amount}</span>
                        <span style="font-size:10px; padding:2px 6px; border-radius:4px; background:var(--surface2); color:var(--text-muted);">${statusLabel}</span>
                    </div>
                </div>
            </div>`;
        }).join('');
    }, 300);
}

function selectEstimate(id, name, amount) {
    linkedEstimateId = id;
    document.getElementById('linkedEstimateTitle').textContent = `#${id} ${name}`;
    document.getElementById('linkedEstimateInfo').style.display = '';
    if (amount) document.getElementById('inputEstimateAmount').value = amount.toLocaleString();
    document.getElementById('estimateSearchOverlay').style.display = 'none';
}

function unlinkEstimate() {
    linkedEstimateId = null;
    document.getElementById('linkedEstimateInfo').style.display = 'none';
}

function openLinkedEstimate() {
    if (linkedEstimateId) window.open(`/estimates/${linkedEstimateId}/edit`, '_blank');
}

function extractEstimateAmount() {
    if (!linkedEstimateId) { alert('먼저 견적서를 불러와주세요.'); return; }
    // 견적서가 연결되어 있으면 해당 견적서의 total_amount를 다시 가져옴
    fetch(`/api/estimates?search=${linkedEstimateId}`)
        .then(r=>r.json())
        .then(data => {
            const list = data.data || data;
            const est = list.find(e => e.id === linkedEstimateId);
            if (est && est.total_amount) {
                document.getElementById('inputEstimateAmount').value = Number(est.total_amount).toLocaleString();
            }
        });
}

// ── 모달 ────────────────────────────────────────────────────────
function openNewModal(dateStr,timeStr){
    editingId=null; currentColor='gold'; selectedAssignees=[];
    document.querySelectorAll('.type-pill').forEach(b=>b.classList.remove('active'));
    document.querySelector('.type-pill[data-color="gold"]').classList.add('active');
    document.querySelectorAll('.gold-only').forEach(s=>s.style.display='');
    document.querySelectorAll('.teal-only').forEach(s=>s.style.display='none');
    document.getElementById('modalTitleText').textContent='새 일정';
    document.getElementById('inputTitle').value='';
    document.getElementById('inputStartDate').value=dateStr||'';
    document.getElementById('inputEndDate').value=dateStr||'';
    document.getElementById('inputStartTime').value=timeStr||'13:00';
    document.getElementById('inputEndTime').value=timeStr?`${String(parseInt(timeStr)+1).padStart(2,'0')}:00`:'14:00';
    document.getElementById('inputClientName').value='';
    document.getElementById('inputAddress').value='';
    document.getElementById('inputAddressDetail').value='';
    document.getElementById('inputDesc').value='';
    document.getElementById('btnDelete').style.display='none';
    resetModalForm();
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(()=>document.getElementById('inputTitle').focus(),50);
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

    // gold_data (방문의뢰) — pill value → label 매핑
    const PLATFORM_L={soop:'SOOP',chzzk:'치지직',youtube:'유튜브',tiktok:'틱톡',etc:'기타'};
    const CAREER_L={first:'처음',beginner:'초보',experienced:'경력'};
    const SOURCE_L={ad:'광고',search:'검색',referral:'소개'};
    const TOPIC_L={talk:'소통',food:'먹방',game:'게임',outdoor:'야외',song:'노래',stock:'주식/코인',etc:'기타'};
    const BUDGET_L={plenty:'풍족',lack:'부족',unknown:'모름',direct:'직접입력'};
    const PAID_L={unpaid:'미결제',paid:'결제완료'};
    const YN_L={yes:'O',no:'X'};
    const g = d.gold_data;
    if (g && Object.keys(g).length) {
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">의뢰자 정보</legend>`;
        if (g.phone) html += infoRow('전화번호', g.phone);
        if (g.source) html += infoRow('유입 경로', SOURCE_L[g.source]||g.source);
        if (g.platform) html += infoRow('플랫폼', PLATFORM_L[g.platform]||g.platform);
        if (g.topic) html += infoRow('방송 주제', TOPIC_L[g.topic]||g.topic);
        if (g.budget) html += infoRow('예산 성향', BUDGET_L[g.budget]||g.budget);
        if (g.career) html += infoRow('경력 여부', CAREER_L[g.career]||g.career);
        if (g.paid) html += infoRow('결제 여부', PAID_L[g.paid]||g.paid);
        if (g.delivery) html += infoRow('주문 제품', YN_L[g.delivery]||g.delivery);
        if (g.balance) html += infoRow('잔금 여부', YN_L[g.balance]||g.balance);
        if (g.estimate_amount) html += infoRow('견적 총액', g.estimate_amount);
        if (g.estimate_id) html += infoRow('견적서', `<a href="/estimates/${g.estimate_id}/edit" target="_blank" style="color:var(--accent);">#${g.estimate_id} 보기</a>`);
        html += `</fieldset>`;

        if (g.equipment) {
            html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">장비 목록</legend>`;
            html += `<div style="font-size:13px; white-space:pre-wrap;">${g.equipment}</div>`;
            html += `</fieldset>`;
        }
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

    // 의뢰 내용
    if (g && (g.request_topic || g.request_detail)) {
        html += `<fieldset style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:14px;"><legend style="font-size:11px; color:var(--text-muted); padding:0 6px;">의뢰 내용</legend>`;
        if (g.request_topic) html += infoRow('주제', g.request_topic);
        if (g.request_detail) html += `<div style="font-size:13px; white-space:pre-wrap; margin-top:6px;">${g.request_detail}</div>`;
        html += `</fieldset>`;
    }

    // 특이사항
    if (d.description) {
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
    editingId=ev.id; currentColor=ev.color;
    selectedAssignees=ev.assignees?ev.assignees.map(a=>a.id):[];
    document.querySelectorAll('.type-pill').forEach(b=>b.classList.remove('active'));
    const cb=document.querySelector(`.type-pill[data-color="${ev.color}"]`);
    if(cb) cb.classList.add('active');
    document.querySelectorAll('.gold-only').forEach(s=>s.style.display=ev.color==='gold'?'':'none');
    document.querySelectorAll('.teal-only').forEach(s=>s.style.display=ev.color==='teal'?'':'none');
    document.getElementById('modalTitleText').textContent='일정 수정';
    document.getElementById('inputTitle').value=ev.title||'';
    document.getElementById('inputStartDate').value=ev.start_date?ev.start_date.substring(0,10):'';
    document.getElementById('inputEndDate').value=ev.end_date?ev.end_date.substring(0,10):'';
    document.getElementById('inputStartTime').value=ev.start_time||'13:00';
    document.getElementById('inputEndTime').value=ev.end_time||'14:00';
    document.getElementById('inputClientName').value=ev.client_name||'';
    document.getElementById('inputAddress').value=ev.address||'';
    document.getElementById('inputAddressDetail').value=ev.location||'';
    document.getElementById('inputDesc').value=ev.description||'';
    document.getElementById('btnDelete').style.display='block';

    // pill 옵션 복원
    resetModalForm();
    if(ev.sched_opt) { setPillValue('schedOptPills', ev.sched_opt); if(ev.sched_opt==='after_date') document.getElementById('afterDateFields').style.display=''; }
    if(ev.sched_event_opts) setMultiPillValues('schedEventOptPills', ev.sched_event_opts);
    if(ev.special_opts) setMultiPillValues('specialOptPills', ev.special_opts);
    if(ev.sched_after_date) document.getElementById('inputAfterDate').value=ev.sched_after_date.substring(0,10);
    if(ev.sched_after_reason) document.getElementById('inputAfterReason').value=ev.sched_after_reason;

    // gold_data 복원
    const g = ev.gold_data || {};
    document.getElementById('inputGoldName').value = g.name || '';
    document.getElementById('inputGoldPhone').value = g.phone || '';
    if(g.platform) setPillValue('platformPills', g.platform);
    if(g.career) setPillValue('careerPills', g.career);
    if(g.source) setPillValue('sourcePills', g.source);
    if(g.topic) setPillValue('topicPills', g.topic);
    if(g.budget) setPillValue('budgetPills', g.budget);
    document.getElementById('inputEquipment').value = g.equipment || '';
    if(g.request_topic) setPillValue('reqTopicPills', g.request_topic);
    document.getElementById('inputReqDetail').value = g.request_detail || '';
    if(g.paid) setPillValue('paidPills', g.paid);
    document.getElementById('inputEstimateAmount').value = g.estimate_amount || '';
    if(g.delivery) setPillValue('deliveryPills', g.delivery);
    if(g.balance) setPillValue('balancePills', g.balance);
    // 견적서 연결 복원
    if(g.estimate_id) {
        linkedEstimateId = g.estimate_id;
        document.getElementById('linkedEstimateTitle').textContent = `#${g.estimate_id}`;
        document.getElementById('linkedEstimateInfo').style.display = '';
    }

    // teal_data 복원
    const t = ev.teal_data || {};
    if(t.mode) setPillValue('tealModePills', t.mode);
    document.getElementById('inputTealName').value = t.name || '';
    document.getElementById('inputTealPlatform').value = t.platform || '';
    document.getElementById('inputTealContent').value = t.content || '';
    document.getElementById('inputTealDesc').value = t.desc || '';

    // 기존 첨부파일 로드
    pendingAttachments = { quote: [], reference: [], room: [] };
    loadExistingAttachments(ev.id);

    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(){
    document.getElementById('modalOverlay').classList.remove('open'); editingId=null;
}

async function saveEvent(){
    const data={
        title:      document.getElementById('inputTitle').value.trim()||'(제목 없음)',
        start_date: document.getElementById('inputStartDate').value,
        end_date:   document.getElementById('inputEndDate').value||document.getElementById('inputStartDate').value,
        start_time: document.getElementById('inputStartTime').value,
        end_time:   document.getElementById('inputEndTime').value,
        is_all_day: false,
        color:      currentColor,
        client_name:document.getElementById('inputClientName').value.trim(),
        address:    document.getElementById('inputAddress').value.trim(),
        location:   document.getElementById('inputAddressDetail').value.trim(),
        description:document.getElementById('inputDesc').value.trim(),
        assignees:  selectedAssignees,
        sched_opt:       getActivePill('schedOptPills'),
        sched_event_opts:getActiveMultiPills('schedEventOptPills'),
        special_opts:    getActiveMultiPills('specialOptPills'),
        sched_after_date:  document.getElementById('inputAfterDate').value||null,
        sched_after_reason:document.getElementById('inputAfterReason').value.trim()||null,
    };

    // gold_data 수집
    if(currentColor==='gold'){
        data.gold_data={
            name:           document.getElementById('inputGoldName').value.trim(),
            phone:          document.getElementById('inputGoldPhone').value.trim(),
            platform:       getActivePill('platformPills'),
            career:         getActivePill('careerPills'),
            source:         getActivePill('sourcePills'),
            topic:          getActivePill('topicPills'),
            budget:         getActivePill('budgetPills'),
            equipment:      document.getElementById('inputEquipment').value.trim(),
            request_topic:  getActivePill('reqTopicPills'),
            request_detail: document.getElementById('inputReqDetail').value.trim(),
            paid:           getActivePill('paidPills'),
            estimate_amount:document.getElementById('inputEstimateAmount').value.trim(),
            delivery:       getActivePill('deliveryPills'),
            balance:        getActivePill('balancePills'),
            estimate_id:    linkedEstimateId,
        };
    } else {
        data.gold_data = null;
    }

    // teal_data 수집
    if(currentColor==='teal'){
        data.teal_data={
            mode:     getActivePill('tealModePills'),
            name:     document.getElementById('inputTealName').value.trim(),
            platform: document.getElementById('inputTealPlatform').value.trim(),
            content:  document.getElementById('inputTealContent').value.trim(),
            desc:     document.getElementById('inputTealDesc').value.trim(),
        };
    } else {
        data.teal_data = null;
    }

    if(!data.start_date){alert('시작일을 입력하세요.');return;}
    const url=editingId?`/api/events/${editingId}`:'/api/events';
    const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(data)});
    if(res.ok){
        const saved = await res.json();
        // 이미지 업로드
        const hasFiles = Object.values(pendingAttachments).some(arr=>arr.length);
        if(hasFiles) await uploadPendingAttachments(saved.id);
        closeModal();loadEvents();
    }
    else{const err=await res.json();alert('저장 실패: '+JSON.stringify(err));}
}

async function deleteEvent(id){
    const delId = id || editingId;
    if(!delId||!confirm('이 일정을 삭제할까요?')) return;
    const res=await fetch(`/api/events/${delId}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}});
    if(res.ok){closeModal();loadEvents();}
}

function searchCalAddr() {
    new daum.Postcode({
        oncomplete: function(data) {
            document.getElementById('inputAddress').value = data.roadAddress || data.jibunAddress;
        }
    }).open();
}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeDetail();document.getElementById('historyOverlay').style.display='none';}});

init();
</script>
@endpush
