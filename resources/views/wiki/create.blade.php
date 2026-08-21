@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '새 문서 작성 - 위키')

@push('styles')
<style>
    .wiki-wrap { padding:24px; max-width:900px; margin:0 auto; }
    .wiki-back { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .wiki-back:hover { color:var(--text); }
    .field-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
    .field-label { font-size:10px; letter-spacing:0.15em; color:var(--text-muted); text-transform:uppercase; }
    .field-input { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:14px; outline:none; width:100%; box-sizing:border-box; }
    .field-input:focus { border-color:var(--accent); }

    .tiptap-wrap { border:1px solid var(--border); border-radius:10px; background:var(--surface); }
    .tiptap-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:8px 10px; border-bottom:1px solid var(--border); background:var(--surface2); position:sticky; top:0; z-index:10; border-radius:10px 10px 0 0; }
    .tiptap-toolbar button { background:none; border:1px solid transparent; color:var(--text-muted); width:30px; height:30px; border-radius:6px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:all 0.12s; }
    .tiptap-toolbar button:hover { background:var(--surface); border-color:var(--border); color:var(--text); }
    .tiptap-toolbar button.is-active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
    [data-theme="light"] .tiptap-toolbar button.is-active { color:#fff; }
    .tiptap-toolbar .sep { width:1px; height:20px; background:var(--border); margin:5px 4px; }
    .tiptap-toolbar .tool-btn { width:auto; padding:0 8px; font-size:11px; gap:4px; display:inline-flex; white-space:nowrap; height:30px; }
    .ProseMirror { padding:20px 24px; min-height:400px; outline:none; font-size:14px; line-height:1.85; color:var(--text); }
    .ProseMirror p { margin:0 0 10px; }
    .ProseMirror h1 { font-size:24px; font-weight:700; margin:20px 0 10px; }
    .ProseMirror h2 { font-size:20px; font-weight:700; margin:16px 0 8px; }
    .ProseMirror h3 { font-size:16px; font-weight:600; margin:14px 0 6px; }
    .ProseMirror ul, .ProseMirror ol { margin:0 0 10px; padding-left:24px; }
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

    .slash-menu { position:absolute; z-index:100; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:6px; min-width:200px; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:none; }
    /* 선택 영역 색상 팝업 */
    .fmt-bubble { position:fixed; z-index:9500; display:none; align-items:center; gap:3px; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:5px 8px; box-shadow:0 6px 24px rgba(0,0,0,0.28); }
    .fmt-bubble.show { display:flex; }
    .fmt-bubble .fb-label { font-size:10px; color:var(--text-muted); margin:0 2px; }
    .fmt-bubble .fb-sep { width:1px; height:18px; background:var(--border); margin:0 4px; }
    .fmt-bubble .fb-c { width:24px; height:24px; border:1px solid var(--border); border-radius:6px; background:var(--surface2); font-size:13px; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1; }
    .fmt-bubble .fb-c:hover, .fmt-bubble .fb-h:hover { outline:2px solid var(--accent); }
    .fmt-bubble .fb-h { width:22px; height:22px; border:1px solid var(--border); border-radius:6px; cursor:pointer; }
    .fmt-bubble .fb-reset { background:var(--surface2); color:var(--text-muted); font-weight:600; font-size:11px; }
    /* 본문 하이라이트 표시 */
    .ProseMirror mark { border-radius:3px; padding:0 2px; }
    .slash-menu.visible { display:block; }
    .slash-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:var(--text); transition:background 0.1s; }
    .slash-item:hover, .slash-item.selected { background:var(--surface2); }
    .slash-icon { width:28px; height:28px; border-radius:6px; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .slash-label { font-weight:500; }
    .slash-desc { font-size:11px; color:var(--text-muted); }

    /* 템플릿 메뉴 */
    .tpl-menu { display:none; position:absolute; right:0; top:calc(100% + 6px); width:300px; background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.25); z-index:900; overflow:hidden; }
    .tpl-menu.open { display:block; }
    .tpl-list { max-height:280px; overflow-y:auto; padding:6px; }
    .tpl-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:7px; cursor:pointer; font-size:13px; }
    .tpl-item:hover { background:var(--surface2); }
    .tpl-item-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tpl-item-meta { font-size:11px; color:var(--text-muted); flex-shrink:0; }
    .tpl-del { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 5px; border-radius:4px; flex-shrink:0; }
    .tpl-del:hover { color:#e03131; background:var(--surface2); }
    .tpl-empty { padding:16px 12px; text-align:center; color:var(--text-muted); font-size:12px; line-height:1.6; }
    .tpl-foot { border-top:1px solid var(--border); padding:8px; }
    .tpl-save-btn { width:100%; background:none; border:1px dashed var(--border); color:var(--text-muted); padding:7px 0; border-radius:7px; font-size:12px; cursor:pointer; }
    .tpl-save-btn:hover { color:var(--text); border-color:var(--accent); }
</style>
@endpush

@section('content')
@php
    // 목록 복귀 링크 — 진입 시 보고 있던 분류(카테고리/특수유형) 유지
    $wikiBackParams = array_filter([
        'type' => array_key_exists(request('type', ''), \App\Models\Wiki::SPECIAL_TYPES) ? request('type') : null,
        'cat' => ctype_digit((string) request('cat')) ? (int) request('cat') : null,
    ]);
@endphp
<div class="wiki-wrap">
    <a href="{{ route('wiki.index', $wikiBackParams) }}" class="wiki-back">← 위키 목록</a>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin:8px 0 20px;">
        <h1 style="font-size:20px;font-weight:700;margin:0;">새 문서 작성</h1>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span id="autosaveStatus" style="font-size:12px;color:var(--text-muted);"></span>
            <button onclick="openDraftModal()" style="background:none;border:1px solid var(--border);color:var(--text);padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer;">불러오기</button>
            <div style="position:relative;">
                <button id="tplBtn" onclick="toggleTplMenu()" style="background:none;border:1px solid var(--border);color:var(--text);padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer;">템플릿</button>
                <div id="tplMenu" class="tpl-menu"></div>
            </div>
            <button onclick="saveNewWiki()" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">등록</button>
        </div>
    </div>

    {{-- 임시저장 불러오기 모달 --}}
    <div id="draftOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:300;align-items:center;justify-content:center;" onclick="if(event.target===this)closeDraftModal()">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:480px;max-width:92vw;max-height:80vh;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 18px 12px;">
                <b style="font-size:15px;">임시저장 글 불러오기</b>
                <button onclick="closeDraftModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
            </div>
            <div style="font-size:11.5px;color:var(--text-muted);padding:0 18px 10px;">자동 저장된 초안입니다. 마지막 수정 후 7일이 지나면 자동 삭제됩니다.</div>
            <div id="draftList" style="flex:1;overflow-y:auto;padding:0 12px 14px;"></div>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-bottom:14px;">
        <div class="field-group" style="flex:1;margin:0;">
            <div class="field-label">제목 *</div>
            <input class="field-input" id="wikiTitle" required placeholder="문서 제목">
        </div>
        @php
            // ?type= 프리셀렉트 — 관리자가 아니면 회의록만 허용
            $allowedTypes = auth()->user()->isAdmin() ? array_keys(\App\Models\Wiki::SPECIAL_TYPES) : ['meeting'];
            $presetType = in_array(request('type'), $allowedTypes, true) ? request('type') : 'normal';
        @endphp
        <div class="field-group" style="width:130px;margin:0;">
            <div class="field-label">유형</div>
            <select class="field-input" id="wikiType" onchange="document.getElementById('wikiCatWrap').style.display=this.value==='normal'?'':'none'">
                <option value="normal">일반 문서</option>
                @if(auth()->user()->isAdmin())
                <option value="notice" @selected($presetType === 'notice')>공지사항</option>
                <option value="update" @selected($presetType === 'update')>업데이트</option>
                @endif
                <option value="meeting" @selected($presetType === 'meeting')>회의록</option>
            </select>
        </div>
        <div class="field-group" id="wikiCatWrap" style="width:220px;margin:0;{{ $presetType !== 'normal' ? 'display:none;' : '' }}">
            <div class="field-label">카테고리</div>
            @php
                $catFlat = [];
                $walkCat = function ($parentId, $depth) use (&$walkCat, $tree, &$catFlat) {
                    foreach ($tree->where('parent_id', $parentId) as $c) {
                        $catFlat[] = ['id' => $c->id, 'name' => str_repeat('— ', $depth - 1).$c->name];
                        $walkCat($c->id, $depth + 1);
                    }
                };
                $walkCat(null, 1);
            @endphp
            <select class="field-input" id="wikiCategoryId">
                <option value="">(미분류)</option>
                @foreach($catFlat as $cf)<option value="{{ $cf['id'] }}">{{ $cf['name'] }}</option>@endforeach
            </select>
        </div>
        <div class="field-group" style="width:auto;margin:0;">
            <div class="field-label">고정</div>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 0;">
                <input type="checkbox" id="wikiPinned">
                <span style="font-size:12px;">📌</span>
            </label>
        </div>
    </div>

    {{-- 열람 권한 — 팀을 선택하면 그 팀(+작성자·관리자)만 볼 수 있음. 비우면 전체 공개 --}}
    <div class="field-group" style="margin-bottom:14px;">
        <div class="field-label">열람 권한 <span style="font-weight:400;color:var(--text-muted);text-transform:none;letter-spacing:0;">선택한 팀만 열람 (아무것도 선택하지 않으면 전체 공개)</span></div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:6px 0;">
            @foreach($teams as $t)
                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;border:1px solid var(--border);border-radius:999px;padding:6px 13px;">
                    <input type="checkbox" class="wiki-allowed-team" value="{{ $t->id }}">
                    {{ $t->name }}
                </label>
            @endforeach
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
            <label class="tool-btn" title="이미지/파일 첨부" style="cursor:pointer;color:var(--text-muted);font-size:13px;">
                📎
                <input type="file" style="display:none;" onchange="uploadAndInsert(this.files[0])">
            </label>
            <button class="tool-btn" onclick="alert('문서를 먼저 저장하면 연결도를 추가할 수 있습니다.')" title="저장 후 연결도 추가 가능"><x-icon name="gear" :size="13"/> 연결도</button>
        </div>
        <div id="editor"></div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
        <a href="{{ route('wiki.index', $wikiBackParams) }}" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;text-decoration:none;">취소</a>
        <button onclick="saveNewWiki()" style="background:var(--accent);color:var(--accent-text);border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
    </div>
</div>

<div class="slash-menu" id="slashMenu"></div>

{{-- 선택 영역 색상 팝업 (노션식) --}}
<div class="fmt-bubble" id="fmtBubble">
    <span class="fb-label">글자</span>
    <button type="button" class="fb-c" data-color="#e03131" style="color:#e03131">A</button>
    <button type="button" class="fb-c" data-color="#f08c00" style="color:#f08c00">A</button>
    <button type="button" class="fb-c" data-color="#2f9e44" style="color:#2f9e44">A</button>
    <button type="button" class="fb-c" data-color="#1971c2" style="color:#1971c2">A</button>
    <button type="button" class="fb-c" data-color="#9c36b5" style="color:#9c36b5">A</button>
    <button type="button" class="fb-c" data-color="#868e96" style="color:#868e96">A</button>
    <button type="button" class="fb-c fb-reset" data-color="" title="글자색 해제">A⨯</button>
    <span class="fb-sep"></span>
    <span class="fb-label">배경</span>
    <button type="button" class="fb-h" data-hl="#ffec99" style="background:#ffec99"></button>
    <button type="button" class="fb-h" data-hl="#ffd8a8" style="background:#ffd8a8"></button>
    <button type="button" class="fb-h" data-hl="#b2f2bb" style="background:#b2f2bb"></button>
    <button type="button" class="fb-h" data-hl="#a5d8ff" style="background:#a5d8ff"></button>
    <button type="button" class="fb-h" data-hl="#eebefa" style="background:#eebefa"></button>
    <button type="button" class="fb-h" data-hl="#dee2e6" style="background:#dee2e6"></button>
    <button type="button" class="fb-h fb-reset" data-hl="" title="배경색 해제">⨯</button>
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
        "@tiptap/extension-text-align": "https://esm.sh/@tiptap/extension-text-align@2.11.5",
        "@tiptap/extension-text-style": "https://esm.sh/@tiptap/extension-text-style@2.11.5",
        "@tiptap/extension-color": "https://esm.sh/@tiptap/extension-color@2.11.5",
        "@tiptap/extension-highlight": "https://esm.sh/@tiptap/extension-highlight@2.11.5"
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
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: { default: null, parseHTML: el => el.getAttribute('width') || el.style.width?.replace('px','') || null, renderHTML: attrs => attrs.width ? { width: attrs.width, style: `width:${attrs.width}px;height:auto;` } : {} },
            height: { default: null, renderHTML: () => ({}) },
        };
    },
});

window.editor = new Editor({
    element: document.getElementById('editor'),
    extensions: [
        StarterKit.configure({ heading: { levels: [1,2,3] } }),
        ResizableImage.configure({ inline: false }),
        Link.configure({ openOnClick: false }),
        Placeholder.configure({ placeholder: '내용을 입력하세요... ("/" 입력으로 블록 추가)' }),
        Table.configure({ resizable: true }),
        TableRow, TableCell, TableHeader,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
    ],
    content: '',
    editorProps: {
        handleKeyDown(view, event) {
            if (event.key === '/') { setTimeout(() => showSlashMenu(view), 10); }
            return false;
        },
        handleDrop(view, event) {
            const files = event.dataTransfer?.files;
            if (files?.length) { event.preventDefault(); uploadAndInsert(files[0]); return true; }
            return false;
        },
        handlePaste(view, event) {
            const items = event.clipboardData?.items;
            if (!items) return false;
            for (const item of items) { if (item.type.startsWith('image/')) { event.preventDefault(); uploadAndInsert(item.getAsFile()); return true; } }
            return false;
        },
    },
});

// ── 선택 영역 색상 팝업 (노션식) ──
(function(){
    const bubble = document.getElementById('fmtBubble');
    if (!bubble) return;
    function updateFmtBubble(){
        if (!window.editor || !editor.isEditable) { bubble.classList.remove('show'); return; }
        const sel = window.getSelection();
        if (!sel || sel.rangeCount===0 || sel.isCollapsed || editor.state.selection.empty) { bubble.classList.remove('show'); return; }
        const rect = sel.getRangeAt(0).getBoundingClientRect();
        if (!rect || (rect.width===0 && rect.height===0)) { bubble.classList.remove('show'); return; }
        bubble.classList.add('show');
        const bw = bubble.offsetWidth, bh = bubble.offsetHeight;
        let left = rect.left + rect.width/2 - bw/2;
        left = Math.max(8, Math.min(left, window.innerWidth - bw - 8));
        let top = rect.top - bh - 8;
        if (top < 8) top = rect.bottom + 8;
        bubble.style.left = left + 'px'; bubble.style.top = top + 'px';
    }
    bubble.addEventListener('mousedown', e => e.preventDefault()); // 클릭 시 선택 유지
    bubble.querySelectorAll('.fb-c').forEach(b => b.addEventListener('click', () => {
        const c = b.dataset.color;
        c ? editor.chain().focus().setColor(c).run() : editor.chain().focus().unsetColor().run();
        updateFmtBubble();
    }));
    bubble.querySelectorAll('.fb-h').forEach(b => b.addEventListener('click', () => {
        const c = b.dataset.hl;
        c ? editor.chain().focus().setHighlight({ color: c }).run() : editor.chain().focus().unsetHighlight().run();
        updateFmtBubble();
    }));
    editor.on('selectionUpdate', updateFmtBubble);
    editor.on('blur', () => setTimeout(() => { if (!bubble.matches(':hover')) bubble.classList.remove('show'); }, 150));
    window.addEventListener('scroll', () => { if (bubble.classList.contains('show')) updateFmtBubble(); }, true);
})();

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
    { icon:'🎛️', label:'방송 연결도', desc:'세팅 에디터 열기', action:()=>window.open('{{ route("wiki.broadcast-editor") }}','broadcast_editor','width=1400,height=900,scrollbars=yes,resizable=yes') },
];
let slashIdx=0;
function showSlashMenu(view){
    const menu=document.getElementById('slashMenu');const {from}=view.state.selection;const coords=view.coordsAtPos(from);
    menu.style.top=(coords.bottom+4)+'px';menu.style.left=coords.left+'px';slashIdx=0;renderSlashMenu();menu.classList.add('visible');
    const handler=(e)=>{
        if(e.key==='ArrowDown'){e.preventDefault();slashIdx=Math.min(slashIdx+1,SLASH_ITEMS.length-1);renderSlashMenu();}
        else if(e.key==='ArrowUp'){e.preventDefault();slashIdx=Math.max(slashIdx-1,0);renderSlashMenu();}
        else if(e.key==='Enter'){e.preventDefault();SLASH_ITEMS[slashIdx].action();hideSlashMenu();editor.commands.deleteRange({from:from-1,to:from});}
        else if(e.key==='Escape'){hideSlashMenu();}
        else{setTimeout(()=>hideSlashMenu(),100);}
        if(!menu.classList.contains('visible'))document.removeEventListener('keydown',handler);
    };
    document.addEventListener('keydown',handler);
    document.addEventListener('click',function once(){hideSlashMenu();document.removeEventListener('click',once);},{once:true});
}
function renderSlashMenu(){
    document.getElementById('slashMenu').innerHTML=SLASH_ITEMS.map((item,i)=>
        `<div class="slash-item ${i===slashIdx?'selected':''}" onclick="SLASH_ITEMS[${i}].action();hideSlashMenu();"><div class="slash-icon">${item.icon}</div><div><div class="slash-label">${item.label}</div><div class="slash-desc">${item.desc}</div></div></div>`
    ).join('');
}
function hideSlashMenu(){document.getElementById('slashMenu').classList.remove('visible');}
window.SLASH_ITEMS=SLASH_ITEMS;window.hideSlashMenu=hideSlashMenu;

// 파일 업로드
const CSRF=document.querySelector('meta[name="csrf-token"]')?.content;
window.uploadAndInsert=async function(file){
    if(!file)return;
    const fd=new FormData();fd.append('file',file);
    const res=await fetch('/api/wiki/upload',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:fd});
    if(!res.ok){alert('업로드 실패');return;}
    const data=await res.json();
    if(data.is_image) editor.chain().focus().setImage({src:data.url,alt:data.name}).run();
    else editor.chain().focus().insertContent(`<a href="${data.url}" target="_blank">${data.name}</a>`).run();
};

// ── 템플릿 — 미리 만든 글 서식 불러오기/저장 ──
let TPL_OPEN=false;
let TPL_BY_ID={};
function tplEsc(s){ return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
async function renderTplMenu(){
    const menu=document.getElementById('tplMenu');
    menu.innerHTML='<div class="tpl-empty">불러오는 중…</div>';
    let list=[];
    try{ const res=await fetch('/api/wiki-templates',{headers:{'Accept':'application/json'}}); if(res.ok) list=await res.json(); }catch(e){}
    TPL_BY_ID={};
    list.forEach(t=>{ TPL_BY_ID[t.id]=t; });
    const items=list.length
        ? list.map(t=>`<div class="tpl-item" onclick="applyTemplate(${t.id})">
            <span class="tpl-item-name">${tplEsc(t.name)}</span>
            <span class="tpl-item-meta">${tplEsc(t.creator||'')}</span>
            <button class="tpl-del" title="템플릿 삭제" onclick="event.stopPropagation();deleteTemplate(${t.id})">✕</button>
        </div>`).join('')
        : '<div class="tpl-empty">등록된 템플릿이 없습니다.<br>내용을 작성한 뒤 아래 버튼으로 저장해보세요.</div>';
    menu.innerHTML=`<div class="tpl-list">${items}</div>
        <div class="tpl-foot"><button class="tpl-save-btn" onclick="saveAsTemplate()">+ 현재 내용을 템플릿으로 저장</button></div>`;
}
window.toggleTplMenu=function(){
    TPL_OPEN=!TPL_OPEN;
    document.getElementById('tplMenu').classList.toggle('open',TPL_OPEN);
    if(TPL_OPEN) renderTplMenu();
};
document.addEventListener('click',e=>{
    if(!TPL_OPEN) return;
    if(!e.target.closest('#tplMenu') && !e.target.closest('#tplBtn')) window.toggleTplMenu();
});
window.applyTemplate=async function(id){
    const name=TPL_BY_ID[id]?.name||'선택한';
    const cur=editor.getHTML();
    if(cur && cur!=='<p></p>' && !confirm(`작성 중인 내용을 '${name}' 템플릿으로 교체할까요?`)) return;
    const res=await fetch('/api/wiki-templates/'+id,{headers:{'Accept':'application/json'}});
    if(!res.ok){ alert('템플릿을 불러오지 못했습니다.'); return; }
    const tpl=await res.json();
    editor.commands.setContent(tpl.content);
    markWikiDirty();
    if(TPL_OPEN) window.toggleTplMenu();
    editor.commands.focus('start');
};
window.saveAsTemplate=async function(){
    const html=editor.getHTML();
    if(!html||html==='<p></p>'){ alert('내용을 작성한 뒤 템플릿으로 저장해주세요.'); return; }
    const name=prompt('템플릿 이름을 입력해주세요.');
    if(!name||!name.trim()) return;
    const res=await fetch('/api/wiki-templates',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:JSON.stringify({name:name.trim(),content:html})});
    if(!res.ok){ alert('템플릿 저장 실패'); return; }
    renderTplMenu();
};
window.deleteTemplate=async function(id){
    const name=TPL_BY_ID[id]?.name||'선택한';
    if(!confirm(`'${name}' 템플릿을 삭제할까요?`)) return;
    const res=await fetch('/api/wiki-templates/'+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(!res.ok){ alert('삭제 실패'); return; }
    renderTplMenu();
};

// 목록에서 카테고리 선택 상태로 진입 시(?cat=) 해당 카테고리 프리셀렉트
(function(){
    const cat = new URLSearchParams(window.location.search).get('cat');
    if (!cat) return;
    const sel = document.getElementById('wikiCategoryId');
    if (sel && [...sel.options].some(o => o.value === cat)) sel.value = cat;
})();

// 저장 — 최초 저장은 생성(POST), 이후(자동저장 포함)는 수정(PATCH). silent=자동저장
let WIKI_CREATED_ID = null;
let WIKI_SAVING = false;
function setAutosaveStatus(msg){ const el=document.getElementById('autosaveStatus'); if(el) el.textContent=msg; }
let WIKI_DIRTY=false; // 마지막 저장 이후 변경 여부 — 자동저장 지점 표시 + 불필요한 저장 방지
function markWikiDirty(){ WIKI_DIRTY=true; setAutosaveStatus('● 저장되지 않은 변경 — 1분 내 임시저장'); }
editor.on('update', markWikiDirty);
document.getElementById('wikiTitle')?.addEventListener('input', markWikiDirty);
setAutosaveStatus('임시저장 켜짐 (1분마다)');
async function doSaveWiki(silent){
    const title=document.getElementById('wikiTitle').value.trim();
    const categoryId=document.getElementById('wikiCategoryId').value || null;
    const html=editor.getHTML();
    const isPinned=document.getElementById('wikiPinned').checked;
    if(!title){ if(!silent){alert('제목을 입력해주세요.');document.getElementById('wikiTitle').focus();} return; }
    if(!html||html==='<p></p>'){ if(!silent){alert('내용을 입력해주세요.');editor.commands.focus();} return; }
    if(WIKI_SAVING) return;
    WIKI_SAVING=true;
    if(silent) setAutosaveStatus('저장 중…');
    try{
        const wikiType=document.getElementById('wikiType')?.value||'normal';
        // 자동저장(silent)은 임시저장(is_draft=1) — 목록에 노출되지 않고, 등록 버튼을 눌러야 발행됨
        const body=JSON.stringify({title,category_id:wikiType==='normal'?categoryId:null,type:wikiType,content:html,is_pinned:isPinned?1:0,is_draft:silent?1:0,
            allowed_team_ids:[...document.querySelectorAll('.wiki-allowed-team:checked')].map(c=>parseInt(c.value,10))});
        let res;
        if(!WIKI_CREATED_ID){
            res=await fetch('{{ route("wiki.store") }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body});
            if(res.ok){ const data=await res.json(); WIKI_CREATED_ID=data.id; }
        } else {
            res=await fetch('/wiki/'+WIKI_CREATED_ID,{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body});
        }
        if(!res.ok){
            if(!silent){ try{const err=await res.json();alert(err.errors?Object.values(err.errors).flat().join('\n'):(err.message||'저장 실패'));}catch(e){alert('저장 실패');} }
            else setAutosaveStatus('자동 저장 실패');
            return;
        }
        WIKI_DIRTY=false;
        const now=new Date(); const t=`${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
        if(silent){ setAutosaveStatus('✓ '+t+' 임시저장됨'); }
        else { location.href='/wiki/'+WIKI_CREATED_ID; }
    } finally { WIKI_SAVING=false; }
}
window.saveNewWiki=function(){ doSaveWiki(false); };
// 1분마다 자동 임시저장 (변경이 있고 제목·내용이 있을 때만)
setInterval(()=>{
    if(!WIKI_DIRTY) return;
    const title=document.getElementById('wikiTitle').value.trim();
    const html=editor?.getHTML?.()||'';
    if(title && html && html!=='<p></p>') doSaveWiki(true);
}, 60000);

// ── 임시저장 불러오기 ──
function _escD(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
window.openDraftModal=async function(){
    document.getElementById('draftOverlay').style.display='flex';
    const list=document.getElementById('draftList');
    list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px;">불러오는 중…</div>';
    const res=await fetch('/api/wiki/drafts',{headers:{'Accept':'application/json'}});
    if(!res.ok){ list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px;">목록을 불러오지 못했습니다</div>'; return; }
    const drafts=await res.json();
    if(!drafts.length){ list.innerHTML='<div style="padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">임시저장된 글이 없습니다</div>'; return; }
    list.innerHTML=drafts.map(d=>`
        <div style="display:flex;align-items:center;gap:10px;padding:10px 8px;border-bottom:1px solid var(--border);">
            <div style="flex:1;min-width:0;">
                <div style="font-size:13.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${_escD(d.title)||'(제목 없음)'}</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">마지막 수정 ${d.updated_at}</div>
            </div>
            <button onclick="loadDraft(${d.id})" style="background:var(--accent);color:var(--accent-text);border:none;padding:6px 12px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;flex-shrink:0;">불러오기</button>
            <button onclick="deleteDraft(${d.id})" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 10px;border-radius:7px;font-size:12px;cursor:pointer;flex-shrink:0;">삭제</button>
        </div>`).join('');
};
window.closeDraftModal=function(){ document.getElementById('draftOverlay').style.display='none'; };
window.loadDraft=async function(id){
    // 현재 작성 중인 내용이 있으면 확인 후 덮어쓰기
    const curTitle=document.getElementById('wikiTitle').value.trim();
    const curHtml=editor?.getHTML?.()||'';
    if((curTitle||(curHtml&&curHtml!=='<p></p>')) && !confirm('작성 중인 내용을 임시저장 글로 덮어쓸까요?')) return;
    const res=await fetch('/api/wiki/drafts/'+id,{headers:{'Accept':'application/json'}});
    if(!res.ok){ alert('불러오기 실패'); return; }
    const d=await res.json();
    document.getElementById('wikiTitle').value=d.title||'';
    const typeSel=document.getElementById('wikiType');
    if(typeSel&&[...typeSel.options].some(o=>o.value===d.type)){ typeSel.value=d.type; typeSel.dispatchEvent(new Event('change')); }
    const catSel=document.getElementById('wikiCategoryId');
    if(catSel&&d.category_id&&[...catSel.options].some(o=>o.value==d.category_id)) catSel.value=d.category_id;
    document.getElementById('wikiPinned').checked=!!d.is_pinned;
    editor.commands.setContent(d.content||'');
    WIKI_CREATED_ID=d.id; // 이어서 저장하면 이 초안이 갱신됨
    WIKI_DIRTY=false;
    setAutosaveStatus('임시저장 글 불러옴 — 등록 버튼을 눌러야 발행됩니다');
    closeDraftModal();
};
// 자동 생성된 초안 링크(?draft=)로 진입 시 해당 임시저장 글 바로 열기
(function(){
    const draftId = parseInt(new URLSearchParams(window.location.search).get('draft'), 10);
    if (draftId) loadDraft(draftId);
})();
window.deleteDraft=async function(id){
    if(!confirm('이 임시저장 글을 삭제할까요?')) return;
    const res=await fetch('/wiki/'+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(res.ok){ if(WIKI_CREATED_ID===id) WIKI_CREATED_ID=null; openDraftModal(); }
    else alert('삭제 실패');
};

// 이미지 리사이즈 — 네이버 에디터 스타일
(function(){
    let popup=null,activeImg=null;
    function removePopup(){if(popup){popup.remove();popup=null;}if(activeImg){activeImg.style.outline='';activeImg=null;}}
    function showPopup(img){
        removePopup();activeImg=img;
        img.style.outline='2px solid var(--accent)';img.style.outlineOffset='2px';
        popup=document.createElement('div');
        popup.style.cssText='position:fixed;z-index:9999;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:8px 12px;box-shadow:0 4px 16px rgba(0,0,0,0.2);display:flex;align-items:center;gap:8px;font-size:12px;';
        popup.innerHTML=`<span style="color:var(--text-muted);font-size:11px;white-space:nowrap;">크기:</span>
            <button onclick="imgResize(0.25)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">25%</button>
            <button onclick="imgResize(0.5)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">50%</button>
            <button onclick="imgResize(0.75)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">75%</button>
            <button onclick="imgResize(1)" style="padding:3px 8px;border:1px solid var(--border);border-radius:5px;background:none;color:var(--text);font-size:11px;cursor:pointer;">100%</button>
            <span style="color:var(--text-muted);">|</span>
            <input type="number" id="imgWidthInput" value="${img.offsetWidth}" min="30" max="2000" style="width:60px;padding:3px 6px;border:1px solid var(--border);border-radius:5px;background:var(--surface2);color:var(--text);font-size:12px;text-align:center;">
            <span style="color:var(--text-muted);font-size:11px;">px</span>
            <button onclick="imgApplyWidth()" style="padding:3px 10px;border:none;border-radius:5px;background:var(--accent);color:var(--accent-text);font-size:11px;font-weight:600;cursor:pointer;">적용</button>`;
        document.body.appendChild(popup);
        const rect=img.getBoundingClientRect();
        popup.style.left=Math.max(8,rect.left+(rect.width-popup.offsetWidth)/2)+'px';
        popup.style.top=Math.max(8,rect.top-popup.offsetHeight-8)+'px';
        if(parseFloat(popup.style.top)<8)popup.style.top=(rect.bottom+8)+'px';
        popup.querySelector('#imgWidthInput').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();imgApplyWidth();}});
        popup.addEventListener('mousedown',e=>e.stopPropagation());
    }
    function applyImgW(w){
        if(!activeImg)return;w=Math.max(30,Math.min(2000,w));
        activeImg.style.width=w+'px';activeImg.style.height='auto';activeImg.setAttribute('width',w);activeImg.removeAttribute('height');
        if(popup)popup.querySelector('#imgWidthInput').value=w;
        try{const pos=editor.view.posAtDOM(activeImg,0);if(pos!=null){const tr=editor.view.state.tr.setNodeMarkup(pos,undefined,{...editor.view.state.doc.nodeAt(pos)?.attrs,width:String(w)});editor.view.dispatch(tr);}}catch(e){}
    }
    window.imgResize=function(ratio){if(!activeImg)return;const maxW=(document.querySelector('.ProseMirror')?.clientWidth||800)-48;applyImgW(Math.round(maxW*ratio));};
    window.imgApplyWidth=function(){if(!activeImg||!popup)return;applyImgW(parseInt(popup.querySelector('#imgWidthInput').value)||200);};
    document.addEventListener('click',function(e){
        if(e.target.tagName==='IMG'&&e.target.closest('.ProseMirror')){e.preventDefault();showPopup(e.target);}
        else if(popup&&!popup.contains(e.target)){removePopup();}
    });
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&popup)removePopup();});
})();
</script>
@include('partials.tiptap-sticky-toolbar')
@endpush
