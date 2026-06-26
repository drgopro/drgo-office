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

    .pay-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; }
    .pay-badge.charge { background:rgba(122,200,160,0.15); color:#2f8f5b; border:1px solid rgba(122,200,160,0.45); }
    .pay-badge.refund { background:rgba(232,137,74,0.15); color:#c2691f; border:1px solid rgba(232,137,74,0.45); }
    .pay-badge.cancel { background:rgba(200,80,80,0.15); color:var(--red); border:1px solid rgba(200,80,80,0.45); }
    .pay-ov { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
    .pay-modal { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; }
    .pay-modal-head { display:flex; justify-content:space-between; align-items:center; padding:15px 18px; border-bottom:1px solid var(--border); font-size:15px; font-weight:800; }
    .pay-modal-body { padding:16px 18px; display:flex; flex-direction:column; gap:13px; }
    .pay-modal-foot { display:flex; gap:8px; justify-content:flex-end; padding:13px 18px; border-top:1px solid var(--border); }
    .pl { display:block; font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:4px; }
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
                @if($p->requester_type)<span>{{ $crm['requester_types'][$p->requester_type]['label'] ?? '' }}</span>@endif
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
            <div class="d-card-title"><span>프로젝트 개요</span> <button class="mini" onclick="toggleOverviewEdit()">수정</button></div>
            <div id="ovView" class="d-empty">{{ $p->overview ?: '프로젝트 개요가 없습니다.' }}</div>
            <div id="ovEdit" style="display:none;">
                <textarea class="ta" id="ovInput" rows="4">{{ $p->overview }}</textarea>
                <div style="text-align:right; margin-top:8px;"><button class="save-btn" onclick="saveOverview()">저장</button></div>
            </div>
        </div>
    </div>

    {{-- 결제 내역 (운영 동일: 결제/환불/취소 트랜잭션) --}}
    <div class="d-card">
        <div class="d-card-title">
            <span>💰 결제 내역 <span id="phNetTotal" style="font-size:12px; color:var(--text-muted); margin-left:4px; font-weight:600;"></span></span>
            <button class="mini" onclick="openPayModal()">+ 결제 추가</button>
        </div>
        <div id="payList" style="display:flex; flex-direction:column; gap:8px;"></div>
        <div id="payBalanceLine" style="display:none; margin-top:10px; font-size:13px; color:var(--red); font-weight:700;"></div>
    </div>

    {{-- 상담 이력 --}}
    <div class="d-card">
        <div class="d-card-title"><span>상담 이력 (<span id="csCount">{{ $p->consultations->count() }}</span>건)</span> <button class="mini" onclick="document.getElementById('csForm').style.display='block'">+ 상담 등록</button></div>
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

{{-- 결제 정보 모달 --}}
<div id="payModal" class="pay-ov" style="display:none;">
    <div class="pay-modal">
        <div class="pay-modal-head"><span id="payModalTitle">💰 결제 정보 입력</span><button class="mini" onclick="closePayModal()">✕</button></div>
        <div class="pay-modal-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div><label class="pl">결제 금액 (원) *</label><input type="number" id="payAmount" class="ta" min="0"></div>
                <div><label class="pl">결제일</label><input type="date" id="payPaidAt" class="ta"></div>
            </div>
            <div><label class="pl">결제 수단</label>
                <select id="payMethod" class="ta">
                    <option value="">선택...</option><option value="카드">카드</option><option value="현금">현금</option><option value="계좌이체">계좌이체</option><option value="기타">기타</option>
                </select>
            </div>
            <div>
                <label class="pl" style="display:flex; justify-content:space-between; align-items:center;"><span>결제 항목 (수기 입력)</span><button class="mini" onclick="addPayItem()">+ 항목</button></label>
                <div id="payItems" style="display:flex; flex-direction:column; gap:6px;"></div>
                <div style="font-size:10px; color:var(--text-muted); margin-top:4px;">항목 입력 시 금액이 자동 합산됩니다.</div>
            </div>
            <div id="payBalanceFields">
                <label class="pl">잔금 여부</label>
                <div style="display:flex; gap:14px; align-items:center;">
                    <label style="display:flex; gap:4px; align-items:center; font-size:13px;"><input type="radio" name="payHasBalance" value="1" onchange="togglePayBalance()"> 있음</label>
                    <label style="display:flex; gap:4px; align-items:center; font-size:13px;"><input type="radio" name="payHasBalance" value="0" checked onchange="togglePayBalance()"> 없음</label>
                    <div id="payBalanceWrap" style="display:none; flex:1;"><input type="number" id="payBalanceAmount" class="ta" placeholder="잔금 금액 (원)" min="0"></div>
                </div>
            </div>
            <div><label class="pl">메모</label><textarea id="payMemo" class="ta" rows="2"></textarea></div>
        </div>
        <div class="pay-modal-foot"><button class="mini" onclick="closePayModal()">취소</button><button class="save-btn" onclick="savePay()">저장</button></div>
    </div>
</div>

{{-- 환불 / 취소 모달 --}}
<div id="refundModal" class="pay-ov" style="display:none;">
    <div class="pay-modal" style="max-width:460px;">
        <div class="pay-modal-head"><span id="refundTitle">↩ 환불</span><button class="mini" onclick="closeRefund()">✕</button></div>
        <div class="pay-modal-body">
            <div id="refundMeta" style="font-size:12px; color:var(--text-muted);"></div>
            <div id="refundAmountWrap"><label class="pl">환불 금액 (원)</label><input type="number" id="refundAmount" class="ta" min="0"></div>
            <div><label class="pl">사유 (선택)</label><textarea id="refundReason" class="ta" rows="2"></textarea></div>
        </div>
        <div class="pay-modal-foot"><button class="mini" onclick="closeRefund()">닫기</button><button class="save-btn" onclick="submitRefund()">처리</button></div>
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
// ── 결제 내역 (운영 동일: charge/refund/cancel) ──
let __payments = [];
let __payEditId = null;          // 수정 중인 charge id (null=신규)
let __refundCtx = null;          // {chargeId, type, refundable}
const fmt = n => Number(n||0).toLocaleString('ko-KR');

async function loadPayments(){
    const res = await fetch(`/api/crm-demo/projects/${PID}/payments`, {headers:H});
    if(!res.ok) return;
    const data = await res.json();
    __payments = data.payments || [];
    renderPayments(data);
}
function renderPayments(data){
    const list = document.getElementById('payList');
    const net = __payments.reduce((s,p)=>s+(p.amount||0),0);
    document.getElementById('phNetTotal').textContent = `· 순 결제액 ${fmt(net)}원`;

    const balLine = document.getElementById('payBalanceLine');
    if(data && data.has_balance && data.balance_amount>0){
        balLine.style.display='block';
        balLine.textContent = `미수 잔금 ${fmt(data.balance_amount)}원`;
    } else { balLine.style.display='none'; }

    if(!__payments.length){ list.innerHTML='<div class="d-empty">결제 내역이 없습니다. + 결제 추가</div>'; return; }
    list.innerHTML = __payments.map(p=>{
        const isCharge=p.type==='charge', isRefund=p.type==='refund';
        const badge = isCharge ? '<span class="pay-badge charge">결제</span>'
            : isRefund ? '<span class="pay-badge refund">환불</span>'
            : '<span class="pay-badge cancel">결제 취소</span>';
        const amount = isCharge ? `+${fmt(p.amount)}원` : `${fmt(p.amount)}원`;
        const amtColor = isCharge ? 'var(--accent)' : 'var(--red)';
        const refundInfo = isCharge && p.refunded_amount>0 ? `<span style="font-size:11px; color:var(--text-muted);">· 환불 ${fmt(p.refunded_amount)}원</span>` : '';
        const fully = isCharge && p.is_fully_refunded;
        const canRefund = isCharge && !fully && p.amount>0;
        const items = (p.items&&p.items.length) ? `<div style="margin-top:6px; display:flex; flex-direction:column; gap:2px;">${p.items.map(it=>`<div style="display:flex; gap:8px; font-size:11px; color:var(--text-muted);"><span style="flex:1;">${esc(it.name||'-')}</span><span>${it.qty||1}개 × ${fmt(it.price||0)}원</span></div>`).join('')}</div>` : '';
        return `<div style="padding:12px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; ${fully?'opacity:0.6;':''}">
            <div style="display:flex; align-items:center; gap:8px; justify-content:space-between; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    ${badge}<span style="font-size:14px; font-weight:800; color:${amtColor};">${amount}</span>${refundInfo}
                    ${fully?'<span style="font-size:10px; color:var(--text-muted); border:1px solid var(--border); padding:1px 6px; border-radius:6px;">전액 환불</span>':''}
                </div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    ${isCharge?`<button class="mini" onclick="editPay(${p.id})">수정</button>`:''}
                    ${canRefund?`<button class="mini" onclick="openRefund(${p.id},'refund')">환불</button>`:''}
                    ${canRefund?`<button class="mini danger" onclick="openRefund(${p.id},'cancel')">결제 취소</button>`:''}
                    <button class="mini danger" onclick="delPay(${p.id})">삭제</button>
                </div>
            </div>
            <div style="margin-top:6px; font-size:12px; color:var(--text-muted); display:flex; gap:10px; flex-wrap:wrap;">
                <span>📅 ${p.paid_at||p.created_at}</span>${p.method?`<span>· ${esc(p.method)}</span>`:''}
            </div>
            ${items}
            ${p.memo?`<div style="margin-top:6px; font-size:12px; color:var(--text-muted); white-space:pre-wrap;">📝 ${esc(p.memo)}</div>`:''}
        </div>`;
    }).join('');
}

// 결제 추가/수정 모달
function openPayModal(){
    __payEditId=null;
    document.getElementById('payModalTitle').textContent='💰 결제 정보 입력';
    document.getElementById('payAmount').value='';
    document.getElementById('payPaidAt').value='{{ now()->format('Y-m-d') }}';
    document.getElementById('payMethod').value='';
    document.getElementById('payMemo').value='';
    payItems=[]; renderPayItems();
    setBalance(false, '');
    document.getElementById('payBalanceFields').style.display='';
    document.getElementById('payModal').style.display='flex';
}
function editPay(id){
    const p=__payments.find(x=>x.id===id); if(!p) return;
    __payEditId=id;
    document.getElementById('payModalTitle').textContent='💰 결제 수정';
    document.getElementById('payAmount').value=p.amount||'';
    document.getElementById('payPaidAt').value=p.paid_at||'';
    document.getElementById('payMethod').value=p.method||'';
    document.getElementById('payMemo').value=p.memo||'';
    payItems=(p.items||[]).map(it=>({name:it.name||'',qty:it.qty||1,price:it.price||0})); renderPayItems();
    document.getElementById('payBalanceFields').style.display='none'; // 잔금은 신규 등록 시에만
    document.getElementById('payModal').style.display='flex';
}
function closePayModal(){ document.getElementById('payModal').style.display='none'; }

var payItems=[];
function renderPayItems(){
    const wrap=document.getElementById('payItems');
    wrap.innerHTML = payItems.map((it,i)=>`<div style="display:flex; gap:6px; align-items:center;">
        <input placeholder="항목명" value="${esc(it.name||'')}" oninput="payItems[${i}].name=this.value" class="ta" style="flex:1;">
        <input type="number" placeholder="수량" value="${it.qty||1}" oninput="payItems[${i}].qty=parseInt(this.value)||0; syncPayAmount()" class="ta" style="width:70px;">
        <input type="number" placeholder="단가" value="${it.price||0}" oninput="payItems[${i}].price=parseInt(this.value)||0; syncPayAmount()" class="ta" style="width:100px;">
        <button class="mini danger" onclick="payItems.splice(${i},1); renderPayItems(); syncPayAmount()">×</button>
    </div>`).join('');
}
function addPayItem(){ payItems.push({name:'',qty:1,price:0}); renderPayItems(); }
function syncPayAmount(){
    const sum=payItems.reduce((s,it)=>s+((it.qty||0)*(it.price||0)),0);
    if(sum>0) document.getElementById('payAmount').value=sum;
}
function setBalance(on, amt){
    document.querySelector(`input[name=payHasBalance][value="${on?1:0}"]`).checked=true;
    document.getElementById('payBalanceAmount').value=amt;
    document.getElementById('payBalanceWrap').style.display=on?'':'none';
}
function togglePayBalance(){
    const on=document.querySelector('input[name=payHasBalance]:checked').value==='1';
    document.getElementById('payBalanceWrap').style.display=on?'':'none';
}
async function savePay(){
    const items=payItems.filter(it=>it.name&&it.name.trim());
    const hasBalance=document.querySelector('input[name=payHasBalance]:checked')?.value==='1';
    const body={
        amount: parseInt(document.getElementById('payAmount').value,10)||0,
        paid_at: document.getElementById('payPaidAt').value||null,
        method: document.getElementById('payMethod').value||null,
        items, memo: document.getElementById('payMemo').value.trim()||null,
        has_balance: hasBalance,
        balance_amount: hasBalance ? (parseInt(document.getElementById('payBalanceAmount').value,10)||0) : 0,
    };
    const url = __payEditId ? `/api/crm-demo/projects/${PID}/payments/${__payEditId}` : `/api/crm-demo/projects/${PID}/payment`;
    const res = await fetch(url, {method: __payEditId?'PATCH':'POST', headers:H, body:JSON.stringify(body)});
    if(res.ok){ closePayModal(); loadPayments(); } else { const e=await res.json().catch(()=>({})); alert(e.message||e.error||'저장 실패'); }
}
async function delPay(id){ if(!confirm('이 결제 기록을 삭제할까요? (연결된 환불/취소도 함께 삭제)'))return; await fetch(`/api/crm-demo/projects/${PID}/payments/${id}`,{method:'DELETE',headers:H}); loadPayments(); }

// 환불 / 취소
function openRefund(chargeId, type){
    const c=__payments.find(p=>p.id===chargeId); if(!c) return;
    const refundable=c.amount-(c.refunded_amount||0);
    __refundCtx={chargeId, type, refundable};
    document.getElementById('refundTitle').textContent = type==='cancel'?'⚠ 결제 취소':'↩ 환불';
    document.getElementById('refundMeta').innerHTML = `원 결제 <b style="color:var(--accent)">${fmt(c.amount)}원</b> · 환불 가능 <b style="color:var(--red)">${fmt(refundable)}원</b>`;
    const amtWrap=document.getElementById('refundAmountWrap');
    amtWrap.style.display = type==='cancel' ? 'none' : '';   // 취소는 잔여 전액
    document.getElementById('refundAmount').value='';
    document.getElementById('refundAmount').max=refundable;
    document.getElementById('refundReason').value='';
    document.getElementById('refundModal').style.display='flex';
}
function closeRefund(){ document.getElementById('refundModal').style.display='none'; }
async function submitRefund(){
    if(!__refundCtx) return;
    const body={ parent_payment_id:__refundCtx.chargeId, type:__refundCtx.type, reason:document.getElementById('refundReason').value.trim()||null };
    if(__refundCtx.type==='refund'){
        const amt=parseInt(document.getElementById('refundAmount').value,10)||0;
        if(amt<=0) return alert('환불 금액을 입력하세요.');
        body.amount=amt;
    }
    const res=await fetch(`/api/crm-demo/projects/${PID}/payments/refund`,{method:'POST',headers:H,body:JSON.stringify(body)});
    if(res.ok){ closeRefund(); loadPayments(); } else { const e=await res.json().catch(()=>({})); alert(e.error||e.message||'처리 실패'); }
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

loadPayments(); renderConsults(); renderFeedbacks();
</script>
@endpush
