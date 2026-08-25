{{-- 칩 생성·툴팁·뷰 전환·글자 크기·검색 --}}
// ── 이벤트 칩 생성 헬퍼 ──
const SPECIAL_ICONS={car:'🚗',brief:'💼',group:'👥',ladder:'▤',pet:'🐾',external_operator:'🎛'};
const SPECIAL_OPT_LABELS={car:'차량 이용 필요',brief:'들고 갈 제품 있음',group:'2인필수 작업',ladder:'사다리 필요',pet:'반려동물 있음',external_operator:'외부 오퍼레이터'};
const SCHED_ICONS={suggest:'💬',hope:'🙏',target:'🎯'};
function buildChipHtml(ev){
    let html='';
    const time=ev.start_time?ev.start_time.substring(0,5):'';
    if(time) html+=`<span class="chip-time">${time}</span>`;
    // 옵션 칩(특수/세부유형/일정옵션) — 타이틀 앞 배치, 모든 뷰 공통 순서
    html+=eventOptIconsHtml(ev);
    // 제목 (의뢰자 이름은 표시하지 않음). flex:1로 늘어나서 담당자 배지를 우측으로 밀어냄
    const title=isGuestUser?(ev.location||'일정'):(ev.title||'');
    html+=`<span class="chip-title">${title}</span>`;
    // 확정 상태 한 글자 칩(확/목/희/제) — 제목 끝 (다른 뷰와 동일 순서)
    html+=schedStatusChip(ev);
    // 배송 상태: 수동 지정 우선, 없으면 등록만 ✕ / 일부 완료 △ / 전부 완료 ○
    html+=shipStatusIcon(ev);
    // 담당자 — chip 우측 정렬. 2명 이상이면 첫 번째 이름 + '+N' (전체 명단은 hover 즉시 툴팁)
    if (ev.assignees && ev.assignees.length) {
        const names = ev.assignees.map(a => (a.name || '').trim()).filter(Boolean);
        if (names.length) {
            const first = names[0];
            const extra = names.length - 1;
            const display = extra > 0 ? `${first} +${extra}` : first;
            const attrEsc = s => s.replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const anames = extra > 0 ? ` data-anames="${attrEsc(names.join('|'))}" data-atitle="${attrEsc(title)}"` : '';
            html += `<span class="chip-badges"><span class="ev-assignee-badge"${anames}>${display}</span></span>`;
        }
    }
    return html;
}

// ── 담당자 전체 명단 툴팁 (hover 즉시 표시) ──
function assigneeNamesOf(ev){ return (ev.assignees||[]).map(a=>(a.name||'').trim()).filter(Boolean); }
const calNamesTip=document.createElement('div');
calNamesTip.id='calNamesTip';
document.body.appendChild(calNamesTip);
document.addEventListener('mouseover',e=>{
    const t=e.target.closest&&e.target.closest('[data-anames]');
    if(!t){ calNamesTip.classList.remove('show'); return; }
    // 제목 + 담당자 전체를 가로 한 줄로
    const tipTitle=t.dataset.atitle||'';
    const tipNames=t.dataset.anames.split('|').join(', ');
    calNamesTip.innerHTML=(tipTitle?`<span class="cnt-t">${_esc(tipTitle)}</span><span class="cnt-sep">·</span>`:'')+`<span class="cnt-n">${_esc(tipNames)}</span>`;
    calNamesTip.classList.add('show');
    const r=t.getBoundingClientRect();
    const tw=calNamesTip.offsetWidth, th=calNamesTip.offsetHeight;
    let left=r.left+r.width/2-tw/2, top=r.top-th-8;
    if(left<8)left=8;
    if(left+tw>window.innerWidth-8)left=window.innerWidth-tw-8;
    if(top<8)top=r.bottom+8;
    calNamesTip.style.left=left+'px';
    calNamesTip.style.top=top+'px';
});
window.addEventListener('scroll',()=>calNamesTip.classList.remove('show'),true);

// ── 뷰 전환 ─────────────────────────────────────────────────────
// ── 캘린더 글자 크기 조절 (노안 대응) ──
const CAL_FZ_KEY='calFontScale';
let calFzScale=parseFloat(localStorage.getItem(CAL_FZ_KEY)||'1')||1;
function applyCalFz(){
    document.documentElement.style.setProperty('--cal-fz', calFzScale);
    const el=document.getElementById('calFontLabel'); if(el) el.textContent=Math.round(calFzScale*100)+'%';
}
function calFont(dir){
    calFzScale=Math.min(2.0, Math.max(0.9, Math.round((calFzScale+dir*0.1)*10)/10));
    localStorage.setItem(CAL_FZ_KEY, calFzScale);
    applyCalFz();
    if(typeof renderView==='function') renderView(); // 다일 제목 오버레이/행 높이 재계산
}

// ── 일정 검색 — Enter 시 목록 뷰 검색만 (자동완성 드롭다운은 리소스 절약 위해 제거) ──
function closeCalSearch(){
    const w=document.getElementById('calSearchWrap');
    if(w) w.style.display='none';
    document.querySelector('.cal-header')?.classList.remove('searching');
}
// Enter → 검색 결과를 목록 뷰로 표시
let agendaSearchQuery=null, agendaSearchResults=[];
async function openSearchListView(){
    const q=document.getElementById('calSearchInput').value.trim();
    if(!q) return;
    closeCalSearch();
    document.getElementById('calSearchInput').blur();
    try{
        const res=await fetch(`/api/events/search?q=${encodeURIComponent(q)}&limit=100`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        agendaSearchResults=await res.json();
    }catch(e){ return; }
    if(currentView!=='list') switchView('list'); // switchView가 agendaSearchQuery를 초기화하므로 이후에 설정
    agendaSearchQuery=q;
    renderAgenda();
}
function clearAgendaSearch(){
    agendaSearchQuery=null; agendaSearchResults=[];
    const inp=document.getElementById('calSearchInput'); if(inp) inp.value='';
    renderView(); loadEvents();
}
// 검색 결과 항목 클릭 → 상세 API로 전체 데이터 로드 후 모달 오픈
async function openSearchResultDetail(id){
    try{
        const res=await fetch(`/api/events/${id}/detail`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        openDetailModal(await res.json());
    }catch(e){}
}
function renderAgendaSearch(){
    const strip=document.getElementById('agendaStrip');
    if(strip) strip.style.display='none';
    document.getElementById('periodTitle').textContent=`검색: "${agendaSearchQuery}"`;
    const wrap=document.getElementById('agendaWrap');
    if(!wrap) return;
    const list=agendaSearchResults;
    let html=`<div class="agenda-search-head">
        <span>🔍 <b>"${_esc(agendaSearchQuery)}"</b> 검색 결과 ${list.length}건${list.length>=100?' (최대 100건 표시)':''}</span>
        <button type="button" class="ship-mini-btn" onclick="clearAgendaSearch()">✕ 검색 해제</button>
    </div>`;
    if(!list.length){
        html+='<div class="agenda-empty">검색 결과가 없습니다.</div>';
        wrap.innerHTML=html; return;
    }
    let lastDate=null;
    list.forEach(ev=>{
        const sd=(ev.start_date||'').substring(0,10);
        if(sd!==lastDate){
            lastDate=sd;
            const d=new Date(sd+'T00:00:00');
            const valid=!isNaN(d.getTime());
            const dowCls=valid&&d.getDay()===0?'ad-sun':valid&&d.getDay()===6?'ad-sat':'';
            // 연월일을 항상 표기 — 날짜 파싱이 실패해도 원본 문자열로 폴백
            const dateLabel=valid?`${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`:(sd||'날짜 미상');
            html+=`<div class="agenda-date-head" style="margin-top:6px;">
                <span class="ad-d ${dowCls}">${dateLabel}</span>
                ${valid?`<span class="ad-dow ${dowCls}">${AGENDA_DOW[d.getDay()]}요일</span>`:''}
            </div>`;
        }
        const ed=(ev.end_date||'').substring(0,10);
        const isMulti=ed&&ed!==sd;
        const timeLabel=ev.is_all_day?'종일':(isMulti?'기간':((ev.start_time||'').substring(0,5)||'시간 미정'));
        const sub=[(isMulti?`${sd.slice(5).replace('-','/')}~${ed.slice(5).replace('-','/')}`:''), ev.client_name, ev.location].filter(Boolean).join(' · ');
        html+=`<div class="agenda-item${ev.completed_at?' is-completed':''}" onclick="openSearchResultDetail(${ev.id})">
            <div class="agenda-stripe" style="background:${chipColor(ev.color)}"></div>
            <div style="flex:1;min-width:0;">
                <div class="agenda-title">${_esc(ev.title||'(제목 없음)')}</div>
                ${sub?`<div class="agenda-sub">${_esc(sub)}</div>`:''}
            </div>
            <div class="agenda-time">${timeLabel}</div>
        </div>`;
    });
    wrap.innerHTML=html;
}

// 창 크기 변경 시 그리드 재계산 (행 높이·연속 바 위치)
let calResizeTimer=null;
window.addEventListener('resize',()=>{
    clearTimeout(calResizeTimer);
    calResizeTimer=setTimeout(()=>renderView(),150);
});

function switchView(view) {
    agendaSearchQuery=null; // 뷰 전환 시 검색 결과 모드 해제 (openSearchListView는 호출 후 다시 설정)
    currentView = view;
    try{ localStorage.setItem('calLastView', view); }catch(e){} // 마지막 본 모드 기억 (다음 방문 시 자동 복원)
    csLoadFilters(); // 뷰별 카테고리 필터 적용 (월/주/일/목록 각각 개인 설정)
    const TAB_IDS={month:'tabMonth',monthc:'tabMonthC',week:'tabWeek',day:'tabDay',list:'tabList'};
    document.querySelectorAll('.view-toggle-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById(TAB_IDS[view])?.classList.add('active');
    document.getElementById('monthView').style.display    = view==='month' ? '' : 'none';
    document.getElementById('monthCompactView').style.display = view==='monthc' ? '' : 'none';
    document.getElementById('timelineView').style.display = (view==='week'||view==='day') ? '' : 'none';
    document.getElementById('listView').style.display     = view==='list' ? '' : 'none';
    if(typeof mcSheetSync==='function') mcSheetSync(); // 컴팩트 뷰 하단 시트 표시/숨김
    // 글자 크기 조절은 월간 뷰에만 적용되므로 그 외 뷰에서는 버튼 숨김
    const fz=document.querySelector('.cal-fontsize'); if(fz) fz.style.display = view==='month' ? '' : 'none';
    // 표시 주 수 선택도 월간 뷰 전용
    const mw=document.getElementById('monthWeeksCtl'); if(mw) mw.style.display = view==='month' ? '' : 'none';
    renderView();
    loadEvents();
}

function changeYear(dir) {
    currentYear += dir;
    if (currentView==='week') { currentWeekStart.setFullYear(currentWeekStart.getFullYear()+dir); }
    if (currentView==='day') { currentDay.setFullYear(currentDay.getFullYear()+dir); }
    renderView(); loadEvents();
}

function changePeriod(dir) {
    if (currentView==='list') {
        moveAgendaWeek(dir); // 목록 뷰: 7일 주 이동
        return;
    }
    if (currentView==='monthc') {
        currentMonth += dir;
        if (currentMonth>11){currentMonth=0;currentYear++;}
        if (currentMonth<0) {currentMonth=11;currentYear--;}
    } else if (currentView==='month') {
        if(monthWeeks<6){
            // 다중 주 모드: 표시 주 수만큼 이동 + 현재 월 상태 동기화
            ensureMultiWeekStart();
            multiWeekStart=new Date(multiWeekStart);
            multiWeekStart.setDate(multiWeekStart.getDate()+dir*monthWeeks*7);
            const mid=new Date(multiWeekStart); mid.setDate(mid.getDate()+Math.floor(monthWeeks*7/2));
            currentYear=mid.getFullYear(); currentMonth=mid.getMonth();
        }else{
            currentMonth += dir;
            if (currentMonth>11){currentMonth=0;currentYear++;}
            if (currentMonth<0) {currentMonth=11;currentYear--;}
        }
    } else if (currentView==='week') {
        currentWeekStart = new Date(currentWeekStart);
        currentWeekStart.setDate(currentWeekStart.getDate()+dir*7);
    } else {
        currentDay = new Date(currentDay);
        currentDay.setDate(currentDay.getDate()+dir);
    }
    renderView(); loadEvents();
}

function toggleCalSearch(){
    const w=document.getElementById('calSearchWrap');
    if(!w) return;
    const show=w.style.display==='none';
    w.style.display=show?'':'none';
    document.querySelector('.cal-header')?.classList.toggle('searching', show); // 모바일: 열려 있는 동안 타이틀 숨김
    if(show) setTimeout(()=>document.getElementById('calSearchInput')?.focus(),0);
}
function goToday() {
    const now = new Date();
    currentYear=now.getFullYear(); currentMonth=now.getMonth();
    currentWeekStart=getWeekStart(now);
    currentDay=new Date(now); currentDay.setHours(0,0,0,0);
    multiWeekStart=getWeekStart(now); // 다중 주 모드도 오늘이 포함된 주로 복귀
    agendaWeekStart=null; agendaSelectedDate=todayStr(); // 목록 뷰도 오늘 기준으로 복귀
    renderView(); loadEvents();
}

function renderView() {
    if (currentView==='month') renderMonth();
    else if (currentView==='monthc') renderMonthCompact();
    else if (currentView==='list') renderAgenda();
    else renderTimeline();
    if(typeof renderCalSide==='function') renderCalSide();
    if(typeof syncMiniPeriod==='function') syncMiniPeriod(); // 모바일 미니 연.월 라벨 갱신
}

