@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '렌탈 계약 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:20px; font-weight:700; }

    .stat-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px; margin-bottom:20px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px 18px; }
    .stat-label { font-size:10px; color:var(--text-muted); letter-spacing:0.15em; text-transform:uppercase; margin-bottom:6px; }
    .stat-value { font-size:22px; font-weight:700; }

    .toolbar { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
    .toolbar input, .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; }
    .toolbar input[type=text] { width:240px; }
    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .btn-primary { color:#fff; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 12px; border-radius:6px; font-size:12px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }

    .table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    th { padding:11px 14px; font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; background:var(--surface2); border-bottom:1px solid var(--border); }
    td { padding:10px 14px; font-size:13px; border-bottom:1px solid var(--border); }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:var(--surface2); }

    .status-badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .status-active { background:rgba(122,200,122,0.15); color:#7ac87a; border:1px solid rgba(122,200,122,0.35); }
    .status-terminated { background:rgba(160,160,160,0.15); color:var(--text-muted); border:1px solid var(--border); }
    [data-theme="light"] .status-active { background:#e8f5e8; color:#1a7a2a; border-color:#a0d8a0; }

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
        <div class="page-title"><x-icon name="home" :size="18"/> 렌탈 계약</div>
        @if(Auth::user()->hasPermission('clients.edit'))
            <button class="btn-primary" onclick="openContractModal()">+ 계약 등록</button>
        @endif
    </div>

    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-label">진행중 계약</div>
            <div class="stat-value">{{ number_format($activeCount) }}건</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">월 매출 (진행중)</div>
            <div class="stat-value" style="color:var(--accent);">{{ number_format($monthlyRevenue) }}원</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">전체 계약</div>
            <div class="stat-value">{{ number_format($contracts->total()) }}건</div>
        </div>
    </div>

    <div class="toolbar">
        <input type="text" id="searchInput" placeholder="의뢰자명/닉네임 검색" onkeyup="if(event.key==='Enter')applyFilter()">
        <select id="statusFilter" onchange="applyFilter()">
            <option value="">전체 상태</option>
            <option value="active">진행중</option>
            <option value="terminated">해지</option>
        </select>
        <button class="btn-outline" onclick="applyFilter()">검색</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>의뢰자</th>
                    <th>시작일</th>
                    <th>종료일</th>
                    <th>월 금액</th>
                    <th>상태</th>
                    <th>메모</th>
                    @if(Auth::user()->hasPermission('clients.edit'))<th></th>@endif
                </tr>
            </thead>
            <tbody id="contractsBody">
                <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">로딩 중...</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- 계약 등록/편집 모달 --}}
<div class="modal-overlay" id="contractModal" onclick="if(event.target===this) drgoModalMinimize(this, '렌탈 계약', '🏠')">
    <div class="modal">
        <h3 id="modalTitle">+ 계약 등록</h3>
        <input type="hidden" id="contractId">
        <div class="field-group">
            <div class="field-label">의뢰자 *</div>
            <div class="client-search-wrap">
                <input type="text" class="field-input" id="clientSearch" placeholder="이름/닉네임/전화번호 검색" oninput="searchClients(this.value)" autocomplete="off">
                <input type="hidden" id="clientId">
                <div class="client-search-results" id="clientResults"></div>
            </div>
            <div id="selectedClient" style="margin-top:6px; font-size:12px; color:var(--accent); display:none;"></div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">시작일 *</div>
                <input type="date" class="field-input" id="startDate">
            </div>
            <div class="field-group">
                <div class="field-label">종료일 (빈칸 = 진행중)</div>
                <input type="date" class="field-input" id="endDate">
            </div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">월 금액 (원) *</div>
                <input type="number" class="field-input" id="monthlyFee" min="0" step="10000">
            </div>
            <div class="field-group">
                <div class="field-label">상태</div>
                <select class="field-input" id="status">
                    <option value="active">진행중</option>
                    <option value="terminated">해지</option>
                </select>
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <textarea class="field-input" id="memo" rows="2"></textarea>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="btn-outline" id="deleteBtn" style="margin-right:auto; display:none; border-color:var(--red); color:var(--red);" onclick="deleteContract()">삭제</button>
            <button class="btn-outline" onclick="closeContractModal()">취소</button>
            <button class="btn-primary" onclick="saveContract()">저장</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const canEdit = @json(Auth::user()->hasPermission('clients.edit'));
let allContracts = [];

async function loadContracts() {
    const res = await fetch('/api/rental-contracts');
    allContracts = await res.json();
    renderContracts();
}

function renderContracts() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const filtered = allContracts.filter(c => {
        if (status && c.status !== status) return false;
        if (search) {
            const name = (c.client_name || '').toLowerCase() + (c.client_nickname || '').toLowerCase();
            if (!name.includes(search)) return false;
        }
        return true;
    });

    const tbody = document.getElementById('contractsBody');
    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">계약이 없습니다.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(c => {
        const statusLabel = c.status === 'active' ? '진행중' : '해지';
        const statusClass = 'status-' + c.status;
        const clientName = c.client_name + (c.client_nickname ? ` (${c.client_nickname})` : '');
        const endDate = c.end_date || '—';
        const fee = c.monthly_fee ? Number(c.monthly_fee).toLocaleString() + '원' : '0원';
        const memo = c.memo ? c.memo.substring(0, 30) + (c.memo.length > 30 ? '...' : '') : '';
        return `<tr>
            <td>${clientName}</td>
            <td>${c.start_date}</td>
            <td>${endDate}</td>
            <td style="font-weight:600;color:var(--accent);">${fee}</td>
            <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
            <td style="color:var(--text-muted);">${memo}</td>
            ${canEdit ? `<td><button class="btn-outline" onclick='editContract(${c.id})'>편집</button></td>` : ''}
        </tr>`;
    }).join('');
}

function applyFilter() { renderContracts(); }

function openContractModal(data) {
    document.getElementById('contractModal').classList.add('open');
    document.getElementById('modalTitle').textContent = data ? '계약 편집' : '+ 계약 등록';
    document.getElementById('contractId').value = data?.id || '';
    document.getElementById('clientId').value = data?.client_id || '';
    document.getElementById('clientSearch').value = data ? (data.client_nickname || data.client_name || '') : '';
    document.getElementById('selectedClient').textContent = data ? `✓ ${data.client_name}${data.client_nickname ? ' ('+data.client_nickname+')' : ''}` : '';
    document.getElementById('selectedClient').style.display = data ? 'block' : 'none';
    document.getElementById('startDate').value = data?.start_date || new Date().toISOString().slice(0,10);
    document.getElementById('endDate').value = data?.end_date || '';
    document.getElementById('monthlyFee').value = data?.monthly_fee || '';
    document.getElementById('status').value = data?.status || 'active';
    document.getElementById('memo').value = data?.memo || '';
    document.getElementById('deleteBtn').style.display = data ? 'inline-block' : 'none';
}

function closeContractModal() {
    document.getElementById('contractModal').classList.remove('open');
    document.getElementById('clientResults').classList.remove('open');
}

function editContract(id) {
    const c = allContracts.find(x => x.id === id);
    if (c) openContractModal(c);
}

let searchTimer;
async function searchClients(q) {
    clearTimeout(searchTimer);
    if (q.length < 1) { document.getElementById('clientResults').classList.remove('open'); return; }
    searchTimer = setTimeout(async () => {
        const res = await fetch('/api/rental-contracts/search-clients?q=' + encodeURIComponent(q));
        const list = await res.json();
        const el = document.getElementById('clientResults');
        if (!list.length) { el.innerHTML = '<div style="padding:12px; color:var(--text-muted); font-size:12px;">검색 결과 없음</div>'; el.classList.add('open'); return; }
        el.innerHTML = list.map(c => `<div class="client-search-result" onclick='selectClient(${JSON.stringify(c)})'>${c.name}${c.nickname ? ' ('+c.nickname+')' : ''} ${c.phone ? '· '+c.phone : ''}</div>`).join('');
        el.classList.add('open');
    }, 200);
}

function selectClient(c) {
    document.getElementById('clientId').value = c.id;
    document.getElementById('clientSearch').value = c.nickname || c.name;
    document.getElementById('selectedClient').textContent = `✓ ${c.name}${c.nickname ? ' ('+c.nickname+')' : ''}`;
    document.getElementById('selectedClient').style.display = 'block';
    document.getElementById('clientResults').classList.remove('open');
}

async function saveContract() {
    const id = document.getElementById('contractId').value;
    const clientId = document.getElementById('clientId').value;
    if (!clientId) return alert('의뢰자를 선택하세요.');
    if (!document.getElementById('startDate').value) return alert('시작일을 입력하세요.');

    const body = {
        client_id: parseInt(clientId),
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value || null,
        monthly_fee: parseInt(document.getElementById('monthlyFee').value || 0),
        status: document.getElementById('status').value,
        memo: document.getElementById('memo').value || null,
    };

    const url = id ? `/api/rental-contracts/${id}` : '/api/rental-contracts';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {
        method, headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });

    if (res.ok) {
        closeContractModal();
        location.reload();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors || {}).flat().join(', ')));
    }
}

async function deleteContract() {
    if (!confirm('이 계약을 삭제하시겠습니까?')) return;
    const id = document.getElementById('contractId').value;
    const res = await fetch(`/api/rental-contracts/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF} });
    if (res.ok) location.reload();
}

loadContracts();
</script>
@endpush
