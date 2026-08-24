<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
    {{-- 본 앱과 동일하게 라이트 모드 고정 (구버전 테마 키 정리) --}}
    <script>(function(){try{localStorage.removeItem('drgo_theme');}catch(e){}document.documentElement.setAttribute('data-theme','light');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>견적서 #{{ $estimate->id }} - 닥터고블린 오피스</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        :root, [data-theme="dark"] { --bg:#111; --surface:#1c1c1c; --surface2:#272727; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --red:#d48888; --green:#88d488; --blue:#8ab4c8; }
        [data-theme="light"] { --bg:#f4f5f7; --surface:#fff; --surface2:#eceef2; --border:#b8bcc8; --text:#1a1e28; --text-muted:#5a6070; --accent:#3b5ea0; --red:#c03838; --green:#248a38; --blue:#2e6a9a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; display:flex; height:100vh; overflow:hidden; }
        input, button, textarea, select { font-family:inherit; }

        /* 좌측 — 제품 리스트 */
        .panel-left { width:420px; border-right:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0; }
        .panel-left-header { padding:14px 16px; border-bottom:1px solid var(--border); }
        .panel-left-header h3 { font-size:14px; font-weight:700; margin-bottom:10px; }
        .cat-tabs { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px; }
        .cat-tab { padding:4px 10px; font-size:11px; border:1px solid var(--border); border-radius:6px; background:none; color:var(--text-muted); cursor:pointer; }
        .cat-tab.active { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
        .search-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; }
        .search-input:focus { border-color:var(--accent); }
        .product-list { flex:1; overflow-y:auto; padding:8px; }
        .product-item { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border-radius:8px; cursor:pointer; transition:background 0.1s; font-size:13px; }
        .product-item:hover { background:var(--surface2); }
        .product-item .pi-name { flex:1; }
        .product-item .pi-cat { font-size:10px; color:var(--text-muted); margin-top:2px; }
        .product-item .pi-price { font-size:12px; color:var(--accent); font-weight:600; white-space:nowrap; margin-left:10px; }
        .product-item .pi-stock { font-size:10px; margin-left:8px; }
        .pi-stock.low { color:var(--red); }
        .pi-stock.ok { color:var(--text-muted); }

        /* 우측 — 견적서 */
        .panel-right { flex:1; display:flex; flex-direction:column; overflow:hidden; }

        /* 최우측 — 프리셋 패널 (클릭해서 품목 담기). 마크업 위치와 무관하게 order로 최우측 고정 */
        .panel-left { order:0; }
        .panel-right { order:1; }
        .panel-presets { order:2; width:210px; border-left:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0; background:var(--surface); }
        .panel-presets-header { padding:14px 14px 10px; border-bottom:1px solid var(--border); }
        .panel-presets-header h3 { font-size:14px; font-weight:700; }
        .preset-list { flex:1; overflow-y:auto; padding:8px; }
        .preset-item { padding:10px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:6px; cursor:pointer; transition:border-color 0.1s, background 0.1s; }
        .preset-item:hover { border-color:var(--accent); background:var(--surface2); }
        .preset-name { font-size:12.5px; font-weight:600; line-height:1.4; word-break:keep-all; }
        .preset-total { font-size:12px; color:var(--accent); font-weight:600; margin-top:3px; }
        .panel-right-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .panel-right-header h2 { font-size:16px; font-weight:700; }
        .est-status { font-size:11px; padding:3px 10px; border-radius:4px; font-weight:600; }
        .est-body { flex:1; overflow-y:auto; padding:20px; }

        /* 주문정보 */
        .client-section { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:16px; }
        .client-section h4 { font-size:12px; color:var(--accent); font-weight:600; margin-bottom:12px; letter-spacing:0.05em; }
        .client-row { display:flex; gap:10px; }
        .client-row .field { flex:1; }
        .field label { font-size:11px; color:var(--text-muted); display:block; margin-bottom:4px; }
        .field input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:13px; outline:none; }
        .field input:focus { border-color:var(--accent); }
        .client-search-wrap { position:relative; }
        .client-results { position:absolute; top:100%; left:0; right:0; background:var(--surface); border:1px solid var(--border); border-radius:8px; max-height:150px; overflow-y:auto; z-index:10; display:none; }
        .client-results.show { display:block; }
        .client-result-item { padding:8px 12px; font-size:12px; cursor:pointer; }
        .client-result-item:hover { background:var(--surface2); }

        /* 장바구니 테이블 */
        .cart-section { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:16px; }
        .cart-section h4 { font-size:12px; color:var(--accent); font-weight:600; margin-bottom:12px; letter-spacing:0.05em; }
        .cart-table { width:100%; border-collapse:collapse; }
        .cart-table th { font-size:10px; color:var(--text-muted); font-weight:600; text-align:left; padding:8px 6px; border-bottom:1px solid var(--border); }
        .cart-table td { font-size:12px; padding:8px 6px; border-bottom:1px solid var(--border); }
        .cart-table tr:last-child td { border-bottom:none; }
        .cart-cat-header { background:var(--surface2); }
        .cart-cat-header td { font-size:11px; font-weight:600; color:var(--accent); padding:6px; }
        .cart-subtotal td { font-size:12px; font-weight:700; text-align:right; padding:6px; border-top:1px solid var(--border); }
        .qty-ctrl { display:flex; align-items:center; gap:2px; }
        .qty-ctrl button { width:22px; height:22px; border:1px solid var(--border); background:var(--surface2); color:var(--text); border-radius:4px; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; }
        .qty-ctrl input { width:36px; text-align:center; background:var(--surface2); border:1px solid var(--border); border-radius:4px; color:var(--text); font-size:12px; padding:2px; outline:none; }
        .btn-remove { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px; }
        .btn-remove:hover { color:var(--red); }
        .text-right { text-align:right; }

        /* 서비스 항목 */
        .svc-row { display:flex; gap:6px; margin-bottom:6px; align-items:center; }
        .svc-row input { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 8px; color:var(--text); font-size:12px; outline:none; }
        .svc-row input:focus { border-color:var(--accent); }
        .btn-add-svc { background:none; border:1px dashed var(--border); color:var(--text-muted); font-size:11px; padding:5px 10px; border-radius:6px; cursor:pointer; width:100%; }
        .btn-add-svc:hover { border-color:var(--accent); color:var(--accent); }

        /* 합계 */
        .total-section { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:16px; }
        .total-row { display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px; }
        .total-row.grand { font-size:18px; font-weight:700; color:var(--accent); margin-top:8px; padding-top:8px; border-top:1px solid var(--border); }
        .total-items { font-size:12px; color:var(--text-muted); }

        /* 하단 액션 */
        .panel-right-footer { padding:12px 20px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
        /* 버튼 위계: 저장(주요·채움) > 견적서 출력(보조·외곽선) > 삭제(위험·외곽선) > 로그(중립) */
        .btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:background 0.12s, border-color 0.12s, color 0.12s, filter 0.12s; }
        .btn-save { background:var(--accent); border-color:var(--accent); color:#fff; font-weight:700; }
        .btn-save:hover { filter:brightness(1.12); }
        .btn-print { background:var(--surface); border-color:var(--accent); color:var(--accent); }
        .btn-print:hover { background:rgba(59,94,160,0.09); }
        .btn-delete { background:none; border-color:var(--border); color:var(--red); }
        .btn-delete:hover { border-color:var(--red); background:rgba(192,56,56,0.07); }
        .btn-ghost { background:none; border-color:var(--border); color:var(--text-muted); }
        .btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
        .save-indicator { font-size:11px; color:var(--text-muted); align-self:center; }
        [data-theme="light"] .cat-tab.active { color:#fff; }
    </style>
</head>
<body>
<div class="panel-left">
    <div class="panel-left-header">
        <h3>제품 리스트</h3>
        <div class="cat-tabs" id="catTabs">
            <button class="cat-tab active" onclick="filterCat(null)">전체</button>
        </div>
        <input class="search-input" id="prodSearch" placeholder="제품명/SKU 검색" oninput="filterProducts()">
    </div>
    <div class="product-list" id="productList"></div>
</div>

<!-- 프리셋 패널 (우측) — 클릭하면 품목이 견적서에 담김, 여러 개 눌러 조립 -->
<div class="panel-presets">
    <div class="panel-presets-header">
        <h3>프리셋</h3>
        <label style="display:flex; align-items:center; gap:5px; font-size:11.5px; color:var(--text-muted); cursor:pointer; margin-top:8px;">
            <input type="checkbox" id="presetReplaceMode" style="width:13px; height:13px;"> 불러올 때 기존 품목 비우기
        </label>
    </div>
    <div class="preset-list" id="presetPanelList"><div style="padding:16px; text-align:center; color:var(--text-muted); font-size:12px;">로딩 중...</div></div>
</div>

<!-- 옵션 선택 팝업 (옵션 그룹 상품) -->
<div id="optionPickerOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:300; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) closeOptionPicker()">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:min(380px, 100%); max-height:70vh; display:flex; flex-direction:column;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border);">
            <div style="font-size:14px; font-weight:700;" id="optPickerTitle">옵션 선택</div>
            <button onclick="closeOptionPicker()" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer;">×</button>
        </div>
        <div id="optPickerList" style="overflow-y:auto; padding:8px;"></div>
    </div>
</div>

<div class="panel-right">
    <div class="panel-right-header">
        <h2>견적서 #{{ $estimate->id }}</h2>
        <span style="display:flex; gap:6px; align-items:center; margin-left:auto; margin-right:8px;">
            @if($estimate->status === 'paid')
                <span style="font-size:12px; padding:5px 12px; border-radius:6px; background:rgba(36,138,56,0.12); color:var(--green); font-weight:700;">💳 결제 완료{{ $estimate->payapp_paid_at ? ' · '.$estimate->payapp_paid_at->format('m/d H:i') : '' }}</span>
            @elseif($estimate->status === 'cancelled')
                <span style="font-size:12px; padding:5px 12px; border-radius:6px; background:rgba(192,56,56,0.1); color:var(--red); font-weight:700;">⛔ 결제 취소됨 — 재결제는 발행 완료로 변경</span>
            @elseif($estimate->payapp_payurl)
                <span style="font-size:12px; padding:5px 12px; border-radius:6px; background:rgba(59,94,160,0.1); color:var(--accent); font-weight:700;">💳 결제 대기 중</span>
                <button class="btn btn-ghost" style="padding:5px 12px; font-size:12px;" onclick="payappCancel()">결제요청 취소</button>
            @else
                <button class="btn btn-ghost" style="padding:5px 12px; font-size:12px;" onclick="payappRequest()">💳 결제요청 생성</button>
            @endif
            <button class="btn btn-ghost" style="padding:5px 12px; font-size:12px;" onclick="copyPublicLink()">🔗 의뢰자 링크 복사</button>
        </span>
        <select id="estStatus" style="background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:5px 10px; color:var(--text); font-size:12px; outline:none; cursor:pointer;">
            <option value="created" {{ $estimate->status === 'created' ? 'selected' : '' }}>생성</option>
            <option value="editing" {{ $estimate->status === 'editing' ? 'selected' : '' }}>수정 중</option>
            <option value="completed" {{ $estimate->status === 'completed' ? 'selected' : '' }}>작성 완료</option>
            <option value="issued" {{ $estimate->status === 'issued' ? 'selected' : '' }}>발행 완료</option>
            <option value="paid" {{ $estimate->status === 'paid' ? 'selected' : '' }}>결제 완료</option>
            <option value="cancelled" {{ $estimate->status === 'cancelled' ? 'selected' : '' }}>결제 취소</option>
            <option value="hold" {{ $estimate->status === 'hold' ? 'selected' : '' }}>보류 중</option>
        </select>
    </div>
    <div class="est-body">
        <!-- 주문정보 -->
        <div class="client-section">
            <h4>주문 정보</h4>
            <div class="client-row">
                <div class="field client-search-wrap">
                    <label>닉네임</label>
                    <input id="cNickname" value="{{ $estimate->client_nickname }}" oninput="searchClients(this.value)" autocomplete="off">
                    <div class="client-results" id="clientResults"></div>
                </div>
                <div class="field">
                    <label>이름</label>
                    <input id="cName" value="{{ $estimate->client_name }}">
                </div>
                <div class="field">
                    <label>연락처</label>
                    <input id="cPhone" value="{{ $estimate->client_phone }}">
                </div>
                <div class="field">
                    <label>프로젝트 연동 (선택)</label>
                    <select id="cProject" disabled style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:13px; outline:none; cursor:pointer;">
                        <option value="">의뢰자 선택 후 지정 가능</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 장바구니 -->
        <div class="cart-section">
            <h4 style="display:flex; align-items:center; gap:8px;">제품 항목
                <span style="margin-left:auto;">
                    <button class="btn-add-svc" style="width:auto; padding:5px 12px;" onclick="saveAsPreset()">현재 품목을 프리셋으로 저장</button>
                </span>
            </h4>
            <table class="cart-table">
                <thead><tr><th>번호</th><th>분류</th><th>제품명</th><th>소요시간</th><th class="text-right">판매가</th><th>수량</th><th class="text-right">합계</th><th></th></tr></thead>
                <tbody id="cartBody"><tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">좌측에서 제품을 선택하세요</td></tr></tbody>
            </table>
        </div>

        <!-- 수기 제품 추가 — 일회성 품목 (제품 관리에 등록하지 않고 견적서 데이터로만 저장) -->
        <div class="cart-section">
            <h4>수기 제품 추가 <span style="color:var(--text-muted); font-weight:400; letter-spacing:0;">— 자주 취급하지 않는 제품·임의 견적용. 제품 관리에 등록되지 않고 작성 시점 가격으로 견적서에 저장됩니다</span></h4>
            <div class="svc-row">
                <input id="miCat" placeholder="카테고리" style="flex:1;">
                <input id="miName" placeholder="제품명 *" style="flex:2;" onkeydown="if(event.key==='Enter')addManualItem()">
                <input id="miPrice" type="number" min="0" placeholder="판매가" style="flex:1;" onkeydown="if(event.key==='Enter')addManualItem()">
                <input id="miQty" type="number" min="1" value="1" title="수량" style="width:56px;">
                <button class="btn-add-svc" style="width:auto; padding:6px 14px;" onclick="addManualItem()">+ 추가</button>
            </div>
        </div>

        <!-- 서비스 항목 (구버전 견적서 호환 — 저장된 항목이 있을 때만 표시) -->
        <div class="cart-section" id="svcSection" style="display:none;">
            <h4>서비스 항목 <span style="color:var(--text-muted); font-weight:400;">(구버전 — 새 항목은 위의 수기 제품 추가를 사용하세요)</span></h4>
            <div id="svcList"></div>
        </div>

        <!-- 합계 -->
        <div class="total-section">
            <div class="total-row"><span>제품 소계</span><span id="productTotal">0원</span></div>
            <div class="total-row" id="svcTotalRow" style="display:none;"><span>서비스 소계</span><span id="serviceTotal">0원</span></div>
            <div class="total-row grand"><span>총 견적 금액</span><span id="grandTotal">0원</span></div>
            <div class="total-items">총 항목 수: <span id="totalItems">0</span>개 (부가세 포함)</div>
        </div>

        <!-- 메모 -->
        <div class="cart-section">
            <h4>메모</h4>
            <textarea id="estMemo" style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:8px 10px; color:var(--text); font-size:13px; outline:none; resize:vertical; min-height:60px;">{{ $estimate->memo }}</textarea>
        </div>
    </div>

    <div class="panel-right-footer">
        <span class="save-indicator" id="saveIndicator"></span>
        <button class="btn btn-ghost" onclick="openActivityLog('Estimate',{{ $estimate->id }},'견적서 #{{ $estimate->id }} 수정 로그')">📋 로그</button>
        <button class="btn btn-delete" onclick="deleteEstimate()">삭제</button>
        <button class="btn btn-print" onclick="printEstimate()">🖨 견적서 출력</button>
        <button class="btn btn-save" onclick="saveEstimate()">저장</button>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const estId = {{ $estimate->id }};
const PUBLIC_URL = @json($estimate->publicUrl());

// === 의뢰자 링크 / 페이앱 결제 ===
function copyPublicLink() {
    navigator.clipboard.writeText(PUBLIC_URL)
        .then(() => alert('의뢰자용 견적서 링크가 복사되었습니다.\n카톡/문자로 전달하세요.\n\n' + PUBLIC_URL))
        .catch(() => prompt('아래 링크를 복사하세요:', PUBLIC_URL));
}

async function payappRequest() {
    if (!confirm('페이앱 결제요청을 생성할까요?\n의뢰자 링크 하단에 결제 버튼이 나타납니다.\n(주문 정보의 연락처와 총 견적 금액 기준)')) return;
    const res = await fetch(`/api/estimates/${estId}/payapp-request`, { method:'POST', headers:H });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) { alert(d.message || `결제요청 실패 (HTTP ${res.status})`); return; }
    alert('결제요청이 생성되었습니다.\n의뢰자 링크를 복사해 전달하세요.');
    location.reload();
}

async function payappCancel() {
    if (!confirm('결제요청을 취소할까요?\n의뢰자 링크의 결제 버튼이 사라집니다.')) return;
    const res = await fetch(`/api/estimates/${estId}/payapp-cancel`, { method:'POST', headers:H });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) { alert(d.message || '취소 실패'); return; }
    location.reload();
}
let clientId = {{ $estimate->client_id ?? 'null' }};
let allProds = [], catData = [], cartItems = @json($estimate->product_items ?? []), svcItems = @json($estimate->service_items ?? []);

function fmt(n) { return Number(n).toLocaleString(); }

// === 제품 로드 ===
async function loadProducts() {
    const [prodRes, catRes, allProdIdsRes] = await Promise.all([
        fetch('/api/inventory/estimate-products'),
        fetch('/api/inventory/categories'),
        // 모든 활성 제품 ID — '(삭제된 제품)' 마커가 false positive 안 되도록 별도 조회
        fetch('/api/inventory/products?id_only=1').catch(() => null),
    ]);
    allProds = await prodRes.json();
    catData = await catRes.json();
    try {
        if (allProdIdsRes && allProdIdsRes.ok) {
            const allProductsData = await allProdIdsRes.json();
            window.__allProdIds = new Set((Array.isArray(allProductsData) ? allProductsData : []).map(p => p.id));
        }
    } catch(e) { window.__allProdIds = null; }
    buildCatTabs();
    filterProducts();
}

// item이 가리키는 제품이 실제로 DB에 없을 때만 true (견적서 노출 OFF는 false positive 방지)
function isProductMissing(item) {
    if (!item.product_id) return false;
    // 전체 제품 ID 목록을 못 받았으면 false (안전한 폴백 — 정상 제품에 마커 안 붙음)
    if (!window.__allProdIds) return false;
    return !window.__allProdIds.has(item.product_id);
}

function buildCatTabs() {
    const tabs = document.getElementById('catTabs');
    tabs.innerHTML = '<button class="cat-tab active" onclick="filterCat(null)">전체</button>';
    catData.forEach(c => {
        tabs.innerHTML += `<button class="cat-tab" onclick="filterCat(${c.id})">${c.name}</button>`;
    });
}

let activeCatId = null;
function filterCat(id) {
    activeCatId = id;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    filterProducts();
}

function filterProducts() {
    const search = document.getElementById('prodSearch').value.toLowerCase();
    const list = document.getElementById('productList');
    let filtered = allProds;

    if (activeCatId) {
        const ids = getCatDescendants(activeCatId);
        filtered = filtered.filter(p => ids.includes(p.category_id));
    }
    if (search) {
        // 띄어쓰기·대소문자 무시 — 'EOS R50 V'도 'r50v'로 매칭
        const norm = s => (s || '').toLowerCase().replace(/\s+/g, '');
        const q = norm(search);
        filtered = filtered.filter(p => norm(p.name).includes(q) || norm(p.sku).includes(q)
            || norm(p.group_name).includes(q) || norm(p.option_name).includes(q) || norm(p.search_tags).includes(q));
    }

    // 옵션 그룹은 하나의 카드로 병합 — 클릭 시 옵션(블랙/화이트 등)을 골라 추가
    const seenGroups = new Set();
    const entries = [];
    filtered.forEach(p => {
        if (!p.group_id) { entries.push({ single: p }); return; }
        if (seenGroups.has(p.group_id)) return;
        seenGroups.add(p.group_id);
        // 검색이 한 옵션만 매칭해도 그룹의 전체 옵션을 보여준다
        const options = allProds.filter(x => x.group_id === p.group_id);
        entries.push({ group: { id: p.group_id, name: p.group_name || p.name, options } });
    });

    list.innerHTML = entries.map(e => {
        if (e.single) {
            const p = e.single;
            return `
        <div class="product-item" onclick="addToCart(${p.id})">
            <div>
                <div class="pi-name">${p.name}</div>
                <div class="pi-cat">${p.sku} · ${p.category||''}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${fmt(p.sale_price)}원</div>
                <div class="pi-stock ${p.is_low?'low':'ok'}">재고 ${p.quantity}</div>
            </div>
        </div>`;
        }
        const g = e.group;
        const prices = g.options.map(o => Number(o.sale_price) || 0);
        const mn = Math.min(...prices), mx = Math.max(...prices);
        const totalQty = g.options.reduce((s, o) => s + (o.quantity || 0), 0);
        return `
        <div class="product-item" onclick="openOptionPicker(${g.id})">
            <div>
                <div class="pi-name">${g.name} <span style="font-size:10px; color:#5e81f4; border:1px solid rgba(94,129,244,0.45); border-radius:4px; padding:0 5px;">옵션 ${g.options.length}종</span></div>
                <div class="pi-cat">${g.options.map(o => o.option_name || o.name).join(' / ')}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${mn === mx ? fmt(mn) : fmt(mn) + '~' + fmt(mx)}원</div>
                <div class="pi-stock ${totalQty > 0 ? 'ok' : 'low'}">재고 ${totalQty}</div>
            </div>
        </div>`;
    }).join('') || '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">제품이 없습니다.</div>';
}

// === 옵션 선택 (그룹 상품) ===
function openOptionPicker(groupId) {
    const options = allProds.filter(p => p.group_id === groupId);
    if (!options.length) return;
    document.getElementById('optPickerTitle').textContent = (options[0].group_name || '') + ' — 옵션 선택';
    document.getElementById('optPickerList').innerHTML = options.map(o => `
        <div class="product-item" onclick="addToCart(${o.id}); closeOptionPicker();">
            <div>
                <div class="pi-name">${o.option_name || o.name}</div>
                <div class="pi-cat">${o.sku}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${fmt(o.sale_price)}원</div>
                <div class="pi-stock ${o.is_low ? 'low' : 'ok'}">재고 ${o.quantity}</div>
            </div>
        </div>`).join('');
    document.getElementById('optionPickerOverlay').style.display = 'flex';
}
function closeOptionPicker() {
    document.getElementById('optionPickerOverlay').style.display = 'none';
}

function getCatDescendants(id) {
    let ids = [id];
    function walk(cats) {
        cats.forEach(c => { if (c.id === id || ids.includes(c.parent_id ?? -1)) { ids.push(c.id); } if (c.children) walk(c.children); });
    }
    catData.forEach(c => { if (c.id === id) { ids.push(c.id); (c.children||[]).forEach(c2 => { ids.push(c2.id); (c2.children||[]).forEach(c3 => ids.push(c3.id)); }); } });
    return [...new Set(ids)];
}

// === 장바구니 ===
function addToCart(productId) {
    const p = allProds.find(x => x.id === productId);
    if (!p) return;
    const price = Number(p.sale_price) || 0;
    const existing = cartItems.find(i => i.product_id === productId);
    if (existing) { existing.qty++; existing.subtotal = Number(existing.sale_price) * existing.qty; }
    else {
        // 스냅샷: 견적서 작성 시점의 제품 정보를 보존. 이후 제품 정보가 바뀌거나 삭제되어도 견적서는 영향 없음.
        cartItems.push({
            product_id: p.id,
            sku: p.sku,
            category: p.category,
            category_root: p.category_root || p.category, // 1차 대분류 — 소계 그룹 기준
            // 옵션 그룹 상품은 '그룹명 (옵션명)'으로 구분해 견적에 표시
            name: p.group_id && p.option_name ? `${p.group_name} (${p.option_name})` : p.name,
            purchase_price: p.purchase_price || 0,
            sale_price: price,
            qty: 1,
            time_required: '',
            subtotal: price,
        });
    }
    renderCart();
}

function renderCart() {
    const tb = document.getElementById('cartBody');
    if (!cartItems.length) {
        tb.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">좌측에서 제품을 선택하세요</td></tr>';
        updateTotals();
        return;
    }

    const grouped = {};
    cartItems.forEach(item => {
        const cat = item.category_root || item.category || '기타'; // 1차 대분류 소계
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(item);
    });

    let html = '', globalIdx = 0;
    for (const [cat, items] of Object.entries(grouped)) {
        html += `<tr class="cart-cat-header"><td colspan="8">${cat}</td></tr>`;
        let catTotal = 0;
        items.forEach((item, i) => {
            const idx = cartItems.indexOf(item);
            catTotal += item.subtotal;
            globalIdx++;
            html += `<tr>
                <td>${globalIdx}</td>
                <td style="font-size:10px; color:var(--text-muted);">${item.category||''}</td>
                <td>${item.name}${item.manual || !item.product_id ? ' <span style="font-size:9px; color:var(--text-muted); border:1px solid var(--border); border-radius:3px; padding:0 4px;" title="일회성 수기 품목 — 제품 관리에 등록되지 않고 견적서에만 저장됩니다">수기</span>' : ''}${isProductMissing(item) ? '<span style="font-size:10px; color:var(--text-muted); margin-left:6px;" title="원본 제품이 삭제되었지만 견적서 데이터는 보존됩니다">(삭제된 제품)</span>' : ''}</td>
                <td><input value="${item.time_required||''}" onchange="cartItems[${idx}].time_required=this.value" style="width:60px; background:var(--surface2); border:1px solid var(--border); border-radius:4px; padding:3px 6px; color:var(--text); font-size:11px; outline:none;"></td>
                <td class="text-right">${fmt(item.sale_price)}원</td>
                <td>
                    <div class="qty-ctrl">
                        <button onclick="changeQty(${idx},-1)">−</button>
                        <input value="${item.qty}" onchange="setQty(${idx},+this.value)">
                        <button onclick="changeQty(${idx},1)">+</button>
                    </div>
                </td>
                <td class="text-right" style="font-weight:600;">${fmt(item.subtotal)}원</td>
                <td><button class="btn-remove" onclick="removeItem(${idx})">×</button></td>
            </tr>`;
        });
        html += `<tr class="cart-subtotal"><td colspan="6">${cat} 소계</td><td class="text-right">${fmt(catTotal)}원</td><td></td></tr>`;
    }
    tb.innerHTML = html;
    updateTotals();
}

function changeQty(idx, delta) {
    cartItems[idx].qty = Math.max(1, cartItems[idx].qty + delta);
    cartItems[idx].subtotal = Number(cartItems[idx].sale_price) * cartItems[idx].qty;
    renderCart();
}
function setQty(idx, val) {
    cartItems[idx].qty = Math.max(1, parseInt(val)||1);
    cartItems[idx].subtotal = Number(cartItems[idx].sale_price) * cartItems[idx].qty;
    renderCart();
}
function removeItem(idx) {
    cartItems.splice(idx, 1);
    renderCart();
}

// === 수기 제품 추가 — 일회성 품목 (제품 관리 미등록, 장바구니에 스냅샷으로 저장) ===
function addManualItem() {
    const name = document.getElementById('miName').value.trim();
    if (!name) return alert('제품명을 입력해주세요.');
    const price = Math.max(0, parseInt(document.getElementById('miPrice').value) || 0);
    const qty = Math.max(1, parseInt(document.getElementById('miQty').value) || 1);
    const miCat = document.getElementById('miCat').value.trim() || '기타';
    cartItems.push({
        product_id: null, sku: '', category: miCat, category_root: miCat,
        name, purchase_price: 0, sale_price: price, qty, time_required: '', subtotal: price * qty, manual: true,
    });
    ['miName','miPrice'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('miQty').value = 1;
    renderCart();
}

// === 서비스 항목 (구버전 호환 — 저장된 항목이 있을 때만 표시/수정) ===
function renderServices() {
    document.getElementById('svcSection').style.display = svcItems.length ? '' : 'none';
    document.getElementById('svcTotalRow').style.display = svcItems.length ? '' : 'none';
    const el = document.getElementById('svcList');
    el.innerHTML = svcItems.map((s, i) => `
        <div class="svc-row">
            <input value="${s.name}" onchange="svcItems[${i}].name=this.value" placeholder="항목명" style="flex:2;">
            <input type="number" value="${s.amount}" onchange="svcItems[${i}].amount=+this.value; updateTotals();" placeholder="금액" style="flex:1;">
            <button class="btn-remove" onclick="svcItems.splice(${i},1); renderServices(); updateTotals();">×</button>
        </div>
    `).join('');
    updateTotals();
}

// === 견적 프리셋 — 저장/불러오기 (여러 개 선택해 조립, 추가/교체) ===
async function saveAsPreset() {
    if (!cartItems.length) return alert('저장할 품목이 없습니다. 먼저 제품을 담아주세요.');
    const title = prompt('프리셋 제목을 입력해주세요.', '');
    if (!title || !title.trim()) return;
    const res = await fetch('/api/estimate-presets', {
        method: 'POST', headers: H,
        body: JSON.stringify({ title: title.trim(), items: cartItems }),
    });
    if (!res.ok) { const e = await res.json().catch(()=>({})); return alert(e.message || '프리셋 저장에 실패했습니다.'); }
    alert('프리셋으로 저장되었습니다. 우측 프리셋 목록과 견적서 목록의 [프리셋] 탭에서 관리할 수 있습니다.');
    loadPresetPanel();
}

let PRESETS = [];
async function loadPresetPanel() {
    const res = await fetch('/api/estimate-presets', { headers: { 'Accept': 'application/json' } });
    PRESETS = res.ok ? await res.json() : [];
    const list = document.getElementById('presetPanelList');
    list.innerHTML = PRESETS.length ? PRESETS.map(p => `
        <div class="preset-item" onclick="applyPresetById(${p.id})" title="클릭하면 품목 ${p.item_count}개가 견적서에 담깁니다">
            <div class="preset-name">${_escE(p.title)}</div>
            <div class="preset-total">${fmt(p.total)}원</div>
        </div>`).join('') : '<div style="padding:16px; text-align:center; color:var(--text-muted); font-size:12px;">저장된 프리셋이 없습니다.<br>품목을 담은 뒤 [현재 품목을 프리셋으로 저장]을 눌러 만들 수 있습니다.</div>';
}
// 프리셋 클릭 → 품목 담기. 여러 프리셋을 연속 클릭해 조립할 수 있고,
// product_id가 살아있는 품목은 현재 판매가/이름으로 갱신 (수기·삭제 제품은 저장본 유지)
function applyPresetById(id) {
    const preset = PRESETS.find(p => p.id === id);
    if (!preset) return;
    const items = (preset.items || []).map(it => {
        const item = { ...it };
        const cur = it.product_id ? allProds.find(p => p.id === it.product_id) : null;
        if (cur) {
            item.name = cur.group_id && cur.option_name ? `${cur.group_name} (${cur.option_name})` : cur.name;
            item.category = cur.category || item.category;
            item.category_root = cur.category_root || item.category_root || item.category;
            item.sku = cur.sku;
            item.sale_price = Number(cur.sale_price) || 0;
            item.purchase_price = Number(cur.purchase_price) || 0;
        }
        item.qty = Math.max(1, parseInt(item.qty) || 1);
        item.subtotal = (Number(item.sale_price) || 0) * item.qty;
        return item;
    });
    if (document.getElementById('presetReplaceMode').checked) { cartItems.length = 0; }
    cartItems.push(...items);
    renderCart();
}
function _escE(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// === 합계 ===
function updateTotals() {
    const pt = cartItems.reduce((s,i) => s + (Number(i.subtotal)||0), 0);
    const st = svcItems.reduce((s,i) => s + (Number(i.amount)||0), 0);
    document.getElementById('productTotal').textContent = fmt(pt)+'원';
    document.getElementById('serviceTotal').textContent = fmt(st)+'원';
    document.getElementById('grandTotal').textContent = fmt(pt+st)+'원';
    document.getElementById('totalItems').textContent = cartItems.length + svcItems.filter(s=>s.name).length;
}

// === 의뢰자 검색 ===
// (검색 구현은 아래 async searchClients — 예전 미완성 중복 선언은 SyntaxError를 유발해 제거)
let searchTimer;
function selectClient(client) {
    clientId = client.id;
    document.getElementById('cNickname').value = client.nickname || '';
    document.getElementById('cName').value = client.name || '';
    document.getElementById('cPhone').value = client.phone || '';
    document.getElementById('clientResults').classList.remove('show');
    loadClientProjects(clientId, null);
}

// 의뢰자의 진행 중 프로젝트를 연동 선택지로 로드 (선택 사항 — '연동 안 함'이 기본)
async function loadClientProjects(cid, selectedId) {
    const sel = document.getElementById('cProject');
    if (!cid) {
        sel.innerHTML = '<option value="">의뢰자 선택 후 지정 가능</option>';
        sel.disabled = true;
        return;
    }
    const res = await fetch(`/api/estimate-client-projects/${cid}`, { headers: { 'Accept': 'application/json' } });
    const projects = res.ok ? await res.json() : [];
    sel.innerHTML = '<option value="">연동 안 함</option>' + projects.map(p =>
        `<option value="${p.id}">${_escE(p.name)}${p.stage_label ? ` · ${_escE(p.stage_label)}` : ''}</option>`).join('');
    sel.disabled = false;
    if (selectedId && projects.some(p => p.id === selectedId)) sel.value = String(selectedId);
}

// === 저장/발행/삭제 ===
async function saveEstimate() {
    const body = {
        client_id: clientId,
        project_id: +document.getElementById('cProject').value || null,
        client_name: document.getElementById('cName').value || null,
        client_nickname: document.getElementById('cNickname').value || null,
        client_phone: document.getElementById('cPhone').value || null,
        product_items: cartItems,
        service_items: svcItems.filter(s => s.name),
        status: document.getElementById('estStatus').value,
        memo: document.getElementById('estMemo').value || null,
    };
    const res = await fetch(`/api/estimates/${estId}`, {method:'PATCH', headers:H, body:JSON.stringify(body)});
    if (res.ok) {
        document.getElementById('saveIndicator').textContent = '저장됨 ' + new Date().toLocaleTimeString('ko-KR',{hour:'2-digit',minute:'2-digit'});
        if (window.opener) try { window.opener.loadEstimates?.(); } catch(e) {}
        const d = await res.json().catch(() => ({}));
        // 발행완료 전환 시 결제요청 자동 생성 결과 안내
        if (d.payapp_warning) { alert(d.payapp_warning); }
        else if (body.status === 'issued' && d.payapp_payurl) {
            if (confirm('발행 완료 — 의뢰자 페이지에 결제 버튼이 활성화되었습니다.\n의뢰자 링크를 지금 복사할까요?')) copyPublicLink();
            location.reload();
        }
        return;
    }
    // 실패 — 어떤 필드/예외가 문제인지 표시
    const err = await res.json().catch(() => ({}));
    const parts = [];
    if (err.message) parts.push(err.message);
    if (err.errors) {
        Object.entries(err.errors).forEach(([f, msgs]) => {
            parts.push(`[${f}] ` + (Array.isArray(msgs) ? msgs.join(', ') : msgs));
        });
    }
    if (err.exception) parts.push(`예외: ${err.exception}`);
    if (err.file) parts.push(`위치: ${err.file}`);
    alert(`저장 실패 (${res.status})\n\n` + (parts.length ? parts.join('\n') : '(빈 응답)'));
}

function printEstimate() {
    window.open(`/estimates/${estId}/print`, `print_${estId}`, 'width=900,height=700,scrollbars=yes');
}

async function deleteEstimate() {
    if (!confirm('이 견적서를 삭제할까요?')) return;
    await fetch(`/api/estimates/${estId}`, {method:'DELETE', headers:H});
    if (window.opener) try { window.opener.loadEstimates?.(); } catch(e) {}
    window.close();
}

// === 의뢰자 검색 (직접 API) ===
async function searchClients(q) {
    clearTimeout(searchTimer);
    const el = document.getElementById('clientResults');
    if (q.length < 1) { el.classList.remove('show'); return; }
    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/api/clients/search?q=${encodeURIComponent(q)}`);
            const clients = await res.json();
            if (!clients.length) { el.classList.remove('show'); return; }
            el.innerHTML = clients.map(c => `<div class="client-result-item" onclick='selectClient(${JSON.stringify(c)})'>${c.nickname || c.name || ''}${c.nickname && c.name ? ' ('+c.name+')' : ''} ${c.phone||''}</div>`).join('');
            el.classList.add('show');
        } catch(e) { el.classList.remove('show'); }
    }, 300);
}

// 초기화
loadProducts();
renderCart();
renderServices();
loadPresetPanel();
if (clientId) loadClientProjects(clientId, {{ $estimate->project_id ?? 'null' }});

document.addEventListener('click', e => {
    if (!e.target.closest('.client-search-wrap')) document.getElementById('clientResults').classList.remove('show');
});
</script>
</body>
</html>
