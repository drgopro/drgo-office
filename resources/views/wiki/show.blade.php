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

    /* 보기 모드 콘텐츠 */
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
    .wiki-content img { max-width:100%; border-radius:8px; margin:8px 0; height:auto; cursor:zoom-in; transition:opacity 0.15s; }
    .wiki-content img:hover { opacity:0.85; }
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

    /* Tiptap 에디터 */
    .tiptap-wrap { border:1px solid var(--border); border-radius:10px; background:var(--surface); }
    .tiptap-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:8px 10px; border-bottom:1px solid var(--border); background:var(--surface2); position:sticky; top:0; z-index:10; border-radius:10px 10px 0 0; }
    .tiptap-toolbar button { background:none; border:1px solid transparent; color:var(--text-muted); width:30px; height:30px; border-radius:6px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:all 0.12s; }
    .tiptap-toolbar button:hover { background:var(--surface); border-color:var(--border); color:var(--text); }
    .tiptap-toolbar button.is-active { background:var(--accent); color:#1a1207; border-color:var(--accent); }
    [data-theme="light"] .tiptap-toolbar button.is-active { color:#fff; }
    .tiptap-toolbar .sep { width:1px; height:20px; background:var(--border); margin:5px 4px; }
    .tiptap-toolbar .file-label { display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; cursor:pointer; color:var(--text-muted); font-size:13px; transition:all 0.12s; }
    .tiptap-toolbar .file-label:hover { background:var(--surface); color:var(--text); }
    .ProseMirror { padding:20px 24px; min-height:400px; outline:none; font-size:14px; line-height:1.85; color:var(--text); }
    .ProseMirror p { margin:0 0 10px; }
    .ProseMirror h1 { font-size:24px; font-weight:700; margin:20px 0 10px; }
    .ProseMirror h2 { font-size:20px; font-weight:700; margin:16px 0 8px; }
    .ProseMirror h3 { font-size:16px; font-weight:600; margin:14px 0 6px; }
    .ProseMirror ul, .ProseMirror ol { margin:0 0 10px; padding-left:24px; }
    .ProseMirror li { margin:3px 0; }
    .ProseMirror code { background:var(--surface2); padding:2px 6px; border-radius:4px; font-family:monospace; font-size:13px; }
    .ProseMirror pre { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 18px; margin:10px 0; overflow-x:auto; }
    .ProseMirror pre code { background:none; padding:0; }
    .ProseMirror blockquote { border-left:3px solid var(--accent); margin:10px 0; padding:6px 16px; color:var(--text-muted); }
    .ProseMirror img { max-width:100%; border-radius:8px; margin:6px 0; display:block; }
    .ProseMirror hr { border:none; border-top:1px solid var(--border); margin:16px 0; }
    .ProseMirror table { width:100%; border-collapse:collapse; margin:10px 0; }
    .ProseMirror th, .ProseMirror td { border:1px solid var(--border); padding:6px 10px; min-width:60px; }
    .ProseMirror th { background:var(--surface2); font-weight:600; }
    .ProseMirror p.is-editor-empty:first-child::before { content:attr(data-placeholder); color:var(--text-muted); float:left; pointer-events:none; height:0; }
    .ProseMirror img { cursor:pointer; transition:outline 0.15s; border-radius:6px; }

    /* 슬래시 메뉴 */
    .slash-menu { position:absolute; z-index:100; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:6px; min-width:200px; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:none; }
    .slash-menu.visible { display:block; }
    .slash-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:var(--text); transition:background 0.1s; }
    .slash-item:hover, .slash-item.selected { background:var(--surface2); }
    .slash-icon { width:28px; height:28px; border-radius:6px; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .slash-label { font-weight:500; }
    .slash-desc { font-size:11px; color:var(--text-muted); }

    /* 이미지 뷰어 모달 */
    .img-viewer { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; align-items:center; justify-content:center; flex-direction:column; }
    .img-viewer.open { display:flex; }
    .img-viewer-wrap { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; overflow:visible; }
    .img-viewer-wrap img { max-width:90vw; max-height:85vh; border-radius:8px; object-fit:contain; box-shadow:0 4px 32px rgba(0,0,0,0.5); transform-origin:center center; transition:transform 0.15s ease; user-select:none; -webkit-user-drag:none; }
    .img-viewer-close { position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.15); border:none; color:#fff; width:40px; height:40px; border-radius:50%; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; z-index:1; }
    .img-viewer-close:hover { background:rgba(255,255,255,0.3); }
    .img-viewer-zoom { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.6); border-radius:20px; padding:6px 14px; display:flex; align-items:center; gap:10px; z-index:1; }
    .img-viewer-zoom button { background:rgba(255,255,255,0.15); border:none; color:#fff; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; }
    .img-viewer-zoom button:hover { background:rgba(255,255,255,0.3); }
    .img-viewer-zoom span { color:#fff; font-size:12px; min-width:40px; text-align:center; }
    .img-viewer-hint { position:absolute; bottom:60px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.4); font-size:11px; pointer-events:none; }
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
    @if(str_starts_with(trim($wiki->content ?? ''), '<'))
        <div class="wiki-content view-mode" id="viewContent">{!! $wiki->content !!}</div>
    @else
        <div class="wiki-content view-mode" id="viewContent">{!! Str::markdown($wiki->content ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    @endif

    <!-- 수정 모드 -->
    <div class="edit-form" id="editForm">
        <div style="display:flex;gap:12px;margin-bottom:14px;">
            <div class="field-group" style="flex:1;margin:0;">
                <div class="field-label">제목</div>
                <input class="field-input" id="editTitle" value="{{ $wiki->title }}" required>
            </div>
            <div class="field-group" style="width:160px;margin:0;">
                <div class="field-label">카테고리</div>
                <input class="field-input" id="editCategory" value="{{ $wiki->category }}" required>
            </div>
            <div class="field-group" style="width:auto;margin:0;">
                <div class="field-label">고정</div>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 0;">
                    <input type="checkbox" id="editPinned" {{ $wiki->is_pinned ? 'checked' : '' }}>
                    <span style="font-size:12px;">📌</span>
                </label>
            </div>
        </div>

        <div class="tiptap-wrap">
            <div class="tiptap-toolbar" id="toolbar">
                <button onclick="editor.chain().focus().toggleHeading({level:1}).run()" title="제목 1">H1</button>
                <button onclick="editor.chain().focus().toggleHeading({level:2}).run()" title="제목 2">H2</button>
                <button onclick="editor.chain().focus().toggleHeading({level:3}).run()" title="제목 3">H3</button>
                <div class="sep"></div>
                <button onclick="editor.chain().focus().toggleBold().run()" title="굵게"><b>B</b></button>
                <button onclick="editor.chain().focus().toggleItalic().run()" title="기울임"><i>I</i></button>
                <button onclick="editor.chain().focus().toggleStrike().run()" title="취소선"><s>S</s></button>
                <button onclick="editor.chain().focus().toggleCode().run()" title="인라인 코드">&lt;&gt;</button>
                <div class="sep"></div>
                <button onclick="editor.chain().focus().toggleBulletList().run()" title="글머리 목록">•</button>
                <button onclick="editor.chain().focus().toggleOrderedList().run()" title="번호 목록">1.</button>
                <button onclick="editor.chain().focus().toggleBlockquote().run()" title="인용">"</button>
                <button onclick="editor.chain().focus().toggleCodeBlock().run()" title="코드 블록">{ }</button>
                <button onclick="editor.chain().focus().setHorizontalRule().run()" title="구분선">—</button>
                <div class="sep"></div>
                <button onclick="editor.chain().focus().setTextAlign('left').run()" title="좌측 정렬" style="font-size:11px;">≡←</button>
                <button onclick="editor.chain().focus().setTextAlign('center').run()" title="중앙 정렬" style="font-size:11px;">≡</button>
                <button onclick="editor.chain().focus().setTextAlign('right').run()" title="우측 정렬" style="font-size:11px;">→≡</button>
                <div class="sep"></div>
                <label class="file-label" title="이미지/파일 첨부">
                    📎
                    <input type="file" style="display:none;" onchange="uploadAndInsert(this.files[0])">
                </label>
                <button onclick="window.open('{{ route('wiki.broadcast-editor') }}?wiki_id={{ $wiki->id }}','broadcast_editor','width=1400,height=900,scrollbars=yes,resizable=yes')" style="background:none;border:1px solid transparent;color:var(--text-muted);padding:0 8px;height:30px;border-radius:6px;cursor:pointer;font-size:11px;display:inline-flex;align-items:center;gap:3px;white-space:nowrap;" title="방송 연결도 에디터">🎛️ 연결도</button>
            </div>
            <div id="editor"></div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
            <button onclick="toggleEdit()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">취소</button>
            <button onclick="saveWiki()" style="background:var(--accent);color:#1a1207;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
        </div>
    </div>
</div>

<div class="slash-menu" id="slashMenu"></div>

<!-- 이미지 뷰어 모달 -->
<div class="img-viewer" id="imgViewer">
    <button class="img-viewer-close" onclick="closeImgViewer()">✕</button>
    <div class="img-viewer-wrap" id="imgViewerWrap">
        <img id="imgViewerImg" src="" alt="">
    </div>
    <div class="img-viewer-zoom">
        <button onclick="imgViewerZoom(-0.2)">−</button>
        <span id="imgViewerLevel">100%</span>
        <button onclick="imgViewerZoom(0.2)">+</button>
        <button onclick="imgViewerZoomReset()" style="font-size:10px;width:auto;padding:0 8px;border-radius:12px;">맞춤</button>
    </div>
    <div class="img-viewer-hint">스크롤: 확대/축소 · 더블클릭: 원본 크기 · 드래그: 이동</div>
</div>
@endsection

@push('scripts')
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

// Image 확장 커스텀 — width/height 속성 보존
const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: { default: null, parseHTML: el => el.getAttribute('width') || el.style.width?.replace('px','') || null, renderHTML: attrs => attrs.width ? { width: attrs.width, style: `width:${attrs.width}px;height:auto;` } : {} },
            height: { default: null, renderHTML: () => ({}) },
        };
    },
});

const wikiContent = @json($wiki->content);

window.editor = new Editor({
    element: document.getElementById('editor'),
    extensions: [
        StarterKit.configure({ heading: { levels: [1,2,3] } }),
        ResizableImage.configure({ inline: false, allowBase64: true }),
        Link.configure({ openOnClick: false }),
        Placeholder.configure({ placeholder: '내용을 입력하세요... ("/" 입력으로 블록 추가)' }),
        Table.configure({ resizable: true }),
        TableRow,
        TableCell,
        TableHeader,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ],
    content: wikiContent,
    editorProps: {
        handleKeyDown(view, event) {
            if (event.key === '/') {
                setTimeout(() => showSlashMenu(view), 10);
            }
            return false;
        },
        handleDrop(view, event) {
            const files = event.dataTransfer?.files;
            if (files?.length) {
                event.preventDefault();
                uploadAndInsertToEditor(files[0]);
                return true;
            }
            return false;
        },
        handlePaste(view, event) {
            const items = event.clipboardData?.items;
            if (!items) return false;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    event.preventDefault();
                    uploadAndInsertToEditor(item.getAsFile());
                    return true;
                }
            }
            return false;
        },
    },
    onUpdate({ editor: e }) {
        updateToolbar(e);
    },
    onSelectionUpdate({ editor: e }) {
        updateToolbar(e);
    },
});

// 툴바 active 상태
function updateToolbar(e) {
    document.querySelectorAll('.tiptap-toolbar button').forEach(btn => btn.classList.remove('is-active'));
    const tb = document.getElementById('toolbar');
    if (e.isActive('heading',{level:1})) tb.children[0].classList.add('is-active');
    if (e.isActive('heading',{level:2})) tb.children[1].classList.add('is-active');
    if (e.isActive('heading',{level:3})) tb.children[2].classList.add('is-active');
    if (e.isActive('bold')) tb.children[4].classList.add('is-active');
    if (e.isActive('italic')) tb.children[5].classList.add('is-active');
    if (e.isActive('strike')) tb.children[6].classList.add('is-active');
    if (e.isActive('code')) tb.children[7].classList.add('is-active');
    if (e.isActive('bulletList')) tb.children[9].classList.add('is-active');
    if (e.isActive('orderedList')) tb.children[10].classList.add('is-active');
    if (e.isActive('blockquote')) tb.children[11].classList.add('is-active');
    if (e.isActive('codeBlock')) tb.children[12].classList.add('is-active');
}

// 슬래시 메뉴
const SLASH_ITEMS = [
    { icon:'📝', label:'텍스트', desc:'기본 텍스트', action:()=>editor.chain().focus().setParagraph().run() },
    { icon:'H1', label:'제목 1', desc:'큰 제목', action:()=>editor.chain().focus().toggleHeading({level:1}).run() },
    { icon:'H2', label:'제목 2', desc:'중간 제목', action:()=>editor.chain().focus().toggleHeading({level:2}).run() },
    { icon:'H3', label:'제목 3', desc:'작은 제목', action:()=>editor.chain().focus().toggleHeading({level:3}).run() },
    { icon:'•', label:'글머리 목록', desc:'순서 없는 목록', action:()=>editor.chain().focus().toggleBulletList().run() },
    { icon:'1.', label:'번호 목록', desc:'순서 있는 목록', action:()=>editor.chain().focus().toggleOrderedList().run() },
    { icon:'"', label:'인용', desc:'인용 블록', action:()=>editor.chain().focus().toggleBlockquote().run() },
    { icon:'{ }', label:'코드 블록', desc:'코드 삽입', action:()=>editor.chain().focus().toggleCodeBlock().run() },
    { icon:'—', label:'구분선', desc:'수평 구분선', action:()=>editor.chain().focus().setHorizontalRule().run() },
    { icon:'🖼', label:'이미지', desc:'파일에서 업로드', action:()=>document.querySelector('.tiptap-toolbar input[type=file]').click() },
    { icon:'📊', label:'표', desc:'3x3 표 삽입', action:()=>editor.chain().focus().insertTable({rows:3,cols:3,withHeaderRow:true}).run() },
];

let slashIdx = 0;
function showSlashMenu(view) {
    const menu = document.getElementById('slashMenu');
    const { from } = view.state.selection;
    const coords = view.coordsAtPos(from);
    menu.style.top = (coords.bottom + 4) + 'px';
    menu.style.left = coords.left + 'px';
    slashIdx = 0;
    renderSlashMenu('');
    menu.classList.add('visible');

    const handler = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); slashIdx = Math.min(slashIdx+1, SLASH_ITEMS.length-1); renderSlashMenu(''); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); slashIdx = Math.max(slashIdx-1, 0); renderSlashMenu(''); }
        else if (e.key === 'Enter') { e.preventDefault(); SLASH_ITEMS[slashIdx].action(); hideSlashMenu(); editor.commands.deleteRange({from:from-1,to:from}); }
        else if (e.key === 'Escape') { hideSlashMenu(); }
        else { setTimeout(()=>hideSlashMenu(), 100); }
        if (!menu.classList.contains('visible')) document.removeEventListener('keydown', handler);
    };
    document.addEventListener('keydown', handler);
    document.addEventListener('click', function once(){ hideSlashMenu(); document.removeEventListener('click', once); }, {once:true});
}
function renderSlashMenu(filter) {
    const menu = document.getElementById('slashMenu');
    menu.innerHTML = SLASH_ITEMS.map((item,i) =>
        `<div class="slash-item ${i===slashIdx?'selected':''}" onmouseenter="slashIdx=${i}" onclick="SLASH_ITEMS[${i}].action();hideSlashMenu();">
            <div class="slash-icon">${item.icon}</div>
            <div><div class="slash-label">${item.label}</div><div class="slash-desc">${item.desc}</div></div>
        </div>`
    ).join('');
}
function hideSlashMenu() { document.getElementById('slashMenu').classList.remove('visible'); }
window.SLASH_ITEMS = SLASH_ITEMS;
window.slashIdx = slashIdx;
window.hideSlashMenu = hideSlashMenu;

// 파일 업로드
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
window.uploadAndInsert = async function(file) {
    if (!file) return;
    uploadAndInsertToEditor(file);
};
async function uploadAndInsertToEditor(file) {
    const fd = new FormData(); fd.append('file', file); fd.append('wiki_id', '{{ $wiki->id }}');
    try {
        const res = await fetch('/api/wiki/upload', {method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:fd});
        if (!res.ok) { alert('업로드 실패'); return; }
        const data = await res.json();
        if (data.is_image) {
            editor.chain().focus().setImage({src:data.url,alt:data.name}).run();
        } else {
            editor.chain().focus().insertContent(`<a href="${data.url}" target="_blank">${data.name}</a>`).run();
        }
    } catch(e) { alert('업로드 오류'); }
}

// 저장 — HTML을 마크다운으로 변환하지 않고 HTML로 저장 (서버에서 처리)
window.saveWiki = async function() {
    const html = editor.getHTML();
    const title = document.getElementById('editTitle').value.trim();
    const category = document.getElementById('editCategory').value.trim();
    const isPinned = document.getElementById('editPinned').checked;
    if (!title) { alert('제목을 입력해주세요.'); return; }
    if (!category) { alert('카테고리를 선택해주세요.'); return; }
    if (!html || html==='<p></p>') { alert('내용을 입력해주세요.'); return; }

    const res = await fetch('{{ route("wiki.update", $wiki) }}', {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ title, category, content:html, is_pinned:isPinned?1:0 }),
    });
    if (res.ok) {
        location.reload();
    } else {
        try { const err=await res.json(); const msgs=err.errors?Object.values(err.errors).flat().join('\n'):(err.message||'저장 실패'); alert(msgs); }
        catch(e) { alert('저장 실패'); }
    }
};

// 수정 모드 토글
window.toggleEdit = function() {
    const editMode = document.getElementById('editForm').classList.toggle('active');
    document.getElementById('viewContent').classList.toggle('hidden', editMode);
    document.getElementById('viewActions').style.display = editMode ? 'none' : '';
    document.getElementById('viewTitle').style.display = editMode ? 'none' : '';
    if (editMode) editor.commands.focus();
};

// 이미지 리사이즈 — 네이버 에디터 스타일 (이미지 위에 크기 조절 바)
(function(){
    let popup = null, activeImg = null;

    function removePopup() {
        if (popup) { popup.remove(); popup = null; }
        if (activeImg) { activeImg.style.outline = ''; activeImg = null; }
    }

    function showPopup(img) {
        removePopup();
        activeImg = img;
        img.style.outline = '2px solid var(--accent)';
        img.style.outlineOffset = '2px';

        popup = document.createElement('div');
        popup.style.cssText = 'position:fixed;z-index:9999;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:8px 12px;box-shadow:0 4px 16px rgba(0,0,0,0.2);display:flex;align-items:center;gap:8px;font-size:12px;';
        popup.innerHTML = `
            <span style="color:var(--text-muted);font-size:11px;white-space:nowrap;">크기:</span>
            <button onclick="imgResize(0.25)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">25%</button>
            <button onclick="imgResize(0.5)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">50%</button>
            <button onclick="imgResize(0.75)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">75%</button>
            <button onclick="imgResize(1)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">100%</button>
            <span style="color:var(--text-muted);">|</span>
            <input type="number" id="imgWidthInput" value="${img.offsetWidth}" min="30" max="2000" style="width:60px;padding:3px 6px;border:1px solid var(--border);border-radius:5px;background:var(--surface2);color:var(--text);font-size:12px;text-align:center;">
            <span style="color:var(--text-muted);font-size:11px;">px</span>
            <button onclick="imgApplyWidth()" style="padding:3px 10px;border:none;border-radius:5px;background:var(--accent);color:#1a1207;font-size:11px;font-weight:600;cursor:pointer;">적용</button>
        `;
        document.body.appendChild(popup);

        // 이미지 위 가운데에 위치
        const rect = img.getBoundingClientRect();
        popup.style.left = Math.max(8, rect.left + (rect.width - popup.offsetWidth) / 2) + 'px';
        popup.style.top = Math.max(8, rect.top - popup.offsetHeight - 8) + 'px';
        // 화면 밖이면 아래로
        if (parseFloat(popup.style.top) < 8) {
            popup.style.top = (rect.bottom + 8) + 'px';
        }

        // Enter키로 적용
        popup.querySelector('#imgWidthInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); imgApplyWidth(); }
        });

        // 클릭 이벤트 전파 방지
        popup.addEventListener('mousedown', function(e) { e.stopPropagation(); });
    }

    function applyImgWidth(w) {
        if (!activeImg) return;
        w = Math.max(30, Math.min(2000, w));
        activeImg.style.width = w + 'px';
        activeImg.style.height = 'auto';
        activeImg.setAttribute('width', w);
        activeImg.removeAttribute('height');
        if (popup) popup.querySelector('#imgWidthInput').value = w;
        // Tiptap 내부 상태 업데이트
        try {
            const pos = editor.view.posAtDOM(activeImg, 0);
            if (pos != null) {
                const tr = editor.view.state.tr.setNodeMarkup(pos, undefined, { ...editor.view.state.doc.nodeAt(pos)?.attrs, width: String(w) });
                editor.view.dispatch(tr);
            }
        } catch(e) {}
    }

    // 퍼센트 리사이즈 — 에디터 너비 기준
    window.imgResize = function(ratio) {
        if (!activeImg) return;
        const editorEl = document.querySelector('.ProseMirror');
        const maxW = editorEl ? editorEl.clientWidth - 48 : 800;
        applyImgWidth(Math.round(maxW * ratio));
    };

    // px 직접 입력
    window.imgApplyWidth = function() {
        if (!activeImg || !popup) return;
        applyImgWidth(parseInt(popup.querySelector('#imgWidthInput').value) || 200);
    };

    // ProseMirror 내 이미지 클릭 감지
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG' && e.target.closest('.ProseMirror')) {
            e.preventDefault();
            showPopup(e.target);
        } else if (popup && !popup.contains(e.target)) {
            removePopup();
        }
    });

    // Escape로 닫기
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popup) removePopup();
    });
})();

// ── 위키 뷰 이미지 뷰어 (확대/축소/드래그) ──
(function(){
    let vZoom=1, vPanX=0, vPanY=0, vDragging=false, vStartX=0, vStartY=0;
    const viewer=document.getElementById('imgViewer');
    const vImg=document.getElementById('imgViewerImg');
    const vWrap=document.getElementById('imgViewerWrap');

    function vUpdate(){
        vImg.style.transform=`translate(${vPanX}px,${vPanY}px) scale(${vZoom})`;
        vImg.style.transition=vDragging?'none':'transform 0.15s ease';
        document.getElementById('imgViewerLevel').textContent=Math.round(vZoom*100)+'%';
    }
    function vReset(){vZoom=1;vPanX=0;vPanY=0;vUpdate();}

    window.openImgViewer=function(src){
        vReset();
        vImg.src=src;
        viewer.classList.add('open');
    };
    window.closeImgViewer=function(){
        viewer.classList.remove('open');
        vImg.src='';
    };
    window.imgViewerZoom=function(d){
        vZoom=Math.max(0.3,Math.min(8,vZoom+d));
        if(vZoom<1.05){vPanX=0;vPanY=0;}
        vUpdate();
    };
    window.imgViewerZoomReset=function(){vReset();};

    // 휠 줌
    viewer.addEventListener('wheel',function(e){
        e.preventDefault();
        const d=e.deltaY>0?-0.15:0.15;
        vZoom=Math.max(0.3,Math.min(8,vZoom+d*vZoom));
        if(vZoom<1.05){vPanX=0;vPanY=0;}
        vUpdate();
    },{passive:false});

    // 더블클릭 줌 토글
    vWrap.addEventListener('dblclick',function(e){
        e.preventDefault();
        if(vZoom>1.05){vReset();}else{vZoom=3;vPanX=0;vPanY=0;vUpdate();}
    });

    // 드래그 팬
    vWrap.addEventListener('mousedown',function(e){
        if(vZoom<=1.05)return;
        e.preventDefault();vDragging=true;vStartX=e.clientX-vPanX;vStartY=e.clientY-vPanY;
        vWrap.style.cursor='grabbing';
    });
    document.addEventListener('mousemove',function(e){
        if(!vDragging)return;
        vPanX=e.clientX-vStartX;vPanY=e.clientY-vStartY;vUpdate();
    });
    document.addEventListener('mouseup',function(){
        if(!vDragging)return;vDragging=false;vWrap.style.cursor='';
    });

    // 배경 클릭으로 닫기
    viewer.addEventListener('click',function(e){
        if(e.target===viewer&&vZoom<=1.05)closeImgViewer();
    });

    // 키보드
    document.addEventListener('keydown',function(e){
        if(!viewer.classList.contains('open'))return;
        if(e.key==='Escape')closeImgViewer();
        if(e.key==='+'||e.key==='=')imgViewerZoom(0.3);
        if(e.key==='-')imgViewerZoom(-0.3);
        if(e.key==='0')imgViewerZoomReset();
    });

    // 위키 뷰 콘텐츠 내 이미지 클릭 감지
    document.getElementById('viewContent')?.addEventListener('click',function(e){
        if(e.target.tagName==='IMG'){
            e.preventDefault();
            openImgViewer(e.target.src);
        }
    });
})();
</script>
@endpush
