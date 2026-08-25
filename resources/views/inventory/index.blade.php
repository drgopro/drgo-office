@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '재고 관리 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1480px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }
    .inv-menu-item { display:block; width:100%; text-align:left; background:none; border:none; padding:10px 14px; font-size:13px; color:var(--text); cursor:pointer; white-space:nowrap; }
    .inv-menu-item:hover { background:var(--surface2); }
    #groupOnlyBtn.on { background:var(--accent); color:#fff; border-color:var(--accent); }

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
    .btn-danger-sm { background:var(--red, #dc2626); border:1px solid var(--red, #dc2626); color:#fff; font-size:12px; font-weight:600; cursor:pointer; padding:5px 10px; border-radius:6px; }
    .btn-danger-sm:hover { filter:brightness(0.9); }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; table-layout:auto; }
    /* 밀도 압축 — 가로 스크롤 최소화 (패딩/폰트 축소) */
    .data-table th { font-size:10.5px; color:var(--text-muted); font-weight:600; text-align:center; padding:9px 8px; background:var(--surface2); border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { font-size:12.5px; padding:9px 8px; border-bottom:1px solid var(--border); white-space:nowrap; vertical-align:middle; }
    .sku-cell { font-size:11px !important; letter-spacing:-0.2px; }
    /* 전체 편집 모드 인라인 입력폼 */
    .pe-input { width:100%; min-width:120px; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 9px; font-size:12.5px; color:var(--text); outline:none; }
    .pe-input:focus { border-color:var(--accent); }
    .pe-input.pe-num { min-width:80px; max-width:112px; text-align:right; -moz-appearance:textfield; appearance:textfield; }
    .pe-input.pe-num::-webkit-outer-spin-button, .pe-input.pe-num::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .pe-input.pe-num.pe-sm { min-width:0; width:56px; }
    .pe-input.pe-invalid { border-color:var(--red, #dc2626); }
    /* 편집 모드에서는 입력창이 있는 칸 아무 곳이나 클릭해도 수정 시작 */
    #productBody td:has(.pe-input) { cursor:text; }
    .action-cell button { padding:4px 7px !important; font-size:11.5px !important; }
    .data-table td.text-wrap { white-space:normal; word-break:keep-all; overflow-wrap:break-word; }
    /* 제품명/카테고리 최소 폭 — 좁은 해상도에서 한 글자씩 세로로 깨지는 대신 표가 가로 스크롤되도록 */
    #panel-products .data-table th:nth-child(3), #panel-products .data-table td:nth-child(3) { min-width:170px; }
    #panel-products .data-table th:nth-child(4), #panel-products .data-table td:nth-child(4) { min-width:76px; white-space:normal; word-break:keep-all; }
    .data-table .action-cell { white-space:nowrap; }
    .data-table .action-cell button { display:inline-flex; align-items:center; vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .empty-row { text-align:center; padding:40px !important; color:var(--text-muted); font-size:13px; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-in { background:#1a2a1a; color:#7ac87a; } .badge-out { background:#2a1a1a; color:#c87a7a; }
    .badge-adjust { background:#1a1a2a; color:#8ab4c8; } .badge-return { background:#2a2010; color:var(--accent); }
    .badge-low { background:#2a1a1a; color:#c87a7a; } .badge-ok { background:#1a2a1a; color:#7ac87a; }
    .badge-set { background:color-mix(in srgb, var(--accent2, #90bcd4) 18%, transparent); color:var(--accent2, #6a9cc0); cursor:help; }
    .badge-requested { background:#2a2010; color:var(--accent); } .badge-approved { background:#1a1a2a; color:#8ab4c8; }
    .badge-ordered { background:#2a1a2a; color:#9b70c8; } .badge-received { background:#1a2a1a; color:#7ac87a; }
    .badge-cancelled { background:var(--surface2); color:var(--text-muted); }
    /* 옵션 그룹 행의 '옵션 N종' — 상품명을 가리지 않도록 무채색 */
    .badge-optcount { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); font-weight:600; }
    /* 그룹 펼침 화살표 — 제품명 앞 고정폭 자리 (자식 행은 같은 폭의 빈 칸으로 라인 정렬) */
    .grp-toggle { display:inline-block; width:18px; color:var(--text-muted); font-size:11px; }
    /* 자식 행 옵션명 칩 — 무채색 + 고정폭으로 제품명 시작 위치 정렬 */
    .badge-opt { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); display:inline-block; min-width:46px; text-align:center; margin-right:8px; font-weight:600; }
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
        #panel-products .data-card { display:none; }
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
        {{-- ⋮ 더보기 메뉴 — 엑셀 가져오기 / 수정 로그 --}}
        <div style="position:relative;" id="invMoreWrap">
            <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 12px;border-radius:8px;font-size:15px;line-height:1;cursor:pointer;" onclick="toggleInvMenu(event)" title="더보기">⋮</button>
            <div id="invMenu" style="display:none; position:absolute; right:0; top:calc(100% + 6px); background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.14); min-width:150px; z-index:120; overflow:hidden;">
                <button class="inv-menu-item" onclick="closeInvMenu(); openExcelImportModal('products','제품')">엑셀 가져오기</button>
                <button class="inv-menu-item" onclick="closeInvMenu(); openActivityLog('Product,ProductCategory,StockMovement,PurchaseOrder',0,'재고 전체 수정 로그')">수정 로그</button>
            </div>
        </div>
    </div>

    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('products')">제품 관리</button>
        <button class="tab-btn" onclick="switchTab('movements')">입출고 내역</button>
        <button class="tab-btn" onclick="switchTab('orders')">주문 내역</button>
        <button class="tab-btn" onclick="switchTab('categories')">카테고리</button>
    </div>

    <!-- 제품 관리 (재고 현황 통합) -->
    <div class="tab-panel active" id="panel-products">
        <div class="toolbar">
            <input type="text" id="productSearch" placeholder="제품명/SKU 검색 후 Enter" onkeydown="if(event.key==='Enter'){prodPage=1;loadProducts();}">
            <select id="stockFilterOp" onchange="onStockFilterChange()" title="재고 수량으로 필터 (세트는 조립 가능 수 기준)">
                <option value="">재고 전체</option>
                <option value="zero">재고 0개</option>
                <option value="low">부족 (안전재고 이하)</option>
                <option value="gte">N개 이상</option>
                <option value="lte">N개 이하</option>
            </select>
            <input type="number" id="stockFilterVal" min="0" placeholder="N" style="display:none; width:74px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:12.5px; text-align:right;" oninput="prodPage=1;saveStockFilter();loadProducts()">
            <button class="btn-outline" id="groupOnlyBtn" onclick="toggleGroupOnly()" title="옵션 그룹으로 묶인 제품만 표시">옵션 그룹만</button>
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
            <button class="btn-outline" onclick="refreshAllMarketPrices(this)" title="시세 URL이 등록된 제품의 판매처 가격을 순차 조회합니다">전체 시세 갱신</button>
            <button class="btn-outline" id="prodEditToggle" onclick="toggleProdEditMode()" title="목록에서 제품명·가격·재고를 바로 고쳐 일괄 저장">전체 편집</button>
            <button class="btn-primary" id="prodEditSave" style="display:none;" onclick="saveProdEdits(this)">일괄 저장</button>
            <button class="btn-primary" id="prodAddBtn" onclick="openProductModal()">+ 제품 등록</button>
        </div>
        <div id="prodCatChips" style="margin-bottom:12px;"></div>
        <div id="prodBulkBar" style="display:none; align-items:center; gap:10px; padding:10px 14px; background:rgba(212,188,150,0.08); border:1px solid var(--accent); border-radius:8px; margin-bottom:10px; flex-wrap:wrap;">
            <span style="font-size:13px; font-weight:600;">
                <span id="prodBulkCount">0</span>개 선택됨
            </span>
            <div style="display:flex; gap:6px; margin-left:auto; flex-wrap:wrap; align-items:center;">
                <button class="btn-outline btn-sm" onclick="openGroupModal()">옵션 그룹으로 묶기</button>
                <button class="btn-outline btn-sm" onclick="bulkSetEstimate(true)"><x-icon name="check" :size="13"/> 견적서 노출 ON</button>
                <button class="btn-outline btn-sm" onclick="bulkSetEstimate(false)"><x-icon name="close" :size="13"/> 견적서 노출 OFF</button>
                <button class="btn-outline btn-sm" onclick="clearProdSelection()">선택 해제</button>
                <button onclick="bulkDeleteProducts()" style="background:var(--red, #dc2626); color:#fff; border:1px solid var(--red, #dc2626); padding:6px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;"><x-icon name="warning" :size="13"/> 선택 삭제</button>
            </div>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th style="width:30px;"><input type="checkbox" id="prodSelectAll" onchange="toggleSelectAllProducts(this.checked)" title="전체 선택"></th><th>SKU</th><th>제품명</th><th>카테고리</th><th class="text-right">매입가</th><th class="text-right">판매가</th><th class="text-right">마진률</th><th class="text-right">시세</th><th class="text-right">현재고</th><th class="text-right">안전재고</th><th>견적</th><th></th></tr></thead>
                <tbody id="productBody"><tr><td colspan="12" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
        <div class="mob-cards" id="productCards"></div>
        <div class="pager" id="prodPager"></div>
    </div>

    <!-- 입출고 내역 -->
    <div class="tab-panel" id="panel-movements">
        <div class="toolbar">
            <input type="text" id="movementSearch" placeholder="제품명/SKU 검색 후 Enter" onkeydown="if(event.key==='Enter'){loadMovements();}">
            <select id="movementType" onchange="loadMovements()">
                <option value="">전체 유형</option>
                <option value="in">입고</option><option value="out">출고</option><option value="adjust">조정</option><option value="return">반품</option>
            </select>
            @if(auth()->user()->isAdmin())
                <button class="btn-outline" id="movDelBtn" style="display:none;border-color:var(--red);color:var(--red);" onclick="deleteSelectedMovements()">선택 삭제 (<span id="movDelCount">0</span>)</button>
                <button class="btn-outline" style="border-color:var(--red);color:var(--red);" onclick="clearAllMovements()">전체 비우기</button>
            @endif
            <button class="btn-primary" onclick="openMovementModal()">+ 입출고 등록</button>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr>
                    @if(auth()->user()->isAdmin())<th style="width:34px;text-align:center;"><input type="checkbox" id="movSelAll" onchange="toggleMovSelAll(this.checked)" style="accent-color:var(--accent);"></th>@endif
                    <th>일시</th><th>유형</th><th>제품</th><th class="text-right">수량</th><th class="text-right">변동 후</th><th>처리자</th><th>메모</th>
                </tr></thead>
                <tbody id="movementBody"><tr><td colspan="8" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- 주문 내역 — 견적서 주문완료 건 + 직접 주문 (그룹 → 펼치면 항목) -->
    <div class="tab-panel" id="panel-orders">
        <div class="toolbar">
            <span class="text-muted" style="font-size:12px;">견적서에서 '주문완료' 표시된 건은 자동으로 나타납니다. 항목을 펼쳐 구매처·메모를 기록하세요.</span>
            <span style="margin-left:auto; display:flex; gap:8px;">
                <button class="btn-outline" onclick="loadOrders()">새로고침</button>
                <button class="btn-primary" onclick="openOrderCreate()">+ 주문 추가</button>
            </span>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th style="width:110px;">유형</th><th>주문명</th><th>항목</th><th>의뢰자/등록자</th><th>최근 수정</th><th style="width:170px;"></th></tr></thead>
                <tbody id="orderBody"><tr><td colspan="6" class="empty-row">로딩 중...</td></tr></tbody>
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
        <div class="field-group">
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                <input type="checkbox" id="pIsBundle" onchange="onBundleToggle()" style="accent-color:var(--accent); width:15px; height:15px; cursor:pointer;">
                세트 상품 <span style="color:var(--text-muted);font-size:11.5px;">(자체 재고 없음 — 출고 시 구성품 재고를 함께 소진)</span>
            </label>
        </div>
        <div class="field-group" id="bundleSection" style="display:none; border:1px dashed var(--border); border-radius:8px; padding:10px 12px;">
            <div class="field-label">구성품 * <span style="font-weight:400;color:var(--text-muted);">— 세트 1개당 필요 수량</span></div>
            <div id="bundleRows"></div>
            <input class="field-input" id="bundleSearch" placeholder="제품명/SKU로 검색해서 좁히기" oninput="renderBundlePicker()" style="margin-top:8px;">
            <div style="display:flex; gap:6px; margin-top:6px;">
                <select class="field-select" id="bundleAddSelect" style="flex:1; min-width:0;"><option value="">구성품 선택…</option></select>
                <button type="button" class="btn-outline btn-sm" onclick="addBundleRow()">+ 추가</button>
            </div>
            <div id="bundleSum" style="font-size:11.5px; color:var(--text-muted); margin-top:8px;"></div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label" id="pPurchaseLabel">매입가</div>
                <input class="field-input" id="pPurchase" type="number" min="0" placeholder="비워두면 0원으로 저장">
            </div>
            <div class="field-group">
                <div class="field-label">판매가</div>
                <input class="field-input" id="pSale" type="number" min="0">
            </div>
        </div>
        <div class="field-group" id="marketUrlGroup1">
            <div class="field-label">시세 URL — 컴퓨존</div>
            <input class="field-input" id="pMarketUrlCompuzone" placeholder="https://www.compuzone.co.kr/... 제품 페이지 주소 (선택)">
        </div>
        <div class="field-group" id="marketUrlGroup2">
            <div class="field-label">시세 URL — 피씨팩토리</div>
            <input class="field-input" id="pMarketUrlPcfactory" placeholder="https://www.pc-factory.co.kr/... 제품 페이지 주소 (선택)">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">등록한 판매처별 판매가를 매일 새벽 자동 조회해 시세 컬럼에 각각 표시합니다.</div>
        </div>
        <div class="field-row" id="safetyGroup">
            <div class="field-group">
                <div class="field-label">현재고</div>
                <input class="field-input" id="pStock" type="number" min="0" placeholder="비워두면 변경 없음">
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">수량을 바꾸면 입출고 내역에 '조정' 이력이 자동 기록됩니다.</div>
            </div>
            <div class="field-group">
                <div class="field-label">안전재고 (선택 · 이하 경고)</div>
                <input class="field-input" id="pSafety" type="number" min="0" placeholder="비워두면 미사용">
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <input class="field-input" id="pMemo">
        </div>
        <div class="field-group">
            <div class="field-label">검색 태그 <span style="font-weight:400; color:var(--text-muted); font-size:11.5px;">— 화면에는 표시되지 않고 검색에만 사용 (쉼표 구분, 예: 야마하, yamaha, 오디오믹서)</span></div>
            <input class="field-input" id="pSearchTags" placeholder="야마하, yamaha">
        </div>
        <div class="field-group">
            <div style="display:flex; align-items:center; gap:12px;">
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; white-space:nowrap;">
                    <input type="checkbox" id="pUseTime" style="accent-color:var(--accent); width:15px; height:15px; cursor:pointer;" onchange="document.getElementById('pTimeRequired').style.display = this.checked ? '' : 'none';">
                    소요시간 사용
                </label>
                <input class="field-input" id="pTimeRequired" placeholder="기본 소요시간 (예: 2시간)" style="flex:1; display:none;">
            </div>
            <div style="font-weight:400; color:var(--text-muted); font-size:11.5px; margin-top:4px;">체크한 제품만 견적서에서 소요시간 입력폼이 표시됩니다</div>
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
        <div class="field-group"><div class="field-label">제품 *</div>
            <input class="field-input" id="mProductSearch" placeholder="제품명/SKU로 검색해서 좁히기" oninput="filterMovProductOptions()" style="margin-bottom:6px;">
            <select class="field-select" id="mProduct" onchange="onMovementProductChange()"></select>
        </div>
        <div class="field-row">
            <div class="field-group"><div class="field-label">유형 *</div>
                <select class="field-select" id="mType" onchange="onMovementTypeChange()"><option value="in">입고</option><option value="out">출고(대여)</option><option value="adjust">재고 조정</option><option value="return">반품(반납)</option></select>
            </div>
            <div class="field-group"><div class="field-label">수량 *</div><input class="field-input" id="mQty" type="number" min="1" value="1"></div>
        </div>
        <div class="field-group" id="mProjectGroup" style="display:none;">
            <div class="field-label">의뢰자 · 프로젝트</div>
            <div style="position:relative;">
                <input class="field-input" id="mClientSearch" placeholder="의뢰자 이름/닉네임/연락처 검색" autocomplete="off" oninput="mvClientSearchInput()">
                <button type="button" id="mClientClear" onclick="mvClearClient()" style="display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">✕</button>
                <div id="mClientResults" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:50; background:var(--surface); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.12); max-height:220px; overflow-y:auto;"></div>
            </div>
            <select class="field-select" id="mProject" style="margin-top:8px;"><option value="">선택 없음 (본사/창고)</option></select>
        </div>
        <div class="field-group"><div class="field-label">메모</div><input class="field-input" id="mMemo" placeholder="사유 또는 참고사항"></div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('movementModal')">취소</button>
            <button class="btn-save" onclick="saveMovement()">등록</button>
        </div>
    </div>
</div>

<!-- 옵션 그룹 묶기 모달 — 기존 제품(ID 유지)들을 하나의 상품으로 묶고 옵션명 지정 -->
<div class="modal-overlay" id="groupModal">
    <div class="modal" style="width:520px;">
        <div class="modal-header">
            <div class="modal-title">옵션 그룹으로 묶기</div>
            <button class="modal-close" onclick="closeModal('groupModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">그룹(대표) 상품명 *</div>
            <input class="field-input" id="gName" placeholder="예: 카메라 X100">
        </div>
        <div class="field-group">
            <div class="field-label">선택된 제품별 옵션명 *</div>
            <div id="gItems" style="display:flex; flex-direction:column; gap:6px;"></div>
            <div style="font-size:11.5px; color:var(--text-muted); margin-top:6px;">재고·가격·입출고는 지금처럼 제품(옵션)별로 관리되고, 견적서에서는 그룹 하나로 표시돼 옵션을 골라 추가합니다.</div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('groupModal')">취소</button>
            <button class="btn-save" onclick="saveProductGroup()">그룹 만들기</button>
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
        const map = {products:'제품',movements:'입출고',orders:'주문 내역',categories:'카테고리'};
        b.classList.toggle('active', b.textContent.includes(map[name]));
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+name));
    if (!skipHash) history.replaceState(null, '', '#'+name);
    localStorage.setItem('invLastTab', name); // 새로고침 후 마지막 탭 복원용
    ({products:loadProducts,movements:loadMovements,orders:loadOrders,categories:loadCategories})[name]();
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

// (재고 현황 탭은 제품 관리에 통합됨 — 현재고 컬럼 + 부족 재고만 필터)

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
    // 재고 수량 필터 — zero/low/gte/lte (+N)
    const op = document.getElementById('stockFilterOp').value;
    if (op) {
        qs.set('stock_op', op);
        if (op === 'gte' || op === 'lte') qs.set('stock_val', document.getElementById('stockFilterVal').value || '0');
    }
    const catId = prodCatFilterId();
    if (catId) qs.set('category_id', catId); // 하위 카테고리 포함 (서버 필터)
    if (groupOnly) qs.set('grouped_only', '1'); // 옵션 그룹으로 묶인 제품만
    return qs;
}

// ── 옵션 그룹만 보기 토글 (선택은 브라우저에 기억) ──
let groupOnly = localStorage.getItem('invGroupOnly') === '1';
function toggleGroupOnly() {
    groupOnly = !groupOnly;
    localStorage.setItem('invGroupOnly', groupOnly ? '1' : '0');
    document.getElementById('groupOnlyBtn').classList.toggle('on', groupOnly);
    prodPage = 1;
    loadProducts();
}
document.getElementById('groupOnlyBtn').classList.toggle('on', groupOnly);

// ── ⋮ 더보기 메뉴 (엑셀 가져오기/수정 로그) ──
function toggleInvMenu(e) {
    e.stopPropagation();
    const m = document.getElementById('invMenu');
    m.style.display = m.style.display === 'none' ? '' : 'none';
}
function closeInvMenu() { document.getElementById('invMenu').style.display = 'none'; }
document.addEventListener('click', e => { if (!e.target.closest('#invMoreWrap')) closeInvMenu(); });

// 재고 필터 변경 — N 입력칸 표시 토글 + 저장 + 재조회
function onStockFilterChange() {
    const op = document.getElementById('stockFilterOp').value;
    document.getElementById('stockFilterVal').style.display = (op === 'gte' || op === 'lte') ? '' : 'none';
    prodPage = 1;
    saveStockFilter();
    loadProducts();
}
function saveStockFilter() {
    localStorage.setItem('invStockOp', document.getElementById('stockFilterOp').value);
    localStorage.setItem('invStockVal', document.getElementById('stockFilterVal').value);
}
(function restoreStockFilter() {
    const op = localStorage.getItem('invStockOp') || '';
    document.getElementById('stockFilterOp').value = op;
    document.getElementById('stockFilterVal').value = localStorage.getItem('invStockVal') || '';
    document.getElementById('stockFilterVal').style.display = (op === 'gte' || op === 'lte') ? '' : 'none';
})();

// 세트 조립 가능 수 — min(구성품 재고 ÷ 필요 수량)
function bundleBuildable(p) {
    const items = p.bundle_items || [];
    if (!items.length) return 0;
    return Math.min(...items.map(i => Math.floor(Math.max(0, i.component?.inventory?.quantity ?? 0) / Math.max(1, i.quantity))));
}

// 세트 구성품 툴팁 텍스트
function bundleTooltip(p) {
    return (p.bundle_items || []).map(i =>
        `${i.component?.name || '#'+i.component_product_id} ×${i.quantity} (재고 ${i.component?.inventory?.quantity ?? 0})`
    ).join('\n');
}

// 현재고 셀 — 안전재고 이하이면 경고색 + 부족 뱃지, 세트는 조립 가능 수
function stockCellHtml(p) {
    if (p.is_bundle) {
        const b = bundleBuildable(p);
        return `<span title="${_esc(bundleTooltip(p))}"><b class="${b === 0 ? 'text-warn' : ''}">${b}</b> <span class="text-muted" style="font-size:11px;">조립가능</span></span>`;
    }
    const qty = p.inventory ? (p.inventory.quantity ?? 0) : 0;
    const low = p.safety_stock && qty <= p.safety_stock;
    return `<b class="${low ? 'text-warn' : ''}">${qty}</b>${low ? ' <span class="badge badge-low">부족</span>' : ''}`;
}

// 제품명 앞 세트 뱃지 (구성품 툴팁 포함)
function bundleBadgeHtml(p) {
    return p.is_bundle ? `<span class="badge badge-set" title="${_esc(bundleTooltip(p))}">세트</span> ` : '';
}

async function loadProducts() {
    // 편집 모드에서 검색/페이지 이동으로 재렌더되면 미저장 변경이 사라짐 — 확인 후 진행
    if (PROD_EDIT_MODE && document.querySelector('#productBody .pe-input') && collectProdEdits().length &&
        !confirm('저장하지 않은 변경 사항이 있습니다. 이동하면 사라집니다. 계속할까요?')) return;
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
        tb.innerHTML = `<tr><td colspan="12" class="empty-row">${msg}</td></tr>`;
        cards.innerHTML = `<div class="empty-row">${msg}</div>`;
        clearProdSelection();
        return;
    }
    // 화면에서 사라진 ID는 선택에서 제거
    const visibleIds = new Set(allProducts.map(p => p.id));
    [...prodSelection].forEach(id => { if (!visibleIds.has(id)) prodSelection.delete(id); });
    const E = PROD_EDIT_MODE; // 전체 편집 모드 — 값 셀을 입력폼으로 렌더

    // 옵션 그룹은 대표 행 하나로 접어서 표시 — 펼치면 안에서 옵션(자식) 행이 보임
    const seq = [];
    const seenGroups = new Set();
    allProducts.forEach(p => {
        if (!p.group_id) { seq.push({ p }); return; }
        if (seenGroups.has(p.group_id)) return;
        seenGroups.add(p.group_id);
        seq.push({ group: p.group || { id: p.group_id, name: p.name }, children: allProducts.filter(x => x.group_id === p.group_id) });
    });

    const prodRowHtml = (p, child) => `<tr data-pid="${p.id}" ${child ? `data-gchild="${p.group_id}" style="${(E || expandedGroups.has(p.group_id)) ? '' : 'display:none;'} background:var(--surface2);"` : ''}>
        <td><input type="checkbox" class="prod-row-check" data-id="${p.id}" ${prodSelection.has(p.id)?'checked':''} onchange="toggleProductSelection(${p.id}, this.checked)"></td>
        <td class="text-muted sku-cell">${_esc(p.sku)}</td>
        <td class="text-wrap">${child ? '<span class="grp-toggle"></span>'+optionChipHtml(p) : ''}${E ? bundleBadgeHtml(p)+peInput(p,'name',p.name,'text') : bundleBadgeHtml(p)+_esc(p.name)}</td>
        <td class="text-muted text-wrap">${p.category||'-'}</td>
        <td class="text-right">${E ? peInput(p,'purchase_price',p.purchase_price??0) : fmt(p.purchase_price)}</td>
        <td class="text-right">${E ? peInput(p,'sale_price',p.sale_price??0) : fmt(p.sale_price)}</td>
        <td class="text-right">${marginCellHtml(p)}</td>
        <td class="text-right">${marketPriceCellHtml(p)}</td>
        <td class="text-right">${E && !p.is_bundle ? peInput(p,'stock_quantity',p.inventory?(p.inventory.quantity??0):0) : stockCellHtml(p)}</td>
        <td class="text-right">${E ? peInput(p,'safety_stock',p.safety_stock??0) : (p.safety_stock||'-')}</td>
        <td>${p.show_in_estimate ? '<span class="badge badge-ok">노출</span>' : ''}</td>
        <td class="action-cell">
            <button class="btn-outline btn-sm" onclick="if(typeof openActivityLog==='function')openActivityLog('Product',${p.id},'${_esc(p.name.replace(/'/g,"\\'"))} 수정 로그');else alert('로그 기능을 사용할 수 없습니다.');">📋</button>
            <button class="btn-outline btn-sm" onclick='editProduct(${p.id})'>수정</button>
            <button class="btn-danger-sm" onclick="deleteProduct(${p.id})">삭제</button>
        </td>
    </tr>`;

    const groupHeaderRowHtml = (g, children) => {
        const opened = E || expandedGroups.has(g.id);
        const qty = children.reduce((s, c) => s + (c.inventory?.quantity ?? 0), 0);
        const sales = children.map(c => Number(c.sale_price) || 0);
        const purchases = children.map(c => Number(c.purchase_price) || 0);
        const range = arr => { const mn = Math.min(...arr), mx = Math.max(...arr); return mn === mx ? fmt(mn) : fmt(mn)+'~'+fmt(mx); };
        return `<tr data-gid="${g.id}" style="cursor:pointer;" onclick="if(!event.target.closest('button,input')) toggleGroup(${g.id})">
        <td><input type="checkbox" ${children.every(c => prodSelection.has(c.id)) ? 'checked' : ''} onchange="toggleGroupSelection(${g.id}, this.checked)" title="그룹 전체 선택"></td>
        <td class="text-muted sku-cell"></td>
        <td class="text-wrap"><span class="grp-arrow grp-toggle" data-gid="${g.id}">${opened ? '▾' : '▸'}</span><b>${_esc(g.name)}</b><span class="badge badge-optcount" style="margin-left:5px;">옵션 ${children.length}종</span></td>
        <td class="text-muted text-wrap">${children[0]?.category || '-'}</td>
        <td class="text-right text-muted">${range(purchases)}</td>
        <td class="text-right text-muted">${range(sales)}</td>
        <td class="text-right text-muted">-</td>
        <td class="text-right text-muted">-</td>
        <td class="text-right"><b>${qty}</b></td>
        <td class="text-right text-muted">-</td>
        <td>${children.every(c => c.show_in_estimate) ? '<span class="badge badge-ok">노출</span>' : ''}</td>
        <td class="action-cell">
            <button class="btn-outline btn-sm" onclick="event.stopPropagation(); ungroupProducts(${g.id}, '${_esc(g.name).replace(/'/g,'&#39;')}')">그룹 해제</button>
        </td>
    </tr>`;
    };

    tb.innerHTML = seq.map(e => e.p ? prodRowHtml(e.p, false)
        : groupHeaderRowHtml(e.group, e.children) + e.children.map(c => prodRowHtml(c, true)).join('')).join('');

    // 모바일 카드 (768px 이하에서 표시) — 체크박스/버튼은 테이블과 동일 핸들러 공유
    const prodCardHtml = (p, child) => `<div class="mob-card" data-pid="${p.id}" ${child ? `data-gchild="${p.group_id}" style="${(E || expandedGroups.has(p.group_id)) ? '' : 'display:none;'} margin-left:14px;"` : ''}>
        <div class="mob-card-top">
            <input type="checkbox" class="prod-row-check" data-id="${p.id}" ${prodSelection.has(p.id)?'checked':''} onchange="toggleProductSelection(${p.id}, this.checked)">
            <div>
                <div class="mob-card-title">${child ? optionChipHtml(p) : ''}${bundleBadgeHtml(p)}${_esc(p.name)}</div>
                <div class="mob-card-sub">${_esc(p.sku)}${p.category ? ' · '+_esc(p.category) : ''}${p.safety_stock ? ' · 안전재고 '+p.safety_stock : ''}${p.show_in_estimate ? ' · <span class="badge badge-ok">노출</span>' : ''}</div>
            </div>
        </div>
        <div class="mob-card-line">매입 ${fmt(p.purchase_price)} → 판매 ${fmt(p.sale_price)} · ${marginCellHtml(p)}</div>
        <div class="mob-card-line">재고 ${stockCellHtml(p)} · 안전재고 ${p.safety_stock||'-'}</div>
        <div class="mob-card-line">시세 ${marketPriceCellHtml(p)}</div>
        <div class="mob-card-actions">
            <button class="btn-outline btn-sm" onclick="if(typeof openActivityLog==='function')openActivityLog('Product',${p.id},'${_esc(p.name.replace(/'/g,"\\'"))} 수정 로그');else alert('로그 기능을 사용할 수 없습니다.');">📋 로그</button>
            <button class="btn-outline btn-sm" onclick='editProduct(${p.id})'>수정</button>
            <button class="btn-danger-sm" onclick="deleteProduct(${p.id})">삭제</button>
        </div>
    </div>`;
    const groupCardHtml = (g, children) => {
        const opened = E || expandedGroups.has(g.id);
        const qty = children.reduce((s, c) => s + (c.inventory?.quantity ?? 0), 0);
        return `<div class="mob-card" data-gid="${g.id}" onclick="if(!event.target.closest('button,input')) toggleGroup(${g.id})" style="cursor:pointer;">
        <div class="mob-card-top">
            <span class="grp-arrow" data-gid="${g.id}" style="margin-top:2px;">${opened ? '▾' : '▸'}</span>
            <div>
                <div class="mob-card-title">${_esc(g.name)}<span class="badge badge-optcount" style="margin-left:5px;">옵션 ${children.length}종</span></div>
                <div class="mob-card-sub">재고 합계 ${qty}</div>
            </div>
        </div>
        <div class="mob-card-actions">
            <button class="btn-outline btn-sm" onclick="event.stopPropagation(); ungroupProducts(${g.id}, '${_esc(g.name).replace(/'/g,'&#39;')}')">그룹 해제</button>
        </div>
    </div>`;
    };
    cards.innerHTML = seq.map(e => e.p ? prodCardHtml(e.p, false)
        : groupCardHtml(e.group, e.children) + e.children.map(c => prodCardHtml(c, true)).join('')).join('');
    updateProdBulkBar();
}

// 그룹 펼침 상태 (페이지 이동해도 유지)
const expandedGroups = new Set();
function toggleGroup(gid) {
    const open = expandedGroups.has(gid);
    open ? expandedGroups.delete(gid) : expandedGroups.add(gid);
    document.querySelectorAll(`[data-gchild="${gid}"]`).forEach(el => { el.style.display = open ? 'none' : ''; });
    document.querySelectorAll(`.grp-arrow[data-gid="${gid}"]`).forEach(el => { el.textContent = open ? '▸' : '▾'; });
}
function toggleGroupSelection(gid, checked) {
    allProducts.filter(p => p.group_id === gid).forEach(p => {
        toggleProductSelection(p.id, checked);
        document.querySelectorAll(`.prod-row-check[data-id="${p.id}"]`).forEach(cb => cb.checked = checked);
    });
}
function optionChipHtml(p) {
    if (!p.option_name) return '';
    return `<span class="badge badge-opt">${_esc(p.option_name)}</span>`;
}

// === 전체 편집 (인라인 일괄 수정) ===
let PROD_EDIT_MODE = false;

// 칸(td) 아무 곳이나 클릭하면 그 칸의 입력창으로 포커스
document.getElementById('productBody').addEventListener('click', e => {
    if (!PROD_EDIT_MODE) { return; }
    const td = e.target.closest('td');
    const inp = td && td.querySelector('.pe-input');
    if (inp && e.target !== inp) { inp.focus(); inp.select(); }
});

function peInput(p, field, value, type = 'number') {
    let cls = type === 'number' ? 'pe-input pe-num' : 'pe-input';
    if (field === 'stock_quantity' || field === 'safety_stock') { cls += ' pe-sm'; } // 재고류는 짧은 폭
    return `<input class="${cls}" type="${type}" ${type==='number'?'min="0"':''} data-id="${p.id}" data-field="${field}" value="${_esc(String(value ?? ''))}">`;
}

// 편집 모드에서 입력폼의 값이 원본과 달라진 행 수집
function collectProdEdits() {
    const byId = new Map(allProducts.map(p => [p.id, p]));
    const items = new Map();
    document.querySelectorAll('#productBody .pe-input').forEach(inp => {
        const id = parseInt(inp.dataset.id, 10);
        const p = byId.get(id);
        if (!p) return;
        const field = inp.dataset.field;
        const raw = inp.value.trim();
        const current = field === 'name' ? (p.name ?? '')
            : field === 'stock_quantity' ? (p.inventory ? (p.inventory.quantity ?? 0) : 0)
            : (p[field] ?? 0);
        const next = field === 'name' ? raw : (raw === '' ? 0 : parseInt(raw, 10));
        if (field === 'name' && raw === '') { inp.classList.add('pe-invalid'); return; }
        inp.classList.remove('pe-invalid');
        if (String(next) === String(current)) return;
        if (!items.has(id)) items.set(id, { id });
        items.get(id)[field] = next;
    });
    return [...items.values()];
}

function toggleProdEditMode() {
    if (PROD_EDIT_MODE && collectProdEdits().length &&
        !confirm('저장하지 않은 변경 사항이 있습니다. 편집을 취소할까요?')) return;
    PROD_EDIT_MODE = !PROD_EDIT_MODE;
    document.getElementById('prodEditToggle').textContent = PROD_EDIT_MODE ? '편집 취소' : '전체 편집';
    document.getElementById('prodEditSave').style.display = PROD_EDIT_MODE ? '' : 'none';
    loadProducts();
}

async function saveProdEdits(btn) {
    const items = collectProdEdits();
    if (document.querySelector('#productBody .pe-invalid')) { alert('제품명은 비울 수 없습니다.'); return; }
    if (!items.length) { alert('변경된 내용이 없습니다.'); return; }
    if (!confirm(`${items.length}개 제품의 변경 사항을 저장할까요?`)) return;
    btn.disabled = true;
    try {
        const res = await fetch('/api/inventory/products/bulk-edit', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ items }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) { alert(data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : '저장에 실패했습니다.')); return; }
        PROD_EDIT_MODE = false;
        document.getElementById('prodEditToggle').textContent = '전체 편집';
        document.getElementById('prodEditSave').style.display = 'none';
        await loadProducts();
        alert(data.message || '저장되었습니다.');
    } finally {
        btn.disabled = false;
    }
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
// === 옵션 그룹 (블랙/화이트 등 같은 상품 묶기) ===
function openGroupModal() {
    if (prodSelection.size < 2) return alert('그룹으로 묶을 제품을 2개 이상 선택해주세요.');
    const items = [...prodSelection].map(id => allProducts.find(p => p.id === id)).filter(Boolean);
    if (items.some(p => p.group)) return alert('이미 그룹에 속한 제품이 포함돼 있습니다. 먼저 그룹을 해제해주세요.');
    // 그룹명 제안: 첫 제품명에서 색상어 제거 없이 그대로 (수정 가능)
    document.getElementById('gName').value = items[0]?.name || '';
    document.getElementById('gItems').innerHTML = items.map(p => `
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="text-muted" style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px;">${_esc(p.name)} <span style="opacity:0.7;">(${_esc(p.sku)})</span></span>
            <input class="field-input g-opt" data-id="${p.id}" placeholder="옵션명 (예: 블랙)" style="width:160px; padding:7px 10px; font-size:12.5px;">
        </div>`).join('');
    openModal('groupModal');
}
async function saveProductGroup() {
    const name = document.getElementById('gName').value.trim();
    if (!name) return alert('그룹 상품명을 입력해주세요.');
    const items = [...document.querySelectorAll('#gItems .g-opt')].map(i => ({ id: +i.dataset.id, option_name: i.value.trim() }));
    if (items.some(i => !i.option_name)) return alert('모든 제품에 옵션명을 입력해주세요.');
    const res = await fetch('/api/inventory/product-groups', { method:'POST', headers:H, body: JSON.stringify({ name, items }) });
    if (!res.ok) { const e = await res.json().catch(()=>({})); return alert(e.message || '그룹 생성에 실패했습니다.'); }
    closeModal('groupModal');
    clearProdSelection();
    loadProducts();
}
async function ungroupProducts(groupId, groupName) {
    if (!confirm(`'${groupName}' 그룹을 해제할까요?\n제품·재고는 그대로 두고 묶음만 풀립니다.`)) return;
    const res = await fetch(`/api/inventory/product-groups/${groupId}`, { method:'DELETE', headers:H });
    if (!res.ok) { const e = await res.json().catch(()=>({})); return alert(e.message || '해제에 실패했습니다.'); }
    loadProducts();
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
// === 세트 상품 (구성품) ===
let BUNDLE_ITEMS = []; // [{product_id, name, sku, quantity, purchase_price}]
let PICKER_PRODUCTS = null; // 구성품 선택용 전체 제품 캐시 (세트 제외)

async function loadPickerProducts() {
    const r = await fetch('/api/inventory/products');
    PICKER_PRODUCTS = (await r.json()).filter(p => !p.is_bundle);
}

function onBundleToggle() {
    const on = document.getElementById('pIsBundle').checked;
    document.getElementById('bundleSection').style.display = on ? '' : 'none';
    // 세트는 자체 재고/시세가 없음 — 관련 입력 숨김, 매입가는 구성품 합계로 자동
    document.getElementById('safetyGroup').style.display = on ? 'none' : '';
    document.getElementById('marketUrlGroup1').style.display = on ? 'none' : '';
    document.getElementById('marketUrlGroup2').style.display = on ? 'none' : '';
    const purchase = document.getElementById('pPurchase');
    purchase.readOnly = on;
    purchase.style.opacity = on ? '0.75' : '';
    document.getElementById('pPurchaseLabel').textContent = on ? '매입가 (구성품 합계 자동)' : '매입가';
    if (on) {
        renderBundleRows();
        if (!PICKER_PRODUCTS) loadPickerProducts().then(renderBundlePicker);
        else renderBundlePicker();
    }
}

function renderBundlePicker() {
    const sel = document.getElementById('bundleAddSelect');
    const used = new Set(BUNDLE_ITEMS.map(i => i.product_id));
    const editId = +document.getElementById('pEditId').value || 0;
    const q = document.getElementById('bundleSearch').value.trim().toLowerCase();
    const matches = (PICKER_PRODUCTS||[])
        .filter(p => !used.has(p.id) && p.id !== editId)
        .filter(p => { const nq = q.replace(/\s+/g, ''); const nrm = s => (s||'').toLowerCase().replace(/\s+/g, ''); return !nq || nrm(p.name).includes(nq) || nrm(p.sku).includes(nq) || nrm(p.search_tags).includes(nq); });
    sel.innerHTML = matches.length
        ? '<option value="">구성품 선택…</option>' + matches.map(p => `<option value="${p.id}">${_esc(p.name)} (${_esc(p.sku)})</option>`).join('')
        : '<option value="">검색 결과 없음</option>';
    // 검색 결과가 하나면 바로 선택해둠 (추가 버튼만 누르면 되도록)
    if (q && matches.length === 1) sel.value = String(matches[0].id);
}

function addBundleRow() {
    const sel = document.getElementById('bundleAddSelect');
    const id = +sel.value;
    if (!id) return;
    const p = (PICKER_PRODUCTS||[]).find(x => x.id === id);
    if (!p) return;
    BUNDLE_ITEMS.push({ product_id: p.id, name: p.name, sku: p.sku, quantity: 1, purchase_price: p.purchase_price||0 });
    renderBundleRows(); renderBundlePicker();
}

function removeBundleRow(idx) { BUNDLE_ITEMS.splice(idx, 1); renderBundleRows(); renderBundlePicker(); }
function bundleQtyChange(idx, v) { BUNDLE_ITEMS[idx].quantity = Math.max(1, parseInt(v, 10)||1); updateBundleSum(); }

function renderBundleRows() {
    const box = document.getElementById('bundleRows');
    box.innerHTML = BUNDLE_ITEMS.length ? BUNDLE_ITEMS.map((i, idx) => `
        <div style="display:flex; align-items:center; gap:8px; padding:5px 0; border-bottom:1px solid var(--border); font-size:13px;">
            <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${_esc(i.name)} <span class="text-muted" style="font-size:11px;">${_esc(i.sku||'')}</span></span>
            <input type="number" min="1" value="${i.quantity}" onchange="bundleQtyChange(${idx}, this.value)" style="width:64px; padding:5px 8px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); text-align:center;">
            <button type="button" class="btn-danger-sm" onclick="removeBundleRow(${idx})">✕</button>
        </div>`).join('') : '<div class="text-muted" style="font-size:12px; padding:4px 0;">아직 구성품이 없습니다. 아래에서 추가해주세요.</div>';
    updateBundleSum();
}

function updateBundleSum() {
    const sum = BUNDLE_ITEMS.reduce((a, i) => a + (i.purchase_price||0) * i.quantity, 0);
    document.getElementById('bundleSum').textContent = BUNDLE_ITEMS.length
        ? `구성품 매입가 합계: ${sum.toLocaleString()}원 (매입가에 자동 반영)` : '';
    if (document.getElementById('pIsBundle').checked) document.getElementById('pPurchase').value = sum || '';
    updateSaleHint();
}

// 판매가가 비어 있으면 매입가 기준 적정 판매가를 placeholder로 안내
// 마진율 정의는 목록과 동일: (판매가-매입가)/판매가 → 적정가 = 매입가 ÷ 0.9 (100원 단위 올림)
function updateSaleHint() {
    const sale = document.getElementById('pSale');
    const buy = parseInt(document.getElementById('pPurchase').value, 10) || 0;
    sale.placeholder = (buy > 0)
        ? `적정 판매가는 ${(Math.ceil(buy / 0.9 / 100) * 100).toLocaleString()}원입니다 (마진 10%)`
        : '';
}
document.getElementById('pPurchase').addEventListener('input', updateSaleHint);

async function openProductModal(p) {
    if (!catData.length) await loadCategories();
    document.getElementById('productModalTitle').textContent = p ? '제품 수정' : '제품 등록';
    document.getElementById('pEditId').value = p ? p.id : '';
    document.getElementById('pName').value = p ? p.name : '';
    document.getElementById('pPurchase').value = p ? (p.purchase_price||'') : '';
    document.getElementById('pSale').value = p ? (p.sale_price||'') : '';
    updateSaleHint();
    const mps = (p && p.market_prices) || [];
    document.getElementById('pMarketUrlCompuzone').value = mps.find(m=>m.vendor==='compuzone')?.url || '';
    document.getElementById('pMarketUrlPcfactory').value = mps.find(m=>m.vendor==='pcfactory')?.url || '';
    document.getElementById('pSafety').value = p ? (p.safety_stock||'') : '';
    // 현재고 — 수정 시 현재 수량 표시 (비워두면 변경 없음), 신규는 초기 재고 입력
    document.getElementById('pStock').value = p ? (p.inventory ? (p.inventory.quantity ?? 0) : '') : '';
    document.getElementById('pStock').placeholder = p ? '비워두면 변경 없음' : '초기 재고 (비워두면 0)';
    document.getElementById('pMemo').value = p ? (p.memo||'') : '';
    document.getElementById('pSearchTags').value = p ? (p.search_tags||'') : '';
    document.getElementById('pUseTime').checked = p ? !!p.use_time_required : false;
    document.getElementById('pTimeRequired').value = p ? (p.time_required||'') : '';
    document.getElementById('pTimeRequired').style.display = (p && p.use_time_required) ? '' : 'none';
    document.getElementById('pEstimate').checked = p ? !!p.show_in_estimate : false;
    // 세트 상품 — 기존 제품도 전환 가능 (저장 시 재고/구성 정리 확인창)
    const bundleCheck = document.getElementById('pIsBundle');
    bundleCheck.checked = p ? !!p.is_bundle : false;
    BUNDLE_ITEMS = (p && p.bundle_items || []).map(i => ({
        product_id: i.component_product_id,
        name: i.component?.name || ('#'+i.component_product_id),
        sku: i.component?.sku || '',
        quantity: i.quantity,
        purchase_price: i.component?.purchase_price || 0,
    }));
    document.getElementById('bundleSearch').value = '';
    onBundleToggle();
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
    stock_quantity: '현재고',
    time_required: '소요시간',
    use_time_required: '소요시간 사용',
    memo: '메모',
    show_in_estimate: '견적서 노출',
    sku: 'SKU',
    is_bundle: '세트 상품',
    bundle_items: '구성품',
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
        stock_quantity: document.getElementById('pIsBundle').checked ? null
            : (document.getElementById('pStock').value !== '' ? parseInt(document.getElementById('pStock').value, 10) : null),
        memo: document.getElementById('pMemo').value || null,
        search_tags: document.getElementById('pSearchTags').value.trim() || null,
        time_required: document.getElementById('pTimeRequired').value.trim() || null,
        use_time_required: document.getElementById('pUseTime').checked,
        show_in_estimate: document.getElementById('pEstimate').checked,
        is_bundle: document.getElementById('pIsBundle').checked,
        bundle_items: document.getElementById('pIsBundle').checked
            ? BUNDLE_ITEMS.map(i => ({ product_id: i.product_id, quantity: i.quantity }))
            : [],
    };
    if (body.is_bundle && !body.bundle_items.length) {
        return alert('세트 상품은 구성품을 1개 이상 추가해야 합니다.');
    }
    // 세트 ↔ 일반 전환 확인 (기존 제품 수정 시)
    const orig = id ? allProducts.find(x => x.id === +id) : null;
    if (orig && !!orig.is_bundle !== body.is_bundle) {
        const msg = body.is_bundle
            ? '세트 상품으로 전환합니다.\n\n이 제품의 자체 재고는 0으로 정리되고(조정 이력 기록),\n앞으로는 구성품 재고로 관리됩니다. 계속할까요?'
            : '일반 제품으로 전환합니다.\n\n구성품 구성이 삭제되고 자체 재고(0부터)로 관리됩니다. 계속할까요?';
        if (!confirm(msg)) return;
    }
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
const MOV_IS_ADMIN = @json(auth()->user()->isAdmin());
const movSel = new Set();
let movPageIds = [];

async function loadMovements() {
    const qs = new URLSearchParams();
    const type = document.getElementById('movementType').value;
    const search = document.getElementById('movementSearch').value.trim();
    if (type) qs.set('type', type);
    if (search) qs.set('search', search);
    const params = qs.toString() ? '?'+qs.toString() : '';
    const res = await fetch('/api/inventory/movements'+params);
    const data = await res.json();
    const tb = document.getElementById('movementBody');
    const typeMap = {in:'입고',out:'출고',adjust:'조정',return:'반품'};
    movSel.clear();
    movPageIds = data.map(m => m.id);
    updateMovSelUI();
    if (!data.length) { tb.innerHTML = '<tr><td colspan="8" class="empty-row">내역이 없습니다.</td></tr>'; return; }
    tb.innerHTML = data.map(m => `<tr>
        ${MOV_IS_ADMIN ? `<td style="text-align:center;"><input type="checkbox" class="mov-sel" ${movSel.has(m.id)?'checked':''} onchange="toggleMovSel(${m.id}, this.checked)" style="accent-color:var(--accent);"></td>` : ''}
        <td class="text-muted">${fmtTime(m.created_at)}</td>
        <td><span class="badge badge-${m.movement_type}">${typeMap[m.movement_type]}</span></td>
        <td>${m.product?.name||'-'}</td>
        <td class="text-right" style="font-weight:600;color:${m.movement_type==='out'?'var(--red)':'var(--green)'};">${m.movement_type==='out'?'-':''}${m.quantity}</td>
        <td class="text-right">${m.quantity_after}</td>
        <td class="text-muted">${_esc(m.user?.display_name)||'-'}</td>
        <td class="text-muted">${_esc(m.memo)||'-'}</td>
    </tr>`).join('');
}

// === 입출고 내역 삭제 (관리자) — 이력만 지우며 재고 수량은 변하지 않음 ===
function toggleMovSel(id, on) { on ? movSel.add(id) : movSel.delete(id); updateMovSelUI(); }
function toggleMovSelAll(on) {
    movPageIds.forEach(id => on ? movSel.add(id) : movSel.delete(id));
    document.querySelectorAll('.mov-sel').forEach(cb => { cb.checked = on; });
    updateMovSelUI();
}
function updateMovSelUI() {
    const btn = document.getElementById('movDelBtn');
    if (!btn) return;
    btn.style.display = movSel.size ? '' : 'none';
    document.getElementById('movDelCount').textContent = movSel.size;
    const all = document.getElementById('movSelAll');
    if (all) all.checked = movPageIds.length > 0 && movPageIds.every(id => movSel.has(id));
}
async function deleteSelectedMovements() {
    if (!movSel.size) return;
    if (!confirm(`선택한 입출고 이력 ${movSel.size}건을 삭제할까요?\n해당 제품의 재고가 남은 이력 기준으로 다시 계산됩니다.`)) return;
    const res = await fetch('/api/inventory/movements', { method:'DELETE', headers:H, body:JSON.stringify({ ids:[...movSel] }) });
    if (!res.ok) { alert('삭제에 실패했습니다.'); return; }
    movSel.clear();
    loadMovements();
}
async function clearAllMovements() {
    if (!confirm('입출고 이력을 전부 비울까요?\n⚠ 이력이 있던 모든 제품의 재고가 0으로 리셋됩니다. 되돌릴 수 없습니다.')) return;
    const res = await fetch('/api/inventory/movements', { method:'DELETE', headers:H, body:JSON.stringify({ all:true }) });
    if (!res.ok) { alert('삭제에 실패했습니다.'); return; }
    movSel.clear();
    loadMovements();
}
let MOV_PRODUCTS = []; // 입출고 모달 제품 목록 (세트 여부 판단용)

// 검색어로 제품 드롭다운 옵션을 좁힘 — 선택값이 목록에서 사라지면 첫 번째 매치로 이동
function filterMovProductOptions() {
    const q = document.getElementById('mProductSearch').value.trim().toLowerCase();
    const sel = document.getElementById('mProduct');
    const prev = sel.value;
    const nq = q.replace(/\s+/g, ''); // 띄어쓰기 무시 매칭
    const nrm = s => (s||'').toLowerCase().replace(/\s+/g, '');
    const matches = !nq ? MOV_PRODUCTS : MOV_PRODUCTS.filter(p =>
        nrm(p.name).includes(nq) || nrm(p.sku).includes(nq) || nrm(p.search_tags).includes(nq));
    sel.innerHTML = matches.length
        ? matches.map(p=>`<option value="${p.id}">${p.is_bundle?'[세트] ':''}${_esc(p.name)} (${_esc(p.sku)})</option>`).join('')
        : '<option value="">검색 결과 없음</option>';
    if (matches.some(p => String(p.id) === prev)) sel.value = prev;
    onMovementProductChange();
}

async function openMovementModal() {
    // allProducts는 현재 페이지만 담고 있으므로 모달용 전체 목록은 별도 조회
    const r = await fetch('/api/inventory/products');
    MOV_PRODUCTS = await r.json();
    if (!allProjects.length) { const pr = await fetch('/api/inventory/projects'); allProjects = await pr.json(); }
    document.getElementById('mProductSearch').value = '';
    filterMovProductOptions();
    mvClearClient();
    document.getElementById('mType').value='in'; document.getElementById('mQty').value=1; document.getElementById('mMemo').value='';
    onMovementProductChange();
    openModal('movementModal');
}

// === 출고 대상 의뢰자 검색 → 연결 프로젝트 선택 (캘린더와 동일한 흐름) ===
let mvSearchTimer = null;
function mvClientSearchInput() {
    clearTimeout(mvSearchTimer);
    const q = document.getElementById('mClientSearch').value.trim();
    if (!q) { mvClearClient(); return; }
    mvSearchTimer = setTimeout(() => mvClientSearch(q), 250);
}
async function mvClientSearch(q) {
    const res = await fetch('/api/inventory/movement-clients?q='+encodeURIComponent(q));
    if (!res.ok) return;
    const clients = await res.json();
    const box = document.getElementById('mClientResults');
    if (!clients.length) {
        box.innerHTML = '<div style="padding:10px 12px; font-size:12px; color:var(--text-muted);">검색 결과가 없습니다.</div>';
    } else {
        box.innerHTML = clients.map(c => {
            const label = _esc(c.nickname || c.name || '') + (c.name && c.nickname && c.name !== c.nickname ? ` <span style="color:var(--text-muted); font-weight:400;">(${_esc(c.name)})</span>` : '');
            const cnt = c.projects.length ? `프로젝트 ${c.projects.length}건` : '진행 중 프로젝트 없음';
            return `<div onclick='mvSelectClient(${JSON.stringify(c).replace(/'/g, '&#39;')})' style="padding:9px 12px; cursor:pointer; border-bottom:1px solid var(--border); font-size:13px;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'">
                <b>${label}</b> <span style="font-size:11px; color:var(--text-muted);">· ${cnt}</span>
            </div>`;
        }).join('');
    }
    box.style.display = 'block';
}
function mvSelectClient(c) {
    document.getElementById('mClientResults').style.display = 'none';
    document.getElementById('mClientSearch').value = (c.nickname || c.name || '') + (c.name && c.nickname && c.name !== c.nickname ? ` (${c.name})` : '');
    document.getElementById('mClientClear').style.display = 'block';
    const sel = document.getElementById('mProject');
    if (!c.projects.length) {
        sel.innerHTML = '<option value="">선택 없음 (본사/창고)</option><option value="" disabled>이 의뢰자에게 진행 중인 프로젝트가 없습니다</option>';
        return;
    }
    sel.innerHTML = '<option value="">선택 없음 (본사/창고)</option>'
        + c.projects.map(p=>`<option value="${p.id}">${_esc(p.name)}${p.stage_label?` · ${_esc(p.stage_label)}`:''}</option>`).join('');
    if (c.projects.length === 1) sel.value = String(c.projects[0].id); // 하나뿐이면 자동 선택
}
// 검색을 지우면 전체 프로젝트 목록으로 복귀 (의뢰자 없는 출고도 가능)
function mvClearClient() {
    document.getElementById('mClientSearch').value = '';
    document.getElementById('mClientClear').style.display = 'none';
    document.getElementById('mClientResults').style.display = 'none';
    const sel = document.getElementById('mProject');
    sel.innerHTML = '<option value="">선택 없음 (본사/창고)</option>' + allProjects.map(p=>`<option value="${p.id}">${_esc(p.name)}</option>`).join('');
    sel.value = '';
}
// 세트 상품은 출고/반품만 가능 — 입고/조정 옵션 비활성화
function onMovementProductChange() {
    const p = MOV_PRODUCTS.find(x => x.id === +document.getElementById('mProduct').value);
    const isBundle = !!(p && p.is_bundle);
    const typeSel = document.getElementById('mType');
    [...typeSel.options].forEach(o => { o.disabled = isBundle && (o.value === 'in' || o.value === 'adjust'); });
    if (isBundle && (typeSel.value === 'in' || typeSel.value === 'adjust')) typeSel.value = 'out';
    onMovementTypeChange();
}
function onMovementTypeChange() {
    const t = document.getElementById('mType').value;
    document.getElementById('mProjectGroup').style.display = (t==='out' || t==='return') ? 'block' : 'none';
}
async function saveMovement(force) {
    const projectId = document.getElementById('mProject').value;
    if (!+document.getElementById('mProduct').value) { return alert('제품을 선택해주세요.'); }
    const body = {
        product_id:+document.getElementById('mProduct').value,
        movement_type:document.getElementById('mType').value,
        quantity:+document.getElementById('mQty').value,
        project_id: projectId ? +projectId : null,
        memo:document.getElementById('mMemo').value||null,
        force: !!force,
    };
    const res = await fetch('/api/inventory/movements',{method:'POST',headers:H,body:JSON.stringify(body)});
    if (res.status === 409) {
        // 세트 구성품 재고 부족 — 경고 후 진행 허용
        const e = await res.json().catch(()=>({}));
        const lines = (e.shortages||[]).map(s=>`• ${s.name}: 필요 ${s.need} / 보유 ${s.have}`).join('\n');
        if (confirm(`구성품 재고가 부족합니다:\n\n${lines}\n\n그래도 출고할까요? (구성품 재고가 음수로 내려갑니다)`)) {
            return saveMovement(true);
        }
        return;
    }
    if (!res.ok) { const e = await res.json().catch(()=>({})); alert(e.message||Object.values(e.errors||{}).flat().join('\n')||'오류 발생'); return; }
    closeModal('movementModal'); loadMovements();
}
// === 주문 내역 — 견적서 주문완료 건 + 직접 주문 (그룹 1건 → 펼치면 항목) ===
let ORDER_ROWS = [];
const expandedOrders = new Set(); // 'estimate-3' / 'manual-5'
async function loadOrders() {
    const res = await fetch('/api/inventory/office-orders');
    ORDER_ROWS = res.ok ? await res.json() : [];
    renderOrders();
}
function loadOfficeOrders() { loadOrders(); } // 새 창(주문 추가/수정) 저장 후 갱신 콜백
function orderKey(o) { return o.type + '-' + o.id; }
function toggleOrderGroup(k) {
    if (expandedOrders.has(k)) expandedOrders.delete(k); else expandedOrders.add(k);
    renderOrders();
}
const SHIP_ST = { delivered:['배송완료','badge-ok'], in_transit:['이동 중','badge-ordered'], out_for_delivery:['배송 출발','badge-ordered'], at_pickup:['인수','badge-ordered'], pending:['집화 전','badge-requested'], error:['조회 오류','badge-low'], unknown:['조회 전','badge-requested'] };
function renderOrders() {
    const tb = document.getElementById('orderBody');
    if (!ORDER_ROWS.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-row">주문 내역이 없습니다. 견적서에서 주문완료 표시를 하거나 주문 추가 버튼으로 등록하세요.</td></tr>';
        return;
    }
    tb.innerHTML = ORDER_ROWS.map(o => {
        const k = orderKey(o), open = expandedOrders.has(k);
        const badge = o.type === 'estimate'
            ? `<span class="badge badge-ordered">견적서 #${o.no}</span>`
            : '<span class="badge badge-requested">직접 주문</span>';
        const who = o.type === 'estimate' ? (o.client || '-') : (o.creator || '-');
        const acts = o.type === 'estimate'
            ? `<button class="btn-outline btn-sm" onclick="event.stopPropagation(); window.open('/estimates/${o.id}/edit','est_${o.id}')">견적서 열기</button>`
            : `<button class="btn-outline btn-sm" onclick="event.stopPropagation(); openOrderEdit(${o.id})">수정</button>
               <button class="btn-danger-sm" onclick="event.stopPropagation(); deleteOfficeOrder(${o.id})">삭제</button>`;
        let html = `<tr style="cursor:pointer;" onclick="toggleOrderGroup('${k}')">
            <td>${badge}</td>
            <td><span class="grp-arrow">${open ? '▾' : '▸'}</span><b>${_esc(o.title)}</b></td>
            <td class="text-muted">${(o.items||[]).length}개 품목</td>
            <td class="text-muted">${_esc(who)}</td>
            <td class="text-muted">${o.type === 'manual' && o.order_date ? `주문일 ${o.order_date}` : o.updated_at}</td>
            <td class="action-cell">${acts}</td>
        </tr>`;
        if (open) {
            html += (o.items||[]).map(it => {
                const noteCells = o.type === 'estimate'
                    ? `<td><input class="oi-src field-input" style="padding:6px 9px; font-size:12px;" placeholder="구매처" maxlength="100" value="${_esc(it.purchase_source)}" onclick="event.stopPropagation()"></td>
                       <td colspan="2"><div style="display:flex; gap:6px;"><input class="oi-memo field-input" style="padding:6px 9px; font-size:12px; flex:1;" placeholder="메모" maxlength="500" value="${_esc(it.memo)}" onclick="event.stopPropagation()">
                       <button class="btn-outline btn-sm" onclick="event.stopPropagation(); saveEstimateItemNote(${o.id}, ${it.index}, this)">저장</button></div></td>`
                    : `<td class="text-muted">${_esc(it.purchase_source) || '-'}</td>
                       <td class="text-muted" colspan="2">${_esc(it.memo) || '-'}</td>`;
                return `<tr style="background:var(--surface2);">
                    <td></td>
                    <td style="padding-left:26px;">${_esc(it.name)}</td>
                    <td class="text-muted">${it.qty}개</td>
                    ${noteCells}
                </tr>`;
            }).join('');
            if (o.type === 'estimate') {
                // 송장별 한 줄씩 + 상태 문구는 셀 안에서 줄바꿈 — 가로 스크롤 방지
                const ships = (o.shipments||[]).map(s => {
                    const [label, cls] = SHIP_ST[s.status] || SHIP_ST.unknown;
                    return `<div style="display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; padding:2px 0;">
                        <b style="white-space:nowrap;">${_esc(s.carrier_label)}</b>
                        <span style="white-space:nowrap;">${_esc(s.tracking_no)}</span>
                        <span class="badge ${cls}">${label}</span>
                        ${s.last_event ? `<span class="text-muted" style="white-space:normal; word-break:break-word; max-width:560px;">${_esc(s.last_event)}</span>` : ''}
                        ${s.delivered_at ? `<span class="text-muted" style="white-space:nowrap;">${s.delivered_at}</span>` : ''}
                    </div>`;
                }).join('');
                html += `<tr style="background:var(--surface2);"><td></td><td colspan="5" class="text-wrap" style="padding-left:26px; font-size:12px;">
                    <div style="display:flex; gap:10px; align-items:baseline;">
                        <span class="text-muted" style="font-weight:700; white-space:nowrap;">운송장</span>
                        <div style="flex:1; min-width:0;">${ships || '<span class="text-muted">등록된 운송장이 없습니다 — 견적서의 배송 정보에서 추가할 수 있습니다.</span>'}</div>
                    </div>
                </td></tr>`;
            }
        }
        return html;
    }).join('');
}
async function saveEstimateItemNote(estimateId, index, btn) {
    const tr = btn.closest('tr');
    const body = { index, purchase_source: tr.querySelector('.oi-src').value.trim(), memo: tr.querySelector('.oi-memo').value.trim() };
    const res = await fetch(`/api/inventory/office-orders/estimate/${estimateId}/item-note`, { method:'PATCH', headers:H, body: JSON.stringify(body) });
    if (!res.ok) { const e = await res.json().catch(()=>({})); alert(e.message || '저장에 실패했습니다.'); return; }
    // 로컬 데이터에도 반영 — 재렌더 시 값 유지
    const row = ORDER_ROWS.find(o => o.type === 'estimate' && o.id === estimateId);
    const item = row?.items.find(i => i.index === index);
    if (item) { item.purchase_source = body.purchase_source; item.memo = body.memo; }
    btn.textContent = '저장됨';
    setTimeout(() => { btn.textContent = '저장'; }, 1500);
}
function openOrderCreate() { window.open('/inventory/orders/new', 'office_order_new', 'width=780,height=640,scrollbars=yes,resizable=yes'); }
function openOrderEdit(id) { window.open(`/inventory/orders/${id}/edit`, 'office_order_'+id, 'width=780,height=640,scrollbars=yes,resizable=yes'); }
async function deleteOfficeOrder(id) {
    if (!confirm('이 주문 건을 삭제할까요?')) return;
    await fetch(`/api/inventory/office-orders/${id}`, { method:'DELETE', headers:H });
    loadOrders();
}

// 초기
const validTabs = ['products','movements','orders','categories'];

// 우선순위: URL 해시 > 마지막 본 탭(localStorage) > 제품 관리
// 'stock'은 제품 관리로 통합됨 — 예전 해시/저장값은 products로 매핑
const normTab = t => t === 'stock' ? 'products' : t;
const savedTab = normTab(localStorage.getItem('invLastTab'));
const hashTab = normTab(location.hash.slice(1));
const initTab = validTabs.includes(hashTab) ? hashTab
    : (validTabs.includes(savedTab) ? savedTab : 'products');
fetch('/api/inventory/categories').then(r=>r.json()).then(d=>{ catData=d; renderProdCatChips(); });
document.getElementById('prodPerPage').value = String(prodPerPage);
switchTab(initTab);
</script>
@endpush
