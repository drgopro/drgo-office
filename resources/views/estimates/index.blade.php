@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '견적서 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }
    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:260px; }
    .toolbar input:focus { border-color:var(--accent); }
    .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .empty-row { text-align:center; padding:40px !important; color:var(--text-muted); font-size:13px; }
    .text-muted { color:var(--text-muted); font-size:12px; }
    .text-right { text-align:right; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-created { background:#2a2010; color:var(--accent); }
    .badge-editing { background:#1a1a2a; color:#8ab4c8; }
    .badge-completed { background:#1a2a1a; color:#7ac87a; }
    .badge-issued { background:#241a2e; color:#b08ad4; }
    .badge-paid { background:#1a2a2a; color:#4ecdc4; }
    .badge-cancelled { background:#242424; color:#909090; text-decoration:line-through; }
    .badge-hold { background:#2a1a1a; color:#c87a7a; }

    .action-cell { display:flex; gap:5px; align-items:center; }
    .btn-act { padding:5px 11px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; border:none; transition:opacity 0.15s; }
    .btn-act:hover { opacity:0.85; }
    .btn-act-edit { background:var(--surface2); border:1px solid var(--border); color:var(--text); }
    .btn-act-edit:hover { border-color:var(--accent); color:var(--accent); }
    .btn-act-print { background:var(--blue); color:#fff; }
    .btn-act-delete { background:var(--red); color:#fff; }
    .print-dropdown { position:relative; display:inline-block; }
    .print-dropdown-menu { display:none; position:absolute; right:0; top:calc(100% + 4px); background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px; z-index:20; min-width:130px; box-shadow:0 4px 16px rgba(0,0,0,0.4); }
    .print-dropdown-menu.show { display:block; }
    .print-dropdown-menu button { display:block; width:100%; text-align:left; background:none; border:none; color:var(--text); padding:8px 12px; font-size:12px; cursor:pointer; border-radius:4px; white-space:nowrap; }
    .print-dropdown-menu button:hover { background:var(--surface2); color:var(--accent); }
    [data-theme="light"] .btn-primary { color:#fff; }
    [data-theme="light"] .badge-created   { background:#f0ebe2; color:#8a6d30; }
    [data-theme="light"] .badge-editing   { background:#e0f0ff; color:#2e6a9a; }
    [data-theme="light"] .badge-completed { background:#e8f5e8; color:#248a38; }
    [data-theme="light"] .badge-issued    { background:#f0e8fa; color:#7a38b8; }
    [data-theme="light"] .badge-paid      { background:#e0f8f5; color:#0a8a70; }
    [data-theme="light"] .badge-cancelled { background:#ececec; color:#808080; }
    [data-theme="light"] .badge-hold      { background:#ffe8e8; color:#c03838; }
    @media (max-width: 768px) {
        .page-wrap { padding:16px; }
        .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
        .data-table { min-width:600px; }
        .data-table th, .data-table td { padding:10px; font-size:12px; white-space:nowrap; }
        .toolbar { flex-direction:column; align-items:stretch; }
        .toolbar input[type="text"] { width:100%; }
    }
    /* 탭 (견적서 목록 | 프리셋) */
    .est-tabs { display:flex; gap:6px; margin-bottom:16px; border-bottom:1px solid var(--border); }
    .est-tab { background:none; border:none; border-bottom:2px solid transparent; padding:9px 14px; font-size:13.5px; font-weight:600; color:var(--text-muted); cursor:pointer; margin-bottom:-1px; }
    .est-tab:hover { color:var(--text); }
    .est-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
    /* 프리셋 모달 입력 */
    .pm-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .pm-input:focus { border-color:var(--accent); }
    .pm-result { display:flex; justify-content:space-between; align-items:center; padding:7px 10px; border:1px solid var(--border); border-radius:8px; margin-bottom:4px; cursor:pointer; font-size:12.5px; }
    .pm-result:hover { border-color:var(--accent); }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">견적서</div>
        <button class="btn-primary" id="headerActionBtn" onclick="createEstimate()">+ 견적서 생성</button>
    </div>

    <div class="est-tabs">
        <button class="est-tab active" id="tabBtnList" onclick="setEstTab('list')">견적서 목록</button>
        <button class="est-tab" id="tabBtnPresets" onclick="setEstTab('presets')">프리셋</button>
    </div>

    <div id="tabPresets" style="display:none;">
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>프리셋 제목</th><th>품목 수</th><th class="text-right">합계 금액</th><th>작성자</th><th>최근 수정</th><th></th></tr></thead>
                <tbody id="presetBody"><tr><td colspan="6" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div id="tabList">
    <div class="toolbar">
        <input type="text" id="estSearch" placeholder="의뢰자명/번호 검색" oninput="loadEstimates()">
        <select id="estStatus" onchange="loadEstimates()">
            <option value="">전체 상태</option>
            <option value="created">생성</option>
            <option value="editing">수정 중</option>
            <option value="completed">작성 완료</option>
            <option value="issued">발행 완료</option>
            <option value="paid">결제 완료</option>
            <option value="cancelled">결제 취소</option>
            <option value="hold">보류 중</option>
        </select>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>번호</th>
                    <th>의뢰자</th>
                    <th class="text-right">견적금액</th>
                    <th>항목수</th>
                    <th>상태</th>
                    <th>작성자</th>
                    <th>작성일</th>
                    <th>최근 수정</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="estBody"><tr><td colspan="9" class="empty-row">로딩 중...</td></tr></tbody>
        </table>
    </div>
    </div>{{-- /tabList --}}
</div>

{{-- 프리셋 생성/수정 모달 --}}
<div id="presetModalOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:300; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) closePresetModal()">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:min(680px, 100%); max-height:85vh; display:flex; flex-direction:column;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border);">
            <div style="font-size:15px; font-weight:700;" id="presetModalTitle">프리셋 만들기</div>
            <button onclick="closePresetModal()" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer;">×</button>
        </div>
        <div style="padding:14px 18px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:12px;">
            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">프리셋 제목 *</div>
                <input class="pm-input" id="pmTitle" placeholder="예: 스튜디오 기본 세팅">
            </div>
            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">제품 검색으로 추가 <span style="opacity:0.8;">— 검색 후 Enter, 결과 클릭 시 담김</span></div>
                <input class="pm-input" id="pmSearch" placeholder="제품명/SKU/검색태그 후 Enter" onkeydown="if(event.key==='Enter')pmSearchProducts()">
                <div id="pmSearchResults" style="max-height:160px; overflow-y:auto; margin-top:6px;"></div>
            </div>
            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">수기 품목 추가 (일회성 — 제품 관리 미등록)</div>
                <div style="display:flex; gap:6px;">
                    <input class="pm-input" id="pmMiCat" placeholder="카테고리" style="flex:1;">
                    <input class="pm-input" id="pmMiName" placeholder="제품명 *" style="flex:2;" onkeydown="if(event.key==='Enter')pmAddManual()">
                    <input class="pm-input" id="pmMiPrice" type="number" min="0" placeholder="판매가" style="flex:1;">
                    <button class="btn-act btn-act-edit" onclick="pmAddManual()" style="white-space:nowrap;">+ 추가</button>
                </div>
            </div>
            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">담긴 품목 <span id="pmCount"></span></div>
                <table class="data-table" style="font-size:12.5px;">
                    <thead><tr><th>분류</th><th>제품명</th><th class="text-right">판매가</th><th style="width:70px;">수량</th><th class="text-right">합계</th><th style="width:34px;"></th></tr></thead>
                    <tbody id="pmItems"><tr><td colspan="6" class="empty-row" style="padding:16px;">아직 담긴 품목이 없습니다.</td></tr></tbody>
                </table>
            </div>
        </div>
        <div style="padding:12px 18px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <span id="pmTotal" style="font-weight:700;"></span>
            <div style="display:flex; gap:8px;">
                <button class="btn-act" onclick="closePresetModal()" style="border:1px solid var(--border); color:var(--text-muted);">취소</button>
                <button class="btn-primary" onclick="savePreset()" style="padding:8px 18px;">저장</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const stMap = {created:'생성', editing:'수정 중', completed:'작성 완료', issued:'발행 완료', paid:'결제 완료', cancelled:'결제 취소', hold:'보류 중'};

function fmt(n) { return n != null ? Number(n).toLocaleString() : '-'; }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('ko-KR') : '-'; }
function fmtTime(d) { return d ? new Date(d).toLocaleString('ko-KR',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '-'; }

async function loadEstimates() {
    const search = document.getElementById('estSearch').value;
    const status = document.getElementById('estStatus').value;
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const res = await fetch('/api/estimates?' + params);
    const data = await res.json();
    const tb = document.getElementById('estBody');

    if (!data.length) {
        tb.innerHTML = '<tr><td colspan="9" class="empty-row">견적서가 없습니다.</td></tr>';
        return;
    }

    tb.innerHTML = data.map(e => {
        const itemCount = (e.product_items||[]).length + (e.service_items||[]).length;
        return `<tr>
            <td class="text-muted">#${e.id}</td>
            <td>${e.client_nickname && e.client_name ? e.client_nickname+' / '+e.client_name : (e.client_nickname || e.client_name || '-')}</td>
            <td class="text-right" style="font-weight:600;">${fmt(e.total_amount)}원</td>
            <td class="text-muted">${itemCount}건</td>
            <td><span class="badge badge-${e.status}">${stMap[e.status]}</span></td>
            <td class="text-muted">${e.creator?.display_name || '-'}</td>
            <td class="text-muted">${fmtDate(e.created_at)}</td>
            <td class="text-muted">${fmtTime(e.updated_at)}</td>
            <td onclick="event.stopPropagation()">
                <div class="action-cell">
                    <button class="btn-act btn-act-edit" onclick="openEstimate(${e.id})">수정</button>
                    <div class="print-dropdown">
                        <button class="btn-act btn-act-print" onclick="togglePrintMenu(event,${e.id})">출력 ▾</button>
                        <div class="print-dropdown-menu" id="printMenu-${e.id}">
                            <button onclick="exportEstimate(${e.id},'image')">이미지 저장</button>
                            <button onclick="exportEstimate(${e.id},'pdf')">PDF 저장</button>
                            <button onclick="window.open('/estimates/${e.id}/print','_blank')">인쇄 미리보기</button>
                        </div>
                    </div>
                    <button class="btn-act btn-act-delete" onclick="deleteEstimate(${e.id})">삭제</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function createEstimate() {
    const res = await fetch('/api/estimates', {method:'POST', headers:H});
    const est = await res.json();
    openEstimate(est.id);
    loadEstimates();
}

function openEstimate(id) {
    window.open(`/estimates/${id}/edit`, `estimate_${id}`, 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

async function deleteEstimate(id) {
    if (!confirm('이 견적서를 삭제할까요?')) return;
    await fetch(`/api/estimates/${id}`, {method:'DELETE', headers:H});
    loadEstimates();
}

function togglePrintMenu(e, id) {
    e.stopPropagation();
    const menu = document.getElementById(`printMenu-${id}`);
    document.querySelectorAll('.print-dropdown-menu.show').forEach(m => { if(m!==menu) m.classList.remove('show'); });
    menu.classList.toggle('show');
}
document.addEventListener('click', () => document.querySelectorAll('.print-dropdown-menu.show').forEach(m => m.classList.remove('show')));

async function exportEstimate(id, type) {
    document.querySelectorAll('.print-dropdown-menu.show').forEach(m => m.classList.remove('show'));
    const printUrl = `/estimates/${id}/print`;
    const w = window.open(printUrl, '_blank', 'width=860,height=900,scrollbars=yes');
    w.addEventListener('load', () => {
        setTimeout(async () => {
            try {
                const { default: html2canvas } = await import('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/+esm');
                const el = w.document.querySelector('.estimate-wrap');
                const bar = w.document.querySelector('.no-print-bar');
                if (bar) bar.style.display = 'none';
                if (el) el.style.marginTop = '0';

                const srcCanvas = await html2canvas(el, { scale:2, useCORS:true, backgroundColor:'#fff', windowWidth:820 });

                // 여백 80px (scale:2 기준 40px * 2)
                const pad = 80;
                const canvas = document.createElement('canvas');
                canvas.width = srcCanvas.width + pad * 2;
                canvas.height = srcCanvas.height + pad * 2;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(srcCanvas, pad, pad);

                if (type === 'image') {
                    const link = document.createElement('a');
                    link.download = `견적서_${id}.png`;
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } else if (type === 'pdf') {
                    const { jsPDF } = await import('https://cdn.jsdelivr.net/npm/jspdf@2.5.2/+esm');
                    const imgData = canvas.toDataURL('image/png');
                    const pxW = canvas.width, pxH = canvas.height;
                    const pdfW = 210;
                    const pdfH = (pxH * pdfW) / pxW;
                    const pdf = new jsPDF({ unit:'mm', format:[pdfW, pdfH] });
                    pdf.addImage(imgData, 'PNG', 0, 0, pdfW, pdfH);
                    pdf.save(`견적서_${id}.pdf`);
                }
                w.close();
            } catch(err) {
                console.error(err);
                alert('출력 처리 중 오류가 발생했습니다. 인쇄 페이지에서 직접 저장해주세요.');
            }
        }, 500);
    });
}

// === 프리셋 탭 ===
const CAN_EST_EDIT = @json(Auth::user()->hasPermission('estimates.edit'));
let PRESETS = [];
function _esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function setEstTab(tab) {
    document.getElementById('tabList').style.display = tab === 'list' ? '' : 'none';
    document.getElementById('tabPresets').style.display = tab === 'presets' ? '' : 'none';
    document.getElementById('tabBtnList').classList.toggle('active', tab === 'list');
    document.getElementById('tabBtnPresets').classList.toggle('active', tab === 'presets');
    const btn = document.getElementById('headerActionBtn');
    if (tab === 'presets') {
        btn.textContent = '+ 프리셋 만들기';
        btn.onclick = () => openPresetModal(null);
        btn.style.display = CAN_EST_EDIT ? '' : 'none';
        loadPresets();
    } else {
        btn.textContent = '+ 견적서 생성';
        btn.onclick = createEstimate;
        btn.style.display = CAN_EST_EDIT ? '' : 'none';
    }
}

async function loadPresets() {
    const res = await fetch('/api/estimate-presets', { headers: { 'Accept': 'application/json' } });
    PRESETS = res.ok ? await res.json() : [];
    const tb = document.getElementById('presetBody');
    if (!PRESETS.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-row">저장된 프리셋이 없습니다. 우측 상단 [+ 프리셋 만들기] 또는 견적서 편집 화면의 [현재 품목을 프리셋으로 저장]으로 만들 수 있습니다.</td></tr>';
        return;
    }
    tb.innerHTML = PRESETS.map(p => `<tr>
        <td style="font-weight:600;">${_esc(p.title)}</td>
        <td class="text-muted">${p.item_count}개</td>
        <td class="text-right" style="font-weight:600;">${fmt(p.total)}원</td>
        <td class="text-muted">${_esc(p.creator || '-')}</td>
        <td class="text-muted">${p.updated_at}</td>
        <td>${CAN_EST_EDIT ? `<div class="action-cell">
            <button class="btn-act btn-act-edit" onclick="openPresetModal(${p.id})">수정</button>
            <button class="btn-act btn-act-delete" onclick="deletePreset(${p.id})">삭제</button>
        </div>` : ''}</td>
    </tr>`).join('');
}

// === 프리셋 생성/수정 모달 ===
let pmItems = [], pmEditId = null;
function openPresetModal(id) {
    pmEditId = id;
    const p = id ? PRESETS.find(x => x.id === id) : null;
    pmItems = p ? p.items.map(i => ({ ...i })) : [];
    document.getElementById('presetModalTitle').textContent = p ? '프리셋 수정' : '프리셋 만들기';
    document.getElementById('pmTitle').value = p ? p.title : '';
    document.getElementById('pmSearch').value = '';
    document.getElementById('pmSearchResults').innerHTML = '';
    ['pmMiCat','pmMiName','pmMiPrice'].forEach(i => document.getElementById(i).value = '');
    renderPmItems();
    document.getElementById('presetModalOverlay').style.display = 'flex';
}
function closePresetModal() { document.getElementById('presetModalOverlay').style.display = 'none'; }

async function pmSearchProducts() {
    const q = document.getElementById('pmSearch').value.trim();
    if (!q) { document.getElementById('pmSearchResults').innerHTML = ''; return; }
    const res = await fetch('/api/inventory/estimate-products?search=' + encodeURIComponent(q));
    if (!res.ok) return;
    const prods = await res.json();
    document.getElementById('pmSearchResults').innerHTML = prods.length ? prods.map(p => {
        const label = p.group_id && p.option_name ? `${p.group_name} (${p.option_name})` : p.name;
        return `<div class="pm-result" onclick='pmAddProduct(${JSON.stringify(p).replace(/'/g, "&#39;")})'>
            <span>${_esc(label)} <span style="color:var(--text-muted); font-size:11px;">${_esc(p.sku)}</span></span>
            <span style="color:var(--text-muted);">${fmt(p.sale_price)}원 · 재고 ${p.quantity}</span>
        </div>`;
    }).join('') : '<div class="empty-row" style="padding:12px;">검색 결과가 없습니다.</div>';
}
function pmAddProduct(p) {
    const name = p.group_id && p.option_name ? `${p.group_name} (${p.option_name})` : p.name;
    pmItems.push({
        product_id: p.id, sku: p.sku, category: p.category || '기타', name,
        purchase_price: p.purchase_price || 0, sale_price: Number(p.sale_price) || 0,
        qty: 1, time_required: '', subtotal: Number(p.sale_price) || 0, manual: false,
    });
    renderPmItems();
}
function pmAddManual() {
    const name = document.getElementById('pmMiName').value.trim();
    if (!name) return alert('제품명을 입력해주세요.');
    const price = Math.max(0, parseInt(document.getElementById('pmMiPrice').value) || 0);
    pmItems.push({
        product_id: null, sku: '', category: document.getElementById('pmMiCat').value.trim() || '기타',
        name, purchase_price: 0, sale_price: price, qty: 1, time_required: '', subtotal: price, manual: true,
    });
    ['pmMiName','pmMiPrice'].forEach(i => document.getElementById(i).value = '');
    renderPmItems();
}
function renderPmItems() {
    const tb = document.getElementById('pmItems');
    document.getElementById('pmCount').textContent = pmItems.length ? `${pmItems.length}개` : '';
    if (!pmItems.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-row" style="padding:16px;">아직 담긴 품목이 없습니다.</td></tr>';
        document.getElementById('pmTotal').textContent = '';
        return;
    }
    tb.innerHTML = pmItems.map((it, i) => `<tr>
        <td class="text-muted">${_esc(it.category || '')}</td>
        <td>${_esc(it.name)}${it.manual || !it.product_id ? ' <span style="font-size:9px; color:var(--text-muted); border:1px solid var(--border); border-radius:3px; padding:0 4px;">수기</span>' : ''}</td>
        <td class="text-right"><input type="number" min="0" value="${it.sale_price}" onchange="pmItems[${i}].sale_price=Math.max(0,+this.value||0); pmItems[${i}].subtotal=pmItems[${i}].sale_price*pmItems[${i}].qty; renderPmItems();" style="width:90px; text-align:right;" class="pm-input"></td>
        <td><input type="number" min="1" value="${it.qty}" onchange="pmItems[${i}].qty=Math.max(1,+this.value||1); pmItems[${i}].subtotal=pmItems[${i}].sale_price*pmItems[${i}].qty; renderPmItems();" style="width:58px; text-align:center;" class="pm-input"></td>
        <td class="text-right" style="font-weight:600;">${fmt(it.subtotal)}원</td>
        <td><button onclick="pmItems.splice(${i},1); renderPmItems();" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">×</button></td>
    </tr>`).join('');
    document.getElementById('pmTotal').textContent = '합계 ' + fmt(pmItems.reduce((s, i) => s + (i.subtotal || 0), 0)) + '원';
}
async function savePreset() {
    const title = document.getElementById('pmTitle').value.trim();
    if (!title) return alert('프리셋 제목을 입력해주세요.');
    if (!pmItems.length) return alert('품목을 1개 이상 담아주세요.');
    const res = await fetch(pmEditId ? `/api/estimate-presets/${pmEditId}` : '/api/estimate-presets', {
        method: pmEditId ? 'PATCH' : 'POST', headers: H,
        body: JSON.stringify({ title, items: pmItems }),
    });
    if (!res.ok) { const e = await res.json().catch(()=>({})); return alert(e.message || '저장에 실패했습니다.'); }
    closePresetModal();
    loadPresets();
}
async function deletePreset(id) {
    if (!confirm('이 프리셋을 삭제할까요? 이미 작성된 견적서에는 영향이 없습니다.')) return;
    await fetch(`/api/estimate-presets/${id}`, { method: 'DELETE', headers: H });
    loadPresets();
}

loadEstimates();
</script>
@endpush
