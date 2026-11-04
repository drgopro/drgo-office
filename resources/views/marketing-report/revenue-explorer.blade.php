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

    /* 커스텀 뷰 */
    .rx-mode { display:inline-flex; gap:4px; background:var(--surface2); border:1px solid var(--border); border-radius:999px; padding:3px; }
    .rx-mode button { border:none; background:none; color:var(--text-muted); font-size:12px; font-weight:700; padding:5px 13px; border-radius:999px; cursor:pointer; }
    .rx-mode button.on { background:var(--accent); color:var(--accent-text); }
    [data-theme="light"] .rx-mode button.on { color:#fff; }
    #rxCustomFrame { width:100%; height:calc(100vh - 320px); min-height:420px; border:none; display:block; background:#fff; border-radius:12px; }
    .rxc-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; display:none; align-items:flex-start; justify-content:center; padding:4vh 12px; overflow-y:auto; }
    .rxc-overlay.open { display:flex; }
    .rxc-modal { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:860px; max-width:100%; padding:22px; }
    .rxc-modal h3 { margin:0 0 10px; font-size:16px; }
    .rxc-guide { font-size:12px; color:var(--text-muted); line-height:1.7; background:var(--surface2); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
    .rxc-guide b { color:var(--text); }
    .rxc-modal textarea { width:100%; min-height:320px; box-sizing:border-box; background:var(--surface2); border:1px solid var(--border); border-radius:10px; color:var(--text); font-family:ui-monospace, Menlo, monospace; font-size:12px; padding:12px; outline:none; resize:vertical; }
    .rxc-modal textarea:focus { border-color:var(--accent); }
    .rxc-actions { display:flex; gap:8px; margin-top:12px; align-items:center; }

    @media (max-width:760px) { .rx-wrap { padding:14px; } .rx-csv { margin-left:0; } }
</style>
@endpush

@section('content')
<div class="rx-wrap">
    <div class="rx-header">
        <div>
            <h1>통계 탐색기</h1>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <div class="rx-mode" id="rxMode">
                <button class="on" id="rxModeBasic" onclick="rxSetMode('basic')">기본 피벗</button>
                <button id="rxModeCustom" onclick="rxSetMode('custom')">커스텀 뷰</button>
            </div>
            @if($canEditCustom)
                <button class="rx-csv" style="margin-left:0;" onclick="rxcOpen()">커스텀 편집</button>
            @endif
            <a class="rx-back" href="{{ route('marketing-report') }}" onclick="event.preventDefault(); location.href=this.href + (location.search.includes('_tab') ? '?_tab=1' : '');">← 통계로</a>
        </div>
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

    <div id="rxBasicArea">
        <div class="rx-summary" id="rxSummary"></div>
        <div class="rx-card"><div id="rxTableWrap"><div class="rx-empty">불러오는 중...</div></div></div>
    </div>
    <div id="rxCustomArea" style="display:none;">
        <div class="rx-card" style="max-height:none;"><iframe id="rxCustomFrame" sandbox="allow-scripts" title="커스텀 통계 뷰"></iframe></div>
    </div>
</div>

{{-- 커스텀 뷰 편집 — GPT/클로드로 고쳐 쓴 HTML을 저장 (sandbox iframe에서만 실행, 오피스 데이터/세션 접근 불가) --}}
@if($canEditCustom)
<div class="rxc-overlay" id="rxcOverlay" onclick="if(event.target===this) rxcClose()">
    <div class="rxc-modal">
        <h3>커스텀 뷰 편집</h3>
        <div class="rxc-guide">
            <b>사용법</b>: ① [현재 코드 복사] 또는 [기본 템플릿 넣기] → ② ChatGPT/Claude에 붙여넣고 "<b>월별 매출을 막대 차트로 보여줘</b>"처럼 원하는 변경을 요청 → ③ 받은 HTML 전체를 아래에 붙여넣고 저장.<br>
            커스텀 코드는 <b>격리된 창(sandbox)</b>에서만 실행되고 오피스 데이터·로그인에 접근할 수 없습니다. 데이터는 오피스가 <code>message</code> 이벤트로 넣어줍니다 — 템플릿의 수신 부분을 유지하라고 AI에게 함께 요청하세요. 코드가 망가져도 저장을 비우면 원래대로 돌아갑니다.
        </div>
        <textarea id="rxcHtml" spellcheck="false" placeholder="여기에 커스텀 HTML 전체를 붙여넣으세요"></textarea>
        <div class="rxc-actions">
            <button class="rx-csv" style="margin-left:0;" onclick="rxcInsertTemplate()">기본 템플릿 넣기</button>
            <button class="rx-csv" style="margin-left:0;" onclick="rxcCopy()">현재 코드 복사</button>
            <span id="rxcStatus" style="font-size:12px; color:var(--text-muted);"></span>
            <span style="flex:1;"></span>
            <button class="rx-csv" style="margin-left:0;" onclick="rxcClose()">닫기</button>
            <button class="rx-go" onclick="rxcSave()">저장</button>
        </div>
    </div>
</div>
@endif
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
    if (rxMode === 'custom') rxCustomSend(); // 기간/데이터셋 변경 시 커스텀 뷰에도 최신 데이터 주입
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

// ── 커스텀 뷰 — sandbox iframe + postMessage 데이터 주입 ──
let rxCustomHtml = @json($customHtml);
let rxMode = 'basic';

function rxSetMode(m){
    if (m === 'custom' && !rxCustomHtml.trim()) {
        alert('저장된 커스텀 뷰가 없습니다.' + (@json($canEditCustom) ? ' [커스텀 편집]에서 템플릿으로 시작해보세요.' : ' 관리자에게 요청하세요.'));
        return;
    }
    rxMode = m;
    document.getElementById('rxModeBasic').classList.toggle('on', m === 'basic');
    document.getElementById('rxModeCustom').classList.toggle('on', m === 'custom');
    document.getElementById('rxBasicArea').style.display = m === 'basic' ? '' : 'none';
    document.getElementById('rxCustomArea').style.display = m === 'custom' ? '' : 'none';
    if (m === 'custom') rxCustomBoot();
}

function rxCustomBoot(){
    const frame = document.getElementById('rxCustomFrame');
    frame.srcdoc = rxCustomHtml; // sandbox="allow-scripts" — 쿠키/세션/부모 접근 불가
    frame.onload = () => rxCustomSend();
}

function rxCustomSend(){
    const frame = document.getElementById('rxCustomFrame');
    frame?.contentWindow?.postMessage({
        type: 'drgo-stats-data',
        dataset: rxDataset(),
        from: document.getElementById('rxFrom').value,
        to: document.getElementById('rxTo').value,
        rows: rxRows,
    }, '*');
}

const RXC_TEMPLATE = @json($customTemplate); // 서버 보관 원문 — JSON 이스케이프로 주입 (HTML 파서 충돌 방지)

function rxcOpen(){
    document.getElementById('rxcHtml').value = rxCustomHtml;
    document.getElementById('rxcStatus').textContent = '';
    document.getElementById('rxcOverlay').classList.add('open');
}
function rxcClose(){ document.getElementById('rxcOverlay').classList.remove('open'); }
function rxcInsertTemplate(){ document.getElementById('rxcHtml').value = RXC_TEMPLATE; }
async function rxcCopy(){
    try { await navigator.clipboard.writeText(document.getElementById('rxcHtml').value || rxCustomHtml || RXC_TEMPLATE);
        document.getElementById('rxcStatus').textContent = '복사됨 — AI에 붙여넣어 수정 요청하세요'; } catch (e) {}
}
async function rxcSave(){
    const html = document.getElementById('rxcHtml').value;
    const res = await fetch('/api/marketing-report/explorer-custom', {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}' },
        body: JSON.stringify({ html }),
    });
    if (!res.ok) { document.getElementById('rxcStatus').textContent = '저장 실패'; return; }
    rxCustomHtml = html;
    document.getElementById('rxcStatus').textContent = '저장 완료';
    if (rxMode === 'custom') rxCustomBoot();
}

rxFillSelects(false);
rxLoad();
</script>
@endpush
