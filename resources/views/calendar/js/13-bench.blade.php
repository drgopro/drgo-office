{{-- 캘린더 대기함 — 보류·재방문·날짜 미정 일정 패널 (기본 접힘). 계산·차단 규칙은 서버(BenchCalculator)가 하고 화면은 결과만 그린다 --}}
// 시스템은 날짜·시간·담당자를 추천하지 않는다 — '이 날로'는 같은 지역 일정의 날짜와 담당자만 채운다
const BENCH_KINDS={hold:['보류','#b4610e','⏸'],revisit:['재방문','#7a3fc4','↻'],undated:['날짜 미정','#4d5967','·']};
const BENCH_OPTS={confirmed:'확정',suggest:'제안',hope:'희망',target:'목표'};
let benchOpen=false, benchData={items:[],total:0,urgent:0,today:todayStr()};
let benchSel=null, benchDetail=null, benchForm=null, benchFilter='all', benchPop=null, benchDragId=null, benchUndo=null, benchToastTimer=null;
let benchTraces=localStorage.getItem('cal_bench_traces')!=='0';

const benchMd=ds=>{ const d=new Date(ds+'T00:00:00'); return `${d.getMonth()+1}/${d.getDate()}`; };
const benchMdw=ds=>benchMd(ds)+'('+DAYS_KO[new Date(ds+'T00:00:00').getDay()]+')';
const benchKind=b=>BENCH_KINDS[b.kind]||BENCH_KINDS.undated;
function benchItem(){ return benchSel===null?null:((benchDetail&&benchDetail.id===benchSel)?benchDetail:benchData.items.find(b=>b.id===benchSel)||null); }
// 달력에 기간을 그릴 수 있는 상태인지 — 조건 대기·준비 확인 필요·준비일 미정이면 표시하지 않는다 (명세 7.2)
function benchActive(b){ return !!b&&!b.blocked&&!['ask','nodate'].includes(b.prep.state); }
// 종일 일정 라벨 — 대기함에서 시간 없이 배치한 일정은 '시간 미정'
function benchAllDayLabel(ev){ return ev&&ev.bench_placed_at?'시간 미정':'종일'; }
// 대기함으로 보낼 수 있는 일정인지 화면에서 먼저 거른다 (최종 판단은 서버)
function benchCanSend(ev){
    if(!canEditCalendar||!ev||ev.parent_id||ev.repeat_group||ev.source_type||ev.is_private) return false;
    const label=(CS_CATS[ev.color]&&CS_CATS[ev.color].label)||'';
    return label.includes('방문')||label.includes('원격')||['gold','teal'].includes(ev.color);
}

async function benchApi(method,url,body){
    try{
        const res=await fetch(url,{method,headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:body?JSON.stringify(body):undefined});
        let data={}; try{ data=await res.json(); }catch(e){}
        return {ok:res.ok,status:res.status,data};
    }catch(e){ return {ok:false,status:0,data:{message:'네트워크 오류로 처리하지 못했습니다.'}}; }
}
function benchErr(r){ const errs=r.data&&r.data.errors; return (errs&&Object.values(errs)[0]&&Object.values(errs)[0][0])||(r.data&&r.data.message)||'처리하지 못했습니다.'; }

// ── 불러오기·그리기 ─────────────────────────────────────────────
async function benchLoad(){
    if(!canEditCalendar) return;
    const r=await benchApi('GET','/api/bench');
    if(!r.ok) return;
    benchData=r.data;
    if(benchSel!==null&&!benchData.items.some(b=>b.id===benchSel)){ benchSel=null; benchDetail=null; benchForm=null; }
    if(benchSel!==null){ const d=await benchApi('GET','/api/bench/'+benchSel); benchDetail=d.ok?d.data:null; }
    benchRender();
    if(currentView==='month') renderView();
}
function benchRender(){
    const btn=document.getElementById('benchBtn');
    if(btn){
        btn.setAttribute('aria-expanded',benchOpen);
        btn.classList.toggle('on',benchOpen);
        btn.innerHTML=`대기함 <span class="bench-cnt">${benchData.total}</span>${benchData.urgent?`<span class="bench-cnt urgent">긴급 ${benchData.urgent}</span>`:''}`;
    }
    const panel=document.getElementById('calBench');
    if(!panel) return;
    panel.hidden=!benchOpen;
    document.body.classList.toggle('bench-open',benchOpen); // 일정 추가(+) 버튼이 패널을 가리지 않게 옮김
    if(!benchOpen) return;
    const counts={all:benchData.items.length};
    Object.keys(BENCH_KINDS).forEach(k=>counts[k]=benchData.items.filter(b=>b.kind===k).length);
    const list=benchData.items.filter(b=>benchFilter==='all'||b.kind===benchFilter);
    panel.innerHTML=`<div class="bench-head">
        <h3><span>대기함 <small>${benchData.total}건</small></span><span class="bench-acts"><button type="button" class="bench-mini" onclick="benchNewOpen()">+ 날짜 미정</button><button type="button" class="bench-mini" onclick="benchHelp(true)" aria-label="대기함 사용법">? 사용법</button><button type="button" class="bench-mini bench-close" onclick="benchToggle(false)" aria-label="대기함 접기">✕ 닫기</button></span></h3>
        <div class="bench-tabs">${[['all','전체'],...Object.entries(BENCH_KINDS).map(([k,v])=>[k,v[0]])].map(([k,l])=>`<button type="button" class="bench-chip" aria-pressed="${benchFilter===k}" onclick="benchFilter='${k}';benchRender()">${l} ${counts[k]}</button>`).join('')}</div>
        <p>카드를 누르면 배치할 수 있는 기간과 같은 지역 일정이 달력에 표시됩니다. 날짜는 직접 고르거나 카드를 날짜 칸으로 끌어 놓으세요.</p>
    </div>
    <div class="bench-list">${list.length?list.map(benchCardHtml).join(''):'<p class="bench-empty">대기 중인 일정이 없습니다. 일정을 눌러 대기함으로 보낼 수 있습니다.</p>'}</div>`;
}
function benchPrepFlag(b){
    const s=b.prep.state; if(s==='none') return '';
    const cls=(b.prep.unconfirmed.length&&s==='stock')?'f-prod':({stock:'f-ok',ok:'f-ok',late:'f-prod',ask:'f-prod',nodate:'f-block'})[s];
    return `<span class="bench-flag ${cls}">${_esc(b.prep.label)}</span>`;
}
function benchCardHtml(item){
    const isSel=benchSel===item.id, b=isSel?(benchItem()||item):item, k=benchKind(b);
    const flagCls=b.urgent?'f-urgent':(b.level>=8?'f-block':(b.level===1.5?'f-prod':'f-wait'));
    let h=`<div class="bench-card${b.blocked?' blocked':''}" style="--k:${k[1]}" ${b.blocked?'':'draggable="true"'} data-bench="${b.id}" aria-selected="${isSel}" tabindex="0" onclick="benchPick(${b.id},event)" onkeydown="if(event.target===this&&(event.key==='Enter'||event.key===' ')){event.preventDefault();benchPick(${b.id},event);}">
        <header><h4>${_esc(b.title)}</h4><span class="bench-tag">${k[2]} ${k[0]}</span></header>
        <div class="bench-meta">${b.flag?`<span class="bench-flag ${flagCls}">${_esc(b.flag)}</span>`:''}${benchPrepFlag(b)}<span>${_esc(b.window_label)}</span>${b.area?`<span>${_esc(b.area)}</span>`:''}${b.assignee_names.length?`<span>담당 <b>${_esc(b.assignee_names.join(', '))}</b></span>`:''}<span>대기 ${b.wait_days}일째</span></div>`;
    if(isSel) h+=benchDetailHtml(b);
    return h+'</div>';
}
function benchDetailHtml(b){
    let h=`<div class="bench-why">${_esc(b.reason)}${b.origin_date?`<br><span class="bench-muted">${b.kind==='revisit'?'방문일':'원래 날짜'} ${benchMdw(b.origin_date)}</span>`:''}</div>`;
    h+=benchPrepHtml(b);
    if(b.blocked) return h+`<div class="bench-acts"><span class="bench-flag f-block">${_esc(b.block_condition||'')}</span><button type="button" class="bench-mini pri" onclick="benchResolve(${b.id})">조건 해결됨</button><button type="button" class="bench-mini link" onclick="benchRemove(${b.id})">대기함에서 빼기</button></div>`;
    h+=`<div class="bench-sub">배치 가능 기간</div><div class="bench-why win">${_esc(b.range_label)}${b.impossible?'<br><b class="bench-red">기한 안에 배치할 수 있는 날이 없습니다. 기한을 고객과 다시 정하거나 제품을 먼저 준비하세요.</b>':''}</div>`;
    if(b.area){
        const sa=b.same_area||[];
        h+=`<div class="bench-sub">같은 지역(${_esc(b.area)}) 일정</div><div class="bench-recs">${sa.length?sa.map(s=>`<div class="bench-rec${s.status==='ok'?'':' off'}"><b>${_esc(s.date_label)}</b><span>${s.start_time?s.start_time+(s.end_time?'–'+s.end_time:'')+' ':''}${_esc(s.title)}<br><span class="bench-muted">담당 ${_esc(s.assignee_names.join(', ')||'미정')}${s.status_label?' · '+s.status_label:''}</span></span>${s.status==='ok'?`<button type="button" class="bench-mini" onclick="benchPlan(${b.id},'${s.date}',[${s.assignee_ids.join(',')}])">이 날로</button>`:'<span></span>'}</div>`).join(''):'<p class="bench-empty sm">가능 기간 근처에 같은 지역 일정이 없습니다.</p>'}</div>`;
    }
    if(benchForm&&benchForm.id===b.id) return h+benchFormHtml(b);
    return h+`<div class="bench-acts"><button type="button" class="bench-mini pri" onclick="benchPlan(${b.id},'${b.earliest}',[${b.assignee_ids.join(',')}])">날짜 선택해서 배치</button><button type="button" class="bench-mini link" onclick="benchRemove(${b.id})">대기함에서 빼기</button></div>`;
}
function benchPrepHtml(b){
    const p=b.prep;
    if(p.state==='none') return b.kind!=='undated'?'<div class="bench-prod none">견적서가 연결되지 않아 필요 제품을 알 수 없습니다. 제품이 필요하면 견적서를 연결해 주세요.</div>':'';
    const ST={stock:['재고 있음','var(--bench-green)'],ok:['주문','var(--bench-hold)'],late:['주문','var(--bench-red)'],ask:['주문 소요일 없음','var(--bench-hold)'],nodate:['준비 불가','var(--bench-red)']};
    const rows=p.rows.map(r=>{
        const a=(b.answers||{})[r.product_id], call=(fn,v)=>`${fn}(${b.id},${r.product_id},'${v}')`;
        let st;
        if(r.status==='stock'){
            st=`<span style="color:${r.confirmed?'var(--bench-green)':'var(--bench-hold)'};font-weight:700">${r.confirmed?'창고 확인함':'전산 재고 · 창고 확인 전'}</span><div class="bench-acts"><button type="button" class="bench-mini${r.confirmed?' pri':''}" onclick="${call('benchChk','in_warehouse')}">창고에 있음</button><button type="button" class="bench-mini" onclick="${call('benchChk','missing')}">실제 없음</button></div>`;
        }else{
            const txt=r.source==='staff'?(a&&a.ok?'3일 내 가능 (담당 입력)':(a&&a.expected_date?`준비 예상 ${benchMd(a.expected_date)} (담당 입력)`:'준비 불가 (예상일 입력 필요)')):ST[r.status][0]+(r.days?` ${r.days}일`:'');
            st=`<span style="color:${ST[r.status][1]};font-weight:700">${r.missing?'실제 없음 · ':''}${txt}</span>${r.missing?`<button type="button" class="bench-mini link" onclick="${call('benchChk','')}">전산 재고로 되돌리기</button>`:''}`;
        }
        if(r.status==='ask'||r.source==='staff'||r.status==='nodate'){
            const no=a&&!a.ok;
            st+=`<div class="bench-acts"><button type="button" class="bench-mini${a&&a.ok?' pri':''}" onclick="${call('benchAns','ok')}">3일 내 가능</button><button type="button" class="bench-mini${no?' pri':''}" onclick="${call('benchAns','no')}">불가</button>${no?`<input type="date" value="${a.expected_date||''}" min="${shiftDate(benchData.today,1)}" aria-label="준비 예상일" onchange="benchAns(${b.id},${r.product_id},'no',this.value)">`:''}</div>`;
        }
        return `<div class="bench-pr"><div class="bench-pn"><span>${_esc(r.name)}</span><small>필요 ${r.qty} · 가용 ${r.missing?`<s>${r.free}</s> 0`:r.free}</small></div><div class="bench-ps">${st}</div></div>`;
    }).join('');
    const head=({
        stock:p.unconfirmed.length?'전산 재고로 모두 충당됩니다. 창고에 실제로 있는지 확인해 주세요.':'창고 확인까지 끝났습니다. 날짜 제한이 없습니다.',
        ok:`3일 내 준비 가능. ${benchMdw(b.prep_start)}부터 배치할 수 있습니다.`,
        late:`3일 내 준비 불가. ${p.ready_on?benchMdw(p.ready_on)+' 준비 예상, ':''}${benchMdw(b.prep_start)}부터 배치할 수 있습니다.`,
        ask:'주문 소요일이 없는 제품이 있습니다. 3일 안에 준비할 수 있는지 골라 주세요.',
        nodate:'준비 불가 제품의 준비 예상일을 넣으면 배치할 수 있는 날짜를 계산합니다.',
    })[p.state];
    return `<div class="bench-prod"><div>${b.estimates.map(e=>`<b>견적서 #${_esc(e.display_no)}</b>${e.title?' · '+_esc(e.title):''}`).join('<br>')}</div><div>${rows||'<span class="bench-muted">준비할 제품이 없습니다.</span>'}</div><div style="font-weight:700">${head}</div></div>`;
}
function benchFormHtml(b){
    const f=benchForm, c=f.check||{error:null,alerts:[],notices:[]}, hasAlerts=!c.error&&c.alerts.length>0;
    const locked=!!c.error||(hasAlerts&&!f.ovr)||f.busyLoading;
    const who=assignees.filter(a=>a.is_active!==false||f.assignee_ids.includes(a.id));
    const names=who.filter(a=>f.assignee_ids.includes(a.id)).map(a=>a.name).join(', ');
    return `<div class="bench-form">
        <div class="row"><label>날짜<input type="date" value="${f.date}" min="${shiftDate(benchData.today,1)}" onchange="benchFormSet('date',this.value)"></label><label>시작 시간 (선택)<input type="time" value="${f.time}" step="1800" onchange="benchForm.time=this.value;benchRender()"></label></div>
        <label>담당<div class="bench-seg">${who.map(a=>`<button type="button" class="bench-mini${f.assignee_ids.includes(a.id)?' pri':''}" aria-pressed="${f.assignee_ids.includes(a.id)}" onclick="benchFormWho(${a.id})">${_esc(a.name)}</button>`).join('')||'<span class="bench-muted">등록된 담당자가 없습니다.</span>'}</div></label>
        <label>확정 상태<select onchange="benchForm.opt=this.value">${Object.entries(BENCH_OPTS).map(([k,l])=>`<option value="${k}"${f.opt===k?' selected':''}>${l}</option>`).join('')}</select></label>
        ${f.assignee_ids.length?`<div class="bench-busy"><b>${benchMdw(f.date)} ${_esc(names)} 일정 ${f.busy.length}건</b>${f.busy.map(e=>`<br>${e.is_all_day?benchAllDayLabel(e):(e.start_time||'').substring(0,5)+(e.end_time?'–'+e.end_time.substring(0,5):'')} ${_esc(e.title||'')}`).join('')}<br><span class="bench-muted">작업 시간은 직접 판단해 주세요.</span></div>`:''}
        ${c.error?`<div class="bench-flag f-urgent wrap">${_esc(c.error)}</div>`:''}
        ${hasAlerts?`<div class="bench-alert"><b>배치 전 확인할 알림 ${c.alerts.length}건</b>${c.alerts.map(x=>`<div>· ${_esc(x)}</div>`).join('')}
            ${f.ovr?`<label>무시하는 사유 (필수, 일정 이력에 남음)<input id="benchOvrWhy" value="${_esc(f.ovrWhy)}" oninput="benchForm.ovrWhy=this.value" placeholder="예: 창고 실재고 확인함, 거래처 내일 입고 확정"></label><button type="button" class="bench-mini link" onclick="benchForm.ovr=false;benchRender()">알림 무시 취소</button>`
                :`<div class="bench-acts"><button type="button" class="bench-mini" onclick="benchForm.ovr=true;benchRender()">알림 무시하고 진행</button><span class="bench-muted">또는 위 제품 표에서 확인을 마치세요.</span></div>`}</div>`:''}
        ${!c.error&&c.notices.length?`<div class="bench-flag f-notice wrap">${_esc(c.notices.join(' '))}</div>`:''}
        <div class="bench-acts"><button type="button" class="bench-mini ${f.ovr&&hasAlerts?'danger':'pri'}" onclick="benchPlace()" ${locked?'disabled':''}>${f.ovr&&hasAlerts?'알림 무시하고 ':''}${benchMdw(f.date)} ${f.time||'시간 미정'}으로 배치</button><button type="button" class="bench-mini" onclick="benchForm=null;benchRender()">취소</button></div>
    </div>`;
}

// ── 동작 ───────────────────────────────────────────────────────
function benchToggle(force){
    benchOpen=typeof force==='boolean'?force:!benchOpen;
    if(!benchOpen){ benchSel=null; benchDetail=null; benchForm=null; }
    benchRender();
    if(currentView==='month') renderView(); // 패널 폭만큼 달력 다시 계산 + 선택 해제 시 기간 표시 지움
}
async function benchPick(id,e){
    if(e&&e.target.closest('button,input,select,textarea,label')) return;
    if(benchSel===id) return;
    benchSel=id; benchDetail=null; benchForm=null; benchOpen=true;
    benchRender();
    if(currentView==='month') renderView(); // 기간·기한 표시는 목록 값으로 먼저 그리고, 같은 지역 표시는 상세가 오면 덧그린다
    const d=await benchApi('GET','/api/bench/'+id);
    if(benchSel!==id) return;
    benchDetail=d.ok?d.data:null;
    benchRender();
    const b=benchItem();
    if(benchActive(b)) benchGoto(b.earliest); else if(currentView==='month') renderView();
    const card=document.querySelector(`.bench-card[data-bench="${id}"]`);
    if(card) card.scrollIntoView({block:'nearest'});
}
// 달력을 그 날짜가 보이는 달로 — 이미 보이면 다시 그리기만
function benchGoto(ds){
    if(currentView!=='month'){ return; }
    if(document.querySelector(`.day-cell[data-date="${ds}"]`)){ renderView(); return; }
    const d=new Date(ds+'T00:00:00');
    currentYear=d.getFullYear(); currentMonth=d.getMonth(); currentWeekStart=getWeekStart(d); currentDay=d; multiWeekStart=getWeekStart(d);
    renderView(); loadEvents();
}
async function benchMutate(method,url,body){
    const r=await benchApi(method,url,body);
    if(!r.ok){ benchToast(benchErr(r)); return false; }
    await benchLoad();
    if(benchForm) await benchCheck();
    return true;
}
function benchChk(id,productId,result){ benchMutate('PUT',`/api/bench/${id}/stock-checks/${productId}`,{result:result||null}); }
function benchAns(id,productId,answer,date){ benchMutate('PUT',`/api/bench/${id}/prep-answers/${productId}`,{answer:answer||null,expected_date:date||null}); }
async function benchResolve(id){ if(await benchMutate('POST',`/api/bench/${id}/resolve-block`)) benchToast('조건을 해결했습니다. 이제 배치할 수 있습니다.'); }
async function benchRemove(id){
    const b=benchData.items.find(x=>x.id===id); if(!b) return;
    const r=await benchApi('DELETE','/api/bench/'+id);
    if(!r.ok){ benchToast(benchErr(r)); return; }
    benchSel=null; benchDetail=null; benchForm=null;
    await benchLoad();
    benchToast(`대기함에서 뺐습니다: ${b.title}`,{id,from:'removed'});
}
function benchPlan(id,date,assigneeIds){
    const b=benchData.items.find(x=>x.id===id); if(!b) return;
    benchForm={id,date,time:'',assignee_ids:[...(assigneeIds||[])],opt:b.kind==='hold'?'suggest':'confirmed',ovr:false,ovrWhy:'',check:null,busy:[],busyLoading:true};
    benchRender();
    benchCheck().then(()=>{ const f=document.querySelector('.bench-form'); if(f) f.scrollIntoView({block:'nearest',behavior:'smooth'}); });
}
function benchFormSet(key,value){ benchForm[key]=value; benchForm.busyLoading=true; benchRender(); benchCheck(); }
function benchFormWho(aid){
    const ids=benchForm.assignee_ids, i=ids.indexOf(aid);
    if(i>=0) ids.splice(i,1); else ids.push(aid);
    benchFormSet('assignee_ids',ids);
}
// 서버와 같은 규칙으로 미리 검사 + 그날 그 담당자의 일정 (겹침 판정은 하지 않는다)
async function benchCheck(){
    const f=benchForm; if(!f) return;
    const qs=`date=${f.date}`+f.assignee_ids.map(a=>`&assignee_ids[]=${a}`).join('');
    const [c,day]=await Promise.all([benchApi('GET',`/api/bench/${f.id}/check?${qs}`), f.assignee_ids.length?benchApi('GET',`/api/events?start=${f.date}&end=${f.date}`):Promise.resolve({ok:true,data:[]})]);
    if(benchForm!==f) return;
    f.check=c.ok?c.data:{error:benchErr(c),alerts:[],notices:[]};
    f.busy=day.ok&&Array.isArray(day.data)?sortByTime(day.data.filter(e=>!e.parent_id&&(e.assignees||[]).some(a=>f.assignee_ids.includes(a.id))),f.date):[];
    if(!f.check.alerts.length) f.ovr=false;
    f.busyLoading=false;
    benchRender();
}
async function benchPlace(){
    const f=benchForm; if(!f||f.sending) return;
    const b=benchData.items.find(x=>x.id===f.id);
    const hasAlerts=f.check&&f.check.alerts.length>0;
    if(hasAlerts&&!f.ovrWhy.trim()){ benchToast('알림을 무시하는 사유를 적어 주세요.'); const i=document.getElementById('benchOvrWhy'); if(i) i.focus(); return; }
    f.sending=true; // 더블클릭으로 두 번 배치되지 않게
    const r=await benchApi('POST',`/api/bench/${f.id}/place`,{date:f.date,time:f.time||null,assignee_ids:f.assignee_ids,sched_opt:f.opt,override_reason:hasAlerts?f.ovrWhy.trim():null});
    f.sending=false;
    if(!r.ok){ benchToast(benchErr(r)); await benchCheck(); return; } // 그사이 재고·확인 상태가 바뀌었으면 알림을 다시 보여 준다
    benchSel=null; benchDetail=null; benchForm=null;
    await benchLoad();
    benchGoto(f.date); loadEvents();
    if(window.innerWidth<=768&&typeof selectMobileDay==='function') selectMobileDay(f.date);
    benchToast(`배치했습니다${r.data.overridden?' (알림 무시)':''}: ${benchMdw(f.date)} ${b?b.title:''}`,{id:f.id,from:'placed'});
}
async function benchUndoRun(){
    const u=benchUndo; if(!u) return;
    benchUndo=null; document.getElementById('benchToast').hidden=true;
    const r=await benchApi('POST',`/api/bench/${u.id}/undo`,{from:u.from});
    if(!r.ok){ benchToast(benchErr(r)); return; }
    benchSel=null; benchDetail=null; benchForm=null;
    await benchLoad(); loadEvents();
    benchToast('되돌렸습니다.');
}
function benchToast(msg,undo){
    const t=document.getElementById('benchToast'); if(!t) return;
    benchUndo=undo||null;
    t.innerHTML=`<span>${_esc(msg)}</span>${undo?'<button type="button" onclick="benchUndoRun()">되돌리기</button>':''}`;
    t.hidden=false;
    clearTimeout(benchToastTimer);
    benchToastTimer=setTimeout(()=>{ t.hidden=true; benchUndo=null; },undo?8000:3000);
}

// ── 팝업: 대기함으로 보내기 / 날짜 미정 만들기 / 사용법 ──────────────
function benchPopClose(){ benchPop=null; document.getElementById('benchPopWrap').innerHTML=''; }
function benchHelp(open){ if(open){ benchPop={type:'help'}; benchPopRender(); } else benchPopClose(); }
function benchSendOpen(evId){
    const ev=events.find(e=>e.id===evId); if(!ev) return;
    if(typeof closeEventPopover==='function') closeEventPopover();
    const g=ev.request_data||{};
    const ids=[...new Set([...(Array.isArray(g.estimate_ids)?g.estimate_ids:[]),...(g.estimate_id?[g.estimate_id]:[])].filter(Boolean).map(Number))];
    const opts=ev.sched_event_opts||[];
    const after=opts.includes('after')&&ev.sched_after_date?String(ev.sched_after_date).substring(0,10):'';
    benchPop={type:'send',ev,kind:evEnd(ev)<benchData.today?'revisit':'hold',reason:'',ests:ids.map(id=>({id,label:'#'+id})),estQuery:'',estResults:[],
        win:after&&after>benchData.today?'after':'asap',from:after&&after>benchData.today?after:shiftDate(benchData.today,3),to:'',due:'',block:''};
    benchPopRender();
    ids.forEach(id=>benchEstLabel(id));
}
function benchSendFromModal(){ const id=editingId; if(!id) return; closeModal(); benchSendOpen(id); }
// 일정 창 푸터의 '대기함으로 보내기' 버튼 — 보낼 수 있는 일정을 열었을 때만 보인다
function benchModalBtn(ev){ const btn=document.getElementById('btnBench'); if(btn) btn.style.display=benchCanSend(ev)?'':'none'; }
function benchNewOpen(){
    benchPop={type:'new',title:'',color:Object.keys(CS_CATS).find(k=>(CS_CATS[k].label||'').includes('방문'))||Object.keys(CS_CATS)[0],reason:'',address:'',assignee_ids:[],
        ests:[],estQuery:'',estResults:[],win:'asap',from:shiftDate(benchData.today,3),to:shiftDate(benchData.today,10),due:'',block:''};
    benchPopRender();
}
// 연동 견적서 번호 표시 — 일정에는 id만 있어 한 번 조회해 화면 번호로 바꾼다
async function benchEstLabel(id){
    try{
        const res=await fetch(`/api/estimates?search=${id}`); if(!res.ok) return;
        const data=await res.json(); const e=(data.data||data).find(x=>Number(x.id)===Number(id));
        const row=benchPop&&benchPop.ests&&benchPop.ests.find(x=>x.id===id);
        if(e&&row){ row.label='#'+(e.display_no??e.id)+(e.title||e.client_name?' · '+(e.title||e.client_name):''); benchPopRender(); }
    }catch(err){}
}
let benchEstTimer=null;
function benchEstSearch(q){
    benchPop.estQuery=q; clearTimeout(benchEstTimer);
    benchEstTimer=setTimeout(async()=>{
        if(!benchPop||!q.trim()){ if(benchPop){ benchPop.estResults=[]; benchPopRender(true); } return; }
        const res=await fetch(`/api/estimates?search=${encodeURIComponent(q)}`); if(!res.ok||!benchPop) return;
        const data=await res.json();
        benchPop.estResults=(data.data||data).slice(0,8).map(e=>({id:Number(e.id),label:'#'+(e.display_no??e.id)+(e.title||e.client_name?' · '+(e.title||e.client_name):'')}));
        benchPopRender(true);
    },300);
}
function benchEstAdd(i){ const e=benchPop.estResults[i]; if(e&&!benchPop.ests.some(x=>x.id===e.id)) benchPop.ests.push(e); benchPop.estResults=[]; benchPop.estQuery=''; benchPopRender(); }
function benchEstDrop(id){ benchPop.ests=benchPop.ests.filter(x=>x.id!==id); benchPopRender(); }
function benchPopFieldsHtml(p,kinds){
    const sel=(v,cur)=>v===cur?' selected':'';
    return `<label>내용 (필수)<textarea id="benchPopWhy" rows="2" oninput="benchPop.reason=this.value" placeholder="예: 고객 장비 도착 지연, 조명 1대 추가 설치">${_esc(p.reason)}</textarea></label>
        <label>연결 견적서 (필요 제품 기준)
            <div class="bench-seg">${p.ests.map(e=>`<span class="bench-estchip">${_esc(e.label)}<button type="button" onclick="benchEstDrop(${e.id})" aria-label="견적서 연결 해제">✕</button></span>`).join('')||'<span class="bench-muted">연결 안 함 (필요 제품 없음)</span>'}</div>
            <input id="benchEstQ" value="${_esc(p.estQuery)}" oninput="benchEstSearch(this.value)" placeholder="견적서 번호·의뢰자로 검색해 추가" autocomplete="off">
            ${p.estResults.length?`<div class="bench-estlist">${p.estResults.map((e,i)=>`<button type="button" onclick="benchEstAdd(${i})">${_esc(e.label)}</button>`).join('')}</div>`:''}
        </label>
        <div class="row"><label>희망 시점<select onchange="benchPop.win=this.value;benchPopRender()"><option value="asap"${sel('asap',p.win)}>빠른 일정 희망</option><option value="after"${sel('after',p.win)}>날짜 이후</option>${kinds?`<option value="between"${sel('between',p.win)}>기간</option>`:''}</select></label>
            ${p.win!=='asap'?`<label>${p.win==='between'?'시작':'이후 날짜'}<input type="date" value="${p.from}" onchange="benchPop.from=this.value"></label>`:''}
            ${p.win==='between'?`<label>끝<input type="date" value="${p.to}" onchange="benchPop.to=this.value"></label>`:''}
            <label>기한 (선택)<input type="date" value="${p.due}" onchange="benchPop.due=this.value"></label></div>
        <label>배치 전 조건 (선택)<input value="${_esc(p.block)}" oninput="benchPop.block=this.value" placeholder="예: 부품 입고 후"></label>`;
}
// keepFocus: 견적서 검색 중 다시 그릴 때 입력 커서 유지
function benchPopRender(keepFocus){
    const wrap=document.getElementById('benchPopWrap'), p=benchPop;
    if(!p){ wrap.innerHTML=''; return; }
    let body;
    if(p.type==='help') body=document.getElementById('benchHelpTpl').innerHTML;
    else if(p.type==='send'){
        const ev=p.ev, past=evEnd(ev)<=benchData.today;
        body=`<h3>대기함으로 보내기</h3><div class="bench-why">${_esc(ev.title||'')}<br><span class="bench-muted">${benchMdw((ev.start_date||'').substring(0,10))} · 담당 ${_esc(assigneeNamesOf(ev).join(', ')||'미정')}</span></div>
            <div class="bench-form"><label>이유<div class="bench-seg">${['hold','revisit'].map(k=>`<button type="button" class="bench-mini${p.kind===k?' pri':''}" aria-pressed="${p.kind===k}" onclick="benchPop.kind='${k}';benchPopRender()">${BENCH_KINDS[k][0]}</button>`).join('')}</div></label>
            ${p.kind==='revisit'&&!past?'<div class="bench-flag f-wait wrap">아직 방문 전 일정입니다. 재방문은 다녀온 뒤에 고르는 이유입니다.</div>':''}
            ${benchPopFieldsHtml(p,false)}
            <div class="bench-acts"><button type="button" class="bench-mini pri" onclick="benchSendSubmit()">보내기</button><button type="button" class="bench-mini" onclick="benchPopClose()">취소</button></div>
            <p class="bench-muted">${p.kind==='revisit'?'이 일정은 그대로 남고, 재방문 일정이 대기함에 새로 생깁니다.':'달력에서 빠지고 원래 날짜에 흔적이 남습니다.'} 견적서를 연결하면 그 품목의 재고로 준비 가능 여부를 계산합니다.</p></div>`;
    }else{
        const who=assignees.filter(a=>a.is_active!==false);
        body=`<h3>날짜 미정 일정 만들기</h3><div class="bench-form">
            <div class="row"><label>제목 (필수)<input id="benchPopTitle" value="${_esc(p.title)}" oninput="benchPop.title=this.value" placeholder="예: 마포 원룸 방송 세팅"></label><label>분류<select onchange="benchPop.color=this.value">${Object.keys(CS_CATS).map(k=>`<option value="${k}"${p.color===k?' selected':''}>${_esc(CS_CATS[k].label||k)}</option>`).join('')}</select></label></div>
            <label>주소 (선택, 같은 지역 일정 표시용)<input value="${_esc(p.address)}" oninput="benchPop.address=this.value" placeholder="예: 서울 마포구 월드컵로 1"></label>
            <label>담당 (선택)<div class="bench-seg">${who.map(a=>`<button type="button" class="bench-mini${p.assignee_ids.includes(a.id)?' pri':''}" aria-pressed="${p.assignee_ids.includes(a.id)}" onclick="benchPopWho(${a.id})">${_esc(a.name)}</button>`).join('')}</div></label>
            ${benchPopFieldsHtml(p,true)}
            <div class="bench-acts"><button type="button" class="bench-mini pri" onclick="benchNewSubmit()">대기함에 넣기</button><button type="button" class="bench-mini" onclick="benchPopClose()">취소</button></div></div>`;
    }
    wrap.innerHTML=`<div class="bench-popbg" onclick="benchPopClose()"></div><div class="bench-pop${p.type==='help'?' help':''}" role="dialog" aria-modal="true" aria-label="${p.type==='help'?'대기함 사용법':'대기함'}">${body}</div>`;
    if(keepFocus){ const q=document.getElementById('benchEstQ'); if(q){ q.focus(); q.setSelectionRange(q.value.length,q.value.length); } }
}
function benchPopWho(aid){ const ids=benchPop.assignee_ids, i=ids.indexOf(aid); if(i>=0) ids.splice(i,1); else ids.push(aid); benchPopRender(); }
function benchPopBody(p){
    return {reason:p.reason.trim(),estimate_ids:p.ests.map(e=>e.id),window_type:p.win,window_from:p.win==='asap'?null:p.from,window_to:p.win==='between'?p.to:null,due_date:p.due||null,block_condition:p.block.trim()||null};
}
async function benchPopSubmit(url,body,focusId,doneMsg){
    if(!body.reason){ benchToast('내용을 적어 주세요. 다시 배치할 때 판단 근거가 됩니다.'); const el=document.getElementById('benchPopWhy'); if(el) el.focus(); return; }
    const r=await benchApi('POST',url,body);
    if(!r.ok){ benchToast(benchErr(r)); return; }
    benchPopClose();
    benchFilter='all'; benchOpen=true; benchSel=null;
    await benchLoad(); loadEvents();
    benchPick(r.data.id);
    benchToast(doneMsg+r.data.title,{id:r.data.id,from:'waiting'});
}
function benchSendSubmit(){ const p=benchPop; benchPopSubmit(`/api/events/${p.ev.id}/bench`,Object.assign({kind:p.kind},benchPopBody(p)),'benchPopWhy','대기함으로 보냈습니다: '); }
function benchNewSubmit(){
    const p=benchPop;
    if(!p.title.trim()){ benchToast('제목을 적어 주세요.'); document.getElementById('benchPopTitle').focus(); return; }
    benchPopSubmit('/api/bench',Object.assign({title:p.title.trim(),color:p.color,address:p.address.trim()||null,assignee_ids:p.assignee_ids},benchPopBody(p)),'benchPopWhy','대기함에 넣었습니다: ');
}

// ── 달력 연동 (renderMonth·일별 목록·사이드 필터가 호출) ──────────────
// 월간 날짜 칸 꾸미기 — 흔적 칩, 선택한 카드의 제품 준비·배치 가능 기간·기한·같은 지역 표시, 끌어 놓기
function benchDecorateCell(div,evList,ds){
    if(!canEditCalendar) return;
    if(benchTraces){
        benchData.items.filter(b=>b.origin_date===ds).forEach(b=>{
            const k=benchKind(b), chip=document.createElement('div');
            chip.className='event-chip single bench-trace'; chip.style.setProperty('--k',k[1]);
            chip.title=`대기함 · ${k[0]} · ${b.title}`;
            chip.innerHTML=`<span class="chip-title">${k[2]} ${k[0]} · ${_esc(b.title)}</span>`;
            chip.onclick=e=>{ e.stopPropagation(); benchPick(b.id); };
            evList.appendChild(chip);
        });
    }
    const b=benchItem(), row=div.querySelector('.day-num-row');
    const tag=(cls,text)=>{ if(row) row.insertAdjacentHTML('beforeend',`<span class="bench-daytag ${cls}">${_esc(text)}</span>`); };
    if(b&&b.due_date===ds) tag('due','기한');
    if(benchActive(b)){
        if(ds>=b.earliest&&(!b.latest||ds<=b.latest)) div.classList.add('bench-win');
        if(ds>benchData.today&&ds<b.prep_start){ div.classList.add('bench-lead'); tag('lead','제품 준비'); }
        const here=(b.same_area||[]).filter(s=>s.date===ds);
        if(here.length){
            tag('area'+(here.some(s=>s.status==='ok')?'':' off'),b.area);
            const ids=here.map(s=>String(s.id));
            evList.querySelectorAll('[data-evid]').forEach(c=>{ if(ids.includes(c.dataset.evid)) c.classList.add('bench-hit'); });
        }
    }
    div.addEventListener('dragover',e=>{ if(benchDragId===null) return; e.preventDefault(); div.classList.add('bench-drop'); });
    div.addEventListener('dragleave',e=>{ if(!div.contains(e.relatedTarget)) div.classList.remove('bench-drop'); });
    div.addEventListener('drop',e=>{
        if(benchDragId===null) return; e.preventDefault();
        const id=benchDragId, item=benchData.items.find(x=>x.id===id); benchDragId=null;
        if(!item) return;
        benchSel=id; if(!benchDetail||benchDetail.id!==id) benchDetail=null;
        benchPlan(id,ds,item.assignee_ids);
        benchApi('GET','/api/bench/'+id).then(d=>{ if(benchSel===id&&d.ok){ benchDetail=d.data; benchRender(); renderView(); } });
    });
}
// 선택한 날의 일정 목록에 흔적도 '대기함' 줄로 보여 준다
function benchDayRowsHtml(ds){
    if(!canEditCalendar||!benchTraces) return '';
    return benchData.items.filter(b=>b.origin_date===ds).map(b=>{
        const k=benchKind(b);
        return `<div class="mde-item bench-dayrow" onclick="benchPick(${b.id})"><div class="mde-time">대기함</div><div class="mde-bar" style="background:none;border-left:3px dashed ${k[1]}"></div><div class="mde-info"><div class="mde-title-row"><div class="mde-title">${k[2]} ${_esc(b.title)}</div></div><div class="mde-meta">${k[0]} · ${_esc(b.reason)}</div></div></div>`;
    }).join('');
}
// 왼쪽 필터 — 흔적 켜기/끄기와 범례
function benchSideHtml(){
    if(!canEditCalendar) return '';
    return `<div class="cs-divider"></div><div class="cs-sec-title">대기함</div>
        <div class="cs-cat${benchTraces?'':' off'}"><span class="cs-check${benchTraces?' on':''}" style="--cat-c:#9aa3b2" onclick="benchToggleTraces()" title="${benchTraces?'끄기':'켜기'}"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></span><span class="cs-cat-label" onclick="benchToggleTraces()" title="대기 일정의 원래 날짜에 점선 흔적을 표시">원래 날짜 흔적 표시</span></div>
        <div class="bench-legend">${Object.values(BENCH_KINDS).map(k=>`<div><i style="border:1.5px dashed ${k[1]}"></i>${k[2]} ${k[0]}</div>`).join('')}
        <div><i class="lg-lead"></i>제품 준비 중</div><div><i class="lg-win"></i>배치 가능 기간</div><div><span class="bench-daytag area">지역</span>같은 지역 일정</div></div>`;
}
function benchToggleTraces(){
    benchTraces=!benchTraces; localStorage.setItem('cal_bench_traces',benchTraces?'1':'0');
    renderCsCats(); if(currentView==='month') renderView(); else if(mobileSelectedDate) renderMobileDayEvents(mobileSelectedDate);
}

document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&benchPop) benchPopClose(); });
document.addEventListener('dragstart',e=>{
    const card=e.target.closest&&e.target.closest('.bench-card[draggable="true"]'); if(!card) return;
    benchDragId=Number(card.dataset.bench); e.dataTransfer.setData('text/plain',String(benchDragId)); e.dataTransfer.effectAllowed='move';
});
document.addEventListener('dragend',()=>{ benchDragId=null; document.querySelectorAll('.day-cell.bench-drop').forEach(c=>c.classList.remove('bench-drop')); });
benchLoad();
