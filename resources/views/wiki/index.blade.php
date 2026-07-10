@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '위키 - 닥터고블린 오피스')

@push('styles')
<style>
    .wiki-layout { display:flex; height:calc(var(--full-h, 100vh) - var(--chrome-h, 120px)); overflow:hidden; }

    /* 좌측 사이드바 */
    .wiki-sidebar { width:240px; flex-shrink:0; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
    .wiki-sidebar-header { padding:16px; border-bottom:1px solid var(--border); display:flex; flex-direction:column; gap:8px; }
    .wiki-sidebar-title { font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:space-between; gap:6px; }
    .wiki-cat-mtoggle { display:none; align-items:center; gap:5px; background:var(--surface2); border:1px solid var(--border); color:var(--text); border-radius:8px; padding:5px 11px; font-size:12px; font-weight:600; cursor:pointer; }
    .wiki-cat-mtoggle .wcm-caret { font-size:10px; color:var(--text-muted); transition:transform .15s; }
    .wiki-sidebar.cat-open .wiki-cat-mtoggle .wcm-caret { transform:rotate(180deg); }
    .wiki-sidebar-search { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; width:100%; }
    .wiki-sidebar-search:focus { border-color:var(--accent); }
    .wiki-cat-list { flex:1; display:block; overflow-y:auto; padding:6px 0; min-height:0; }
    .wiki-cat-item { display:flex; align-items:center; justify-content:space-between; padding:8px 16px; font-size:13px; cursor:pointer; color:var(--text-muted); transition:all 0.12s; border-left:3px solid transparent; }
    .wiki-cat-item:hover { color:var(--text); background:var(--surface2); }
    .wiki-cat-item.active { color:var(--accent); background:var(--surface2); border-left-color:var(--accent); font-weight:600; }
    .wiki-cat-count { font-size:10px; background:var(--surface2); color:var(--text-muted); padding:1px 6px; border-radius:10px; min-width:18px; text-align:center; }
    /* 계층 트리 */
    .wiki-cat-row { display:flex; align-items:center; gap:4px; padding:5px 10px 5px 4px; font-size:13px; line-height:1.3; min-height:0; cursor:pointer; color:var(--text-muted); border-left:3px solid transparent; transition:all .12s; }
    .wiki-cat-row:hover { color:var(--text); background:var(--surface2); }
    .wiki-cat-row.active { color:var(--accent); background:var(--surface2); border-left-color:var(--accent); font-weight:600; }
    .wiki-cat-caret { flex-shrink:0; width:16px; text-align:center; font-size:10px; color:var(--text-muted); transition:transform .12s; cursor:pointer; }
    .wiki-cat-caret.open { transform:rotate(90deg); }
    .wiki-cat-caret.blank { visibility:hidden; }
    .wiki-cat-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .wiki-cat-children.collapsed { display:none; }
    /* 하위 계층: 중첩 컨테이너마다 들여쓰기 + 점선 가이드 라인 */
    #wikiCatTree .wiki-cat-children { margin-left:16px; border-left:1px dashed var(--border); }
    /* 계층별 타이포: 최상단 굵게·크게 / 하위 보통·작게 */
    #wikiCatTree .wiki-cat-row { font-size:12px; font-weight:400; }
    #wikiCatTree .wiki-cat-row.cat-top { font-size:14px; font-weight:700; }
    .wiki-cat-edit-btn { background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:7px; padding:6px 12px; font-size:12px; cursor:pointer; white-space:nowrap; }
    .wiki-cat-edit-btn:hover { border-color:var(--accent); color:var(--accent); }
    .wiki-cat-edit-btn.active { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 12%, transparent); }
    /* 게시물 선택 모드 액션 바 */
    .wiki-selbar { display:none; align-items:center; gap:8px; padding:8px 12px; margin-bottom:10px; border:1px solid var(--accent); border-radius:10px; background:color-mix(in srgb, var(--accent) 8%, transparent); flex-wrap:wrap; }
    .wiki-selbar.open { display:flex; }
    .wiki-selbar-count { font-size:12px; font-weight:700; color:var(--accent); white-space:nowrap; }
    .wiki-selbar-arrow { color:var(--text-muted); font-size:12px; }
    .wiki-selbar-target { flex:1; min-width:140px; max-width:280px; padding:6px 10px; font-size:12px; }
    .wiki-selbar-move { background:var(--accent); color:var(--accent-text); border:none; border-radius:7px; padding:6px 16px; font-size:12px; font-weight:700; cursor:pointer; }
    .wiki-selbar-move:disabled { opacity:0.45; cursor:default; }
    .wiki-selbar-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:7px; padding:6px 12px; font-size:12px; cursor:pointer; }
    .wiki-sel-cb { width:15px; height:15px; flex-shrink:0; pointer-events:none; accent-color:var(--accent); }
    .wiki-item.sel-on { border-color:var(--accent); background:color-mix(in srgb, var(--accent) 7%, transparent); }
    /* 카테고리 편집 모달 */
    .ce-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:300; align-items:center; justify-content:center; padding:20px; }
    .ce-overlay.open { display:flex; }
    .ce-modal { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:560px; max-height:88vh; display:flex; flex-direction:column; }
    .ce-head { display:flex; justify-content:space-between; align-items:center; padding:15px 18px; border-bottom:1px solid var(--border); font-size:15px; font-weight:800; }
    .ce-body { padding:14px 18px; overflow-y:auto; }
    .ce-row { display:flex; align-items:center; gap:6px; padding:5px 0; }
    .ce-handle { cursor:grab; color:var(--text-muted); font-size:13px; flex-shrink:0; user-select:none; opacity:0.5; }
    .ce-handle:active { cursor:grabbing; }
    .ce-row:hover .ce-handle { opacity:1; }
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
        .wiki-layout { flex-direction:column; }
        .wiki-sidebar { width:100%; border-right:none; border-bottom:1px solid var(--border); flex-shrink:0; max-height:none; }
        .wiki-cat-mtoggle { display:inline-flex; }
        /* 모바일: 카테고리 목록은 기본 접힘 → 토글로 펼침(세로 목록) */
        .wiki-cat-list { display:none; max-height:50vh; overflow-y:auto; padding:6px 0; }
        .wiki-sidebar.cat-open .wiki-cat-list { display:block; }
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

    // category_id → 계층 경로(이름 배열)
    $catById = $tree->keyBy('id');
    $wikiCatPath = function ($categoryId) use ($catById) {
        $path = [];
        $node = $categoryId ? $catById->get($categoryId) : null;
        while ($node) {
            array_unshift($path, $node->name);
            $node = $node->parent_id ? $catById->get($node->parent_id) : null;
        }

        return $path;
    };

    // 에디터 HTML → 미리보기 평문: 태그 제거 + HTML 엔티티(&nbsp; 등) 디코드 + 공백 정리
    $htmlPreview = function (?string $html, int $limit = 120): string {
        $text = preg_replace('/<[^>]+>/u', ' ', $html ?? '');           // 태그 → 공백 (단어 붙음 방지)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);                     // &nbsp; 잔여 문자
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return \Illuminate\Support\Str::limit($text, $limit);
    };

    // 문서 목록(클라이언트 렌더용) — @json 디렉티브 멀티라인 파싱 문제 회피 위해 여기서 구성
    $wikiDocs = $wikis->map(fn ($w) => [
        'id' => $w->id,
        'title' => $w->title,
        'category_id' => $w->category_id,
        'type' => $w->type ?? 'normal',
        'is_pinned' => (bool) $w->is_pinned,
        'creator' => $w->creator?->display_name ?? '알 수 없음',
        'updated' => $w->updated_at->format('Y.m.d H:i'),
        'preview' => $htmlPreview($w->content),
    ])->values();
@endphp

<div class="wiki-layout">
    <!-- 좌측: 카테고리 사이드바 -->
    <div class="wiki-sidebar" id="wikiSidebar">
        <div class="wiki-sidebar-header">
            <div class="wiki-sidebar-title">
                <span><x-icon name="book" :size="16"/> 위키</span>
                <button type="button" class="wiki-cat-mtoggle" onclick="document.getElementById('wikiSidebar').classList.toggle('cat-open')">카테고리 <span class="wcm-caret">▾</span></button>
            </div>
            <form method="GET" action="{{ route('wiki.index') }}" id="wikiSearchForm">
                <input class="wiki-sidebar-search" type="text" name="search" placeholder="문서 검색..." value="{{ request('search') }}">
                <input type="hidden" name="category" id="catInput" value="{{ $currentCat }}">
                <input type="hidden" name="cat" value="{{ request('cat') }}">
            </form>
        </div>
        <div class="wiki-cat-list" id="wikiCatTree"></div>
        <div class="wiki-sidebar-footer" style="display:flex;flex-direction:column;gap:6px;">
            <a href="{{ route('wiki.create') }}{{ request('cat') ? '?cat='.(int) request('cat') : '' }}" id="wikiNewBtn" class="btn-new" style="text-decoration:none;display:flex;align-items:center;justify-content:center;">+ 새 문서</a>
            <button class="btn-new" style="background:none;border:1px solid var(--border);color:var(--text);cursor:pointer;font-size:12px;" onclick="window.open('{{ route('wiki.broadcast-editor') }}','broadcast_editor','width=1400,height=900,scrollbars=yes,resizable=yes')"><x-icon name="gear" :size="13"/> 연결도 에디터</button>
        </div>
    </div>

    <!-- 우측: 문서 목록 -->
    <div class="wiki-main">
        <div class="wiki-main-header">
            <div class="wiki-main-title" id="wikiMainTitle">전체 문서</div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="wiki-main-count" id="wikiMainCount">{{ $wikis->count() }}건</div>
                <button type="button" class="wiki-cat-edit-btn" id="wikiSelToggle" onclick="toggleSelMode()"><x-icon name="check" :size="13"/> 게시물 편집</button>
                @if(auth()->user()->isAdmin())
                    <button type="button" class="wiki-cat-edit-btn" onclick="openCatEditor()"><x-icon name="folder" :size="13"/> 카테고리 편집</button>
                @endif
            </div>
        </div>

        <!-- 게시물 선택 모드 액션 바 -->
        <div class="wiki-selbar" id="wikiSelBar">
            <span class="wiki-selbar-count" id="wikiSelCount">0개 선택</span>
            <span class="wiki-selbar-arrow">→</span>
            <select class="field-input wiki-selbar-target" id="wikiSelTarget"></select>
            <button type="button" class="wiki-selbar-move" id="wikiSelMoveBtn" onclick="bulkMoveCategory()">이동</button>
            <button type="button" class="wiki-selbar-cancel" onclick="toggleSelMode()">취소</button>
        </div>

        <div class="wiki-list" id="wikiDocList"></div>
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
                @if(auth()->user()->isAdmin())
                <div class="field-group" style="width:130px;">
                    <div class="field-label">유형</div>
                    <select class="field-input" name="type" onchange="document.getElementById('wikiModalCatWrap').style.display=this.value==='normal'?'':'none'">
                        <option value="normal">일반 문서</option>
                        <option value="notice">공지사항</option>
                        <option value="update">업데이트</option>
                    </select>
                </div>
                @endif
                <div class="field-group" id="wikiModalCatWrap" style="width:200px;">
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
                            <x-icon name="clip" :size="12"/> 파일 첨부
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
                    <span style="font-size:12px;"><x-icon name="pin" :size="12"/> 상단 고정</span>
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
        <div class="ce-head"><span><x-icon name="folder" :size="14"/> 카테고리 편집</span><button class="ce-mini" onclick="closeCatEditor()"><x-icon name="close" :size="14"/></button></div>
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
let WIKI_CAT_COUNTS = @json($catCounts);
let WIKI_UNCAT = {{ (int) $uncategorized }};
let WIKI_CUR_CAT = {{ (int) request('cat') }};
// 특수 유형(공지사항/업데이트) — 카테고리 트리와 별개의 고정 섹션
const WIKI_TYPE_COUNTS = @json($typeCounts);
const WIKI_TYPE_LABELS = {notice:'공지사항', update:'업데이트'};
@php($curSpecialType = in_array(request('type'), ['notice', 'update'], true) ? request('type') : '')
let WIKI_CUR_TYPE = @json($curSpecialType);
const WIKI_DOCS = @json($wikiDocs);
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

    let html = `<div class="wiki-cat-row cat-top ${!WIKI_CUR_CAT ? 'active' : ''}" onclick="filterCatId('')">
        <span class="wiki-cat-caret blank"></span><span class="wiki-cat-name">전체</span><span class="wiki-cat-count">${total}</span></div>`;

    function node(c, depth) {
        const kids = childMap[c.id] || [];
        const canCollapse = kids.length && depth <= 3; // 3단계까지만 접기 토글
        const isCollapsed = collapsed.has(c.id);
        const caret = kids.length
            ? `<span class="wiki-cat-caret ${isCollapsed ? '' : 'open'}" onclick="event.stopPropagation();toggleWikiCat(${c.id})">▸</span>`
            : `<span class="wiki-cat-caret blank"></span>`;
        let h = `<div class="wiki-cat-row ${depth === 1 ? 'cat-top' : ''} ${WIKI_CUR_CAT === c.id ? 'active' : ''}" onclick="onCatRowClick(${c.id}, ${canCollapse ? 1 : 0})">
            ${canCollapse ? caret : `<span class="wiki-cat-caret blank"></span>`}
            <span class="wiki-cat-name">${wikiEsc(c.name)}</span>
            <span class="wiki-cat-count">${wikiTotalCount(c.id, childMap)}</span></div>`;
        if (kids.length) {
            h += `<div class="wiki-cat-children ${(canCollapse && isCollapsed) ? 'collapsed' : ''}">` + kids.map(k => node(k, depth + 1)).join('') + `</div>`;
        }
        return h;
    }
    html += (childMap[null] || []).map(c => node(c, 1)).join('');
    if (WIKI_UNCAT > 0) {
        html += `<div class="wiki-cat-row" onclick="filterCatId('')" style="opacity:0.7;"><span class="wiki-cat-caret blank"></span><span class="wiki-cat-name">미분류</span><span class="wiki-cat-count">${WIKI_UNCAT}</span></div>`;
    }
    // 고정 섹션(공지사항/업데이트) — 카테고리 트리 상단, 트리와 별개 관리
    let fixed = ['notice','update'].map(t =>
        `<div class="wiki-cat-row cat-top ${WIKI_CUR_TYPE === t ? 'active' : ''}" onclick="filterType('${t}')">
            <span class="wiki-cat-caret blank"></span>
            <span class="wiki-cat-name">${WIKI_TYPE_LABELS[t]}</span>
            <span class="wiki-cat-count">${WIKI_TYPE_COUNTS[t] || 0}</span></div>`
    ).join('') + '<div style="border-bottom:1px solid var(--border);margin:6px 0;"></div>';
    wrap.innerHTML = fixed + html;
}
// 행 클릭: 하위가 있으면 펼치기/접기 토글 + 해당 카테고리 필터를 동시에 처리
function onCatRowClick(id, collapsible) {
    if (collapsible) {
        const set = wikiCollapsedSet();
        set.has(id) ? set.delete(id) : set.add(id);
        wikiSaveCollapsed(set);
    }
    filterCatId(id); // 현재 카테고리 설정 + 트리/문서목록 즉시 갱신
}
function toggleWikiCat(id) {
    const set = wikiCollapsedSet();
    set.has(id) ? set.delete(id) : set.add(id);
    wikiSaveCollapsed(set);
    renderWikiTree();
}
// 특수 유형(공지/업데이트) 필터
function filterType(t) {
    WIKI_CUR_TYPE = (WIKI_CUR_TYPE === t) ? '' : t; // 재클릭 시 해제
    WIKI_CUR_CAT = 0;
    const params = new URLSearchParams(window.location.search);
    params.delete('cat'); params.delete('category');
    if (WIKI_CUR_TYPE) { params.set('type', WIKI_CUR_TYPE); } else { params.delete('type'); }
    history.replaceState(null, '', window.location.pathname + (params.toString() ? '?' + params : ''));
    renderWikiTree();
    renderDocList();
}
// 카테고리 필터 — 새로고침 없이 즉시 목록 갱신
function filterCatId(id) {
    WIKI_CUR_CAT = id ? parseInt(id, 10) : 0;
    WIKI_CUR_TYPE = '';
    const params = new URLSearchParams(window.location.search);
    if (WIKI_CUR_CAT) { params.set('cat', WIKI_CUR_CAT); } else { params.delete('cat'); }
    params.delete('category'); params.delete('type');
    history.replaceState(null, '', window.location.pathname + (params.toString() ? '?' + params : ''));
    // 새 문서 버튼에 현재 카테고리 반영 (선택된 카테고리에서 바로 작성)
    const newBtn = document.getElementById('wikiNewBtn');
    if (newBtn) newBtn.href = '/wiki/create' + (WIKI_CUR_CAT ? '?cat=' + WIKI_CUR_CAT : '');
    renderWikiTree();
    renderDocList();
}
// 해당 카테고리 + 하위 전체 id 집합
function wikiDescendantSet(rootId) {
    const childMap = wikiChildrenMap(WIKI_TREE_DATA);
    const ids = new Set([rootId]); const stack = [rootId];
    while (stack.length) { const cur = stack.pop(); (childMap[cur] || []).forEach(ch => { ids.add(ch.id); stack.push(ch.id); }); }
    return ids;
}
function wikiCatName(id) { const c = WIKI_TREE_DATA.find(x => x.id === id); return c ? c.name : '전체 문서'; }
function wikiCatPathStr(id) {
    if (!id) return '미분류';
    const byId = {}; WIKI_TREE_DATA.forEach(c => byId[c.id] = c);
    const p = []; let n = byId[id];
    while (n) { p.unshift(n.name); n = n.parent_id ? byId[n.parent_id] : null; }
    return p.length ? p.join(' › ') : '미분류';
}
function renderDocList() {
    const list = document.getElementById('wikiDocList');
    let docs = WIKI_DOCS;
    if (WIKI_CUR_TYPE) { docs = WIKI_DOCS.filter(d => d.type === WIKI_CUR_TYPE); }
    else if (WIKI_CUR_CAT) { const ids = wikiDescendantSet(WIKI_CUR_CAT); docs = WIKI_DOCS.filter(d => ids.has(d.category_id)); }
    document.getElementById('wikiMainTitle').textContent = WIKI_CUR_TYPE ? WIKI_TYPE_LABELS[WIKI_CUR_TYPE] : (WIKI_CUR_CAT ? wikiCatName(WIKI_CUR_CAT) : '전체 문서');
    document.getElementById('wikiMainCount').textContent = docs.length + '건';
    if (!docs.length) { list.innerHTML = '<div class="empty">해당하는 문서가 없습니다.</div>'; return; }
    list.innerHTML = docs.map(d => `<div class="wiki-item ${d.is_pinned ? 'pinned' : ''} ${WIKI_SEL_MODE && WIKI_SEL.has(d.id) ? 'sel-on' : ''}" onclick="${WIKI_SEL_MODE ? `toggleDocSel(${d.id})` : `location.href='/wiki/${d.id}'`}">
        <div class="wiki-item-header">${WIKI_SEL_MODE ? `<input type="checkbox" class="wiki-sel-cb" ${WIKI_SEL.has(d.id) ? 'checked' : ''} tabindex="-1">` : ''}${d.is_pinned ? '<span class="wiki-pin">📌</span>' : ''}<div class="wiki-title">${wikiEsc(d.title)}</div></div>
        <div class="wiki-meta">
            <span class="wiki-cat-badge">${d.type !== 'normal' ? WIKI_TYPE_LABELS[d.type] : wikiEsc(wikiCatPathStr(d.category_id))}</span>
            <span>${wikiEsc(d.creator)}</span><span>${d.updated}</span>
        </div>
        <div class="wiki-preview">${wikiEsc(d.preview)}</div>
    </div>`).join('');
}

// ── 게시물 편집 (선택 → 카테고리 일괄 이동) ──
let WIKI_SEL_MODE = false;
const WIKI_SEL = new Set();
function toggleSelMode() {
    WIKI_SEL_MODE = !WIKI_SEL_MODE;
    WIKI_SEL.clear();
    document.getElementById('wikiSelBar').classList.toggle('open', WIKI_SEL_MODE);
    document.getElementById('wikiSelToggle').classList.toggle('active', WIKI_SEL_MODE);
    if (WIKI_SEL_MODE) buildSelTargetOptions();
    updateSelCount();
    renderDocList();
}
function toggleDocSel(id) {
    WIKI_SEL.has(id) ? WIKI_SEL.delete(id) : WIKI_SEL.add(id);
    updateSelCount();
    renderDocList();
}
function updateSelCount() {
    const el = document.getElementById('wikiSelCount');
    if (el) el.textContent = WIKI_SEL.size + '개 선택';
    const btn = document.getElementById('wikiSelMoveBtn');
    if (btn) btn.disabled = WIKI_SEL.size === 0;
}
// 이동 대상 카테고리 옵션 (계층 순서 + 들여쓰기)
function buildSelTargetOptions() {
    const sel = document.getElementById('wikiSelTarget');
    const childMap = wikiChildrenMap(WIKI_TREE_DATA);
    let opts = '<option value="">(미분류)</option>';
    function walk(parentId, depth) {
        (childMap[parentId] || []).forEach(c => {
            opts += `<option value="${c.id}">${'— '.repeat(depth)}${wikiEsc(c.name)}</option>`;
            walk(c.id, depth + 1);
        });
    }
    walk(null, 0);
    sel.innerHTML = opts;
}
async function bulkMoveCategory() {
    if (!WIKI_SEL.size) return;
    const targetVal = document.getElementById('wikiSelTarget').value;
    const targetId = targetVal ? parseInt(targetVal, 10) : null;
    const res = await fetch('/api/wiki/bulk-category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WIKI_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ ids: [...WIKI_SEL], category_id: targetId }),
    });
    if (!res.ok) { alert('이동에 실패했습니다.'); return; }
    // 로컬 데이터 갱신 후 목록/트리 즉시 반영
    WIKI_DOCS.forEach(d => { if (WIKI_SEL.has(d.id)) d.category_id = targetId; });
    WIKI_CAT_COUNTS = {}; WIKI_UNCAT = 0;
    WIKI_DOCS.forEach(d => {
        if (d.category_id) WIKI_CAT_COUNTS[d.category_id] = (WIKI_CAT_COUNTS[d.category_id] || 0) + 1;
        else WIKI_UNCAT++;
    });
    toggleSelMode(); // 선택 모드 종료 (renderDocList 포함)
    renderWikiTree();
}
renderWikiTree();
renderDocList();

// 레이아웃 높이를 실제 가용 공간에 맞춤(하단 잘림 방지)
function fitWikiLayout() {
    const el = document.querySelector('.wiki-layout');
    if (!el) return;
    const top = el.getBoundingClientRect().top;
    el.style.height = Math.max(320, window.innerHeight - top) + 'px';
}
fitWikiLayout();
window.addEventListener('resize', fitWikiLayout);

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
        let h = `<div class="ce-row" draggable="true" style="padding-left:${(depth - 1) * 16}px" data-id="${c.id}" data-parent="${c.parent_id ?? ''}">
            <span class="ce-handle" title="드래그하여 순서 변경">⠿</span>
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
    ceAttachDnD();
}
let CE_DRAG_ID = null;
function ceAttachDnD() {
    document.querySelectorAll('#ceTree .ce-row').forEach(row => {
        row.ondragstart = e => { CE_DRAG_ID = parseInt(row.dataset.id, 10); e.dataTransfer.effectAllowed = 'move'; };
        row.ondragend = () => { CE_DRAG_ID = null; document.querySelectorAll('#ceTree .ce-row').forEach(r => { r.style.borderTop = ''; r.style.borderBottom = ''; }); };
        const sameParent = row => { const d = CE_DATA.find(c => c.id === CE_DRAG_ID); const t = CE_DATA.find(c => c.id === parseInt(row.dataset.id, 10)); return d && t && d.id !== t.id && (d.parent_id ?? null) === (t.parent_id ?? null); };
        row.ondragover = e => {
            if (!sameParent(row)) return;
            e.preventDefault();
            const rect = row.getBoundingClientRect(); const after = (e.clientY - rect.top) > rect.height / 2;
            row.style.borderTop = after ? '' : '2px solid var(--accent)';
            row.style.borderBottom = after ? '2px solid var(--accent)' : '';
        };
        row.ondragleave = () => { row.style.borderTop = ''; row.style.borderBottom = ''; };
        row.ondrop = async e => {
            row.style.borderTop = ''; row.style.borderBottom = '';
            if (!sameParent(row)) return;
            e.preventDefault();
            const drag = CE_DATA.find(c => c.id === CE_DRAG_ID);
            const tgt = CE_DATA.find(c => c.id === parseInt(row.dataset.id, 10));
            const rect = row.getBoundingClientRect(); const after = (e.clientY - rect.top) > rect.height / 2;
            let sibs = CE_DATA.filter(c => (c.parent_id ?? null) === (drag.parent_id ?? null))
                .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
                .filter(c => c.id !== drag.id);
            const idx = sibs.findIndex(c => c.id === tgt.id);
            sibs.splice(after ? idx + 1 : idx, 0, drag);
            await ceReorder(sibs.map((c, i) => ({ id: c.id, sort_order: i })));
        };
    });
}
async function ceReorder(items) {
    const res = await fetch('/api/wiki-categories/reorder', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WIKI_CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ items }) });
    if (!res.ok) { alert('순서 변경 실패'); return; }
    CE_DIRTY = true; await ceFetch();
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
