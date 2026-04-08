@extends('layouts.app')

@section('title', '캘린더 - 닥터고블린 오피스')

@push('styles')
<style>
    :root {
        --gold: #c8b08a;
        --teal: #4ecdc4;
        --blue: #8ab4c8;
        --red: #c87a7a;
        --green: #7ac87a;
        --purple: #9b70c8;
    }

    .cal-header { padding:12px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); background:var(--surface); }
    .cal-header-left { display:flex; align-items:center; gap:10px; }
    .month-nav { display:flex; align-items:center; gap:8px; }
    .month-nav button { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:all 0.15s; }
    .month-nav button:hover { background:var(--surface2); color:var(--text); }
    .month-title { font-size:15px; font-weight:700; min-width:160px; text-align:center; }
    .nav-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 12px; border-radius:6px; font-size:12px; cursor:pointer; transition:all 0.15s; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); }

    .view-tabs { display:flex; background:var(--surface2); border-radius:8px; padding:2px; gap:2px; }
    .view-tab { padding:5px 14px; border-radius:6px; font-size:12px; cursor:pointer; border:none; background:none; color:var(--text-muted); transition:all 0.15s; }
    .view-tab.active { background:var(--surface); color:var(--accent); font-weight:600; }

    .add-btn { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:filter 0.15s; }
    .add-btn:hover { filter:brightness(1.1); }

    .legend { padding:8px 20px; display:flex; gap:12px; flex-wrap:wrap; border-bottom:1px solid var(--border); background:var(--surface); }
    .legend-item { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--text-muted); }
    .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

    /* ── 월간 뷰 ── */
    .calendar-wrap { padding:16px 20px; }
    .weekdays { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; margin-bottom:4px; }
    .weekday { text-align:center; font-size:11px; color:var(--text-muted); padding:6px 0; letter-spacing:0.08em; }
    .weekday:first-child { color:var(--red); }
    .weekday:last-child { color:var(--blue); }
    .days-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:var(--border); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
    .day-cell { background:var(--bg); min-height:100px; padding:6px; cursor:pointer; transition:background 0.1s; }
    .day-cell:hover { background:var(--surface2); }
    .day-cell.other-month { opacity:0.35; }
    .day-cell.today .day-num { background:var(--accent); color:#1a1207; border-radius:50%; }
    .day-num { font-size:12px; color:var(--text-muted); margin-bottom:4px; width:22px; height:22px; display:flex; align-items:center; justify-content:center; }
    .day-num.sun { color:var(--red); }
    .day-num.sat { color:var(--blue); }

    /* ── 이벤트 칩 ── */
    .event-chip { font-size:11px; padding:2px 6px; border-radius:4px; margin-bottom:2px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:filter 0.1s; }
    .event-chip:hover { filter:brightness(1.15); }
    .event-chip.color-gold   { background:var(--gold); color:#1a1207; }
    .event-chip.color-teal   { background:var(--teal); color:#1a1207; }
    .event-chip.color-blue   { background:var(--blue); color:#1a1207; }
    .event-chip.color-red    { background:var(--red); color:#fff; }
    .event-chip.color-green  { background:var(--green); color:#1a1207; }
    .event-chip.color-purple { background:var(--purple); color:#fff; }
    .event-chip.color-holiday{ background:var(--red); color:#fff; }
    .more-events { font-size:10px; color:var(--text-muted); padding:1px 4px; cursor:pointer; }

    /* ── 주간/일간 공통 ── */
    .timeline-wrap { padding:0 20px 20px; overflow-x:auto; }
    .timeline-grid { border:1px solid var(--border); border-radius:8px; overflow:hidden; min-width:600px; }

    .tl-header { display:flex; background:var(--surface); border-bottom:1px solid var(--border); }
    .tl-time-col { width:60px; flex-shrink:0; border-right:1px solid var(--border); }
    .tl-day-col { flex:1; text-align:center; padding:10px 6px; border-right:1px solid var(--border); min-width:80px; }
    .tl-day-col:last-child { border-right:none; }
    .tl-day-col.today-col { background:rgba(200,176,138,0.06); }
    .tl-day-name { font-size:11px; color:var(--text-muted); }
    .tl-day-num { font-size:18px; font-weight:700; margin-top:2px; }
    .tl-day-num.today-num { background:var(--accent); color:#1a1207; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; margin:2px auto 0; font-size:16px; }
    .tl-day-num.sun-c { color:var(--red); }
    .tl-day-num.sat-c { color:var(--blue); }

    /* 종일 행 */
    .tl-allday-row { display:flex; border-bottom:1px solid var(--border); }
    .tl-allday-label { width:60px; flex-shrink:0; border-right:1px solid var(--border); font-size:10px; color:var(--text-muted); display:flex; align-items:center; justify-content:center; padding:4px; background:var(--surface); }
    .tl-allday-cell { flex:1; min-height:28px; padding:3px 4px; border-right:1px solid var(--border); background:var(--bg); min-width:80px; }
    .tl-allday-cell:last-child { border-right:none; }
    .tl-allday-cell.today-col { background:rgba(200,176,138,0.04); }

    /* 시간 슬롯 */
    .tl-body { position:relative; }
    .tl-row { display:flex; }
    .tl-time-label { width:60px; flex-shrink:0; border-right:1px solid var(--border); padding:0 6px; font-size:10px; color:var(--text-muted); text-align:right; height:48px; display:flex; align-items:flex-start; padding-top:4px; background:var(--surface); }
    .tl-slot { flex:1; border-right:1px solid var(--border); border-bottom:1px solid var(--border); height:48px; position:relative; cursor:pointer; background:var(--bg); transition:background 0.1s; min-width:80px; }
    .tl-slot:last-child { border-right:none; }
    .tl-slot:hover { background:var(--surface2); }
    .tl-slot.today-col { background:rgba(200,176,138,0.04); }

    .tl-event { position:absolute; left:2px; right:2px; border-radius:4px; padding:2px 5px; font-size:11px; overflow:hidden; cursor:pointer; z-index:1; transition:filter 0.1s; line-height:1.3; }
    .tl-event:hover { filter:brightness(1.15); z-index:2; }
    .tl-event.color-gold   { background:var(--gold); color:#1a1207; }
    .tl-event.color-teal   { background:var(--teal); color:#1a1207; }
    .tl-event.color-blue   { background:var(--blue); color:#1a1207; }
    .tl-event.color-red    { background:var(--red); color:#fff; }
    .tl-event.color-green  { background:var(--green); color:#1a1207; }
    .tl-event.color-purple { background:var(--purple); color:#fff; }

    /* ── 모달 ── */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:500px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .modal-title { font-size:16px; font-weight:700; }
    .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
    .field-group { margin-bottom:14px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; letter-spacing:0.05em; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; transition:border-color 0.15s; }
    .field-input:focus { border-color:var(--accent); }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .field-textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; resize:vertical; }
    .field-textarea:focus { border-color:var(--accent); }
    .color-picker { display:flex; gap:8px; flex-wrap:wrap; }
    .color-btn { width:28px; height:28px; border-radius:50%; border:2px solid transparent; cursor:pointer; transition:transform 0.15s; }
    .color-btn:hover { transform:scale(1.15); }
    .color-btn.active { border-color:var(--text); transform:scale(1.15); }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-delete { background:none; border:1px solid var(--red); color:var(--red); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .assignee-list { display:flex; flex-wrap:wrap; gap:6px; }
    .assignee-chip { padding:4px 10px; border-radius:20px; border:1px solid var(--border); font-size:12px; cursor:pointer; color:var(--text-muted); transition:all 0.15s; }
    .assignee-chip.selected { background:var(--accent); color:#1a1207; border-color:var(--accent); font-weight:600; }
    .assignee-chip:hover { border-color:var(--accent); }
</style>
@endpush

@section('content')
<div class="cal-header">
    <div class="cal-header-left">
        <div class="month-nav">
            <button onclick="changePeriod(-1)">‹</button>
            <div class="month-title" id="periodTitle"></div>
            <button onclick="changePeriod(1)">›</button>
        </div>
        <button class="nav-btn" onclick="goToday()">오늘</button>
        <div class="view-tabs">
            <button class="view-tab active" id="tabMonth" onclick="switchView('month')">월간</button>
            <button class="view-tab"        id="tabWeek"  onclick="switchView('week')">주간</button>
            <button class="view-tab"        id="tabDay"   onclick="switchView('day')">일간</button>
        </div>
    </div>
    @if(Auth::user()->hasPermission('calendar.edit'))
        <button class="add-btn" onclick="openNewModal()">+ 일정</button>
    @endif
</div>

<div class="legend">
    <div class="legend-item"><div class="legend-dot" style="background:var(--gold)"></div>방문의뢰</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--teal)"></div>원격/방송룸</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div>사내업무</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>휴가/개인</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div>촬영/스튜디오</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--purple)"></div>미팅/내방</div>
</div>

<!-- 월간 뷰 -->
<div id="monthView">
    <div class="calendar-wrap">
        <div class="weekdays">
            <div class="weekday">일</div><div class="weekday">월</div><div class="weekday">화</div>
            <div class="weekday">수</div><div class="weekday">목</div><div class="weekday">금</div><div class="weekday">토</div>
        </div>
        <div class="days-grid" id="daysGrid"></div>
    </div>
</div>

<!-- 주간/일간 뷰 -->
<div id="timelineView" style="display:none;">
    <div class="timeline-wrap">
        <div class="timeline-grid" id="timelineGrid"></div>
    </div>
</div>

<!-- 일정 모달 -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitleText">새 일정</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">제목</div>
            <input class="field-input" id="inputTitle" type="text" placeholder="일정 제목">
        </div>
        <div class="field-group">
            <div class="field-label">유형</div>
            <div class="color-picker">
                <div class="color-btn active" style="background:var(--gold)"   data-color="gold"   onclick="setColor(this)" title="방문의뢰"></div>
                <div class="color-btn"        style="background:var(--teal)"   data-color="teal"   onclick="setColor(this)" title="원격/방송룸"></div>
                <div class="color-btn"        style="background:var(--blue)"   data-color="blue"   onclick="setColor(this)" title="사내업무"></div>
                <div class="color-btn"        style="background:var(--red)"    data-color="red"    onclick="setColor(this)" title="휴가/개인"></div>
                <div class="color-btn"        style="background:var(--green)"  data-color="green"  onclick="setColor(this)" title="촬영/스튜디오"></div>
                <div class="color-btn"        style="background:var(--purple)" data-color="purple" onclick="setColor(this)" title="미팅/내방"></div>
            </div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">시작일</div>
                <input class="field-input" id="inputStartDate" type="date">
            </div>
            <div class="field-group">
                <div class="field-label">종료일</div>
                <input class="field-input" id="inputEndDate" type="date">
            </div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">시작 시간</div>
                <input class="field-input" id="inputStartTime" type="time" value="13:00">
            </div>
            <div class="field-group">
                <div class="field-label">종료 시간</div>
                <input class="field-input" id="inputEndTime" type="time" value="14:00">
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">고객명</div>
            <input class="field-input" id="inputClientName" type="text" placeholder="의뢰인/고객명">
        </div>
        <div class="field-group">
            <div class="field-label">주소</div>
            <div style="display:flex; gap:8px; margin-bottom:6px;">
                <input class="field-input" id="inputAddress" type="text" placeholder="우편번호 검색 버튼을 눌러주세요" readonly style="flex:1; cursor:pointer;" onclick="searchCalAddr()">
                <button type="button" onclick="searchCalAddr()" style="background:var(--accent); color:#1a1207; border:none; padding:0 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">검색</button>
            </div>
            <input class="field-input" id="inputAddressDetail" type="text" placeholder="상세주소 (동/호수)">
        </div>
        <div class="field-group">
            <div class="field-label">담당자</div>
            <div class="assignee-list" id="assigneeList">
                <div style="font-size:12px; color:var(--text-muted);">불러오는 중...</div>
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <textarea class="field-input field-textarea" id="inputDesc" rows="3" placeholder="메모"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">삭제</button>
            <button class="btn-cancel" onclick="closeModal()">취소</button>
            <button class="btn-save" onclick="saveEvent()">저장</button>
        </div>
    </div>
</div>
@endsection

<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const DAYS_KO = ['일','월','화','수','목','금','토'];
const canEditCalendar = @json(Auth::user()->hasPermission('calendar.edit'));
const isGuestUser = @json(Auth::user()->isGuest());
const HOURS = Array.from({length:14}, (_,i) => i+9); // 9시~22시

let currentYear, currentMonth, currentWeekStart, currentDay;
let events = [], assignees = [], selectedAssignees = [];
let editingId = null, currentColor = 'gold', currentView = 'month';

// ── 초기화 ──────────────────────────────────────────────────────
function init() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth();
    currentWeekStart = getWeekStart(now);
    currentDay = new Date(now); currentDay.setHours(0,0,0,0);
    loadAssignees();
    renderView();
    loadEvents();
}

function getWeekStart(d) {
    const r = new Date(d); r.setDate(r.getDate()-r.getDay()); r.setHours(0,0,0,0); return r;
}
function fmt(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function todayStr() { return fmt(new Date()); }

// ── 뷰 전환 ─────────────────────────────────────────────────────
function switchView(view) {
    currentView = view;
    ['month','week','day'].forEach(v => {
        document.getElementById(`tab${v.charAt(0).toUpperCase()+v.slice(1)}`).classList.toggle('active', v===view);
    });
    document.getElementById('monthView').style.display    = view==='month' ? '' : 'none';
    document.getElementById('timelineView').style.display = view!=='month' ? '' : 'none';
    renderView();
    loadEvents();
}

function changePeriod(dir) {
    if (currentView==='month') {
        currentMonth += dir;
        if (currentMonth>11){currentMonth=0;currentYear++;}
        if (currentMonth<0) {currentMonth=11;currentYear--;}
    } else if (currentView==='week') {
        currentWeekStart = new Date(currentWeekStart);
        currentWeekStart.setDate(currentWeekStart.getDate()+dir*7);
    } else {
        currentDay = new Date(currentDay);
        currentDay.setDate(currentDay.getDate()+dir);
    }
    renderView(); loadEvents();
}

function goToday() {
    const now = new Date();
    currentYear=now.getFullYear(); currentMonth=now.getMonth();
    currentWeekStart=getWeekStart(now);
    currentDay=new Date(now); currentDay.setHours(0,0,0,0);
    renderView(); loadEvents();
}

function renderView() {
    if (currentView==='month') renderMonth();
    else renderTimeline();
}

// ── 이벤트 로드 ─────────────────────────────────────────────────
async function loadEvents() {
    let start, end;
    if (currentView==='month') {
        start=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-01`;
        const last=new Date(currentYear,currentMonth+1,0).getDate();
        end=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${last}`;
    } else if (currentView==='week') {
        start=fmt(currentWeekStart);
        const we=new Date(currentWeekStart); we.setDate(we.getDate()+6); end=fmt(we);
    } else {
        start=end=fmt(currentDay);
    }
    const res=await fetch(`/api/events?start=${start}&end=${end}`);
    events=await res.json();
    renderView();
}

async function loadAssignees() {
    const res=await fetch('/api/assignees');
    if(res.ok) assignees=await res.json();
}

// ── 월간 뷰 ─────────────────────────────────────────────────────
function renderMonth() {
    const months=['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'];
    document.getElementById('periodTitle').textContent=`${currentYear}년 ${months[currentMonth]}`;
    const grid=document.getElementById('daysGrid'); grid.innerHTML='';
    const firstDay=new Date(currentYear,currentMonth,1).getDay();
    const lastDate=new Date(currentYear,currentMonth+1,0).getDate();
    const prevLast=new Date(currentYear,currentMonth,0).getDate();
    const ts=todayStr();
    let cells=[];
    for(let i=firstDay-1;i>=0;i--) cells.push({date:prevLast-i,month:'prev',full:null});
    for(let d=1;d<=lastDate;d++){
        const full=`${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({date:d,month:'cur',full});
    }
    const rem=42-cells.length;
    for(let d=1;d<=rem;d++) cells.push({date:d,month:'next',full:null});
    cells.forEach((cell,idx)=>{
        const div=document.createElement('div');
        div.className='day-cell'+(cell.month!=='cur'?' other-month':'');
        if(cell.full===ts) div.classList.add('today');
        const dow=idx%7;
        const nc=dow===0?'sun':dow===6?'sat':'';
        div.innerHTML=`<div class="day-num ${nc}">${cell.date}</div>`;
        if(cell.full){
            const dayEvs=events.filter(ev=>ev.start_date<=cell.full&&(ev.end_date||ev.start_date)>=cell.full);
            dayEvs.slice(0,3).forEach(ev=>{
                const chip=document.createElement('div');
                chip.className=`event-chip color-${ev.color}`;
                chip.textContent=isGuestUser?(ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):''):((ev.client_name?ev.client_name+' ':'')+ev.title);
                chip.onclick=e=>{e.stopPropagation();openEditModal(ev);};
                div.appendChild(chip);
            });
            if(dayEvs.length>3){
                const more=document.createElement('div');
                more.className='more-events';
                more.textContent=`+${dayEvs.length-3}개 더`;
                div.appendChild(more);
            }
            div.addEventListener('click',()=>openNewModal(cell.full));
        }
        grid.appendChild(div);
    });
}

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
        events.filter(ev=>ev.is_all_day&&ev.start_date<=ds&&(ev.end_date||ev.start_date)>=ds).forEach(ev=>{
            const chip=document.createElement('div');
            chip.className=`event-chip color-${ev.color}`;
            chip.style.marginBottom='2px';
            chip.textContent=isGuestUser?(ev.location||'일정')+(ev.start_time?' '+ev.start_time.slice(0,5):''):((ev.client_name?ev.client_name+' ':'')+ev.title);
            chip.onclick=()=>openEditModal(ev);
            cell.appendChild(chip);
        });
        cell.addEventListener('click',e=>{if(e.target===cell)openNewModal(ds);});
        alldayRow.appendChild(cell);
    });
    grid.appendChild(alldayRow);

    // 시간 슬롯
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
                if(ev.is_all_day) return false;
                if(ev.start_date!==ds) return false;
                const h=ev.start_time?parseInt(ev.start_time.split(':')[0]):null;
                return h===hour;
            }).forEach(ev=>{
                const el=document.createElement('div');
                el.className=`tl-event color-${ev.color}`;
                const sm=ev.start_time?parseInt(ev.start_time.split(':')[1]):0;
                const eh=ev.end_time?parseInt(ev.end_time.split(':')[0]):hour+1;
                const em=ev.end_time?parseInt(ev.end_time.split(':')[1]):0;
                const dur=(eh+em/60)-(hour+sm/60);
                el.style.top=`${(sm/60)*48}px`;
                el.style.height=`${Math.max(dur*48,20)}px`;
                el.textContent=(ev.client_name?ev.client_name+' ':'')+ev.title;
                el.onclick=e=>{e.stopPropagation();openEditModal(ev);};
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

// ── 색상/담당자 ─────────────────────────────────────────────────
function setColor(el){
    document.querySelectorAll('.color-btn').forEach(b=>b.classList.remove('active'));
    el.classList.add('active'); currentColor=el.dataset.color;
}
function renderAssigneeList(){
    const c=document.getElementById('assigneeList');
    if(!assignees.length){c.innerHTML='<div style="font-size:12px;color:var(--text-muted);">등록된 담당자 없음</div>';return;}
    c.innerHTML='';
    assignees.forEach(a=>{
        const chip=document.createElement('div');
        chip.className='assignee-chip'+(selectedAssignees.includes(a.id)?' selected':'');
        chip.textContent=a.name; chip.dataset.id=a.id;
        chip.onclick=()=>{
            if(selectedAssignees.includes(a.id)){selectedAssignees=selectedAssignees.filter(id=>id!==a.id);chip.classList.remove('selected');}
            else{selectedAssignees.push(a.id);chip.classList.add('selected');}
        };
        c.appendChild(chip);
    });
}

// ── 모달 ────────────────────────────────────────────────────────
function openNewModal(dateStr,timeStr){
    editingId=null; currentColor='gold'; selectedAssignees=[];
    document.querySelectorAll('.color-btn').forEach(b=>b.classList.remove('active'));
    document.querySelector('.color-btn[data-color="gold"]').classList.add('active');
    document.getElementById('modalTitleText').textContent='새 일정';
    document.getElementById('inputTitle').value='';
    document.getElementById('inputStartDate').value=dateStr||'';
    document.getElementById('inputEndDate').value=dateStr||'';
    document.getElementById('inputStartTime').value=timeStr||'13:00';
    document.getElementById('inputEndTime').value=timeStr?`${String(parseInt(timeStr)+1).padStart(2,'0')}:00`:'14:00';
    document.getElementById('inputClientName').value='';
    document.getElementById('inputAddress').value='';
    document.getElementById('inputAddressDetail').value='';
    document.getElementById('inputDesc').value='';
    document.getElementById('btnDelete').style.display='none';
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(()=>document.getElementById('inputTitle').focus(),50);
}

function openEditModal(ev){
    if(isGuestUser) return;
    editingId=ev.id; currentColor=ev.color;
    selectedAssignees=ev.assignees?ev.assignees.map(a=>a.id):[];
    document.querySelectorAll('.color-btn').forEach(b=>b.classList.remove('active'));
    const cb=document.querySelector(`.color-btn[data-color="${ev.color}"]`);
    if(cb) cb.classList.add('active');
    document.getElementById('modalTitleText').textContent='일정 수정';
    document.getElementById('inputTitle').value=ev.title||'';
    document.getElementById('inputStartDate').value=ev.start_date?ev.start_date.substring(0,10):'';
    document.getElementById('inputEndDate').value=ev.end_date?ev.end_date.substring(0,10):'';
    document.getElementById('inputStartTime').value=ev.start_time||'13:00';
    document.getElementById('inputEndTime').value=ev.end_time||'14:00';
    document.getElementById('inputClientName').value=ev.client_name||'';
    document.getElementById('inputAddress').value=ev.address||'';
    document.getElementById('inputAddressDetail').value=ev.location||'';
    document.getElementById('inputDesc').value=ev.description||'';
    document.getElementById('btnDelete').style.display='block';
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(){
    document.getElementById('modalOverlay').classList.remove('open'); editingId=null;
}

async function saveEvent(){
    const data={
        title:      document.getElementById('inputTitle').value.trim()||'(제목 없음)',
        start_date: document.getElementById('inputStartDate').value,
        end_date:   document.getElementById('inputEndDate').value||document.getElementById('inputStartDate').value,
        start_time: document.getElementById('inputStartTime').value,
        end_time:   document.getElementById('inputEndTime').value,
        is_all_day: false,
        color:      currentColor,
        client_name:document.getElementById('inputClientName').value.trim(),
        address:    document.getElementById('inputAddress').value.trim(),
        location:   document.getElementById('inputAddressDetail').value.trim(),
        description:document.getElementById('inputDesc').value.trim(),
        assignees:  selectedAssignees,
    };
    if(!data.start_date){alert('시작일을 입력하세요.');return;}
    const url=editingId?`/api/events/${editingId}`:'/api/events';
    const method=editingId?'POST':'POST';
    const res=await fetch(url,{method,headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(data)});
    if(res.ok){closeModal();loadEvents();}
    else{const err=await res.json();alert('저장 실패: '+JSON.stringify(err));}
}

async function deleteEvent(){
    if(!editingId||!confirm('이 일정을 삭제할까요?')) return;
    const res=await fetch(`/api/events/${editingId}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}});
    if(res.ok){closeModal();loadEvents();}
}

function searchCalAddr() {
    new daum.Postcode({
        oncomplete: function(data) {
            document.getElementById('inputAddress').value = data.roadAddress || data.jibunAddress;
        }
    }).open();
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

init();
</script>
@endpush
