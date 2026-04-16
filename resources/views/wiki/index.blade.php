@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '위키 - 닥터고블린 오피스')

@push('styles')
<style>
    .wiki-layout { display:flex; height:calc(100vh - 120px); overflow:hidden; }

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
    .wiki-cat-item.active .wiki-cat-count { background:rgba(var(--accent),0.15); color:var(--accent); }
    .wiki-sidebar-footer { padding:12px 16px; border-top:1px solid var(--border); }
    .btn-new { background:var(--accent); color:#1a1207; border:none; padding:8px 0; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; width:100%; text-align:center; }
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
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
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
        .wiki-layout { flex-direction:column; height:auto; }
        .wiki-sidebar { width:100%; border-right:none; border-bottom:1px solid var(--border); max-height:200px; }
        .wiki-cat-list { display:flex; flex-wrap:wrap; gap:4px; padding:8px 16px; overflow-x:auto; overflow-y:hidden; }
        .wiki-cat-item { padding:5px 12px; border-left:none; border-radius:20px; border:1px solid var(--border); white-space:nowrap; }
        .wiki-cat-item.active { border-color:var(--accent); }
    }
</style>
@endpush

@section('content')
@php
    $currentCat = request('category');
    $grouped = $wikis->groupBy('category');
    $allCats = $categories->count() ? $categories : collect(['일반']);
@endphp

<div class="wiki-layout">
    <!-- 좌측: 카테고리 사이드바 -->
    <div class="wiki-sidebar">
        <div class="wiki-sidebar-header">
            <div class="wiki-sidebar-title">📖 위키</div>
            <form method="GET" action="{{ route('wiki.index') }}" id="wikiSearchForm">
                <input class="wiki-sidebar-search" type="text" name="search" placeholder="문서 검색..." value="{{ request('search') }}">
                <input type="hidden" name="category" id="catInput" value="{{ $currentCat }}">
            </form>
        </div>
        <div class="wiki-cat-list">
            <div class="wiki-cat-item {{ !$currentCat ? 'active' : '' }}" onclick="filterCat('')">
                <span>전체</span>
                <span class="wiki-cat-count">{{ $wikis->count() }}</span>
            </div>
            @foreach($allCats as $cat)
                <div class="wiki-cat-item {{ $currentCat === $cat ? 'active' : '' }}" onclick="filterCat('{{ $cat }}')">
                    <span>{{ $cat }}</span>
                    <span class="wiki-cat-count">{{ $grouped->get($cat)?->count() ?? 0 }}</span>
                </div>
            @endforeach
        </div>
        <div class="wiki-sidebar-footer" style="display:flex;flex-direction:column;gap:6px;">
            <a href="{{ route('wiki.create') }}" class="btn-new" style="text-decoration:none;display:flex;align-items:center;justify-content:center;">+ 새 문서</a>
            <button class="btn-new" style="background:none;border:1px solid var(--border);color:var(--text);cursor:pointer;font-size:12px;" onclick="window.open('{{ route('wiki.broadcast-editor') }}','broadcast_editor','width=1400,height=900,scrollbars=yes,resizable=yes')">🎛️ 연결도 에디터</button>
        </div>
    </div>

    <!-- 우측: 문서 목록 -->
    <div class="wiki-main">
        <div class="wiki-main-header">
            <div class="wiki-main-title">{{ $currentCat ?: '전체 문서' }}</div>
            <div class="wiki-main-count">{{ $wikis->count() }}건</div>
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
                <div class="field-group" style="width:160px;">
                    <div class="field-label">카테고리 *</div>
                    <input class="field-input" name="category" required placeholder="예: 기술매뉴얼" list="catList">
                    <datalist id="catList">
                        @foreach($allCats as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                        <option value="기술매뉴얼">
                        <option value="업무가이드">
                        <option value="장비매뉴얼">
                        <option value="일반">
                    </datalist>
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
@endsection

@push('scripts')
<script>
function filterCat(cat) {
    document.getElementById('catInput').value = cat;
    document.getElementById('wikiSearchForm').submit();
}
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
