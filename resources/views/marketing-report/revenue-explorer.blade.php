@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '통계 탐색기 - 닥터고블린 오피스')

@push('styles')
<style>
    .rx-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .rx-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:6px; flex-wrap:wrap; }
    .rx-header h1 { font-size:20px; font-weight:800; margin:0; }
    .rx-sub { font-size:12px; color:var(--text-muted); margin-bottom:16px; }
    .rx-back { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); border-radius:9px; padding:8px 14px; font-size:12.5px; text-decoration:none; font-weight:600; }
    .rx-back:hover { border-color:var(--accent); color:var(--text); }

    .rx-controls { display:flex; align-items:center; gap:8px; flex-wrap:wrap; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:12px 16px; margin-bottom:14px; }
    .rx-controls label { font-size:11.5px; font-weight:700; color:var(--text-muted); }
    .rx-controls input[type=date], .rx-controls select, .rx-controls input[type=text] { border:1px solid var(--border); background:var(--surface2); color:var(--text); border-radius:8px; padding:7px 10px; font-size:12.5px; outline:none; }
    .rx-controls input:focus, .rx-controls select:focus { border-color:var(--accent); }
    .rx-go { border:none; background:var(--accent); color:var(--accent-text); border-radius:8px; padding:8px 16px; font-size:12.5px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .rx-go { color:#fff; }
    .rx-quick { display:flex; gap:6px; }
    .rx-quick button { background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:8px; padding:7px 11px; font-size:12px; cursor:pointer; }
    .rx-quick button:hover { border-color:var(--accent); color:var(--accent); }
    .rx-sep { width:1px; height:22px; background:var(--border); margin:0 4px; }
    .rx-csv { margin-left:auto; background:none; border:1px solid var(--border); color:var(--text-muted); border-radius:8px; padding:7px 13px; font-size:12px; cursor:pointer; }
    .rx-csv:hover { border-color:var(--accent); color:var(--accent); }

    .rx-summary { font-size:12.5px; color:var(--text-muted); margin-bottom:10px; }
    .rx-summary b { color:var(--accent); font-size:14px; }

    .rx-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:auto; max-height:calc(100vh - 320px); }
    .rx-table { width:100%; border-collapse:collapse; font-size:12.5px; }
    .rx-table th { position:sticky; top:0; background:var(--surface2); text-align:right; padding:9px 12px; font-size:11px; color:var(--text-muted); border-bottom:1px solid var(--border); white-space:nowrap; z-index:1; }
    .rx-table th:first-child { text-align:left; left:0; z-index:2; }
    .rx-table td { padding:8px 12px; border-bottom:1px solid var(--border); text-align:right; white-space:nowrap; }
    .rx-table td:first-child { text-align:left; font-weight:600; position:sticky; left:0; background:var(--surface); }
    .rx-table tr:last-child td { border-bottom:none; }
    .rx-table tr.total td { font-weight:800; border-top:2px solid var(--border); background:var(--surface2); position:sticky; bottom:0; }
    .rx-table td.zero { color:var(--text-muted); opacity:0.45; }
    .rx-empty { padding:48px 16px; text-align:center; color:var(--text-muted); font-size:13px; }

    @media (max-width:760px) { .rx-wrap { padding:14px; } .rx-csv { margin-left:0; } }
</style>
@endpush

@section('content')
<div class="rx-wrap">
    <div class="rx-header">
        <div>
            <h1>통계 탐색기</h1>
        </div>
        <a class="rx-back" href="{{ route('marketing-report') }}" onclick="event.preventDefault(); location.href=this.href + (location.search.includes('_tab') ? '?_tab=1' : '');">← 통계로</a>
    </div>
    <div class="rx-sub">통계 데이터를 원하는 차원으로 직접 조합해 보는 셀프서비스 페이지입니다. 행/열/값을 바꾸면 즉시 다시 집계됩니다. 매출은 통계 총 매출과 동일 기준(환불 음수 차감), 일정·작업은 엑셀 RAW와 동일 기준입니다.</div>

    <div class="rx-controls">
        <label>기간</label>
        <input type="date" id="rxFrom" value="{{ $from }}">
        <span style="color:var(--text-muted);">~</span>
        <input type="date" id="rxTo" value="{{ $to }}">
        <button class="rx-go" onclick="rxLoad()">조회</button>
        <div class="rx-quick">
            <button onclick="rxQuick('month')">이번달</button>
            <button onclick="rxQuick('prev')">지난달</button>
            <button onclick="rxQuick('3m')">3개월</button>
            <button onclick="rxQuick('year')">올해</button>
        </div>
        <div class="rx-sep"></div>
        <label>데이터</label>
        <select id="rxDataset" onchange="rxDatasetChange()">
            <option value="revenue">매출 (인식 원장)</option>
            <option value="schedules">일정·작업 (RAW)</option>
        </select>
        <label>행</label>
        <select id="rxRow" onchange="rxRender()"></select>
        <label>열</label>
        <select id="rxCol" onchange="rxRender()"></select>
        <label>값</label>
        <select id="rxVal" onchange="rxRender()"></select>
        <input type="text" id="rxSearch" placeholder="의뢰자/프로젝트 필터" style="width:150px;" oninput="rxSearchDebounced()">
        <button class="rx-csv" onclick="rxCsv()">CSV 다운로드</button>
    </div>

    <div class="rx-summary" id="rxSummary"></div>
    <div class="rx-card"><div id="rxTableWrap"><div class="rx-empty">불러오는 중...</div></div></div>
</div>
@endsection

@push('scripts')
<script>
let rxRows = [];
let rxTimer = null;

// 데이터셋별 차원/값 구성 — 행·열은 같은 목록을 공유 (열은 '없음' 추가)
const DATASETS = {
    revenue: {
        dims: { month:'월', date:'일자', scale:'의뢰자 유형', ptype:'프로젝트 유형', work:'작업 유형', kind:'기록 종류', client:'의뢰자', project:'프로젝트' },
        vals: { amount:'총액', service:'세팅비', product:'장비판매', other:'기타', count:'건수' },
        defaults: { row:'scale', col:'', val:'amount' },
        noColSplit: ['service','product','other','amount'], // 열 없음 → 금액 분해
    },
    schedules: {
        dims: { month:'월', date:'일자', dow:'요일', type:'유형', category:'카테고리', client_type:'의뢰자 유형', platform:'플랫폼', career:'경력', attrs:'속성', status:'상태', main:'주담당', region:'지역', internal:'사내/의뢰 구분', client:'의뢰자' },
        vals: { count:'건수', amount:'결제금액 합' },
        defaults: { row:'type', col:'', val:'count' },
        noColSplit: null, // 열 없음 → 값 1열
    },
};

function rxDataset(){ return document.getElementById('rxDataset').value; }

function rxFillSelects(keep){
    const ds = DATASETS[rxDataset()];
    const row = document.getElementById('rxRow'), col = document.getElementById('rxCol'), val = document.getElementById('rxVal');
    const prev = keep ? { row:row.value, col:col.value, val:val.value } : ds.defaults;
    row.innerHTML = Object.entries(ds.dims).map(([k,l]) => `<option value="${k}">${l}</option>`).join('');
    col.innerHTML = `<option value="">${ds.noColSplit ? '(없음 — 금액 분해)' : '(없음)'}</option>`
        + Object.entries(ds.dims).filter(([k]) => !['client','project','date'].includes(k)).map(([k,l]) => `<option value="${k}">${l}</option>`).join('');
    val.innerHTML = Object.entries(ds.vals).map(([k,l]) => `<option value="${k}">${l}</option>`).join('');
    row.value = ds.dims[prev.row] !== undefined ? prev.row : ds.defaults.row;
    col.value = prev.col && ds.dims[prev.col] !== undefined ? prev.col : '';
    val.value = ds.vals[prev.val] !== undefined ? prev.val : ds.defaults.val;
}

function rxDatasetChange(){ rxFillSelects(false); rxLoad(); }

function _e(s){ const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
const fmt = n => Number(n||0).toLocaleString('ko-KR');

async function rxLoad(){
    const from = document.getElementById('rxFrom').value;
    const to = document.getElementById('rxTo').value;
    if (!from || !to) return;
    document.getElementById('rxTableWrap').innerHTML = '<div class="rx-empty">불러오는 중...</div>';
    try {
        const res = await fetch(`/api/marketing-report/revenue-explorer-data?from=${from}&to=${to}&dataset=${rxDataset()}`, { headers:{ 'Accept':'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        rxRows = (await res.json()).rows || [];
    } catch (e) {
        document.getElementById('rxTableWrap').innerHTML = '<div class="rx-empty">데이터를 불러오지 못했습니다. 새로고침 해주세요.</div>';
        return;
    }
    rxRender();
}

function rxQuick(kind){
    const now = new Date();
    const d2s = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    let from, to;
    if (kind === 'month') { from = new Date(now.getFullYear(), now.getMonth(), 1); to = now; }
    else if (kind === 'prev') { from = new Date(now.getFullYear(), now.getMonth()-1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); }
    else if (kind === '3m') { from = new Date(now.getFullYear(), now.getMonth()-2, 1); to = now; }
    else { from = new Date(now.getFullYear(), 0, 1); to = now; }
    document.getElementById('rxFrom').value = d2s(from);
    document.getElementById('rxTo').value = d2s(to);
    rxLoad();
}

function rxSearchDebounced(){ clearTimeout(rxTimer); rxTimer = setTimeout(rxRender, 250); }

function rxFiltered(){
    const q = document.getElementById('rxSearch').value.trim().toLowerCase();
    if (!q) return rxRows;
    return rxRows.filter(r => (r.client||'').toLowerCase().includes(q) || (r.project||'').toLowerCase().includes(q));
}

function rxRender(){
    const rows = rxFiltered();
    const rowDim = document.getElementById('rxRow').value;
    const colDim = document.getElementById('rxCol').value;
    const valKey = document.getElementById('rxVal').value;
    const wrap = document.getElementById('rxTableWrap');

    const ds = DATASETS[rxDataset()];
    const total = rows.reduce((a,r)=>a+(r.amount||0), 0);
    document.getElementById('rxSummary').innerHTML = rxDataset() === 'revenue'
        ? `기록 ${fmt(rows.length)}건 · 총 매출 <b>${fmt(total)}원</b> <span style="font-size:11px;">(세팅비 ${fmt(rows.reduce((a,r)=>a+r.service,0))}원 · 장비판매 ${fmt(rows.reduce((a,r)=>a+r.product,0))}원)</span>`
        : `일정 ${fmt(rows.length)}건 · 기재 결제금액 합 <b>${fmt(total)}원</b>`;

    if (!rows.length) { wrap.innerHTML = '<div class="rx-empty">기간 내 매출 기록이 없습니다.</div>'; return; }

    const val = r => valKey === 'count' ? 1 : (r[valKey] || 0);
    const unit = valKey === 'count' ? '건' : '원';

    // 열 차원이 없으면: 매출은 금액 분해(세팅비/장비판매/기타/총액), 일정은 선택한 값 1열
    let colKeys, cellOf;
    if (!colDim) {
        colKeys = (ds.noColSplit && valKey !== 'count') ? ds.noColSplit : [valKey];
        cellOf = (r, c) => c === 'count' ? 1 : (r[c] || 0);
    } else {
        colKeys = [...new Set(rows.map(r => r[colDim]))].sort();
        cellOf = (r, c) => r[colDim] === c ? val(r) : 0;
    }
    const colLabel = c => !colDim ? ({service:'세팅비', product:'장비판매', other:'기타', amount: rxDataset()==='revenue'?'총액':'결제금액', count:'건수'}[c] || c) : c;

    // 피벗 집계
    const pivot = {};
    for (const r of rows) {
        const k = r[rowDim] ?? '미지정';
        pivot[k] ??= Object.fromEntries(colKeys.map(c => [c, 0]));
        for (const c of colKeys) pivot[k][c] += cellOf(r, c);
    }
    const rowKeys = Object.keys(pivot).sort((a,b) => {
        if (rowDim === 'month' || rowDim === 'date') return a.localeCompare(b);
        const sum = k => colKeys.reduce((s,c)=>s+pivot[k][c],0);
        return sum(b) - sum(a); // 금액 큰 순
    });
    const colTotals = Object.fromEntries(colKeys.map(c => [c, rowKeys.reduce((s,k)=>s+pivot[k][c],0)]));

    // 열 차원이 없을 때(금액 분해/단일 값)는 '총액' 열이 이미 합계라 별도 합계 열을 붙이지 않는다
    const showRowSum = !!colDim;
    let html = `<table class="rx-table"><thead><tr><th>${_e(ds.dims[rowDim] || rowDim)}</th>`;
    html += colKeys.map(c => `<th>${_e(colLabel(c))}</th>`).join('');
    html += (showRowSum ? `<th>합계</th>` : '') + `</tr></thead><tbody>`;
    for (const k of rowKeys) {
        const rowSum = colKeys.reduce((s,c)=>s+pivot[k][c],0);
        html += `<tr><td>${_e(k)}</td>`
            + colKeys.map(c => { const v = pivot[k][c]; return `<td class="${v===0?'zero':''}">${v===0?'-':fmt(v)}</td>`; }).join('')
            + (showRowSum ? `<td style="font-weight:700;">${fmt(rowSum)}${unit==='건'?'건':''}</td>` : '') + `</tr>`;
    }
    const grand = colKeys.reduce((s,c)=>s+colTotals[c],0);
    html += `<tr class="total"><td>합계</td>`
        + colKeys.map(c => `<td>${fmt(colTotals[c])}</td>`).join('')
        + (showRowSum ? `<td>${fmt(grand)}${unit==='건'?'건':''}</td>` : '') + `</tr></tbody></table>`;
    wrap.innerHTML = html;
}

function rxCsv(){
    const table = document.querySelector('.rx-table');
    if (!table) return;
    const rows = [...table.querySelectorAll('tr')].map(tr =>
        [...tr.children].map(td => '"' + td.textContent.trim().replace(/"/g,'""') + '"').join(','));
    const blob = new Blob(['﻿' + rows.join('\n')], { type:'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `통계탐색기_${rxDataset()}_${document.getElementById('rxFrom').value}_${document.getElementById('rxTo').value}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

rxFillSelects(false);
rxLoad();
</script>
@endpush
