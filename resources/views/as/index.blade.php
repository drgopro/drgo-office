@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', 'AS 현황 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
    .page-title { font-size:20px; font-weight:800; }
    .page-sub { font-size:12px; color:var(--text-muted); margin-bottom:18px; }
    .btn-primary { background:var(--accent); border:none; color:var(--accent-text); padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
    [data-theme="light"] .btn-primary { color:#fff; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:7px 14px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }

    /* 단계 카운트 카드 — 클릭 시 해당 단계 필터 */
    .stat-row { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:16px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:13px 16px; min-width:0; cursor:pointer; text-align:left; transition:border-color 0.15s, box-shadow 0.15s; position:relative; overflow:hidden; }
    .stat-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--sc, var(--border)); }
    .stat-card:hover { border-color:var(--accent); }
    .stat-card.active { border-color:var(--accent); box-shadow:0 0 0 1px var(--accent); }
    .stat-label { font-size:11px; color:var(--text-muted); letter-spacing:0.12em; text-transform:uppercase; margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .stat-value { font-size:22px; font-weight:800; white-space:nowrap; line-height:1; }
    .stat-card.received { --sc:#d9a24a; }
    .stat-card.in_repair { --sc:#4a90d9; }
    .stat-card.repaired { --sc:#9b70c8; }
    .stat-card.done { --sc:#7ac87a; }

    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:240px; min-height:36px; box-sizing:border-box; }
    .toolbar input:focus { border-color:var(--accent); }
    .filter-hint { font-size:12px; color:var(--text-muted); }
    .filter-hint b { color:var(--accent); }

    /* ── AS 카드 목록 ── */
    .cards { display:flex; flex-direction:column; gap:10px; }
    .cards-empty { text-align:center; padding:48px 16px; color:var(--text-muted); background:var(--surface); border:1px solid var(--border); border-radius:12px; font-size:13px; }
    .as-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 18px; display:grid; grid-template-columns:minmax(200px, 0.85fr) minmax(0, 1.2fr) auto; grid-template-areas:"main steps side"; align-items:center; gap:10px 20px; min-width:0; transition:border-color 0.15s; }
    .as-card:hover { border-color:color-mix(in srgb, var(--accent) 45%, var(--border)); }
    .as-card.is-done { opacity:0.6; }
    .as-card.is-done:hover { opacity:1; }

    .ac-main { grid-area:main; display:flex; flex-direction:column; gap:5px; min-width:0; }
    .ac-product { font-size:15px; font-weight:800; line-height:1.35; word-break:break-all; }
    .ac-links { display:flex; align-items:center; gap:6px; font-size:13px; min-width:0; flex-wrap:wrap; }
    .ac-links svg { flex-shrink:0; width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; color:var(--text-muted); }
    .ac-links a { color:var(--accent); text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px; }
    .ac-links a:hover { text-decoration:underline; }
    .ac-links .sep { color:var(--text-muted); }
    .ac-desc { font-size:12px; color:var(--text-muted); line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; word-break:break-word; cursor:default; }
    .ac-cost { display:flex; align-items:center; gap:6px; font-size:12px; }
    .cost-badge { display:inline-flex; align-items:center; font-size:11px; font-weight:800; padding:1px 8px; border-radius:999px; white-space:nowrap; line-height:1.7; }
    .cost-badge.free { background:rgba(122,200,122,0.15); color:#7ac87a; border:1px solid rgba(122,200,122,0.35); }
    .cost-badge.paid { background:rgba(200,80,80,0.13); color:#e08585; border:1px solid rgba(200,80,80,0.35); }
    .cost-badge.unset { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); }
    [data-theme="light"] .cost-badge.free { background:#e8f5e8; color:#1a7a2a; border-color:#a0d8a0; }
    [data-theme="light"] .cost-badge.paid { background:#fdeaea; color:#c22; border-color:#f0b0b0; }
    .ac-cost .amount { font-weight:800; color:var(--text); }

    /* 4단계 스테퍼 */
    .ac-steps { grid-area:steps; display:flex; min-width:0; padding:2px 0; }
    .step { flex:1; min-width:0; text-align:center; position:relative; }
    .step::before { content:''; position:absolute; top:7px; left:-50%; width:100%; height:2px; background:var(--border); }
    .step:first-child::before { display:none; }
    .step.on::before { background:var(--accent); }
    .step-dot { position:relative; z-index:1; width:14px; height:14px; margin:0 auto 5px; border-radius:50%; background:var(--surface); border:2px solid var(--border); box-sizing:border-box; }
    .step.on .step-dot { background:var(--accent); border-color:var(--accent); }
    .step.cur .step-dot { box-shadow:0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent); }
    .step-name { font-size:11px; font-weight:700; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .step.on .step-name { color:var(--text); }
    .step.cur .step-name { color:var(--accent); }
    .step-date { font-size:11px; color:var(--text-muted); margin-top:1px; white-space:nowrap; }

    .ac-side { grid-area:side; display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0; }
    .status-sel { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:6px 10px; color:var(--text); font-size:12px; font-weight:600; outline:none; cursor:pointer; }
    .status-sel:focus { border-color:var(--accent); }
    .as-badge { display:inline-flex; align-items:center; font-size:11px; font-weight:700; padding:2px 10px; border-radius:999px; white-space:nowrap; line-height:1.8; }
    .as-badge.received { background:rgba(230,160,60,0.16); color:#d9a24a; border:1px solid rgba(230,160,60,0.4); }
    .as-badge.in_repair { background:rgba(74,144,217,0.14); color:#6aa8e8; border:1px solid rgba(74,144,217,0.35); }
    .as-badge.repaired { background:rgba(155,112,200,0.16); color:#b394d9; border:1px solid rgba(155,112,200,0.4); }
    .as-badge.done { background:rgba(122,200,122,0.15); color:#7ac87a; border:1px solid rgba(122,200,122,0.35); }
    .ac-btns { display:flex; gap:6px; }
    .mini-btn { background:none; border:1px solid var(--border); border-radius:6px; color:var(--text-muted); font-size:12px; padding:4px 10px; cursor:pointer; white-space:nowrap; }
    .mini-btn:hover { border-color:var(--accent); color:var(--accent); }
    .mini-btn.danger:hover { border-color:#dc2626; color:#dc2626; }
    .next-btn { background:none; border:1px solid var(--accent); border-radius:6px; color:var(--accent); font-size:12px; font-weight:700; padding:4px 10px; cursor:pointer; white-space:nowrap; }
    .next-btn:hover { background:var(--accent); color:var(--accent-text); }
    [data-theme="light"] .next-btn:hover { color:#fff; }

    @media (max-width: 920px) {
        .page-wrap { padding:14px; }
        .stat-row { grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; }
        .as-card { grid-template-columns:1fr; grid-template-areas:"main" "steps" "side"; gap:12px; }
        .ac-side { flex-direction:row; align-items:center; justify-content:space-between; width:100%; }
    }

    /* ── 등록/수정 모달 ── */
    .as-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; display:none; align-items:flex-start; justify-content:center; padding:5vh 12px; overflow-y:auto; }
    .as-overlay.open { display:flex; }
    .as-modal { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:560px; max-width:100%; padding:22px; }
    .as-modal h3 { margin:0 0 16px; font-size:16px; }
    .field-group { margin-bottom:13px; }
    .field-label { font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:5px; }
    .field-label .req { color:#dc2626; }
    .field-input, .field-select, .field-textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .field-input:focus, .field-select:focus, .field-textarea:focus { border-color:var(--accent); }
    .field-textarea { min-height:90px; resize:vertical; font-family:inherit; }
    .field-row { display:flex; gap:10px; }
    .field-row > div { flex:1; }
    .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
    .form-error { color:#dc2626; font-size:12px; margin-top:8px; display:none; white-space:pre-wrap; }

    /* 검색 드롭다운 (의뢰자/제품 공용) */
    .cl-search-wrap { position:relative; }
    .cl-results { position:absolute; top:100%; left:0; right:0; background:var(--surface); border:1px solid var(--border); border-radius:8px; margin-top:4px; max-height:220px; overflow-y:auto; z-index:10; box-shadow:0 8px 24px rgba(0,0,0,0.25); display:none; }
    .cl-results.open { display:block; }
    .cl-item { padding:9px 12px; font-size:13px; cursor:pointer; border-bottom:1px solid var(--border); }
    .cl-item:last-child { border-bottom:none; }
    .cl-item:hover { background:var(--surface2); }
    .cl-item small { color:var(--text-muted); }
    .cl-picked { display:flex; align-items:center; gap:8px; background:var(--surface2); border:1px solid var(--accent); border-radius:8px; padding:8px 12px; font-size:13px; }
    .cl-picked b { color:var(--accent); }
    .cl-picked button { margin-left:auto; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px; }

    @media (max-width: 760px) {
        .field-row { flex-direction:column; gap:0; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">AS 현황</div>
        @if($canEdit)
            <button class="btn-primary" onclick="asOpenModal()">+ AS 접수</button>
        @endif
    </div>
    <div class="page-sub">사무실에서 물건을 <b>받은 시점부터</b> 기재합니다. 상담 중 AS 얘기만 나온 건은 기재하지 않습니다.</div>

    <div class="stat-row" id="asStats"></div>

    <div class="toolbar">
        <input type="text" id="asSearch" placeholder="제품명·의뢰자·프로젝트 검색" oninput="asSearchDebounced()">
        <span class="filter-hint" id="asFilterHint"></span>
    </div>

    <div class="cards" id="asCards"><div class="cards-empty">불러오는 중...</div></div>
</div>

{{-- 등록/수정 모달 --}}
<div class="as-overlay" id="asOverlay" onclick="if(event.target===this) asCloseModal()">
    <div class="as-modal">
        <h3 id="asModalTitle">AS 접수</h3>

        <div class="field-group">
            <div class="field-label">의뢰자 <span class="req">*</span> <span style="font-weight:400;">— 연동 필수</span></div>
            <div class="cl-search-wrap" id="clSearchWrap">
                <input type="text" class="field-input" id="clSearch" placeholder="이름·닉네임·전화번호로 검색" autocomplete="off" oninput="clSearchInput()">
                <div class="cl-results" id="clResults"></div>
            </div>
            <div class="cl-picked" id="clPicked" style="display:none;">
                <b id="clPickedName"></b>
                <button type="button" onclick="clClear()" title="연동 해제">✕</button>
            </div>
        </div>

        <div class="field-group">
            <div class="field-label">프로젝트 <span class="req">*</span> <span style="font-weight:400;">— 연동 필수</span></div>
            <select class="field-select" id="asProject" disabled>
                <option value="">의뢰자를 먼저 선택하세요</option>
            </select>
        </div>

        <div class="field-group">
            <div class="field-label">제품명 <span class="req">*</span> <span style="font-weight:400;">— 제품관리에서 검색해 넣거나 직접 입력</span></div>
            <div class="cl-search-wrap" id="prodSearchWrap">
                <input type="text" class="field-input" id="asProduct" placeholder="제품명 검색 또는 직접 입력" maxlength="200" autocomplete="off" oninput="prodSearchInput()">
                <div class="cl-results" id="prodResults"></div>
            </div>
        </div>

        <div class="field-group">
            <div class="field-label">상세설명</div>
            <textarea class="field-textarea" id="asDesc" placeholder="같이 온 부속, 증상, 기타 특이사항" maxlength="5000"></textarea>
        </div>

        <div class="field-row">
            <div class="field-group">
                <div class="field-label">무상/유상</div>
                <select class="field-select" id="asCostType" onchange="asCostTypeChange()">
                    <option value="">미정</option>
                    <option value="free">무상</option>
                    <option value="paid">유상</option>
                </select>
            </div>
            <div class="field-group">
                <div class="field-label">유상 비용 (원)</div>
                <input type="number" class="field-input" id="asCostAmount" placeholder="유상일 때 입력" min="0" disabled>
            </div>
        </div>

        <div class="form-error" id="asFormError"></div>

        <div class="modal-actions">
            <button class="btn-outline" onclick="asCloseModal()">취소</button>
            <button class="btn-primary" id="asSaveBtn" onclick="asSave()">저장</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const AS_CAN_EDIT = @json($canEdit);
const AS_STATUS = @json(\App\Models\AsRequest::STATUS_LABELS);
const AS_ORDER = Object.keys(AS_STATUS);
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

let asItems = [];
let asCounts = {};
let asActiveTab = ''; // '' = 전체
let asEditId = null;  // null = 신규
let asSearchTimer = null;

function _e(s){ const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
function _won(n){ return n==null ? '' : Number(n).toLocaleString('ko-KR') + '원'; }

// ── 목록 ──
async function asLoad(){
    const params = new URLSearchParams();
    if (asActiveTab) params.set('status', asActiveTab);
    const q = document.getElementById('asSearch').value.trim();
    if (q) params.set('q', q);
    try {
        const res = await fetch('/api/as-requests?' + params, { headers:{ 'Accept':'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        asItems = data.items || [];
        asCounts = data.counts || {};
    } catch (e) {
        document.getElementById('asCards').innerHTML = '<div class="cards-empty">목록을 불러오지 못했습니다. 새로고침 해주세요.</div>';
        return;
    }
    asRenderStats();
    asRenderList();
}

function asRenderStats(){
    document.getElementById('asStats').innerHTML = Object.entries(AS_STATUS).map(([key, label]) =>
        `<button class="stat-card ${key} ${asActiveTab===key?'active':''}" onclick="asSetTab('${key}')" title="클릭하면 이 단계만 필터 (다시 클릭하면 전체)">
            <div class="stat-label">${_e(label)}</div>
            <div class="stat-value">${asCounts[key]||0}</div>
        </button>`
    ).join('');
    document.getElementById('asFilterHint').innerHTML = asActiveTab
        ? `<b>${_e(AS_STATUS[asActiveTab])}</b> 단계만 표시 중 — 카드를 다시 누르면 전체로`
        : '';
}

function asSetTab(key){ asActiveTab = (asActiveTab === key) ? '' : key; asLoad(); }
function asSearchDebounced(){ clearTimeout(asSearchTimer); asSearchTimer = setTimeout(asLoad, 300); }

const STEP_DATES = { received:'received_at', in_repair:'sent_at', repaired:'repaired_at', done:'done_at' };
const CAL_SVG_USER = '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>';
const CAL_SVG_PROJ = '<svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path><path d="M4 9h16M9 4v16"></path></svg>';

function asStepsHtml(r){
    const curIdx = AS_ORDER.indexOf(r.status);
    return '<div class="ac-steps">' + AS_ORDER.map((key, i) => {
        const date = r[STEP_DATES[key]];
        const cls = (i <= curIdx ? ' on' : '') + (i === curIdx ? ' cur' : '');
        return `<div class="step${cls}">
            <div class="step-dot"></div>
            <div class="step-name">${_e(AS_STATUS[key])}</div>
            <div class="step-date">${i <= curIdx && date ? date.slice(5).replace('-','/') : '&nbsp;'}</div>
        </div>`;
    }).join('') + '</div>';
}

function asRenderList(){
    const wrap = document.getElementById('asCards');
    if (!asItems.length) {
        wrap.innerHTML = '<div class="cards-empty">표시할 AS 건이 없습니다.' + (AS_CAN_EDIT ? ' 우측 상단 <b>+ AS 접수</b>로 등록하세요.' : '') + '</div>';
        return;
    }
    wrap.innerHTML = asItems.map(r => {
        const cost = r.cost_type
            ? `<span class="cost-badge ${r.cost_type}">${_e(r.cost_label)}</span>${r.cost_type==='paid' && r.cost_amount!=null ? '<span class="amount">'+_won(r.cost_amount)+'</span>' : ''}`
            : '<span class="cost-badge unset">무상/유상 미정</span>';
        const curIdx = AS_ORDER.indexOf(r.status);
        const nextKey = curIdx >= 0 && curIdx < AS_ORDER.length - 1 ? AS_ORDER[curIdx + 1] : null;
        const side = AS_CAN_EDIT
            ? `${nextKey ? `<button class="next-btn" onclick="asSetStatus(${r.id}, '${nextKey}')">${_e(AS_STATUS[nextKey])} →</button>` : `<span class="as-badge done">${_e(AS_STATUS.done)}</span>`}
               <div class="ac-btns">
                   <select class="status-sel" onchange="asSetStatus(${r.id}, this.value)" title="단계 직접 지정">
                       ${AS_ORDER.map(k => `<option value="${k}" ${r.status===k?'selected':''}>${_e(AS_STATUS[k])}</option>`).join('')}
                   </select>
                   <button class="mini-btn" onclick="asOpenModal(${r.id})">수정</button>
                   <button class="mini-btn danger" onclick="asDelete(${r.id})">삭제</button>
               </div>`
            : `<span class="as-badge ${r.status}">${_e(r.status_label)}</span>`;
        return `<div class="as-card ${r.status==='done'?'is-done':''}">
            <div class="ac-main">
                <div class="ac-product">${_e(r.product_name)}</div>
                <div class="ac-links">
                    ${CAL_SVG_USER}<a href="/clients?open=${r.client_id}" target="_blank" rel="noopener">${_e(r.client||'-')}</a>
                    <span class="sep">·</span>
                    ${CAL_SVG_PROJ}<a href="/projects/${r.project_id}" target="_blank" rel="noopener">${_e(r.project||'-')}</a>
                </div>
                ${r.description ? `<div class="ac-desc" title="${_e(r.description)}">${_e(r.description)}</div>` : ''}
                <div class="ac-cost">${cost}</div>
            </div>
            ${asStepsHtml(r)}
            <div class="ac-side">${side}</div>
        </div>`;
    }).join('');
}

// ── 상태 변경 ──
async function asSetStatus(id, status){
    const res = await fetch(`/api/as-requests/${id}/status`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':CSRF },
        body: JSON.stringify({ status }),
    });
    if (!res.ok) { alert('상태 변경에 실패했습니다.'); }
    asLoad();
}

async function asDelete(id){
    if (!confirm('이 AS 건을 삭제할까요?')) return;
    await fetch(`/api/as-requests/${id}`, { method:'DELETE', headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN':CSRF } });
    asLoad();
}

// ── 의뢰자 검색/프로젝트 연동 ──
let clTimer = null;
let clPickedId = null;

function clSearchInput(){
    clearTimeout(clTimer);
    const q = document.getElementById('clSearch').value.trim();
    if (q.length < 1) { document.getElementById('clResults').classList.remove('open'); return; }
    clTimer = setTimeout(async () => {
        try {
            const res = await fetch('/api/clients/search?q=' + encodeURIComponent(q));
            const list = await res.json();
            const box = document.getElementById('clResults');
            const closeRow = `<div style="position:sticky;top:0;z-index:1;display:flex;justify-content:space-between;align-items:center;padding:5px 12px;background:var(--surface);border-bottom:1px solid var(--border);"><span style="font-size:11px;color:var(--text-muted);">검색 결과</span><button type="button" onclick="document.getElementById('clResults').classList.remove('open')" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:12px;padding:2px 4px;">✕ 닫기</button></div>`;
            box.innerHTML = closeRow + (list.length
                ? list.map(c => `<div class="cl-item" onclick="clPick(${c.id}, '${_e(c.nickname||c.name||'').replace(/'/g,"\\'")}')">${_e(c.nickname||c.name)}${c.name && c.nickname ? ' <small>('+_e(c.name)+')</small>' : ''}${c.phone ? ' <small>'+_e(c.phone)+'</small>' : ''}${c.affiliation ? ' <small style="color:var(--accent);">'+_e(c.affiliation)+'</small>' : ''}</div>`).join('')
                : '<div class="cl-item" style="color:var(--text-muted); cursor:default;">검색 결과 없음</div>');
            box.classList.add('open');
        } catch (e) { /* 네트워크 오류 — 입력 계속 가능 */ }
    }, 250);
}

async function clPick(id, label){
    clPickedId = id;
    document.getElementById('clResults').classList.remove('open');
    document.getElementById('clSearchWrap').style.display = 'none';
    document.getElementById('clPicked').style.display = 'flex';
    document.getElementById('clPickedName').textContent = label;
    await clLoadProjects(id);
}

async function clLoadProjects(clientId, selectedId){
    const sel = document.getElementById('asProject');
    sel.disabled = true;
    sel.innerHTML = '<option value="">불러오는 중...</option>';
    try {
        const res = await fetch(`/api/as-requests/client-projects/${clientId}`, { headers:{ 'Accept':'application/json' } });
        const list = await res.json();
        if (!list.length) {
            sel.innerHTML = '<option value="">이 의뢰자에게 프로젝트가 없습니다 — 먼저 생성하세요</option>';
            return;
        }
        sel.innerHTML = '<option value="">프로젝트 선택</option>'
            + list.map(p => `<option value="${p.id}" ${selectedId===p.id?'selected':''}>${_e(p.name)}${p.created ? ' ('+p.created+')' : ''}</option>`).join('');
        sel.disabled = false;
        if (list.length === 1 && !selectedId) sel.value = String(list[0].id); // 하나뿐이면 자동 선택
    } catch (e) {
        sel.innerHTML = '<option value="">프로젝트를 불러오지 못했습니다</option>';
    }
}

function clClear(){
    clPickedId = null;
    document.getElementById('clPicked').style.display = 'none';
    document.getElementById('clSearchWrap').style.display = 'block';
    document.getElementById('clSearch').value = '';
    const sel = document.getElementById('asProject');
    sel.disabled = true;
    sel.innerHTML = '<option value="">의뢰자를 먼저 선택하세요</option>';
}

// ── 제품명 자동완성 — 제품관리 카탈로그 검색, 선택하면 이름이 채워지고 수동 입력도 그대로 허용 ──
let prodTimer = null;
function prodSearchInput(){
    clearTimeout(prodTimer);
    const q = document.getElementById('asProduct').value.trim();
    const box = document.getElementById('prodResults');
    if (q.length < 1) { box.classList.remove('open'); return; }
    prodTimer = setTimeout(async () => {
        try {
            const res = await fetch('/api/as-requests/product-search?q=' + encodeURIComponent(q), { headers:{ 'Accept':'application/json' } });
            const list = await res.json();
            if (!list.length) { box.classList.remove('open'); return; }
            box.innerHTML = list.map(p => `<div class="cl-item" data-label="${_e(p.label)}" onclick="prodPick(this)">${_e(p.label)}</div>`).join('');
            box.classList.add('open');
        } catch (e) { /* 검색 실패 — 수동 입력으로 진행 */ }
    }, 250);
}
function prodPick(el){
    document.getElementById('asProduct').value = el.dataset.label;
    document.getElementById('prodResults').classList.remove('open');
}

// ── 등록/수정 모달 ──
function asOpenModal(id){
    asEditId = id || null;
    const item = id ? asItems.find(r => r.id === id) : null;
    document.getElementById('asModalTitle').textContent = item ? 'AS 건 수정' : 'AS 접수';
    document.getElementById('asFormError').style.display = 'none';
    document.getElementById('asProduct').value = item ? item.product_name : '';
    document.getElementById('asDesc').value = item ? (item.description || '') : '';
    document.getElementById('asCostType').value = item ? (item.cost_type || '') : '';
    document.getElementById('asCostAmount').value = item && item.cost_amount != null ? item.cost_amount : '';
    asCostTypeChange();

    clClear();
    if (item) {
        clPick(item.client_id, item.client || '');
        clLoadProjects(item.client_id, item.project_id);
    }
    document.getElementById('asOverlay').classList.add('open');
    if (!item) setTimeout(() => document.getElementById('clSearch').focus(), 50);
}

function asCloseModal(){ document.getElementById('asOverlay').classList.remove('open'); }

function asCostTypeChange(){
    const paid = document.getElementById('asCostType').value === 'paid';
    const amt = document.getElementById('asCostAmount');
    amt.disabled = !paid;
    if (!paid) amt.value = '';
}

async function asSave(){
    const err = document.getElementById('asFormError');
    err.style.display = 'none';
    const payload = {
        client_id: clPickedId,
        project_id: Number(document.getElementById('asProject').value) || null,
        product_name: document.getElementById('asProduct').value.trim(),
        description: document.getElementById('asDesc').value.trim() || null,
        cost_type: document.getElementById('asCostType').value || null,
        cost_amount: document.getElementById('asCostAmount').value !== '' ? Number(document.getElementById('asCostAmount').value) : null,
    };
    if (!payload.client_id) { err.textContent = '의뢰자를 연동해주세요.'; err.style.display = 'block'; return; }
    if (!payload.project_id) { err.textContent = '프로젝트를 연동해주세요.'; err.style.display = 'block'; return; }
    if (!payload.product_name) { err.textContent = '제품명을 입력해주세요.'; err.style.display = 'block'; return; }

    const btn = document.getElementById('asSaveBtn');
    btn.disabled = true;
    try {
        const res = await fetch(asEditId ? `/api/as-requests/${asEditId}` : '/api/as-requests', {
            method: asEditId ? 'PATCH' : 'POST',
            headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':CSRF },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            err.textContent = data.message || Object.values(data.errors || {}).flat().join('\n') || '저장에 실패했습니다.';
            err.style.display = 'block';
            return;
        }
        asCloseModal();
        asLoad();
    } finally {
        btn.disabled = false;
    }
}

document.addEventListener('click', e => {
    if (!e.target.closest('#clSearchWrap')) document.getElementById('clResults')?.classList.remove('open');
    if (!e.target.closest('#prodSearchWrap')) document.getElementById('prodResults')?.classList.remove('open');
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('clResults')?.classList.remove('open');
        document.getElementById('prodResults')?.classList.remove('open');
    }
});

asLoad();
</script>
@endpush
