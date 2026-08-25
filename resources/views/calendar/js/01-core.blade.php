{{-- 전역 상수·필터·사이드바·헬퍼 --}}
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const DAYS_KO = ['일','월','화','수','목','금','토'];

// 한국 공휴일 (양력 고정 + 음력 변동 2025~2027)
const KR_HOLIDAYS = {
    // 양력 고정
    '01-01':'신정','03-01':'삼일절','05-05':'어린이날','06-06':'현충일','08-15':'광복절','10-03':'개천절','10-09':'한글날','12-25':'성탄절',
    // 2025 음력 변동
    '2025-01-28':'설날 연휴','2025-01-29':'설날','2025-01-30':'설날 연휴','2025-05-05':'부처님오신날','2025-10-05':'추석 연휴','2025-10-06':'추석','2025-10-07':'추석 연휴','2025-10-08':'대체공휴일',
    // 2026 음력 변동
    '2026-02-16':'설날 연휴','2026-02-17':'설날','2026-02-18':'설날 연휴','2026-05-24':'부처님오신날','2026-09-24':'추석 연휴','2026-09-25':'추석','2026-09-26':'추석 연휴',
    // 2027 음력 변동
    '2027-02-06':'설날 연휴','2027-02-07':'설날','2027-02-08':'설날 연휴','2027-05-13':'부처님오신날','2027-10-14':'추석 연휴','2027-10-15':'추석','2027-10-16':'추석 연휴',
};
function getHoliday(dateStr) {
    if (!dateStr) return null;
    return KR_HOLIDAYS[dateStr] || KR_HOLIDAYS[dateStr.substring(5)] || null;
}

const canEditCalendar = @json(Auth::user()->hasPermission('calendar.edit'));
const isGuestUser = @json(Auth::user()->isGuest());
const isCalAdmin = @json(Auth::user()->isAdmin());
// 첫 화면(이번 달) 이벤트 서버 주입 — 초기 /api/events 왕복 제거
const INITIAL_EVENTS = @json($initialEvents ?? null);
const CAL_USER_ID = @json(Auth::id());
const CAL_VISIT_OPTIONS = @json(collect(explode("\n", (string) \App\Models\Setting::get('calendar_visit_options', '')))->map(fn ($s) => trim($s))->filter()->values());
const HOURS = Array.from({length:14}, (_,i) => i+9); // 9시~22시

let currentYear, currentMonth, currentWeekStart, currentDay;
// ── 월간 뷰 표시 주 수 (다중 주 모드) — 6=월 전체(기본), 2~5=현재 주부터 N주 ──
let monthWeeks=(v=>[2,3,4,5,6].includes(v)?v:6)(parseInt(localStorage.getItem('cal_month_weeks'),10));
let multiWeekStart=null; // 다중 주 모드의 첫 주 시작일(일요일)
function monthGridWeeks(){ return monthWeeks>=6?6:monthWeeks; }
function ensureMultiWeekStart(){
    if(multiWeekStart) return;
    const now=new Date();
    const base=(now.getFullYear()===currentYear&&now.getMonth()===currentMonth)?now:new Date(currentYear,currentMonth,1);
    multiWeekStart=getWeekStart(base);
}
function monthGridStart(){
    if(monthWeeks>=6){
        const first=new Date(currentYear,currentMonth,1);
        const gs=new Date(first); gs.setDate(gs.getDate()-first.getDay());
        return gs;
    }
    ensureMultiWeekStart();
    return new Date(multiWeekStart);
}
function setMonthWeeks(v){
    monthWeeks=(x=>[2,3,4,5,6].includes(x)?x:6)(parseInt(v,10));
    localStorage.setItem('cal_month_weeks',monthWeeks);
    multiWeekStart=null; // 현재 달/오늘 기준으로 재앵커
    updateMwLabel();
    renderView(); loadEvents();
}
function mwStep(dir){ setMonthWeeks(Math.min(6,Math.max(2,monthWeeks+dir))); }
function updateMwLabel(){
    const l=document.getElementById('mwLabel');
    if(l) l.textContent=monthWeeks>=6?'월 전체':`${monthWeeks}주`;
}
// ── 삭제/변경 흔적 오버레이 — 사이드 필터로 켜고 끔 (월간 뷰에 취소선 고스트 칩) ──
let showGhosts = localStorage.getItem('cal_show_ghosts') === '1';
let ghostEvents = []; // 이력 API의 shadow 칩 (modified=이동 전 위치, deleted=삭제 시점 위치)
function csToggleGhosts(){
    showGhosts = !showGhosts;
    localStorage.setItem('cal_show_ghosts', showGhosts ? '1' : '0');
    renderCsCats();
    loadEvents();
}
let events = [], assignees = [], selectedAssignees = [];
let selectedNotifyAssignees = []; // 알림 받을 멤버 (비어있으면 담당자 전체)
let editingId = null, currentColor = 'gold', currentView = 'month';
let editingOrigDT = null; // 편집 중 일정의 원본 날짜/시간 (변경 사유 필수 판정용)
let editingRepeatOrig = null; // 편집 중 반복 일정의 원본 반복 설정 (미변경 시 검증 스킵용)
// 종료일 정규화 — 역전(종료<시작) 데이터는 시작일 하루짜리로 취급
// 주말(토/일) 여부
function isWeekendStr(ds){ const d=new Date(ds+'T00:00:00').getDay(); return d===0||d===6; }
// 일정이 해당 날짜를 포함하는지 — '주말 제외' 다일 일정은 토/일 미포함 (주말에서 칩이 끊김)
function evCoversDate(ev, ds){
    const st=(ev.start_date||'').substring(0,10), en=((ev.end_date||ev.start_date)||'').substring(0,10);
    if(!(st<=ds&&en>=ds)) return false;
    if(ev.exclude_weekends&&st!==en&&isWeekendStr(ds)) return false;
    return true;
}
function evEnd(ev){
    const sd=(ev.start_date||'').substring(0,10);
    const ed=(ev.end_date||'').substring(0,10);
    return (ed && ed>=sd) ? ed : sd;
}
// 카테고리 색 — 관리 설정과 연동된 CSS 변수 반환 (커스텀 카테고리 포함)
function chipColor(c){
    if(c==='holiday') return 'var(--chip-red-bg)';
    return (window.CALENDAR_CATEGORIES&&window.CALENDAR_CATEGORIES[c])?`var(--chip-${c}-bg)`:'var(--accent)';
}
// 최상위 우선 일정 — 스튜디오 카테고리의 '외부 오퍼레이터' 체크 일정은 전체/월/주/일 어디서든 맨 위
function isTopEv(ev){ return !!ev && Array.isArray(ev.special_opts) && ev.special_opts.includes('external_operator'); }
// 배송 상태 인라인 아이콘 — 수동 지정만 표시 (자동 판정 제거: 일부 송장만 등록해도
// 전부 도착하면 완료(○)로 보여 착각을 유발하던 문제 → 담당자가 직접 지정)
const SHIP_ICON_MAP={all:['○','s-all','배송 완료'],part:['△','s-part','부분 배송'],none:['✕','s-none','미배송']};
function shipStatusIcon(ev){
    if(!ev) return '';
    if(ev.ship_icon_override&&SHIP_ICON_MAP[ev.ship_icon_override]){
        const [ico,cls,tt]=SHIP_ICON_MAP[ev.ship_icon_override];
        return `<span class="chip-ship ${cls}" title="${tt}">${ico}</span>`;
    }
    return '';
}
// 일정 옵션(빠름/긴급/AS이후) 아이콘 맵
const SCHED_EVENT_ICONS={fast:'←',urgent:'🚨',after:'→'};
// 확정 상태 — 리스트/칩에는 한 글자로 축약 표시 (제목 끝), 툴팁·설명에는 전체 라벨
const SCHED_CHIP_LABELS={suggest:'제',hope:'희',target:'목',confirmed:'확'};
const SCHED_FULL_LABELS={suggest:'제안',hope:'희망',target:'목표',confirmed:'확정'};
// 확정 상태 단계 설명 — 버튼 선택 시 모달에 표시
const SCHED_OPT_DESCS={
    suggest:'💬 제안 — 우리가 의뢰자에게 제안해 둔 날짜 (아직 미확정)',
    hope:'🙏 희망 — 의뢰자가 희망하는 날짜 (아직 미확정)',
    target:'🎯 목표 — 내부적으로 잡아둔 목표 날짜',
    confirmed:'✅ 확정 — 의뢰자와 협의가 끝난 확정 일정',
};
function updateSchedOptDesc(){
    const a=document.querySelector('#scheduleOpts .sched-opt-btn.active');
    const el=document.getElementById('schedOptDesc');
    if(el) el.textContent=a?(SCHED_OPT_DESCS[a.dataset.sopt]||''):'';
}
const SCHED_EVENT_CHIP_LABELS={fast:'빠른',urgent:'긴급',after:'이후'};
// 시기 요청 전체 라벨 (아이콘 툴팁용 — 아이콘 맵 SCHED_EVENT_ICONS는 위에 정의됨)
const SCHED_EVENT_FULL_LABELS={fast:'빠른 일정 희망',urgent:'긴급 일정',after:'날짜 이후 희망'};
function optChip(label,cls,title){ return `<span class="opt-chip${cls?' '+cls:''}"${title?` title="${title}"`:''}>${label}</span>`; }
// 시기 요청(아이콘) + 특수옵션(아이콘) 묶음 — 제목 앞에 표시, 전 뷰 공용 (확정 상태는 schedStatusChip으로 제목 끝에)
function eventOptIconsHtml(ev){
    if(!ev) return '';
    let h='';
    (ev.sched_event_opts||[]).forEach(o=>{ if(SCHED_EVENT_ICONS[o]) h+=`<span class="opt-ic${o==='urgent'?'':' opt-ic-arrow'}" title="${SCHED_EVENT_FULL_LABELS[o]||o}">${SCHED_EVENT_ICONS[o]}</span>`; });
    (ev.special_opts||[]).forEach(o=>{ if(SPECIAL_ICONS[o]) h+=`<span class="opt-ic" title="${SPECIAL_OPT_LABELS[o]||o}">${SPECIAL_ICONS[o]}</span>`; });
    return h;
}
// 확정 상태 — 한 글자 칩(확/목/희/제), 제목 끝에 붙임
function schedStatusChip(ev){
    if(!ev||!ev.sched_opt||!SCHED_CHIP_LABELS[ev.sched_opt]) return '';
    return optChip(SCHED_CHIP_LABELS[ev.sched_opt],(ev.sched_opt==='confirmed'?'confirmed':'accent')+' sched-end',SCHED_FULL_LABELS[ev.sched_opt]);
}
// 이사세팅 여부
function isMoveSetting(ev){
    if(!ev || ev.color!=='gold') return false;
    const rt=(ev.request_data&&ev.request_data.req_topic)||'';
    return rt.split(',').map(s=>s.trim()).includes('이사세팅');
}
// 이사세팅 출발/도착 2줄 HTML (리스트용) — 이사세팅 아닐 땐 빈 문자열
function moveAddrLinesHtml(ev){
    if(!isMoveSetting(ev)) return '';
    const g=ev.request_data||{};
    const from = g.move_no_from ? '없음' : (g.move_from_location||g.move_from_address||'—');
    const to = ev.location||ev.address||'—';
    return `<div class="agenda-move"><span>출발: ${_esc(from)}</span><span>도착: ${_esc(to)}</span></div>`;
}
let expandedDays = new Set();

// ── 초기화 ──────────────────────────────────────────────────────
function init() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth();
    currentWeekStart = getWeekStart(now);
    currentDay = new Date(now); currentDay.setHours(0,0,0,0);
    loadAssignees();
    applyCalFz(); // 저장된 글자 크기 적용(라벨 갱신)
    // 모바일: ⋯ 메뉴 항목을 필터 패널(☰) 맨 하단 '도구' 섹션으로 이동
    if(window.matchMedia('(max-width: 768px)').matches){
        const menu=document.getElementById('calMenu');
        const dst=document.getElementById('csToolsBody');
        if(menu&&dst){
            while(menu.firstChild) dst.appendChild(menu.firstChild);
            document.getElementById('csTools').style.display='';
            const wrap=document.getElementById('calMoreWrap');
            if(wrap) wrap.style.display='none';
        }
    }
    // 마지막으로 본 캘린더 모드 복원 (탭 UI/뷰 표시까지 switchView와 동일하게 반영)
    let savedView=localStorage.getItem('calLastView');
    if(savedView&&['month','monthc','week','day','list'].includes(savedView)&&savedView!==currentView){
        currentView=savedView;
        const TAB_IDS={month:'tabMonth',monthc:'tabMonthC',week:'tabWeek',day:'tabDay',list:'tabList'};
        document.querySelectorAll('.view-toggle-btn').forEach(b=>b.classList.remove('active'));
        document.getElementById(TAB_IDS[savedView])?.classList.add('active');
        document.getElementById('monthView').style.display=savedView==='month'?'':'none';
        document.getElementById('monthCompactView').style.display=savedView==='monthc'?'':'none';
        document.getElementById('timelineView').style.display=(savedView==='week'||savedView==='day')?'':'none';
        document.getElementById('listView').style.display=savedView==='list'?'':'none';
        const fz=document.querySelector('.cal-fontsize'); if(fz) fz.style.display=savedView==='month'?'':'none';
    }
    csLoadFilters(); // 현재(복원된) 뷰의 카테고리 필터 적용 — 연/월 설정 이후 시점
    // 월간 표시 주 수 초기화 (저장값 복원)
    updateMwLabel();
    const mwCtl=document.getElementById('monthWeeksCtl');
    if(mwCtl) mwCtl.style.display=currentView==='month'?'':'none';
    if (Array.isArray(INITIAL_EVENTS)) {
        events = INITIAL_EVENTS; // 서버 주입분으로 즉시 렌더 (이번 달)
        renderView();
        loadEvents(); // 백그라운드로 라이브 API 대조 — 스냅샷 어긋남으로 인한 월간 누락 방지
    } else {
        renderView();
        loadEvents();
    }
}

function getWeekStart(d) {
    const r = new Date(d); r.setDate(r.getDate()-r.getDay()); r.setHours(0,0,0,0); return r;
}
function fmt(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function todayStr() { return fmt(new Date()); }

// ── 필터 ──
// 실제 렌더된 카테고리 칩(.filter-btn.active)에서 초기화 — 커스텀 카테고리도 누락 없이 매칭
let activeFilters = (function(){
    const keys=[...document.querySelectorAll('#filterBar .filter-btn')].map(b=>b.dataset.filter).filter(Boolean);
    keys.push('holiday'); // 공휴일은 칩이 없어도 항상 포함
    return new Set(keys.length?keys:['gold','teal','blue','red','green','purple','holiday']);
})();
let activeAssigneeIds = new Set(); // 비어있으면 전체, 값이 있으면 해당 담당자들(OR) 만
function toggleFilter(btn){
    const f=btn.dataset.filter;
    if(activeFilters.has(f)){activeFilters.delete(f);btn.classList.remove('active');}
    else{activeFilters.add(f);btn.classList.add('active');}
    csSaveHidden();
    renderView();
}

// ── 사이드 필터 (미니멀 접이식, 데스크탑) ──
const CS_CATS = @json(\App\Models\CalendarCategory::map());
const CS_HIDDEN_KEY = 'calHiddenCats';
let csMiniY = null, csMiniM = null; // 미니 달력 표시 월

// ── 뷰별 카테고리 필터 (개인 설정) — 월(전체 포함)/주/일/목록 각각 따로 저장 ──
function csBucket(){ return currentView==='monthc' ? 'month' : (currentView||'month'); }
function csHiddenKeyFor(){ return CS_HIDDEN_KEY+':'+csBucket(); }
function csLoadFilters(){
    // 전 카테고리 ON으로 초기화 후 현재 뷰의 숨김 목록 적용 (뷰별 저장값 없으면 구버전 공용 값 폴백)
    Object.keys(CS_CATS).forEach(k => activeFilters.add(k));
    activeFilters.add('holiday');
    let hidden = null;
    try{ hidden = JSON.parse(localStorage.getItem(csHiddenKeyFor()) || 'null'); }catch(e){}
    if(!Array.isArray(hidden)){
        try{ hidden = JSON.parse(localStorage.getItem(CS_HIDDEN_KEY) || '[]'); }catch(e){ hidden = []; }
    }
    hidden.filter(k => CS_CATS[k]).forEach(k => activeFilters.delete(k));
    document.querySelectorAll('#filterBar .filter-btn').forEach(b => b.classList.toggle('active', activeFilters.has(b.dataset.filter)));
    // 사이드 패널 갱신 — init 이전(연/월 미설정)에는 건너뜀 (미니 달력 NaN 방지)
    if(typeof renderCalSide === 'function' && typeof currentYear === 'number') renderCalSide();
}

function csSaveHidden(){
    try{ localStorage.setItem(csHiddenKeyFor(), JSON.stringify(Object.keys(CS_CATS).filter(k => !activeFilters.has(k)))); }catch(e){}
}
function csToggleCat(k){
    if(activeFilters.has(k)) activeFilters.delete(k); else activeFilters.add(k);
    const btn = document.querySelector(`#filterBar .filter-btn[data-filter="${k}"]`);
    if(btn) btn.classList.toggle('active', activeFilters.has(k));
    csSaveHidden();
    renderView();
}
function csCatRow(k, on){
    const c = CS_CATS[k];
    // 체크박스 클릭 = 켜기/끄기, 라벨 클릭 = 이 카테고리만 보기(만 보기)
    return `<div class="cs-cat${on?'':' off'}">
        <span class="cs-check${on?' on':''}" style="--cat-c:var(--chip-${k}-bg)" onclick="csToggleCat('${k}')" title="${on?'클릭하여 끄기':'클릭하여 켜기'}"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></span>
        <span class="cs-cat-label" onclick="csSoloCat('${k}')" title="이 카테고리만 보기">${(c.label||k).replace(/[<>&]/g,'')}</span>
    </div>`;
}
// '만 보기' — 해당 카테고리만 켜기. 해제 시 전체가 아니라 '만 보기 이전의 선택 상태'로 복원 (뷰별 저장)
const CS_PREV_KEY = 'calPrevCats';
function csPrevKeyFor(){ return CS_PREV_KEY+':'+csBucket(); }
function csSoloCat(k){
    const keys = Object.keys(CS_CATS);
    const isSolo = activeFilters.has(k) && keys.every(x => x === k || !activeFilters.has(x));
    let targetOn;
    if(!isSolo){
        // 단독 보기 진입 — 현재 선택을 저장해둠 (이미 다른 카테고리 단독 상태면 최초 저장본 유지)
        const onCats = keys.filter(x => activeFilters.has(x));
        if(onCats.length > 1){
            try{ localStorage.setItem(csPrevKeyFor(), JSON.stringify(onCats)); }catch(e){}
        }
        targetOn = new Set([k]);
    }else{
        // 단독 해제 — 이전 선택 복원 (저장본 없으면 전체)
        let prev = null;
        try{ prev = JSON.parse(localStorage.getItem(csPrevKeyFor()) || 'null'); }catch(e){}
        const restored = Array.isArray(prev) ? prev.filter(x => CS_CATS[x]) : [];
        targetOn = restored.length ? new Set(restored) : new Set(keys);
        try{ localStorage.removeItem(csPrevKeyFor()); }catch(e){}
    }
    keys.forEach(x => {
        const on = targetOn.has(x);
        if(on) activeFilters.add(x); else activeFilters.delete(x);
        const btn = document.querySelector(`#filterBar .filter-btn[data-filter="${x}"]`);
        if(btn) btn.classList.toggle('active', on);
    });
    csSaveHidden();
    renderView();
}
function renderCsCats(){
    const keys = Object.keys(CS_CATS);
    document.getElementById('csOnCount').textContent = keys.filter(k => activeFilters.has(k)).length;
    document.getElementById('csCatsOn').innerHTML = keys.map(k => csCatRow(k, activeFilters.has(k))).join('')
        // 특수 필터: 삭제/변경 흔적 — 일반 카테고리처럼 체크박스로 켜고 끔 (월간 뷰에 취소선 고스트 표시)
        + `<div class="cs-cat cs-ghost-row${showGhosts?'':' off'}">
            <span class="cs-check${showGhosts?' on':''}" style="--cat-c:#9aa3b2" onclick="csToggleGhosts()" title="${showGhosts?'끄기':'켜기'}"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></span>
            <span class="cs-cat-label" onclick="csToggleGhosts()" title="삭제·이동된 일정의 원래 위치를 취소선으로 표시">🗑 삭제/변경 흔적</span>
        </div>`;
    // 접힘 레일: 카테고리 점 (클릭으로 토글)
    const rail = document.getElementById('csRail');
    if(rail) rail.innerHTML = '<button type="button" class="cs-collapse-btn" onclick="csToggleSide()" title="필터 펼치기">»</button>' + keys.map(k =>
        `<span class="cs-dot${activeFilters.has(k)?'':' off'}" style="background:var(--chip-${k}-bg)" onclick="csToggleCat('${k}')" title="${(CS_CATS[k].label||k).replace(/[<>&"]/g,'')}"></span>`
    ).join('');
}
// 저해상도: 리모컨 패널 토글
function csToggleMobile(force){
    const side=document.getElementById('calSide');
    const bd=document.getElementById('calSideBackdrop');
    if(!side) return;
    const open = typeof force==='boolean' ? force : !side.classList.contains('mobile-open');
    side.classList.toggle('mobile-open', open);
    if(bd) bd.classList.toggle('show', open);
    if(open) renderCalSide();
}
function csMiniMove(dir){
    csMiniM += dir;
    if(csMiniM > 11){ csMiniM = 0; csMiniY++; }
    if(csMiniM < 0){ csMiniM = 11; csMiniY--; }
    renderCsMini();
}
function csGoDate(dstr){
    const d = new Date(dstr + 'T00:00:00');
    currentYear = d.getFullYear(); currentMonth = d.getMonth();
    currentWeekStart = getWeekStart(d);
    currentDay = new Date(d); currentDay.setHours(0,0,0,0);
    multiWeekStart = getWeekStart(d); // 다중 주 모드도 선택한 날짜의 주로 이동
    if(currentView === 'list'){ agendaWeekStart = null; agendaSelectedDate = dstr; }
    renderView(); loadEvents();
}
function renderCsMini(){
    const grid = document.getElementById('csMiniGrid');
    if(!grid) return;
    if(csMiniY === null){ csMiniY = currentYear; csMiniM = currentMonth; }
    document.getElementById('csMiniLabel').textContent = `${csMiniY}년 ${csMiniM+1}월`;
    const first = new Date(csMiniY, csMiniM, 1);
    const start = new Date(first); start.setDate(1 - first.getDay()); // 일요일 시작
    const today = todayStr();
    // 현재 뷰 기준 강조: 일간=해당 일, 주간=주 범위
    const selSet = new Set();
    let weekBand = null; // 주간: 연속 밴드 시작/끝
    if(currentView === 'day'){ selSet.add(fmt(currentDay)); }
    else if(currentView === 'week'){
        const days=[];
        for(let i=0;i<7;i++){ const d=new Date(currentWeekStart); d.setDate(d.getDate()+i); days.push(fmt(d)); }
        weekBand = { set:new Set(days), start:days[0], end:days[6] };
    }
    let html = '';
    const cur = new Date(start);
    for(let i=0;i<42;i++){
        const ds = fmt(cur);
        const dim = cur.getMonth() !== csMiniM;
        const inBand = weekBand && weekBand.set.has(ds);
        const cls = ['cs-mini-day', dim?'dim':'', ds===today?'today':'',
            inBand?'selr':'', (inBand&&ds===weekBand.start)?'sel-start':'', (inBand&&ds===weekBand.end)?'sel-end':'',
            (!inBand&&ds!==today&&selSet.has(ds))?'sel':''].filter(Boolean).join(' ');
        html += `<span class="${cls}" onclick="csGoDate('${ds}')">${cur.getDate()}</span>`;
        cur.setDate(cur.getDate()+1);
        if(i>=34 && cur.getMonth()!==csMiniM && cur.getDay()===0) break; // 마지막 주가 전부 다음달이면 생략
    }
    grid.innerHTML = html;
}
const CS_COLLAPSE_KEY='calSideFilterCollapsed';
function csToggleSide(){
    const side=document.getElementById('calSide');
    if(!side) return;
    const collapsed=side.classList.toggle('collapsed');
    try{ localStorage.setItem(CS_COLLAPSE_KEY, collapsed?'1':''); }catch(e){}
    renderCalSide();
}
(function(){
    try{
        if(localStorage.getItem(CS_COLLAPSE_KEY)==='1'){
            const side=document.getElementById('calSide');
            if(side) side.classList.add('collapsed');
        }
    }catch(e){}
})();
// 데스크탑: 사이드 패널이 화면 세로 중앙을 따라다니도록 sticky top 계산
function csCenterSide(){
    // 세로 중앙 추적은 위쪽 여백이 과해 보인다는 피드백으로 상단 고정으로 변경
    const el=document.getElementById('calSideSticky');
    if(!el) return;
    el.style.top=window.innerWidth<=1024?'':'10px';
}
(function(){
    const el=document.getElementById('calSideSticky');
    if(!el) return;
    window.addEventListener('resize', csCenterSide);
    new ResizeObserver(csCenterSide).observe(el); // 필터/담당자 목록 변화로 높이가 바뀌어도 재계산
})();
function renderCalSide(){
    const side=document.getElementById('calSide');
    if(!side) return;
    renderCsMini();
    renderCsCats();
    csAlignTop();
}
// 사이드바 상단을 요일 헤더 아래(그리드 시작선)에 맞춤
function csAlignTop(){
    const side=document.getElementById('calSide');
    if(!side) return;
    if(window.innerWidth<=1024){ side.style.marginTop='0'; return; }
    let head=null;
    if(currentView==='month') head=document.querySelector('#monthView .weekdays');
    else if(currentView==='week'||currentView==='day') head=document.querySelector('#timelineView .tl-header');
    let offset=0;
    if(head && head.offsetHeight){
        const bodyTop=document.getElementById('calBody').getBoundingClientRect().top;
        offset=Math.max(0, Math.round(head.getBoundingClientRect().bottom - bodyTop));
    }
    side.style.marginTop=offset?offset+'px':'0';
    // 패널 높이는 콘텐츠에 맞춤 — 그리드 전체 높이로 늘리면 아래 빈 공간이 과도함
    side.style.height='';
}
// 담당자 필터(셀렉트+칩)를 사이드 패널로 이동 (전 해상도 공통)
(function(){
    const dst = document.getElementById('csAssignees');
    const sel = document.getElementById('assigneeFilter');
    const chips = document.getElementById('assigneeFilterChips');
    if(dst && sel && chips){ dst.appendChild(sel); dst.appendChild(chips); }
})();
function isFiltered(ev){
    if (!activeFilters.has(ev.color)) return false;
    if (activeAssigneeIds.size > 0) {
        if (!Array.isArray(ev.assignees) || !ev.assignees.some(a => activeAssigneeIds.has(a.id))) return false;
    }
    return true;
}
function populateAssigneeFilter() {
    const sel = document.getElementById('assigneeFilter');
    if (!sel) return;
    // 이미 선택된 담당자는 드롭다운에서 제외
    sel.innerHTML = '<option value="">담당자 추가…</option>'
        + assignees
            .filter(a => !activeAssigneeIds.has(a.id))
            .map(a => `<option value="${a.id}">${(a.name||'').replace(/[<>&"]/g,'')}</option>`).join('');
    renderAssigneeChips();
}
function renderAssigneeChips() {
    const wrap = document.getElementById('assigneeFilterChips');
    if (!wrap) return;
    wrap.innerHTML = [...activeAssigneeIds].map(id => {
        const a = assignees.find(x => x.id === id);
        const name = a ? (a.name || `#${id}`) : `#${id}`;
        return `<span class="af-chip">${name.replace(/[<>&"]/g,'')}<button type="button" title="필터 해제" onclick="removeAssigneeFilter(${id})">✕</button></span>`;
    }).join('');
    const sel = document.getElementById('assigneeFilter');
    if (sel) sel.classList.toggle('active-filter', activeAssigneeIds.size > 0);
}
function onAssigneeFilterChange() {
    const sel = document.getElementById('assigneeFilter');
    const v = sel.value;
    if (v) {
        activeAssigneeIds.add(+v);
        populateAssigneeFilter(); // 선택된 항목을 드롭다운에서 제거 + 칩 갱신
        sel.value = '';
        renderView();
    }
}
function removeAssigneeFilter(id) {
    activeAssigneeIds.delete(id);
    populateAssigneeFilter();
    renderView();
}

