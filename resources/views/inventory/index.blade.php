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

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
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
    /* ── 장비 현황판 (매트릭스 보드) ── */
    .board-toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .board-toolbar > * { flex-shrink:0; }
    .board-toolbar .search-box { flex:1 1 220px; min-width:180px; max-width:320px; position:relative; }
    .board-toolbar .search-box input { width:100%; height:32px; padding:0 12px 0 30px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .board-toolbar .search-box input:focus { border-color:var(--accent); }
    .board-toolbar .search-box::before { content:"🔍"; position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:12px; opacity:.6; }
    .board-toolbar .stat-pill { display:inline-flex; align-items:center; gap:6px; height:28px; padding:0 12px; background:var(--surface2); border:1px solid var(--border); border-radius:14px; font-size:11px; color:var(--text-muted); line-height:1; white-space:nowrap; }
    .board-toolbar .stat-pill strong { color:var(--accent); font-family:"SF Mono",Menlo,Monaco,monospace; font-size:12px; font-weight:700; line-height:1; }
    .board-toolbar .spacer { flex:1 1 0; min-width:0; }
    .board-toolbar .tb-btn { display:inline-flex; align-items:center; gap:4px; height:32px; padding:0 12px; border:1px solid var(--border); background:none; border-radius:7px; color:var(--text-muted); font-size:12px; cursor:pointer; white-space:nowrap; font-family:inherit; }
    .board-toolbar .tb-btn:hover { border-color:var(--accent); color:var(--accent); }
    .board-toolbar .tb-btn.primary { background:var(--accent); color:#1a1207; border-color:var(--accent); font-weight:700; }
    [data-theme="light"] .board-toolbar .tb-btn.primary { color:#fff; }

    .board-wrap { max-height:calc(100vh - 280px); min-height:420px; overflow:auto; background:var(--surface); border:1px solid var(--border); border-radius:12px; -webkit-overflow-scrolling:touch; position:relative; }
    .eq-board { display:grid; min-width:max-content; border-collapse:collapse; }
    .eq-cell-base { border-right:1px solid var(--border); border-bottom:1px solid var(--border); background:var(--surface); display:flex; align-items:center; justify-content:center; transition:background .12s; }
    .eq-corner { position:sticky; top:0; left:0; z-index:6; background:var(--surface2); padding:10px 12px; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--text-muted); text-align:left; justify-content:flex-start; }
    .eq-col-header { position:sticky; top:0; z-index:5; background:var(--surface2); padding:10px 8px; font-size:12px; font-weight:600; color:var(--text); cursor:pointer; min-height:56px; flex-direction:column; gap:3px; text-align:center; }
    .eq-col-header:hover { background:var(--surface); }
    .eq-col-header .ch-name { font-size:13px; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
    .eq-col-header .ch-sub { font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; }
    .eq-col-header.empty { color:var(--text-muted); font-style:italic; font-weight:400; }
    .eq-col-header.empty:hover { color:var(--accent); }
    .eq-row-header { position:sticky; left:0; z-index:4; background:var(--surface2); padding:10px 12px; font-size:13px; color:var(--text); cursor:pointer; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; min-height:54px; }
    .eq-row-header:hover { background:var(--surface); }
    .eq-row-header .rh-name { font-weight:600; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
    .eq-row-header .rh-serial { font-family:"SF Mono",Menlo,monospace; font-size:10px; color:var(--text-muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
    .eq-row-header.empty { color:var(--text-muted); font-style:italic; font-weight:400; }
    .eq-row-header.empty:hover { color:var(--accent); }
    .eq-matrix-cell { cursor:pointer; min-height:54px; position:relative; }
    .eq-matrix-cell:hover { background:var(--surface2); }
    .eq-matrix-cell.marked { background:rgba(212,188,150,0.12); }
    .eq-matrix-cell.marked:hover { background:rgba(212,188,150,0.2); }
    [data-theme="light"] .eq-matrix-cell.marked { background:rgba(156,125,72,0.12); }
    [data-theme="light"] .eq-matrix-cell.marked:hover { background:rgba(156,125,72,0.22); }
    .eq-o-mark { width:30px; height:30px; display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:28px; line-height:1; transition:transform .15s; cursor:grab; touch-action:none; user-select:none; -webkit-user-select:none; }
    .eq-o-mark:active { cursor:grabbing; }
    .eq-matrix-cell:hover .eq-o-mark { transform:scale(1.08); }
    .eq-matrix-cell.dragging-source .eq-o-mark { opacity:0.25; transform:scale(0.9); }
    .eq-matrix-cell.drop-target { background:rgba(212,188,150,0.06); box-shadow:inset 0 0 0 1px rgba(212,188,150,0.3); }
    .eq-matrix-cell.drop-target.drop-hover { background:rgba(212,188,150,0.24); box-shadow:inset 0 0 0 2px var(--accent); }
    [data-theme="light"] .eq-matrix-cell.drop-target { background:rgba(156,125,72,0.08); box-shadow:inset 0 0 0 1px rgba(156,125,72,0.3); }
    [data-theme="light"] .eq-matrix-cell.drop-target.drop-hover { background:rgba(156,125,72,0.26); box-shadow:inset 0 0 0 2px var(--accent); }
    .eq-drag-ghost { position:fixed; pointer-events:none; z-index:300; width:34px; height:34px; display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:32px; line-height:1; text-shadow:0 4px 12px rgba(0,0,0,0.5); transform:translate(-50%,-50%); }
    body.eq-dragging { cursor:grabbing !important; }
    body.eq-dragging .eq-o-mark { cursor:grabbing; }
    body.eq-dragging .board-wrap { touch-action:none; }

    /* 로그 패널 */
    .eq-log-panel { position:fixed; top:0; right:0; bottom:0; width:380px; max-width:92vw; background:var(--surface); border-left:1px solid var(--border); z-index:90; transform:translateX(100%); transition:transform .25s cubic-bezier(.4,0,.2,1); display:flex; flex-direction:column; }
    .eq-log-panel.open { transform:translateX(0); }
    .eq-log-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .eq-log-head .title { font-size:14px; font-weight:700; }
    .eq-log-body { flex:1; overflow-y:auto; padding:12px 16px; display:flex; flex-direction:column; gap:8px; }
    .eq-log-item { padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; font-size:12px; line-height:1.5; }
    .eq-log-head-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
    .eq-log-user { font-weight:700; color:var(--accent); font-size:12px; }
    .eq-log-time { font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; }
    .eq-log-action { color:var(--text-muted); }
    .eq-log-action strong { color:var(--text); font-weight:600; }

    /* 토스트 */
    .eq-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px); background:var(--surface); color:var(--text); padding:10px 18px; border-radius:8px; border:1px solid var(--border); font-size:13px; opacity:0; pointer-events:none; transition:all .25s; z-index:200; box-shadow:0 6px 20px rgba(0,0,0,0.4); }
    .eq-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }

    @media (max-width: 768px) {
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
        <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openExcelImportModal('products','제품')">📥 엑셀 가져오기</button>
        <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openActivityLog('Product,ProductCategory,StockMovement,PurchaseOrder',0,'재고 전체 수정 로그')">📋 수정 로그</button>
    </div>

    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('stock')">재고 현황</button>
        <button class="tab-btn" onclick="switchTab('products')">제품 관리</button>
        <button class="tab-btn" onclick="switchTab('locations')">장비 위치</button>
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

    <!-- 장비 위치 · 매트릭스 현황판 (테스트) -->
    <div class="tab-panel" id="panel-locations">
        <div class="board-toolbar">
            <div class="search-box">
                <input type="text" id="bdSearch" placeholder="장비명, 시리얼, 대상 검색…">
            </div>
            <span class="stat-pill">장비 <strong id="bdStatItems">0</strong></span>
            <span class="stat-pill">대여중 <strong id="bdStatInUse">0</strong></span>
            <span class="stat-pill">대상 <strong id="bdStatTargets">0</strong></span>
            <div class="spacer"></div>
            <button class="tb-btn" id="bdScanBtn" title="QR 스캔">📷 스캔</button>
            <button class="tb-btn" id="bdGroupBtn" title="그룹 관리">🧩 그룹</button>
            <button class="tb-btn" id="bdLogBtn" title="변경 이력">📋 이력</button>
            <button class="tb-btn" onclick="openRentalItemModal(null)">＋ 장비</button>
            <button class="tb-btn primary" onclick="openRentalTargetModal(null)">＋ 대상</button>
        </div>
        <div class="board-wrap" id="bdBoardWrap">
            <div class="eq-board" id="bdBoard"></div>
        </div>
        <div class="text-muted" style="margin-top:8px; font-size:11px;">셀을 클릭하면 해당 대상으로 지정됩니다. ● 마크를 드래그해 같은 행의 다른 셀로 이동할 수 있습니다.</div>
    </div>

    <!-- 대여 장비: 추가/편집 모달 -->
    <div class="modal-overlay" id="rentalItemModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title" id="rentalItemTitle">＋ 장비 추가</div>
                <button class="modal-close" onclick="closeModal('rentalItemModal')">×</button>
            </div>
            <div class="field-group">
                <div class="field-label">장비명 *</div>
                <input class="field-input" id="riName">
            </div>
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">시리얼 번호</div>
                    <input class="field-input" id="riSerial">
                </div>
                <div class="field-group">
                    <div class="field-label">카테고리</div>
                    <input class="field-input" id="riCategory">
                </div>
            </div>
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">원래 위치 (반납 시 복귀)</div>
                    <select class="field-select" id="riHomeTarget"><option value="">없음</option></select>
                </div>
                <div class="field-group">
                    <div class="field-label">그룹</div>
                    <select class="field-select" id="riGroup"><option value="">없음</option></select>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">제품 구성</div>
                <textarea class="field-input" id="riComponents" rows="2"></textarea>
            </div>
            <div class="field-group">
                <div class="field-label">제품 설명 / 비고</div>
                <textarea class="field-input" id="riDesc" rows="2"></textarea>
            </div>
            <div class="field-group" id="riQrWrap" style="display:none;">
                <div class="field-label">QR 코드 (이 장비 식별용)</div>
                <div style="background:#fff; padding:8px; border-radius:8px; display:inline-block;">
                    <img id="riQrImg" alt="QR" style="display:block; width:160px; height:160px;">
                </div>
                <div class="text-muted" style="font-size:11px; margin-top:4px;">인쇄 후 장비에 부착 · 📷 스캔으로 인식</div>
            </div>
            <input type="hidden" id="riId">
            <div class="modal-actions">
                <button class="btn-danger-sm" id="riDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteRentalItem()">삭제</button>
                <button class="btn-cancel" onclick="closeModal('rentalItemModal')">취소</button>
                <button class="btn-save" onclick="saveRentalItem()">저장</button>
            </div>
        </div>
    </div>

    <!-- 대여 대상: 추가/편집 모달 -->
    <div class="modal-overlay" id="rentalTargetModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title" id="rentalTargetTitle">＋ 사용 대상 추가</div>
                <button class="modal-close" onclick="closeModal('rentalTargetModal')">×</button>
            </div>
            <div class="field-group">
                <div class="field-label">이름 / 명칭 *</div>
                <input class="field-input" id="rtName">
            </div>
            <div class="field-group">
                <div class="field-label">연락처</div>
                <input class="field-input" id="rtPhone">
            </div>
            <div class="field-group">
                <div class="field-label">주소 / 장소</div>
                <textarea class="field-input" id="rtAddress" rows="2"></textarea>
            </div>
            <div class="field-group">
                <div class="field-label">메모</div>
                <textarea class="field-input" id="rtNote" rows="2"></textarea>
            </div>
            <input type="hidden" id="rtId">
            <div class="modal-actions">
                <button class="btn-danger-sm" id="rtDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteRentalTarget()">삭제</button>
                <button class="btn-cancel" onclick="closeModal('rentalTargetModal')">취소</button>
                <button class="btn-save" onclick="saveRentalTarget()">저장</button>
            </div>
        </div>
    </div>

    <!-- 그룹 관리 모달 -->
    <div class="modal-overlay" id="groupManageModal">
        <div class="modal" style="width:520px;">
            <div class="modal-header">
                <div class="modal-title">🧩 그룹 관리</div>
                <button class="modal-close" onclick="closeModal('groupManageModal')">×</button>
            </div>
            <div class="field-group">
                <div class="field-label">새 그룹 추가</div>
                <div style="display:flex; gap:6px;">
                    <input class="field-input" id="newGroupName" style="flex:1;">
                    <button class="btn-save" onclick="saveNewGroup()">추가</button>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">그룹 목록 (장비 수 / 일괄 이동)</div>
                <div id="groupList" style="display:flex; flex-direction:column; gap:6px;"></div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('groupManageModal')">닫기</button>
            </div>
        </div>
    </div>

    <!-- 그룹 일괄 이동 모달 -->
    <div class="modal-overlay" id="groupAssignModal">
        <div class="modal" style="width:420px;">
            <div class="modal-header">
                <div class="modal-title" id="gaTitle">그룹 일괄 이동</div>
                <button class="modal-close" onclick="closeModal('groupAssignModal')">×</button>
            </div>
            <div class="field-group">
                <div class="field-label">이동할 대상</div>
                <select class="field-select" id="gaTarget"><option value="">선택하세요</option></select>
            </div>
            <input type="hidden" id="gaGroupId">
            <div class="modal-actions">
                <button class="btn-cancel" onclick="gaReturnAll()" style="margin-right:auto;">원래 위치로 반납</button>
                <button class="btn-cancel" onclick="closeModal('groupAssignModal')">취소</button>
                <button class="btn-save" onclick="gaAssign()">이동</button>
            </div>
        </div>
    </div>

    <!-- QR 스캐너 모달 -->
    <div class="modal-overlay" id="qrScanModal">
        <div class="modal" style="width:420px;">
            <div class="modal-header">
                <div class="modal-title">📷 QR 스캔</div>
                <button class="modal-close" onclick="stopQrScan(true)">×</button>
            </div>
            <div id="qrScanReader" style="width:100%; background:#000; border-radius:8px; overflow:hidden; min-height:260px;"></div>
            <div class="text-muted" style="font-size:11px; margin-top:6px;">장비 QR 코드를 카메라에 비춰주세요. 카메라 권한이 필요합니다.</div>
            <div class="field-group" style="margin-top:10px;">
                <div class="field-label">수동 입력 (테스트)</div>
                <div style="display:flex; gap:6px;">
                    <input class="field-input" id="qrManual" placeholder="예: rental:3 또는 3" style="flex:1;">
                    <button class="btn-cancel" onclick="handleScanResult(document.getElementById('qrManual').value)">확인</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR 스캔 결과 액션 모달 -->
    <div class="modal-overlay" id="scanActionModal">
        <div class="modal" style="width:420px;">
            <div class="modal-header">
                <div class="modal-title">장비 액션</div>
                <button class="modal-close" onclick="closeModal('scanActionModal')">×</button>
            </div>
            <div id="scanActionInfo" style="font-size:13px; line-height:1.6; color:var(--text-muted); margin-bottom:16px;"></div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <button class="btn-save" id="saReturnBtn" onclick="scanActionReturn()">반납 처리</button>
                <button class="btn-cancel" id="saDetailBtn" onclick="scanActionDetail()">상세 보기 (편집)</button>
                <button class="btn-cancel" id="saMoveBtn" onclick="scanActionMove()">다른 위치로 이동</button>
            </div>
            <input type="hidden" id="scanActionItemId">
        </div>
    </div>

    <!-- 단일 장비 이동 모달 (스캔 결과에서) -->
    <div class="modal-overlay" id="moveItemModal">
        <div class="modal" style="width:400px;">
            <div class="modal-header">
                <div class="modal-title" id="miTitle">위치 이동</div>
                <button class="modal-close" onclick="closeModal('moveItemModal')">×</button>
            </div>
            <div class="field-group">
                <div class="field-label">이동할 대상</div>
                <select class="field-select" id="miTarget"><option value="">선택하세요</option></select>
            </div>
            <input type="hidden" id="miItemId">
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('moveItemModal')">취소</button>
                <button class="btn-save" onclick="miAssign()">이동</button>
            </div>
        </div>
    </div>

    <!-- 매트릭스: 셀 액션 모달 -->
    <div class="modal-overlay" id="bdCellModal">
        <div class="modal" style="width:400px;">
            <div class="modal-header">
                <div class="modal-title" id="bdCellTitle">위치 지정</div>
                <button class="modal-close" onclick="closeModal('bdCellModal')">×</button>
            </div>
            <div id="bdCellInfo" style="font-size:13px; line-height:1.6; color:var(--text-muted);"></div>
            <div class="modal-actions">
                <button class="btn-cancel" id="bdCellClearBtn" style="margin-right:auto; display:none;">반납 처리</button>
                <button class="btn-cancel" onclick="closeModal('bdCellModal')">취소</button>
                <button class="btn-save" id="bdCellAssignBtn">이 위치로 지정</button>
            </div>
        </div>
    </div>

    <!-- 매트릭스: 로그 패널 -->
    <div class="eq-log-panel" id="bdLogPanel">
        <div class="eq-log-head">
            <span class="title">📋 변경 이력</span>
            <button class="modal-close" onclick="document.getElementById('bdLogPanel').classList.remove('open')">×</button>
        </div>
        <div class="eq-log-body" id="bdLogBody">
            <div class="empty-row">아직 기록된 변경이 없습니다.</div>
        </div>
    </div>

    <div class="eq-toast" id="bdToast"></div>

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
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
let allProducts = [], catData = [], allProjects = [];

function switchTab(name, skipHash) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        const map = {stock:'현황',products:'제품',locations:'장비 위치',movements:'입출고',orders:'발주',categories:'카테고리'};
        b.classList.toggle('active', b.textContent.includes(map[name]));
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+name));
    if (!skipHash) history.replaceState(null, '', '#'+name);
    ({stock:loadStock,products:loadProducts,locations:loadLocations,movements:loadMovements,orders:loadOrders,categories:loadCategories})[name]();
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => {
    if (e.key==='Escape') {
        if (qrScanner) stopQrScan(false);
        document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open'));
        document.getElementById('bdLogPanel')?.classList.remove('open');
    }
});

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
            <button class="btn-outline btn-sm" onclick="if(typeof openActivityLog==='function')openActivityLog('Product',${p.id},'${p.name.replace(/'/g,"\\'")} 수정 로그');else alert('로그 기능을 사용할 수 없습니다.');">📋</button>
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
    if (!allProjects.length) { const r = await fetch('/api/inventory/projects'); allProjects = await r.json(); }
    document.getElementById('mProduct').innerHTML = allProducts.map(p=>`<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
    document.getElementById('mProject').innerHTML = '<option value="">선택 없음 (본사/창고)</option>' + allProjects.map(p=>`<option value="${p.id}">${p.name}</option>`).join('');
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
    if (document.getElementById('panel-locations').classList.contains('active')) loadLocations();
}

// === 장비 현황판 (매트릭스 보드) ===
const bdState = { items:[], targets:[], groups:[], assignments:{}, logs:[] };
let qrScanner = null;
const bdDrag = { active:false, justDragged:false, itemId:null, fromTargetId:null, ghost:null, sourceCell:null, currentDropEl:null, startX:0, startY:0, pointerId:null };

function bdEsc(s) {
    if (s==null) return '';
    return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function bdToast(msg) {
    const t = document.getElementById('bdToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(bdToast._t);
    bdToast._t = setTimeout(()=>t.classList.remove('show'), 2200);
}

async function loadLocations() {
    const res = await fetch('/api/rental/board');
    const data = await res.json();
    bdState.items = data.items || [];
    bdState.targets = data.targets || [];
    bdState.groups = data.groups || [];
    bdState.assignments = data.assignments || {};
    bdState.logs = data.logs || [];
    bdRender();
    bdRenderLogs();
}

function bdFilteredItems() {
    const q = (document.getElementById('bdSearch').value || '').trim().toLowerCase();
    if (!q) return bdState.items;
    return bdState.items.filter(it => {
        const tg = bdState.targets.find(t => t.id === bdState.assignments[it.id]);
        return (it.name||'').toLowerCase().includes(q)
            || (it.serial||'').toLowerCase().includes(q)
            || (it.category||'').toLowerCase().includes(q)
            || (tg && (tg.name||'').toLowerCase().includes(q));
    });
}

function bdRender() {
    const board = document.getElementById('bdBoard');
    const items = bdFilteredItems();
    const targets = bdState.targets;
    const totalCols = targets.length + 1; // +1 for "+ 대상 추가" 빈 열
    const colWidth = 120, firstColWidth = 200;
    board.style.gridTemplateColumns = `${firstColWidth}px repeat(${totalCols}, minmax(${colWidth}px, 1fr))`;

    let html = `<div class="eq-cell-base eq-corner">장비 \\ 대상 →</div>`;
    targets.forEach(t => {
        html += `<div class="eq-cell-base eq-col-header" data-target-id="${t.id}">
            <div class="ch-name">${bdEsc(t.name)}</div>
            ${t.phone ? `<div class="ch-sub">${bdEsc(t.phone)}</div>` : ''}
        </div>`;
    });
    html += `<div class="eq-cell-base eq-col-header empty" data-add-target="1">＋ 대상 추가</div>`;

    items.forEach(item => {
        html += `<div class="eq-cell-base eq-row-header" data-item-id="${item.id}">
            <div class="rh-name">${bdEsc(item.name)}</div>
            ${item.serial ? `<div class="rh-serial">${bdEsc(item.serial)}</div>` : ''}
        </div>`;
        targets.forEach(t => {
            const marked = bdState.assignments[item.id] === t.id;
            html += `<div class="eq-cell-base eq-matrix-cell ${marked?'marked':''}" data-item-id="${item.id}" data-target-id="${t.id}">
                ${marked ? '<div class="eq-o-mark">●</div>' : ''}
            </div>`;
        });
        // 빈 대상 열 셀 (비활성)
        html += `<div class="eq-cell-base eq-matrix-cell" style="cursor:default;"></div>`;
    });

    // 마지막 빈 행: "+ 장비 추가"
    html += `<div class="eq-cell-base eq-row-header empty" data-add-item="1">＋ 장비 추가</div>`;
    for (let i = 0; i < totalCols; i++) {
        html += `<div class="eq-cell-base eq-matrix-cell" style="cursor:default;"></div>`;
    }

    board.innerHTML = html;

    document.getElementById('bdStatItems').textContent = bdState.items.length;
    document.getElementById('bdStatTargets').textContent = bdState.targets.length;
    document.getElementById('bdStatInUse').textContent = Object.keys(bdState.assignments).length;

    bdBindEvents();
}

function bdBindEvents() {
    document.querySelectorAll('#bdBoard .eq-row-header[data-item-id]').forEach(el => {
        el.addEventListener('click', () => openRentalItemModal(+el.dataset.itemId));
    });
    document.querySelectorAll('#bdBoard .eq-row-header[data-add-item]').forEach(el => {
        el.addEventListener('click', () => openRentalItemModal(null));
    });
    document.querySelectorAll('#bdBoard .eq-col-header[data-target-id]').forEach(el => {
        el.addEventListener('click', () => openRentalTargetModal(+el.dataset.targetId));
    });
    document.querySelectorAll('#bdBoard .eq-col-header[data-add-target]').forEach(el => {
        el.addEventListener('click', () => openRentalTargetModal(null));
    });
    document.querySelectorAll('#bdBoard .eq-matrix-cell[data-item-id][data-target-id]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (bdDrag.justDragged) { bdDrag.justDragged = false; return; }
            bdOpenCellModal(+el.dataset.itemId, +el.dataset.targetId);
        });
    });
    document.querySelectorAll('#bdBoard .eq-o-mark').forEach(mark => {
        mark.addEventListener('pointerdown', bdOnMarkDown);
    });
}

// ── 드래그앤드롭 (포인터 이벤트, PC/모바일 통합) ──
function bdOnMarkDown(e) {
    e.preventDefault(); e.stopPropagation();
    const cell = e.currentTarget.closest('.eq-matrix-cell'); if (!cell) return;
    bdDrag.itemId = +cell.dataset.itemId;
    bdDrag.fromTargetId = +cell.dataset.targetId;
    bdDrag.startX = e.clientX; bdDrag.startY = e.clientY;
    bdDrag.active = false; bdDrag.sourceCell = cell; bdDrag.pointerId = e.pointerId;
    try { e.currentTarget.setPointerCapture(e.pointerId); } catch(_) {}
    document.addEventListener('pointermove', bdOnMove);
    document.addEventListener('pointerup', bdOnUp, {once:true});
    document.addEventListener('pointercancel', bdOnUp, {once:true});
}
function bdOnMove(e) {
    if (!bdDrag.active) {
        if (Math.abs(e.clientX-bdDrag.startX)<5 && Math.abs(e.clientY-bdDrag.startY)<5) return;
        bdStartDrag();
    }
    if (bdDrag.ghost) { bdDrag.ghost.style.left = e.clientX+'px'; bdDrag.ghost.style.top = e.clientY+'px'; }
    const el = document.elementFromPoint(e.clientX, e.clientY);
    const dropCell = el?.closest('.eq-matrix-cell.drop-target');
    if (bdDrag.currentDropEl && bdDrag.currentDropEl !== dropCell) { bdDrag.currentDropEl.classList.remove('drop-hover'); bdDrag.currentDropEl = null; }
    if (dropCell && dropCell !== bdDrag.currentDropEl) { dropCell.classList.add('drop-hover'); bdDrag.currentDropEl = dropCell; }
}
function bdStartDrag() {
    bdDrag.active = true;
    bdDrag.sourceCell.classList.add('dragging-source');
    document.body.classList.add('eq-dragging');
    document.querySelectorAll(`#bdBoard .eq-matrix-cell[data-item-id="${bdDrag.itemId}"]`).forEach(c => {
        if (c !== bdDrag.sourceCell) c.classList.add('drop-target');
    });
    const ghost = document.createElement('div');
    ghost.className = 'eq-drag-ghost'; ghost.textContent = '●';
    ghost.style.left = bdDrag.startX+'px'; ghost.style.top = bdDrag.startY+'px';
    document.body.appendChild(ghost); bdDrag.ghost = ghost;
}
async function bdOnUp(e) {
    document.removeEventListener('pointermove', bdOnMove);
    if (!bdDrag.active) { bdResetDrag(); return; }
    const el = document.elementFromPoint(e.clientX, e.clientY);
    const dropCell = el?.closest('.eq-matrix-cell.drop-target');
    if (dropCell) {
        const newTargetId = +dropCell.dataset.targetId;
        await bdAssign(bdDrag.itemId, newTargetId, '드래그로 이동');
    }
    bdDrag.justDragged = true;
    bdResetDrag();
    setTimeout(()=>{ bdDrag.justDragged = false; }, 50);
}
function bdResetDrag() {
    document.body.classList.remove('eq-dragging');
    if (bdDrag.sourceCell) bdDrag.sourceCell.classList.remove('dragging-source');
    document.querySelectorAll('.eq-matrix-cell.drop-target').forEach(c => c.classList.remove('drop-target','drop-hover'));
    if (bdDrag.ghost) { bdDrag.ghost.remove(); bdDrag.ghost = null; }
    bdDrag.active = false; bdDrag.itemId = null; bdDrag.fromTargetId = null; bdDrag.sourceCell = null; bdDrag.currentDropEl = null;
}

// ── 셀 액션 모달 ──
let bdCellCtx = { itemId:null, targetId:null };
function bdOpenCellModal(itemId, targetId) {
    bdCellCtx = { itemId, targetId };
    const item = bdState.items.find(i=>i.id===itemId);
    const target = bdState.targets.find(t=>t.id===targetId);
    const currentTargetId = bdState.assignments[itemId];
    const currentTarget = currentTargetId ? bdState.targets.find(t=>t.id===currentTargetId) : null;
    const isCurrentlyHere = currentTargetId === targetId;

    document.getElementById('bdCellTitle').textContent = isCurrentlyHere ? '현재 위치' : '위치 지정';
    document.getElementById('bdCellInfo').innerHTML = `
        <div style="margin-bottom:10px;">
            <div style="font-size:11px;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">장비</div>
            <div style="color:var(--text);font-weight:600;">${bdEsc(item.name)} ${item.serial?`<span style="font-family:'SF Mono',Menlo,monospace;font-size:11px;color:var(--text-muted);">· ${bdEsc(item.serial)}</span>`:''}</div>
        </div>
        <div style="margin-bottom:10px;">
            <div style="font-size:11px;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">현재 위치</div>
            <div style="color:var(--text);">${currentTarget ? bdEsc(currentTarget.name) : '<span style="color:var(--text-muted);">본사/창고</span>'}</div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">${isCurrentlyHere ? '현재 위치' : '새 위치'}</div>
            <div style="color:var(--accent);font-weight:600;">${bdEsc(target.name)}</div>
        </div>
    `;
    const assignBtn = document.getElementById('bdCellAssignBtn');
    const clearBtn = document.getElementById('bdCellClearBtn');
    if (isCurrentlyHere) {
        assignBtn.style.display = 'none';
        clearBtn.style.display = 'inline-block';
    } else {
        assignBtn.style.display = 'inline-block';
        clearBtn.style.display = currentTargetId ? 'inline-block' : 'none';
    }
    openModal('bdCellModal');
}

async function bdAssign(itemId, targetId, memo) {
    const body = { item_id: itemId, target_id: targetId, memo: memo || null };
    const res = await fetch('/api/rental/assign', {method:'POST', headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || Object.values(e.errors||{}).flat().join('\n') || '오류'); return; }
    const item = bdState.items.find(i=>i.id===itemId);
    const target = bdState.targets.find(t=>t.id===targetId);
    bdToast(`${item?.name||''} → ${target?.name||''}`);
    await loadLocations();
}
async function bdClear(itemId, memo) {
    const body = { item_id: itemId, return: true, memo: memo || null };
    const res = await fetch('/api/rental/assign', {method:'POST', headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    const item = bdState.items.find(i=>i.id===itemId);
    const home = item?.home_target_id ? bdState.targets.find(t=>t.id===item.home_target_id) : null;
    bdToast(home ? `${item?.name||''} → ${home.name} (원위치)` : `${item?.name||''} 반납 처리`);
    await loadLocations();
}

// === 장비(item) 모달 ===
function openRentalItemModal(itemId) {
    const isEdit = !!itemId;
    const it = isEdit ? bdState.items.find(i=>i.id===itemId) : null;
    document.getElementById('rentalItemTitle').textContent = isEdit ? '장비 편집' : '＋ 장비 추가';
    document.getElementById('riId').value = itemId || '';
    document.getElementById('riName').value = it?.name || '';
    document.getElementById('riSerial').value = it?.serial || '';
    document.getElementById('riCategory').value = it?.category || '';
    document.getElementById('riComponents').value = it?.components || '';
    document.getElementById('riDesc').value = it?.description || '';

    const homeSel = document.getElementById('riHomeTarget');
    homeSel.innerHTML = '<option value="">없음</option>' + bdState.targets.map(t=>`<option value="${t.id}">${bdEsc(t.name)}</option>`).join('');
    homeSel.value = it?.home_target_id || '';

    const grpSel = document.getElementById('riGroup');
    grpSel.innerHTML = '<option value="">없음</option>' + bdState.groups.map(g=>`<option value="${g.id}">${bdEsc(g.name)}</option>`).join('');
    grpSel.value = it?.group_id || '';

    const qrWrap = document.getElementById('riQrWrap');
    if (isEdit) {
        qrWrap.style.display = 'block';
        document.getElementById('riQrImg').src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent('rental:'+itemId)}&size=200x200&margin=8`;
    } else {
        qrWrap.style.display = 'none';
    }

    document.getElementById('riDeleteBtn').style.display = isEdit ? 'inline-block' : 'none';
    openModal('rentalItemModal');
    setTimeout(()=>document.getElementById('riName').focus(), 50);
}
async function saveRentalItem() {
    const id = document.getElementById('riId').value;
    const body = {
        name: document.getElementById('riName').value.trim(),
        serial: document.getElementById('riSerial').value.trim() || null,
        category: document.getElementById('riCategory').value.trim() || null,
        components: document.getElementById('riComponents').value.trim() || null,
        description: document.getElementById('riDesc').value.trim() || null,
        home_target_id: document.getElementById('riHomeTarget').value ? +document.getElementById('riHomeTarget').value : null,
        group_id: document.getElementById('riGroup').value ? +document.getElementById('riGroup').value : null,
    };
    if (!body.name) { bdToast('장비명은 필수입니다.'); return; }
    const url = id ? `/api/rental/items/${id}` : '/api/rental/items';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {method, headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(Object.values(e.errors||{}).flat().join('\n') || e.message || '오류'); return; }
    closeModal('rentalItemModal');
    bdToast(id ? '장비 정보가 수정되었습니다.' : '새 장비가 추가되었습니다.');
    await loadLocations();
}
async function deleteRentalItem() {
    const id = document.getElementById('riId').value;
    if (!id) return;
    const it = bdState.items.find(i=>i.id===+id);
    if (!confirm(`"${it?.name}" 장비를 삭제하시겠습니까?`)) return;
    const res = await fetch(`/api/rental/items/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    closeModal('rentalItemModal');
    bdToast('장비가 삭제되었습니다.');
    await loadLocations();
}

// === 대상(target) 모달 ===
function openRentalTargetModal(targetId) {
    const isEdit = !!targetId;
    const tg = isEdit ? bdState.targets.find(t=>t.id===targetId) : null;
    document.getElementById('rentalTargetTitle').textContent = isEdit ? '사용 대상 편집' : '＋ 사용 대상 추가';
    document.getElementById('rtId').value = targetId || '';
    document.getElementById('rtName').value = tg?.name || '';
    document.getElementById('rtPhone').value = tg?.phone || '';
    document.getElementById('rtAddress').value = tg?.address || '';
    document.getElementById('rtNote').value = tg?.note || '';
    document.getElementById('rtDeleteBtn').style.display = isEdit ? 'inline-block' : 'none';
    openModal('rentalTargetModal');
    setTimeout(()=>document.getElementById('rtName').focus(), 50);
}
async function saveRentalTarget() {
    const id = document.getElementById('rtId').value;
    const body = {
        name: document.getElementById('rtName').value.trim(),
        phone: document.getElementById('rtPhone').value.trim() || null,
        address: document.getElementById('rtAddress').value.trim() || null,
        note: document.getElementById('rtNote').value.trim() || null,
    };
    if (!body.name) { bdToast('이름은 필수입니다.'); return; }
    const url = id ? `/api/rental/targets/${id}` : '/api/rental/targets';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {method, headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(Object.values(e.errors||{}).flat().join('\n') || e.message || '오류'); return; }
    closeModal('rentalTargetModal');
    bdToast(id ? '대상 정보가 수정되었습니다.' : '새 대상이 추가되었습니다.');
    await loadLocations();
}
async function deleteRentalTarget() {
    const id = document.getElementById('rtId').value;
    if (!id) return;
    const tg = bdState.targets.find(t=>t.id===+id);
    if (!confirm(`"${tg?.name}" 대상을 삭제하시겠습니까?\n이 대상에 지정된 장비는 위치가 해제됩니다.`)) return;
    const res = await fetch(`/api/rental/targets/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    closeModal('rentalTargetModal');
    bdToast('대상이 삭제되었습니다.');
    await loadLocations();
}

document.getElementById('bdCellAssignBtn').addEventListener('click', async () => {
    await bdAssign(bdCellCtx.itemId, bdCellCtx.targetId, '매트릭스에서 지정');
    closeModal('bdCellModal');
});
document.getElementById('bdCellClearBtn').addEventListener('click', async () => {
    await bdClear(bdCellCtx.itemId, '매트릭스에서 반납');
    closeModal('bdCellModal');
});

// ── 로그 패널 ──
function bdRenderLogs() {
    const body = document.getElementById('bdLogBody');
    if (!bdState.logs.length) { body.innerHTML = '<div class="empty-row">아직 기록된 변경이 없습니다.</div>'; return; }
    body.innerHTML = bdState.logs.map(log => `
        <div class="eq-log-item">
            <div class="eq-log-head-row">
                <span class="eq-log-user">${bdEsc(log.user||'-')}</span>
                <span class="eq-log-time">${fmtTime(log.created_at)}</span>
            </div>
            <div class="eq-log-action"><strong>${bdEsc(log.action||'')}</strong>${log.detail?' · '+bdEsc(log.detail):''}</div>
        </div>
    `).join('');
}
document.getElementById('bdLogBtn').addEventListener('click', () => { document.getElementById('bdLogPanel').classList.add('open'); bdRenderLogs(); });
document.getElementById('bdSearch').addEventListener('input', () => bdRender());

// === 그룹 관리 ===
document.getElementById('bdGroupBtn').addEventListener('click', () => { renderGroupList(); openModal('groupManageModal'); });
function renderGroupList() {
    const wrap = document.getElementById('groupList');
    if (!bdState.groups.length) { wrap.innerHTML = '<div class="text-muted" style="padding:10px 0;">등록된 그룹이 없습니다.</div>'; return; }
    wrap.innerHTML = bdState.groups.map(g => {
        const count = bdState.items.filter(i => i.group_id === g.id).length;
        return `<div style="display:flex; align-items:center; gap:6px; padding:8px 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px;">
            <span style="flex:1; font-weight:600;">${bdEsc(g.name)}</span>
            <span class="text-muted" style="font-size:11px;">장비 ${count}개</span>
            <button class="btn-outline btn-sm" onclick="openGroupAssignModal(${g.id})">이동</button>
            <button class="btn-outline btn-sm" onclick="renameGroup(${g.id})">이름</button>
            <button class="btn-danger-sm" onclick="deleteGroup(${g.id})">삭제</button>
        </div>`;
    }).join('');
}
async function saveNewGroup() {
    const name = document.getElementById('newGroupName').value.trim();
    if (!name) { bdToast('그룹명을 입력하세요.'); return; }
    const res = await fetch('/api/rental/groups', {method:'POST', headers:H, body:JSON.stringify({name})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    document.getElementById('newGroupName').value = '';
    await loadLocations();
    renderGroupList();
}
async function renameGroup(id) {
    const g = bdState.groups.find(x=>x.id===id);
    const name = prompt('그룹명', g?.name || '');
    if (!name || !name.trim()) return;
    const res = await fetch(`/api/rental/groups/${id}`, {method:'PATCH', headers:H, body:JSON.stringify({name: name.trim()})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    await loadLocations();
    renderGroupList();
}
async function deleteGroup(id) {
    const g = bdState.groups.find(x=>x.id===id);
    if (!confirm(`"${g?.name}" 그룹을 삭제할까요?\n소속 장비의 그룹 소속만 해제됩니다.`)) return;
    const res = await fetch(`/api/rental/groups/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    await loadLocations();
    renderGroupList();
}
function openGroupAssignModal(groupId) {
    const g = bdState.groups.find(x=>x.id===groupId);
    const count = bdState.items.filter(i=>i.group_id===groupId).length;
    document.getElementById('gaTitle').textContent = `[${g.name}] 일괄 이동 (${count}개)`;
    document.getElementById('gaGroupId').value = groupId;
    document.getElementById('gaTarget').innerHTML = '<option value="">선택하세요</option>' + bdState.targets.map(t=>`<option value="${t.id}">${bdEsc(t.name)}</option>`).join('');
    openModal('groupAssignModal');
}
async function gaAssign() {
    const groupId = +document.getElementById('gaGroupId').value;
    const targetId = document.getElementById('gaTarget').value;
    if (!targetId) { bdToast('대상을 선택하세요.'); return; }
    const res = await fetch('/api/rental/assign-group', {method:'POST', headers:H, body:JSON.stringify({group_id: groupId, target_id: +targetId})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    const data = await res.json();
    bdToast(`${data.updated}개 장비 이동 완료`);
    closeModal('groupAssignModal'); closeModal('groupManageModal');
    await loadLocations();
}
async function gaReturnAll() {
    const groupId = +document.getElementById('gaGroupId').value;
    if (!confirm('그룹 내 모든 장비를 각자의 원래 위치로 반납할까요?')) return;
    const res = await fetch('/api/rental/assign-group', {method:'POST', headers:H, body:JSON.stringify({group_id: groupId, return: true})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    const data = await res.json();
    bdToast(`${data.updated}개 장비 반납 완료`);
    closeModal('groupAssignModal'); closeModal('groupManageModal');
    await loadLocations();
}

// === QR 스캔 ===
document.getElementById('bdScanBtn').addEventListener('click', () => startQrScan());
async function startQrScan() {
    document.getElementById('qrManual').value = '';
    openModal('qrScanModal');
    if (typeof Html5Qrcode === 'undefined') { return; }
    try {
        qrScanner = new Html5Qrcode('qrScanReader');
        await qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            (decoded) => { handleScanResult(decoded); },
            () => {}
        );
    } catch (err) {
        // 카메라 실패 시 수동 입력 fallback
    }
}
async function stopQrScan(alsoClose) {
    if (qrScanner) {
        try { await qrScanner.stop(); qrScanner.clear(); } catch(_) {}
        qrScanner = null;
    }
    if (alsoClose) closeModal('qrScanModal');
}
async function handleScanResult(text) {
    if (!text) return;
    const match = String(text).match(/^(?:rental:)?(\d+)$/i);
    if (!match) { bdToast('인식 실패: rental:ID 형식이 아닙니다.'); return; }
    const itemId = +match[1];
    const item = bdState.items.find(i=>i.id===itemId);
    await stopQrScan(false);
    closeModal('qrScanModal');
    if (!item) { bdToast(`장비 ID ${itemId}를 찾을 수 없습니다.`); return; }
    openScanActionModal(item);
}
function openScanActionModal(item) {
    const currentTarget = item.current_target_id ? bdState.targets.find(t=>t.id===item.current_target_id) : null;
    const home = item.home_target_id ? bdState.targets.find(t=>t.id===item.home_target_id) : null;
    document.getElementById('scanActionItemId').value = item.id;
    document.getElementById('scanActionInfo').innerHTML = `
        <div style="color:var(--text); font-weight:700; font-size:14px; margin-bottom:6px;">${bdEsc(item.name)}</div>
        ${item.serial ? `<div style="font-family:'SF Mono',Menlo,monospace; font-size:11px;">${bdEsc(item.serial)}</div>` : ''}
        <div style="margin-top:8px;">현재 위치: <span style="color:var(--text);">${currentTarget ? bdEsc(currentTarget.name) : '미지정'}</span></div>
        ${home ? `<div>원래 위치: <span style="color:var(--text);">${bdEsc(home.name)}</span></div>` : ''}
    `;
    openModal('scanActionModal');
}
async function scanActionReturn() {
    const id = +document.getElementById('scanActionItemId').value;
    closeModal('scanActionModal');
    await bdClear(id, 'QR 스캔 반납');
}
function scanActionDetail() {
    const id = +document.getElementById('scanActionItemId').value;
    closeModal('scanActionModal');
    openRentalItemModal(id);
}
function scanActionMove() {
    const id = +document.getElementById('scanActionItemId').value;
    closeModal('scanActionModal');
    openMoveItemModal(id);
}
function openMoveItemModal(itemId) {
    const item = bdState.items.find(i=>i.id===itemId);
    document.getElementById('miTitle').textContent = `${item.name} · 위치 이동`;
    document.getElementById('miItemId').value = itemId;
    document.getElementById('miTarget').innerHTML = '<option value="">선택하세요</option>' + bdState.targets.map(t=>`<option value="${t.id}">${bdEsc(t.name)}</option>`).join('');
    openModal('moveItemModal');
}
async function miAssign() {
    const itemId = +document.getElementById('miItemId').value;
    const targetId = document.getElementById('miTarget').value;
    if (!targetId) { bdToast('대상을 선택하세요.'); return; }
    closeModal('moveItemModal');
    await bdAssign(itemId, +targetId, 'QR 스캔으로 이동');
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
const validTabs = ['stock','products','locations','movements','orders','categories'];

const initTab = validTabs.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'stock';
fetch('/api/inventory/categories').then(r=>r.json()).then(d=>{ catData=d; });
switchTab(initTab);
</script>
@endpush
