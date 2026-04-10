<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
    <script>(function(){var t=localStorage.getItem('drgo_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>견적서 #{{ $estimate->id }} - 닥터고블린 오피스</title>
    <style>
        :root, [data-theme="dark"] { --bg:#111; --surface:#1c1c1c; --surface2:#272727; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --red:#d48888; --green:#88d488; --blue:#8ab4c8; }
        [data-theme="light"] { --bg:#f4f5f7; --surface:#fff; --surface2:#eceef2; --border:#b8bcc8; --text:#1a1e28; --text-muted:#5a6070; --accent:#3b5ea0; --red:#c03838; --green:#248a38; --blue:#2e6a9a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:-apple-system,sans-serif; display:flex; height:100vh; overflow:hidden; }

        /* 좌측 — 제품 리스트 */
        .panel-left { width:420px; border-right:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0; }
        .panel-left-header { padding:14px 16px; border-bottom:1px solid var(--border); }
        .panel-left-header h3 { font-size:14px; font-weight:700; margin-bottom:10px; }
        .cat-tabs { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px; }
        .cat-tab { padding:4px 10px; font-size:11px; border:1px solid var(--border); border-radius:6px; background:none; color:var(--text-muted); cursor:pointer; }
        .cat-tab.active { background:var(--accent); color:#1a1207; border-color:var(--accent); }
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
        .btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; }
        .btn-save { background:var(--surface2); border:1px solid var(--border); color:var(--text); }
        .btn-save:hover { border-color:var(--accent); }
        .btn-issue { background:var(--accent); color:#1a1207; }
        .btn-delete { background:none; border:1px solid var(--border); color:var(--red); }
        .btn-delete:hover { border-color:var(--red); }
        .btn-print { background:var(--blue); color:#1a1207; }
        .save-indicator { font-size:11px; color:var(--text-muted); align-self:center; }
        [data-theme="light"] .cat-tab.active { color:#fff; }
        [data-theme="light"] .btn-issue { color:#fff; }
        [data-theme="light"] .btn-print { color:#fff; }
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

<div class="panel-right">
    <div class="panel-right-header">
        <h2>견적서 #{{ $estimate->id }}</h2>
        <select id="estStatus" style="background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:5px 10px; color:var(--text); font-size:12px; outline:none; cursor:pointer;">
            <option value="created" {{ $estimate->status === 'created' ? 'selected' : '' }}>생성</option>
            <option value="editing" {{ $estimate->status === 'editing' ? 'selected' : '' }}>수정 중</option>
            <option value="completed" {{ $estimate->status === 'completed' ? 'selected' : '' }}>작성 완료</option>
            <option value="paid" {{ $estimate->status === 'paid' ? 'selected' : '' }}>결제 완료</option>
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
            </div>
        </div>

        <!-- 장바구니 -->
        <div class="cart-section">
            <h4>제품 항목</h4>
            <table class="cart-table">
                <thead><tr><th>번호</th><th>분류</th><th>제품명</th><th>소요시간</th><th class="text-right">판매가</th><th>수량</th><th class="text-right">합계</th><th></th></tr></thead>
                <tbody id="cartBody"><tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">좌측에서 제품을 선택하세요</td></tr></tbody>
            </table>
        </div>

        <!-- 서비스 항목 -->
        <div class="cart-section">
            <h4>서비스 항목</h4>
            <div id="svcList"></div>
            <button class="btn-add-svc" onclick="addServiceItem()">+ 서비스 항목 추가</button>
        </div>

        <!-- 합계 -->
        <div class="total-section">
            <div class="total-row"><span>제품 소계</span><span id="productTotal">0원</span></div>
            <div class="total-row"><span>서비스 소계</span><span id="serviceTotal">0원</span></div>
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
        <button class="btn" style="border:1px solid var(--border);color:var(--text-muted);background:none;" onclick="openActivityLog('Estimate',{{ $estimate->id }},'견적서 #{{ $estimate->id }} 수정 로그')">📋 로그</button>
        <button class="btn btn-delete" onclick="deleteEstimate()">삭제</button>
        <button class="btn btn-print" onclick="printEstimate()">견적서 출력</button>
        <button class="btn btn-save" onclick="saveEstimate()" style="background:var(--accent); color:#1a1207; font-weight:700;">저장</button>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const estId = {{ $estimate->id }};
let clientId = {{ $estimate->client_id ?? 'null' }};
let allProds = [], catData = [], cartItems = @json($estimate->product_items ?? []), svcItems = @json($estimate->service_items ?? []);

function fmt(n) { return Number(n).toLocaleString(); }

// === 제품 로드 ===
async function loadProducts() {
    const [prodRes, catRes] = await Promise.all([
        fetch('/api/inventory/estimate-products'),
        fetch('/api/inventory/categories')
    ]);
    allProds = await prodRes.json();
    catData = await catRes.json();
    buildCatTabs();
    filterProducts();
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
        filtered = filtered.filter(p => p.name.toLowerCase().includes(search) || p.sku.toLowerCase().includes(search));
    }

    list.innerHTML = filtered.map(p => `
        <div class="product-item" onclick="addToCart(${p.id})">
            <div>
                <div class="pi-name">${p.name}</div>
                <div class="pi-cat">${p.sku} · ${p.category||''}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${fmt(p.sale_price)}원</div>
                <div class="pi-stock ${p.is_low?'low':'ok'}">재고 ${p.quantity}</div>
            </div>
        </div>
    `).join('') || '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">제품이 없습니다.</div>';
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
    else { cartItems.push({ product_id:p.id, category:p.category, name:p.name, sale_price:price, qty:1, time_required:'', subtotal:price }); }
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
        const cat = item.category || '기타';
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
                <td>${item.name}</td>
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

// === 서비스 항목 ===
function addServiceItem() {
    svcItems.push({name:'', amount:0});
    renderServices();
}
function renderServices() {
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
let searchTimer;
function searchClients(q) {
    clearTimeout(searchTimer);
    const el = document.getElementById('clientResults');
    if (q.length < 1) { el.classList.remove('show'); return; }
    searchTimer = setTimeout(async () => {
        const res = await fetch('/api/inventory/products'); // reuse client search later
        // Simple client search via existing API
        const r = await fetch(`/clients?_format=json&search=${encodeURIComponent(q)}`);
        // fallback: search clients directly
        el.classList.remove('show');
    }, 300);
}
function selectClient(client) {
    clientId = client.id;
    document.getElementById('cNickname').value = client.nickname || '';
    document.getElementById('cName').value = client.name || '';
    document.getElementById('cPhone').value = client.phone || '';
    document.getElementById('clientResults').classList.remove('show');
}

// === 저장/발행/삭제 ===
async function saveEstimate() {
    const body = {
        client_id: clientId,
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
    } else {
        alert('저장 실패');
    }
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
            el.innerHTML = clients.map(c => `<div class="client-result-item" onclick='selectClient(${JSON.stringify(c)})'>${c.nickname||''} (${c.name}) ${c.phone||''}</div>`).join('');
            el.classList.add('show');
        } catch(e) { el.classList.remove('show'); }
    }, 300);
}

// 초기화
loadProducts();
renderCart();
renderServices();

document.addEventListener('click', e => {
    if (!e.target.closest('.client-search-wrap')) document.getElementById('clientResults').classList.remove('show');
});
</script>
</body>
</html>
