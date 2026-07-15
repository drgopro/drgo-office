@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '매출 상세 - 닥터고블린 오피스')

@push('styles')
<style>
    .rv-wrap { padding:24px; max-width:960px; margin:0 auto; }
    .rv-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
    .rv-header h1 { font-size:20px; font-weight:800; }
    .rv-header .sub { font-size:12.5px; color:var(--text-muted); margin-top:5px; }
    .rv-back { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); border-radius:9px; padding:8px 14px; font-size:12.5px; text-decoration:none; font-weight:600; }
    .rv-back:hover { border-color:var(--accent); color:var(--text); text-decoration:none; }

    /* 필터 칩 */
    .rv-filters { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px 16px 8px; margin-bottom:14px; }
    .rv-filter-row { display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-bottom:9px; }
    .rv-filter-label { font-size:11.5px; font-weight:700; color:var(--text-muted); width:34px; flex-shrink:0; }
    .rv-chip { border:1px solid var(--border); background:var(--surface); color:var(--text-muted); border-radius:15px; padding:4px 13px; font-size:12.5px; cursor:pointer; }
    .rv-chip:hover { border-color:var(--accent); color:var(--text); }
    .rv-chip.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:700; }

    /* 목록 */
    .rv-list { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .rv-item { border-bottom:1px solid var(--surface2); }
    .rv-item:last-child { border-bottom:none; }
    .rv-row { display:flex; align-items:center; gap:11px; padding:13px 16px; cursor:pointer; }
    .rv-row:hover { background:var(--surface2); }
    .rv-caret { flex-shrink:0; width:16px; color:var(--text-muted); font-size:11px; transition:transform .15s; }
    .rv-item.open .rv-caret { transform:rotate(90deg); }
    .rv-name { font-size:14px; font-weight:700; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rv-sub { font-size:12px; color:var(--text-muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rv-amt { margin-left:auto; flex-shrink:0; font-size:14.5px; font-weight:800; color:var(--accent); }
    .rv-amt.minus { color:#c0392b; }
    .rv-open { flex-shrink:0; border:1px solid var(--border); background:var(--surface); color:var(--text-muted); border-radius:8px; width:29px; height:29px; cursor:pointer; font-size:14px; }
    .rv-open:hover { border-color:var(--accent); color:var(--accent); }
    /* 개별 결제 건 (펼침) */
    .rv-pays { display:none; padding:2px 16px 12px 43px; background:color-mix(in srgb, var(--surface2) 45%, transparent); }
    .rv-item.open .rv-pays { display:block; }
    .rv-pay { display:flex; align-items:center; gap:9px; padding:7px 0; font-size:13px; border-bottom:1px dashed var(--border); }
    .rv-pay:last-child { border-bottom:none; }
    .rv-pay-label { flex-shrink:0; font-weight:700; color:var(--accent); }
    .rv-pay-label.minus, .rv-pay-amt.minus { color:#c0392b; }
    .rv-pay-info { color:var(--text-muted); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rv-pay-amt { margin-left:auto; flex-shrink:0; font-weight:700; }
    .rv-empty { color:var(--text-muted); font-size:13px; text-align:center; padding:46px 0; }

    @media (max-width:640px) {
        .rv-wrap { padding:16px 12px; }
        .rv-sub { white-space:normal; }
    }
</style>
@endpush

@section('content')
<div class="rv-wrap">
    <div class="rv-header">
        <div>
            <h1>💰 매출 상세</h1>
            <div class="sub" id="rvSub">불러오는 중…</div>
        </div>
        <a class="rv-back" href="{{ route('marketing-report') }}?from={{ $from }}&to={{ $to }}">← 통계로 돌아가기</a>
    </div>

    <div class="rv-filters" id="rvFilters" style="display:none;">
        <div class="rv-filter-row"><span class="rv-filter-label">유형</span><span id="rvTypeChips"></span></div>
        <div class="rv-filter-row"><span class="rv-filter-label">작업</span><span id="rvWorkChips"></span></div>
    </div>

    <div class="rv-list" id="rvList"><div class="rv-empty">불러오는 중…</div></div>
</div>
@endsection

@push('scripts')
<script>
const RV_FROM = @json($from);
const RV_TO = @json($to);
let RV_DATA = null, RV_TYPE = '', RV_WORK = '';

function rvEsc(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }

async function rvLoad(){
    try{
        const res=await fetch(`/api/marketing-report/revenue-projects?from=${RV_FROM}&to=${RV_TO}`,{headers:{'Accept':'application/json'}});
        if(!res.ok) throw new Error();
        RV_DATA=await res.json();
        rvRender();
    }catch(e){
        document.getElementById('rvList').innerHTML='<div class="rv-empty">목록을 불러오지 못했습니다.</div>';
        document.getElementById('rvSub').textContent=`${RV_FROM} ~ ${RV_TO}`;
    }
}

function rvChip(label,active,onclick){
    return `<button type="button" class="rv-chip ${active?'on':''}" onclick="${onclick}">${rvEsc(label)}</button> `;
}

function rvRender(){
    const list=document.getElementById('rvList');
    const all=RV_DATA.projects;

    if(!all.length){
        document.getElementById('rvSub').textContent=`${RV_DATA.from} ~ ${RV_DATA.to} · 결제 내역 없음`;
        list.innerHTML='<div class="rv-empty">기간 내 결제가 발생한 프로젝트가 없습니다.</div>';
        return;
    }

    // 필터 칩 — 데이터에 존재하는 유형/작업 유형만
    document.getElementById('rvFilters').style.display='';
    const types=[...new Set(all.map(p=>p.type))];
    const works=[...new Set(all.map(p=>p.work))];
    const q=s=>s.replaceAll("\\","\\\\").replaceAll("'","\\'");
    document.getElementById('rvTypeChips').innerHTML=rvChip('전체',!RV_TYPE,"rvSetType('')")+types.map(t=>rvChip(t,RV_TYPE===t,`rvSetType('${q(t)}')`)).join('');
    document.getElementById('rvWorkChips').innerHTML=rvChip('전체',!RV_WORK,"rvSetWork('')")+works.map(w=>rvChip(w,RV_WORK===w,`rvSetWork('${q(w)}')`)).join('');

    const rows=all.filter(p=>(!RV_TYPE||p.type===RV_TYPE)&&(!RV_WORK||p.work===RV_WORK));
    const sum=rows.reduce((a,p)=>a+p.amount,0);
    document.getElementById('rvSub').textContent=`${RV_DATA.from} ~ ${RV_DATA.to} · ${rows.length}건 · 합계 ${sum.toLocaleString()}원`;

    if(!rows.length){ list.innerHTML='<div class="rv-empty">조건에 맞는 프로젝트가 없습니다.</div>'; return; }

    list.innerHTML=rows.map((p,i)=>`<div class="rv-item" id="rvItem${i}">
        <div class="rv-row" onclick="rvToggle(${i})">
            <span class="rv-caret">▶</span>
            <span style="min-width:0;">
                <div class="rv-name">${rvEsc(p.name)}</div>
                <div class="rv-sub">${rvEsc(p.client)} · ${rvEsc(p.type)} · ${rvEsc(p.work)} · 결제 ${p.pay_count}건${p.last_paid_at?` · 최근 ${p.last_paid_at}`:''}</div>
            </span>
            <span class="rv-amt ${p.amount<0?'minus':''}">${Number(p.amount).toLocaleString()}원</span>
            ${p.project_id?`<button type="button" class="rv-open" onclick="event.stopPropagation(); rvOpenProject(${p.project_id})" title="프로젝트 열기">↗</button>`:''}
        </div>
        <div class="rv-pays">
            ${(p.payments||[]).map(pay=>`<div class="rv-pay">
                <span class="rv-pay-label ${pay.amount<0?'minus':''}">${rvEsc(pay.label)}</span>
                <span class="rv-pay-info">${pay.paid_at||''}${pay.method?` · ${rvEsc(pay.method)}`:''}${pay.memo?` · ${rvEsc(pay.memo)}`:''}</span>
                <span class="rv-pay-amt ${pay.amount<0?'minus':''}">${Number(pay.amount).toLocaleString()}원</span>
            </div>`).join('')||'<div class="rv-pay" style="color:var(--text-muted);">개별 결제 내역 없음</div>'}
        </div>
    </div>`).join('');
}

function rvSetType(t){ RV_TYPE=t; rvRender(); }
function rvSetWork(w){ RV_WORK=w; rvRender(); }
function rvToggle(i){ document.getElementById('rvItem'+i)?.classList.toggle('open'); }
function rvOpenProject(id){
    if(window.parent && window.parent.drgoTabs){ window.parent.drgoTabs.openNav('projects','/projects/'+id); }
    else{ location.href='/projects/'+id; }
}

rvLoad();
</script>
@endpush
