@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '재고 관리 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }

    /* 탭 */
    .tab-bar { display:flex; gap:2px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:4px; margin-bottom:20px; }
    .tab-btn { flex:1; padding:10px 0; text-align:center; font-size:13px; font-weight:600; border:none; background:none; color:var(--text-muted); cursor:pointer; border-radius:8px; transition:all 0.15s; }
    .tab-btn.active { background:var(--accent); color:#1a1207; }
    .tab-btn:not(.active):hover { color:var(--text); background:var(--surface2); }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }

    /* 공통 */
    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:240px; }
    .toolbar input:focus { border-color:var(--accent); }
    .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-sm { padding:5px 10px; font-size:12px; border-radius:6px; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .btn-danger-sm { background:none; border:none; color:var(--text-muted); font-size:12px; cursor:pointer; padding:5px 8px; }
    .btn-danger-sm:hover { color:var(--red); }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .empty-row { text-align:center; padding:40px !important; color:var(--text-muted); font-size:13px; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-in { background:#1a2a1a; color:#7ac87a; } .badge-out { background:#2a1a1a; color:#c87a7a; }
    .badge-adjust { background:#1a1a2a; color:#8ab4c8; } .badge-return { background:#2a2010; color:var(--accent); }
    .badge-low { background:#2a1a1a; color:#c87a7a; } .badge-ok { background:#1a2a1a; color:#7ac87a; }
    .badge-requested { background:#2a2010; color:var(--accent); } .badge-approved { background:#1a1a2a; color:#8ab4c8; }
    .badge-ordered { background:#2a1a2a; color:#9b70c8; } .badge-received { background:#1a2a1a; color:#7ac87a; }
    .badge-cancelled { background:var(--surface2); color:var(--text-muted); }
    .text-muted { color:var(--text-muted); font-size:12px; } .text-right { text-align:center; } .text-warn { color:var(--red); }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:500px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .modal-title { font-size:16px; font-weight:700; }
    .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
    .field-group { margin-bottom:14px; } .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-select { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .field-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

    .order-items { margin-top:10px; }
    .order-item-row { display:flex; gap:6px; align-items:flex-end; margin-bottom:6px; }
    .order-item-row select, .order-item-row input { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; }
    .order-item-row select { flex:2; } .order-item-row input { flex:1; }
    .btn-remove-item { background:none; border:none; color:var(--text-muted); font-size:14px; cursor:pointer; padding:4px 8px; }
    .btn-remove-item:hover { color:var(--red); }
    .btn-add-item { background:none; border:1px dashed var(--border); color:var(--text-muted); font-size:12px; padding:6px 12px; border-radius:6px; cursor:pointer; width:100%; margin-top:4px; }
    .btn-add-item:hover { border-color:var(--accent); color:var(--accent); }

    /* 카테고리 트리 */
    .cat-tree { display:flex; flex-direction:column; gap:4px; }
    .cat-lv1 { background:var(--surface); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    .cat-row { display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:13px; }
    .cat-row:hover { background:var(--surface2); }
    .cat-code { font-size:11px; color:var(--accent); font-weight:600; min-width:40px; }
    .cat-name { flex:1; }
    .cat-actions { display:flex; gap:4px; }
    .cat-children { padding-left:24px; border-top:1px solid var(--border); }
    .cat-children .cat-children { padding-left:24px; }
    .cat-depth { color:var(--text-muted); font-size:11px; }
    .cat-add-inline { display:flex; gap:6px; padding:6px 14px; align-items:center; }
    .cat-add-inline input { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:5px 8px; color:var(--text); font-size:12px; outline:none; }
    .cat-add-inline input:focus { border-color:var(--accent); }
    .cat-add-inline button { background:var(--accent); color:#1a1207; border:none; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; }
    .sku-preview { font-size:12px; color:var(--accent); font-weight:600; padding:8px 12px; background:var(--surface2); border-radius:6px; margin-top:4px; }
    [data-theme="light"] .tab-btn.active { color:#fff; }
    [data-theme="light"] .btn-primary { color:#fff; }
    [data-theme="light"] .btn-save { color:#fff; }
    [data-theme="light"] .cat-add-inline button { color:#fff; }
    /* 배지 라이트모드 */
    [data-theme="light"] .badge-in       { background:#e8f5e8; color:#1a7a2a; }
    [data-theme="light"] .badge-out      { background:#ffe8e8; color:#c03838; }
    [data-theme="light"] .badge-adjust   { background:#e0f0ff; color:#2e6a9a; }
    [data-theme="light"] .badge-return   { background:#fff3e0; color:#a06800; }
    [data-theme="light"] .badge-low      { background:#ffe8e8; color:#c03838; }
    [data-theme="light"] .badge-ok       { background:#e8f5e8; color:#1a7a2a; }
    [data-theme="light"] .badge-requested { background:#fff3e0; color:#a06800; }
    [data-theme="light"] .badge-approved  { background:#e0f0ff; color:#2e6a9a; }
    [data-theme="light"] .badge-ordered   { background:#f0e8ff; color:#5c2e90; }
    [data-theme="light"] .badge-received  { background:#e8f5e8; color:#248a38; }
    [data-theme="light"] .badge-cancelled { background:#e8eaef; color:#5a6070; }
    /* 입력/테이블 라이트모드 */
    [data-theme="light"] .toolbar input[type="text"], [data-theme="light"] .toolbar select { background:#fff; border-color:#b8bcc8; }
    [data-theme="light"] .field-input, [data-theme="light"] .field-select { background:#fff; border-color:#b8bcc8; }
    [data-theme="light"] .data-table th { background:#f0f1f3; color:#4a5060; }
    [data-theme="light"] .modal { background:#fff; border-color:#c8ccd4; }
    [data-theme="light"] .data-card { border-color:#c8ccd4; }
    [data-theme="light"] .cat-lv1 { border-color:#c8ccd4; }
    [data-theme="light"] .cat-add-inline input { background:#fff; border-color:#b8bcc8; }
    [data-theme="light"] .btn-outline { border-color:#b8bcc8; color:#4a5060; }
    [data-theme="light"] .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">재고 관리</div>
        <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openActivityLog('Product,ProductCategory,StockMovement,PurchaseOrder',0,'재고 전체 수정 로그')">📋 수정 로그</button>
    </div>

    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('stock')">재고 현황</button>
        <button class="tab-btn" onclick="switchTab('products')">제품 관리</button>
        <button class="tab-btn" onclick="switchTab('movements')">입출고 내역</button>
        <button class="tab-btn" onclick="switchTab('orders')">발주 관리</button>
        <button class="tab-btn" onclick="switchTab('categories')">카테고리</button>
    </div>

    <!-- 재고 현황 -->
    <div class="tab-panel active" id="panel-stock">
        <div class="toolbar">
            <input type="text" id="stockSearch" placeholder="제품명/SKU 검색" oninput="loadStock()">
            <label style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:4px; cursor:pointer;">
                <input type="checkbox" id="lowStockOnly" onchange="loadStock()" style="accent-color:var(--accent);"> 부족 재고만
            </label>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>SKU</th><th>제품명</th><th>카테고리</th><th class="text-right">현재 수량</th><th class="text-right">안전재고</th><th>상태</th></tr></thead>
                <tbody id="stockBody"><tr><td colspan="6" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- 제품 관리 -->
    <div class="tab-panel" id="panel-products">
        <div class="toolbar">
            <input type="text" id="productSearch" placeholder="제품명/SKU 검색" oninput="loadProducts()">
            <button class="btn-primary" onclick="openProductModal()">+ 제품 등록</button>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>SKU</th><th>제품명</th><th>카테고리</th><th class="text-right">매입가</th><th class="text-right">판매가</th><th class="text-right">안전재고</th><th>견적</th><th></th></tr></thead>
                <tbody id="productBody"><tr><td colspan="8" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- 입출고 내역 -->
    <div class="tab-panel" id="panel-movements">
        <div class="toolbar">
            <select id="movementType" onchange="loadMovements()">
                <option value="">전체 유형</option>
                <option value="in">입고</option><option value="out">출고</option><option value="adjust">조정</option><option value="return">반품</option>
            </select>
            <button class="btn-primary" onclick="openMovementModal()">+ 입출고 등록</button>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>일시</th><th>유형</th><th>제품</th><th class="text-right">수량</th><th class="text-right">변동 후</th><th>처리자</th><th>메모</th></tr></thead>
                <tbody id="movementBody"><tr><td colspan="7" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- 발주 관리 -->
    <div class="tab-panel" id="panel-orders">
        <div class="toolbar">
            <select id="orderStatus" onchange="loadOrders()">
                <option value="">전체 상태</option>
                <option value="requested">요청</option><option value="approved">승인</option><option value="ordered">발주</option><option value="received">입고완료</option><option value="cancelled">취소</option>
            </select>
            <button class="btn-primary" onclick="openOrderModal()">+ 발주 요청</button>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>번호</th><th>거래처</th><th>품목</th><th class="text-right">금액</th><th>상태</th><th>요청자</th><th>예정일</th><th></th></tr></thead>
                <tbody id="orderBody"><tr><td colspan="8" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- 카테고리 관리 -->
    <div class="tab-panel" id="panel-categories">
        <div class="toolbar">
            <button class="btn-primary" onclick="showAddCat(null)">+ 1차 카테고리 추가</button>
            <span class="text-muted">코드는 영문 대문자 (SKU 접두사로 사용됩니다)</span>
        </div>
        <div id="catAddRoot" style="display:none; margin-bottom:12px;">
            <div class="cat-add-inline" style="padding:0;">
                <input id="catRootName" placeholder="카테고리명" style="width:140px;">
                <input id="catRootCode" placeholder="코드 (예: PCC)" style="width:80px; text-transform:uppercase;" maxlength="10">
                <button onclick="saveCat(null)">추가</button>
                <button onclick="document.getElementById('catAddRoot').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer;">취소</button>
            </div>
        </div>
        <div class="cat-tree" id="catTree"><div class="empty-row">로딩 중...</div></div>
    </div>
</div>

<!-- 제품 등록/수정 모달 -->
<div class="modal-overlay" id="productModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="productModalTitle">제품 등록</div>
            <button class="modal-close" onclick="closeModal('productModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">카테고리 *</div>
            <div class="field-row-3">
                <select class="field-select" id="pCat1" onchange="onCat1Change()"><option value="">1차 선택</option></select>
                <select class="field-select" id="pCat2" onchange="onCat2Change()" disabled><option value="">2차 선택</option></select>
                <select class="field-select" id="pCat3" disabled><option value="">3차 선택</option></select>
            </div>
            <div class="sku-preview" id="skuPreview" style="display:none;">SKU: <span id="skuText"></span> (자동 생성)</div>
        </div>
        <div class="field-group">
            <div class="field-label">제품명 *</div>
            <input class="field-input" id="pName">
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">매입가</div>
                <input class="field-input" id="pPurchase" type="number" min="0">
            </div>
            <div class="field-group">
                <div class="field-label">판매가</div>
                <input class="field-input" id="pSale" type="number" min="0">
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">안전재고 (이하 경고)</div>
            <input class="field-input" id="pSafety" type="number" min="0">
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <input class="field-input" id="pMemo">
        </div>
        <div class="field-group">
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                <input type="checkbox" id="pEstimate" style="accent-color:var(--accent); width:15px; height:15px; cursor:pointer;">
                견적서에 노출
            </label>
        </div>
        <input type="hidden" id="pEditId">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('productModal')">취소</button>
            <button class="btn-save" onclick="saveProduct()">저장</button>
        </div>
    </div>
</div>

<!-- 입출고 등록 모달 -->
<div class="modal-overlay" id="movementModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">입출고 등록</div>
            <button class="modal-close" onclick="closeModal('movementModal')">×</button>
        </div>
        <div class="field-group"><div class="field-label">제품 *</div><select class="field-select" id="mProduct"></select></div>
        <div class="field-row">
            <div class="field-group"><div class="field-label">유형 *</div>
                <select class="field-select" id="mType"><option value="in">입고</option><option value="out">출고</option><option value="adjust">재고 조정</option><option value="return">반품</option></select>
            </div>
            <div class="field-group"><div class="field-label">수량 *</div><input class="field-input" id="mQty" type="number" min="1" value="1"></div>
        </div>
        <div class="field-group"><div class="field-label">메모</div><input class="field-input" id="mMemo" placeholder="사유 또는 참고사항"></div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('movementModal')">취소</button>
            <button class="btn-save" onclick="saveMovement()">등록</button>
        </div>
    </div>
</div>

<!-- 발주 등록 모달 -->
<div class="modal-overlay" id="orderModal">
    <div class="modal" style="width:600px;">
        <div class="modal-header">
            <div class="modal-title">발주 요청</div>
            <button class="modal-close" onclick="closeModal('orderModal')">×</button>
        </div>
        <div class="field-row">
            <div class="field-group"><div class="field-label">거래처 *</div><input class="field-input" id="oSupplier"></div>
            <div class="field-group"><div class="field-label">예정일</div><input class="field-input" id="oDate" type="date"></div>
        </div>
        <div class="field-group">
            <div class="field-label">품목 *</div>
            <div class="order-items" id="orderItems"></div>
            <button class="btn-add-item" onclick="addOrderItem()">+ 품목 추가</button>
        </div>
        <div class="field-group"><div class="field-label">메모</div><input class="field-input" id="oMemo"></div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('orderModal')">취소</button>
            <button class="btn-save" onclick="saveOrder()">요청</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
let allProducts = [], catData = [];

function switchTab(name, skipHash) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        const map = {stock:'현황',products:'제품',movements:'입출고',orders:'발주',categories:'카테고리'};
        b.classList.toggle('active', b.textContent.includes(map[name]));
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+name));
    if (!skipHash) history.replaceState(null, '', '#'+name);
    ({stock:loadStock,products:loadProducts,movements:loadMovements,orders:loadOrders,categories:loadCategories})[name]();
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open')); });

function fmt(n) { return n!=null ? Number(n).toLocaleString() : '-'; }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('ko-KR') : '-'; }
function fmtTime(d) { return d ? new Date(d).toLocaleString('ko-KR',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '-'; }

// === 카테고리 ===
async function loadCategories() {
    const res = await fetch('/api/inventory/categories');
    catData = await res.json();
    renderCatTree();
}
function renderCatTree() {
    const el = document.getElementById('catTree');
    if (!catData.length) { el.innerHTML = '<div class="empty-row">등록된 카테고리가 없습니다.</div>'; return; }
    el.innerHTML = catData.map(c => renderCatNode(c, 1)).join('');
}
function renderCatNode(cat, depth) {
    const children = cat.children || [];
    const canAddChild = depth < 3;
    let html = `<div class="cat-lv1" style="${depth>1?'border:none; border-radius:0;':''}">
        <div class="cat-row">
            <span class="cat-code">${cat.code}</span>
            <span class="cat-name">${cat.name}</span>
            <span class="cat-depth">${depth}차</span>
            <div class="cat-actions">
                ${canAddChild ? `<button class="btn-outline btn-sm" onclick="showAddCat(${cat.id})">+ 하위</button>` : ''}
                <button class="btn-danger-sm" onclick="deleteCat(${cat.id})">삭제</button>
            </div>
        </div>
        <div id="catAdd-${cat.id}" style="display:none;" class="cat-add-inline">
            <input id="catName-${cat.id}" placeholder="카테고리명" style="width:120px;">
            <input id="catCode-${cat.id}" placeholder="코드" style="width:70px; text-transform:uppercase;" maxlength="10">
            <button onclick="saveCat(${cat.id})">추가</button>
            <button onclick="document.getElementById('catAdd-${cat.id}').style.display='none'" style="background:none;border:none;color:var(--text-muted);cursor:pointer;">취소</button>
        </div>`;
    if (children.length) {
        html += `<div class="cat-children">${children.map(c => renderCatNode(c, depth+1)).join('')}</div>`;
    }
    html += '</div>';
    return html;
}
function showAddCat(parentId) {
    if (parentId === null) {
        document.getElementById('catAddRoot').style.display = 'block';
        document.getElementById('catRootName').value = '';
        document.getElementById('catRootCode').value = '';
        document.getElementById('catRootName').focus();
    } else {
        document.getElementById(`catAdd-${parentId}`).style.display = 'flex';
        document.getElementById(`catName-${parentId}`).value = '';
        document.getElementById(`catCode-${parentId}`).value = '';
        document.getElementById(`catName-${parentId}`).focus();
    }
}
async function saveCat(parentId) {
    const name = parentId === null ? document.getElementById('catRootName').value : document.getElementById(`catName-${parentId}`).value;
    const code = (parentId === null ? document.getElementById('catRootCode').value : document.getElementById(`catCode-${parentId}`).value).toUpperCase();
    if (!name || !code) { alert('이름과 코드를 입력해주세요.'); return; }
    const body = { name, code, parent_id: parentId };
    const res = await fetch('/api/inventory/categories', {method:'POST', headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); alert(e.message || Object.values(e.errors||{}).flat().join('\n')); return; }
    if (parentId === null) document.getElementById('catAddRoot').style.display = 'none';
    loadCategories();
}
async function deleteCat(id) {
    if (!confirm('이 카테고리를 삭제할까요?')) return;
    const res = await fetch(`/api/inventory/categories/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); alert(e.message); return; }
    loadCategories();
}

// === 카테고리 드롭다운 (제품 모달) ===
function getSelectedCategoryId() {
    const v3 = document.getElementById('pCat3').value;
    const v2 = document.getElementById('pCat2').value;
    const v1 = document.getElementById('pCat1').value;
    return v3 || v2 || v1 || null;
}
function updateSkuPreview() {
    const catId = getSelectedCategoryId();
    const preview = document.getElementById('skuPreview');
    if (!catId) { preview.style.display = 'none'; return; }
    const codes = buildCodePath(+catId);
    if (codes) {
        document.getElementById('skuText').textContent = codes + '-XXX';
        preview.style.display = 'block';
    }
}
function buildCodePath(catId) {
    for (const c1 of catData) {
        if (c1.id === catId) return c1.code;
        for (const c2 of (c1.children||[])) {
            if (c2.id === catId) return c1.code+'-'+c2.code;
            for (const c3 of (c2.children||[])) {
                if (c3.id === catId) return c1.code+'-'+c2.code+'-'+c3.code;
            }
        }
    }
    return null;
}
function populateCatDropdowns(editCatId) {
    const s1 = document.getElementById('pCat1');
    s1.innerHTML = '<option value="">1차 선택</option>' + catData.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
    document.getElementById('pCat2').innerHTML = '<option value="">2차 선택</option>';
    document.getElementById('pCat2').disabled = true;
    document.getElementById('pCat3').innerHTML = '<option value="">3차 선택</option>';
    document.getElementById('pCat3').disabled = true;
    if (editCatId) {
        for (const c1 of catData) {
            if (c1.id === editCatId) { s1.value = c1.id; onCat1Change(); break; }
            for (const c2 of (c1.children||[])) {
                if (c2.id === editCatId) { s1.value = c1.id; onCat1Change(); document.getElementById('pCat2').value = c2.id; onCat2Change(); break; }
                for (const c3 of (c2.children||[])) {
                    if (c3.id === editCatId) { s1.value = c1.id; onCat1Change(); document.getElementById('pCat2').value = c2.id; onCat2Change(); document.getElementById('pCat3').value = c3.id; break; }
                }
            }
        }
    }
    updateSkuPreview();
}
function onCat1Change() {
    const c1 = catData.find(c=>c.id===+document.getElementById('pCat1').value);
    const s2 = document.getElementById('pCat2');
    const s3 = document.getElementById('pCat3');
    s3.innerHTML = '<option value="">3차 선택</option>'; s3.disabled = true;
    if (c1 && c1.children?.length) {
        s2.innerHTML = '<option value="">2차 선택</option>' + c1.children.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
        s2.disabled = false;
    } else { s2.innerHTML = '<option value="">2차 없음</option>'; s2.disabled = true; }
    updateSkuPreview();
}
function onCat2Change() {
    const c1 = catData.find(c=>c.id===+document.getElementById('pCat1').value);
    const c2 = c1?.children?.find(c=>c.id===+document.getElementById('pCat2').value);
    const s3 = document.getElementById('pCat3');
    if (c2 && c2.children?.length) {
        s3.innerHTML = '<option value="">3차 선택</option>' + c2.children.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
        s3.disabled = false;
    } else { s3.innerHTML = '<option value="">3차 없음</option>'; s3.disabled = true; }
    updateSkuPreview();
}

// === 재고 현황 ===
async function loadStock() {
    const search = document.getElementById('stockSearch').value;
    const low = document.getElementById('lowStockOnly').checked;
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (low) params.set('low_stock', '1');
    const res = await fetch('/api/inventory/stock?'+params);
    const data = await res.json();
    const tb = document.getElementById('stockBody');
    if (!data.length) { tb.innerHTML = '<tr><td colspan="6" class="empty-row">데이터가 없습니다.</td></tr>'; return; }
    tb.innerHTML = data.map(p => `<tr>
        <td class="text-muted">${p.sku}</td><td>${p.name}</td><td class="text-muted">${p.category||'-'}</td>
        <td class="text-right ${p.is_low?'text-warn':''}" style="font-weight:600;">${p.quantity}</td>
        <td class="text-right text-muted">${p.safety_stock||'-'}</td>
        <td>${p.is_low?'<span class="badge badge-low">부족</span>':'<span class="badge badge-ok">정상</span>'}</td>
    </tr>`).join('');
}

// === 제품 관리 ===
async function loadProducts() {
    const search = document.getElementById('productSearch').value;
    const params = search ? '?search='+encodeURIComponent(search) : '';
    const res = await fetch('/api/inventory/products'+params);
    allProducts = await res.json();
    const tb = document.getElementById('productBody');
    if (!allProducts.length) { tb.innerHTML = '<tr><td colspan="8" class="empty-row">등록된 제품이 없습니다.</td></tr>'; return; }
    tb.innerHTML = allProducts.map(p => `<tr>
        <td class="text-muted">${p.sku}</td><td>${p.name}</td><td class="text-muted">${p.category||'-'}</td>
        <td class="text-right">${fmt(p.purchase_price)}</td><td class="text-right">${fmt(p.sale_price)}</td>
        <td class="text-right">${p.safety_stock||'-'}</td>
        <td>${p.show_in_estimate ? '<span class="badge badge-ok">노출</span>' : ''}</td>
        <td>
            <button class="btn-outline btn-sm" onclick="openActivityLog('Product',${p.id},'제품 '+p.name+' 수정 로그')">📋</button>
            <button class="btn-outline btn-sm" onclick='editProduct(${p.id})'>수정</button>
            <button class="btn-danger-sm" onclick="deleteProduct(${p.id})">삭제</button>
        </td>
    </tr>`).join('');
}
async function openProductModal(p) {
    if (!catData.length) await loadCategories();
    document.getElementById('productModalTitle').textContent = p ? '제품 수정' : '제품 등록';
    document.getElementById('pEditId').value = p ? p.id : '';
    document.getElementById('pName').value = p ? p.name : '';
    document.getElementById('pPurchase').value = p ? (p.purchase_price||'') : '';
    document.getElementById('pSale').value = p ? (p.sale_price||'') : '';
    document.getElementById('pSafety').value = p ? (p.safety_stock||'') : '';
    document.getElementById('pMemo').value = p ? (p.memo||'') : '';
    document.getElementById('pEstimate').checked = p ? !!p.show_in_estimate : false;
    populateCatDropdowns(p ? p.category_id : null);
    openModal('productModal');
}
function editProduct(id) {
    const p = allProducts.find(x=>x.id===id);
    if (p) openProductModal(p);
}
async function saveProduct() {
    const id = document.getElementById('pEditId').value;
    const categoryId = getSelectedCategoryId();
    if (!categoryId) { alert('카테고리를 선택해주세요.'); return; }
    const body = {
        name: document.getElementById('pName').value,
        category_id: +categoryId,
        purchase_price: document.getElementById('pPurchase').value || null,
        sale_price: document.getElementById('pSale').value || null,
        safety_stock: document.getElementById('pSafety').value || null,
        memo: document.getElementById('pMemo').value || null,
        show_in_estimate: document.getElementById('pEstimate').checked,
    };
    const url = id ? `/api/inventory/products/${id}` : '/api/inventory/products';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {method, headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); alert(Object.values(e.errors||{}).flat().join('\n')||'오류 발생'); return; }
    closeModal('productModal');
    loadProducts();
}
async function deleteProduct(id) {
    if (!confirm('이 제품을 삭제할까요?')) return;
    await fetch(`/api/inventory/products/${id}`, {method:'DELETE', headers:H});
    loadProducts();
}

// === 입출고 ===
async function loadMovements() {
    const type = document.getElementById('movementType').value;
    const params = type ? '?type='+type : '';
    const res = await fetch('/api/inventory/movements'+params);
    const data = await res.json();
    const tb = document.getElementById('movementBody');
    const typeMap = {in:'입고',out:'출고',adjust:'조정',return:'반품'};
    if (!data.length) { tb.innerHTML = '<tr><td colspan="7" class="empty-row">내역이 없습니다.</td></tr>'; return; }
    tb.innerHTML = data.map(m => `<tr>
        <td class="text-muted">${fmtTime(m.created_at)}</td>
        <td><span class="badge badge-${m.movement_type}">${typeMap[m.movement_type]}</span></td>
        <td>${m.product?.name||'-'}</td>
        <td class="text-right" style="font-weight:600;color:${m.movement_type==='out'?'var(--red)':'var(--green)'};">${m.movement_type==='out'?'-':''}${m.quantity}</td>
        <td class="text-right">${m.quantity_after}</td>
        <td class="text-muted">${m.user?.display_name||'-'}</td>
        <td class="text-muted">${m.memo||'-'}</td>
    </tr>`).join('');
}
async function openMovementModal() {
    if (!allProducts.length) { const r = await fetch('/api/inventory/products'); allProducts = await r.json(); }
    document.getElementById('mProduct').innerHTML = allProducts.map(p=>`<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
    document.getElementById('mType').value='in'; document.getElementById('mQty').value=1; document.getElementById('mMemo').value='';
    openModal('movementModal');
}
async function saveMovement() {
    const body = { product_id:+document.getElementById('mProduct').value, movement_type:document.getElementById('mType').value, quantity:+document.getElementById('mQty').value, memo:document.getElementById('mMemo').value||null };
    const res = await fetch('/api/inventory/movements',{method:'POST',headers:H,body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); alert(Object.values(e.errors||{}).flat().join('\n')||'오류 발생'); return; }
    closeModal('movementModal'); loadMovements();
}

// === 발주 ===
async function loadOrders() {
    const status = document.getElementById('orderStatus').value;
    const res = await fetch('/api/inventory/orders'+(status?'?status='+status:''));
    const data = await res.json();
    const tb = document.getElementById('orderBody');
    const stMap = {requested:'요청',approved:'승인',ordered:'발주',received:'입고완료',cancelled:'취소'};
    if (!data.length) { tb.innerHTML = '<tr><td colspan="8" class="empty-row">발주 내역이 없습니다.</td></tr>'; return; }
    tb.innerHTML = data.map(o => {
        const itemNames = (o.items||[]).map(i=>i.name||`#${i.product_id}`).join(', ');
        const acts = [];
        if (o.status==='requested') acts.push(`<button class="btn-outline btn-sm" onclick="updateOrder(${o.id},'approved')">승인</button>`);
        if (o.status==='approved') acts.push(`<button class="btn-outline btn-sm" onclick="updateOrder(${o.id},'ordered')">발주</button>`);
        if (o.status==='ordered') acts.push(`<button class="btn-outline btn-sm" onclick="receiveOrder(${o.id})">입고처리</button>`);
        if (['requested','approved'].includes(o.status)) acts.push(`<button class="btn-danger-sm" onclick="updateOrder(${o.id},'cancelled')">취소</button>`);
        return `<tr><td class="text-muted">#${o.id}</td><td>${o.supplier}</td>
            <td class="text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${itemNames}</td>
            <td class="text-right">${fmt(o.total_amount)}</td><td><span class="badge badge-${o.status}">${stMap[o.status]}</span></td>
            <td class="text-muted">${o.requester?.display_name||'-'}</td><td class="text-muted">${fmtDate(o.expected_date)}</td>
            <td>${acts.join(' ')}</td></tr>`;
    }).join('');
}
async function openOrderModal() {
    if (!allProducts.length) { const r=await fetch('/api/inventory/products'); allProducts=await r.json(); }
    document.getElementById('oSupplier').value=''; document.getElementById('oDate').value=''; document.getElementById('oMemo').value='';
    document.getElementById('orderItems').innerHTML=''; addOrderItem(); openModal('orderModal');
}
function addOrderItem() {
    const div=document.getElementById('orderItems'), row=document.createElement('div'); row.className='order-item-row';
    row.innerHTML=`<select>${allProducts.map(p=>`<option value="${p.id}">${p.name}</option>`).join('')}</select>
        <input type="number" min="1" value="1" placeholder="수량"><input type="number" min="0" value="0" placeholder="단가">
        <button class="btn-remove-item" onclick="this.parentElement.remove()">×</button>`;
    div.appendChild(row);
}
async function saveOrder() {
    const items=[...document.querySelectorAll('#orderItems .order-item-row')].map(r=>{
        const sel=r.querySelector('select'),ins=r.querySelectorAll('input');
        return {product_id:+sel.value,name:sel.options[sel.selectedIndex].text,qty:+ins[0].value,unit_price:+ins[1].value};
    }).filter(i=>i.qty>0);
    if (!items.length){alert('품목을 추가해주세요.');return;}
    const body={supplier:document.getElementById('oSupplier').value,items,expected_date:document.getElementById('oDate').value||null,memo:document.getElementById('oMemo').value||null};
    const res=await fetch('/api/inventory/orders',{method:'POST',headers:H,body:JSON.stringify(body)});
    if(!res.ok){const e=await res.json();alert(Object.values(e.errors||{}).flat().join('\n')||'오류 발생');return;}
    closeModal('orderModal'); loadOrders();
}
async function updateOrder(id,status){
    if(status==='cancelled'&&!confirm('발주를 취소할까요?'))return;
    await fetch(`/api/inventory/orders/${id}`,{method:'PATCH',headers:H,body:JSON.stringify({status})}); loadOrders();
}
async function receiveOrder(id){
    if(!confirm('입고 처리하시겠습니까? 재고가 자동으로 반영됩니다.'))return;
    const res=await fetch(`/api/inventory/orders/${id}/receive`,{method:'POST',headers:H});
    if(!res.ok){const e=await res.json();alert(e.message||'오류 발생');return;} loadOrders();
}

// 초기
const validTabs = ['stock','products','movements','orders','categories'];

const initTab = validTabs.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'stock';
fetch('/api/inventory/categories').then(r=>r.json()).then(d=>{ catData=d; });
switchTab(initTab);
</script>
@endpush
