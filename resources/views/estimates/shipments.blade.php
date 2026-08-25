<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
@include('partials.ajax-fetch-header')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>주문/배송 운송장 - 견적서 #{{ $estimate->display_no }}</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        :root { --bg:#eef0f3; --surface:#fff; --surface2:#f2f4f7; --border:#dfe3e9; --text:#1d1f24; --text-muted:#6b7684; --accent:#2e6cb5; --red:#c03838; --green:#248a38; --navy:#1d2d3d; --slate:#416180; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; padding:18px; font-size:13px; }
        input, button, select { font-family:inherit; }
        h2 { font-size:16px; font-weight:800; color:var(--navy); margin-bottom:14px; }
        .card { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
        .card h4 { font-size:12px; color:var(--slate); font-weight:700; letter-spacing:0.08em; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        .ship-row { display:flex; align-items:center; gap:10px; padding:9px 4px; border-bottom:1px solid var(--border); font-size:12.5px; }
        .ship-row:last-child { border-bottom:none; }
        .ship-carrier { font-weight:700; white-space:nowrap; }
        .ship-no { font-family:monospace; font-size:12.5px; }
        .ship-no-link { color:var(--accent); text-decoration:none; }
        .ship-no-link:hover { text-decoration:underline; }
        .badge { font-size:10.5px; padding:2px 8px; border-radius:4px; font-weight:700; white-space:nowrap; }
        .badge-delivered { background:#e8f5e8; color:#248a38; }
        .badge-moving { background:#e0f0ff; color:#2e6a9a; }
        .badge-unknown { background:#ececec; color:#808080; }
        .ship-event { color:var(--text-muted); font-size:11.5px; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .btn { padding:7px 14px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid transparent; }
        .btn-navy { background:var(--navy); color:#fff; font-weight:700; }
        .btn-navy:hover { filter:brightness(1.25); }
        .btn-line { background:var(--surface); border-color:#c9d2dc; color:#33404e; }
        .btn-line:hover { border-color:var(--slate); color:var(--slate); }
        .btn-x { background:none; border:none; color:var(--text-muted); font-size:15px; cursor:pointer; }
        .btn-x:hover { color:var(--red); }
        .add-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
        .add-row select, .add-row input { background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:8px 10px; font-size:12.5px; color:var(--text); outline:none; }
        .add-row select { width:150px; }
        .add-row input { flex:1; }
        .add-row input:focus, .add-row select:focus { border-color:var(--accent); }
        .btn-plus { background:var(--surface); border:1px dashed #b9c4d2; color:var(--accent); font-size:12px; padding:7px 12px; border-radius:7px; cursor:pointer; width:100%; }
        .btn-plus:hover { border-color:var(--accent); }
        .footer { display:flex; justify-content:flex-end; gap:8px; margin-top:12px; }
        .empty { text-align:center; color:var(--text-muted); padding:16px; font-size:12px; }
    </style>
</head>
<body>
    <h2>주문/배송 운송장 — 견적서 #{{ $estimate->display_no }}</h2>

    <div class="card">
        <h4>등록된 운송장
            <button class="btn btn-line" style="margin-left:auto; padding:4px 10px; font-size:11px;" onclick="refreshShipments()">배송상태 새로고침</button>
        </h4>
        <div id="shipList"><div class="empty">불러오는 중...</div></div>
    </div>

    <div class="card">
        <h4>운송장 추가</h4>
        <div id="addRows"></div>
        <button class="btn-plus" onclick="addRow()">+ 운송장 입력칸 추가</button>
        <div class="footer">
            <button class="btn btn-line" onclick="window.close()">닫기</button>
            <button class="btn btn-navy" onclick="submitAll()">등록</button>
        </div>
    </div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const estId = {{ $estimate->id }};
let carriers = {};

function _esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function statusBadge(s) {
    if (s === 'delivered') return '<span class="badge badge-delivered">배송완료</span>';
    if (!s || s === 'unknown') return '<span class="badge badge-unknown">조회 전</span>';
    return `<span class="badge badge-moving">${_esc(s === 'in_transit' ? '배송 중' : s)}</span>`;
}

function renderList(data) {
    carriers = data.carriers || carriers;
    const el = document.getElementById('shipList');
    if (!data.shipments.length) {
        el.innerHTML = '<div class="empty">등록된 운송장이 없습니다.</div>';
        return;
    }
    el.innerHTML = data.shipments.map(s => `
        <div class="ship-row">
            <span class="ship-carrier">${_esc(s.carrier_label)}</span>
            ${s.tracking_url
                ? `<a class="ship-no ship-no-link" href="${_esc(s.tracking_url)}" target="_blank" rel="noopener" title="택배사 조회 페이지 열기 (송장번호 자동 입력)">${_esc(s.tracking_no)} ↗</a>`
                : `<span class="ship-no">${_esc(s.tracking_no)}</span>`}
            ${statusBadge(s.status)}
            <span class="ship-event">${_esc([s.last_event, s.last_location].filter(Boolean).join(' · '))}${s.delivered_at ? ' · ' + _esc(s.delivered_at) : ''}</span>
            <button class="btn-x" title="삭제" onclick="removeShipment(${s.id})">×</button>
        </div>`).join('');
}

function addRow() {
    const wrap = document.getElementById('addRows');
    const row = document.createElement('div');
    row.className = 'add-row';
    row.innerHTML = `
        <select class="rowCarrier">${Object.entries(carriers).map(([k, v]) => `<option value="${k}">${_esc(v)}</option>`).join('')}</select>
        <input class="rowNo" placeholder="운송장 번호 (숫자)" onkeydown="if(event.key==='Enter')submitAll()">
        <button class="btn-x" onclick="this.parentElement.remove()">×</button>`;
    wrap.appendChild(row);
    row.querySelector('.rowNo').focus();
}

async function submitAll() {
    const rows = [...document.querySelectorAll('#addRows .add-row')]
        .map(r => ({ carrier: r.querySelector('.rowCarrier').value, tracking_no: r.querySelector('.rowNo').value.trim(), el: r }))
        .filter(r => r.tracking_no);
    if (!rows.length) return alert('운송장 번호를 입력해주세요.');

    const errors = [];
    for (const row of rows) {
        const res = await fetch(`/api/estimates/${estId}/shipments`, {
            method: 'POST', headers: H,
            body: JSON.stringify({ carrier: row.carrier, tracking_no: row.tracking_no }),
        });
        const d = await res.json().catch(() => ({}));
        if (res.ok) { row.el.remove(); renderList(d); }
        else {
            const detail = d.errors ? Object.values(d.errors).flat().join(' ') : '';
            errors.push(`${row.tracking_no}: ${d.message || ''} ${detail}`.trim());
        }
    }
    if (errors.length) alert('일부 운송장 등록 실패:\n' + errors.join('\n'));
    else if (!document.querySelectorAll('#addRows .add-row').length) addRow();
}

async function refreshShipments() {
    const res = await fetch(`/api/estimates/${estId}/shipments/refresh`, { method: 'POST', headers: H });
    if (res.ok) renderList(await res.json());
}

async function removeShipment(id) {
    if (!confirm('이 운송장을 삭제할까요?')) return;
    const res = await fetch(`/api/estimate-shipments/${id}`, { method: 'DELETE', headers: H });
    if (res.ok) renderList(await res.json());
}

(async function init() {
    const res = await fetch(`/api/estimates/${estId}/shipments`);
    renderList(await res.json());
    addRow();
})();
</script>
</body>
</html>
