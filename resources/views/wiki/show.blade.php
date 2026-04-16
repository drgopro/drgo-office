@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', $wiki->title . ' - 위키')

@push('styles')
<style>
    .wiki-wrap { padding:24px; max-width:900px; margin:0 auto; }
    .wiki-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; gap:12px; }
    .wiki-back { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .wiki-back:hover { color:var(--text); }
    .wiki-title-row { display:flex; align-items:center; gap:8px; margin-bottom:4px; }
    .wiki-title-text { font-size:22px; font-weight:700; }
    .wiki-cat { font-size:10px; padding:3px 10px; border-radius:12px; background:var(--surface2); color:var(--accent); font-weight:600; border:1px solid var(--border); }
    .wiki-meta { font-size:11px; color:var(--text-muted); display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .wiki-actions { display:flex; gap:6px; flex-shrink:0; }
    .wiki-actions button, .wiki-actions a { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 14px; border-radius:8px; font-size:12px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
    .wiki-actions button:hover, .wiki-actions a:hover { border-color:var(--accent); color:var(--accent); }
    .wiki-actions .btn-del { border-color:var(--red); color:var(--red); }
    .wiki-actions .btn-del:hover { background:rgba(200,50,50,0.1); }

    .wiki-content { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:28px 32px; line-height:1.85; font-size:14px; }
    .wiki-content h1 { font-size:24px; font-weight:700; margin:24px 0 12px; padding-bottom:8px; border-bottom:2px solid var(--border); }
    .wiki-content h2 { font-size:20px; font-weight:700; margin:20px 0 10px; padding-bottom:6px; border-bottom:1px solid var(--border); }
    .wiki-content h3 { font-size:16px; font-weight:600; margin:16px 0 8px; }
    .wiki-content p { margin:0 0 12px; }
    .wiki-content ul, .wiki-content ol { margin:0 0 12px; padding-left:24px; }
    .wiki-content li { margin:4px 0; }
    .wiki-content code { background:var(--surface2); padding:2px 6px; border-radius:4px; font-size:13px; font-family:monospace; }
    .wiki-content pre { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 18px; overflow-x:auto; margin:12px 0; font-size:13px; line-height:1.5; }
    .wiki-content pre code { background:none; padding:0; }
    .wiki-content blockquote { border-left:3px solid var(--accent); margin:12px 0; padding:8px 16px; color:var(--text-muted); background:var(--surface2); border-radius:0 8px 8px 0; }
    .wiki-content table { width:100%; border-collapse:collapse; margin:12px 0; }
    .wiki-content th, .wiki-content td { border:1px solid var(--border); padding:8px 12px; text-align:left; font-size:13px; }
    .wiki-content th { background:var(--surface2); font-weight:600; }
    .wiki-content img { max-width:100%; border-radius:8px; margin:8px 0; }
    .wiki-content a { color:var(--accent); text-decoration:underline; }
    .wiki-content hr { border:none; border-top:1px solid var(--border); margin:20px 0; }

    /* 수정 모드 */
    .edit-form { display:none; }
    .edit-form.active { display:block; }
    .view-mode.hidden { display:none; }
    .field-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
    .field-label { font-size:10px; letter-spacing:0.15em; color:var(--text-muted); text-transform:uppercase; }
    .field-input { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:14px; outline:none; width:100%; }
    .field-input:focus { border-color:var(--accent); }
    .field-textarea { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 18px; color:var(--text); font-size:13px; outline:none; width:100%; resize:vertical; min-height:400px; line-height:1.7; font-family:monospace; box-sizing:border-box; }
    .field-textarea:focus { border-color:var(--accent); }
</style>
@endpush

@section('content')
<div class="wiki-wrap">
    <div class="wiki-header">
        <div style="flex:1;min-width:0;">
            <a href="{{ route('wiki.index') }}" class="wiki-back">← 위키 목록</a>
            <div class="wiki-title-row" id="viewTitle">
                @if($wiki->is_pinned)<span style="font-size:14px;">📌</span>@endif
                <span class="wiki-title-text">{{ $wiki->title }}</span>
                <span class="wiki-cat">{{ $wiki->category }}</span>
            </div>
            <div class="wiki-meta">
                <span>작성: {{ $wiki->creator?->display_name ?? '알 수 없음' }} · {{ $wiki->created_at->format('Y.m.d H:i') }}</span>
                @if($wiki->updated_by && $wiki->updated_at->gt($wiki->created_at->addMinutes(1)))
                    <span>수정: {{ $wiki->updater?->display_name ?? '' }} · {{ $wiki->updated_at->format('Y.m.d H:i') }}</span>
                @endif
            </div>
        </div>
        <div class="wiki-actions" id="viewActions">
            <button onclick="openActivityLog('Wiki',{{ $wiki->id }},'{{ addslashes($wiki->title) }} 수정 로그')">📋 로그</button>
            <button onclick="toggleEdit()">수정</button>
            <form method="POST" action="{{ route('wiki.destroy', $wiki) }}" style="display:inline;" onsubmit="return confirm('이 문서를 삭제하시겠습니까?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-del">삭제</button>
            </form>
        </div>
    </div>

    <!-- 보기 모드 -->
    <div class="wiki-content view-mode" id="viewContent">{!! nl2br(e($wiki->content)) !!}</div>

    <!-- 수정 모드 -->
    <div class="edit-form" id="editForm">
        <form method="POST" action="{{ route('wiki.update', $wiki) }}">
            @csrf @method('PATCH')
            <div style="display:flex;gap:12px;">
                <div class="field-group" style="flex:1;">
                    <div class="field-label">제목</div>
                    <input class="field-input" name="title" value="{{ $wiki->title }}" required>
                </div>
                <div class="field-group" style="width:160px;">
                    <div class="field-label">카테고리</div>
                    <input class="field-input" name="category" value="{{ $wiki->category }}" required>
                </div>
                <div class="field-group" style="width:auto;">
                    <div class="field-label">고정</div>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 0;">
                        <input type="checkbox" name="is_pinned" value="1" {{ $wiki->is_pinned ? 'checked' : '' }}>
                        <span style="font-size:12px;">📌</span>
                    </label>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">내용</div>
                <textarea class="field-textarea" name="content" required>{{ $wiki->content }}</textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="toggleEdit()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">취소</button>
                <button type="submit" style="background:var(--accent);color:#1a1207;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
let editMode = false;
function toggleEdit() {
    editMode = !editMode;
    document.getElementById('viewContent').classList.toggle('hidden', editMode);
    document.getElementById('viewActions').style.display = editMode ? 'none' : '';
    document.getElementById('viewTitle').style.display = editMode ? 'none' : '';
    document.getElementById('editForm').classList.toggle('active', editMode);
    if (editMode) document.querySelector('#editForm textarea').focus();
}
</script>
@endpush
