{{-- 배송 현황·세팅 항목 표시·견적서 연동 --}}
// ── 배송 현황 (송장 추적) — 방문의뢰(gold)·촬영/스튜디오(green), 저장된 일정만 ──
const SHIP_COLORS=['gold','green'];
let shipCache={shipments:[],carriers:{}};
function toggleShipmentBody(force){
    const body=document.getElementById('shipmentBody');
    const caret=document.getElementById('shipCaret');
    if(!body) return;
    const open = force!==undefined ? force : body.style.display==='none';
    body.style.display=open?'':'none';
    if(caret) caret.textContent=open?'▾':'▸';
}
function updateShipmentSectionVisibility(){
    const sec=document.getElementById('shipmentSection');
    if(sec) sec.style.display=(editingId&&SHIP_COLORS.includes(currentColor))?'':'none';
}
async function loadShipments(){
    if(!editingId||!SHIP_COLORS.includes(currentColor)) return;
    const sid=editingId;
    // 캐시 즉시 표시 → 백그라운드 최신화 (재열람 시 늦은 갱신으로 화면이 바뀌어 보이는 문제 완화)
    const cached=swrGet('ship:'+sid);
    if(cached){ shipCache=cached; renderShipments(); if(isLocked) renderLockSummary(); }
    try{
        const res=await fetch(`/api/schedules/${sid}/shipments`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        const data=await res.json();
        if(String(editingId)!==String(sid)) return; // 로딩 중 다른 일정으로 전환됨
        const changed=!cached||JSON.stringify(cached)!==JSON.stringify(data);
        swrSet('ship:'+sid,data);
        if(changed){ shipCache=data; renderShipments(); if(isLocked) renderLockSummary(); }
        // 배송완료인데 사업장 위치가 없는 송장 → 열람 시 1회 자동 백필 (서버가 60일/6시간 제한)
        if(!shipLocBackfilled.has(sid)&&(data.shipments||[]).some(s=>s.status==='delivered'&&!s.last_location)){
            shipLocBackfilled.add(sid);
            try{
                const r2=await fetch(`/api/schedules/${sid}/shipments/refresh`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
                if(r2.ok){
                    const d2=await r2.json();
                    if(String(editingId)===String(sid)){ shipCache=d2; swrSet('ship:'+sid,d2); renderShipments(); if(isLocked) renderLockSummary(); }
                }
            }catch(e){}
        }
    }catch(e){}
}
const shipLocBackfilled=new Set(); // 세션 내 일정별 1회만 시도
// 택배사별 실시간 조회 페이지 (송장번호 클릭 시 새 창) — {no} 자리에 송장번호 치환, 없으면 조회 페이지만 열기
const CARRIER_TRACK_URLS={
    'kr.cjlogistics':'https://trace.cjlogistics.com/next/tracking.html?wblNo={no}',
    'kr.lotte':'https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo={no}',
    'kr.hanjin':'https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&schLang=KR&wblnumText2={no}',
    'kr.logen':'https://www.ilogen.com/web/personal/trace/{no}',
    'kr.epost':'https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1={no}',
    'kr.kdexp':'https://kdexp.com/service/delivery/etc/delivery.do?barcode={no}',
    'kr.coupangls':'https://www.coupangls.com/web/modal/invoice/{no}', // 조회 모달 딥링크 — 송장번호 자동 입력
};
function shipRowHtml(s){
    const ico=s.status==='delivered'?['○','var(--green)']:(s.status==='error'?['⚠','var(--red)']:['✕','var(--red)']);
    const evTxt=s.status==='delivered'?`배송완료${s.delivered_at?' · '+s.delivered_at:''}`:(s.last_event||'조회 대기');
    const trackUrl=CARRIER_TRACK_URLS[s.carrier];
    const trackHref=trackUrl?(trackUrl.includes('{no}')?trackUrl.replace('{no}',encodeURIComponent(s.tracking_no)):trackUrl):null;
    const noHtml=trackHref
        ? `<a class="ship-no ship-no-link" href="${trackHref}" target="_blank" rel="noopener" title="택배사 실시간 조회 열기" onclick="event.stopPropagation()">${_esc(s.tracking_no)} ↗</a>`
        : `<span class="ship-no">${_esc(s.tracking_no)}</span>`;
    const locHtml=s.last_location?`<span class="ship-loc" title="마지막 처리 사업장: ${_esc(s.last_location)}">📍 ${_esc(s.last_location)}</span>`:'';
    // 견적서에서 등록된 송장 — 캘린더는 표시만 (삭제·수정은 견적서 주문/배송에서)
    const fromEst=s.source==='estimate';
    return `<div class="ship-item">
        <span class="ship-status-ico" style="color:${ico[1]}">${ico[0]}</span>
        <span class="ship-carrier">${_esc(s.carrier_label)}</span>
        ${noHtml}
        ${fromEst?'<span style="font-size:10px;color:var(--text-muted);border:1px solid var(--border);border-radius:3px;padding:0 4px;white-space:nowrap;" title="연동된 견적서의 주문/배송에서 등록된 송장">견적서</span>':''}
        ${locHtml}
        <span class="ship-event" title="${_esc(evTxt)}">${_esc(evTxt)}</span>
        ${fromEst?'':`<button type="button" class="ship-del" onclick="deleteShipment(${s.id})" title="송장 삭제">✕</button>`}
    </div>`;
}
let shipIconOverride=null; // 현재 열린 일정의 수동 배송 아이콘 (null=제목에 표시 안 함)
function renderShipIconButtons(){
    document.querySelectorAll('.ship-ico-btn').forEach(b=>{
        b.classList.toggle('primary', (b.dataset.sio||'')===(shipIconOverride||''));
    });
}
// 요약 뷰에서 확정 상태(제/희/목/확) 바로 지정 — 같은 값 다시 클릭하면 해제, 즉시 서버 반영
async function lsSetSchedOpt(v){
    if(!editingId||!canEditCalendar) return;
    const cur=document.querySelector('#scheduleOpts .sched-opt-btn.active')?.dataset.sopt||null;
    const val=v===cur?null:v;
    if(!(await quickUpdateEvent({sched_opt:val}))) return;
    // 폼 버튼 상태도 동기화 (요약 해제 후 편집·저장 시 일관성 유지)
    document.querySelectorAll('#scheduleOpts .sched-opt-btn').forEach(b=>b.classList.toggle('active',b.dataset.sopt===val));
    updateSchedOptDesc();
    if(detailEvent) detailEvent.sched_opt=val;
    showCalToast(val?`확정 상태: ${SCHED_FULL_LABELS[val]}`:'확정 상태를 해제했습니다');
    if(isLocked&&typeof renderLockSummary==='function') renderLockSummary();
    loadEvents();
}

// 배송 아이콘 수동 지정 — 클릭 즉시 서버 반영 (부분 송장 업로드 시 완료 착각 방지)
// 이미 선택된 아이콘을 다시 클릭하면 해제 (실수 클릭 복구)
async function setShipIconOverride(v){
    if(!editingId||!canEditCalendar) return;
    let val=v||null;
    if(val&&val===shipIconOverride) val=null;
    if(!(await quickUpdateEvent({ship_icon_override:val}))) return;
    shipIconOverride=val;
    if(detailEvent) detailEvent.ship_icon_override=val;
    renderShipIconButtons();
    updateModalShipBadge();
    showCalToast(val?'제목 배송 아이콘을 지정했습니다':'제목 배송 아이콘을 해제했습니다');
    if(isLocked&&typeof renderLockSummary==='function') renderLockSummary(); // 요약 뷰의 선택 상태 갱신
    loadEvents();
}
// 모달 제목 옆 배송 상태 아이콘 — 수동 지정만 표시 (자동 판정 제거)
function updateModalShipBadge(){
    const badge=document.getElementById('modalShipBadge');
    if(!badge) return;
    if(shipIconOverride&&SHIP_ICON_MAP[shipIconOverride]){
        const [ico,cls]=SHIP_ICON_MAP[shipIconOverride];
        badge.textContent=ico;
        badge.style.color=cls==='s-all'?'var(--green)':(cls==='s-part'?'#d78a2e':'var(--red)');
        badge.title=SHIP_ICON_MAP[shipIconOverride][2];
        badge.style.display='';
        return;
    }
    badge.style.display='none'; badge.textContent='';
}
function renderShipments(){
    const list=document.getElementById('shipmentList');
    const badge=document.getElementById('shipSummaryBadge');
    if(!list) return;
    const ships=shipCache.shipments||[];
    const done=ships.filter(s=>s.status==='delivered').length;
    if(badge) badge.textContent=ships.length?`(${done}/${ships.length} 완료)`:'';
    // 송장 입력은 견적서 주문/배송으로 일원화 — 캘린더는 표시 전용
    const linkedEst=shipCache.estimate_id||null;
    const noteText=document.getElementById('shipEstimateNoteText');
    const btn=document.getElementById('shipEstimateOpenBtn');
    if(noteText) noteText.textContent=linkedEst?'송장은 연동된 견적서의 주문/배송에서 입력합니다.':'송장은 견적서의 주문/배송에서 입력합니다. 일정에 견적서를 연동하면 자동으로 표시됩니다.';
    if(btn){
        btn.style.display=linkedEst?'':'none';
        if(linkedEst) btn.onclick=()=>window.open(`/estimates/${linkedEst}/shipments`,'est_ship_'+linkedEst,'width=900,height=720,scrollbars=yes,resizable=yes');
    }
    list.innerHTML=ships.length?ships.map(shipRowHtml).join('')
        :`<div class="ship-empty">${linkedEst?'등록된 송장이 없습니다. 연동된 견적서의 주문/배송에서 입력하세요.':'등록된 송장이 없습니다. 견적서에서 입력한 송장이 여기에 표시됩니다.'}</div>`;
    updateModalShipBadge();
}
// 송장 등록은 견적서 주문/배송으로 일원화 — 캘린더 직접 등록 UI 제거됨 (과거 등록분 삭제만 가능)
async function deleteShipment(id){
    if(!confirm('이 송장을 삭제하시겠습니까?')) return;
    const res=await fetch(`/api/schedule-shipments/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(!res.ok){alert('삭제 실패');return;}
    shipCache=await res.json(); if(editingId) swrSet('ship:'+editingId,shipCache); renderShipments(); loadEvents();
}
async function refreshShipments(){
    if(!editingId) return;
    const res=await fetch(`/api/schedules/${editingId}/shipments/refresh`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(!res.ok){alert('배송상태 갱신 실패');return;}
    shipCache=await res.json(); swrSet('ship:'+editingId,shipCache); renderShipments(); loadEvents();
    if(isLocked) renderLockSummary();
}

// ── 세팅 항목 (읽기 전용) — 연결된 프로젝트의 의뢰 내용(custom_data.__req_items)을 불러와 표시 ──
let reqItems=[];       // 일정 자체에 저장된 선택 (구버전 호환 — 저장 시 그대로 보존)
let projReqItems=[];   // 연결 프로젝트에서 불러온 의뢰 내용
let projReqLoadedFor=null;

function activeReqItems(){ return projReqItems.length?projReqItems:reqItems; }

async function loadProjectReqItems(pid){
    if(!pid){ projReqItems=[]; projReqLoadedFor=null; renderReqView(); return; }
    if(String(projReqLoadedFor)===String(pid)) return;
    projReqLoadedFor=pid;
    try{
        const res=await fetch(`/api/projects/${pid}/request-items`,{headers:{'Accept':'application/json'}});
        if(String(projReqLoadedFor)!==String(pid)) return; // 로딩 중 프로젝트 변경됨
        projReqItems=res.ok?((await res.json()).req_items||[]):[];
    }catch(e){ projReqItems=[]; }
    renderReqView();
    if(isLocked&&typeof renderLockSummary==='function') renderLockSummary();
}

// 타이틀 → 분류별 그룹 HTML (모달 표시부·요약 뷰 공용)
// 분류는 지정 순서(기본 서비스→의뢰 서비스→컴퓨터→카메라/조명→오디오→기타)로 정렬, 항목은 - 접두사로 한 줄씩
const RQV_CAT_ORDER=['기본 서비스','의뢰 서비스','컴퓨터','카메라/조명','오디오','기타'];
function reqItemsGroupedHtml(items){
    const byTitle={};
    items.forEach(i=>{ (byTitle[i.t]=byTitle[i.t]||[]).push(i); });
    return Object.entries(byTitle).map(([t,list])=>{
        const byCat={};
        list.forEach(i=>{ (byCat[i.c]=byCat[i.c]||[]).push(i); });
        const cats=Object.entries(byCat).sort((a,b)=>{
            const ia=RQV_CAT_ORDER.indexOf(a[0]), ib=RQV_CAT_ORDER.indexOf(b[0]);
            return (ia===-1?999:ia)-(ib===-1?999:ib);
        });
        return `<div class="rqv-title">${_esc(t)}</div>`+cats.map(([c,ls])=>
            `<div class="rqv-cat">${_esc(c)}</div>`
            +ls.map(i=>`<div class="rqv-item">- ${_esc(i.d)}${(i.qty||1)>1?` ×${i.qty}`:''}</div>`).join('')
        ).join('');
    }).join('');
}

function renderReqView(){
    const el=document.getElementById('reqItemsView');
    if(!el) return;
    const items=activeReqItems();
    if(!items.length){
        el.innerHTML=`<span style="color:var(--text-muted);font-size:12px;">${linkedProjectId?'연결된 프로젝트에 작성된 의뢰 내용이 없습니다.':'프로젝트를 연결하면 의뢰 내용을 불러옵니다.'}</span>`;
        return;
    }
    el.innerHTML=reqItemsGroupedHtml(items)
        +`<div class="rqv-src">${projReqItems.length?'📁 연결된 프로젝트의 의뢰 내용':'이 일정에 저장된 항목 (구버전)'}</div>`;
}

function resetAttachments(){
    pendingAttachments={quote:[],reference:[],room:[],general:[]};existingAttachments={quote:[],reference:[],room:[],general:[]};
    ['quote','reference','room','general'].forEach(t=>renderImgGrid(t));
}

// ── 견적서 연동 ──
let estimateSearchTimer=null;
function renderEstimateList(list){
    const sm={created:'작성중',editing:'수정중',completed:'완료',paid:'결제완료',hold:'보류'};
    if(!list.length) return '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">결과 없음</div>';
    // 자동 첨부 파일명(날짜 닉네임(이름))용 의뢰자 메타 — 선택 시 참조
    window.__estSearchMeta=window.__estSearchMeta||{};
    list.forEach(e=>{ window.__estSearchMeta[e.id]={nickname:e.client_nickname||'',cname:e.client_name||''}; });
    return list.map(e=>{
        const amt=e.total_amount?Number(e.total_amount).toLocaleString()+'원':'';
        const date=e.created_at?(e.created_at.substring(0,10)):'';
        const name=e.client_nickname||e.client_name||'(이름없음)';
        return `<div style="padding:10px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;transition:background 0.1s;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
            <div style="flex:1;cursor:pointer;min-width:0;" onclick="selectEstimate(${e.id},'${name.replace(/'/g,"\\'")}',${e.total_amount||0},${e.display_no??e.id})">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="font-size:13px;font-weight:600;">#${e.display_no??e.id}</span>
                    <span style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${name}</span>
                    <span style="font-size:10px;padding:2px 6px;border-radius:4px;background:var(--surface2);color:var(--text-muted);flex-shrink:0;">${sm[e.status]||e.status}</span>
                </div>
                <div style="display:flex;gap:8px;margin-top:3px;font-size:11px;color:var(--text-muted);">
                    ${amt?'<span style="color:var(--accent);">'+amt+'</span>':''}
                    ${date?'<span>'+date+'</span>':''}
                </div>
            </div>
            <button onclick="event.stopPropagation();window.open('/estimates/${e.id}/print','estimate_print','width=900,height=700,scrollbars=yes,resizable=yes')" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;flex-shrink:0;transition:all 0.15s;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">보기</button>
        </div>`;
    }).join('');
}

async function openEstimateSearch(){
    document.getElementById('estimateSearchOverlay').style.display='flex';
    document.getElementById('estimateSearchInput').value='';
    document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">로딩 중...</div>';
    setTimeout(()=>document.getElementById('estimateSearchInput').focus(),50);
    // 최근 목록 자동 로드
    try{
        const res=await fetch('/api/estimates');
        if(res.ok){const data=await res.json();const list=data.data||data;document.getElementById('estimateSearchResults').innerHTML=renderEstimateList(list);}
    }catch(e){document.getElementById('estimateSearchResults').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">로드 실패</div>';}
}
function searchEstimates(query){
    clearTimeout(estimateSearchTimer);
    if(!query.trim()){openEstimateSearch();return;}
    estimateSearchTimer=setTimeout(async()=>{
        const res=await fetch(`/api/estimates?search=${encodeURIComponent(query)}`);if(!res.ok)return;
        const data=await res.json();const list=data.data||data;
        document.getElementById('estimateSearchResults').innerHTML=renderEstimateList(list);
    },300);
}
// 여러 견적서 연동 — 선택 시 목록에 추가 (중복 방지), 견적서별 보기/해제
function selectEstimate(id,name,amount,no){
    if(!linkedEstimateIds.some(x=>String(x)===String(id))) linkedEstimateIds.push(id);
    const cm=(window.__estSearchMeta||{})[id]||{};
    linkedEstimateMeta[id]={display_no:no??id,name:name||'',total:Number(amount)||0,client_nickname:cm.nickname||'',client_name:cm.cname||''};
    renderLinkedEstimates();
    document.getElementById('estimateSearchOverlay').style.display='none';
    // 금액 칸이 비어 있으면 연동 견적서 총액 합계로 채움
    const inp=document.getElementById('g_estimate_amount');
    const sum=linkedEstimatesTotal();
    if(inp&&!inp.value.trim()&&sum) inp.value=sum.toLocaleString();
    autoAttachEstimatePng(id,no??id); // 견적서 PNG를 '견적서' 첨부 대기열에 자동 추가
}

// 견적서 불러오기 시 PNG 자동 첨부 — 인쇄 페이지를 숨은 iframe으로 렌더해 캡처 (팝업 없음),
// '견적서' 첨부 대기열(pendingAttachments.quote)에 넣어 저장 시 함께 업로드된다.
async function autoAttachEstimatePng(id,no){
    let iframe=null;
    try{
        // 파일명: 'yyyy-mm-dd 닉네임(이름).png' — 날짜는 불러온 날, 파일명 금지 문자는 제거
        const t=new Date();
        const ds=`${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`;
        const m=linkedEstimateMeta[id]||{};
        const nick=(m.client_nickname||'').trim();
        const nm=(m.client_name||'').trim();
        let who=nick&&nm?`${nick}(${nm})`:(nick||nm||`견적서#${no}`);
        who=who.replace(/[\\/:*?"<>|]/g,'').trim();
        const label=`${ds} ${who}`;
        // 같은 견적서(이 세션) 또는 같은 파일명이 이미 있으면 다시 만들지 않는다
        window.__autoAttachedEst=window.__autoAttachedEst||new Set();
        if(window.__autoAttachedEst.has(String(id))) return;
        const names=[...(existingAttachments.quote||[]).map(a=>a.file_name||''),
                     ...(pendingAttachments.quote||[]).map(a=>a.file?.name||'')];
        if(names.some(n=>n===`${label}.png`)) return;
        showCalToast('견적서 이미지 생성 중...');
        iframe=document.createElement('iframe');
        iframe.style.cssText='position:fixed;left:-11000px;top:0;width:1060px;height:1600px;border:0;visibility:hidden;';
        iframe.src=`/estimates/${id}/print`;
        document.body.appendChild(iframe);
        await new Promise((ok,fail)=>{iframe.onload=ok;iframe.onerror=fail;setTimeout(fail,15000);});
        const w=iframe.contentWindow;
        for(let i=0;i<50&&!w.html2canvas;i++){ await new Promise(r=>setTimeout(r,200)); } // 라이브러리 로드 대기
        if(!w.html2canvas) throw new Error('html2canvas not loaded');
        const el=w.document.querySelector('.estimate-wrap');
        if(!el) throw new Error('estimate-wrap not found');
        const bar=w.document.querySelector('.no-print-bar'); if(bar) bar.style.display='none';
        el.style.marginTop='0';
        await new Promise(r=>setTimeout(r,300)); // 폰트·직인 이미지 안정화
        const src=await w.html2canvas(el,{scale:2,backgroundColor:'#f2f2f3',useCORS:true,windowWidth:1060});
        const pad=80;
        const c=document.createElement('canvas');
        c.width=src.width+pad*2; c.height=src.height+pad*2;
        const ctx=c.getContext('2d');
        ctx.fillStyle='#fff'; ctx.fillRect(0,0,c.width,c.height); ctx.drawImage(src,pad,pad);
        const blob=await new Promise(r=>c.toBlob(r,'image/png'));
        if(!blob) throw new Error('toBlob failed');
        handleImgFiles('quote',[new File([blob],`${label}.png`,{type:'image/png'})]);
        window.__autoAttachedEst.add(String(id));
        showCalToast('견적서 이미지가 첨부(견적서)에 추가되었습니다 — 저장 시 업로드됩니다');
    }catch(e){
        console.error('견적서 PNG 자동 첨부 실패:',e);
        showCalToast('견적서 이미지 자동 첨부에 실패했습니다 — 첨부에서 직접 업로드해주세요');
    }finally{
        if(iframe) iframe.remove();
    }
}
function unlinkEstimate(id){
    linkedEstimateIds = id===undefined ? [] : linkedEstimateIds.filter(x=>String(x)!==String(id));
    renderLinkedEstimates();
}
function openLinkedEstimate(id){
    const t=id??linkedEstimateIds[0];
    if(!t) return;
    window.open(`/estimates/${t}/print`,'estimate_print_'+t,'width=900,height=700,scrollbars=yes,resizable=yes');
}
function linkedEstimatesTotal(){
    return linkedEstimateIds.reduce((s,id)=>s+((linkedEstimateMeta[id]||{}).total||0),0);
}
function renderLinkedEstimates(){
    const wrap=document.getElementById('linkedEstimateInfo');
    const list=document.getElementById('linkedEstimateList');
    if(!wrap||!list) return;
    if(!linkedEstimateIds.length){wrap.style.display='none';list.innerHTML='';return;}
    wrap.style.display='';
    list.innerHTML=linkedEstimateIds.map(id=>{
        const m=linkedEstimateMeta[id]||{};
        const label=`#${m.display_no??id}${m.name?' '+_esc(m.name):''}`;
        const amt=m.total?` · ${m.total.toLocaleString()}원`:'';
        return `<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:3px 0;">
            <div style="font-size:13px;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${label}<span style="font-weight:400;color:var(--text-muted);">${amt}</span></div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <button type="button" onclick="openLinkedEstimate(${id})" data-always-active style="font-size:11px;padding:3px 10px;border:1px solid var(--border);border-radius:6px;background:none;color:var(--text-muted);cursor:pointer;">보기</button>
                <button type="button" onclick="unlinkEstimate(${id})" data-always-active style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
            </div></div>`;
    }).join('');
    if(typeof isLocked!=='undefined'&&isLocked&&typeof renderLockSummary==='function') renderLockSummary();
}
// 연동 견적서 메타(표시 번호·이름·총액) 최신화 — 복원 직후·금액 추출 전 호출
async function refreshLinkedEstimateLabels(){
    for(const id of [...linkedEstimateIds]){
        try{
            const res=await fetch(`/api/estimates?search=${id}`);
            if(!res.ok) continue;
            const data=await res.json();const list=data.data||data;
            const est=(list||[]).find(e=>e.id==id); // 문자열 id 구데이터 대비 느슨 비교
            if(est) linkedEstimateMeta[id]={display_no:est.display_no??est.id,name:est.client_nickname||est.client_name||'',total:Number(est.total_amount)||0};
        }catch(e){}
    }
    renderLinkedEstimates();
}
// 연동 견적서 총액 합계 (메타 최신화 후) — 결제 금액 추출/자동 입력용
async function fetchLinkedEstimateTotal(){
    if(!linkedEstimateIds.length) return null;
    await refreshLinkedEstimateLabels();
    const sum=linkedEstimatesTotal();
    return sum>0?sum:null;
}
function _setEstimateStatus(msg){
    const st=document.getElementById('g_estimate_status');
    if(st){ st.textContent=msg; if(msg) setTimeout(()=>{ st.textContent=''; },4000); }
}
async function extractEstimateAmount(){
    if(!linkedEstimateIds.length){alert('먼저 견적서를 불러와주세요.');return;}
    const total=await fetchLinkedEstimateTotal();
    if(total){ document.getElementById('g_estimate_amount').value=total.toLocaleString(); _setEstimateStatus('견적서 금액 불러옴'); }
    else { _setEstimateStatus('금액 조회 실패 — 견적서 확인 필요'); }
}
// 결제완료 선택 시 — 금액이 비어 있으면 연동 견적서 총액을 자동 입력 (0원 기록 방지)
async function autofillEstimateAmountIfEmpty(){
    const inp=document.getElementById('g_estimate_amount');
    if(!linkedEstimateIds.length||!inp||inp.value.trim()) return;
    const total=await fetchLinkedEstimateTotal();
    if(total&&!inp.value.trim()){ inp.value=total.toLocaleString(); _setEstimateStatus('연동 견적서 금액 자동 입력됨'); }
}
document.getElementById('g_paid_group')?.addEventListener('click', e=>{
    const btn=e.target.closest('.radio-btn');
    if(btn&&btn.dataset.val==='결제완료') autofillEstimateAmountIfEmpty();
});

function openHistoryFromEdit(){
    if(!editingId) return;
    openActivityLog('Schedule', editingId, '일정 수정 로그');
}

