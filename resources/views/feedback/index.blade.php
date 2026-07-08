@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '피드백 보드 - 닥터고블린 오피스')

@push('styles')
<style>
    .fb-wrap { display:flex; gap:20px; padding:24px; max-width:1280px; margin:0 auto; align-items:flex-start; }
    .fb-main { flex:1; min-width:0; }
    .fb-side { width:260px; flex-shrink:0; display:flex; flex-direction:column; gap:16px; }

    /* 탭 */
    .fb-tabs { display:flex; gap:18px; border-bottom:1px solid var(--border); margin-bottom:16px; }
    .fb-tab { padding:10px 2px; font-size:14px; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; }
    .fb-tab.active { color:var(--text); border-bottom-color:var(--accent); }
    .fb-tab .cnt { font-size:12px; color:var(--text-muted); margin-left:4px; font-weight:500; }

    /* 작성 폼 */
    .fb-composer { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; margin-bottom:16px; }
    .fb-composer input[type=text], .fb-composer textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:10px 13px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; font-family:inherit; }
    .fb-composer input[type=text]:focus, .fb-composer textarea:focus { border-color:var(--accent); }
    .fb-composer textarea { margin-top:8px; min-height:74px; resize:vertical; }
    .fb-composer-foot { display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap; }
    .fb-select, .fb-attach-btn { background:var(--surface2); border:1px solid var(--border); border-radius:9px; padding:7px 11px; color:var(--text); font-size:12px; cursor:pointer; font-family:inherit; transition:border-color 0.15s, background 0.15s, box-shadow 0.15s; }
    .fb-select {
        -webkit-appearance:none; appearance:none;
        padding:8px 30px 8px 13px; border-radius:10px; font-weight:600; line-height:1.2;
        background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%23999' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:right 11px center;
    }
    .fb-select:hover, .fb-attach-btn:hover { border-color:var(--text-muted); }
    .fb-select:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }
    .fb-select.has-value { border-color:var(--accent); color:var(--text); }
    .fb-attach-btn { border-style:dashed; color:var(--text-muted); }
    .fb-attach-btn.req { color:#5b8def; border-color:#5b8def66; }
    .fb-composer-hint { margin-left:auto; font-size:11px; color:var(--text-muted); }
    .fb-submit { background:var(--accent); color:var(--accent-text,#111); border:none; border-radius:9px; padding:8px 18px; font-size:13px; font-weight:700; cursor:pointer; }
    .fb-submit:disabled { opacity:0.4; cursor:not-allowed; }
    [data-theme="light"] .fb-submit { color:#fff; }
    .fb-preview-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .fb-preview { position:relative; width:86px; height:64px; border-radius:8px; overflow:hidden; border:1px solid var(--border); }
    .fb-preview img { width:100%; height:100%; object-fit:cover; display:block; }
    .fb-preview button { position:absolute; top:3px; right:3px; width:18px; height:18px; border-radius:50%; border:none; background:rgba(0,0,0,0.6); color:#fff; font-size:10px; cursor:pointer; line-height:1; }

    /* 필터 */
    .fb-filters { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
    .fb-filters input[type=search] { flex:1; min-width:160px; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:8px 13px 8px 32px; color:var(--text); font-size:12px; outline:none; transition:border-color 0.15s, box-shadow 0.15s;
        background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='7' fill='none' stroke='%23999' stroke-width='2'/%3E%3Cpath d='m21 21-4.35-4.35' stroke='%23999' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:11px center; }
    .fb-filters input[type=search]:focus { border-color:var(--accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }

    /* 카드 */
    .fb-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; margin-bottom:10px; overflow:hidden; }
    .fb-card-head { display:flex; align-items:center; gap:11px; padding:14px 16px; cursor:pointer; }
    .fb-avatar { width:32px; height:32px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:var(--text-muted); flex-shrink:0; }
    .fb-head-main { flex:1; min-width:0; }
    .fb-title { font-size:14px; font-weight:600; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .fb-card.status-done .fb-title, .fb-card.status-rejected .fb-title { color:var(--text-muted); font-weight:500; }
    .fb-meta { font-size:11px; color:var(--text-muted); margin-top:3px; display:flex; gap:7px; align-items:center; flex-wrap:wrap; }
    .fb-page-tag { background:var(--surface2); border:1px solid var(--border); border-radius:5px; padding:1px 6px; font-family:ui-monospace,monospace; font-size:10px; }
    .fb-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:7px; flex-shrink:0; }
    .fb-badge.waiting { background:#8884; color:var(--text-muted); }
    .fb-badge.reviewing { background:#b8860b22; color:#c9a227; }
    .fb-badge.hold { background:#8b5cf622; color:#a78bfa; }
    .fb-badge.done { background:#3b82f622; color:#5b8def; }
    .fb-badge.rejected { background:#ef444422; color:#e06c6c; }
    .fb-chevron { color:var(--text-muted); font-size:11px; flex-shrink:0; transition:transform 0.15s; }
    .fb-card.open .fb-chevron { transform:rotate(180deg); }

    .fb-card-body { display:none; padding:0 16px 16px; }
    .fb-card.open .fb-card-body { display:block; }
    .fb-body-text { font-size:13px; color:var(--text); line-height:1.65; white-space:pre-wrap; word-break:break-word; padding:2px 0 4px; }
    .fb-reject-box { margin-top:10px; background:#ef44441a; border:1px solid #ef444440; border-radius:9px; padding:9px 13px; font-size:12px; color:#e06c6c; }
    .fb-att-grid { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
    .fb-att { width:150px; height:104px; border-radius:9px; overflow:hidden; border:1px solid var(--border); cursor:zoom-in; position:relative; background:var(--surface2); }
    .fb-att img { width:100%; height:100%; object-fit:cover; display:block; }

    /* 개발자 처리 바 */
    .fb-dev-bar { display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-top:14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:9px 12px; }
    .fb-dev-bar-label { font-size:11px; font-weight:700; color:var(--text-muted); margin-right:2px; }
    .fb-dev-btn { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 13px; font-size:12px; font-weight:600; color:var(--text); cursor:pointer; }
    .fb-dev-btn.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text,#111); }
    [data-theme="light"] .fb-dev-btn.on { color:#fff; }
    .fb-reject-input-wrap { display:none; gap:6px; flex:1; min-width:200px; }
    .fb-reject-input-wrap.show { display:flex; }
    .fb-reject-input-wrap input { flex:1; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 10px; color:var(--text); font-size:12px; outline:none; }

    /* 의견 */
    .fb-comments { margin-top:14px; border-top:1px solid var(--border); padding-top:12px; display:flex; flex-direction:column; gap:11px; }
    .fb-comment { display:flex; gap:9px; }
    .fb-comment-body { flex:1; min-width:0; font-size:12.5px; }
    .fb-comment-head { display:flex; align-items:center; gap:6px; }
    .fb-comment-head b { font-size:12px; color:var(--text); }
    .fb-dev-badge { font-size:9px; font-weight:800; background:#3b82f622; color:#5b8def; border-radius:4px; padding:1px 5px; letter-spacing:0.05em; }
    .fb-comment-date { font-size:10px; color:var(--text-muted); }
    .fb-comment-text { color:var(--text); margin-top:2px; line-height:1.55; white-space:pre-wrap; word-break:break-word; }
    .fb-comment-del { background:none; border:none; color:var(--text-muted); font-size:10px; cursor:pointer; padding:0 4px; }
    .fb-comment-form { display:flex; gap:8px; margin-top:2px; }
    .fb-comment-form input { flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:9px; padding:8px 12px; color:var(--text); font-size:12.5px; outline:none; }
    .fb-comment-submit { background:var(--surface2); border:1px solid var(--border); border-radius:9px; padding:0 14px; color:var(--text); font-size:12px; font-weight:600; cursor:pointer; }
    .fb-card-actions { text-align:right; margin-top:10px; font-size:11px; color:var(--text-muted); }
    .fb-card-actions button { background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 5px; }
    .fb-card-actions button:hover { color:var(--text); }

    /* 사이드바 */
    .fb-panel { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px; }
    .fb-panel-title { font-size:13px; font-weight:700; margin-bottom:12px; }
    .fb-count-row { display:flex; align-items:center; justify-content:space-between; padding:5px 0; font-size:12.5px; color:var(--text); }
    .fb-count-row .dot { width:8px; height:8px; border-radius:50%; margin-right:8px; display:inline-block; }
    .fb-count-row b { font-size:13px; }
    .fb-guide { font-size:11.5px; color:var(--text-muted); line-height:1.8; }

    .fb-empty { text-align:center; padding:40px 0; color:var(--text-muted); font-size:13px; }

    /* 라이트박스 (간단 확대) */
    .fb-lb { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; }
    .fb-lb.open { display:flex; }
    .fb-lb img { max-width:92vw; max-height:88vh; border-radius:8px; object-fit:contain; }
    .fb-lb-close { position:fixed; top:16px; right:16px; background:rgba(255,255,255,0.15); border:none; color:#fff; width:40px; height:40px; border-radius:50%; cursor:pointer; font-size:17px; z-index:10; }

    @media (max-width: 900px) {
        .fb-wrap { flex-direction:column; padding:14px; }
        .fb-side { width:100%; order:-1; }
        .fb-side .fb-panel:last-child { display:none; } /* 모바일에선 가이드 숨김 */
        .fb-att { width:110px; height:80px; }
    }
</style>
@endpush

@section('content')
<div class="fb-wrap">
    <div class="fb-main">
        <div class="fb-tabs">
            <div class="fb-tab active" id="fbTabBug" onclick="fbSwitchTab('bug')">버그 제보<span class="cnt" id="fbCntBug"></span></div>
            <div class="fb-tab" id="fbTabFeature" onclick="fbSwitchTab('feature')">기능 요청<span class="cnt" id="fbCntFeature"></span></div>
        </div>

        {{-- 작성 폼 --}}
        <div class="fb-composer" id="fbComposer">
            <input type="text" id="fbTitle" maxlength="200" placeholder="어떤 문제가 있나요? 제목을 입력하세요">
            <textarea id="fbBody" maxlength="5000" placeholder="상세 설명 — 재현 경로와 기대 동작을 적어주세요"></textarea>
            <div class="fb-preview-row" id="fbPreviews"></div>
            <div class="fb-composer-foot">
                <select class="fb-select" id="fbPage">
                    <option value="">페이지 선택</option>
                    @foreach($pageOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
                <button type="button" class="fb-attach-btn req" id="fbAttachBtn" onclick="document.getElementById('fbFile').click()">+ 스크린샷 첨부 <b id="fbAttachReq">(필수)</b></button>
                <input type="file" id="fbFile" accept="image/*" multiple style="display:none;">
                <span class="fb-composer-hint" id="fbHint">스크린샷 첨부 후 등록할 수 있어요 · 캡처 후 붙여넣기(Ctrl+V)도 가능</span>
                <button type="button" class="fb-submit" id="fbSubmit" disabled onclick="fbSubmit()">등록</button>
            </div>
        </div>

        {{-- 필터 --}}
        <div class="fb-filters">
            <select class="fb-select" id="fbFilterStatus" onchange="fbLoad()">
                <option value="">상태: 전체</option>
                <option value="waiting">대기</option>
                <option value="reviewing">검토중</option>
                <option value="hold">보류</option>
                <option value="done">완료</option>
                <option value="rejected">반려</option>
            </select>
            <select class="fb-select" id="fbFilterPage" onchange="fbLoad()">
                <option value="">페이지: 전체</option>
                @foreach($pageOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
            <select class="fb-select" id="fbSort" onchange="fbLoad()">
                <option value="latest">최신순</option>
                <option value="oldest">오래된순</option>
            </select>
            <input type="search" id="fbSearch" placeholder="제목, 내용, 작성자 검색" onkeydown="if(event.key==='Enter')fbLoad()">
        </div>

        <div id="fbFeed"><div class="fb-empty">불러오는 중...</div></div>
    </div>

    <div class="fb-side">
        <div class="fb-panel">
            <div class="fb-panel-title">처리 현황</div>
            <div id="fbCounts"></div>
        </div>
        <div class="fb-panel">
            <div class="fb-panel-title">작성 가이드</div>
            <div class="fb-guide">
                · 어떤 페이지인지 꼭 선택해주세요<br>
                · 버그 제보에는 스크린샷이 필수입니다<br>
                · 캡처 후 붙여넣기(Ctrl+V)로도 첨부돼요<br>
                · 반려된 요청은 사유를 확인해주세요<br>
                · 완료 = 수정이 실제 반영된 상태
            </div>
        </div>
    </div>
</div>

<div class="fb-lb" id="fbLb" onclick="if(event.target===this)fbLbClose()">
    <button class="fb-lb-close" onclick="fbLbClose()">✕</button>
    <img id="fbLbImg" src="" alt="">
</div>

<script>
const FB_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
const FB_IS_DEV = @json($isDeveloper);
const FB_STATUS = {waiting:'대기', reviewing:'검토중', hold:'보류', done:'완료', rejected:'반려'};
const FB_STATUS_DOT = {waiting:'#888', reviewing:'#c9a227', hold:'#a78bfa', done:'#5b8def', rejected:'#e06c6c'};
let fbType = 'bug';
let fbPosts = [];
let fbFiles = []; // 첨부 대기 파일 (File 객체)
let fbOpenId = null;

function _e(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── 탭 전환 ──
function fbSwitchTab(t){
    fbType = t;
    document.getElementById('fbTabBug').classList.toggle('active', t==='bug');
    document.getElementById('fbTabFeature').classList.toggle('active', t==='feature');
    // 버그만 스크린샷 필수
    document.getElementById('fbAttachReq').textContent = t==='bug' ? '(필수)' : '(선택)';
    document.getElementById('fbAttachBtn').classList.toggle('req', t==='bug');
    document.getElementById('fbTitle').placeholder = t==='bug' ? '어떤 문제가 있나요? 제목을 입력하세요' : '어떤 기능이 필요한가요? 제목을 입력하세요';
    fbOpenId = null;
    fbUpdateSubmit();
    fbLoad();
}

// ── 목록 로드 ──
async function fbLoad(){
    fbMarkSelects();
    const params = new URLSearchParams({ type: fbType });
    const st = document.getElementById('fbFilterStatus').value; if(st) params.set('status', st);
    const pg = document.getElementById('fbFilterPage').value; if(pg) params.set('page', pg);
    params.set('sort', document.getElementById('fbSort').value);
    const q = document.getElementById('fbSearch').value.trim(); if(q) params.set('q', q);
    try{
        const res = await fetch('/api/feedback?'+params.toString(), {headers:{'Accept':'application/json'}});
        if(!res.ok) throw new Error();
        const data = await res.json();
        fbPosts = data.posts;
        document.getElementById('fbCntBug').textContent = data.tab_totals.bug || '';
        document.getElementById('fbCntFeature').textContent = data.tab_totals.feature || '';
        fbRenderCounts(data.counts);
        fbRenderFeed();
    }catch(e){
        document.getElementById('fbFeed').innerHTML = '<div class="fb-empty">목록을 불러오지 못했습니다.</div>';
    }
}

function fbRenderCounts(counts){
    document.getElementById('fbCounts').innerHTML = Object.keys(FB_STATUS).map(s =>
        `<div class="fb-count-row"><span><span class="dot" style="background:${FB_STATUS_DOT[s]}"></span>${FB_STATUS[s]}</span><b>${counts[s]||0}</b></div>`
    ).join('');
}

// ── 피드 렌더 ──
function fbRenderFeed(){
    const feed = document.getElementById('fbFeed');
    if(!fbPosts.length){ feed.innerHTML = '<div class="fb-empty">아직 등록된 글이 없습니다. 첫 글을 남겨보세요!</div>'; return; }
    feed.innerHTML = fbPosts.map(p => {
        const open = p.id === fbOpenId;
        const initial = _e((p.author||'?').charAt(0));
        return `<div class="fb-card status-${p.status}${open?' open':''}" id="fbCard${p.id}">
            <div class="fb-card-head" onclick="fbToggle(${p.id})">
                <div class="fb-avatar">${initial}</div>
                <div class="fb-head-main">
                    <div class="fb-title">${_e(p.title)}</div>
                    <div class="fb-meta">${_e(p.author||'')} · ${p.created_at} <span class="fb-page-tag">${_e(p.page||'')}</span> 첨부 ${p.attachments.length} · 의견 ${p.comments_count}</div>
                </div>
                <span class="fb-badge ${p.status}">${_e(p.status_label)}</span>
                <span class="fb-chevron">▼</span>
            </div>
            <div class="fb-card-body">${open ? fbBodyHtml(p) : ''}</div>
        </div>`;
    }).join('');
}

function fbBodyHtml(p){
    let html = '';
    if(p.body) html += `<div class="fb-body-text">${_e(p.body)}</div>`;
    if(p.status==='rejected' && p.reject_reason) html += `<div class="fb-reject-box">반려 사유: ${_e(p.reject_reason)}</div>`;
    if(p.attachments.length){
        html += '<div class="fb-att-grid">'+p.attachments.map(a =>
            `<div class="fb-att" onclick="fbLbOpen('${a.url}')"><img src="${a.thumb_url}" alt="${_e(a.file_name)}" loading="lazy"></div>`
        ).join('')+'</div>';
    }
    if(FB_IS_DEV){
        html += `<div class="fb-dev-bar">
            <span class="fb-dev-bar-label">개발자 처리</span>
            <button class="fb-dev-btn ${p.status==='reviewing'?'on':''}" onclick="fbSetStatus(${p.id},'reviewing')">수락</button>
            <button class="fb-dev-btn ${p.status==='rejected'?'on':''}" onclick="fbRejectClick(${p.id})">반려</button>
            <button class="fb-dev-btn ${p.status==='hold'?'on':''}" onclick="fbSetStatus(${p.id},'hold')">보류</button>
            <button class="fb-dev-btn ${p.status==='done'?'on':''}" onclick="fbSetStatus(${p.id},'done')">완료</button>
            <span class="fb-reject-input-wrap" id="fbRejectWrap${p.id}">
                <input type="text" id="fbRejectReason${p.id}" maxlength="500" placeholder="반려 사유 (필수)" onkeydown="if(event.key==='Enter')fbSetStatus(${p.id},'rejected')">
                <button class="fb-dev-btn" onclick="fbSetStatus(${p.id},'rejected')">확인</button>
            </span>
        </div>`;
    }
    html += `<div class="fb-comments" id="fbComments${p.id}">${p.comments.map(c => fbCommentHtml(c)).join('')}
        <div class="fb-comment-form">
            <input type="text" id="fbCommentInput${p.id}" maxlength="2000" placeholder="의견 남기기" onkeydown="if(event.key==='Enter')fbComment(${p.id})">
            <button class="fb-comment-submit" onclick="fbComment(${p.id})">등록</button>
        </div>
    </div>`;
    if((p.is_mine && !p.locked) || FB_IS_DEV){
        html += `<div class="fb-card-actions">
            <button onclick="fbEdit(${p.id})">수정</button>
            <button onclick="fbDelete(${p.id})">삭제</button>
            <span style="margin-left:4px;">작성자·개발자만 가능</span>
        </div>`;
    }
    return html;
}

function fbCommentHtml(c){
    return `<div class="fb-comment" id="fbComment${c.id}">
        <div class="fb-avatar" style="width:26px;height:26px;font-size:11px;">${_e((c.user||'?').charAt(0))}</div>
        <div class="fb-comment-body">
            <div class="fb-comment-head"><b>${_e(c.user||'')}</b>${c.is_dev?'<span class="fb-dev-badge">DEV</span>':''}<span class="fb-comment-date">${c.created_at}</span>
            ${(c.is_mine||FB_IS_DEV)?`<button class="fb-comment-del" onclick="fbDeleteComment(${c.id})">삭제</button>`:''}</div>
            <div class="fb-comment-text">${_e(c.body)}</div>
        </div>
    </div>`;
}

function fbToggle(id){
    fbOpenId = fbOpenId === id ? null : id;
    fbRenderFeed();
}

// ── 첨부 (파일 선택 + 클립보드 붙여넣기) ──
document.getElementById('fbFile').addEventListener('change', e => {
    [...e.target.files].forEach(f => { if(f.type.startsWith('image/')) fbFiles.push(f); });
    e.target.value = '';
    fbRenderPreviews();
});
document.addEventListener('paste', e => {
    // 작성 폼 주변에서 붙여넣기 시 이미지 첨부
    const items = e.clipboardData?.items || [];
    let added = false;
    for(const it of items){
        if(it.kind === 'file' && it.type.startsWith('image/')){
            const f = it.getAsFile();
            if(f){ fbFiles.push(new File([f], 'pasted-'+Date.now()+'.png', {type:f.type})); added = true; }
        }
    }
    if(added){ e.preventDefault(); fbRenderPreviews(); }
});
function fbRenderPreviews(){
    const row = document.getElementById('fbPreviews');
    row.innerHTML = '';
    fbFiles.forEach((f, i) => {
        const div = document.createElement('div');
        div.className = 'fb-preview';
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.onload = () => URL.revokeObjectURL(img.src);
        const btn = document.createElement('button');
        btn.textContent = '✕';
        btn.onclick = () => { fbFiles.splice(i,1); fbRenderPreviews(); };
        div.appendChild(img); div.appendChild(btn);
        row.appendChild(div);
    });
    fbUpdateSubmit();
}
function fbMarkSelects(){
    ['fbPage','fbFilterStatus','fbFilterPage'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.classList.toggle('has-value', !!el.value);
    });
}
function fbUpdateSubmit(){
    fbMarkSelects();
    const title = document.getElementById('fbTitle').value.trim();
    const page = document.getElementById('fbPage').value;
    const needShot = fbType === 'bug' && fbFiles.length === 0;
    document.getElementById('fbSubmit').disabled = !title || !page || needShot;
    document.getElementById('fbHint').textContent = fbType === 'bug'
        ? (needShot ? '스크린샷 첨부 후 등록할 수 있어요 · 캡처 후 붙여넣기(Ctrl+V)도 가능' : '')
        : '';
}
document.getElementById('fbTitle').addEventListener('input', fbUpdateSubmit);
document.getElementById('fbPage').addEventListener('change', fbUpdateSubmit);

// ── 등록 ──
let fbSubmitting = false;
async function fbSubmit(){
    if(fbSubmitting) return;
    fbSubmitting = true;
    const btn = document.getElementById('fbSubmit');
    btn.disabled = true;
    try{
        const fd = new FormData();
        fd.append('type', fbType);
        fd.append('title', document.getElementById('fbTitle').value.trim());
        fd.append('body', document.getElementById('fbBody').value.trim());
        fd.append('page', document.getElementById('fbPage').value);
        fbFiles.forEach(f => fd.append('files[]', f));
        const res = await fetch('/api/feedback', {method:'POST', headers:{'X-CSRF-TOKEN':FB_CSRF,'Accept':'application/json'}, body:fd});
        const data = await res.json().catch(()=>({}));
        if(!res.ok){ alert(data.message || (data.errors && Object.values(data.errors).flat().join('\n')) || '등록 실패'); return; }
        document.getElementById('fbTitle').value = '';
        document.getElementById('fbBody').value = '';
        document.getElementById('fbPage').value = '';
        fbFiles = [];
        fbRenderPreviews();
        fbLoad();
    } finally { fbSubmitting = false; fbUpdateSubmit(); }
}

// ── 상태 변경 (개발자) ──
function fbRejectClick(id){
    const wrap = document.getElementById('fbRejectWrap'+id);
    wrap.classList.toggle('show');
    if(wrap.classList.contains('show')) document.getElementById('fbRejectReason'+id).focus();
}
async function fbSetStatus(id, status){
    const body = {status};
    if(status === 'rejected'){
        const reason = (document.getElementById('fbRejectReason'+id)?.value || '').trim();
        if(!reason){ alert('반려 사유를 입력해주세요.'); return; }
        body.reject_reason = reason;
    }
    const res = await fetch(`/api/feedback/${id}/status`, {method:'POST', headers:{'X-CSRF-TOKEN':FB_CSRF,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify(body)});
    const data = await res.json().catch(()=>({}));
    if(!res.ok){ alert(data.message || '처리 실패'); return; }
    fbLoad();
}

// ── 의견 ──
async function fbComment(id){
    const input = document.getElementById('fbCommentInput'+id);
    const body = input.value.trim();
    if(!body) return;
    const res = await fetch(`/api/feedback/${id}/comments`, {method:'POST', headers:{'X-CSRF-TOKEN':FB_CSRF,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({body})});
    if(!res.ok){ alert('의견 등록 실패'); return; }
    input.value = '';
    fbLoad();
}
async function fbDeleteComment(id){
    if(!confirm('이 의견을 삭제할까요?')) return;
    const res = await fetch(`/api/feedback-comments/${id}`, {method:'DELETE', headers:{'X-CSRF-TOKEN':FB_CSRF,'Accept':'application/json'}});
    if(res.ok) fbLoad();
}

// ── 수정 / 삭제 ──
async function fbEdit(id){
    const p = fbPosts.find(x => x.id === id);
    if(!p) return;
    const title = prompt('제목 수정', p.title);
    if(title === null) return;
    const body = prompt('상세 설명 수정', p.body || '');
    if(body === null) return;
    const res = await fetch(`/api/feedback/${id}`, {method:'PATCH', headers:{'X-CSRF-TOKEN':FB_CSRF,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({title:title.trim()||p.title, body:body.trim(), page:p.page})});
    const data = await res.json().catch(()=>({}));
    if(!res.ok){ alert(data.message || '수정 실패'); return; }
    fbLoad();
}
async function fbDelete(id){
    if(!confirm('이 글을 삭제할까요?')) return;
    const res = await fetch(`/api/feedback/${id}`, {method:'DELETE', headers:{'X-CSRF-TOKEN':FB_CSRF,'Accept':'application/json'}});
    const data = await res.json().catch(()=>({}));
    if(!res.ok){ alert(data.message || '삭제 실패'); return; }
    if(fbOpenId === id) fbOpenId = null;
    fbLoad();
}

// ── 라이트박스 ──
function fbLbOpen(url){
    document.getElementById('fbLbImg').src = url;
    document.getElementById('fbLb').classList.add('open');
}
function fbLbClose(){
    document.getElementById('fbLb').classList.remove('open');
    document.getElementById('fbLbImg').src = '';
}
document.addEventListener('keydown', e => {
    if(e.key === 'Escape' && document.getElementById('fbLb').classList.contains('open')) fbLbClose();
});

fbLoad();
</script>
@endsection
