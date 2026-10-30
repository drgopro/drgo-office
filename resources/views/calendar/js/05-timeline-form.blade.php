{{-- 주간/일간 타임라인·라디오·색상 전환·반복·담당자 --}}
// ── 주간/일간 타임라인 ───────────────────────────────────────────
function renderTimeline() {
    const ts=todayStr();
    let cols=[];
    if(currentView==='week'){
        for(let i=0;i<7;i++){
            const d=new Date(currentWeekStart); d.setDate(d.getDate()+i); cols.push(d);
        }
        const ws=cols[0],we=cols[6];
        document.getElementById('periodTitle').textContent=
            `${ws.getFullYear()}년 ${ws.getMonth()+1}월 ${ws.getDate()}일 ~ ${we.getMonth()+1}월 ${we.getDate()}일`;
    } else {
        cols=[currentDay];
        document.getElementById('periodTitle').textContent=
            `${currentDay.getFullYear()}년 ${currentDay.getMonth()+1}월 ${currentDay.getDate()}일 (${DAYS_KO[currentDay.getDay()]})`;
    }

    const grid=document.getElementById('timelineGrid'); grid.innerHTML='';

    // 헤더
    const header=document.createElement('div');
    header.className='tl-header';
    const th0=document.createElement('div'); th0.className='tl-time-col'; header.appendChild(th0);
    cols.forEach(d=>{
        const dow=d.getDay();
        const isToday=fmt(d)===ts;
        const holiday=getHoliday(fmt(d)); // 공휴일 — 주/일간 뷰에도 표시
        const cell=document.createElement('div');
        cell.className='tl-day-col'+(isToday?' today-col':'');
        const nc=dow===0||holiday?'sun-c':dow===6?'sat-c':'';
        cell.innerHTML=`<div class="tl-day-name">${DAYS_KO[dow]}${holiday?` <span class="tl-holiday">${holiday}</span>`:''}</div>
            <div class="tl-day-num ${nc} ${isToday?'today-num':''}">${d.getDate()}</div>`;
        // 일간 뷰에서 날짜 클릭 → 월간으로 이동
        if(currentView==='day') cell.style.cursor='default';
        header.appendChild(cell);
    });
    grid.appendChild(header);

    // 종일 행
    const alldayRow=document.createElement('div');
    alldayRow.className='tl-allday-row';
    const alldayLabel=document.createElement('div');
    alldayLabel.className='tl-allday-label'; alldayLabel.textContent='종일';
    alldayRow.appendChild(alldayLabel);
    cols.forEach(d=>{
        const ds=fmt(d);
        const isToday=ds===ts;
        const cell=document.createElement('div');
        cell.className='tl-allday-cell'+(isToday?' today-col':'');
        events.filter(ev=>isFiltered(ev)&&ev.is_all_day&&evCoversDate(ev,ds)).sort((a,b)=>isTopEv(b)-isTopEv(a)).forEach(ev=>{
            const chip=document.createElement('div');
            chip.className=`event-chip color-${ev.color}`+(ev.completed_at?' is-completed':'');
            chip.style.marginBottom='2px';
            chip.innerHTML=isGuestUser?_esc((ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):'')):(eventOptIconsHtml(ev)+`<span class="chip-title">${_esc(ev.title||'')}</span>`+schedStatusChip(ev)+shipStatusIcon(ev)+((ev.assignees||[]).length?` <span class="ev-assignee-badge">${_esc(assigneeNamesOf(ev).join(', '))}</span>`:''));
            chip.onclick=()=>openDetailModal(ev);
            cell.appendChild(chip);
        });
        // 자정 넘김(여러 날) 시간 일정의 이어지는 날 — 시간 그리드엔 그릴 수 없으니 종일 행에 '~03:00 종료' 칩으로 표시
        events.filter(ev=>isFiltered(ev)&&!ev.is_all_day&&(ev.start_date||'').substring(0,10)<ds&&evCoversDate(ev,ds))
            .sort((a,b)=>(evTimeOn(a,ds)||'99:99').localeCompare(evTimeOn(b,ds)||'99:99')).forEach(ev=>{
            const endsHere=ds===evEnd(ev)&&ev.end_time;
            const tag=endsHere?`~${ev.end_time.slice(0,5)} 종료`:'계속';
            const chip=document.createElement('div');
            chip.className=`event-chip color-${ev.color}`+(ev.completed_at?' is-completed':'');
            chip.style.marginBottom='2px';
            chip.innerHTML=isGuestUser?_esc(`${tag} `+(ev.location||'일정')):(`<span style="opacity:0.8;">${tag}</span> `+eventOptIconsHtml(ev)+`<span class="chip-title">${_esc(ev.title||'')}</span>`+schedStatusChip(ev)+shipStatusIcon(ev));
            chip.onclick=()=>openDetailModal(ev);
            cell.appendChild(chip);
        });
        cell.addEventListener('click',e=>{if(e.target===cell)openNewModal(ds);});
        alldayRow.appendChild(cell);
    });
    grid.appendChild(alldayRow);

    // 시간 슬롯
    // 날짜별로 시간 일정의 겹침을 계산해 컬럼(좌우 분할) 배치를 미리 구한다.
    // id → { col, total } (col: 0부터, total: 그 겹침 묶음의 컬럼 수)
    const dayLayouts = {};
    cols.forEach(d=>{
        const ds=fmt(d);
        const items = events
            .filter(ev=>isFiltered(ev) && !ev.is_all_day && ev.start_date===ds && ev.start_time)
            .map(ev=>{
                const [sh,sm]=ev.start_time.split(':').map(Number);
                let eh, em;
                if(ev.end_time){ [eh,em]=ev.end_time.split(':').map(Number); }
                else { eh=sh+1; em=sm; }
                let start=sh*60+sm, end=eh*60+em;
                if(evEnd(ev)>ds) end=23*60; // 자정 넘김: 시작일엔 하루 끝까지
                else if(end<=start) end=start+30; // 종료<=시작이면 최소 30분
                return { ev, start, end, col:0, total:1 };
            })
            .sort((a,b)=> a.start-b.start || a.end-b.end);

        const layout={};
        let cluster=[], clusterEnd=-1;
        const flush=(grp)=>{
            const lanes=[]; // 각 레인의 마지막 종료시각
            grp.forEach(it=>{
                let placed=false;
                for(let i=0;i<lanes.length;i++){
                    if(lanes[i]<=it.start){ lanes[i]=it.end; it.col=i; placed=true; break; }
                }
                if(!placed){ it.col=lanes.length; lanes.push(it.end); }
            });
            const total=lanes.length||1;
            grp.forEach(it=> layout[it.ev.id]={col:it.col, total});
        };
        items.forEach(it=>{
            if(cluster.length && it.start>=clusterEnd){ flush(cluster); cluster=[]; clusterEnd=-1; }
            cluster.push(it); clusterEnd=Math.max(clusterEnd, it.end);
        });
        if(cluster.length) flush(cluster);
        dayLayouts[ds]=layout;
    });

    // ── 반응형: 시간대 행 높이를 화면에 맞게 늘림 (48px 하한) ──
    let TL_HH=48;
    if(window.innerWidth>768){
        const gTop=grid.getBoundingClientRect().top;
        const used=grid.offsetHeight; // 헤더 + 종일 행
        TL_HH=Math.max(48, Math.floor((window.innerHeight-gTop-used-28)/HOURS.length));
    }
    grid.style.setProperty('--tl-hh', TL_HH+'px');

    HOURS.forEach(hour=>{
        const row=document.createElement('div');
        row.className='tl-row';
        const label=document.createElement('div');
        label.className='tl-time-label';
        label.textContent=`${hour}:00`;
        row.appendChild(label);
        cols.forEach(d=>{
            const ds=fmt(d);
            const isToday=ds===ts;
            const slot=document.createElement('div');
            slot.className='tl-slot'+(isToday?' today-col':'');
            events.filter(ev=>{
                if(!isFiltered(ev)) return false;
                if(ev.is_all_day) return false;
                if(ev.start_date!==ds) return false;
                const h=ev.start_time?parseInt(ev.start_time.split(':')[0]):null;
                return h===hour;
            }).forEach(ev=>{
                const el=document.createElement('div');
                el.className=`tl-event color-${ev.color}`+(ev.completed_at?' is-completed':'');
                const sm=ev.start_time?parseInt(ev.start_time.split(':')[1]):0;
                let eh=ev.end_time?parseInt(ev.end_time.split(':')[0]):hour+1;
                let em=ev.end_time?parseInt(ev.end_time.split(':')[1]):0;
                if(evEnd(ev)>ds){ eh=23; em=0; } // 자정 넘김: 시작일엔 하루 끝까지 그린다
                const dur=(eh+em/60)-(hour+sm/60);
                el.style.top=`${(sm/60)*TL_HH}px`;
                el.style.height=`${Math.max(dur*TL_HH,20)}px`;
                // 겹치는 일정은 셀을 좌우로 분할해 서로 덮지 않게
                const lo=(dayLayouts[ds]||{})[ev.id];
                if(lo && lo.total>1){
                    const gap=2; // px
                    const widthPct=100/lo.total;
                    el.style.left=`calc(${widthPct*lo.col}% + ${gap}px)`;
                    el.style.width=`calc(${widthPct}% - ${gap*2}px)`;
                    el.style.right='auto';
                }
                el.innerHTML=`<div class="tl-ev-head">${eventOptIconsHtml(ev)}<span class="tl-ev-title">${_esc(ev.title||'')}</span>${schedStatusChip(ev)}${shipStatusIcon(ev)}</div>`+((ev.assignees||[]).length?`<div class="tl-ev-assignee">${_esc(assigneeNamesOf(ev).join(', '))}</div>`:'');
                el.onclick=e=>{e.stopPropagation();openDetailModal(ev);};
                slot.appendChild(el);
            });
            slot.addEventListener('click',e=>{
                if(e.target===slot) openNewModal(ds,`${String(hour).padStart(2,'0')}:00`);
            });
            row.appendChild(slot);
        });
        grid.appendChild(row);
    });
}

// ── 라디오 그룹 헬퍼 ──────────────────────────────────────────
const COLOR_NAMES = (function(){
    const o = {holiday:'공휴일'};
    const cats = window.CALENDAR_CATEGORIES || {};
    Object.keys(cats).forEach(k => { o[k] = cats[k].label; });
    return o;
})();
let isAllDay=false, isLocked=false, linkedEstimateIds=[], linkedEstimateMeta={}; // 다중 견적서 연동 (id 배열 + 표시용 메타)

function initRadioGroup(gid, opts){
    const g=document.getElementById(gid); if(!g) return;
    const multi=opts?.multi||false;
    g.querySelectorAll('.radio-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
            if(isLocked) return;
            if(multi){btn.classList.toggle('active');}
            else{
                const wasActive=btn.classList.contains('active');
                g.querySelectorAll('.radio-btn').forEach(b=>b.classList.remove('active','active-red','active-green'));
                if(!wasActive) btn.classList.add('active');
            }
            // conditional field 토글
            handleConditional(gid);
            if(opts?.onChange) opts.onChange(getRadio(gid));
        });
    });
}
function getRadio(gid){
    const g=document.getElementById(gid); if(!g) return '';
    const a=g.querySelector('.radio-btn.active');
    return a?a.dataset.val||a.dataset.sopt||a.dataset.seopt||a.dataset.opt:'';
}
function getMultiRadio(gid){
    const g=document.getElementById(gid); if(!g) return [];
    return [...g.querySelectorAll('.radio-btn.active')].map(b=>b.dataset.val||b.dataset.sopt||b.dataset.seopt||b.dataset.opt);
}
function setRadio(gid,val){
    const g=document.getElementById(gid); if(!g) return;
    g.querySelectorAll('.radio-btn').forEach(b=>{
        b.classList.remove('active','active-red','active-green');
        if(b.dataset.val===val||b.dataset.sopt===val||b.dataset.seopt===val||b.dataset.opt===val) b.classList.add('active');
    });
    handleConditional(gid);
}
function setMultiRadio(gid,vals){
    const g=document.getElementById(gid); if(!g||!vals) return;
    const arr=Array.isArray(vals)?vals:vals.split(',').map(v=>v.trim());
    g.querySelectorAll('.radio-btn').forEach(b=>{
        b.classList.toggle('active',arr.includes(b.dataset.val||b.dataset.sopt||b.dataset.seopt||b.dataset.opt));
    });
    handleConditional(gid);
}
function clearRadio(gid){
    const g=document.getElementById(gid); if(!g) return;
    g.querySelectorAll('.radio-btn').forEach(b=>b.classList.remove('active','active-red','active-green'));
}

function handleConditional(gid){
    // 기타 → 직접입력 필드
    const condMap={'g_platform_group':'g_platform_etc_wrap','g_topic_group':'g_topic_etc_wrap','g_budget_group':'g_budget_etc_wrap','g_source_group':'g_source_ref_wrap','g_req_topic_group':'g_req_topic_etc_wrap'};
    if(condMap[gid]){
        const g=document.getElementById(gid);
        const wrap=document.getElementById(condMap[gid]);
        if(!g||!wrap) return;
        // 유입 경로의 부가 입력(소개해 준 분)은 '소개'에서만 — 기타 pill 추가로 오작동 방지
        const triggerVals=gid==='g_source_group'?['소개']:['기타','직접입력','소개'];
        const hasMatch=[...g.querySelectorAll('.radio-btn.active')].some(b=>triggerVals.includes(b.dataset.val));
        wrap.classList.toggle('visible',hasMatch);
    }
    // 의뢰주제 이사세팅 체크/해제 → 출발지 입력 토글 동기화 (토글은 수동으로도 켜고 끌 수 있음)
    if(gid==='g_req_topic_group'){
        syncMoveToggleWithTopic();
    }
    // (배송완료 O/X 필드는 제거됨 — 제목 배송 아이콘 수동 지정으로 대체)
    // 잔금 O → 금액
    if(gid==='g_balance_group'){
        const v=getRadio('g_balance_group');
        const cond=document.getElementById('g_balance_amount_wrap');
        if(cond) cond.classList.toggle('visible',v==='O');
        // X 로 바뀌면 입력값 비우기 (저장 시 잔여 데이터 방지)
        if(v!=='O'){
            const amt=document.getElementById('g_balance_amount');
            if(amt) amt.value='';
        }
        updateBalanceBanner();
    }
}

// 스튜디오/촬영 카테고리 판정 — 라벨 매칭 (커스텀 키 대응). 시기요청·특수옵션·외부 오퍼레이터·견적서 연동 노출 기준
function isStudioCategory(c){
    return Object.keys(CS_CATS).some(k=>k===c&&/스튜디오|촬영/.test((CS_CATS[k]&&CS_CATS[k].label)||''));
}
// 견적서 연동 블록 노출 기준 — 의뢰자 연결이 가능한 모든 카테고리(촬영·스튜디오·미팅/내방·원격/방송룸·커스텀).
// gold는 첨부 '견적서' 영역에 같은 기능이 있어 제외, 사내업무/휴가는 의뢰자 연결 자체가 없음
function supportsEstimateLinkSection(c){
    if(c==='gold') return false;
    return typeof colorSupportsClientLink==='function' ? colorSupportsClientLink(c) : !['blue','red'].includes(c);
}
// ── 색상 전환 ──
function setColor(c){
    currentColor=c;
    // color dots
    document.querySelectorAll('.color-dot').forEach(d=>{d.classList.toggle('active',d.dataset.color===c);});
    // strip
    const strip=document.getElementById('modalStrip');
    strip.className='modal-strip'+(c!=='gold'?' color-'+c:'');
    // type badge
    const badge=document.getElementById('typeBadge');
    badge.className='type-badge '+c;
    badge.textContent='● '+(COLOR_NAMES[c]||c);
    // 템플릿 토글
    document.querySelectorAll('.gold-only').forEach(s=>s.style.display=c==='gold'?'flex':'none');
    document.querySelectorAll('.teal-only').forEach(s=>s.style.display=c==='teal'?'flex':'none');
    document.querySelectorAll('.common-only').forEach(s=>s.style.display=(c!=='gold'&&c!=='teal')?'flex':'none');
    // 일정 옵션 카드 — 확정 상태는 모든 카테고리에서 사용
    // 시기 요청·특수 옵션: 방문의뢰(gold) + 스튜디오/촬영 카테고리 (라벨 매칭 — 커스텀 키 대응)
    const soCard=document.getElementById('schedOptCard');
    if(soCard) soCard.style.display='flex';
    const isStudioCat=isStudioCategory(c);
    const optExtra=c==='gold'||isStudioCat;
    // 견적서 연동 블록 — 의뢰자 연결 가능한 비-gold 카테고리 전부 (gold는 첨부 '견적서' 영역에 동일 기능)
    const seEst=document.getElementById('studioEstimateSection');
    if(seEst) seEst.style.display=supportsEstimateLinkSection(c)?'':'none';
    const seSec=document.getElementById('schedEventSection');
    if(seSec) seSec.style.display=optExtra?'':'none';
    const spGrp=document.getElementById('specialOptsGroup');
    if(spGrp) spGrp.style.display=optExtra?'':'none';
    // 외부 오퍼레이터 체크는 스튜디오/촬영 카테고리에서만 노출 — 다른 카테고리로 바꾸면 체크도 해제
    const extBtn=document.getElementById('extOperatorBtn');
    if(extBtn){
        extBtn.style.display=isStudioCat?'':'none';
        if(!isStudioCat) extBtn.classList.remove('active');
        applyExtOperatorUI();
    }
    // 사내업무(blue)/휴가·개인(red)은 의뢰자 검색 불필요 → 섹션 숨김
    const clientSec=document.getElementById('clientLinkSection');
    if(clientSec) clientSec.style.display=(c==='blue'||c==='red')?'none':'';
    // 연차 차감은 휴가/개인(red) 전용
    const ldRow=document.getElementById('leaveDeductRow');
    if(ldRow){
        ldRow.style.display=c==='red'?'':'none';
        if(c!=='red'){
            const chk=document.getElementById('leaveDeductChk');
            if(chk){ chk.checked=false; document.getElementById('leaveDeductType').style.display='none'; }
        }
    }
    // 사내업무/휴가·개인은 장소가 필수 아님 → 필수 표시(*) 숨김
    const addrReq=document.getElementById('addrReqMark');
    if(addrReq) addrReq.style.display=(c==='blue'||c==='red')?'none':'';
    // gold 전용 날짜 행
    document.getElementById('standardDtRows').style.display=c==='gold'?'none':'';
    document.getElementById('goldDtRow').style.display=c==='gold'?'flex':'none';
    updateBalanceBanner();
    applyVisitOptsUI();
    updateShipmentSectionVisibility();
    if(typeof updateBrRentalUI==='function') updateBrRentalUI();
    if(typeof updateMoveSettingUI==='function') updateMoveSettingUI();
    if(typeof updateReasonFieldVisibility==='function') updateReasonFieldVisibility();
    // 방문의뢰(gold)는 첨부 이미지 카드에 '첨부 파일' 그룹이 이미 있어 하단 공통 첨부 카드는 숨김 (중복 제거)
    // — 두 곳 모두 같은 general 타입을 쓰므로 기존 첨부는 카드 내 그룹에 그대로 표시된다
    const gAttSec=document.getElementById('generalAttachSection');
    if(gAttSec) gAttSec.style.display=(c==='gold')?'none':'';
    // 연동 프로젝트 첨부 문서 — 방문의뢰는 첨부 이미지 그룹 안에, 그 외는 하단 블록에 (배치가 카테고리에 따라 달라짐)
    if(typeof renderLinkedProjDocs==='function') renderLinkedProjDocs();
    // 카테고리 전환으로 방금 보이게 된 섹션의 textarea는 숨김 상태에서 높이가 0으로
    // 측정돼 있음 — 표시 후 다시 측정해야 기존 내용만큼 늘어난다
    if(typeof calRefreshAutoGrow==='function') setTimeout(calRefreshAutoGrow,0);
}

// ── 미팅/내방 · 사내업무(blue) 옵션 선택 UI — 목록은 '내방 옵션' 설정 공유 ──
function renderVisitOpts(){
    const list=document.getElementById('visitOptsList');
    if(!list) return;
    const isOffice=currentColor==='blue';
    const opts=CAL_VISIT_OPTIONS||[];
    const empty='관리 → 설정에서 내방 옵션을 추가하세요.';
    // 카테고리 전환으로 재렌더돼도 이미 체크된 값은 유지 (수정 열기 시 복원 보존)
    const prevChecked=[...list.querySelectorAll('input:checked')].map(i=>i.value);
    list.innerHTML=opts.map(opt=>{
        const v=String(opt).replace(/"/g,'&quot;'); const t=String(opt).replace(/</g,'&lt;');
        return `<label class="visit-opt"><input type="checkbox" value="${v}" onchange="onVisitOptChange()">${t}</label>`;
    }).join('') || `<span style="font-size:12px;color:var(--text-muted);">${empty}</span>`;
    list.querySelectorAll('input').forEach(cb=>{ if(prevChecked.includes(cb.value)) cb.checked=true; });
    const label=document.getElementById('visitOptsLabel');
    if(label) label.textContent=isOffice?'장소 선택':'내방 옵션';
}
function applyVisitOptsUI(){
    const sec=document.getElementById('visitOptsSection');
    const addr=document.getElementById('addressBlock');
    if(!sec||!addr) return;
    if(currentColor==='purple'||currentColor==='blue'){ renderVisitOpts(); sec.style.display=''; onVisitOptChange(); }
    else { sec.style.display='none'; addr.style.display=''; }
}
function onVisitOptChange(){
    const addr=document.getElementById('addressBlock');
    if(!addr) return;
    const anyChecked=[...document.querySelectorAll('#visitOptsList input:checked')].length>0;
    addr.style.display=(currentColor==='purple' && anyChecked) ? 'none' : '';
}

// ── 반복 설정 ──
function onRepeatChkToggle(){
    const on=document.getElementById('repeatChk')?.checked;
    const ctrl=document.getElementById('repeatControls');
    if(ctrl) ctrl.style.display=on?'inline-flex':'none';
    onRepeatFreqChange();
}
function onRepeatFreqChange(){
    const f=document.getElementById('repeatFreq')?.value||'';
    const cw=document.getElementById('repeatCustomWrap');
    if(cw) cw.style.display=f==='custom'?'inline-flex':'none';
}

// ── 담당자 ──
// 담당자가 많아져 전체 칩을 펼치지 않는다: 접힘 상태에는 본인 + 이미 선택된 담당자 + 최근 선택한 3명만 보이고,
// 나머지는 이름 검색으로 찾는다. 최근 선택은 이 브라우저의 localStorage 에 저장(저장·즉시 변경 시 갱신).
const RECENT_ASSIGNEE_KEY='calRecentAssignees:'+CAL_USER_ID; // 사용자별 — 같은 브라우저에서 계정을 바꿔도 남의 최근 선택이 보이지 않게
const RECENT_ASSIGNEE_MAX=3;
let assigneePanelOpen=false;
let assigneeQuery=''; // 검색어 (비어 있으면 접힘 상태)
function recentAssigneeIds(){
    try{ const v=JSON.parse(localStorage.getItem(RECENT_ASSIGNEE_KEY)||'[]'); return Array.isArray(v)?v.map(Number).filter(Number.isFinite):[]; }
    catch(e){ return []; }
}
/** 저장/변경한 담당자를 최근 목록 맨 앞에 — 본인은 항상 보이므로 제외, 최대 3명 */
function rememberRecentAssignees(ids){
    const selfId=assignees.find(a=>a.user_id===CAL_USER_ID)?.id;
    const picked=(ids||[]).map(Number).filter(id=>Number.isFinite(id)&&id!==selfId);
    if(!picked.length) return;
    const next=[...picked.slice().reverse(), ...recentAssigneeIds()].filter((id,i,arr)=>arr.indexOf(id)===i).slice(0,RECENT_ASSIGNEE_MAX);
    try{ localStorage.setItem(RECENT_ASSIGNEE_KEY, JSON.stringify(next)); }catch(e){}
}
function toggleAssigneePanel(){
    assigneePanelOpen=!assigneePanelOpen;
    document.getElementById('assigneeList').style.display=assigneePanelOpen?'flex':'none';
    if(assigneePanelOpen){
        assigneeQuery=''; renderAssigneeList();
        // 캘린더는 상주 탭이라 목록이 페이지 로드 시점 스냅샷 — 열 때마다 최신화
        // (관리 페이지에서 방금 추가한 외부 담당자도 새로고침 없이 반영)
        loadAssignees().then(()=>{ if(assigneePanelOpen) renderAssigneeList(); }).catch(()=>{});
    }
}
function updateAssigneeBtn(){
    const btn=document.getElementById('assigneeBtn');
    const label=document.getElementById('assigneeBtnLabel');
    if(selectedAssignees.length){
        // 선택한 순서대로 표시 (먼저 고른 담당자가 앞)
        const names=selectedAssignees.map(id=>assignees.find(a=>a.id===id)?.name).filter(Boolean).join(', ');
        label.textContent=names;
        btn.classList.add('has-assignee');
    }else{
        label.textContent='담당자 지정';
        btn.classList.remove('has-assignee');
    }
}
function renderAssigneeList(){
    const c=document.getElementById('assigneeList');
    if(!assignees.length){c.innerHTML='<div style="font-size:12px;color:var(--text-muted);">등록된 담당자 없음</div>';return;}
    // 검색창은 한 번만 만들고 유지 (칩만 다시 그려야 입력 중 포커스·커서가 안 날아감)
    let search=document.getElementById('assigneeSearch');
    let chips=document.getElementById('assigneeChips');
    if(!search||!chips){
        c.innerHTML='';
        search=document.createElement('input');
        search.type='search'; search.id='assigneeSearch'; search.className='assignee-search';
        search.placeholder='이름으로 담당자 검색'; search.autocomplete='off'; search.setAttribute('aria-label','담당자 검색');
        search.oninput=()=>{ assigneeQuery=search.value.trim(); renderAssigneeList(); };
        search.onkeydown=(e)=>{ if(e.key==='Escape'){ e.stopPropagation(); search.value=''; assigneeQuery=''; renderAssigneeList(); } };
        chips=document.createElement('div'); chips.id='assigneeChips'; chips.className='assignee-chips';
        c.appendChild(search); c.appendChild(chips);
    }
    if(search.value.trim()!==assigneeQuery) search.value=assigneeQuery;
    const q=assigneeQuery.toLowerCase();
    const recent=recentAssigneeIds();
    const isSelfOf=a=>a.user_id===CAL_USER_ID;
    // 접힘: 본인 → 선택된 담당자(선택 순) → 최근 선택 3명. 검색: 이름 부분일치 전체
    let visible;
    if(q){
        visible=assignees.filter(a=>(a.name||'').toLowerCase().includes(q));
    }else{
        const self=assignees.filter(isSelfOf);
        const selected=selectedAssignees.map(id=>assignees.find(a=>a.id===id)).filter(a=>a&&!isSelfOf(a));
        const recents=recent.map(id=>assignees.find(a=>a.id===id)).filter(a=>a&&!isSelfOf(a)&&!selectedAssignees.includes(a.id));
        visible=[...self, ...selected, ...recents];
    }
    chips.innerHTML='';
    visible.forEach(a=>{
        const isSelf=isSelfOf(a);
        const isSel=selectedAssignees.includes(a.id);
        const chip=document.createElement('div');
        chip.className='assignee-chip'+(isSel?' selected':'')+(isSelf?' self':'');
        chip.textContent=a.name+(isSelf?' (나)':''); chip.dataset.id=a.id;
        chip.onclick=async ()=>{
            // 보기 모드: 수정 진입 없이 클릭 즉시 서버 반영
            if(viewMode&&editingId){
                if(!canEditCalendar) return;
                const adding=!selectedAssignees.includes(a.id);
                const next=adding ? [...selectedAssignees, a.id] : selectedAssignees.filter(id=>id!==a.id);
                if(!(await quickUpdateEvent({assignees:next}))) return;
                selectedAssignees=next;
                if(adding) rememberRecentAssignees([a.id]);
                if(detailEvent) detailEvent.assignees=assignees.filter(x=>next.includes(x.id));
                assigneeQuery=''; updateAssigneeBtn(); renderAssigneeList();
                showCalToast('담당자가 변경되었습니다');
                loadEvents();
                return;
            }
            if(isLocked) return;
            if(selectedAssignees.includes(a.id)){selectedAssignees=selectedAssignees.filter(id=>id!==a.id);}
            else{selectedAssignees.push(a.id);}
            assigneeQuery=''; // 고른 뒤에는 접힘 상태로 돌아가 선택 결과가 바로 보이게
            updateAssigneeBtn(); renderAssigneeList();
        };
        chips.appendChild(chip);
    });
    const hint=document.createElement('div');
    hint.className='assignee-hint';
    if(q){
        if(!visible.length) hint.textContent='검색 결과 없음';
    }else{
        const others=assignees.length-visible.length;
        if(others>0) hint.textContent=`그 외 ${others}명은 이름으로 검색`;
    }
    if(hint.textContent) chips.appendChild(hint);
}

// ── 알림 받을 멤버 ──
let notifyPanelOpen=false;
function toggleNotifyPanel(){
    notifyPanelOpen=!notifyPanelOpen;
    document.getElementById('notifyList').style.display=notifyPanelOpen?'flex':'none';
    if(notifyPanelOpen){
        renderNotifyList();
        // 담당자 패널과 동일 — 열 때마다 목록 최신화 (상주 탭 스냅샷 문제)
        loadAssignees().then(()=>{ if(notifyPanelOpen) renderNotifyList(); }).catch(()=>{});
    }
}
function updateNotifyBtn(){
    const btn=document.getElementById('notifyBtn');
    const label=document.getElementById('notifyBtnLabel');
    if(!btn||!label) return;
    if(selectedNotifyAssignees.length){
        label.textContent=assignees.filter(a=>selectedNotifyAssignees.includes(a.id)).map(a=>a.name).join(', ');
        btn.classList.add('has-assignee');
    }else{
        label.textContent='알림 받을 멤버';
        btn.classList.remove('has-assignee');
    }
}
function renderNotifyList(){
    const c=document.getElementById('notifyList');
    if(!c) return;
    if(!assignees.length){c.innerHTML='<div style="font-size:12px;color:var(--text-muted);">등록된 멤버 없음</div>';return;}
    c.innerHTML='<div style="width:100%;font-size:11px;color:var(--text-muted);margin-bottom:4px;">선택하지 않으면 담당자 전체에게 알림이 갑니다</div>';
    const ordered=[...assignees].sort((a,b)=>((a.user_id===CAL_USER_ID)?0:1)-((b.user_id===CAL_USER_ID)?0:1));
    ordered.forEach(a=>{
        const isSelf=a.user_id===CAL_USER_ID;
        const isSel=selectedNotifyAssignees.includes(a.id);
        const chip=document.createElement('div');
        chip.className='assignee-chip'+(isSel?' selected':'')+(isSelf?' self':'');
        chip.textContent=a.name+(isSelf?' (나)':''); chip.dataset.id=a.id;
        chip.onclick=()=>{
            if(isLocked) return;
            if(selectedNotifyAssignees.includes(a.id)){selectedNotifyAssignees=selectedNotifyAssignees.filter(id=>id!==a.id);}
            else{selectedNotifyAssignees.push(a.id);}
            updateNotifyBtn(); renderNotifyList();
        };
        c.appendChild(chip);
    });
}

// ── 종일 토글 ──
function toggleAllDay(){
    if(isLocked) return;
    if(isExtOperatorChecked()) return; // 외부 오퍼레이터는 항시 종일 고정
    isAllDay=!isAllDay;
    document.getElementById('alldayTrack').classList.toggle('on',isAllDay);
    document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display=isAllDay?'none':'');
}

// ── 시작 날짜 변경 시 마감 자동 보정 — 시간 일정은 당일, 종일 일정은 다음날 ──
// (마감이 변경 전 날짜에 남아 시작보다 앞서던 문제 방지 — 마감은 이후에 직접 늘릴 수 있음)
function syncEndFromStart(prefix){
    const sdEl=document.getElementById(prefix==='gold'?'goldStartDate':'startDate');
    const edEl=document.getElementById(prefix==='gold'?'goldEndDate':'endDate');
    if(!sdEl?.value||!edEl) return;
    const d=new Date(sdEl.value+'T00:00:00');
    if(isNaN(d)) return;
    if(isAllDay) d.setDate(d.getDate()+1); // 종일 — 다음날 마감
    edEl.value=fmt(d);
}
['startDate','goldStartDate'].forEach(id=>{
    document.getElementById(id)?.addEventListener('change',()=>syncEndFromStart(id==='goldStartDate'?'gold':''));
});

// ── 외부 오퍼레이터(스튜디오 전용) — 체크 시 항시 종일 일정으로 고정 (시간 입력 불필요) ──
function isExtOperatorChecked(){
    const b=document.getElementById('extOperatorBtn');
    return !!(b&&b.style.display!=='none'&&b.classList.contains('active'));
}
function applyExtOperatorUI(){
    const on=isExtOperatorChecked();
    if(on&&!isAllDay){
        isAllDay=true;
        document.getElementById('alldayTrack').classList.add('on');
        document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='none');
    }
    const track=document.getElementById('alldayTrack');
    if(track){ track.style.pointerEvents=on?'none':''; track.style.opacity=on?'0.55':''; }
}

