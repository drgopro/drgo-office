<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
@include('partials.ajax-fetch-header')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $order ? '주문 수정' : '주문 추가' }} - 닥터고블린 오피스</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        :root { --bg:#eef0f3; --surface:#fff; --surface2:#f2f4f7; --border:#dfe3e9; --text:#1d1f24; --text-muted:#6b7684; --accent:#2e6cb5; --red:#c03838; --navy:#1d2d3d; --slate:#416180; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; padding:18px; font-size:13px; }
        input, button, textarea { font-family:inherit; }
        h2 { font-size:16px; font-weight:800; color:var(--navy); margin-bottom:14px; }
        .card { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
        .card h4 { font-size:12px; color:var(--slate); font-weight:700; letter-spacing:0.08em; margin-bottom:10px; }
        .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:9px 11px; font-size:13px; color:var(--text); outline:none; }
        .field-input:focus { border-color:var(--accent); }
        .item-head { display:flex; gap:8px; font-size:11px; color:var(--slate); font-weight:700; padding:0 2px 6px; }
        .item-row { display:flex; gap:8px; margin-bottom:7px; align-items:center; }
        .item-row input { background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:8px 10px; font-size:12.5px; color:var(--text); outline:none; }
        .item-row input:focus { border-color:var(--accent); }
        .w-name { flex:2.2; min-width:0; }
        .w-qty { width:64px; text-align:right; }
        .w-src { flex:1.2; min-width:0; }
        .w-memo { flex:1.8; min-width:0; }
        .btn-x { background:none; border:none; color:var(--text-muted); font-size:15px; cursor:pointer; flex:none; }
        .btn-x:hover { color:var(--red); }
        .btn-plus { background:var(--surface); border:1px dashed #b9c4d2; color:var(--accent); font-size:12px; padding:7px 12px; border-radius:7px; cursor:pointer; width:100%; }
        .btn-plus:hover { border-color:var(--accent); }
        .footer { display:flex; justify-content:flex-end; gap:8px; margin-top:12px; }
        .btn { padding:8px 16px; border-radius:7px; font-size:12.5px; font-weight:600; cursor:pointer; border:1px solid transparent; }
        .btn-navy { background:var(--navy); color:#fff; font-weight:700; }
        .btn-navy:hover { filter:brightness(1.25); }
        .btn-line { background:var(--surface); border-color:#c9d2dc; color:#33404e; }
    </style>
</head>
<body>
<h2>{{ $order ? '주문 수정' : '주문 추가' }} <span style="font-size:11.5px; color:var(--text-muted); font-weight:400;">— 사무실 비품·간식 등 견적서와 무관한 주문 건</span></h2>

<div class="card">
    <h4>주문명</h4>
    <input class="field-input" id="orderTitle" placeholder="예: 8월 사무실 간식 주문 *" maxlength="200" value="{{ $order->title ?? '' }}">
</div>

<div class="card">
    <h4>주문 항목</h4>
    <div class="item-head">
        <span class="w-name">제품명 *</span><span class="w-qty">수량</span><span class="w-src">구매처</span><span class="w-memo">메모</span><span style="width:18px;"></span>
    </div>
    <div id="itemRows"></div>
    <button class="btn-plus" onclick="addRow()">+ 항목 추가</button>
</div>

<div class="footer">
    <button class="btn btn-line" onclick="window.close()">닫기</button>
    <button class="btn btn-navy" onclick="saveOrder(this)">저장</button>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const orderId = @json($order->id ?? null);
const initialItems = @json($order->items ?? []);
function _esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function addRow(it) {
    it = it || {};
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <input class="w-name" placeholder="제품명" maxlength="200" value="${_esc(it.name||'')}">
        <input class="w-qty" type="number" min="1" value="${parseInt(it.qty)||1}">
        <input class="w-src" placeholder="구매처 (예: 쿠팡)" maxlength="100" value="${_esc(it.purchase_source||'')}">
        <input class="w-memo" placeholder="메모" maxlength="500" value="${_esc(it.memo||'')}">
        <button class="btn-x" onclick="this.parentElement.remove()" title="항목 삭제">×</button>`;
    document.getElementById('itemRows').appendChild(div);
}
if (initialItems.length) { initialItems.forEach(addRow); } else { addRow(); }

async function saveOrder(btn) {
    const title = document.getElementById('orderTitle').value.trim();
    if (!title) return alert('주문명을 입력해주세요.');
    const items = [...document.querySelectorAll('#itemRows .item-row')].map(r => {
        const [name, qty, src, memo] = r.querySelectorAll('input');
        return { name: name.value.trim(), qty: Math.max(1, parseInt(qty.value)||1), purchase_source: src.value.trim(), memo: memo.value.trim() };
    }).filter(i => i.name);
    if (!items.length) return alert('항목을 1개 이상 입력해주세요.');

    btn.disabled = true;
    const res = await fetch(orderId ? `/api/inventory/office-orders/${orderId}` : '/api/inventory/office-orders', {
        method: orderId ? 'PATCH' : 'POST', headers: H, body: JSON.stringify({ title, items }),
    }).catch(() => null);
    btn.disabled = false;
    if (!res || !res.ok) {
        const e = res ? await res.json().catch(() => ({})) : {};
        return alert((e.message || '저장에 실패했습니다.') + (e.errors ? '\n' + Object.values(e.errors).flat().join('\n') : ''));
    }
    if (window.opener) { try { window.opener.loadOfficeOrders?.(); } catch(e) {} }
    alert('저장되었습니다.');
    window.close();
}
</script>
</body>
</html>
