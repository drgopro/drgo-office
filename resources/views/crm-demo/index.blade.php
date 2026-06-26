@extends('layouts.app')

@section('title', 'CRM 개편 데모 - 닥터고블린 오피스')

@push('styles')
<style>
    .crm-wrap { max-width:1040px; margin:0 auto; padding:20px 18px 80px; }
    .crm-top { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
    .crm-wrap h1.page { font-size:24px; font-weight:800; margin:0; }
    .crm-wrap .lead { font-size:13px; color:var(--text-muted); }
    .crm-banner { background:rgba(212,188,150,0.12); border:1px solid var(--accent); border-radius:10px; padding:9px 13px; font-size:12.5px; margin:10px 0 16px; }
    .crm-btn { border:none; border-radius:8px; padding:9px 16px; font-size:13px; font-weight:700; cursor:pointer; }
    .crm-btn.primary { background:var(--accent); color:var(--accent-text); }
    .crm-btn.ghost { background:var(--surface); border:1px solid var(--border); color:var(--text-muted); }
    .crm-filter { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
    .crm-fbtn { font-size:12px; padding:6px 12px; border-radius:16px; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); cursor:pointer; }
    .crm-fbtn.active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }

    /* 운영 목록과 동일한 테이블 형식 */
    .pj-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    table.pj-table { width:100%; border-collapse:collapse; }
    table.pj-table thead { background:var(--surface2); }
    table.pj-table th { padding:11px 16px; text-align:left; font-size:11px; color:var(--text-muted); font-weight:600; letter-spacing:.05em; border-bottom:1px solid var(--border); white-space:nowrap; }
    table.pj-table td { padding:12px 16px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:middle; }
    table.pj-table tr.row-main { cursor:pointer; }
    table.pj-table tr.row-main:hover td { background:var(--surface2); }
    table.pj-table tr.row-main.cancelled td { background:rgba(220,38,38,0.06); }
    .pj-tags-cell { display:flex; gap:5px; flex-wrap:wrap; align-items:center; }
    .pj-type-badge { font-size:10px; font-weight:700; color:var(--accent); border:1px solid var(--accent); border-radius:5px; padding:2px 8px; white-space:nowrap; }
    .pj-stage-badge { font-size:10px; font-weight:600; padding:2px 8px; border-radius:5px; background:var(--surface2); border:1px solid var(--border); white-space:nowrap; }
    .pj-stage-badge.billing { background:rgba(36,138,56,0.12); border-color:#86efac; color:#248a38; }
    .pj-stage-badge.done { color:var(--text-muted); }
    .pj-cell-muted { color:var(--text-muted); font-size:12px; }
    .pj-detail-td { padding:0 !important; background:var(--surface2); }
    .pj-detail-inner { padding:14px 18px; }
    .pj-free { font-size:13px; color:var(--text); margin-bottom:8px; }
    .caret-mini { color:var(--text-muted); font-size:11px; transition:transform .15s; display:inline-block; }
    tr.row-main.expanded .caret-mini { transform:rotate(90deg); }

    .pj-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 16px; margin-bottom:10px; }
    .pj-card.cancelled { background:rgba(220,38,38,0.06); border-color:rgba(220,38,38,0.3); }
    .pj-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .pj-name { font-size:15px; font-weight:700; }
    .pj-tag { font-size:11px; font-weight:700; color:#9d174d; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:12px; padding:2px 9px; }
    .pj-type { font-size:11px; font-weight:700; color:var(--accent); border:1px solid var(--accent); border-radius:6px; padding:2px 8px; }
    .pj-work { font-size:11px; color:var(--text-muted); background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:2px 8px; }
    .pj-meta { font-size:12px; color:var(--text-muted); margin-top:6px; display:flex; gap:10px; flex-wrap:wrap; }
    .pj-cancel-badge { font-size:11px; font-weight:700; color:#fff; background:#dc2626; border-radius:5px; padding:2px 8px; }
    .pj-reason { font-size:11px; color:#b91c1c; background:#fee2e2; border:1px solid #fecaca; border-radius:5px; padding:2px 8px; }
    .pj-pipe { display:flex; align-items:center; flex-wrap:wrap; gap:4px; margin-top:10px; }
    .pj-step { font-size:11.5px; padding:5px 10px; border-radius:7px; background:var(--surface2); border:1px solid var(--border); cursor:pointer; white-space:nowrap; }
    .pj-step.cur { background:var(--accent); color:var(--accent-text); border-color:var(--accent); font-weight:700; }
    .pj-step.done { opacity:0.55; }
    .pj-step.billing { border-color:#86efac; }
    .pj-step.billing.cur { background:#248a38; border-color:#248a38; color:#fff; }
    .pj-sep { color:var(--text-muted); }
    .pj-actions { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }
    .pj-mini { font-size:11px; padding:4px 10px; border-radius:6px; border:1px solid var(--border); background:none; color:var(--text-muted); cursor:pointer; }
    .pj-mini.danger { border-color:var(--red); color:var(--red); }
    .pj-mini.detail { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:700; text-decoration:none; }
    .pj-bill { margin-top:10px; padding:10px; background:var(--surface2); border-radius:8px; font-size:12px; }
    .pj-bill .bill-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; flex-wrap:wrap; }
    .pj-bill input { width:110px; padding:5px 8px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); font-size:12px; }
    .bill-sum { font-weight:700; }
    .bill-out { color:var(--red); }

    /* 모달 */
    .crm-modal-ov { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9000; align-items:center; justify-content:center; padding:18px; }
    .crm-modal-ov.open { display:flex; }
    .crm-modal { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto; padding:20px; }
    .crm-modal h3 { font-size:16px; font-weight:800; margin:0 0 14px; display:flex; justify-content:space-between; }
    .crm-modal .fld { margin-bottom:13px; }
    .crm-modal .fld label { display:block; font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:5px; }
    .crm-modal .fld input, .crm-modal .fld select, .crm-modal .fld textarea { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); color:var(--text); font-size:13px; box-sizing:border-box; }
    /* 대주제 태그 드롭다운 */
    .tag-dd { position:relative; }
    .tag-dd-control { display:flex; align-items:center; gap:6px; flex-wrap:wrap; border:1px solid var(--accent); border-radius:8px; background:var(--surface2); padding:8px 11px; min-height:42px; cursor:pointer; }
    .tag-dd-chips { display:flex; gap:6px; flex-wrap:wrap; flex:1; }
    .tag-dd-ph { font-size:13px; color:var(--text-muted); }
    .tag-dd-chip { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#9d174d; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:14px; padding:3px 6px 3px 10px; }
    .tag-dd-chip .x { width:15px; height:15px; border-radius:50%; background:#f8d4e4; color:#9d174d; font-size:10px; display:inline-flex; align-items:center; justify-content:center; font-weight:800; cursor:pointer; }
    .tag-dd-caret { color:var(--accent); margin-left:auto; }
    .tag-dd-menu { position:absolute; left:0; right:0; top:calc(100% + 4px); z-index:20; background:var(--surface); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,0.15); overflow:hidden; max-height:240px; overflow-y:auto; }
    .tag-dd-opt { font-size:13px; padding:9px 13px; display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border); cursor:pointer; }
    .tag-dd-opt:first-child { border-top:none; }
    .tag-dd-opt:hover { background:var(--surface2); }
    .tag-dd-opt.sel { background:#fdf2f8; color:#9d174d; font-weight:700; }
    .tag-dd-opt.manage { color:var(--accent); font-weight:700; background:var(--surface2); }
    .crm-modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:6px; }
    .empty { text-align:center; color:var(--text-muted); padding:40px; font-size:13px; }
</style>
@endpush

@section('content')
<div class="crm-wrap">
    <div class="crm-top">
        <div>
            <h1 class="page">CRM 개편 데모</h1>
            <span class="lead">실제처럼 동작하는 검증용 (운영 데이터와 분리)</span>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="crm-btn ghost" onclick="openTagManage()">🏷 태그 관리</button>
            <button class="crm-btn primary" onclick="openCreate()">+ 새 프로젝트</button>
        </div>
    </div>
    <div class="crm-banner">⚙️ config/crm.php 기반. 여기서 만든 데이터는 demo_projects 테이블에만 저장되며 운영에 영향 없음.</div>

    <div class="crm-filter" id="crmFilter">
        <button class="crm-fbtn active" data-f="" onclick="setFilter('')">전체</button>
        <button class="crm-fbtn" data-f="billing" onclick="setFilter('billing')">💰 잔금 남은 건</button>
    </div>

    <div id="pjList"><div class="empty">불러오는 중…</div></div>
</div>

{{-- 생성 모달 --}}
<div class="crm-modal-ov" id="createModal">
    <div class="crm-modal">
        <h3><span id="createTitle">새 프로젝트</span> <button class="pj-mini" onclick="closeModal('createModal')">✕</button></h3>
        <div class="fld">
            <label>의뢰자 <span style="font-weight:400; color:var(--text-muted);">(데모 전용 · 직접 입력)</span></label>
            <input id="cClientSearch" placeholder="의뢰자명 직접 입력" autocomplete="off">
        </div>
        <div class="fld">
            <label>의뢰자 유형</label>
            <select id="cReqType"></select>
        </div>
        <div class="fld">
            <label>프로젝트 유형 *</label>
            <select id="cProjType" onchange="onProjTypeChange()"></select>
        </div>
        <div class="fld">
            <label>작업 유형 (유형에 종속)</label>
            <select id="cWorkType"></select>
        </div>
        <div class="fld">
            <label>대주제 태그 * <span style="font-weight:400; color:var(--text-muted);">(드롭다운 · 복수)</span></label>
            <div class="tag-dd" id="cTagDd">
                <div class="tag-dd-control" onclick="toggleTagMenu(event)">
                    <span class="tag-dd-chips" id="cTagChips"></span>
                    <span class="tag-dd-caret">▾</span>
                </div>
                <div class="tag-dd-menu" id="cTagMenu" style="display:none;"></div>
            </div>
        </div>
        <div class="fld">
            <label>주관식 (식별용 메모)</label>
            <input id="cFreeName" placeholder="예: 스튜디오 A 2층 이전 세팅">
        </div>
        <div class="crm-modal-actions">
            <button class="crm-btn ghost" onclick="closeModal('createModal')">취소</button>
            <button class="crm-btn primary" onclick="submitCreate()">생성</button>
        </div>
    </div>
</div>

{{-- 태그 관리 모달 --}}
<div class="crm-modal-ov" id="tagModal">
    <div class="crm-modal" style="max-width:420px;">
        <h3>대주제 태그 관리 <button class="pj-mini" onclick="closeModal('tagModal')">✕</button></h3>
        <div class="fld" style="display:flex; gap:6px;">
            <input id="newTagName" placeholder="새 태그 이름" style="flex:1;">
            <button class="crm-btn primary" onclick="addTag()">추가</button>
        </div>
        <div id="tagManageList"></div>
    </div>
</div>

{{-- 취소 모달 --}}
<div class="crm-modal-ov" id="cancelModal">
    <div class="crm-modal" style="max-width:400px;">
        <h3>프로젝트 취소 <button class="pj-mini" onclick="closeModal('cancelModal')">✕</button></h3>
        <div class="fld">
            <label>취소 사유</label>
            <select id="cancelReason"></select>
        </div>
        <div class="crm-modal-actions">
            <button class="crm-btn ghost" onclick="closeModal('cancelModal')">닫기</button>
            <button class="crm-btn primary" style="background:var(--red);" onclick="submitCancel()">취소 처리</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CRM = @json($crm);
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'};
let allTags = [];
let curFilter = '';
let cancelTargetId = null;

function reqTypeLabel(k){ return CRM.requester_types[k]?.label || ''; }

// ── 목록 ──
let expandedRows = new Set();
let projectsById = {};
async function loadProjects(){
    const params = curFilter==='billing' ? '?billing_only=1' : '';
    const res = await fetch('/api/crm-demo/projects'+params, {headers:{'Accept':'application/json'}});
    const list = await res.json();
    projectsById = {}; list.forEach(p=>projectsById[p.id]=p);
    const el = document.getElementById('pjList');
    if(!list.length){ el.innerHTML='<div class="empty">프로젝트가 없습니다. + 새 프로젝트로 추가하세요.</div>'; return; }
    el.innerHTML = `<div class="pj-table-wrap"><table class="pj-table">
        <thead><tr>
            <th style="width:24%;">의뢰자</th>
            <th>프로젝트명 (대주제 태그)</th>
            <th style="width:90px;">유형</th>
            <th style="width:110px;">단계</th>
            <th style="width:90px;">담당</th>
        </tr></thead>
        <tbody>${list.map(renderRow).join('')}</tbody>
    </table></div>`;
}
function renderRow(p){
    const cancelled = p.status==='cancelled';
    const expanded = expandedRows.has(p.id);
    const tags = (p.tags||[]).map(t=>`<span class="pj-tag">${esc(t)}</span>`).join('') || '<span class="pj-cell-muted">태그 없음</span>';
    const curStep = p.pipeline.find(s=>s.key===p.stage);
    const billing = (p.billing_outstanding>0) ? ` <span class="pj-cell-muted">· 잔금 ${(p.billing_outstanding).toLocaleString()}</span>` : '';
    const main = `<tr class="row-main ${cancelled?'cancelled':''} ${expanded?'expanded':''}" onclick="toggleRow(${p.id})">
        <td><span class="caret-mini">▶</span> ${esc(p.client_name||'-')}${p.requester_type_label?` <span class="pj-cell-muted">${esc(p.requester_type_label)}</span>`:''}</td>
        <td><div class="pj-tags-cell">${tags}${cancelled?'<span class="pj-cancel-badge">취소</span>':''}</div></td>
        <td><span class="pj-type-badge">${esc(p.project_type_label)}</span></td>
        <td><span class="pj-stage-badge ${(curStep&&curStep.billing)?'billing':''} ${p.status==='done'?'done':''}">${esc(p.stage_label)}</span>${billing}</td>
        <td class="pj-cell-muted">${esc(p.department||'-')}</td>
    </tr>`;
    if(!expanded) return main;
    // 펼침 상세
    const curIdx = p.pipeline.findIndex(s=>s.key===p.stage);
    const pipe = p.pipeline.map((s,i)=>{
        let cls='pj-step'; if(s.billing)cls+=' billing';
        if(s.key===p.stage)cls+=' cur'; else if(i<curIdx)cls+=' done';
        return (i>0?'<span class="pj-sep">→</span>':'')+`<span class="${cls}" onclick="setStage(${p.id},'${s.key}')">${esc(s.label)}</span>`;
    }).join('');
    const detail = `<tr class="row-detail"><td class="pj-detail-td" colspan="5"><div class="pj-detail-inner">
        ${p.free_name?`<div class="pj-free">📝 ${esc(p.free_name)}</div>`:''}
        ${cancelled?`<div class="pj-free" style="color:#b91c1c;">⛔ 취소 사유: ${esc(p.cancel_reason||'-')}</div>`:''}
        <div class="pj-cell-muted" style="margin-bottom:8px;">${p.work_type?'작업유형: '+esc(p.work_type)+' · ':''}생성 ${p.created_at||''}</div>
        <div class="pj-pipe">${pipe}</div>
        ${renderBilling(p)}
        <div class="pj-actions">
            <a class="pj-mini detail" href="/crm-demo/${p.id}" onclick="event.stopPropagation();">🔍 상세 보기 / 보고·수정</a>
            <button class="pj-mini" onclick="openEdit(projectsById[${p.id}])">✎ 수정</button>
            <button class="pj-mini" onclick="toggleBilling(${p.id})">💰 청구/잔금</button>
            ${!cancelled?`<button class="pj-mini danger" onclick="openCancel(${p.id})">취소</button>`:''}
            <button class="pj-mini danger" onclick="delProject(${p.id})">삭제</button>
        </div>
    </div></td></tr>`;
    return main + detail;
}
function toggleRow(id){
    if(expandedRows.has(id)) expandedRows.delete(id); else expandedRows.add(id);
    loadProjects();
}
function renderBilling(p){
    if(!p.billing || !p.billing.length) return `<div class="pj-bill" id="bill-${p.id}" style="display:none;"></div>`;
    const rows = p.billing.map((b,i)=>billRow(p.id,i,b)).join('');
    return `<div class="pj-bill" id="bill-${p.id}">
        ${rows}
        <div class="bill-row"><button class="pj-mini" onclick="addBillRow(${p.id})">+ 항목</button>
        <span style="margin-left:auto;">청구 <span class="bill-sum">${(p.billing_charged||0).toLocaleString()}</span> · 입금 ${(p.billing_paid||0).toLocaleString()} · 잔금 <span class="bill-out">${(p.billing_outstanding||0).toLocaleString()}</span>원</span></div>
        <div class="bill-row"><button class="pj-mini" onclick="saveBilling(${p.id})">저장</button></div>
    </div>`;
}
function billRow(pid,i,b){
    return `<div class="bill-row" data-bill="${pid}">
        <input placeholder="항목" value="${esc(b.label||'')}" data-bk="label">
        <input type="number" placeholder="청구액" value="${b.amount||''}" data-bk="amount">
        <input type="number" placeholder="입금액" value="${b.paid||''}" data-bk="paid">
        <input placeholder="입금일" value="${esc(b.paid_at||'')}" data-bk="paid_at" style="width:90px;">
    </div>`;
}
function toggleBilling(pid){
    const el=document.getElementById('bill-'+pid);
    if(!el) return;
    if(el.style.display==='none'||!el.innerHTML.trim()){
        // 비어있으면 한 줄 추가
        if(!el.innerHTML.trim()){ el.innerHTML = billRow(pid,0,{}) + `<div class="bill-row"><button class="pj-mini" onclick="addBillRow(${pid})">+ 항목</button></div><div class="bill-row"><button class="pj-mini" onclick="saveBilling(${pid})">저장</button></div>`; }
        el.style.display='block';
    } else { el.style.display='none'; }
}
function addBillRow(pid){
    const el=document.getElementById('bill-'+pid);
    const tmp=document.createElement('div'); tmp.innerHTML=billRow(pid,0,{});
    el.insertBefore(tmp.firstElementChild, el.querySelector('.bill-row:nth-last-child(2)')||null);
}
async function saveBilling(pid){
    const el=document.getElementById('bill-'+pid);
    const billing=[...el.querySelectorAll('.bill-row[data-bill]')].map(r=>{
        const o={}; r.querySelectorAll('[data-bk]').forEach(inp=>{ o[inp.dataset.bk]= inp.dataset.bk==='label'||inp.dataset.bk==='paid_at'? inp.value.trim() : (parseInt(inp.value,10)||0); });
        return o;
    }).filter(o=>o.label||o.amount||o.paid);
    const res=await fetch(`/api/crm-demo/projects/${pid}/billing`,{method:'POST',headers:H,body:JSON.stringify({billing})});
    if(res.ok) loadProjects(); else alert('저장 실패');
}

async function setStage(pid,stage){
    const res=await fetch(`/api/crm-demo/projects/${pid}/stage`,{method:'PATCH',headers:H,body:JSON.stringify({stage})});
    if(res.ok) loadProjects();
}
async function delProject(pid){
    if(!confirm('이 데모 프로젝트를 삭제할까요?')) return;
    await fetch(`/api/crm-demo/projects/${pid}`,{method:'DELETE',headers:H});
    loadProjects();
}
function setFilter(f){ curFilter=f; document.querySelectorAll('.crm-fbtn').forEach(b=>b.classList.toggle('active',b.dataset.f===f)); loadProjects(); }

// ── 생성/수정 ──
let editingId = null;
function fillTypeSelects(){
    document.getElementById('cReqType').innerHTML='<option value="">선택</option>'+Object.entries(CRM.requester_types).map(([k,v])=>`<option value="${k}">${v.label}</option>`).join('');
    document.getElementById('cProjType').innerHTML=Object.entries(CRM.project_types).map(([k,v])=>`<option value="${k}">${v.label}</option>`).join('');
}
function clearClientPick(){
    document.getElementById('cClientSearch').value='';
}
function openCreate(){
    editingId=null;
    document.getElementById('createTitle').textContent='새 프로젝트';
    fillTypeSelects();
    clearClientPick();
    document.getElementById('cReqType').value='';
    document.getElementById('cFreeName').value='';
    onProjTypeChange();
    renderTagPick([]);
    document.getElementById('createModal').classList.add('open');
}
function openEdit(p){
    editingId=p.id;
    document.getElementById('createTitle').textContent='프로젝트 수정';
    fillTypeSelects();
    clearClientPick();
    if(p.client_name){ document.getElementById('cClientSearch').value=p.client_name; }
    document.getElementById('cReqType').value=p.requester_type||'';
    document.getElementById('cProjType').value=p.project_type;
    onProjTypeChange();
    document.getElementById('cWorkType').value=p.work_type||'';
    document.getElementById('cFreeName').value=p.free_name||'';
    renderTagPick(p.tags||[]);
    document.getElementById('createModal').classList.add('open');
}
function onProjTypeChange(){
    const k=document.getElementById('cProjType').value;
    const works=CRM.project_types[k]?.work_types||[];
    document.getElementById('cWorkType').innerHTML='<option value="">선택</option>'+works.map(w=>`<option value="${esc(w)}">${esc(w)}</option>`).join('');
}
let pickedTags=[];
function renderTagPick(sel){
    pickedTags=[...sel];
    // 선택 칩 (컨트롤 안)
    const chips=document.getElementById('cTagChips');
    chips.innerHTML = pickedTags.length
        ? pickedTags.map(n=>`<span class="tag-dd-chip">${esc(n)}<span class="x" onclick="event.stopPropagation(); toggleTagPick('${esc(n)}')">×</span></span>`).join('')
        : '<span class="tag-dd-ph">태그 선택…</span>';
    // 메뉴 옵션
    const menu=document.getElementById('cTagMenu');
    menu.innerHTML = (allTags.length
        ? allTags.map(t=>`<div class="tag-dd-opt ${pickedTags.includes(t.name)?'sel':''}" onclick="event.stopPropagation(); toggleTagPick('${esc(t.name)}')">${esc(t.name)}${pickedTags.includes(t.name)?'<span>✓</span>':''}</div>`).join('')
        : '<div class="tag-dd-opt" style="color:var(--text-muted);">태그 없음</div>')
        + `<div class="tag-dd-opt manage" onclick="event.stopPropagation(); closeTagMenu(); openTagManage();">+ 태그 추가 / 삭제 (관리)</div>`;
}
function toggleTagPick(name){
    const i=pickedTags.indexOf(name);
    if(i>=0)pickedTags.splice(i,1); else pickedTags.push(name);
    renderTagPick(pickedTags);
}
function toggleTagMenu(e){
    if(e) e.stopPropagation();
    const menu=document.getElementById('cTagMenu');
    menu.style.display = menu.style.display==='none' ? 'block' : 'none';
}
function closeTagMenu(){ const m=document.getElementById('cTagMenu'); if(m) m.style.display='none'; }
// 바깥 클릭 시 메뉴 닫기
document.addEventListener('click', e=>{ const dd=document.getElementById('cTagDd'); if(dd && !dd.contains(e.target)) closeTagMenu(); });
async function submitCreate(){
    if(!pickedTags.length) return alert('대주제 태그를 1개 이상 선택하세요.');
    if(!document.getElementById('cProjType').value) return alert('프로젝트 유형을 선택하세요.');
    const body={
        client_name:document.getElementById('cClientSearch').value.trim()||null,
        client_id:null, // 데모 전용 — 실제 의뢰자 데이터와 연동하지 않음
        requester_type:document.getElementById('cReqType').value||null,
        project_type:document.getElementById('cProjType').value,
        work_type:document.getElementById('cWorkType').value||null,
        tags:pickedTags,
        free_name:document.getElementById('cFreeName').value.trim()||null,
    };
    const url = editingId ? `/api/crm-demo/projects/${editingId}` : '/api/crm-demo/projects';
    const method = editingId ? 'PATCH' : 'POST';
    const res=await fetch(url,{method,headers:H,body:JSON.stringify(body)});
    if(res.ok){ closeModal('createModal'); loadProjects(); }
    else { const e=await res.json().catch(()=>({})); alert(e.message||'저장 실패'); }
}

// ── 취소 ──
function openCancel(pid){
    cancelTargetId=pid;
    document.getElementById('cancelReason').innerHTML=CRM.cancel_reasons.map(r=>`<option>${esc(r)}</option>`).join('');
    document.getElementById('cancelModal').classList.add('open');
}
async function submitCancel(){
    const res=await fetch(`/api/crm-demo/projects/${cancelTargetId}/cancel`,{method:'POST',headers:H,body:JSON.stringify({cancel_reason:document.getElementById('cancelReason').value})});
    if(res.ok){ closeModal('cancelModal'); loadProjects(); }
}

// ── 태그 관리 ──
async function loadTags(){ const res=await fetch('/api/crm-demo/tags',{headers:{'Accept':'application/json'}}); allTags=await res.json(); }
function openTagManage(){ renderTagManage(); document.getElementById('tagModal').classList.add('open'); }
function renderTagManage(){
    document.getElementById('tagManageList').innerHTML=allTags.map(t=>`<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);"><span>${esc(t.name)}</span><button class="pj-mini danger" onclick="delTag(${t.id})">삭제</button></div>`).join('')||'<div class="empty">태그 없음</div>';
}
async function addTag(){
    const name=document.getElementById('newTagName').value.trim();
    if(!name) return;
    const res=await fetch('/api/crm-demo/tags',{method:'POST',headers:H,body:JSON.stringify({name})});
    if(res.ok){ document.getElementById('newTagName').value=''; await loadTags(); renderTagManage(); refreshTagPickIfOpen(); }
    else { const e=await res.json().catch(()=>({})); alert(e.message||Object.values(e.errors||{}).flat().join('\n')||'추가 실패'); }
}
async function delTag(id){
    if(!confirm('태그를 삭제할까요? (기존 프로젝트 태그 이름은 유지됨)')) return;
    await fetch('/api/crm-demo/tags/'+id,{method:'DELETE',headers:H});
    await loadTags(); renderTagManage(); refreshTagPickIfOpen();
}
function refreshTagPickIfOpen(){
    if(document.getElementById('createModal').classList.contains('open')) renderTagPick(pickedTags.filter(n=>allTags.some(t=>t.name===n)));
}

function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

(async function init(){ await loadTags(); loadProjects(); })();
</script>
@endpush
