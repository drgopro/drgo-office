@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '견적서 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1100px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }
    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

    .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:260px; }
    .toolbar input:focus { border-color:var(--accent); }
    .toolbar select { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:visible; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .empty-row { text-align:center; padding:40px !important; color:var(--text-muted); font-size:13px; }
    .text-muted { color:var(--text-muted); font-size:12px; }
    .text-right { text-align:right; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-created { background:#2a2010; color:var(--accent); }
    .badge-editing { background:#1a1a2a; color:#8ab4c8; }
    .badge-completed { background:#1a2a1a; color:#7ac87a; }
    .badge-paid { background:#1a2a2a; color:#4ecdc4; }
    .badge-hold { background:#2a1a1a; color:#c87a7a; }

    .action-cell { display:flex; gap:5px; align-items:center; }
    .btn-act { padding:5px 11px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; border:none; transition:opacity 0.15s; }
    .btn-act:hover { opacity:0.85; }
    .btn-act-edit { background:var(--surface2); border:1px solid var(--border); color:var(--text); }
    .btn-act-edit:hover { border-color:var(--accent); color:var(--accent); }
    .btn-act-print { background:var(--blue); color:#fff; }
    .btn-act-delete { background:var(--red); color:#fff; }
    .print-dropdown { position:relative; display:inline-block; }
    .print-dropdown-menu { display:none; position:absolute; right:0; top:calc(100% + 4px); background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px; z-index:20; min-width:130px; box-shadow:0 4px 16px rgba(0,0,0,0.4); }
    .print-dropdown-menu.show { display:block; }
    .print-dropdown-menu button { display:block; width:100%; text-align:left; background:none; border:none; color:var(--text); padding:8px 12px; font-size:12px; cursor:pointer; border-radius:4px; white-space:nowrap; }
    .print-dropdown-menu button:hover { background:var(--surface2); color:var(--accent); }
    [data-theme="light"] .btn-primary { color:#fff; }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">견적서</div>
        <button class="btn-primary" onclick="createEstimate()">+ 견적서 생성</button>
    </div>

    <div class="toolbar">
        <input type="text" id="estSearch" placeholder="의뢰자명/번호 검색" oninput="loadEstimates()">
        <select id="estStatus" onchange="loadEstimates()">
            <option value="">전체 상태</option>
            <option value="created">생성</option>
            <option value="editing">수정 중</option>
            <option value="completed">작성 완료</option>
            <option value="paid">결제 완료</option>
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
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const stMap = {created:'생성', editing:'수정 중', completed:'작성 완료', paid:'결제 완료', hold:'보류 중'};

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

    tb.innerHTML = data.map(e => {
        const itemCount = (e.product_items||[]).length + (e.service_items||[]).length;
        return `<tr>
            <td class="text-muted">#${e.id}</td>
            <td>${e.client_nickname && e.client_name ? e.client_nickname+' / '+e.client_name : (e.client_nickname || e.client_name || '-')}</td>
            <td class="text-right" style="font-weight:600;">${fmt(e.total_amount)}원</td>
            <td class="text-muted">${itemCount}건</td>
            <td><span class="badge badge-${e.status}">${stMap[e.status]}</span></td>
            <td class="text-muted">${e.creator?.display_name || '-'}</td>
            <td class="text-muted">${fmtDate(e.created_at)}</td>
            <td class="text-muted">${fmtTime(e.updated_at)}</td>
            <td onclick="event.stopPropagation()">
                <div class="action-cell">
                    <button class="btn-act btn-act-edit" onclick="openEstimate(${e.id})">수정</button>
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
    window.open(`/estimates/${id}/edit`, `estimate_${id}`, 'width=1200,height=800,scrollbars=yes,resizable=yes');
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
    menu.classList.toggle('show');
}
document.addEventListener('click', () => document.querySelectorAll('.print-dropdown-menu.show').forEach(m => m.classList.remove('show')));

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

                const srcCanvas = await html2canvas(el, { scale:2, useCORS:true, backgroundColor:'#fff', windowWidth:820 });

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
                    link.download = `견적서_${id}.png`;
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
                    pdf.save(`견적서_${id}.pdf`);
                }
                w.close();
            } catch(err) {
                console.error(err);
                alert('출력 처리 중 오류가 발생했습니다. 인쇄 페이지에서 직접 저장해주세요.');
            }
        }, 500);
    });
}

loadEstimates();
</script>
@endpush
