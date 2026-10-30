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

    /* 장비 렌탈 / 서버 렌탈 탭 — 프로젝트 목록의 전체/진행/완료/취소 탭과 동일한 알약(세그먼트) 형태, 상단 가운데 */
    .proj-status-tabs { position:relative; display:flex; gap:4px; background:var(--surface2); border:1px solid var(--border); border-radius:999px; padding:5px; margin-bottom:14px; overflow-x:auto; }
    .pst-tab { flex:1; position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 16px; font-size:13px; font-weight:600; color:var(--text-muted); text-decoration:none; border-radius:999px; white-space:nowrap; transition:color 0.15s; }
    /* hover — 선택 알약은 그대로 두고, 더 옅은 톤의 알약이 살짝 비친다 */
    .pst-tab:hover:not(.active) { color:var(--text); background:rgba(255,255,255,0.07); transition:color 0.15s, background 0.15s; }
    [data-theme="light"] .pst-tab:hover:not(.active) { background:rgba(255,255,255,0.65); }
    .pst-tab.active { color:var(--text); font-weight:800; }
    /* 슬라이딩 알약 — JS가 active 탭 위치로 움직인다. JS 전에는 active 탭 자체 배경으로 폴백 */
    .pst-pill { position:absolute; top:5px; bottom:5px; left:0; width:0; border-radius:999px; background:var(--surface); box-shadow:0 2px 8px rgba(0,0,0,0.22); transition:left 0.28s cubic-bezier(.4,0,.2,1), width 0.28s cubic-bezier(.4,0,.2,1); z-index:0; }
    [data-theme="light"] .pst-pill { background:#fff; box-shadow:0 2px 8px rgba(29,45,61,0.14); }
    .proj-status-tabs:not(.pst-js) .pst-tab.active { background:var(--surface); box-shadow:0 2px 8px rgba(0,0,0,0.22); }
    [data-theme="light"] .proj-status-tabs:not(.pst-js) .pst-tab.active { background:#fff; box-shadow:0 2px 8px rgba(29,45,61,0.14); }
    .pst-count { font-size:11px; font-weight:700; padding:1px 7px; border-radius:9px; background:rgba(200,80,80,0.12); color:var(--red); }
    .pst-count-active { background:rgba(45,138,62,0.12); color:#2d8a3e; }
    .pst-count-done { background:rgba(74,144,217,0.14); color:#4a90d9; }
    [data-theme="light"] .pst-count-done { background:#e0f0ff; color:#2e6a9a; }
    /* 상단 가운데 — 내용 폭만큼만 차지하고 중앙 정렬 */
    .rental-kind-tabs { width:max-content; max-width:100%; margin:0 auto 18px; }
    .rental-kind-tabs .pst-tab { min-width:150px; }

    .server-only { display:none; }
    .kind-server .server-only { display:block; }
    .kind-server .field-row.server-only { display:grid; }
    .mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; }

    @media (max-width:768px) {
        .field-row { grid-template-columns:1fr; }
        table { min-width:700px; }
        .kind-server table { min-width:960px; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap" id="pageWrap">
    {{-- 탭: 장비 렌탈(기존) / 서버 렌탈(서버 대여 현황) — 상단 가운데 알약 탭, URL ?kind= 로 복원 --}}
    <div class="proj-status-tabs rental-kind-tabs" id="kindTabs" role="tablist">
        <a href="?kind=equipment" class="pst-tab" role="tab" data-kind="equipment" onclick="event.preventDefault(); switchKind('equipment')">장비 렌탈<span class="pst-count pst-count-done" id="cntEquipment">–</span></a>
        <a href="?kind=server" class="pst-tab" role="tab" data-kind="server" onclick="event.preventDefault(); switchKind('server')">서버 렌탈<span class="pst-count pst-count-active" id="cntServer">–</span></a>
    </div>

    <div class="page-header">
        <div class="page-title"><x-icon name="home" :size="18"/> <span id="pageTitleText">렌탈 계약</span></div>
        @if(Auth::user()->hasPermission('clients.edit'))
            <button class="btn-primary" id="addBtn" onclick="openContractModal()">+ 계약 등록</button>
        @endif
    </div>

    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-label">진행중 계약</div>
            <div class="stat-value" id="statActive">–</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">월 매출 (진행중)</div>
            <div class="stat-value" style="color:var(--accent);" id="statRevenue">–</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">전체 계약</div>
            <div class="stat-value" id="statTotal">–</div>
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
                <tr id="contractsHead"></tr>
            </thead>
            <tbody id="contractsBody">
                <tr><td colspan="10" style="text-align:center; padding:40px; color:var(--text-muted);">로딩 중...</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- 계약 등록/편집 모달 --}}
<div class="modal-overlay" id="contractModal" onclick="if(event.target===this) drgoModalMinimize(this, KIND==='server' ? '서버 렌탈 계약' : '렌탈 계약', KIND==='server' ? '🖥️' : '🏠')">
    <div class="modal">
        <h3 id="modalTitle">+ 계약 등록</h3>
        <input type="hidden" id="contractId">
        <input type="hidden" id="contractKind" value="equipment">
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
        {{-- 서버 렌탈 전용 --}}
        <div class="field-row server-only">
            <div class="field-group">
                <div class="field-label">서버명</div>
                <input type="text" class="field-input" id="serverName" placeholder="예: 스트리밍 서버 A" maxlength="100">
            </div>
            <div class="field-group">
                <div class="field-label">접속 정보 (IP·주소)</div>
                <input type="text" class="field-input mono" id="serverAccess" placeholder="예: 123.45.67.89:3389" maxlength="200" autocomplete="off">
            </div>
        </div>
        <div class="field-group server-only">
            <div class="field-label">사양</div>
            <input type="text" class="field-input" id="serverSpec" placeholder="예: Ryzen 9 7950X · 64GB · RTX 4090 · 2TB NVMe" maxlength="200">
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
const KIND_LABEL = { equipment:'장비 렌탈', server:'서버 렌탈' };
let KIND = @json($kind); // 현재 탭 (장비/서버)
let allContracts = []; // 전체 종류 — 탭 전환은 요청 없이 화면에서 거른다
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function loadContracts() {
    // 종류별로 따로 받아 합친다 — 목록 상한(종류당 500건)이 한쪽에 쏠려 다른 탭이 비어 보이지 않게
    const lists = await Promise.all(Object.keys(KIND_LABEL).map(k =>
        fetch('/api/rental-contracts?kind=' + k, {headers:{'Accept':'application/json'}}).then(r => r.ok ? r.json() : [])
    ));
    allContracts = lists.flat();
    renderContracts();
}

function switchKind(kind) {
    if (!KIND_LABEL[kind]) return;
    KIND = kind;
    // URL 에 기록 (새로고침·탭 복원), 다른 파라미터(_tab 등)는 유지
    const url = new URL(location.href); url.searchParams.set('kind', kind); history.replaceState(null, '', url);
    renderContracts();
}

// ── 종류 탭 — 슬라이딩 알약 (프로젝트 상태 탭과 동일) ──
const kindBar = document.getElementById('kindTabs');
const kindPill = document.createElement('span');
kindPill.className = 'pst-pill';
kindBar.classList.add('pst-js');
kindBar.prepend(kindPill);
function moveKindPill(instant) {
    const el = kindBar.querySelector('.pst-tab.active');
    if (!el) return;
    if (instant) kindPill.style.transition = 'none';
    kindPill.style.left = el.offsetLeft + 'px';
    kindPill.style.width = el.offsetWidth + 'px';
    if (instant) requestAnimationFrame(() => { kindPill.style.transition = ''; });
}
window.addEventListener('resize', () => moveKindPill(true));
let kindPillPlaced = false;

function renderContracts() {
    document.getElementById('pageWrap').classList.toggle('kind-server', KIND === 'server');
    kindBar.querySelectorAll('.pst-tab').forEach(b => b.classList.toggle('active', b.dataset.kind === KIND));
    moveKindPill(!kindPillPlaced); kindPillPlaced = true; // 첫 배치는 즉시, 이후 전환은 슬라이드
    const titleEl = document.getElementById('pageTitleText');
    if (titleEl) titleEl.textContent = KIND === 'server' ? '서버 렌탈 계약' : '렌탈 계약';
    document.getElementById('cntEquipment').textContent = allContracts.filter(c => (c.kind || 'equipment') === 'equipment').length;
    document.getElementById('cntServer').textContent = allContracts.filter(c => c.kind === 'server').length;
    const addBtn = document.getElementById('addBtn');
    if (addBtn) addBtn.textContent = KIND === 'server' ? '+ 서버 렌탈 등록' : '+ 계약 등록';

    const ofKind = allContracts.filter(c => (c.kind || 'equipment') === KIND);
    const active = ofKind.filter(c => c.status === 'active');
    document.getElementById('statActive').textContent = active.length.toLocaleString() + '건';
    document.getElementById('statRevenue').textContent = active.reduce((s, c) => s + (Number(c.monthly_fee) || 0), 0).toLocaleString() + '원';
    document.getElementById('statTotal').textContent = ofKind.length.toLocaleString() + '건';

    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const filtered = ofKind.filter(c => {
        if (status && c.status !== status) return false;
        if (search) {
            const hay = [c.client_name, c.client_nickname, c.server_name, c.server_spec, c.server_access].map(v => (v || '').toLowerCase()).join(' ');
            if (!hay.includes(search)) return false;
        }
        return true;
    });

    const isServer = KIND === 'server';
    const heads = isServer
        ? ['의뢰자', '서버명', '사양', '접속 정보', '시작일', '종료일', '월 금액', '상태', '메모']
        : ['의뢰자', '시작일', '종료일', '월 금액', '상태', '메모'];
    if (canEdit) heads.push('');
    document.getElementById('contractsHead').innerHTML = heads.map(h => `<th>${h}</th>`).join('');

    const tbody = document.getElementById('contractsBody');
    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="${heads.length}" style="text-align:center; padding:40px; color:var(--text-muted);">${isServer ? '서버 렌탈 계약이 없습니다.' : '계약이 없습니다.'}</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(c => {
        const statusLabel = c.status === 'active' ? '진행중' : '해지';
        const statusClass = 'status-' + c.status;
        const clientName = esc((c.client_name || c.client_nickname || '-') + (c.client_name && c.client_nickname ? ` (${c.client_nickname})` : ''));
        const endDate = c.end_date || '—';
        const fee = c.monthly_fee ? Number(c.monthly_fee).toLocaleString() + '원' : '0원';
        const memo = c.memo ? esc(c.memo.substring(0, 30) + (c.memo.length > 30 ? '...' : '')) : '';
        const calBadge = c.calendar_synced
            ? `<a href="/calendar" title="캘린더에 연동됨 — 클릭 시 캘린더로 이동" style="text-decoration:none;margin-left:6px;cursor:pointer;" onclick="event.preventDefault();if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('calendar','/calendar');}else{location.href='/calendar';}">📅</a>`
            : '';
        const serverCols = isServer
            ? `<td style="font-weight:600;">${esc(c.server_name) || '<span style="color:var(--text-muted);">—</span>'}</td>
            <td style="color:var(--text-muted);">${esc(c.server_spec) || '—'}</td>
            <td class="mono">${esc(c.server_access) || '—'}</td>`
            : '';
        return `<tr>
            <td>${clientName}${calBadge}</td>
            ${serverCols}
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
    const kind = data?.kind || KIND;
    document.getElementById('contractModal').classList.add('open');
    document.getElementById('contractModal').classList.toggle('kind-server', kind === 'server');
    document.getElementById('modalTitle').textContent = (data ? '계약 편집' : '+ 계약 등록') + (kind === 'server' ? ' · 서버 렌탈' : '');
    document.getElementById('contractId').value = data?.id || '';
    document.getElementById('contractKind').value = kind;
    document.getElementById('serverName').value = data?.server_name || '';
    document.getElementById('serverSpec').value = data?.server_spec || '';
    document.getElementById('serverAccess').value = data?.server_access || '';
    document.getElementById('clientId').value = data?.client_id || '';
    document.getElementById('clientSearch').value = data ? (data.client_nickname || data.client_name || '') : '';
    document.getElementById('selectedClient').textContent = data ? `✓ ${data.client_name || data.client_nickname || ''}${data.client_name && data.client_nickname ? ' ('+data.client_nickname+')' : ''}` : '';
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
        el.innerHTML = list.map(c => `<div class="client-search-result" onclick='selectClient(${JSON.stringify(c)})'>${c.name || c.nickname || ''}${c.name && c.nickname ? ' ('+c.nickname+')' : ''} ${c.phone ? '· '+c.phone : ''}</div>`).join('');
        el.classList.add('open');
    }, 200);
}

function selectClient(c) {
    document.getElementById('clientId').value = c.id;
    document.getElementById('clientSearch').value = c.nickname || c.name;
    document.getElementById('selectedClient').textContent = `✓ ${c.name || c.nickname || ''}${c.name && c.nickname ? ' ('+c.nickname+')' : ''}`;
    document.getElementById('selectedClient').style.display = 'block';
    document.getElementById('clientResults').classList.remove('open');
}

async function saveContract() {
    const id = document.getElementById('contractId').value;
    const clientId = document.getElementById('clientId').value;
    if (!clientId) return alert('의뢰자를 선택하세요.');
    if (!document.getElementById('startDate').value) return alert('시작일을 입력하세요.');

    const kind = document.getElementById('contractKind').value || 'equipment';
    const body = {
        client_id: parseInt(clientId),
        kind,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value || null,
        monthly_fee: parseInt(document.getElementById('monthlyFee').value || 0),
        status: document.getElementById('status').value,
        memo: document.getElementById('memo').value || null,
        server_name: kind === 'server' ? (document.getElementById('serverName').value.trim() || null) : null,
        server_spec: kind === 'server' ? (document.getElementById('serverSpec').value.trim() || null) : null,
        server_access: kind === 'server' ? (document.getElementById('serverAccess').value.trim() || null) : null,
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
