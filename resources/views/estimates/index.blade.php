@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '견적서 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }
    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:260px; }
    .toolbar input:focus { border-color:var(--accent); }
    .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .data-table { width:100%; border-collapse:collapse; }
    /* 중간 해상도에서 셀 내용이 줄바꿈으로 깨지지 않도록 — 좁으면 카드가 가로 스크롤 */
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .empty-row { text-align:center; padding:40px !important; color:var(--text-muted); font-size:13px; }
    .text-muted { color:var(--text-muted); font-size:12px; }
    .text-right { text-align:right; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-created { background:#2a2010; color:var(--accent); }
    .badge-editing { background:#1a1a2a; color:#8ab4c8; }
    .badge-completed { background:#1a2a1a; color:#7ac87a; }
    .badge-issued { background:#241a2e; color:#b08ad4; }
    .badge-paid { background:#1a2a2a; color:#4ecdc4; }
    .badge-cancelled { background:#242424; color:#909090; text-decoration:line-through; }
    .badge-hold { background:#2a1a1a; color:#c87a7a; }

    .action-cell { display:flex; gap:5px; align-items:center; }
    .btn-act { padding:5px 11px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; border:none; transition:opacity 0.15s; white-space:nowrap; }
    .btn-act:hover { opacity:0.85; }
    .btn-act-edit { background:var(--surface2); border:1px solid var(--border); color:var(--text); }
    .btn-act-link { background:var(--surface); border:1px solid var(--border); color:var(--accent); }
    .btn-act-edit:hover { border-color:var(--accent); color:var(--accent); }
    .btn-act-print { background:var(--blue); color:#fff; }
    .btn-act-delete { background:var(--red); color:#fff; }
    .print-dropdown { position:relative; display:inline-block; }
    .print-dropdown-menu { display:none; position:absolute; right:0; top:calc(100% + 4px); background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px; z-index:20; min-width:130px; box-shadow:0 4px 16px rgba(0,0,0,0.4); }
    .print-dropdown-menu.show { display:block; }
    .print-dropdown-menu button { display:block; width:100%; text-align:left; background:none; border:none; color:var(--text); padding:8px 12px; font-size:12px; cursor:pointer; border-radius:4px; white-space:nowrap; }
    .print-dropdown-menu button:hover { background:var(--surface2); color:var(--accent); }
    [data-theme="light"] .btn-primary { color:#fff; }
    [data-theme="light"] .badge-created   { background:#f0ebe2; color:#8a6d30; }
    [data-theme="light"] .badge-editing   { background:#e0f0ff; color:#2e6a9a; }
    [data-theme="light"] .badge-completed { background:#e8f5e8; color:#248a38; }
    [data-theme="light"] .badge-issued    { background:#f0e8fa; color:#7a38b8; }
    [data-theme="light"] .badge-paid      { background:#e0f8f5; color:#0a8a70; }
    [data-theme="light"] .badge-cancelled { background:#ececec; color:#808080; }
    [data-theme="light"] .badge-hold      { background:#ffe8e8; color:#c03838; }
    @media (max-width: 768px) {
        .page-wrap { padding:16px; }
        .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
        .toolbar { flex-direction:column; align-items:stretch; }
        .toolbar input[type="text"] { width:100%; }

        /* 테이블 → 카드형 리스트 (가로 스크롤 제거) — 전역 min-width 무효화 */
        .data-card { overflow:visible; background:none; border:none; border-radius:0; }
        .data-table, .data-table tbody { display:block; min-width:0 !important; }
        .data-table thead { display:none; }
        .data-table tr { display:flex; flex-wrap:wrap; align-items:center; row-gap:5px;
            background:var(--surface); border:1px solid var(--border); border-radius:12px;
            padding:12px 14px; margin-bottom:10px; }
        .data-table tr:hover td { background:none; }
        .data-table td { display:block; padding:0 4px 0 0; border-bottom:none; white-space:normal; font-size:13px; }
        .data-table td:empty { display:none; }
        .data-table td.empty-row { flex-basis:100%; text-align:center; padding:16px 4px; }

        /* 견적서 목록: #번호 + 상태(우측) / 의뢰자·제목 / 금액·항목수 / 작성자·일시 / 버튼 */
        #estBody td:nth-child(1) { order:0; font-weight:600; }
        #estBody td:nth-child(5) { order:1; margin-left:auto; }
        #estBody td:nth-child(2) { order:2; flex-basis:100%; font-size:14.5px; font-weight:600; }
        #estBody td:nth-child(3) { order:3; font-size:16px; font-weight:800; color:var(--navy, var(--text)); }
        #estBody td:nth-child(4) { order:4; }
        #estBody tr::after { content:""; order:5; flex-basis:100%; } /* 금액 줄과 작성자 메타 줄 분리 */
        #estBody td:nth-child(6) { order:6; }
        #estBody td:nth-child(7) { order:7; }
        #estBody td:nth-child(8) { order:8; }
        #estBody td:nth-child(6), #estBody td:nth-child(7), #estBody td:nth-child(8) { font-size:11.5px; }
        #estBody td:nth-child(7)::before, #estBody td:nth-child(8)::before { content:"· "; color:var(--text-muted); }
        #estBody td:nth-child(9) { order:9; flex-basis:100%; margin-top:4px; }
        #estBody .action-cell { display:flex; gap:6px; flex-wrap:wrap; }
        #estBody .btn-act { padding:7px 12px; font-size:12.5px; }

        /* 프리셋: 제목 / 금액·품목수 / 작성자·수정일 / 버튼 */
        #presetBody td:nth-child(1) { order:0; flex-basis:100%; font-size:14.5px; }
        #presetBody td:nth-child(3) { order:1; font-size:16px; font-weight:800; color:var(--navy, var(--text)); }
        #presetBody td:nth-child(2) { order:2; }
        #presetBody tr::after { content:""; order:3; flex-basis:100%; }
        #presetBody td:nth-child(4), #presetBody td:nth-child(5) { order:4; font-size:11.5px; }
        #presetBody td:nth-child(5)::before { content:"· "; color:var(--text-muted); }
        #presetBody td:nth-child(6) { order:5; flex-basis:100%; margin-top:4px; }
        #presetBody .action-cell { display:flex; gap:6px; }
        #presetBody .btn-act { padding:7px 12px; font-size:12.5px; }
    }
    /* 탭 (견적서 목록 | 프리셋) */
    .est-tabs { display:flex; gap:6px; margin-bottom:16px; border-bottom:1px solid var(--border); }
    .est-tab { background:none; border:none; border-bottom:2px solid transparent; padding:9px 14px; font-size:13.5px; font-weight:600; color:var(--text-muted); cursor:pointer; margin-bottom:-1px; }
    .est-tab:hover { color:var(--text); }
    .est-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">견적서</div>
        <button class="btn-primary" id="headerActionBtn" onclick="createEstimate()">+ 견적서 생성</button>
    </div>

    <div class="est-tabs">
        <button class="est-tab active" id="tabBtnList" onclick="setEstTab('list')">견적서 목록</button>
        <button class="est-tab" id="tabBtnPresets" onclick="setEstTab('presets')">프리셋</button>
    </div>

    <div id="tabPresets" style="display:none;">
        <div class="data-card">
            <table class="data-table">
                <thead><tr><th>프리셋 제목</th><th>품목 수</th><th class="text-right">합계 금액</th><th>작성자</th><th>최근 수정</th><th></th></tr></thead>
                <tbody id="presetBody"><tr><td colspan="6" class="empty-row">로딩 중...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div id="tabList">
    <div class="toolbar">
        <input type="text" id="estSearch" placeholder="의뢰자명/번호 검색" oninput="loadEstimates()">
        <select id="estStatus" onchange="loadEstimates()">
            <option value="">전체 상태</option>
            <option value="created">생성</option>
            <option value="editing">수정 중</option>
            <option value="completed">작성 완료</option>
            <option value="issued">발행 완료</option>
            <option value="paid">결제 완료</option>
            <option value="cancelled">결제 취소</option>
            <option value="hold">보류 중</option>
        </select>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>번호</th>
                    <th>의뢰자</th>
                    <th class="text-right">견적금액</th>
                    <th>항목수</th>
                    <th>상태</th>
                    <th>작성자</th>
                    <th>작성일</th>
                    <th>최근 수정</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="estBody"><tr><td colspan="9" class="empty-row">로딩 중...</td></tr></tbody>
        </table>
    </div>
    </div>{{-- /tabList --}}
</div>

@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const stMap = {created:'생성', editing:'수정 중', completed:'작성 완료', issued:'발행 완료', paid:'결제 완료', cancelled:'결제 취소', hold:'보류 중'};

function fmt(n) { return n != null ? Number(n).toLocaleString() : '-'; }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('ko-KR') : '-'; }
function fmtTime(d) { return d ? new Date(d).toLocaleString('ko-KR',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '-'; }

async function loadEstimates() {
    const search = document.getElementById('estSearch').value;
    const status = document.getElementById('estStatus').value;
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const res = await fetch('/api/estimates?' + params);
    const data = await res.json();
    const tb = document.getElementById('estBody');

    if (!data.length) {
        tb.innerHTML = '<tr><td colspan="9" class="empty-row">견적서가 없습니다.</td></tr>';
        return;
    }

    // 출력(이미지/PDF) 파일명용 메타 — 'yyyy-mm-dd 닉네임(이름)' 형식에 사용
    window.__estMeta = {};
    data.forEach(e => { window.__estMeta[e.id] = { nickname: e.client_nickname || '', cname: e.client_name || '', no: e.display_no ?? e.id }; });

    tb.innerHTML = data.map(e => {
        const itemCount = (e.product_items||[]).length + (e.service_items||[]).length;
        return `<tr>
            <td class="text-muted">#${e.display_no ?? e.id}</td>
            <td>${e.client_nickname && e.client_name ? e.client_nickname+' / '+e.client_name : (e.client_nickname || e.client_name || '-')}${e.title ? `<div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;">${_esc(e.title)}</div>` : ''}</td>
            <td class="text-right" style="font-weight:600;">${fmt(e.total_amount)}원</td>
            <td class="text-muted">${itemCount}건</td>
            <td><span class="badge badge-${e.status}">${stMap[e.status]}</span></td>
            <td class="text-muted">${e.creator?.display_name || '-'}</td>
            <td class="text-muted">${fmtDate(e.created_at)}</td>
            <td class="text-muted">${fmtTime(e.updated_at)}</td>
            <td onclick="event.stopPropagation()">
                <div class="action-cell">
                    <button class="btn-act btn-act-edit" onclick="openEstimate(${e.id})">수정</button>
                    <button class="btn-act btn-act-link" onclick="copyEstimateLink(${e.id})" title="의뢰자용 견적서 링크 복사">링크</button>
                    <div class="print-dropdown">
                        <button class="btn-act btn-act-print" onclick="togglePrintMenu(event,${e.id})">출력 ▾</button>
                        <div class="print-dropdown-menu" id="printMenu-${e.id}">
                            <button onclick="exportEstimate(${e.id},'image')">이미지 저장</button>
                            <button onclick="exportEstimate(${e.id},'pdf')">PDF 저장</button>
                            <button onclick="window.open('/estimates/${e.id}/print','_blank')">인쇄 미리보기</button>
                        </div>
                    </div>
                    <button class="btn-act btn-act-delete" onclick="deleteEstimate(${e.id})">삭제</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function createEstimate() {
    const res = await fetch('/api/estimates', {method:'POST', headers:H});
    const est = await res.json();
    openEstimate(est.id);
    loadEstimates();
}

function openEstimate(id) {
    // 마지막으로 닫은 빌더 창 크기로 열기 (빌더 페이지가 localStorage에 저장) — 화면보다 크면 화면에 맞춤
    let w = 1200, h = 800;
    try {
        const s = JSON.parse(localStorage.getItem('estWinSize') || 'null');
        if (s && s.w >= 600 && s.h >= 400) {
            w = Math.min(s.w, screen.availWidth || s.w);
            h = Math.min(s.h, screen.availHeight || s.h);
        }
    } catch (e) {}
    window.open(`/estimates/${id}/edit`, `estimate_${id}`, `width=${w},height=${h},scrollbars=yes,resizable=yes`);
}

async function deleteEstimate(id) {
    if (!confirm('이 견적서를 삭제할까요?')) return;
    await fetch(`/api/estimates/${id}`, {method:'DELETE', headers:H});
    loadEstimates();
}

function togglePrintMenu(e, id) {
    e.stopPropagation();
    const menu = document.getElementById(`printMenu-${id}`);
    document.querySelectorAll('.print-dropdown-menu.show').forEach(m => { if(m!==menu) m.classList.remove('show'); });
    const willShow = !menu.classList.contains('show');
    menu.classList.toggle('show');
    if (willShow) {
        // 목록 카드의 overflow에 잘리지 않도록 뷰포트 기준(fixed)으로 띄운다
        const r = e.currentTarget.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = (r.bottom + 4) + 'px';
        menu.style.left = 'auto';
        menu.style.right = Math.max(8, window.innerWidth - r.right) + 'px';
    }
}
document.addEventListener('click', () => document.querySelectorAll('.print-dropdown-menu.show').forEach(m => m.classList.remove('show')));
// 스크롤하면 fixed 메뉴 위치가 어긋나므로 닫는다
window.addEventListener('scroll', () => document.querySelectorAll('.print-dropdown-menu.show').forEach(m => m.classList.remove('show')), true);

// 의뢰자용 공개 링크 복사 — 토큰이 없으면 서버에서 생성해 받아온다
async function copyEstimateLink(id) {
    const res = await fetch(`/api/estimates/${id}/public-url`, { headers: H });
    const d = await res.json().catch(() => ({}));
    if (!res.ok || !d.public_url) { alert(d.message || '링크를 가져오지 못했습니다.'); return; }
    try {
        await navigator.clipboard.writeText(d.public_url);
        alert('의뢰자용 견적서 링크가 복사되었습니다.\n카톡/문자로 전달하세요.\n\n' + d.public_url);
    } catch (e) {
        prompt('아래 링크를 복사하세요:', d.public_url);
    }
}

// 출력 파일명: 'yyyy-mm-dd 닉네임(이름)' — 캘린더 자동 첨부·인쇄 페이지 PNG 저장과 동일 규칙,
// 닉네임/이름이 없으면 목록에 표시되는 '견적서#번호' 폴백, 파일명 금지 문자는 제거
function estExportName(id) {
    const m = (window.__estMeta || {})[id] || {};
    const t = new Date();
    const ds = `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`;
    const nick = (m.nickname || '').trim();
    const nm = (m.cname || '').trim();
    let who = nick && nm ? `${nick}(${nm})` : (nick || nm || `견적서#${m.no ?? id}`);
    who = who.replace(/[\\/:*?"<>|]/g, '').trim();
    return `${ds} ${who}`;
}

async function exportEstimate(id, type) {
    document.querySelectorAll('.print-dropdown-menu.show').forEach(m => m.classList.remove('show'));
    const printUrl = `/estimates/${id}/print`;
    const w = window.open(printUrl, '_blank', 'width=860,height=900,scrollbars=yes');
    w.addEventListener('load', () => {
        setTimeout(async () => {
            try {
                const { default: html2canvas } = await import('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/+esm');
                const el = w.document.querySelector('.estimate-wrap');
                const bar = w.document.querySelector('.no-print-bar');
                if (bar) bar.style.display = 'none';
                if (el) el.style.marginTop = '0';

                const srcCanvas = await html2canvas(el, { scale:2, useCORS:true, backgroundColor:'#f2f2f3', windowWidth:1060 }); // 견적서 화면 폭(1020px) 기준 캡처

                // 여백 80px (scale:2 기준 40px * 2)
                const pad = 80;
                const canvas = document.createElement('canvas');
                canvas.width = srcCanvas.width + pad * 2;
                canvas.height = srcCanvas.height + pad * 2;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(srcCanvas, pad, pad);

                if (type === 'image') {
                    const link = document.createElement('a');
                    link.download = `${estExportName(id)}.png`;
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } else if (type === 'pdf') {
                    const { jsPDF } = await import('https://cdn.jsdelivr.net/npm/jspdf@2.5.2/+esm');
                    const imgData = canvas.toDataURL('image/png');
                    const pxW = canvas.width, pxH = canvas.height;
                    const pdfW = 210;
                    const pdfH = (pxH * pdfW) / pxW;
                    const pdf = new jsPDF({ unit:'mm', format:[pdfW, pdfH] });
                    pdf.addImage(imgData, 'PNG', 0, 0, pdfW, pdfH);
                    pdf.save(`${estExportName(id)}.pdf`);
                }
                w.close();
            } catch(err) {
                console.error(err);
                alert('출력 처리 중 오류가 발생했습니다. 인쇄 페이지에서 직접 저장해주세요.');
            }
        }, 500);
    });
}

// === 프리셋 탭 ===
const CAN_EST_EDIT = @json(Auth::user()->hasPermission('estimates.edit'));
let PRESETS = [];
function _esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function setEstTab(tab) {
    document.getElementById('tabList').style.display = tab === 'list' ? '' : 'none';
    document.getElementById('tabPresets').style.display = tab === 'presets' ? '' : 'none';
    document.getElementById('tabBtnList').classList.toggle('active', tab === 'list');
    document.getElementById('tabBtnPresets').classList.toggle('active', tab === 'presets');
    const btn = document.getElementById('headerActionBtn');
    if (tab === 'presets') {
        btn.textContent = '+ 프리셋 만들기';
        btn.onclick = () => openPresetModal(null);
        btn.style.display = CAN_EST_EDIT ? '' : 'none';
        loadPresets();
    } else {
        btn.textContent = '+ 견적서 생성';
        btn.onclick = createEstimate;
        btn.style.display = CAN_EST_EDIT ? '' : 'none';
    }
}

async function loadPresets() {
    const res = await fetch('/api/estimate-presets', { headers: { 'Accept': 'application/json' } });
    PRESETS = res.ok ? await res.json() : [];
    const tb = document.getElementById('presetBody');
    if (!PRESETS.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-row">저장된 프리셋이 없습니다. 우측 상단 [+ 프리셋 만들기] 또는 견적서 편집 화면의 [현재 품목을 프리셋으로 저장]으로 만들 수 있습니다.</td></tr>';
        return;
    }
    tb.innerHTML = PRESETS.map(p => `<tr>
        <td style="font-weight:600;">${_esc(p.title)}</td>
        <td class="text-muted">${p.item_count}개</td>
        <td class="text-right" style="font-weight:600;">${fmt(p.total)}원</td>
        <td class="text-muted">${_esc(p.creator || '-')}</td>
        <td class="text-muted">${p.updated_at}</td>
        <td>${CAN_EST_EDIT ? `<div class="action-cell">
            <button class="btn-act btn-act-edit" onclick="openPresetModal(${p.id})">수정</button>
            <button class="btn-act btn-act-delete" onclick="deletePreset(${p.id})">삭제</button>
        </div>` : ''}</td>
    </tr>`).join('');
}

// 프리셋 만들기/수정 — 견적서 편집과 동일한 레이아웃의 새 창
function openPresetModal(id) {
    const url = id ? `/estimate-presets/${id}/edit` : '/estimate-presets/create';
    window.open(url, id ? `preset_${id}` : 'preset_new', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

async function deletePreset(id) {
    if (!confirm('이 프리셋을 삭제할까요? 이미 작성된 견적서에는 영향이 없습니다.')) return;
    await fetch(`/api/estimate-presets/${id}`, { method: 'DELETE', headers: H });
    loadPresets();
}

loadEstimates();
</script>
@endpush
