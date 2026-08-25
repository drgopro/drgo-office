<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
@include('partials.ajax-fetch-header')
    <script>(function(){try{localStorage.removeItem('drgo_theme');}catch(e){}document.documentElement.setAttribute('data-theme','light');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $preset ? '프리셋 수정' : '프리셋 만들기' }} - 닥터고블린 오피스</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        :root, [data-theme="dark"] { --bg:#111; --surface:#1c1c1c; --surface2:#272727; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --red:#d48888; --green:#88d488; }
        [data-theme="light"] { --bg:#eef0f3; --surface:#fff; --surface2:#f2f4f7; --border:#dfe3e9; --text:#1d1f24; --text-muted:#6b7684; --accent:#2e6cb5; --accent-text:#fff; --red:#c03838; --green:#248a38; --navy:#1d2d3d; --slate:#416180; --slate-lt:#cfe0f0; }
        :root, [data-theme="dark"] { --navy:#1d2d3d; --slate:#416180; --slate-lt:#cfe0f0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; display:flex; height:100vh; overflow:hidden; }
        input, button, textarea, select { font-family:inherit; }

        /* 좌측 — 제품 리스트 (견적서 편집과 동일 레이아웃) */
        .panel-left { width:420px; border-right:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0; }
        .panel-left-header { padding:14px 16px; border-bottom:1px solid var(--border); }
        .panel-left-header h3 { font-size:14px; font-weight:700; margin-bottom:10px; }
        .cat-tabs { display:flex; flex-direction:column; gap:5px; margin-bottom:9px; }
        .cat-tab-row { display:flex; flex-wrap:wrap; gap:5px; align-items:center; }
        .cat-tab-arrow { color:var(--text-muted); font-size:11px; margin-right:1px; }
        .cat-tab { padding:4px 11px; font-size:11px; border:1px solid #b9cbe0; border-radius:7px; background:var(--surface); color:var(--accent); font-weight:600; cursor:pointer; }
        .cat-tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }
        .search-input { width:100%; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; }
        .search-input:focus { border-color:var(--accent); }
        .product-list { flex:1; overflow-y:auto; padding:4px 8px; }
        .product-item { display:flex; justify-content:space-between; align-items:center; padding:10px 8px; border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.1s; font-size:13px; }
        .product-item:hover { background:var(--surface2); }
        .product-item .pi-name { flex:1; font-weight:600; }
        .product-item .pi-cat { font-size:10.5px; color:var(--text-muted); margin-top:3px; font-weight:400; }
        .product-item .pi-price { font-size:12.5px; color:var(--navy); font-weight:700; white-space:nowrap; margin-left:10px; }
        .product-item .pi-stock { font-size:10px; margin-left:8px; }
        .pi-stock.low { color:var(--red); }
        .pi-stock.ok { color:var(--text-muted); }

        /* 패널 폭 조절 리사이저 */
        .panel-resizer { width:6px; margin:0 -3px; cursor:col-resize; flex-shrink:0; z-index:20; background:transparent; transition:background 0.12s; }
        .panel-resizer:hover, .panel-resizer.active { background:var(--accent); opacity:0.45; }

        /* 우측 — 프리셋 */
        .panel-right { flex:1; display:flex; flex-direction:column; overflow:hidden; }
        .panel-right-header { background:var(--surface); padding:13px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:center; }
        .panel-right-header h2 { font-size:17px; font-weight:800; color:var(--navy); white-space:nowrap; }
        .title-input { flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:14px; font-weight:600; outline:none; }
        .title-input:focus { border-color:var(--accent); }
        .est-body { flex:1; overflow-y:auto; padding:20px; background:var(--bg); }
        .cart-section { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
        .cart-section h4 { font-size:12px; color:var(--slate); font-weight:700; margin-bottom:12px; letter-spacing:0.08em; }
        .cart-table { width:100%; border-collapse:separate; border-spacing:0; }
        .cart-table th { font-size:11px; color:var(--slate); font-weight:700; letter-spacing:0.06em; text-align:left; padding:8px 6px 10px; border-bottom:1px solid #ccd4dd; }
        .cart-table td { font-size:12.5px; padding:9px 6px; border-bottom:1px solid var(--border); }
        .cart-table tr:last-child td { border-bottom:none; }
        /* 대분류 밴드 — 슬레이트 배경 + 우측 소계 */
        .cart-cat-header td { background:var(--slate); color:#fff; font-size:12px; font-weight:700; padding:8px 10px; border-bottom:none; }
        .cart-cat-header td:first-child { border-radius:6px 0 0 6px; }
        .cart-cat-header td:last-child { border-radius:0 6px 6px 0; }
        .cart-row-num { color:var(--accent); font-weight:600; }
        /* 분류 소계 — 각 대분류 블록 최하단의 옅은 밴드 */
        .cart-subtotal td { background:#f2f4f6; font-size:12px; font-weight:700; color:var(--navy); text-align:right; padding:9px 10px; border-bottom:none; }
        .cart-subtotal td:first-child { border-radius:0 0 0 6px; }
        .cart-subtotal td:last-child { border-radius:0 0 6px 0; }
        .qty-ctrl { display:flex; align-items:center; gap:3px; }
        .qty-ctrl button { width:22px; height:22px; border:1px solid #9db8d4; background:var(--surface); color:var(--accent); border-radius:50%; cursor:pointer; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .qty-ctrl button:hover { background:var(--surface2); }
        .qty-ctrl input { width:34px; text-align:center; background:var(--surface); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:12px; padding:3px 2px; outline:none; }
        .btn-remove { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px; }
        .btn-remove:hover { color:var(--red); }
        .text-right { text-align:right; }
        .svc-row { display:flex; gap:6px; margin-bottom:6px; align-items:center; }
        .svc-row input { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 8px; color:var(--text); font-size:12px; outline:none; }
        .svc-row input:focus { border-color:var(--accent); }
        .btn-add-svc { background:none; border:1px dashed var(--border); color:var(--text-muted); font-size:11px; padding:5px 10px; border-radius:6px; cursor:pointer; }
        .btn-add-svc:hover { border-color:var(--accent); color:var(--accent); }
        .total-section { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:16px 18px; }
        .total-row { display:flex; justify-content:space-between; font-size:18px; font-weight:800; color:var(--navy); }
        .panel-right-footer { background:var(--surface); padding:12px 20px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
        .btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid transparent; }
        .btn-save { background:var(--navy); color:#fff; font-weight:700; }
        .btn-save:hover { filter:brightness(1.25); }
        .btn-ghost { background:var(--surface); border-color:#d9dee5; color:var(--text-muted); }
        .btn-ghost:hover { border-color:var(--slate); color:var(--slate); }
    </style>
</head>
<body>
<div class="panel-left">
    <div class="panel-left-header">
        <h3>제품 리스트</h3>
        <div class="cat-tabs" id="catTabs">
            <div class="cat-tab-row"><button class="cat-tab active" onclick="setCatPath(0,null)">전체</button></div>
        </div>
        <input class="search-input" id="prodSearch" placeholder="제품명/SKU 검색" oninput="filterProducts()">
    </div>
    <div class="product-list" id="productList"></div>
</div>
<div class="panel-resizer" id="rzLeft" title="드래그해서 제품 리스트 폭 조절"></div>

<div class="panel-right">
    <div class="panel-right-header">
        <h2>{{ $preset ? '프리셋 수정' : '프리셋 만들기' }}</h2>
        <input class="title-input" id="presetTitle" placeholder="프리셋 제목 (예: 스튜디오 기본 세팅) *" value="{{ $preset->title ?? '' }}">
    </div>

    <div class="est-body">
        <div class="cart-section">
            <h4>프리셋 품목</h4>
            <table class="cart-table">
                <thead><tr><th>번호</th><th>분류</th><th>제품명</th><th class="text-right">판매가</th><th>수량</th><th class="text-right">합계</th><th></th></tr></thead>
                <tbody id="cartBody"></tbody>
            </table>
        </div>

        <div class="cart-section">
            <h4>수기 제품 추가 <span style="color:var(--text-muted); font-weight:400; letter-spacing:0;">— 제품 관리에 등록되지 않는 일회성 품목</span></h4>
            <div class="svc-row">
                <input id="miCat" placeholder="카테고리" style="flex:1;">
                <input id="miName" placeholder="제품명 *" style="flex:2;" onkeydown="if(event.key==='Enter')addManualItem()">
                <input id="miPrice" type="number" min="0" placeholder="판매가" style="flex:1;" onkeydown="if(event.key==='Enter')addManualItem()">
                <input id="miQty" type="number" min="1" value="1" title="수량" style="width:56px;">
                <button class="btn-add-svc" style="padding:6px 14px;" onclick="addManualItem()">+ 추가</button>
            </div>
        </div>

        <div class="total-section">
            <div class="total-row"><span>합계 금액</span><span id="grandTotal">0원</span></div>
        </div>
    </div>

    <div class="panel-right-footer">
        <button class="btn btn-ghost" onclick="window.close()">닫기</button>
        <button class="btn btn-save" onclick="savePreset()">저장</button>
    </div>
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

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const presetId = {{ $preset->id ?? 'null' }};
let allProds = [], catData = [], cartItems = @json($preset->items ?? []);

function fmt(n) { return Number(n || 0).toLocaleString(); }
function _escE(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function loadInitial() {
    const [prodRes, catRes] = await Promise.all([
        fetch('/api/inventory/estimate-products'),
        fetch('/api/inventory/categories'),
    ]);
    allProds = await prodRes.json();
    catData = await catRes.json();
    buildCatTabs();
    filterProducts();
    renderCart();
}

// === 카테고리 드릴다운 필터 — 1차 칩 선택 시 하위 카테고리 칩 행이 열린다 (견적서 편집과 동일) ===
let catPath = [];
let activeCatId = null;

function buildCatTabs() {
    const el = document.getElementById('catTabs');
    const chain = [];
    let level = catData;
    for (const id of catPath) {
        const node = (level || []).find(c => c.id === id);
        if (!node) break;
        chain.push(node);
        level = node.children || [];
    }
    if (chain.length !== catPath.length) catPath.length = chain.length;
    activeCatId = catPath.length ? catPath[catPath.length - 1] : null;

    let html = '<div class="cat-tab-row">'
        + `<button class="cat-tab ${!catPath.length ? 'active' : ''}" onclick="setCatPath(0,null)">전체</button>`
        + catData.map(c => `<button class="cat-tab ${catPath[0] === c.id ? 'active' : ''}" onclick="setCatPath(0,${c.id})">${_escE(c.name)}</button>`).join('')
        + '</div>';
    chain.forEach((node, i) => {
        const children = node.children || [];
        if (!children.length) return;
        html += '<div class="cat-tab-row"><span class="cat-tab-arrow">└</span>'
            + `<button class="cat-tab ${catPath.length === i + 1 ? 'active' : ''}" onclick="setCatPath(${i + 1},null)">${_escE(node.name)} 전체</button>`
            + children.map(c => `<button class="cat-tab ${catPath[i + 1] === c.id ? 'active' : ''}" onclick="setCatPath(${i + 1},${c.id})">${_escE(c.name)}</button>`).join('')
            + '</div>';
    });
    el.innerHTML = html;
}

function setCatPath(depth, id) {
    catPath = catPath.slice(0, depth);
    if (id) catPath.push(id);
    buildCatTabs();
    filterProducts();
}

// 트리 어느 깊이의 카테고리든 자기 자신 + 모든 하위 ID를 수집
function getCatDescendants(id) {
    const find = nodes => {
        for (const n of nodes || []) {
            if (n.id === id) return n;
            const f = find(n.children);
            if (f) return f;
        }
        return null;
    };
    const ids = [];
    const collect = n => { ids.push(n.id); (n.children || []).forEach(collect); };
    const node = find(catData);
    if (node) collect(node); else ids.push(id);
    return ids;
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
        const norm = s => (s || '').toLowerCase().replace(/\s+/g, '');
        const q = norm(search);
        filtered = filtered.filter(p => norm(p.name).includes(q) || norm(p.sku).includes(q)
            || norm(p.group_name).includes(q) || norm(p.option_name).includes(q) || norm(p.search_tags).includes(q));
    }

    // 옵션 그룹은 카드 하나로 병합 (견적서 편집과 동일)
    const seenGroups = new Set();
    const entries = [];
    filtered.forEach(p => {
        if (!p.group_id) { entries.push({ single: p }); return; }
        if (seenGroups.has(p.group_id)) return;
        seenGroups.add(p.group_id);
        entries.push({ group: { id: p.group_id, name: p.group_name || p.name, options: allProds.filter(x => x.group_id === p.group_id) } });
    });

    list.innerHTML = entries.map(e => {
        if (e.single) {
            const p = e.single;
            return `<div class="product-item" onclick="addToCart(${p.id})">
                <div><div class="pi-name">${p.name}</div><div class="pi-cat">${p.sku} · ${p.category_path || p.category || ''}</div></div>
                <div style="text-align:right;"><div class="pi-price">${fmt(p.sale_price)}원</div><div class="pi-stock ${p.is_low?'low':'ok'}">재고 ${p.quantity}</div></div>
            </div>`;
        }
        const g = e.group;
        const prices = g.options.map(o => Number(o.sale_price) || 0);
        const mn = Math.min(...prices), mx = Math.max(...prices);
        return `<div class="product-item" onclick="openOptionPicker(${g.id})">
            <div><div class="pi-name">${g.name} <span style="font-size:10px; color:var(--accent); border:1px solid #9db8d4; border-radius:4px; padding:0 5px;">옵션 ${g.options.length}종</span></div>
            <div class="pi-cat">${g.options[0]?.category_path || g.options[0]?.category || ''} · ${g.options.map(o => o.option_name || o.name).join(' / ')}</div></div>
            <div style="text-align:right;"><div class="pi-price">${mn === mx ? fmt(mn) : fmt(mn)+'~'+fmt(mx)}원</div></div>
        </div>`;
    }).join('') || '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">제품이 없습니다.</div>';
}

function openOptionPicker(groupId) {
    const options = allProds.filter(p => p.group_id === groupId);
    if (!options.length) return;
    document.getElementById('optPickerTitle').textContent = (options[0].group_name || '') + ' — 옵션 선택';
    document.getElementById('optPickerList').innerHTML = options.map(o => `
        <div class="product-item" onclick="addToCart(${o.id}); closeOptionPicker();">
            <div><div class="pi-name">${o.option_name || o.name}</div><div class="pi-cat">${o.sku}</div></div>
            <div style="text-align:right;"><div class="pi-price">${fmt(o.sale_price)}원</div><div class="pi-stock ${o.is_low?'low':'ok'}">재고 ${o.quantity}</div></div>
        </div>`).join('');
    document.getElementById('optionPickerOverlay').style.display = 'flex';
}
function closeOptionPicker() { document.getElementById('optionPickerOverlay').style.display = 'none'; }

function addToCart(productId) {
    const p = allProds.find(x => x.id === productId);
    if (!p) return;
    const price = Number(p.sale_price) || 0;
    const existing = cartItems.find(i => i.product_id === productId);
    if (existing) { existing.qty++; existing.subtotal = Number(existing.sale_price) * existing.qty; }
    else {
        cartItems.push({
            product_id: p.id, sku: p.sku, category: p.category,
            category_root: p.category_root || p.category,
            name: p.group_id && p.option_name ? `${p.group_name} (${p.option_name})` : p.name,
            purchase_price: p.purchase_price || 0, sale_price: price, qty: 1,
            time_required: p.use_time_required ? (p.time_required || '') : '', use_time: !!p.use_time_required,
            subtotal: price, manual: false,
        });
    }
    renderCart();
}
function addManualItem() {
    const name = document.getElementById('miName').value.trim();
    if (!name) return alert('제품명을 입력해주세요.');
    const price = Math.max(0, parseInt(document.getElementById('miPrice').value) || 0);
    const qty = Math.max(1, parseInt(document.getElementById('miQty').value) || 1);
    const miCat = document.getElementById('miCat').value.trim() || '기타';
    cartItems.push({ product_id: null, sku: '', category: miCat, category_root: miCat, name, purchase_price: 0, sale_price: price, qty, time_required: '', use_time: false, subtotal: price * qty, manual: true });
    ['miName','miPrice'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('miQty').value = 1;
    renderCart();
}

function renderCart() {
    const tb = document.getElementById('cartBody');
    if (!cartItems.length) {
        tb.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">좌측에서 제품을 선택하거나 아래에서 수기 품목을 추가하세요</td></tr>';
        updateTotals();
        return;
    }
    const grouped = {};
    cartItems.forEach(item => {
        const cat = item.category_root || item.category || '기타';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(item);
    });
    let html = '', globalIdx = 0;
    for (const [cat, items] of Object.entries(grouped)) {
        const catTotal = items.reduce((s, i) => s + i.subtotal, 0);
        html += `<tr class="cart-cat-header"><td colspan="7">${_escE(cat)}</td></tr>`;
        items.forEach(item => {
            const idx = cartItems.indexOf(item);
            globalIdx++;
            html += `<tr>
                <td><span class="cart-row-num">${globalIdx}</span></td>
                <td style="font-size:10px; color:var(--text-muted);">${_escE(item.category||'')}</td>
                <td>${_escE(item.name)}${item.manual || !item.product_id ? ' <span style="font-size:9px; color:var(--text-muted); border:1px solid var(--border); border-radius:3px; padding:0 4px;">수기</span>' : ''}</td>
                <td class="text-right"><input type="number" min="0" value="${item.sale_price}" onchange="setPrice(${idx}, +this.value)" style="width:86px; text-align:right; background:var(--surface2); border:1px solid var(--border); border-radius:4px; padding:3px 6px; color:var(--text); font-size:12px; outline:none;"></td>
                <td><div class="qty-ctrl">
                    <button onclick="changeQty(${idx},-1)">−</button>
                    <input value="${item.qty}" onchange="setQty(${idx},+this.value)">
                    <button onclick="changeQty(${idx},1)">+</button>
                </div></td>
                <td class="text-right" style="font-weight:600;">${fmt(item.subtotal)}원</td>
                <td><button class="btn-remove" onclick="cartItems.splice(${idx},1); renderCart();">×</button></td>
            </tr>`;
        });
        html += `<tr class="cart-subtotal"><td colspan="5">${_escE(cat)} 소계</td><td class="text-right">${fmt(catTotal)}원</td><td></td></tr>`;
    }
    tb.innerHTML = html;
    updateTotals();
}
function changeQty(idx, d) { cartItems[idx].qty = Math.max(1, cartItems[idx].qty + d); cartItems[idx].subtotal = Number(cartItems[idx].sale_price) * cartItems[idx].qty; renderCart(); }
function setQty(idx, v) { cartItems[idx].qty = Math.max(1, parseInt(v)||1); cartItems[idx].subtotal = Number(cartItems[idx].sale_price) * cartItems[idx].qty; renderCart(); }
function setPrice(idx, v) { cartItems[idx].sale_price = Math.max(0, v||0); cartItems[idx].subtotal = cartItems[idx].sale_price * cartItems[idx].qty; renderCart(); }
function updateTotals() {
    document.getElementById('grandTotal').textContent = fmt(cartItems.reduce((s,i) => s + (Number(i.subtotal)||0), 0)) + '원';
}

async function savePreset() {
    const title = document.getElementById('presetTitle').value.trim();
    if (!title) return alert('프리셋 제목을 입력해주세요.');
    if (!cartItems.length) return alert('품목을 1개 이상 담아주세요.');
    const res = await fetch(presetId ? `/api/estimate-presets/${presetId}` : '/api/estimate-presets', {
        method: presetId ? 'PATCH' : 'POST', headers: H,
        body: JSON.stringify({ title, items: cartItems }),
    });
    if (!res.ok) {
        const e = await res.json().catch(()=>({}));
        const detail = e.errors ? '\n' + Object.values(e.errors).flat().join('\n') : '';
        return alert((e.message || '저장에 실패했습니다.') + detail + `\n(HTTP ${res.status})`);
    }
    if (window.opener) { try { window.opener.loadPresets?.(); } catch(e) {} }
    alert('저장되었습니다.');
    window.close();
}

// 패널 폭 드래그 조절 (견적서 편집과 동일 키 공유)
(function initPanelResize() {
    const L = document.querySelector('.panel-left');
    const lw = parseInt(localStorage.getItem('estPanelLeftW'));
    if (lw) L.style.width = Math.min(Math.max(lw, 240), 680) + 'px';
    const rz = document.getElementById('rzLeft');
    rz.addEventListener('mousedown', e => {
        e.preventDefault();
        rz.classList.add('active');
        document.body.style.userSelect = 'none';
        const move = ev => { L.style.width = Math.min(Math.max(ev.clientX, 240), 680) + 'px'; };
        const up = () => {
            rz.classList.remove('active');
            document.body.style.userSelect = '';
            localStorage.setItem('estPanelLeftW', parseInt(L.style.width));
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup', up);
        };
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', up);
    });
})();

loadInitial();
</script>
</body>
</html>
