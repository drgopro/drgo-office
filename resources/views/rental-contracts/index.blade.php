@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '렌탈 계약 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:20px; }
    .page-title { font-size:20px; font-weight:700; display:flex; align-items:center; gap:8px; min-width:0; }
    .header-actions { display:flex; gap:8px; flex-shrink:0; }

    .stat-row { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:20px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px 18px; min-width:0; }
    .stat-label { font-size:10px; color:var(--text-muted); letter-spacing:0.15em; text-transform:uppercase; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .stat-value { font-size:22px; font-weight:700; white-space:nowrap; }

    .toolbar { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
    .toolbar input, .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; min-height:36px; }
    .toolbar input[type=text] { width:260px; }
    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; min-height:36px; }
    [data-theme="light"] .btn-primary { color:#fff; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 12px; border-radius:6px; font-size:12px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:5px; min-height:34px; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .btn-outline.danger { border-color:var(--red); color:var(--red); }

    /* 장비 렌탈 / 서버 렌탈 탭 — 프로젝트 목록의 전체/진행/완료/취소 탭과 동일한 알약(세그먼트) 형태, 상단 가운데 */
    .proj-status-tabs { position:relative; display:flex; gap:4px; background:var(--surface2); border:1px solid var(--border); border-radius:999px; padding:5px; margin-bottom:14px; overflow-x:auto; }
    .pst-tab { flex:1; position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 16px; font-size:13px; font-weight:600; color:var(--text-muted); text-decoration:none; border-radius:999px; white-space:nowrap; transition:color 0.15s; }
    .pst-tab:hover:not(.active) { color:var(--text); background:rgba(255,255,255,0.07); transition:color 0.15s, background 0.15s; }
    [data-theme="light"] .pst-tab:hover:not(.active) { background:rgba(255,255,255,0.65); }
    .pst-tab.active { color:var(--text); font-weight:800; }
    .pst-pill { position:absolute; top:5px; bottom:5px; left:0; width:0; border-radius:999px; background:var(--surface); box-shadow:0 2px 8px rgba(0,0,0,0.22); transition:left 0.28s cubic-bezier(.4,0,.2,1), width 0.28s cubic-bezier(.4,0,.2,1); z-index:0; }
    [data-theme="light"] .pst-pill { background:#fff; box-shadow:0 2px 8px rgba(29,45,61,0.14); }
    .proj-status-tabs:not(.pst-js) .pst-tab.active { background:var(--surface); box-shadow:0 2px 8px rgba(0,0,0,0.22); }
    [data-theme="light"] .proj-status-tabs:not(.pst-js) .pst-tab.active { background:#fff; box-shadow:0 2px 8px rgba(29,45,61,0.14); }
    .pst-count { font-size:11px; font-weight:700; padding:1px 7px; border-radius:9px; background:rgba(200,80,80,0.12); color:var(--red); white-space:nowrap; flex-shrink:0; }
    .pst-count-active { background:rgba(45,138,62,0.12); color:#2d8a3e; }
    .pst-count-done { background:rgba(74,144,217,0.14); color:#4a90d9; }
    [data-theme="light"] .pst-count-done { background:#e0f0ff; color:#2e6a9a; }
    .rental-kind-tabs { width:max-content; max-width:100%; margin:0 auto 18px; }
    .rental-kind-tabs .pst-tab { min-width:150px; }

    /* 상태 칩 — 어떤 폭에서도 한 줄 (nowrap + 축소 금지) */
    .status-badge { display:inline-flex; align-items:center; font-size:11px; padding:2px 9px; border-radius:999px; font-weight:700; white-space:nowrap; flex-shrink:0; line-height:1.7; }
    .status-active { background:rgba(122,200,122,0.15); color:#7ac87a; border:1px solid rgba(122,200,122,0.35); }
    .status-pending { background:rgba(74,144,217,0.14); color:#6aa8e8; border:1px solid rgba(74,144,217,0.35); }
    .status-terminated { background:rgba(160,160,160,0.15); color:var(--text-muted); border:1px solid var(--border); }
    [data-theme="light"] .status-active { background:#e8f5e8; color:#1a7a2a; border-color:#a0d8a0; }
    [data-theme="light"] .status-pending { background:#e0f0ff; color:#2e6a9a; border-color:#a8cbe8; }
    .fee-manual-tag { display:inline-flex; align-items:center; font-size:10px; padding:0 6px; border-radius:4px; font-weight:700; white-space:nowrap; flex-shrink:0; background:rgba(230,160,60,0.16); color:#d9a24a; border:1px solid rgba(230,160,60,0.4); line-height:1.7; margin-left:4px; }
    [data-theme="light"] .fee-manual-tag { background:#fff3e0; color:#9a5a10; border-color:#f0c890; }
    .mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; }

    /* 계약 카드 — 리스트형: 한 줄에 한 계약 (최신순이 위→아래로 읽히도록). 폭이 줄면 한 카드 안에서 줄바꿈 */
    .cards { display:flex; flex-direction:column; gap:8px; }
    .cards-empty { text-align:center; padding:40px; color:var(--text-muted); background:var(--surface); border:1px solid var(--border); border-radius:12px; }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px 16px; display:grid; grid-template-columns:minmax(180px, 0.8fr) 340px minmax(0, 1.5fr) auto; grid-template-areas:"main meta body side"; align-items:center; gap:10px 16px; min-width:0; }
    .card-main { grid-area:main; display:flex; flex-direction:column; gap:4px; min-width:0; }
    .card-product { font-size:15px; font-weight:800; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:1.35; }
    .card-product.none { color:var(--text-muted); font-weight:600; }
    .card-client { display:flex; align-items:center; gap:6px; font-size:13px; min-width:0; }
    .card-client span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
    .card-client svg { flex-shrink:0; width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2; }
    .card-client a { text-decoration:none; flex-shrink:0; }
    .card-meta { grid-area:meta; display:grid; grid-template-columns:1fr 1fr 1.1fr; gap:10px; padding:8px 14px; background:var(--surface2); border-radius:8px; min-width:0; }
    .card-meta div { min-width:0; }
    .card-meta .k { font-size:10px; color:var(--text-muted); letter-spacing:0.08em; text-transform:uppercase; margin-bottom:2px; white-space:nowrap; }
    .card-meta .v { font-size:13px; font-weight:600; white-space:nowrap; display:flex; align-items:center; overflow:hidden; text-overflow:ellipsis; }
    .card-meta .fee { color:var(--accent); font-weight:700; }
    .card-access { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); min-width:0; }
    .card-access span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
    .card-body { grid-area:body; display:flex; flex-direction:column; gap:6px; min-width:0; }
    .card-memo { font-size:12px; color:var(--text-muted); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    /* 결제 현황 — 결제일(갱신일)마다 칩 하나. 클릭(rental.edit)으로 결제됨 체크/해제 */
    .card-pay { display:flex; align-items:center; gap:4px; flex-wrap:wrap; min-width:0; }
    .pay-label { font-size:11px; color:var(--text-muted); margin-right:2px; white-space:nowrap; }
    .pay-chip { display:inline-flex; align-items:center; gap:3px; font-size:11px; font-weight:700; padding:1px 8px; border-radius:999px; border:1px solid var(--border); background:var(--surface2); color:var(--text-muted); white-space:nowrap; line-height:1.7; cursor:default; font-family:inherit; }
    button.pay-chip { cursor:pointer; }
    button.pay-chip:hover { filter:brightness(1.08); }
    .pay-chip.paid { background:rgba(122,200,122,0.18); color:#7ac87a; border-color:rgba(122,200,122,0.45); }
    .pay-chip.due { background:rgba(230,160,60,0.16); color:#d9a24a; border-color:rgba(230,160,60,0.55); box-shadow:0 0 0 2px rgba(230,160,60,0.12); }
    .pay-chip.overdue { background:rgba(200,80,80,0.14); color:var(--red); border-color:rgba(200,80,80,0.5); }
    .pay-chip.upcoming { border-style:dashed; opacity:.8; }
    .pay-chip.more { font-weight:600; }
    [data-theme="light"] .pay-chip.paid { background:#e8f5e8; color:#1a7a2a; border-color:#a0d8a0; }
    [data-theme="light"] .pay-chip.due { background:#fff3e0; color:#9a5a10; border-color:#f0c890; }
    [data-theme="light"] .pay-chip.overdue { background:#fdecec; color:#b03030; border-color:#f0b0b0; }
    .overdue-tag { display:inline-flex; align-items:center; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; background:rgba(200,80,80,0.14); color:var(--red); border:1px solid rgba(200,80,80,0.45); line-height:1.7; }
    .due-tag { display:inline-flex; align-items:center; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; background:rgba(230,160,60,0.16); color:#d9a24a; border:1px solid rgba(230,160,60,0.5); line-height:1.7; }
    [data-theme="light"] .overdue-tag { background:#fdecec; color:#b03030; border-color:#f0b0b0; }
    [data-theme="light"] .due-tag { background:#fff3e0; color:#9a5a10; border-color:#f0c890; }
    .card-side { grid-area:side; display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
    .card-side-row { display:flex; align-items:center; gap:8px; }
    .card-updated { font-size:11px; color:var(--text-muted); white-space:nowrap; }
    .card-updated b { font-weight:600; color:var(--text); }

    /* 모달 */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:520px; max-width:95vw; max-height:92vh; overflow-y:auto; padding:24px; }
    .modal h3 { font-size:16px; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
    .field-group { margin-bottom:14px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; min-height:38px; }
    .field-input:disabled { color:var(--text-muted); opacity:.8; }
    .field-hint { font-size:11px; color:var(--text-muted); margin-top:5px; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .link-btn { background:none; border:none; color:var(--accent); font-size:12px; font-weight:600; cursor:pointer; padding:0 4px; white-space:nowrap; }
    .toggle { display:inline-flex; align-items:center; gap:8px; font-size:12px; color:var(--text); cursor:pointer; user-select:none; }
    .toggle input { position:absolute; opacity:0; width:0; height:0; }
    .toggle .sw { width:34px; height:20px; border-radius:999px; background:var(--border); position:relative; transition:background .15s; flex-shrink:0; }
    .toggle .sw::after { content:""; position:absolute; top:2px; left:2px; width:16px; height:16px; border-radius:50%; background:#fff; transition:left .15s; }
    .toggle input:checked + .sw { background:var(--accent); }
    .toggle input:checked + .sw::after { left:16px; }
    .status-pick { display:flex; gap:6px; }
    .status-pick button { flex:1; display:inline-flex; align-items:center; justify-content:center; padding:8px 6px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; min-height:38px; }
    .status-pick button.on[data-status="active"] { background:rgba(122,200,122,0.15); color:#7ac87a; border-color:rgba(122,200,122,0.5); }
    .status-pick button.on[data-status="pending"] { background:rgba(74,144,217,0.14); color:#6aa8e8; border-color:rgba(74,144,217,0.5); }
    .status-pick button.on[data-status="terminated"] { background:var(--surface2); color:var(--text); border-color:var(--text-muted); }
    [data-theme="light"] .status-pick button.on[data-status="active"] { background:#e8f5e8; color:#1a7a2a; border-color:#a0d8a0; }
    [data-theme="light"] .status-pick button.on[data-status="pending"] { background:#e0f0ff; color:#2e6a9a; border-color:#a8cbe8; }
    .server-only { display:none; }
    .kind-server .server-only { display:block; }
    .client-search-wrap { position:relative; }
    .client-search-results { display:none; position:absolute; top:100%; left:0; right:0; background:var(--surface); border:1px solid var(--border); border-radius:0 0 8px 8px; max-height:240px; overflow-y:auto; z-index:10; box-shadow:0 4px 12px rgba(0,0,0,0.2); }
    .client-search-results.open { display:block; }
    .client-search-result { padding:10px 12px; font-size:13px; cursor:pointer; border-bottom:1px solid var(--border); }
    .client-search-result:hover { background:var(--surface2); }

    /* 상품 관리 모달 */
    .prod-list { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
    .prod-row { display:grid; grid-template-columns:minmax(0, 1fr) 110px auto; gap:8px; align-items:center; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); }
    .prod-row.inactive { opacity:.55; }
    .prod-row .pn { font-size:13px; font-weight:600; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .prod-row .pm { font-size:11px; color:var(--text-muted); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .prod-row .pf { font-size:13px; font-weight:700; color:var(--accent); white-space:nowrap; text-align:right; }
    .prod-row .pa { display:flex; gap:4px; }
    .prod-row .pa button { padding:4px 8px; min-height:30px; font-size:11px; }
    .prod-form { display:grid; grid-template-columns:minmax(0, 1fr) 120px; gap:8px; align-items:start; padding-top:12px; border-top:1px solid var(--border); }
    .prod-form .full { grid-column:1 / -1; }
    .prod-form-actions { grid-column:1 / -1; display:flex; gap:8px; justify-content:flex-end; }

    @media (max-width:1023px) {
        .card { grid-template-columns:minmax(0, 1fr) auto; grid-template-areas:"main side" "meta meta" "body body"; gap:10px 12px; }
        .card-meta { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px; }
        .card-meta .v { overflow:hidden; text-overflow:ellipsis; }
        .card-memo { white-space:normal; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
    }
    @media (max-width:639px) {
        .page-wrap { padding:16px; }
        .rental-kind-tabs { width:100%; }
        .rental-kind-tabs .pst-tab { min-width:0; padding:8px 10px; }
        .page-header { flex-wrap:wrap; }
        .page-title { font-size:18px; }
        .stat-row { gap:8px; grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .stat-card { padding:10px 12px; }
        .stat-label { letter-spacing:0.06em; font-size:9px; }
        .stat-value { font-size:16px; }
        .toolbar input[type=text] { width:100%; flex:1 1 100%; }
        .toolbar select { flex:1; min-width:0; }
        .card { grid-template-columns:minmax(0, 1fr); grid-template-areas:"main" "meta" "body" "side"; }
        .card-side { flex-direction:row; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:10px; }
        .field-row { grid-template-columns:1fr; }
        .prod-row { grid-template-columns:minmax(0, 1fr) auto; }
        .prod-row .pf { grid-column:1; text-align:left; }
        .prod-row .pa { grid-column:2; grid-row:1 / span 2; }
        .prod-form { grid-template-columns:1fr; }
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
        @if(Auth::user()->hasPermission('rental.edit'))
        <div class="header-actions">
            <button type="button" class="btn-outline" onclick="openProductModal()" title="렌탈 상품(항목) 생성·편집·삭제">⚙ 상품 관리</button>
            <button class="btn-primary" id="addBtn" onclick="openContractModal()">+ 계약 등록</button>
        </div>
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
            <div class="stat-label">미납 결제</div>
            <div class="stat-value" id="statOverdue" style="color:var(--red);">–</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">전체 계약</div>
            <div class="stat-value" id="statTotal">–</div>
        </div>
    </div>

    <div class="toolbar">
        <input type="text" id="searchInput" placeholder="상품명/의뢰자명/닉네임 검색" onkeyup="if(event.key==='Enter')applyFilter()" oninput="applyFilter()">
        <select id="sortSelect" onchange="applyFilter()" aria-label="정렬">
            <option value="updated">최근 업데이트순</option>
            <option value="start">시작일순</option>
            <option value="fee">금액순</option>
        </select>
        <select id="statusFilter" onchange="applyFilter()" aria-label="상태">
            <option value="">전체 상태</option>
            <option value="active">진행중</option>
            <option value="pending">준비중</option>
            <option value="terminated">해지</option>
            <option value="overdue">미납 있음</option>
        </select>
    </div>

    <div class="cards" id="contractCards">
        <div class="cards-empty">로딩 중...</div>
    </div>
</div>

{{-- 계약 등록/편집 모달 --}}
<div class="modal-overlay" id="contractModal" onclick="if(event.target===this) drgoModalMinimize(this, KIND==='server' ? '서버 렌탈 계약' : '렌탈 계약', KIND==='server' ? '🖥️' : '🏠')">
    <div class="modal">
        <h3 id="modalTitle">+ 계약 등록</h3>
        <input type="hidden" id="contractId">
        <input type="hidden" id="contractKind" value="equipment">

        <div class="field-group">
            <div class="field-label"><span>상품명 (렌탈 항목) *</span>@if(Auth::user()->hasPermission('rental.edit'))<button type="button" class="link-btn" onclick="openProductModal()">⚙ 상품 관리</button>@endif</div>
            <select class="field-input" id="productSelect" onchange="onProductChange()"></select>
            <input type="text" class="field-input" id="productNameInput" placeholder="상품명 직접 입력" maxlength="100" style="display:none; margin-top:6px;">
            <div class="field-hint">상품을 고르면 금액이 자동으로 들어갑니다. 목록에 없으면 '직접 입력'을 고르세요.</div>
        </div>

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
        <div class="field-group">
            <div class="field-label">
                <span>월 금액 (원) *</span>
                <label class="toggle"><input type="checkbox" id="feeManual" onchange="onFeeManualChange()"><span class="sw" aria-hidden="true"></span>금액 직접 입력</label>
            </div>
            <input type="number" class="field-input" id="monthlyFee" min="0" step="1000">
            <div class="field-hint" id="feeHint">세트 일부 제외 등 예외 금액은 '금액 직접 입력'을 켜고 수정하세요. 카드에 '수동' 표시가 붙습니다.</div>
        </div>
        <div class="field-group">
            <div class="field-label">상태</div>
            <input type="hidden" id="status" value="active">
            <div class="status-pick" role="radiogroup" aria-label="상태">
                <button type="button" data-status="pending" onclick="pickStatus('pending')">준비중</button>
                <button type="button" data-status="active" onclick="pickStatus('active')">진행중</button>
                <button type="button" data-status="terminated" onclick="pickStatus('terminated')">해지</button>
            </div>
            <div class="field-hint">준비중 = 계약 후 설치·개통 전. 캘린더에 시작 일정만 등록되고, 진행중으로 바꾸면 결제 반복이 생성됩니다.</div>
        </div>
        {{-- 서버 렌탈 전용 --}}
        <div class="server-only">
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">서버명 (선택)</div>
                    <input type="text" class="field-input" id="serverName" placeholder="예: 스트리밍 서버 A" maxlength="100">
                </div>
                <div class="field-group">
                    <div class="field-label">사양 (선택)</div>
                    <input type="text" class="field-input" id="serverSpec" placeholder="예: Ryzen 9 · 64GB · RTX 4070" maxlength="200">
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">접속 정보 (IP·주소)</div>
                <input type="text" class="field-input mono" id="serverAccess" placeholder="예: 123.45.67.89:3389" maxlength="200" autocomplete="off">
                <div class="field-hint">접속 정보는 렌탈 화면에만 보이고 캘린더 일정에는 복제되지 않습니다.</div>
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <textarea class="field-input" id="memo" rows="2"></textarea>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="btn-outline danger" id="deleteBtn" style="margin-right:auto; display:none;" onclick="deleteContract()">삭제</button>
            <button class="btn-outline" onclick="closeContractModal()">취소</button>
            <button class="btn-primary" onclick="saveContract()">저장</button>
        </div>
    </div>
</div>

{{-- 상품 관리 모달 — 렌탈 담당자가 탭별 상품을 생성·편집·삭제 --}}
<div class="modal-overlay" id="productModal" onclick="if(event.target===this) closeProductModal()">
    <div class="modal">
        <h3><span>⚙ 상품 관리</span><span class="status-badge status-pending" id="productModalKind">장비 렌탈</span></h3>
        <div class="prod-list" id="productList"><div class="field-hint">로딩 중...</div></div>
        <div class="prod-form">
            <input type="hidden" id="prodEditId">
            <input type="text" class="field-input" id="prodName" placeholder="상품명 (예: 방송 풀세트)" maxlength="100">
            <input type="number" class="field-input" id="prodFee" placeholder="월 금액" min="0" step="1000">
            <input type="text" class="field-input full" id="prodMemo" placeholder="구성품 등 짧은 설명 (선택)" maxlength="200">
            <div class="prod-form-actions">
                <button type="button" class="btn-outline" id="prodCancelBtn" style="display:none;" onclick="resetProductForm()">취소</button>
                <button type="button" class="btn-primary" id="prodSaveBtn" onclick="saveProduct()">+ 상품 추가</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const canEdit = @json(Auth::user()->hasPermission('rental.edit'));
const KIND_LABEL = { equipment:'장비 렌탈', server:'서버 렌탈' };
const STATUS_LABEL = { pending:'준비중', active:'진행중', terminated:'해지' };
let KIND = @json($kind); // 현재 탭 (장비/서버)
let allContracts = []; // 전체 종류 — 탭 전환은 요청 없이 화면에서 거른다
let products = { equipment: [], server: [] }; // 탭별 상품 목록 (활성)
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const won = n => (Number(n) || 0).toLocaleString() + '원';

async function loadContracts() {
    // 종류별로 따로 받아 합친다 — 목록 상한(종류당 500건)이 한쪽에 쏠려 다른 탭이 비어 보이지 않게
    const lists = await Promise.all(Object.keys(KIND_LABEL).map(k =>
        fetch('/api/rental-contracts?kind=' + k, {headers:{'Accept':'application/json'}}).then(r => r.ok ? r.json() : [])
    ));
    allContracts = lists.flat();
    renderContracts();
}
async function loadProducts() {
    const lists = await Promise.all(Object.keys(KIND_LABEL).map(k =>
        fetch('/api/rental-products?kind=' + k, {headers:{'Accept':'application/json'}}).then(r => r.ok ? r.json() : [])
    ));
    Object.keys(KIND_LABEL).forEach((k, i) => { products[k] = lists[i]; });
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

const PAY_STATE_LABEL = { paid:'결제됨', due:'오늘 결제일', overdue:'미납', upcoming:'예정' };
const PAY_VISIBLE = 8; // 카드당 기본 노출 결제일 수 — 넘치면 '+N' 칩으로 펼침
const expandedPay = new Set();
function payChipLabel(dateStr) {
    const [y, m] = dateStr.split('-');
    return (String(new Date().getFullYear()) === y ? '' : y.slice(2) + '.') + parseInt(m, 10) + '월';
}
function payStripHtml(c) {
    if (c.status === 'pending') return '<div class="card-pay"><span class="pay-label">결제</span><span class="pay-chip">준비중 — 결제 일정 없음</span></div>';
    const pays = c.payments || [];
    if (!pays.length) return '';
    const show = expandedPay.has(c.id) ? pays : pays.slice(-PAY_VISIBLE);
    const hidden = pays.length - show.length;
    const more = hidden > 0 ? `<button type="button" class="pay-chip more" onclick="expandedPay.add(${c.id}); renderContracts();" title="이전 결제일 ${hidden}개 펼치기">+${hidden}</button>` : '';
    const chips = show.map(p => {
        const title = `${p.due_date} · ${PAY_STATE_LABEL[p.state] || p.state}` + (p.state === 'paid' && p.paid_at ? ` · ${p.paid_by || ''} ${p.paid_at}`.replace(/\s+/g, ' ') : '') + (canEdit ? (p.state === 'paid' ? ' — 클릭: 결제 해제' : ' — 클릭: 결제됨으로 체크') : '');
        const mark = p.state === 'paid' ? '✓ ' : (p.state === 'overdue' ? '! ' : '');
        const inner = `${mark}${payChipLabel(p.due_date)}`;
        return canEdit
            ? `<button type="button" class="pay-chip ${p.state}" title="${esc(title)}" aria-pressed="${p.state === 'paid'}" onclick="togglePay(${c.id}, '${p.due_date}', ${p.state !== 'paid'})">${inner}</button>`
            : `<span class="pay-chip ${p.state}" title="${esc(title)}">${inner}</span>`;
    }).join('');
    return `<div class="card-pay"><span class="pay-label">결제</span>${more}${chips}</div>`;
}
async function togglePay(id, dueDate, paid) {
    const res = await fetch(`/api/rental-contracts/${id}/payments`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify({ due_date: dueDate, paid }),
    });
    if (!res.ok) { const err = await res.json().catch(() => ({})); return alert('저장 실패: ' + (err.message || Object.values(err.errors || {}).flat().join(', '))); }
    const updated = await res.json();
    const i = allContracts.findIndex(c => c.id === updated.id);
    if (i >= 0) allContracts[i] = updated; else allContracts.push(updated);
    renderContracts();
}

/** 최근 업데이트 기준일 — 시작일·종료일 중 늦은 날짜 */
function updatedKey(c) { return [c.start_date, c.end_date].filter(Boolean).sort().pop() || ''; }

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
    document.getElementById('statRevenue').textContent = won(active.reduce((s, c) => s + (Number(c.monthly_fee) || 0), 0));
    document.getElementById('statTotal').textContent = ofKind.length.toLocaleString() + '건';
    document.getElementById('statOverdue').textContent = ofKind.reduce((s, c) => s + (c.payment_summary?.overdue || 0), 0).toLocaleString() + '건';

    const search = document.getElementById('searchInput').value.trim().toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const sort = document.getElementById('sortSelect').value;
    const filtered = ofKind.filter(c => {
        if (status === 'overdue') { if (!(c.payment_summary?.overdue > 0)) return false; }
        else if (status && c.status !== status) return false;
        if (search) {
            const hay = [c.product_name, c.client_name, c.client_nickname, c.server_name, c.server_spec, c.server_access, c.memo].map(v => (v || '').toLowerCase()).join(' ');
            if (!hay.includes(search)) return false;
        }
        return true;
    });
    // 기본 정렬: 최근 업데이트순(시작일·종료일 중 늦은 날짜 최신순) → 같은 날짜는 최근 등록(id) 우선
    filtered.sort((a, b) => {
        if (sort === 'start') return (b.start_date || '').localeCompare(a.start_date || '') || b.id - a.id;
        if (sort === 'fee') return (Number(b.monthly_fee) || 0) - (Number(a.monthly_fee) || 0) || b.id - a.id;
        return updatedKey(b).localeCompare(updatedKey(a)) || b.id - a.id;
    });

    const wrap = document.getElementById('contractCards');
    if (!filtered.length) {
        wrap.innerHTML = `<div class="cards-empty">${KIND === 'server' ? '서버 렌탈 계약이 없습니다.' : '계약이 없습니다.'}</div>`;
        return;
    }
    const personIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
    wrap.innerHTML = filtered.map(c => {
        const clientName = esc((c.client_name || c.client_nickname || '-') + (c.client_name && c.client_nickname ? ` (${c.client_nickname})` : ''));
        const calBadge = c.calendar_synced
            ? `<a href="/calendar" title="캘린더에 연동됨 — 클릭 시 캘린더로 이동" onclick="event.preventDefault();if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('calendar','/calendar');}else{location.href='/calendar';}">📅</a>`
            : '';
        const product = c.product_name ? `<div class="card-product" title="${esc(c.product_name)}">${esc(c.product_name)}</div>` : `<div class="card-product none">상품 미지정</div>`;
        const endLabel = c.end_date ? esc(c.end_date) : (c.status === 'pending' ? '<span style="color:var(--text-muted);">미정</span>' : '<span style="color:var(--text-muted);">진행중</span>');
        const manual = c.fee_manual ? '<span class="fee-manual-tag" title="상품 기본 금액 대신 직접 입력한 금액">수동</span>' : '';
        const access = (c.kind === 'server')
            ? `<div class="card-access"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="8" rx="2"></rect><rect x="2" y="14" width="20" height="8" rx="2"></rect></svg><span class="mono">${c.server_access ? esc(c.server_access) : '접속 정보 미입력'}</span>${c.server_spec ? `<span title="${esc(c.server_spec)}">· ${esc(c.server_spec)}</span>` : ''}</div>`
            : '';
        const memo = c.memo ? esc(c.memo) : '<span style="opacity:.6">메모 없음</span>';
        const updated = updatedKey(c);
        const ps = c.payment_summary || {};
        const payTag = ps.overdue > 0 ? `<span class="overdue-tag" title="결제일이 지났는데 체크되지 않은 결제 ${ps.overdue}건">미납 ${ps.overdue}</span>` : (ps.due > 0 ? '<span class="due-tag">오늘 결제일</span>' : '');
        return `<article class="card">
            <div class="card-main">
                ${product}
                <div class="card-client">${personIcon}<span title="${clientName}">${clientName}</span>${calBadge}</div>
                ${access}
            </div>
            <div class="card-meta">
                <div><div class="k">시작일</div><div class="v">${esc(c.start_date)}</div></div>
                <div><div class="k">종료일</div><div class="v">${endLabel}</div></div>
                <div><div class="k">월 금액</div><div class="v fee">${won(c.monthly_fee)}${manual}</div></div>
            </div>
            <div class="card-body">
                ${payStripHtml(c)}
                <div class="card-memo" title="${esc(c.memo || '')}">${memo}</div>
            </div>
            <div class="card-side">
                <div class="card-side-row">${payTag}<span class="status-badge status-${esc(c.status)}">${STATUS_LABEL[c.status] || esc(c.status)}</span>${canEdit ? `<button class="btn-outline" onclick='editContract(${c.id})'>편집</button>` : ''}</div>
                <div class="card-updated" title="시작일·종료일 중 늦은 날짜 — 최근 업데이트순 정렬 기준">업데이트 <b>${esc(updated)}</b></div>
            </div>
        </article>`;
    }).join('');
}

function applyFilter() { renderContracts(); }

// ── 계약 모달 ──
function fillProductSelect(kind, selectedId, productName) {
    const sel = document.getElementById('productSelect');
    const list = products[kind] || [];
    const opts = ['<option value="">상품 선택</option>']
        .concat(list.map(p => `<option value="${p.id}" data-fee="${p.default_fee}">${esc(p.name)} — ${won(p.default_fee)}</option>`));
    // 편집 중인 계약의 상품이 비활성/삭제되었으면 스냅샷 이름으로 표시
    if (selectedId && !list.some(p => p.id === selectedId)) opts.push(`<option value="${selectedId}" data-fee="">${esc(productName || '(삭제된 상품)')}</option>`);
    opts.push('<option value="__custom__">직접 입력…</option>');
    sel.innerHTML = opts.join('');
    if (selectedId) sel.value = String(selectedId);
    else if (productName) sel.value = '__custom__';
    else sel.value = '';
    syncProductNameInput();
}
function syncProductNameInput() {
    const sel = document.getElementById('productSelect');
    document.getElementById('productNameInput').style.display = sel.value === '__custom__' ? 'block' : 'none';
}
function onProductChange() {
    const sel = document.getElementById('productSelect');
    syncProductNameInput();
    const manual = document.getElementById('feeManual');
    if (sel.value === '__custom__') { manual.checked = true; onFeeManualChange(); return; }
    const fee = sel.selectedOptions[0]?.dataset.fee;
    if (fee !== undefined && fee !== '' && !manual.checked) document.getElementById('monthlyFee').value = fee;
    onFeeManualChange();
}
function onFeeManualChange() {
    const manual = document.getElementById('feeManual').checked;
    const sel = document.getElementById('productSelect');
    const feeEl = document.getElementById('monthlyFee');
    const hasProduct = sel.value && sel.value !== '__custom__';
    feeEl.disabled = hasProduct && !manual; // 상품을 골랐고 자동이면 잠금
    if (hasProduct && !manual) { const fee = sel.selectedOptions[0]?.dataset.fee; if (fee !== undefined && fee !== '') feeEl.value = fee; }
    document.getElementById('feeHint').textContent = manual
        ? '직접 입력한 금액으로 저장됩니다 (카드에 \'수동\' 표시).'
        : '세트 일부 제외 등 예외 금액은 \'금액 직접 입력\'을 켜고 수정하세요. 카드에 \'수동\' 표시가 붙습니다.';
}
function pickStatus(s) {
    document.getElementById('status').value = s;
    document.querySelectorAll('.status-pick button').forEach(b => { const on = b.dataset.status === s; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
}

async function openContractModal(data) {
    const kind = data?.kind || KIND;
    if (!products[kind].length) await loadProducts().catch(() => {});
    document.getElementById('contractModal').classList.add('open');
    document.getElementById('contractModal').classList.toggle('kind-server', kind === 'server');
    document.getElementById('modalTitle').textContent = (data ? '계약 편집' : '+ 계약 등록') + ' · ' + KIND_LABEL[kind];
    document.getElementById('contractId').value = data?.id || '';
    document.getElementById('contractKind').value = kind;
    document.getElementById('clientId').value = data?.client_id || '';
    document.getElementById('clientSearch').value = data ? (data.client_nickname || data.client_name || '') : '';
    document.getElementById('selectedClient').textContent = data ? `✓ ${data.client_name || data.client_nickname || ''}${data.client_name && data.client_nickname ? ' ('+data.client_nickname+')' : ''}` : '';
    document.getElementById('selectedClient').style.display = data ? 'block' : 'none';
    document.getElementById('startDate').value = data?.start_date || new Date().toISOString().slice(0,10);
    document.getElementById('endDate').value = data?.end_date || '';
    document.getElementById('feeManual').checked = !!data?.fee_manual;
    fillProductSelect(kind, data?.product_id || null, data?.product_name || '');
    document.getElementById('productNameInput').value = (!data?.product_id && data?.product_name) ? data.product_name : '';
    document.getElementById('monthlyFee').value = data?.monthly_fee ?? '';
    onFeeManualChange();
    pickStatus(data?.status || 'active');
    document.getElementById('serverName').value = data?.server_name || '';
    document.getElementById('serverSpec').value = data?.server_spec || '';
    document.getElementById('serverAccess').value = data?.server_access || '';
    document.getElementById('memo').value = data?.memo || '';
    document.getElementById('deleteBtn').style.display = data ? 'inline-flex' : 'none';
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
let lastClientResults = []; // 검색 결과 — 인라인 onclick 에 JSON 을 넣지 않고 인덱스로 고른다
async function searchClients(q) {
    clearTimeout(searchTimer);
    if (q.length < 1) { document.getElementById('clientResults').classList.remove('open'); return; }
    searchTimer = setTimeout(async () => {
        const res = await fetch('/api/rental-contracts/search-clients?q=' + encodeURIComponent(q));
        const list = await res.json();
        lastClientResults = Array.isArray(list) ? list : [];
        const el = document.getElementById('clientResults');
        if (!lastClientResults.length) { el.innerHTML = '<div style="padding:12px; color:var(--text-muted); font-size:12px;">검색 결과 없음</div>'; el.classList.add('open'); return; }
        el.innerHTML = lastClientResults.map((c, i) => `<div class="client-search-result" onclick="selectClient(${i})">${esc(c.name || c.nickname || '')}${c.name && c.nickname ? ' ('+esc(c.nickname)+')' : ''} ${c.phone ? '· '+esc(c.phone) : ''}</div>`).join('');
        el.classList.add('open');
    }, 200);
}

function selectClient(i) {
    const c = lastClientResults[i];
    if (!c) return;
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
    const sel = document.getElementById('productSelect').value;
    const isCustom = sel === '__custom__';
    const productId = (!isCustom && sel) ? parseInt(sel) : null;
    const productName = isCustom ? document.getElementById('productNameInput').value.trim() : null;
    if (!productId && !productName) return alert('상품을 고르거나 상품명을 직접 입력하세요.');
    const manual = document.getElementById('feeManual').checked;
    const feeRaw = document.getElementById('monthlyFee').value;
    if ((manual || !productId) && feeRaw === '') return alert('월 금액을 입력하세요.');

    const body = {
        client_id: parseInt(clientId),
        kind,
        product_id: productId,
        product_name: productId ? null : productName,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value || null,
        monthly_fee: feeRaw === '' ? null : parseInt(feeRaw),
        fee_manual: manual || !productId,
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
        await loadContracts();
    } else {
        const err = await res.json().catch(() => ({}));
        alert('저장 실패: ' + (err.message || Object.values(err.errors || {}).flat().join(', ')));
    }
}

async function deleteContract() {
    if (!confirm('이 계약을 삭제하시겠습니까?')) return;
    const id = document.getElementById('contractId').value;
    const res = await fetch(`/api/rental-contracts/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'} });
    if (res.ok) { closeContractModal(); await loadContracts(); }
}

// ── 상품 관리 모달 (현재 탭 종류의 상품) ──
let productModalKind = 'equipment';
let allProductsForKind = [];
async function openProductModal() {
    productModalKind = document.getElementById('contractModal').classList.contains('open') ? (document.getElementById('contractKind').value || KIND) : KIND;
    document.getElementById('productModalKind').textContent = KIND_LABEL[productModalKind];
    document.getElementById('productModal').classList.add('open');
    resetProductForm();
    await refreshProductList();
}
function closeProductModal() {
    document.getElementById('productModal').classList.remove('open');
    // 계약 모달이 열려 있으면 상품 목록을 새로 채운다 (방금 추가한 상품 선택 가능)
    loadProducts().then(() => {
        if (document.getElementById('contractModal').classList.contains('open')) {
            const sel = document.getElementById('productSelect');
            const cur = sel.value;
            fillProductSelect(document.getElementById('contractKind').value, (cur && cur !== '__custom__') ? parseInt(cur) : null, document.getElementById('productNameInput').value);
            if (cur === '__custom__') { sel.value = '__custom__'; syncProductNameInput(); }
        }
    }).catch(() => {});
}
async function refreshProductList() {
    const res = await fetch('/api/rental-products?kind=' + productModalKind + '&include_inactive=1', {headers:{'Accept':'application/json'}});
    allProductsForKind = res.ok ? await res.json() : [];
    const el = document.getElementById('productList');
    if (!allProductsForKind.length) { el.innerHTML = '<div class="field-hint">등록된 상품이 없습니다. 아래에서 추가하세요.</div>'; return; }
    el.innerHTML = allProductsForKind.map(p => `<div class="prod-row ${p.is_active ? '' : 'inactive'}">
        <div><div class="pn" title="${esc(p.name)}">${esc(p.name)}${p.is_active ? '' : ' <span class="field-hint">(숨김)</span>'}</div>${p.memo ? `<div class="pm" title="${esc(p.memo)}">${esc(p.memo)}</div>` : ''}</div>
        <div class="pf">${won(p.default_fee)}</div>
        <div class="pa">
            <button type="button" class="btn-outline" onclick="editProduct(${p.id})">편집</button>
            <button type="button" class="btn-outline" onclick="toggleProduct(${p.id}, ${p.is_active ? 'false' : 'true'})" title="${p.is_active ? '목록에서 숨김 (기존 계약은 유지)' : '다시 표시'}">${p.is_active ? '숨김' : '표시'}</button>
            <button type="button" class="btn-outline danger" onclick="deleteProduct(${p.id}, ${p.contracts_count || 0})">삭제</button>
        </div>
    </div>`).join('');
}
function resetProductForm() {
    document.getElementById('prodEditId').value = '';
    document.getElementById('prodName').value = '';
    document.getElementById('prodFee').value = '';
    document.getElementById('prodMemo').value = '';
    document.getElementById('prodSaveBtn').textContent = '+ 상품 추가';
    document.getElementById('prodCancelBtn').style.display = 'none';
}
function editProduct(id) {
    const p = allProductsForKind.find(x => x.id === id);
    if (!p) return;
    document.getElementById('prodEditId').value = p.id;
    document.getElementById('prodName').value = p.name;
    document.getElementById('prodFee').value = p.default_fee;
    document.getElementById('prodMemo').value = p.memo || '';
    document.getElementById('prodSaveBtn').textContent = '수정 저장';
    document.getElementById('prodCancelBtn').style.display = 'inline-flex';
    document.getElementById('prodName').focus();
}
async function saveProduct() {
    const id = document.getElementById('prodEditId').value;
    const name = document.getElementById('prodName').value.trim();
    const fee = document.getElementById('prodFee').value;
    if (!name) return alert('상품명을 입력하세요.');
    if (fee === '') return alert('월 금액을 입력하세요.');
    const body = { kind: productModalKind, name, default_fee: parseInt(fee), memo: document.getElementById('prodMemo').value.trim() || null };
    const res = await fetch(id ? `/api/rental-products/${id}` : '/api/rental-products', {
        method: id ? 'PATCH' : 'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: JSON.stringify(body),
    });
    if (!res.ok) { const err = await res.json().catch(() => ({})); return alert('저장 실패: ' + (err.message || Object.values(err.errors || {}).flat().join(', '))); }
    resetProductForm();
    await refreshProductList();
}
async function toggleProduct(id, active) {
    const res = await fetch(`/api/rental-products/${id}`, { method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: JSON.stringify({ is_active: active }) });
    if (res.ok) await refreshProductList();
}
async function deleteProduct(id, count) {
    const msg = count > 0 ? `이 상품을 쓰는 계약이 ${count}건 있습니다. 삭제해도 기존 계약의 상품명·금액은 유지됩니다. 삭제할까요?` : '이 상품을 삭제할까요?';
    if (!confirm(msg)) return;
    const res = await fetch(`/api/rental-products/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'} });
    if (res.ok) await refreshProductList();
}

loadProducts().catch(() => {});
loadContracts();
</script>
@endpush
