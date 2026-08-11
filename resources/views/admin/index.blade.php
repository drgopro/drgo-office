@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '관리 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1000px; margin:0 auto; }
    .page-title { font-size:22px; font-weight:700; margin-bottom:20px; }

    .tab-bar { display:flex; gap:2px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:4px; margin-bottom:20px; }
    .tab-btn { flex:1; padding:10px 0; text-align:center; font-size:13px; font-weight:600; border:none; background:none; color:var(--text-muted); cursor:pointer; border-radius:8px; transition:all 0.15s; }
    .tab-btn.active { background:var(--accent); color:var(--accent-text); }
    .tab-btn:not(.active):hover { color:var(--text); background:var(--surface2); }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }
    .sub-tab-bar { display:flex; gap:0; border-bottom:1px solid var(--border); margin-bottom:18px; }
    .sub-tab-btn { padding:10px 18px; font-size:13px; font-weight:600; color:var(--text-muted); background:none; border:none; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all 0.15s; }
    .sub-tab-btn:hover { color:var(--text); }
    .sub-tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
    .sub-tab-panel { display:none; }
    .sub-tab-panel.active { display:block; }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .text-muted { color:var(--text-muted); font-size:12px; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-success { background:#1a2a1a; color:#7ac87a; }
    .badge-fail { background:#2a1a1a; color:#c87a7a; }

    .pagination-wrap { display:flex; justify-content:center; margin-top:16px; }
    .pagination-wrap nav span, .pagination-wrap nav a { display:inline-block; padding:6px 12px; margin:0 2px; border-radius:6px; font-size:12px; border:1px solid var(--border); color:var(--text-muted); text-decoration:none; }
    .pagination-wrap nav span[aria-current] { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
    .pagination-wrap nav a:hover { border-color:var(--accent); color:var(--accent); }

    .settings-form { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:600px; }
    .settings-form .field-group { margin-bottom:16px; }
    .settings-form .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .settings-form .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .settings-form .field-input:focus { border-color:var(--accent); }
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-save:hover { opacity:0.85; }
    .save-msg { font-size:12px; color:var(--green); margin-left:10px; display:none; }

    /* 셀렉트, 인라인 폼 */
    .inline-select { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:5px 8px; color:var(--text); font-size:12px; outline:none; }
    .inline-select:focus { border-color:var(--accent); }
    .btn-sm { background:var(--accent); color:var(--accent-text); border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
    .btn-sm:hover { opacity:0.85; }
    .btn-danger { background:var(--red); color:#fff; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
    .btn-danger:hover { opacity:0.85; }
    .btn-add { background:none; border:1px solid var(--accent); color:var(--accent); padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; margin-bottom:12px; }
    .btn-add:hover { background:var(--accent); color:var(--accent-text); }

    /* 팀 카드 */
    .team-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:12px; }
    .team-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .team-name-input { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:15px; font-weight:700; outline:none; max-width:220px; }
    .team-name-input:focus { border-color:var(--accent); }
    .team-count { font-size:11px; color:var(--text-muted); margin-left:8px; }

    /* 권한 토글 */
    .perm-section { margin-bottom:14px; }
    .perm-section:last-child { margin-bottom:0; }
    .perm-section-title { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:8px; letter-spacing:0.05em; }
    .perm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:10px; }
    .perm-toggle { display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
    .perm-toggle input[type="checkbox"] { display:none; }
    .perm-switch { position:relative; width:34px; height:18px; background:var(--border); border-radius:9px; transition:background 0.2s; flex-shrink:0; }
    .perm-switch::after { content:''; position:absolute; top:2px; left:2px; width:14px; height:14px; background:var(--text-muted); border-radius:50%; transition:all 0.2s; }
    .perm-toggle input:checked + .perm-switch { background:var(--accent); }
    .perm-toggle input:checked + .perm-switch::after { left:18px; background:#fff; }
    .perm-label { font-size:12px; color:var(--text-muted); transition:color 0.15s; }
    .perm-toggle input:checked ~ .perm-label { color:var(--text); font-weight:600; }

    /* 활성 토글 */
    .toggle-active { cursor:pointer; font-size:12px; }
    .toggle-active.on { color:var(--green); }
    .toggle-active.off { color:var(--red); }
    [data-theme="light"] .tab-btn.active { color:#fff; }
    [data-theme="light"] .pagination-wrap nav span[aria-current] { color:#fff; }
    [data-theme="light"] .btn-save { color:#fff; }
    [data-theme="light"] .btn-sm { color:#fff; }
    [data-theme="light"] .btn-add:hover { color:#fff; }
    .account-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
    .account-modal-overlay.open { display:flex; }
    .account-modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:440px; max-width:95vw; padding:24px; }
    .account-modal h3 { font-size:16px; font-weight:700; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
    .account-modal .close-btn { background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer; }
    .account-modal .field-group { margin-bottom:14px; }
    .account-modal .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .account-modal .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .account-modal .field-input:focus { border-color:var(--accent); }
    .account-modal .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    [data-theme="light"] .account-modal { background:#fff; border-color:#c8ccd4; }
    [data-theme="light"] .account-modal .field-input { background:#fff; border-color:#b8bcc8; }

    /* 의뢰자 필드 정의 */
    .cf-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
    .cf-hint { font-size:12px; color:var(--text-muted); line-height:1.5; }
    .cf-empty { padding:48px 24px; text-align:center; background:var(--surface); border:1px dashed var(--border); border-radius:14px; }
    .cf-empty-icon { font-size:32px; margin-bottom:10px; opacity:0.5; }
    .cf-empty-title { font-size:14px; font-weight:600; color:var(--text); margin-bottom:4px; }
    .cf-empty-sub { font-size:12px; color:var(--text-muted); }

    .cf-section { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px; margin-bottom:14px; }
    .cf-section-head { display:flex; align-items:center; gap:8px; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid var(--border); }
    .cf-section-title { font-size:13px; font-weight:700; color:var(--text); }
    .cf-section-count { font-size:11px; color:var(--text-muted); background:var(--surface2); padding:2px 8px; border-radius:10px; }
    .cf-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px; }

    .cf-card { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:13px 14px; cursor:pointer; transition:border-color 0.15s, transform 0.15s, opacity 0.15s; display:flex; flex-direction:column; gap:8px; position:relative; }
    .cf-card:hover { border-color:var(--accent); transform:translateY(-1px); }
    .cf-card.inactive { opacity:0.55; }
    .cf-card[draggable="true"] .cf-drag-handle { position:absolute; top:6px; right:6px; width:18px; height:18px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); opacity:0; cursor:grab; font-size:12px; line-height:1; user-select:none; transition:opacity 0.15s; }
    .cf-card:hover .cf-drag-handle { opacity:0.7; }
    .cf-card.dragging { opacity:0.35; }
    .cf-card.drag-over-top { box-shadow:inset 0 3px 0 var(--accent); }
    .cf-card.drag-over-bottom { box-shadow:inset 0 -3px 0 var(--accent); }
    .cf-card-row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .cf-card-label { font-size:13px; font-weight:600; color:var(--text); display:flex; align-items:center; gap:6px; line-height:1.3; }
    .cf-required { color:var(--red); font-weight:700; }
    .cf-card-key { font-size:10px; color:var(--text-muted); font-family:ui-monospace,Menlo,monospace; }
    .cf-card-meta { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .cf-type-badge { display:inline-flex; align-items:center; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; letter-spacing:0.02em; }
    .cf-type-text { background:rgba(122,160,200,0.15); color:#7aa0c8; }
    .cf-type-textarea { background:rgba(122,160,200,0.15); color:#7aa0c8; }
    .cf-type-select { background:rgba(180,140,80,0.15); color:#d4a96a; }
    .cf-type-radio { background:rgba(180,140,80,0.15); color:#d4a96a; }
    .cf-type-checkbox { background:rgba(180,140,80,0.15); color:#d4a96a; }
    .cf-type-number { background:rgba(122,200,160,0.15); color:#7ac8a0; }
    .cf-type-date { background:rgba(200,122,180,0.15); color:#c87ab4; }
    .cf-chip { display:inline-flex; align-items:center; font-size:10px; padding:2px 7px; border-radius:10px; background:var(--surface); border:1px solid var(--border); color:var(--text-muted); }
    .cf-chip.muted { opacity:0.7; }

    /* 필드 모달 */
    .cf-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
    .cf-modal-overlay.open { display:flex; }
    .cf-modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:540px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .cf-modal h3 { font-size:16px; font-weight:700; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center; }
    .cf-modal-sub { font-size:11px; color:var(--text-muted); margin-bottom:18px; }
    .cf-modal .close-btn { background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer; }
    .cf-modal .field-group { margin-bottom:14px; }
    .cf-modal .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; font-weight:600; letter-spacing:0.02em; }
    .cf-modal .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; font-family:inherit; }
    .cf-modal .field-input:focus { border-color:var(--accent); }
    .cf-modal textarea.field-input { resize:vertical; min-height:90px; }
    .cf-modal-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .cf-toggle-row { display:flex; gap:14px; margin:8px 0 18px; padding:12px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; }
    .cf-toggle-row label { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted); cursor:pointer; font-weight:500; }
    .cf-toggle-row input[type=checkbox] { accent-color:var(--accent); width:14px; height:14px; cursor:pointer; }
    .cf-modal-actions { display:flex; gap:10px; justify-content:flex-end; padding-top:14px; border-top:1px solid var(--border); }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-outline:hover { border-color:var(--text-muted); color:var(--text); }
    .btn-danger-outline { background:none; border:1px solid var(--red); color:var(--red); padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-danger-outline:hover { background:var(--red); color:#fff; }
    [data-theme="light"] .cf-modal { background:#fff; border-color:#c8ccd4; }
    [data-theme="light"] .cf-modal .field-input { background:#fff; border-color:#b8bcc8; }
    /* ── 보고서 템플릿 Tiptap 에디터 (위키와 동일) ── */
    .tiptap-wrap { border:1px solid var(--border); border-radius:10px; background:var(--surface); }
    .tiptap-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:8px 10px; border-bottom:1px solid var(--border); background:var(--surface2); border-radius:10px 10px 0 0; }
    .tiptap-toolbar button { background:none; border:1px solid transparent; color:var(--text-muted); width:30px; height:30px; border-radius:6px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:all 0.12s; }
    .tiptap-toolbar button:hover { background:var(--surface); border-color:var(--border); color:var(--text); }
    .tiptap-toolbar button.is-active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
    .tiptap-toolbar .sep { width:1px; height:20px; background:var(--border); margin:5px 4px; }
    .tiptap-toolbar .tool-btn { width:auto; padding:0 8px; font-size:11px; gap:4px; display:inline-flex; white-space:nowrap; height:30px; }
    #rtEditor .ProseMirror { padding:16px 20px; min-height:260px; max-height:420px; overflow-y:auto; outline:none; font-size:14px; line-height:1.85; color:var(--text); }
    #rtEditor .ProseMirror p { margin:0 0 10px; }
    #rtEditor .ProseMirror h1 { font-size:22px; font-weight:700; margin:16px 0 8px; }
    #rtEditor .ProseMirror h2 { font-size:18px; font-weight:700; margin:14px 0 6px; }
    #rtEditor .ProseMirror h3 { font-size:15px; font-weight:600; margin:12px 0 4px; }
    #rtEditor .ProseMirror ul, #rtEditor .ProseMirror ol { margin:0 0 10px; padding-left:24px; }
    #rtEditor .ProseMirror blockquote { border-left:3px solid var(--accent); margin:10px 0; padding:6px 16px; color:var(--text-muted); }
    #rtEditor .ProseMirror hr { border:none; border-top:1px solid var(--border); margin:14px 0; }
    #rtEditor .ProseMirror code { background:var(--surface2); padding:2px 6px; border-radius:4px; font-family:monospace; font-size:13px; }
    #rtEditor .ProseMirror pre { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 18px; margin:10px 0; overflow-x:auto; }
    #rtEditor .ProseMirror p.is-editor-empty:first-child::before { content:attr(data-placeholder); color:var(--text-muted); float:left; pointer-events:none; height:0; }
    #rtEditor .ProseMirror [data-text-align="center"] { text-align:center; }
    #rtEditor .ProseMirror [data-text-align="right"] { text-align:right; }

    @media (max-width:600px) {
        .cf-modal-row { grid-template-columns:1fr; }
        .cf-grid { grid-template-columns:1fr; }
    }

    /* 캘린더 카테고리 */
    .cat-card { display:grid; grid-template-columns:20px 64px 1fr 130px 130px auto; gap:12px; align-items:center; padding:14px; background:var(--surface); border:1px solid var(--border); border-radius:12px; margin-bottom:10px; }
    .cat-card.dragging { opacity:0.45; border-style:dashed; }
    .cat-drag-handle { color:var(--text-muted); cursor:grab; user-select:none; font-size:13px; letter-spacing:-1px; text-align:center; padding:8px 2px; }
    .cat-drag-handle:active { cursor:grabbing; }
    .cat-preview { padding:10px 14px; border-radius:8px; text-align:center; font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cat-card input[type=text] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 10px; color:var(--text); font-size:13px; outline:none; width:100%; }
    .cat-card input[type=text]:focus { border-color:var(--accent); }
    .cat-color-wrap { display:flex; align-items:center; gap:6px; }
    .cat-color-wrap input[type=color] { width:36px; height:36px; border:1px solid var(--border); border-radius:8px; background:transparent; cursor:pointer; padding:0; }
    .cat-color-wrap input[type=text] { width:88px; font-family:ui-monospace,Menlo,monospace; font-size:11px; padding:7px 8px; }
    .cat-actions { display:flex; gap:6px; justify-content:flex-end; }
    /* 중간 해상도: 카드가 세로로 길게 늘어지지 않도록 3줄 compact 배치 */
    @media (max-width:900px) {
        .cat-card { display:flex; flex-wrap:wrap; gap:10px; }
        .cat-preview { flex:1; min-width:0; }
        .cat-card > input[type=text] { flex:1 1 100%; }
        .cat-color-wrap { flex:0 0 auto; }
        .cat-actions { margin-left:auto; }
    }

    @media (max-width: 768px) {
        .page-wrap { padding:16px; }
        .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
        .data-table { min-width:500px; }
        .data-table th, .data-table td { padding:10px; font-size:12px; white-space:nowrap; }
        .tab-bar { flex-wrap:wrap; }
        .tab-btn { font-size:12px; padding:8px 4px; }
        .modal { width:95vw !important; max-width:95vw !important; padding:16px !important; }
        .field-row { grid-template-columns:1fr !important; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-title">관리</div>

    <div class="tab-bar" id="adminTabBar">
        <button class="tab-btn active" data-tab="logs">로그인 기록</button>
        <button class="tab-btn" data-tab="users">사용자 관리</button>
        <button class="tab-btn" data-tab="teams">팀 관리</button>
        <button class="tab-btn" data-tab="assignees">담당자 관리</button>
        <button class="tab-btn" data-tab="settings"><x-icon name="gear" :size="14"/> 설정</button>
    </div>

    {{-- 설정 그룹 (의뢰자/프로젝트 필드 + 유형 + 캘린더 + 판매처 + 보고서 템플릿) --}}
    <div class="tab-panel" id="panel-settings">
        <div class="sub-tab-bar" id="settingsSubTabBar">
            <button class="sub-tab-btn active" data-subtab="clientFields" onclick="switchSettingsSubTab('clientFields')">의뢰자 필드</button>
            <button class="sub-tab-btn" data-subtab="projectFields" onclick="switchSettingsSubTab('projectFields')">프로젝트 필드</button>
            <button class="sub-tab-btn" data-subtab="projectTypes" onclick="switchSettingsSubTab('projectTypes')">프로젝트 유형</button>
            <button class="sub-tab-btn" data-subtab="workTypes" onclick="switchSettingsSubTab('workTypes')">작업 유형</button>
            <button class="sub-tab-btn" data-subtab="calendarCategories" onclick="switchSettingsSubTab('calendarCategories')">캘린더 카테고리</button>
            <button class="sub-tab-btn" data-subtab="reportTemplates" onclick="switchSettingsSubTab('reportTemplates')">보고서 템플릿</button>
            <button class="sub-tab-btn" data-subtab="visitOptions" onclick="switchSettingsSubTab('visitOptions')">내방 옵션</button>
            <button class="sub-tab-btn" data-subtab="cancelReasons" onclick="switchSettingsSubTab('cancelReasons')">취소 사유</button>
            <button class="sub-tab-btn" data-subtab="seller" onclick="switchSettingsSubTab('seller')">판매처 설정</button>
        </div>
        <div id="settingsContent"></div>
    </div>

    {{-- 로그인 기록 --}}
    <div class="tab-panel active" id="panel-logs">
        <div class="data-card">
            <table class="data-table">
                <thead>
                    <tr><th>일시</th><th>사용자</th><th>아이디</th><th>결과</th><th>IP</th><th>브라우저</th></tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->created_at->format('Y.m.d H:i:s') }}</td>
                        <td>{{ $log->user?->display_name ?? '-' }}</td>
                        <td class="text-muted">{{ $log->username }}</td>
                        <td>
                            @if($log->success)
                                <span class="badge badge-success">성공</span>
                            @else
                                <span class="badge badge-fail">실패</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $log->ip_address }}</td>
                        <td class="text-muted" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $log->user_agent }}">
                            @php
                                $ua = $log->user_agent ?? '';
                                if (str_contains($ua, 'Chrome')) $browser = 'Chrome';
                                elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
                                elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
                                elseif (str_contains($ua, 'Edge')) $browser = 'Edge';
                                else $browser = mb_substr($ua, 0, 30);
                            @endphp
                            {{ $browser }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">로그인 기록이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="pagination-wrap">{{ $logs->links() }}</div>
        @endif
    </div>

    {{-- 사용자 관리 --}}
    <div class="tab-panel" id="panel-users">
        <button class="btn-add" onclick="toggleNewUserForm()">+ 사용자 추가</button>
        <div id="newUserForm" style="display:none; margin-bottom:16px;" class="settings-form">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="field-group">
                    <div class="field-label">아이디 (로그인용)</div>
                    <input class="field-input" id="newUsername" placeholder="username">
                </div>
                <div class="field-group">
                    <div class="field-label">표시 이름</div>
                    <input class="field-input" id="newDisplayName" placeholder="홍길동">
                </div>
                <div class="field-group">
                    <div class="field-label">비밀번호</div>
                    <div style="position:relative;">
                        <input class="field-input" id="newPassword" type="password" placeholder="8자 이상" style="padding-right:40px;">
                        <button type="button" onclick="togglePwVisibility('newPassword',this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px;padding:4px;"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-0.15em"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">역할</div>
                    <select class="field-input" id="newRole" onchange="document.getElementById('newTeamId').disabled=this.value!=='member'">
                        <option value="member">member</option>
                        <option value="admin">admin</option>
                        <option value="guest">guest</option>
                    </select>
                </div>
                <div class="field-group">
                    <div class="field-label">팀</div>
                    <select class="field-input" id="newTeamId"><option value="">없음</option></select>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:12px;">
                <button class="btn-save" onclick="createUser()">생성</button>
                <button class="btn-danger" onclick="document.getElementById('newUserForm').style.display='none'">취소</button>
            </div>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead>
                    <tr><th>이름</th><th>아이디</th><th>역할</th><th>팀</th><th>활성</th><th></th></tr>
                </thead>
                <tbody id="usersBody">
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">로딩 중...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 팀 관리 --}}
    <div class="tab-panel" id="panel-teams">
        <button class="btn-add" onclick="showNewTeamForm()">+ 팀 추가</button>
        <div id="newTeamForm" style="display:none;" class="team-card">
            <div class="team-header">
                <input class="team-name-input" id="newTeamName" placeholder="팀 이름">
                <div style="display:flex; gap:6px;">
                    <button class="btn-sm" onclick="createTeam()">저장</button>
                    <button class="btn-danger" onclick="document.getElementById('newTeamForm').style.display='none'">취소</button>
                </div>
            </div>
            <div id="newTeamPerms"></div>
        </div>
        <div id="teamsContainer"></div>
    </div>

    {{-- 의뢰자 필드 정의 --}}
    <div class="tab-panel" id="panel-clientFields">
        <div class="cf-toolbar">
            <div class="cf-hint">
                관리자가 정의한 필드는 의뢰자 등록/편집 화면에 자동으로 노출됩니다.<br>
                <span style="opacity:0.7;">예: 메인 카메라, 렌즈, PC 사양, 방송 주제 등 장비/방송 정보를 자유롭게 추가하세요.</span>
            </div>
            <button class="btn-add" style="margin-bottom:0;" onclick="openFieldModal()">+ 필드 추가</button>
        </div>
        <div id="clientFieldsContainer"></div>
    </div>

    {{-- 의뢰자 필드 추가/편집 모달 --}}
    <div id="fieldModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) drgoModalMinimize(this, '의뢰자 필드 편집', '📝')">
        <div class="cf-modal">
            <h3>
                <span id="fieldModalTitle">+ 필드 추가</span>
                <button type="button" class="close-btn" onclick="closeFieldModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub">의뢰자 정보에 노출될 사용자 정의 필드를 구성합니다.</div>
            <input type="hidden" id="fieldId">

            <div class="field-group">
                <div class="field-label">라벨 *</div>
                <input type="text" class="field-input" id="fieldLabel" placeholder="예: 메인 카메라">
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">타입 *</div>
                    <select class="field-input" id="fieldType" onchange="onTypeChange()">
                        <option value="text">텍스트 (한 줄)</option>
                        <option value="textarea">텍스트 (여러 줄)</option>
                        <option value="select">드롭다운</option>
                        <option value="radio">라디오 버튼</option>
                        <option value="checkbox">체크박스 (다중)</option>
                        <option value="number">숫자</option>
                        <option value="date">날짜</option>
                    </select>
                </div>
                <div class="field-group">
                    <div class="field-label">섹션</div>
                    <select class="field-input" id="fieldSection">
                        <option value="basic">기본 정보</option>
                        <option value="equipment">장비 정보</option>
                        <option value="broadcast">방송 정보</option>
                        <option value="business">사업자 정보</option>
                        <option value="etc">기타</option>
                    </select>
                </div>
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">소분류 (선택)</div>
                    <input type="text" class="field-input" id="fieldSubsection" list="fieldSubsectionList" placeholder="예: PC, 카메라, 오디오, 조명" maxlength="50">
                    <datalist id="fieldSubsectionList"></datalist>
                    <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">같은 섹션 안에서 카드 그룹으로 묶이는 이름입니다.</div>
                </div>
                <div class="field-group">
                    <div class="field-label">필드 너비 (4열 기준)</div>
                    <select class="field-input" id="fieldWidth">
                        <option value="1">1/4 (한 줄에 4개)</option>
                        <option value="2" selected>2/4 (한 줄에 2개)</option>
                        <option value="3">3/4</option>
                        <option value="4">4/4 (한 줄 전체)</option>
                    </select>
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">우선순위 (정렬용)</div>
                <input type="number" class="field-input" id="fieldPriority" value="0" min="-100" max="100" step="1" style="max-width:140px;">
                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">숫자가 클수록 위에 표시됩니다. 같은 소분류 내 필드 순서 및 소분류 그룹 자체 순서에 영향 (그룹 우선순위 = 그룹 내 최대값).</div>
            </div>

            <div class="field-group" id="optionsWrap" style="display:none;">
                <div class="field-label">선택지 (한 줄에 하나씩) *</div>
                <textarea class="field-input" id="fieldOptions" rows="5" placeholder="옵션1&#10;옵션2&#10;옵션3"></textarea>
            </div>

            <div class="field-group">
                <div class="field-label">플레이스홀더</div>
                <input type="text" class="field-input" id="fieldPlaceholder" placeholder="입력 안내 문구">
            </div>

            <div class="field-group">
                <div class="field-label">도움말</div>
                <input type="text" class="field-input" id="fieldHelpText" placeholder="필드 아래 표시할 설명">
            </div>

            <div class="cf-toggle-row">
                <label>
                    <input type="checkbox" id="fieldRequired"> 필수 입력
                </label>
                <label>
                    <input type="checkbox" id="fieldActive" checked> 활성 (폼에 노출)
                </label>
                <label title="값과 함께 수량(개수)을 입력하는 작은 입력칸이 추가됩니다.">
                    <input type="checkbox" id="fieldHasQuantity"> 수량 입력 활성화
                </label>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-danger-outline" id="fieldDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteFieldFromModal()">삭제</button>
                <button class="btn-outline" onclick="closeFieldModal()">취소</button>
                <button class="btn-save" onclick="saveField()">저장</button>
            </div>
        </div>
    </div>

    {{-- 프로젝트 설정 (필드 정의 + 프로젝트 유형) --}}
    <div class="tab-panel" id="panel-projectFields">
        {{-- 설정 그룹의 상위 서브탭에서 직접 진입하므로 내부 서브탭 바는 숨김 --}}
        <div class="sub-tab-bar" style="display:none;">
            <button class="sub-tab-btn active" data-subtab="fields" onclick="switchProjectSubTab('fields')">필드 정의</button>
            <button class="sub-tab-btn" data-subtab="types" onclick="switchProjectSubTab('types')">프로젝트 유형</button>
        </div>

        {{-- 필드 정의 --}}
        <div class="sub-tab-panel active" id="subpanel-fields">
            <div class="cf-toolbar">
                <div class="cf-hint">
                    관리자가 정의한 필드는 <b>프로젝트 상세 화면</b>에 자동으로 노출됩니다.<br>
                    <span style="opacity:0.7;">예: 작업 인원, 차량 번호, 출고 장비 메모, 견적 금액 등 자유롭게 추가하세요.</span>
                </div>
                <button class="btn-add" style="margin-bottom:0;" onclick="openProjectFieldModal()">+ 필드 추가</button>
            </div>
            <div id="projectFieldsContainer"></div>
        </div>

        {{-- 프로젝트 유형 --}}
        <div class="sub-tab-panel" id="subpanel-types">
            <div class="cf-toolbar">
                <div class="cf-hint">
                    의뢰자 페이지에서 새 프로젝트 등록 시 선택하는 <b>프로젝트 유형</b>을 관리합니다.<br>
                    <span style="opacity:0.7;">기본 유형(방문세팅·원격세팅·디자인·단순문의·A/S·문제 해결)은 삭제할 수 없고 비활성화만 가능합니다.</span>
                </div>
                <button class="btn-add" style="margin-bottom:0;" onclick="openConsultTypeModal()">+ 유형 추가</button>
            </div>
            <div id="consultTypesContainer"></div>
        </div>
    </div>

    {{-- 프로젝트 필드 추가/편집 모달 --}}
    <div id="projectFieldModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) drgoModalMinimize(this, '프로젝트 필드 편집', '📝')">
        <div class="cf-modal">
            <h3>
                <span id="projectFieldModalTitle">+ 필드 추가</span>
                <button type="button" class="close-btn" onclick="closeProjectFieldModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub">프로젝트 정보에 노출될 사용자 정의 필드를 구성합니다.</div>
            <input type="hidden" id="projectFieldId">

            <div class="field-group">
                <div class="field-label">라벨 *</div>
                <input type="text" class="field-input" id="projectFieldLabel" placeholder="예: 작업 인원">
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">타입 *</div>
                    <select class="field-input" id="projectFieldType" onchange="onProjectFieldTypeChange()">
                        <option value="text">텍스트 (한 줄)</option>
                        <option value="textarea">텍스트 (여러 줄)</option>
                        <option value="select">드롭다운</option>
                        <option value="radio">라디오 버튼</option>
                        <option value="checkbox">체크박스 (다중)</option>
                        <option value="toggle">토글 (있음/없음)</option>
                        <option value="number">숫자</option>
                        <option value="date">날짜</option>
                    </select>
                </div>
                <div class="field-group">
                    <div class="field-label">섹션</div>
                    <select class="field-input" id="projectFieldSection">
                        <option value="basic">기본 정보</option>
                        <option value="equipment">장비 정보</option>
                        <option value="schedule">일정 정보</option>
                        <option value="billing">금액/결제</option>
                        <option value="etc">기타</option>
                    </select>
                </div>
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">소분류 (선택)</div>
                    <input type="text" class="field-input" id="projectFieldSubsection" list="projectFieldSubsectionList" placeholder="예: PC, 카메라, 오디오, 조명" maxlength="50">
                    <datalist id="projectFieldSubsectionList"></datalist>
                    <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">같은 섹션 안에서 카드 그룹으로 묶이는 이름입니다.</div>
                </div>
                <div class="field-group">
                    <div class="field-label">필드 너비 (4열 기준)</div>
                    <select class="field-input" id="projectFieldWidth">
                        <option value="1">1/4 (한 줄에 4개)</option>
                        <option value="2" selected>2/4 (한 줄에 2개)</option>
                        <option value="3">3/4</option>
                        <option value="4">4/4 (한 줄 전체)</option>
                    </select>
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">우선순위 (정렬용)</div>
                <input type="number" class="field-input" id="projectFieldPriority" value="0" min="-100" max="100" step="1" style="max-width:140px;">
                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">숫자가 클수록 위에 표시됩니다. 같은 소분류 내 필드 순서 및 소분류 그룹 자체 순서에 영향 (그룹 우선순위 = 그룹 내 최대값).</div>
            </div>

            <div class="field-group" id="projectOptionsWrap" style="display:none;">
                <div class="field-label">선택지 (한 줄에 하나씩) *</div>
                <textarea class="field-input" id="projectFieldOptions" rows="5" placeholder="옵션1&#10;옵션2&#10;옵션3"></textarea>
            </div>

            <div class="field-group">
                <div class="field-label">플레이스홀더</div>
                <input type="text" class="field-input" id="projectFieldPlaceholder" placeholder="입력 안내 문구">
            </div>

            <div class="field-group">
                <div class="field-label">도움말</div>
                <input type="text" class="field-input" id="projectFieldHelpText" placeholder="필드 아래 표시할 설명">
            </div>

            <div class="cf-toggle-row">
                <label>
                    <input type="checkbox" id="projectFieldRequired"> 필수 입력
                </label>
                <label>
                    <input type="checkbox" id="projectFieldActive" checked> 활성 (폼에 노출)
                </label>
                <label title="값과 함께 수량(개수)을 입력하는 작은 입력칸이 추가됩니다.">
                    <input type="checkbox" id="projectFieldHasQuantity"> 수량 입력 활성화
                </label>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-danger-outline" id="projectFieldDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteProjectFieldFromModal()">삭제</button>
                <button class="btn-outline" onclick="closeProjectFieldModal()">취소</button>
                <button class="btn-save" onclick="saveProjectField()">저장</button>
            </div>
        </div>
    </div>

    {{-- 담당자 관리 --}}
    <div class="tab-panel" id="panel-assignees">
        <div class="cf-toolbar">
            <div class="cf-hint">
                일정에 배정할 수 있는 담당자 풀입니다.<br>
                <span style="opacity:0.7;">시스템 사용자(직원)는 자동 동기화됩니다. <b>외부 담당자</b>(프리랜서/협력사 등)는 여기서 직접 추가하세요.</span>
            </div>
            <button class="btn-add" style="margin-bottom:0;" onclick="openAssigneeModal(null)">+ 외부 담당자 추가</button>
        </div>
        <div id="assigneesContainer"></div>
    </div>

    {{-- 담당자 추가/편집 모달 --}}
    <div id="assigneeModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) closeAssigneeModal()">
        <div class="cf-modal">
            <h3>
                <span id="assigneeModalTitle">+ 외부 담당자 추가</span>
                <button type="button" class="close-btn" onclick="closeAssigneeModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub" id="assigneeModalSub">외부 인력(프리랜서/협력사 등)을 담당자 풀에 추가합니다.</div>
            <input type="hidden" id="assigneeIdInput">
            <input type="hidden" id="assigneeIsInternal" value="0">

            <div class="field-group">
                <div class="field-label">이름 *</div>
                <input type="text" class="field-input" id="assigneeNameInput" placeholder="예: 황진선">
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">표시 순서</div>
                    <input type="number" class="field-input" id="assigneeOrderInput" value="0">
                </div>
                <div class="field-group">
                    <div class="field-label">상태</div>
                    <select class="field-input" id="assigneeActiveSel">
                        <option value="1">활성</option>
                        <option value="0">비활성</option>
                    </select>
                </div>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-danger-outline" id="assigneeDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteAssigneeFromModal()">삭제</button>
                <button class="btn-outline" onclick="closeAssigneeModal()">취소</button>
                <button class="btn-save" onclick="saveAssignee()">저장</button>
            </div>
        </div>
    </div>

    {{-- 캘린더 카테고리 --}}
    <div class="tab-panel" id="panel-calendarCategories">
        <div class="cf-toolbar">
            <div class="cf-hint">
                캘린더 일정에 사용되는 카테고리의 <b>라벨</b>과 <b>색상</b>을 변경하거나, 새 카테고리를 추가할 수 있습니다.<br>
                <span style="opacity:0.7;">변경 사항은 페이지를 새로 고침해야 모든 화면에 반영됩니다.</span>
            </div>
            <button class="btn-add" style="margin-bottom:0;" onclick="openNewCalendarCategoryModal()">+ 카테고리 추가</button>
        </div>
        <div id="calendarCategoriesContainer"></div>
    </div>

    {{-- 프로젝트 유형 모달 --}}
    <div id="consultTypeModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) drgoModalMinimize(this, '프로젝트 유형 편집', '🏷')">
        <div class="cf-modal">
            <h3>
                <span id="consultTypeModalTitle">+ 프로젝트 유형 추가</span>
                <button type="button" class="close-btn" onclick="closeConsultTypeModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub">프로젝트 유형 정의. key는 비워두면 라벨에서 자동 생성됩니다.</div>
            <input type="hidden" id="consultTypeId">

            <div class="field-group">
                <div class="field-label">라벨 (한글) *</div>
                <input type="text" class="field-input" id="consultTypeLabel" placeholder="예: 라이브 코칭">
            </div>

            <div class="field-group">
                <div class="field-label">key (영문, 선택)</div>
                <input type="text" class="field-input" id="consultTypeKey" placeholder="자동 생성 — 예: live_coaching">
            </div>

            <div class="field-group">
                <div class="field-label">정렬 순서</div>
                <input type="number" class="field-input" id="consultTypeSortOrder" placeholder="작을수록 먼저 표시">
            </div>

            <div class="cf-toggle-row">
                <label>
                    <input type="checkbox" id="consultTypeActive" checked> 활성 (드롭다운에 노출)
                </label>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-danger-outline" id="consultTypeDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteConsultType()">삭제</button>
                <button class="btn-outline" onclick="closeConsultTypeModal()">취소</button>
                <button class="btn-save" onclick="saveConsultType()">저장</button>
            </div>
        </div>
    </div>

    {{-- 캘린더 카테고리 추가 모달 --}}
    <div id="newCatModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) closeNewCalendarCategoryModal()">
        <div class="cf-modal" style="max-width:480px;">
            <h3>
                <span>+ 카테고리 추가</span>
                <button type="button" class="close-btn" onclick="closeNewCalendarCategoryModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub">새로운 일정 분류를 추가합니다. 기본 카테고리(방문의뢰·원격·사내업무 등)와 동일한 방식으로 동작합니다.</div>

            <div class="field-group">
                <div class="field-label">라벨 *</div>
                <input type="text" class="field-input" id="newCatLabel" placeholder="예: 외부 미팅">
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">배경색 *</div>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <input type="color" id="newCatColor" value="#7a98c8" oninput="document.getElementById('newCatColorHex').value=this.value; updateNewCatPreview();" style="width:42px; height:34px; padding:0; border-radius:6px; cursor:pointer; border:1px solid var(--border);">
                        <input type="text" id="newCatColorHex" value="#7a98c8" class="field-input" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){document.getElementById('newCatColor').value=this.value;} updateNewCatPreview();" style="flex:1;">
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">글자색 *</div>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <input type="color" id="newCatTextColor" value="#0a1828" oninput="document.getElementById('newCatTextColorHex').value=this.value; updateNewCatPreview();" style="width:42px; height:34px; padding:0; border-radius:6px; cursor:pointer; border:1px solid var(--border);">
                        <input type="text" id="newCatTextColorHex" value="#0a1828" class="field-input" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){document.getElementById('newCatTextColor').value=this.value;} updateNewCatPreview();" style="flex:1;">
                    </div>
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">미리보기</div>
                <div id="newCatPreview" style="display:inline-block; padding:6px 14px; border-radius:6px; font-weight:600; background:#7a98c8; color:#0a1828;">외부 미팅</div>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-outline" onclick="closeNewCalendarCategoryModal()">취소</button>
                <button class="btn-save" onclick="createCalendarCategory()">추가</button>
            </div>
        </div>
    </div>

    {{-- 작업 유형 --}}
    <div class="tab-panel" id="panel-workTypes">
        <div class="cf-toolbar">
            <div class="cf-hint">
                프로젝트의 <b>작업 유형</b>(단순·문제해결·일반·중계 등)을 관리합니다.<br>
                <span style="opacity:0.7;">프로젝트 유형을 먼저 고르면 그에 속한 작업 유형만 드롭다운에 노출됩니다(종속). 유형을 지정하지 않으면 모든 프로젝트 유형에서 공통으로 노출됩니다.</span>
            </div>
            <button class="btn-add" style="margin-bottom:0;" onclick="openWorkTypeModal()">+ 작업 유형 추가</button>
        </div>
        <div id="workTypesContainer"></div>
    </div>

    {{-- 작업 유형 모달 --}}
    <div id="wtModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) drgoModalMinimize(this, '작업 유형 편집', '⚙️')">
        <div class="cf-modal">
            <h3>
                <span id="wtModalTitle">+ 작업 유형</span>
                <button type="button" class="close-btn" onclick="closeWorkTypeModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub">새 프로젝트 등록 시 '프로젝트 유형'에 따라 선택할 수 있는 작업 유형을 관리합니다.</div>
            <input type="hidden" id="wtId">

            <div class="field-group">
                <div class="field-label">라벨 (한글) *</div>
                <input type="text" class="field-input" id="wtLabel" placeholder="예: 라이브 송출">
            </div>

            <div class="field-group">
                <div class="field-label">key (영문, 선택)</div>
                <input type="text" class="field-input" id="wtKey" placeholder="자동 생성 — 예: live_streaming">
            </div>

            <div class="field-group">
                <div class="field-label">종속 프로젝트 유형 (선택 안 하면 모든 유형에 공통 노출)</div>
                <select class="field-input" id="wtTypeKey">
                    <option value="">공통 (모든 유형)</option>
                </select>
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">정렬 순서</div>
                    <input type="number" class="field-input" id="wtSortOrder" placeholder="작을수록 먼저">
                </div>
                <div class="field-group">
                    <div class="field-label">활성</div>
                    <div class="cf-toggle-row" style="margin:0; padding:8px 10px;">
                        <label><input type="checkbox" id="wtIsActive" checked> 드롭다운에 노출</label>
                    </div>
                </div>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-danger-outline" id="wtDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteWorkType()">삭제</button>
                <button class="btn-outline" onclick="closeWorkTypeModal()">취소</button>
                <button class="btn-save" onclick="saveWorkType()">저장</button>
            </div>
        </div>
    </div>

    {{-- 보고서 템플릿 --}}
    <div class="tab-panel" id="panel-reportTemplates">
        <div class="cf-toolbar">
            <div class="cf-hint">
                방문 보고서 작성 시 사용할 <b>템플릿</b>을 관리합니다.<br>
                <span style="opacity:0.7;">기본 템플릿(★)은 새 보고서 작성 시 자동 불러올 수 있습니다.</span>
            </div>
            <button class="btn-add" style="margin-bottom:0;" onclick="openReportTemplateModal()">+ 템플릿 추가</button>
        </div>
        <div id="reportTemplatesContainer"></div>
    </div>

    {{-- 보고서 템플릿 모달 --}}
    <div id="rtModalOverlay" class="cf-modal-overlay" onclick="if(event.target===this) drgoModalMinimize(this, '보고서 템플릿 편집', '📋')">
        <div class="cf-modal" style="max-width:900px;">
            <h3>
                <span id="rtModalTitle">+ 보고서 템플릿</span>
                <button type="button" class="close-btn" onclick="closeReportTemplateModal()"><x-icon name="close" :size="15"/></button>
            </h3>
            <div class="cf-modal-sub">방문 보고서 작성 시 자동으로 불러올 수 있는 본문 템플릿을 정의합니다.</div>
            <input type="hidden" id="rtId">

            <div class="field-group">
                <div class="field-label">템플릿 이름 *</div>
                <input type="text" class="field-input" id="rtName" placeholder="예: 일반 방문 세팅 보고서">
            </div>

            <div class="field-group">
                <div class="field-label">본문 (Tiptap)</div>
                <div class="tiptap-wrap">
                    <div class="tiptap-toolbar" id="rtToolbar">
                        <button type="button" data-rt-cmd="h1" title="제목 1">H1</button>
                        <button type="button" data-rt-cmd="h2" title="제목 2">H2</button>
                        <button type="button" data-rt-cmd="h3" title="제목 3">H3</button>
                        <div class="sep"></div>
                        <button type="button" data-rt-cmd="bold" title="굵게"><b>B</b></button>
                        <button type="button" data-rt-cmd="italic" title="기울임"><i>I</i></button>
                        <button type="button" data-rt-cmd="strike" title="취소선"><s>S</s></button>
                        <button type="button" data-rt-cmd="code" title="인라인 코드">&lt;&gt;</button>
                        <div class="sep"></div>
                        <button type="button" data-rt-cmd="bulletList" title="글머리 목록">•</button>
                        <button type="button" data-rt-cmd="orderedList" title="번호 목록">1.</button>
                        <button type="button" data-rt-cmd="blockquote" title="인용">"</button>
                        <button type="button" data-rt-cmd="codeBlock" title="코드 블록">{ }</button>
                        <button type="button" data-rt-cmd="hr" title="구분선">—</button>
                        <div class="sep"></div>
                        <button type="button" data-rt-cmd="alignLeft" title="좌측 정렬" style="font-size:11px;">≡←</button>
                        <button type="button" data-rt-cmd="alignCenter" title="중앙 정렬" style="font-size:11px;">≡</button>
                        <button type="button" data-rt-cmd="alignRight" title="우측 정렬" style="font-size:11px;">→≡</button>
                    </div>
                    <div id="rtEditor"></div>
                </div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">템플릿에는 이미지를 포함하지 않는 것을 권장합니다 (텍스트 골격 위주).</div>
            </div>

            <div class="cf-modal-row">
                <div class="field-group">
                    <div class="field-label">정렬 순서</div>
                    <input type="number" class="field-input" id="rtSortOrder" placeholder="작을수록 먼저">
                </div>
                <div class="field-group">
                    <div class="field-label">기본 / 활성</div>
                    <div class="cf-toggle-row" style="margin:0; padding:8px 10px;">
                        <label><input type="checkbox" id="rtIsDefault"> <x-icon name="star" :size="14"/> 기본 (자동 불러오기)</label>
                        <label><input type="checkbox" id="rtIsActive" checked> 활성</label>
                    </div>
                </div>
            </div>

            <div class="cf-modal-actions">
                <button class="btn-danger-outline" id="rtDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteReportTemplate()">삭제</button>
                <button class="btn-outline" onclick="closeReportTemplateModal()">취소</button>
                <button class="btn-save" onclick="saveReportTemplate()">저장</button>
            </div>
        </div>
    </div>

    {{-- 내방 옵션 (미팅/내방 카테고리 체크박스) --}}
    <div class="tab-panel" id="panel-visitOptions">
        <div class="settings-form">
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:12px; line-height:1.6;">
                캘린더에서 <b>미팅/내방</b> 카테고리를 선택하면 주소 검색 위에 나타나는 <b>체크박스 선택지</b>를 관리합니다.<br>
                한 줄에 하나씩 입력하세요. (체크 시 주소 검색은 숨겨집니다)
            </div>
            <div class="field-group">
                <div class="field-label">내방 옵션 (한 줄에 하나)</div>
                <textarea class="field-input" id="visitOptionsText" rows="7" placeholder="예:&#10;사무실 방문&#10;화상 미팅&#10;외부 미팅" style="resize:vertical; line-height:1.7; font-family:inherit;">{{ $sellerSettings['calendar_visit_options'] ?? '' }}</textarea>
            </div>
            <div style="display:flex; align-items:center;">
                <button class="btn-save" onclick="saveVisitOptions()">저장</button>
                <span class="save-msg" id="visitOptionsSaveMsg">저장되었습니다.</span>
            </div>
        </div>
    </div>

    {{-- 프로젝트 취소 사유 --}}
    <div class="tab-panel" id="panel-cancelReasons">
        <div class="settings-form">
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:12px; line-height:1.6;">
                프로젝트 <b>취소 모달</b>에 표시되는 취소 사유 선택지를 관리합니다.<br>
                한 줄에 하나씩 입력하세요 — 줄을 추가하면 생성, 고치면 수정, 지우면 삭제됩니다.<br>
                <b>"기타"</b>(상세 사유 직접 입력)는 목록과 별개로 항상 마지막에 표시됩니다.
            </div>
            <div class="field-group">
                <div class="field-label">취소 사유 (한 줄에 하나)</div>
                <textarea class="field-input" id="cancelReasonsText" rows="7" placeholder="예:&#10;의뢰자 연락 두절&#10;의뢰자 사정으로 취소&#10;일정이 맞지 않음" style="resize:vertical; line-height:1.7; font-family:inherit;">{{ $sellerSettings['project_cancel_reasons'] ?? "의뢰자 연락 두절\n의뢰자 사정으로 취소\n일정이 맞지 않음" }}</textarea>
            </div>
            <div style="display:flex; align-items:center;">
                <button class="btn-save" onclick="saveCancelReasons()">저장</button>
                <span class="save-msg" id="cancelReasonsSaveMsg">저장되었습니다.</span>
            </div>
        </div>
    </div>

    {{-- 판매처 설정 --}}
    <div class="tab-panel" id="panel-seller">
        <div class="settings-form">
            <div class="field-group">
                <div class="field-label">상호명</div>
                <input class="field-input" id="sellerName" value="{{ $sellerSettings['seller_name'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">사업자번호</div>
                <input class="field-input" id="sellerBizNo" value="{{ $sellerSettings['seller_biz_no'] ?? '' }}" placeholder="000-00-00000">
            </div>
            <div class="field-group">
                <div class="field-label">주소</div>
                <input class="field-input" id="sellerAddress" value="{{ $sellerSettings['seller_address'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">업태</div>
                <input class="field-input" id="sellerBizType" value="{{ $sellerSettings['seller_biz_type'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">종목</div>
                <input class="field-input" id="sellerBizItem" value="{{ $sellerSettings['seller_biz_item'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">대표전화</div>
                <input class="field-input" id="sellerPhone" value="{{ $sellerSettings['seller_phone'] ?? '' }}">
            </div>
            <div style="display:flex; align-items:center;">
                <button class="btn-save" onclick="saveSellerSettings()">저장</button>
                <span class="save-msg" id="sellerSaveMsg">저장되었습니다.</span>
            </div>
        </div>
    </div>
</div>

{{-- 계정 수정 모달 (master 전용) --}}
@if(Auth::user()->role === 'master')
<div class="account-modal-overlay" id="accountModalOverlay" onclick="if(event.target===this) closeAccountModal()">
    <div class="account-modal">
        <h3>계정 정보 수정 <button class="close-btn" onclick="closeAccountModal()"><x-icon name="close" :size="15"/></button></h3>
        <input type="hidden" id="accUserId">
        <div class="field-group">
            <div class="field-label">아이디 (로그인 ID)</div>
            <input class="field-input" id="accUsername">
        </div>
        <div class="field-group">
            <div class="field-label">이름</div>
            <input class="field-input" id="accDisplayName">
        </div>
        <div class="field-group">
            <div class="field-label">이메일</div>
            <input class="field-input" type="email" id="accEmail">
        </div>
        <div class="field-group">
            <div class="field-label">비밀번호 변경 <span style="font-size:10px;color:var(--text-muted);">(입력 시에만 변경)</span></div>
            <div style="position:relative;">
                <input class="field-input" type="password" id="accPassword" placeholder="새 비밀번호 (8자 이상)" style="padding-right:40px;">
                <button type="button" onclick="togglePwVisibility('accPassword',this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px;padding:4px;"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-0.15em"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeAccountModal()">취소</button>
            <button class="btn-save" onclick="saveAccount()">저장</button>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const currentRole = @json(Auth::user()->role);
const PERM_GROUPS = [
    { title: '캘린더', perms: [{ key: 'calendar.view', label: '조회' }, { key: 'calendar.edit', label: '편집' }, { key: 'calendar.backup', label: '백업/내보내기' }] },
    { title: '의뢰자', perms: [{ key: 'clients.view', label: '조회' }, { key: 'clients.edit', label: '편집' }] },
    { title: '프로젝트', perms: [{ key: 'projects.view', label: '조회' }, { key: 'projects.edit', label: '편집' }] },
    { title: '태그', perms: [{ key: 'tags.manage', label: '태그 수정(소분류 추가/삭제)' }] },
    { title: '재고', perms: [{ key: 'inventory.view', label: '조회' }, { key: 'inventory.edit', label: '편집' }] },
    { title: '견적서', perms: [{ key: 'estimates.view', label: '조회' }, { key: 'estimates.edit', label: '편집' }] },
    { title: '입금 내역', perms: [{ key: 'deposits.view', label: '조회' }] },
    { title: '문서', perms: [{ key: 'documents.edit', label: '편집' }] },
];
const ALL_PERMS = PERM_GROUPS.flatMap(g => g.perms);

function renderPermToggles(containerId, activePerms = []) {
    return PERM_GROUPS.map(g => `
        <div class="perm-section">
            <div class="perm-section-title">${g.title}</div>
            <div class="perm-grid">
                ${g.perms.map(p => {
                    const checked = activePerms.includes(p.key) ? 'checked' : '';
                    return `<label class="perm-toggle">
                        <input type="checkbox" value="${p.key}" ${checked}>
                        <span class="perm-switch"></span>
                        <span class="perm-label">${p.label}</span>
                    </label>`;
                }).join('')}
            </div>
        </div>
    `).join('');
}

let teamsList = [];

// ── 탭 전환 ──
document.querySelectorAll('#adminTabBar .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('#adminTabBar .tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + tab));
        localStorage.setItem('adminLastTab', tab); // 새로고침 후 마지막 탭 복원용
        if (tab === 'users') loadUsers();
        if (tab === 'teams') loadTeams();
        if (tab === 'assignees') loadAssigneesAdmin();
        if (tab === 'settings') {
            const last = sessionStorage.getItem('drgo_admin_setting_subtab') || 'clientFields';
            switchSettingsSubTab(last);
        }
    });
});
// 마지막 본 탭 복원 (기본: 로그인 기록)
(function () {
    const last = localStorage.getItem('adminLastTab');
    if (last && last !== 'logs') {
        document.querySelector('#adminTabBar .tab-btn[data-tab="' + last + '"]')?.click();
    }
})();

// 프로젝트 설정 탭 내부 서브탭 전환 (legacy, projectFields 내부 — 호환용)
function switchProjectSubTab(sub) {
    document.querySelectorAll('#panel-projectFields .sub-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.subtab === sub));
    document.querySelectorAll('#panel-projectFields .sub-tab-panel').forEach(p => p.classList.toggle('active', p.id === 'subpanel-' + sub));
}

// 설정 그룹 서브탭 전환
const SETTINGS_PANEL_MAP = {
    clientFields: { panel: 'panel-clientFields', load: () => typeof loadClientFields === 'function' && loadClientFields() },
    projectFields: { panel: 'panel-projectFields', load: () => { typeof loadProjectFields === 'function' && loadProjectFields(); typeof loadConsultTypes === 'function' && loadConsultTypes(); switchProjectSubTab('fields'); } },
    projectTypes:  { panel: 'panel-projectFields', load: () => { typeof loadConsultTypes === 'function' && loadConsultTypes(); switchProjectSubTab('types'); } },
    workTypes:     { panel: 'panel-workTypes', load: () => typeof loadWorkTypes === 'function' && loadWorkTypes() },
    calendarCategories: { panel: 'panel-calendarCategories', load: () => typeof loadCalendarCategories === 'function' && loadCalendarCategories() },
    reportTemplates: { panel: 'panel-reportTemplates', load: () => typeof loadReportTemplates === 'function' && loadReportTemplates() },
    visitOptions: { panel: 'panel-visitOptions', load: () => {} },
    cancelReasons: { panel: 'panel-cancelReasons', load: () => {} },
    seller: { panel: 'panel-seller', load: () => {} },
};

function switchSettingsSubTab(sub) {
    document.querySelectorAll('#settingsSubTabBar .sub-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.subtab === sub));
    const conf = SETTINGS_PANEL_MAP[sub];
    if (!conf) return;
    // 설정 그룹에 속한 모든 패널은 hidden, 선택된 panel만 show
    Object.values(SETTINGS_PANEL_MAP).forEach(c => {
        const el = document.getElementById(c.panel);
        if (el) el.classList.remove('active');
    });
    const target = document.getElementById(conf.panel);
    if (target) {
        target.classList.add('active');
        // 설정 그룹 컨테이너 안으로 이동(아직 안에 없다면)
        const settingsContent = document.getElementById('settingsContent');
        if (settingsContent && target.parentElement !== settingsContent) settingsContent.appendChild(target);
    }
    sessionStorage.setItem('drgo_admin_setting_subtab', sub);
    conf.load();
}

// ── 필드 카드 드래그-앤-드롭 정렬 (의뢰자/프로젝트 공용) ──
// 같은 섹션 안에서만 순서를 바꿀 수 있고, 드롭 후 즉시 /reorder API 호출
let cfDragSrc = null;

function enableCfDrag(root, kind) {
    if (!root) return;
    const apiBase = kind === 'project' ? '/api/admin/project-fields' : '/api/admin/client-fields';
    const stateArr = kind === 'project' ? () => allProjectFields : () => allFields;
    const setStateArr = (arr) => {
        if (kind === 'project') allProjectFields = arr;
        else allFields = arr;
    };

    root.querySelectorAll('.cf-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', (e) => {
            cfDragSrc = card;
            card.classList.add('dragging');
            try { e.dataTransfer.setData('text/plain', card.dataset.id); } catch (err) {}
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            root.querySelectorAll('.cf-card').forEach(c => c.classList.remove('drag-over-top', 'drag-over-bottom'));
            cfDragSrc = null;
        });
        card.addEventListener('dragover', (e) => {
            if (!cfDragSrc || cfDragSrc === card) return;
            if (cfDragSrc.dataset.section !== card.dataset.section) return; // 같은 섹션만
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const rect = card.getBoundingClientRect();
            const before = (e.clientY - rect.top) < rect.height / 2;
            card.classList.toggle('drag-over-top', before);
            card.classList.toggle('drag-over-bottom', !before);
        });
        card.addEventListener('dragleave', () => {
            card.classList.remove('drag-over-top', 'drag-over-bottom');
        });
        card.addEventListener('drop', async (e) => {
            if (!cfDragSrc || cfDragSrc === card) return;
            if (cfDragSrc.dataset.section !== card.dataset.section) return;
            e.preventDefault();
            const before = card.classList.contains('drag-over-top');
            card.classList.remove('drag-over-top', 'drag-over-bottom');
            const parent = card.parentElement;
            if (before) parent.insertBefore(cfDragSrc, card);
            else parent.insertBefore(cfDragSrc, card.nextSibling);
            // 같은 섹션 안 카드 순서 → 새 sort_order 로 직렬화 (섹션 간 충돌 방지 위해 전 섹션 합쳐서 전송)
            const newIds = [];
            root.querySelectorAll('.cf-grid').forEach(grid => {
                grid.querySelectorAll('.cf-card').forEach(c => newIds.push(parseInt(c.dataset.id, 10)));
            });
            // 로컬 상태도 새 순서로 갱신
            const byId = new Map(stateArr().map(f => [f.id, f]));
            const reordered = newIds.map(id => byId.get(id)).filter(Boolean);
            setStateArr(reordered);

            try {
                const res = await fetch(`${apiBase}/reorder`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ order: newIds }),
                });
                if (!res.ok) throw new Error('reorder failed');
            } catch (err) {
                alert('순서 저장 실패 — 다시 시도해 주세요.');
                // 실패 시 서버 상태로 재동기화
                if (kind === 'project') await loadProjectFields();
                else await loadClientFields();
            }
        });
    });
}

// ── 프로젝트 필드 관리 (의뢰자 필드와 동일 패턴) ──
let allProjectFields = [];
const PROJECT_FIELD_SECTIONS = { basic:'기본 정보', equipment:'장비 정보', schedule:'일정 정보', billing:'금액/결제', etc:'기타' };

async function loadProjectFields() {
    const res = await fetch('/api/admin/project-fields', { headers:{ 'Accept':'application/json' } });
    allProjectFields = await res.json();
    renderProjectFields();
}
function renderProjectFields() {
    const container = document.getElementById('projectFieldsContainer');
    if (!allProjectFields.length) {
        container.innerHTML = `<div class="cf-empty">
            <div class="cf-empty-icon"><x-icon name="clip" :size="30"/></div>
            <div class="cf-empty-title">정의된 필드가 없습니다</div>
            <div class="cf-empty-sub">우측 상단 <b>+ 필드 추가</b> 버튼으로 첫 필드를 만들어 보세요.</div>
        </div>`;
        return;
    }
    const grouped = {};
    allProjectFields.forEach(f => {
        const sec = f.section || 'etc';
        (grouped[sec] = grouped[sec] || []).push(f);
    });
    let html = '';
    Object.entries(PROJECT_FIELD_SECTIONS).forEach(([key, label]) => {
        if (!grouped[key]) return;
        html += `<div class="cf-section">
            <div class="cf-section-head">
                <span class="cf-section-title">${escHtml(label)}</span>
                <span class="cf-section-count">${grouped[key].length}개</span>
            </div>
            <div class="cf-grid">`;
        grouped[key].forEach(f => {
            const required = f.is_required ? '<span class="cf-required">*</span>' : '';
            const inactive = f.is_active ? '' : ' inactive';
            const typeLabel = (typeof FIELD_TYPES !== 'undefined' ? FIELD_TYPES[f.type] : f.type) || f.type;
            const optsChip = (f.options && f.options.length) ? `<span class="cf-chip">옵션 ${f.options.length}</span>` : '';
            const statusChip = f.is_active ? '' : '<span class="cf-chip muted">비활성</span>';
            const reqChip = f.is_required ? '<span class="cf-chip" style="color:var(--red); border-color:var(--red);">필수</span>' : '';
            html += `<div class="cf-card${inactive}" data-id="${f.id}" data-section="${escHtml(key)}" draggable="true" onclick="editProjectField(${f.id})">
                <span class="cf-drag-handle" title="드래그해서 순서 변경" aria-hidden="true">⋮⋮</span>
                <div class="cf-card-row">
                    <div class="cf-card-label">${escHtml(f.label)} ${required}</div>
                    <span class="cf-type-badge cf-type-${escHtml(f.type)}">${escHtml(typeLabel)}</span>
                </div>
                <div class="cf-card-row">
                    <span class="cf-card-key">${escHtml(f.key)}</span>
                    <div class="cf-card-meta">${reqChip}${optsChip}${statusChip}</div>
                </div>
            </div>`;
        });
        html += `</div></div>`;
    });
    container.innerHTML = html;
    enableCfDrag(container, 'project');
}
function openProjectFieldModal(field) {
    const m = document.getElementById('projectFieldModalOverlay');
    m.classList.add('open');
    document.getElementById('projectFieldModalTitle').textContent = field ? '필드 편집' : '+ 필드 추가';
    document.getElementById('projectFieldId').value = field?.id || '';
    document.getElementById('projectFieldLabel').value = field?.label || '';
    document.getElementById('projectFieldType').value = field?.type || 'text';
    document.getElementById('projectFieldSection').value = field?.section || 'basic';
    document.getElementById('projectFieldSubsection').value = field?.subsection || '';
    // 자동완성 옵션 갱신
    const pSubDl = document.getElementById('projectFieldSubsectionList');
    pSubDl.innerHTML = [...new Set(allProjectFields.map(x => x.subsection).filter(Boolean))]
        .map(s => `<option value="${escHtml(s)}">`).join('');
    document.getElementById('projectFieldPriority').value = String(field?.priority ?? 0);
    document.getElementById('projectFieldWidth').value = String(field?.width || 2);
    document.getElementById('projectFieldOptions').value = (field?.options || []).join('\n');
    document.getElementById('projectFieldPlaceholder').value = field?.placeholder || '';
    document.getElementById('projectFieldHelpText').value = field?.help_text || '';
    document.getElementById('projectFieldRequired').checked = !!field?.is_required;
    document.getElementById('projectFieldActive').checked = field ? !!field.is_active : true;
    document.getElementById('projectFieldHasQuantity').checked = !!field?.has_quantity;
    document.getElementById('projectFieldDeleteBtn').style.display = field ? 'inline-block' : 'none';
    onProjectFieldTypeChange();
}
function closeProjectFieldModal() { document.getElementById('projectFieldModalOverlay').classList.remove('open'); }
function onProjectFieldTypeChange() {
    const type = document.getElementById('projectFieldType').value;
    const needsOptions = ['select','radio','checkbox'].includes(type);
    document.getElementById('projectOptionsWrap').style.display = needsOptions ? 'block' : 'none';
}
function editProjectField(id) {
    const f = allProjectFields.find(x => x.id === id);
    if (f) openProjectFieldModal(f);
}
async function saveProjectField() {
    const id = document.getElementById('projectFieldId').value;
    const label = document.getElementById('projectFieldLabel').value.trim();
    const type = document.getElementById('projectFieldType').value;
    if (!label) return alert('라벨을 입력하세요.');

    const optionsRaw = document.getElementById('projectFieldOptions').value.trim();
    const options = optionsRaw ? optionsRaw.split('\n').map(s => s.trim()).filter(Boolean) : null;
    if (['select','radio','checkbox'].includes(type) && (!options || !options.length)) {
        return alert('선택지를 한 줄에 하나씩 입력하세요.');
    }

    const body = {
        label,
        type,
        section: document.getElementById('projectFieldSection').value,
        subsection: document.getElementById('projectFieldSubsection').value.trim() || null,
        priority: parseInt(document.getElementById('projectFieldPriority').value, 10) || 0,
        width: parseInt(document.getElementById('projectFieldWidth').value, 10) || 2,
        options,
        placeholder: document.getElementById('projectFieldPlaceholder').value || null,
        help_text: document.getElementById('projectFieldHelpText').value || null,
        is_required: document.getElementById('projectFieldRequired').checked,
        is_active: document.getElementById('projectFieldActive').checked,
        has_quantity: document.getElementById('projectFieldHasQuantity').checked,
    };

    const url = id ? `/api/admin/project-fields/${id}` : '/api/admin/project-fields';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {
        method,
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        body: JSON.stringify(body)
    });
    if (res.ok) {
        closeProjectFieldModal();
        await loadProjectFields();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors || {}).flat().join('\n')));
    }
}
async function deleteProjectFieldFromModal() {
    if (!confirm('이 필드를 삭제하시겠습니까?\n기존에 입력된 값은 DB에 남아있지만, 폼에서는 더 이상 노출되지 않습니다.')) return;
    const id = document.getElementById('projectFieldId').value;
    const res = await fetch(`/api/admin/project-fields/${id}`, { method:'DELETE', headers:{ 'X-CSRF-TOKEN':CSRF } });
    if (res.ok) {
        closeProjectFieldModal();
        await loadProjectFields();
    } else alert('삭제 실패');
}

// ── 담당자 관리 ──
let assigneesAdminList = [];
async function loadAssigneesAdmin() {
    const res = await fetch('/api/assignees/manage', { headers:{ 'Accept':'application/json' } });
    if (!res.ok) { document.getElementById('assigneesContainer').innerHTML = '<div style="padding:30px; text-align:center; color:var(--red);">로드 실패</div>'; return; }
    assigneesAdminList = await res.json();
    renderAssigneesAdmin();
}
function renderAssigneesAdmin() {
    const container = document.getElementById('assigneesContainer');
    if (!assigneesAdminList.length) {
        container.innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-muted);">등록된 담당자가 없습니다.</div>';
        return;
    }
    const internal = assigneesAdminList.filter(a => !a.is_external);
    const external = assigneesAdminList.filter(a => a.is_external);

    const row = a => {
        const badge = a.is_external
            ? '<span style="font-size:10px; padding:2px 7px; border-radius:4px; background:rgba(232,137,74,0.18); color:var(--teal); font-weight:600;">외부</span>'
            : `<span style="font-size:10px; padding:2px 7px; border-radius:4px; background:rgba(138,180,200,0.18); color:var(--blue); font-weight:600;">내부 · ${escAd(a.user?.role||'')}</span>`;
        const status = a.is_active
            ? '<span style="font-size:10px; padding:2px 7px; border-radius:4px; background:rgba(122,200,122,0.18); color:var(--green); font-weight:600;">활성</span>'
            : '<span style="font-size:10px; padding:2px 7px; border-radius:4px; background:var(--surface2); color:var(--text-muted); font-weight:600;">비활성</span>';
        return `<div style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--surface); border:1px solid var(--border); border-radius:10px; margin-bottom:6px;">
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; font-size:13px;">${escAd(a.name)}</div>
                <div style="display:flex; gap:6px; margin-top:4px; align-items:center; flex-wrap:wrap;">
                    ${badge}${status}
                    <span style="font-size:11px; color:var(--text-muted);">순서: ${a.display_order}</span>
                </div>
            </div>
            <button class="btn-outline" style="padding:5px 10px; font-size:11px;" onclick="openAssigneeModal(${a.id})">편집</button>
        </div>`;
    };

    let html = '';
    if (external.length) {
        html += '<div style="font-size:11px; color:var(--text-muted); margin:8px 0 6px; letter-spacing:0.04em;">외부 담당자 (수동 등록)</div>';
        html += external.map(row).join('');
    }
    if (internal.length) {
        html += '<div style="font-size:11px; color:var(--text-muted); margin:14px 0 6px; letter-spacing:0.04em;">내부 담당자 (시스템 사용자 자동 동기화)</div>';
        html += internal.map(row).join('');
    }
    container.innerHTML = html;
}
function escAd(s){ return String(s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function openAssigneeModal(id) {
    const a = id ? assigneesAdminList.find(x => x.id === id) : null;
    const isEdit = !!a;
    const isInternal = isEdit && !a.is_external;
    document.getElementById('assigneeModalTitle').textContent = isEdit
        ? (isInternal ? '내부 담당자 편집' : '외부 담당자 편집')
        : '+ 외부 담당자 추가';
    document.getElementById('assigneeModalSub').textContent = isInternal
        ? '내부 담당자(시스템 사용자)는 이름이 사용자 계정과 동기화되므로 표시 순서·활성 여부만 변경할 수 있습니다.'
        : '외부 인력(프리랜서/협력사 등)을 담당자 풀에 추가합니다.';
    document.getElementById('assigneeIdInput').value = id || '';
    document.getElementById('assigneeIsInternal').value = isInternal ? '1' : '0';
    const nameEl = document.getElementById('assigneeNameInput');
    nameEl.value = a?.name || '';
    nameEl.disabled = isInternal;
    document.getElementById('assigneeOrderInput').value = a?.display_order ?? 0;
    document.getElementById('assigneeActiveSel').value = a?.is_active === false ? '0' : '1';
    document.getElementById('assigneeDeleteBtn').style.display = (isEdit && !isInternal) ? 'inline-block' : 'none';
    document.getElementById('assigneeModalOverlay').classList.add('open');
}
function closeAssigneeModal() {
    document.getElementById('assigneeModalOverlay').classList.remove('open');
}
async function saveAssignee() {
    const id = document.getElementById('assigneeIdInput').value;
    const isInternal = document.getElementById('assigneeIsInternal').value === '1';
    const body = {
        display_order: parseInt(document.getElementById('assigneeOrderInput').value, 10) || 0,
        is_active: document.getElementById('assigneeActiveSel').value === '1',
    };
    if (!isInternal) {
        const name = document.getElementById('assigneeNameInput').value.trim();
        if (!name) { alert('이름을 입력하세요.'); return; }
        body.name = name;
    }
    const url = id ? `/api/assignees/${id}` : '/api/assignees';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {
        method,
        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        const e = await res.json().catch(()=>({}));
        alert(e.message || Object.values(e.errors||{}).flat().join('\n') || '저장 실패');
        return;
    }
    closeAssigneeModal();
    loadAssigneesAdmin();
}
async function deleteAssigneeFromModal() {
    const id = document.getElementById('assigneeIdInput').value;
    if (!id || !confirm('이 외부 담당자를 삭제하시겠습니까?')) return;
    const res = await fetch(`/api/assignees/${id}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
    });
    if (!res.ok) {
        const e = await res.json().catch(()=>({}));
        alert(e.message || '삭제 실패');
        return;
    }
    closeAssigneeModal();
    loadAssigneesAdmin();
}

// ── 사용자 관리 ──
async function loadUsers() {
    const [usersRes, teamsRes] = await Promise.all([
        fetch('/api/admin/users', { headers: { 'Accept': 'application/json' } }),
        fetch('/api/admin/teams', { headers: { 'Accept': 'application/json' } }),
    ]);
    const users = await usersRes.json();
    teamsList = await teamsRes.json();

    const tbody = document.getElementById('usersBody');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">사용자가 없습니다.</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(u => {
        const roles = ['master', 'admin', 'member', 'guest'];
        const roleOpts = roles.map(r => {
            const disabled = (r === 'master' && currentRole !== 'master') ? 'disabled' : '';
            return `<option value="${r}" ${u.role === r ? 'selected' : ''} ${disabled}>${r}</option>`;
        }).join('');

        const teamOpts = ['<option value="">없음</option>'].concat(
            teamsList.map(t => `<option value="${t.id}" ${u.team_id === t.id ? 'selected' : ''}>${t.name}</option>`)
        ).join('');

        const activeClass = u.is_active ? 'on' : 'off';
        const activeText = u.is_active ? '활성' : '비활성';

        return `<tr data-uid="${u.id}">
            <td>${u.display_name}</td>
            <td class="text-muted">${u.username}</td>
            <td><select class="inline-select ur" onchange="toggleTeamSelect(this)">${roleOpts}</select></td>
            <td><select class="inline-select ut" ${u.role !== 'member' ? 'disabled' : ''}>${teamOpts}</select></td>
            <td><span class="toggle-active ${activeClass}" onclick="toggleActive(this)">${activeText}</span></td>
            <td style="display:flex;gap:4px;">
                <button class="btn-sm" onclick="saveUser(${u.id}, this)">저장</button>
                ${currentRole==='master'?`<button class="btn-sm" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border);" onclick="openAccountModal(${u.id},'${(u.username||'').replace(/'/g,"\\'")}','${(u.display_name||'').replace(/'/g,"\\'")}','${(u.email||'').replace(/'/g,"\\'")}')">수정</button>`:''}
            </td>
        </tr>`;
    }).join('');
}

function toggleTeamSelect(sel) {
    const row = sel.closest('tr');
    const teamSel = row.querySelector('.ut');
    teamSel.disabled = sel.value !== 'member';
    if (sel.value !== 'member') teamSel.value = '';
}

function toggleActive(span) {
    const isOn = span.classList.contains('on');
    span.classList.toggle('on', !isOn);
    span.classList.toggle('off', isOn);
    span.textContent = isOn ? '비활성' : '활성';
}

function toggleNewUserForm() {
    const form = document.getElementById('newUserForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    // 팀 목록 채우기
    const sel = document.getElementById('newTeamId');
    sel.innerHTML = '<option value="">없음</option>' + teamsList.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
}

async function createUser() {
    const username = document.getElementById('newUsername').value.trim();
    const display_name = document.getElementById('newDisplayName').value.trim();
    const password = document.getElementById('newPassword').value;
    const role = document.getElementById('newRole').value;
    const team_id = document.getElementById('newTeamId').value || null;

    if (!username || !display_name || !password) return alert('아이디, 이름, 비밀번호를 입력하세요.');
    if (password.length < 8) return alert('비밀번호는 8자 이상이어야 합니다.');

    const res = await fetch('/api/admin/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ username, display_name, password, role, team_id }),
    });

    if (res.ok) {
        document.getElementById('newUserForm').style.display = 'none';
        document.getElementById('newUsername').value = '';
        document.getElementById('newDisplayName').value = '';
        document.getElementById('newPassword').value = '';
        loadUsers();
    } else {
        const err = await res.json();
        alert(err.message || Object.values(err.errors || {}).flat().join('\n') || '생성 실패');
    }
}

async function saveUser(id, btn) {
    const row = btn.closest('tr');
    const role = row.querySelector('.ur').value;
    const team_id = row.querySelector('.ut').value || null;
    const is_active = row.querySelector('.toggle-active').classList.contains('on');

    const res = await fetch(`/api/admin/users/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ role, team_id, is_active }),
    });

    if (res.ok) {
        btn.textContent = '완료';
        setTimeout(() => btn.textContent = '저장', 1500);
    } else {
        const err = await res.json();
        alert(err.message || '저장 실패');
    }
}

function togglePwVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.innerHTML = isPassword ? `<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-0.15em"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-7 0-11-7-11-7a19 19 0 0 1 5.1-5.9M9.9 4.2A10.5 10.5 0 0 1 12 4c7 0 11 7 11 7a19 19 0 0 1-2.2 3.2M1 1l22 22M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>` : `<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-0.15em"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>`;
}

// ── 계정 수정 모달 (master 전용) ──
function openAccountModal(id, username, displayName, email) {
    document.getElementById('accUserId').value = id;
    document.getElementById('accUsername').value = username || '';
    document.getElementById('accDisplayName').value = displayName || '';
    document.getElementById('accEmail').value = email || '';
    document.getElementById('accPassword').value = '';
    document.getElementById('accountModalOverlay').classList.add('open');
}

function closeAccountModal() {
    document.getElementById('accountModalOverlay').classList.remove('open');
}

async function saveAccount() {
    const id = document.getElementById('accUserId').value;
    const body = {
        username: document.getElementById('accUsername').value,
        display_name: document.getElementById('accDisplayName').value,
        email: document.getElementById('accEmail').value || null,
    };
    const pw = document.getElementById('accPassword').value;
    if (pw) body.password = pw;

    const res = await fetch(`/api/admin/users/${id}/account`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    });

    if (res.ok) {
        closeAccountModal();
        loadUsers();
        alert('계정 정보가 수정되었습니다.');
    } else {
        const err = await res.json();
        alert(err.message || Object.values(err.errors || {}).flat().join('\n') || '수정 실패');
    }
}

// ── 팀 관리 ──
async function loadTeams() {
    const res = await fetch('/api/admin/teams', { headers: { 'Accept': 'application/json' } });
    teamsList = await res.json();
    renderTeams();
}

function renderTeams() {
    const container = document.getElementById('teamsContainer');
    if (!teamsList.length) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-muted);">등록된 팀이 없습니다.</div>';
        return;
    }

    container.innerHTML = teamsList.map(t => `<div class="team-card" data-tid="${t.id}">
        <div class="team-header">
            <div style="display:flex; align-items:center;">
                <input class="team-name-input tn" value="${t.name}">
                <span class="team-count">${t.users_count || 0}명</span>
            </div>
            <div style="display:flex; gap:6px;">
                <button class="btn-sm" onclick="saveTeam(${t.id}, this)">저장</button>
                <button class="btn-danger" onclick="deleteTeam(${t.id})">삭제</button>
            </div>
        </div>
        ${renderPermToggles('', t.permissions || [])}
    </div>`).join('');
}

function showNewTeamForm() {
    const form = document.getElementById('newTeamForm');
    form.style.display = 'block';
    document.getElementById('newTeamName').value = '';
    document.getElementById('newTeamPerms').innerHTML = renderPermToggles('newTeamPerms', []);
}

async function createTeam() {
    const name = document.getElementById('newTeamName').value.trim();
    if (!name) return alert('팀 이름을 입력하세요.');

    const perms = [...document.querySelectorAll('#newTeamPerms input:checked')].map(c => c.value);

    const res = await fetch('/api/admin/teams', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ name, permissions: perms }),
    });

    if (res.ok) {
        document.getElementById('newTeamForm').style.display = 'none';
        loadTeams();
    } else {
        const err = await res.json();
        alert(err.message || Object.values(err.errors || {}).flat().join('\n') || '생성 실패');
    }
}

async function saveTeam(id, btn) {
    const card = btn.closest('.team-card');
    const name = card.querySelector('.tn').value.trim();
    const perms = [...card.querySelectorAll('input[type="checkbox"]:checked')].map(c => c.value);

    const res = await fetch(`/api/admin/teams/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ name, permissions: perms }),
    });

    if (res.ok) {
        btn.textContent = '완료';
        setTimeout(() => btn.textContent = '저장', 1500);
    } else {
        alert('저장 실패');
    }
}

async function deleteTeam(id) {
    if (!confirm('이 팀을 삭제하시겠습니까? 소속 사용자의 팀이 해제됩니다.')) return;

    await fetch(`/api/admin/teams/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    loadTeams();
}

// ── 판매처 설정 ──
async function saveSellerSettings() {
    const body = {
        seller_name: document.getElementById('sellerName').value,
        seller_biz_no: document.getElementById('sellerBizNo').value,
        seller_address: document.getElementById('sellerAddress').value,
        seller_biz_type: document.getElementById('sellerBizType').value,
        seller_biz_item: document.getElementById('sellerBizItem').value,
        seller_phone: document.getElementById('sellerPhone').value,
    };
    await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body)
    });
    const msg = document.getElementById('sellerSaveMsg');
    msg.style.display = 'inline';
    setTimeout(() => msg.style.display = 'none', 2000);
}

// ── 내방 옵션 ──
async function saveVisitOptions() {
    await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ calendar_visit_options: document.getElementById('visitOptionsText').value })
    });
    const msg = document.getElementById('visitOptionsSaveMsg');
    msg.style.display = 'inline';
    setTimeout(() => msg.style.display = 'none', 2000);
}

async function saveCancelReasons() {
    await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ project_cancel_reasons: document.getElementById('cancelReasonsText').value })
    });
    const msg = document.getElementById('cancelReasonsSaveMsg');
    msg.style.display = 'inline';
    setTimeout(() => msg.style.display = 'none', 2000);
}

// ────────────── 의뢰자 필드 관리 ──────────────
const FIELD_SECTIONS = { basic:'기본 정보', equipment:'장비 정보', broadcast:'방송 정보', business:'사업자 정보', etc:'기타' };
const FIELD_TYPES = { text:'텍스트', textarea:'여러 줄', select:'드롭다운', radio:'라디오', checkbox:'체크박스', toggle:'토글', number:'숫자', date:'날짜' };
let allFields = [];

async function loadClientFields() {
    const res = await fetch('/api/admin/client-fields', { headers:{ 'Accept':'application/json' } });
    allFields = await res.json();
    renderClientFields();
}

function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function renderClientFields() {
    const container = document.getElementById('clientFieldsContainer');
    if (!allFields.length) {
        container.innerHTML = `<div class="cf-empty">
            <div class="cf-empty-icon"><x-icon name="clip" :size="30"/></div>
            <div class="cf-empty-title">정의된 필드가 없습니다</div>
            <div class="cf-empty-sub">우측 상단 <b>+ 필드 추가</b> 버튼으로 첫 필드를 만들어 보세요.</div>
        </div>`;
        return;
    }
    // 섹션별 그룹핑
    const grouped = {};
    allFields.forEach(f => {
        const sec = f.section || 'etc';
        (grouped[sec] = grouped[sec] || []).push(f);
    });
    let html = '';
    Object.entries(FIELD_SECTIONS).forEach(([key, label]) => {
        if (!grouped[key]) return;
        html += `<div class="cf-section">
            <div class="cf-section-head">
                <span class="cf-section-title">${escHtml(label)}</span>
                <span class="cf-section-count">${grouped[key].length}개</span>
            </div>
            <div class="cf-grid">`;
        grouped[key].forEach(f => {
            const required = f.is_required ? '<span class="cf-required">*</span>' : '';
            const inactive = f.is_active ? '' : ' inactive';
            const typeLabel = FIELD_TYPES[f.type] || f.type;
            const optsChip = (f.options && f.options.length) ? `<span class="cf-chip">옵션 ${f.options.length}</span>` : '';
            const statusChip = f.is_active ? '' : '<span class="cf-chip muted">비활성</span>';
            const reqChip = f.is_required ? '<span class="cf-chip" style="color:var(--red); border-color:var(--red);">필수</span>' : '';
            html += `<div class="cf-card${inactive}" data-id="${f.id}" data-section="${escHtml(key)}" draggable="true" onclick="editField(${f.id})">
                <span class="cf-drag-handle" title="드래그해서 순서 변경" aria-hidden="true">⋮⋮</span>
                <div class="cf-card-row">
                    <div class="cf-card-label">${escHtml(f.label)} ${required}</div>
                    <span class="cf-type-badge cf-type-${escHtml(f.type)}">${escHtml(typeLabel)}</span>
                </div>
                <div class="cf-card-row">
                    <span class="cf-card-key">${escHtml(f.key)}</span>
                    <div class="cf-card-meta">${reqChip}${optsChip}${statusChip}</div>
                </div>
            </div>`;
        });
        html += `</div></div>`;
    });
    container.innerHTML = html;
    enableCfDrag(container, 'client');
}

function openFieldModal(field) {
    const m = document.getElementById('fieldModalOverlay');
    m.classList.add('open');
    document.getElementById('fieldModalTitle').textContent = field ? '필드 편집' : '+ 필드 추가';
    document.getElementById('fieldId').value = field?.id || '';
    document.getElementById('fieldLabel').value = field?.label || '';
    document.getElementById('fieldType').value = field?.type || 'text';
    document.getElementById('fieldSection').value = field?.section || 'basic';
    document.getElementById('fieldSubsection').value = field?.subsection || '';
    const cSubDl = document.getElementById('fieldSubsectionList');
    cSubDl.innerHTML = [...new Set(allFields.map(x => x.subsection).filter(Boolean))]
        .map(s => `<option value="${escHtml(s)}">`).join('');
    document.getElementById('fieldPriority').value = String(field?.priority ?? 0);
    document.getElementById('fieldWidth').value = String(field?.width || 2);
    document.getElementById('fieldOptions').value = (field?.options || []).join('\n');
    document.getElementById('fieldPlaceholder').value = field?.placeholder || '';
    document.getElementById('fieldHelpText').value = field?.help_text || '';
    document.getElementById('fieldRequired').checked = !!field?.is_required;
    document.getElementById('fieldActive').checked = field ? !!field.is_active : true;
    document.getElementById('fieldHasQuantity').checked = !!field?.has_quantity;
    document.getElementById('fieldDeleteBtn').style.display = field ? 'inline-block' : 'none';
    onTypeChange();
}

function closeFieldModal() { document.getElementById('fieldModalOverlay').classList.remove('open'); }

function onTypeChange() {
    const type = document.getElementById('fieldType').value;
    const needsOptions = ['select', 'radio', 'checkbox'].includes(type);
    document.getElementById('optionsWrap').style.display = needsOptions ? 'block' : 'none';
}

function editField(id) {
    const f = allFields.find(x => x.id === id);
    if (f) openFieldModal(f);
}

async function saveField() {
    const id = document.getElementById('fieldId').value;
    const label = document.getElementById('fieldLabel').value.trim();
    const type = document.getElementById('fieldType').value;
    if (!label) return alert('라벨을 입력하세요.');

    const optionsRaw = document.getElementById('fieldOptions').value.trim();
    const options = optionsRaw ? optionsRaw.split('\n').map(s => s.trim()).filter(Boolean) : null;
    if (['select','radio','checkbox'].includes(type) && (!options || !options.length)) {
        return alert('선택지를 한 줄에 하나씩 입력하세요.');
    }

    const body = {
        label,
        type,
        section: document.getElementById('fieldSection').value,
        subsection: document.getElementById('fieldSubsection').value.trim() || null,
        priority: parseInt(document.getElementById('fieldPriority').value, 10) || 0,
        width: parseInt(document.getElementById('fieldWidth').value, 10) || 2,
        options,
        placeholder: document.getElementById('fieldPlaceholder').value || null,
        help_text: document.getElementById('fieldHelpText').value || null,
        is_required: document.getElementById('fieldRequired').checked,
        is_active: document.getElementById('fieldActive').checked,
        has_quantity: document.getElementById('fieldHasQuantity').checked,
    };

    const url = id ? `/api/admin/client-fields/${id}` : '/api/admin/client-fields';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {
        method,
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        body: JSON.stringify(body)
    });
    if (res.ok) {
        closeFieldModal();
        await loadClientFields();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors || {}).flat().join('\n')));
    }
}

async function deleteFieldFromModal() {
    if (!confirm('이 필드를 삭제하시겠습니까?\n기존에 입력된 값은 DB에 남아있지만, 폼에서는 더 이상 노출되지 않습니다.')) return;
    const id = document.getElementById('fieldId').value;
    const res = await fetch(`/api/admin/client-fields/${id}`, { method:'DELETE', headers:{ 'X-CSRF-TOKEN':CSRF } });
    if (res.ok) {
        closeFieldModal();
        await loadClientFields();
    } else alert('삭제 실패');
}

// ─────────────── 캘린더 카테고리 ───────────────
let allCalendarCategories = [];

async function loadCalendarCategories() {
    const res = await fetch('/api/admin/calendar-categories', { headers:{ 'Accept':'application/json' } });
    allCalendarCategories = await res.json();
    renderCalendarCategories();
}

function renderCalendarCategories() {
    const container = document.getElementById('calendarCategoriesContainer');
    if (!allCalendarCategories.length) {
        container.innerHTML = '<div class="cf-empty"><div class="cf-empty-icon"><svg viewBox=\"0 0 24 24\" width=\"30\" height=\"30\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.7\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\"/><path d=\"M16 2v4M8 2v4M3 10h18\"/></svg></div><div class="cf-empty-title">카테고리가 없습니다</div></div>';
        return;
    }
    const DEFAULT_KEYS = ['gold','teal','blue','red','green','purple','holiday'];
    container.innerHTML = allCalendarCategories.map(c => {
        const isDefault = DEFAULT_KEYS.includes(c.key);
        return `
        <div class="cat-card" data-id="${c.id}">
            <span class="cat-drag-handle" title="드래그하여 순서 변경 (캘린더에 표시되는 순서)">⋮⋮</span>
            <div class="cat-preview" style="background:${c.color}; color:${c.text_color};" id="catPreview-${c.id}">${escHtml(c.label)}</div>
            <input type="text" id="catLabel-${c.id}" value="${escHtml(c.label)}" placeholder="라벨" oninput="updateCatPreview(${c.id})">
            <div class="cat-color-wrap">
                <input type="color" id="catColor-${c.id}" value="${c.color}" oninput="syncCatColor(${c.id},'color')">
                <input type="text" id="catColorHex-${c.id}" value="${c.color}" oninput="syncCatColor(${c.id},'hex')">
            </div>
            <div class="cat-color-wrap">
                <input type="color" id="catTextColor-${c.id}" value="${c.text_color}" oninput="syncCatColor(${c.id},'textColor')">
                <input type="text" id="catTextColorHex-${c.id}" value="${c.text_color}" oninput="syncCatColor(${c.id},'textHex')">
            </div>
            <div class="cat-actions">
                ${isDefault
                    ? `<button class="btn-outline" onclick="resetCalendarCategory(${c.id})" style="padding:7px 12px; font-size:12px;" title="기본 라벨/색으로 되돌림">기본값</button>`
                    : `<button class="btn-danger-outline" onclick="deleteCalendarCategory(${c.id})" style="padding:7px 12px; font-size:12px;">삭제</button>`}
                <button class="btn-save" onclick="saveCalendarCategory(${c.id})" style="padding:7px 14px; font-size:12px;">저장</button>
            </div>
        </div>`;
    }).join('');
    enableCatDrag(container);
}

// ── 카테고리 순서 변경 (드래그앤드롭) — 캘린더 뷰어 리스트 표시 순서 ──
let catDragSrc = null;
function enableCatDrag(container) {
    container.querySelectorAll('.cat-card').forEach(card => {
        // 핸들을 잡았을 때만 드래그 시작 (입력창 텍스트 선택과 충돌 방지)
        card.addEventListener('mousedown', e => { card.draggable = !!e.target.closest('.cat-drag-handle'); });
        card.addEventListener('dragstart', e => {
            catDragSrc = card;
            card.classList.add('dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', card.dataset.id); } catch (err) {}
            }
        });
        card.addEventListener('dragover', e => {
            if (!catDragSrc || catDragSrc === card) return;
            e.preventDefault();
            const rect = card.getBoundingClientRect();
            const before = e.clientY < rect.top + rect.height / 2;
            card.parentNode.insertBefore(catDragSrc, before ? card : card.nextSibling);
        });
        card.addEventListener('dragend', () => {
            card.draggable = false;
            if (!catDragSrc) return;
            catDragSrc.classList.remove('dragging');
            catDragSrc = null;
            saveCalendarCategoryOrder();
        });
    });
}

async function saveCalendarCategoryOrder() {
    const ids = [...document.querySelectorAll('#calendarCategoriesContainer .cat-card')].map(el => Number(el.dataset.id));
    if (ids.join(',') === allCalendarCategories.map(c => c.id).join(',')) return; // 순서 변화 없음
    allCalendarCategories.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
    const res = await fetch('/api/admin/calendar-categories/reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ order: ids }),
    });
    if (!res.ok) { alert('순서 저장에 실패했습니다.'); loadCalendarCategories(); }
}

// ── 카테고리 추가 ──
function openNewCalendarCategoryModal() {
    document.getElementById('newCatLabel').value = '';
    document.getElementById('newCatColor').value = '#7a98c8';
    document.getElementById('newCatColorHex').value = '#7a98c8';
    document.getElementById('newCatTextColor').value = '#0a1828';
    document.getElementById('newCatTextColorHex').value = '#0a1828';
    updateNewCatPreview();
    document.getElementById('newCatModalOverlay').classList.add('open');
}
function closeNewCalendarCategoryModal() {
    document.getElementById('newCatModalOverlay').classList.remove('open');
}
function updateNewCatPreview() {
    const lbl = document.getElementById('newCatLabel').value.trim() || '미리보기';
    const bg = document.getElementById('newCatColorHex').value;
    const fg = document.getElementById('newCatTextColorHex').value;
    const p = document.getElementById('newCatPreview');
    p.textContent = lbl;
    p.style.background = bg;
    p.style.color = fg;
}
document.addEventListener('input', e => {
    if (e.target && e.target.id === 'newCatLabel') updateNewCatPreview();
});
async function createCalendarCategory() {
    const body = {
        label: document.getElementById('newCatLabel').value.trim(),
        color: document.getElementById('newCatColorHex').value.trim(),
        text_color: document.getElementById('newCatTextColorHex').value.trim(),
    };
    if (!body.label) return alert('라벨을 입력하세요.');
    if (!/^#[0-9A-Fa-f]{6}$/.test(body.color)) return alert('배경색은 #RRGGBB 형식이어야 합니다.');
    if (!/^#[0-9A-Fa-f]{6}$/.test(body.text_color)) return alert('글자색은 #RRGGBB 형식이어야 합니다.');

    const res = await fetch('/api/admin/calendar-categories', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (res.ok) {
        closeNewCalendarCategoryModal();
        await loadCalendarCategories();
        alert('카테고리가 추가되었습니다. 캘린더 화면에서 새로고침하면 적용됩니다.');
    } else {
        const err = await res.json().catch(() => ({}));
        alert('추가 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}
async function deleteCalendarCategory(id) {
    if (!confirm('이 카테고리를 삭제하시겠습니까?\n해당 카테고리로 분류된 기존 일정은 그대로 남지만, 새로 추가하거나 변경 시 색이 표시되지 않을 수 있습니다.')) return;
    const res = await fetch(`/api/admin/calendar-categories/${id}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
    });
    if (res.ok) {
        await loadCalendarCategories();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('삭제 실패: ' + (err.message || '오류'));
    }
}

function updateCatPreview(id) {
    const label = document.getElementById(`catLabel-${id}`).value;
    const color = document.getElementById(`catColor-${id}`).value;
    const textColor = document.getElementById(`catTextColor-${id}`).value;
    const preview = document.getElementById(`catPreview-${id}`);
    preview.textContent = label;
    preview.style.background = color;
    preview.style.color = textColor;
}

function syncCatColor(id, source) {
    const colorEl = document.getElementById(`catColor-${id}`);
    const hexEl = document.getElementById(`catColorHex-${id}`);
    const tColorEl = document.getElementById(`catTextColor-${id}`);
    const tHexEl = document.getElementById(`catTextColorHex-${id}`);
    if (source === 'color') hexEl.value = colorEl.value;
    if (source === 'hex' && /^#[0-9A-Fa-f]{6}$/.test(hexEl.value)) colorEl.value = hexEl.value;
    if (source === 'textColor') tHexEl.value = tColorEl.value;
    if (source === 'textHex' && /^#[0-9A-Fa-f]{6}$/.test(tHexEl.value)) tColorEl.value = tHexEl.value;
    updateCatPreview(id);
}

async function saveCalendarCategory(id) {
    const body = {
        label: document.getElementById(`catLabel-${id}`).value.trim(),
        color: document.getElementById(`catColorHex-${id}`).value.trim(),
        text_color: document.getElementById(`catTextColorHex-${id}`).value.trim(),
    };
    if (!body.label) return alert('라벨을 입력하세요.');
    if (!/^#[0-9A-Fa-f]{6}$/.test(body.color)) return alert('배경색은 #RRGGBB 형식이어야 합니다.');
    if (!/^#[0-9A-Fa-f]{6}$/.test(body.text_color)) return alert('글자색은 #RRGGBB 형식이어야 합니다.');

    const res = await fetch(`/api/admin/calendar-categories/${id}`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify(body)
    });
    if (res.ok) {
        alert('저장되었습니다. 캘린더 화면에서 새로고침하면 적용됩니다.');
        await loadCalendarCategories();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}

async function resetCalendarCategory(id) {
    if (!confirm('이 카테고리를 기본값으로 되돌리시겠습니까?')) return;
    const res = await fetch(`/api/admin/calendar-categories/${id}/reset`, {
        method:'POST',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    if (res.ok) await loadCalendarCategories();
    else alert('초기화 실패');
}

// ─────────────── 프로젝트 유형 ───────────────
const CONSULT_TYPE_DEFAULTS = ['visit','remote','design','inquiry','as','troubleshoot'];
let allConsultTypes = [];

async function loadConsultTypes() {
    const res = await fetch('/api/admin/consultation-types', { headers:{ 'Accept':'application/json' } });
    allConsultTypes = await res.json();
    renderConsultTypes();
}

function renderConsultTypes() {
    const container = document.getElementById('consultTypesContainer');
    if (!allConsultTypes.length) {
        container.innerHTML = '<div class="cf-empty"><div class="cf-empty-icon"><x-icon name="clip" :size="30"/></div><div class="cf-empty-title">정의된 프로젝트 유형이 없습니다</div></div>';
        return;
    }
    container.innerHTML = `<div class="cf-grid">${allConsultTypes.map(t => {
        const isDefault = CONSULT_TYPE_DEFAULTS.includes(t.key);
        const inactive = t.is_active ? '' : ' inactive';
        return `<div class="cf-card${inactive}" onclick="editConsultType(${t.id})">
            <div class="cf-card-row">
                <div class="cf-card-label">${escHtml(t.label)}</div>
                ${isDefault ? '<span class="cf-chip" style="border-color:var(--accent); color:var(--accent);">기본</span>' : ''}
            </div>
            <div class="cf-card-row">
                <span class="cf-card-key">${escHtml(t.key)}</span>
                <div class="cf-card-meta">
                    ${t.is_active ? '' : '<span class="cf-chip muted">비활성</span>'}
                    <span class="cf-chip">순서 ${t.sort_order ?? 0}</span>
                </div>
            </div>
        </div>`;
    }).join('')}</div>`;
}

function openConsultTypeModal(t) {
    const m = document.getElementById('consultTypeModalOverlay');
    m.classList.add('open');
    document.getElementById('consultTypeModalTitle').textContent = t ? '프로젝트 유형 편집' : '+ 프로젝트 유형 추가';
    document.getElementById('consultTypeId').value = t?.id || '';
    document.getElementById('consultTypeLabel').value = t?.label || '';
    document.getElementById('consultTypeKey').value = t?.key || '';
    document.getElementById('consultTypeSortOrder').value = t?.sort_order ?? '';
    document.getElementById('consultTypeActive').checked = t ? !!t.is_active : true;
    document.getElementById('consultTypeKey').disabled = !!t; // 편집 시 key 잠금 (FK 영향)
    // 삭제 버튼은 모든 유형에 노출 — 사용 중인 프로젝트가 있으면 서버가 차단하고 비활성화 안내
    document.getElementById('consultTypeDeleteBtn').style.display = t ? 'inline-block' : 'none';
}

function closeConsultTypeModal() { document.getElementById('consultTypeModalOverlay').classList.remove('open'); }
function editConsultType(id) { const t = allConsultTypes.find(x => x.id === id); if (t) openConsultTypeModal(t); }

async function saveConsultType() {
    const id = document.getElementById('consultTypeId').value;
    const label = document.getElementById('consultTypeLabel').value.trim();
    if (!label) return alert('라벨을 입력하세요.');

    const body = {
        label,
        sort_order: parseInt(document.getElementById('consultTypeSortOrder').value || 0),
        is_active: document.getElementById('consultTypeActive').checked,
    };
    if (!id) {
        const key = document.getElementById('consultTypeKey').value.trim();
        if (key) body.key = key;
    }

    const url = id ? `/api/admin/consultation-types/${id}` : '/api/admin/consultation-types';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {
        method,
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (res.ok) {
        closeConsultTypeModal();
        await loadConsultTypes();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}

async function deleteConsultType() {
    if (!confirm('이 프로젝트 유형을 삭제하시겠습니까?\n사용 중인 프로젝트가 있으면 삭제 대신 비활성화 처리만 가능합니다.')) return;
    const id = document.getElementById('consultTypeId').value;
    const res = await fetch(`/api/admin/consultation-types/${id}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
    });
    if (res.ok) {
        closeConsultTypeModal();
        await loadConsultTypes();
    } else {
        const err = await res.json().catch(() => ({}));
        alert(err.message || '삭제 실패');
    }
}

// ─────────────── 보고서 템플릿 ───────────────
let allReportTemplates = [];

async function loadReportTemplates() {
    const res = await fetch('/api/admin/visit-report-templates', { headers:{'Accept':'application/json'} });
    allReportTemplates = await res.json();
    renderReportTemplates();
}

function renderReportTemplates() {
    const container = document.getElementById('reportTemplatesContainer');
    if (!allReportTemplates.length) {
        container.innerHTML = '<div class="cf-empty"><div class="cf-empty-icon"><x-icon name="clip" :size="30"/></div><div class="cf-empty-title">등록된 템플릿이 없습니다</div><div class="cf-empty-sub">+ 템플릿 추가로 시작하세요</div></div>';
        return;
    }
    // 텍스트 미리보기 — HTML 태그/엔티티를 안전하게 디코딩
    function _rtTextPreview(html, max = 120) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        const text = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
        if (!text) return '';
        return text.length > max ? text.slice(0, max) + '…' : text;
    }
    container.innerHTML = `<div class="cf-grid">${allReportTemplates.map(t => {
        const star = t.is_default ? '<span class="cf-chip" style="border-color:var(--accent); color:var(--accent);"><svg viewBox=\"0 0 24 24\" width=\"12\" height=\"12\" fill=\"currentColor\" stroke=\"none\" style=\"vertical-align:-0.1em\"><path d=\"m12 2 3 6.6 7 .6-5.3 4.6 1.6 6.9L12 17.7 5.7 20.7l1.6-6.9L2 9.2l7-.6z\"/></svg> 기본</span>' : '';
        const inactive = t.is_active ? '' : ' inactive';
        const preview = _rtTextPreview(t.content);
        return `<div class="cf-card${inactive}" onclick="editReportTemplate(${t.id})">
            <div class="cf-card-row">
                <div class="cf-card-label">${escHtml(t.name)}</div>
                ${star}
            </div>
            <div class="cf-card-row">
                <div style="font-size:12px; color:var(--text-muted); flex:1; line-height:1.5; max-height:3em; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">${preview ? escHtml(preview) : '<em style="opacity:0.6;">(빈 본문)</em>'}</div>
            </div>
            <div class="cf-card-row">
                <span class="cf-card-key">순서 ${t.sort_order ?? 0}</span>
                <div class="cf-card-meta">${t.is_active ? '' : '<span class="cf-chip muted">비활성</span>'}${t.creator ? `<span class="cf-chip">${escHtml(t.creator)}</span>` : ''}</div>
            </div>
        </div>`;
    }).join('')}</div>`;
}

window.__rtEditor = null;
function openReportTemplateModal(t) {
    document.getElementById('rtModalOverlay').classList.add('open');
    document.getElementById('rtModalTitle').textContent = t ? '템플릿 편집' : '+ 보고서 템플릿';
    document.getElementById('rtId').value = t?.id || '';
    document.getElementById('rtName').value = t?.name || '';
    document.getElementById('rtSortOrder').value = t?.sort_order ?? '';
    document.getElementById('rtIsDefault').checked = !!t?.is_default;
    document.getElementById('rtIsActive').checked = t ? !!t.is_active : true;
    document.getElementById('rtDeleteBtn').style.display = t ? 'inline-block' : 'none';
    // 에디터 초기화 또는 초기 콘텐츠 설정
    if (window.__rtEditor) {
        window.__rtEditor.commands.setContent(t?.content || '');
    } else {
        initRtEditor(t?.content || '');
    }
}
function closeReportTemplateModal() { document.getElementById('rtModalOverlay').classList.remove('open'); }
function editReportTemplate(id) { const t = allReportTemplates.find(x => x.id === id); if (t) openReportTemplateModal(t); }

async function saveReportTemplate() {
    const id = document.getElementById('rtId').value;
    const name = document.getElementById('rtName').value.trim();
    if (!name) return alert('템플릿 이름을 입력하세요.');
    const body = {
        name,
        content: window.__rtEditor ? window.__rtEditor.getHTML() : '',
        sort_order: parseInt(document.getElementById('rtSortOrder').value || 0),
        is_default: document.getElementById('rtIsDefault').checked,
        is_active: document.getElementById('rtIsActive').checked,
    };
    const url = id ? `/api/admin/visit-report-templates/${id}` : '/api/admin/visit-report-templates';
    const res = await fetch(url, {
        method: id ? 'PATCH' : 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (res.ok) { closeReportTemplateModal(); await loadReportTemplates(); }
    else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}

async function deleteReportTemplate() {
    if (!confirm('이 템플릿을 삭제하시겠습니까?')) return;
    const id = document.getElementById('rtId').value;
    const res = await fetch(`/api/admin/visit-report-templates/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
    });
    if (res.ok) { closeReportTemplateModal(); await loadReportTemplates(); }
    else alert('삭제 실패');
}

// Tiptap 에디터 초기화 (관리자 템플릿용)
async function initRtEditor(initialContent) {
    if (window.__rtEditor) {
        window.__rtEditor.commands.setContent(initialContent || '');
        return;
    }
    const ed = document.getElementById('rtEditor');
    if (!ed) return;
    try {
        const { Editor } = await import('https://esm.sh/@tiptap/core@2.11.5');
        const StarterKit = (await import('https://esm.sh/@tiptap/starter-kit@2.11.5')).default;
        const Placeholder = (await import('https://esm.sh/@tiptap/extension-placeholder@2.11.5')).default;
        const TextAlign = (await import('https://esm.sh/@tiptap/extension-text-align@2.11.5')).default;

        window.__rtEditor = new Editor({
            element: ed,
            extensions: [
                StarterKit.configure({ heading: { levels: [1,2,3] } }),
                Placeholder.configure({ placeholder: '템플릿 본문을 입력하세요. — 방문 보고서 작성 시 이 내용이 자동 불러와집니다.' }),
                TextAlign.configure({ types: ['heading', 'paragraph'] }),
            ],
            content: initialContent || '',
            onUpdate({ editor }) { updateRtToolbar(editor); },
            onSelectionUpdate({ editor }) { updateRtToolbar(editor); },
        });

        const rtCmds = {
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
            hr:          ed => ed.chain().focus().setHorizontalRule().run(),
            alignLeft:   ed => ed.chain().focus().setTextAlign('left').run(),
            alignCenter: ed => ed.chain().focus().setTextAlign('center').run(),
            alignRight:  ed => ed.chain().focus().setTextAlign('right').run(),
        };
        document.querySelectorAll('#rtToolbar button[data-rt-cmd]').forEach(btn => {
            btn.addEventListener('click', () => {
                const fn = rtCmds[btn.dataset.rtCmd];
                if (fn) fn(window.__rtEditor);
            });
        });

        function updateRtToolbar(ed) {
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
                alignLeft: ed.isActive({ textAlign: 'left' }),
                alignCenter: ed.isActive({ textAlign: 'center' }),
                alignRight: ed.isActive({ textAlign: 'right' }),
            };
            document.querySelectorAll('#rtToolbar button[data-rt-cmd]').forEach(b => {
                b.classList.toggle('is-active', !!checks[b.dataset.rtCmd]);
            });
        }
    } catch(err) {
        console.error('Tiptap load failed:', err);
        const ed2 = document.getElementById('rtEditor');
        if (ed2) ed2.innerHTML = '<div style="padding:14px; color:var(--red); font-size:12px;">에디터 로드 실패 — 새로고침 후 다시 시도해 주세요.</div>';
    }
}

// ─────────────── 작업 유형 ───────────────
const WORK_TYPE_DEFAULTS = ['setup','remote','survey','filming','design','as','dispatch','monthly','hourly'];
let allWorkTypes = [];

async function ensureConsultTypesLoaded() {
    if (allConsultTypes.length) return;
    try {
        const res = await fetch('/api/admin/consultation-types', { headers:{ 'Accept':'application/json' } });
        if (res.ok) allConsultTypes = await res.json();
    } catch(e) {}
}

function consultTypeLabel(key) {
    if (!key) return null;
    const t = allConsultTypes.find(x => x.key === key);
    return t ? t.label : key;
}

async function loadWorkTypes() {
    await ensureConsultTypesLoaded();
    const res = await fetch('/api/admin/work-types', { headers:{'Accept':'application/json'} });
    allWorkTypes = await res.json();
    renderWorkTypes();
}

function renderWorkTypes() {
    const container = document.getElementById('workTypesContainer');
    if (!allWorkTypes.length) {
        container.innerHTML = '<div class="cf-empty"><div class="cf-empty-icon"><svg viewBox=\"0 0 24 24\" width=\"30\" height=\"30\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.7\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"12\" cy=\"12\" r=\"3\"/><path d=\"M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z\"/></svg></div><div class="cf-empty-title">정의된 작업 유형이 없습니다</div></div>';
        return;
    }
    container.innerHTML = `<div class="cf-grid">${allWorkTypes.map(t => {
        const isDefault = WORK_TYPE_DEFAULTS.includes(t.key);
        const inactive = t.is_active ? '' : ' inactive';
        const typeChip = t.type_key
            ? `<span class="cf-chip" style="border-color:var(--accent); color:var(--accent);">${escHtml(consultTypeLabel(t.type_key))}</span>`
            : '<span class="cf-chip muted">공통</span>';
        return `<div class="cf-card${inactive}" onclick="editWorkType(${t.id})">
            <div class="cf-card-row">
                <div class="cf-card-label">${escHtml(t.label)}</div>
                ${isDefault ? '<span class="cf-chip" style="border-color:var(--accent); color:var(--accent);">기본</span>' : ''}
            </div>
            <div class="cf-card-row">
                <span class="cf-card-key">${escHtml(t.key)}</span>
                <div class="cf-card-meta">
                    ${t.is_active ? '' : '<span class="cf-chip muted">비활성</span>'}
                    <span class="cf-chip">순서 ${t.sort_order ?? 0}</span>
                </div>
            </div>
            <div class="cf-card-row" style="flex-wrap:wrap; gap:4px;">${typeChip}</div>
        </div>`;
    }).join('')}</div>`;
}

async function openWorkTypeModal(t) {
    document.getElementById('wtModalOverlay').classList.add('open');
    document.getElementById('wtModalTitle').textContent = t ? '작업 유형 편집' : '+ 작업 유형';
    document.getElementById('wtId').value = t?.id || '';
    document.getElementById('wtLabel').value = t?.label || '';
    document.getElementById('wtKey').value = t?.key || '';
    document.getElementById('wtKey').disabled = !!t;
    document.getElementById('wtSortOrder').value = t?.sort_order ?? '';
    document.getElementById('wtIsActive').checked = t ? !!t.is_active : true;
    await ensureConsultTypesLoaded();
    const typeSel = document.getElementById('wtTypeKey');
    typeSel.innerHTML = '<option value="">공통 (모든 유형)</option>'
        + allConsultTypes.filter(ct => ct.is_active || ct.key === t?.type_key)
            .map(ct => `<option value="${ct.key}">${escHtml(ct.label)}</option>`).join('');
    typeSel.value = t?.type_key || '';
    const isDefault = t && WORK_TYPE_DEFAULTS.includes(t.key);
    document.getElementById('wtDeleteBtn').style.display = (t && !isDefault) ? 'inline-block' : 'none';
}

function closeWorkTypeModal() { document.getElementById('wtModalOverlay').classList.remove('open'); }
function editWorkType(id) { const t = allWorkTypes.find(x => x.id === id); if (t) openWorkTypeModal(t); }

async function saveWorkType() {
    const id = document.getElementById('wtId').value;
    const label = document.getElementById('wtLabel').value.trim();
    if (!label) return alert('라벨을 입력하세요.');
    const body = {
        label,
        type_key: document.getElementById('wtTypeKey').value || null,
        sort_order: parseInt(document.getElementById('wtSortOrder').value || 0),
        is_active: document.getElementById('wtIsActive').checked,
    };
    if (!id) {
        const key = document.getElementById('wtKey').value.trim();
        if (key) body.key = key;
    }
    const url = id ? `/api/admin/work-types/${id}` : '/api/admin/work-types';
    const res = await fetch(url, {
        method: id ? 'PATCH' : 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (res.ok) { closeWorkTypeModal(); await loadWorkTypes(); }
    else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors||{}).flat().join('\n')));
    }
}

async function deleteWorkType() {
    if (!confirm('이 작업 유형을 삭제하시겠습니까?')) return;
    const id = document.getElementById('wtId').value;
    const res = await fetch(`/api/admin/work-types/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
    });
    if (res.ok) { closeWorkTypeModal(); await loadWorkTypes(); }
    else {
        const err = await res.json().catch(() => ({}));
        alert(err.message || '삭제 실패');
    }
}
</script>
@endpush
