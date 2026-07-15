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
    .todo-list-layout { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:16px; align-items:start; }
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
    /* 우측 상세 패널 */
    .todo-detail-pane { position:sticky; top:16px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; display:flex; flex-direction:column; gap:13px; }
    .todo-detail-empty { color:var(--text-muted); font-size:12.5px; text-align:center; padding:46px 0; }
    .tdp-title { font-size:16px; font-weight:700; line-height:1.5; word-break:break-word; }
    .todo-view-content a { color:var(--accent); word-break:break-all; }
    .yt-embed { margin-top:10px; border-radius:10px; overflow:hidden; aspect-ratio:16/9; background:#000; }
    .yt-embed iframe { width:100%; height:100%; border:0; display:block; }
    .tdp-actions { display:flex; gap:8px; flex-wrap:wrap; border-top:1px solid var(--border); padding-top:13px; }
    .tdp-actions .todo-btn { padding:8px 14px; font-size:12.5px; }
    @media (max-width:900px) {
        .todo-list-layout { grid-template-columns:1fr; }
        .todo-detail-pane { position:static; order:-1; }
    }
    @media (max-width:560px) { .todo-lrow-assignee, .todo-lrow .todo-team-label { display:none; } }

    /* ── 칸반 보드 ── */
    .todo-board { display:flex; gap:14px; align-items:flex-start; overflow-x:auto; padding-bottom:14px; }
    .todo-col { flex:0 0 280px; background:var(--surface); border:1px solid var(--border); border-radius:14px; min-height:120px; }
    .todo-col.drag-over { border-color:var(--accent); background:color-mix(in srgb, var(--accent) 5%, var(--surface)); }
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
    .todo-due { font-size:10px; font-weight:700; padding:3px 9px; border-radius:9px; background:var(--surface2); color:var(--text-muted); }
    .todo-due.overdue { background:#fdecea; color:#c0392b; }
    .todo-due.today { background:#fdf1e3; color:#b26a00; }
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
    .todo-field-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    @media (max-width:560px) { .todo-field-row { grid-template-columns:1fr; } }
    .todo-modal-foot { display:flex; gap:8px; padding:14px 20px; border-top:1px solid var(--border); justify-content:flex-end; flex-wrap:wrap; }
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

    @media (max-width:768px) {
        .todo-wrap { padding:16px 12px; }
        .todo-board { flex-direction:column; }
        .todo-col { flex:none; width:100%; }
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
                <div class="todo-field" id="tfAssigneeField" @if(!$me->isAdmin()) style="display:none;" @endif>
                    <label>담당자 *</label>
                    <select id="tfAssignee" @if(!$me->isAdmin()) disabled @endif></select>
                </div>
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
            <div class="todo-attach-list" id="tvAttachments"></div>
        </div>
        <div class="todo-modal-foot">
            <button type="button" class="todo-btn danger" onclick="deleteTodo()">삭제</button>
            <button type="button" class="todo-btn ghost" onclick="editTodo()">수정</button>
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

function dueDiff(t) {
    if (!t.due_date) { return null; }
    const today = new Date(); today.setHours(0,0,0,0);
    return Math.round((new Date(t.due_date + 'T00:00:00') - today) / 86400000);
}

function dueChip(t) {
    if (!t.due_date) { return ''; }
    if (t.completed) { return `<span class="todo-due">${t.due_date.replaceAll('-', '.')}</span>`; }
    const diff = dueDiff(t);
    if (diff < 0) { return `<span class="todo-due overdue">⚠ ${-diff}일 지남</span>`; }
    if (diff === 0) { return `<span class="todo-due today">오늘 마감</span>`; }
    return `<span class="todo-due">D-${diff}</span>`;
}

// 리스트 뷰용 기한 날짜 — 임박 정도에 따라 색상 강조
function dueDateHtml(t) {
    if (!t.due_date) { return ''; }
    const diff = dueDiff(t);
    const cls = t.completed ? '' : diff <= 0 ? 'imminent' : diff <= 3 ? 'soon' : '';
    return `<span class="todo-lrow-due-date ${cls}">${t.due_date.replaceAll('-', '.')}</span>`;
}

// 본문 링크 처리 — URL은 클릭 가능하게, 유튜브 링크는 미리보기 임베드 (최대 3개)
function contentHtml(text) {
    if (!text) { return '내용 없음'; }
    const html = esc(text).replace(/(https?:\/\/[^\s<]+)/g, m => `<a href="${m}" target="_blank" rel="noopener">${m}</a>`);
    const ids = [];
    const ytRe = /(?:youtube\.com\/(?:watch\?[^\s<]*v=|shorts\/|embed\/|live\/)|youtu\.be\/)([\w-]{11})/g;
    let m;
    while ((m = ytRe.exec(text)) && ids.length < 3) {
        if (!ids.includes(m[1])) { ids.push(m[1]); }
    }
    const embeds = ids.map(id => `<div class="yt-embed"><iframe src="https://www.youtube-nocookie.com/embed/${id}" loading="lazy" allowfullscreen allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>`).join('');
    return html + embeds;
}

function filteredTodos() {
    const mineOnly = document.getElementById('todoMineOnly').checked;
    const showDone = document.getElementById('todoShowDone').checked;
    let todos = TODOS.filter(t => showDone ? true : !t.completed);
    if (mineOnly) { todos = todos.filter(t => t.assignee_id === TODO_ME); }
    else if (MFILTER.size) { todos = todos.filter(t => MFILTER.has(t.assignee_id)); }
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
}

function boardHtml(todos) {
    // 할 일이 있는 인원만 컬럼 생성
    const byAssignee = new Map();
    todos.forEach(t => {
        if (!byAssignee.has(t.assignee_id)) { byAssignee.set(t.assignee_id, []); }
        byAssignee.get(t.assignee_id).push(t);
    });

    return [...byAssignee.entries()].map(([uid, items]) => {
        const m = memberById(uid) || { name: items[0].assignee, team: items[0].team };
        const openCount = items.filter(t => !t.completed).length;
        return `<div class="todo-col" data-assignee="${uid}"
            ondragover="event.preventDefault(); this.classList.add('drag-over')"
            ondragleave="this.classList.remove('drag-over')"
            ondrop="dropTodo(event, ${uid})">
            <div class="todo-col-head">
                <span class="todo-col-name">${esc(m.name)}</span>
                ${m.team ? `<span class="todo-col-team">${esc(m.team)}</span>` : ''}
                <span class="todo-col-count">${openCount}</span>
            </div>
            <div class="todo-col-body">${items.map(cardHtml).join('')}</div>
        </div>`;
    }).join('');
}

const PRI_WEIGHT = { high: 0, medium: 1, low: 2 };
let SELECTED_ID = null;

function listHtml(todos) {
    const sorted = [...todos].sort((a, b) =>
        (a.completed - b.completed)
        || ((a.due_date || '9999') < (b.due_date || '9999') ? -1 : (a.due_date || '9999') > (b.due_date || '9999') ? 1 : 0)
        || (PRI_WEIGHT[a.priority] - PRI_WEIGHT[b.priority]));
    return sorted.map(t => `<div class="todo-lrow ${t.completed ? 'done' : ''} ${t.id === SELECTED_ID ? 'sel' : ''}" onclick="selectTodo(${t.id})">
        <button type="button" class="todo-check ${t.completed ? 'on' : ''}" onclick="event.stopPropagation(); quickComplete(${t.id})" title="${t.completed ? '완료 취소' : '완료 처리'}">
            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
        <span class="todo-lrow-title">${esc(t.title)}</span>
        ${t.attachments.length ? `<span class="todo-attach-n">📎 ${t.attachments.length}</span>` : ''}
        <span class="todo-lrow-right">
            ${t.team ? `<span class="todo-team-label">${esc(t.team)}</span>` : ''}
            <span class="todo-pri ${t.priority}">${PRI_LABELS[t.priority] || t.priority}</span>
            ${dueDateHtml(t)}
            ${dueChip(t)}
            <span class="todo-lrow-assignee">${esc(t.assignee)}</span>
        </span>
    </div>`).join('');
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
            <span>담당 <b>${esc(t.assignee)}</b>${t.team ? ` · ${esc(t.team)}` : ''}</span>
            ${t.due_date ? `<span>기한 ${t.due_date.replaceAll('-', '.')}</span>` : ''}
            ${dueChip(t)}
            ${t.creator ? `<span>${esc(t.creator)} 등록 ${t.created_at}</span>` : ''}
            ${t.completed ? `<span>✅ 완료 ${t.completed_at}</span>` : ''}
        </div>
        <div class="todo-view-content">${contentHtml(t.content)}</div>
        ${t.attachments.length ? `<div class="todo-attach-list">${t.attachments.map(a => `
            <div class="todo-attach-item">
                ${a.mime_type && a.mime_type.startsWith('image/') ? `<img src="${a.url}" alt="" loading="lazy">` : '📄'}
                <a href="${a.url}" target="_blank" rel="noopener">${esc(a.file_name)}</a>
                <button type="button" class="todo-attach-del" onclick="deleteAttachment(${a.id})" title="첨부 삭제">✕</button>
            </div>`).join('')}</div>` : ''}
        <div class="tdp-actions">
            <button type="button" class="todo-btn danger" onclick="paneDelete()">삭제</button>
            <button type="button" class="todo-btn ghost" onclick="paneEdit()">수정</button>
            <button type="button" class="todo-btn success" onclick="quickComplete(${t.id})">${t.completed ? '완료 취소' : '완료 처리'}</button>
        </div>`;
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

function cardHtml(t) {
    const priLabel = PRI_LABELS[t.priority] || t.priority;
    return `<div class="todo-card p-${t.priority} ${t.completed ? 'done' : ''}" draggable="${IS_ADMIN}" data-id="${t.id}"
        ondragstart="event.dataTransfer.setData('text/plain', '${t.id}'); this.classList.add('dragging')"
        ondragend="this.classList.remove('dragging')"
        onclick="openTodoView(${t.id})">
        <div class="todo-card-meta">
            ${t.team ? `<span class="todo-team-label">${esc(t.team)}</span>` : ''}
            <span class="todo-pri ${t.priority}">${priLabel}</span>
        </div>
        <div class="todo-card-title-row">
            <button type="button" class="todo-check ${t.completed ? 'on' : ''}" onclick="event.stopPropagation(); quickComplete(${t.id})" title="${t.completed ? '완료 취소' : '완료 처리'}">
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            </button>
            <div class="todo-card-title">${esc(t.title)}</div>
        </div>
        <div class="todo-card-foot">
            ${dueChip(t)}
            ${t.attachments.length ? `<span class="todo-attach-n">📎 ${t.attachments.length}</span>` : ''}
            ${t.completed ? `<span class="todo-due">완료 ${t.completed_at}</span>` : ''}
        </div>
    </div>`;
}

async function dropTodo(ev, assigneeId) {
    ev.preventDefault();
    ev.currentTarget.classList.remove('drag-over');
    if (!IS_ADMIN) { return; } // 담당자 변경은 관리자 이상
    const id = parseInt(ev.dataTransfer.getData('text/plain'), 10);
    const todo = TODOS.find(t => t.id === id);
    if (!todo || todo.assignee_id === assigneeId) { return; }

    const res = await fetch(`/api/todos/${id}/assign`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ assignee_id: assigneeId }),
    });
    if (!res.ok) { alert('담당자 변경에 실패했습니다.'); return; }
    const m = memberById(assigneeId);
    todo.assignee_id = assigneeId;
    todo.assignee = m ? m.name : todo.assignee;
    todo.team = m ? m.team : todo.team;
    renderBoard();
}

async function refreshBoard() {
    const res = await fetch('/api/todos', { headers: { 'Accept': 'application/json' } });
    if (res.ok) { TODOS = (await res.json()).todos; renderBoard(); }
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
function fillAssigneeOptions(selected) {
    document.getElementById('tfAssignee').innerHTML = TODO_MEMBERS.map(m =>
        `<option value="${m.id}" ${m.id === selected ? 'selected' : ''}>${esc(m.name)}${m.team ? ` (${esc(m.team)})` : ''}</option>`
    ).join('');
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
    fillAssigneeOptions(todo ? todo.assignee_id : TODO_ME);
    document.getElementById('todoFormOverlay').classList.add('open');
    document.getElementById('tfTitle').focus();
}
function closeTodoForm() { document.getElementById('todoFormOverlay').classList.remove('open'); }

async function saveTodo() {
    const btn = document.getElementById('tfSaveBtn');
    const payload = {
        title: document.getElementById('tfTitle').value.trim(),
        priority: document.getElementById('tfPriority').value,
        due_date: document.getElementById('tfDue').value || null,
        content: document.getElementById('tfContent').value.trim() || null,
        // 일반 멤버는 본인에게만 등록 가능
        assignee_id: IS_ADMIN ? parseInt(document.getElementById('tfAssignee').value, 10) : TODO_ME,
    };
    if (!payload.title) { alert('타이틀을 입력하세요.'); return; }

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
        `<span>담당 <b>${esc(t.assignee)}</b>${t.team ? ` · ${esc(t.team)}` : ''}</span>`,
        t.due_date ? `<span>기한 ${t.due_date.replaceAll('-', '.')}</span>` : '',
        dueChip(t),
        t.creator ? `<span>${esc(t.creator)} 등록 ${t.created_at}</span>` : '',
        t.completed ? `<span>✅ 완료 ${t.completed_at}</span>` : '',
    ].filter(Boolean).join('');
    document.getElementById('tvContent').innerHTML = contentHtml(t.content);
    document.getElementById('tvAttachments').innerHTML = t.attachments.map(a => `
        <div class="todo-attach-item">
            ${a.mime_type && a.mime_type.startsWith('image/') ? `<img src="${a.url}" alt="" loading="lazy">` : '📄'}
            <a href="${a.url}" target="_blank" rel="noopener">${esc(a.file_name)}</a>
            <button type="button" class="todo-attach-del" onclick="deleteAttachment(${a.id})" title="첨부 삭제">✕</button>
        </div>`).join('');
    document.getElementById('tvCompleteBtn').textContent = t.completed ? '완료 취소' : '완료 처리';
    document.getElementById('todoViewOverlay').classList.add('open');
}
function closeTodoView() { document.getElementById('todoViewOverlay').classList.remove('open'); TODO_VIEW_ID = null; }

function editTodo() {
    const t = TODOS.find(x => x.id === TODO_VIEW_ID);
    if (!t) { return; }
    closeTodoView();
    openTodoForm(t);
}

// 카드 체크박스로 즉시 완료 토글
async function quickComplete(id) {
    const res = await fetch(`/api/todos/${id}/complete`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) { alert('처리에 실패했습니다.'); return; }
    await refreshBoard();
}

async function toggleComplete() {
    const res = await fetch(`/api/todos/${TODO_VIEW_ID}/complete`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': TODO_CSRF, 'Accept': 'application/json' },
    });
    if (!res.ok) { alert('처리에 실패했습니다.'); return; }
    closeTodoView();
    await refreshBoard();
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

// 오버레이 바깥 클릭으로 닫기
document.getElementById('todoFormOverlay').addEventListener('click', e => { if (e.target.id === 'todoFormOverlay') { closeTodoForm(); } });
document.getElementById('todoViewOverlay').addEventListener('click', e => { if (e.target.id === 'todoViewOverlay') { closeTodoView(); } });

renderMfilterPanel();
renderBoard();
</script>
@endpush
