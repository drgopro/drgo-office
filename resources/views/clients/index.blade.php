@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '의뢰자 - 닥터고블린 오피스')

@push('styles')
<style>
    .crm-wrap { display:flex; height:calc(var(--full-h, 100vh) - var(--chrome-h, 86px)); overflow:hidden; }
    body.in-iframe .crm-wrap { height:var(--full-h, 100vh); }

    /* ── 좌측 사이드바 ── */
    .crm-sidebar { width:220px; min-width:220px; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; }
    .sidebar-header { padding:12px; border-bottom:1px solid var(--border); }
    .sidebar-title { font-size:13px; font-weight:700; margin-bottom:10px; color:var(--text-muted); }
    .sidebar-search { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; }
    .sidebar-search:focus { border-color:var(--accent); }
    .sidebar-filters { display:flex; gap:4px; margin-top:8px; flex-wrap:wrap; }
    .filter-chip { padding:3px 8px; border-radius:4px; font-size:10px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:none; color:var(--text-muted); transition:all 0.12s; }
    .filter-chip.active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
    .filter-chip:hover:not(.active) { border-color:var(--accent); color:var(--accent); }

    .sidebar-list { flex:1; overflow-y:auto; padding:6px; }
    .sidebar-pagination { display:flex; flex-direction:column; align-items:center; gap:3px; padding:6px 4px 8px; border-top:1px solid var(--border); background:var(--surface); }
    .sidebar-pagination .pg-row { display:flex; align-items:center; gap:2px; flex-wrap:nowrap; max-width:100%; overflow-x:auto; scrollbar-width:none; }
    .sidebar-pagination .pg-row::-webkit-scrollbar { display:none; }
    .sidebar-pagination button { flex:0 0 auto; background:none; border:1px solid var(--border); color:var(--text-muted); padding:2px 0; border-radius:5px; font-size:11px; cursor:pointer; min-width:22px; line-height:1.3; }
    .sidebar-pagination button:hover:not(:disabled) { border-color:var(--accent); color:var(--accent); }
    .sidebar-pagination button.active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); font-weight:700; }
    [data-theme="light"] .sidebar-pagination button.active { color:#fff; }
    .sidebar-pagination button:disabled { opacity:0.35; cursor:default; }
    .sidebar-pagination .pg-info { font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; white-space:nowrap; }
    .sidebar-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:8px; cursor:pointer; transition:all 0.12s; position:relative; }
    .sidebar-item:hover { background:var(--surface2); }
    .sidebar-item.active { background:var(--surface2); border-left:3px solid var(--accent); }
    .sidebar-item .avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; border:1px solid var(--border); }
    .sidebar-item .item-info { flex:1; min-width:0; }
    .sidebar-item .item-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-item .item-sub { font-size:11px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-item .item-grade { font-size:10px; padding:3px 8px; border-radius:10px; font-weight:700; flex-shrink:0; letter-spacing:0.05em; border:1px solid; }
    .grade-normal { background:rgba(160,168,180,0.15); color:#a0a8b4; border-color:rgba(160,168,180,0.4); }
    .grade-vip { background:rgba(212,188,150,0.18); color:#d4bc96; border-color:rgba(212,188,150,0.5); }
    .grade-rental { background:rgba(138,180,200,0.18); color:#8ab4c8; border-color:rgba(138,180,200,0.5); }
    [data-theme="light"] .grade-normal { background:#eceef2; color:#5a6070; border-color:#b8bcc8; }
    [data-theme="light"] .grade-vip { background:#fff3e0; color:#a06800; border-color:#e0b870; }
    [data-theme="light"] .grade-rental { background:#e0f0ff; color:#2e6a9a; border-color:#88b8d8; }
    .sidebar-item .online-dot { width:6px; height:6px; border-radius:50%; background:var(--green); position:absolute; right:10px; top:50%; transform:translateY(-50%); }

    .sidebar-add { margin:8px; padding:8px; border-radius:8px; border:1px dashed var(--border); text-align:center; font-size:12px; color:var(--text-muted); cursor:pointer; transition:all 0.12s; }
    .sidebar-add:hover { border-color:var(--accent); color:var(--accent); }

    /* ── 우측 메인 ── */
    .crm-main { flex:1; display:flex; flex-direction:column; overflow:hidden; }

    /* 의뢰자 탭 바 */
    .client-tab-bar { display:flex; align-items:center; background:var(--surface); border-bottom:1px solid var(--border); padding:0 12px; height:36px; gap:1px; overflow-x:auto; flex-shrink:0; }
    .client-tab-bar::-webkit-scrollbar { display:none; }
    .client-tab { display:flex; align-items:center; gap:5px; padding:6px 12px; font-size:12px; cursor:pointer; color:var(--text-muted); border:none; background:none; white-space:nowrap; border-radius:5px 5px 0 0; border:1px solid transparent; border-bottom:none; transition:all 0.12s; flex-shrink:0; }
    .client-tab:hover { color:var(--text); background:var(--surface2); }
    .client-tab.active { color:var(--accent); background:var(--surface2); border-color:var(--border); font-weight:600; position:relative; }
    .client-tab.active::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:1px; background:var(--surface2); }
    .client-tab .ct-avatar { width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:8px; font-weight:700; border:1px solid var(--border); }
    .client-tab .ct-close { display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:3px; font-size:9px; opacity:0; transition:opacity 0.1s; }
    .client-tab:hover .ct-close { opacity:0.5; }
    .client-tab .ct-close:hover { opacity:1; background:var(--border); }
    .client-tab.ct-dragging { opacity:0.4; cursor:grabbing; }
    .client-tab.ct-drag-over { box-shadow:inset 2px 0 0 var(--accent); }
    .client-tab-add { padding:4px 8px; font-size:14px; color:var(--text-muted); cursor:pointer; background:none; border:none; border-radius:4px; }
    .client-tab-add:hover { color:var(--accent); background:var(--surface2); }

    /* 의뢰자 상세 영역 */
    .client-content { flex:1; overflow-y:auto; }
    .client-empty { display:flex; align-items:center; justify-content:center; height:100%; color:var(--text-muted); font-size:14px; }
    .client-pane { display:none; padding:20px; padding-bottom:60px; }
    .client-pane.active { display:block; }

    /* 상세 헤더 */
    .detail-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
    .detail-identity { display:flex; align-items:center; gap:12px; }
    .detail-avatar { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; border:2px solid var(--accent); }
    .detail-name { font-size:18px; font-weight:700; }
    .detail-meta { font-size:12px; color:var(--text-muted); }
    .detail-actions { display:flex; gap:6px; }
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:7px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
    .btn-save:hover { opacity:0.85; }
    .btn-delete { background:none; border:1px solid var(--red); color:var(--red); padding:7px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
    .btn-delete:hover { background:var(--red); color:#fff; }
    .btn-log { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); padding:7px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
    .btn-log:hover { border-color:var(--accent); color:var(--accent); }

    /* 서브 탭 */
    .sub-tabs { display:flex; gap:0; border-bottom:1px solid var(--border); margin-bottom:20px; }
    .sub-tab { padding:8px 16px; font-size:13px; color:var(--text-muted); cursor:pointer; border:none; background:none; border-bottom:2px solid transparent; transition:all 0.12s; }
    .sub-tab:hover { color:var(--text); }
    .sub-tab.active { color:var(--accent); border-bottom-color:var(--accent); font-weight:600; }
    .sub-panel { display:none; }
    .sub-panel.active { display:block; }

    /* 폼 필드 */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-grid.full { grid-template-columns:1fr; }
    /* 동적 추가 정보(4열 기준 width 1~4) */
    .cf-dyn-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px 16px; }
    .cf-dyn-grid > .field { grid-column:span 2; min-width:0; }
    .cf-dyn-grid > .field.w-1 { grid-column:span 1; }
    .cf-dyn-grid > .field.w-2 { grid-column:span 2; }
    .cf-dyn-grid > .field.w-3 { grid-column:span 3; }
    .cf-dyn-grid > .field.w-4 { grid-column:1 / -1; }
    .field { }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:5px; }
    .tag-pick { display:flex; flex-wrap:wrap; gap:6px; }
    .tag-chip-pick { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border:1px solid var(--border); border-radius:14px; font-size:12px; cursor:pointer; background:var(--surface2); color:var(--text-muted); user-select:none; }
    .tag-chip-pick input { display:none; }
    .tag-chip-pick:has(input:checked) { background:rgba(36,138,56,0.14); border-color:#248a38; color:#248a38; font-weight:600; }
    .tag-add-btn { background:none; border:1px solid var(--border); color:var(--accent); border-radius:6px; padding:2px 9px; font-size:11px; cursor:pointer; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:8px 10px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-textarea { min-height:60px; resize:vertical; }
    .chk-group { display:flex; flex-wrap:wrap; gap:6px; }
    .chk-chip { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:16px; border:1px solid var(--border); background:var(--surface2); color:var(--text-muted); font-size:12px; cursor:pointer; user-select:none; transition:all 0.12s; }
    .chk-chip:hover { border-color:var(--accent); color:var(--text); }
    .chk-chip input[type=checkbox], .chk-chip input[type=radio] { display:none; }
    .chk-chip.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:600; }
    [data-theme="light"] .chk-chip.on { color:#fff; }
    .field-select { cursor:pointer; }

    /* 알림 */
    .toast { position:fixed; bottom:20px; right:20px; background:var(--accent); color:var(--accent-text); padding:10px 16px; border-radius:8px; font-size:13px; font-weight:600; z-index:999; display:none; }
    .toast.show { display:block; }

    /* 새 의뢰자 모달 — 캘린더 일정 등록과 동일 문법 (히어로 + 번호 카드 + 사이드 진행) */
    .new-client-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:500; align-items:center; justify-content:center; padding:16px; }
    .new-client-overlay.open { display:flex; }
    .new-client-modal.ncm { background:var(--bg); border:1px solid var(--border); border-radius:14px; padding:0; width:1100px; max-width:96vw; max-height:92vh; overflow-y:auto; }
    .ncm-head { background:var(--surface); border-bottom:1px solid var(--border); padding:18px 28px 16px; position:sticky; top:0; z-index:5; border-radius:14px 14px 0 0; }
    .ncm-head-top { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .ncm-meta { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted); }
    .ncm-meta b { color:var(--text); font-weight:600; }
    .ncm-actions { display:flex; align-items:center; gap:8px; }
    .ncm-btn { height:34px; padding:0 16px; border:1px solid var(--border); border-radius:8px; background:var(--surface); font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; }
    .ncm-btn:hover { color:var(--text); border-color:var(--text-muted); }
    .ncm-btn.primary { border:none; background:var(--accent); color:var(--accent-text); font-weight:700; padding:0 18px; }
    .ncm-btn.primary:hover { opacity:0.9; color:var(--accent-text); }
    .ncm-btn.icon { width:34px; padding:0; font-size:15px; }
    .ncm-hero { margin-top:10px; width:100%; border:none; outline:none; font-size:24px; font-weight:800; color:var(--text); background:transparent; padding:0; box-sizing:border-box; font-family:inherit; }
    .ncm-hero::placeholder { color:var(--text-muted); opacity:0.55; font-weight:700; }
    .ncm-pills { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
    .ncm-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 8px 5px 12px; border:1px dashed var(--border); border-radius:999px; font-size:12px; color:var(--text-muted); }
    .ncm-pill select { border:none; background:transparent; color:var(--text); font-size:12px; font-weight:600; outline:none; cursor:pointer; font-family:inherit; }
    .ncm-body { display:grid; grid-template-columns:minmax(0,1fr) 236px; gap:20px; padding:22px 28px 26px; align-items:start; }
    .ncm-cards { display:flex; flex-direction:column; gap:16px; min-width:0; }
    .ncm-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; }
    .ncm-card-head { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
    .ncm-no { font-size:12px; font-weight:700; color:var(--text-muted); background:var(--surface2); border-radius:6px; padding:3px 8px; }
    .ncm-title { font-size:15px; font-weight:700; color:var(--text); }
    .ncm-cnt { margin-left:auto; font-size:12px; color:var(--text-muted); }
    .ncm-label { font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:6px; }
    .ncm-input { width:100%; height:38px; border:1px solid var(--border); border-radius:8px; padding:0 12px; font-size:14px; color:var(--text); background:var(--surface); box-sizing:border-box; outline:none; font-family:inherit; }
    .ncm-input:focus { border-color:var(--accent); }
    .ncm-ta { height:64px; padding:10px 12px; resize:vertical; }
    .ncm-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    /* 칩(라디오/체크) — 단색 pill (기존 chk-chip 메커니즘 위에 크기·모양만 조정) */
    .ncm .chk-group { display:flex; flex-wrap:wrap; gap:8px; }
    .ncm .chk-chip { padding:7px 14px; border-radius:999px; background:var(--surface); font-size:13px; }
    .ncm .chk-chip.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:700; }
    /* 03 장비 — 자동 연동 안내 (읽기 전용) */
    .ncm-equip { border-style:dashed; }
    .ncm-equip .ncm-card-head { margin-bottom:0; }
    .ncm-badge { padding:3px 9px; border-radius:999px; background:color-mix(in srgb, var(--accent) 14%, transparent); color:var(--accent); font-size:10.5px; font-weight:700; }
    .ncm-equip-note { display:flex; align-items:center; gap:12px; margin-top:12px; padding:14px 16px; background:var(--surface2); border-radius:10px; font-size:12.5px; color:var(--text-muted); line-height:1.5; }
    .ncm-equip-note b { color:var(--text); }
    .ncm-equip-ico { width:34px; height:34px; border-radius:10px; background:color-mix(in srgb, var(--accent) 14%, transparent); color:var(--accent); font-size:15px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    /* 사이드바 */
    .ncm-side { display:flex; flex-direction:column; gap:14px; position:sticky; top:96px; }
    .ncm-side-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px; }
    .ncm-side-title { font-size:12px; font-weight:700; color:var(--text-muted); }
    .ncm-prog { display:flex; align-items:baseline; gap:8px; margin-top:8px; }
    .ncm-pct { font-size:28px; font-weight:800; color:var(--text); }
    .ncm-prog-sub { font-size:12px; color:var(--text-muted); }
    .ncm-bar { height:6px; background:var(--surface2); border-radius:99px; margin-top:10px; overflow:hidden; }
    .ncm-bar div { width:0; height:100%; background:var(--accent); border-radius:99px; transition:width .2s; }
    .ncm-secs { display:flex; flex-direction:column; gap:2px; margin-top:14px; }
    .ncm-sec-row { display:flex; align-items:center; gap:8px; padding:7px 8px; border-radius:8px; font-size:12.5px; color:var(--text); }
    .ncm-sec-row.done { background:var(--surface2); font-weight:600; }
    .ncm-sec-no { font-size:11px; font-weight:700; color:var(--text-muted); width:18px; text-align:center; flex-shrink:0; }
    .ncm-sec-row.done .ncm-sec-no { width:18px; height:18px; border-radius:50%; background:var(--accent); color:var(--accent-text); font-size:10px; display:inline-flex; align-items:center; justify-content:center; }
    .ncm-sec-cnt { margin-left:auto; font-size:11px; color:var(--text-muted); }
    .ncm-req { padding:4px 10px; border:1px solid color-mix(in srgb, var(--red) 45%, transparent); background:color-mix(in srgb, var(--red) 10%, transparent); border-radius:999px; font-size:11.5px; color:var(--red); }
    .ncm-req-done { font-size:12px; color:var(--green, #3fae54); font-weight:600; }
    @media (max-width:900px) {
        .ncm-body { grid-template-columns:1fr; }
        .ncm-side { display:none; }
        .ncm-grid2 { grid-template-columns:1fr; }
        .ncm-head { padding:14px 16px 12px; }
        .ncm-body { padding:16px; }
        .ncm-hero { font-size:19px; }
    }

    /* ── 라이트모드 보정 ── */
    [data-theme="light"] .btn-log { background:#f0f1f3; border-color:#b8bcc8; color:#4a5060; }
    [data-theme="light"] .btn-log:hover { border-color:var(--accent); color:var(--accent); background:#e8eaef; }
    [data-theme="light"] .btn-save { background:var(--accent); color:#fff; }
    [data-theme="light"] .btn-save:hover { opacity:0.9; }
    [data-theme="light"] .btn-delete { border-color:var(--red); color:var(--red); }
    [data-theme="light"] .btn-delete:hover { background:var(--red); color:#fff; }
    [data-theme="light"] .sidebar-item { color:var(--text); }
    [data-theme="light"] .sidebar-item:hover { background:var(--surface2); }
    [data-theme="light"] .sidebar-item.active { background:var(--accent); color:#fff; }
    [data-theme="light"] .sidebar-item.active .item-sub { color:rgba(255,255,255,0.8); }
    [data-theme="light"] .sidebar-item.active .avatar { color:#fff !important; border-color:rgba(255,255,255,0.5) !important; }
    [data-theme="light"] .client-tab.active { background:var(--surface2); border-color:var(--border); }
    [data-theme="light"] .sub-tab { color:var(--text-muted); }
    [data-theme="light"] .sub-tab.active { color:var(--accent); border-color:var(--accent); }
    [data-theme="light"] .field-input { background:#fff; border-color:#c8ccd4; color:var(--text); }
    [data-theme="light"] .field-input:focus { border-color:var(--accent); }
    [data-theme="light"] .field-select { background:#fff; }
    [data-theme="light"] .grade-chip.active { background:var(--accent); color:#fff; }
    [data-theme="light"] .new-client-overlay { background:rgba(0,0,0,0.4); }
    [data-theme="light"] .new-client-modal { background:#fff; border-color:#c8ccd4; }
    [data-theme="light"] .filter-chip.active { color:#fff; }
    [data-theme="light"] .toast { color:#fff; }

    /* 기본 정보 조회 뷰 (디자인 3a) — 섹션 타이틀 레일 + 값 그리드, 구분선 기반 */
    .cv-sec { display:grid; grid-template-columns:190px 1fr; gap:28px; padding:24px 0; border-bottom:1px solid var(--border); }
    .cv-wrap .cv-sec:first-child { padding-top:8px; }
    .cv-wrap .cv-sec:last-child { border-bottom:none; }
    .cv-rail { border-right:2px solid var(--border); padding-right:20px; }
    .cv-rt { display:flex; align-items:center; gap:9px; font-size:15px; font-weight:800; color:var(--text); }
    .cv-bar { width:4px; height:16px; background:var(--accent); border-radius:2px; flex-shrink:0; }
    .cv-rd { font-size:12px; color:var(--text-muted); margin-top:8px; line-height:1.5; padding-left:13px; }
    .cv-badge { padding:3px 10px; border-radius:999px; background:color-mix(in srgb, var(--accent) 14%, transparent); color:var(--accent); font-size:10.5px; font-weight:700; }
    .cv-grid { display:grid; grid-template-columns:1fr 1fr; column-gap:36px; row-gap:16px; }
    .cv-grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; column-gap:30px; row-gap:14px; }
    .cv-l { font-size:12px; color:var(--text-muted); font-weight:600; }
    .cv-v { font-size:14.5px; color:var(--text); margin-top:4px; font-weight:500; word-break:break-word; white-space:pre-wrap; }
    .cv-v.dim { color:var(--text-muted); opacity:0.55; font-weight:400; }
    .cv-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .cv-chip { padding:5px 13px; border-radius:999px; border:1px solid var(--border); color:var(--text); font-size:12.5px; }
    .cv-chip.fill { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:600; }
    .cv-eqwrap { display:flex; flex-direction:column; gap:18px; }
    .cv-eqgroup .cv-subchip { display:inline-block; padding:3px 10px; border-radius:6px; background:color-mix(in srgb, var(--accent) 14%, transparent); color:var(--accent); font-size:11.5px; font-weight:700; margin-bottom:10px; }
    .cv-eqlink { font-size:13px; font-weight:600; color:var(--accent); text-decoration:none; }
    .cv-eqlink:hover { text-decoration:underline; }
    @media (max-width:768px) {
        .cv-sec { grid-template-columns:1fr; gap:10px; padding:18px 0; }
        .cv-rail { border-right:none; padding-right:0; }
        .cv-rd br { display:none; }
        .cv-grid { column-gap:16px; }
        .cv-grid3 { grid-template-columns:1fr 1fr; column-gap:16px; }
    }

    /* 사이드바 토글 버튼 (모바일 전용) */
    .sidebar-toggle { display:none; }
    .sidebar-overlay { display:none; }

    @media (max-width: 768px) {
        .crm-wrap { flex-direction:column; height:var(--full-h, 100vh); }
        body.in-iframe .crm-wrap { height:var(--full-h, 100vh); }

        /* 사이드바 → 슬라이드 드로어 */
        .crm-sidebar { position:fixed; left:0; top:0; bottom:0; width:280px; min-width:0; max-height:none; z-index:300; transform:translateX(-100%); transition:transform 0.25s ease; border-right:1px solid var(--border); border-bottom:none; box-shadow:4px 0 20px rgba(0,0,0,0.3); }
        .crm-sidebar.open { transform:translateX(0); }
        .sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:299; display:none; }
        .sidebar-overlay.open { display:block; }

        /* 사이드바 토글 버튼 표시 */
        .sidebar-toggle { display:flex; align-items:center; gap:6px; padding:8px 12px; background:none; border:1px solid var(--border); border-radius:8px; color:var(--text-muted); font-size:12px; cursor:pointer; min-height:36px; flex-shrink:0; }
        .sidebar-toggle:hover { border-color:var(--accent); color:var(--accent); }

        /* 터치 타겟 확대 */
        .filter-chip { padding:6px 12px; font-size:11px; min-height:32px; }
        .sidebar-item { padding:12px 12px; }
        .sidebar-search { min-height:40px; font-size:14px; }
        .sidebar-add { min-height:44px; display:flex; align-items:center; justify-content:center; }

        /* 서브탭 스크롤 */
        .sub-tabs { overflow-x:auto; -webkit-overflow-scrolling:touch; flex-wrap:nowrap; }
        .sub-tabs::-webkit-scrollbar { display:none; }
        .sub-tab { white-space:nowrap; flex-shrink:0; padding:8px 14px; font-size:12px; }

        /* 상세 헤더 세로 배치 */
        .detail-header { flex-direction:column; gap:10px; align-items:flex-start; }
        .detail-actions { width:100%; justify-content:flex-start; flex-wrap:wrap; }
        .detail-actions button { min-height:36px; }

        /* 폼/콘텐츠 */
        .form-grid { grid-template-columns:1fr; }
        .cf-dyn-grid { grid-template-columns:repeat(2, 1fr); }
        .cf-dyn-grid > .field, .cf-dyn-grid > .field.w-1, .cf-dyn-grid > .field.w-2 { grid-column:span 1; }
        .cf-dyn-grid > .field.w-3, .cf-dyn-grid > .field.w-4 { grid-column:1 / -1; }
        .client-pane { padding:16px; padding-bottom:60px; }

        /* 탭바에 토글 버튼 포함 */
        .client-tab-bar { gap:4px; }
        .client-tab .ct-close { opacity:0.5; }
    }
</style>
@endpush

@section('content')
<div class="crm-wrap">
    {{-- 좌측 사이드바 --}}
    <div class="crm-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title" id="clientListTitle">의뢰자 목록</div>
            <input class="sidebar-search" type="text" id="clientSearch" placeholder="검색..." oninput="filterClients()">
            <div class="sidebar-filters">
                <button class="filter-chip active" data-grade="" onclick="setGradeFilter(this)">전체</button>
                <button class="filter-chip" data-grade="normal" onclick="setGradeFilter(this)">일반</button>
                <button class="filter-chip" data-grade="vip" onclick="setGradeFilter(this)">VIP</button>
                <button class="filter-chip" data-grade="rental" onclick="setGradeFilter(this)">렌탈</button>
            </div>
        </div>
        <div class="sidebar-list" id="clientList"></div>
        <div class="sidebar-pagination" id="clientPagination"></div>
        <div style="display:flex; gap:6px; margin:8px;">
            <div class="sidebar-add" style="flex:1; margin:0;" onclick="openNewClientModal()">+ 의뢰자 등록</div>
            <div class="sidebar-add" style="flex:0; margin:0; white-space:nowrap;" onclick="openExcelImportModal('clients','의뢰자')"><x-icon name="download" :size="13"/> 엑셀</div>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- 우측 메인 --}}
    <div class="crm-main">
        <div class="client-tab-bar" id="clientTabBar">
            <button class="sidebar-toggle" onclick="openSidebar()"><x-icon name="users" :size="14"/> 고객목록</button>
            <span style="padding:0 8px; color:var(--text-muted); font-size:11px;">열린 의뢰자가 없습니다</span>
        </div>
        <div class="client-content" id="clientContent">
            <div class="client-empty" id="clientEmpty">좌측 목록에서 의뢰자를 선택하세요</div>
        </div>
    </div>
</div>

{{-- 새 의뢰자 모달 — 캘린더 일정 등록과 동일 문법 (디자인 1a: 히어로 닉네임 + 번호 카드 스택 + 작성 현황 사이드바, 장비는 2b 자동 연동 안내) --}}
<div class="new-client-overlay" id="newClientOverlay" onclick="if(event.target===this) drgoModalMinimize(this, '+ 새 의뢰자', '👤')">
    <div class="new-client-modal ncm">
        {{-- 헤더: 메타 + 액션 + 히어로 닉네임 + 등급/유형 필 --}}
        <div class="ncm-head">
            <div class="ncm-head-top">
                <div class="ncm-meta"><b>신규 의뢰자</b><span>·</span><span id="ncmToday"></span></div>
                <div class="ncm-actions">
                    <button type="button" class="ncm-btn" onclick="closeNewClientModal()">취소</button>
                    <button type="button" class="ncm-btn primary" onclick="createClient()">등록</button>
                    <button type="button" class="ncm-btn icon" onclick="closeNewClientModal()" title="닫기">✕</button>
                </div>
            </div>
            <input class="ncm-hero" id="ncNickname" placeholder="의뢰자 닉네임을 입력하세요 *" autocomplete="off">
            <div class="ncm-pills">
                <label class="ncm-pill">등급
                    <select id="ncGrade">
                        <option value="normal">일반</option>
                        <option value="vip">VIP</option>
                        <option value="rental">렌탈</option>
                    </select>
                </label>
                <label class="ncm-pill">의뢰자 유형
                    <select id="ncClientType">
                        <option value="">선택</option>
                        <option value="personal">개인</option>
                        <option value="enterprise">엔터</option>
                        <option value="studio">스튜디오</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="ncm-body">
            <div class="ncm-cards">
                {{-- 01 플랫폼 / 방송 정보 --}}
                <div class="ncm-card" data-sec="플랫폼 / 방송">
                    <div class="ncm-card-head"><span class="ncm-no">01</span><span class="ncm-title">플랫폼 / 방송 정보</span><span class="ncm-cnt"></span></div>
                    <div class="ncm-label">플랫폼</div>
                    <div id="ncPlatformsWrap"></div>
                    <div class="ncm-label" style="margin-top:16px;">방송 주제</div>
                    <div id="ncTopicsWrap"></div>
                    <div class="ncm-grid2" style="margin-top:16px;">
                        <div><div class="ncm-label">방송 아이디</div><input class="ncm-input" id="ncBroadcastId" placeholder="플랫폼 방송 ID/채널명"></div>
                        <div><div class="ncm-label">방송 경력</div>
                            <select class="ncm-input" id="ncCareer">
                                <option value="">선택</option>
                                <option value="처음">처음</option>
                                <option value="초보">초보</option>
                                <option value="경력">경력</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- 02 기본 정보 --}}
                <div class="ncm-card" data-sec="기본 정보">
                    <div class="ncm-card-head"><span class="ncm-no">02</span><span class="ncm-title">기본 정보</span><span class="ncm-cnt"></span></div>
                    <div class="ncm-grid2">
                        <div><div class="ncm-label">이름</div><input class="ncm-input" id="ncName"></div>
                        <div><div class="ncm-label">연락처</div><input class="ncm-input" id="ncPhone" placeholder="010-0000-0000"></div>
                        <div><div class="ncm-label">성별</div>
                            <select class="ncm-input" id="ncGender">
                                <option value="">선택</option>
                                <option value="female">여성</option>
                                <option value="male">남성</option>
                                <option value="other">기타</option>
                            </select>
                        </div>
                        <div><div class="ncm-label">소속</div><input class="ncm-input" id="ncAffiliation"></div>
                    </div>
                    <div style="margin-top:14px;"><div class="ncm-label">주소</div>
                        <div style="display:flex;gap:8px;">
                            <input class="ncm-input" id="ncAddress" placeholder="주소 검색 버튼으로 입력하세요" readonly style="flex:1;background:var(--surface2);cursor:pointer;" onclick="ncSearchAddress()">
                            <button type="button" class="ncm-btn primary" style="white-space:nowrap;" onclick="ncSearchAddress()">주소 검색</button>
                        </div>
                        <input class="ncm-input" id="ncAddressDetail" placeholder="상세주소 (동/호수 등) 직접 입력" style="margin-top:8px;">
                    </div>
                    <div style="margin-top:14px;"><div class="ncm-label">특이사항</div>
                        <textarea class="ncm-input ncm-ta" id="ncImportantMemo" placeholder="응대 시 참고할 내용"></textarea>
                    </div>
                </div>

                {{-- 03 장비 정보 — 직접 입력 없음, 프로젝트 자동 연동 안내 (읽기 전용) --}}
                <div class="ncm-card ncm-equip">
                    <div class="ncm-card-head"><span class="ncm-no">03</span><span class="ncm-title">장비 정보</span><span class="ncm-badge">자동 연동</span></div>
                    <div class="ncm-equip-note">
                        <span class="ncm-equip-ico">⟳</span>
                        <div>장비 정보는 <b>프로젝트에서 자동으로 불러옵니다.</b><br>등록 시 입력할 필요 없이, 프로젝트 생성 후 의뢰자 조회 화면에 표시돼요.</div>
                    </div>
                </div>

                {{-- 04 의뢰자 성향 --}}
                <div class="ncm-card" data-sec="의뢰자 성향">
                    <div class="ncm-card-head"><span class="ncm-no">04</span><span class="ncm-title">의뢰자 성향</span><span class="ncm-cnt"></span></div>
                    <div class="ncm-label">의뢰자 성격</div>
                    <input class="ncm-input" id="ncPersonality" placeholder="예: 꼼꼼함, 빠른 결정, 의견 수용 적극적">
                    <div class="ncm-label" style="margin-top:14px;">예산 성향</div>
                    <div class="chk-group" onchange="syncRadioState(this)">
                        <label class="chk-chip"><input type="radio" name="budget-nc" value="풍족"><span>풍족</span></label>
                        <label class="chk-chip"><input type="radio" name="budget-nc" value="부족"><span>부족</span></label>
                        <label class="chk-chip"><input type="radio" name="budget-nc" value="모름"><span>모름</span></label>
                        <label class="chk-chip"><input type="radio" name="budget-nc" value="직접입력"><span>직접입력</span></label>
                    </div>
                    <input type="text" class="ncm-input" id="budget-nc-etc" placeholder="예산 성향 직접 입력" style="margin-top:8px; display:none;">
                </div>

                {{-- 05 유입경로 --}}
                <div class="ncm-card" data-sec="유입경로">
                    <div class="ncm-card-head"><span class="ncm-no">05</span><span class="ncm-title">유입경로</span><span class="ncm-cnt"></span></div>
                    <div class="chk-group" onchange="syncRadioState(this)">
                        <label class="chk-chip"><input type="radio" name="ncInflow" value="search"><span>검색</span></label>
                        <label class="chk-chip"><input type="radio" name="ncInflow" value="referral"><span>지인 소개</span></label>
                        <label class="chk-chip"><input type="radio" name="ncInflow" value="sns"><span>SNS</span></label>
                        <label class="chk-chip"><input type="radio" name="ncInflow" value="ad"><span>광고</span></label>
                        <label class="chk-chip"><input type="radio" name="ncInflow" value="community"><span>커뮤니티</span></label>
                        <label class="chk-chip"><input type="radio" name="ncInflow" value="other"><span>기타</span></label>
                    </div>
                </div>

                {{-- 06 메모 --}}
                <div class="ncm-card" data-sec="메모">
                    <div class="ncm-card-head"><span class="ncm-no">06</span><span class="ncm-title">메모</span><span class="ncm-cnt"></span></div>
                    <textarea class="ncm-input ncm-ta" id="ncMemo" placeholder="내부 공유용 메모를 입력하세요"></textarea>
                </div>
            </div>

            {{-- 사이드바: 작성 현황 + 남은 필수 항목 --}}
            <div class="ncm-side">
                <div class="ncm-side-card">
                    <div class="ncm-side-title">작성 현황</div>
                    <div class="ncm-prog"><span class="ncm-pct" id="ncmPct">0%</span><span class="ncm-prog-sub" id="ncmCnt"></span></div>
                    <div class="ncm-bar"><div id="ncmBarFill"></div></div>
                    <div class="ncm-secs" id="ncmSecs"></div>
                </div>
                <div class="ncm-side-card">
                    <div class="ncm-side-title">남은 필수 항목</div>
                    <div id="ncmRequired" style="display:flex;flex-wrap:wrap;gap:6px;"><span class="ncm-req">닉네임</span></div>
                    <div class="ncm-req-done" id="ncmRequiredDone" style="display:none;">✓ 필수 항목 완료</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 앨범 뷰어 모달 --}}
<div id="albumOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:600; align-items:center; justify-content:center;" onclick="if(event.target===this) closeAlbumViewer()">
    <button onclick="closeAlbumViewer()" style="position:fixed; top:16px; right:16px; background:none; border:none; color:#fff; font-size:28px; cursor:pointer; z-index:603;">×</button>
    <button onclick="albumNavDir(-1)" style="position:fixed; left:16px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.12); border:none; color:#fff; width:44px; height:44px; border-radius:50%; font-size:20px; cursor:pointer; z-index:603;">‹</button>
    <button onclick="albumNavDir(1)" style="position:fixed; right:16px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.12); border:none; color:#fff; width:44px; height:44px; border-radius:50%; font-size:20px; cursor:pointer; z-index:603;">›</button>
    <div style="display:flex; flex-direction:column; align-items:center;">
        <div id="albumMediaWrap" style="display:flex; align-items:center; justify-content:center; min-height:200px;"></div>
        <div style="text-align:center; margin-top:10px;">
            <div id="albumName" style="color:#fff; font-size:13px;"></div>
            <div id="albumNote" style="color:rgba(255,255,255,0.5); font-size:11px;"></div>
            <div id="albumCounter" style="color:rgba(255,255,255,0.4); font-size:11px; margin-top:4px;"></div>
        </div>
    </div>
    <div id="albumZoomControls" style="display:none; position:fixed; bottom:20px; left:50%; transform:translateX(-50%); gap:8px; z-index:603;">
        <button onclick="albumZoomStep(-1)" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:16px;cursor:pointer;">−</button>
        <span id="albumZoomLevel" style="min-width:48px;text-align:center;color:#fff;font-size:13px;font-weight:600;line-height:36px;">100%</span>
        <button onclick="albumZoomStep(1)" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:16px;cursor:pointer;">+</button>
        <button onclick="albumZoomReset()" style="height:36px;border-radius:18px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:11px;cursor:pointer;padding:0 12px;">맞춤</button>
    </div>
</div>

<div class="toast" id="toast"></div>
@endsection

@push('scripts')
@include('partials.tag-picker-assets')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
// 프로젝트 태그 위젯(공용 CrmTagPicker) — 컨테이너만 렌더, 삽입 후 init
function renderTagPicker(id){
    return `<div class="field" style="margin-top:10px;"><div class="crm-tagpick" data-key="client-${id}" data-major="[]" data-minor="[]"></div></div>`;
}

// ── 서버 에러 메시지 통합 핸들러 ──
// 모든 fetch 호출의 실패 응답에서 일관된 형식으로 alert를 띄움.
// 형식: '[코드 422] 검증 실패\n• name: 이름은 필수입니다'
async function showFetchError(res, prefix) {
    let detail = '';
    let payload = null;
    try { payload = await res.json(); } catch(e) {}

    if (payload) {
        if (payload.message) detail += payload.message;
        if (payload.error) detail += (detail ? '\n' : '') + payload.error;
        if (payload.errors && typeof payload.errors === 'object') {
            const lines = [];
            for (const [field, msgs] of Object.entries(payload.errors)) {
                const ms = Array.isArray(msgs) ? msgs.join(', ') : String(msgs);
                lines.push(`• ${field}: ${ms}`);
            }
            if (lines.length) detail += (detail ? '\n' : '') + lines.join('\n');
        }
        if (payload.exception) detail += (detail ? '\n' : '') + `[예외] ${payload.exception}`;
    } else {
        try { detail = await res.text(); } catch(e) {}
    }
    if (!detail) detail = '응답 본문 없음';
    alert(`[${prefix||'요청 실패'} · 코드 ${res.status} ${res.statusText||''}]\n${detail}`.trim());
}

// ── 부모(최상위) 탭 시스템으로 라우팅 (iframe 중첩 방지) ──
function openTopTab(type, url) {
    try {
        let w = window;
        // 가장 바깥(window.top)의 drgoTabs를 찾음
        for (let i = 0; i < 5 && w !== w.parent; i++) {
            if (w.parent && w.parent.drgoTabs && typeof w.parent.drgoTabs.openNav === 'function') {
                return w.parent.drgoTabs.openNav(type, url);
            }
            w = w.parent;
        }
    } catch (e) { /* cross-origin 등 */ }
    // fallback: 직접 이동
    if (window.top && window.top !== window) {
        try { window.top.location.href = url; return; } catch (e) {}
    }
    window.location.href = url;
}
const GRADE_LABELS = { normal:'일반', vip:'VIP', rental:'렌탈' };
// HTML 이스케이프 — 사용자 입력(이름/메모/파일명 등)을 innerHTML에 넣기 전 필수 (XSS 방지)
function _esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
const GRADE_COLORS = { normal:'var(--text-muted)', vip:'var(--accent)', rental:'var(--blue)' };

const PLATFORM_OPTIONS = ['SOOP','유튜브','치지직','틱톡','팬더티비','기타'];
// 플랫폼 아이콘
const PLATFORM_ICONS = {
    'SOOP':'/icons/platforms/soop.svg',
    '유튜브':'/icons/platforms/youtube.svg',
    '치지직':'/icons/platforms/chzzk.svg',
    '틱톡':'/icons/platforms/tiktok.svg',
    '팬더티비':'/icons/platforms/pandatv.svg',
    '기타':'/icons/platforms/etc.svg',
};
function platformLabelHtml(p){
    const ic=PLATFORM_ICONS[p];
    return ic?`<img src="${ic}" alt="${p}" style="width:20px;height:20px;border-radius:4px;vertical-align:middle;margin-right:4px;">${p}`:p;
}
// 아바타 대체용 — 아이콘 있는 첫 플랫폼의 로고 경로 (없으면 null → 이니셜 유지)
function platformAvatarIcon(platforms){
    for(const p of (platforms||[])){ if(PLATFORM_ICONS[p]) return PLATFORM_ICONS[p]; }
    return null;
}
const TOPIC_OPTIONS = ['소통','게임','노래','먹방','야외','버추얼','코인','주식','기타','미정'];

function renderCheckboxGroup(group, id, options, selected, etcText) {
    const sel = new Set(selected || []);
    const hasEtc = sel.has('기타');
    const items = options.map(opt => {
        const checked = sel.has(opt) ? 'checked' : '';
        const onChange = opt === '기타' ? ` onchange="toggleEtcInput('${group}',${id})"` : '';
        return `<label class="chk-chip${checked?' on':''}">
            <input type="checkbox" name="${group}-${id}" value="${opt}" ${checked}${onChange}>
            <span>${opt}</span>
        </label>`;
    }).join('');
    const etcInput = `<input type="text" class="field-input" id="f-${group}-etc-${id}" value="${(etcText||'').replace(/"/g,'&quot;')}" placeholder="기타 내용 입력" style="margin-top:8px; display:${hasEtc?'block':'none'};">`;
    return `<div class="chk-group" id="chkgroup-${group}-${id}" onchange="syncChipState(this)">${items}</div>${etcInput}`;
}

// 라디오 pill 그룹 — 캘린더와 동일한 버튼형 단일 선택 (options: [{value,label}] 또는 문자열 배열)
function renderRadioGroup(name, options, selectedValue) {
    return `<div class="chk-group" onchange="syncRadioState(this)">${options.map(opt => {
        const value = typeof opt === 'string' ? opt : opt.value;
        const label = typeof opt === 'string' ? opt : opt.label;
        const checked = selectedValue === value ? 'checked' : '';
        return `<label class="chk-chip${checked ? ' on' : ''}">
            <input type="radio" name="${name}" value="${value.replace(/"/g, '&quot;')}" ${checked}>
            <span>${label}</span>
        </label>`;
    }).join('')}</div>`;
}

function syncRadioState(wrap) {
    wrap.querySelectorAll('label.chk-chip').forEach(l => {
        const r = l.querySelector('input[type=radio]');
        l.classList.toggle('on', !!r?.checked);
    });
    // 예산 성향 직접입력 토글
    const name = wrap.querySelector('input[type=radio]')?.name || '';
    if (name.startsWith('budget')) {
        const etc = document.getElementById(`${name}-etc`);
        if (etc) {
            const isCustom = wrap.querySelector('input[value="직접입력"]')?.checked;
            etc.style.display = isCustom ? 'block' : 'none';
        }
    }
}

function getRadioValue(name) {
    return document.querySelector(`input[name="${name}"]:checked`)?.value || '';
}

// 예산 성향 값 수집 — 풍족/부족/모름은 그대로, 직접입력은 입력 텍스트
function collectBudgetStyle(name) {
    const sel = getRadioValue(name);
    if (!sel) return null;
    if (sel === '직접입력') return document.getElementById(`${name}-etc`)?.value.trim() || null;
    return sel;
}

// 저장된 예산 성향 → 라디오 상태 (풍족/부족/모름 외 자유 서술은 직접입력)
function budgetRadioState(stored) {
    const v = (stored || '').trim();
    if (!v) return { selected: '', etc: '' };
    if (['풍족', '부족', '모름'].includes(v)) return { selected: v, etc: '' };
    return { selected: '직접입력', etc: v };
}

const INFLOW_OPTIONS = [
    { value: 'search', label: '검색' }, { value: 'referral', label: '지인 소개' }, { value: 'sns', label: 'SNS' },
    { value: 'ad', label: '광고' }, { value: 'community', label: '커뮤니티' }, { value: 'other', label: '기타' },
];
const BUDGET_OPTIONS = ['풍족', '부족', '모름', '직접입력'];

// 상세 탭용 — 중첩 템플릿 리터럴을 피하기 위한 필드 렌더 헬퍼
function renderInflowField(id, stored) {
    return renderRadioGroup(`inflow-${id}`, INFLOW_OPTIONS, stored || '');
}

function renderBudgetField(id, stored) {
    const b = budgetRadioState(stored);
    return renderRadioGroup(`budget-${id}`, BUDGET_OPTIONS, b.selected)
        + `<input type="text" class="field-input" id="budget-${id}-etc" value="${b.etc.replace(/"/g, '&quot;')}" placeholder="예산 성향 직접 입력" style="margin-top:8px; display:${b.selected === '직접입력' ? 'block' : 'none'};">`;
}

function toggleEtcInput(group, id) {
    const wrap = document.getElementById(`chkgroup-${group}-${id}`);
    const etc = document.getElementById(`f-${group}-etc-${id}`);
    if (!wrap || !etc) return;
    const checked = wrap.querySelector(`input[value="기타"]`)?.checked;
    etc.style.display = checked ? 'block' : 'none';
    if (!checked) etc.value = '';
}

function syncChipState(wrap) {
    wrap.querySelectorAll('label.chk-chip').forEach(l => {
        const cb = l.querySelector('input[type=checkbox]');
        l.classList.toggle('on', !!cb?.checked);
    });
}

function collectCheckboxGroup(group, id) {
    const wrap = document.getElementById(`chkgroup-${group}-${id}`);
    if (!wrap) return { values:[], etc:'' };
    const values = Array.from(wrap.querySelectorAll('input[type=checkbox]:checked')).map(c=>c.value);
    const etc = document.getElementById(`f-${group}-etc-${id}`)?.value?.trim() || '';
    return { values, etc };
}

let allClients = [];
let currentGrade = '';
let openClientTabs = []; // {id, name, nickname, grade, data, activeSubTab}
let activeClientId = null;
let customFieldDefs = []; // 동적 필드 정의

// 동적 필드 정의 로드
async function loadCustomFieldDefs() {
    try {
        const res = await fetch('/api/client-fields/active', { headers:{ 'Accept':'application/json' } });
        if (res.ok) customFieldDefs = await res.json();
    } catch(e) { customFieldDefs = []; }
}

// 프로젝트 유형 마스터 로드 → window.CONSULTATION_TYPES + TYPE_LABELS 자동 동기화
window.CONSULTATION_TYPES = window.CONSULTATION_TYPES || [];
async function loadConsultationTypes() {
    try {
        const res = await fetch('/api/consultation-types/active', { headers:{ 'Accept':'application/json' } });
        if (res.ok) {
            window.CONSULTATION_TYPES = await res.json();
            // 디스플레이 라벨맵 갱신
            window.CONSULTATION_TYPES.forEach(t => { window.TYPE_LABELS[t.key] = t.label; });
        }
    } catch(e) {}
}

// 페이지네이션 상태
let clientPage = 1;
let clientLastPage = 1;
let clientTotal = 0;
let clientSearchTimer = null;

// 이전 세션 상태 복원 (페이지/검색/등급)
function __readSavedClientState() {
    try {
        const raw = sessionStorage.getItem('drgo_client_tabs');
        return raw ? (JSON.parse(raw) || {}) : {};
    } catch { return {}; }
}
const __savedClientState = __readSavedClientState();
if (__savedClientState.search) {
    const si = document.getElementById('clientSearch');
    if (si) si.value = __savedClientState.search;
}
if (__savedClientState.grade) {
    currentGrade = __savedClientState.grade;
    document.querySelectorAll('.filter-chip').forEach(b => {
        b.classList.toggle('active', b.dataset.grade === __savedClientState.grade);
    });
}

// ── 초기화 ──
loadCustomFieldDefs();
loadConsultationTypes();
loadClientList(__savedClientState.page || 1).then(async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const openId = urlParams.get('open');
    if (openId) {
        await openClient(parseInt(openId));
        history.replaceState(null, '', window.location.pathname);
    } else {
        await restoreClientTabs();
    }
});

async function loadClientList(page = 1) {
    clientPage = page;
    const search = document.getElementById('clientSearch').value.trim();
    const params = new URLSearchParams({ page: String(page), per_page: '20' });
    if (search) params.set('search', search);
    if (currentGrade) params.set('grade', currentGrade);

    const res = await fetch('/api/clients/list?' + params, { headers:{ 'Accept':'application/json' } });
    const data = await res.json();
    // 응답 구조: {data, current_page, last_page, per_page, total}
    allClients = data.data || [];
    clientPage = data.current_page || 1;
    clientLastPage = data.last_page || 1;
    clientTotal = data.total || 0;
    renderClientList();
    renderClientPagination();
    // 페이지/검색/등급 상태도 세션에 보존 (탭 전환·뒤로가기 시 복원)
    saveClientTabs();
}

function filterClients() {
    // 검색 입력 시 디바운스 후 첫 페이지부터 다시 fetch
    clearTimeout(clientSearchTimer);
    clientSearchTimer = setTimeout(() => loadClientList(1), 250);
}

function setGradeFilter(btn) {
    document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentGrade = btn.dataset.grade;
    loadClientList(1);
}

function renderClientPagination() {
    const wrap = document.getElementById('clientPagination');
    if (!wrap) return;
    if (clientTotal === 0 || clientLastPage <= 1) {
        wrap.innerHTML = clientTotal > 0
            ? `<span class="pg-info">${clientTotal}명</span>`
            : '';
        return;
    }
    // 페이지 번호 묶음 (현재 페이지 좌우 1개씩 — 좁은 사이드바 대응)
    const start = Math.max(1, clientPage - 1);
    const end = Math.min(clientLastPage, clientPage + 1);
    let btns = '';
    btns += `<button onclick="loadClientList(1)" ${clientPage === 1 ? 'disabled' : ''} title="처음">«</button>`;
    btns += `<button onclick="loadClientList(${clientPage - 1})" ${clientPage === 1 ? 'disabled' : ''} title="이전">‹</button>`;
    for (let p = start; p <= end; p++) {
        btns += `<button class="${p === clientPage ? 'active' : ''}" onclick="loadClientList(${p})">${p}</button>`;
    }
    btns += `<button onclick="loadClientList(${clientPage + 1})" ${clientPage === clientLastPage ? 'disabled' : ''} title="다음">›</button>`;
    btns += `<button onclick="loadClientList(${clientLastPage})" ${clientPage === clientLastPage ? 'disabled' : ''} title="끝">»</button>`;
    wrap.innerHTML = `
        <div class="pg-row">${btns}</div>
        <span class="pg-info">${clientPage}/${clientLastPage} · 총 ${clientTotal}명</span>
    `;
}

function renderClientList() {
    const list = document.getElementById('clientList');
    const filtered = allClients;

    // 타이틀 업데이트 (현재 페이지에 표시된 수가 아닌 전체)
    const titleEl = document.getElementById('clientListTitle');
    if (titleEl) titleEl.textContent = `의뢰자 목록 (${clientTotal}명)`;

    if (!filtered.length) {
        list.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">결과 없음</div>';
        return;
    }

    list.innerHTML = filtered.map(c => {
        const active = activeClientId === c.id ? 'active' : '';
        const displayName = c.nickname && c.name ? `${c.nickname} / ${c.name}` : (c.nickname || c.name);
        const platforms = Array.isArray(c.platforms) && c.platforms.length ? c.platforms.join(', ') : '';
        return `<div class="sidebar-item ${active}" onclick="openClient(${c.id})">
            <div class="item-info" style="flex:1;min-width:0;">
                <div class="item-name">${_esc(displayName)}</div>
                <div class="item-sub">${_esc(platforms) || '<span style="opacity:0.5;">플랫폼 없음</span>'}</div>
            </div>
            <span class="item-grade grade-${c.grade}">${GRADE_LABELS[c.grade]||''}</span>
        </div>`;
    }).join('');
}

// ── 의뢰자 탭 ──
async function openClient(id) {
    // 모바일: 사이드바 닫기
    if(window.innerWidth<=768) closeSidebar();
    // 이미 열려있으면 전환만
    const existing = openClientTabs.find(t => t.id === id);
    if (existing) { activateClientTab(id); return; }

    // 데이터 로드
    const res = await fetch(`/api/clients/${id}/detail`, { headers:{ 'Accept':'application/json' } });
    if (!res.ok) { showToast('로드 실패'); return; }
    const data = await res.json();

    openClientTabs.push({
        id: data.id,
        name: data.name,
        nickname: data.nickname,
        grade: data.grade,
        data,
        activeSubTab: 'info'
    });

    activateClientTab(id);
}

function activateClientTab(id) {
    activeClientId = id;
    renderClientTabs();
    renderClientContent(id);
    renderClientList();
    saveClientTabs();
}

function closeClientTab(id, e) {
    if (e) e.stopPropagation();
    const idx = openClientTabs.findIndex(t => t.id === id);
    if (idx === -1) return;

    openClientTabs.splice(idx, 1);
    const pane = document.getElementById('cpane-' + id);
    if (pane) pane.remove();

    if (activeClientId === id) {
        if (openClientTabs.length) {
            const next = openClientTabs[Math.min(idx, openClientTabs.length - 1)];
            activateClientTab(next.id);
        } else {
            activeClientId = null;
            renderClientTabs();
            document.getElementById('clientContent').innerHTML = '<div class="client-empty" id="clientEmpty">좌측 목록에서 의뢰자를 선택하세요</div>';
            renderClientList();
            saveClientTabs();
        }
    } else {
        renderClientTabs();
        saveClientTabs();
    }
}

function renderClientTabs() {
    const bar = document.getElementById('clientTabBar');
    if (!openClientTabs.length) {
        bar.innerHTML = '<span style="padding:0 8px; color:var(--text-muted); font-size:11px;">열린 의뢰자가 없습니다</span>';
        return;
    }
    bar.innerHTML = openClientTabs.map(t => {
        const cls = t.id === activeClientId ? 'active' : '';
        return `<button class="client-tab ${cls}" draggable="true" data-client-id="${t.id}" onclick="activateClientTab(${t.id})">
            ${_esc(t.nickname || t.name)}
            <span class="ct-close" onclick="closeClientTab(${t.id}, event)">✕</span>
        </button>`;
    }).join('');
    bindClientTabDrag();
}

let _clientTabDragId = null;
function bindClientTabDrag() {
    const bar = document.getElementById('clientTabBar');
    bar.querySelectorAll('.client-tab').forEach(el => {
        el.addEventListener('dragstart', e => {
            _clientTabDragId = +el.dataset.clientId;
            el.classList.add('ct-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', el.dataset.clientId); } catch (_) {}
        });
        el.addEventListener('dragend', () => {
            el.classList.remove('ct-dragging');
            bar.querySelectorAll('.ct-drag-over').forEach(x => x.classList.remove('ct-drag-over'));
            _clientTabDragId = null;
        });
        el.addEventListener('dragover', e => {
            if (_clientTabDragId == null || +el.dataset.clientId === _clientTabDragId) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            el.classList.add('ct-drag-over');
        });
        el.addEventListener('dragleave', () => el.classList.remove('ct-drag-over'));
        el.addEventListener('drop', e => {
            e.preventDefault();
            el.classList.remove('ct-drag-over');
            const fromId = _clientTabDragId;
            const toId = +el.dataset.clientId;
            if (fromId == null || fromId === toId) return;
            const fromIdx = openClientTabs.findIndex(t => t.id === fromId);
            const toIdx = openClientTabs.findIndex(t => t.id === toId);
            if (fromIdx < 0 || toIdx < 0) return;
            const [moved] = openClientTabs.splice(fromIdx, 1);
            openClientTabs.splice(toIdx, 0, moved);
            renderClientTabs();
            saveClientTabs();
        });
    });
}

const STAGE_LABELS = {consulting:'상담',equipment:'장비파악',proposal:'일정제안',survey:'사전답사',estimate:'견적/계약',payment:'결제/예약',visit:'세팅',delivery:'납품',as:'AS',done:'완료',cancelled:'취소'}; // 폴백 — 유형별 라벨은 서버 stage_label 우선
window.TYPE_LABELS = @json(\App\Models\ConsultationType::map(false)); {{-- 비활성 포함 — 기존 프로젝트 라벨 표시용 --}}
const TYPE_LABELS = window.TYPE_LABELS;

function renderClientContent(id) {
    const tab = openClientTabs.find(t => t.id === id);
    if (!tab) return;
    const d = tab.data;

    document.querySelectorAll('.client-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('clientEmpty')?.remove();

    let pane = document.getElementById('cpane-' + id);
    if (!pane) {
        pane = document.createElement('div');
        pane.id = 'cpane-' + id;
        pane.className = 'client-pane';
        document.getElementById('clientContent').appendChild(pane);

        // 닉네임 옆 플랫폼 아이콘 (다중이면 모두, 20px)
        const platIcons = (d.platforms || [])
            .filter(pf => PLATFORM_ICONS[pf])
            .map(pf => `<img src="${PLATFORM_ICONS[pf]}" alt="${pf}" title="${pf}" style="width:20px;height:20px;border-radius:5px;vertical-align:middle;">`)
            .join('');
        pane.innerHTML = `
        <div class="detail-header">
            <div class="detail-identity">
                <div>
                    <div class="detail-name" style="display:flex;align-items:center;gap:7px;">${_esc(d.nickname || d.name || '(이름 없음)')}${platIcons ? `<span style="display:inline-flex;gap:4px;align-items:center;">${platIcons}</span>` : ''}</div>
                    <div class="detail-meta">${_esc([d.name, GRADE_LABELS[d.grade], d.assigned_user].filter(Boolean).join(' · '))}</div>
                </div>
            </div>
            <div class="detail-actions">
                <button class="btn-log" onclick="openActivityLog('Client',${id},'${_esc((d.name||'').replace(/'/g,"\\'"))} 수정 로그')"><svg viewBox=\"0 0 24 24\" width=\"13\" height=\"13\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.1\" stroke-linecap=\"round\" stroke-linejoin=\"round\" style=\"vertical-align:-0.15em\"><path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"/><path d=\"M14 2v6h6M9 13h6M9 17h4\"/></svg> 로그</button>
                <button class="btn-save" id="ce-edit-${id}" onclick="clientEditMode(${id},true)">수정</button>
                <button class="btn-save" id="ce-save-${id}" style="display:none;" onclick="saveClient(${id})">저장</button>
                <button class="btn-log" id="ce-cancel-${id}" style="display:none;" onclick="clientEditMode(${id},false)">취소</button>
                <button class="btn-delete" onclick="deleteClient(${id})">삭제</button>
            </div>
        </div>

        <div class="sub-tabs" id="subtabs-${id}">
            <button class="sub-tab active" onclick="switchSubTab(${id},'info',this)">기본 정보</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'projects',this)">프로젝트 ${d.projects.length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'docs',this)">첨부파일 ${d.documents.length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'estimates',this)">견적서 ${(d.estimates||[]).length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'memo',this)">메모</button>
        </div>

        <!-- 기본 정보 -->
        <div class="sub-panel active" id="sub-info-${id}">
            {{-- 조회 뷰 (기본) — 디자인 3a: 좌측 섹션 타이틀 레일 + 우측 값 그리드 --}}
            <div id="view-info-${id}">${renderClientView(d)}</div>
            {{-- 수정 폼 (수정 버튼으로 전환) --}}
            <div id="edit-info-${id}" style="display:none;">
            <div class="form-grid">
                <div class="field">
                    <div class="field-label">닉네임 *</div>
                    <input class="field-input" id="f-nickname-${id}" value="${_esc(d.nickname||'')}">
                </div>
                <div class="field">
                    <div class="field-label">이름</div>
                    <input class="field-input" id="f-name-${id}" value="${_esc(d.name||'')}">
                </div>
                <div class="field">
                    <div class="field-label">전화번호</div>
                    <input class="field-input" id="f-phone-${id}" value="${_esc(d.phone||'')}">
                </div>
                <div class="field">
                    <div class="field-label">고객 유형</div>
                    <select class="field-input field-select" id="f-grade-${id}">
                        <option value="normal" ${d.grade==='normal'?'selected':''}>일반</option>
                        <option value="vip" ${d.grade==='vip'?'selected':''}>VIP</option>
                        <option value="rental" ${d.grade==='rental'?'selected':''}>렌탈</option>
                    </select>
                </div>
                <div class="field">
                    <div class="field-label">소속</div>
                    <input class="field-input" id="f-affiliation-${id}" value="${_esc(d.affiliation||'')}">
                </div>
                <div class="field">
                    <div class="field-label">성별</div>
                    <select class="field-input field-select" id="f-gender-${id}">
                        <option value="">미지정</option>
                        <option value="female" ${d.gender==='female'?'selected':''}>여성</option>
                        <option value="male" ${d.gender==='male'?'selected':''}>남성</option>
                        <option value="other" ${d.gender==='other'?'selected':''}>기타</option>
                    </select>
                </div>
            </div>
            <div class="form-grid" style="margin-top:14px;">
                <div class="field" style="grid-column:1/-1;">
                    <div class="field-label">주소</div>
                    <div style="display:flex; gap:6px;">
                        <input class="field-input" id="f-address-${id}" value="${_esc(d.address||'')}" readonly style="flex:1; cursor:pointer;" onclick="searchAddress(${id})">
                        <button class="btn-save" onclick="searchAddress(${id})" style="white-space:nowrap;">주소 검색</button>
                    </div>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <div class="field-label">상세주소</div>
                    <input class="field-input" id="f-address_detail-${id}" value="${_esc(d.address_detail||'')}">
                </div>
            </div>
            <div class="form-grid full" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">특이사항</div>
                    <textarea class="field-input field-textarea" id="f-important_memo-${id}">${_esc(d.important_memo||'')}</textarea>
                </div>
            </div>
            <div class="form-grid full" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">플랫폼</div>
                    ${renderCheckboxGroup('platforms', id, PLATFORM_OPTIONS, d.platforms||[], d.platform_etc||'')}
                </div>
            </div>
            <div class="form-grid full" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">방송 주제</div>
                    ${renderCheckboxGroup('topics', id, TOPIC_OPTIONS, d.content_types||[], d.topic_etc||'')}
                </div>
            </div>
            <div class="form-grid" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">방송 경력</div>
                    <select class="field-input field-select" id="f-career-${id}">
                        <option value="">선택</option>
                        <option value="처음" ${d.career==='처음'?'selected':''}>처음</option>
                        <option value="초보" ${d.career==='초보'?'selected':''}>초보</option>
                        <option value="경력" ${d.career==='경력'?'selected':''}>경력</option>
                    </select>
                </div>
                <div class="field">
                    <div class="field-label">의뢰자 유형</div>
                    <select class="field-input field-select" id="f-client_type-${id}">
                        <option value="">선택</option>
                        <option value="personal" ${d.client_type==='personal'?'selected':''}>개인</option>
                        <option value="enterprise" ${d.client_type==='enterprise'?'selected':''}>엔터</option>
                        <option value="studio" ${d.client_type==='studio'?'selected':''}>스튜디오</option>
                    </select>
                </div>
            </div>
            <div class="form-grid" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">방송 아이디</div>
                    <input class="field-input" id="f-broadcast_id-${id}" value="${_esc(d.broadcast_id||'')}" placeholder="플랫폼 방송 ID/채널명">
                </div>
                <div class="field">
                    <div class="field-label">최초 등록일</div>
                    <input class="field-input" value="${d.created_at||''}" readonly style="opacity:0.7; cursor:not-allowed;">
                </div>
            </div>
            <div class="form-grid full" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">유입경로</div>
                    ${renderInflowField(id, d.inflow_source)}
                </div>
            </div>

            <!-- 의뢰자 성향 -->
            <div style="margin-top:18px; padding:14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px;">
                <div style="font-size:12px; font-weight:700; color:var(--accent); margin-bottom:10px;">의뢰자 성향</div>
                <div class="form-grid full">
                    <div class="field">
                        <div class="field-label">의뢰자 성격</div>
                        <textarea class="field-input field-textarea" id="f-personality-${id}" rows="2" placeholder="예: 꼼꼼함, 빠른 결정, 의견 수용 적극적">${_esc(d.personality||'')}</textarea>
                    </div>
                </div>
                <div class="form-grid full" style="margin-top:10px;">
                    <div class="field">
                        <div class="field-label">예산 성향</div>
                        ${renderBudgetField(id, d.budget_style)}
                    </div>
                </div>
            </div>

            <!-- 동적 필드 (관리자 정의) -->
            ${renderCustomFields(d.custom_data || {}, id)}

            <!-- 장비 정보: 최근 프로젝트의 '장비 정보' 동적 필드 요약 -->
            ${renderEquipmentSummary(d.last_project_equipment)}

            <div style="display:flex; gap:8px; margin-top:16px; justify-content:flex-end;">
                <button class="btn-save" onclick="saveClient(${id})">저장</button>
            </div>
            </div>{{-- /edit-info --}}

            <!-- 메모 (인라인 스레드) -->
            <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                <div class="field-label" style="margin:0 0 10px; font-size:12px; font-weight:600;">메모</div>
                <div style="display:flex; gap:8px; margin-bottom:12px;">
                    <textarea class="field-input" id="info-memo-input-${id}" rows="1" placeholder="메모를 입력하세요..." style="flex:1; resize:none; min-height:34px;" onfocus="this.rows=2" onblur="if(!this.value)this.rows=1"></textarea>
                    <button class="btn-save" onclick="addMemo(${id}, 'info')" style="align-self:flex-end; white-space:nowrap; padding:7px 14px;">추가</button>
                </div>
                <div id="info-memos-${id}">${renderInfoMemos(d.memos, id)}</div>
            </div>
        </div>

        <!-- 프로젝트 -->
        <div class="sub-panel" id="sub-projects-${id}">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:8px;">
                <select id="project-sort-${id}" onchange="sortProjects(${id}, this.value)" style="background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 10px; color:var(--text); font-size:12px; cursor:pointer;">
                    <option value="desc">최신 순</option>
                    <option value="asc">오래된 순</option>
                </select>
                <button class="btn-save" onclick="openProjectForm(${id})">+ 프로젝트</button>
            </div>
            <div id="project-form-${id}" style="display:none; margin-bottom:16px; padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--surface);">
                <div class="form-grid">
                    <div class="field">
                        <div class="field-label">프로젝트명 *</div>
                        <input class="field-input" id="pf-name-${id}">
                    </div>
                    <div class="field">
                        <div class="field-label">프로젝트 유형</div>
                        <select class="field-input field-select" id="pf-type-${id}" onchange="updateWorkTypeOptions(${id})">
                            ${(window.CONSULTATION_TYPES || []).map(t => `<option value="${t.key}">${t.label}</option>`).join('') || '<option value="visit">방문세팅</option>'}
                        </select>
                    </div>
                </div>
                <div class="form-grid" style="margin-top:10px;">
                    <div class="field">
                        <div class="field-label">규모 *</div>
                        <select class="field-input field-select" id="pf-scale-${id}">
                            <option value="personal">개인</option>
                            <option value="studio">스튜디오</option>
                            <option value="corporate">기업</option>
                            <option value="rental">렌탈</option>
                            <option value="broadcast_room">방송룸</option>
                        </select>
                    </div>
                    <div class="field">
                        <div class="field-label">작업 유형 *</div>
                        <select class="field-input field-select" id="pf-work_type-${id}">
                            <option value="setup">세팅</option>
                            <option value="remote">원격</option>
                            <option value="filming">촬영중계</option>
                            <option value="design">디자인</option>
                            <option value="as">A/S</option>
                        </select>
                    </div>
                </div>
                <div class="field" style="margin-top:10px;">
                    <div class="field-label">프로젝트 개요</div>
                    <textarea class="field-input field-textarea" id="pf-memo-${id}" rows="2"></textarea>
                </div>
                ${renderTagPicker(id)}
                <div style="display:flex; gap:6px; margin-top:10px; justify-content:flex-end;">
                    <button class="btn-delete" onclick="document.getElementById('project-form-${id}').style.display='none'" style="border-color:var(--border); color:var(--text-muted);">취소</button>
                    <button class="btn-save" onclick="createProject(${id})">생성</button>
                </div>
            </div>
            <div id="project-list-${id}">
                ${renderProjectList(d.projects, id)}
            </div>
        </div>

        <!-- 첨부파일 -->
        <div class="sub-panel" id="sub-docs-${id}">
            <div style="margin-bottom:16px; padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--surface);">
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <label style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:var(--accent); color:var(--accent-text); border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">
                        + 파일 추가
                        <input type="file" multiple id="doc-file-${id}" style="display:none;" onchange="docAddFiles(${id}, this)">
                    </label>
                    <select class="field-input field-select" id="doc-cat-${id}" style="width:auto; padding:7px 10px; font-size:12px;">
                        <option>사진/이미지</option>
                        <option>현금영수증</option>
                        <option>사업자등록증</option>
                        <option>계약서</option>
                        <option>견적서</option>
                        <option>기타</option>
                    </select>
                    <input class="field-input" id="doc-note-${id}" placeholder="메모" style="width:140px; padding:7px 10px; font-size:12px;">
                    <button type="button" class="btn-save" id="doc-upload-btn-${id}" onclick="uploadDocs(${id})" disabled style="padding:7px 16px;">업로드</button>
                </div>
                <div id="doc-preview-${id}" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;"></div>
            </div>
            <div id="doc-list-${id}">
                ${renderDocList(d.documents, id)}
            </div>
        </div>

        <!-- 견적서 -->
        <div class="sub-panel" id="sub-estimates-${id}">
            <div id="estimate-list-${id}">
                ${renderEstimateList(d.estimates||[], id)}
            </div>
        </div>

        <!-- 메모 (전체) -->
        <div class="sub-panel" id="sub-memo-${id}">
            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <textarea class="field-input" id="new-memo-${id}" rows="2" placeholder="메모를 입력하세요..." style="flex:1; resize:vertical;"></textarea>
                <button class="btn-save" onclick="addMemo(${id}, 'full')" style="align-self:flex-end; white-space:nowrap;">메모 추가</button>
            </div>
            <div id="memo-thread-${id}">${renderMemoThread(d.memos, id)}</div>
        </div>
        `;
    }
    pane.classList.add('active');
}

function renderProjectList(projects, clientId, order) {
    if (!projects.length) return '<div style="padding:40px; text-align:center; color:var(--text-muted);">프로젝트가 없습니다.</div>';
    const sortOrder = order || 'desc';
    const sorted = [...projects].sort((a, b) => {
        const da = a.created_at || '', db = b.created_at || '';
        return sortOrder === 'desc' ? db.localeCompare(da) : da.localeCompare(db);
    });
    return sorted.map(p => `
        <div style="padding:10px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="openTopTab('projects','/projects/${p.id}')">
            <div>
                <div style="font-size:14px; font-weight:600;">${_esc(p.name)}</div>
                <div style="font-size:11px; color:var(--text-muted);">${_esc([TYPE_LABELS[p.type]||p.type, `상담 ${p.consultations_count}건`, p.created_at].filter(Boolean).join(' · '))}</div>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <span style="font-size:10px; padding:3px 8px; border-radius:4px; background:var(--surface2); color:var(--accent); font-weight:600;">${_esc(p.stage_label||STAGE_LABELS[p.stage]||p.stage)}</span>
                ${p.stage !== 'cancelled' ? `<button class="btn-cancel-sm" style="padding:3px 8px; font-size:10px; background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:5px; cursor:pointer;" onclick="event.stopPropagation(); cancelProject(${p.id}, ${clientId})" title="프로젝트 취소 (데이터 보존)">취소</button>` : ''}
                <button class="btn-delete" style="padding:3px 8px; font-size:10px;" onclick="event.stopPropagation(); deleteProject(${p.id}, ${clientId})" title="프로젝트 완전 삭제">삭제</button>
            </div>
        </div>
    `).join('');
}

function renderDocList(docs, clientId) {
    if (!docs.length) return '<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">첨부파일이 없습니다.</div>';
    return docs.map((doc, i) => {
        const isImg = doc.mime_type && doc.mime_type.startsWith('image/');
        const isVid = doc.mime_type && doc.mime_type.startsWith('video/');
        const ext = doc.file_name.split('.').pop().toUpperCase();
        const thumbContent = isImg
            ? `<img src="${doc.thumb_url||doc.view_url}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" decoding="async">`
            : isVid ? `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);font-size:14px;">▶</div>`
            : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);font-size:10px;font-weight:600;color:var(--text-muted);">${ext}</div>`;
        return `<div style="display:flex; align-items:center; gap:10px; padding:8px 10px; border-bottom:1px solid var(--border);" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='transparent'">
            <div style="width:40px; height:40px; border-radius:6px; overflow:hidden; flex-shrink:0; cursor:pointer; border:1px solid var(--border);" onclick="openAlbumViewer(${clientId},${i})">${thumbContent}</div>
            <div style="flex:1; min-width:0;">
                <div style="font-size:12px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${_esc(doc.file_name)}">${_esc(doc.file_name)}</div>
                <div style="font-size:10px; color:var(--text-muted);">${doc.note ? _esc(doc.note) + ' · ' : ''}${doc.created_at}</div>
            </div>
            <div style="display:flex; gap:6px; flex-shrink:0;">
                <a href="${doc.download_url}" style="padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; background:var(--surface2); border:1px solid var(--border); color:var(--accent); text-decoration:none; transition:all 0.12s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">다운로드</a>
                <button onclick="deleteDoc(${doc.id},${clientId})" style="padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; background:none; border:1px solid var(--red); color:var(--red); cursor:pointer; transition:all 0.12s;" onmouseover="this.style.background='var(--red)';this.style.color='#fff'" onmouseout="this.style.background='none';this.style.color='var(--red)'">삭제</button>
            </div>
        </div>`;
    }).join('');
}

const EST_STATUS = {created:'작성중',editing:'수정중',completed:'완료',paid:'결제완료',hold:'보류'};
const EST_COLOR = {created:'var(--text-muted)',editing:'var(--accent)',completed:'var(--green)',paid:'var(--accent2)',hold:'var(--red)'};

function renderEstimateList(estimates, clientId) {
    if (!estimates.length) return '<div style="padding:40px; text-align:center; color:var(--text-muted); font-size:13px;">등록된 견적서가 없습니다.</div>';
    return estimates.map(e => {
        const statusLabel = EST_STATUS[e.status] || e.status;
        const statusColor = EST_COLOR[e.status] || 'var(--text-muted)';
        const amount = e.total_amount ? Number(e.total_amount).toLocaleString() + '원' : '';
        const isPaid = e.status === 'paid';
        return `<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:8px;">
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:12px; color:var(--text-muted);">#${e.id}</span>
                    <span style="font-size:11px; padding:2px 8px; border-radius:4px; background:color-mix(in srgb, ${statusColor} 20%, transparent); color:${statusColor}; font-weight:600;">${statusLabel}</span>
                </div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
                    ${amount ? '<span style="font-weight:600; color:var(--text);">' + amount + '</span> · ' : ''}${e.created_at}${e.creator_name ? ' · ' + _esc(e.creator_name) : ''}
                </div>
            </div>
            <div style="display:flex; gap:6px;">
                <button onclick="window.open('${e.print_url}','estimate_print','width=900,height=700,scrollbars=yes,resizable=yes')" style="padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; background:var(--surface2); border:1px solid var(--border); color:var(--text); cursor:pointer;">보기</button>
                ${isPaid ? '<span style="padding:4px 10px; font-size:11px; color:var(--text-muted); opacity:0.5;">결제완료</span>' : `<button onclick="window.open('${e.edit_url}','_blank')" style="padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; background:none; border:1px solid var(--accent); color:var(--accent); cursor:pointer;">편집</button>`}
            </div>
        </div>`;
    }).join('');
}

// ── 주소 검색 (Daum Postcode) ──
function searchAddress(clientId) {
    if (typeof daum === 'undefined' || !daum.Postcode) {
        // 다음 주소 API 동적 로드
        const script = document.createElement('script');
        script.src = '//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
        script.onload = () => _openPostcode(clientId);
        document.head.appendChild(script);
    } else {
        _openPostcode(clientId);
    }
}
function _openPostcode(clientId) {
    new daum.Postcode({
        oncomplete: function(data) {
            document.getElementById('f-address-' + clientId).value = data.address;
            document.getElementById('f-address_detail-' + clientId).focus();
        }
    }).open();
}

// ── 프로젝트 CRUD ──
const WORK_TYPE_FALLBACK = {
    personal: [['setup','세팅'],['remote','원격'],['filming','촬영중계'],['design','디자인'],['as','A/S']],
    studio: [['setup','세팅'],['survey','답사'],['filming','촬영중계'],['design','디자인'],['as','A/S'],['dispatch','파견']],
    corporate: [['setup','세팅'],['survey','답사'],['filming','촬영중계'],['design','디자인'],['as','A/S']],
    rental: [['monthly','월 계약']],
    broadcast_room: [['monthly','월 계약'],['hourly','시간 대여']],
};
let WORK_TYPES_CACHE = null; // 관리자 정의 작업 유형 캐시

async function loadWorkTypesCache() {
    if (WORK_TYPES_CACHE) return WORK_TYPES_CACHE;
    try {
        const res = await fetch('/api/work-types/active', { headers:{ 'Accept':'application/json' } });
        if (res.ok) WORK_TYPES_CACHE = await res.json();
    } catch(e) {}
    return WORK_TYPES_CACHE || [];
}

function workTypeOptionsFor(projectType) {
    if (!WORK_TYPES_CACHE || !WORK_TYPES_CACHE.length) return WORK_TYPE_FALLBACK.personal || [];
    // 종속 구조: 선택한 프로젝트 유형에 속한 작업 유형만 노출 (type_key 없는 항목은 공통)
    return WORK_TYPES_CACHE
        .filter(w => !w.type_key || w.type_key === projectType)
        .map(w => [w.key, w.label]);
}

async function updateWorkTypeOptions(clientId) {
    await loadWorkTypesCache();
    const projectType = document.getElementById('pf-type-' + clientId).value;
    const sel = document.getElementById('pf-work_type-' + clientId);
    const opts = workTypeOptionsFor(projectType);
    sel.innerHTML = opts.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
}

function openProjectForm(clientId) {
    const form = document.getElementById('project-form-' + clientId);
    form.style.display = 'block';
    document.getElementById('pf-name-' + clientId).value = '';
    document.getElementById('pf-memo-' + clientId).value = '';
    updateWorkTypeOptions(clientId);
    if (window.CrmTagPicker) CrmTagPicker.init('client-' + clientId);
}

async function createProject(clientId) {
    const name = document.getElementById('pf-name-' + clientId).value.trim();
    if (!name) return alert('프로젝트명을 입력하세요.');
    const body = {
        name,
        project_type: document.getElementById('pf-type-' + clientId).value,
        client_scale: document.getElementById('pf-scale-' + clientId).value,
        work_type: document.getElementById('pf-work_type-' + clientId).value,
        overview: document.getElementById('pf-memo-' + clientId).value,
        tags: CrmTagPicker.value('client-' + clientId),
    };
    const res = await fetch(`/clients/${clientId}/projects`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });
    if (res.ok || res.status === 302) {
        document.getElementById('project-form-' + clientId).style.display = 'none';
        await refreshClientData(clientId);
        showToast('프로젝트가 생성되었습니다');
    } else {
        await showFetchError(res, '프로젝트 생성 실패');
    }
}

async function cancelProject(projectId, clientId) {
    if (!confirm('이 프로젝트를 취소 상태로 변경하시겠습니까?\n(데이터는 보존되며, 단계만 "취소"로 변경됩니다)')) return;
    await fetch(`/projects/${projectId}/stage`, {
        method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({stage:'cancelled'})
    });
    await refreshClientData(clientId);
    showToast('프로젝트가 취소되었습니다');
}

async function deleteProject(projectId, clientId) {
    if (!confirm('⚠️ 이 프로젝트를 완전히 삭제하시겠습니까?\n상담 이력/문서 등 관련 데이터가 함께 삭제되며 되돌릴 수 없습니다.')) return;
    const res = await fetch(`/api/projects/${projectId}`, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    if (res.ok) {
        await refreshClientData(clientId);
        showToast('프로젝트가 삭제되었습니다');
    } else {
        await showFetchError(res, '프로젝트 삭제 실패');
    }
}

// 프로젝트 정렬
function sortProjects(clientId, order) {
    const tab = openClientTabs.find(t => t.id === clientId);
    if (!tab || !tab.data) return;
    const listEl = document.getElementById('project-list-' + clientId);
    if (!listEl) return;
    listEl.innerHTML = renderProjectList(tab.data.projects, clientId, order);
}

// ── 첨부파일 업로드 (썸네일 프리뷰 + 누적 목록) ──
const pendingFiles = {}; // clientId → File[]
const IMG_TYPES = ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml'];
const VID_TYPES = ['video/mp4','video/webm','video/ogg','video/quicktime','video/x-msvideo','video/x-matroska'];

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function docAddFiles(clientId, input) {
    if (!pendingFiles[clientId]) pendingFiles[clientId] = [];
    for (const f of input.files) pendingFiles[clientId].push(f);
    input.value = '';
    renderFilePreview(clientId);
}

function removeFile(clientId, idx) {
    pendingFiles[clientId].splice(idx, 1);
    renderFilePreview(clientId);
}

function renderFilePreview(clientId) {
    const container = document.getElementById('doc-preview-' + clientId);
    const btn = document.getElementById('doc-upload-btn-' + clientId);
    const files = pendingFiles[clientId] || [];
    btn.disabled = files.length === 0;

    if (!files.length) { container.innerHTML = ''; return; }

    container.innerHTML = files.map((f, i) => {
        let thumbContent;
        if (IMG_TYPES.includes(f.type)) {
            const url = URL.createObjectURL(f);
            thumbContent = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">`;
        } else if (VID_TYPES.includes(f.type)) {
            thumbContent = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:4px;font-size:16px;">▶</div>`;
        } else if (f.type === 'application/pdf') {
            thumbContent = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:4px;font-size:10px;font-weight:700;color:var(--red);">PDF</div>`;
        } else {
            const ext = f.name.split('.').pop().toUpperCase();
            thumbContent = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:4px;font-size:10px;color:var(--text-muted);">${ext}</div>`;
        }

        return `<div style="width:80px; position:relative;">
            <div style="width:80px; height:80px; border:1px solid var(--border); border-radius:6px; overflow:hidden;">${thumbContent}</div>
            <div style="font-size:9px; color:var(--text-muted); margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${f.name}">${f.name}</div>
            <div style="font-size:9px; color:var(--text-muted);">${formatFileSize(f.size)}</div>
            <button onclick="removeFile(${clientId},${i})" style="position:absolute;top:-4px;right:-4px;width:18px;height:18px;border-radius:50%;background:var(--red);color:#fff;border:none;font-size:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;">×</button>
        </div>`;
    }).join('');
}

async function uploadDocs(clientId) {
    const files = pendingFiles[clientId] || [];
    if (!files.length) return;

    const formData = new FormData();
    files.forEach(f => formData.append('files[]', f));
    formData.append('category', document.getElementById('doc-cat-' + clientId).value);
    formData.append('note', document.getElementById('doc-note-' + clientId).value);

    const btn = document.getElementById('doc-upload-btn-' + clientId);
    btn.disabled = true;
    btn.textContent = '업로드 중...';

    const res = await fetch(`/clients/${clientId}/documents`, {
        method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body:formData
    });

    btn.textContent = '업로드';

    if (res.ok || res.status === 302) {
        pendingFiles[clientId] = [];
        document.getElementById('doc-note-' + clientId).value = '';
        renderFilePreview(clientId);
        await refreshClientData(clientId);
        showToast(`${files.length}개 파일 업로드 완료`);
    } else {
        btn.disabled = false;
        alert('업로드 실패');
    }
}

async function deleteDoc(docId, clientId) {
    if (!confirm('이 파일을 삭제하시겠습니까?')) return;
    await fetch(`/documents/${docId}`, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    await refreshClientData(clientId);
    showToast('삭제되었습니다');
}

// ── 앨범 뷰어 ──
let albumDocs = [], albumIdx = 0, zoomScale = 1, panX = 0, panY = 0, isPanning = false, panStartX, panStartY, baseW = 0, baseH = 0;

function openAlbumViewer(clientId, idx) {
    const tab = openClientTabs.find(t => t.id === clientId);
    if (!tab) return;
    albumDocs = tab.data.documents;
    albumIdx = idx;
    renderAlbumMedia();
    document.getElementById('albumOverlay').style.display = 'flex';
}
function closeAlbumViewer() {
    document.getElementById('albumOverlay').style.display = 'none';
    document.getElementById('albumMediaWrap').innerHTML = '';
    document.getElementById('albumZoomControls').style.display = 'none';
    zoomScale = 1; panX = 0; panY = 0;
}
function albumNavDir(dir) {
    albumIdx = (albumIdx + dir + albumDocs.length) % albumDocs.length;
    zoomScale = 1; panX = 0; panY = 0;
    renderAlbumMedia();
}
// 모바일: 좌우 스와이프로 이전/다음 (확대 상태가 아닐 때)
(function(){
    const ov=document.getElementById('albumOverlay');
    if(!ov) return;
    let sx=0, sy=0, on=false;
    ov.addEventListener('touchstart',e=>{ if(e.touches.length===1 && zoomScale===1){ on=true; sx=e.touches[0].clientX; sy=e.touches[0].clientY; } else on=false; },{passive:true});
    ov.addEventListener('touchend',e=>{
        if(!on||!e.changedTouches.length) return; on=false;
        const dx=e.changedTouches[0].clientX-sx, dy=e.changedTouches[0].clientY-sy;
        if(Math.abs(dx)>50 && Math.abs(dx)>Math.abs(dy)*1.5) albumNavDir(dx<0?1:-1);
    },{passive:true});
})();
function albumZoomStep(dir) {
    const steps = [0.5, 0.75, 1, 1.5, 2, 3, 4];
    let ci = steps.indexOf(zoomScale); if (ci === -1) ci = 2;
    ci = Math.max(0, Math.min(steps.length - 1, ci + dir));
    zoomScale = steps[ci];
    if (zoomScale === 1) { panX = 0; panY = 0; }
    applyAlbumZoom();
}
function albumZoomReset() { zoomScale = 1; panX = 0; panY = 0; applyAlbumZoom(); }
function applyAlbumZoom() {
    const img = document.querySelector('#albumMediaWrap img.album-media');
    if (!img) return;
    if (zoomScale === 1) { img.style.width = ''; img.style.height = ''; }
    else { img.style.width = (baseW * zoomScale) + 'px'; img.style.height = (baseH * zoomScale) + 'px'; }
    img.style.transform = `translate(${panX}px,${panY}px)`;
    document.getElementById('albumZoomLevel').textContent = Math.round(zoomScale * 100) + '%';
}
function renderAlbumMedia() {
    const doc = albumDocs[albumIdx]; if (!doc) return;
    const wrap = document.getElementById('albumMediaWrap');
    const zoomCtrl = document.getElementById('albumZoomControls');
    wrap.innerHTML = '';
    const isImage = doc.mime_type && doc.mime_type.startsWith('image/');
    zoomCtrl.style.display = isImage ? 'flex' : 'none';
    if (isImage) {
        const img = document.createElement('img');
        img.className = 'album-media'; img.src = doc.view_url;
        img.style.maxWidth = '85vw'; img.style.maxHeight = '75vh';
        img.onload = () => { baseW = img.offsetWidth; baseH = img.offsetHeight; };
        img.addEventListener('wheel', e => { e.preventDefault(); albumZoomStep(e.deltaY < 0 ? 1 : -1); }, {passive:false});
        img.addEventListener('mousedown', e => { if (zoomScale===1) return; isPanning=true; panStartX=e.clientX-panX; panStartY=e.clientY-panY; e.preventDefault(); });
        img.addEventListener('dblclick', () => { zoomScale===1 ? albumZoomStep(2) : albumZoomReset(); });
        wrap.appendChild(img);
    } else if (doc.mime_type && doc.mime_type.startsWith('video/')) {
        const vid = document.createElement('video');
        vid.className = 'album-media'; vid.src = doc.view_url; vid.controls = true; vid.autoplay = true;
        vid.style.maxWidth = '85vw'; vid.style.maxHeight = '75vh';
        wrap.appendChild(vid);
    } else if (doc.mime_type === 'application/pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = doc.view_url; iframe.style.cssText = 'width:80vw;height:75vh;border:none;';
        wrap.appendChild(iframe);
    } else {
        wrap.innerHTML = '<div style="color:var(--text-muted);font-size:14px;padding:60px;text-align:center;">미리보기를 지원하지 않는 파일입니다.</div>';
    }
    document.getElementById('albumName').textContent = doc.file_name;
    document.getElementById('albumNote').textContent = doc.note || '';
    document.getElementById('albumCounter').textContent = `${albumIdx + 1} / ${albumDocs.length}`;
}
document.addEventListener('mousemove', e => { if (!isPanning) return; panX = e.clientX - panStartX; panY = e.clientY - panStartY; applyAlbumZoom(); });
document.addEventListener('mouseup', () => { isPanning = false; });

// ── 클라이언트 데이터 새로고침 (현재 활성 sub-tab 유지) ──
async function refreshClientData(clientId) {
    const res = await fetch(`/api/clients/${clientId}/detail`, { headers:{'Accept':'application/json'} });
    if (!res.ok) return;
    const data = await res.json();
    const tab = openClientTabs.find(t => t.id === clientId);
    if (tab) {
        tab.data = data;
        tab.name = data.name;
        tab.nickname = data.nickname;
        tab.grade = data.grade;
    }
    // 패널 전체 재구성 — 조각별 갱신은 누락 지점이 생겨 저장 후 조회 뷰에 반영 안 되는 문제가 있었음
    const pane = document.getElementById('cpane-' + clientId);
    const activeSub = tab?.activeSubTab || 'info';
    if (pane) pane.remove();
    renderClientContent(clientId);
    if (activeSub !== 'info') {
        const idx = { info:0, projects:1, docs:2, estimates:3, memo:4 }[activeSub] ?? 0;
        const btns = document.querySelectorAll(`#subtabs-${clientId} .sub-tab`);
        if (btns[idx]) btns[idx].click();
    }
    renderClientTabs();
}

function switchSubTab(clientId, tab, btn) {
    const t = openClientTabs.find(t => t.id === clientId);
    if (t) t.activeSubTab = tab;

    document.querySelectorAll(`#subtabs-${clientId} .sub-tab`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    ['info','projects','docs','memo'].forEach(k => {
        const panel = document.getElementById(`sub-${k}-${clientId}`);
        if (panel) panel.classList.toggle('active', k === tab);
    });
}

// ── 저장/삭제 ──
async function saveClient(id) {
    const body = {
        name: document.getElementById(`f-name-${id}`).value,
        nickname: document.getElementById(`f-nickname-${id}`).value,
        phone: document.getElementById(`f-phone-${id}`).value,
        grade: document.getElementById(`f-grade-${id}`).value,
        affiliation: document.getElementById(`f-affiliation-${id}`)?.value || '',
        gender: document.getElementById(`f-gender-${id}`)?.value || null,
        address: document.getElementById(`f-address-${id}`)?.value || '',
        address_detail: document.getElementById(`f-address_detail-${id}`)?.value || '',
        important_memo: document.getElementById(`f-imp-memo-${id}`)?.value || document.getElementById(`f-important_memo-${id}`)?.value || '',
        memo: document.getElementById(`f-memo-${id}`)?.value || '',
        ...(() => {
            const p = collectCheckboxGroup('platforms', id);
            const t = collectCheckboxGroup('topics', id);
            return {
                platforms: p.values,
                platform_etc: p.values.includes('기타') ? p.etc : null,
                content_types: t.values,
                topic_etc: t.values.includes('기타') ? t.etc : null,
            };
        })(),
        broadcast_id: document.getElementById(`f-broadcast_id-${id}`)?.value || null,
        career: document.getElementById(`f-career-${id}`)?.value || null,
        inflow_source: getRadioValue(`inflow-${id}`) || null,
        client_type: document.getElementById(`f-client_type-${id}`)?.value || null,
        personality: document.getElementById(`f-personality-${id}`)?.value || null,
        budget_style: collectBudgetStyle(`budget-${id}`),
        custom_data: collectCustomData(id),
    };

    const res = await fetch(`/api/clients/${id}`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });

    if (res.ok) {
        showToast('저장되었습니다');
        // 사이드바 리스트 갱신 + 상세 재로드 (조회 뷰로 복귀하며 최신 값 반영)
        loadClientList();
        await refreshClientData(id);
    } else {
        await showFetchError(res, '의뢰자 저장 실패');
    }
}

async function deleteClient(id) {
    if (!confirm('이 의뢰자를 삭제하시겠습니까?')) return;
    const res = await fetch(`/clients/${id}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    if (res.ok) {
        closeClientTab(id);
        loadClientList();
        showToast('삭제되었습니다');
    }
}

// ── 새 의뢰자 ──
function openNewClientModal() {
    document.getElementById('newClientOverlay').classList.add('open');
    // 체크박스 그룹 렌더 (id='nc')
    document.getElementById('ncPlatformsWrap').innerHTML = renderCheckboxGroup('platforms', 'nc', PLATFORM_OPTIONS, [], '');
    document.getElementById('ncTopicsWrap').innerHTML = renderCheckboxGroup('topics', 'nc', TOPIC_OPTIONS, [], '');
    // 헤더 날짜 + 작성 현황 초기화
    const n = new Date();
    document.getElementById('ncmToday').textContent = `${n.getFullYear()}.${String(n.getMonth()+1).padStart(2,'0')}.${String(n.getDate()).padStart(2,'0')} 등록`;
    ncmBind();
    ncmRefresh();
}
function closeNewClientModal() { document.getElementById('newClientOverlay').classList.remove('open'); }

// ── 작성 현황 사이드바 (섹션별 채움 카운트 + 전체 % + 남은 필수) ──
let NCM_BOUND = false;
function ncmBind() {
    if (NCM_BOUND) return;
    NCM_BOUND = true;
    const modal = document.querySelector('#newClientOverlay .ncm');
    modal.addEventListener('input', ncmRefresh);
    modal.addEventListener('change', ncmRefresh);
}
function ncmSectionCount(card) {
    let filled = 0, total = 0;
    // 개별 입력 (칩 그룹 내부 라디오/체크는 제외 — 그룹당 1항목으로 계산)
    card.querySelectorAll('input.ncm-input, select.ncm-input, textarea.ncm-input').forEach(el => {
        if (el.offsetParent === null && el.id !== 'budget-nc-etc') return; // 숨김 필드 제외 (직접입력 등)
        if (el.id === 'budget-nc-etc') return; // 예산 직접입력은 칩 그룹에 포함
        total++;
        if ((el.value || '').trim()) filled++;
    });
    card.querySelectorAll('.chk-group').forEach(g => {
        total++;
        if (g.querySelector('input:checked')) filled++;
    });
    return [filled, total];
}
function ncmRefresh() {
    const cards = [...document.querySelectorAll('#newClientOverlay .ncm-card[data-sec]')];
    if (!cards.length) return;
    let filled = 0, total = 0;
    const secs = cards.map((card, i) => {
        const [f, t] = ncmSectionCount(card);
        filled += f; total += t;
        card.querySelector('.ncm-cnt').textContent = `${f}/${t} 작성`;
        return { name: card.dataset.sec, no: String(i+1).padStart(2,'0'), f, t };
    });
    // 히어로(닉네임) + 헤더 필(등급은 기본값이라 제외, 유형만) 포함
    const nick = document.getElementById('ncNickname').value.trim();
    total += 2; if (nick) filled++;
    if (document.getElementById('ncClientType').value) filled++;
    const pct = total ? Math.round(filled/total*100) : 0;
    document.getElementById('ncmPct').textContent = pct + '%';
    document.getElementById('ncmCnt').textContent = `${filled}/${total} 항목`;
    document.getElementById('ncmBarFill').style.width = pct + '%';
    document.getElementById('ncmSecs').innerHTML = secs.map(s => `
        <div class="ncm-sec-row ${s.f >= s.t && s.t > 0 ? 'done' : ''}">
            <span class="ncm-sec-no">${s.f >= s.t && s.t > 0 ? '✓' : s.no}</span>
            <span>${s.name}</span><span class="ncm-sec-cnt">${s.f}/${s.t}</span>
        </div>`).join('');
    // 남은 필수: 닉네임
    document.getElementById('ncmRequired').style.display = nick ? 'none' : 'flex';
    document.getElementById('ncmRequiredDone').style.display = nick ? '' : 'none';
}

// 주소 검색 (다음 우편번호)
function ncSearchAddress() {
    const fill = () => new daum.Postcode({
        oncomplete: d => {
            document.getElementById('ncAddress').value = d.address;
            document.getElementById('ncAddressDetail').focus();
            ncmRefresh();
        },
    }).open();
    if (typeof daum === 'undefined' || !daum.Postcode) {
        const s = document.createElement('script');
        s.src = '//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
        s.onload = fill;
        document.head.appendChild(s);
    } else { fill(); }
}

async function createClient() {
    const nickname = document.getElementById('ncNickname').value.trim();
    if (!nickname) return alert('닉네임을 입력하세요.');
    const name = document.getElementById('ncName').value.trim();

    const p = collectCheckboxGroup('platforms', 'nc');
    const t = collectCheckboxGroup('topics', 'nc');

    const body = {
        name: name || null,
        nickname,
        phone: document.getElementById('ncPhone').value.trim(),
        grade: document.getElementById('ncGrade').value,
        inflow_source: getRadioValue('ncInflow') || null,
        client_type: document.getElementById('ncClientType').value || null,
        platforms: p.values,
        platform_etc: p.values.includes('기타') ? p.etc : null,
        content_types: t.values,
        topic_etc: t.values.includes('기타') ? t.etc : null,
        broadcast_id: document.getElementById('ncBroadcastId').value.trim() || null,
        career: document.getElementById('ncCareer').value || null,
        personality: document.getElementById('ncPersonality').value.trim() || null,
        budget_style: collectBudgetStyle('budget-nc'),
        // 리디자인에서 추가된 기본 정보 필드
        gender: document.getElementById('ncGender').value || null,
        affiliation: document.getElementById('ncAffiliation').value.trim() || null,
        address: document.getElementById('ncAddress').value.trim() || null,
        address_detail: document.getElementById('ncAddressDetail').value.trim() || null,
        important_memo: document.getElementById('ncImportantMemo').value.trim() || null,
        memo: document.getElementById('ncMemo').value.trim() || null,
    };

    const res = await fetch('/api/clients', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });

    if (res.ok) {
        const data = await res.json();
        closeNewClientModal();
        ['ncName','ncNickname','ncPhone','ncBroadcastId','ncCareer','ncPersonality','budget-nc-etc'].forEach(k => {
            const el = document.getElementById(k); if (el) el.value = '';
        });
        // 라디오 pill 초기화 (유입경로/예산 성향)
        document.querySelectorAll('input[name="ncInflow"], input[name="budget-nc"]').forEach(r => {
            r.checked = false;
            r.closest('label')?.classList.remove('on');
        });
        document.getElementById('budget-nc-etc').style.display = 'none';
        await loadClientList();
        openClient(data.id);
        showToast('등록되었습니다');
    } else {
        await showFetchError(res, '의뢰자 등록 실패');
    }
}

// ── 토스트 ──
// ── 메모 스레드 ──
function renderMemoItem(m, clientId) {
    return `<div style="display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--border);" id="memo-item-${m.id}">
        <div style="width:30px; height:30px; border-radius:50%; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:var(--accent); flex-shrink:0;">${(m.user_name||'?').substring(0,1)}</div>
        <div style="flex:1; min-width:0;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="font-size:12px; font-weight:600;">${_esc(m.user_name)}</span>
                    <span style="font-size:10px; color:var(--text-muted); margin-left:6px;">${m.created_at}</span>
                </div>
                <button onclick="deleteMemo(${m.id},${clientId})" style="background:none; border:none; color:var(--text-muted); font-size:10px; cursor:pointer; opacity:0.5;" onmouseover="this.style.opacity=1;this.style.color='var(--red)'" onmouseout="this.style.opacity=0.5;this.style.color='var(--text-muted)'">삭제</button>
            </div>
            <div style="font-size:13px; margin-top:4px; white-space:pre-wrap; word-break:break-word;">${_esc(m.content)}</div>
        </div>
    </div>`;
}

function renderMemoThread(memos, clientId) {
    if (!memos || !memos.length) return '<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">메모가 없습니다.</div>';
    return memos.map(m => renderMemoItem(m, clientId)).join('');
}

// ── 동적 필드 렌더링 ──
const SECTION_LABELS = { basic:'기본 정보', equipment:'장비 정보', broadcast:'방송 정보', business:'사업자 정보', etc:'기타' };

function escAttr(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
function escText(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

// has_quantity 필드: {value, qty} → 표시용 문자열
function formatCfDisplay(v) {
    if (Array.isArray(v)) return v.join(', ');
    if (v && typeof v === 'object' && !Array.isArray(v) && ('value' in v || 'qty' in v)) {
        const val = String(v.value ?? '').trim();
        const qty = v.qty;
        if (qty === null || qty === undefined || qty === '') return val;
        return val ? `${val} × ${qty}` : `× ${qty}`;
    }
    if (typeof v === 'boolean') return v ? '예' : '아니오';
    return v;
}

// 소분류 → 아이콘 매핑 (대소문자/부분일치)
const CF_SUB_ICONS = {
    'pc':'💻','computer':'💻','컴퓨터':'💻','데스크탑':'💻','노트북':'💻','랩탑':'💻',
    '카메라':'🎥','camera':'🎥','캠':'🎥',
    '렌즈':'🔍','lens':'🔍',
    '오디오':'🎙️','audio':'🎙️','마이크':'🎙️','사운드':'🎙️','음향':'🎙️',
    '조명':'💡','light':'💡','lighting':'💡',
    '모니터':'🖥️','monitor':'🖥️','디스플레이':'🖥️',
    '주변기기':'🎛️','액세서리':'🎛️','주변장치':'🎛️',
    '인터넷':'🌐','네트워크':'🌐','network':'🌐',
    '소프트웨어':'🛠️','software':'🛠️',
    '스튜디오':'🎬','세트':'🎬',
};
function cfSubIcon(name) {
    if (!name) return '📦';
    const k = String(name).trim().toLowerCase();
    if (CF_SUB_ICONS[k]) return CF_SUB_ICONS[k];
    for (const key of Object.keys(CF_SUB_ICONS)) {
        if (k.includes(key)) return CF_SUB_ICONS[key];
    }
    return '📦';
}

// ── 기본 정보 조회 뷰 (디자인 3a) — 좌측 섹션 타이틀 레일 + 우측 값 그리드, 장비는 프로젝트 연동 읽기 전용 ──
const CV_GENDER = { male:'남성', female:'여성', other:'기타' };
const CV_SRC = { search:'검색', referral:'지인 소개', sns:'SNS', ad:'광고', community:'커뮤니티', other:'기타' };
const CV_TYPE = { personal:'개인', enterprise:'엔터', studio:'스튜디오' };
const CV_SECTION_LABELS = { basic:'기본 정보', broadcast:'방송 정보', business:'사업자 정보', etc:'기타 정보' };
function cvVal(v, dim) {
    const has = v !== null && v !== undefined && String(v).trim() !== '';
    return `<div class="cv-v ${has ? '' : 'dim'}">${has ? _esc(v) : (dim || '—')}</div>`;
}
function cvField(label, v, span, dim) {
    return `<div ${span ? 'style="grid-column:span 2;"' : ''}><div class="cv-l">${_esc(label)}</div>${cvVal(v, dim)}</div>`;
}
function cvChips(list, filled) {
    if (!list || !list.length) return '<div class="cv-v dim">—</div>';
    return `<div class="cv-chips">${list.map(x => `<span class="cv-chip ${filled ? 'fill' : ''}">${_esc(x)}</span>`).join('')}</div>`;
}
function cvSection(title, railHtml, bodyHtml) {
    return `<div class="cv-sec">
        <div class="cv-rail"><div class="cv-rt"><span class="cv-bar"></span>${_esc(title)}</div>${railHtml || ''}</div>
        <div>${bodyHtml}</div>
    </div>`;
}
function renderClientView(d) {
    // 인적 정보
    const addr = [d.address, d.address_detail].filter(Boolean).join(', ');
    const personal = `<div class="cv-grid">
        ${cvField('이름', d.name)}
        ${cvField('성별', CV_GENDER[d.gender] || '')}
        ${cvField('소속', d.affiliation)}
        ${cvField('유입경로', CV_SRC[d.inflow_source] || '')}
        ${cvField('의뢰자 유형', CV_TYPE[d.client_type] || '')}
        ${cvField('등록일', d.created_at)}
        ${cvField('주소', addr, true)}
        ${cvField('특이사항', d.important_memo, true)}
    </div>`;
    // 방송 정보
    const platforms = [...(d.platforms || []), ...(d.platform_etc ? [d.platform_etc] : [])];
    const topics = [...(d.content_types || []), ...(d.topic_etc ? [d.topic_etc] : [])];
    const broadcast = `<div class="cv-grid">
        <div><div class="cv-l">플랫폼</div>${cvChips(platforms, true)}</div>
        <div><div class="cv-l">방송 주제</div>${cvChips(topics, false)}</div>
        ${cvField('방송 아이디', d.broadcast_id)}
        ${cvField('방송 경력', d.career)}
        ${cvField('의뢰자 성격', d.personality, false, '미입력')}
        ${cvField('예산 성향', d.budget_style, false, '미입력')}
    </div>`;
    // 장비 정보 — 최근 프로젝트 연동 (읽기 전용)
    const eq = d.last_project_equipment;
    let equipBody, equipRail;
    if (eq && eq.fields?.length) {
        const groups = {};
        eq.fields.forEach(f => { const s = f.subsection || '기타'; (groups[s] = groups[s] || []).push(f); });
        equipBody = Object.keys(groups).map(sub => `<div class="cv-eqgroup">
            <span class="cv-subchip">${_esc(sub)}</span>
            <div class="cv-grid3">${groups[sub].map(f => `<div><div class="cv-l">${_esc(f.label)}</div>${cvVal(formatCfDisplay(f.value))}</div>`).join('')}</div>
        </div>`).join('')
            + `<a class="cv-eqlink" href="/projects/${eq.project_id}">프로젝트에서 원본 보기 →</a>`;
        // 최신 프로젝트가 아닌 과거 프로젝트에서 가져온 경우 출처를 명확히 표기
        const srcLabel = eq.is_latest === false ? '장비 정보가 있는 마지막 프로젝트' : '최근 프로젝트';
        equipRail = `<div class="cv-rd"><span class="cv-badge">프로젝트 연동</span></div><div class="cv-rd">${srcLabel}<br>「${_esc(eq.project_name)}」 · ${eq.created_at}</div>`;
    } else {
        equipBody = '<div class="cv-v dim">연동된 장비 정보가 없습니다 — 프로젝트에서 장비 정보를 입력하면 여기에 표시됩니다.</div>';
        equipRail = '<div class="cv-rd"><span class="cv-badge">프로젝트 연동</span></div>';
    }
    // 기타 커스텀 필드 (장비 섹션 제외) — 관리자 정의 필드를 섹션별 읽기 전용으로
    let customSections = '';
    if (typeof customFieldDefs !== 'undefined' && customFieldDefs?.length) {
        const bySec = {};
        customFieldDefs.filter(f => (f.section || 'etc') !== 'equipment')
            .forEach(f => { const s = f.section || 'etc'; (bySec[s] = bySec[s] || []).push(f); });
        customSections = Object.keys(bySec).map(sec => cvSection(
            CV_SECTION_LABELS[sec] || sec,
            '',
            `<div class="cv-grid">${bySec[sec].map(f => cvField(f.label, formatCfDisplay((d.custom_data || {})[f.key]))).join('')}</div>`
        )).join('');
    }
    return `<div class="cv-wrap">
        ${cvSection('인적 정보', '<div class="cv-rd">이름 · 연락 · 주소 등<br>개인 식별 정보</div>', personal)}
        ${cvSection('방송 정보', '<div class="cv-rd">플랫폼 · 주제 · 경력</div>', broadcast)}
        ${cvSection('장비 정보', equipRail, `<div class="cv-eqwrap">${equipBody}</div>`)}
        ${customSections}
    </div>`;
}
// 조회 ↔ 수정 전환
function clientEditMode(id, on) {
    const v = document.getElementById('view-info-' + id);
    const e = document.getElementById('edit-info-' + id);
    if (!v || !e) return;
    v.style.display = on ? 'none' : '';
    e.style.display = on ? '' : 'none';
    document.getElementById('ce-edit-' + id).style.display = on ? 'none' : '';
    document.getElementById('ce-save-' + id).style.display = on ? '' : 'none';
    document.getElementById('ce-cancel-' + id).style.display = on ? '' : 'none';
    // 기본 정보 탭으로 이동 (다른 탭에서 수정 눌렀을 때)
    if (on) { const tab = document.querySelector(`#subtabs-${id} .sub-tab`); if (tab) tab.click(); }
}

// 장비 정보 요약: 최근 프로젝트의 '장비 정보' 동적 필드(custom_data) 만 보여줌
function renderEquipmentSummary(latest) {
    if (!latest || !latest.fields?.length) return '';

    // 소분류로 그룹핑 + 소분류별 최대 priority 계산
    const groups = {};
    const subMaxPrio = {};
    latest.fields.forEach(f => {
        const sub = f.subsection || '';
        const p = Number.isFinite(parseInt(f.priority, 10)) ? parseInt(f.priority, 10) : 0;
        if (!groups[sub]) groups[sub] = [];
        groups[sub].push(f);
        if (subMaxPrio[sub] === undefined || p > subMaxPrio[sub]) subMaxPrio[sub] = p;
    });

    // 소분류 정렬: priority DESC → 한글 사전순. 단 빈 소분류('기타')는 마지막.
    const subKeys = Object.keys(groups).sort((a, b) => {
        if (a === '' || a === '기타') {
            if (b === '' || b === '기타') return 0;
            return 1;
        }
        if (b === '' || b === '기타') return -1;
        const dp = (subMaxPrio[b] || 0) - (subMaxPrio[a] || 0);
        if (dp !== 0) return dp;
        return a.localeCompare(b, 'ko');
    });

    const cardsHtml = subKeys.map(sub => {
        // 그룹 내부 필드 정렬: priority DESC → 원래 순서 (서버에서 sort_order 로 이미 정렬됨)
        const fields = [...groups[sub]].sort((a, b) => (b.priority || 0) - (a.priority || 0));
        const subLabel = sub || '기타';
        const icon = cfSubIcon(sub);
        const rows = fields.map(f => {
            const v = formatCfDisplay(f.value);
            return `<div style="display:flex; gap:10px; font-size:12px; align-items:baseline; padding:3px 0;">
                <span style="color:var(--text-muted); flex-shrink:0; min-width:90px;">${escText(f.label)}</span>
                <span style="color:var(--text); flex:1; word-break:break-all; font-weight:500;">${escText(v)}</span>
            </div>`;
        }).join('');
        return `<div style="background:var(--surface2); border:1px solid var(--border); border-left:3px solid var(--accent); border-radius:8px; padding:10px 12px;">
            <div style="font-size:10px; font-weight:700; color:var(--accent); letter-spacing:0.08em; text-transform:uppercase; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                <span style="font-size:14px;">${icon}</span>${escText(subLabel)}
                <span style="margin-left:auto; font-weight:400; color:var(--text-muted); font-size:10px;">${fields.length}개</span>
            </div>
            ${rows}
        </div>`;
    }).join('');

    return `<div style="margin-top:20px; border-top:1px solid var(--border); padding-top:14px;">
        <div style="font-size:12px; font-weight:700; color:var(--accent); margin-bottom:12px;">📦 장비 정보 <span style="font-weight:400; font-size:11px; color:var(--text-muted);">(최근 프로젝트 기준)</span></div>
        <div style="background:rgba(212,188,150,0.06); border:1px solid rgba(212,188,150,0.25); border-radius:10px; padding:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <a href="/projects/${latest.project_id}" style="font-size:11px; color:var(--text-muted); text-decoration:none;">📁 ${escText(latest.project_name)} · ${latest.created_at} →</a>
            </div>
            <div style="display:flex; flex-direction:column; gap:10px;">${cardsHtml}</div>
        </div>
    </div>`;
}

function renderCustomFields(customData, clientId) {
    if (!customFieldDefs || !customFieldDefs.length) return '';
    // section → subsection → fields 2단 그룹핑 + priority 집계
    const grouped = {};
    const subMaxPrio = {}; // key: `${sec}::${sub}` → max priority
    customFieldDefs.forEach(f => {
        const sec = f.section || 'etc';
        const sub = f.subsection || '';
        const p = Number.isFinite(parseInt(f.priority, 10)) ? parseInt(f.priority, 10) : 0;
        if (!grouped[sec]) grouped[sec] = {};
        if (!grouped[sec][sub]) grouped[sec][sub] = [];
        grouped[sec][sub].push(f);
        const k = `${sec}::${sub}`;
        if (subMaxPrio[k] === undefined || p > subMaxPrio[k]) subMaxPrio[k] = p;
    });

    const resolveWidth = (f) => {
        const w = parseInt(f.width, 10);
        if (w >= 1 && w <= 4) return w;
        if (f.type === 'textarea') return 4;
        if (['radio','checkbox'].includes(f.type) && (f.options||[]).length > 3) return 4;
        return 2;
    };

    // 수량 입력 지원 타입
    const CF_QTY_TYPES = ['text', 'textarea', 'select', 'radio', 'date'];
    const cfGetVQ = (v) => {
        if (v && typeof v === 'object' && !Array.isArray(v)) return { value: v.value ?? '', qty: v.qty ?? '' };
        return { value: v ?? '', qty: '' };
    };

    const renderOneField = (f) => {
        const rawValue = customData[f.key];
        const useQty = !!f.has_quantity && CF_QTY_TYPES.includes(f.type);
        let value = rawValue;
        let qtyValue = '';
        if (useQty) {
            const vq = cfGetVQ(rawValue);
            value = vq.value;
            qtyValue = vq.qty;
        } else if (value && typeof value === 'object' && !Array.isArray(value) && 'value' in value) {
            value = value.value; // has_quantity 끈 뒤 잔존 객체
        }
        const required = f.is_required ? ' <span style="color:var(--red);">*</span>' : '';
        const help = f.help_text ? `<div style="font-size:10px; color:var(--text-muted); margin-top:3px;">${escText(f.help_text)}</div>` : '';
        const inputId = `cf-${clientId}-${f.key}`;
        const placeholder = escAttr(f.placeholder || '');
        const w = resolveWidth(f);
        let out = `<div class="field w-${w}"><div class="field-label">${escText(f.label)}${required}</div>`;

        // 메인 입력
        let mainHtml = '';
        if (f.type === 'text' || f.type === 'number' || f.type === 'date') {
            const inputType = f.type === 'text' ? 'text' : f.type;
            mainHtml = `<input type="${inputType}" class="field-input" id="${inputId}" value="${escAttr(value ?? '')}" placeholder="${placeholder}" data-cf-key="${escAttr(f.key)}" data-cf-type="${f.type}">`;
        } else if (f.type === 'textarea') {
            mainHtml = `<textarea class="field-input field-textarea" id="${inputId}" placeholder="${placeholder}" data-cf-key="${escAttr(f.key)}" data-cf-type="textarea" rows="3">${escText(value ?? '')}</textarea>`;
        } else if (f.type === 'select') {
            const opts = (f.options || []).map(o => `<option value="${escAttr(o)}" ${value === o ? 'selected' : ''}>${escText(o)}</option>`).join('');
            mainHtml = `<select class="field-input field-select" id="${inputId}" data-cf-key="${escAttr(f.key)}" data-cf-type="select">
                <option value="">선택</option>${opts}
            </select>`;
        } else if (f.type === 'radio') {
            const opts = (f.options || []).map((o, i) => `
                <label style="display:inline-flex; align-items:center; gap:5px; margin-right:12px; font-size:13px; cursor:pointer;">
                    <input type="radio" name="${inputId}" value="${escAttr(o)}" ${value === o ? 'checked' : ''} data-cf-key="${escAttr(f.key)}" data-cf-type="radio"> ${escText(o)}
                </label>`).join('');
            mainHtml = `<div style="padding:6px 0;">${opts}</div>`;
        } else if (f.type === 'checkbox') {
            const arr = Array.isArray(value) ? value : [];
            const opts = (f.options || []).map(o => `
                <label style="display:inline-flex; align-items:center; gap:5px; margin-right:12px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" value="${escAttr(o)}" ${arr.includes(o) ? 'checked' : ''} data-cf-key="${escAttr(f.key)}" data-cf-type="checkbox"> ${escText(o)}
                </label>`).join('');
            mainHtml = `<div style="padding:6px 0;">${opts}</div>`;
        }

        // 수량 입력 래퍼
        if (useQty) {
            const qtyHtml = `<input type="number" class="field-input" min="0" step="1" value="${escAttr(qtyValue ?? '')}" placeholder="수량" data-cf-qty-key="${escAttr(f.key)}" style="max-width:90px;">`;
            if (f.type === 'textarea' || f.type === 'radio') {
                out += `<div style="display:flex; flex-direction:column; gap:6px;">${mainHtml}<div style="display:flex; align-items:center; gap:6px;"><span style="font-size:11px; color:var(--text-muted); white-space:nowrap;">수량</span>${qtyHtml}</div></div>`;
            } else {
                out += `<div style="display:grid; grid-template-columns:1fr 90px; gap:6px;">${mainHtml}${qtyHtml}</div>`;
            }
        } else {
            out += mainHtml;
        }
        out += help + `</div>`;
        return out;
    };

    let html = '';
    Object.entries(SECTION_LABELS).forEach(([secKey, secLabel]) => {
        if (!grouped[secKey]) return;
        const subs = grouped[secKey];
        const subKeys = Object.keys(subs);
        const hasSubsections = subKeys.some(s => s !== '');

        html += `<div style="margin-top:20px; border-top:1px solid var(--border); padding-top:14px;">
            <div style="font-size:12px; font-weight:700; color:var(--accent); margin-bottom:12px;">${secLabel}</div>`;

        if (!hasSubsections) {
            const sortedFields = [...(subs[''] || [])].sort((a, b) => (b.priority || 0) - (a.priority || 0));
            html += `<div class="cf-dyn-grid">`;
            sortedFields.forEach(f => { html += renderOneField(f); });
            html += `</div>`;
        } else {
            const ordered = subKeys.sort((a, b) => {
                if (a === '' || a === '기타') {
                    if (b === '' || b === '기타') return 0;
                    return 1;
                }
                if (b === '' || b === '기타') return -1;
                const dp = (subMaxPrio[`${secKey}::${b}`] || 0) - (subMaxPrio[`${secKey}::${a}`] || 0);
                if (dp !== 0) return dp;
                return a.localeCompare(b, 'ko');
            });
            html += `<div style="display:flex; flex-direction:column; gap:12px;">`;
            ordered.forEach(sub => {
                const fields = [...subs[sub]].sort((a, b) => (b.priority || 0) - (a.priority || 0));
                const subLabel = sub || '기타';
                const icon = cfSubIcon(sub);
                html += `<div style="background:var(--surface2); border:1px solid var(--border); border-left:3px solid var(--accent); border-radius:8px; padding:12px 14px;">
                    <div style="font-size:11px; font-weight:700; color:var(--accent); letter-spacing:0.06em; text-transform:uppercase; display:flex; align-items:center; gap:6px; margin-bottom:10px;">
                        <span style="font-size:14px;">${icon}</span>${escText(subLabel)}
                    </div>
                    <div class="cf-dyn-grid">`;
                fields.forEach(f => { html += renderOneField(f); });
                html += `</div></div>`;
            });
            html += `</div>`;
        }
        html += `</div>`;
    });
    return html;
}

function collectCustomData(clientId) {
    const data = {};
    const pane = document.getElementById('cpane-' + clientId);
    if (!pane) return data;
    pane.querySelectorAll('[data-cf-key]').forEach(el => {
        const key = el.dataset.cfKey;
        const type = el.dataset.cfType;
        if (type === 'checkbox') {
            if (!data[key]) data[key] = [];
            if (el.checked) data[key].push(el.value);
        } else if (type === 'radio') {
            if (el.checked) data[key] = el.value;
        } else {
            data[key] = el.value || null;
        }
    });
    // has_quantity 후처리 — {value, qty} 객체로 묶어 저장
    pane.querySelectorAll('[data-cf-qty-key]').forEach(el => {
        const key = el.dataset.cfQtyKey;
        const num = parseInt(el.value, 10);
        const qty = (Number.isFinite(num) && num >= 0) ? num : null;
        const existing = data[key];
        let valuePart;
        if (existing && typeof existing === 'object' && !Array.isArray(existing)) {
            valuePart = existing.value ?? '';
        } else {
            valuePart = existing ?? '';
        }
        if ((valuePart && String(valuePart).length) || qty !== null) {
            data[key] = { value: valuePart || '', qty };
        } else {
            data[key] = null;
        }
    });
    return data;
}

function renderInfoMemos(memos, clientId) {
    if (!memos || !memos.length) return '<div style="padding:12px; text-align:center; color:var(--text-muted); font-size:12px;">메모가 없습니다.</div>';
    const recent = memos.slice(0, 3);
    const rest = memos.slice(3);
    let html = recent.map(m => renderMemoItem(m, clientId)).join('');
    if (rest.length) {
        html += `<div id="info-memos-rest-${clientId}" style="display:none;">
            ${rest.map(m => renderMemoItem(m, clientId)).join('')}
        </div>`;
        html += `<div style="text-align:center; padding:8px;" id="info-memos-toggle-${clientId}">
            <button onclick="toggleMoreMemos(${clientId})" style="background:none; border:1px solid var(--border); color:var(--accent); font-size:11px; padding:4px 12px; border-radius:5px; cursor:pointer;">+ ${rest.length}개 더 보기</button>
        </div>`;
    }
    return html;
}

function toggleMoreMemos(clientId) {
    const rest = document.getElementById('info-memos-rest-' + clientId);
    const toggle = document.getElementById('info-memos-toggle-' + clientId);
    if (!rest) return;
    const isHidden = rest.style.display === 'none';
    rest.style.display = isHidden ? 'block' : 'none';
    toggle.querySelector('button').textContent = isHidden ? '접기' : `+ ${rest.children.length}개 더 보기`;
}

async function addMemo(clientId, from) {
    const inputId = from === 'info' ? 'info-memo-input-' + clientId : 'new-memo-' + clientId;
    const textarea = document.getElementById(inputId);
    const content = textarea.value.trim();
    if (!content) return;

    const res = await fetch(`/api/clients/${clientId}/memos`, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ content })
    });

    if (res.ok) {
        textarea.value = '';
        if (from === 'info') textarea.rows = 1;
        await refreshClientData(clientId);
        showToast('메모가 추가되었습니다');
    } else {
        alert('메모 추가 실패');
    }
}

async function deleteMemo(memoId, clientId) {
    if (!confirm('이 메모를 삭제하시겠습니까?')) return;
    await fetch(`/api/client-memos/${memoId}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    await refreshClientData(clientId);
    showToast('메모가 삭제되었습니다');
}

// ── 사이드바 토글 (모바일) ──
function openSidebar() {
    document.querySelector('.crm-sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
}
function closeSidebar() {
    document.querySelector('.crm-sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2000);
}

// ── 의뢰자 탭 상태 저장/복원 ──
function saveClientTabs() {
    const data = {
        tabs: openClientTabs.map(t => t.id),
        activeId: activeClientId,
        page: clientPage,
        search: document.getElementById('clientSearch')?.value || '',
        grade: currentGrade,
    };
    sessionStorage.setItem('drgo_client_tabs', JSON.stringify(data));
}

async function restoreClientTabs() {
    try {
        const raw = sessionStorage.getItem('drgo_client_tabs');
        if (!raw) return;
        const data = JSON.parse(raw);
        if (!data.tabs || !data.tabs.length) return;

        for (const id of data.tabs) {
            await openClient(id);
        }
        if (data.activeId && openClientTabs.find(t => t.id === data.activeId)) {
            activateClientTab(data.activeId);
        }
    } catch {}
}

// 키보드 단축키
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAlbumViewer(); closeNewClientModal(); }
    if (document.getElementById('albumOverlay').style.display === 'flex') {
        if (e.key === 'ArrowLeft') albumNavDir(-1);
        if (e.key === 'ArrowRight') albumNavDir(1);
    }
});
</script>
@endpush
