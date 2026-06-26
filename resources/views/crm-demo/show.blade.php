@extends('layouts.app')

@section('title', '데모 프로젝트 상세')

@php
    $typeDef = $crm['project_types'][$p->project_type] ?? null;
    $pipeline = $typeDef['pipeline'] ?? [];
    $stageKeys = array_map(fn ($s) => $s['key'], $pipeline);
    $curIdx = array_search($p->stage, $stageKeys, true);
    if ($curIdx === false) {
        $curIdx = 0;
    }
    $cancelled = $p->status === 'cancelled';
@endphp

@push('styles')
<style>
    .d-wrap { max-width:1000px; margin:0 auto; padding:18px 18px 80px; }
    .d-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
    .d-back { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .d-backbtn { font-size:13px; color:var(--text-muted); text-decoration:none; border:1px solid var(--border); border-radius:8px; padding:6px 12px; }
    .d-backbtn.client { color:var(--accent); border-color:var(--accent); }
    .d-title { font-size:22px; font-weight:800; margin:8px 0 4px; }
    .d-tags { display:inline-flex; gap:5px; flex-wrap:wrap; vertical-align:middle; }
    .d-tag { font-size:11px; font-weight:700; color:#9d174d; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:12px; padding:2px 9px; }
    .d-meta { font-size:13px; color:var(--text-muted); display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .d-badge { font-size:11px; font-weight:700; color:var(--accent); border:1px solid var(--accent); border-radius:6px; padding:2px 8px; }
    .d-head-btns { display:flex; gap:8px; flex-wrap:wrap; }
    .d-btn { font-size:12px; border-radius:8px; padding:8px 14px; cursor:pointer; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); }
    .d-btn.primary { background:none; border-color:var(--accent); color:var(--accent); font-weight:700; }
    .d-btn.danger { border-color:var(--red); color:var(--red); }

    .d-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 22px; margin-bottom:16px; }
    .d-card-title { font-size:13px; font-weight:800; color:var(--accent); margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }
    .d-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media(max-width:720px){ .d-grid2{ grid-template-columns:1fr; } }

    /* 단계 dots */
    .proc-title { font-size:13px; font-weight:800; color:var(--accent); margin-bottom:18px; }
    .proc-steps { display:flex; align-items:flex-start; }
    .proc-step { flex:1; display:flex; flex-direction:column; align-items:center; position:relative; }
    .proc-step:not(:last-child)::after { content:''; position:absolute; top:18px; left:50%; width:100%; height:2px; background:var(--border); z-index:0; }
    .proc-step.done:not(:last-child)::after, .proc-step.active:not(:last-child)::after { background:var(--accent); }
    .step-dot { width:36px; height:36px; border-radius:50%; border:2px solid var(--border); background:var(--surface); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:var(--text-muted); cursor:pointer; position:relative; z-index:1; }
    .step-dot.done { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .step-dot.active { border-color:var(--accent); color:var(--accent); }
    .step-dot.billing { box-shadow:0 0 0 3px rgba(36,138,56,0.18); }
    .step-label { font-size:12px; margin-top:8px; color:var(--text-muted); text-align:center; }
    .step-label.active { color:var(--accent); font-weight:700; }

    .info-row { display:flex; gap:14px; font-size:13px; margin-bottom:8px; }
    .info-row .il { width:60px; color:var(--text-muted); flex-shrink:0; }
    .d-empty { color:var(--text-muted); font-size:13px; }

    .bill-item { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:8px; }
    .bill-amt { font-size:16px; font-weight:800; color:var(--accent); }
    .bill-line { display:flex; justify-content:space-between; font-size:12px; color:var(--text-muted); margin-top:4px; }
    .bi { display:flex; gap:8px; align-items:center; margin-bottom:6px; flex-wrap:wrap; }
    .bi input { padding:6px 9px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); font-size:12px; }

    .cs-item { border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:8px; }
    .cs-meta { font-size:11px; color:var(--text-muted); margin-bottom:5px; display:flex; gap:8px; }
    .fb-item { display:flex; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); font-size:13px; }
    .ta { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); color:var(--text); font-size:13px; box-sizing:border-box; resize:vertical; }
    .mini { font-size:11px; border:1px solid var(--border); background:none; color:var(--text-muted); border-radius:6px; padding:4px 9px; cursor:pointer; }
    .mini.danger { border-color:var(--red); color:var(--red); }
    .save-btn { background:var(--accent); color:var(--accent-text); border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; }
</style>
@endpush

@section('content')
<div class="d-wrap" id="dWrap" data-pid="{{ $p->id }}">
    <div class="d-head">
        <div>
            <div class="d-back">
                <a href="{{ route('crm-demo') }}" class="d-backbtn">← 프로젝트 목록</a>
                @if($p->client_name)<span class="d-backbtn client">👤 {{ $p->client_name }}</span>@endif
            </div>
            <div class="d-title">
                {{ $p->free_name ?: '(제목 없음)' }}
                <span class="d-tags">@foreach($p->tags ?? [] as $t)<span class="d-tag">{{ $t }}</span>@endforeach</span>
            </div>
            <div class="d-meta">
                <span class="d-badge">{{ $typeDef['label'] ?? $p->project_type }}</span>
                @if($p->requester_type){<span>{{ $crm['requester_types'][$p->requester_type]['label'] ?? '' }}</span>}@endif
                @if($p->work_type)<span>· {{ $p->work_type }}</span>@endif
                <span>· {{ $p->created_at?->format('Y.m.d') }} 시작</span>
                <span>· 담당: {{ $crm['departments'][$typeDef['department'] ?? ''] ?? '-' }}</span>
                @if($cancelled)<span style="color:var(--red); font-weight:700;">· 취소됨 ({{ $p->cancel_reason }})</span>@endif
            </div>
        </div>
        <div class="d-head-btns">
            <button class="d-btn primary" onclick="openEditDemo()">✎ 프로젝트 수정</button>
            @if(!$cancelled)<button class="d-btn" onclick="openCancelDemo()">취소</button>@endif
            <button class="d-btn danger" onclick="deleteDemo()">삭제</button>
        </div>
    </div>

    {{-- 진행 단계 --}}
    <div class="d-card">
        <div class="proc-title">진행 단계 — 클릭하여 변경</div>
        <div class="proc-steps">
            @foreach($pipeline as $i => $st)
                <div class="proc-step {{ $i < $curIdx ? 'done' : ($i === $curIdx ? 'active' : '') }}">
                    <div class="step-dot {{ $i < $curIdx ? 'done' : ($i === $curIdx ? 'active' : '') }} {{ ($st['billing'] ?? false) ? 'billing' : '' }}"
                         onclick="setStageDemo('{{ $st['key'] }}')">
                        {{ $i < $curIdx ? '✓' : $i + 1 }}
                    </div>
                    <div class="step-label {{ $i === $curIdx ? 'active' : '' }}">{{ $st['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="d-grid2">
        {{-- 의뢰자 정보 --}}
        <div class="d-card">
            <div class="d-card-title">의뢰자 정보</div>
            <div class="info-row"><div class="il">이름</div><div>{{ $p->client_name ?: '-' }}</div></div>
            <div class="info-row"><div class="il">연락처</div><div>{{ $p->client_phone ?: '-' }}</div></div>
            <div class="info-row"><div class="il">주소</div><div>{{ $p->client_address ?: '-' }}</div></div>
            <div class="info-row"><div class="il">유형</div><div>{{ $crm['requester_types'][$p->requester_type]['label'] ?? '-' }}</div></div>
        </div>
        {{-- 프로젝트 개요 --}}
        <div class="d-card">
            <div class="d-card-title">프로젝트 개요 <button class="mini" onclick="toggleOverviewEdit()">수정</button></div>
            <div id="ovView" class="d-empty">{{ $p->overview ?: '프로젝트 개요가 없습니다.' }}</div>
            <div id="ovEdit" style="display:none;">
                <textarea class="ta" id="ovInput" rows="4">{{ $p->overview }}</textarea>
                <div style="text-align:right; margin-top:8px;"><button class="save-btn" onclick="saveOverview()">저장</button></div>
            </div>
        </div>
    </div>

    {{-- 결제 내역 (청구) --}}
    <div class="d-card">
        <div class="d-card-title">💰 결제 내역 <button class="mini" onclick="addBillRow()">+ 항목 추가</button></div>
        <div id="billRows"></div>
        <div style="text-align:right; margin-top:8px;">
            <span id="billSum" style="font-size:12px; color:var(--text-muted); margin-right:10px;"></span>
            <button class="save-btn" onclick="saveBilling()">청구 저장</button>
        </div>
    </div>

    {{-- 상담 이력 --}}
    <div class="d-card">
        <div class="d-card-title">상담 이력 (<span id="csCount">{{ $p->consultations->count() }}</span>건) <button class="mini" onclick="document.getElementById('csForm').style.display='block'">+ 상담 등록</button></div>
        <div id="csForm" style="display:none; margin-bottom:12px;">
            <textarea class="ta" id="csContent" rows="2" placeholder="상담 내용"></textarea>
            <div style="display:flex; gap:8px; margin-top:8px; align-items:center; flex-wrap:wrap;">
                <input type="date" id="csDate" class="ta" style="width:auto;" value="{{ now()->format('Y-m-d') }}">
                <button class="save-btn" onclick="addConsult()">등록</button>
            </div>
        </div>
        <div id="csList"></div>
    </div>

    {{-- 피드백 --}}
    <div class="d-card">
        <div class="d-card-title">피드백</div>
        <div style="display:flex; gap:8px; margin-bottom:12px;">
            <textarea class="ta" id="fbInput" rows="2" placeholder="피드백을 입력하세요..." style="flex:1; resize:none;"></textarea>
            <button class="save-btn" onclick="addFeedback()">추가</button>
        </div>
        <div id="fbList"></div>
    </div>
</div>

{{-- 수정 / 취소 모달 (간단) --}}
<div id="editOv" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9000; align-items:center; justify-content:center; padding:18px;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; padding:20px;">
        <h3 style="margin:0 0 14px; font-size:16px; font-weight:800;">프로젝트 수정</h3>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">의뢰자</label><input id="eClient" class="ta" style="margin-top:5px;"></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">연락처</label><input id="ePhone" class="ta" style="margin-top:5px;"></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">주소</label><input id="eAddr" class="ta" style="margin-top:5px;"></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">의뢰자 유형</label><select id="eReq" class="ta" style="margin-top:5px;"></select></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">프로젝트 유형</label><select id="eType" class="ta" style="margin-top:5px;" onchange="eOnType()"></select></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">작업 유형</label><select id="eWork" class="ta" style="margin-top:5px;"></select></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px; font-weight:700; color:var(--text-muted);">주관식</label><input id="eFree" class="ta" style="margin-top:5px;"></div>
        <div style="text-align:right; display:flex; gap:8px; justify-content:flex-end;"><button class="mini" onclick="document.getElementById('editOv').style.display='none'">닫기</button><button class="save-btn" onclick="saveEditDemo()">저장</button></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CRM = @json($crm);
const P = @json($p);
const PID = {{ $p->id }};
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'};
function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// 단계 변경
async function setStageDemo(stage){
    const res=await fetch(`/api/crm-demo/projects/${PID}/stage`,{method:'PATCH',headers:H,body:JSON.stringify({stage})});
    if(res.ok) location.reload();
}
// 개요
function toggleOverviewEdit(){ document.getElementById('ovView').style.display='none'; document.getElementById('ovEdit').style.display='block'; }
async function saveOverview(){
    const v=document.getElementById('ovInput').value;
    const res=await fetch(`/api/crm-demo/projects/${PID}/overview`,{method:'PATCH',headers:H,body:JSON.stringify({overview:v})});
    if(res.ok) location.reload();
}
// 결제(청구)
let billing = (P.billing||[]).slice();
function renderBilling(){
    const wrap=document.getElementById('billRows');
    wrap.innerHTML = billing.map((b,i)=>`<div class="bi" data-i="${i}">
        <input placeholder="항목" value="${esc(b.label||'')}" data-k="label" style="flex:1; min-width:120px;">
        <input type="number" placeholder="청구액" value="${b.amount||''}" data-k="amount" style="width:110px;">
        <input type="number" placeholder="입금액" value="${b.paid||''}" data-k="paid" style="width:110px;">
        <input placeholder="입금일" value="${esc(b.paid_at||'')}" data-k="paid_at" style="width:100px;">
        <button class="mini danger" onclick="rmBill(${i})">×</button>
    </div>`).join('') || '<div class="d-empty">결제(청구) 항목이 없습니다. + 항목 추가</div>';
    const charged=billing.reduce((s,b)=>s+(+b.amount||0),0), paid=billing.reduce((s,b)=>s+(+b.paid||0),0);
    document.getElementById('billSum').innerHTML=`청구 <b>${charged.toLocaleString()}</b> · 입금 ${paid.toLocaleString()} · 잔금 <b style="color:var(--red)">${Math.max(0,charged-paid).toLocaleString()}</b>원`;
}
function collectBill(){ billing=[...document.querySelectorAll('#billRows .bi')].map(r=>{const o={};r.querySelectorAll('[data-k]').forEach(inp=>{o[inp.dataset.k]=inp.dataset.k==='label'||inp.dataset.k==='paid_at'?inp.value.trim():(parseInt(inp.value,10)||0);});return o;}); }
function addBillRow(){ collectBill(); billing.push({label:'',amount:0,paid:0,paid_at:''}); renderBilling(); }
function rmBill(i){ collectBill(); billing.splice(i,1); renderBilling(); }
async function saveBilling(){
    collectBill();
    const clean=billing.filter(b=>b.label||b.amount||b.paid);
    const res=await fetch(`/api/crm-demo/projects/${PID}/billing`,{method:'POST',headers:H,body:JSON.stringify({billing:clean})});
    if(res.ok) location.reload(); else alert('저장 실패');
}
// 상담 이력
function renderConsults(){
    const list=P.consultations||[];
    document.getElementById('csCount').textContent=list.length;
    document.getElementById('csList').innerHTML = list.length ? list.map(c=>`<div class="cs-item">
        <div class="cs-meta"><span>📅 ${esc(c.consulted_at||'')}</span><button class="mini danger" style="margin-left:auto;" onclick="delConsult(${c.id})">삭제</button></div>
        <div style="font-size:13px; white-space:pre-wrap;">${esc(c.content||'')}</div>
    </div>`).join('') : '<div class="d-empty">상담 이력이 없습니다.</div>';
}
async function addConsult(){
    const content=document.getElementById('csContent').value.trim();
    if(!content) return;
    const res=await fetch(`/api/crm-demo/projects/${PID}/consultations`,{method:'POST',headers:H,body:JSON.stringify({content, consulted_at:document.getElementById('csDate').value})});
    if(res.ok) location.reload();
}
async function delConsult(id){ if(!confirm('삭제할까요?'))return; await fetch(`/api/crm-demo/projects/${PID}/consultations/${id}`,{method:'DELETE',headers:H}); location.reload(); }
// 피드백
function renderFeedbacks(){
    const list=P.feedbacks||[];
    document.getElementById('fbList').innerHTML = list.length ? list.map(f=>`<div class="fb-item"><span style="white-space:pre-wrap;">${esc(f.content)}</span><button class="mini danger" onclick="delFeedback(${f.id})">삭제</button></div>`).join('') : '<div class="d-empty">피드백이 없습니다.</div>';
}
async function addFeedback(){
    const content=document.getElementById('fbInput').value.trim();
    if(!content) return;
    const res=await fetch(`/api/crm-demo/projects/${PID}/feedbacks`,{method:'POST',headers:H,body:JSON.stringify({content})});
    if(res.ok) location.reload();
}
async function delFeedback(id){ if(!confirm('삭제할까요?'))return; await fetch(`/api/crm-demo/projects/${PID}/feedbacks/${id}`,{method:'DELETE',headers:H}); location.reload(); }

// 취소 / 삭제
async function openCancelDemo(){
    const r=prompt('취소 사유를 입력/선택:\n'+CRM.cancel_reasons.join(' / '), CRM.cancel_reasons[0]);
    if(!r) return;
    const res=await fetch(`/api/crm-demo/projects/${PID}/cancel`,{method:'POST',headers:H,body:JSON.stringify({cancel_reason:r})});
    if(res.ok) location.reload();
}
async function deleteDemo(){ if(!confirm('이 데모 프로젝트를 삭제할까요?'))return; await fetch(`/api/crm-demo/projects/${PID}`,{method:'DELETE',headers:H}); location.href='{{ route('crm-demo') }}'; }

// 수정 모달
function openEditDemo(){
    document.getElementById('eReq').innerHTML='<option value="">선택</option>'+Object.entries(CRM.requester_types).map(([k,v])=>`<option value="${k}" ${P.requester_type===k?'selected':''}>${v.label}</option>`).join('');
    document.getElementById('eType').innerHTML=Object.entries(CRM.project_types).map(([k,v])=>`<option value="${k}" ${P.project_type===k?'selected':''}>${v.label}</option>`).join('');
    eOnType(P.work_type);
    document.getElementById('eClient').value=P.client_name||'';
    document.getElementById('ePhone').value=P.client_phone||'';
    document.getElementById('eAddr').value=P.client_address||'';
    document.getElementById('eFree').value=P.free_name||'';
    document.getElementById('editOv').style.display='flex';
}
function eOnType(presetWork){
    const k=document.getElementById('eType').value;
    const works=CRM.project_types[k]?.work_types||[];
    document.getElementById('eWork').innerHTML='<option value="">선택</option>'+works.map(w=>`<option value="${esc(w)}" ${presetWork===w?'selected':''}>${esc(w)}</option>`).join('');
}
async function saveEditDemo(){
    const body={
        client_name:document.getElementById('eClient').value.trim()||null,
        client_id:null,
        client_phone:document.getElementById('ePhone').value.trim()||null,
        client_address:document.getElementById('eAddr').value.trim()||null,
        requester_type:document.getElementById('eReq').value||null,
        project_type:document.getElementById('eType').value,
        work_type:document.getElementById('eWork').value||null,
        tags:P.tags||[],
        free_name:document.getElementById('eFree').value.trim()||null,
    };
    const res=await fetch(`/api/crm-demo/projects/${PID}`,{method:'PATCH',headers:H,body:JSON.stringify(body)});
    if(res.ok) location.reload(); else { const e=await res.json().catch(()=>({})); alert(e.message||'저장 실패'); }
}

renderBilling(); renderConsults(); renderFeedbacks();
</script>
@endpush
