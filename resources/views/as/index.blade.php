@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', 'AS 현황 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .page-title { font-size:20px; font-weight:800; }
    .page-sub { font-size:12px; color:var(--text-muted); margin-bottom:16px; }
    .btn-primary { background:var(--accent); border:none; color:var(--accent-text); padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:7px 14px; border-radius:8px; font-size:12.5px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }

    .as-tabs { display:flex; gap:6px; margin-bottom:12px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .as-tab { background:none; border:none; border-bottom:2px solid transparent; padding:9px 12px; font-size:13.5px; font-weight:600; color:var(--text-muted); cursor:pointer; margin-bottom:-1px; white-space:nowrap; }
    .as-tab:hover { color:var(--text); }
    .as-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
    .as-tab .cnt { font-size:11.5px; font-weight:700; margin-left:4px; opacity:0.8; }

    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:230px; }
    .toolbar input:focus { border-color:var(--accent); }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; min-width:860px; }
    .data-table th { text-align:left; padding:11px 12px; font-size:11.5px; color:var(--text-muted); border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { padding:11px 12px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:top; }
    .data-table tr:last-child td { border-bottom:none; }
    .as-empty { padding:40px 16px; text-align:center; color:var(--text-muted); font-size:13px; }

    .as-badge { display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; white-space:nowrap; }
    .as-badge.received { background:#fef3e2; color:#b45309; }
    .as-badge.in_repair { background:#e8f0fe; color:#1d4ed8; }
    .as-badge.repaired { background:#ede9fe; color:#6d28d9; }
    .as-badge.done { background:#e7f6ec; color:#15803d; }
    .cost-badge { display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }
    .cost-badge.free { background:#e7f6ec; color:#15803d; }
    .cost-badge.paid { background:#fdeaea; color:#dc2626; }

    .as-link { color:var(--accent); cursor:pointer; text-decoration:none; }
    .as-link:hover { text-decoration:underline; }
    .as-desc { color:var(--text-muted); font-size:12px; white-space:pre-wrap; word-break:break-word; max-width:320px; }
    .as-dates { font-size:11px; color:var(--text-muted); line-height:1.7; white-space:nowrap; }
    .status-sel { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:6px 8px; color:var(--text); font-size:12px; outline:none; cursor:pointer; }
    .row-done td { opacity:0.55; }
    tr.row-done:hover td { opacity:1; }
    .mini-btn { background:none; border:1px solid var(--border); border-radius:6px; color:var(--text-muted); font-size:11.5px; padding:4px 8px; cursor:pointer; white-space:nowrap; }
    .mini-btn:hover { border-color:var(--accent); color:var(--accent); }
    .mini-btn.danger:hover { border-color:#dc2626; color:#dc2626; }

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

    /* 의뢰자 검색 */
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
        .page-wrap { padding:14px; }
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

    <div class="as-tabs" id="asTabs"></div>

    <div class="toolbar">
        <input type="text" id="asSearch" placeholder="제품명·의뢰자·프로젝트 검색" oninput="asSearchDebounced()">
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>상태</th>
                    <th>의뢰자</th>
                    <th>프로젝트</th>
                    <th>제품명</th>
                    <th>상세설명</th>
                    <th>무상/유상</th>
                    <th>진행 일자</th>
                    @if($canEdit)<th></th>@endif
                </tr>
            </thead>
            <tbody id="asBody"><tr><td colspan="8" class="as-empty">불러오는 중...</td></tr></tbody>
        </table>
    </div>
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
            <div class="field-label">제품명 <span class="req">*</span></div>
            <input type="text" class="field-input" id="asProduct" placeholder="AS 대상 제품명" maxlength="200">
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
        document.getElementById('asBody').innerHTML = '<tr><td colspan="8" class="as-empty">목록을 불러오지 못했습니다. 새로고침 해주세요.</td></tr>';
        return;
    }
    asRenderTabs();
    asRenderList();
}

function asRenderTabs(){
    const total = Object.values(asCounts).reduce((a,b)=>a+Number(b||0), 0);
    let html = `<button class="as-tab ${asActiveTab===''?'active':''}" onclick="asSetTab('')">전체<span class="cnt">${total}</span></button>`;
    for (const [key, label] of Object.entries(AS_STATUS)) {
        html += `<button class="as-tab ${asActiveTab===key?'active':''}" onclick="asSetTab('${key}')">${_e(label)}<span class="cnt">${asCounts[key]||0}</span></button>`;
    }
    document.getElementById('asTabs').innerHTML = html;
}

function asSetTab(key){ asActiveTab = key; asLoad(); }
function asSearchDebounced(){ clearTimeout(asSearchTimer); asSearchTimer = setTimeout(asLoad, 300); }

function asRenderList(){
    const body = document.getElementById('asBody');
    if (!asItems.length) {
        body.innerHTML = '<tr><td colspan="8" class="as-empty">표시할 AS 건이 없습니다.</td></tr>';
        return;
    }
    body.innerHTML = asItems.map(r => {
        const cost = r.cost_type
            ? `<span class="cost-badge ${r.cost_type}">${_e(r.cost_label)}</span>${r.cost_type==='paid' && r.cost_amount!=null ? '<div style="margin-top:4px; font-size:12px;">'+_won(r.cost_amount)+'</div>' : ''}`
            : '<span style="color:var(--text-muted); font-size:12px;">미정</span>';
        const dates = [
            r.received_at ? '접수 ' + r.received_at : null,
            r.sent_at ? '발송 ' + r.sent_at : null,
            r.repaired_at ? 'AS완료 ' + r.repaired_at : null,
            r.done_at ? '배송완료 ' + r.done_at : null,
        ].filter(Boolean).join('<br>');
        const statusCell = AS_CAN_EDIT
            ? `<select class="status-sel" onchange="asSetStatus(${r.id}, this.value)">`
                + Object.entries(AS_STATUS).map(([k,l]) => `<option value="${k}" ${r.status===k?'selected':''}>${_e(l)}</option>`).join('')
                + '</select>'
            : `<span class="as-badge ${r.status}">${_e(r.status_label)}</span>`;
        return `<tr class="${r.status==='done'?'row-done':''}">
            <td>${statusCell}</td>
            <td><a class="as-link" href="/clients?open=${r.client_id}" target="_blank" rel="noopener">${_e(r.client||'-')}</a></td>
            <td><a class="as-link" href="/projects/${r.project_id}" target="_blank" rel="noopener">${_e(r.project||'-')}</a></td>
            <td style="font-weight:600;">${_e(r.product_name)}</td>
            <td><div class="as-desc">${_e(r.description||'')}</div></td>
            <td>${cost}</td>
            <td><div class="as-dates">${dates}</div></td>
            ${AS_CAN_EDIT ? `<td style="white-space:nowrap;"><button class="mini-btn" onclick="asOpenModal(${r.id})">수정</button> <button class="mini-btn danger" onclick="asDelete(${r.id})">삭제</button></td>` : ''}
        </tr>`;
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
            box.innerHTML = list.length
                ? list.map(c => `<div class="cl-item" onclick="clPick(${c.id}, '${_e(c.nickname||c.name||'').replace(/'/g,"\\'")}')">${_e(c.nickname||c.name)}${c.name && c.nickname ? ' <small>('+_e(c.name)+')</small>' : ''}${c.phone ? ' <small>'+_e(c.phone)+'</small>' : ''}</div>`).join('')
                : '<div class="cl-item" style="color:var(--text-muted); cursor:default;">검색 결과 없음</div>';
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
});

asLoad();
</script>
@endpush
