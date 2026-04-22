@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '방송룸 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:20px; font-weight:700; }

    .stat-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; margin-bottom:20px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px 18px; }
    .stat-label { font-size:10px; color:var(--text-muted); letter-spacing:0.15em; text-transform:uppercase; margin-bottom:6px; }
    .stat-value { font-size:20px; font-weight:700; }

    .tabs { display:flex; gap:4px; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:4px; margin-bottom:16px; }
    .tab-btn { flex:1; padding:10px; border:none; background:none; color:var(--text-muted); font-size:13px; font-weight:600; cursor:pointer; border-radius:6px; }
    .tab-btn.active { background:var(--accent); color:#1a1207; }
    [data-theme="light"] .tab-btn.active { color:#fff; }

    .tab-panel { display:none; }
    .tab-panel.active { display:block; }

    .toolbar { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; justify-content:flex-end; }
    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .btn-primary { color:#fff; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 12px; border-radius:6px; font-size:12px; cursor:pointer; }

    .table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    th { padding:11px 14px; font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; background:var(--surface2); border-bottom:1px solid var(--border); }
    td { padding:10px 14px; font-size:13px; border-bottom:1px solid var(--border); }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:var(--surface2); }

    .status-badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .status-active { background:rgba(122,200,122,0.15); color:#7ac87a; border:1px solid rgba(122,200,122,0.35); }
    .status-terminated { background:rgba(160,160,160,0.15); color:var(--text-muted); border:1px solid var(--border); }
    [data-theme="light"] .status-active { background:#e8f5e8; color:#1a7a2a; }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:500px; max-width:95vw; padding:24px; }
    .modal h3 { font-size:16px; margin-bottom:16px; }
    .field-group { margin-bottom:14px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .client-search-wrap { position:relative; }
    .client-search-results { display:none; position:absolute; top:100%; left:0; right:0; background:var(--surface); border:1px solid var(--border); border-radius:0 0 8px 8px; max-height:240px; overflow-y:auto; z-index:10; box-shadow:0 4px 12px rgba(0,0,0,0.2); }
    .client-search-results.open { display:block; }
    .client-search-result { padding:10px 12px; font-size:13px; cursor:pointer; border-bottom:1px solid var(--border); }
    .client-search-result:hover { background:var(--surface2); }

    @media (max-width:768px) {
        .field-row { grid-template-columns:1fr; }
        table { min-width:700px; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">🎙 방송룸</div>
    </div>

    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-label">진행중 월 계약</div>
            <div class="stat-value">{{ number_format($activeContracts) }}건</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">월 계약 매출</div>
            <div class="stat-value" style="color:var(--accent);">{{ number_format($monthlyRevenue) }}원</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">이번달 시간 대여</div>
            <div class="stat-value">{{ number_format($thisMonthUsage) }}회</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">이번달 시간 대여 매출</div>
            <div class="stat-value" style="color:var(--accent);">{{ number_format($thisMonthUsageRevenue) }}원</div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="contracts" onclick="switchTab('contracts')">월 계약</button>
        <button class="tab-btn" data-tab="usages" onclick="switchTab('usages')">시간 대여</button>
    </div>

    {{-- 월 계약 탭 --}}
    <div class="tab-panel active" id="panel-contracts">
        @if(Auth::user()->hasPermission('clients.edit'))
        <div class="toolbar">
            <button class="btn-primary" onclick="openContractModal()">+ 월 계약 등록</button>
        </div>
        @endif
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>의뢰자</th><th>시작일</th><th>종료일</th><th>월 금액</th><th>상태</th><th>메모</th>
                        @if(Auth::user()->hasPermission('clients.edit'))<th></th>@endif
                    </tr>
                </thead>
                <tbody id="contractsBody"><tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    {{-- 시간 대여 탭 --}}
    <div class="tab-panel" id="panel-usages">
        @if(Auth::user()->hasPermission('clients.edit'))
        <div class="toolbar">
            <button class="btn-primary" onclick="openUsageModal()">+ 시간 대여 등록</button>
        </div>
        @endif
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>날짜</th><th>의뢰자</th><th>시간</th><th>금액</th><th>메모</th>
                        @if(Auth::user()->hasPermission('clients.edit'))<th></th>@endif
                    </tr>
                </thead>
                <tbody id="usagesBody"><tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

{{-- 월 계약 모달 --}}
<div class="modal-overlay" id="contractModal" onclick="if(event.target===this)closeContractModal()">
    <div class="modal">
        <h3 id="contractModalTitle">+ 월 계약 등록</h3>
        <input type="hidden" id="contractId">
        <div class="field-group">
            <div class="field-label">의뢰자 *</div>
            <div class="client-search-wrap">
                <input type="text" class="field-input" id="cClientSearch" placeholder="이름/닉네임/전화 검색" oninput="searchClients(this.value, 'c')" autocomplete="off">
                <input type="hidden" id="cClientId">
                <div class="client-search-results" id="cClientResults"></div>
            </div>
            <div id="cSelectedClient" style="margin-top:6px; font-size:12px; color:var(--accent); display:none;"></div>
        </div>
        <div class="field-row">
            <div class="field-group"><div class="field-label">시작일 *</div><input type="date" class="field-input" id="cStartDate"></div>
            <div class="field-group"><div class="field-label">종료일</div><input type="date" class="field-input" id="cEndDate"></div>
        </div>
        <div class="field-row">
            <div class="field-group"><div class="field-label">월 금액 (원) *</div><input type="number" class="field-input" id="cMonthlyFee" min="0" step="10000"></div>
            <div class="field-group"><div class="field-label">상태</div><select class="field-input" id="cStatus"><option value="active">진행중</option><option value="terminated">해지</option></select></div>
        </div>
        <div class="field-group"><div class="field-label">메모</div><textarea class="field-input" id="cMemo" rows="2"></textarea></div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="btn-outline" id="cDeleteBtn" style="margin-right:auto; display:none; border-color:var(--red); color:var(--red);" onclick="deleteContract()">삭제</button>
            <button class="btn-outline" onclick="closeContractModal()">취소</button>
            <button class="btn-primary" onclick="saveContract()">저장</button>
        </div>
    </div>
</div>

{{-- 시간 대여 모달 --}}
<div class="modal-overlay" id="usageModal" onclick="if(event.target===this)closeUsageModal()">
    <div class="modal">
        <h3 id="usageModalTitle">+ 시간 대여 등록</h3>
        <input type="hidden" id="usageId">
        <div class="field-group">
            <div class="field-label">의뢰자 *</div>
            <div class="client-search-wrap">
                <input type="text" class="field-input" id="uClientSearch" placeholder="이름/닉네임/전화 검색" oninput="searchClients(this.value, 'u')" autocomplete="off">
                <input type="hidden" id="uClientId">
                <div class="client-search-results" id="uClientResults"></div>
            </div>
            <div id="uSelectedClient" style="margin-top:6px; font-size:12px; color:var(--accent); display:none;"></div>
        </div>
        <div class="field-row">
            <div class="field-group"><div class="field-label">이용일 *</div><input type="date" class="field-input" id="uUsedDate"></div>
            <div class="field-group"><div class="field-label">시간 (시간) *</div><input type="number" class="field-input" id="uHours" min="0" step="0.5"></div>
        </div>
        <div class="field-group"><div class="field-label">금액 (원) *</div><input type="number" class="field-input" id="uFee" min="0" step="1000"></div>
        <div class="field-group"><div class="field-label">메모</div><textarea class="field-input" id="uMemo" rows="2"></textarea></div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="btn-outline" id="uDeleteBtn" style="margin-right:auto; display:none; border-color:var(--red); color:var(--red);" onclick="deleteUsage()">삭제</button>
            <button class="btn-outline" onclick="closeUsageModal()">취소</button>
            <button class="btn-primary" onclick="saveUsage()">저장</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const canEdit = @json(Auth::user()->hasPermission('clients.edit'));
let allContracts = [], allUsages = [];

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + tab));
}

async function loadContracts() {
    const res = await fetch('/api/broadcast-room/contracts');
    allContracts = await res.json();
    const tbody = document.getElementById('contractsBody');
    if (!allContracts.length) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">계약이 없습니다.</td></tr>'; return; }
    tbody.innerHTML = allContracts.map(c => {
        const statusLabel = c.status === 'active' ? '진행중' : '해지';
        const clientName = c.client_name + (c.client_nickname ? ` (${c.client_nickname})` : '');
        return `<tr>
            <td>${clientName}</td><td>${c.start_date}</td><td>${c.end_date || '—'}</td>
            <td style="font-weight:600;color:var(--accent);">${Number(c.monthly_fee||0).toLocaleString()}원</td>
            <td><span class="status-badge status-${c.status}">${statusLabel}</span></td>
            <td style="color:var(--text-muted);">${(c.memo||'').substring(0,30)}</td>
            ${canEdit ? `<td><button class="btn-outline" onclick='editContract(${c.id})'>편집</button></td>` : ''}
        </tr>`;
    }).join('');
}

async function loadUsages() {
    const res = await fetch('/api/broadcast-room/usages');
    allUsages = await res.json();
    const tbody = document.getElementById('usagesBody');
    if (!allUsages.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">이용 내역이 없습니다.</td></tr>'; return; }
    tbody.innerHTML = allUsages.map(u => {
        const clientName = u.client_name + (u.client_nickname ? ` (${u.client_nickname})` : '');
        return `<tr>
            <td>${u.used_date}</td><td>${clientName}</td>
            <td>${u.hours}시간</td>
            <td style="font-weight:600;color:var(--accent);">${Number(u.fee||0).toLocaleString()}원</td>
            <td style="color:var(--text-muted);">${(u.memo||'').substring(0,30)}</td>
            ${canEdit ? `<td><button class="btn-outline" onclick='editUsage(${u.id})'>편집</button></td>` : ''}
        </tr>`;
    }).join('');
}

// 의뢰자 검색 (공통)
let searchTimer;
async function searchClients(q, prefix) {
    clearTimeout(searchTimer);
    if (q.length < 1) { document.getElementById(prefix + 'ClientResults').classList.remove('open'); return; }
    searchTimer = setTimeout(async () => {
        const res = await fetch('/api/rental-contracts/search-clients?q=' + encodeURIComponent(q));
        const list = await res.json();
        const el = document.getElementById(prefix + 'ClientResults');
        if (!list.length) { el.innerHTML = '<div style="padding:12px; color:var(--text-muted); font-size:12px;">검색 결과 없음</div>'; el.classList.add('open'); return; }
        el.innerHTML = list.map(c => `<div class="client-search-result" onclick='selectClient(${JSON.stringify(c)}, "${prefix}")'>${c.name}${c.nickname ? ' ('+c.nickname+')' : ''} ${c.phone ? '· '+c.phone : ''}</div>`).join('');
        el.classList.add('open');
    }, 200);
}
function selectClient(c, prefix) {
    document.getElementById(prefix + 'ClientId').value = c.id;
    document.getElementById(prefix + 'ClientSearch').value = c.nickname || c.name;
    document.getElementById(prefix + 'SelectedClient').textContent = `✓ ${c.name}${c.nickname ? ' ('+c.nickname+')' : ''}`;
    document.getElementById(prefix + 'SelectedClient').style.display = 'block';
    document.getElementById(prefix + 'ClientResults').classList.remove('open');
}

// ── 월 계약 ──
function openContractModal(data) {
    document.getElementById('contractModal').classList.add('open');
    document.getElementById('contractModalTitle').textContent = data ? '월 계약 편집' : '+ 월 계약 등록';
    document.getElementById('contractId').value = data?.id || '';
    document.getElementById('cClientId').value = data?.client_id || '';
    document.getElementById('cClientSearch').value = data ? (data.client_nickname || data.client_name || '') : '';
    document.getElementById('cSelectedClient').textContent = data ? `✓ ${data.client_name}${data.client_nickname ? ' ('+data.client_nickname+')' : ''}` : '';
    document.getElementById('cSelectedClient').style.display = data ? 'block' : 'none';
    document.getElementById('cStartDate').value = data?.start_date || new Date().toISOString().slice(0,10);
    document.getElementById('cEndDate').value = data?.end_date || '';
    document.getElementById('cMonthlyFee').value = data?.monthly_fee || '';
    document.getElementById('cStatus').value = data?.status || 'active';
    document.getElementById('cMemo').value = data?.memo || '';
    document.getElementById('cDeleteBtn').style.display = data ? 'inline-block' : 'none';
}
function closeContractModal() { document.getElementById('contractModal').classList.remove('open'); }
function editContract(id) { const c = allContracts.find(x => x.id === id); if (c) openContractModal(c); }
async function saveContract() {
    const id = document.getElementById('contractId').value;
    const clientId = document.getElementById('cClientId').value;
    if (!clientId) return alert('의뢰자를 선택하세요.');
    const body = {
        client_id: parseInt(clientId),
        start_date: document.getElementById('cStartDate').value,
        end_date: document.getElementById('cEndDate').value || null,
        monthly_fee: parseInt(document.getElementById('cMonthlyFee').value || 0),
        status: document.getElementById('cStatus').value,
        memo: document.getElementById('cMemo').value || null,
    };
    const url = id ? `/api/broadcast-room/contracts/${id}` : '/api/broadcast-room/contracts';
    const res = await fetch(url, { method: id?'PATCH':'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(body) });
    if (res.ok) location.reload(); else alert('저장 실패');
}
async function deleteContract() {
    if (!confirm('삭제하시겠습니까?')) return;
    const id = document.getElementById('contractId').value;
    const res = await fetch(`/api/broadcast-room/contracts/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF} });
    if (res.ok) location.reload();
}

// ── 시간 대여 ──
function openUsageModal(data) {
    document.getElementById('usageModal').classList.add('open');
    document.getElementById('usageModalTitle').textContent = data ? '시간 대여 편집' : '+ 시간 대여 등록';
    document.getElementById('usageId').value = data?.id || '';
    document.getElementById('uClientId').value = data?.client_id || '';
    document.getElementById('uClientSearch').value = data ? (data.client_nickname || data.client_name || '') : '';
    document.getElementById('uSelectedClient').textContent = data ? `✓ ${data.client_name}${data.client_nickname ? ' ('+data.client_nickname+')' : ''}` : '';
    document.getElementById('uSelectedClient').style.display = data ? 'block' : 'none';
    document.getElementById('uUsedDate').value = data?.used_date || new Date().toISOString().slice(0,10);
    document.getElementById('uHours').value = data?.hours || '';
    document.getElementById('uFee').value = data?.fee || '';
    document.getElementById('uMemo').value = data?.memo || '';
    document.getElementById('uDeleteBtn').style.display = data ? 'inline-block' : 'none';
}
function closeUsageModal() { document.getElementById('usageModal').classList.remove('open'); }
function editUsage(id) { const u = allUsages.find(x => x.id === id); if (u) openUsageModal(u); }
async function saveUsage() {
    const id = document.getElementById('usageId').value;
    const clientId = document.getElementById('uClientId').value;
    if (!clientId) return alert('의뢰자를 선택하세요.');
    const body = {
        client_id: parseInt(clientId),
        used_date: document.getElementById('uUsedDate').value,
        hours: parseFloat(document.getElementById('uHours').value || 0),
        fee: parseInt(document.getElementById('uFee').value || 0),
        memo: document.getElementById('uMemo').value || null,
    };
    const url = id ? `/api/broadcast-room/usages/${id}` : '/api/broadcast-room/usages';
    const res = await fetch(url, { method: id?'PATCH':'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(body) });
    if (res.ok) location.reload(); else alert('저장 실패');
}
async function deleteUsage() {
    if (!confirm('삭제하시겠습니까?')) return;
    const id = document.getElementById('usageId').value;
    const res = await fetch(`/api/broadcast-room/usages/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF} });
    if (res.ok) location.reload();
}

loadContracts();
loadUsages();
</script>
@endpush
