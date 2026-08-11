@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '입금 내역 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:20px; font-weight:800; }
    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:200px; }
    .toolbar input[type="date"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:7px 10px; color:var(--text); font-size:13px; outline:none; }
    .toolbar input:focus { border-color:var(--accent); }
    .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:7px 14px; border-radius:8px; font-size:12.5px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .btn-outline.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .sum-line { font-size:13px; color:var(--text-muted); margin-bottom:10px; }
    .sum-line b { color:var(--text); font-size:14px; }
    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { text-align:left; padding:11px 12px; font-size:11.5px; color:var(--text-muted); border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { padding:11px 12px; font-size:13px; border-bottom:1px solid var(--border); }
    .data-table tr:last-child td { border-bottom:none; }
    .text-right { text-align:right; }
    .text-muted { color:var(--text-muted); font-size:12px; }
    .empty-row { text-align:center; color:var(--text-muted); padding:32px 0; font-size:13px; }
    .amt { font-weight:700; }
    .raw-toggle { cursor:help; }
    .pager { display:flex; gap:4px; align-items:center; justify-content:center; margin-top:12px; flex-wrap:wrap; }
    .pager-info { font-size:12px; color:var(--text-muted); margin-right:8px; }
    .pager-btn { min-width:30px; padding:6px 8px; border:1px solid var(--border); border-radius:6px; background:var(--surface2); color:var(--text-muted); font-size:12.5px; cursor:pointer; }
    .pager-btn:hover:not(:disabled) { color:var(--text); border-color:var(--accent); }
    .pager-btn.active { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:700; }
    .pager-btn:disabled { opacity:0.4; cursor:default; }
    .mob-cards { display:none; }
    .mob-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:10px; }
    .mob-card-title { font-weight:700; font-size:14px; }
    .mob-card-sub { color:var(--text-muted); font-size:11.5px; margin-top:4px; }
    [data-theme="light"] .pager-btn.active { color:#fff; }
    @media (max-width: 768px) {
        .page-wrap { padding:16px; }
        .toolbar { flex-direction:column; align-items:stretch; }
        .toolbar input[type="text"] { width:100%; }
        .date-range { display:flex; gap:6px; align-items:center; }
        .date-range input { flex:1; min-width:0; }
        .data-card { display:none; }
        .mob-cards { display:block; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">입금 내역</div>
    </div>

    <div class="toolbar">
        <span class="date-range">
            <input type="date" id="depFrom" onchange="depPage=1;loadDeposits()">
            <span class="text-muted">~</span>
            <input type="date" id="depTo" onchange="depPage=1;loadDeposits()">
        </span>
        <button class="btn-outline" onclick="depQuickRange(0)">오늘</button>
        <button class="btn-outline" onclick="depQuickRange(7)">7일</button>
        <button class="btn-outline" onclick="depQuickRange(30)">30일</button>
        <button class="btn-outline" onclick="depQuickRange(90)">3개월</button>
        <input type="text" id="depSearch" placeholder="입금자명/금액 검색" oninput="depPage=1;loadDeposits()">
        <select id="depPerPage" onchange="setDepPerPage(this.value)">
            <option value="10">10개씩</option>
            <option value="20">20개씩</option>
            <option value="50">50개씩</option>
            <option value="100">100개씩</option>
        </select>
    </div>

    <div class="sum-line" id="depSummary"></div>

    <div class="data-card">
        <table class="data-table">
            <thead><tr><th>입금 시간</th><th>입금자명</th><th class="text-right">입금 금액</th><th>원문</th></tr></thead>
            <tbody id="depBody"><tr><td colspan="5" class="empty-row">로딩 중...</td></tr></tbody>
        </table>
    </div>
    <div class="mob-cards" id="depCards"></div>
    <div class="pager" id="depPager"></div>
</div>

<script>
function _esc(s) { return s==null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
function fmt(n) { return n!=null ? Number(n).toLocaleString() : '-'; }
function fmtDt(d) { return d ? new Date(d).toLocaleString('ko-KR',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '-'; }

let depPage = 1;
let depPerPage = parseInt(localStorage.getItem('depPerPage'), 10) || 20;

function setDepPerPage(v) {
    depPerPage = parseInt(v, 10) || 20;
    localStorage.setItem('depPerPage', depPerPage);
    depPage = 1;
    loadDeposits();
}

function goDepPage(n) { depPage = n; loadDeposits(); }

// 오늘 기준 최근 N일로 기간 설정 (0 = 오늘 하루)
function depQuickRange(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days);
    const iso = d => d.toISOString().slice(0, 10);
    document.getElementById('depFrom').value = iso(from);
    document.getElementById('depTo').value = iso(to);
    depPage = 1;
    loadDeposits();
}

function renderDepPager(p) {
    const el = document.getElementById('depPager');
    if (!p.total) { el.innerHTML = ''; return; }
    const cur = p.current_page, last = p.last_page;
    let start = Math.max(1, cur - 3);
    const end = Math.min(last, start + 6);
    start = Math.max(1, end - 6);
    let html = `<span class="pager-info">총 ${p.total.toLocaleString()}건</span>`;
    html += `<button class="pager-btn" ${cur===1?'disabled':''} onclick="goDepPage(${cur-1})">‹</button>`;
    for (let i = start; i <= end; i++) html += `<button class="pager-btn ${i===cur?'active':''}" onclick="goDepPage(${i})">${i}</button>`;
    html += `<button class="pager-btn" ${cur===last?'disabled':''} onclick="goDepPage(${cur+1})">›</button>`;
    el.innerHTML = html;
}

async function loadDeposits() {
    const qs = new URLSearchParams();
    const from = document.getElementById('depFrom').value;
    const to = document.getElementById('depTo').value;
    const search = document.getElementById('depSearch').value.trim();
    if (from) qs.set('from', from);
    if (to) qs.set('to', to);
    if (search) qs.set('search', search);
    qs.set('per_page', depPerPage);
    qs.set('page', depPage);

    const res = await fetch('/api/bank-deposits?'+qs.toString());
    const payload = await res.json();
    const data = payload.data;
    if (!data.length && payload.total > 0 && depPage > 1) {
        depPage = payload.last_page;
        return loadDeposits();
    }
    renderDepPager(payload);
    document.getElementById('depSummary').innerHTML =
        `기간 내 <b>${payload.total.toLocaleString()}건</b> · 합계 <b>${fmt(payload.total_amount)}원</b>`;

    const tb = document.getElementById('depBody');
    const cards = document.getElementById('depCards');
    if (!data.length) {
        tb.innerHTML = '<tr><td colspan="4" class="empty-row">입금 내역이 없습니다.</td></tr>';
        cards.innerHTML = '<div class="empty-row">입금 내역이 없습니다.</div>';
        return;
    }
    tb.innerHTML = data.map(d => `<tr>
        <td class="text-muted">${fmtDt(d.received_at)}</td>
        <td style="font-weight:600;">${_esc(d.depositor_name)||'<span class="text-muted">(파싱 실패)</span>'}</td>
        <td class="text-right amt">${d.amount!=null ? fmt(d.amount)+'원' : '<span class="text-muted">-</span>'}</td>
        <td class="text-muted raw-toggle" title="${_esc(d.raw_text)}">원문 보기</td>
    </tr>`).join('');
    cards.innerHTML = data.map(d => `<div class="mob-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="mob-card-title">${_esc(d.depositor_name)||'(파싱 실패)'}</div>
            <div class="amt">${d.amount!=null ? fmt(d.amount)+'원' : '-'}</div>
        </div>
        <div class="mob-card-sub">${fmtDt(d.received_at)}</div>
        <div class="mob-card-sub" style="word-break:break-all;">${_esc(d.raw_text)}</div>
    </div>`).join('');
}

document.getElementById('depPerPage').value = String(depPerPage);
depQuickRange(30); // 기본: 최근 30일
</script>
@endsection
