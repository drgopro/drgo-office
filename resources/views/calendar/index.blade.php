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
    .cal-header { padding:12px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); }
    .month-nav { display:flex; align-items:center; gap:12px; }
    .month-nav button { background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer; padding:4px 8px; }
    .month-nav button:hover { color:var(--text); }
    .month-title { font-size:16px; font-weight:700; min-width:140px; text-align:center; }
    .nav-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer; transition:all 0.15s; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); }
    .add-btn { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .legend { padding:8px 20px; display:flex; gap:12px; flex-wrap:wrap; border-bottom:1px solid var(--border); }
    .legend-item { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--text-muted); }
    .legend-dot { width:8px; height:8px; border-radius:50%; }
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
    .event-chip { font-size:11px; padding:2px 6px; border-radius:4px; margin-bottom:2px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .event-chip.color-gold { background:var(--gold); color:#1a1207; }
    .event-chip.color-teal { background:var(--teal); color:#1a1207; }
    .event-chip.color-blue { background:var(--blue); color:#1a1207; }
    .event-chip.color-red { background:var(--red); color:#fff; }
    .event-chip.color-green { background:var(--green); color:#1a1207; }
    .event-chip.color-purple { background:var(--purple); color:#fff; }
    .event-chip.color-holiday { background:var(--red); color:#fff; }
    .more-events { font-size:10px; color:var(--text-muted); padding:1px 4px; }
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; align-items:center; justify-content:center; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:480px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .modal-title { font-size:16px; font-weight:700; }
    .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
    .field-group { margin-bottom:14px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:5px; letter-spacing:0.05em; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .color-picker { display:flex; gap:8px; flex-wrap:wrap; }
    .color-btn { width:28px; height:28px; border-radius:50%; border:2px solid transparent; cursor:pointer; transition:transform 0.15s; }
    .color-btn:hover { transform:scale(1.15); }
    .color-btn.active { border-color:var(--text); transform:scale(1.15); }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-delete { background:none; border:1px solid var(--red); color:var(--red); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
</style>
@endpush

@section('content')
<div class="cal-header">
    <div style="display:flex; align-items:center; gap:12px;">
        <div class="month-nav">
            <button onclick="changeMonth(-1)">‹</button>
            <div class="month-title" id="monthTitle"></div>
            <button onclick="changeMonth(1)">›</button>
        </div>
        <button class="nav-btn" onclick="goToday()">오늘</button>
    </div>
    <button class="add-btn" onclick="openNewModal()">+ 일정</button>
</div>

<div class="legend">
    <div class="legend-item"><div class="legend-dot" style="background:var(--gold)"></div>방문의뢰</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--teal)"></div>원격/방송룸</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div>사내업무</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>휴가/개인</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div>촬영/스튜디오</div>
    <div class="legend-item"><div class="legend-dot" style="background:var(--purple)"></div>미팅/내방</div>
</div>

<div class="calendar-wrap">
    <div class="weekdays">
        <div class="weekday">일</div>
        <div class="weekday">월</div>
        <div class="weekday">화</div>
        <div class="weekday">수</div>
        <div class="weekday">목</div>
        <div class="weekday">금</div>
        <div class="weekday">토</div>
    </div>
    <div class="days-grid" id="daysGrid"></div>
</div>

<!-- 모달 -->
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
                <div class="color-btn active" style="background:var(--gold)" data-color="gold" onclick="setColor(this)" title="방문의뢰"></div>
                <div class="color-btn" style="background:var(--teal)" data-color="teal" onclick="setColor(this)" title="원격/방송룸"></div>
                <div class="color-btn" style="background:var(--blue)" data-color="blue" onclick="setColor(this)" title="사내업무"></div>
                <div class="color-btn" style="background:var(--red)" data-color="red" onclick="setColor(this)" title="휴가/개인"></div>
                <div class="color-btn" style="background:var(--green)" data-color="green" onclick="setColor(this)" title="촬영/스튜디오"></div>
                <div class="color-btn" style="background:var(--purple)" data-color="purple" onclick="setColor(this)" title="미팅/내방"></div>
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
            <input class="field-input" id="inputAddress" type="text" placeholder="방문 주소">
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <textarea class="field-input" id="inputDesc" rows="3" placeholder="메모" style="resize:vertical"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">삭제</button>
            <button class="btn-cancel" onclick="closeModal()">취소</button>
            <button class="btn-save" onclick="saveEvent()">저장</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let currentYear, currentMonth, events = [], editingId = null, currentColor = 'gold';

function init() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth();
    renderCalendar();
    loadEvents();
}

function changeMonth(dir) {
    currentMonth += dir;
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    if (currentMonth < 0)  { currentMonth = 11; currentYear--; }
    renderCalendar();
    loadEvents();
}

function goToday() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth();
    renderCalendar();
    loadEvents();
}

async function loadEvents() {
    const start = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-01`;
    const lastDay = new Date(currentYear, currentMonth+1, 0).getDate();
    const end = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${lastDay}`;
    const res = await fetch(`/api/events?start=${start}&end=${end}`);
    events = await res.json();
    renderCalendar();
}

function renderCalendar() {
    const months = ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'];
    document.getElementById('monthTitle').textContent = `${currentYear}년 ${months[currentMonth]}`;
    const grid = document.getElementById('daysGrid');
    grid.innerHTML = '';
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const lastDate = new Date(currentYear, currentMonth+1, 0).getDate();
    const prevLastDate = new Date(currentYear, currentMonth, 0).getDate();
    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
    let cells = [];
    for (let i = firstDay - 1; i >= 0; i--) cells.push({ date: prevLastDate - i, month: 'prev', full: null });
    for (let d = 1; d <= lastDate; d++) {
        const full = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({ date: d, month: 'cur', full });
    }
    const remaining = 42 - cells.length;
    for (let d = 1; d <= remaining; d++) cells.push({ date: d, month: 'next', full: null });

    cells.forEach((cell, idx) => {
        const div = document.createElement('div');
        div.className = 'day-cell' + (cell.month !== 'cur' ? ' other-month' : '');
        if (cell.full === todayStr) div.classList.add('today');
        const dow = idx % 7;
        const numClass = dow === 0 ? 'sun' : dow === 6 ? 'sat' : '';
        div.innerHTML = `<div class="day-num ${numClass}">${cell.date}</div>`;
        if (cell.full) {
            const dayEvents = events.filter(ev => ev.start_date <= cell.full && (ev.end_date || ev.start_date) >= cell.full);
            dayEvents.slice(0, 3).forEach(ev => {
                const chip = document.createElement('div');
                chip.className = `event-chip color-${ev.color}`;
                chip.textContent = ev.title;
                chip.onclick = (e) => { e.stopPropagation(); openEditModal(ev); };
                div.appendChild(chip);
            });
            if (dayEvents.length > 3) {
                const more = document.createElement('div');
                more.className = 'more-events';
                more.textContent = `+${dayEvents.length - 3}개 더`;
                div.appendChild(more);
            }
            div.addEventListener('click', () => openNewModal(cell.full));
        }
        grid.appendChild(div);
    });
}

function setColor(el) {
    document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    currentColor = el.dataset.color;
}

function openNewModal(dateStr) {
    editingId = null; currentColor = 'gold';
    document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.color-btn[data-color="gold"]').classList.add('active');
    document.getElementById('modalTitleText').textContent = '새 일정';
    document.getElementById('inputTitle').value = '';
    document.getElementById('inputStartDate').value = dateStr || '';
    document.getElementById('inputEndDate').value = dateStr || '';
    document.getElementById('inputStartTime').value = '13:00';
    document.getElementById('inputEndTime').value = '14:00';
    document.getElementById('inputClientName').value = '';
    document.getElementById('inputAddress').value = '';
    document.getElementById('inputDesc').value = '';
    document.getElementById('btnDelete').style.display = 'none';
    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('inputTitle').focus();
}

function openEditModal(ev) {
    editingId = ev.id; currentColor = ev.color;
    document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
    const cb = document.querySelector(`.color-btn[data-color="${ev.color}"]`);
    if (cb) cb.classList.add('active');
    document.getElementById('modalTitleText').textContent = '일정 수정';
    document.getElementById('inputTitle').value = ev.title || '';
    document.getElementById('inputStartDate').value = ev.start_date ? ev.start_date.substring(0,10) : '';
    document.getElementById('inputEndDate').value = ev.end_date ? ev.end_date.substring(0,10) : '';
    document.getElementById('inputStartTime').value = ev.start_time || '13:00';
    document.getElementById('inputEndTime').value = ev.end_time || '14:00';
    document.getElementById('inputClientName').value = ev.client_name || '';
    document.getElementById('inputAddress').value = ev.address || '';
    document.getElementById('inputDesc').value = ev.description || '';
    document.getElementById('btnDelete').style.display = 'block';
    document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    editingId = null;
}

async function saveEvent() {
    const data = {
        title: document.getElementById('inputTitle').value.trim() || '(제목 없음)',
        start_date: document.getElementById('inputStartDate').value,
        end_date: document.getElementById('inputEndDate').value || document.getElementById('inputStartDate').value,
        start_time: document.getElementById('inputStartTime').value,
        end_time: document.getElementById('inputEndTime').value,
        is_all_day: false,
        color: currentColor,
        client_name: document.getElementById('inputClientName').value.trim(),
        address: document.getElementById('inputAddress').value.trim(),
        description: document.getElementById('inputDesc').value.trim(),
    };
    if (!data.start_date) { alert('시작일을 입력하세요.'); return; }
    let res;
    if (editingId) {
        res = await fetch(`/api/events/${editingId}`, { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(data) });
    } else {
        res = await fetch('/api/events', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(data) });
    }
    if (res.ok) { closeModal(); loadEvents(); }
    else { const err = await res.json(); alert('저장 실패: ' + JSON.stringify(err)); }
}

async function deleteEvent() {
    if (!editingId || !confirm('이 일정을 삭제할까요?')) return;
    const res = await fetch(`/api/events/${editingId}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF} });
    if (res.ok) { closeModal(); loadEvents(); }
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
document.getElementById('modalOverlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

init();
</script>
@endpush
