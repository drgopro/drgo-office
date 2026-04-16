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

    .tiptap-wrap { border:1px solid var(--border); border-radius:10px; overflow:hidden; background:var(--surface); }
    .tiptap-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:8px 10px; border-bottom:1px solid var(--border); background:var(--surface2); }
    .tiptap-toolbar button { background:none; border:1px solid transparent; color:var(--text-muted); width:30px; height:30px; border-radius:6px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:all 0.12s; }
    .tiptap-toolbar button:hover { background:var(--surface); border-color:var(--border); color:var(--text); }
    .tiptap-toolbar button.is-active { background:var(--accent); color:#1a1207; border-color:var(--accent); }
    [data-theme="light"] .tiptap-toolbar button.is-active { color:#fff; }
    .tiptap-toolbar .sep { width:1px; height:20px; background:var(--border); margin:5px 4px; }
    .tiptap-toolbar .tool-btn { width:auto; padding:0 8px; font-size:11px; gap:4px; display:inline-flex; }
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

    .slash-menu { position:absolute; z-index:100; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:6px; min-width:200px; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:none; }
    .slash-menu.visible { display:block; }
    .slash-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:var(--text); transition:background 0.1s; }
    .slash-item:hover, .slash-item.selected { background:var(--surface2); }
    .slash-icon { width:28px; height:28px; border-radius:6px; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .slash-label { font-weight:500; }
    .slash-desc { font-size:11px; color:var(--text-muted); }
</style>
@endpush

@section('content')
<div class="wiki-wrap">
    <a href="{{ route('wiki.index') }}" class="wiki-back">← 위키 목록</a>
    <h1 style="font-size:20px;font-weight:700;margin:8px 0 20px;">새 문서 작성</h1>

    <div style="display:flex;gap:12px;margin-bottom:14px;">
        <div class="field-group" style="flex:1;margin:0;">
            <div class="field-label">제목 *</div>
            <input class="field-input" id="wikiTitle" required placeholder="문서 제목">
        </div>
        <div class="field-group" style="width:160px;margin:0;">
            <div class="field-label">카테고리 *</div>
            <input class="field-input" id="wikiCategory" required placeholder="예: 기술매뉴얼" list="catList">
            <datalist id="catList">
                @foreach($categories as $cat)<option value="{{ $cat }}">@endforeach
                <option value="기술매뉴얼"><option value="업무가이드"><option value="장비매뉴얼"><option value="일반">
            </datalist>
        </div>
        <div class="field-group" style="width:auto;margin:0;">
            <div class="field-label">고정</div>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 0;">
                <input type="checkbox" id="wikiPinned">
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
            <label class="tool-btn" title="이미지/파일 첨부" style="cursor:pointer;color:var(--text-muted);font-size:13px;">
                📎
                <input type="file" style="display:none;" onchange="uploadAndInsert(this.files[0])">
            </label>
            <button class="tool-btn" onclick="window.open('{{ route('wiki.broadcast-editor') }}','broadcast_editor','width=1400,height=900,scrollbars=yes,resizable=yes')" title="방송 세팅 에디터">🎛️ 연결도</button>
        </div>
        <div id="editor"></div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
        <a href="{{ route('wiki.index') }}" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;text-decoration:none;">취소</a>
        <button onclick="saveNewWiki()" style="background:var(--accent);color:#1a1207;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">저장</button>
    </div>
</div>

<div class="slash-menu" id="slashMenu"></div>
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
        "@tiptap/extension-table-header": "https://esm.sh/@tiptap/extension-table-header@2.11.5"
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

window.editor = new Editor({
    element: document.getElementById('editor'),
    extensions: [
        StarterKit.configure({ heading: { levels: [1,2,3] } }),
        Image.configure({ inline: false }),
        Link.configure({ openOnClick: false }),
        Placeholder.configure({ placeholder: '내용을 입력하세요... ("/" 입력으로 블록 추가)' }),
        Table.configure({ resizable: true }),
        TableRow, TableCell, TableHeader,
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

// 저장
window.saveNewWiki=async function(){
    const title=document.getElementById('wikiTitle').value.trim();
    const category=document.getElementById('wikiCategory').value.trim();
    const html=editor.getHTML();
    const isPinned=document.getElementById('wikiPinned').checked;
    if(!title||!html||html==='<p></p>'){alert('제목과 내용을 입력하세요.');return;}
    const res=await fetch('{{ route("wiki.store") }}',{
        method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({title,category,content:html,is_pinned:isPinned?1:0}),
    });
    if(res.ok){const data=await res.json();location.href='/wiki/'+data.id;}
    else{alert('저장 실패');}
};
</script>
@endpush
