@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', $project->name . ' - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px 32px; max-width:1400px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .page-header-left { display:flex; align-items:center; gap:12px; }
    .back-btn { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .back-btn:hover { color:var(--text); }
    .project-name { font-size:22px; font-weight:700; }
    .project-meta { font-size:13px; color:var(--text-muted); margin-top:4px; display:flex; align-items:center; gap:8px; }

    .process-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px 24px; margin-bottom:16px; }

    /* ── 방문 보고서 Tiptap 에디터 (위키와 동일 스타일) ── */
    .tiptap-wrap { border:1px solid var(--border); border-radius:10px; background:var(--surface); }
    /* 뷰 모드: 툴바 숨김 + 테두리/패딩 살짝 정리 */
    #visitReportCard[data-mode="view"] .tiptap-toolbar { display:none; }
    #visitReportCard[data-mode="view"] .tiptap-wrap { background:transparent; border-color:transparent; }
    #visitReportCard[data-mode="view"] #vrEditor .ProseMirror { padding:0; min-height:auto; cursor:default; }
    #visitReportCard[data-mode="view"] #vrEditor .ProseMirror img { cursor:zoom-in; }
    /* 뷰 모드에서는 placeholder(데이터 없는 빈 단락 안내) 숨김 */
    #visitReportCard[data-mode="view"] #vrEditor .ProseMirror p.is-editor-empty::before,
    #visitReportCard[data-mode="view"] #vrEditor .ProseMirror p.is-empty::before { content:none !important; }
    /* 뷰 모드 + 접힘 상태: 500px 까지만 보이고 페이드 그라데이션 */
    /* 뷰 모드 접힘: 500px 까지만 노출 + 페이드 그라데이션 */
    #visitReportCard[data-mode="view"].is-collapsed .tiptap-wrap { max-height:500px; overflow:hidden; position:relative; }
    #visitReportCard[data-mode="view"].is-collapsed .tiptap-wrap::after {
        content:''; position:absolute; left:0; right:0; bottom:0; height:80px;
        background:linear-gradient(to bottom, transparent, var(--surface) 90%);
        pointer-events:none;
    }
    /* '더 보기 / 접기' 버튼 — 뷰 모드에서는 무조건 노출 */
    .vr-expand-btn { display:none; width:100%; margin-top:8px; padding:10px 14px; background:var(--surface2); border:1px solid var(--border); color:var(--text-muted); border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s; }
    .vr-expand-btn:hover { color:var(--accent); border-color:var(--accent); background:var(--surface); }
    #visitReportCard[data-mode="view"] .vr-expand-btn { display:block; }
    /* JS에서 측정 후 'is-short' 클래스 부착 시에만 버튼 숨김 */
    #visitReportCard[data-mode="view"].is-short .vr-expand-btn { display:none; }
    .tiptap-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:8px 10px; border-bottom:1px solid var(--border); background:var(--surface2); position:sticky; top:0; z-index:10; border-radius:10px 10px 0 0; }
    .tiptap-toolbar button { background:none; border:1px solid transparent; color:var(--text-muted); width:30px; height:30px; border-radius:6px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:all 0.12s; }
    .tiptap-toolbar button:hover { background:var(--surface); border-color:var(--border); color:var(--text); }
    .tiptap-toolbar button.is-active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
    [data-theme="light"] .tiptap-toolbar button.is-active { color:#fff; }
    .tiptap-toolbar .sep { width:1px; height:20px; background:var(--border); margin:5px 4px; }
    .tiptap-toolbar .tool-btn { width:auto; padding:0 8px; font-size:11px; gap:4px; display:inline-flex; white-space:nowrap; height:30px; }
    #vrEditor .ProseMirror,
    #vrEditor.ProseMirror { padding:20px 24px; min-height:360px; outline:none; font-size:14px; line-height:1.85; color:var(--text); }
    #vrEditor .ProseMirror p { margin:0 0 10px; }
    #vrEditor .ProseMirror h1 { font-size:24px; font-weight:700; margin:20px 0 10px; }
    #vrEditor .ProseMirror h2 { font-size:20px; font-weight:700; margin:16px 0 8px; }
    #vrEditor .ProseMirror h3 { font-size:16px; font-weight:600; margin:14px 0 6px; }
    #vrEditor .ProseMirror ul, #vrEditor .ProseMirror ol { margin:0 0 10px; padding-left:24px; }
    #vrEditor .ProseMirror code { background:var(--surface2); padding:2px 6px; border-radius:4px; font-family:monospace; font-size:13px; }
    #vrEditor .ProseMirror pre { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 18px; margin:10px 0; overflow-x:auto; }
    #vrEditor .ProseMirror pre code { background:none; padding:0; }
    #vrEditor .ProseMirror blockquote { border-left:3px solid var(--accent); margin:10px 0; padding:6px 16px; color:var(--text-muted); }
    #vrEditor .ProseMirror img { max-width:100%; border-radius:8px; margin:6px 0; display:block; cursor:pointer; transition:outline 0.15s; }
    #vrEditor .ProseMirror img.ProseMirror-selectednode { outline:2px solid var(--accent); outline-offset:2px; }
    #vrEditor .ProseMirror img[data-align="left"]   { margin-left:0; margin-right:auto; }
    #vrEditor .ProseMirror img[data-align="center"] { margin-left:auto; margin-right:auto; }
    #vrEditor .ProseMirror img[data-align="right"]  { margin-left:auto; margin-right:0; }
    #vrEditor .ProseMirror video { max-width:100%; border-radius:8px; margin:6px 0; display:block; }
    #vrEditor .ProseMirror hr { border:none; border-top:1px solid var(--border); margin:16px 0; }
    #vrEditor .ProseMirror table { width:100%; border-collapse:collapse; margin:10px 0; }
    #vrEditor .ProseMirror th, #vrEditor .ProseMirror td { border:1px solid var(--border); padding:6px 10px; min-width:60px; }
    #vrEditor .ProseMirror th { background:var(--surface2); font-weight:600; }
    #vrEditor .ProseMirror p.is-editor-empty:first-child::before { content:attr(data-placeholder); color:var(--text-muted); float:left; pointer-events:none; height:0; white-space:pre-wrap; }
    #vrEditor .ProseMirror [data-text-align="center"] { text-align:center; }
    #vrEditor .ProseMirror [data-text-align="right"] { text-align:right; }
    .slash-menu { position:absolute; z-index:100; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:6px; min-width:200px; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:none; }
    .slash-menu.visible { display:block; }
    .slash-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:var(--text); transition:background 0.1s; }
    .slash-item:hover, .slash-item.selected { background:var(--surface2); }
    .slash-icon { width:28px; height:28px; border-radius:6px; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .slash-label { font-weight:500; }
    .slash-desc { font-size:11px; color:var(--text-muted); }
    .process-title { font-size:12px; color:var(--accent); font-weight:600; margin-bottom:16px; letter-spacing:0.05em; }
    .process-steps { display:flex; align-items:flex-start; }
    .process-step { flex:1; text-align:center; position:relative; }
    .process-step::after { content:''; position:absolute; top:14px; left:50%; width:100%; height:2px; background:var(--border); z-index:0; }
    .process-step:last-child::after { display:none; }
    .step-dot { width:28px; height:28px; border-radius:50%; border:2px solid var(--border); background:var(--bg); display:flex; align-items:center; justify-content:center; margin:0 auto 6px; font-size:11px; position:relative; z-index:1; cursor:pointer; transition:all 0.2s; color:var(--text-muted); }
    .step-dot:hover { border-color:var(--accent); color:var(--accent); }
    .step-dot.done { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .step-dot.active { border-color:var(--accent); color:var(--accent); background:var(--surface2); }
    .step-label { font-size:10px; color:var(--text-muted); }
    .step-label.active { color:var(--accent); font-weight:600; }

    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .info-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; }
    .info-card.full { grid-column:1/-1; }
    .card-title { font-size:12px; font-weight:600; color:var(--accent); margin-bottom:14px; letter-spacing:0.05em; display:flex; justify-content:space-between; align-items:center; }
    .info-row { display:flex; margin-bottom:10px; font-size:13px; }
    .info-label { color:var(--text-muted); min-width:80px; flex-shrink:0; }

    .badge { display:inline-block; font-size:11px; padding:3px 10px; border-radius:4px; font-weight:600; }
    .badge-visit   { background:#1a3a2a; color:#7ac87a; }
    .badge-remote  { background:#1a2a3a; color:#8ab4c8; }
    .badge-as      { background:#2a1a1a; color:#c87a7a; }

    .consult-list { display:flex; flex-direction:column; gap:8px; }
    .consult-item { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 16px; }
    .consult-item.important { border-color:#3a2a10; background:#1a1500; }
    .consult-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .consult-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .consult-date { font-size:12px; color:var(--text-muted); }
    .consult-type-badge { font-size:10px; padding:2px 7px; border-radius:4px; background:var(--surface); color:var(--text-muted); }
    .consult-result-badge { font-size:10px; padding:2px 7px; border-radius:4px; }
    .result-in_progress { background:#2a2010; color:var(--accent); }
    .result-waiting     { background:#1a1a2a; color:#8ab4c8; }
    .result-valid       { background:#1a2a1a; color:#7ac87a; }
    .result-invalid     { background:#2a1a1a; color:#c87a7a; }
    .result-done        { background:var(--surface); color:var(--text-muted); }
    .consult-content { font-size:13px; color:var(--text); line-height:1.6; white-space:pre-wrap; }
    .consult-footer { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
    .consult-author { font-size:11px; color:var(--text-muted); }
    .consult-actions { display:flex; gap:6px; }
    .btn-del { background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 6px; }
    .btn-del:hover { color:var(--red); }
    .btn-edit-sm { background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 6px; }
    .btn-edit-sm:hover { color:var(--accent); }
    .important-mark { color:var(--accent); font-size:12px; }

    .empty { text-align:center; padding:30px; color:var(--text-muted); font-size:13px; }

    .success-msg { background:#1a3a2a; border:1px solid #2a5a3a; color:#7ac87a; padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }
    .important-memo-box { background:rgba(212,188,150,0.12); border:1px solid rgba(212,188,150,0.4); color:var(--accent); }
    [data-theme="light"] .important-memo-box { background:#fff3e0; border-color:#e0b870; color:#a06800; }

    /* 문서 업로드 */
    .doc-upload-area { margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border); }
    .doc-upload-row { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
    .doc-upload-area .field-mini { font-size:11px; color:var(--text-muted); margin-bottom:4px; }
    .doc-upload-area select, .doc-upload-area input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; }
    .doc-upload-area select:focus, .doc-upload-area input[type="text"]:focus { border-color:var(--accent); }
    .btn-upload { background:var(--accent); color:var(--accent-text); border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; }
    .btn-upload:disabled { opacity:0.4; cursor:default; }
    .btn-choose { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 14px; color:var(--text); font-size:12px; cursor:pointer; white-space:nowrap; }
    .btn-choose:hover { border-color:var(--accent); }
    .file-preview-list { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .file-preview-item { position:relative; display:flex; align-items:center; gap:8px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:6px 10px; font-size:12px; color:var(--text); max-width:220px; }
    .file-preview-item .thumb { width:36px; height:36px; border-radius:4px; background:var(--bg); flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:var(--text-muted); overflow:hidden; position:relative; }
    .file-preview-item .thumb img, .file-preview-item .thumb canvas { width:100%; height:100%; object-fit:cover; }
    .file-preview-item .thumb .video-badge { position:absolute; bottom:1px; right:1px; background:rgba(0,0,0,0.7); color:#fff; font-size:7px; padding:1px 3px; border-radius:2px; }
    .file-preview-item .file-info { overflow:hidden; }
    .file-preview-item .file-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:130px; }
    .file-preview-item .file-size { font-size:10px; color:var(--text-muted); }
    .file-preview-item .btn-remove { position:absolute; top:-6px; right:-6px; width:18px; height:18px; border-radius:50%; background:var(--red); color:#fff; border:none; font-size:11px; line-height:18px; text-align:center; cursor:pointer; padding:0; }

    /* 썸네일 그리드 (업로드된 파일) */
    .doc-grid { display:flex; flex-wrap:wrap; gap:12px; }
    .doc-thumb-card { position:relative; width:120px; cursor:pointer; }
    .doc-thumb-card .thumb-img { width:120px; height:120px; border-radius:8px; background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; overflow:hidden; font-size:11px; color:var(--text-muted); font-weight:600; transition:border-color 0.15s; }
    .doc-thumb-card:hover .thumb-img { border-color:var(--accent); }
    .doc-thumb-card .thumb-img img, .doc-thumb-card .thumb-img video { width:100%; height:100%; object-fit:cover; }
    .doc-thumb-card .thumb-img .video-play { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:28px; height:28px; background:rgba(0,0,0,0.6); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; pointer-events:none; }
    .doc-thumb-meta { margin-top:6px; }
    .doc-thumb-meta .thumb-name { font-size:11px; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .doc-thumb-meta .thumb-note { font-size:10px; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:1px; }
    .doc-thumb-meta .thumb-date { font-size:9px; color:var(--text-muted); margin-top:1px; }
    .doc-thumb-card .thumb-actions { position:absolute; top:4px; right:4px; display:none; gap:3px; }
    .doc-thumb-card:hover .thumb-actions { display:flex; }
    .thumb-actions a, .thumb-actions button { width:22px; height:22px; border-radius:4px; background:rgba(0,0,0,0.65); border:none; color:#fff; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; text-decoration:none; }
    .thumb-actions a:hover, .thumb-actions button:hover { background:rgba(0,0,0,0.85); }

    /* 앨범 모달 */
    .album-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:300; align-items:center; justify-content:center; backdrop-filter:blur(6px); }
    .album-overlay.open { display:flex; }
    .album-inner { position:relative; max-width:90vw; max-height:90vh; display:flex; flex-direction:column; align-items:center; }
    .album-media { max-width:85vw; max-height:75vh; border-radius:12px; object-fit:contain; background:#000; user-select:none; }
    .album-media-wrap.zoomed .album-media { max-width:none; max-height:none; }
    .album-media-wrap { display:flex; align-items:center; justify-content:center; min-height:200px; }
    .album-info { color:#fff; font-size:13px; margin-top:10px; text-align:center; }
    .album-info .album-name { font-weight:600; }
    .album-info .album-note { font-size:11px; color:rgba(255,255,255,0.5); margin-top:2px; }
    .album-nav { position:fixed; top:50%; transform:translateY(-50%); width:80px; height:200px; background:none; border:none; color:#fff; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:302; }
    .album-nav .nav-circle { width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center; transition:background 0.15s; }
    .album-nav:hover .nav-circle { background:rgba(255,255,255,0.3); }
    .album-nav.prev { left:0; }
    .album-nav.next { right:0; }
    .album-close { position:fixed; top:20px; right:20px; background:none; border:none; color:#fff; font-size:28px; cursor:pointer; z-index:303; }
    .album-counter { font-size:11px; color:rgba(255,255,255,0.4); margin-top:4px; }
    .album-zoom-controls { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); display:flex; gap:8px; z-index:303; }
    .album-zoom-controls button { width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .album-zoom-controls button:hover { background:rgba(255,255,255,0.3); }
    .album-media-wrap img.album-media { transition:transform 0.2s; cursor:grab; }
    .album-media-wrap img.album-media.dragging { cursor:grabbing; transition:none; }

    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:500px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .modal-title { font-size:16px; font-weight:700; }
    .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
    .field-group { margin-bottom:14px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-select { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .field-textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; resize:vertical; }
    .field-textarea:focus { border-color:var(--accent); }
    .check-row { display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; }
    .check-row input { accent-color:var(--accent); width:15px; height:15px; cursor:pointer; }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .step-dot.done { color:#fff; }
    [data-theme="light"] .btn-upload { color:#fff; }
    [data-theme="light"] .btn-primary { color:#fff; }
    [data-theme="light"] .btn-save { color:#fff; }
    [data-theme="light"] .badge-visit   { background:#e8f5e8; color:#1a7a2a; }
    [data-theme="light"] .badge-remote  { background:#e0f0ff; color:#1a5a8a; }
    [data-theme="light"] .badge-as      { background:#ffe8e8; color:#a03030; }
    [data-theme="light"] .result-in_progress { background:#fff3e0; color:#a06800; }
    [data-theme="light"] .result-waiting     { background:#e0f0ff; color:#2e6a9a; }
    [data-theme="light"] .result-valid       { background:#e8f5e8; color:#248a38; }
    [data-theme="light"] .result-invalid     { background:#ffe8e8; color:#c03838; }
    [data-theme="light"] .result-done        { background:#e8eaef; color:#5a6070; }

    /* 동적 필드 (관리자 정의) */
    .pcf-section { display:flex; flex-direction:column; gap:10px; }
    .pcf-sec-title { font-size:11px; font-weight:600; color:var(--text-muted); letter-spacing:0.06em; padding-bottom:4px; border-bottom:1px solid var(--border); margin-bottom:4px; grid-column:1 / -1; display:flex; align-items:center; gap:6px; cursor:pointer; user-select:none; transition:color 0.12s; }
    .pcf-sec-title:hover { color:var(--text); }
    .pcf-sec-toggle { display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px; font-size:10px; color:var(--text-muted); transition:transform 0.2s; }
    .pcf-section.collapsed .pcf-sec-toggle { transform:rotate(-90deg); }
    .pcf-section.collapsed > :not(.pcf-sec-title) { display:none !important; }
    .pcf-subgroup { background:var(--surface2); border:1px solid var(--border); border-left:3px solid var(--accent); border-radius:8px; padding:12px 14px; display:flex; flex-direction:column; gap:10px; }
    .pcf-sub-title { font-size:11px; font-weight:700; color:var(--accent); letter-spacing:0.06em; text-transform:uppercase; display:flex; align-items:center; gap:6px; }
    .pcf-sub-title .pcf-sub-icon { font-size:14px; }
    .pcf-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px 16px; }
    .pcf-grid .pcf-field { grid-column:span 2; min-width:0; }
    .pcf-grid .pcf-field.w-1 { grid-column:span 1; }
    .pcf-grid .pcf-field.w-2 { grid-column:span 2; }
    .pcf-grid .pcf-field.w-3 { grid-column:span 3; }
    .pcf-grid .pcf-field.w-4 { grid-column:1 / -1; }
    .pcf-grid .pcf-field.full { grid-column:1 / -1; }
    .pcf-field { display:flex; flex-direction:column; gap:4px; }
    @media (max-width:900px) {
        .pcf-grid { grid-template-columns:repeat(2, 1fr); }
        .pcf-grid .pcf-field.w-1, .pcf-grid .pcf-field.w-2 { grid-column:span 1; }
        .pcf-grid .pcf-field.w-3, .pcf-grid .pcf-field.w-4 { grid-column:1 / -1; }
    }
    @media (max-width:560px) {
        .pcf-grid { grid-template-columns:1fr; }
        .pcf-grid .pcf-field, .pcf-grid .pcf-field.w-1, .pcf-grid .pcf-field.w-2, .pcf-grid .pcf-field.w-3, .pcf-grid .pcf-field.w-4 { grid-column:1 / -1; }
    }
    .pcf-label { font-size:11px; color:var(--text-muted); font-weight:600; }
    .pcf-help { font-size:10px; color:var(--text-muted); opacity:0.7; }
    .pcf-input { background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:7px 10px; color:var(--text); font-size:13px; outline:none; font-family:inherit; box-sizing:border-box; }
    .pcf-input:focus { border-color:var(--accent); }
    .pcf-radios { display:flex; gap:10px; flex-wrap:wrap; font-size:13px; }
    .pcf-radios label { display:inline-flex; align-items:center; gap:4px; cursor:pointer; }
    [data-theme="light"] .pcf-input { background:#fff; border-color:#b8bcc8; }
</style>
@endpush

@section('content')
@php
    $projectDocs = $project->documents->sortByDesc('created_at')->values()->map(fn($d) => [
        'name' => $d->file_name,
        'note' => $d->note,
        'mime' => $d->mime_type,
        'url'  => route('project-documents.serve', $d),
    ]);
@endphp
<div class="page-wrap">

    @if(session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route('clients.index', ['open' => $project->client->id]) }}" class="back-btn" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openClientDetail({{ $project->client->id }}); else window.location.href=this.href;">← {{ $project->client->name }}</a>
            <div>
                <div class="project-name" id="projectNameDisplay" onclick="enableProjectNameEdit()" style="cursor:pointer;" title="클릭하여 수정">{{ $project->name }}</div>
                <input id="projectNameEdit" type="text" value="{{ $project->name }}" style="display:none;font-size:22px;font-weight:600;background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:6px 10px;color:var(--text);width:100%;outline:none;" onblur="saveProjectName()" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}if(event.key==='Escape'){this.value='{{ addslashes($project->name) }}';this.blur();}">
                @php
                    // 동적 라벨 매핑 — 관리자 정의(consultation_types/work_types)에서 라벨 우선, 폴백은 하드코딩 기본 셋
                    $ptDefaults = ['visit'=>'방문세팅','remote'=>'원격세팅','design'=>'디자인','inquiry'=>'단순문의','as'=>'A/S','troubleshoot'=>'문제 해결'];
                    $ptLabelMap = \App\Models\ConsultationType::pluck('label', 'key')->toArray();
                    $projectTypeLabel = $ptLabelMap[$project->project_type] ?? ($ptDefaults[$project->project_type] ?? $project->project_type);

                    $scaleL = ['personal'=>'개인','studio'=>'스튜디오','corporate'=>'기업','rental'=>'렌탈','broadcast_room'=>'방송룸'];
                    $workDefaults = ['setup'=>'세팅','remote'=>'원격','survey'=>'답사','filming'=>'촬영중계','design'=>'디자인','as'=>'A/S','dispatch'=>'파견','monthly'=>'월 계약','hourly'=>'시간 대여'];
                    $workLabelMap = class_exists(\App\Models\WorkType::class) ? \App\Models\WorkType::pluck('label', 'key')->toArray() : [];
                    $workTypeLabel = $project->work_type ? ($workLabelMap[$project->work_type] ?? ($workDefaults[$project->work_type] ?? $project->work_type)) : null;
                @endphp
                <div class="project-meta">
                    <span class="badge badge-{{ $project->project_type }}">
                        {{ $projectTypeLabel }}
                    </span>
                    @if($project->client_scale)
                        <span style="font-size:11px;padding:3px 8px;border-radius:4px;background:var(--surface2);color:var(--accent);border:1px solid var(--border);cursor:pointer;" onclick="openScaleEditor()" title="규모/작업유형 수정">
                            {{ $scaleL[$project->client_scale] ?? $project->client_scale }}
                            @if($workTypeLabel) · {{ $workTypeLabel }} @endif
                        </span>
                    @elseif($workTypeLabel)
                        <span style="font-size:11px;padding:3px 8px;border-radius:4px;background:var(--surface2);color:var(--accent);border:1px solid var(--border);cursor:pointer;" onclick="openScaleEditor()" title="규모/작업유형 수정">
                            {{ $workTypeLabel }}
                        </span>
                    @else
                        <span style="font-size:11px;padding:3px 8px;border-radius:4px;background:var(--surface2);color:var(--text-muted);cursor:pointer;border:1px dashed var(--border);" onclick="openScaleEditor()">+ 규모 지정</span>
                    @endif
                    <span>{{ $project->created_at->format('Y.m.d') }} 시작</span>
                    <span>담당: {{ $project->assignedUser?->display_name ?? '-' }}</span>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn-edit" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openActivityLog('Project',{{ $project->id }},'프로젝트 {{ $project->name }} 수정 로그')">📋 로그</button>
            <button class="btn-edit" style="background:none;border:1px solid var(--accent);color:var(--accent);padding:8px 14px;border-radius:8px;font-size:12px;cursor:pointer;font-weight:600;" onclick="openProjectEditModal()">✏️ 프로젝트 수정</button>
            @if($project->stage !== 'cancelled')
                <button class="btn-edit" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openCancelModal()" title="프로젝트 취소 (데이터 보존)">취소</button>
            @endif
            <button class="btn-edit" style="background:none;border:1px solid var(--red);color:var(--red);padding:8px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="deleteProject()" title="완전 삭제">삭제</button>
        </div>
    </div>

    <!-- 프로세스 바 (프로젝트 유형별 단계 세트) -->
    @php
        // project_type 별 단계 시퀀스. DB stage enum은 공유하되 라벨/포함 여부만 분기.
        $stageSets = [
            'visit' => [
                'consulting' => '상담',
                'equipment'  => '장비파악',
                'proposal'   => '일정제안',
                'estimate'   => '견적/계약',
                'payment'    => '결제/예약',
                'visit'      => '방문 세팅',
                'done'       => '완료',
            ],
            'remote' => [
                'consulting' => '상담',
                'equipment'  => '장비파악',
                'proposal'   => '일정제안',
                'estimate'   => '견적/계약',
                'payment'    => '결제/예약',
                'visit'      => '원격 세팅',
                'done'       => '완료',
            ],
            'design' => [
                'consulting' => '상담',
                'estimate'   => '견적/계약',
                'payment'    => '결제',
                'visit'      => '디자인 작업',
                'done'       => '납품 완료',
            ],
            'inquiry' => [
                'consulting' => '문의 접수',
                'visit'      => '처리 중',
                'done'       => '상담 완료',
            ],
            'product_inquiry' => [
                'consulting' => '상담 접수',
                'estimate'   => '견적서 전달',
                'payment'    => '결제 대기',
                'done'       => '결제완료',
            ],
            'as' => [
                'consulting' => 'AS 접수',
                'equipment'  => '점검',
                'visit'      => 'AS 진행',
                'done'       => '처리 완료',
            ],
            'troubleshoot' => [
                'consulting' => '문의 접수',
                'equipment'  => '진단',
                'visit'      => '조치 진행',
                'done'       => '해결 완료',
            ],
        ];
        // '상품 문의' 라벨은 항상 product_inquiry 시퀀스를 사용 (다른 key를 쓰는 경우 폴백)
        $ptLabel = optional(\App\Models\ConsultationType::where('key', $project->project_type)->first())->label ?? '';
        if (in_array($ptLabel, ['상품 문의', '상품문의'], true) && isset($stageSets['product_inquiry'])) {
            $stages = $stageSets['product_inquiry'];
        } else {
            $stages = $stageSets[$project->project_type] ?? $stageSets['visit'];
        }
        $stageKeys = array_keys($stages);
        $currentIdx = array_search($project->stage, $stageKeys);
        if ($currentIdx === false) {
            $currentIdx = -1;
        }
    @endphp

    @php
        // 단계별 전용 모달 매핑 — 단계 클릭 시 form submit 대신 해당 JS 함수 호출
        // 장비파악(equipment)은 모달 없이 카드에서 인라인 직접 편집
        // 'visit' 단계는 모달 없이 단순 단계 전환만 (방문 보고서는 '완료' 후 작성)
        $stageModals = [
            'proposal' => 'openProposalModal',
            'estimate' => 'openEstimateInfoModal',
            'payment' => 'openPaymentModal',
            'done' => 'confirmDoneStage',
        ];
    @endphp
    @php
        $hasReport = ! empty(trim(strip_tags($project->visit_report ?? '')));
    @endphp

    <div class="process-wrap">
        <div class="process-title">진행 단계 — 클릭하여 변경 (단계별 상세 입력 가능)</div>
        <div class="process-steps">
            @foreach($stages as $key => $label)
            @php
                $idx = array_search($key, $stageKeys);
                $modalFn = $stageModals[$key] ?? null;
            @endphp
            <div class="process-step">
                @if($modalFn)
                    <button type="button" class="step-dot {{ $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'active' : '') }}" title="{{ $label }} — 상세 입력" onclick="{{ $modalFn }}()">
                        {{ $idx < $currentIdx ? '✓' : $idx + 1 }}
                    </button>
                @else
                    <form method="POST" action="{{ route('projects.stage', $project) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="stage" value="{{ $key }}">
                        <button type="submit" class="step-dot {{ $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'active' : '') }}" title="{{ $label }}">
                            {{ $idx < $currentIdx ? '✓' : $idx + 1 }}
                        </button>
                    </form>
                @endif
                <div class="step-label {{ $idx === $currentIdx ? 'active' : '' }}">{{ $label }}</div>
            </div>
            @endforeach
        </div>
    </div>

    @if($project->stage === 'cancelled' && $project->cancel_reason)
    <div style="background:rgba(200,80,80,0.1);border:1px solid rgba(200,80,80,0.3);border-radius:12px;padding:16px 20px;margin-bottom:16px;font-size:13px;">
        <div style="font-weight:700;color:var(--red);margin-bottom:6px;">⛔ 취소 사유</div>
        <div style="color:var(--text);">{{ $project->cancel_reason }}</div>
        @if($project->cancel_detail)
            <div style="color:var(--text-muted);margin-top:4px;">{{ $project->cancel_detail }}</div>
        @endif
        @if($project->cancelled_at)
            <div style="color:var(--text-muted);font-size:11px;margin-top:6px;">취소일: {{ $project->cancelled_at->format('Y.m.d H:i') }}</div>
        @endif
    </div>
    @endif

    <div class="info-grid">
        <div class="info-card">
            <div class="card-title">의뢰자 정보</div>
            <div class="info-row">
                <div class="info-label">이름</div>
                <div>
                    <a href="{{ route('clients.index', ['open' => $project->client->id]) }}" style="color:var(--accent); text-decoration:none;" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openClientDetail({{ $project->client->id }}); else window.location.href=this.href;">
                        {{ $project->client->name }}
                    </a>
                    @if($project->client->nickname)
                        <span style="color:var(--text-muted); font-size:12px;"> ({{ $project->client->nickname }})</span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">연락처</div>
                <div>{{ $project->client->phone ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">주소</div>
                <div>{{ $project->client->address ?? '-' }}</div>
            </div>
            @if($project->client->important_memo)
            <div class="important-memo-box" style="margin-top:10px; border-radius:6px; padding:8px 12px; font-size:12px;">
                ⚠ {{ $project->client->important_memo }}
            </div>
            @endif
        </div>

        <div class="info-card">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>프로젝트 개요</span>
                <button onclick="toggleMemoEdit()" id="memoEditBtn" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:3px 10px;border-radius:6px;font-size:11px;cursor:pointer;">수정</button>
            </div>
            <div id="memoDisplay" style="font-size:13px; color:{{ $project->overview ? 'var(--text)' : 'var(--text-muted)' }}; white-space:pre-wrap; text-align:left; padding:4px 0;">{{ $project->overview ?: '프로젝트 개요가 없습니다.' }}</div>
            <textarea id="memoEdit" placeholder="프로젝트 개요를 입력하세요" style="display:none;width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:8px 10px;color:var(--text);font-size:13px;outline:none;resize:vertical;min-height:80px;font-family:inherit;">{{ $project->overview }}</textarea>
        </div>

        @php
            $payment = $project->payment_info ?? null;
            $sdata = $project->stage_data ?? [];
            $proposalData = $sdata['proposal'] ?? null;
            $estimateData = $sdata['estimate'] ?? null;
            $visitData = $sdata['visit'] ?? null;
        @endphp

        {{-- 장비 파악 단계의 인라인 카드는 제거됨. 장비 정보는 '추가 정보' 동적 필드(section=equipment)에서 입력. --}}

        <!-- 일정제안 (연결 캘린더 일정) -->
        @if($proposalData && (!empty($proposalData['schedule_ids']) || !empty($proposalData['note'])))
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>📅 일정 제안</span>
                <button type="button" onclick="openProposalModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:3px 10px;border-radius:6px;font-size:11px;cursor:pointer;">편집</button>
            </div>
            <div id="proposalSummary" data-ids='@json($proposalData['schedule_ids'] ?? [])' style="font-size:13px; color:var(--text-muted);">불러오는 중...</div>
            @if(!empty($proposalData['note']))
                <div style="margin-top:8px; font-size:12px; color:var(--text-muted); white-space:pre-wrap;">📝 {{ $proposalData['note'] }}</div>
            @endif
        </div>
        @endif

        <!-- 견적/계약 (연동 견적서) -->
        @if($estimateData && (!empty($estimateData['estimate_ids']) || !empty($estimateData['note'])))
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>📝 견적/계약</span>
                <button type="button" onclick="openEstimateInfoModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:3px 10px;border-radius:6px;font-size:11px;cursor:pointer;">편집</button>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px; font-size:13px;">
            @foreach(($estimateData['estimate_ids'] ?? []) as $eid)
                <a href="/estimates/{{ $eid }}/edit" style="color:var(--accent); text-decoration:none;">→ 견적서 #{{ $eid }}</a>
            @endforeach
            </div>
            @if(!empty($estimateData['note']))
                <div style="margin-top:8px; font-size:12px; color:var(--text-muted); white-space:pre-wrap;">📝 {{ $estimateData['note'] }}</div>
            @endif
        </div>
        @endif

        <!-- 방문 보고서 -->
        @if($visitData && !empty($visitData['report']))
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>🛠 방문 보고서</span>
                <button type="button" onclick="openVisitReportModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:3px 10px;border-radius:6px;font-size:11px;cursor:pointer;">편집</button>
            </div>
            <div style="font-size:13px; color:var(--text); white-space:pre-wrap; line-height:1.55;">{{ $visitData['report'] }}</div>
        </div>
        @endif

        <!-- 결제 내역 (charge/refund/cancel 트랜잭션) -->
        <div class="info-card full" id="paymentHistoryCard" style="display:none;">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>💰 결제 내역 <span id="phNetTotal" style="font-size:12px; color:var(--text-muted); margin-left:6px;"></span></span>
                <button type="button" onclick="openPaymentModal()" style="background:none;border:1px solid var(--accent);color:var(--accent);padding:3px 10px;border-radius:6px;font-size:11px;cursor:pointer;">+ 결제 추가</button>
            </div>
            <div id="paymentHistoryList" style="display:flex; flex-direction:column; gap:8px;"></div>
        </div>

        <!-- 환불 모달 -->
        <div id="refundModalOverlay" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) drgoModalMinimize(this, '↩ 환불 / 결제 취소', '↩')">
            <div class="modal" style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);">
                    <div style="font-size:15px; font-weight:700;" id="refundModalTitle">↩ 환불</div>
                    <button type="button" onclick="closeRefundModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="padding:18px 20px; display:flex; flex-direction:column; gap:14px;">
                    <div id="refundChargeMeta" style="font-size:12px; color:var(--text-muted); padding:8px 12px; background:var(--surface2); border-radius:8px;"></div>

                    <label style="display:flex; align-items:center; gap:8px; padding:8px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; cursor:pointer; user-select:none;">
                        <input type="checkbox" id="refundManualMode" onchange="toggleRefundManualMode(this.checked)">
                        <span style="font-size:13px; font-weight:600;">환불금액 수기 입력</span>
                        <span style="font-size:11px; color:var(--text-muted); margin-left:auto;">항목과 무관하게 금액 직접 지정</span>
                    </label>

                    <div id="refundItemsWrap">
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">환불할 제품 선택 (체크 + 수량 조정)</div>
                        <div id="refundItemsList" style="display:flex; flex-direction:column; gap:6px;"></div>
                    </div>

                    <div id="refundManualWrap" style="display:none;">
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">환불 금액 (원) <span id="refundManualMax" style="color:var(--text-muted);"></span></div>
                        <input type="number" id="refundManualAmount" placeholder="환불 금액 입력" min="0" oninput="updateRefundPreview()" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:14px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; padding:10px 12px; background:var(--surface2); border-radius:8px;">
                        <span style="font-size:12px; color:var(--text-muted);">환불 금액 (선택 항목 합산):</span>
                        <span id="refundAmountPreview" style="font-size:18px; font-weight:700; color:var(--red); margin-left:auto;">0원</span>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">사유 / 메모</div>
                        <textarea id="refundReason" rows="2" placeholder="환불 사유" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; font-family:inherit; box-sizing:border-box; resize:vertical;"></textarea>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">환불 수단</div>
                        <input type="text" id="refundMethod" placeholder="예: 카드 취소 / 계좌 환불" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; gap:8px; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border);">
                    <button type="button" onclick="confirmFullCancel()" style="background:none;border:1px solid var(--red);color:var(--red);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">⚠ 전체 결제 취소</button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeRefundModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">취소</button>
                        <button type="button" onclick="submitRefund('refund')" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;">선택 항목 환불</button>
                    </div>
                </div>
            </div>
        </div>

        @php
            $sdModalStyle = 'display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center; padding:20px;';
            $sdInnerStyle = 'background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:640px; max-height:90vh; overflow-y:auto;';
            $sdHeadStyle = 'display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);';
            $sdBodyStyle = 'padding:18px 20px; display:flex; flex-direction:column; gap:14px;';
            $sdFootStyle = 'display:flex; gap:8px; justify-content:flex-end; padding:14px 20px; border-top:1px solid var(--border);';
            $sdInputStyle = 'width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; font-family:inherit; box-sizing:border-box;';
            $sdLabelStyle = 'font-size:11px;color:var(--text-muted);margin-bottom:4px;';
        @endphp

        <!-- 일정제안 모달 -->
        <div id="proposalModalOverlay" class="modal-overlay" style="{{ $sdModalStyle }}" onclick="if(event.target===this) drgoModalMinimize(this, '📅 일정 제안', '📅')">
            <div class="modal" style="{{ $sdInnerStyle }}">
                <div style="{{ $sdHeadStyle }}">
                    <div style="font-size:15px; font-weight:700;">📅 일정 제안 · 캘린더 일정 연동</div>
                    <button type="button" onclick="closeProposalModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="{{ $sdBodyStyle }}">
                    <div style="font-size:11px; color:var(--text-muted);">의뢰자 이름과 일치하는 캘린더 일정 후보입니다. 이 프로젝트에 연결할 일정을 체크해 주세요.</div>
                    <div id="proposalSchedulesList" style="display:flex; flex-direction:column; gap:6px; max-height:380px; overflow-y:auto; padding:4px 2px;"></div>
                    <div>
                        <div style="{{ $sdLabelStyle }}">메모</div>
                        <textarea id="proposalNote" rows="2" placeholder="일정 제안 관련 메모" style="{{ $sdInputStyle }} resize:vertical;"></textarea>
                    </div>
                </div>
                <div style="{{ $sdFootStyle }}">
                    <button type="button" onclick="closeProposalModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">취소</button>
                    <button type="button" onclick="saveProposal()" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
                </div>
            </div>
        </div>

        <!-- 견적/계약 모달 -->
        <div id="estimateInfoModalOverlay" class="modal-overlay" style="{{ $sdModalStyle }}" onclick="if(event.target===this) drgoModalMinimize(this, '📝 견적/계약', '📝')">
            <div class="modal" style="{{ $sdInnerStyle }}">
                <div style="{{ $sdHeadStyle }}">
                    <div style="font-size:15px; font-weight:700;">📝 견적/계약 연동</div>
                    <button type="button" onclick="closeEstimateInfoModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="{{ $sdBodyStyle }}">
                    <div style="font-size:11px; color:var(--text-muted);">이 의뢰자의 견적서 목록입니다. 이 프로젝트에 연결할 견적서를 체크해 주세요.</div>
                    <div id="estimateInfoList" style="display:flex; flex-direction:column; gap:6px; max-height:380px; overflow-y:auto; padding:4px 2px;"></div>
                    <div>
                        <div style="{{ $sdLabelStyle }}">메모</div>
                        <textarea id="estimateInfoNote" rows="2" placeholder="견적/계약 관련 메모" style="{{ $sdInputStyle }} resize:vertical;"></textarea>
                    </div>
                </div>
                <div style="{{ $sdFootStyle }}">
                    <button type="button" onclick="closeEstimateInfoModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">취소</button>
                    <button type="button" onclick="saveEstimateInfo()" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
                </div>
            </div>
        </div>

        <!-- 방문 보고서 모달 -->
        <div id="visitReportModalOverlay" class="modal-overlay" style="{{ $sdModalStyle }}" onclick="if(event.target===this) drgoModalMinimize(this, '🛠 방문 보고서', '🛠')">
            <div class="modal" style="{{ $sdInnerStyle }} max-width:820px;">
                <div style="{{ $sdHeadStyle }}">
                    <div style="font-size:15px; font-weight:700;">🛠 방문 보고서</div>
                    <button type="button" onclick="closeVisitReportModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="{{ $sdBodyStyle }}">
                    <div style="font-size:11px; color:var(--text-muted);">방문 현장 상황, 진행한 작업, 특이사항 등을 자유롭게 작성합니다.</div>
                    <textarea id="visitReportText" rows="14" placeholder="예:&#10;• 방문 일시: 2026-05-13 14:00&#10;• 동행: 김광래, 황진선&#10;• 작업 내역: 카메라 설치, 음향 세팅 등&#10;• 특이사항: …" style="{{ $sdInputStyle }} resize:vertical; line-height:1.6; min-height:300px;"></textarea>
                </div>
                <div style="{{ $sdFootStyle }}">
                    <button type="button" onclick="closeVisitReportModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">취소</button>
                    <button type="button" onclick="saveVisitReport()" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
                </div>
            </div>
        </div>

        <!-- 프로젝트 수정 모달 -->
        <div id="projectEditModalOverlay" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) drgoModalMinimize(this, '✏️ 프로젝트 수정', '✏️')">
            <div class="modal" style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);">
                    <div style="font-size:15px; font-weight:700;">✏️ 프로젝트 수정</div>
                    <button type="button" onclick="closeProjectEditModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="padding:18px 20px; display:flex; flex-direction:column; gap:14px;">
                    <div>
                        <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">프로젝트명 *</div>
                        <input type="text" id="peName" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">프로젝트 유형</div>
                        <select id="peProjectType" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;"></select>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">규모</div>
                            <select id="peScale" onchange="updatePeWorkType()" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                                <option value="">선택</option>
                                <option value="personal">개인</option>
                                <option value="studio">스튜디오</option>
                                <option value="corporate">기업</option>
                                <option value="rental">렌탈</option>
                                <option value="broadcast_room">방송룸</option>
                            </select>
                        </div>
                        <div>
                            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">작업 유형</div>
                            <select id="peWorkType" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;"></select>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end; padding:14px 20px; border-top:1px solid var(--border);">
                    <button type="button" onclick="closeProjectEditModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">취소</button>
                    <button type="button" onclick="saveProjectEdit()" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
                </div>
            </div>
        </div>

        <!-- 결제 정보 모달 -->
        <div id="paymentModalOverlay" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) drgoModalMinimize(this, '💰 결제 정보 입력', '💰')">
            <div class="modal" style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:620px; max-height:90vh; overflow-y:auto;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);">
                    <div style="font-size:15px; font-weight:700;">💰 결제 정보 입력</div>
                    <button type="button" onclick="closePaymentModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="padding:18px 20px; display:flex; flex-direction:column; gap:14px;">

                    <div>
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">견적서 연결</div>
                        <select id="payEstimateId" onchange="onSelectEstimate()" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; font-family:inherit;">
                            <option value="">— 견적서 미연결 (수기 입력) —</option>
                        </select>
                        <div id="payEstimateInfo" style="font-size:11px; color:var(--text-muted); margin-top:4px; min-height:14px;"></div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">결제 금액 (원) *</div>
                            <input type="number" id="payAmount" min="0" value="{{ $payment['amount'] ?? '' }}" oninput="window.payAmountManual=true; recalcPayAmount();" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                                <span id="payAmountNote" style="font-size:10px; color:var(--text-muted);">(항목 합산 자동 반영)</span>
                                <button type="button" onclick="window.payAmountManual=false; recalcPayAmount();" style="background:none; border:none; color:var(--accent); font-size:10px; cursor:pointer; padding:0;">↻ 항목 합산으로 재설정</button>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">결제일</div>
                            <input type="date" id="payPaidAt" value="{{ $payment['paid_at'] ?? date('Y-m-d') }}" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none;">
                        </div>
                    </div>

                    <div>
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">결제 수단</div>
                        <select id="payMethod" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; font-family:inherit;">
                            <option value="">선택...</option>
                            <option value="카드">카드</option>
                            <option value="현금">현금</option>
                            <option value="계좌이체">계좌이체</option>
                            <option value="기타">기타</option>
                        </select>
                    </div>

                    <div>
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px; display:flex; justify-content:space-between; align-items:center;">
                            <span>결제 항목 (수기 또는 견적서에서 자동 채움)</span>
                            <button type="button" onclick="addPayItem()" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:2px 8px; border-radius:5px; font-size:11px; cursor:pointer;">+ 항목</button>
                        </div>
                        <div id="payItemsWrap" style="display:flex; flex-direction:column; gap:6px;"></div>
                    </div>

                    <div style="display:grid; grid-template-columns:auto 1fr; gap:10px; align-items:end;">
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">잔금 여부</div>
                            <div style="display:flex; gap:6px;">
                                <label style="display:flex; align-items:center; gap:4px; font-size:13px; cursor:pointer;">
                                    <input type="radio" name="payHasBalance" value="1" onchange="togglePayBalance()"> O
                                </label>
                                <label style="display:flex; align-items:center; gap:4px; font-size:13px; cursor:pointer;">
                                    <input type="radio" name="payHasBalance" value="0" checked onchange="togglePayBalance()"> X
                                </label>
                            </div>
                        </div>
                        <div id="payBalanceAmountWrap" style="display:none;">
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">잔금 금액 (원)</div>
                            <input type="number" id="payBalanceAmount" min="0" value="{{ $payment['balance_amount'] ?? '' }}" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none;">
                        </div>
                    </div>

                    <div>
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">메모</div>
                        <textarea id="payMemo" rows="2" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; resize:vertical; font-family:inherit; box-sizing:border-box;">{{ $payment['memo'] ?? '' }}</textarea>
                    </div>

                    <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); cursor:pointer;">
                        <input type="checkbox" id="payMarkPaid" checked> 연결한 견적서의 상태를 '결제됨'으로 표시
                    </label>
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end; padding:14px 20px; border-top:1px solid var(--border);">
                    <button type="button" class="btn-cancel" onclick="closePaymentModal()" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 16px; border-radius:7px; font-size:13px; cursor:pointer;">취소</button>
                    <button type="button" class="btn-save" onclick="savePayment()" style="background:var(--accent); color:var(--accent-text); border:none; padding:8px 18px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer;">저장</button>
                </div>
            </div>
        </div>

        {{-- 방문 보고서 (stage=done 일 때만 노출 — 추가 정보 영역 상단) --}}
        <div class="info-card full {{ $hasReport ? 'is-collapsed' : '' }}" id="visitReportCard" style="display:{{ $project->stage === 'done' ? 'block' : 'none' }};" data-mode="{{ $hasReport ? 'view' : 'edit' }}">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span>📋 방문 보고서</span>
                <div style="display:flex; gap:6px; align-items:center;">
                    <span id="vrSaveStatus" style="font-size:11px; color:var(--text-muted);"></span>
                    <button type="button" id="vrBtnEdit" onclick="vrSetMode('edit')" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 14px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; display:{{ $hasReport ? 'inline-flex' : 'none' }};">✏️ 수정</button>
                    <button type="button" id="vrBtnCancel" onclick="vrSetMode('view')" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 14px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; display:none;">취소</button>
                    <button type="button" id="vrBtnSave" onclick="saveVisitReportEditor()" style="background:var(--accent); color:var(--accent-text); border:none; padding:6px 14px; border-radius:7px; font-size:12px; font-weight:700; cursor:pointer; display:{{ $hasReport ? 'none' : 'inline-flex' }};">저장</button>
                </div>
            </div>
            <div class="tiptap-wrap">
                <div class="tiptap-toolbar" id="vrToolbar">
                    <button type="button" data-cmd="h1" title="제목 1">H1</button>
                    <button type="button" data-cmd="h2" title="제목 2">H2</button>
                    <button type="button" data-cmd="h3" title="제목 3">H3</button>
                    <div class="sep"></div>
                    <button type="button" data-cmd="bold" title="굵게"><b>B</b></button>
                    <button type="button" data-cmd="italic" title="기울임"><i>I</i></button>
                    <button type="button" data-cmd="strike" title="취소선"><s>S</s></button>
                    <button type="button" data-cmd="code" title="인라인 코드">&lt;&gt;</button>
                    <div class="sep"></div>
                    <button type="button" data-cmd="bulletList" title="글머리 목록">•</button>
                    <button type="button" data-cmd="orderedList" title="번호 목록">1.</button>
                    <button type="button" data-cmd="blockquote" title="인용">"</button>
                    <button type="button" data-cmd="codeBlock" title="코드 블록">{ }</button>
                    <button type="button" data-cmd="hr" title="구분선">—</button>
                    <div class="sep"></div>
                    <button type="button" data-cmd="alignLeft" title="좌측 정렬" style="font-size:11px;">≡←</button>
                    <button type="button" data-cmd="alignCenter" title="중앙 정렬" style="font-size:11px;">≡</button>
                    <button type="button" data-cmd="alignRight" title="우측 정렬" style="font-size:11px;">→≡</button>
                    <div class="sep"></div>
                    <label class="tool-btn" title="이미지/파일 첨부" style="cursor:pointer;color:var(--text-muted);font-size:13px;">
                        📎
                        <input type="file" style="display:none;" onchange="vrUploadAndInsert(this.files[0]); this.value='';">
                    </label>
                    <button type="button" data-cmd="table" class="tool-btn" title="표 삽입" style="font-size:11px;">📊 표</button>
                    <div class="sep"></div>
                    <button type="button" class="tool-btn" title="템플릿 불러오기" onclick="vrOpenTemplatePicker()" style="font-size:11px;">📋 템플릿</button>
                </div>
                <div id="vrEditor"></div>
            </div>
            <button type="button" class="vr-expand-btn" id="vrExpandBtn" onclick="vrToggleExpand()">▼ 더 보기</button>
        </div>
        <div class="slash-menu" id="vrSlashMenu"></div>

        <!-- 추가 정보 (관리자 정의 동적 필드) -->
        <div class="info-card full" id="customDataCard" style="display:none;">
            <div class="card-title">추가 정보</div>
            <div id="projectCustomFields" style="display:flex; flex-direction:column; gap:14px;"></div>
            <div class="text-muted" id="pcfSaveStatus" style="font-size:11px; color:var(--text-muted); margin-top:6px; min-height:14px;"></div>
        </div>

        <!-- 상담 이력 -->
        @php $consultations = $project->consultations->load('authorUser', 'consultant'); @endphp
        <div class="info-card full">
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:center; gap:8px; flex-wrap:wrap;">
                <span>상담 이력 ({{ $consultations->count() }}건)</span>
                <div style="display:flex; gap:8px; align-items:center;">
                    <select id="consultSortSelect" onchange="sortConsultations(this.value)" style="background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:4px 10px;color:var(--text);font-size:12px;cursor:pointer;">
                        <option value="desc">최근 순</option>
                        <option value="asc">오래된 순</option>
                    </select>
                    <button class="btn-primary" onclick="openConsultModal()" style="padding:6px 14px; font-size:12px;">+ 상담 등록</button>
                </div>
            </div>
            @if($consultations->count() > 0)
                <div class="consult-list" id="consultList">
                    @foreach($consultations->sortBy([['consulted_at', 'desc'], ['created_at', 'desc']]) as $consult)
                    <div class="consult-item {{ $consult->is_important ? 'important' : '' }}" data-date="{{ $consult->consulted_at->format('Y-m-d') }}" data-created="{{ $consult->created_at->format('Y-m-d H:i:s') }}" data-id="{{ $consult->id }}">
                        <div class="consult-header">
                            <div class="consult-meta">
                                @if($consult->is_important)
                                    <span class="important-mark">⭐</span>
                                @endif
                                <span class="consult-date">{{ $consult->consulted_at->format('Y-m-d') }}</span>
                                <span class="consult-type-badge">
                                    {{ ['kakao'=>'카카오톡','phone'=>'전화','visit'=>'내방상담','field'=>'현장답사'][$consult->consult_type] ?? $consult->consult_type }}
                                </span>
                                <span class="consult-result-badge result-{{ $consult->result }}">
                                    {{ ['in_progress'=>'진행중','waiting'=>'대기','valid'=>'유효','invalid'=>'무효','done'=>'완료'][$consult->result] }}
                                </span>
                            </div>
                            <div class="consult-actions">
                                <button class="btn-edit-sm" onclick="openEditModal({{ $consult->id }}, '{{ $consult->consulted_at->format('Y-m-d') }}', '{{ $consult->consult_type }}', '{{ $consult->result }}', {{ $consult->is_important ? 'true' : 'false' }}, @js($consult->content), @js($consult->manager_name))">수정</button>
                                <form method="POST" action="{{ route('consultations.destroy', $consult) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-del" onclick="return confirm('삭제할까요?')">삭제</button>
                                </form>
                            </div>
                        </div>
                        @if($consult->content)
                            <div class="consult-content">{{ $consult->content }}</div>
                        @endif
                        <div class="consult-footer">
                            <span class="consult-author">
                                작성자: {{ $consult->authorUser?->display_name ?? $consult->consultant?->display_name ?? '-' }}
                                @if($consult->manager_name)
                                    · 담당자: {{ $consult->manager_name }}
                                @endif
                            </span>
                            <span class="consult-date" title="작성 일시">📝 {{ $consult->created_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="empty">상담 이력이 없습니다.</div>
            @endif
        </div>

        <!-- 피드백 -->
        <div class="info-card full">
            <div class="card-title">피드백</div>
            <div style="display:flex; gap:8px; margin-bottom:14px;">
                <textarea id="projectMemoInput" class="field-textarea" rows="2" placeholder="피드백을 입력하세요..." style="flex:1; resize:none;"></textarea>
                <button class="btn-save" onclick="addProjectMemo()" style="align-self:flex-end; white-space:nowrap;">추가</button>
            </div>
            <div id="projectMemoThread">
                @forelse($project->memos as $memo)
                    <div style="display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--border);" id="pmemo-{{ $memo->id }}">
                        <div style="width:30px; height:30px; border-radius:50%; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:var(--accent); flex-shrink:0;">{{ mb_substr($memo->user?->display_name ?? '?', 0, 1) }}</div>
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <span style="font-size:12px; font-weight:600;">{{ $memo->user?->display_name ?? '알 수 없음' }}</span>
                                    <span style="font-size:10px; color:var(--text-muted); margin-left:6px;">{{ $memo->created_at->format('Y.m.d H:i') }}</span>
                                </div>
                                <button onclick="deleteProjectMemo({{ $memo->id }})" style="background:none; border:none; color:var(--text-muted); font-size:10px; cursor:pointer; opacity:0.5;" onmouseover="this.style.opacity=1;this.style.color='var(--red)'" onmouseout="this.style.opacity=0.5;this.style.color='var(--text-muted)'">삭제</button>
                            </div>
                            <div style="font-size:13px; margin-top:4px; white-space:pre-wrap; word-break:break-word;">{{ $memo->content }}</div>
                        </div>
                    </div>
                @empty
                    <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;" id="pmemoEmpty">피드백이 없습니다.</div>
                @endforelse
            </div>
        </div>

        <!-- 첨부 문서 -->
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>첨부 문서 ({{ $project->documents->count() }}건)</span>
                <button type="button" class="btn-primary" onclick="toggleDocUpload()" id="btnToggleDocUpload" style="padding:6px 14px; font-size:12px;">+ 문서 추가</button>
            </div>
            <form method="POST" action="{{ route('project-documents.store', $project) }}" enctype="multipart/form-data" id="docUploadForm" style="display:{{ $project->documents->count() > 0 ? 'none' : 'block' }};">
                @csrf
                <input type="file" id="docFileInput" multiple style="display:none;">
                <input type="file" name="files[]" id="docFileReal" multiple style="display:none;">
                <div class="doc-upload-area">
                    <div class="doc-upload-row">
                        <div>
                            <div class="field-mini">파일 *</div>
                            <button type="button" class="btn-choose" onclick="document.getElementById('docFileInput').click()">파일 선택 (여러 개 가능)</button>
                        </div>
                        <div>
                            <div class="field-mini">카테고리 *</div>
                            <select name="category">
                                <option value="현금영수증">현금영수증</option>
                                <option value="사업자등록증">사업자등록증</option>
                                <option value="계약서">계약서</option>
                                <option value="견적서">견적서</option>
                                <option value="사진/이미지">사진/이미지</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:120px;">
                            <div class="field-mini">메모</div>
                            <input type="text" name="note" placeholder="간단한 메모" style="width:100%;">
                        </div>
                        <button type="submit" class="btn-upload" id="btnUpload" disabled>업로드</button>
                    </div>
                    <div class="file-preview-list" id="filePreviewList"></div>
                </div>
            </form>
            @if($project->documents->count() > 0)
                <div class="doc-grid">
                    @foreach($project->documents->sortByDesc('created_at') as $i => $doc)
                    @php
                        $isImg = str_starts_with($doc->mime_type ?? '', 'image/');
                        $isVid = str_starts_with($doc->mime_type ?? '', 'video/');
                        $isPdf = ($doc->mime_type ?? '') === 'application/pdf';
                        $ext = strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION));
                    @endphp
                    <div class="doc-thumb-card" onclick="openAlbum({{ $i }})">
                        <div class="thumb-img">
                            @if($isImg)
                                <img src="{{ route('project-documents.serve', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy">
                            @elseif($isVid)
                                <video src="{{ route('project-documents.serve', $doc) }}" preload="metadata" muted></video>
                                <div class="video-play">▶</div>
                            @else
                                {{ $isPdf ? 'PDF' : $ext }}
                            @endif
                        </div>
                        <div class="thumb-actions" onclick="event.stopPropagation()">
                            <a href="{{ route('project-documents.download', $doc) }}" title="다운로드">↓</a>
                            <form method="POST" action="{{ route('project-documents.destroy', $doc) }}" style="display:contents;">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('삭제할까요?')" title="삭제">×</button>
                            </form>
                        </div>
                        <div class="doc-thumb-meta">
                            <div class="thumb-name" title="{{ $doc->file_name }}">{{ $doc->file_name }}</div>
                            @if($doc->note)
                                <div class="thumb-note" title="{{ $doc->note }}">{{ $doc->note }}</div>
                            @endif
                            <div class="thumb-date">{{ $doc->created_at->format('Y.m.d') }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="empty">등록된 문서가 없습니다.</div>
            @endif
        </div>
    </div>
</div>

<!-- 상담 등록 모달 -->
<div class="modal-overlay" id="consultModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">상담 등록</div>
            <button class="modal-close" onclick="closeConsultModal()">×</button>
        </div>
        <form method="POST" action="{{ route('consultations.store', $project) }}">
            @csrf
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">상담일 *</div>
                    <input class="field-input" type="date" name="consulted_at" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="field-group">
                    <div class="field-label">상담 유형 *</div>
                    <select class="field-select" name="consult_type">
                        <option value="kakao">카카오톡</option>
                        <option value="phone">전화</option>
                        <option value="visit">내방상담</option>
                        <option value="field">현장답사</option>
                    </select>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">결과 *</div>
                <select class="field-select" name="result">
                    <option value="in_progress">진행중(대화)</option>
                    <option value="waiting">대기</option>
                    <option value="valid">유효</option>
                    <option value="invalid">무효</option>
                    <option value="done">완료</option>
                </select>
            </div>
            <div class="field-group">
                <div class="field-label">담당자 (수기 입력)</div>
                <input class="field-input" type="text" name="manager_name" placeholder="담당자 이름 (선택)">
            </div>
            <div class="field-group">
                <div class="field-label">상담 내용</div>
                <textarea class="field-textarea" name="content" rows="5" placeholder="상담 내용을 입력하세요"></textarea>
            </div>
            <div class="field-group">
                <label class="check-row">
                    <input type="checkbox" name="is_important" value="1">
                    <span>⭐ 중요 상담으로 표시</span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeConsultModal()">취소</button>
                <button type="submit" class="btn-save">등록</button>
            </div>
        </form>
    </div>
</div>

<!-- 앨범 모달 -->
<div class="album-overlay" id="albumOverlay">
    <button class="album-close" onclick="closeAlbum()">×</button>
    <button class="album-nav prev" onclick="albumNav(-1)"><span class="nav-circle">‹</span></button>
    <button class="album-nav next" onclick="albumNav(1)"><span class="nav-circle">›</span></button>
    <div class="album-inner" id="albumInner">
        <div class="album-media-wrap" id="albumMediaWrap"></div>
        <div class="album-info">
            <div class="album-name" id="albumName"></div>
            <div class="album-note" id="albumNote"></div>
            <div class="album-counter" id="albumCounter"></div>
        </div>
    </div>
    <div class="album-zoom-controls" id="albumZoomControls" style="display:none;">
        <button onclick="albumZoom(-1)" title="축소">−</button>
        <span id="albumZoomLevel" style="min-width:48px; text-align:center; color:#fff; font-size:13px; font-weight:600; line-height:36px;">100%</span>
        <button onclick="albumZoom(1)" title="확대">+</button>
        <button onclick="albumZoomReset()" title="원본 크기" style="font-size:11px; width:auto; padding:0 10px; border-radius:18px;">맞춤</button>
    </div>
</div>

<!-- 상담 수정 모달 -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">상담 수정</div>
            <button class="modal-close" onclick="closeEditModal()">×</button>
        </div>
        <form method="POST" id="editForm">
            @csrf @method('PATCH')
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">상담일 *</div>
                    <input class="field-input" type="date" name="consulted_at" id="editDate" required>
                </div>
                <div class="field-group">
                    <div class="field-label">상담 유형 *</div>
                    <select class="field-select" name="consult_type" id="editType">
                        <option value="kakao">카카오톡</option>
                        <option value="phone">전화</option>
                        <option value="visit">내방상담</option>
                        <option value="field">현장답사</option>
                    </select>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">결과 *</div>
                <select class="field-select" name="result" id="editResult">
                    <option value="in_progress">진행중(대화)</option>
                    <option value="waiting">대기</option>
                    <option value="valid">유효</option>
                    <option value="invalid">무효</option>
                    <option value="done">완료</option>
                </select>
            </div>
            <div class="field-group">
                <div class="field-label">담당자 (수기 입력)</div>
                <input class="field-input" type="text" name="manager_name" id="editManagerName" placeholder="담당자 이름 (선택)">
            </div>
            <div class="field-group">
                <div class="field-label">상담 내용</div>
                <textarea class="field-textarea" name="content" id="editContent" rows="5"></textarea>
            </div>
            <div class="field-group">
                <label class="check-row">
                    <input type="checkbox" name="is_important" id="editImportant" value="1">
                    <span>⭐ 중요 상담으로 표시</span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">취소</button>
                <button type="submit" class="btn-save">수정</button>
            </div>
        </form>
    </div>
</div>
{{-- 규모/작업유형 편집 모달 --}}
<div class="modal-overlay" id="scaleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(3px);" onclick="if(event.target===this) drgoModalMinimize(this, '규모/작업유형 수정', '📊')">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:420px;max-width:95vw;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <div style="font-size:16px;font-weight:700;">규모 / 작업 유형</div>
            <button onclick="closeScaleEditor()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
        </div>
        <div style="margin-bottom:14px;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">규모 *</div>
            <select id="editScale" onchange="updateEditWorkTypes()" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);font-size:13px;outline:none;">
                <option value="personal" {{ $project->client_scale === 'personal' ? 'selected' : '' }}>개인</option>
                <option value="studio" {{ $project->client_scale === 'studio' ? 'selected' : '' }}>스튜디오</option>
                <option value="corporate" {{ $project->client_scale === 'corporate' ? 'selected' : '' }}>기업</option>
                <option value="rental" {{ $project->client_scale === 'rental' ? 'selected' : '' }}>렌탈</option>
                <option value="broadcast_room" {{ $project->client_scale === 'broadcast_room' ? 'selected' : '' }}>방송룸</option>
            </select>
        </div>
        <div style="margin-bottom:14px;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">작업 유형 *</div>
            <select id="editWorkType" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);font-size:13px;outline:none;"></select>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="closeScaleEditor()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">취소</button>
            <button onclick="submitScale()" style="background:var(--accent);color:var(--accent-text);border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
        </div>
    </div>
</div>

{{-- 취소 사유 모달 --}}
<div class="modal-overlay" id="cancelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(3px);" onclick="if(event.target===this) drgoModalMinimize(this, '프로젝트 취소', '⚠')">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:440px;max-width:95vw;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <div style="font-size:16px;font-weight:700;">프로젝트 취소</div>
            <button onclick="closeCancelModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">데이터는 보존되며, 단계만 "취소"로 변경됩니다.</p>
        <div style="margin-bottom:14px;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">취소 사유 *</div>
            <div style="display:flex;flex-direction:column;gap:6px;" id="cancelReasons">
                <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;transition:all 0.12s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border)'">
                    <input type="radio" name="cancel_reason" value="no_contact" style="accent-color:var(--accent);"> 의뢰자 연락 두절
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;transition:all 0.12s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border)'">
                    <input type="radio" name="cancel_reason" value="client_request" style="accent-color:var(--accent);"> 의뢰자 사정으로 취소
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;transition:all 0.12s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border)'">
                    <input type="radio" name="cancel_reason" value="schedule_mismatch" style="accent-color:var(--accent);"> 일정이 맞지 않음
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;transition:all 0.12s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border)'">
                    <input type="radio" name="cancel_reason" value="other" style="accent-color:var(--accent);"> 기타
                </label>
            </div>
        </div>
        <div id="cancelDetailWrap" style="display:none;margin-bottom:14px;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">상세 사유</div>
            <textarea id="cancelDetail" rows="3" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);font-size:13px;outline:none;resize:vertical;" placeholder="취소 사유를 입력하세요"></textarea>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="closeCancelModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">닫기</button>
            <button onclick="submitCancel()" style="background:var(--red);color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">취소 처리</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// 상담 모달
function openConsultModal() { document.getElementById('consultModal').classList.add('open'); }

function toggleDocUpload() {
    const form = document.getElementById('docUploadForm');
    const btn = document.getElementById('btnToggleDocUpload');
    if (!form || !btn) return;
    const showing = form.style.display !== 'none';
    form.style.display = showing ? 'none' : 'block';
    btn.textContent = showing ? '+ 문서 추가' : '× 닫기';
    if (!showing) form.querySelector('input[type=file], button')?.focus();
}
function closeConsultModal() { document.getElementById('consultModal').classList.remove('open'); }
function openEditModal(id, date, type, result, isImportant, content, managerName) {
    document.getElementById('editForm').action = `/consultations/${id}`;
    document.getElementById('editDate').value = date;
    document.getElementById('editType').value = type;
    document.getElementById('editResult').value = result;
    document.getElementById('editContent').value = content || '';
    document.getElementById('editManagerName').value = managerName || '';
    document.getElementById('editImportant').checked = isImportant;
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('open'); }

// 상담 정렬
function sortConsultations(order) {
    const list = document.getElementById('consultList');
    if (!list) return;
    const items = Array.from(list.children);
    items.sort((a, b) => {
        // consulted_at(date) 우선, 동일 날짜는 created_at(datetime)으로 정렬
        const keyA = (a.dataset.date || '') + ' ' + (a.dataset.created || '');
        const keyB = (b.dataset.date || '') + ' ' + (b.dataset.created || '');
        return order === 'desc' ? keyB.localeCompare(keyA) : keyA.localeCompare(keyB);
    });
    items.forEach(el => list.appendChild(el));
}

// 부모 탭 타이틀을 프로젝트명으로 갱신 (멀티 탭 식별)
(function setParentTabTitle() {
    try {
        const title = '📁 ' + @json($project->name);
        if (window.parent && window.parent.drgoTabs && typeof window.parent.drgoTabs.setActiveTitle === 'function') {
            window.parent.drgoTabs.setActiveTitle(title);
        }
    } catch(e) {}
})();

// 프로젝트 제목 인라인 수정
function enableProjectNameEdit() {
    const display = document.getElementById('projectNameDisplay');
    const input = document.getElementById('projectNameEdit');
    display.style.display = 'none';
    input.style.display = 'block';
    input.focus();
    input.select();
}
async function saveProjectName() {
    const display = document.getElementById('projectNameDisplay');
    const input = document.getElementById('projectNameEdit');
    const newName = input.value.trim();
    const oldName = display.textContent.trim();
    if (!newName || newName === oldName) {
        input.style.display = 'none';
        display.style.display = '';
        input.value = oldName;
        return;
    }
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(`/api/projects/{{ $project->id }}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ name: newName }),
        });
        if (res.ok) {
            display.textContent = newName;
        } else {
            input.value = oldName;
            alert('수정 실패');
        }
    } catch (e) {
        input.value = oldName;
        alert('수정 오류');
    }
    input.style.display = 'none';
    display.style.display = '';
}

// 프로젝트 메모 인라인 수정
function toggleMemoEdit() {
    const display = document.getElementById('memoDisplay');
    const edit = document.getElementById('memoEdit');
    const btn = document.getElementById('memoEditBtn');
    if (edit.style.display === 'none') {
        display.style.display = 'none';
        edit.style.display = 'block';
        btn.textContent = '저장';
        edit.focus();
    } else {
        saveMemo();
    }
}
async function saveMemo() {
    const display = document.getElementById('memoDisplay');
    const edit = document.getElementById('memoEdit');
    const btn = document.getElementById('memoEditBtn');
    const newMemo = edit.value.trim();
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(`/api/projects/{{ $project->id }}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ overview: newMemo }),
        });
        if (res.ok) {
            display.textContent = newMemo || '프로젝트 개요가 없습니다.';
            display.style.color = newMemo ? 'var(--text)' : 'var(--text-muted)';
        } else {
            alert('저장 실패');
        }
    } catch (e) {
        alert('저장 오류');
    }
    edit.style.display = 'none';
    display.style.display = '';
    btn.textContent = '수정';
}

// ── 프로젝트 수정 모달 ──
const CURRENT_PROJECT = {
    name: @json($project->name),
    project_type: @json($project->project_type),
    client_scale: @json($project->client_scale),
    work_type: @json($project->work_type),
};

async function openProjectEditModal() {
    // 프로젝트 유형 + 작업 유형 마스터 로드
    try {
        const res = await fetch('/api/consultation-types/active', { headers:{ 'Accept':'application/json' } });
        const types = res.ok ? await res.json() : [];
        const sel = document.getElementById('peProjectType');
        if (types.length) {
            sel.innerHTML = types.map(t => `<option value="${t.key}" ${CURRENT_PROJECT.project_type === t.key ? 'selected' : ''}>${t.label}</option>`).join('');
        } else {
            sel.innerHTML = `<option value="${CURRENT_PROJECT.project_type}">${CURRENT_PROJECT.project_type}</option>`;
        }
    } catch(e) {}
    await loadActiveWorkTypes();

    document.getElementById('peName').value = CURRENT_PROJECT.name || '';
    document.getElementById('peScale').value = CURRENT_PROJECT.client_scale || '';
    updatePeWorkType();
    document.getElementById('projectEditModalOverlay').style.display = 'flex';
}
function closeProjectEditModal() { document.getElementById('projectEditModalOverlay').style.display = 'none'; }

function updatePeWorkType() {
    const scale = document.getElementById('peScale').value;
    const opts = WORK_TYPES_FOR(scale);
    const sel = document.getElementById('peWorkType');
    sel.innerHTML = `<option value="">선택</option>` + opts.map(([v,l]) => `<option value="${v}" ${CURRENT_PROJECT.work_type === v ? 'selected' : ''}>${l}</option>`).join('');
}

async function saveProjectEdit() {
    const name = document.getElementById('peName').value.trim();
    if (!name) return alert('프로젝트명을 입력하세요.');

    const body = {
        name,
        project_type: document.getElementById('peProjectType').value,
        client_scale: document.getElementById('peScale').value || null,
        work_type: document.getElementById('peWorkType').value || null,
    };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const res = await fetch(`/api/projects/{{ $project->id }}`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body:JSON.stringify(body),
    });
    if (res.ok) {
        closeProjectEditModal();
        location.reload();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}

// 규모/작업유형 편집 — 작업 유형은 관리자 정의(work_types)에서 동적 로드, 폴백 보존
const WORK_TYPES_FALLBACK = {
    personal: [['setup','세팅'],['remote','원격'],['filming','촬영중계'],['design','디자인'],['as','A/S']],
    studio: [['setup','세팅'],['survey','답사'],['filming','촬영중계'],['design','디자인'],['as','A/S'],['dispatch','파견']],
    corporate: [['setup','세팅'],['survey','답사'],['filming','촬영중계'],['design','디자인'],['as','A/S']],
    rental: [['monthly','월 계약']],
    broadcast_room: [['monthly','월 계약'],['hourly','시간 대여']],
};
let WORK_TYPES_ACTIVE = null;
const CURRENT_WORK_TYPE = @json($project->work_type);

async function loadActiveWorkTypes() {
    if (WORK_TYPES_ACTIVE) return WORK_TYPES_ACTIVE;
    try {
        const res = await fetch('/api/work-types/active', { headers:{ 'Accept':'application/json' } });
        if (res.ok) WORK_TYPES_ACTIVE = await res.json();
    } catch(e) {}
    return WORK_TYPES_ACTIVE || [];
}

function WORK_TYPES_FOR(scale) {
    if (!WORK_TYPES_ACTIVE || !WORK_TYPES_ACTIVE.length) return WORK_TYPES_FALLBACK[scale] || [];
    return WORK_TYPES_ACTIVE
        .filter(w => !w.scale_keys || !w.scale_keys.length || (scale && w.scale_keys.includes(scale)))
        .map(w => [w.key, w.label]);
}

// 'WORK_TYPES'는 다른 코드 호환을 위해 Proxy로 노출 (스칼라 키 접근 시 자동 조회)
const WORK_TYPES = new Proxy({}, { get: (_, scale) => WORK_TYPES_FOR(scale) });

function updateEditWorkTypes() {
    const scale = document.getElementById('editScale').value;
    const sel = document.getElementById('editWorkType');
    const opts = WORK_TYPES_FOR(scale);
    sel.innerHTML = opts.map(([v,l]) => `<option value="${v}" ${CURRENT_WORK_TYPE===v?'selected':''}>${l}</option>`).join('');
}

async function openScaleEditor() {
    await loadActiveWorkTypes();
    updateEditWorkTypes();
    document.getElementById('scaleModal').style.display = 'flex';
}
function closeScaleEditor() { document.getElementById('scaleModal').style.display = 'none'; }

async function submitScale() {
    const body = {
        client_scale: document.getElementById('editScale').value,
        work_type: document.getElementById('editWorkType').value,
    };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const res = await fetch(`/api/projects/{{ $project->id }}`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body:JSON.stringify(body),
    });
    if (res.ok) location.reload();
    else alert('저장 실패');
}

// 프로젝트 취소 모달
function openCancelModal() {
    document.getElementById('cancelModal').style.display = 'flex';
    document.querySelectorAll('input[name="cancel_reason"]').forEach(r => r.checked = false);
    document.getElementById('cancelDetail').value = '';
    document.getElementById('cancelDetailWrap').style.display = 'none';
}
function closeCancelModal() { document.getElementById('cancelModal').style.display = 'none'; }

// 라디오 선택 시 기타 상세 입력 토글
document.querySelectorAll('input[name="cancel_reason"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('cancelDetailWrap').style.display = this.value === 'other' ? 'block' : 'none';
        // 선택된 라벨 강조
        document.querySelectorAll('#cancelReasons label').forEach(l => l.style.borderColor = 'var(--border)');
        this.closest('label').style.borderColor = 'var(--accent)';
    });
});

async function submitCancel() {
    const reason = document.querySelector('input[name="cancel_reason"]:checked');
    if (!reason) { alert('취소 사유를 선택하세요.'); return; }
    const REASON_LABELS = { no_contact:'의뢰자 연락 두절', client_request:'의뢰자 사정으로 취소', schedule_mismatch:'일정이 맞지 않음', other:'기타' };
    const detail = reason.value === 'other' ? document.getElementById('cancelDetail').value.trim() : '';
    if (reason.value === 'other' && !detail) { alert('기타 사유를 입력하세요.'); return; }

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const res = await fetch(`/projects/{{ $project->id }}/stage`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({
            stage: 'cancelled',
            cancel_reason: REASON_LABELS[reason.value] || reason.value,
            cancel_detail: detail || null,
        }),
    });
    if (res.ok || res.status === 302) location.reload();
    else alert('취소 처리 실패');
}

// 프로젝트 완전 삭제
async function deleteProject() {
    if (!confirm('⚠️ 이 프로젝트를 완전히 삭제하시겠습니까?\n상담 이력/문서 등 관련 데이터가 함께 삭제되며 되돌릴 수 없습니다.')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const res = await fetch(`/api/projects/{{ $project->id }}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    if (res.ok) {
        alert('프로젝트가 삭제되었습니다.');
        // 부모(의뢰자 상세) 탭이 있으면 그쪽으로 이동, 아니면 프로젝트 목록
        if (window.parent && window.parent.location) {
            window.parent.location.href = '/projects';
        } else {
            location.href = '/projects';
        }
    } else {
        alert('삭제 실패');
    }
}

// 앨범 뷰어 + 줌/드래그
const albumDocs = @json($projectDocs);
let albumIdx = 0, zoomScale = 1, panX = 0, panY = 0, isPanning = false, panStartX, panStartY;

function openAlbum(idx) {
    albumIdx = idx;
    renderAlbum();
    document.getElementById('albumOverlay').classList.add('open');
}
function closeAlbum() {
    document.getElementById('albumOverlay').classList.remove('open');
    document.getElementById('albumMediaWrap').innerHTML = '';
    document.getElementById('albumZoomControls').style.display = 'none';
    resetZoom();
}
document.getElementById('albumOverlay').addEventListener('click', e => {
    if (e.target.id === 'albumOverlay') closeAlbum();
});
function albumNav(dir) {
    albumIdx = (albumIdx + dir + albumDocs.length) % albumDocs.length;
    resetZoom();
    renderAlbum();
}
let baseW = 0, baseH = 0;
function resetZoom() { zoomScale = 1; panX = 0; panY = 0; }
function albumZoom(dir) {
    const steps = [0.5, 0.75, 1, 1.5, 2, 3, 4];
    let ci = steps.indexOf(zoomScale);
    if (ci === -1) ci = 2;
    ci = Math.max(0, Math.min(steps.length - 1, ci + dir));
    zoomScale = steps[ci];
    if (zoomScale === 1) { panX = 0; panY = 0; }
    applyZoom();
}
function albumZoomReset() { resetZoom(); applyZoom(); }
function applyZoom() {
    const wrap = document.getElementById('albumMediaWrap');
    const img = wrap.querySelector('img.album-media');
    if (!img) return;
    if (zoomScale === 1) {
        wrap.classList.remove('zoomed');
        img.style.width = '';
        img.style.height = '';
    } else {
        wrap.classList.add('zoomed');
        img.style.width = (baseW * zoomScale) + 'px';
        img.style.height = (baseH * zoomScale) + 'px';
    }
    img.style.transform = `translate(${panX}px,${panY}px)`;
    document.getElementById('albumZoomLevel').textContent = Math.round(zoomScale * 100) + '%';
}
function renderAlbum() {
    const doc = albumDocs[albumIdx];
    const wrap = document.getElementById('albumMediaWrap');
    const zoomCtrl = document.getElementById('albumZoomControls');
    wrap.innerHTML = '';
    wrap.classList.remove('zoomed');
    const isImage = doc.mime && doc.mime.startsWith('image/');
    zoomCtrl.style.display = isImage ? 'flex' : 'none';
    if (isImage) {
        const img = document.createElement('img');
        img.className = 'album-media'; img.src = doc.url;
        img.onload = () => { baseW = img.offsetWidth; baseH = img.offsetHeight; };
        img.addEventListener('wheel', e => { e.preventDefault(); albumZoom(e.deltaY < 0 ? 1 : -1); }, {passive:false});
        img.addEventListener('mousedown', e => {
            if (zoomScale === 1) return;
            isPanning = true; panStartX = e.clientX - panX; panStartY = e.clientY - panY;
            img.classList.add('dragging'); e.preventDefault();
        });
        img.addEventListener('dblclick', () => { zoomScale === 1 ? albumZoom(2) : albumZoomReset(); });
        wrap.appendChild(img);
    } else if (doc.mime && doc.mime.startsWith('video/')) {
        const vid = document.createElement('video');
        vid.className = 'album-media'; vid.src = doc.url; vid.controls = true; vid.autoplay = true;
        wrap.appendChild(vid);
    } else if (doc.mime === 'application/pdf') {
        const iframe = document.createElement('iframe');
        iframe.className = 'album-media'; iframe.src = doc.url;
        iframe.style.cssText = 'width:80vw; height:75vh; border:none;';
        wrap.appendChild(iframe);
    } else {
        const div = document.createElement('div');
        div.style.cssText = 'color:var(--text-muted); font-size:14px; padding:60px; text-align:center;';
        div.textContent = '미리보기를 지원하지 않는 파일입니다.';
        wrap.appendChild(div);
    }
    document.getElementById('albumName').textContent = doc.name;
    document.getElementById('albumNote').textContent = doc.note || '';
    document.getElementById('albumCounter').textContent = `${albumIdx + 1} / ${albumDocs.length}`;
}
document.addEventListener('mousemove', e => {
    if (!isPanning) return;
    panX = e.clientX - panStartX; panY = e.clientY - panStartY;
    applyZoom();
});
document.addEventListener('mouseup', () => {
    if (isPanning) { isPanning = false; const img = document.querySelector('#albumMediaWrap img.album-media'); if(img) img.classList.remove('dragging'); }
});

// 파일 업로드 프리뷰
(function(){
    const fileInput = document.getElementById('docFileInput');
    const fileReal = document.getElementById('docFileReal');
    const previewList = document.getElementById('filePreviewList');
    const btnUpload = document.getElementById('btnUpload');
    const form = document.getElementById('docUploadForm');
    let selectedFiles = [];

    const IMG_TYPES = ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml'];
    const VID_TYPES = ['video/mp4','video/webm','video/ogg','video/quicktime','video/x-msvideo','video/x-matroska'];

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    function getExtIcon(name) {
        const ext = name.split('.').pop().toLowerCase();
        const map = {pdf:'PDF', doc:'DOC', docx:'DOC', xls:'XLS', xlsx:'XLS', ppt:'PPT', pptx:'PPT', zip:'ZIP', rar:'RAR', txt:'TXT', csv:'CSV'};
        return map[ext] || ext.toUpperCase();
    }
    function makeVideoThumb(file, thumb) {
        const video = document.createElement('video');
        video.preload = 'metadata'; video.muted = true; video.playsInline = true;
        const url = URL.createObjectURL(file);
        video.src = url;
        video.addEventListener('loadeddata', () => { video.currentTime = Math.min(1, video.duration / 2); });
        video.addEventListener('seeked', () => {
            const canvas = document.createElement('canvas');
            canvas.width = 72; canvas.height = 72;
            const ctx = canvas.getContext('2d');
            const s = Math.min(video.videoWidth, video.videoHeight);
            ctx.drawImage(video, (video.videoWidth-s)/2, (video.videoHeight-s)/2, s, s, 0, 0, 72, 72);
            thumb.innerHTML = '';
            thumb.appendChild(canvas);
            const badge = document.createElement('span');
            badge.className = 'video-badge'; badge.textContent = '▶';
            thumb.appendChild(badge);
            URL.revokeObjectURL(url);
        });
    }
    function renderPreviews() {
        previewList.innerHTML = '';
        selectedFiles.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'file-preview-item';
            const thumb = document.createElement('div');
            thumb.className = 'thumb';
            if (IMG_TYPES.includes(file.type)) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                thumb.appendChild(img);
            } else if (VID_TYPES.includes(file.type)) {
                thumb.textContent = '...';
                makeVideoThumb(file, thumb);
            } else if (file.type === 'application/pdf') {
                thumb.textContent = 'PDF';
            } else {
                thumb.textContent = getExtIcon(file.name);
            }
            const info = document.createElement('div');
            info.className = 'file-info';
            info.innerHTML = `<div class="file-name" title="${file.name}">${file.name}</div><div class="file-size">${formatSize(file.size)}</div>`;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button'; removeBtn.className = 'btn-remove'; removeBtn.textContent = '×';
            removeBtn.onclick = () => { selectedFiles.splice(idx, 1); syncAndRender(); };
            item.append(thumb, info, removeBtn);
            previewList.appendChild(item);
        });
        btnUpload.disabled = selectedFiles.length === 0;
    }
    function syncAndRender() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileReal.files = dt.files;
        renderPreviews();
    }
    fileInput.addEventListener('change', () => {
        for (const f of fileInput.files) selectedFiles.push(f);
        fileInput.value = '';
        syncAndRender();
    });
    form.addEventListener('submit', (e) => {
        if (selectedFiles.length === 0) { e.preventDefault(); return; }
        syncAndRender();
    });
})();

// 프로젝트 메모
async function addProjectMemo() {
    const textarea = document.getElementById('projectMemoInput');
    const content = textarea.value.trim();
    if (!content) return;
    const res = await fetch(`/api/projects/{{ $project->id }}/memos`, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
        body:JSON.stringify({ content })
    });
    if (res.ok) {
        textarea.value = '';
        location.reload();
    } else { alert('메모 추가 실패'); }
}
async function deleteProjectMemo(id) {
    if (!confirm('이 메모를 삭제하시겠습니까?')) return;
    await fetch(`/api/project-memos/${id}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}
    });
    const el = document.getElementById('pmemo-' + id);
    if (el) el.remove();
}

// ── 동적 필드(관리자 정의 custom_data) 로드/편집 ──
const PROJECT_ID = {{ $project->id }};
const CSRF_PJ = document.querySelector('meta[name="csrf-token"]').content;
let projectFieldDefs = [];
let projectCustomData = @json($project->custom_data ?? new \stdClass);
const PCF_SECTIONS = { basic:'기본 정보', equipment:'장비 정보', schedule:'일정 정보', billing:'금액/결제', etc:'기타' };

function pcfEsc(s){ return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadProjectFieldsForShow() {
    try {
        const res = await fetch('/api/project-fields/active', {headers:{'Accept':'application/json'}});
        if (!res.ok) return;
        projectFieldDefs = (await res.json()).filter(f => f.is_active);
        if (!projectFieldDefs.length) return; // 정의된 필드 없으면 카드도 숨김
        document.getElementById('customDataCard').style.display = '';
        renderProjectCustomFields();
    } catch(e) {}
}

// 소분류 라벨 → 아이콘 매핑 (대소문자/공백 무시 매칭)
const PCF_SUB_ICONS = {
    'pc': '💻', 'computer': '💻', '컴퓨터': '💻', '데스크탑': '💻',
    '노트북': '💻', '랩탑': '💻',
    '카메라': '🎥', 'camera': '🎥', '캠': '🎥',
    '렌즈': '🔍', 'lens': '🔍',
    '오디오': '🎙️', 'audio': '🎙️', '마이크': '🎙️', '사운드': '🎙️', '음향': '🎙️',
    '조명': '💡', 'light': '💡', 'lighting': '💡',
    '모니터': '🖥️', 'monitor': '🖥️', '디스플레이': '🖥️',
    '주변기기': '🎛️', '액세서리': '🎛️', '주변장치': '🎛️',
    '인터넷': '🌐', '네트워크': '🌐', 'network': '🌐',
    '소프트웨어': '🛠️', 'software': '🛠️',
    '스튜디오': '🎬', '세트': '🎬',
};
function pcfSubIcon(name) {
    if (!name) return '📦';
    const k = String(name).trim().toLowerCase();
    if (PCF_SUB_ICONS[k]) return PCF_SUB_ICONS[k];
    // 부분 매칭
    for (const key of Object.keys(PCF_SUB_ICONS)) {
        if (k.includes(key)) return PCF_SUB_ICONS[key];
    }
    return '📦';
}

function renderProjectCustomFields() {
    const wrap = document.getElementById('projectCustomFields');
    // section → subsection → fields 2단 그룹 + priority 집계
    const grouped = {};
    const subMaxPrio = {};
    projectFieldDefs.forEach(f => {
        const sec = f.section || 'etc';
        const sub = f.subsection || '';
        const p = Number.isFinite(parseInt(f.priority, 10)) ? parseInt(f.priority, 10) : 0;
        if (!grouped[sec]) grouped[sec] = {};
        if (!grouped[sec][sub]) grouped[sec][sub] = [];
        grouped[sec][sub].push(f);
        const k = `${sec}::${sub}`;
        if (subMaxPrio[k] === undefined || p > subMaxPrio[k]) subMaxPrio[k] = p;
    });

    // width(1~4) 정규화 — textarea 등 풀폭이 자연스러운 타입은 미지정 시 4로 폴백
    const resolveWidth = (f) => {
        const w = parseInt(f.width, 10);
        if (w >= 1 && w <= 4) return w;
        if (f.type === 'textarea') return 4;
        if (['radio','checkbox'].includes(f.type) && (f.options||[]).length > 3) return 4;
        return 2;
    };

    const renderFieldHtml = (f) => {
        const val = projectCustomData[f.key];
        const w = resolveWidth(f);
        return `<div class="pcf-field w-${w}">
            <div class="pcf-label">${pcfEsc(f.label)}${f.is_required?' <span style="color:var(--red)">*</span>':''}</div>
            ${pcfInput(f, val)}
            ${f.help_text?`<div class="pcf-help">${pcfEsc(f.help_text)}</div>`:''}
        </div>`;
    };

    let html = '';
    // 섹션 접힘 상태 (localStorage에 영속)
    const collapsedKey = 'drgo_pcf_collapsed_{{ $project->id }}';
    const collapsed = (() => { try { return JSON.parse(localStorage.getItem(collapsedKey) || '[]'); } catch(e) { return []; } })();
    Object.entries(PCF_SECTIONS).forEach(([k, lbl]) => {
        if (!grouped[k]) return;
        const subs = grouped[k];
        const subKeys = Object.keys(subs);
        const hasSubsections = subKeys.some(s => s !== '');
        const isCollapsed = collapsed.includes(k);

        html += `<div class="pcf-section${isCollapsed ? ' collapsed' : ''}" data-section="${k}">
            <div class="pcf-sec-title" onclick="togglePcfSection('${k}')" title="클릭하여 접기/펼치기">
                <span class="pcf-sec-toggle">▼</span>${pcfEsc(lbl)}
            </div>`;

        if (!hasSubsections) {
            // 소분류 없음 → 기존 1-그리드 렌더 (priority DESC 정렬)
            const sortedFields = [...(subs[''] || [])].sort((a, b) => (b.priority || 0) - (a.priority || 0));
            html += `<div class="pcf-grid">`;
            sortedFields.forEach(f => { html += renderFieldHtml(f); });
            html += `</div>`;
        } else {
            // 소분류 있음 → 소분류별 서브카드. 소분류 정렬은 그룹 최대 priority DESC, 빈 소분류는 마지막
            const ordered = subKeys.sort((a, b) => {
                if (a === '' || a === '기타') {
                    if (b === '' || b === '기타') return 0;
                    return 1;
                }
                if (b === '' || b === '기타') return -1;
                const dp = (subMaxPrio[`${k}::${b}`] || 0) - (subMaxPrio[`${k}::${a}`] || 0);
                if (dp !== 0) return dp;
                return a.localeCompare(b, 'ko');
            });
            html += `<div style="display:flex; flex-direction:column; gap:10px;">`;
            ordered.forEach(sub => {
                const fields = [...subs[sub]].sort((a, b) => (b.priority || 0) - (a.priority || 0));
                const subLabel = sub || '기타';
                const icon = pcfSubIcon(sub);
                html += `<div class="pcf-subgroup">
                    <div class="pcf-sub-title"><span class="pcf-sub-icon">${icon}</span>${pcfEsc(subLabel)}</div>
                    <div class="pcf-grid">`;
                fields.forEach(f => { html += renderFieldHtml(f); });
                html += `</div></div>`;
            });
            html += `</div>`;
        }
        html += `</div>`;
    });
    wrap.innerHTML = html;
}

// 추가 정보 섹션 접기/펼치기 (localStorage 영속)
function togglePcfSection(sectionKey) {
    const el = document.querySelector(`.pcf-section[data-section="${sectionKey}"]`);
    if (!el) return;
    const key = 'drgo_pcf_collapsed_{{ $project->id }}';
    let list = [];
    try { list = JSON.parse(localStorage.getItem(key) || '[]'); } catch(e) {}
    const isCollapsed = el.classList.toggle('collapsed');
    if (isCollapsed) {
        if (!list.includes(sectionKey)) list.push(sectionKey);
    } else {
        list = list.filter(k => k !== sectionKey);
    }
    localStorage.setItem(key, JSON.stringify(list));
}

// 수량 입력을 지원하는 타입
const PCF_QTY_TYPES = ['text', 'textarea', 'select', 'radio', 'date'];

// raw value(평문/객체) → {value, qty} 정규화 (has_quantity 필드 전용)
function pcfGetVQ(v) {
    if (v && typeof v === 'object' && !Array.isArray(v)) {
        return { value: v.value ?? '', qty: v.qty ?? '' };
    }
    return { value: v ?? '', qty: '' };
}

function pcfInput(f, val) {
    const useQty = !!f.has_quantity && PCF_QTY_TYPES.includes(f.type);
    if (useQty) {
        const vq = pcfGetVQ(val);
        const inner = pcfInputCore(f, vq.value);
        const qtyInput = `<input type="number" class="pcf-input" min="0" step="1" value="${pcfEsc(vq.qty)}" data-qty-key="${f.key}" oninput="pcfQtyChange(this)" placeholder="수량" style="max-width:90px;">`;
        // textarea/radio 는 세로 배치, 그 외는 한 줄
        if (f.type === 'textarea' || f.type === 'radio') {
            return `<div style="display:flex; flex-direction:column; gap:6px;">${inner}<div style="display:flex; align-items:center; gap:6px;"><span style="font-size:11px; color:var(--text-muted); white-space:nowrap;">수량</span>${qtyInput}</div></div>`;
        }
        return `<div style="display:grid; grid-template-columns:1fr 90px; gap:6px;">${inner}${qtyInput}</div>`;
    }
    return pcfInputCore(f, val);
}

function pcfInputCore(f, val) {
    // has_quantity 끈 뒤 잔존하는 {value,qty} 객체도 깨지지 않도록 정규화
    if (val && typeof val === 'object' && !Array.isArray(val) && 'value' in val) val = val.value;
    val = val ?? '';
    const ph = pcfEsc(f.placeholder || '');
    switch (f.type) {
        case 'textarea':
            return `<textarea class="pcf-input" rows="2" data-key="${f.key}" oninput="pcfChange(this)" placeholder="${ph}">${pcfEsc(val)}</textarea>`;
        case 'select':
            const opts = (f.options||[]).map(o => `<option value="${pcfEsc(o)}"${val===o?' selected':''}>${pcfEsc(o)}</option>`).join('');
            return `<select class="pcf-input" data-key="${f.key}" onchange="pcfChange(this)"><option value="">선택...</option>${opts}</select>`;
        case 'radio':
            return `<div class="pcf-radios">${(f.options||[]).map(o => `<label><input type="radio" name="rad_${f.key}" value="${pcfEsc(o)}"${val===o?' checked':''} data-key="${f.key}" onchange="pcfChange(this)"> ${pcfEsc(o)}</label>`).join('')}</div>`;
        case 'checkbox':
            const arr = Array.isArray(val) ? val : [];
            return `<div class="pcf-radios">${(f.options||[]).map(o => `<label><input type="checkbox" name="chk_${f.key}" value="${pcfEsc(o)}"${arr.includes(o)?' checked':''} data-key="${f.key}" data-group="1" onchange="pcfCheckChange(this)"> ${pcfEsc(o)}</label>`).join('')}</div>`;
        case 'number':
            return `<input type="number" class="pcf-input" value="${pcfEsc(val)}" data-key="${f.key}" oninput="pcfChange(this)" placeholder="${ph}">`;
        case 'date':
            return `<input type="date" class="pcf-input" value="${pcfEsc(val)}" data-key="${f.key}" onchange="pcfChange(this)">`;
        default:
            return `<input type="text" class="pcf-input" value="${pcfEsc(val)}" data-key="${f.key}" oninput="pcfChange(this)" placeholder="${ph}">`;
    }
}

function pcfChange(el) {
    const key = el.dataset.key;
    const f = projectFieldDefs.find(x => x.key === key);
    if (f && f.has_quantity && PCF_QTY_TYPES.includes(f.type)) {
        const prev = projectCustomData[key];
        const cur = (prev && typeof prev === 'object' && !Array.isArray(prev)) ? {...prev} : {};
        cur.value = el.value;
        projectCustomData[key] = cur;
    } else {
        projectCustomData[key] = el.value;
    }
    pcfScheduleSave();
}

function pcfQtyChange(el) {
    const key = el.dataset.qtyKey;
    const prev = projectCustomData[key];
    const cur = (prev && typeof prev === 'object' && !Array.isArray(prev)) ? {...prev} : { value: prev ?? '' };
    const num = parseInt(el.value, 10);
    cur.qty = Number.isFinite(num) && num >= 0 ? num : null;
    projectCustomData[key] = cur;
    pcfScheduleSave();
}
function pcfCheckChange(el) {
    const all = [...document.querySelectorAll(`input[name="chk_${el.dataset.key}"]:checked`)].map(x => x.value);
    projectCustomData[el.dataset.key] = all;
    pcfScheduleSave();
}

let pcfSaveTimer = null;
function pcfScheduleSave() {
    clearTimeout(pcfSaveTimer);
    document.getElementById('pcfSaveStatus').textContent = '저장 중...';
    pcfSaveTimer = setTimeout(pcfSave, 600);
}
async function pcfSave() {
    try {
        const res = await fetch(`/api/projects/${PROJECT_ID}`, {
            method:'PATCH',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_PJ,'Accept':'application/json'},
            body: JSON.stringify({ custom_data: projectCustomData }),
        });
        const el = document.getElementById('pcfSaveStatus');
        if (res.ok) {
            el.textContent = '✓ 저장됨';
            setTimeout(() => { el.textContent = ''; }, 2000);
        } else {
            el.textContent = '저장 실패';
            el.style.color = 'var(--red)';
        }
    } catch(e) {
        document.getElementById('pcfSaveStatus').textContent = '저장 실패';
    }
}

loadProjectFieldsForShow();

// ──────────── 결제 내역 (history) ────────────
let __payments = [];
let __refundContext = null; // { chargeId, items: [{name,qty,price,maxQty,checked}] }

async function loadPaymentHistory() {
    try {
        const res = await fetch(`/api/projects/{{ $project->id }}/payments`, {headers:{'Accept':'application/json'}});
        if (!res.ok) return;
        const data = await res.json();
        __payments = data.payments || [];
        renderPaymentHistory();
    } catch(e) {}
}

function _escPh(s){ return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function _fmtPh(n){ return Number(n||0).toLocaleString('ko-KR'); }

function renderPaymentHistory() {
    const card = document.getElementById('paymentHistoryCard');
    const list = document.getElementById('paymentHistoryList');
    if (!__payments.length) {
        card.style.display = 'none';
        return;
    }
    card.style.display = '';

    // 순 결제액 = sum(amount), refund/cancel은 음수로 저장되어 있음
    const net = __payments.reduce((s, p) => s + (p.amount||0), 0);
    document.getElementById('phNetTotal').textContent = `· 순 결제액 ${_fmtPh(net)}원`;

    list.innerHTML = __payments.map(p => {
        const isCharge = p.type === 'charge';
        const isRefund = p.type === 'refund';
        const isCancel = p.type === 'cancel';
        const badge = isCharge ? '<span style="background:rgba(122,200,160,0.15);color:#7ac8a0;border:1px solid rgba(122,200,160,0.35);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">결제</span>'
            : isRefund ? '<span style="background:rgba(232,137,74,0.15);color:#e8894a;border:1px solid rgba(232,137,74,0.35);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">환불</span>'
            : '<span style="background:rgba(200,80,80,0.15);color:var(--red);border:1px solid rgba(200,80,80,0.35);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">결제 취소</span>';
        const amount = isCharge ? `+${_fmtPh(p.amount)}원` : `${_fmtPh(p.amount)}원`;
        const amtColor = isCharge ? 'var(--accent)' : 'var(--red)';
        const refundInfo = isCharge && p.refunded_amount > 0
            ? `<span style="font-size:11px; color:var(--text-muted);">· 환불 ${_fmtPh(p.refunded_amount)}원</span>`
            : '';
        const fullyRefunded = isCharge && p.is_fully_refunded;
        const canRefund = isCharge && !fullyRefunded;
        const itemsHtml = (p.items && p.items.length)
            ? `<div style="margin-top:6px; display:flex; flex-direction:column; gap:2px;">${p.items.map(it => `<div style="display:flex; gap:8px; font-size:11px; color:var(--text-muted);"><span style="flex:1;">${_escPh(it.name||'-')}</span><span>${it.qty||1}개 × ${_fmtPh(it.price||0)}원</span></div>`).join('')}</div>`
            : '';
        return `<div style="padding:12px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; ${fullyRefunded ? 'opacity:0.6;' : ''}">
            <div style="display:flex; align-items:center; gap:8px; justify-content:space-between; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    ${badge}
                    <span style="font-size:14px; font-weight:700; color:${amtColor};">${amount}</span>
                    ${refundInfo}
                    ${fullyRefunded ? '<span style="font-size:10px; color:var(--text-muted); border:1px solid var(--border); padding:1px 6px; border-radius:6px;">전액 환불</span>' : ''}
                </div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    ${isCharge ? `<button onclick="editPayment(${p.id})" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;">수정</button>` : ''}
                    ${canRefund && p.amount > 0 ? `<button onclick="openRefundModal(${p.id}, 'refund')" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;">환불</button>` : ''}
                    ${canRefund && p.amount > 0 ? `<button onclick="openRefundModal(${p.id}, 'cancel')" style="background:none;border:1px solid var(--red);color:var(--red);padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;">결제 취소</button>` : ''}
                    <button onclick="deletePayment(${p.id})" style="background:none;border:1px solid var(--red);color:var(--red);padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;">삭제</button>
                </div>
            </div>
            <div style="margin-top:6px; font-size:12px; color:var(--text-muted); display:flex; gap:10px; flex-wrap:wrap;">
                <span>📅 ${p.paid_at || p.created_at}</span>
                ${p.method ? `<span>· ${_escPh(p.method)}</span>` : ''}
                ${p.estimate_id ? `<span>· 견적서 #${p.estimate_id}</span>` : ''}
                ${p.recorder ? `<span>· ${_escPh(p.recorder)}</span>` : ''}
            </div>
            ${itemsHtml}
            ${p.memo ? `<div style="margin-top:6px; font-size:12px; color:var(--text-muted); white-space:pre-wrap;">📝 ${_escPh(p.memo)}</div>` : ''}
        </div>`;
    }).join('');
}

function openRefundModal(chargeId, type) {
    const charge = __payments.find(p => p.id === chargeId);
    if (!charge) return alert('결제 정보를 찾을 수 없습니다.');

    // 환불 가능 잔여액
    const refundable = charge.amount - charge.refunded_amount;

    // 환불할 항목 후보 (charge.items 그대로, qty는 환불 가능 max로)
    // 추후 견적서 연동 시 source_estimate_item_id가 있으면 표기
    const items = (charge.items || []).map(it => ({
        name: it.name || '항목',
        qty: it.qty || 1,
        price: it.price || 0,
        maxQty: it.qty || 1,
        checked: false,
        source_estimate_item_id: it.source_estimate_item_id || null,
    }));
    __refundContext = { chargeId, type, charge, refundable, items, manualMode: !items.length };

    document.getElementById('refundModalTitle').textContent = type === 'cancel' ? '⚠ 결제 취소' : '↩ 환불';
    document.getElementById('refundChargeMeta').innerHTML = `
        원 결제: <b style="color:var(--accent);">${_fmtPh(charge.amount)}원</b> (${charge.paid_at || charge.created_at})
        · 환불 가능 잔여: <b style="color:var(--red);">${_fmtPh(refundable)}원</b>
        ${charge.method ? '· ' + _escPh(charge.method) : ''}
    `;
    // 항목이 없으면 수기 입력 모드만 활성화
    const hasItems = items.length > 0;
    const manualCheckbox = document.getElementById('refundManualMode');
    manualCheckbox.checked = !hasItems;
    manualCheckbox.disabled = !hasItems; // 항목이 없으면 수기만 가능, 체크박스 비활성화
    document.getElementById('refundManualMax').textContent = `· 최대 ${_fmtPh(refundable)}원`;
    document.getElementById('refundManualAmount').max = refundable;
    document.getElementById('refundManualAmount').value = '';
    applyRefundModeUI();

    document.getElementById('refundReason').value = '';
    document.getElementById('refundMethod').value = charge.method || '';
    document.getElementById('refundModalOverlay').style.display = 'flex';
}

function toggleRefundManualMode(checked) {
    if (!__refundContext) return;
    __refundContext.manualMode = checked;
    if (!checked) {
        // 항목 모드로 돌아갈 때 수기 입력값 초기화
        document.getElementById('refundManualAmount').value = '';
    } else {
        // 수기 모드로 전환 시 항목 선택 해제
        __refundContext.items.forEach(it => it.checked = false);
    }
    applyRefundModeUI();
}

function applyRefundModeUI() {
    const ctx = __refundContext;
    if (!ctx) return;
    const manual = ctx.manualMode;
    document.getElementById('refundItemsWrap').style.display = manual ? 'none' : '';
    document.getElementById('refundManualWrap').style.display = manual ? '' : 'none';
    if (!manual) renderRefundItems();
    updateRefundPreview();
}
function closeRefundModal() {
    document.getElementById('refundModalOverlay').style.display = 'none';
    __refundContext = null;
}

function renderRefundItems() {
    const wrap = document.getElementById('refundItemsList');
    const ctx = __refundContext;
    if (!ctx) return;
    if (!ctx.items.length) {
        wrap.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:8px;">등록된 항목이 없습니다. 위 \'환불금액 수기 입력\' 옵션을 사용해 주세요.</div>';
        return;
    }
    wrap.innerHTML = ctx.items.map((it, i) => {
        return `<label style="display:flex; align-items:center; gap:10px; padding:8px 10px; background:var(--surface); border:1px solid var(--border); border-radius:8px; cursor:pointer;">
            <input type="checkbox" data-idx="${i}" onchange="toggleRefundItem(${i}, this.checked)" ${it.checked?'checked':''}>
            <div style="flex:1; font-size:13px;">${_escPh(it.name)}</div>
            <div style="display:flex; align-items:center; gap:6px;">
                <input type="number" min="1" max="${it.maxQty}" value="${it.qty}" data-idx="${i}" onchange="changeRefundItemQty(${i}, this.value)" ${it.checked?'':'disabled'} style="width:60px; padding:5px 8px; background:var(--surface2); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:12px; outline:none; text-align:right;">
                <span style="font-size:12px; color:var(--text-muted);">/ ${it.maxQty} × ${_fmtPh(it.price)}원</span>
            </div>
        </label>`;
    }).join('');
    updateRefundPreview();
}

function toggleRefundItem(idx, checked) {
    if (!__refundContext) return;
    __refundContext.items[idx].checked = checked;
    renderRefundItems();
}
function changeRefundItemQty(idx, val) {
    if (!__refundContext) return;
    const it = __refundContext.items[idx];
    const q = Math.max(1, Math.min(it.maxQty, parseInt(val||1)));
    it.qty = q;
    updateRefundPreview();
}
function updateRefundPreview() {
    const ctx = __refundContext;
    if (!ctx) return;
    let amount;
    if (ctx.manualMode) {
        amount = parseInt(document.getElementById('refundManualAmount').value || 0);
    } else {
        amount = ctx.items.reduce((s, it) => s + (it.checked ? it.qty * it.price : 0), 0);
    }
    if (amount > ctx.refundable) amount = ctx.refundable;
    document.getElementById('refundAmountPreview').textContent = _fmtPh(amount) + '원';
}

async function submitRefund(type) {
    const ctx = __refundContext;
    if (!ctx) return;
    const isManual = ctx.manualMode;
    const selectedItems = isManual ? [] : ctx.items.filter(it => it.checked).map(it => ({
        name: it.name, qty: it.qty, price: it.price,
        source_estimate_item_id: it.source_estimate_item_id,
    }));
    const directAmount = isManual ? parseInt(document.getElementById('refundManualAmount').value || 0) : 0;

    if (type === 'refund') {
        if (isManual && !directAmount) return alert('환불 금액을 입력해 주세요.');
        if (!isManual && !selectedItems.length) return alert('환불할 항목을 선택해 주세요.');
    }
    const body = {
        parent_payment_id: ctx.chargeId,
        type,
        items: selectedItems,
        amount: isManual ? directAmount : null,
        reason: document.getElementById('refundReason').value || null,
        method: document.getElementById('refundMethod').value || null,
    };
    const res = await fetch(`/api/projects/{{ $project->id }}/payments/refund`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_PJ,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (res.ok) {
        closeRefundModal();
        await loadPaymentHistory();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('실패: ' + (err.error || err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}
function confirmFullCancel() {
    if (!__refundContext) return;
    if (!confirm('이 결제 전체를 취소 처리하시겠습니까?\n환불 가능 잔여액 전체가 음수로 기록됩니다.')) return;
    submitRefund('cancel');
}

loadPaymentHistory();

// URL hash '#openPayment' 감지 시 결제 모달 자동 오픈 (단순 결제 프로젝트 진입 후)
(function autoOpenPaymentIfRequested() {
    const checkHash = () => {
        const h = window.location.hash || '';
        if (h.includes('openPayment')) {
            // hash 정리 후 모달 오픈 (다음 새로고침 시 재오픈 방지)
            history.replaceState(null, '', window.location.pathname + window.location.search);
            setTimeout(() => {
                if (typeof openPaymentModal === 'function') openPaymentModal();
            }, 200);
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', checkHash);
    else checkHash();
})();

// ── 단계별 데이터 (stage_data) 공통 ──
const INITIAL_STAGE_DATA = @json($project->stage_data ?? new \stdClass);

async function saveStageData(key, data, advanceTo = null) {
    const res = await fetch(`/api/projects/${PROJECT_ID}/stage-data`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_PJ,'Accept':'application/json'},
        body: JSON.stringify({ key, data, advance_to: advanceTo }),
    });
    if (!res.ok) {
        const err = await res.json().catch(()=>({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
        return false;
    }
    // 페이지 리로드해 요약 카드와 stage 변경 반영
    location.reload();
    return true;
}

// 장비 파악 단계 데이터는 제거됨 — 장비 정보는 '추가 정보'(custom_data) 의 동적 필드(section=equipment)에서 관리

// ── 일정제안 ──
let proposalScheduleCache = [];
async function openProposalModal() {
    const wrap = document.getElementById('proposalSchedulesList');
    wrap.innerHTML = '<div style="padding:14px; text-align:center; color:var(--text-muted); font-size:12px;">불러오는 중...</div>';
    document.getElementById('proposalModalOverlay').style.display = 'flex';
    const cur = INITIAL_STAGE_DATA.proposal || {};
    const selectedIds = cur.schedule_ids || [];
    document.getElementById('proposalNote').value = cur.note || '';

    const res = await fetch(`/api/projects/${PROJECT_ID}/schedules`, {headers:{'Accept':'application/json'}});
    if (!res.ok) { wrap.innerHTML = '<div style="padding:14px; color:var(--red);">로드 실패</div>'; return; }
    proposalScheduleCache = await res.json();
    if (!proposalScheduleCache.length) {
        wrap.innerHTML = '<div style="padding:14px; text-align:center; color:var(--text-muted); font-size:12px;">의뢰자 이름이 일치하는 캘린더 일정이 없습니다.<br>먼저 캘린더에 일정을 등록해주세요.</div>';
        return;
    }
    wrap.innerHTML = proposalScheduleCache.map(s => {
        const checked = selectedIds.includes(s.id);
        const dateLabel = s.start_date + (s.end_date && s.end_date !== s.start_date ? ' ~ '+s.end_date : '');
        const timeLabel = s.is_all_day ? '종일' : (s.start_time ? s.start_time.slice(0,5)+(s.end_time?'-'+s.end_time.slice(0,5):'') : '');
        return `<label style="display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); cursor:pointer;">
            <input type="checkbox" value="${s.id}" ${checked?'checked':''} class="prop-sch-cb">
            <div style="flex:1; min-width:0;">
                <div style="font-size:13px; font-weight:600;">${pcfEsc(s.title||'(제목 없음)')}</div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${dateLabel}${timeLabel?' · '+timeLabel:''}${s.location?' · '+pcfEsc(s.location):''}</div>
            </div>
        </label>`;
    }).join('');
}
function closeProposalModal() {
    document.getElementById('proposalModalOverlay').style.display = 'none';
}
async function saveProposal() {
    const ids = [...document.querySelectorAll('#proposalSchedulesList .prop-sch-cb:checked')].map(c => parseInt(c.value, 10));
    await saveStageData('proposal', {
        schedule_ids: ids,
        note: document.getElementById('proposalNote').value.trim(),
    }, 'proposal');
}

// 페이지 로드 시 일정제안 요약(card)에 일정 제목 채우기
(async function fillProposalSummary() {
    const el = document.getElementById('proposalSummary');
    if (!el) return;
    const ids = JSON.parse(el.dataset.ids || '[]');
    if (!ids.length) { el.textContent = '연결된 일정 없음'; return; }
    try {
        const res = await fetch(`/api/projects/${PROJECT_ID}/schedules`, {headers:{'Accept':'application/json'}});
        const all = res.ok ? await res.json() : [];
        const linked = all.filter(s => ids.includes(s.id));
        if (!linked.length) { el.textContent = `연결된 일정 ID: ${ids.join(', ')} (캘린더에서 삭제됨)`; return; }
        el.innerHTML = linked.map(s => {
            const dateLabel = s.start_date + (s.end_date && s.end_date !== s.start_date ? ' ~ '+s.end_date : '');
            return `<div style="display:flex; gap:8px; align-items:center; font-size:13px; margin-bottom:3px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--chip-${s.color||'gold'}-bg);"></span><span>${pcfEsc(s.title||'(제목 없음)')}</span><span style="color:var(--text-muted); font-size:11px;">${dateLabel}</span></div>`;
        }).join('');
    } catch { el.textContent = ''; }
})();

// ── 견적/계약 ──
let estimateInfoCache = [];
async function openEstimateInfoModal() {
    const wrap = document.getElementById('estimateInfoList');
    wrap.innerHTML = '<div style="padding:14px; text-align:center; color:var(--text-muted); font-size:12px;">불러오는 중...</div>';
    document.getElementById('estimateInfoModalOverlay').style.display = 'flex';
    const cur = INITIAL_STAGE_DATA.estimate || {};
    const selectedIds = cur.estimate_ids || [];
    document.getElementById('estimateInfoNote').value = cur.note || '';

    const res = await fetch(`/api/projects/${PROJECT_ID}/payment-estimates`, {headers:{'Accept':'application/json'}});
    if (!res.ok) { wrap.innerHTML = '<div style="padding:14px; color:var(--red);">로드 실패</div>'; return; }
    estimateInfoCache = await res.json();
    if (!estimateInfoCache.length) {
        wrap.innerHTML = '<div style="padding:14px; text-align:center; color:var(--text-muted); font-size:12px;">이 의뢰자의 견적서가 없습니다.<br>견적서 페이지에서 먼저 생성해주세요.</div>';
        return;
    }
    const STATUS = {temp:'작성중', created:'완성', editing:'수정중', completed:'발행', paid:'결제완료', hold:'보류'};
    wrap.innerHTML = estimateInfoCache.map(e => {
        const checked = selectedIds.includes(e.id);
        const status = STATUS[e.status] || e.status;
        const name = e.client_nickname || e.client_name || '의뢰자';
        return `<label style="display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); cursor:pointer;">
            <input type="checkbox" value="${e.id}" ${checked?'checked':''} class="est-cb">
            <div style="flex:1; min-width:0;">
                <div style="font-size:13px; font-weight:600;">#${e.id} · ${pcfEsc(name)} <span style="font-size:11px; color:var(--text-muted); font-weight:400;">${e.is_linked?'★ 연결됨':''}</span></div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${(e.total_amount||0).toLocaleString()}원 · ${status} · ${e.issued_at||e.created_at||'-'} · 상품 ${e.items_summary.products}건/서비스 ${e.items_summary.services}건</div>
            </div>
            <a href="/estimates/${e.id}/edit" target="_blank" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:3px 8px; border-radius:5px; font-size:11px; text-decoration:none;" onclick="event.stopPropagation();">열기 ↗</a>
        </label>`;
    }).join('');
}
function closeEstimateInfoModal() {
    document.getElementById('estimateInfoModalOverlay').style.display = 'none';
}
async function saveEstimateInfo() {
    const ids = [...document.querySelectorAll('#estimateInfoList .est-cb:checked')].map(c => parseInt(c.value, 10));
    await saveStageData('estimate', {
        estimate_ids: ids,
        note: document.getElementById('estimateInfoNote').value.trim(),
    }, 'estimate');
}

// ── 방문 보고서 ──
function openVisitReportModal() {
    const v = INITIAL_STAGE_DATA.visit || {};
    document.getElementById('visitReportText').value = v.report || '';
    document.getElementById('visitReportModalOverlay').style.display = 'flex';
    setTimeout(() => document.getElementById('visitReportText').focus(), 50);
}
function closeVisitReportModal() {
    document.getElementById('visitReportModalOverlay').style.display = 'none';
}
async function saveVisitReport() {
    const text = document.getElementById('visitReportText').value.trim();
    await saveStageData('visit', { report: text }, 'visit');
}

// ── 결제 정보 모달 ──
let payEstimatesList = [];
const initialPayment = @json($project->payment_info ?? new \stdClass);

async function openPaymentModal(prefillPayment) {
    // editPayment에서 호출 시에는 prefillPayment를 전달, +결제 추가에서는 인자 없음 → 신규 모드
    if (prefillPayment === undefined) {
        window.editingPaymentId = null;
    }
    document.getElementById('paymentModalOverlay').style.display = 'flex';
    // 견적서 목록 로드
    try {
        const res = await fetch(`/api/projects/${PROJECT_ID}/payment-estimates`, {headers:{'Accept':'application/json'}});
        payEstimatesList = res.ok ? await res.json() : [];
    } catch { payEstimatesList = []; }
    const sel = document.getElementById('payEstimateId');
    sel.innerHTML = '<option value="">— 견적서 미연결 (수기 입력) —</option>'
        + payEstimatesList.map(e => {
            const tag = e.is_linked ? '★' : '';
            const status = ({temp:'작성중', created:'완성', editing:'수정중', completed:'발행', paid:'결제완료', hold:'보류'})[e.status] || e.status;
            const name = e.client_nickname || e.client_name || '의뢰자';
            return `<option value="${e.id}">${tag}#${e.id} · ${pcfEsc(name)} · ${(e.total_amount||0).toLocaleString()}원 (${status})</option>`;
        }).join('');

    // 기존 결제 데이터 복원: 수정 모드면 prefillPayment, 신규면 project.payment_info 폴백
    const cur = (typeof prefillPayment === 'object' && prefillPayment) ? prefillPayment : (initialPayment || {});
    if (cur.estimate_id) sel.value = String(cur.estimate_id);
    document.getElementById('payAmount').value = cur.amount || '';
    document.getElementById('payPaidAt').value = cur.paid_at || new Date().toISOString().slice(0,10);
    document.getElementById('payMethod').value = cur.method || '';
    document.getElementById('payMemo').value = cur.memo || '';
    // 잔금 여부/금액 복원
    const hasBal = !!cur.has_balance;
    document.querySelectorAll('input[name="payHasBalance"]').forEach(r => {
        r.checked = (r.value === (hasBal ? '1' : '0'));
    });
    document.getElementById('payBalanceAmount').value = cur.balance_amount || '';
    togglePayBalance();
    // 항목/금액 합산 초기화 — 기존 amount가 있으면 수기 모드, 없으면 합산 모드
    window.payAmountManual = !!cur.amount;
    renderPayItems(cur.items || []);
    recalcPayAmount();
    onSelectEstimate(); // 정보 표시
}
function closePaymentModal() {
    document.getElementById('paymentModalOverlay').style.display = 'none';
    window.editingPaymentId = null;
}

function togglePayBalance() {
    const checked = document.querySelector('input[name="payHasBalance"]:checked');
    const has = checked && checked.value === '1';
    document.getElementById('payBalanceAmountWrap').style.display = has ? '' : 'none';
    if (!has) document.getElementById('payBalanceAmount').value = '';
}

function onSelectEstimate() {
    const id = document.getElementById('payEstimateId').value;
    const info = document.getElementById('payEstimateInfo');
    if (!id) { info.textContent = ''; return; }
    const est = payEstimatesList.find(e => String(e.id) === id);
    if (!est) { info.textContent = ''; return; }
    info.innerHTML = `상품 ${est.items_summary.products}건 · 서비스 ${est.items_summary.services}건 · 합계 <strong style="color:var(--accent);">${(est.total_amount||0).toLocaleString()}원</strong> · 발행 ${est.issued_at || est.created_at || '-'}`;
    // 결제 금액이 비어 있으면 견적서 합계로 자동 채움
    const amountEl = document.getElementById('payAmount');
    if (!amountEl.value || +amountEl.value === 0) amountEl.value = est.total_amount || 0;
}

function renderPayItems(items) {
    const wrap = document.getElementById('payItemsWrap');
    wrap.innerHTML = '';
    (items.length ? items : [{name:'', qty:1, price:0}]).forEach(it => addPayItem(it));
}
function addPayItem(it = {name:'', qty:1, price:0}) {
    const wrap = document.getElementById('payItemsWrap');
    const row = document.createElement('div');
    row.className = 'pay-item-row';
    row.style.cssText = 'display:flex; gap:6px; align-items:center;';
    row.innerHTML = `
        <input type="text" class="pcf-input" value="${pcfEsc(it.name||'')}" placeholder="항목명" data-pi="name" style="flex:2;" oninput="recalcPayAmount()">
        <input type="number" class="pcf-input" value="${it.qty ?? 1}" min="0" placeholder="수량" data-pi="qty" style="flex:0.6; max-width:80px;" oninput="recalcPayAmount()">
        <input type="number" class="pcf-input" value="${it.price ?? 0}" min="0" placeholder="단가" data-pi="price" style="flex:1; max-width:120px;" oninput="recalcPayAmount()">
        <button type="button" onclick="this.parentElement.remove(); recalcPayAmount();" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 8px; border-radius:5px; font-size:11px; cursor:pointer;">×</button>
    `;
    wrap.appendChild(row);
}

/**
 * 결제 항목 합산 → 금액 자동 갱신
 * 사용자가 amount를 직접 수정한 적이 있으면(payAmountManual=true) 자동 갱신 안 함
 */
window.payAmountManual = false;
function recalcPayAmount() {
    const amountInput = document.getElementById('payAmount');
    if (!amountInput) return;
    let sum = 0;
    document.querySelectorAll('#payItemsWrap .pay-item-row').forEach(row => {
        const name = row.querySelector('[data-pi="name"]').value.trim();
        const qty = parseInt(row.querySelector('[data-pi="qty"]').value, 10) || 0;
        const price = parseInt(row.querySelector('[data-pi="price"]').value, 10) || 0;
        if (name) sum += qty * price;
    });
    if (!window.payAmountManual) {
        amountInput.value = sum;
    }
    const note = document.getElementById('payAmountNote');
    if (note) note.textContent = window.payAmountManual ? '(수기 입력 모드 — 항목 합산 무시됨)' : `(항목 합산 자동 반영)`;
}
function collectPayItems() {
    const items = [];
    document.querySelectorAll('#payItemsWrap .pay-item-row').forEach(row => {
        const name = row.querySelector('[data-pi="name"]').value.trim();
        const qty = parseInt(row.querySelector('[data-pi="qty"]').value, 10) || 0;
        const price = parseInt(row.querySelector('[data-pi="price"]').value, 10) || 0;
        if (name) items.push({name, qty, price});
    });
    return items;
}

// 결제 수정: 모달을 기존 결제 데이터로 채워서 열고, 저장 시 PATCH로 분기
window.editingPaymentId = null;
async function editPayment(paymentId) {
    const p = __payments.find(x => x.id === paymentId);
    if (!p) return alert('결제 정보를 찾을 수 없습니다.');
    window.editingPaymentId = paymentId;
    // openPaymentModal은 항상 새로 초기화하므로 initialPayment 인자가 필요
    await openPaymentModal({
        amount: p.amount,
        paid_at: p.paid_at,
        method: p.method || '',
        items: p.items || [],
        memo: p.memo || '',
        estimate_id: p.estimate_id || null,
        has_balance: false,
        balance_amount: 0,
    });
    // 모달 제목 갱신 + 저장 버튼 라벨 변경
    const title = document.querySelector('#paymentModalOverlay .modal-title, #paymentModalOverlay h3, #paymentModalOverlay [style*="font-weight:700"]');
}

async function deletePayment(paymentId) {
    const p = __payments.find(x => x.id === paymentId);
    if (!p) return;
    const msg = p.type === 'charge'
        ? '이 결제를 삭제하시겠습니까?\n연결된 환불/취소 기록도 함께 삭제됩니다.'
        : '이 환불/취소 기록을 삭제하시겠습니까?';
    if (!confirm(msg)) return;
    const res = await fetch(`/api/projects/${PROJECT_ID}/payments/${paymentId}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN':CSRF_PJ, 'Accept':'application/json'},
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        return alert('삭제 실패: ' + (err.error || err.message || '알 수 없는 오류'));
    }
    location.reload();
}

async function savePayment() {
    const hasBalanceChecked = document.querySelector('input[name="payHasBalance"]:checked');
    const hasBalance = !!(hasBalanceChecked && hasBalanceChecked.value === '1');
    const body = {
        estimate_id: document.getElementById('payEstimateId').value ? +document.getElementById('payEstimateId').value : null,
        amount: parseInt(document.getElementById('payAmount').value, 10) || 0,
        paid_at: document.getElementById('payPaidAt').value || null,
        method: document.getElementById('payMethod').value || null,
        items: collectPayItems(),
        memo: document.getElementById('payMemo').value.trim() || null,
        mark_estimate_paid: document.getElementById('payMarkPaid').checked,
        has_balance: hasBalance,
        balance_amount: hasBalance ? (parseInt(document.getElementById('payBalanceAmount').value, 10) || 0) : 0,
    };
    // 0원도 허용. 단, 빈 결제(amount=0, 견적/항목/메모 전부 없음)는 의미가 없어 차단
    if (!body.amount && !body.estimate_id && !body.items.length && !body.memo) {
        return alert('결제 금액, 견적서, 항목, 메모 중 하나는 입력해야 합니다.');
    }
    // 수정 모드 (PATCH) — editingPaymentId가 설정되어 있으면 분기
    const editId = window.editingPaymentId;
    const url = editId
        ? `/api/projects/${PROJECT_ID}/payments/${editId}`
        : `/api/projects/${PROJECT_ID}/payment`;
    const method = editId ? 'PATCH' : 'POST';
    const res = await fetch(url, {
        method,
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_PJ,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        const err = await res.json().catch(()=>({}));
        return alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
    closePaymentModal();
    location.reload();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeConsultModal(); closeEditModal(); closeAlbum(); closePaymentModal();
        closeProposalModal(); closeEstimateInfoModal(); closeVisitReportModal();
    }
    if (document.getElementById('albumOverlay').classList.contains('open')) {
        if (e.key === 'ArrowLeft') albumNav(-1);
        if (e.key === 'ArrowRight') albumNav(1);
    }
});

// ──────────────────────────── 방문 보고서 (완료 시) ────────────────────────────

// 완료 단계 클릭 시 확인 다이얼로그
window.confirmDoneStage = function() {
    if (!confirm('세팅을 완료합니다.\n보고서를 작성하시겠습니까?\n\n(예: 즉시 작성 / 아니오: 완료만 처리)')) {
        // '아니오' — 완료만 처리하고 보고서는 비움
        return advanceToDone(false);
    }
    advanceToDone(true);
};

async function advanceToDone(openEditor) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    try {
        const res = await fetch(`/projects/{{ $project->id }}/stage`, {
            method: 'PATCH',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ stage: 'done' }),
        });
        if (!res.ok) {
            await showFetchErrorVR(res, '완료 처리 실패');
            return;
        }
        if (openEditor) {
            // 카드 노출 + 포커스
            const card = document.getElementById('visitReportCard');
            if (card) card.style.display = 'block';
            setTimeout(() => {
                if (card) card.scrollIntoView({behavior:'smooth', block:'start'});
                if (window.vrEditor) window.vrEditor.commands.focus();
            }, 250);
        } else {
            // '아니오' 선택 시 단계 UI 갱신 위해 새로고침
            location.reload();
        }
    } catch(e) {
        alert('통신 오류: ' + e.message);
    }
}

async function showFetchErrorVR(res, prefix) {
    let detail = '';
    try { const p = await res.json(); detail = p.message || p.error || JSON.stringify(p.errors || {}); } catch(e) { detail = await res.text().catch(()=>''); }
    alert(`[${prefix} · 코드 ${res.status}]\n${detail || '응답 본문 없음'}`);
}

window.saveVisitReportEditor = async function() {
    if (!window.vrEditor) return;
    const html = window.vrEditor.getHTML();
    const statusEl = document.getElementById('vrSaveStatus');
    statusEl.textContent = '저장 중...';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const res = await fetch(`/api/projects/{{ $project->id }}`, {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({ visit_report: html }),
    });
    if (res.ok) {
        statusEl.textContent = '✓ 저장됨';
        statusEl.style.color = 'var(--green)';
        setTimeout(() => { statusEl.textContent = ''; statusEl.style.color = ''; }, 2500);
        // 저장 후 자동으로 읽기 모드로 전환
        vrSetMode('view');
    } else {
        statusEl.textContent = '저장 실패';
        statusEl.style.color = 'var(--red)';
        await showFetchErrorVR(res, '방문 보고서 저장 실패');
    }
};

// ── 보고서 템플릿 불러오기 ──
window.vrOpenTemplatePicker = async function() {
    if (!window.vrEditor) return;
    let templates = [];
    try {
        const res = await fetch('/api/visit-report-templates/active', {headers:{'Accept':'application/json'}});
        if (res.ok) templates = await res.json();
    } catch(e) {}
    if (!templates.length) {
        return alert('등록된 활성 템플릿이 없습니다.\n관리 → 설정 → 보고서 템플릿에서 추가해 주세요.');
    }
    const list = templates.map((t, i) => `${i+1}. ${t.is_default?'⭐ ':''}${t.name}`).join('\n');
    const pick = prompt(`적용할 템플릿 번호를 입력하세요:\n\n${list}\n\n(현재 내용에 덮어쓰기 됩니다)`, '1');
    if (pick === null) return;
    const idx = parseInt(pick) - 1;
    if (Number.isNaN(idx) || idx < 0 || idx >= templates.length) return alert('잘못된 번호입니다.');
    const t = templates[idx];
    if (window.vrEditor.getText().trim() !== '' && !confirm(`현재 작성된 내용이 '${t.name}' 템플릿으로 대체됩니다. 진행할까요?`)) return;
    window.vrEditor.commands.setContent(t.content || '');
};

// ── 보고서 모드 전환 (view ↔ edit) ──
window.vrSetMode = function(mode) {
    const card = document.getElementById('visitReportCard');
    if (!card) return;
    card.dataset.mode = mode;
    const isView = (mode === 'view');
    // 버튼 표시 전환
    const btnEdit = document.getElementById('vrBtnEdit');
    const btnCancel = document.getElementById('vrBtnCancel');
    const btnSave = document.getElementById('vrBtnSave');
    if (btnEdit)   btnEdit.style.display = isView ? 'inline-flex' : 'none';
    if (btnCancel) btnCancel.style.display = isView ? 'none' : 'inline-flex';
    if (btnSave)   btnSave.style.display = isView ? 'none' : 'inline-flex';
    // 에디터 편집 가능 상태 토글
    if (window.vrEditor) {
        window.vrEditor.setEditable(!isView);
    }
    if (isView) {
        // 뷰 모드 진입 시 기본은 접힘 + 오버플로 측정
        card.classList.add('is-collapsed');
        card.classList.remove('is-short');
        setTimeout(() => window.vrCheckOverflow && window.vrCheckOverflow(), 80);
    } else {
        // 편집 모드에서는 펼침
        card.classList.remove('is-collapsed');
        card.classList.remove('is-short');
    }
    // 편집 진입 시 포커스
    if (!isView && window.vrEditor) setTimeout(() => window.vrEditor.commands.focus(), 50);
};

// 본문 길이 측정 — 500px 넘으면 'is-collapsed' 클래스 유지, 아니면 'is-short' 부착
window.vrCheckOverflow = function() {
    const card = document.getElementById('visitReportCard');
    if (!card) return;
    const body = card.querySelector('#vrEditor .ProseMirror') || document.getElementById('vrEditor');
    if (!body) return;
    // 펼친 상태에서 진짜 높이 측정
    const wasCollapsed = card.classList.contains('is-collapsed');
    if (wasCollapsed) card.classList.remove('is-collapsed');
    const h = Math.max(body.scrollHeight || 0, body.offsetHeight || 0);
    if (wasCollapsed) card.classList.add('is-collapsed');
    if (h <= 500) {
        // 500px 미만 — 접기 의미 없으므로 펼친 상태로 + 버튼 숨김
        card.classList.add('is-short');
        card.classList.remove('is-collapsed');
    } else {
        card.classList.remove('is-short');
    }
    const btn = document.getElementById('vrExpandBtn');
    if (btn) btn.textContent = card.classList.contains('is-collapsed') ? '▼ 더 보기' : '▲ 접기';
};

// ResizeObserver — 본문 크기 변할 때마다 자동 재측정 (이미지 로드, 폰트 늦은 적용 등 모두 커버)
(function setupVrResizeObserver(){
    if (typeof ResizeObserver === 'undefined') return;
    const tryAttach = () => {
        const body = document.querySelector('#vrEditor .ProseMirror');
        if (!body) { setTimeout(tryAttach, 300); return; }
        const ro = new ResizeObserver(() => window.vrCheckOverflow && window.vrCheckOverflow());
        ro.observe(body);
        // 페이지 안에서 동적으로 src가 바뀌는 이미지가 추가될 때마다 load 리스너 부착
        const moResolve = () => {
            body.querySelectorAll('img').forEach(img => {
                if (img.complete || img.dataset.vrBound) return;
                img.dataset.vrBound = '1';
                img.addEventListener('load', () => window.vrCheckOverflow && window.vrCheckOverflow(), { once: true });
                img.addEventListener('error', () => window.vrCheckOverflow && window.vrCheckOverflow(), { once: true });
            });
        };
        new MutationObserver(moResolve).observe(body, { childList: true, subtree: true });
        moResolve();
        // 윈도우 리사이즈도 트리거
        window.addEventListener('resize', () => window.vrCheckOverflow && window.vrCheckOverflow());
    };
    document.addEventListener('DOMContentLoaded', tryAttach);
    setTimeout(tryAttach, 0);
})();

window.vrToggleExpand = function() {
    const card = document.getElementById('visitReportCard');
    if (!card) return;
    const collapsed = card.classList.contains('is-collapsed');
    card.classList.toggle('is-collapsed', !collapsed);
    const btn = document.getElementById('vrExpandBtn');
    if (btn) btn.textContent = collapsed ? '▲ 접기' : '▼ 더 보기';
    // 접기 시 카드 상단으로 스크롤
    if (!collapsed) card.scrollIntoView({ behavior:'smooth', block:'start' });
};

// 파일 업로드 (이미지/영상) — base64 임베드 (간단 구현). 대용량은 추후 별도 업로드 API로 전환 가능.
window.vrUploadFiles = function(files, kind) {
    if (!window.vrEditor || !files || !files.length) return;
    Array.from(files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const src = e.target.result;
            if (kind === 'image') {
                window.vrEditor.chain().focus().setImage({ src }).run();
            } else {
                // video는 raw HTML 삽입
                window.vrEditor.chain().focus().insertContent(
                    `<video src="${src}" controls></video><p></p>`
                ).run();
            }
        };
        reader.readAsDataURL(file);
    });
};
</script>

{{-- Tiptap 모듈 (위키와 동일 셋업) --}}
<script type="importmap">
{
    "imports": {
        "@tiptap/core": "https://esm.sh/@tiptap/core@2.11.5",
        "@tiptap/starter-kit": "https://esm.sh/@tiptap/starter-kit@2.11.5",
        "@tiptap/extension-image": "https://esm.sh/@tiptap/extension-image@2.11.5",
        "@tiptap/extension-link": "https://esm.sh/@tiptap/extension-link@2.11.5",
        "@tiptap/extension-placeholder": "https://esm.sh/@tiptap/extension-placeholder@2.11.5",
        "@tiptap/extension-table": "https://esm.sh/@tiptap/extension-table@2.11.5",
        "@tiptap/extension-table-row": "https://esm.sh/@tiptap/extension-table-row@2.11.5",
        "@tiptap/extension-table-cell": "https://esm.sh/@tiptap/extension-table-cell@2.11.5",
        "@tiptap/extension-table-header": "https://esm.sh/@tiptap/extension-table-header@2.11.5",
        "@tiptap/extension-text-align": "https://esm.sh/@tiptap/extension-text-align@2.11.5"
    }
}
</script>
<script type="module">
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TextAlign from '@tiptap/extension-text-align';

const editorEl = document.getElementById('vrEditor');
if (editorEl) {
    const ResizableImage = Image.extend({
        addAttributes() {
            return {
                ...this.parent?.(),
                width: { default: null, parseHTML: el => el.getAttribute('width') || el.style.width?.replace('px','') || null, renderHTML: attrs => attrs.width ? { width: attrs.width, style: `width:${attrs.width}px;height:auto;` } : {} },
                height: { default: null, renderHTML: () => ({}) },
                align: {
                    default: null,
                    parseHTML: el => el.getAttribute('data-align'),
                    renderHTML: attrs => attrs.align ? { 'data-align': attrs.align } : {},
                },
            };
        },
    });

    let __vrLastSaved = @json($project->visit_report ?? '');
    let __vrDirty = false;
    const CSRF_VR = document.querySelector('meta[name="csrf-token"]').content;

    // 이미지를 최대 900px 폭으로 리사이즈 (POST 용량 초과 방지)
    async function vrResizeImage(file, maxW = 900) {
        if (!file || !file.type || !file.type.startsWith('image/')) return file;
        // GIF는 애니메이션 손실 우려가 있으므로 패스
        if (file.type === 'image/gif') return file;
        try {
            const bitmap = await createImageBitmap(file);
            if (bitmap.width <= maxW) { bitmap.close && bitmap.close(); return file; }
            const ratio = maxW / bitmap.width;
            const w = maxW;
            const h = Math.round(bitmap.height * ratio);
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(bitmap, 0, 0, w, h);
            bitmap.close && bitmap.close();
            // PNG 원본은 PNG로, 그 외 이미지는 JPEG로 인코딩 (용량 절감)
            const outType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
            const quality = outType === 'image/jpeg' ? 0.88 : undefined;
            const blob = await new Promise(res => canvas.toBlob(res, outType, quality));
            if (!blob) return file;
            // 원본보다 작아진 경우에만 사용
            if (blob.size >= file.size) return file;
            const ext = outType === 'image/png' ? '.png' : '.jpg';
            const base = (file.name || 'image').replace(/\.[^.]+$/, '');
            return new File([blob], `${base}-w${maxW}${ext}`, { type: outType });
        } catch(e) {
            console.warn('image resize failed, uploading original', e);
            return file;
        }
    }

    // 파일 업로드 — 프로젝트 첨부 문서로도 함께 등록되는 인라인 업로드 엔드포인트
    async function vrUploadAndInsert(file) {
        if (!file) return;
        // 이미지면 자동 리사이즈 (900px 폭, JPEG 88%)
        file = await vrResizeImage(file, 900);
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await fetch(`/api/projects/{{ $project->id }}/documents/inline`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_VR, 'Accept': 'application/json' },
                body: fd,
            });
            if (!res.ok) {
                let detail = '';
                try { const e = await res.json(); detail = e.message || JSON.stringify(e.errors||{}); } catch(_) {}
                alert(`업로드 실패 [코드 ${res.status}]\n${detail}`);
                return;
            }
            const data = await res.json();
            if (data.is_image) {
                window.vrEditor.chain().focus().setImage({ src: data.url, alt: data.name }).run();
            } else if (data.is_video) {
                window.vrEditor.chain().focus().insertContent(
                    `<video src="${data.url}" controls></video><p></p>`
                ).run();
            } else {
                window.vrEditor.chain().focus().insertContent(
                    `<a href="${data.url}" target="_blank">${data.name}</a>`
                ).run();
            }
        } catch(e) { alert('업로드 오류: ' + e.message); }
    }
    window.vrUploadAndInsert = vrUploadAndInsert;

    window.vrEditor = new Editor({
        element: editorEl,
        extensions: [
            StarterKit.configure({ heading: { levels: [1,2,3] } }),
            ResizableImage.configure({ inline: false }),
            Link.configure({ openOnClick: false }),
            Placeholder.configure({ placeholder: '방문/세팅 보고서를 작성하세요. ("/" 입력으로 블록 추가)' }),
            Table.configure({ resizable: true }),
            TableRow, TableCell, TableHeader,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
        ],
        content: __vrLastSaved,
        editorProps: {
            handleKeyDown(view, event) {
                if (event.key === '/') { setTimeout(() => vrShowSlashMenu(view), 10); }
                return false;
            },
            handleDrop(view, event) {
                const files = event.dataTransfer?.files;
                if (files?.length) { event.preventDefault(); vrUploadAndInsert(files[0]); return true; }
                return false;
            },
            handlePaste(view, event) {
                const items = event.clipboardData?.items;
                if (!items) return false;
                for (const item of items) {
                    if (item.type.startsWith('image/')) { event.preventDefault(); vrUploadAndInsert(item.getAsFile()); return true; }
                }
                return false;
            },
        },
        onUpdate({ editor }) {
            updateVrToolbar(editor);
            const cur = editor.getHTML();
            __vrDirty = (cur !== __vrLastSaved);
            if (__vrDirty) {
                const s = document.getElementById('vrSaveStatus');
                if (s && !s.dataset.saving) { s.textContent = '● 수정됨'; s.style.color = 'var(--text-muted)'; }
            }
        },
        onSelectionUpdate({ editor }) { updateVrToolbar(editor); },
    });

    // 초기 mode에 맞춰 편집 가능 상태 동기화
    const __vrInitCard = document.getElementById('visitReportCard');
    const initMode = __vrInitCard?.dataset.mode || 'edit';
    window.vrEditor.setEditable(initMode === 'edit');
    if (initMode === 'view' && __vrInitCard) {
        __vrInitCard.classList.add('is-collapsed');
        // 이미지 로드 후 정확한 높이로 재측정
        setTimeout(() => window.vrCheckOverflow && window.vrCheckOverflow(), 150);
        // 보고서에 이미지가 있으면 마지막 이미지 로드 후에도 재측정
        document.querySelectorAll('#vrEditor img').forEach(img => {
            if (!img.complete) img.addEventListener('load', () => window.vrCheckOverflow && window.vrCheckOverflow(), { once: true });
        });
    }

    // ── 슬래시 메뉴 (위키와 동일) ──
    const SLASH_ITEMS = [
        { icon:'📝', label:'텍스트', desc:'기본 텍스트', action:()=>window.vrEditor.chain().focus().setParagraph().run() },
        { icon:'H1', label:'제목 1', desc:'큰 제목', action:()=>window.vrEditor.chain().focus().toggleHeading({level:1}).run() },
        { icon:'H2', label:'제목 2', desc:'중간 제목', action:()=>window.vrEditor.chain().focus().toggleHeading({level:2}).run() },
        { icon:'H3', label:'제목 3', desc:'작은 제목', action:()=>window.vrEditor.chain().focus().toggleHeading({level:3}).run() },
        { icon:'•', label:'글머리 목록', desc:'순서 없는 목록', action:()=>window.vrEditor.chain().focus().toggleBulletList().run() },
        { icon:'1.', label:'번호 목록', desc:'순서 있는 목록', action:()=>window.vrEditor.chain().focus().toggleOrderedList().run() },
        { icon:'"', label:'인용', desc:'인용 블록', action:()=>window.vrEditor.chain().focus().toggleBlockquote().run() },
        { icon:'{ }', label:'코드 블록', desc:'코드 삽입', action:()=>window.vrEditor.chain().focus().toggleCodeBlock().run() },
        { icon:'—', label:'구분선', desc:'수평 구분선', action:()=>window.vrEditor.chain().focus().setHorizontalRule().run() },
        { icon:'📎', label:'파일/이미지/영상', desc:'파일 업로드', action:()=>document.querySelector('#vrToolbar input[type=file]').click() },
        { icon:'📊', label:'표', desc:'3x3 표 삽입', action:()=>window.vrEditor.chain().focus().insertTable({rows:3,cols:3,withHeaderRow:true}).run() },
    ];
    let slashIdx = 0;
    function vrShowSlashMenu(view){
        const menu = document.getElementById('vrSlashMenu');
        const { from } = view.state.selection;
        const coords = view.coordsAtPos(from);
        menu.style.top = (coords.bottom + 4) + 'px';
        menu.style.left = coords.left + 'px';
        slashIdx = 0;
        renderSlashMenu();
        menu.classList.add('visible');
        const handler = (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); slashIdx = Math.min(slashIdx+1, SLASH_ITEMS.length-1); renderSlashMenu(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); slashIdx = Math.max(slashIdx-1, 0); renderSlashMenu(); }
            else if (e.key === 'Enter') { e.preventDefault(); SLASH_ITEMS[slashIdx].action(); hideSlashMenu(); window.vrEditor.commands.deleteRange({from: from-1, to: from}); }
            else if (e.key === 'Escape') { hideSlashMenu(); }
            else { setTimeout(() => hideSlashMenu(), 100); }
            if (!menu.classList.contains('visible')) document.removeEventListener('keydown', handler);
        };
        document.addEventListener('keydown', handler);
        document.addEventListener('click', function once() { hideSlashMenu(); document.removeEventListener('click', once); }, { once: true });
    }
    function renderSlashMenu(){
        document.getElementById('vrSlashMenu').innerHTML = SLASH_ITEMS.map((item, i) =>
            `<div class="slash-item ${i===slashIdx?'selected':''}" data-vr-slash="${i}"><div class="slash-icon">${item.icon}</div><div><div class="slash-label">${item.label}</div><div class="slash-desc">${item.desc}</div></div></div>`
        ).join('');
        document.querySelectorAll('#vrSlashMenu .slash-item').forEach(el => {
            el.addEventListener('click', () => { SLASH_ITEMS[parseInt(el.dataset.vrSlash)].action(); hideSlashMenu(); });
        });
    }
    function hideSlashMenu(){ document.getElementById('vrSlashMenu').classList.remove('visible'); }

    // ── 자동 저장 (60초) ──
    async function vrAutoSave() {
        if (!__vrDirty) return;
        const statusEl = document.getElementById('vrSaveStatus');
        statusEl.dataset.saving = '1';
        statusEl.textContent = '자동 저장 중...';
        statusEl.style.color = 'var(--text-muted)';
        const html = window.vrEditor.getHTML();
        try {
            const res = await fetch(`/api/projects/{{ $project->id }}`, {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_VR,'Accept':'application/json'},
                body: JSON.stringify({ visit_report: html }),
            });
            if (res.ok) {
                __vrLastSaved = html;
                __vrDirty = false;
                const now = new Date();
                statusEl.textContent = `✓ 자동 저장 ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
                statusEl.style.color = 'var(--green)';
            } else {
                statusEl.textContent = '⚠ 자동 저장 실패';
                statusEl.style.color = 'var(--red)';
            }
        } catch(e) {
            statusEl.textContent = '⚠ 네트워크 오류';
            statusEl.style.color = 'var(--red)';
        }
        delete statusEl.dataset.saving;
    }
    setInterval(vrAutoSave, 60000);

    window.addEventListener('beforeunload', () => {
        if (!__vrDirty) return;
        try {
            fetch(`/api/projects/{{ $project->id }}`, {
                method:'PATCH',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_VR},
                body: JSON.stringify({ visit_report: window.vrEditor.getHTML() }),
                keepalive: true,
            });
        } catch(e) {}
    });

    const _origSave = window.saveVisitReportEditor;
    window.saveVisitReportEditor = async function() {
        await _origSave();
        __vrLastSaved = window.vrEditor.getHTML();
        __vrDirty = false;
    };

    // 툴바 핸들러
    const cmdMap = {
        bold:        ed => ed.chain().focus().toggleBold().run(),
        italic:      ed => ed.chain().focus().toggleItalic().run(),
        strike:      ed => ed.chain().focus().toggleStrike().run(),
        code:        ed => ed.chain().focus().toggleCode().run(),
        h1:          ed => ed.chain().focus().toggleHeading({level:1}).run(),
        h2:          ed => ed.chain().focus().toggleHeading({level:2}).run(),
        h3:          ed => ed.chain().focus().toggleHeading({level:3}).run(),
        bulletList:  ed => ed.chain().focus().toggleBulletList().run(),
        orderedList: ed => ed.chain().focus().toggleOrderedList().run(),
        blockquote:  ed => ed.chain().focus().toggleBlockquote().run(),
        codeBlock:   ed => ed.chain().focus().toggleCodeBlock().run(),
        alignLeft:   ed => ed.isActive('image')
            ? ed.chain().focus().updateAttributes('image', { align: 'left' }).run()
            : ed.chain().focus().setTextAlign('left').run(),
        alignCenter: ed => ed.isActive('image')
            ? ed.chain().focus().updateAttributes('image', { align: 'center' }).run()
            : ed.chain().focus().setTextAlign('center').run(),
        alignRight:  ed => ed.isActive('image')
            ? ed.chain().focus().updateAttributes('image', { align: 'right' }).run()
            : ed.chain().focus().setTextAlign('right').run(),
        hr:          ed => ed.chain().focus().setHorizontalRule().run(),
        table:       ed => ed.chain().focus().insertTable({rows:3,cols:3,withHeaderRow:true}).run(),
    };
    document.querySelectorAll('#vrToolbar button[data-cmd]').forEach(btn => {
        btn.addEventListener('click', () => {
            const fn = cmdMap[btn.dataset.cmd];
            if (fn) fn(window.vrEditor);
        });
    });

    function updateVrToolbar(ed) {
        const checks = {
            bold: ed.isActive('bold'),
            italic: ed.isActive('italic'),
            strike: ed.isActive('strike'),
            code: ed.isActive('code'),
            h1: ed.isActive('heading',{level:1}),
            h2: ed.isActive('heading',{level:2}),
            h3: ed.isActive('heading',{level:3}),
            bulletList: ed.isActive('bulletList'),
            orderedList: ed.isActive('orderedList'),
            blockquote: ed.isActive('blockquote'),
            codeBlock: ed.isActive('codeBlock'),
            alignLeft: ed.isActive('image', { align: 'left' }) || (!ed.isActive('image') && ed.isActive({ textAlign: 'left' })),
            alignCenter: ed.isActive('image', { align: 'center' }) || (!ed.isActive('image') && ed.isActive({ textAlign: 'center' })),
            alignRight: ed.isActive('image', { align: 'right' }) || (!ed.isActive('image') && ed.isActive({ textAlign: 'right' })),
        };
        document.querySelectorAll('#vrToolbar button[data-cmd]').forEach(b => {
            b.classList.toggle('is-active', !!checks[b.dataset.cmd]);
        });
    }

    // ── 이미지 라이트박스 (확대/축소) ──
    window.vrImgLightbox = function() {
        // 리사이즈 팝업의 activeImg 사용
        if (!window.__vrActiveImg) return;
        const src = window.__vrActiveImg.src;
        const alt = window.__vrActiveImg.alt || '';
        let zoom = 1;
        const overlay = document.createElement('div');
        overlay.id = 'vrLightbox';
        overlay.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; display:flex; align-items:center; justify-content:center; overflow:auto; user-select:none;';
        overlay.innerHTML = `
            <button id="vrLbClose" title="닫기 (Esc)" style="position:fixed; top:20px; right:24px; background:rgba(255,255,255,0.12); border:none; color:#fff; width:42px; height:42px; border-radius:50%; font-size:20px; cursor:pointer; z-index:10001;">✕</button>
            <div id="vrLbToolbar" style="position:fixed; bottom:24px; left:50%; transform:translateX(-50%); display:flex; gap:6px; align-items:center; background:rgba(0,0,0,0.55); backdrop-filter:blur(8px); border-radius:32px; padding:8px 12px; z-index:10001;">
                <button data-z="-" title="축소 (-)" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;">−</button>
                <span id="vrLbLevel" style="min-width:56px;text-align:center;color:#fff;font-size:13px;font-weight:600;">100%</span>
                <button data-z="+" title="확대 (+)" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;">+</button>
                <button data-z="r" title="원본 (1배)" style="height:36px;border-radius:18px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:11px;cursor:pointer;padding:0 12px;">원본</button>
                <button data-z="f" title="화면 맞춤" style="height:36px;border-radius:18px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:11px;cursor:pointer;padding:0 12px;">맞춤</button>
            </div>
            <img id="vrLbImg" src="${src}" alt="${alt}" style="max-width:none; max-height:none; transition:transform 0.15s; transform:scale(1); display:block; cursor:grab;">
        `;
        document.body.appendChild(overlay);

        const img = overlay.querySelector('#vrLbImg');
        const level = overlay.querySelector('#vrLbLevel');

        function applyZoom() {
            img.style.transform = `scale(${zoom})`;
            level.textContent = Math.round(zoom * 100) + '%';
        }
        function fit() {
            // 화면에 맞도록 자동 계산
            const vw = window.innerWidth - 80;
            const vh = window.innerHeight - 120;
            const iw = img.naturalWidth || vw;
            const ih = img.naturalHeight || vh;
            zoom = Math.min(vw/iw, vh/ih, 1);
            applyZoom();
        }
        // 처음엔 맞춤 표시
        if (img.complete) fit(); else img.addEventListener('load', fit, { once: true });

        overlay.querySelectorAll('[data-z]').forEach(btn => {
            btn.addEventListener('click', e => {
                const v = btn.dataset.z;
                if (v === '+') zoom = Math.min(zoom + 0.25, 8);
                else if (v === '-') zoom = Math.max(zoom - 0.25, 0.1);
                else if (v === 'r') zoom = 1;
                else if (v === 'f') return fit();
                applyZoom();
            });
        });

        // 휠로 줌
        overlay.addEventListener('wheel', e => {
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.15 : -0.15;
            zoom = Math.max(0.1, Math.min(8, zoom + delta));
            applyZoom();
        }, { passive: false });

        // 드래그로 패닝
        let dragging = false, sx = 0, sy = 0, scrollSx = 0, scrollSy = 0;
        img.addEventListener('mousedown', e => {
            dragging = true; img.style.cursor = 'grabbing';
            sx = e.clientX; sy = e.clientY;
            scrollSx = overlay.scrollLeft; scrollSy = overlay.scrollTop;
            e.preventDefault();
        });
        document.addEventListener('mousemove', e => {
            if (!dragging) return;
            overlay.scrollLeft = scrollSx - (e.clientX - sx);
            overlay.scrollTop = scrollSy - (e.clientY - sy);
        });
        document.addEventListener('mouseup', () => { dragging = false; img.style.cursor = 'grab'; });

        function close() {
            overlay.remove();
            document.removeEventListener('keydown', keyHandler);
        }
        overlay.querySelector('#vrLbClose').addEventListener('click', close);
        overlay.addEventListener('click', e => {
            if (e.target === overlay) close();
        });
        function keyHandler(e) {
            if (e.key === 'Escape') close();
            else if (e.key === '+' || e.key === '=') { zoom = Math.min(zoom + 0.25, 8); applyZoom(); }
            else if (e.key === '-' || e.key === '_') { zoom = Math.max(zoom - 0.25, 0.1); applyZoom(); }
            else if (e.key === '0') { zoom = 1; applyZoom(); }
        }
        document.addEventListener('keydown', keyHandler);
    };

    // ── 이미지 클릭 → 리사이즈 팝업 (네이버 에디터 스타일) ──
    (function(){
        let popup = null, activeImg = null;
        function removePopup() {
            if (popup) { popup.remove(); popup = null; }
            activeImg = null;
            window.__vrActiveImg = null;
        }
        function applyImgW(w) {
            if (!activeImg) return;
            w = Math.max(30, Math.min(2000, w));
            activeImg.style.width = w + 'px';
            activeImg.style.height = 'auto';
            activeImg.setAttribute('width', w);
            activeImg.removeAttribute('height');
            if (popup) popup.querySelector('#vrImgWidthInput').value = w;
            // Tiptap 노드 attr도 동기화 (직렬화/저장에 반영)
            try {
                const pos = window.vrEditor.view.posAtDOM(activeImg, 0);
                if (pos != null) {
                    const node = window.vrEditor.view.state.doc.nodeAt(pos);
                    if (node) {
                        const tr = window.vrEditor.view.state.tr.setNodeMarkup(pos, undefined, { ...node.attrs, width: String(w) });
                        window.vrEditor.view.dispatch(tr);
                    }
                }
            } catch(e) {}
        }
        window.vrImgResize = function(ratio) {
            if (!activeImg) return;
            const pm = document.querySelector('#vrEditor .ProseMirror');
            const maxW = (pm?.clientWidth || 800) - 48;
            applyImgW(Math.round(maxW * ratio));
        };
        window.vrImgApplyWidth = function() {
            if (!activeImg || !popup) return;
            applyImgW(parseInt(popup.querySelector('#vrImgWidthInput').value) || 200);
        };
        function showPopup(img) {
            removePopup();
            activeImg = img;
            window.__vrActiveImg = img;
            popup = document.createElement('div');
            popup.style.cssText = 'position:fixed;z-index:9999;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:8px 12px;box-shadow:0 4px 16px rgba(0,0,0,0.2);display:flex;align-items:center;gap:8px;font-size:12px;';
            popup.innerHTML = `<span style="color:var(--text-muted);font-size:11px;white-space:nowrap;">크기:</span>
                <button onclick="vrImgResize(0.25)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">25%</button>
                <button onclick="vrImgResize(0.5)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">50%</button>
                <button onclick="vrImgResize(0.75)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">75%</button>
                <button onclick="vrImgResize(1)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">100%</button>
                <span style="color:var(--text-muted);">|</span>
                <input type="number" id="vrImgWidthInput" value="${img.offsetWidth}" min="30" max="2000" style="width:60px;padding:3px 6px;border:1px solid var(--border);border-radius:5px;background:var(--surface2);color:var(--text);font-size:12px;text-align:center;">
                <span style="color:var(--text-muted);font-size:11px;">px</span>
                <button onclick="vrImgApplyWidth()" style="padding:3px 10px;border:none;border-radius:5px;background:var(--accent);color:var(--accent-text);font-size:11px;font-weight:600;cursor:pointer;">적용</button>
                <span style="color:var(--text-muted);">|</span>
                <button onclick="vrImgLightbox()" title="원본 보기 (확대/축소)" style="padding:3px 10px;border:1px solid var(--accent);border-radius:5px;background:none;color:var(--accent);font-size:11px;font-weight:600;cursor:pointer;">🔍 원본</button>`;
            document.body.appendChild(popup);
            const rect = img.getBoundingClientRect();
            popup.style.left = Math.max(8, rect.left + (rect.width - popup.offsetWidth)/2) + 'px';
            popup.style.top = Math.max(8, rect.top - popup.offsetHeight - 8) + 'px';
            if (parseFloat(popup.style.top) < 8) popup.style.top = (rect.bottom + 8) + 'px';
            popup.querySelector('#vrImgWidthInput').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); vrImgApplyWidth(); } });
            popup.addEventListener('mousedown', e => e.stopPropagation());
        }
        document.addEventListener('click', function(e){
            if (e.target.tagName === 'IMG' && e.target.closest('#vrEditor .ProseMirror')) {
                e.preventDefault();
                const isView = document.getElementById('visitReportCard')?.dataset.mode === 'view';
                if (isView) {
                    // 뷰 모드: 리사이즈 팝업 없이 바로 라이트박스
                    window.__vrActiveImg = e.target;
                    window.vrImgLightbox();
                } else {
                    showPopup(e.target);
                }
            } else if (popup && !popup.contains(e.target)) {
                removePopup();
            }
        });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && popup) removePopup(); });
    })();
}
</script>
@endpush
