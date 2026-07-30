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
        const cell=document.createElement('div');
        cell.className='tl-day-col'+(isToday?' today-col':'');
        const nc=dow===0?'sun-c':dow===6?'sat-c':'';
        cell.innerHTML=`<div class="tl-day-name">${DAYS_KO[dow]}</div>
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
        events.filter(ev=>isFiltered(ev)&&ev.is_all_day&&evCoversDate(ev,ds)).forEach(ev=>{
            const chip=document.createElement('div');
            chip.className=`event-chip color-${ev.color}`+(ev.completed_at?' is-completed':'');
            chip.style.marginBottom='2px';
            chip.innerHTML=isGuestUser?_esc((ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):'')):(eventOptIconsHtml(ev)+`<span class="chip-title">${_esc(ev.title||'')}</span>`+schedStatusChip(ev)+shipStatusIcon(ev)+((ev.assignees||[]).length?` <span class="ev-assignee-badge">${_esc(assigneeNamesOf(ev).join(', '))}</span>`:''));
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
                if(end<=start) end=start+30; // 종료<=시작이면 최소 30분
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
                const eh=ev.end_time?parseInt(ev.end_time.split(':')[0]):hour+1;
                const em=ev.end_time?parseInt(ev.end_time.split(':')[1]):0;
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
let isAllDay=false, isLocked=false, linkedEstimateId=null;

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
    // 의뢰주제 이사세팅 → 출발지/도착지 UI
    if(gid==='g_req_topic_group'){
        updateMoveSettingUI();
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
    const optExtra=c==='gold'||Object.keys(CS_CATS).some(k=>k===c&&/스튜디오|촬영/.test((CS_CATS[k]&&CS_CATS[k].label)||''));
    const seSec=document.getElementById('schedEventSection');
    if(seSec) seSec.style.display=optExtra?'':'none';
    const spGrp=document.getElementById('specialOptsGroup');
    if(spGrp) spGrp.style.display=optExtra?'':'none';
    // 사내업무(blue)/휴가·개인(red)은 의뢰자 검색 불필요 → 섹션 숨김
    const clientSec=document.getElementById('clientLinkSection');
    if(clientSec) clientSec.style.display=(c==='blue'||c==='red')?'none':'';
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
}

// ── 미팅/내방 옵션 ──
function renderVisitOpts(){
    const list=document.getElementById('visitOptsList');
    if(!list) return;
    list.innerHTML=(CAL_VISIT_OPTIONS||[]).map(opt=>{
        const v=String(opt).replace(/"/g,'&quot;'); const t=String(opt).replace(/</g,'&lt;');
        return `<label class="visit-opt"><input type="checkbox" value="${v}" onchange="onVisitOptChange()">${t}</label>`;
    }).join('') || '<span style="font-size:12px;color:var(--text-muted);">관리 → 설정에서 내방 옵션을 추가하세요.</span>';
}
function applyVisitOptsUI(){
    const sec=document.getElementById('visitOptsSection');
    const addr=document.getElementById('addressBlock');
    if(!sec||!addr) return;
    if(currentColor==='purple'){ sec.style.display=''; onVisitOptChange(); }
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
let assigneePanelOpen=false;
let assigneeShowAll=false; // 본인 외 나머지 펼침 여부
function toggleAssigneePanel(){
    assigneePanelOpen=!assigneePanelOpen;
    document.getElementById('assigneeList').style.display=assigneePanelOpen?'flex':'none';
    if(assigneePanelOpen){ assigneeShowAll=false; renderAssigneeList(); }
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
    // 로그인 본인을 맨 앞으로 정렬
    const ordered=[...assignees].sort((a,b)=>((a.user_id===CAL_USER_ID)?0:1)-((b.user_id===CAL_USER_ID)?0:1));
    c.innerHTML='';
    let hidden=0;
    ordered.forEach(a=>{
        const isSelf=a.user_id===CAL_USER_ID;
        const isSel=selectedAssignees.includes(a.id);
        const chip=document.createElement('div');
        chip.className='assignee-chip'+(isSel?' selected':'')+(isSelf?' self':'');
        chip.textContent=a.name+(isSelf?' (나)':''); chip.dataset.id=a.id;
        // 접힘 상태: 본인/이미 선택된 담당자만 노출, 나머지는 숨김
        if(!assigneeShowAll && !isSelf && !isSel){ chip.style.display='none'; hidden++; }
        chip.onclick=async ()=>{
            // 보기 모드: 수정 진입 없이 클릭 즉시 서버 반영
            if(viewMode&&editingId){
                if(!canEditCalendar) return;
                const next=selectedAssignees.includes(a.id)
                    ? selectedAssignees.filter(id=>id!==a.id)
                    : [...selectedAssignees, a.id];
                if(!(await quickUpdateEvent({assignees:next}))) return;
                selectedAssignees=next;
                if(detailEvent) detailEvent.assignees=assignees.filter(x=>next.includes(x.id));
                updateAssigneeBtn(); renderAssigneeList();
                showCalToast('담당자가 변경되었습니다');
                loadEvents();
                return;
            }
            if(isLocked) return;
            if(selectedAssignees.includes(a.id)){selectedAssignees=selectedAssignees.filter(id=>id!==a.id);}
            else{selectedAssignees.push(a.id);}
            updateAssigneeBtn(); renderAssigneeList();
        };
        c.appendChild(chip);
    });
    // 더보기/접기
    const remaining=ordered.filter(a=>a.user_id!==CAL_USER_ID && !selectedAssignees.includes(a.id)).length;
    if(remaining>0 || assigneeShowAll){
        const more=document.createElement('button');
        more.type='button'; more.className='assignee-more';
        more.textContent=assigneeShowAll?'접기 ▲':`+${remaining}명 더보기 ▼`;
        more.onclick=(e)=>{ e.stopPropagation(); assigneeShowAll=!assigneeShowAll; renderAssigneeList(); };
        c.appendChild(more);
    }
}

// ── 알림 받을 멤버 ──
let notifyPanelOpen=false;
function toggleNotifyPanel(){
    notifyPanelOpen=!notifyPanelOpen;
    document.getElementById('notifyList').style.display=notifyPanelOpen?'flex':'none';
    if(notifyPanelOpen) renderNotifyList();
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
    isAllDay=!isAllDay;
    document.getElementById('alldayTrack').classList.toggle('on',isAllDay);
    document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display=isAllDay?'none':'');
}

