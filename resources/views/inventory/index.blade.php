@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '재고 관리 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }

    /* 탭 */
    .tab-bar { display:flex; gap:2px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:4px; margin-bottom:20px; }
    .tab-btn { flex:1; padding:10px 0; text-align:center; font-size:13px; font-weight:600; border:none; background:none; color:var(--text-muted); cursor:pointer; border-radius:8px; transition:all 0.15s; }
    .tab-btn.active { background:var(--accent); color:var(--accent-text); }
    .tab-btn:not(.active):hover { color:var(--text); background:var(--surface2); }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }

    /* 공통 */
    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:240px; }
    .toolbar input:focus { border-color:var(--accent); }
    .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .pager { display:flex; gap:4px; align-items:center; justify-content:center; margin-top:12px; flex-wrap:wrap; }
    .pager-info { font-size:12px; color:var(--text-muted); margin-right:8px; }
    .pager-btn { min-width:30px; padding:6px 8px; border:1px solid var(--border); border-radius:6px; background:var(--surface2); color:var(--text-muted); font-size:12.5px; cursor:pointer; }
    .pager-btn:hover:not(:disabled) { color:var(--text); border-color:var(--accent); }
    .pager-btn.active { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:700; }
    .pager-btn:disabled { opacity:0.4; cursor:default; }
    .cat-chip-row { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
    .cat-chip-row + .cat-chip-row { margin-top:7px; }
    .cat-chip-sub { padding-left:16px; }
    .cat-chip-arrow { color:var(--text-muted); font-size:12px; }
    .cat-chip { padding:6px 14px; border:1px solid var(--border); border-radius:16px; background:var(--surface2); color:var(--text-muted); font-size:12.5px; font-weight:600; cursor:pointer; transition:all 0.15s; }
    .cat-chip:hover { color:var(--text); border-color:var(--accent); }
    .cat-chip.active { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .cat-chip-sub .cat-chip { padding:4px 11px; font-size:12px; }
    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-sm { padding:5px 10px; font-size:12px; border-radius:6px; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .btn-danger-sm { background:none; border:none; color:var(--text-muted); font-size:12px; cursor:pointer; padding:5px 8px; }
    .btn-danger-sm:hover { color:var(--red); }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; table-layout:auto; }
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); white-space:nowrap; vertical-align:middle; }
    .data-table td.text-wrap { white-space:normal; word-break:break-word; }
    .data-table .action-cell { white-space:nowrap; }
    .data-table .action-cell button { display:inline-flex; align-items:center; vertical-align:middle; }
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
    .data-table th.text-right { text-align:center; } /* 헤더도 값과 동일하게 중앙 정렬 (시세 등 숫자 컬럼) */

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
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

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
    .cat-add-inline button { background:var(--accent); color:var(--accent-text); border:none; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; }
    .sku-preview { font-size:12px; color:var(--accent); font-weight:600; padding:8px 12px; background:var(--surface2); border-radius:6px; margin-top:4px; }
    [data-theme="light"] .tab-btn.active { color:#fff; }
    [data-theme="light"] .cat-chip.active { color:#fff; }
    [data-theme="light"] .pager-btn.active { color:#fff; }
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
    /* 모바일 카드형 리스트 (재고 현황·제품 관리) — 데스크탑에서는 숨김 */
    .mob-cards { display:none; }
    .mob-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:10px; }
    .mob-card-top { display:flex; gap:10px; align-items:flex-start; }
    .mob-card-top input[type="checkbox"] { width:17px; height:17px; margin-top:2px; accent-color:var(--accent); flex-shrink:0; }
    .mob-card-title { font-weight:700; font-size:13.5px; line-height:1.45; word-break:break-all; }
    .mob-card-sub { color:var(--text-muted); font-size:11.5px; margin-top:4px; }
    .mob-card-line { font-size:12.5px; margin-top:7px; display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
    .mob-card-actions { display:flex; gap:6px; margin-top:11px; }
    .mob-card-actions button { flex:1; padding:8px 0; font-size:12.5px; }
    [data-theme="light"] .mob-card { border-color:#c8ccd4; }
    @media (max-width: 768px) {
        #panel-stock .data-card, #panel-products .data-card { display:none; }
        .mob-cards { display:block; }
        .page-wrap { padding:16px; }
        .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
        .data-table { min-width:600px; }
        .data-table th, .data-table td { padding:10px; font-size:12px; white-space:nowrap; }
        .toolbar { flex-direction:column; align-items:stretch; }
        .toolbar input[type="text"] { width:100%; }
        .tab-bar { flex-wrap:wrap; }
        .tab-btn { font-size:12px; padding:8px 4px; }
        .modal { width:95vw; max-width:95vw; padding:16px; }
        .field-row, .field-row-3 { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">재고 관리</div>
        <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openExcelImportModal('products','제품')"><x-icon name="download" :size="14"/> 엑셀 가져오기</button>
        <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openActivityLog('Product,ProductCategory,StockMovement,PurchaseOrder',0,'재고 전체 수정 로그')"><x-icon name="clip" :size="14"/> 수정 로그</button>
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
            <input type="text" id="stockSearch" placeholder="제품명/SKU 검색" oninput="stockPage=1;loadStock()">
            <select id="stockPerPage" onchange="setStockPerPage(this.value)" title="페이지당 표시 개수">
                <option value="10">10개씩</option>
                <option value="20">20개씩</option>
                <option value="50">50개씩</option>
                <option value="100">100개씩</option>
            </select>
            <label style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:4px; cursor:pointer;">
                <input type="checkbox" id="lowStockOnly" onchange="stockPage=1;loadStock()" style="accent-color:var(--accent);"> 부족 재고만
            </label>
        </div>
        <div id="stockCatChips" style="margin-bottom:12px;"></div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>SKU</th><th>제품명</th><th>카테고리</th><th class="text-right">현재 수량</th><th class="text-right">안전재고</th><th>상태</th></tr></thead>
                <tbody id="stockBody"><tr><td colspan="6" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
        <div class="mob-cards" id="stockCards"></div>
        <div class="pager" id="stockPager"></div>
    </div>

    <!-- 제품 관리 -->
    <div class="tab-panel" id="panel-products">
        <div class="toolbar">
            <input type="text" id="productSearch" placeholder="제품명/SKU 검색" oninput="prodPage=1;loadProducts()">
            <select id="prodPerPage" onchange="setProdPerPage(this.value)" title="페이지당 표시 개수">
                <option value="10">10개씩</option>
                <option value="20">20개씩</option>
                <option value="50">50개씩</option>
                <option value="100">100개씩</option>
            </select>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12.5px;color:var(--text-muted);white-space:nowrap;">
                ⚠ 마진 경고 기준
                <input type="number" id="marginWarnInput" min="0" max="99" value="{{ $marginWarnPercent }}" style="width:54px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);font-size:12.5px;text-align:right;">
                %
                <button class="btn-outline btn-sm" onclick="saveMarginWarn()">저장</button>
            </span>
            <button class="btn-outline" onclick="refreshAllMarketPrices(this)" title="시세 URL이 등록된 제품의 판매처 가격을 순차 조회합니다">↻ 전체 시세 갱신</button>
            <button class="btn-primary" onclick="openProductModal()">+ 제품 등록</button>
        </div>
        <div id="prodCatChips" style="margin-bottom:12px;"></div>
        <div id="prodBulkBar" style="display:none; align-items:center; gap:10px; padding:10px 14px; background:rgba(212,188,150,0.08); border:1px solid var(--accent); border-radius:8px; margin-bottom:10px; flex-wrap:wrap;">
            <span style="font-size:13px; font-weight:600;">
                <span id="prodBulkCount">0</span>개 선택됨
            </span>
            <div style="display:flex; gap:6px; margin-left:auto; flex-wrap:wrap; align-items:center;">
                <button class="btn-outline btn-sm" onclick="bulkSetEstimate(true)"><x-icon name="check" :size="13"/> 견적서 노출 ON</button>
                <button class="btn-outline btn-sm" onclick="bulkSetEstimate(false)"><x-icon name="close" :size="13"/> 견적서 노출 OFF</button>
                <button class="btn-outline btn-sm" onclick="clearProdSelection()">선택 해제</button>
                <button onclick="bulkDeleteProducts()" style="background:var(--red, #dc2626); color:#fff; border:1px solid var(--red, #dc2626); padding:6px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;"><x-icon name="warning" :size="13"/> 선택 삭제</button>
            </div>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th style="width:30px;"><input type="checkbox" id="prodSelectAll" onchange="toggleSelectAllProducts(this.checked)" title="전체 선택"></th><th>SKU</th><th>제품명</th><th>카테고리</th><th class="text-right">매입가</th><th class="text-right">판매가</th><th class="text-right">마진률</th><th class="text-right">시세</th><th class="text-right">안전재고</th><th>견적</th><th></th></tr></thead>
                <tbody id="productBody"><tr><td colspan="11" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
        <div class="mob-cards" id="productCards"></div>
        <div class="pager" id="prodPager"></div>
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
            <div class="field-row" style="margin-bottom:10px;">
                <select class="field-select" id="pCat1" onchange="onCat1Change()"><option value="">1차 선택</option></select>
                <select class="field-select" id="pCat2" onchange="onCat2Change()" disabled><option value="">2차 선택</option></select>
            </div>
            <div class="field-row">
                <select class="field-select" id="pCat3" onchange="onCat3Change()" disabled><option value="">3차 선택</option></select>
                <select class="field-select" id="pCat4" disabled><option value="">4차 선택</option></select>
            </div>
            <div class="sku-preview" id="skuPreview" style="display:none;">SKU: <span id="skuText"></span> <span style="color:var(--text-muted); font-size:11px;">(2차 코드 기반 자동 생성)</span></div>
        </div>
        <div class="field-group">
            <div class="field-label">제품명 *</div>
            <input class="field-input" id="pName">
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">매입가</div>
                <input class="field-input" id="pPurchase" type="number" min="0" placeholder="비워두면 0원으로 저장">
            </div>
            <div class="field-group">
                <div class="field-label">판매가</div>
                <input class="field-input" id="pSale" type="number" min="0">
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">시세 URL — 컴퓨존</div>
            <input class="field-input" id="pMarketUrlCompuzone" placeholder="https://www.compuzone.co.kr/... 제품 페이지 주소 (선택)">
        </div>
        <div class="field-group">
            <div class="field-label">시세 URL — 피씨팩토리</div>
            <input class="field-input" id="pMarketUrlPcfactory" placeholder="https://www.pc-factory.co.kr/... 제품 페이지 주소 (선택)">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">등록한 판매처별 판매가를 매일 새벽 자동 조회해 시세 컬럼에 각각 표시합니다.</div>
        </div>
        <div class="field-group">
            <div class="field-label">안전재고 (선택 · 이하 경고)</div>
            <input class="field-input" id="pSafety" type="number" min="0" placeholder="비워두면 미사용">
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
                <select class="field-select" id="mType" onchange="onMovementTypeChange()"><option value="in">입고</option><option value="out">출고(대여)</option><option value="adjust">재고 조정</option><option value="return">반품(반납)</option></select>
            </div>
            <div class="field-group"><div class="field-label">수량 *</div><input class="field-input" id="mQty" type="number" min="1" value="1"></div>
        </div>
        <div class="field-group" id="mProjectGroup" style="display:none;">
            <div class="field-label">스튜디오(프로젝트)</div>
            <select class="field-select" id="mProject"><option value="">선택 없음 (본사/창고)</option></select>
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
let allProducts = [], catData = [], allProjects = [];
// HTML 이스케이프 — 사용자 입력(제품명/메모 등)을 innerHTML에 넣기 전 필수 (XSS 방지)
function _esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

function switchTab(name, skipHash) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        const map = {stock:'현황',products:'제품',movements:'입출고',orders:'발주',categories:'카테고리'};
        b.classList.toggle('active', b.textContent.includes(map[name]));
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+name));
    if (!skipHash) history.replaceState(null, '', '#'+name);
    localStorage.setItem('invLastTab', name); // 새로고침 후 마지막 탭 복원용
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
    renderProdCatChips(); // 카테고리 추가/삭제가 제품 필터 칩에도 반영되도록
    renderStockCatChips();
}
function renderCatTree() {
    const el = document.getElementById('catTree');
    if (!catData.length) { el.innerHTML = '<div class="empty-row">등록된 카테고리가 없습니다.</div>'; return; }
    // 루트 컨테이너에 data-parent-id="" (null)
    el.innerHTML = `<div class="cat-sortable" data-parent-id="">${catData.map(c => renderCatNode(c, 1)).join('')}</div>`;
    setupSortables();
}
function renderCatNode(cat, depth) {
    const children = cat.children || [];
    const canAddChild = depth < 4;
    // data-id, data-depth — Sortable + 이동 시 사용
    let html = `<div class="cat-lv1 cat-node" data-id="${cat.id}" data-depth="${depth}" style="${depth>1?'border:none; border-radius:0;':''}">
        <div class="cat-row">
            <span class="cat-handle" title="드래그하여 이동/정렬" style="cursor:grab; user-select:none; padding:0 4px; color:var(--text-muted);">⋮⋮</span>
            <span class="cat-code" ondblclick="startEditCat(${cat.id},'code')">${cat.code}</span>
            <span class="cat-name" ondblclick="startEditCat(${cat.id},'name')">${_esc(cat.name)}</span>
            <span class="cat-depth">${depth}차</span>
            <div class="cat-actions">
                <button class="btn-outline btn-sm" onclick="startEditCat(${cat.id},'name')" title="이름/코드 수정">✎</button>
                ${canAddChild ? `<button class="btn-outline btn-sm" onclick="showAddCat(${cat.id})">+ 하위</button>` : ''}
                <button class="btn-danger-sm" onclick="deleteCat(${cat.id})">삭제</button>
            </div>
        </div>
        <div id="catAdd-${cat.id}" style="display:none;" class="cat-add-inline">
            <input id="catName-${cat.id}" placeholder="카테고리명" style="width:120px;">
            <input id="catCode-${cat.id}" placeholder="코드" style="width:70px; text-transform:uppercase;" maxlength="10">
            <button onclick="saveCat(${cat.id})">추가</button>
            <button onclick="document.getElementById('catAdd-${cat.id}').style.display='none'" style="background:none;border:none;color:var(--text-muted);cursor:pointer;">취소</button>
        </div>
        <div class="cat-children cat-sortable" data-parent-id="${cat.id}">${children.map(c => renderCatNode(c, depth+1)).join('')}</div>
    </div>`;
    return html;
}

// === 인라인 편집 (이름/코드) ===
function startEditCat(id, field) {
    const cat = findCatById(id);
    if (!cat) return;
    const current = cat[field] || '';
    const label = field === 'code' ? '코드 (영문/숫자, 최대 10자)' : '카테고리명';
    const next = prompt(label, current);
    if (next === null) return;
    const trimmed = String(next).trim();
    if (!trimmed) { alert('값을 입력해주세요.'); return; }
    if (field === 'code' && !/^[A-Z0-9]+$/.test(trimmed.toUpperCase())) { alert('코드는 영문/숫자만 가능합니다.'); return; }
    const body = { name: cat.name, code: cat.code };
    body[field] = (field === 'code') ? trimmed.toUpperCase() : trimmed;
    fetch(`/api/inventory/categories/${id}`, { method:'PATCH', headers:H, body:JSON.stringify(body) })
        .then(async res => {
            if (!res.ok) { const e = await res.json(); alert(e.message || '수정 실패'); return; }
            loadCategories();
        });
}
function findCatById(id, list) {
    const arr = list || catData;
    for (const c of arr) {
        if (c.id === id) return c;
        const found = findCatById(id, c.children || []);
        if (found) return found;
    }
    return null;
}

// === 드래그 정렬/이동 (SortableJS) ===
function setupSortables() {
    if (typeof Sortable === 'undefined') return;
    document.querySelectorAll('.cat-sortable').forEach(container => {
        new Sortable(container, {
            group: 'categories',         // 모든 .cat-sortable이 같은 그룹 → 부모 간 이동 허용
            handle: '.cat-handle',
            animation: 150,
            fallbackOnBody: true,
            invertSwap: true,
            onEnd: handleCatDrop,
        });
    });
}
async function handleCatDrop(evt) {
    const movedId = +evt.item.dataset.id;
    const oldParentId = evt.from.dataset.parentId === '' ? null : +evt.from.dataset.parentId;
    const newParentId = evt.to.dataset.parentId === '' ? null : +evt.to.dataset.parentId;
    const newIndex = evt.newIndex;

    try {
        if (oldParentId !== newParentId) {
            // 다른 부모로 이동
            const res = await fetch(`/api/inventory/categories/${movedId}/move`, {
                method: 'POST', headers: H,
                body: JSON.stringify({ new_parent_id: newParentId, sort_order: newIndex }),
            });
            if (!res.ok) { const e = await res.json(); alert(e.message || '이동 실패'); loadCategories(); return; }
        }
        // 새 부모 내 형제 순서 재배치
        const orderedIds = Array.from(evt.to.children)
            .filter(el => el.classList.contains('cat-node'))
            .map(el => +el.dataset.id);
        await fetch('/api/inventory/categories/reorder', {
            method: 'POST', headers: H,
            body: JSON.stringify({ parent_id: newParentId, ordered_ids: orderedIds }),
        });
        loadCategories();
    } catch(e) { alert('네트워크 오류'); loadCategories(); }
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
    const v4 = document.getElementById('pCat4')?.value;
    const v3 = document.getElementById('pCat3').value;
    const v2 = document.getElementById('pCat2').value;
    const v1 = document.getElementById('pCat1').value;
    return v4 || v3 || v2 || v1 || null;
}
function updateSkuPreview() {
    const catId = getSelectedCategoryId();
    const preview = document.getElementById('skuPreview');
    if (!catId) { preview.style.display = 'none'; return; }
    // SKU 베이스 = 2차 코드 (없으면 1차 폴백)
    const base = getSkuBaseCode(+catId);
    if (base) {
        document.getElementById('skuText').textContent = base + '-XXX';
        preview.style.display = 'block';
    }
}
// SKU 베이스 코드 — 항상 2차 카테고리 코드 (1차만 있으면 1차 폴백)
function getSkuBaseCode(catId) {
    for (const c1 of catData) {
        if (c1.id === catId) return c1.code; // 1차 직접 선택
        for (const c2 of (c1.children||[])) {
            if (c2.id === catId) return c2.code;
            for (const c3 of (c2.children||[])) {
                if (c3.id === catId) return c2.code; // 3차 → 2차 코드
                for (const c4 of (c3.children||[])) {
                    if (c4.id === catId) return c2.code; // 4차 → 2차 코드
                }
            }
        }
    }
    return null;
}
function populateCatDropdowns(editCatId) {
    const s1 = document.getElementById('pCat1');
    s1.innerHTML = '<option value="">1차 선택</option>' + catData.map(c=>`<option value="${c.id}">${_esc(c.name)}</option>`).join('');
    ['pCat2','pCat3','pCat4'].forEach((id, idx) => {
        const sel = document.getElementById(id);
        if (sel) { sel.innerHTML = `<option value="">${idx+2}차 선택</option>`; sel.disabled = true; }
    });
    if (editCatId) {
        for (const c1 of catData) {
            if (c1.id === editCatId) { s1.value = c1.id; onCat1Change(); break; }
            let matched = false;
            for (const c2 of (c1.children||[])) {
                if (c2.id === editCatId) { s1.value = c1.id; onCat1Change(); document.getElementById('pCat2').value = c2.id; onCat2Change(); matched = true; break; }
                for (const c3 of (c2.children||[])) {
                    if (c3.id === editCatId) {
                        s1.value = c1.id; onCat1Change();
                        document.getElementById('pCat2').value = c2.id; onCat2Change();
                        document.getElementById('pCat3').value = c3.id; onCat3Change();
                        matched = true; break;
                    }
                    for (const c4 of (c3.children||[])) {
                        if (c4.id === editCatId) {
                            s1.value = c1.id; onCat1Change();
                            document.getElementById('pCat2').value = c2.id; onCat2Change();
                            document.getElementById('pCat3').value = c3.id; onCat3Change();
                            document.getElementById('pCat4').value = c4.id;
                            matched = true; break;
                        }
                    }
                    if (matched) break;
                }
                if (matched) break;
            }
            if (matched) break;
        }
    }
    updateSkuPreview();
}
function onCat1Change() {
    const c1 = catData.find(c=>c.id===+document.getElementById('pCat1').value);
    const s2 = document.getElementById('pCat2');
    const s3 = document.getElementById('pCat3');
    const s4 = document.getElementById('pCat4');
    s3.innerHTML = '<option value="">3차 선택</option>'; s3.disabled = true;
    if (s4) { s4.innerHTML = '<option value="">4차 선택</option>'; s4.disabled = true; }
    if (c1 && c1.children?.length) {
        s2.innerHTML = '<option value="">2차 선택</option>' + c1.children.map(c=>`<option value="${c.id}">${_esc(c.name)}</option>`).join('');
        s2.disabled = false;
    } else { s2.innerHTML = '<option value="">2차 없음</option>'; s2.disabled = true; }
    updateSkuPreview();
}
function onCat2Change() {
    const c1 = catData.find(c=>c.id===+document.getElementById('pCat1').value);
    const c2 = c1?.children?.find(c=>c.id===+document.getElementById('pCat2').value);
    const s3 = document.getElementById('pCat3');
    const s4 = document.getElementById('pCat4');
    if (s4) { s4.innerHTML = '<option value="">4차 선택</option>'; s4.disabled = true; }
    if (c2 && c2.children?.length) {
        s3.innerHTML = '<option value="">3차 선택</option>' + c2.children.map(c=>`<option value="${c.id}">${_esc(c.name)}</option>`).join('');
        s3.disabled = false;
    } else { s3.innerHTML = '<option value="">3차 없음</option>'; s3.disabled = true; }
    updateSkuPreview();
}
function onCat3Change() {
    const c1 = catData.find(c=>c.id===+document.getElementById('pCat1').value);
    const c2 = c1?.children?.find(c=>c.id===+document.getElementById('pCat2').value);
    const c3 = c2?.children?.find(c=>c.id===+document.getElementById('pCat3').value);
    const s4 = document.getElementById('pCat4');
    if (!s4) return;
    if (c3 && c3.children?.length) {
        s4.innerHTML = '<option value="">4차 선택</option>' + c3.children.map(c=>`<option value="${c.id}">${_esc(c.name)}</option>`).join('');
        s4.disabled = false;
    } else { s4.innerHTML = '<option value="">4차 없음</option>'; s4.disabled = true; }
    updateSkuPreview();
}

// === 재고 현황 ===
let stockPage = 1;
let stockPerPage = parseInt(localStorage.getItem('invStockPerPage'), 10) || 20;

function setStockPerPage(v) {
    stockPerPage = parseInt(v, 10) || 20;
    localStorage.setItem('invStockPerPage', stockPerPage);
    stockPage = 1;
    loadStock();
}

function goStockPage(n) {
    stockPage = n;
    loadStock();
}

async function loadStock() {
    const search = document.getElementById('stockSearch').value;
    const low = document.getElementById('lowStockOnly').checked;
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (low) params.set('low_stock', '1');
    const catId = stockCatFilterId();
    if (catId) params.set('category_id', catId); // 하위 카테고리 포함 (서버 필터)
    params.set('per_page', stockPerPage);
    params.set('page', stockPage);
    const res = await fetch('/api/inventory/stock?'+params);
    const payload = await res.json();
    const data = payload.data;
    // 페이지 범위를 벗어나면 마지막 페이지로 클램프
    if (!data.length && payload.total > 0 && stockPage > 1) {
        stockPage = payload.last_page;
        return loadStock();
    }
    renderPagerInto('stockPager', payload, 'goStockPage');
    const tb = document.getElementById('stockBody');
    const cards = document.getElementById('stockCards');
    if (!data.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-row">데이터가 없습니다.</td></tr>';
        cards.innerHTML = '<div class="empty-row">데이터가 없습니다.</div>';
        return;
    }
    tb.innerHTML = data.map(p => `<tr>
        <td class="text-muted">${_esc(p.sku)}</td><td>${_esc(p.name)}</td><td class="text-muted">${_esc(p.category)||'-'}</td>
        <td class="text-right ${p.is_low?'text-warn':''}" style="font-weight:600;">${p.quantity}</td>
        <td class="text-right text-muted">${p.safety_stock||'-'}</td>
        <td>${p.is_low?'<span class="badge badge-low">부족</span>':'<span class="badge badge-ok">정상</span>'}</td>
    </tr>`).join('');
    // 모바일 카드 (768px 이하에서 표시)
    cards.innerHTML = data.map(p => `<div class="mob-card">
        <div class="mob-card-title">${_esc(p.name)}</div>
        <div class="mob-card-sub">${_esc(p.sku)}${p.category ? ' · '+_esc(p.category) : ''}</div>
        <div class="mob-card-line">수량 <b class="${p.is_low?'text-warn':''}">${p.quantity}</b> · 안전재고 ${p.safety_stock||'-'} ${p.is_low?'<span class="badge badge-low">부족</span>':'<span class="badge badge-ok">정상</span>'}</div>
    </div>`).join('');
}

// === 마진률 경고 ===
let marginWarnPercent = {{ (int) $marginWarnPercent }};

function marginCellHtml(p) {
    const buy = Number(p.purchase_price), sell = Number(p.sale_price);
    if (!(buy > 0) || !(sell > 0)) return '<span class="text-muted">-</span>';
    const pct = (sell - buy) / sell * 100;
    const txt = (Math.round(pct * 10) / 10) + '%';
    if (pct < marginWarnPercent) return `<span class="text-warn" style="font-weight:600;" title="마진률이 경고 기준(${marginWarnPercent}%) 미만입니다">⚠ ${txt}</span>`;
    return `<span>${txt}</span>`;
}

async function saveMarginWarn() {
    const input = document.getElementById('marginWarnInput');
    const percent = parseInt(input.value, 10);
    if (isNaN(percent) || percent < 0 || percent > 99) { alert('0~99 사이 숫자를 입력해주세요.'); return; }
    try {
        const res = await fetch('/api/inventory/margin-threshold', { method:'POST', headers:H, body: JSON.stringify({ percent }) });
        if (!res.ok) { const e = await res.json(); alert(e.message || '저장 실패'); return; }
        marginWarnPercent = percent;
        loadProducts();
    } catch(e) { alert('네트워크 오류'); }
}

// === 컴퓨존 시세 ===
const MARKET_VENDOR_LABELS = { compuzone: '컴퓨존', pcfactory: '팩토리' };

// 판매처 한 곳의 시세 한 줄 (가격 + 매입가 대비 ▲▼ + 오류 ⚠)
function marketVendorLineHtml(p, m) {
    const label = MARKET_VENDOR_LABELS[m.vendor] || m.vendor;
    const parts = [`<span class="text-muted" style="font-size:11px;">${label}</span>`];
    if (m.price != null) {
        let diffHtml = '';
        if (p.purchase_price > 0) {
            const diff = (m.price - p.purchase_price) / p.purchase_price * 100;
            const pct = Math.round(Math.abs(diff) * 10) / 10;
            if (diff > 0) diffHtml = ` <span style="color:var(--red,#dc2626);font-size:11px;">▲${pct}%</span>`;
            else if (diff < 0) diffHtml = ` <span style="color:var(--blue,#3b82f6);font-size:11px;">▼${pct}%</span>`;
        }
        const checked = m.checked_at ? fmtTime(m.checked_at) : '-';
        parts.push(`<span title="매입가 대비 · 확인: ${checked}">${fmt(m.price)}${diffHtml}</span>`);
    } else {
        parts.push('<span class="text-muted">미조회</span>');
    }
    if (m.error) parts.push(`<span title="${_esc(m.error)}" style="cursor:help;">⚠</span>`);
    return `<div style="white-space:nowrap;">${parts.join(' ')}</div>`;
}

function marketPriceCellHtml(p) {
    const rows = p.market_prices || [];
    if (!rows.length) return '<span class="text-muted">-</span>';
    const lines = rows.map(m => marketVendorLineHtml(p, m)).join('');
    return `<div style="display:inline-flex;align-items:center;gap:7px;"><div>${lines}</div>`
        + `<button class="btn-outline btn-sm" style="padding:2px 7px;" title="등록된 판매처 시세 지금 갱신" onclick="refreshMarketPrice(${p.id}, this)">↻</button></div>`;
}

async function refreshMarketPrice(id, btn) {
    if (btn) { btn.disabled = true; btn.textContent = '…'; }
    try {
        const res = await fetch(`/api/inventory/products/${id}/refresh-market-price`, { method:'POST', headers:H });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) alert(data.message || `시세 조회 실패 (HTTP ${res.status})`);
    } catch(e) { alert('네트워크 오류'); }
    loadProducts();
}

// 전체 시세 갱신 — PHP 타임아웃 회피를 위해 브라우저에서 제품별로 순차 호출
async function refreshAllMarketPrices(btn) {
    // 페이징과 무관하게 현재 검색/카테고리 조건의 전체 제품 대상
    const qs = prodFilterParams();
    const listRes = await fetch('/api/inventory/products' + (qs.toString() ? '?'+qs.toString() : ''));
    const targets = (await listRes.json()).filter(p => (p.market_prices || []).length);
    if (!targets.length) return alert('시세 URL이 등록된 제품이 없습니다.\n제품 수정에서 컴퓨존/피씨팩토리 제품 페이지 주소를 먼저 등록해주세요.');
    if (!confirm(`${targets.length}개 제품의 시세를 갱신할까요?\n(순차 조회라 다소 시간이 걸립니다)`)) return;
    const origText = btn.textContent;
    btn.disabled = true;
    let fail = 0;
    for (let i = 0; i < targets.length; i++) {
        btn.textContent = `갱신 중 ${i+1}/${targets.length}`;
        try {
            const res = await fetch(`/api/inventory/products/${targets[i].id}/refresh-market-price`, { method:'POST', headers:H });
            if (!res.ok) fail++;
        } catch(e) { fail++; }
    }
    btn.disabled = false;
    btn.textContent = origText;
    await loadProducts();
    if (fail) alert(`시세 갱신 완료 — ${fail}건 실패 (시세 컬럼의 ⚠ 아이콘에 사유가 표시됩니다)`);
}

// === 제품 관리 ===
const prodSelection = new Set();

// === 카테고리 필터 — 계단식 칩 드릴다운 (1차 → 2차 → 3차 → 4차) ===
// prodCatPath = 선택 경로 id 배열 (예: [CPU, 인텔]). 필터는 마지막 요소(하위 포함).
let prodCatPath = [];
try { prodCatPath = JSON.parse(localStorage.getItem('invProdCatPath')) || []; } catch(_) { prodCatPath = []; }
// 구버전 단일 필터 키 마이그레이션
const _legacyCatFilter = parseInt(localStorage.getItem('invProdCatFilter'), 10);
if (_legacyCatFilter && !prodCatPath.length) { prodCatPath = [_legacyCatFilter]; }
localStorage.removeItem('invProdCatFilter');

function saveProdCatPath() {
    if (prodCatPath.length) localStorage.setItem('invProdCatPath', JSON.stringify(prodCatPath));
    else localStorage.removeItem('invProdCatPath');
}

function prodCatFilterId() {
    return prodCatPath.length ? prodCatPath[prodCatPath.length - 1] : null;
}

// 공용: 계단식 카테고리 칩 렌더 — 경로(path)는 삭제된 카테고리 발견 시 in-place 절단됨
function renderCatChipsInto(elId, path, setter) {
    const el = document.getElementById(elId);
    if (!el) return;
    const chain = [];
    let level = catData;
    for (const id of path) {
        const node = (level || []).find(c => c.id === id);
        if (!node) break;
        chain.push(node);
        level = node.children || [];
    }
    if (chain.length !== path.length) path.length = chain.length;
    let html = '<div class="cat-chip-row">'
        + `<button class="cat-chip ${!path.length?'active':''}" onclick="${setter}(0,null)">전체</button>`
        + catData.map(c => `<button class="cat-chip ${path[0]===c.id?'active':''}" onclick="${setter}(0,${c.id})">${_esc(c.name)}</button>`).join('')
        + '</div>';
    // 선택된 노드마다 하위가 있으면 다음 행 노출
    chain.forEach((node, i) => {
        const children = node.children || [];
        if (!children.length) return;
        html += '<div class="cat-chip-row cat-chip-sub">'
            + '<span class="cat-chip-arrow">└</span>'
            + `<button class="cat-chip ${path.length===i+1?'active':''}" onclick="${setter}(${i+1},null)">${_esc(node.name)} 전체</button>`
            + children.map(c => `<button class="cat-chip ${path[i+1]===c.id?'active':''}" onclick="${setter}(${i+1},${c.id})">${_esc(c.name)}</button>`).join('')
            + '</div>';
    });
    el.innerHTML = html;
}

function renderProdCatChips() {
    renderCatChipsInto('prodCatChips', prodCatPath, 'setProdCatPath');
    saveProdCatPath(); // 절단됐을 수 있으니 재저장
}

function setProdCatPath(depth, id) {
    prodCatPath = prodCatPath.slice(0, depth);
    if (id) prodCatPath.push(id);
    saveProdCatPath();
    prodPage = 1;
    renderProdCatChips();
    loadProducts();
}

// === 재고 현황 탭 카테고리 필터 ===
let stockCatPath = [];
try { stockCatPath = JSON.parse(localStorage.getItem('invStockCatPath')) || []; } catch(_) { stockCatPath = []; }

function saveStockCatPath() {
    if (stockCatPath.length) localStorage.setItem('invStockCatPath', JSON.stringify(stockCatPath));
    else localStorage.removeItem('invStockCatPath');
}

function stockCatFilterId() {
    return stockCatPath.length ? stockCatPath[stockCatPath.length - 1] : null;
}

function renderStockCatChips() {
    renderCatChipsInto('stockCatChips', stockCatPath, 'setStockCatPath');
    saveStockCatPath();
}

function setStockCatPath(depth, id) {
    stockCatPath = stockCatPath.slice(0, depth);
    if (id) stockCatPath.push(id);
    saveStockCatPath();
    stockPage = 1;
    renderStockCatChips();
    loadStock();
}

// === 페이징 ===
let prodPage = 1;
let prodPerPage = parseInt(localStorage.getItem('invProdPerPage'), 10) || 20;

function setProdPerPage(v) {
    prodPerPage = parseInt(v, 10) || 20;
    localStorage.setItem('invProdPerPage', prodPerPage);
    prodPage = 1;
    loadProducts();
}

function goProdPage(n) {
    prodPage = n;
    loadProducts();
}

// 공용: 페이저 렌더
function renderPagerInto(elId, p, goFn) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (!p.total) { el.innerHTML = ''; return; }
    const cur = p.current_page, last = p.last_page;
    let start = Math.max(1, cur - 3);
    const end = Math.min(last, start + 6);
    start = Math.max(1, end - 6);
    let html = `<span class="pager-info">총 ${p.total.toLocaleString()}개</span>`;
    html += `<button class="pager-btn" ${cur === 1 ? 'disabled' : ''} onclick="${goFn}(${cur - 1})">‹</button>`;
    for (let i = start; i <= end; i++) {
        html += `<button class="pager-btn ${i === cur ? 'active' : ''}" onclick="${goFn}(${i})">${i}</button>`;
    }
    html += `<button class="pager-btn" ${cur === last ? 'disabled' : ''} onclick="${goFn}(${cur + 1})">›</button>`;
    el.innerHTML = html;
}

function renderProdPager(p) { renderPagerInto('prodPager', p, 'goProdPage'); }

// 현재 검색/카테고리 필터 조건의 쿼리스트링 (페이징 제외)
function prodFilterParams() {
    const qs = new URLSearchParams();
    const search = document.getElementById('productSearch').value;
    if (search) qs.set('search', search);
    const catId = prodCatFilterId();
    if (catId) qs.set('category_id', catId); // 하위 카테고리 포함 (서버 필터)
    return qs;
}

async function loadProducts() {
    const qs = prodFilterParams();
    qs.set('per_page', prodPerPage);
    qs.set('page', prodPage);
    const res = await fetch('/api/inventory/products?'+qs.toString());
    const payload = await res.json();
    allProducts = payload.data; // 현재 페이지 제품만
    // 삭제 등으로 현재 페이지가 범위를 벗어나면 마지막 페이지로 클램프
    if (!allProducts.length && payload.total > 0 && prodPage > 1) {
        prodPage = payload.last_page;
        return loadProducts();
    }
    renderProdPager(payload);
    const tb = document.getElementById('productBody');
    const cards = document.getElementById('productCards');
    if (!allProducts.length) {
        const filtered = document.getElementById('productSearch').value || prodCatFilterId();
        const msg = filtered ? '조건에 맞는 제품이 없습니다.' : '등록된 제품이 없습니다.';
        tb.innerHTML = `<tr><td colspan="11" class="empty-row">${msg}</td></tr>`;
        cards.innerHTML = `<div class="empty-row">${msg}</div>`;
        clearProdSelection();
        return;
    }
    // 화면에서 사라진 ID는 선택에서 제거
    const visibleIds = new Set(allProducts.map(p => p.id));
    [...prodSelection].forEach(id => { if (!visibleIds.has(id)) prodSelection.delete(id); });
    tb.innerHTML = allProducts.map(p => `<tr data-pid="${p.id}">
        <td><input type="checkbox" class="prod-row-check" data-id="${p.id}" ${prodSelection.has(p.id)?'checked':''} onchange="toggleProductSelection(${p.id}, this.checked)"></td>
        <td class="text-muted">${_esc(p.sku)}</td>
        <td class="text-wrap">${_esc(p.name)}</td>
        <td class="text-muted text-wrap">${p.category||'-'}</td>
        <td class="text-right">${fmt(p.purchase_price)}</td>
        <td class="text-right">${fmt(p.sale_price)}</td>
        <td class="text-right">${marginCellHtml(p)}</td>
        <td class="text-right">${marketPriceCellHtml(p)}</td>
        <td class="text-right">${p.safety_stock||'-'}</td>
        <td>${p.show_in_estimate ? '<span class="badge badge-ok">노출</span>' : ''}</td>
        <td class="action-cell">
            <button class="btn-outline btn-sm" onclick="if(typeof openActivityLog==='function')openActivityLog('Product',${p.id},'${_esc(p.name.replace(/'/g,"\\'"))} 수정 로그');else alert('로그 기능을 사용할 수 없습니다.');">📋</button>
            <button class="btn-outline btn-sm" onclick='editProduct(${p.id})'>수정</button>
            <button class="btn-danger-sm" onclick="deleteProduct(${p.id})">삭제</button>
        </td>
    </tr>`).join('');
    // 모바일 카드 (768px 이하에서 표시) — 체크박스/버튼은 테이블과 동일 핸들러 공유
    cards.innerHTML = allProducts.map(p => `<div class="mob-card" data-pid="${p.id}">
        <div class="mob-card-top">
            <input type="checkbox" class="prod-row-check" data-id="${p.id}" ${prodSelection.has(p.id)?'checked':''} onchange="toggleProductSelection(${p.id}, this.checked)">
            <div>
                <div class="mob-card-title">${_esc(p.name)}</div>
                <div class="mob-card-sub">${_esc(p.sku)}${p.category ? ' · '+_esc(p.category) : ''}${p.safety_stock ? ' · 안전재고 '+p.safety_stock : ''}${p.show_in_estimate ? ' · <span class="badge badge-ok">노출</span>' : ''}</div>
            </div>
        </div>
        <div class="mob-card-line">매입 ${fmt(p.purchase_price)} → 판매 ${fmt(p.sale_price)} · ${marginCellHtml(p)}</div>
        <div class="mob-card-line">시세 ${marketPriceCellHtml(p)}</div>
        <div class="mob-card-actions">
            <button class="btn-outline btn-sm" onclick="if(typeof openActivityLog==='function')openActivityLog('Product',${p.id},'${_esc(p.name.replace(/'/g,"\\'"))} 수정 로그');else alert('로그 기능을 사용할 수 없습니다.');">📋 로그</button>
            <button class="btn-outline btn-sm" onclick='editProduct(${p.id})'>수정</button>
            <button class="btn-danger-sm" onclick="deleteProduct(${p.id})">삭제</button>
        </div>
    </div>`).join('');
    updateProdBulkBar();
}

// === 일괄 선택/액션 ===
function toggleProductSelection(id, checked) {
    if (checked) prodSelection.add(id); else prodSelection.delete(id);
    updateProdBulkBar();
}
function toggleSelectAllProducts(checked) {
    if (checked) allProducts.forEach(p => prodSelection.add(p.id));
    else prodSelection.clear();
    document.querySelectorAll('.prod-row-check').forEach(cb => { cb.checked = checked; });
    updateProdBulkBar();
}
function clearProdSelection() {
    prodSelection.clear();
    document.querySelectorAll('.prod-row-check').forEach(cb => { cb.checked = false; });
    const selAll = document.getElementById('prodSelectAll'); if (selAll) selAll.checked = false;
    updateProdBulkBar();
}
function updateProdBulkBar() {
    const bar = document.getElementById('prodBulkBar');
    const cnt = document.getElementById('prodBulkCount');
    if (!bar) return;
    if (prodSelection.size > 0) {
        bar.style.display = 'flex';
        cnt.textContent = prodSelection.size;
    } else {
        bar.style.display = 'none';
    }
    // 전체 선택 체크박스 상태 동기화
    const selAll = document.getElementById('prodSelectAll');
    if (selAll && allProducts.length) {
        selAll.checked = allProducts.every(p => prodSelection.has(p.id));
    }
}
async function bulkSetEstimate(show) {
    if (!prodSelection.size) return;
    const ids = [...prodSelection];
    if (!confirm(`선택된 ${ids.length}개 제품의 견적서 노출을 ${show?'ON':'OFF'}로 변경합니다.`)) return;
    const res = await fetch('/api/inventory/products/bulk-estimate', {
        method:'POST', headers:H,
        body: JSON.stringify({ ids, show_in_estimate: show }),
    });
    if (!res.ok) { const e = await res.json().catch(()=>({})); alert(e.message || '변경 실패'); return; }
    await loadProducts();
}
async function bulkDeleteProducts() {
    if (!prodSelection.size) return;
    const ids = [...prodSelection];

    // 1단계: 어떤 제품이 삭제되는지 미리보기 + 1차 확인
    const names = allProducts.filter(p => prodSelection.has(p.id)).map(p => `• ${p.sku} ${p.name}`).slice(0, 10);
    const more = prodSelection.size > 10 ? `\n... 외 ${prodSelection.size - 10}개` : '';
    const preview = names.join('\n') + more;

    if (!confirm(`⚠️ 선택한 ${ids.length}개 제품을 삭제합니다.\n\n${preview}\n\n계속하시겠습니까?`)) return;

    // 2단계: 다시 한 번 확인 (되돌릴 수 없음 강조)
    if (!confirm(`정말 삭제하시겠습니까?\n\n❌ 삭제된 제품은 새 견적서에 추가할 수 없습니다.\n✓ 이미 작성된 견적서의 항목 정보(이름·금액)는 그대로 보존됩니다.`)) return;

    const res = await fetch('/api/inventory/products/bulk-delete', {
        method:'POST', headers:H,
        body: JSON.stringify({ ids }),
    });
    if (!res.ok) { const e = await res.json().catch(()=>({})); alert(e.message || '삭제 실패'); return; }
    prodSelection.clear();
    await loadProducts();
}
async function openProductModal(p) {
    if (!catData.length) await loadCategories();
    document.getElementById('productModalTitle').textContent = p ? '제품 수정' : '제품 등록';
    document.getElementById('pEditId').value = p ? p.id : '';
    document.getElementById('pName').value = p ? p.name : '';
    document.getElementById('pPurchase').value = p ? (p.purchase_price||'') : '';
    document.getElementById('pSale').value = p ? (p.sale_price||'') : '';
    const mps = (p && p.market_prices) || [];
    document.getElementById('pMarketUrlCompuzone').value = mps.find(m=>m.vendor==='compuzone')?.url || '';
    document.getElementById('pMarketUrlPcfactory').value = mps.find(m=>m.vendor==='pcfactory')?.url || '';
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
// 필드명 → 한글 라벨 매핑 (서버 validation 오류 메시지 표시용)
const PRODUCT_FIELD_LABELS = {
    name: '제품명',
    category_id: '카테고리',
    purchase_price: '매입가',
    sale_price: '판매가',
    market_price_url_compuzone: '시세 URL(컴퓨존)',
    market_price_url_pcfactory: '시세 URL(피씨팩토리)',
    safety_stock: '안전재고',
    memo: '메모',
    show_in_estimate: '견적서 노출',
    sku: 'SKU',
};

async function saveProduct() {
    const id = document.getElementById('pEditId').value;
    const categoryId = getSelectedCategoryId();
    const name = document.getElementById('pName').value.trim();

    // 클라이언트 사전 검증 — 어떤 필드가 비었는지 명확히 알려줌
    const missing = [];
    if (!name) missing.push('• 제품명');
    if (!categoryId) missing.push('• 카테고리');
    if (missing.length) {
        return alert('다음 필수값이 누락되었습니다:\n\n' + missing.join('\n'));
    }

    const body = {
        name,
        category_id: +categoryId,
        // 매입가는 비워두면 0으로 자동 저장
        purchase_price: parseInt(document.getElementById('pPurchase').value, 10) || 0,
        sale_price: document.getElementById('pSale').value || null,
        market_price_url_compuzone: document.getElementById('pMarketUrlCompuzone').value.trim() || null,
        market_price_url_pcfactory: document.getElementById('pMarketUrlPcfactory').value.trim() || null,
        safety_stock: document.getElementById('pSafety').value || null,
        memo: document.getElementById('pMemo').value || null,
        show_in_estimate: document.getElementById('pEstimate').checked,
    };
    const url = id ? `/api/inventory/products/${id}` : '/api/inventory/products';
    const method = id ? 'PATCH' : 'POST';

    let res;
    try {
        res = await fetch(url, { method, headers:H, body:JSON.stringify(body) });
    } catch(networkErr) {
        return alert('네트워크 오류:\n' + networkErr.message);
    }

    if (res.ok) {
        closeModal('productModal');
        loadProducts();
        return;
    }

    // 오류 응답 — 필드별 한글 라벨로 어떤 값이 문제인지 명확히 표시
    let payload = {};
    try { payload = await res.json(); } catch(_) {}

    if (payload.errors && typeof payload.errors === 'object') {
        const lines = Object.entries(payload.errors).map(([field, msgs]) => {
            const label = PRODUCT_FIELD_LABELS[field] || field;
            const detail = Array.isArray(msgs) ? msgs.join(', ') : String(msgs);
            const value = body[field];
            const valueShown = value === null || value === '' ? '(비어있음)' : JSON.stringify(value);
            return `• [${label}] ${detail}\n   입력값: ${valueShown}`;
        });
        return alert(`저장 실패 (${res.status}) — 다음 값이 잘못되었습니다:\n\n` + lines.join('\n\n'));
    }

    if (payload.message) {
        let detail = `저장 실패 (${res.status})\n\n${payload.message}`;
        if (payload.exception) detail += `\n\n예외: ${payload.exception}`;
        if (payload.file) detail += `\n위치: ${payload.file}`;
        if (payload.sku_generated) detail += `\nSKU: ${payload.sku_generated}`;
        if (payload.category) detail += `\n카테고리: ${JSON.stringify(payload.category)}`;
        return alert(detail);
    }

    alert(`저장 실패: HTTP ${res.status}\n\n응답 본문을 확인할 수 없습니다.`);
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
        <td class="text-muted">${_esc(m.user?.display_name)||'-'}</td>
        <td class="text-muted">${_esc(m.memo)||'-'}</td>
    </tr>`).join('');
}
async function openMovementModal() {
    // allProducts는 현재 페이지만 담고 있으므로 모달용 전체 목록은 별도 조회
    const r = await fetch('/api/inventory/products');
    const movProducts = await r.json();
    if (!allProjects.length) { const pr = await fetch('/api/inventory/projects'); allProjects = await pr.json(); }
    document.getElementById('mProduct').innerHTML = movProducts.map(p=>`<option value="${p.id}">${_esc(p.name)} (${_esc(p.sku)})</option>`).join('');
    document.getElementById('mProject').innerHTML = '<option value="">선택 없음 (본사/창고)</option>' + allProjects.map(p=>`<option value="${p.id}">${_esc(p.name)}</option>`).join('');
    document.getElementById('mType').value='in'; document.getElementById('mQty').value=1; document.getElementById('mMemo').value='';
    document.getElementById('mProject').value='';
    onMovementTypeChange();
    openModal('movementModal');
}
function onMovementTypeChange() {
    const t = document.getElementById('mType').value;
    document.getElementById('mProjectGroup').style.display = (t==='out' || t==='return') ? 'block' : 'none';
}
async function saveMovement() {
    const projectId = document.getElementById('mProject').value;
    const body = {
        product_id:+document.getElementById('mProduct').value,
        movement_type:document.getElementById('mType').value,
        quantity:+document.getElementById('mQty').value,
        project_id: projectId ? +projectId : null,
        memo:document.getElementById('mMemo').value||null,
    };
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
    row.innerHTML=`<select>${allProducts.map(p=>`<option value="${p.id}">${_esc(p.name)}</option>`).join('')}</select>
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

// 우선순위: URL 해시 > 마지막 본 탭(localStorage) > 재고 현황
const savedTab = localStorage.getItem('invLastTab');
const initTab = validTabs.includes(location.hash.slice(1)) ? location.hash.slice(1)
    : (validTabs.includes(savedTab) ? savedTab : 'stock');
fetch('/api/inventory/categories').then(r=>r.json()).then(d=>{ catData=d; renderProdCatChips(); renderStockCatChips(); });
document.getElementById('prodPerPage').value = String(prodPerPage);
document.getElementById('stockPerPage').value = String(stockPerPage);
switchTab(initTab);
</script>
@endpush
