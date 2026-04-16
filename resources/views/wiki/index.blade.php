@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '위키 - 닥터고블린 오피스')

@push('styles')
<style>
    .wiki-wrap { padding:24px; max-width:1000px; margin:0 auto; }
    .wiki-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
    .wiki-header h1 { font-size:20px; font-weight:700; }
    .wiki-toolbar { display:flex; gap:8px; flex-wrap:wrap; }
    .wiki-search { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; min-width:200px; }
    .wiki-search:focus { border-color:var(--accent); }
    .cat-filter { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; cursor:pointer; outline:none; }
    .btn-new { background:var(--accent); color:#1a1207; border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
    [data-theme="light"] .btn-new { color:#fff; }

    .wiki-list { display:flex; flex-direction:column; gap:8px; }
    .wiki-item { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:16px 20px; cursor:pointer; transition:all 0.15s; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .wiki-item:hover { border-color:var(--accent); transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .wiki-item.pinned { border-left:3px solid var(--accent); }
    .wiki-title { font-size:15px; font-weight:600; margin-bottom:4px; }
    .wiki-meta { font-size:11px; color:var(--text-muted); display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .wiki-cat { font-size:10px; padding:2px 8px; border-radius:10px; background:var(--surface2); color:var(--accent); font-weight:600; }
    .wiki-preview { font-size:12px; color:var(--text-muted); margin-top:6px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
    .wiki-pin { font-size:11px; color:var(--accent); }

    /* 새 문서 모달 */
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
    .empty { text-align:center; padding:40px; color:var(--text-muted); font-size:13px; }
</style>
@endpush

@section('content')
<div class="wiki-wrap">
    <div class="wiki-header">
        <h1>📖 위키</h1>
        <div class="wiki-toolbar">
            <form method="GET" action="{{ route('wiki.index') }}" style="display:flex;gap:8px;">
                <input class="wiki-search" type="text" name="search" placeholder="검색..." value="{{ request('search') }}">
                <select class="cat-filter" name="category" onchange="this.form.submit()">
                    <option value="">전체 카테고리</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </form>
            <button class="btn-new" onclick="openWikiModal()">+ 새 문서</button>
        </div>
    </div>

    @if($wikis->count() > 0)
        <div class="wiki-list">
            @foreach($wikis as $wiki)
            <div class="wiki-item {{ $wiki->is_pinned ? 'pinned' : '' }}" onclick="location.href='{{ route('wiki.show', $wiki) }}'">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        @if($wiki->is_pinned)<span class="wiki-pin">📌</span>@endif
                        <div class="wiki-title">{{ $wiki->title }}</div>
                    </div>
                    <div class="wiki-meta">
                        <span class="wiki-cat">{{ $wiki->category }}</span>
                        <span>{{ $wiki->creator?->display_name ?? '알 수 없음' }}</span>
                        <span>{{ $wiki->updated_at->format('Y.m.d H:i') }}</span>
                        @if($wiki->updated_by && $wiki->updated_by !== $wiki->created_by)
                            <span>수정: {{ $wiki->updater?->display_name }}</span>
                        @endif
                    </div>
                    <div class="wiki-preview">{{ Str::limit(strip_tags($wiki->content), 150) }}</div>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <div class="empty">등록된 문서가 없습니다.</div>
    @endif
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
                        @foreach($categories as $cat)
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
                <div class="field-label">내용 * (마크다운 지원)</div>
                <textarea class="field-textarea" name="content" required placeholder="# 제목&#10;&#10;내용을 입력하세요...&#10;&#10;## 소제목&#10;- 항목 1&#10;- 항목 2"></textarea>
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
function openWikiModal() { document.getElementById('wikiModal').classList.add('open'); }
function closeWikiModal() { document.getElementById('wikiModal').classList.remove('open'); }
document.getElementById('wikiModal').addEventListener('click', e => { if (e.target === document.getElementById('wikiModal')) closeWikiModal(); });
</script>
@endpush
