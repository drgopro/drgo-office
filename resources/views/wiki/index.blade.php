@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '위키 - 닥터고블린 오피스')

@push('styles')
<style>
    .wiki-layout { display:flex; height:calc(var(--full-h, 100vh) - var(--chrome-h, 120px)); overflow:hidden; }

    /* 좌측 사이드바 */
    .wiki-sidebar { width:240px; flex-shrink:0; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
    .wiki-sidebar-header { padding:16px; border-bottom:1px solid var(--border); display:flex; flex-direction:column; gap:8px; }
    .wiki-sidebar-title { font-size:14px; font-weight:700; display:flex; align-items:center; gap:6px; }
    .wiki-sidebar-search { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; width:100%; }
    .wiki-sidebar-search:focus { border-color:var(--accent); }
    .wiki-cat-list { flex:1; overflow-y:auto; padding:8px 0; }
    .wiki-cat-item { display:flex; align-items:center; justify-content:space-between; padding:8px 16px; font-size:13px; cursor:pointer; color:var(--text-muted); transition:all 0.12s; border-left:3px solid transparent; }
    .wiki-cat-item:hover { color:var(--text); background:var(--surface2); }
    .wiki-cat-item.active { color:var(--accent); background:var(--surface2); border-left-color:var(--accent); font-weight:600; }
    .wiki-cat-count { font-size:10px; background:var(--surface2); color:var(--text-muted); padding:1px 6px; border-radius:10px; min-width:18px; text-align:center; }
    /* 계층 트리 */
    .wiki-cat-row { display:flex; align-items:center; gap:4px; padding:7px 10px 7px 0; font-size:13px; cursor:pointer; color:var(--text-muted); border-left:3px solid transparent; transition:all .12s; }
    .wiki-cat-row:hover { color:var(--text); background:var(--surface2); }
    .wiki-cat-row.active { color:var(--accent); background:var(--surface2); border-left-color:var(--accent); font-weight:600; }
    .wiki-cat-caret { flex-shrink:0; width:16px; text-align:center; font-size:10px; color:var(--text-muted); transition:transform .12s; cursor:pointer; }
    .wiki-cat-caret.open { transform:rotate(90deg); }
    .wiki-cat-caret.empty { visibility:hidden; }
    .wiki-cat-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .wiki-cat-children.collapsed { display:none; }
    .wiki-cat-edit-btn { background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:7px; padding:6px 12px; font-size:12px; cursor:pointer; white-space:nowrap; }
    .wiki-cat-edit-btn:hover { border-color:var(--accent); color:var(--accent); }
    /* 카테고리 편집 모달 */
    .ce-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:300; align-items:center; justify-content:center; padding:20px; }
    .ce-overlay.open { display:flex; }
    .ce-modal { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:560px; max-height:88vh; display:flex; flex-direction:column; }
    .ce-head { display:flex; justify-content:space-between; align-items:center; padding:15px 18px; border-bottom:1px solid var(--border); font-size:15px; font-weight:800; }
    .ce-body { padding:14px 18px; overflow-y:auto; }
    .ce-row { display:flex; align-items:center; gap:6px; padding:5px 0; }
    .ce-row .ce-name { flex:1; min-width:0; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ce-row input.ce-edit { flex:1; padding:5px 8px; border:1px solid var(--accent); border-radius:6px; background:var(--surface2); color:var(--text); font-size:13px; }
    .ce-mini { background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:6px; padding:3px 8px; font-size:11px; cursor:pointer; flex-shrink:0; }
    .ce-mini:hover { border-color:var(--accent); color:var(--accent); }
    .ce-mini.del:hover { border-color:var(--red); color:var(--red); }
    .ce-addtop { display:flex; gap:6px; margin-bottom:12px; }
    .ce-addtop input { flex:1; padding:8px 11px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); color:var(--text); font-size:13px; }
    .ce-foot { padding:12px 18px; border-top:1px solid var(--border); text-align:right; }
    .wiki-cat-item.active .wiki-cat-count { background:rgba(var(--accent),0.15); color:var(--accent); }
    .wiki-sidebar-footer { padding:12px 16px; border-top:1px solid var(--border); }
    .btn-new { background:var(--accent); color:var(--accent-text); border:none; padding:8px 0; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; width:100%; text-align:center; }
    [data-theme="light"] .btn-new { color:#fff; }

    /* 우측 문서 목록 */
    .wiki-main { flex:1; overflow-y:auto; padding:20px 24px; min-width:0; }
    .wiki-main-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .wiki-main-title { font-size:16px; font-weight:700; }
    .wiki-main-count { font-size:12px; color:var(--text-muted); }

    .wiki-list { display:flex; flex-direction:column; gap:6px; }
    .wiki-item { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:14px 16px; cursor:pointer; transition:all 0.12s; }
    .wiki-item:hover { border-color:var(--accent); background:var(--surface2); }
    .wiki-item.pinned { border-left:3px solid var(--accent); }
    .wiki-item-header { display:flex; align-items:center; gap:6px; margin-bottom:4px; }
    .wiki-title { font-size:14px; font-weight:600; }
    .wiki-pin { font-size:11px; color:var(--accent); }
    .wiki-meta { font-size:11px; color:var(--text-muted); display:flex; gap:8px; align-items:center; }
    .wiki-cat-badge { font-size:10px; padding:2px 8px; border-radius:10px; background:var(--surface2); color:var(--accent); font-weight:600; }
    .wiki-preview { font-size:12px; color:var(--text-muted); margin-top:6px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; line-height:1.5; }
    .empty { text-align:center; padding:40px; color:var(--text-muted); font-size:13px; }

    /* 모달 */
    .wiki-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9000; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
    .wiki-modal.open { display:flex; }
    .wiki-modal-body { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:700px; max-height:90vh; overflow-y:auto; padding:24px; }
    .field-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
    .field-label { font-size:10px; letter-spacing:0.15em; color:var(--text-muted); text-transform:uppercase; }
    .field-input, .field-textarea { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:14px; outline:none; width:100%; box-sizing:border-box; }
    .field-input:focus, .field-textarea:focus { border-color:var(--accent); }
    .field-textarea { resize:vertical; min-height:300px; line-height:1.7; font-family:monospace; font-size:13px; }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .btn-save { color:#fff; }
    .hidden { display:none !important; }
    .wiki-preview-pane h1 { font-size:22px; font-weight:700; margin:16px 0 8px; padding-bottom:6px; border-bottom:2px solid var(--border); }
    .wiki-preview-pane h2 { font-size:18px; font-weight:700; margin:14px 0 6px; padding-bottom:4px; border-bottom:1px solid var(--border); }
    .wiki-preview-pane h3 { font-size:15px; font-weight:600; margin:12px 0 6px; }
    .wiki-preview-pane p { margin:0 0 10px; }
    .wiki-preview-pane ul, .wiki-preview-pane ol { margin:0 0 10px; padding-left:20px; }
    .wiki-preview-pane code { background:var(--surface); padding:1px 5px; border-radius:3px; font-size:12px; }
    .wiki-preview-pane pre { background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:10px 14px; overflow-x:auto; margin:8px 0; font-size:12px; }
    .wiki-preview-pane pre code { background:none; padding:0; }
    .wiki-preview-pane blockquote { border-left:3px solid var(--accent); margin:8px 0; padding:6px 14px; color:var(--text-muted); background:var(--surface); border-radius:0 6px 6px 0; }
    .wiki-preview-pane table { width:100%; border-collapse:collapse; margin:8px 0; }
    .wiki-preview-pane th, .wiki-preview-pane td { border:1px solid var(--border); padding:6px 10px; font-size:12px; }
    .wiki-preview-pane th { background:var(--surface); font-weight:600; }
    .wiki-preview-pane img { max-width:100%; border-radius:6px; }

    @media (max-width:768px) {
        .wiki-layout { flex-direction:column; height:calc(var(--full-h, 100vh) - var(--chrome-h, 120px)); }
        .wiki-sidebar { width:100%; border-right:none; border-bottom:1px solid var(--border); max-height:38vh; flex-shrink:0; }
        /* 계층 트리는 모바일에서도 세로 목록 유지(가로 칩 X) */
        .wiki-cat-list { display:block; overflow-y:auto; padding:6px 0; }
    }
</style>
@endpush

@section('content')
@php
    $currentCat = request('category');
    $grouped = $wikis->groupBy('category');
    $allCats = $categories->count() ? $categories : collect(['일반']);

    // 트리를 들여쓰기된 평면 목록으로 (select 옵션용)
    $catFlat = [];
    $walkCat = function ($parentId, $depth) use (&$walkCat, $tree, &$catFlat) {
        foreach ($tree->where('parent_id', $parentId) as $c) {
            $catFlat[] = ['id' => $c->id, 'name' => str_repeat('— ', $depth - 1).$c->name];
            $walkCat($c->id, $depth + 1);
        }
    };
    $walkCat(null, 1);
@endphp

<div class="wiki-layout">
    <!-- 좌측: 카테고리 사이드바 -->
    <div class="wiki-sidebar">
        <div class="wiki-sidebar-header">
            <div class="wiki-sidebar-title">📖 위키</div>
            <form method="GET" action="{{ route('wiki.index') }}" id="wikiSearchForm">
                <input class="wiki-sidebar-search" type="text" name="search" placeholder="문서 검색..." value="{{ request('search') }}">
                <input type="hidden" name="category" id="catInput" value="{{ $currentCat }}">
                <input type="hidden" name="cat" value="{{ request('cat') }}">
            </form>
        </div>
        <div class="wiki-cat-list" id="wikiCatTree"></div>
        <div class="wiki-sidebar-footer" style="display:flex;flex-direction:column;gap:6px;">
            <a href="{{ route('wiki.create') }}" class="btn-new" style="text-decoration:none;display:flex;align-items:center;justify-content:center;">+ 새 문서</a>
            <button class="btn-new" style="background:none;border:1px solid var(--border);color:var(--text);cursor:pointer;font-size:12px;" onclick="window.open('{{ route('wiki.broadcast-editor') }}','broadcast_editor','width=1400,height=900,scrollbars=yes,resizable=yes')">🎛️ 연결도 에디터</button>
        </div>
    </div>

    <!-- 우측: 문서 목록 -->
    <div class="wiki-main">
        <div class="wiki-main-header">
            <div class="wiki-main-title">{{ $currentCat ?: '전체 문서' }}</div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="wiki-main-count">{{ $wikis->count() }}건</div>
                @if(auth()->user()->isAdmin())
                    <button type="button" class="wiki-cat-edit-btn" onclick="openCatEditor()">🗂 카테고리 편집</button>
                @endif
            </div>
        </div>

        @if($wikis->count() > 0)
            <div class="wiki-list">
                @foreach($wikis as $wiki)
                <div class="wiki-item {{ $wiki->is_pinned ? 'pinned' : '' }}" onclick="location.href='{{ route('wiki.show', $wiki) }}'">
                    <div class="wiki-item-header">
                        @if($wiki->is_pinned)<span class="wiki-pin">📌</span>@endif
                        <div class="wiki-title">{{ $wiki->title }}</div>
                    </div>
                    <div class="wiki-meta">
                        <span class="wiki-cat-badge">{{ $wiki->category }}</span>
                        <span>{{ $wiki->creator?->display_name ?? '알 수 없음' }}</span>
                        <span>{{ $wiki->updated_at->format('Y.m.d H:i') }}</span>
                    </div>
                    <div class="wiki-preview">{{ Str::limit(strip_tags($wiki->content), 120) }}</div>
                </div>
                @endforeach
            </div>
        @else
            <div class="empty">{{ $currentCat ? $currentCat.' 카테고리에 문서가 없습니다.' : '등록된 문서가 없습니다.' }}</div>
        @endif
    </div>
</div>

<!-- 새 문서 모달 -->
<div class="wiki-modal" id="wikiModal">
    <div class="wiki-modal-body">
        <div style="font-size:18px;font-weight:700;margin-bottom:16px;">새 문서 작성</div>
        <form method="POST" action="{{ route('wiki.store') }}">
            @csrf
            <div style="display:flex;gap:12px;">
                <div class="field-group" style="flex:1;">
                    <div class="field-label">제목 *</div>
                    <input class="field-input" name="title" required placeholder="문서 제목">
                </div>
                <div class="field-group" style="width:200px;">
                    <div class="field-label">카테고리</div>
                    <select class="field-input" name="category_id">
                        <option value="">(미분류)</option>
                        @foreach($catFlat as $cf)
                            <option value="{{ $cf['id'] }}">{{ $cf['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label" style="display:flex;justify-content:space-between;align-items:center;">
                    <span>내용 * (마크다운 지원)</span>
                    <div style="display:flex;gap:6px;">
                        <label style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid var(--border);border-radius:6px;font-size:11px;cursor:pointer;color:var(--text-muted);">
                            📎 파일 첨부
                            <input type="file" id="wikiFileInput" style="display:none;" onchange="uploadWikiFile(this.files[0],'newContent')">
                        </label>
                        <button type="button" onclick="document.getElementById('previewPane').classList.toggle('hidden');this.textContent=this.textContent==='미리보기'?'미리보기 닫기':'미리보기'" style="padding:4px 10px;border:1px solid var(--border);border-radius:6px;font-size:11px;cursor:pointer;background:none;color:var(--text-muted);">미리보기</button>
                    </div>
                </div>
                <div style="display:flex;gap:12px;">
                    <textarea class="field-textarea" name="content" id="newContent" required placeholder="# 제목&#10;&#10;내용을 입력하세요..." oninput="updatePreview('newContent','previewPane')" style="flex:1;"></textarea>
                    <div id="previewPane" class="wiki-preview-pane hidden" style="flex:1;min-height:300px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px 18px;overflow-y:auto;font-size:14px;line-height:1.85;"></div>
                </div>
            </div>
            <div class="field-group">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="is_pinned" value="1">
                    <span style="font-size:12px;">📌 상단 고정</span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeWikiModal()">취소</button>
                <button type="submit" class="btn-save">저장</button>
            </div>
        </form>
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- 카테고리 편집 모달 -->
<div class="ce-overlay" id="catEditModal">
    <div class="ce-modal">
        <div class="ce-head"><span>🗂 카테고리 편집</span><button class="ce-mini" onclick="closeCatEditor()">✕</button></div>
        <div class="ce-body">
            <div class="ce-addtop">
                <input type="text" id="ceTopInput" placeholder="새 최상위 카테고리 이름" onkeydown="if(event.key==='Enter'&&!event.isComposing){event.preventDefault();ceAdd(null,this);}">
                <button class="ce-mini" onclick="ceAdd(null, document.getElementById('ceTopInput'))">+ 추가</button>
            </div>
            <div id="ceTree"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:10px;">· 대분류 포함 최대 5단계까지 추가할 수 있습니다. · 삭제 시 하위 카테고리는 상위로 이동합니다.</div>
        </div>
        <div class="ce-foot"><button class="ce-mini" onclick="closeCatEditor()">닫기</button></div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
const WIKI_TREE_DATA = @json($tree->map(fn ($c) => ['id' => $c->id, 'parent_id' => $c->parent_id, 'name' => $c->name])->values());
const WIKI_CAT_COUNTS = @json($catCounts);
const WIKI_UNCAT = {{ (int) $uncategorized }};
const WIKI_CUR_CAT = {{ (int) request('cat') }};
const WIKI_CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const WIKI_COLLAPSE_KEY = 'wikiCatCollapsed';

function wikiCollapsedSet() { try { return new Set(JSON.parse(localStorage.getItem(WIKI_COLLAPSE_KEY) || '[]')); } catch (e) { return new Set(); } }
function wikiSaveCollapsed(set) { localStorage.setItem(WIKI_COLLAPSE_KEY, JSON.stringify([...set])); }
function wikiEsc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

function wikiChildrenMap(data) {
    const m = {};
    data.forEach(c => { (m[c.parent_id] = m[c.parent_id] || []).push(c); });
    return m;
}
// 노드 + 하위 전체 문서 수
function wikiTotalCount(id, childMap) {
    let n = WIKI_CAT_COUNTS[id] || 0;
    (childMap[id] || []).forEach(ch => { n += wikiTotalCount(ch.id, childMap); });
    return n;
}

function renderWikiTree() {
    const wrap = document.getElementById('wikiCatTree');
    if (!wrap) return;
    const childMap = wikiChildrenMap(WIKI_TREE_DATA);
    const collapsed = wikiCollapsedSet();
    const total = WIKI_TREE_DATA.reduce((s, c) => s + (WIKI_CAT_COUNTS[c.id] || 0), 0) + WIKI_UNCAT;

    let html = `<div class="wiki-cat-row ${!WIKI_CUR_CAT ? 'active' : ''}" onclick="filterCatId('')">
        <span class="wiki-cat-caret empty"></span><span class="wiki-cat-name">전체</span><span class="wiki-cat-count">${total}</span></div>`;

    function node(c, depth) {
        const kids = childMap[c.id] || [];
        const canCollapse = kids.length && depth <= 3; // 3단계까지만 접기 토글
        const isCollapsed = collapsed.has(c.id);
        const caret = kids.length
            ? `<span class="wiki-cat-caret ${isCollapsed ? '' : 'open'}" onclick="event.stopPropagation();toggleWikiCat(${c.id})">▸</span>`
            : `<span class="wiki-cat-caret empty"></span>`;
        let h = `<div class="wiki-cat-row ${WIKI_CUR_CAT === c.id ? 'active' : ''}" style="padding-left:${depth * 14}px" onclick="filterCatId(${c.id})">
            ${canCollapse ? caret : `<span class="wiki-cat-caret empty"></span>`}
            <span class="wiki-cat-name">${wikiEsc(c.name)}</span>
            <span class="wiki-cat-count">${wikiTotalCount(c.id, childMap)}</span></div>`;
        if (kids.length) {
            h += `<div class="wiki-cat-children ${(canCollapse && isCollapsed) ? 'collapsed' : ''}">` + kids.map(k => node(k, depth + 1)).join('') + `</div>`;
        }
        return h;
    }
    html += (childMap[null] || []).map(c => node(c, 1)).join('');
    if (WIKI_UNCAT > 0) {
        html += `<div class="wiki-cat-row" onclick="filterCatId('')" style="opacity:0.7;"><span class="wiki-cat-caret empty"></span><span class="wiki-cat-name">미분류</span><span class="wiki-cat-count">${WIKI_UNCAT}</span></div>`;
    }
    wrap.innerHTML = html;
}
function toggleWikiCat(id) {
    const set = wikiCollapsedSet();
    set.has(id) ? set.delete(id) : set.add(id);
    wikiSaveCollapsed(set);
    renderWikiTree();
}
function filterCatId(id) {
    const params = new URLSearchParams(window.location.search);
    if (id) { params.set('cat', id); } else { params.delete('cat'); }
    params.delete('category');
    window.location.search = params.toString();
}
renderWikiTree();

function filterCat(cat) {
    document.getElementById('catInput').value = cat;
    document.getElementById('wikiSearchForm').submit();
}

// ── 카테고리 편집 ──
let CE_DATA = [];
let CE_DIRTY = false;
async function openCatEditor() { await ceFetch(); document.getElementById('catEditModal').classList.add('open'); }
function closeCatEditor() {
    document.getElementById('catEditModal').classList.remove('open');
    if (CE_DIRTY) { CE_DIRTY = false; location.reload(); }
}
async function ceFetch() {
    const res = await fetch('/api/wiki-categories', { headers: { 'Accept': 'application/json' } });
    CE_DATA = res.ok ? await res.json() : [];
    ceRender();
}
function ceRender() {
    const map = {}; CE_DATA.forEach(c => { (map[c.parent_id] = map[c.parent_id] || []).push(c); });
    function node(c, depth) {
        const kids = map[c.id] || [];
        let h = `<div class="ce-row" style="padding-left:${(depth - 1) * 16}px" data-id="${c.id}">
            <span class="ce-name">${wikiEsc(c.name)}</span>
            <button class="ce-mini" onclick="ceRenameStart(${c.id})" title="이름 변경">✎</button>
            ${depth < 5 ? `<button class="ce-mini" onclick="ceAddChild(${c.id})">+ 하위</button>` : ''}
            <button class="ce-mini del" onclick="ceDelete(${c.id})" title="삭제">🗑</button>
        </div>`;
        if (kids.length) h += kids.map(k => node(k, depth + 1)).join('');
        return h;
    }
    document.getElementById('ceTree').innerHTML = (map[null] || []).map(c => node(c, 1)).join('')
        || '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">카테고리가 없습니다. 위에서 추가하세요.</div>';
}
let CE_BUSY = false;
async function ceAdd(parentId, inputEl) {
    const name = (inputEl.value || '').trim(); if (!name || CE_BUSY) return;
    CE_BUSY = true; inputEl.disabled = true;
    try {
        const res = await fetch('/api/wiki-categories', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WIKI_CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ name, parent_id: parentId }) });
        if (!res.ok) { const e = await res.json().catch(() => ({})); alert(e.message || '추가 실패'); return; }
        inputEl.value = ''; CE_DIRTY = true; await ceFetch();
    } finally { CE_BUSY = false; inputEl.disabled = false; }
}
function ceAddChild(id) {
    const row = document.querySelector(`.ce-row[data-id="${id}"]`); if (!row) return;
    if (row.nextElementSibling && row.nextElementSibling.classList.contains('ce-addrow')) { row.nextElementSibling.querySelector('input').focus(); return; }
    const div = document.createElement('div');
    div.className = 'ce-row ce-addrow';
    div.style.paddingLeft = (parseInt(row.style.paddingLeft || '0', 10) + 16) + 'px';
    div.innerHTML = `<input class="ce-edit" placeholder="하위 카테고리 이름">`;
    const inp = div.querySelector('input');
    inp.onkeydown = e => { if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); ceAdd(id, inp); } else if (e.key === 'Escape') { div.remove(); } };
    inp.onblur = () => { if (!inp.value.trim()) div.remove(); };
    row.after(div); inp.focus();
}
function ceRenameStart(id) {
    const row = document.querySelector(`.ce-row[data-id="${id}"]`); if (!row) return;
    const span = row.querySelector('.ce-name'); const cur = span.textContent;
    const inp = document.createElement('input'); inp.className = 'ce-edit'; inp.value = cur;
    let done = false;
    const finish = async (save) => { if (done) return; done = true; inp.onblur = null;
        if (save && inp.value.trim() && inp.value.trim() !== cur) { await ceRename(id, inp.value.trim()); } else { ceRender(); } };
    span.replaceWith(inp); inp.focus(); inp.select();
    inp.onkeydown = e => { if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); finish(true); } else if (e.key === 'Escape') { finish(false); } };
    inp.onblur = () => finish(true);
}
async function ceRename(id, name) {
    const res = await fetch(`/api/wiki-categories/${id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WIKI_CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ name }) });
    if (!res.ok) { alert('이름 변경 실패'); ceRender(); return; }
    CE_DIRTY = true; await ceFetch();
}
async function ceDelete(id) {
    if (!confirm('이 카테고리를 삭제할까요?\n하위 카테고리는 상위로 이동하고, 연결된 문서는 상위(또는 미분류)로 이동합니다.')) return;
    const res = await fetch(`/api/wiki-categories/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': WIKI_CSRF, 'Accept': 'application/json' } });
    if (!res.ok) { alert('삭제 실패'); return; }
    CE_DIRTY = true; await ceFetch();
}
document.getElementById('catEditModal')?.addEventListener('click', e => { if (e.target.id === 'catEditModal') closeCatEditor(); });

function openWikiModal() { document.getElementById('wikiModal').classList.add('open'); }
function closeWikiModal() { document.getElementById('wikiModal').classList.remove('open'); }
document.getElementById('wikiModal').addEventListener('click', e => { if (e.target === document.getElementById('wikiModal')) closeWikiModal(); });

// 마크다운 → HTML 간이 변환 (클라이언트 사이드 라이브 프리뷰)
function mdToHtml(md) {
    let html = md
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        // code block
        .replace(/```(\w*)\n([\s\S]*?)```/g, (m,lang,code)=>`<pre><code>${code.trim()}</code></pre>`)
        // inline code
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        // headings
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h1>$1</h1>')
        // bold/italic
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/~~(.+?)~~/g, '<del>$1</del>')
        // images
        .replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" style="max-width:100%;border-radius:6px;">')
        // links
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
        // blockquote
        .replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')
        // unordered list
        .replace(/^[-*] (.+)$/gm, '<li>$1</li>')
        // ordered list
        .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
        // hr
        .replace(/^---$/gm, '<hr>')
        // paragraphs
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>');
    // wrap li in ul
    html = html.replace(/(<li>.*?<\/li>)/gs, '<ul>$1</ul>').replace(/<\/ul>\s*<ul>/g, '');
    return '<p>'+html+'</p>';
}

function updatePreview(textareaId, previewId) {
    const ta = document.getElementById(textareaId);
    const pv = document.getElementById(previewId);
    if (!ta || !pv) return;
    pv.innerHTML = mdToHtml(ta.value);
}

// 파일 업로드 → 마크다운 삽입
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
async function uploadWikiFile(file, textareaId) {
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res = await fetch('/api/wiki/upload', {
            method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body:fd
        });
        if (!res.ok) { alert('업로드 실패'); return; }
        const data = await res.json();
        const ta = document.getElementById(textareaId);
        if (ta) {
            const pos = ta.selectionStart;
            const before = ta.value.substring(0, pos);
            const after = ta.value.substring(pos);
            ta.value = before + '\n' + data.markdown + '\n' + after;
            ta.focus();
            updatePreview(textareaId, textareaId === 'newContent' ? 'previewPane' : 'editPreviewPane');
        }
    } catch(e) { alert('업로드 오류'); }
}

// 드래그 앤 드롭
['newContent'].forEach(id => {
    const ta = document.getElementById(id);
    if (!ta) return;
    ta.addEventListener('dragover', e => { e.preventDefault(); ta.style.borderColor='var(--accent)'; });
    ta.addEventListener('dragleave', () => { ta.style.borderColor='var(--border)'; });
    ta.addEventListener('drop', e => {
        e.preventDefault(); ta.style.borderColor='var(--border)';
        if (e.dataTransfer.files.length) uploadWikiFile(e.dataTransfer.files[0], id);
    });
    // 클립보드 붙여넣기 (이미지)
    ta.addEventListener('paste', e => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                e.preventDefault();
                uploadWikiFile(item.getAsFile(), id);
                break;
            }
        }
    });
});
</script>
@endpush
