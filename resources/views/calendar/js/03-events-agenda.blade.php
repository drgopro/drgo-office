{{-- 이벤트 로드·일별 팝오버·목록 뷰 --}}
// ── 이벤트 로드 ─────────────────────────────────────────────────
// 상단 새로고침 버튼 — 현재 보기(월/주/일/목록)를 유지한 채 일정만 다시 불러옴
async function refreshCalendar(){
    const btn=document.getElementById('calRefreshBtn');
    const ico=document.getElementById('calRefreshIco');
    if(btn) btn.disabled=true;
    if(ico) ico.style.animation='calSpin 0.8s linear infinite';
    try{
        await loadEvents();
        showCalToast('일정을 새로고침했습니다');
    } finally {
        if(btn) btn.disabled=false;
        if(ico) ico.style.animation='';
    }
}

async function loadEvents() {
    expandedDays.clear();
    let start, end;
    if (currentView==='list') {
        // 목록 뷰: 오늘부터 7일 (+ 다일 일정 겹침 위해 약간 여유)
        const wk=agendaWeekDates();
        start=wk[0]; end=wk[wk.length-1];
    } else if (currentView==='month') {
        // 그리드에 보이는 범위 전체 (월 전체 42칸 또는 다중 주 N*7칸)
        const gs=monthGridStart();
        const ge=new Date(gs); ge.setDate(ge.getDate()+monthGridWeeks()*7-1);
        start=fmt(gs); end=fmt(ge);
    } else if (currentView==='monthc') {
        // 컴팩트 월간: 항상 6주 고정 그리드
        const first=new Date(currentYear,currentMonth,1);
        const gs=new Date(first); gs.setDate(1-first.getDay());
        const ge=new Date(gs); ge.setDate(gs.getDate()+41);
        start=fmt(gs); end=fmt(ge);
    } else if (currentView==='week') {
        start=fmt(currentWeekStart);
        const we=new Date(currentWeekStart); we.setDate(we.getDate()+6); end=fmt(we);
    } else {
        start=end=fmt(currentDay);
    }
    const res=await fetch(`/api/events?start=${start}&end=${end}`);
    events=await res.json();
    // 삭제/변경 흔적 — 필터가 켜진 경우에만 이력 shadow를 함께 로드 (월간 뷰 전용)
    if(showGhosts && currentView==='month'){
        try{
            const gr=await fetch(`/api/events/history?start=${start}&end=${end}`,{headers:{'Accept':'application/json'}});
            ghostEvents=gr.ok?(await gr.json()).filter(c=>c.is_shadow):[];
        }catch(e){ ghostEvents=[]; }
    }else{
        ghostEvents=[];
    }
    renderView();
}

async function loadAssignees() {
    const res=await fetch('/api/assignees');
    if(res.ok) {
        assignees=await res.json();
        populateAssigneeFilter();
    }
}

// 외부 오퍼레이터 최상단 → 종일 먼저 → 시작시간 오름차순 정렬 (월간 셀·팝오버·모바일 공용)
function sortByTime(list){
    return [...list].sort((a,b)=>{
        const aTop=isTopEv(a)?0:1, bTop=isTopEv(b)?0:1;
        if(aTop!==bTop) return aTop-bTop;
        const aAll=a.is_all_day?0:1, bAll=b.is_all_day?0:1;
        if(aAll!==bAll) return aAll-bAll;
        return (a.start_time||'99:99').localeCompare(b.start_time||'99:99');
    });
}

// ── 하루 일정 전체 보기 팝오버 ───────────────────────────────────
function openDayPopover(dateStr, anchorEl){
    const pop=document.getElementById('dayPopover');
    const overlay=document.getElementById('dayPopoverOverlay');
    if(!pop||!overlay) return;

    const dayEvs=sortByTime(events.filter(ev=>isFiltered(ev)&&evCoversDate(ev,dateStr)));
    const d=new Date(dateStr+'T00:00:00');
    const DAYS=['일','월','화','수','목','금','토'];
    document.getElementById('dpTitle').textContent=`${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS[d.getDay()]}) · ${dayEvs.length}건`;
    // 주소를 도로명까지만 (상세주소·동/호수 등 쉼표 뒷부분 제거)
    const roadOnly=(addr)=>{
        if(!addr) return '';
        return String(addr).split(',')[0].trim();
    };
    document.getElementById('dpList').innerHTML=dayEvs.map(ev=>{
        const title=isGuestUser?(ev.location||'일정'):(ev.title||'(제목 없음)');
        const assignees=(ev.assignees||[]).map(a=>a.name).filter(Boolean).join(', ');
        const time=ev.is_all_day?'종일':((ev.start_time||'').substring(0,5)+((ev.end_time)?'~'+ev.end_time.substring(0,5):''));
        // 담당자는 제목 우측에, 나머지(시간·주소)는 하단 메타에
        const moveHtml=moveAddrLinesHtml(ev);
        const meta=[time, moveHtml?'':roadOnly(ev.location)].filter(Boolean).join(' · ');
        return `<div class="dp-item${ev.completed_at?' is-completed':''}" onclick="closeDayPopover(); openDetailModal(events.find(e=>e.id===${ev.id}))">
            <span class="dp-dot" style="background:${chipColor(ev.color)}"></span>
            <div class="dp-info">
                <div class="dp-title-row">
                    <span class="dp-title">${eventOptIconsHtml(ev)}${_esc(title)}${schedStatusChip(ev)}${shipStatusIcon(ev)}</span>
                    ${assignees?`<span class="dp-assignee">${_esc(assignees)}</span>`:''}
                </div>
                ${meta?`<div class="dp-meta">${_esc(meta)}</div>`:''}
                ${moveHtml}
            </div>
        </div>`;
    }).join('');

    // 위치: 화면 정중앙 고정 — 셀 근처 배치는 해상도/스크롤 조합에 따라 잘리는 예외가 계속 발생해 중앙 고정으로 단순화
    overlay.classList.add('open');
    pop.style.display='block';
    const vv=window.visualViewport;
    const vw=vv?vv.width:window.innerWidth, vh=vv?vv.height:window.innerHeight;
    const vx=vv?vv.offsetLeft:0, vy=vv?vv.offsetTop:0;
    pop.style.maxHeight=Math.round(vh*0.7)+'px'; // 내부 스크롤 (overflow-y:auto)
    const pw=Math.min(320, vw-24);
    pop.style.width=pw+'px';
    const ph=pop.offsetHeight;
    pop.style.left=Math.round(vx+(vw-pw)/2)+'px';
    pop.style.top=Math.round(vy+Math.max(12,(vh-ph)/2))+'px';
}
function closeDayPopover(){
    const pop=document.getElementById('dayPopover');
    const overlay=document.getElementById('dayPopoverOverlay');
    if(pop) pop.style.display='none';
    if(overlay) overlay.classList.remove('open');
}

// ── 목록(아젠다) 뷰 ─────────────────────────────────────────────
// 오늘 기준 7일 스트립에서 날짜를 고르고, 선택한 날의 일정을 시간순으로 표시
let agendaSelectedDate = null; // 'YYYY-MM-DD'
const AGENDA_DOW=['일','월','화','수','목','금','토'];
let agendaWeekStart=null; // 7일 스트립의 시작 날짜(Date). null이면 오늘부터.

function agendaWeekDates(){
    // 기준일이 속한 주의 일요일~토요일
    const base = getWeekStart(agendaWeekStart ? new Date(agendaWeekStart) : new Date());
    const arr=[];
    for(let i=0;i<7;i++){ const d=new Date(base); d.setDate(base.getDate()+i); arr.push(fmt(d)); }
    return arr;
}
// 스와이프/화살표로 한 주(일~토) 이동
function moveAgendaWeek(dir){
    const base = getWeekStart(agendaWeekStart ? new Date(agendaWeekStart) : new Date());
    base.setDate(base.getDate()+dir*7);
    agendaWeekStart=base;
    agendaSelectedDate=fmt(base); // 이동한 주의 일요일 선택
    loadEvents(); // 새 주 범위 로드 후 renderAgenda
}
function renderAgenda(){
    // 검색 결과 모드
    if(agendaSearchQuery){ renderAgendaSearch(); return; }
    const stripEl=document.getElementById('agendaStrip');
    if(stripEl) stripEl.style.display='';
    const ts=todayStr();
    const week=agendaWeekDates();
    if(!agendaSelectedDate || !week.includes(agendaSelectedDate)) agendaSelectedDate=week.includes(ts)?ts:week[0];
    const ws=new Date(week[0]+'T00:00:00');
    const we=new Date(week[6]+'T00:00:00');
    document.getElementById('periodTitle').textContent=`${ws.getMonth()+1}.${ws.getDate()} ~ ${we.getMonth()+1}.${we.getDate()}`;

    // 상단 7일 스트립
    const strip=document.getElementById('agendaStrip');
    if(strip){
        strip.innerHTML=week.map(full=>{
            const d=new Date(full+'T00:00:00'); const dow=d.getDay();
            const cls=dow===0?'sun':dow===6?'sat':'';
            const hasEv=events.some(ev=>isFiltered(ev)&&evCoversDate(ev,full));
            return `<button class="agenda-day-btn ${full===agendaSelectedDate?'active':''}" onclick="selectAgendaDate('${full}')">
                <span class="adb-dow ${cls}">${AGENDA_DOW[dow]}</span>
                <span class="adb-num ${cls}">${d.getDate()}</span>
                ${hasEv?'<span class="adb-dot"></span>':''}
            </button>`;
        }).join('');
    }

    // 선택일 일정
    const wrap=document.getElementById('agendaWrap');
    if(!wrap) return;
    const full=agendaSelectedDate;
    const d=new Date(full+'T00:00:00');
    const dayEvs=sortByTime(events.filter(ev=>isFiltered(ev)&&evCoversDate(ev,full)));
    const dowCls=full===ts?'ad-today':d.getDay()===0?'ad-sun':d.getDay()===6?'ad-sat':'';

    let html=`<div class="agenda-day"><div class="agenda-date-head">
        <span class="ad-d ${dowCls}">${d.getMonth()+1}.${d.getDate()}</span>
        <span class="ad-dow ${dowCls}">${AGENDA_DOW[d.getDay()]}요일${full===ts?' · 오늘':''}</span>
    </div>`;
    if(!dayEvs.length){
        html+='<div class="agenda-empty">일정이 없습니다.</div>';
    } else {
        dayEvs.forEach(ev=>{
            const isMulti=ev.end_date&&ev.end_date!==ev.start_date;
            const timeLabel=ev.is_all_day?'종일':(isMulti?'기간':((ev.start_time||'').substring(0,5)||'시간 미정'));
            const title=isGuestUser?(ev.location||'일정'):(ev.title||'(제목 없음)');
            const assignees=(ev.assignees||[]).map(a=>a.name).filter(Boolean).join(', ');
            const moveHtml=moveAddrLinesHtml(ev);
            const sub=[ (isMulti?`${ev.start_date.slice(5).replace('-','/')}~${ev.end_date.slice(5).replace('-','/')}`:''), ev.location ].filter(Boolean).join(' · ');
            const subHtml=moveHtml || (sub?`<div class="agenda-sub">${_esc(sub)}</div>`:'');
            html+=`<div class="agenda-item${ev.completed_at?' is-completed':''}" onclick="openDetailModal(events.find(e=>e.id===${ev.id}))">
                <div class="agenda-stripe" style="background:${chipColor(ev.color)}"></div>
                <div class="agenda-main">
                    <div class="agenda-title">${eventOptIconsHtml(ev)}${_esc(title)}${schedStatusChip(ev)}${shipStatusIcon(ev)}</div>
                    ${subHtml}
                </div>
                <div class="agenda-right">
                    <span class="agenda-time">${timeLabel}</span>
                    ${assignees?`<span class="agenda-assignee">${_esc(assignees)}</span>`:''}
                </div>
            </div>`;
        });
    }
    html+='</div>';
    wrap.innerHTML=html;
}
function selectAgendaDate(full){ agendaSelectedDate=full; renderAgenda(); }

