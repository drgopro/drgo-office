{{-- 라이트박스·변경 이력·하위 일정·년월 피커·드래그 --}}
// ── 라이트박스 (이미지 뷰어 + 줌/팬) ──
let lightboxImages=[], lightboxIdx=0;
let lbZoom=1, lbPanX=0, lbPanY=0, lbDragging=false, lbStartX=0, lbStartY=0;
const LB_MIN_ZOOM=0.5, LB_MAX_ZOOM=8;

function lbUpdateTransform(){
    const img=document.getElementById('lightboxImg');
    img.style.transform=`translate(${lbPanX}px,${lbPanY}px) scale(${lbZoom})`;
    img.style.transition=lbDragging?'none':'transform 0.15s ease';
    const wrap=document.getElementById('lightboxWrap');
    wrap.classList.toggle('zoomed',lbZoom>1.05);
}
function lbResetZoom(){ lbZoom=1; lbPanX=0; lbPanY=0; lbUpdateTransform(); }
function lbShowZoomInfo(){
    const info=document.getElementById('lightboxZoomInfo');
    info.textContent=Math.round(lbZoom*100)+'%';
    info.classList.add('show');
    clearTimeout(info._t);
    info._t=setTimeout(()=>info.classList.remove('show'),800);
}

// 원본 프리로드 캐시 (한 번 받은 원본은 즉시 표시)
const lbLoaded=new Set();
function lbPreload(idx){
    const it=lightboxImages[idx];
    if(!it||lbLoaded.has(it.src)) return;
    const im=new Image();
    im.onload=()=>lbLoaded.add(it.src);
    im.src=it.src;
}
// 현재 인덱스 표시: 썸네일 먼저(즉시) → 원본 로드되면 교체, 양옆은 미리 로드
function lbShow(){
    const it=lightboxImages[lightboxIdx];
    const img=document.getElementById('lightboxImg');
    document.getElementById('lightboxFilename').textContent=it.filename||'';
    if(lbLoaded.has(it.src) || !it.thumb || it.thumb===it.src){
        img.src=it.src; // 원본이 캐시됐거나 썸네일이 없으면 바로 원본
    } else {
        img.src=it.thumb; // 썸네일 즉시 표시
        const full=new Image();
        const myIdx=lightboxIdx;
        full.onload=()=>{ lbLoaded.add(it.src); if(lightboxIdx===myIdx) img.src=it.src; };
        full.src=it.src;
    }
    // 양옆 프리로드 — 다음 넘김이 즉시 뜨도록
    if(lightboxImages.length>1){
        lbPreload((lightboxIdx+1)%lightboxImages.length);
        lbPreload((lightboxIdx-1+lightboxImages.length)%lightboxImages.length);
    }
}
// 앨범 전용 history 항목 — ESC/뒤로가기로 앨범만 먼저 닫히도록
let __lbHistory=false, __lbConsuming=false;
// ── 삭제/변경 이력 (문장 로그) ──
async function openChangeLog(){
    const overlay=document.getElementById('changeLogOverlay');
    const body=document.getElementById('changeLogBody');
    overlay.classList.add('open');
    body.innerHTML='<div class="changelog-empty">불러오는 중…</div>';
    try{
        const res=await fetch('/api/events/change-log?limit=60',{headers:{'Accept':'application/json'}});
        if(!res.ok) throw new Error();
        const items=await res.json();
        if(!items.length){ body.innerHTML='<div class="changelog-empty">기록된 이동/삭제 이력이 없습니다.</div>'; return; }
        body.innerHTML=items.map(it=>`<div class="changelog-item">
            <span class="cl-at">${_esc(it.at)}</span><span class="cl-kind ${it.kind}">${it.kind==='delete'?'삭제':'이동'}</span>${_esc(it.text)}
            ${it.reason?`<span class="cl-reason">└ 사유: ${_esc(it.reason)}</span>`:''}
        </div>`).join('');
    }catch(e){ body.innerHTML='<div class="changelog-empty">이력을 불러오지 못했습니다.</div>'; }
}
function closeChangeLog(){ document.getElementById('changeLogOverlay').classList.remove('open'); }

// 차량 이용 필요 선택 시 사유 입력 폼 노출 + 하단 고정 배너 갱신
function updateCarReasonUI(){
    const carOn=!!document.querySelector('#specialOpts .special-opt-btn[data-opt="car"].active');
    const wrap=document.getElementById('specialReasonWrap');
    const input=document.getElementById('specialReason');
    if(wrap) wrap.style.display=carOn?'':'none';
    if(!carOn&&input) input.value='';
    const banner=document.getElementById('carReasonBanner');
    if(banner){
        const goldVisible=currentColor==='gold'&&!isLocked;
        banner.style.display=(carOn&&goldVisible)?'flex':'none';
        const reason=input?.value.trim()||'';
        document.getElementById('carReasonBannerText').textContent=reason?('— '+reason):'— 사유 미입력';
    }
}

function openLightbox(src,filename,images,idx){
    lightboxImages=images||[{src,filename}];
    lightboxIdx=idx||0;
    lbResetZoom();
    lbShow();
    document.getElementById('lightbox').classList.add('open');
    document.querySelector('.lightbox-nav.prev').style.display=lightboxImages.length>1?'':'none';
    document.querySelector('.lightbox-nav.next').style.display=lightboxImages.length>1?'':'none';
    document.body.classList.add('lb-open');
    lbPinClose(); lbBindPin(true);
    if(!__lbHistory){ try{ history.pushState({lb:1},''); }catch(e){} __lbHistory=true; }
}
function closeLightbox(){
    document.getElementById('lightbox').classList.remove('open'); lbResetZoom();
    document.body.classList.remove('lb-open');
    lbBindPin(false);
    const btn=document.querySelector('.lightbox-close'); if(btn){ btn.style.top=''; btn.style.left=''; btn.style.right=''; }
    // UI로 닫을 때(뒤로가기 아님) push했던 history 항목 소비 — popstate가 무시하도록 플래그
    if(__lbHistory){ __lbHistory=false; __lbConsuming=true; try{ history.back(); }catch(e){ __lbConsuming=false; } }
}

// 닫기 버튼을 '실제 보이는 화면'(비주얼 뷰포트) 우상단에 고정 — 핀치 줌/스크롤에도 안 움직임
function lbPinClose(){
    const btn=document.querySelector('.lightbox-close'); if(!btn) return;
    const vv=window.visualViewport;
    if(!vv){ btn.style.top='16px'; btn.style.right='16px'; return; }
    btn.style.right='auto';
    btn.style.top=(vv.offsetTop+12)+'px';
    btn.style.left=(vv.offsetLeft+vv.width-52)+'px';
}
let __lbPinBound=false;
function lbBindPin(on){
    const vv=window.visualViewport; if(!vv) return;
    if(on&&!__lbPinBound){ vv.addEventListener('resize',lbPinClose); vv.addEventListener('scroll',lbPinClose); __lbPinBound=true; }
    else if(!on&&__lbPinBound){ vv.removeEventListener('resize',lbPinClose); vv.removeEventListener('scroll',lbPinClose); __lbPinBound=false; }
}
// ── 장기 일정 하위 일정 (일자별 시간·담당자) — 요약 뷰 카드에서 관리 ──
let CHILD_ROWS=[], CH_ASSIGNEES=[], CH_EDIT_ID=null, CH_MODE='range';
function childSpanDays(){
    if(!editingId) return 0;
    const ev=events.find(e=>e.id===editingId); if(!ev) return 0;
    return (new Date(ev.end_date||ev.start_date)-new Date(ev.start_date))/86400000+1;
}
let CH_RENDER_SEQ=0, CH_BOX=null; // 렌더 순번 — 요약↔폼 전환 중 늦게 끝난 fetch가 반대쪽에 중복 카드를 남기는 것 방지
function chEl(id){ return CH_BOX?CH_BOX.querySelector('#'+id):document.getElementById(id); }
async function renderChildrenCard(){
    const seq=++CH_RENDER_SEQ;
    const ev=editingId?events.find(e=>e.id===editingId):null;
    const show=!!(ev && !ev.parent_id && childSpanDays()>=2);
    if(show){
        try{
            const res=await fetch(`/api/events/${ev.id}/children`,{headers:{'Accept':'application/json'}});
            CHILD_ROWS=res.ok?await res.json():[];
        }catch(e){ CHILD_ROWS=[]; }
    }
    if(seq!==CH_RENDER_SEQ) return; // 이후에 더 최신 렌더가 시작됨 — 이 결과는 버림
    // 컨테이너 결정은 fetch 완료 후 현재 뷰 기준으로 (요약=lsChildren, 폼=lsChildrenForm), 반대쪽은 비움
    const locked=document.querySelector('#modalOverlay .modal-body')?.classList.contains('is-locked');
    const box=document.getElementById(locked?'lsChildren':'lsChildrenForm');
    const other=document.getElementById(locked?'lsChildrenForm':'lsChildren');
    if(other) other.innerHTML='';
    if(!box) return;
    CH_BOX=box;
    if(!show){ box.innerHTML=''; return; }
    const rows=CHILD_ROWS.map(c=>`<div class="lsc-row">
        <b>${chFmtDate(c.start_date)}${c.start_date!==c.end_date?' ~ '+chFmtDate(c.end_date):''}</b>
        <span class="lsc-time">${c.start_time||''}${c.end_time?' ~ '+c.end_time:''}</span>
        <span class="lsc-who">${(c.assignees||[]).map(a=>_esc(a.name)).join(', ')}${c.memo?' · '+_esc(c.memo):''}</span>
        ${canEditCalendar?`<button class="lsc-mini-btn" onclick="chEdit(${c.id})">수정</button>
        <button class="lsc-mini-btn danger" onclick="chDelete(${c.id})">삭제</button>`:''}
    </div>`).join('');
    if(!canEditCalendar){
        box.innerHTML=CHILD_ROWS.length?`<div class="ls-card lsc-card">
            <div class="ls-card-head"><span class="ls-card-title">일자별 세부 일정</span><span class="ls-card-extra">${CHILD_ROWS.length}건</span></div>${rows}</div>`:'';
        return;
    }
    box.innerHTML=`<div class="ls-card lsc-card">
        <div class="ls-card-head"><span class="ls-card-title">일자별 세부 일정</span><span class="ls-card-extra">${CHILD_ROWS.length}건</span></div>
        ${rows||'<div class="lsc-empty">등록된 세부 일정이 없습니다. 기간 또는 개별 날짜를 골라 시간을 지정하세요.</div>'}
        <div class="lsc-form">
            <div class="lsc-form-top">
                <div class="lsc-seg">
                    <button id="chModeRange" onclick="chSetMode('range')">기간</button>
                    <button id="chModeDates" onclick="chSetMode('dates')">개별 날짜</button>
                </div>
                <span id="chEditBadge" class="lsc-edit-badge" style="display:none;">수정 중 — 저장 시 반영</span>
            </div>
            <div class="lsc-grid">
                <span class="lsc-lab">날짜</span>
                <span>
                    <span id="chRangeWrap" class="lsc-inline"><input type="date" id="chStart" class="field-input"><span class="lsc-tilde">~</span><input type="date" id="chEnd" class="field-input"></span>
                    <span id="chDatesWrap" class="lsc-inline" style="display:none;">
                        <input type="date" id="chDatePick" class="field-input">
                        <button class="lsc-mini-btn" onclick="chAddDate()">+ 추가</button>
                        <span id="chDateChips" class="lsc-inline"></span>
                    </span>
                </span>
                <span class="lsc-lab">시간</span>
                <span class="lsc-inline"><input type="time" id="chTimeS" class="field-input"><span class="lsc-tilde">~</span><input type="time" id="chTimeE" class="field-input"></span>
                <span class="lsc-lab">담당자</span>
                <span id="chAssigneeChips" class="lsc-inline"></span>
                <span class="lsc-lab">메모</span>
                <input type="text" id="chMemo" class="field-input lsc-memo" placeholder="메모 (선택)">
            </div>
            <div class="lsc-actions">
                <button id="chCancelBtn" class="lsc-btn-ghost" style="display:none;" onclick="chResetForm()">취소</button>
                <button id="chSubmitBtn" class="lsc-btn-primary" onclick="chSubmit()">추가</button>
            </div>
        </div>
    </div>`;
    CH_DATES=[]; chRenderAssignees(); chSetMode(CH_MODE);
    // 기간 모드 기본값: 부모 일정 범위 프리필 (빈 폼에서 바로 시간만 고르면 되도록)
    const pS=(ev.start_date||'').substring(0,10), pE=((ev.end_date||ev.start_date)||'').substring(0,10);
    if(chEl('chStart') && !chEl('chStart').value){ chEl('chStart').value=pS; chEl('chEnd').value=pE; }
    if(chEl('chDatePick')){ chEl('chDatePick').min=pS; chEl('chDatePick').max=pE; }
    if(chEl('chStart')){ chEl('chStart').min=pS; chEl('chStart').max=pE; chEl('chEnd').min=pS; chEl('chEnd').max=pE; }
}
// 'YYYY-MM-DD' → '7/1(수)' 짧은 표기
function chFmtDate(ds){
    if(!ds) return '';
    const d=new Date(ds+'T00:00:00');
    return `${d.getMonth()+1}/${d.getDate()}(${DAYS_KO[d.getDay()]})`;
}
let CH_DATES=[];
function chSetMode(m){
    CH_MODE=m;
    const r=chEl('chModeRange'), d=chEl('chModeDates');
    if(!r) return;
    r.classList.toggle('on',m==='range');
    d.classList.toggle('on',m==='dates');
    chEl('chRangeWrap').style.display=m==='range'?'flex':'none';
    chEl('chDatesWrap').style.display=m==='dates'?'flex':'none';
}
function chAddDate(){
    const v=chEl('chDatePick').value;
    if(v&&!CH_DATES.includes(v)){ CH_DATES.push(v); CH_DATES.sort(); chRenderDates(); }
}
function chRenderDates(){
    chEl('chDateChips').innerHTML=CH_DATES.map(d=>`<span class="lsc-date-chip">${chFmtDate(d)} <a onclick="CH_DATES=CH_DATES.filter(x=>x!=='${d}');chRenderDates()">✕</a></span>`).join('');
}
function chRenderAssignees(){
    const wrap=chEl('chAssigneeChips'); if(!wrap) return;
    wrap.innerHTML=(assignees||[]).filter(a=>a.is_active!==false).map(a=>{
        const idx=CH_ASSIGNEES.indexOf(a.id), on=idx!==-1;
        return `<button class="lsc-a-chip${on?' on':''}" onclick="chToggleAssignee(${a.id})">${on?(idx+1)+'. ':''}${_esc(a.name)}</button>`;
    }).join('');
}
function chToggleAssignee(id){
    const i=CH_ASSIGNEES.indexOf(id);
    if(i!==-1) CH_ASSIGNEES.splice(i,1); else CH_ASSIGNEES.push(id);
    chRenderAssignees();
}
function chResetForm(){ CH_EDIT_ID=null; CH_DATES=[]; CH_ASSIGNEES=[]; renderChildrenCard(); }
function chEdit(id){
    const c=CHILD_ROWS.find(x=>x.id===id); if(!c) return;
    CH_EDIT_ID=id; CH_MODE='range'; chSetMode('range');
    chEl('chStart').value=c.start_date;
    chEl('chEnd').value=c.end_date;
    chEl('chTimeS').value=c.start_time||'';
    chEl('chTimeE').value=c.end_time||'';
    chEl('chMemo').value=c.memo||'';
    CH_ASSIGNEES=(c.assignees||[]).map(a=>a.id); chRenderAssignees();
    chEl('chEditBadge').style.display='';
    chEl('chCancelBtn').style.display='';
    chEl('chSubmitBtn').textContent='저장';
}
async function chDelete(id){
    if(!confirm('이 세부 일정을 삭제할까요?')) return;
    const res=await fetch(`/api/events/children/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
    if(res.ok){ renderChildrenCard(); loadEvents(); } else alert('삭제 실패');
}
async function chSubmit(){
    const ts=chEl('chTimeS').value;
    if(!ts) return alert('시작 시간을 입력하세요.');
    const body={ start_time:ts, end_time:chEl('chTimeE').value||null,
        assignees:CH_ASSIGNEES, memo:chEl('chMemo').value.trim()||null };
    let url, method;
    if(CH_EDIT_ID){
        url=`/api/events/children/${CH_EDIT_ID}`; method='PATCH';
        body.start_date=chEl('chStart').value;
        body.end_date=chEl('chEnd').value||body.start_date;
        if(!body.start_date) return alert('날짜를 입력하세요.');
    } else if(CH_MODE==='dates'){
        if(!CH_DATES.length) return alert('날짜를 추가하세요.');
        url=`/api/events/${editingId}/children`; method='POST'; body.dates=CH_DATES;
    } else {
        body.start_date=chEl('chStart').value;
        body.end_date=chEl('chEnd').value||body.start_date;
        if(!body.start_date) return alert('시작 날짜를 입력하세요.');
        url=`/api/events/${editingId}/children`; method='POST';
    }
    const res=await fetch(url,{method,headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:JSON.stringify(body)});
    if(res.ok){ chResetForm(); loadEvents(); }
    else{ const e=await res.json().catch(()=>({})); alert(e.message||'저장 실패'); }
}

function lightboxNav(dir){
    lightboxIdx=(lightboxIdx+dir+lightboxImages.length)%lightboxImages.length;
    lbResetZoom();
    lbShow();
}

// 휠 줌
document.getElementById('lightbox').addEventListener('wheel',e=>{
    e.preventDefault();
    const delta=e.deltaY>0?-0.15:0.15;
    lbZoom=Math.min(LB_MAX_ZOOM,Math.max(LB_MIN_ZOOM,lbZoom+delta*lbZoom));
    if(lbZoom<1.05){lbPanX=0;lbPanY=0;}
    lbUpdateTransform(); lbShowZoomInfo();
},{passive:false});

// 더블클릭 줌 토글
document.getElementById('lightboxWrap').addEventListener('dblclick',e=>{
    e.preventDefault();
    if(lbZoom>1.05){lbResetZoom();}
    else{lbZoom=3;lbPanX=0;lbPanY=0;lbUpdateTransform();}
    lbShowZoomInfo();
});

// 드래그 팬
document.getElementById('lightboxWrap').addEventListener('mousedown',e=>{
    if(lbZoom<=1.05) return;
    e.preventDefault(); lbDragging=true; lbStartX=e.clientX-lbPanX; lbStartY=e.clientY-lbPanY;
    document.getElementById('lightboxWrap').classList.add('dragging');
});
document.addEventListener('mousemove',e=>{
    if(!lbDragging) return;
    lbPanX=e.clientX-lbStartX; lbPanY=e.clientY-lbStartY; lbUpdateTransform();
});
document.addEventListener('mouseup',()=>{
    if(!lbDragging) return;
    lbDragging=false;
    document.getElementById('lightboxWrap').classList.remove('dragging');
});

// 터치: 핀치 줌 + 한 손가락 팬 + (확대 안 됐을 때) 좌우 스와이프로 prev/next
let lbTouchMode=null, lbPinchStartDist=0, lbPinchStartZoom=1, lbTouchStartX=0, lbTouchStartY=0, lbPanStartX=0, lbPanStartY=0;
let lbSwipeActive=false, lbSwipeX=0, lbSwipeY=0;
function lbTouchDist(t){ const dx=t[0].clientX-t[1].clientX, dy=t[0].clientY-t[1].clientY; return Math.hypot(dx,dy); }
(function(){
    const wrap=document.getElementById('lightboxWrap');
    wrap.addEventListener('touchstart',e=>{
        lbSwipeActive=false;
        if(e.touches.length===2){
            lbTouchMode='pinch'; lbPinchStartDist=lbTouchDist(e.touches); lbPinchStartZoom=lbZoom;
            e.preventDefault();
        }else if(e.touches.length===1 && lbZoom>1.05){
            lbTouchMode='pan'; lbTouchStartX=e.touches[0].clientX; lbTouchStartY=e.touches[0].clientY;
            lbPanStartX=lbPanX; lbPanStartY=lbPanY;
        }else if(e.touches.length===1){
            // 확대되지 않은 상태 → 좌우 스와이프로 이미지 넘김
            lbTouchMode=null; lbSwipeActive=true;
            lbSwipeX=e.touches[0].clientX; lbSwipeY=e.touches[0].clientY;
        }else{ lbTouchMode=null; }
    },{passive:false});
    wrap.addEventListener('touchmove',e=>{
        if(lbTouchMode==='pinch' && e.touches.length===2){
            e.preventDefault();
            const d=lbTouchDist(e.touches);
            if(lbPinchStartDist>0){
                lbZoom=Math.min(LB_MAX_ZOOM,Math.max(LB_MIN_ZOOM,lbPinchStartZoom*(d/lbPinchStartDist)));
                if(lbZoom<1.05){lbPanX=0;lbPanY=0;}
                lbUpdateTransform(); lbShowZoomInfo();
            }
        }else if(lbTouchMode==='pan' && e.touches.length===1){
            e.preventDefault();
            lbPanX=lbPanStartX+(e.touches[0].clientX-lbTouchStartX);
            lbPanY=lbPanStartY+(e.touches[0].clientY-lbTouchStartY);
            lbUpdateTransform();
        }
    },{passive:false});
    wrap.addEventListener('touchend',e=>{
        // 좌우 스와이프 판정 (확대 안 된 상태, 손가락 모두 뗐을 때)
        if(lbSwipeActive && e.touches.length===0 && e.changedTouches.length){
            const dx=e.changedTouches[0].clientX-lbSwipeX, dy=e.changedTouches[0].clientY-lbSwipeY;
            if(lightboxImages.length>1 && Math.abs(dx)>50 && Math.abs(dx)>Math.abs(dy)*1.5){
                lightboxNav(dx<0?1:-1); // 왼쪽으로 밀면 다음
            }
            lbSwipeActive=false;
        }
        if(e.touches.length===0){ lbTouchMode=null; }
        else if(e.touches.length===1 && lbZoom>1.05){
            lbTouchMode='pan'; lbTouchStartX=e.touches[0].clientX; lbTouchStartY=e.touches[0].clientY;
            lbPanStartX=lbPanX; lbPanStartY=lbPanY;
        }
    },{passive:false});
})();

// 배경 클릭으로 닫기 (줌 안되어있을 때만)
document.getElementById('lightbox').addEventListener('click',e=>{
    if(e.target===document.getElementById('lightbox')&&lbZoom<=1.05) closeLightbox();
});

// 키보드
document.addEventListener('keydown',e=>{
    if(!document.getElementById('lightbox').classList.contains('open')) return;
    if(e.key==='Escape') closeLightbox();
    if(e.key==='ArrowLeft') lightboxNav(-1);
    if(e.key==='ArrowRight') lightboxNav(1);
    if(e.key==='+'||e.key==='='){lbZoom=Math.min(LB_MAX_ZOOM,lbZoom*1.3);lbUpdateTransform();lbShowZoomInfo();}
    if(e.key==='-'){lbZoom=Math.max(LB_MIN_ZOOM,lbZoom/1.3);if(lbZoom<1.05){lbPanX=0;lbPanY=0;}lbUpdateTransform();lbShowZoomInfo();}
    if(e.key==='0'){lbResetZoom();lbShowZoomInfo();}
});

// 이미지 그리드 클릭 이벤트 위임
document.addEventListener('click',e=>{
    // 요약 뷰 이미지: 요약 내 모든 이미지를 하나의 앨범으로 (prev/next 표시)
    const lsImg=e.target.closest('.ls-img-grid img');
    if(lsImg){
        e.preventDefault();
        const scope=document.getElementById('lockSummary')||document;
        const all=[...scope.querySelectorAll('.ls-img-grid img')].map(i=>({src:i.dataset.full||i.src,thumb:i.src,filename:i.alt||''}));
        const cur=lsImg.dataset.full||lsImg.src;
        openLightbox(cur, lsImg.alt||'', all, Math.max(0, all.findIndex(i=>i.src===cur)));
        return;
    }
    const img=e.target.closest('.img-item img');
    if(!img) return;
    e.preventDefault();
    const grid=img.closest('.img-grid');
    if(!grid) { openLightbox(img.dataset.full||img.src,img.alt||''); return; }
    const allImgs=[...grid.querySelectorAll('.img-item img')].map(i=>({src:i.dataset.full||i.src,thumb:i.src,filename:i.alt||i.closest('.img-item')?.querySelector('.img-filename')?.textContent||''}));
    const idx=[...grid.querySelectorAll('.img-item img')].indexOf(img);
    openLightbox(img.dataset.full||img.src,'',allImgs,idx);
});

// ── 캘린더 메뉴 (백업/내보내기) ──
function toggleCalMenu(){
    const m=document.getElementById('calMenu');
    m.style.display=m.style.display==='none'?'block':'none';
}
document.addEventListener('click',e=>{
    const m=document.getElementById('calMenu');
    if(m&&!e.target.closest('.cal-menu')&&!e.target.closest('[onclick*="toggleCalMenu"]')) m.style.display='none';
});
// ── 모바일 년/월 피커 (연.월▾ 탭 → < 2026년 > + 1~12월 칩) ──
let mpYearSel=null;
function toggleCalPicker(e){
    e?.stopPropagation();
    const p=document.getElementById('calMonthPicker');
    if(!p) return;
    const show=p.style.display==='none';
    if(show){ mpYearSel=currentYear; mpRender(); }
    p.style.display=show?'':'none';
}
function closeCalPicker(){ const p=document.getElementById('calMonthPicker'); if(p) p.style.display='none'; }
function mpYear(dir){ mpYearSel+=dir; mpRender(); }
function mpRender(){
    document.getElementById('mpYearLabel').textContent=mpYearSel+'년';
    document.getElementById('mpGrid').innerHTML=[...Array(12)].map((_,m)=>
        `<button type="button" class="${mpYearSel===currentYear&&m===currentMonth?'on':''}" onclick="mpPick(${m})">${m+1}월</button>`
    ).join('');
}
function mpPick(m){
    currentYear=mpYearSel; currentMonth=m;
    const d=new Date(mpYearSel,m,1);
    currentWeekStart=getWeekStart(d);
    currentDay=new Date(d); currentDay.setHours(0,0,0,0);
    multiWeekStart=getWeekStart(d);
    agendaWeekStart=null; agendaSelectedDate=fmt(d);
    closeCalPicker();
    renderView(); loadEvents();
}
document.addEventListener('click',e=>{
    const p=document.getElementById('calMonthPicker');
    if(p&&p.style.display!=='none'&&!e.target.closest('#calMonthPicker')&&!e.target.closest('.cal-mini-period')) closeCalPicker();
});
// 모바일 미니 연.월 라벨 — periodTitle 변경 시 동기화 (연.월 전체 표시)
function syncMiniPeriod(){
    const mini=document.getElementById('periodTitleMini');
    const full=document.getElementById('periodTitle');
    if(mini&&full) mini.textContent=full.textContent||'';
}
async function importFile(type, input){
    const file=input.files[0];
    if(!file) return;
    input.value='';

    // iCal: 기준일 입력 → dry-run으로 카테고리 매핑 요약 확인 → 실제 가져오기
    let importUntil='';
    if(type==='ical'){
        const u=prompt('어느 날짜까지 가져올까요? (YYYY-MM-DD, 비우면 전체)\n이후에 시작하는 일정은 제외됩니다.','2026-06-30');
        if(u===null) return; // 취소
        importUntil=(u||'').trim();
        if(importUntil && !/^\d{4}-\d{2}-\d{2}$/.test(importUntil)){ showCalToast('날짜 형식이 올바르지 않습니다 (YYYY-MM-DD)'); return; }

        const dryFd=new FormData();
        dryFd.append('file',file);
        dryFd.append('dry','1');
        if(importUntil) dryFd.append('until',importUntil);
        const dryRes=await fetch('/api/events/import/ical',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:dryFd});
        if(!dryRes.ok){ const d=await dryRes.json().catch(()=>({})); showCalToast(d.error||d.message||'파일 분석 실패'); return; }
        const s=await dryRes.json();
        const catLines=Object.entries(s.by_category||{}).map(([l,n])=>`  · ${l}: ${n}건`).join('\n');
        let msg=`총 ${s.total}건 분석 결과:\n${catLines}`;
        if(s.skipped_after) msg+=`\n\n${importUntil} 이후 시작 ${s.skipped_after}건 제외`;
        if(s.skipped_holiday) msg+=`\n공휴일 ${s.skipped_holiday}건 제외 (앱 자동 표시와 중복)`;
        if(s.duplicates) msg+=`\n이미 가져온 일정 ${s.duplicates}건 스킵`;
        if(s.repaired) msg+=`\n기존 일정 ${s.repaired}건은 내용이 보이는 위치로 이동됩니다`;
        if(s.rrule_count) msg+=`\n반복 규칙 ${s.rrule_count}건은 첫 회차만 가져옴`;
        if((s.will_create_categories||[]).length) msg+=`\n새 카테고리 생성: ${s.will_create_categories.join(', ')}`;
        if((s.unmapped||[]).length) msg+=`\n미매칭(사내업무로 지정): ${s.unmapped.slice(0,5).join(' / ')}`;
        msg+='\n\n이대로 가져올까요?';
        if(!confirm(msg)) return;
    }

    const fd=new FormData();
    fd.append('file',file);
    if(importUntil) fd.append('until',importUntil);
    const res=await fetch(`/api/events/import/${type}`,{
        method:'POST',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:fd
    });
    if(res.ok){ const d=await res.json(); showCalToast(d.message); loadEvents(); }
    else { const d=await res.json().catch(()=>({})); showCalToast(d.error||'가져오기 실패'); }
}

// ── 드래그앤드롭 (데스크탑 월간뷰) ──
let dragEvent=null, dragGhost=null, dragStartDate=null, dragStartX=0, dragStartY=0, isDragging=false;
const DRAG_COLORS={gold:'var(--chip-gold-bg)',teal:'var(--chip-teal-bg)',blue:'var(--chip-blue-bg)',red:'var(--chip-red-bg)',green:'var(--chip-green-bg)',purple:'var(--chip-purple-bg)'};

function dragStart(ev, e){
    if(window.innerWidth<=768||!canEditCalendar||ev.is_locked) return;
    e.preventDefault();
    dragEvent=ev;
    dragStartDate=ev.start_date;
    dragStartX=e.clientX; dragStartY=e.clientY;
    isDragging=false;
}

function dragActivate(e){
    if(isDragging) return;
    isDragging=true;
    dragGhost=document.createElement('div');
    dragGhost.className='drag-ghost';
    dragGhost.style.background=DRAG_COLORS[dragEvent.color]||'var(--accent)';
    dragGhost.style.color='#fff';
    dragGhost.textContent=dragEvent.title||'(제목 없음)';
    dragGhost.style.left=(e.clientX+12)+'px';
    dragGhost.style.top=(e.clientY-12)+'px';
    document.body.appendChild(dragGhost);
    document.body.classList.add('dragging');
}

document.addEventListener('mousemove',e=>{
    if(!dragEvent) return;
    // 5px 이상 움직여야 드래그 활성화
    if(!isDragging){
        if(Math.abs(e.clientX-dragStartX)+Math.abs(e.clientY-dragStartY)<5) return;
        dragActivate(e);
    }
    dragGhost.style.left=(e.clientX+12)+'px';
    dragGhost.style.top=(e.clientY-12)+'px';
    document.querySelectorAll('.day-cell.drop-target').forEach(c=>c.classList.remove('drop-target'));
    const el=document.elementFromPoint(e.clientX,e.clientY);
    const cell=el?.closest('.day-cell[data-date]');
    if(cell) cell.classList.add('drop-target');
});

document.addEventListener('mouseup',async e=>{
    if(!dragEvent) return;
    document.querySelectorAll('.day-cell.drop-target').forEach(c=>c.classList.remove('drop-target'));
    document.body.classList.remove('dragging');
    if(dragGhost){ dragGhost.remove(); dragGhost=null; }
    if(!isDragging){ dragEvent=null; return; } // 드래그 안 했으면 클릭으로 처리
    isDragging=false;
    const el=document.elementFromPoint(e.clientX,e.clientY);
    const cell=el?.closest('.day-cell[data-date]');
    const targetDate=cell?.dataset.date;
    if(!targetDate||targetDate===dragStartDate){ dragEvent=null; return; }
    // 날짜 차이 계산 (다일 이벤트 대응)
    const diff=Math.round((new Date(targetDate+'T00:00:00')-new Date(dragStartDate+'T00:00:00'))/(1000*60*60*24));
    const ev=dragEvent;
    const newStart=shiftDate(ev.start_date, diff);
    const newEnd=ev.end_date?shiftDate(ev.end_date, diff):newStart;
    dragEvent=null;
    // API 호출 — 변경 사유 필수이므로 드래그 이동 사유를 자동 첨부
    const res=await fetch(`/api/events/${ev.id}`,{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({...buildEventPayload(ev), start_date:newStart, end_date:newEnd, reason:`드래그 이동: ${dragStartDate} → ${targetDate}`})
    });
    if(res.ok){ showCalToast('일정이 이동되었습니다'); loadEvents(); }
    else { const er=await res.json().catch(()=>({})); showCalToast(er.message||'이동 실패'); }
});

function shiftDate(dateStr, days){
    const d=new Date(dateStr+'T00:00:00');
    d.setDate(d.getDate()+days);
    return fmt(d); // 로컬 기준 YYYY-MM-DD (toISOString 사용 시 UTC로 하루 밀림)
}

// ── 월간 뷰: 빈 셀 드래그로 기간 선택 → 새 일정(기간) 생성 ──
let rangeSelecting=false, rangeStartDate=null, rangeEndDate=null, rangeMoved=false, suppressCellClick=false;
function calRangeCells(){ return [...document.querySelectorAll('#daysGrid .day-cell[data-date]')]; }
function highlightRange(){
    if(!rangeStartDate||!rangeEndDate) return;
    const [a,b]=[rangeStartDate,rangeEndDate].sort();
    calRangeCells().forEach(c=>{ const d=c.dataset.date; const inR=d>=a && d<=b;
        c.classList.toggle('range-sel', inR);
        c.classList.toggle('range-start', inR && d===a);
        c.classList.toggle('range-end', inR && d===b); });
}
function clearRangeHighlight(){ calRangeCells().forEach(c=>c.classList.remove('range-sel','range-start','range-end')); }
document.addEventListener('mousedown', e=>{
    if(window.innerWidth<=768 || !canEditCalendar || e.button!==0) return;
    if(dragEvent) return; // 일정 이동 중이면 제외
    if(e.target.closest('.event-chip')||e.target.closest('.more-badge')) return; // 칩/더보기 제외
    if(e.target.closest('.mday-title-overlay')) return;
    const cell=e.target.closest('#daysGrid .day-cell[data-date]');
    if(!cell) return;
    rangeSelecting=true; rangeMoved=false;
    rangeStartDate=cell.dataset.date; rangeEndDate=cell.dataset.date;
});
document.addEventListener('mousemove', e=>{
    if(!rangeSelecting) return;
    const cell=document.elementFromPoint(e.clientX,e.clientY)?.closest('#daysGrid .day-cell[data-date]');
    if(!cell) return;
    if(cell.dataset.date!==rangeEndDate){ rangeEndDate=cell.dataset.date; rangeMoved=true; }
    document.body.classList.add('range-dragging');
    highlightRange();
});
document.addEventListener('mouseup', ()=>{
    if(!rangeSelecting) return;
    rangeSelecting=false;
    document.body.classList.remove('range-dragging');
    clearRangeHighlight();
    if(!rangeMoved) return; // 드래그 안 함 → 기존 클릭(단일일) 처리에 맡김
    const [a,b]=[rangeStartDate,rangeEndDate].sort();
    suppressCellClick=true;
    openNewModal(a, null, b);
});

function buildEventPayload(ev){
    return {
        title:ev.title, start_date:ev.start_date, end_date:ev.end_date||ev.start_date,
        start_time:ev.start_time, end_time:ev.end_time, is_all_day:ev.is_allday||ev.is_all_day||false,
        exclude_weekends:!!ev.exclude_weekends,
        ship_icon_override:ev.ship_icon_override||null,
        color:ev.color, client_name:ev.client_name||'', address:ev.address||'', location:ev.location||'',
        description:ev.description||'', notif_minutes:ev.notif_minutes||null,
        is_locked:ev.is_locked||false, is_private:ev.is_private||false,
        sched_opt:ev.sched_opt||null, sched_event_opts:ev.sched_event_opts||[],
        special_opts:ev.special_opts||[], sched_after_reason:ev.sched_after_reason||null,
        request_data:ev.request_data||null, remote_data:ev.remote_data||null,
        assignees:ev.assignees?ev.assignees.map(a=>a.id||a):[],
    };
}

function showCalToast(msg){
    const t=document.createElement('div');
    t.style.cssText='position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--accent);color:var(--accent-text);padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:9999;';
    t.textContent=msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),2000);
}

// === 휴지통 ===
let trashItems = [];
async function openTrashModal() {
    document.getElementById('trashOverlay').style.display = 'flex';
    document.getElementById('trashBody').innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-muted);">로딩 중...</div>';
    const res = await fetch('/api/events/trashed', {headers:{'Accept':'application/json'}});
    if (!res.ok) { document.getElementById('trashBody').innerHTML = '<div style="padding:30px; text-align:center; color:var(--red);">불러오기 실패</div>'; return; }
    trashItems = await res.json();
    renderTrash();
}
function renderTrash() {
    const body = document.getElementById('trashBody');
    document.getElementById('trashCount').textContent = `${trashItems.length}건`;
    if (!trashItems.length) {
        body.innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">휴지통이 비어 있습니다.</div>';
        document.getElementById('trashSelectAll').checked = false;
        updateTrashButtons();
        return;
    }
    body.innerHTML = trashItems.map(it => `
        <div style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-bottom:1px solid var(--border);">
            <input type="checkbox" class="trash-check" data-id="${it.id}" onchange="updateTrashButtons()">
            <div style="flex:1; min-width:0;">
                <div style="font-size:13px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escHtml(it.title||'(제목 없음)')}</div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${it.start_date}${it.end_date && it.end_date !== it.start_date ? ' ~ '+it.end_date : ''} · ${escHtml(it.client_name||'')} · ${escHtml(it.creator||'-')} 등록 · ${it.deleted_at} 삭제</div>
            </div>
            <button class="nav-btn" style="font-size:11px; width:auto; padding:3px 8px;" onclick="trashRestoreOne(${it.id})">복원</button>
            <button class="nav-btn" style="font-size:11px; width:auto; padding:3px 8px; color:var(--red); border-color:var(--red);" onclick="trashForceOne(${it.id})">영구삭제</button>
        </div>
    `).join('');
    document.getElementById('trashSelectAll').checked = false;
    updateTrashButtons();
}
function trashToggleAll(checked) {
    document.querySelectorAll('.trash-check').forEach(c => c.checked = checked);
    updateTrashButtons();
}
function updateTrashButtons() {
    const ids = [...document.querySelectorAll('.trash-check:checked')].map(c => +c.dataset.id);
    document.getElementById('trashRestoreBtn').disabled = !ids.length;
    document.getElementById('trashClearBtn').disabled = !ids.length;
}
function getTrashSelectedIds() {
    return [...document.querySelectorAll('.trash-check:checked')].map(c => +c.dataset.id);
}
async function trashRestoreOne(id) {
    const res = await fetch(`/api/events/${id}/restore`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    if (!res.ok) { showCalToast('복원 실패'); return; }
    showCalToast('복원되었습니다');
    openTrashModal();
    loadEvents();
}
async function trashRestoreSelected() {
    const ids = getTrashSelectedIds();
    if (!ids.length || !confirm(`${ids.length}건을 복원합니다. 계속할까요?`)) return;
    for (const id of ids) {
        await fetch(`/api/events/${id}/restore`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    }
    showCalToast(`${ids.length}건 복원 완료`);
    openTrashModal();
    loadEvents();
}
async function trashForceOne(id) {
    if (!confirm('이 일정을 영구 삭제합니다. 복구할 수 없습니다. 계속할까요?')) return;
    const res = await fetch(`/api/events/${id}/force`, {method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    if (!res.ok) { showCalToast('삭제 실패'); return; }
    showCalToast('영구 삭제됨');
    openTrashModal();
}
async function trashEmptySelected() {
    const ids = getTrashSelectedIds();
    if (!ids.length || !confirm(`선택한 ${ids.length}건을 영구 삭제합니다. 복구할 수 없습니다. 계속할까요?`)) return;
    const res = await fetch('/api/events/trash/empty', {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', 'Accept':'application/json'}, body:JSON.stringify({ids})});
    if (!res.ok) { showCalToast('실패'); return; }
    showCalToast(`${ids.length}건 영구 삭제 완료`);
    openTrashModal();
}
async function trashEmptyAll() {
    if (!confirm('휴지통 전체를 비웁니다. 모든 항목이 영구 삭제되며 복구할 수 없습니다. 계속할까요?')) return;
    const res = await fetch('/api/events/trash/empty', {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', 'Accept':'application/json'}, body:JSON.stringify({})});
    if (!res.ok) { showCalToast('실패'); return; }
    showCalToast('휴지통이 비워졌습니다');
    openTrashModal();
}
function escHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// === 일정 완료 토글 ===
async function toggleCompleteFromDetail() {
    if (!detailEvent) return;
    const id = detailEvent.id;
    const isCompleted = !!detailEvent.completed_at;
    const url = `/api/events/${id}/${isCompleted ? 'uncomplete' : 'complete'}`;
    const res = await fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF, 'Accept':'application/json'}});
    if (!res.ok) { showCalToast('실패'); return; }
    showCalToast(isCompleted ? '완료 해제' : '완료 처리');
    viewMode=false; closeModal(); detailEvent=null; // 보기/편집 모드 공통으로 편집 모달 닫기
    loadEvents();
}

// ── 상세 내용 자동 높이(최대 400px, 초과 시 스크롤) ──
const CAL_AUTOGROW_IDS = ['commonDesc', 'commonHandoverNote', 't_desc', 't_remote_content', 't_studio_content', 'g_req_detail', 'g_special'];
function calAutoGrow(el) {
    if (!el) return;
    el.style.height = 'auto';
    const h = Math.min(el.scrollHeight, 400);
    el.style.height = h + 'px';
    el.style.overflowY = el.scrollHeight > 400 ? 'auto' : 'hidden';
}
function calRefreshAutoGrow() { CAL_AUTOGROW_IDS.forEach(id => calAutoGrow(document.getElementById(id))); }
CAL_AUTOGROW_IDS.forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.classList.add('autogrow'); el.addEventListener('input', () => calAutoGrow(el)); }
});

renderVisitOpts();
init();
