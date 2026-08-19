@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '할 일 - 닥터고블린 오피스')

@push('styles')
<style>
    .todo-wrap { padding:24px; max-width:1400px; margin:0 auto; }
    .todo-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; flex-wrap:wrap; gap:10px; }
    .todo-header h1 { font-size:20px; font-weight:700; }
    .todo-header p { font-size:12px; color:var(--text-muted); margin-top:4px; }
    .todo-header-actions { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
    .todo-toggle { display:flex; align-items:center; gap:7px; font-size:12px; color:var(--text-muted); cursor:pointer; user-select:none; }
    .todo-toggle:has(input:checked) { color:var(--text); font-weight:600; }
    /* 스위치형 토글 */
    .todo-toggle input { appearance:none; -webkit-appearance:none; width:34px; height:19px; border-radius:10px; background:var(--surface3); border:1px solid var(--border); position:relative; cursor:pointer; transition:background 0.18s, border-color 0.18s; margin:0; flex-shrink:0; }
    .todo-toggle input::after { content:''; position:absolute; top:2px; left:2px; width:13px; height:13px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.25); transition:left 0.18s; }
    .todo-toggle input:checked { background:var(--accent); border-color:var(--accent); }
    .todo-toggle input:checked::after { left:17px; }
    .todo-add-btn { background:#1f2b40; color:#fff; border:none; padding:9px 16px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; }
    .todo-add-btn:hover { background:#2c3d5c; }

    /* 직원 필터 (관리자) */
    .todo-mfilter { position:relative; }
    .todo-mfilter-btn { background:var(--surface); border:1px solid var(--border); color:var(--text-muted); padding:7px 12px; border-radius:9px; font-size:12px; cursor:pointer; }
    .todo-mfilter-btn:hover { border-color:var(--accent); color:var(--text); }
    .todo-mfilter-btn #mfilterCount { color:var(--accent); font-weight:700; }
    .todo-mfilter-panel { display:none; position:absolute; top:calc(100% + 6px); left:0; z-index:50; min-width:190px; max-height:300px; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.15); padding:8px; }
    .todo-mfilter.open .todo-mfilter-panel { display:block; }
    .todo-mfilter-panel label { display:flex; align-items:center; gap:9px; font-size:12.5px; padding:7px 9px; border-radius:7px; cursor:pointer; }
    .todo-mfilter-panel label:hover { background:var(--surface2); }
    .todo-mfilter-panel label:has(input:checked) { color:var(--accent); font-weight:600; }
    /* 커스텀 체크박스 — 라운드 사각 + 체크마크 */
    .todo-mfilter-panel input { appearance:none; -webkit-appearance:none; width:17px; height:17px; margin:0; flex-shrink:0; border:1.5px solid #c3cad6; border-radius:5px; background:var(--surface); cursor:pointer; position:relative; transition:all 0.15s; }
    .todo-mfilter-panel input:hover { border-color:var(--accent); }
    .todo-mfilter-panel input:checked { background:var(--accent); border-color:var(--accent); }
    .todo-mfilter-panel input::after { content:''; position:absolute; left:5px; top:1.5px; width:4px; height:8px; border:solid #fff; border-width:0 2px 2px 0; transform:rotate(45deg) scale(0); transition:transform 0.12s; }
    .todo-mfilter-panel input:checked::after { transform:rotate(45deg) scale(1); }
    .todo-mfilter-panel .mfilter-all { border-bottom:1px solid var(--border); margin-bottom:4px; padding-bottom:9px; font-weight:700; }
    .todo-mfilter-panel .mfilter-team { color:var(--text-muted); font-size:11px; margin-left:auto; }

    /* 카드/리스트 뷰 전환 */
    .todo-viewswitch { display:flex; border:1px solid var(--border); border-radius:9px; overflow:hidden; }
    .todo-viewswitch button { background:var(--surface); border:none; color:var(--text-muted); padding:7px 12px; font-size:12px; cursor:pointer; }
    .todo-viewswitch button + button { border-left:1px solid var(--border); }
    .todo-viewswitch button.on { background:#1f2b40; color:#fff; font-weight:700; }

    /* ── 리스트 뷰 (좌측 리스트 + 우측 상세 패널) ── */
    .todo-list-layout { display:grid; grid-template-columns:minmax(0,1fr) 430px; gap:16px; align-items:start; }
    .todo-list { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .todo-lrow { display:flex; align-items:center; gap:11px; padding:13px 16px; border-bottom:1px solid var(--border); cursor:pointer; }
    .todo-lrow:last-child { border-bottom:none; }
    .todo-lrow:hover { background:var(--surface2); }
    .todo-lrow.sel { background:color-mix(in srgb, var(--accent) 8%, transparent); box-shadow:inset 3px 0 0 var(--accent); }
    .todo-lrow .todo-check { margin-top:0; }
    .todo-lrow-title { font-size:14.5px; font-weight:600; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .todo-lrow.done .todo-lrow-title { text-decoration:line-through; color:var(--text-muted); }
    .todo-lrow-right { margin-left:auto; display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .todo-lrow-right .todo-team-label { font-size:12.5px; }
    .todo-lrow-right .todo-pri { font-size:11px; padding:3px 10px; }
    .todo-lrow-right .todo-due { font-size:12px; padding:4px 10px; }
    .todo-lrow-due-date { font-size:13.5px; color:var(--text-muted); font-weight:600; }
    .todo-lrow-due-date.imminent { color:#c0392b; }  /* 오늘 마감·기한 지남 */
    .todo-lrow-due-date.soon { color:#b26a00; }      /* D-3 이내 */
    .todo-lrow-assignee { font-size:13.5px; color:var(--text); font-weight:600; }
    /* 담당자별 섹션 헤더 */
    .todo-lgroup-head { display:flex; align-items:center; gap:8px; padding:11px 16px 9px; background:var(--surface2); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1; }
    .todo-lgroup-head:not(:first-child) { border-top:1px solid var(--border); }
    .todo-lgroup-name { font-size:13.5px; font-weight:800; }
    .todo-lgroup-team { font-size:11.5px; color:var(--text-muted); }
    .todo-lgroup-count { font-size:11.5px; font-weight:700; color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); border-radius:9px; padding:1px 9px; }
    /* 우측 상세 패널 */
    .todo-detail-pane { position:sticky; top:16px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; display:flex; flex-direction:column; gap:13px; }
    .todo-detail-empty { color:var(--text-muted); font-size:12.5px; text-align:center; padding:46px 0; }
    .tdp-title { font-size:16px; font-weight:700; line-height:1.5; word-break:break-word; }
    .todo-view-content a { color:var(--accent); word-break:break-all; }
    .yt-embed { margin-top:10px; border-radius:10px; overflow:hidden; aspect-ratio:16/9; background:#000; }
    .yt-embed iframe { width:100%; height:100%; border:0; display:block; }
    .yt-embed.vertical { aspect-ratio:9/16; max-width:250px; } /* 쇼츠 등 세로 영상 */
    /* 외부 링크 OG 미리보기 카드 */
    .link-card { display:flex; gap:12px; margin-top:10px; padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--surface); text-decoration:none !important; color:var(--text); align-items:center; min-height:52px; transition:border-color 0.15s; }
    .link-card:hover { border-color:var(--accent); }
    .link-card:empty { display:none; }
    .lc-thumb { width:76px; height:56px; object-fit:cover; border-radius:7px; flex-shrink:0; background:var(--surface2); }
    .lc-body { display:flex; flex-direction:column; gap:3px; min-width:0; }
    .lc-title { font-size:12.5px; font-weight:700; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; word-break:break-all; }
    .lc-desc { font-size:11px; color:var(--text-muted); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; word-break:break-all; }
    .lc-host { font-size:10.5px; color:var(--text-muted); opacity:0.8; }
    .tdp-actions { display:flex; gap:8px; flex-wrap:wrap; border-top:1px solid var(--border); padding-top:13px; }
    .tdp-actions .todo-btn { padding:8px 14px; font-size:12.5px; }
    .tdp-done-badge { background:#2e7d32; color:#fff; font-size:11px; font-weight:700; padding:3px 11px; border-radius:8px; }
    @media (max-width:900px) {
        .todo-list-layout { grid-template-columns:1fr; }
        .todo-detail-pane { position:static; order:-1; }
    }
    @media (max-width:560px) { .todo-lrow-assignee, .todo-lrow .todo-team-label { display:none; } }

    /* ── 칸반 보드 ── */
    /* 보드 높이를 화면에 맞춰 고정(JS) — 가로 스크롤바가 페이지 최하단이 아니라 항상 화면 하단에 보이도록 */
    .todo-board { display:flex; gap:14px; align-items:flex-start; overflow-x:auto; padding-bottom:6px; }
    .todo-col { flex:0 0 280px; background:var(--surface); border:1px solid var(--border); border-radius:14px; min-height:120px; display:flex; flex-direction:column; max-height:100%; }
    .todo-col-body { overflow-y:auto; }
    .todo-col.drag-over { border-color:var(--accent); background:color-mix(in srgb, var(--accent) 5%, var(--surface)); }
    .todo-col.ghost { border-style:dashed; opacity:0.85; }
    .todo-col.ghost .todo-empty { padding:18px 10px; }
    .todo-col-head { display:flex; align-items:center; gap:8px; padding:14px 16px; border-bottom:1px solid var(--border); }
    .todo-col-name { font-size:14px; font-weight:700; }
    .todo-col-team { font-size:11px; color:var(--text-muted); }
    .todo-col-count { margin-left:auto; font-size:12px; color:var(--text-muted); font-weight:600; }
    .todo-col-body { padding:12px; display:flex; flex-direction:column; gap:10px; min-height:60px; }

    /* ── 카드 ── */
    .todo-card { position:relative; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:12px 14px 12px 18px; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
    .todo-card:hover { border-color:var(--accent); }
    .todo-card::before { content:''; position:absolute; left:6px; top:10px; bottom:10px; width:4px; border-radius:2px; background:var(--p-color, #d0d5dd); }
    .todo-card.p-high { --p-color:#e0604f; }
    .todo-card.p-medium { --p-color:#e8a13a; }
    .todo-card.p-low { --p-color:#57ab5a; }
    .todo-card.dragging { opacity:0.45; }
    .todo-card-meta { display:flex; align-items:center; gap:6px; margin-bottom:6px; }
    .todo-team-label { font-size:10px; color:var(--text-muted); font-weight:600; }
    .todo-pri { font-size:9px; font-weight:700; color:#fff; padding:2px 8px; border-radius:8px; }
    .todo-pri.high { background:#d64545; }
    .todo-pri.medium { background:#e8a13a; }
    .todo-pri.low { background:#57ab5a; }
    .todo-card-title-row { display:flex; align-items:flex-start; gap:8px; }
    .todo-card-title { font-size:13px; font-weight:700; line-height:1.45; word-break:break-word; }
    /* 원형 완료 체크 — 호버 시 미리보기, 완료 시 채워짐 */
    .todo-check { flex-shrink:0; width:19px; height:19px; border-radius:50%; border:2px solid #c3cad6; background:transparent; cursor:pointer; padding:0; display:flex; align-items:center; justify-content:center; transition:all 0.15s; margin-top:-1px; }
    .todo-check svg { width:11px; height:11px; fill:none; stroke:#fff; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; opacity:0; transition:opacity 0.15s; }
    .todo-check:hover { border-color:#2e7d32; }
    .todo-check:hover svg { opacity:1; stroke:#2e7d32; }
    .todo-check.on { background:#2e7d32; border-color:#2e7d32; }
    .todo-check.on svg { opacity:1; stroke:#fff; }
    .todo-card-foot { display:flex; align-items:center; gap:6px; margin-top:8px; }
    .todo-co-assignees { font-size:11px; color:var(--text-muted); margin:3px 0 0 28px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .todo-co-assignees b { color:var(--text); font-weight:700; }
    .todo-co-assignees .a-done { color:var(--green, #4caf50); }

    /* 진행률 (체크리스트) */
    .todo-progress { display:flex; align-items:center; gap:7px; margin:7px 0 0 28px; }
    .todo-progress-bar { flex:1; height:5px; background:var(--surface2); border-radius:4px; overflow:hidden; }
    .todo-progress-bar span { display:block; height:100%; background:var(--accent); border-radius:4px; transition:width .2s; }
    .todo-progress-n { font-size:10.5px; color:var(--text-muted); font-weight:600; white-space:nowrap; }

    /* 내 완료만 체크된 상태 (전원 완료 대기) */
    .todo-check.half { border-color:var(--green, #4caf50); color:var(--green, #4caf50); background:color-mix(in srgb, var(--green, #4caf50) 12%, transparent); }
    .todo-check.half svg { opacity:0.9; }

    /* 상세 모달 — 담당 현황 / 체크리스트 */
    .tv-section-title { font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:7px; }
    .tv-assign-chips { display:flex; gap:6px; flex-wrap:wrap; }
    .tv-assign-chip { font-size:12px; padding:4px 11px; border-radius:12px; border:1px solid var(--border); color:var(--text-muted); }
    .tv-assign-chip.done { border-color:var(--green, #4caf50); color:var(--green, #4caf50); background:color-mix(in srgb, var(--green, #4caf50) 10%, transparent); }
    .tv-check-row { display:flex; align-items:center; gap:9px; padding:6px 0; border-bottom:1px dashed var(--border); font-size:13px; }
    .tv-check-row:last-of-type { border-bottom:none; }
    .tv-check-row.done .tv-check-title { text-decoration:line-through; color:var(--text-muted); }
    .tv-check-title { flex:1; min-width:0; }
    .tv-check-by { font-size:10.5px; color:var(--text-muted); white-space:nowrap; }
    .tv-check-del { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 6px; }
    .tv-check-del:hover { color:var(--red, #dc2626); }
    .tv-check-add { display:flex; gap:6px; margin-top:8px; }
    .tv-check-add input { flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:7px 10px; color:var(--text); font-size:13px; outline:none; }
    .todo-due { font-size:10px; font-weight:700; padding:3px 9px; border-radius:9px; background:var(--surface2); color:var(--text-muted); }
    .todo-due.overdue { background:#fdecea; color:#c0392b; }
    .todo-due.today { background:#fdf1e3; color:#b26a00; }
    .todo-due.held { background:#eef0f7; color:#5b6b95; }
    .todo-attach-n { font-size:10px; color:var(--text-muted); }
    .todo-card.done { opacity:0.6; }
    .todo-card.done .todo-card-title { text-decoration:line-through; }
    .todo-empty { color:var(--text-muted); font-size:12px; text-align:center; padding:30px 10px; }

    /* ── 모달 (등록/상세) ── */
    .todo-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:400; align-items:flex-start; justify-content:center; padding:6vh 16px 16px; overflow-y:auto; }
    .todo-overlay.open { display:flex; }
    .todo-modal { width:100%; max-width:560px; background:var(--surface); border:1px solid var(--border); border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
    .todo-modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .todo-modal-head h2 { font-size:15px; font-weight:700; }
    .todo-modal-close { background:none; border:none; font-size:18px; color:var(--text-muted); cursor:pointer; padding:2px 8px; }
    .todo-modal-body { padding:18px 20px; display:flex; flex-direction:column; gap:14px; }
    .todo-field label { display:block; font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:5px; }
    .todo-field input[type=text], .todo-field input[type=date], .todo-field select, .todo-field textarea {
        width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; font-family:inherit;
    }
    .todo-field textarea { resize:vertical; min-height:90px; line-height:1.6; }
    .todo-field input:focus, .todo-field select:focus, .todo-field textarea:focus { border-color:var(--accent); }
    .todo-field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width:560px) { .todo-field-row { grid-template-columns:1fr; } }
    .todo-modal-foot { display:flex; gap:8px; padding:14px 20px; border-top:1px solid var(--border); justify-content:flex-end; flex-wrap:wrap; }
    /* 임시저장 배너 */
    .tf-draft-bar { display:flex; align-items:center; gap:8px; background:color-mix(in srgb, var(--accent) 8%, transparent); border:1px solid color-mix(in srgb, var(--accent) 30%, transparent); border-radius:9px; padding:8px 12px; font-size:12px; color:var(--text); }
    .tf-draft-bar button { border:none; border-radius:7px; padding:5px 11px; font-size:11.5px; font-weight:700; cursor:pointer; background:var(--accent); color:var(--accent-text); }
    .tf-draft-bar .tf-draft-del { background:none; color:var(--text-muted); margin-left:auto; }
    .tf-draft-bar .tf-draft-del:hover { color:#c0392b; }
    .todo-btn { border:none; border-radius:8px; padding:9px 16px; font-size:13px; font-weight:700; cursor:pointer; }
    .todo-btn.primary { background:var(--accent); color:var(--accent-text); }
    .todo-btn.ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); }
    .todo-btn.danger { background:none; color:#c0392b; border:1px solid #e5b4ae; margin-right:auto; }
    .todo-btn.success { background:#2e7d32; color:#fff; }

    /* 상세 보기 */
    .todo-view-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:12px; color:var(--text-muted); }
    .todo-view-content { font-size:13px; line-height:1.7; white-space:pre-wrap; word-break:break-word; background:var(--surface2); border-radius:10px; padding:14px 16px; }
    .todo-attach-list { display:flex; flex-direction:column; gap:6px; }
    .todo-attach-item { display:flex; align-items:center; gap:8px; font-size:12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:7px 10px; }
    .todo-attach-item a { color:var(--accent); text-decoration:none; word-break:break-all; }
    .todo-attach-item img { width:38px; height:38px; object-fit:cover; border-radius:6px; flex-shrink:0; }
    .todo-attach-del { margin-left:auto; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 6px; }
    .todo-attach-del:hover { color:#c0392b; }

    /* 첨부 드롭존 — 캘린더 첨부 UI와 동일 패턴 */
    .img-upload-zone { border:1px dashed var(--border); border-radius:8px; padding:14px 12px; text-align:center; cursor:pointer; transition:all 0.2s; position:relative; font-size:11px; color:var(--text-muted); }
    .img-upload-zone:hover, .img-upload-zone.drag-over { border-color:var(--accent); background:color-mix(in srgb, var(--accent) 5%, transparent); color:var(--accent); }
    .img-upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
    .img-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:10px; margin-top:8px; }
    .img-grid:empty { display:none; }
    .img-item { position:relative; border-radius:8px; border:1px solid var(--border); background:var(--surface2); display:flex; flex-direction:column; }
    .img-item .img-thumb-wrap { position:relative; aspect-ratio:1; overflow:hidden; border-radius:8px 8px 0 0; }
    .img-item img { width:100%; height:100%; object-fit:cover; display:block; }
    .img-item .img-fileicon { width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:30px; background:var(--surface2); }
    .img-item .img-filename { font-size:10px; color:var(--text-muted); padding:4px 7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-top:1px solid var(--border); }
    .img-remove { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.75); border:none; color:#fff; width:18px; height:18px; border-radius:50%; cursor:pointer; font-size:10px; display:flex; align-items:center; justify-content:center; z-index:1; }

    /* 이미지 뷰어 */
    .todo-lb { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:600; align-items:center; justify-content:center; cursor:zoom-out; }
    .todo-lb.open { display:flex; }
    .todo-lb img { max-width:92vw; max-height:88vh; object-fit:contain; border-radius:8px; box-shadow:0 4px 32px rgba(0,0,0,0.5); }
    .todo-lb-close { position:fixed; top:14px; right:14px; width:38px; height:38px; border-radius:50%; border:1px solid rgba(255,255,255,0.35); background:rgba(0,0,0,0.5); color:#fff; font-size:16px; cursor:pointer; z-index:601; }

    @media (max-width:768px) {
        .todo-wrap { padding:16px 12px; }
        .todo-board { flex-direction:column; max-height:none !important; }
        .todo-col { flex:none; width:100%; max-height:none; }
        .todo-col-body { overflow-y:visible; }
    }
</style>
@endpush

@section('content')
@php
    $me = auth()->user();
    $membersJson = $members->map(fn ($m) => [
        'id' => $m->id,
        'name' => $m->display_name,
        'team' => $m->team?->name,
    ])->values();
@endphp
<div class="todo-wrap">
    <div class="todo-header">
        <div>
            <h1>할 일</h1>
            <p>담당자별 진행 현황 · 드래그로 담당자 변경</p>
        </div>
        <div class="todo-header-actions">
            @if($me->isAdmin())
            <label class="todo-toggle"><input type="checkbox" id="todoMineOnly" onchange="renderBoard()"> 내 것만 보기</label>
            <div class="todo-mfilter" id="todoMfilter">
                <button type="button" class="todo-mfilter-btn" onclick="toggleMfilter()">직원 필터 <span id="mfilterCount"></span> ▾</button>
                <div class="todo-mfilter-panel" id="mfilterPanel"></div>
            </div>
            @else
            <input type="checkbox" id="todoMineOnly" hidden>
            @endif
            <label class="todo-toggle"><input type="checkbox" id="todoShowDone" onchange="renderBoard()"> 완료 보기</label>
            <div class="todo-viewswitch">
                <button type="button" id="viewBtnBoard" onclick="setTodoView('board')" title="카드 보드">▦ 카드</button>
                <button type="button" id="viewBtnList" onclick="setTodoView('list')" title="리스트">☰ 리스트</button>
            </div>
            <button type="button" class="todo-add-btn" onclick="openTodoForm()">+ 할일 추가</button>
        </div>
    </div>
    <div class="todo-board" id="todoBoard"></div>
</div>

{{-- 등록/수정 모달 --}}
<div class="todo-overlay" id="todoFormOverlay">
    <div class="todo-modal">
        <div class="todo-modal-head">
            <h2 id="todoFormTitle">할일 추가</h2>
            <button type="button" class="todo-modal-close" onclick="closeTodoForm()">✕</button>
        </div>
        <div class="todo-modal-body">
            {{-- 임시저장 배너 --}}
            <div class="tf-draft-bar" id="tfDraftBar" style="display:none;">
                <span id="tfDraftInfo"></span>
                <button type="button" id="tfDraftRestore" onclick="restoreDraft()">불러오기</button>
                <button type="button" class="tf-draft-del" onclick="discardDraft()">삭제</button>
            </div>
            <div class="todo-field">
                <label>타이틀 *</label>
                <input type="text" id="tfTitle" maxlength="255" placeholder="할 일 제목">
            </div>
            <div class="todo-field-row">
                <div class="todo-field">
                    <label>우선순위 *</label>
                    <select id="tfPriority">
                        <option value="high">높음</option>
                        <option value="medium" selected>중간</option>
                        <option value="low">낮음</option>
                    </select>
                </div>
                <div class="todo-field">
                    <label>기한</label>
                    <input type="date" id="tfDue">
                </div>
            </div>
            {{-- 담당자 — 전체 폭 섹션 (칩이 가로로 흐르도록) --}}
            <div class="todo-field" id="tfAssigneeField" @if(!$me->isAdmin()) style="display:none;" @endif>
                <label>담당자 * <span style="font-weight:400;color:var(--text-muted);">선택한 순서대로 표시되며 첫 번째가 대표 담당자입니다</span></label>
                <div id="tfAssigneeChips" style="display:flex;flex-wrap:wrap;gap:7px;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;"></div>
            </div>
            <div class="todo-field">
                <label>내용</label>
                <textarea id="tfContent" maxlength="10000" placeholder="상세 내용을 입력하세요"></textarea>
            </div>
            <div class="todo-field">
                <label>첨부파일 (이미지 · 파일 · 영상)</label>
                <div class="img-upload-zone" id="todoDropZone">
                    <input type="file" id="tfFiles" multiple onchange="addTodoFiles(this.files); this.value='';">
                    📎 파일을 클릭·드래그하거나 클립보드에서 붙여넣기 (Ctrl+V)
                </div>
                <div class="img-grid" id="tfFileGrid"></div>
            </div>
        </div>
        <div class="todo-modal-foot">
            <button type="button" class="todo-btn ghost" onclick="closeTodoForm()">취소</button>
            <button type="button" class="todo-btn primary" id="tfSaveBtn" onclick="saveTodo()">저장</button>
        </div>
    </div>
</div>

{{-- 상세 모달 --}}
<div class="todo-overlay" id="todoViewOverlay">
    <div class="todo-modal">
        <div class="todo-modal-head">
            <h2 id="tvTitle"></h2>
            <button type="button" class="todo-modal-close" onclick="closeTodoView()">✕</button>
        </div>
        <div class="todo-modal-body">
            <div class="todo-view-meta" id="tvMeta"></div>
            <div class="todo-view-content" id="tvContent"></div>
            <div id="tvAssignStatus"></div>
            <div id="tvChecklist"></div>
            <div class="todo-attach-list" id="tvAttachments"></div>
        </div>
        <div class="todo-modal-foot">
            <button type="button" class="todo-btn danger" onclick="deleteTodo()">삭제</button>
            <button type="button" class="todo-btn ghost" onclick="editTodo()">수정</button>
            <button type="button" class="todo-btn ghost" id="tvHoldBtn" onclick="toggleHoldDue()">기한 보류</button>
            <button type="button" class="todo-btn success" id="tvCompleteBtn" onclick="toggleComplete()">완료 처리</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const TODO_MEMBERS = @json($membersJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
const TODO_ME = {{ $me->id }};
const IS_ADMIN = @json($me->isAdmin());
const TODO_CSRF = '{{ csrf_token() }}';
let TODO_VIEW = localStorage.getItem('todo_view') || 'board';
let MFILTER = new Set(); // 직원 필터 (비어 있으면 전체)
let TODOS = @json($todos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
let TODO_VIEW_ID = null;
let TODO_EDIT_ID = null;

const PRI_LABELS = { high: '높음', medium: '중간', low: '낮음' };
function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function memberById(id) { return TODO_MEMBERS.find(m => m.id === id); }

// YYYY-MM-DD → YY년 MM월 DD일
function fmtDate(iso) {
    if (!iso) { return ''; }
    const [y, m, d] = iso.split('-');
    return `${y.slice(2)}년 ${m}월 ${d}일`;
}

function dueDiff(t) {
    if (!t.due_date) { return null; }
    const today = new Date(); today.setHours(0,0,0,0);
    return Math.round((new Date(t.due_date + 'T00:00:00') - today) / 86400000);
}

function dueChip(t) {
    if (!t.due_date) { return ''; }
    if (t.completed) { return `<span class="todo-due">${fmtDate(t.due_date)}</span>`; }
    if (t.due_held) { return `<span class="todo-due held" title="기한 보류 중${t.due_held_at ? ' · ' + t.due_held_at : ''}">⏸ 보류</span>`; }
    const diff = dueDiff(t);
    if (diff < 0) { return `<span class="todo-due overdue">⚠ ${-diff}일 지남</span>`; }
    if (diff === 0) { return `<span class="todo-due today">오늘 마감</span>`; }
    return `<span class="todo-due">D-${diff}</span>`;
}

// 리스트 뷰용 기한 날짜 — 임박 정도에 따라 색상 강조
function dueDateHtml(t) {
    if (!t.due_date) { return ''; }
    const diff = dueDiff(t);
    const cls = (t.completed || t.due_held) ? '' : diff <= 0 ? 'imminent' : diff <= 3 ? 'soon' : '';
    return `<span class="todo-lrow-due-date ${cls}">${fmtDate(t.due_date)}</span>`;
}

// 본문 링크 처리 — URL은 클릭 가능하게, 유튜브는 임베드, 일반 링크는 OG 미리보기 카드 (각 최대 3개)
function contentHtml(text) {
    if (!text) { return '내용 없음'; }
    const html = esc(text).replace(/(https?:\/\/[^\s<]+)/g, m => `<a href="${m}" target="_blank" rel="noopener">${m}</a>`);
    const ytVideos = [], others = [];
    const ytRe = /(?:youtube\.com\/(?:watch\?[^\s<]*v=|shorts\/|embed\/|live\/)|youtu\.be\/)([\w-]{11})/;
    (text.match(/https?:\/\/[^\s<]+/g) || []).forEach(u => {
        const yt = u.match(ytRe);
        if (yt) {
            if (!ytVideos.some(v => v.id === yt[1]) && ytVideos.length < 3) {
                ytVideos.push({ id: yt[1], vertical: u.includes('/shorts/') });
            }
        } else if (!others.includes(u) && others.length < 3) {
            others.push(u);
        }
    });
    const embeds = ytVideos.map(v => `<div class="yt-embed ${v.vertical ? 'vertical' : ''}"><iframe src="https://www.youtube-nocookie.com/embed/${v.id}" loading="lazy" allowfullscreen allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>`).join('');
    const cards = others.map(u => `<a class="link-card" data-url="${esc(u)}" href="${esc(u)}" target="_blank" rel="noopener"></a>`).join('');
    return html + embeds + cards;
}

// OG 미리보기 카드 로딩 — contentHtml 렌더 후 호출
const LINK_PREVIEW_CACHE = new Map();
async function loadLinkCards() {
    for (const el of document.querySelectorAll('.link-card[data-url]:not(.loaded)')) {
        el.classList.add('loaded');
        const url = el.dataset.url;
        try {
            let data = LINK_PREVIEW_CACHE.get(url);
            if (!data) {
                const res = await fetch('/api/link-preview?url=' + encodeURIComponent(url), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { el.remove(); continue; }
                data = await res.json();
                LINK_PREVIEW_CACHE.set(url, data);
            }
            el.innerHTML = `
                ${data.image ? `<img class="lc-thumb" src="${esc(data.image)}" alt="" loading="lazy" onerror="this.remove()">` : ''}
                <span class="lc-body">
                    <span class="lc-title">${esc(data.title)}</span>
                    ${data.description ? `<span class="lc-desc">${esc(data.description)}</span>` : ''}
                    <span class="lc-host">${esc(data.host)}</span>
                </span>`;
        } catch (e) { el.remove(); }
    }
}

function filteredTodos() {
    const mineOnly = document.getElementById('todoMineOnly').checked;
    const showDone = document.getElementById('todoShowDone').checked;
    let todos = TODOS.filter(t => showDone ? true : !t.completed);
    // 복수 담당자: 어느 순번이든 포함되어 있으면 매치
    if (mineOnly) { todos = todos.filter(t => (t.assignee_ids || [t.assignee_id]).includes(TODO_ME)); }
    else if (MFILTER.size) { todos = todos.filter(t => (t.assignee_ids || [t.assignee_id]).some(id => MFILTER.has(id))); }
    return todos;
}

function renderBoard() {
    document.getElementById('viewBtnBoard').classList.toggle('on', TODO_VIEW === 'board');
    document.getElementById('viewBtnList').classList.toggle('on', TODO_VIEW === 'list');
    const board = document.getElementById('todoBoard');
    board.className = TODO_VIEW === 'list' ? 'todo-list-layout' : 'todo-board';

    const todos = filteredTodos();
    if (!todos.length) {
        board.innerHTML = '<div class="todo-empty" style="width:100%;">표시할 할 일이 없습니다. 우측 상단에서 추가해보세요.</div>';
        return;
    }

    if (TODO_VIEW === 'list') {
        board.innerHTML = `<div class="todo-list">${listHtml(todos)}</div><div class="todo-detail-pane" id="todoDetailPane"></div>`;
        renderDetailPane();
    } else {
        board.innerHTML = boardHtml(todos);
    }
    fitBoardHeight();
}

// 보드 높이를 화면 하단에 맞춤 — 컬럼은 내부 스크롤, 가로 스크롤바는 항상 화면 하단에 보임
// 보드 뷰에서는 페이지 세로 스크롤을 아예 꺼서 스크롤바가 절대 이중으로 보이지 않게 함
function fitBoardHeight() {
    const board = document.getElementById('todoBoard');
    if (!board) return;
    if (TODO_VIEW !== 'board' || window.innerWidth <= 768) {
        board.style.maxHeight = '';
        document.body.style.overflowY = '';
        return;
    }
    const top = board.getBoundingClientRect().top;
    board.style.maxHeight = Math.max(240, window.innerHeight - top - 14) + 'px';
    document.body.style.overflowY = 'hidden';
}
window.addEventListener('resize', fitBoardHeight);

// 필터 상태에 따라 표시할 담당자 컬럼 제한 (null = 전체)
function allowedColumnIds() {
    if (document.getElementById('todoMineOnly').checked) { return new Set([TODO_ME]); }
    if (MFILTER.size) { return new Set(MFILTER); }
    return null;
}

// 복수 담당 할 일은 각 담당자의 컬럼/섹션에 모두 표시
function groupByEachAssignee(todos) {
    const allowed = allowedColumnIds();
    const byAssignee = new Map();
    todos.forEach(t => {
        const ids = (t.assignee_ids && t.assignee_ids.length) ? t.assignee_ids : [t.assignee_id];
        ids.forEach(uid => {
            if (allowed && !allowed.has(uid)) { return; }
            if (!byAssignee.has(uid)) { byAssignee.set(uid, []); }
            byAssignee.get(uid).push(t);
        });
    });
    return byAssignee;
}

function boardHtml(todos) {
    // 할 일이 있는 인원만 컬럼 생성 — 복수 담당이면 각자의 컬럼에 모두 등장
    const byAssignee = groupByEachAssignee(todos);

    return [...byAssignee.entries()].map(([uid, items]) => {
        const m = memberById(uid) || { name: items[0].assignee, team: items[0].team };
        const openCount = items.filter(t => !t.completed).length;
        return `<div class="todo-col" data-assignee="${uid}"
            ondragover="colDragOver(event, this)"
            ondragleave="this.classList.remove('drag-over')"
            ondrop="dropTodo(event, ${uid})">
            <div class="todo-col-head">
                <span class="todo-col-name">${esc(m.name)}</span>
                ${m.team ? `<span class="todo-col-team">${esc(m.team)}</span>` : ''}
                <span class="todo-col-count">${openCount}</span>
            </div>
            <div class="todo-col-body">${items.map(t => cardHtml(t, uid)).join('')}</div>
        </div>`;
    }).join('');
}

const PRI_WEIGHT = { high: 0, medium: 1, low: 2 };
let SELECTED_ID = null;

function listHtml(todos) {
    // 담당자별 섹션 그룹화 — 복수 담당이면 각자의 섹션에 모두 등장
    const byAssignee = groupByEachAssignee(todos);

    return [...byAssignee.entries()].map(([uid, items]) => {
        const m = memberById(uid) || { name: items[0].assignee, team: items[0].team };
        const openCount = items.filter(t => !t.completed).length;
        const sorted = [...items].sort((a, b) =>
            (a.completed - b.completed)
            || ((a.due_date || '9999') < (b.due_date || '9999') ? -1 : (a.due_date || '9999') > (b.due_date || '9999') ? 1 : 0)
            || (PRI_WEIGHT[a.priority] - PRI_WEIGHT[b.priority]));
        return `<div class="todo-lgroup-head">
            <span class="todo-lgroup-name">${esc(m.name)}</span>
            ${m.team ? `<span class="todo-lgroup-team">${esc(m.team)}</span>` : ''}
            <span class="todo-lgroup-count">${openCount}</span>
        </div>` + sorted.map(t => `<div class="todo-lrow ${t.completed ? 'done' : ''} ${t.id === SELECTED_ID ? 'sel' : ''}" onclick="selectTodo(${t.id})" data-lrow="${t.id}">
            <button type="button" class="todo-check ${t.completed ? 'on' : ''}" onclick="event.stopPropagation(); quickComplete(${t.id})" title="${t.completed ? '완료 취소' : '완료 처리'}">
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            </button>
            <span class="todo-lrow-title">${esc(t.title)}</span>
            ${(t.checklist || []).length ? `<span class="todo-attach-n" title="진행 단계">☑ ${t.checklist.filter(c => c.done).length}/${t.checklist.length}</span>` : ''}
            ${t.attachments.length ? `<span class="todo-attach-n">📎 ${t.attachments.length}</span>` : ''}
            ${(t.assignee_names || []).length > 1 ? `<span class="todo-attach-n" title="${esc(t.assignee_names.join(', '))}">👥 +${t.assignee_names.length - 1}</span>` : ''}
            <span class="todo-lrow-right">
                ${t.team ? `<span class="todo-team-label">${esc(t.team)}</span>` : ''}
                <span class="todo-pri ${t.priority}">${PRI_LABELS[t.priority] || t.priority}</span>
                ${dueDateHtml(t)}
                ${dueChip(t)}
            </span>
        </div>`).join('');
    }).join('');
}

// 리스트 뷰: 행 선택 → 우측 상세 패널
function selectTodo(id) {
    SELECTED_ID = (SELECTED_ID === id) ? null : id;
    renderBoard();
}

function renderDetailPane() {
    const pane = document.getElementById('todoDetailPane');
    if (!pane) { return; }
    const t = filteredTodos().find(x => x.id === SELECTED_ID);
    if (!t) {
        pane.innerHTML = '<div class="todo-detail-empty">왼쪽 목록에서 할 일을 선택하면<br>내용이 여기에 표시됩니다.</div>';
        return;
    }
    pane.innerHTML = `
        <div class="tdp-title">${esc(t.title)}</div>
        <div class="todo-view-meta">
            <span class="todo-pri ${t.priority}">${PRI_LABELS[t.priority] || t.priority}</span>
            <span>담당 <b>${esc((t.assignee_names || [t.assignee]).join(', '))}</b>${t.team ? ` · ${esc(t.team)}` : ''}</span>
            ${t.due_date ? `<span>기한 ${fmtDate(t.due_date)}</span>` : ''}
            ${dueChip(t)}
            ${t.creator ? `<span>${esc(t.creator)} 등록 ${t.created_at}</span>` : ''}
            ${t.completed ? `<span class="tdp-done-badge">완료</span><span>${t.completed_at}</span>` : ''}
        </div>
        <div class="todo-view-content">${contentHtml(t.content)}</div>
        ${t.attachments.length ? `<div class="todo-attach-list">${t.attachments.map(a => `
            <div class="todo-attach-item">
                ${a.mime_type && a.mime_type.startsWith('image/') ? `<img src="${a.url}" alt="" loading="lazy" style="cursor:zoom-in;" onclick="event.stopPropagation(); todoLbOpen('${a.url}', ${t.id})">` : '📄'}
                <a href="${a.url}" target="_blank" rel="noopener">${esc(a.file_name)}</a>
                <button type="button" class="todo-attach-del" onclick="deleteAttachment(${a.id})" title="첨부 삭제">✕</button>
            </div>`).join('')}</div>` : ''}
        <div class="tdp-actions">
            <button type="button" class="todo-btn danger" onclick="paneDelete()">삭제</button>
            <button type="button" class="todo-btn ghost" onclick="paneEdit()">수정</button>
            ${t.due_date && !t.completed ? `<button type="button" class="todo-btn ghost" onclick="quickHoldDue(${t.id})">${t.due_held ? '보류 해제' : '기한 보류'}</button>` : ''}
            <button type="button" class="todo-btn success" onclick="quickComplete(${t.id})">${t.completed ? '완료 취소' : '완료 처리'}</button>
        </div>`;
    loadLinkCards();
}

function paneEdit() {
    const t = TODOS.find(x => x.id === SELECTED_ID);
    if (t) { openTodoForm(t); }
}

async function paneDelete() {
    if (!SELECTED_ID || !confirm('이 할 일을 삭제하시겠습니까?')) { return; }
    const res = await fetch(`/api/todos/${SELECTED_ID}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) { alert('삭제에 실패했습니다.'); return; }
    SELECTED_ID = null;
    await refreshBoard();
}

function setTodoView(v) {
    TODO_VIEW = v;
    localStorage.setItem('todo_view', v);
    renderBoard();
}

// ── 직원 필터 (관리자) ──
function toggleMfilter() {
    document.getElementById('todoMfilter').classList.toggle('open');
}

function renderMfilterPanel() {
    const panel = document.getElementById('mfilterPanel');
    if (!panel) { return; }
    panel.innerHTML = `<label class="mfilter-all"><input type="checkbox" ${MFILTER.size ? '' : 'checked'} onchange="MFILTER.clear(); renderMfilterPanel(); renderBoard();"> 전체</label>`
        + TODO_MEMBERS.map(m => `<label>
            <input type="checkbox" ${MFILTER.has(m.id) ? 'checked' : ''} onchange="toggleMfilterMember(${m.id})">
            ${esc(m.name)}${m.team ? `<span class="mfilter-team">${esc(m.team)}</span>` : ''}
        </label>`).join('');
    const count = document.getElementById('mfilterCount');
    if (count) { count.textContent = MFILTER.size ? `${MFILTER.size}` : ''; }
}

function toggleMfilterMember(id) {
    if (MFILTER.has(id)) { MFILTER.delete(id); } else { MFILTER.add(id); }
    renderMfilterPanel();
    renderBoard();
}

document.addEventListener('click', e => {
    const mf = document.getElementById('todoMfilter');
    if (mf && mf.classList.contains('open') && !mf.contains(e.target)) { mf.classList.remove('open'); }
});

// 복수 담당 + 내가 담당이면 카드 체크 = '내 완료' 토글 (전원 완료 시 전체 완료)
function isMyMultiToggle(t) {
    return t && (t.assignee_ids || []).length > 1 && t.assignee_ids.includes(TODO_ME);
}

function checklistProgressHtml(t, noIndent) {
    const list = t.checklist || [];
    if (!list.length) return '';
    const done = list.filter(c => c.done).length;
    const pct = Math.round(done / list.length * 100);
    return `<div class="todo-progress"${noIndent ? ' style="margin-left:0;"' : ''} title="진행 단계 ${done}/${list.length}">
        <div class="todo-progress-bar"><span style="width:${pct}%"></span></div>
        <span class="todo-progress-n">${done}/${list.length}</span>
    </div>`;
}

function cardHtml(t, colUid) {
    const priLabel = PRI_LABELS[t.priority] || t.priority;
    // 복수 담당 — 담당자 전원을 카드에 나열 (현재 컬럼 담당자는 굵게, 완료 체크한 사람은 ✓)
    const names = t.assignee_names || [];
    const ids = t.assignee_ids || [];
    const doneIds = new Set(t.assignee_completed_ids || []);
    const coLine = names.length > 1
        ? `<div class="todo-co-assignees" title="담당자 ${esc(names.join(', '))}">👥 ${ids.map((id, i) => {
            const nm = (doneIds.has(id) ? '<span class="a-done">✓' : '<span>') + esc(names[i] || '') + '</span>';
            return id === colUid ? `<b>${nm}</b>` : nm;
        }).join(' · ')}</div>`
        : '';
    const myHalf = !t.completed && t.my_completed; // 내 몫은 완료, 다른 담당자 대기 중
    const checkTitle = isMyMultiToggle(t)
        ? (t.my_completed ? '내 완료 해제' : '내 완료 체크 (전원 완료 시 전체 완료)')
        : (t.completed ? '완료 취소' : '완료 처리');
    return `<div class="todo-card p-${t.priority} ${t.completed ? 'done' : ''}" draggable="true" data-id="${t.id}"
        ondragstart="startCardDrag(event, ${t.id})"
        ondragend="endCardDrag(this)"
        onclick="openTodoView(${t.id})">
        <div class="todo-card-meta">
            ${t.team ? `<span class="todo-team-label">${esc(t.team)}</span>` : ''}
            <span class="todo-pri ${t.priority}">${priLabel}</span>
        </div>
        <div class="todo-card-title-row">
            <button type="button" class="todo-check ${t.completed ? 'on' : ''} ${myHalf ? 'half' : ''}" onclick="event.stopPropagation(); quickComplete(${t.id})" title="${checkTitle}">
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            </button>
            <div class="todo-card-title">${esc(t.title)}</div>
        </div>
        ${coLine}
        ${checklistProgressHtml(t)}
        <div class="todo-card-foot">
            ${dueChip(t)}
            ${ids.length > 1 && !t.completed ? `<span class="todo-due">완료 ${doneIds.size}/${ids.length}</span>` : ''}
            ${t.attachments.length ? `<span class="todo-attach-n">📎 ${t.attachments.length}</span>` : ''}
            ${t.completed ? `<span class="todo-due">완료 ${t.completed_at}</span>` : ''}
        </div>
    </div>`;
}

// 드래그 시작 — 할 일이 없어 컬럼이 없는 직원도 드롭 대상이 되도록 빈 고스트 컬럼을 펼침
function startCardDrag(ev, id) {
    ev.dataTransfer.setData('text/plain', String(id));
    ev.dataTransfer.effectAllowed = 'move';
    ev.currentTarget.classList.add('dragging');
    const board = document.getElementById('todoBoard');
    const existing = new Set([...board.querySelectorAll('.todo-col')].map(c => parseInt(c.dataset.assignee, 10)));
    TODO_MEMBERS.filter(m => !existing.has(m.id)).forEach(m => {
        const col = document.createElement('div');
        col.className = 'todo-col ghost';
        col.dataset.assignee = m.id;
        col.innerHTML = `<div class="todo-col-head">
                <span class="todo-col-name">${esc(m.name)}</span>
                ${m.team ? `<span class="todo-col-team">${esc(m.team)}</span>` : ''}
                <span class="todo-col-count">0</span>
            </div>
            <div class="todo-col-body"><div class="todo-empty">여기로 드래그</div></div>`;
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => dropTodo(e, m.id));
        board.appendChild(col);
    });
}

function endCardDrag(card) {
    card.classList.remove('dragging');
    document.querySelectorAll('#todoBoard .todo-col.ghost').forEach(c => c.remove());
}

// 드래그 중 컬럼 위 이동 — 같은 컬럼(또는 관리자는 다른 컬럼)에서 카드가 마우스 위치로 실시간 재배치됨
function colDragOver(ev, colEl) {
    ev.preventDefault();
    colEl.classList.add('drag-over');
    const dragging = document.querySelector('#todoBoard .todo-card.dragging');
    if (!dragging) { return; }
    const dragTodo = TODOS.find(t => t.id === parseInt(dragging.dataset.id, 10));
    const colAssignee = parseInt(colEl.dataset.assignee, 10);
    // 멤버는 자기 컬럼 안에서만 이동 (다른 컬럼으로의 실시간 이동 차단)
    if (!IS_ADMIN && dragTodo && dragTodo.assignee_id !== colAssignee) { return; }
    const body = colEl.querySelector('.todo-col-body');
    const after = cardAfterPointer(body, ev.clientY);
    if (after === null) { body.appendChild(dragging); }
    else if (after !== dragging) { body.insertBefore(dragging, after); }
}

// 마우스 y좌표 기준으로 어느 카드 앞에 끼울지 계산
function cardAfterPointer(body, y) {
    let closest = { offset: -Infinity, el: null };
    body.querySelectorAll('.todo-card:not(.dragging)').forEach(card => {
        const box = card.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) { closest = { offset, el: card }; }
    });
    return closest.el;
}

async function dropTodo(ev, assigneeId) {
    ev.preventDefault();
    ev.currentTarget.classList.remove('drag-over');
    const id = parseInt(ev.dataTransfer.getData('text/plain'), 10);
    const todo = TODOS.find(t => t.id === id);
    if (!todo) { return; }

    // 이미 담당자(대표든 공동이든)인 컬럼으로의 드롭은 순서 변경으로만 처리 —
    // 복수 담당 카드가 여러 컬럼에 표시되므로, 자기 컬럼 내 정렬이 대표 변경으로 번지지 않게 함
    const changed = !(todo.assignee_ids || [todo.assignee_id]).includes(assigneeId);
    if (changed && !IS_ADMIN) { return; } // 담당자 변경은 관리자 이상 (순서 변경은 누구나)

    if (changed) {
        const res = await fetch(`/api/todos/${id}/assign`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ assignee_id: assigneeId }),
        });
        if (!res.ok) { alert('담당자 변경에 실패했습니다.'); await refreshBoard(); return; }
    }

    // 드롭된 컬럼의 화면 순서 그대로 저장
    const body = ev.currentTarget.querySelector('.todo-col-body');
    const ids = body ? [...body.querySelectorAll('.todo-card')].map(c => parseInt(c.dataset.id, 10)).filter(Boolean) : [];
    if (ids.length) {
        const res = await fetch('/api/todos/reorder', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ ids }),
        });
        if (!res.ok) { alert('순서 저장에 실패했습니다.'); }
    }
    await refreshBoard();
}

async function refreshBoard() {
    const res = await fetch('/api/todos', { headers: { 'Accept': 'application/json' } });
    if (res.ok) { TODOS = (await res.json()).todos; renderBoard(); }
}

// 이미지 뷰어 — 공용 drgoViewer. 해당 할 일의 이미지 첨부 전체를 앨범으로 (확대·스와이프·넘김)
function todoLbOpen(src, todoId){
    const t = todoId ? TODOS.find(x => x.id === todoId) : null;
    const imgs = t ? t.attachments.filter(a => a.mime_type && a.mime_type.startsWith('image/')) : [];
    if (imgs.length) {
        drgoViewer.open(imgs.map(a => ({ src: a.url, filename: a.file_name })), Math.max(0, imgs.findIndex(a => a.url === src)));
    } else {
        drgoViewer.open(src);
    }
}

// ── 첨부 대기열 (드롭존·클립보드 붙여넣기·파일 선택 공용) ──
let TF_PENDING = [];

function addTodoFiles(files) {
    [...files].forEach(f => TF_PENDING.push(f));
    renderTfGrid();
}

function removeTodoFile(idx) {
    TF_PENDING.splice(idx, 1);
    renderTfGrid();
}

function renderTfGrid() {
    const grid = document.getElementById('tfFileGrid');
    grid.innerHTML = '';
    TF_PENDING.forEach((f, i) => {
        const div = document.createElement('div'); div.className = 'img-item';
        const wrap = document.createElement('div'); wrap.className = 'img-thumb-wrap';
        if (f.type.startsWith('image/')) {
            const img = document.createElement('img'); img.src = URL.createObjectURL(f); img.alt = f.name;
            img.style.cursor = 'zoom-in'; img.title = '클릭하여 크게 보기';
            img.onclick = () => todoLbOpen(img.src); // 저장 전 대기 첨부도 뷰어로 확인
            wrap.appendChild(img);
        } else {
            const ic = document.createElement('div'); ic.className = 'img-fileicon';
            ic.textContent = f.type.startsWith('video/') ? '🎬' : '📄';
            wrap.appendChild(ic);
        }
        const rm = document.createElement('button'); rm.type = 'button'; rm.className = 'img-remove'; rm.textContent = '✕';
        rm.onclick = () => removeTodoFile(i);
        wrap.appendChild(rm); div.appendChild(wrap);
        const fn = document.createElement('div'); fn.className = 'img-filename'; fn.textContent = f.name;
        div.appendChild(fn);
        grid.appendChild(div);
    });
}

// 드롭존 드래그앤드롭
const todoDropZone = document.getElementById('todoDropZone');
todoDropZone.addEventListener('dragover', e => { e.preventDefault(); todoDropZone.classList.add('drag-over'); });
todoDropZone.addEventListener('dragleave', () => todoDropZone.classList.remove('drag-over'));
todoDropZone.addEventListener('drop', e => { e.preventDefault(); todoDropZone.classList.remove('drag-over'); addTodoFiles(e.dataTransfer.files); });

// 클립보드 붙여넣기 — 등록/수정 모달이 열려 있을 때만
document.addEventListener('paste', e => {
    if (!document.getElementById('todoFormOverlay').classList.contains('open')) { return; }
    const files = [...(e.clipboardData?.files || [])];
    if (!files.length) { return; }
    e.preventDefault();
    addTodoFiles(files.map((f, i) => {
        if (f.name && f.name !== 'image.png') { return f; }
        const ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        const stamp = new Date().toISOString().slice(0, 19).replaceAll(':', '').replace('T', '-');
        return new File([f], `붙여넣기-${stamp}${i ? '-' + i : ''}.${ext}`, { type: f.type });
    }));
});

// ── 등록/수정 모달 ──
// 담당자 다중 선택 — 클릭한 순서대로 배열에 쌓임 (첫 번째 = 대표, 칸반 컬럼 기준)
let TF_ASSIGNEES = [];
function fillAssigneeOptions(selectedIds) {
    TF_ASSIGNEES = (selectedIds || []).filter(id => TODO_MEMBERS.some(m => m.id === id));
    renderAssigneeChips();
}
function renderAssigneeChips() {
    const wrap = document.getElementById('tfAssigneeChips');
    if (!wrap) return;
    wrap.innerHTML = TODO_MEMBERS.map(m => {
        const idx = TF_ASSIGNEES.indexOf(m.id);
        const on = idx !== -1;
        return `<button type="button" onclick="toggleTfAssignee(${m.id})"
            style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:8px;font-size:12.5px;cursor:pointer;transition:all .12s;
            border:1px solid ${on ? 'var(--accent)' : 'var(--border)'};
            background:${on ? 'color-mix(in srgb, var(--accent) 16%, transparent)' : 'none'};
            color:${on ? 'var(--accent)' : 'var(--text-muted)'};font-weight:${on ? 700 : 400};">
            ${on ? `<span style="display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;border-radius:50%;background:var(--accent);color:var(--accent-text);font-size:9.5px;font-weight:800;">${idx + 1}</span>` : ''}
            ${esc(m.name)}</button>`;
    }).join('');
}
function toggleTfAssignee(id) {
    const idx = TF_ASSIGNEES.indexOf(id);
    if (idx !== -1) { TF_ASSIGNEES.splice(idx, 1); }
    else { TF_ASSIGNEES.push(id); }
    renderAssigneeChips();
}

function openTodoForm(todo) {
    TODO_EDIT_ID = todo ? todo.id : null;
    document.getElementById('todoFormTitle').textContent = todo ? '할일 수정' : '할일 추가';
    document.getElementById('tfTitle').value = todo ? todo.title : '';
    document.getElementById('tfPriority').value = todo ? todo.priority : 'medium';
    document.getElementById('tfDue').value = todo ? (todo.due_date || '') : '';
    document.getElementById('tfContent').value = todo ? (todo.content || '') : '';
    TF_PENDING = [];
    renderTfGrid();
    fillAssigneeOptions(todo ? (todo.assignee_ids || [todo.assignee_id]) : [TODO_ME]);
    document.getElementById('todoFormOverlay').classList.add('open');
    document.getElementById('tfTitle').focus();
    armModalHistory();
    checkDraftBar();
    clearInterval(TF_AUTOSAVE);
    TF_AUTOSAVE = setInterval(saveDraft, 60000); // 1분마다 자동 임시저장
}
function closeTodoForm() {
    if (MODAL_HIST) { history.back(); } else { reallyCloseForm(); }
}
function reallyCloseForm() {
    document.getElementById('todoFormOverlay').classList.remove('open');
    clearInterval(TF_AUTOSAVE);
}

// ── 임시저장 (자동저장 1분 주기 + 불러오기) ──
let TF_AUTOSAVE = null;
function draftKey() { return TODO_EDIT_ID ? `todo_draft_edit_${TODO_EDIT_ID}` : 'todo_draft_new'; }

function saveDraft() {
    if (!document.getElementById('todoFormOverlay').classList.contains('open')) { return; }
    const title = document.getElementById('tfTitle').value.trim();
    const content = document.getElementById('tfContent').value.trim();
    if (!title && !content) { return; }
    localStorage.setItem(draftKey(), JSON.stringify({
        title,
        content,
        priority: document.getElementById('tfPriority').value,
        due_date: document.getElementById('tfDue').value,
        ts: Date.now(),
    }));
    const bar = document.getElementById('tfDraftBar');
    bar.style.display = 'flex';
    document.getElementById('tfDraftInfo').textContent = `🕐 ${new Date().toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })} 자동저장됨`;
    document.getElementById('tfDraftRestore').style.display = 'none';
}

function checkDraftBar() {
    const bar = document.getElementById('tfDraftBar');
    const raw = localStorage.getItem(draftKey());
    if (!raw) { bar.style.display = 'none'; return; }
    try {
        const d = JSON.parse(raw);
        bar.style.display = 'flex';
        document.getElementById('tfDraftInfo').textContent = `🕐 ${new Date(d.ts).toLocaleString('ko-KR', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })} 임시저장본이 있습니다`;
        document.getElementById('tfDraftRestore').style.display = '';
    } catch (e) { bar.style.display = 'none'; }
}

function restoreDraft() {
    const raw = localStorage.getItem(draftKey());
    if (!raw) { return; }
    try {
        const d = JSON.parse(raw);
        document.getElementById('tfTitle').value = d.title || '';
        document.getElementById('tfContent').value = d.content || '';
        document.getElementById('tfPriority').value = d.priority || 'medium';
        document.getElementById('tfDue').value = d.due_date || '';
        document.getElementById('tfDraftRestore').style.display = 'none';
        document.getElementById('tfDraftInfo').textContent = '임시저장본을 불러왔습니다';
    } catch (e) { /* 무시 */ }
}

function discardDraft() {
    localStorage.removeItem(draftKey());
    document.getElementById('tfDraftBar').style.display = 'none';
}

async function saveTodo() {
    const btn = document.getElementById('tfSaveBtn');
    // 일반 멤버는 본인에게만 등록 가능 — 관리자는 선택 순서 그대로 (첫 번째 = 대표)
    const ids = IS_ADMIN ? TF_ASSIGNEES : [TODO_ME];
    const payload = {
        title: document.getElementById('tfTitle').value.trim(),
        priority: document.getElementById('tfPriority').value,
        due_date: document.getElementById('tfDue').value || null,
        content: document.getElementById('tfContent').value.trim() || null,
        assignee_ids: ids,
        assignee_id: ids[0] || null,
    };
    if (!payload.title) { alert('타이틀을 입력하세요.'); return; }
    if (!ids.length) { alert('담당자를 한 명 이상 선택하세요.'); return; }

    btn.disabled = true;
    try {
        const url = TODO_EDIT_ID ? `/api/todos/${TODO_EDIT_ID}` : '/api/todos';
        const res = await fetch(url, {
            method: TODO_EDIT_ID ? 'PATCH' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            alert(err.message || '저장에 실패했습니다.');
            return;
        }
        const { todo } = await res.json();

        // 첨부 업로드 (드롭존·붙여넣기 대기열)
        if (TF_PENDING.length) {
            const fd = new FormData();
            TF_PENDING.forEach(f => fd.append('files[]', f));
            const up = await fetch(`/api/todos/${todo.id}/attachments`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
                body: fd,
            });
            if (!up.ok) {
                const err = await up.json().catch(() => ({}));
                alert('할 일은 저장됐지만 첨부 업로드에 실패했습니다: ' + (err.message || ''));
            }
        }

        localStorage.removeItem(draftKey()); // 저장 성공 → 임시저장본 정리
        closeTodoForm();
        await refreshBoard();
    } finally {
        btn.disabled = false;
    }
}

// ── 상세 모달 ──
function openTodoView(id) {
    const t = TODOS.find(x => x.id === id);
    if (!t) { return; }
    TODO_VIEW_ID = id;
    document.getElementById('tvTitle').textContent = t.title;
    document.getElementById('tvMeta').innerHTML = [
        `<span class="todo-pri ${t.priority}">${PRI_LABELS[t.priority] || t.priority}</span>`,
        `<span>담당 <b>${esc((t.assignee_names || [t.assignee]).join(', '))}</b>${t.team ? ` · ${esc(t.team)}` : ''}</span>`,
        t.due_date ? `<span>기한 ${fmtDate(t.due_date)}</span>` : '',
        dueChip(t),
        t.creator ? `<span>${esc(t.creator)} 등록 ${t.created_at}</span>` : '',
        t.completed ? `<span class="tdp-done-badge">완료</span><span>${t.completed_at}</span>` : '',
    ].filter(Boolean).join('');
    document.getElementById('tvContent').innerHTML = contentHtml(t.content);
    loadLinkCards();
    document.getElementById('tvAttachments').innerHTML = t.attachments.map(a => `
        <div class="todo-attach-item">
            ${a.mime_type && a.mime_type.startsWith('image/') ? `<img src="${a.url}" alt="" loading="lazy" style="cursor:zoom-in;" onclick="event.stopPropagation(); todoLbOpen('${a.url}', ${t.id})">` : '📄'}
            <a href="${a.url}" target="_blank" rel="noopener">${esc(a.file_name)}</a>
            <button type="button" class="todo-attach-del" onclick="deleteAttachment(${a.id})" title="첨부 삭제">✕</button>
        </div>`).join('');
    // 담당자별 완료 현황 (복수 담당)
    const ids = t.assignee_ids || [];
    const doneIds = new Set(t.assignee_completed_ids || []);
    document.getElementById('tvAssignStatus').innerHTML = ids.length > 1 ? `
        <div class="tv-section-title">담당자 완료 현황 ${doneIds.size}/${ids.length} — 전원 완료 시 자동 완료</div>
        <div class="tv-assign-chips">${ids.map((id, i) =>
            `<span class="tv-assign-chip ${doneIds.has(id) ? 'done' : ''}">${doneIds.has(id) ? '✓ ' : ''}${esc((t.assignee_names || [])[i] || '')}</span>`).join('')}
        </div>` : '';
    tvRenderChecklist(t);
    const mine = isMyMultiToggle(t);
    document.getElementById('tvCompleteBtn').textContent = mine
        ? (t.my_completed ? '내 완료 해제' : '내 완료 체크')
        : (t.completed ? '완료 취소' : '완료 처리');
    const holdBtn = document.getElementById('tvHoldBtn');
    holdBtn.style.display = (t.due_date && !t.completed) ? '' : 'none';
    holdBtn.textContent = t.due_held ? '보류 해제' : '기한 보류';
    document.getElementById('todoViewOverlay').classList.add('open');
    armModalHistory();
}

// ── 체크리스트 (진행 단계) ──
function tvRenderChecklist(t) {
    const list = t.checklist || [];
    const done = list.filter(c => c.done).length;
    document.getElementById('tvChecklist').innerHTML = `
        <div class="tv-section-title">진행 단계 ${list.length ? `${done}/${list.length}` : ''}</div>
        ${checklistProgressHtml(t, true)}
        ${list.map(c => `<div class="tv-check-row ${c.done ? 'done' : ''}">
            <button type="button" class="todo-check ${c.done ? 'on' : ''}" onclick="toggleChecklistItem(${c.id}, ${c.done ? 'false' : 'true'})" title="${c.done ? '완료 해제' : '완료'}">
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            </button>
            <span class="tv-check-title">${esc(c.title)}</span>
            ${c.done && c.done_by ? `<span class="tv-check-by">✓ ${esc(c.done_by)}</span>` : ''}
            <button type="button" class="tv-check-del" onclick="deleteChecklistItem(${c.id})" title="단계 삭제">✕</button>
        </div>`).join('')}
        <div class="tv-check-add">
            <input id="tvCheckAddInput" placeholder="진행 단계 추가 (예: 전파인증 서류 접수)" onkeydown="if(event.key==='Enter'){event.preventDefault();addChecklistItem();}">
            <button type="button" class="todo-btn ghost" onclick="addChecklistItem()">추가</button>
        </div>`;
}

async function checklistApi(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) { const e = await res.json().catch(() => ({})); alert(e.message || '처리에 실패했습니다.'); return false; }
    const id = TODO_VIEW_ID;
    await refreshBoard();
    if (id) { const t = TODOS.find(x => x.id === id); if (t) { openTodoView(id); } }
    return true;
}
async function addChecklistItem() {
    const input = document.getElementById('tvCheckAddInput');
    const title = input.value.trim();
    if (!title) { return; }
    await checklistApi(`/api/todos/${TODO_VIEW_ID}/checklist`, 'POST', { title });
    const again = document.getElementById('tvCheckAddInput');
    if (again) { again.focus(); }
}
async function toggleChecklistItem(itemId, done) {
    await checklistApi(`/api/todo-checklist/${itemId}`, 'PATCH', { done });
}
async function deleteChecklistItem(itemId) {
    if (!confirm('이 진행 단계를 삭제할까요?')) { return; }
    await checklistApi(`/api/todo-checklist/${itemId}`, 'DELETE');
}
function closeTodoView() {
    if (MODAL_HIST) { history.back(); } else { reallyCloseView(); }
}
function reallyCloseView() { document.getElementById('todoViewOverlay').classList.remove('open'); TODO_VIEW_ID = null; }

function editTodo() {
    const t = TODOS.find(x => x.id === TODO_VIEW_ID);
    if (!t) { return; }
    reallyCloseView(); // 히스토리는 폼 모달이 이어받음
    openTodoForm(t);
}

// 카드 체크박스로 즉시 완료 토글 — 복수 담당 + 내가 담당이면 '내 완료' 토글
async function quickComplete(id) {
    const t = TODOS.find(x => x.id === id);
    const url = isMyMultiToggle(t) ? `/api/todos/${id}/my-complete` : `/api/todos/${id}/complete`;
    const res = await fetch(url, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) { const e = await res.json().catch(() => ({})); alert(e.message || '처리에 실패했습니다.'); return; }
    await refreshBoard();
}

// 기한 보류 토글 — 상세 패널·카드 공용 (기한이 있는 미완료 할 일만 버튼 노출)
async function quickHoldDue(id) {
    const res = await fetch(`/api/todos/${id}/hold-due`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        alert(err.message || '처리에 실패했습니다.');
        return;
    }
    await refreshBoard();
}

// 상세 모달에서 보류 토글 — 갱신된 데이터로 모달을 다시 그림
async function toggleHoldDue() {
    const id = TODO_VIEW_ID;
    await quickHoldDue(id);
    if (id && TODOS.some(t => t.id === id)) { openTodoView(id); }
}

async function toggleComplete() {
    const id = TODO_VIEW_ID;
    const wasMine = isMyMultiToggle(TODOS.find(x => x.id === id));
    await quickComplete(id); // 스마트 라우팅 (내 완료/전체 완료) + 보드 갱신 포함
    const t = TODOS.find(x => x.id === id);
    // 내 완료 체크로 아직 전체 완료가 아니면 모달 유지(현황 갱신), 그 외에는 닫기
    if (wasMine && t && !t.completed) { openTodoView(id); } else { closeTodoView(); }
}

async function deleteTodo() {
    if (!confirm('이 할 일을 삭제하시겠습니까?')) { return; }
    const res = await fetch(`/api/todos/${TODO_VIEW_ID}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) { alert('삭제에 실패했습니다.'); return; }
    closeTodoView();
    await refreshBoard();
}

async function deleteAttachment(id) {
    if (!confirm('이 첨부파일을 삭제하시겠습니까?')) { return; }
    const res = await fetch(`/api/todo-attachments/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) { alert('삭제에 실패했습니다.'); return; }
    await refreshBoard();
    if (TODO_VIEW_ID) { openTodoView(TODO_VIEW_ID); }
}

// 모달 닫기 UX — 바깥 클릭으로는 닫히지 않고 ESC/뒤로가기(✕·취소 포함)로만 닫힘
let MODAL_HIST = false;
function armModalHistory() {
    if (!MODAL_HIST) { history.pushState({ todoModal: true }, ''); MODAL_HIST = true; }
}
window.addEventListener('popstate', () => { MODAL_HIST = false; reallyCloseForm(); reallyCloseView(); });
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') { return; }
    if (document.getElementById('todoFormOverlay').classList.contains('open')
        || document.getElementById('todoViewOverlay').classList.contains('open')) {
        e.preventDefault();
        closeAnyModal();
    }
});
function closeAnyModal() {
    if (MODAL_HIST) { history.back(); } // popstate에서 실제로 닫힘 (뒤로가기와 동일 경로)
    else { reallyCloseForm(); reallyCloseView(); }
}

renderMfilterPanel();
renderBoard();
</script>
@endpush
