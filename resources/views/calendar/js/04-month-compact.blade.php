{{-- 월간 뷰·컴팩트(전체) 뷰·모바일 시트 --}}
// ── 월간 뷰 ─────────────────────────────────────────────────────
// ── 컴팩트 월간 뷰 (네이버식 고밀도 — 균등 6주 셀 + 작은 칩 + "+N") ──
function renderMonthCompact(){
    const view=document.getElementById('monthCompactView');
    if(!view) return;
    const ts=todayStr();
    document.getElementById('periodTitle').textContent=`${currentYear}년 ${currentMonth+1}월`;
    const first=new Date(currentYear,currentMonth,1);
    const gridStart=new Date(first); gridStart.setDate(1-first.getDay());
    const _d=v=>(v||'').substring(0,10);

    // 행 높이 — 데스크탑: 6주가 화면에 딱 맞게 / 모바일: 칩이 최대한 보이도록 넉넉하게 (그리드는 세로 스크롤)
    const mcMobile=window.innerWidth<=768;
    let rowH=92;
    const vTop=view.getBoundingClientRect().top;
    if(window.innerHeight-vTop>360){
        const reserve=mcMobile?26+54:26+14+mcListH+12; // 요일 헤더 + (모바일: 시트 바 / 데스크탑: 핸들+리스트 영역)
        rowH=Math.max(mcMobile?58:72, Math.floor((window.innerHeight-vTop-reserve)/6));
    }
    const CHIP=mcMobile?19:17, BAR=17, HEAD=20, LANE_CAP=3;
    if(mcMobile) rowH=Math.max(rowH, HEAD+CHIP*6+4); // 모바일: 셀당 칩 6개 수준 확보 (네이버식 밀도)

    let html=`<div class="mc-weekdays">${['일','월','화','수','목','금','토'].map(d=>`<span>${d}</span>`).join('')}</div>`;
    for(let w=0;w<6;w++){
        const days=[...Array(7)].map((_,i)=>{const dt=new Date(gridStart);dt.setDate(gridStart.getDate()+w*7+i);return dt;});
        const weekStart=fmt(days[0]), weekEnd=fmt(days[6]);

        // 다일 일정 레인 배정 (기존 월간 뷰와 동일 규칙 — 휴가/개인 우선)
        // 전·다음 달 일정도 표시 (다른 달 셀은 other-month 흐림으로 구분)
        const weekMulti=events.filter(ev=>isFiltered(ev)&&!ev.parent_id&&evEnd(ev)!==_d(ev.start_date)&&_d(ev.start_date)<=weekEnd&&evEnd(ev)>=weekStart);
        weekMulti.sort((a,b)=>(isTopEv(b)-isTopEv(a))||((b.color==='red')-(a.color==='red'))||a.start_date.localeCompare(b.start_date)||b.end_date.localeCompare(a.end_date)||a.id-b.id);
        const laneOf={}, lanes=[];
        weekMulti.forEach(ev=>{
            let lane=0;
            while((lanes[lane]||[]).some(r=>!(evEnd(ev)<r.s||_d(ev.start_date)>r.e))) lane++;
            (lanes[lane]=lanes[lane]||[]).push({s:_d(ev.start_date),e:evEnd(ev)});
            laneOf[ev.id]=lane;
        });

        // 다일 연속 바 (레인 상한 초과분은 각 날짜 +N에 합산)
        let bars='';
        weekMulti.forEach(ev=>{
            const lane=laneOf[ev.id];
            if(lane>=LANE_CAP) return;
            const s=_d(ev.start_date)<weekStart?weekStart:_d(ev.start_date);
            const e=evEnd(ev)>weekEnd?weekEnd:evEnd(ev);
            const c0=days.findIndex(d=>fmt(d)===s), c1=days.findIndex(d=>fmt(d)===e);
            if(c0<0||c1<0) return;
            const label=_d(ev.start_date)>=weekStart||w===0?`<span>${eventOptIconsHtml(ev)}${_esc(ev.title||'(제목 없음)')}${schedStatusChip(ev)}</span>`:'<span></span>';
            bars+=`<div class="mc-bar color-${ev.color}${ev.completed_at?' is-completed':''}" data-mcid="${ev.id}" title="${_esc(ev.title||'')}"
                style="left:calc(${c0} * 100% / 7 + 2px); width:calc(${c1-c0+1} * 100% / 7 - 5px); top:${HEAD+lane*BAR}px;">${label}</div>`;
        });

        // 날짜 셀 + 단일 일정 칩 — 월간 뷰와 동일 배치: 바가 지나는 레인은 비우고, 빈 레인은 그날 단일 칩으로 채움
        let cellsHtml='';
        days.forEach((dt,i)=>{
            const full=fmt(dt);
            const cur=dt.getMonth()===currentMonth;
            const holiday=getHoliday(full);
            const nc=i===0||holiday?'sun':i===6?'sat':'';
            // 다른 달 날짜도 칩/+N 표시 (other-month 흐림 처리로 현재 달과 구분)
            const daySingles=sortByTime(events.filter(ev=>isFiltered(ev)&&!ev.parent_id&&evEnd(ev)===_d(ev.start_date)&&_d(ev.start_date)===full));
            const hiddenMulti=weekMulti.filter(ev=>laneOf[ev.id]>=LANE_CAP&&_d(ev.start_date)<=full&&evEnd(ev)>=full).length;
            // 이 날짜를 지나는 표시 레인 집합 — 바 자리는 스페이서로 비우고 빈 레인엔 단일 칩 삽입
            const laneSet=new Set();
            weekMulti.forEach(ev=>{ if(laneOf[ev.id]<LANE_CAP&&_d(ev.start_date)<=full&&evEnd(ev)>=full) laneSet.add(laneOf[ev.id]); });
            const laneRows=laneSet.size?Math.max(...laneSet)+1:0;
            const freeLanes=laneRows-laneSet.size;
            const extraCap=Math.max(0,Math.floor((rowH-HEAD-laneRows*BAR)/CHIP));
            const singleBudget=Math.max(1,freeLanes+extraCap);
            let show=daySingles, moreCnt=hiddenMulti;
            if(daySingles.length+hiddenMulti>singleBudget){
                const cut=Math.max(0,singleBudget-1);
                moreCnt=hiddenMulti+(daySingles.length-cut);
                show=daySingles.slice(0,cut);
            }
            // 시간을 칩 맨 앞 고정폭으로 — 아이콘 폭 차이로 시간 열이 어긋나지 않게 (시간 → 아이콘 → 제목 순)
            const chipOf=(ev,inLane)=>`<div class="mc-chip${inLane?' in-lane':''} color-${ev.color}${ev.completed_at?' is-completed':''}" data-mcid="${ev.id}" title="${_esc(ev.title||'')}">${ev.is_all_day||!ev.start_time?'':`<span class="mc-time">${(ev.start_time||'').slice(0,5)}</span>`}${eventOptIconsHtml(ev)}${_esc(ev.title||'(제목 없음)')}${schedStatusChip(ev)}</div>`;
            let body='', si=0;
            for(let L=0;L<laneRows;L++){
                if(laneSet.has(L)) body+='<div class="mc-slot"></div>';
                else if(si<show.length) body+=chipOf(show[si++],true);
                else body+='<div class="mc-slot"></div>'; // 아래 레인의 바 자리 확보
            }
            while(si<show.length) body+=chipOf(show[si++],false);
            const more=moreCnt>0?`<div class="mc-more" data-mcmore="${full}">+${moreCnt}</div>`:'';
            cellsHtml+=`<div class="mc-cell${cur?'':' other-month'}${full===ts?' today':''}" data-mcday="${full}">
                <div class="mc-daynum-row"><span class="mc-daynum ${nc}">${dt.getDate()}</span>${holiday?`<span class="mc-holiday">${holiday}</span>`:''}</div>
                ${body}${more}
            </div>`;
        });
        html+=`<div class="mc-week" style="height:${rowH}px">${bars}${cellsHtml}</div>`;
    }
    view.innerHTML=html;
    mcSheetSync();
}
// 컴팩트 뷰 클릭 위임 — 칩=상세 / 데스크탑: +N·빈 셀=일별 팝업 / 모바일: 날짜 선택 → 하단 시트
document.getElementById('monthCompactView')?.addEventListener('click', e=>{
    const isMobile=window.innerWidth<=768;
    const more=e.target.closest('[data-mcmore]');
    if(more){
        e.stopPropagation();
        if(isMobile) mcSelectDay(more.dataset.mcmore, true);
        else openDayPopover(more.dataset.mcmore, more);
        return;
    }
    const chip=e.target.closest('[data-mcid]');
    if(chip){ e.stopPropagation(); const ev=events.find(x=>String(x.id)===chip.dataset.mcid); if(ev) openDetailModal(ev); return; }
    const cell=e.target.closest('[data-mcday]');
    if(cell){
        mcSelectDay(cell.dataset.mcday); // 데스크탑: 하단 리스트 갱신 / 모바일: 시트 갱신
    }
});
window.addEventListener('resize', ()=>{ if(currentView==='monthc') renderMonthCompact(); });

// ── 월간·전체 뷰: 마우스 휠로 이전/다음 달 이동 (데스크탑 전용) ──
(function(){
    let lock=0;
    [['monthView','month'],['monthCompactView','monthc']].forEach(([id,view])=>{
        const el=document.getElementById(id);
        if(!el) return;
        el.addEventListener('wheel', e=>{
            if(currentView!==view||window.innerWidth<=768) return; // 모바일 터치 스크롤 간섭 방지
            if(Math.abs(e.deltaY)<=Math.abs(e.deltaX)) return;     // 가로 스크롤 무시
            e.preventDefault();
            const now=Date.now();
            if(now-lock<400||Math.abs(e.deltaY)<15) return;        // 트랙팩 미세 스크롤 무시 + 연타 쿨다운
            lock=now;
            changePeriod(e.deltaY>0?1:-1);
        }, {passive:false});
    });
})();

// ── 컴팩트 뷰 모바일 하단 시트 — 날짜 선택 + 바 드래그/탭으로 리스트 열기 ──
let mcSheetOpen=false, mcSelDate=null;
// 데스크탑 하단 리스트 높이 (드래그 핸들로 조절, 저장 유지)
let mcListH=(()=>{ const v=parseInt(localStorage.getItem('mcDeskListH'),10); return (v>=120&&v<=800)?v:230; })();
(function(){
    const rz=document.getElementById('mcListResizer');
    if(!rz) return;
    let dragging=false, sy=0, sh=0, lastY=0, raf=0;
    function applyDrag(){
        raf=0;
        // 위로 끌면 리스트가 커지고 그리드가 줄어듦 — 총 높이를 유지해 여백/끊김 없이 이동
        mcListH=Math.max(120, Math.min(Math.round(window.innerHeight*0.7), sh+(sy-lastY)));
        const dl=document.getElementById('mcDeskList');
        if(dl) dl.style.maxHeight=mcListH+'px';
        const view=document.getElementById('monthCompactView');
        if(view){
            const vTop=view.getBoundingClientRect().top;
            const rowH=Math.max(72, Math.floor((window.innerHeight-vTop-26-14-mcListH-12)/6));
            view.querySelectorAll('.mc-week').forEach(w=>w.style.height=rowH+'px');
        }
    }
    rz.addEventListener('mousedown', e=>{
        dragging=true; sy=e.clientY; sh=mcListH;
        document.body.style.cursor='row-resize';
        document.body.style.userSelect='none';
        e.preventDefault();
    });
    window.addEventListener('mousemove', e=>{
        if(!dragging) return;
        lastY=e.clientY;
        if(!raf) raf=requestAnimationFrame(applyDrag); // 프레임당 1회만 반영
    });
    window.addEventListener('mouseup', ()=>{
        if(!dragging) return;
        dragging=false;
        document.body.style.cursor='';
        document.body.style.userSelect='';
        try{ localStorage.setItem('mcDeskListH', mcListH); }catch(e){}
        if(currentView==='monthc') renderMonthCompact(); // 칩 표시 수/+N을 새 높이에 맞게 재계산
    });
})();
function mcSelectDay(dateStr, expand){
    mcSelDate=dateStr;
    document.querySelectorAll('.mc-cell.selected').forEach(c=>c.classList.remove('selected'));
    document.querySelector(`.mc-cell[data-mcday="${dateStr}"]`)?.classList.add('selected');
    if(window.innerWidth<=768){
        const d=new Date(dateStr+'T00:00:00');
        const cnt=events.filter(ev=>isFiltered(ev)&&evCoversDate(ev,dateStr)).length;
        const label=document.getElementById('mcSheetLabel');
        if(label) label.textContent=`${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS_KO[d.getDay()]}) · ${cnt}건`;
        renderMobileDayEvents(dateStr, document.getElementById('mcSheetBody'));
        document.querySelector('#mcSheetBody .mde-header')?.remove(); // 날짜는 핸들 바에 이미 표시 — 중복 제거
        if(expand) mcSheetSet(true);
    }else{
        // 데스크탑: 그리드 아래 상시 리스트 갱신
        renderMobileDayEvents(dateStr, document.getElementById('mcDeskList'));
    }
}
function mcSheetSet(open){
    mcSheetOpen=open;
    document.getElementById('mcSheet')?.classList.toggle('open', open);
    // 시트가 올라온 동안 뒷배경(그리드) 터치 차단 — 백드롭 탭 시 닫힘
    document.getElementById('mcSheetBackdrop')?.classList.toggle('show', open);
}
// 컴팩트 뷰(모바일)일 때만 시트 표시 + 선택일 유지 (기본: 오늘)
function mcSheetSync(){
    const sheet=document.getElementById('mcSheet');
    if(!sheet) return;
    const isMobile=window.innerWidth<=768;
    const show=currentView==='monthc'&&isMobile;
    sheet.style.display=show?'block':'none';
    if(!show) mcSheetSet(false);
    // 데스크탑 컴팩트: 그리드 아래 상시 리스트 + 크기 조절 핸들 표시
    const showDesk=currentView==='monthc'&&!isMobile;
    const dl=document.getElementById('mcDeskList');
    if(dl){ dl.style.display=showDesk?'block':'none'; dl.style.maxHeight=mcListH+'px'; }
    const rz=document.getElementById('mcListResizer');
    if(rz) rz.style.display=showDesk?'flex':'none';
    if(currentView==='monthc') mcSelectDay(mcSelDate||todayStr());
    // 플로팅 + 버튼: 시트 바가 있는 컴팩트 뷰에선 바 위로 띄움
    const fab=document.getElementById('calAddFab');
    if(fab) fab.style.bottom=show?'calc(76px + env(safe-area-inset-bottom))':'calc(20px + env(safe-area-inset-bottom))';
}
(function(){
    const handle=document.getElementById('mcSheetHandle');
    if(!handle) return;
    let startY=null, moved=false;
    handle.addEventListener('touchstart', e=>{ startY=e.touches[0].clientY; moved=false; }, {passive:true});
    handle.addEventListener('touchmove', e=>{
        if(startY===null) return;
        if(Math.abs(e.touches[0].clientY-startY)>12) moved=true;
    }, {passive:true});
    handle.addEventListener('touchend', e=>{
        if(startY===null) return;
        const dy=e.changedTouches[0].clientY-startY;
        mcSheetSet(moved ? dy<0 : !mcSheetOpen); // 위로 스와이프=열기, 아래=닫기, 탭=토글
        startY=null;
    });
    handle.addEventListener('click', ()=>{ if(!('ontouchstart' in window)) mcSheetSet(!mcSheetOpen); });
})();

// 컴팩트 뷰 하단 리스트: 선택일 하루 이동 — 그리드 밖(6주 경계) 날짜면 달을 넘겨서 선택 유지
function mcShiftDay(dir){
    const base=mcSelDate||todayStr();
    const d=new Date(base+'T00:00:00'); d.setDate(d.getDate()+dir);
    const nd=fmt(d);
    if(document.querySelector(`.mc-cell[data-mcday="${nd}"]`)){ mcSelectDay(nd); return; }
    mcSelDate=nd; // renderMonthCompact→mcSheetSync가 이 날짜를 다시 선택함
    currentYear=d.getFullYear(); currentMonth=d.getMonth();
    renderView(); loadEvents();
}
// 하단 리스트 좌우 스와이프 — 왼쪽으로 밀면 다음 날, 오른쪽으로 밀면 이전 날 (모바일 시트·데스크탑 리스트 공용)
(function(){
    ['mcSheetBody','mcDeskList'].forEach(id=>{
        const el=document.getElementById(id);
        if(!el) return;
        let sx=null, sy=null;
        el.addEventListener('touchstart', e=>{ sx=e.touches[0].clientX; sy=e.touches[0].clientY; }, {passive:true});
        el.addEventListener('touchend', e=>{
            if(sx===null) return;
            const dx=e.changedTouches[0].clientX-sx, dy=e.changedTouches[0].clientY-sy;
            sx=sy=null;
            if(Math.abs(dx)<60||Math.abs(dx)<Math.abs(dy)*1.5) return; // 세로 스크롤·짧은 터치 무시
            mcShiftDay(dx<0?1:-1);
        }, {passive:true});
    });
})();

function renderMonth() {
    const N=monthGridWeeks();
    const grid=document.getElementById('daysGrid'); grid.innerHTML='';
    const ts=todayStr();

    // 셀 데이터 생성 — 모든 셀에 실제 날짜 부여(다른 달 포함) → 다일 바가 월 경계에서도 이어짐
    const gridStart=monthGridStart();
    const monthStart=fmt(new Date(currentYear,currentMonth,1));
    let cells=[];
    for(let i=0;i<N*7;i++){
        const dt=new Date(gridStart); dt.setDate(gridStart.getDate()+i);
        const full=fmt(dt);
        // 다중 주 모드에서는 월 경계 흐림 없이 모두 현재로 취급
        const month=(monthWeeks<6||dt.getMonth()===currentMonth)?'cur':(full<monthStart?'prev':'next');
        cells.push({date:dt.getDate(), month, full});
    }

    // 타이틀 — 월 전체: 해당 월 / 다중 주: 가운데 날짜의 월 + 주 수 표기
    if(monthWeeks>=6){
        document.getElementById('periodTitle').textContent=`${currentYear}년 ${currentMonth+1}월`;
    }else{
        const mid=new Date(gridStart); mid.setDate(gridStart.getDate()+Math.floor(N*7/2));
        document.getElementById('periodTitle').textContent=`${mid.getFullYear()}년 ${mid.getMonth()+1}월`;
    }

    const isMobileCal = window.innerWidth<=768; // 모바일: 점 표시 간소화 모드
    // ── 반응형: 그리드가 화면 높이를 꽉 채우도록 주 행 높이 계산 (110px*배율은 하한) ──
    let rowMin=Math.round(110*calFzScale);
    if(window.innerWidth>768){
        const gTop=grid.getBoundingClientRect().top;
        const avail=window.innerHeight-gTop-24; // 하단 여백
        rowMin=Math.max(rowMin, Math.floor((avail-N-1)/N)); // N주 + 1px 경계선
    }
    // 셀 높이에 들어가는 만큼 일정 칩 표시 (기존 고정 3개 → 동적, 최소 3개 보장)
    const chipH=22*calFzScale+2, headH=12+28*calFzScale, badgeH=18;
    const MAX_VISIBLE=Math.max(3, Math.floor((rowMin-headH-badgeH)/chipH));

    // 주 단위로 렌더링
    for(let w=0;w<N;w++){
        const weekCells=cells.slice(w*7, w*7+7);
        const weekStart=weekCells[0].full, weekEnd=weekCells[6].full;

        // ── 이 주에 걸친 다일 일정에 고정 레인(행) 배정 → 바가 같은 행을 유지해 정렬됨 ──
        const _d=v=>(v||'').substring(0,10); // 날짜 정규화 (시간 접미 방어)
        // 외부 오퍼레이터 최상위 → 휴가/개인(red) 상단 우선 — 레인 배정을 먼저 받아 위쪽 바를 차지 (주 단위 고정이라 바 연속성 유지)
        const redFirst=(a,b)=>(isTopEv(b)-isTopEv(a))||((b.color==='red')-(a.color==='red'));
        const weekMulti=events.filter(ev=>isFiltered(ev)&&!ev.parent_id&&evEnd(ev)!==_d(ev.start_date)&&_d(ev.start_date)<=weekEnd&&evEnd(ev)>=weekStart);
        weekMulti.sort((a,b)=> redFirst(a,b) || a.start_date.localeCompare(b.start_date) || b.end_date.localeCompare(a.end_date) || a.id-b.id);
        const laneOf={};
        const lanes=[];
        weekMulti.forEach(ev=>{
            let lane=0;
            while((lanes[lane]||[]).some(r=> !(ev.end_date<r.s||ev.start_date>r.e))) lane++;
            (lanes[lane]=lanes[lane]||[]).push({s:ev.start_date,e:ev.end_date});
            laneOf[ev.id]=lane;
        });
        const LANE_CAP=monthWeeks<6?Math.max(3,Math.floor(MAX_VISIBLE*0.6)):3; // 다일 레인 상한 — 다중 주 모드는 행이 높아 더 표시

        const weekRow=document.createElement('div');
        weekRow.className='week-row';
        if(!isMobileCal) weekRow.style.minHeight=rowMin+'px';
        for(let d=0;d<7;d++){
            const cell=weekCells[d];
            const div=document.createElement('div');
            div.className='day-cell'+(cell.month!=='cur'?' other-month':'');
            div.dataset.date=cell.full;
            if(cell.full===ts) div.classList.add('today');
            const holiday=getHoliday(cell.full);
            const nc=d===0||holiday?'sun':d===6?'sat':'';
            const holidayHtml=holiday?`<span class="holiday-label">${holiday}</span>`:'';
            div.innerHTML=`<div class="day-num-row"><span class="day-num ${nc}">${cell.date}</span>${holidayHtml}</div>`;

            // 모바일 간소화: 칩/바 없이 일정 유무 점만 표시, 상세는 하단 리스트에서
            if(isMobileCal){
                // 그날 일정의 카테고리 색 점 (중복 제거, 최대 3개)
                const dayCats=[];
                events.forEach(ev=>{
                    if(!isFiltered(ev)||ev.parent_id||!evCoversDate(ev,cell.full)) return;
                    if(!dayCats.includes(ev.color)) dayCats.push(ev.color);
                });
                if(dayCats.length){
                    div.classList.add('m-has-ev');
                    dayCats.sort((a,b)=>((b==='red')-(a==='red'))); // 휴가/개인 점이 잘리지 않도록 앞으로
                    div.innerHTML+=`<div class="m-dots">${dayCats.slice(0,3).map(c=>`<i style="background:${chipColor(c)}"></i>`).join('')}</div>`;
                }
                div.addEventListener('click',e=>{
                    if(suppressCellClick){ suppressCellClick=false; return; }
                    selectMobileDay(cell.full);
                });
                weekRow.appendChild(div);
                continue;
            }

            const evList=document.createElement('div');
            evList.className='events-list';

            // 이 날짜를 덮는 다일 일정을 고정 레인에 배치(정렬 유지). 비는 레인은 그날 단일로 채워 빈 칸 방지.
            const coveringByLane={};
            let maxLane=-1;
            weekMulti.forEach(ev=>{
                if(laneOf[ev.id]<LANE_CAP && evCoversDate(ev,cell.full)){
                    coveringByLane[laneOf[ev.id]]=ev;
                    if(laneOf[ev.id]>maxLane) maxLane=laneOf[ev.id];
                }
            });
            // 캡 초과로 못 그리는 다일(더보기로)
            const hiddenMultiHere=weekMulti.filter(ev=>laneOf[ev.id]>=LANE_CAP&&evCoversDate(ev,cell.full)).length;

            // 단일 일정(시간순) — 다른 달 셀(회색)에도 표시. 휴가/개인은 상단 우선 (그룹 내 시간순 유지: stable sort)
            const singles=sortByTime(events.filter(ev=>isFiltered(ev)&&!ev.parent_id&&evEnd(ev)===_d(ev.start_date)&&_d(ev.start_date)===cell.full)).sort(redFirst);
            let si=0; // 단일 큐 인덱스

            // 행 구성: 레인 0..maxLane 은 다일 우선, 빈 레인은 단일로 채움(시간 빠른 단일이 위로). 이후 남은 단일.
            const rows=[];
            for(let L=0;L<=maxLane;L++){
                if(coveringByLane[L]) rows.push({multi:true, ev:coveringByLane[L]});
                else if(si<singles.length) rows.push({multi:false, ev:singles[si++]});
                else rows.push({spacer:true});
            }
            while(si<singles.length) rows.push({multi:false, ev:singles[si++]});

            const isExpanded=expandedDays.has(cell.full);
            if(isExpanded) div.classList.add('expanded');
            // 셀 높이에 들어가는 만큼 표시 (창이 크거나 주 수를 줄이면 그만큼 더 노출, 최소 4행)
            const TARGET_ROWS=Math.max(4, MAX_VISIBLE);
            const shownRows=isExpanded?rows:rows.slice(0, TARGET_ROWS);
            shownRows.forEach(r=>{
                if(r.spacer){ const sp=document.createElement('div'); sp.className='lane-spacer'; evList.appendChild(sp); return; }
                const ev=r.ev;
                const chip=document.createElement('div');
                if(r.multi){
                    const isStart=_d(ev.start_date)===cell.full||d===0||(ev.exclude_weekends&&!evCoversDate(ev,shiftDate(cell.full,-1)));
                    const isEnd=_d(ev.end_date)===cell.full||(ev.exclude_weekends&&!evCoversDate(ev,shiftDate(cell.full,1)));
                    let cls=`event-chip single color-${ev.color} multi-day`;
                    cls+= isStart&&isEnd?' day-start day-end':isStart?' day-start':isEnd?' day-end':' day-cont';
                    if(ev.completed_at) cls+=' is-completed';
                    chip.className=cls;
                    chip.innerHTML=''; // 제목은 주 단위 오버레이가 바 전체 폭에 걸쳐 그림 (PC/모바일 공통)
                    chip.dataset.mev=ev.id; // 오버레이 위치 측정용
                    // 오버레이는 pointer-events:none — 담당자 툴팁은 바 조각에서 hover
                    const mnames=assigneeNamesOf(ev);
                    if(mnames.length>1){ chip.dataset.anames=mnames.join('|'); chip.dataset.atitle=isGuestUser?(ev.location||'일정'):(ev.title||''); }
                } else {
                    chip.className=`event-chip single color-${ev.color}`+(ev.completed_at?' is-completed':'');
                    chip.innerHTML=buildChipHtml(ev);
                }
                chip.onclick=e=>{e.stopPropagation();if(!dragEvent&&!isDragging)openDetailModal(ev);};
                chip.onmousedown=e=>{if(e.button===0)dragStart(ev,e);};
                evList.appendChild(chip);
            });

            // 삭제/변경 흔적 — 원래 있던 날짜에 취소선 고스트 칩 (필터 켠 경우만)
            if(showGhosts&&ghostEvents.length){
                ghostEvents.filter(g=>g.display_start_date<=cell.full&&(g.display_end_date||g.display_start_date)>=cell.full&&activeFilters.has(g.color))
                    .forEach(g=>{
                        const gc=document.createElement('div');
                        gc.className=`event-chip single ghost-chip color-${g.color} g-${g.state}`;
                        gc.innerHTML=`<span class="chip-title">${g.state==='deleted'?'🗑':'↪'} ${_esc(g.title||'(제목 없음)')}</span>`;
                        const at=g.change_at?String(g.change_at).substring(0,16).replace('T',' '):'';
                        gc.title=(g.state==='deleted'?'삭제된 일정':'이동 전 위치 — 클릭하면 현재 일정 열기')+(at?` · ${at}`:'');
                        gc.onclick=e=>{
                            e.stopPropagation();
                            const cur=events.find(ev=>ev.id===g.schedule_id);
                            if(g.state==='modified'&&cur){ openDetailModal(cur); }
                            else{ openChangeLog(); }
                        };
                        evList.appendChild(gc);
                    });
            }

            // 더보기 = (표시 못한 행 중 실제 일정) + (캡 초과 다일)
            const hiddenRealInRows=rows.slice(shownRows.length).filter(r=>!r.spacer).length;
            const hiddenCount=hiddenRealInRows+hiddenMultiHere;
            if(hiddenCount>0){
                const more=document.createElement('div');
                more.className='more-badge';
                more.textContent=`+${hiddenCount}건 더보기`;
                more.onclick=e=>{e.stopPropagation(); openDayPopover(cell.full, div);};
                evList.appendChild(more);
            }

            div.appendChild(evList);
            div.addEventListener('click',e=>{
                if(suppressCellClick){ suppressCellClick=false; return; } // 범위 드래그 직후 클릭 무시
                if(window.innerWidth<=768){
                    selectMobileDay(cell.full);
                } else {
                    if(e.target.closest('.event-chip')||e.target.closest('.more-badge')) return;
                    // 날짜 숫자 클릭: 일정이 있으면 더보기 없이도 그 날 팝업 열기 (게스트 제외 — 일정 내용 비노출)
                    if(!isGuestUser&&e.target.closest('.day-num-row')){
                        const hasEv=events.some(ev=>isFiltered(ev)&&evCoversDate(ev,cell.full));
                        if(hasEv){ openDayPopover(cell.full, div); return; }
                    }
                    if(canEditCalendar&&(e.target===div||e.target.classList.contains('day-num-row')||e.target.classList.contains('day-num'))) openNewModal(cell.full);
                }
            });
            weekRow.appendChild(div);
        }
        grid.appendChild(weekRow);

        // ── 다일 일정 제목 오버레이: 연속 구간(세그먼트)마다 출력 — '주말 제외' 일정은 주말에서 끊긴 구간별로 반복 ──
        if(!isMobileCal){
            const wrRect=weekRow.getBoundingClientRect();
            weekMulti.forEach(ev=>{
                if(laneOf[ev.id]>=LANE_CAP) return;
                // 이 주에서 일정이 덮는 날들의 연속 구간 목록
                const segs=[]; let seg=null;
                for(let d=0;d<7;d++){
                    const f=weekCells[d].full;
                    if(evCoversDate(ev,f)){ if(!seg) seg={s:d,e:d}; else seg.e=d; }
                    else if(seg){ segs.push(seg); seg=null; }
                }
                if(seg) segs.push(seg);
                segs.forEach(sg=>{
                    const startCell=weekRow.children[sg.s];
                    const chip=startCell&&startCell.querySelector(`.event-chip.multi-day[data-mev="${ev.id}"]`);
                    if(!chip) return;
                    const chipRect=chip.getBoundingClientRect();
                    const endRect=weekRow.children[sg.e].getBoundingClientRect();
                    const ov=document.createElement('div');
                    ov.className='mday-title-overlay'+(ev.completed_at?' is-completed':'');
                    // 제목 글자색도 카테고리 설정(text_color)과 연동
                    if(window.CALENDAR_CATEGORIES&&window.CALENDAR_CATEGORIES[ev.color]) ov.style.color=`var(--chip-${ev.color}-text)`;
                    ov.style.top=(chipRect.top-wrRect.top)+'px';
                    ov.style.height=chipRect.height+'px';
                    ov.style.left=(chipRect.left-wrRect.left)+'px';
                    ov.style.width=Math.max(0, (endRect.right-6)-chipRect.left)+'px';
                    ov.innerHTML=buildChipHtml(ev); // 제목 + 담당자 배지 등(연속 일정도 담당자 표기)
                    weekRow.appendChild(ov);
                });
            });
        }
    }
    // 모바일: 오늘 날짜 자동 선택
    if(window.innerWidth<=768){
        const ts=todayStr();
        const cells=document.querySelectorAll('.day-cell[data-date]');
        const todayCell=[...cells].find(c=>c.dataset.date===ts);
        if(todayCell) selectMobileDay(ts);
    }
}

let mobileSelectedDate = null;
function selectMobileDay(dateStr){
    mobileSelectedDate = dateStr;
    document.querySelectorAll('.day-cell.mobile-selected').forEach(c=>c.classList.remove('mobile-selected'));
    const cell=document.querySelector(`.day-cell[data-date="${dateStr}"]`);
    if(cell) cell.classList.add('mobile-selected');
    renderMobileDayEvents(dateStr);
}

function renderMobileDayEvents(dateStr, container){
    container=container||document.getElementById('mobileDayEvents');
    if(!container) return;
    // 시간순 정렬: 종일 먼저, 그 다음 그 날짜 기준 유효 시각 오름차순 (자정 넘김 일정은 끝나는 날 새벽 종료 시각)
    const dayEvs=sortByTime(events.filter(ev=>isFiltered(ev)&&evCoversDate(ev,dateStr)), dateStr);
    const d=new Date(dateStr+'T00:00:00');
    const DAYS_KO_FULL=['일요일','월요일','화요일','수요일','목요일','금요일','토요일'];
    const header=`${d.getMonth()+1}월 ${d.getDate()}일 ${DAYS_KO_FULL[d.getDay()]}`;

    if(!dayEvs.length){
        container.innerHTML=`<div class="mde-header">${header}</div><div class="mde-empty">일정이 없습니다</div>`;
        return;
    }
    const items=dayEvs.map(ev=>{
        const stD=(ev.start_date||'').substring(0,10);
        const tOn=evTimeOn(ev,dateStr);
        const time=ev.is_all_day?'종일':(tOn?((dateStr!==stD&&dateStr===evEnd(ev)&&ev.end_time)?'~'+tOn:tOn):'—');
        const title=ev.title||'(제목 없음)';
        const assignees=assigneeNamesOf(ev).join(', ');
        const moveHtml=moveAddrLinesHtml(ev);
        const catLabel=(typeof CS_CATS!=='undefined'&&CS_CATS[ev.color]&&CS_CATS[ev.color].label)||'';
        const dur=evDurationLabel(ev);
        const sub=[catLabel,dur,(!moveHtml&&ev.location)?ev.location:''].filter(Boolean).join(' · ');
        return `<div class="mde-item${ev.completed_at?' is-completed':''}" onclick="openDetailModal(events.find(e=>e.id===${ev.id}))">
            <div class="mde-time">${time}</div>
            <div class="mde-bar" style="background:${chipColor(ev.color)}"></div>
            <div class="mde-info">
                <div class="mde-title-row">
                    <div class="mde-title">${eventOptIconsHtml(ev)}${_esc(title)}${schedStatusChip(ev)}${shipStatusIcon(ev)}</div>
                    ${assignees?`<span class="mde-assignee">${_esc(assignees)}</span>`:''}
                </div>
                ${sub?`<div class="mde-meta">${_esc(sub)}</div>`:''}
                ${moveHtml}
            </div>
        </div>`;
    }).join('');
    container.innerHTML=`<div class="mde-header">${header} <span style="font-weight:500;color:var(--text-muted);">· ${dayEvs.length}건</span></div>${items}`;
}

// 소요시간 라벨 (예: 1시간 / 1시간 30분)
function evDurationLabel(ev){
    if(ev.is_all_day||!ev.start_time||!ev.end_time) return '';
    const [sh,sm]=ev.start_time.split(':').map(Number);
    const [eh,em]=ev.end_time.split(':').map(Number);
    // 자정을 넘는 일정은 날짜 차이만큼 더해 계산 (예: 15:00~익일 03:00 = 12시간)
    const stD=(ev.start_date||'').substring(0,10);
    const days=Math.max(0, Math.round((new Date(evEnd(ev)+'T00:00:00')-new Date(stD+'T00:00:00'))/86400000));
    let m=days*1440+(eh*60+em)-(sh*60+sm);
    if(!(m>0)) return '';
    const h=Math.floor(m/60), mm=m%60;
    return (h?h+'시간':'')+(mm?(h?' ':'')+mm+'분':'');
}

