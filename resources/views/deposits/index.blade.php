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
    .text-center { text-align:center !important; }
    .text-muted { color:var(--text-muted); font-size:12px; }
    .empty-row { text-align:center; color:var(--text-muted); padding:32px 0; font-size:13px; }
    .amt { font-weight:700; white-space:nowrap; }
    .sel-col { width:36px; text-align:center !important; }
    .sel-col input, .mob-sel { width:15px; height:15px; accent-color:var(--accent); cursor:pointer; }
    .btn-del { background:none; border:1px solid var(--red); color:var(--red); padding:7px 14px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer; white-space:nowrap; }
    .btn-del:hover { background:var(--red); color:#fff; }
    .bank-badge { display:inline-block; font-size:11px; font-weight:600; padding:2px 9px; border-radius:10px; background:var(--surface2); border:1px solid var(--border); color:var(--text-muted); white-space:nowrap; }
    /* 원문 보기 — 클릭 시 행 아래로 펼침 */
    .raw-btn { flex-shrink:0; background:none; border:1px solid var(--border); border-radius:8px; color:var(--text-muted); font-size:11px; padding:3px 9px; cursor:pointer; white-space:nowrap; }
    .raw-btn:hover { border-color:var(--accent); color:var(--accent); }
    .dep-raw { margin-top:9px; }
    .dep-raw pre { margin:0; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:10px 14px; font-size:12px; line-height:1.7; color:var(--text-muted); white-space:pre-wrap; word-break:break-all; font-family:inherit; font-weight:400; }
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

    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;">
        <div class="sum-line" id="depSummary" style="margin-bottom:0;"></div>
        <button class="btn-del" id="depDelBtn" style="display:none;" onclick="depDeleteSelected()">선택 삭제 (<span id="depDelCount">0</span>)</button>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead><tr>
                <th class="sel-col"><input type="checkbox" id="depSelAll" onchange="toggleDepSelAll(this.checked)"></th>
                <th style="width:150px;">입금 시간</th>
                <th style="width:110px;">은행</th>
                <th class="text-center" style="width:150px;">입금 금액</th>
                <th>입금자명</th>
            </tr></thead>
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
    depSel.clear();
    depPageIds = data.map(d => d.id);
    updateDepSelUI();
    if (!data.length) {
        tb.innerHTML = '<tr><td colspan="5" class="empty-row">입금 내역이 없습니다.</td></tr>';
        cards.innerHTML = '<div class="empty-row">입금 내역이 없습니다.</div>';
        return;
    }
    // 원문은 '원문 보기' 클릭 시 행 아래로 펼침
    tb.innerHTML = data.map(d => `<tr>
        <td class="sel-col"><input type="checkbox" class="dep-sel" ${depSel.has(d.id)?'checked':''} onchange="toggleDepSel(${d.id}, this.checked)"></td>
        <td class="text-muted">${fmtDt(d.received_at)}</td>
        <td>${d.bank ? `<span class="bank-badge">${_esc(d.bank)}</span>` : '<span class="text-muted">-</span>'}</td>
        <td class="text-center amt">${d.amount!=null ? fmt(d.amount)+'원' : '<span class="text-muted">-</span>'}</td>
        <td style="font-weight:600;">
            <div style="display:flex;align-items:center;gap:22px;">
                <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;">${_esc(d.depositor_name)||'<span class="text-muted">(파싱 실패)</span>'}</span>
                ${d.raw_text ? `<button type="button" class="raw-btn" id="depRawBtn${d.id}" onclick="toggleDepRaw(${d.id}, this)">원문 보기</button>` : ''}
            </div>
            ${d.raw_text ? `<div class="dep-raw" id="depRaw${d.id}" style="display:none;">
                <pre>${_esc(d.raw_text)}</pre>
                <div style="text-align:right;margin-top:6px;"><button type="button" class="raw-btn" onclick="closeDepRaw(${d.id})">닫기</button></div>
            </div>` : ''}
        </td>
    </tr>`).join('');
    cards.innerHTML = data.map(d => `<div class="mob-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div style="display:flex;align-items:center;gap:9px;min-width:0;">
                <input type="checkbox" class="mob-sel" ${depSel.has(d.id)?'checked':''} onchange="toggleDepSel(${d.id}, this.checked)">
                <div class="mob-card-title">${_esc(d.depositor_name)||'(파싱 실패)'}</div>
            </div>
            <div class="amt">${d.amount!=null ? fmt(d.amount)+'원' : '-'}</div>
        </div>
        <div class="mob-card-sub" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <span>${fmtDt(d.received_at)}${d.bank ? ' · '+_esc(d.bank) : ''}</span>
            ${d.raw_text ? `<button type="button" class="raw-btn" id="depRawBtnM${d.id}" onclick="toggleDepRaw(${d.id}, this)">원문 보기</button>` : ''}
        </div>
        ${d.raw_text ? `<div id="depRawM${d.id}" style="display:none;margin-top:8px;">
            <pre style="margin:0;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:12px;line-height:1.7;color:var(--text-muted);white-space:pre-wrap;word-break:break-all;font-family:inherit;">${_esc(d.raw_text)}</pre>
            <div style="text-align:right;margin-top:6px;"><button type="button" class="raw-btn" onclick="closeDepRaw(${d.id})">닫기</button></div>
        </div>` : ''}
    </div>`).join('');
}

// 원문 펼침/접힘 — 테이블 행(depRaw{id})과 모바일 카드(depRawM{id}) 공용
function toggleDepRaw(id, btn) {
    const el = document.getElementById('depRaw'+id) || null;
    const elM = document.getElementById('depRawM'+id) || null;
    const target = btn.closest('.mob-card') ? elM : el;
    if (!target) { return; }
    const open = target.style.display === 'none';
    target.style.display = open ? '' : 'none';
    btn.textContent = open ? '원문 접기' : '원문 보기';
}

// 펼쳐진 원문 하단 '닫기' — 데스크탑/모바일 패널과 토글 버튼 라벨을 함께 원복
function closeDepRaw(id) {
    ['depRaw'+id, 'depRawM'+id].forEach(x => { const el = document.getElementById(x); if (el) { el.style.display = 'none'; } });
    ['depRawBtn'+id, 'depRawBtnM'+id].forEach(x => { const b = document.getElementById(x); if (b) { b.textContent = '원문 보기'; } });
}

// === 선택 삭제 ===
const DEP_CSRF = '{{ csrf_token() }}';
const depSel = new Set();
let depPageIds = [];

function toggleDepSel(id, on) {
    on ? depSel.add(id) : depSel.delete(id);
    updateDepSelUI();
}

function toggleDepSelAll(on) {
    depPageIds.forEach(id => on ? depSel.add(id) : depSel.delete(id));
    document.querySelectorAll('.dep-sel, .mob-sel').forEach(cb => { cb.checked = on; });
    updateDepSelUI();
}

function updateDepSelUI() {
    const btn = document.getElementById('depDelBtn');
    btn.style.display = depSel.size ? '' : 'none';
    document.getElementById('depDelCount').textContent = depSel.size;
    const all = document.getElementById('depSelAll');
    all.checked = depPageIds.length > 0 && depPageIds.every(id => depSel.has(id));
}

async function depDeleteSelected() {
    if (!depSel.size) return;
    if (!confirm(`선택한 입금 내역 ${depSel.size}건을 삭제하시겠습니까?\n삭제 후에는 되돌릴 수 없습니다.`)) return;
    const res = await fetch('/api/bank-deposits', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': DEP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ ids: [...depSel] }),
    });
    if (!res.ok) {
        alert('삭제에 실패했습니다. 새로고침 후 다시 시도해주세요.');
        return;
    }
    depSel.clear();
    loadDeposits();
}

document.getElementById('depPerPage').value = String(depPerPage);
depQuickRange(30); // 기본: 최근 30일
</script>
@endsection
