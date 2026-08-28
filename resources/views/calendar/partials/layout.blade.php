<div class="cal-header">
    <div class="cal-header-left">
        {{-- 모바일: ☰=필터 패널(사이드 리모컨), 연.월▾=년/월 피커, ‹›=기간 이동 --}}
        <button class="cal-hamburger" id="calHamBtn" onclick="csToggleMobile()" title="필터/미니 달력">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <button class="nav-btn cal-mini-nav" onclick="changePeriod(-1)" title="이전">‹</button>
        <span class="cal-mini-period" id="periodTitleMini" onclick="toggleCalPicker(event)"></span>
        <button class="nav-btn cal-mini-nav" onclick="changePeriod(1)" title="다음">›</button>
        <div class="cal-hl-items" id="calHlItems">
            <span class="cal-center-nav">
                <button class="nav-btn cal-today-btn" onclick="goToday()">오늘</button>
                <button class="nav-btn" onclick="changePeriod(-1)" title="이전">‹</button>
                <div class="month-label cal-title-xl" id="periodTitle"></div>
                <button class="nav-btn" onclick="changePeriod(1)" title="다음">›</button>
            </span>
            <button class="nav-btn" id="calRefreshBtn" onclick="refreshCalendar()" title="새로고침 (현재 보기 유지)">
                <svg id="calRefreshIco" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
            </button>
        </div>
        {{-- 년/월 피커 드롭다운 (모바일) --}}
        <div id="calMonthPicker" class="cal-mpicker" style="display:none;">
            <div class="cal-mpicker-year">
                <button type="button" onclick="mpYear(-1)">‹</button>
                <span id="mpYearLabel"></span>
                <button type="button" onclick="mpYear(1)">›</button>
            </div>
            <div class="cal-mpicker-grid" id="mpGrid"></div>
            <div class="cal-mpicker-foot">
                <button type="button" onclick="goToday();closeCalPicker()">오늘</button>
                <button type="button" onclick="refreshCalendar();closeCalPicker()">새로고침</button>
            </div>
        </div>
        @if(!Auth::user()->isGuest())
        {{-- 일정 검색 — 헤더 좌측 --}}
        <button class="nav-btn" id="calSearchBtn" onclick="toggleCalSearch()" title="일정 검색"><x-icon name="search" :size="14"/></button>
        <div class="cal-search-wrap" id="calSearchWrap" style="display:none;">
            <input class="cal-search-input" id="calSearchInput" placeholder="일정 검색 (Enter)" autocomplete="off"
                oninput="document.getElementById('calSearchClear').classList.toggle('show', !!this.value)"
                onkeydown="if(event.key==='Enter'&&!event.isComposing){event.preventDefault();openSearchListView();}">
            <button type="button" class="cal-search-clear" id="calSearchClear" title="검색어 지우기"
                onclick="const i=document.getElementById('calSearchInput');i.value='';this.classList.remove('show');i.focus();">✕</button>
        </div>
        @endif
    </div>
    <div class="cal-header-right" style="display:flex;align-items:center;gap:8px;">
        <div class="view-toggle-group">
            <button class="view-toggle-btn"        id="tabMonthC" onclick="switchView('monthc')" title="한 달 전체를 칩으로 한눈에">전체</button>
            <button class="view-toggle-btn active" id="tabMonth" onclick="switchView('month')">월</button>
            <button class="view-toggle-btn"        id="tabWeek"  onclick="switchView('week')">주</button>
            <button class="view-toggle-btn"        id="tabDay"   onclick="switchView('day')">일</button>
            <button class="view-toggle-btn"        id="tabList"  onclick="switchView('list')">목록</button>
        </div>
        {{-- 월간 뷰 표시 주 수 (다중 주, 2~6주 화살표) --}}
        <div id="monthWeeksCtl" class="mw-stepper" title="월간 뷰에 표시할 주 수 (2주~월 전체)">
            <button type="button" onclick="mwStep(-1)">‹</button>
            <span id="mwLabel">월 전체</span>
            <button type="button" onclick="mwStep(1)">›</button>
        </div>
        @if(Auth::user()->hasPermission('calendar.edit'))
            <button class="add-btn" onclick="openNewModal()">+ 일정 추가</button>
        @endif
        <div style="position:relative;" id="calMoreWrap">
            <button class="nav-btn" onclick="toggleCalMenu()" title="더보기" style="font-size:14px;">⋯</button>
            <div class="cal-menu" id="calMenu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:4px;z-index:20;min-width:180px;box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                {{-- 캘린더 이력 — 사용 빈도가 낮아 비활성화 (라우트는 유지, 필요 시 버튼만 복원) --}}
                @if(Auth::user()->hasPermission('calendar.edit'))
                <button onclick="toggleCalMenu();openTrashModal()" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="trash" :size="13"/> 휴지통</button>
                @endif
                <div style="height:1px;background:var(--border);margin:4px 0;"></div>
                <div class="cal-fontsize" title="글자 크기 조절 (월간 뷰)" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:8px 14px;background:none;">
                    <button class="cal-fz-btn" onclick="calFont(-1)" aria-label="글자 작게">A−</button>
                    <span id="calFontLabel" style="font-size:11px;color:var(--text-muted);min-width:34px;text-align:center;">100%</span>
                    <button class="cal-fz-btn" onclick="calFont(1)" aria-label="글자 크게" style="font-size:15px;">A+</button>
                </div>
                @if(Auth::user()->isAdmin())
                <div style="height:1px;background:var(--border);margin:4px 0;"></div>
                <button onclick="location.href='/api/events/export/json'" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="box" :size="13"/> JSON 백업</button>
                <button onclick="location.href='/api/events/export/ical'" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="calendar" :size="13"/> iCal 내보내기</button>
                <button onclick="document.getElementById('jsonImportInput').click()" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="upload" :size="13"/> JSON 가져오기</button>
                <button onclick="document.getElementById('icalImportInput').click()" style="display:block;width:100%;text-align:left;background:none;border:none;color:var(--text);padding:10px 14px;font-size:12px;cursor:pointer;border-radius:6px;white-space:nowrap;"><x-icon name="upload" :size="13"/> iCal 가져오기</button>
                @endif
            </div>
        </div>
        @if(Auth::user()->isAdmin())
        <input type="file" id="jsonImportInput" accept=".json" style="display:none" onchange="importFile('json',this)">
        <input type="file" id="icalImportInput" accept=".ics,.ical" style="display:none" onchange="importFile('ical',this)">
        @endif
    </div>
</div>

<style>
    /* ── 모바일 헤더 정리 — ☰=필터, 연.월▾=년/월 피커, + 는 플로팅 버튼 (데스크탑은 기존 그대로) ── */
    .cal-hamburger, .cal-mini-period, .cal-mini-nav, #calAddFab { display:none; }
    .cal-hl-items { display:contents; }
    .cal-mpicker { position:absolute; left:0; top:calc(100% + 6px); z-index:40; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:12px; box-shadow:0 10px 28px rgba(0,0,0,0.45); width:250px; }
    .cal-mpicker-year { display:flex; align-items:center; justify-content:center; gap:14px; margin-bottom:10px; }
    .cal-mpicker-year span { font-size:14.5px; font-weight:800; }
    .cal-mpicker-year button { width:28px; height:28px; border-radius:8px; border:1px solid var(--border); background:none; color:var(--text-muted); font-size:14px; cursor:pointer; }
    .cal-mpicker-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
    .cal-mpicker-grid button { padding:8px 0; border-radius:8px; border:1px solid var(--border); background:none; color:var(--text); font-size:12.5px; cursor:pointer; }
    .cal-mpicker-grid button.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); font-weight:700; }
    .cal-mpicker-foot { display:flex; gap:6px; margin-top:10px; }
    .cal-mpicker-foot button { flex:1; padding:7px 0; border-radius:8px; border:1px solid var(--border); background:none; color:var(--text); font-size:12px; cursor:pointer; }
    @media (min-width: 769px) {
        /* 데스크탑 2행 헤더 — 1행: 좌측 도구 + 중앙 [오늘] ‹ 연.월 › + 우측 ⋯ / 2행: 뷰 버튼 중앙 */
        .cal-header .cal-center-nav { position:absolute; left:50%; top:14px; transform:translateX(-50%); display:flex; align-items:center; gap:4px; }
        .cal-center-nav .nav-btn { border:none; background:none; box-shadow:none; width:28px; height:28px; font-size:19px; color:var(--text-muted); }
        .cal-center-nav .nav-btn:hover { color:var(--text); background:var(--surface2); }
        .cal-center-nav .month-label { margin:0; min-width:0; white-space:nowrap; }
        .cal-center-nav .cal-today-btn { width:auto; padding:0 12px; font-size:12px; font-weight:600; border:1px solid var(--border); border-radius:8px; margin-right:6px; color:var(--text); }
        /* 우측 그룹을 헤더 직속으로 풀어 2행 구성: 1행 도구/타이틀/더보기, 2행 뷰 버튼+주 수 */
        .cal-header-right { display:contents !important; }
        #calMoreWrap { margin-left:auto; }
        .cal-header::before { content:''; order:9; flex-basis:100%; height:0; } /* 행 구분선(줄바꿈) */
        .view-toggle-group { order:10; margin-left:auto; }
        #monthWeeksCtl { order:11; margin-right:auto; margin-left:8px; } /* 주 수 스텝퍼도 2행으로 — 1행 겹침 방지 */
        .cal-search-input { width:150px; }
        /* + 일정 추가는 모바일처럼 우하단 플로팅 버튼으로 */
        .cal-header .add-btn { display:none; }
        #calAddFab { display:flex; position:fixed; right:24px; bottom:24px; z-index:58; width:52px; height:52px; border-radius:50%; border:none; background:var(--accent); color:var(--accent-text); font-size:26px; align-items:center; justify-content:center; box-shadow:0 6px 18px rgba(0,0,0,0.35); cursor:pointer; }
        #calAddFab:hover { filter:brightness(1.1); }
    }
    @media (max-width: 768px) {
        {{-- 햄버거: 테두리 없이 진한 선 아이콘만 --}}
        .cal-hamburger { display:inline-flex; align-items:center; justify-content:center; position:absolute; left:0; top:50%; transform:translateY(-50%); border:none; background:none; box-shadow:none; color:var(--text); padding:4px; cursor:pointer; }
        .cal-mini-period { display:inline-flex; align-items:center; gap:3px; font-size:14px; font-weight:800; cursor:pointer; padding:0 2px; letter-spacing:-0.02em; white-space:nowrap; }
        .cal-mini-period::after { content:'▾'; font-size:10px; color:var(--text-muted); }
        .cal-header { justify-content:space-between; }
        {{-- 연.월 중앙 정렬 — ☰는 좌측 고정, 타이틀 좌우 ‹ ›로 날/주/월 이동 --}}
        .cal-header-left { width:100%; justify-content:center; position:relative; flex:1 1 100%; gap:4px; }
        .cal-mini-nav { display:inline-flex; align-items:center; justify-content:center; border:none; background:none; box-shadow:none; width:32px; height:32px; padding:0; font-size:20px; color:var(--text-muted); cursor:pointer; }
        .cal-mini-nav:active { color:var(--text); }
        .cal-header-right { width:100%; border-top:none; padding-top:0; justify-content:center; }
        .cal-mpicker { left:50%; transform:translateX(-50%); }
        .cal-hl-items { display:none; } /* 연.월/이동/오늘/새로고침 데스크탑 세트는 모바일에서 숨김 (피커로 대체) */
        /* 모바일: 검색은 타이틀 행 우측 아이콘 (☰와 대칭), 입력창은 우측에서 펼침 */
        .cal-header-left #calSearchBtn { position:absolute; right:0; top:50%; transform:translateY(-50%); }
        .cal-header-left .cal-search-wrap { position:absolute; right:36px; top:50%; transform:translateY(-50%); z-index:6; }
        .cal-header-left .cal-search-input { width:52vw; max-width:260px; }
        /* 검색창이 열려 있는 동안 연.월 타이틀·이동 화살표 숨김 — 겹침 방지 */
        .cal-header.searching .cal-mini-period, .cal-header.searching .cal-mini-nav { visibility:hidden; }
        /* 주 수 스텝퍼는 모바일에서 숨김 (월간 dots 뷰에선 의미 없음) */
        #monthWeeksCtl { display:none !important; }
        .cal-header .add-btn { display:none; } /* + 일정 추가는 플로팅 버튼으로 */
        #calSideFab { display:none !important; } /* 하단 좌측 필터 버튼 → 상단 ☰로 이동 */
        #calAddFab { display:flex; position:fixed; right:16px; bottom:calc(76px + env(safe-area-inset-bottom)); z-index:58; width:52px; height:52px; border-radius:50%; border:none; background:var(--accent); color:var(--accent-text); font-size:26px; font-weight:400; align-items:center; justify-content:center; box-shadow:0 6px 18px rgba(0,0,0,0.35); cursor:pointer; }
    }
</style>
<script>(function(){var s=parseFloat(localStorage.getItem('calFontScale')||'1')||1;document.documentElement.style.setProperty('--cal-fz',s);})();</script>

<div class="legend" id="filterBar">
    @foreach(\App\Models\CalendarCategory::map() as $__k => $__c)
    <button class="filter-btn active f-{{ $__k }}" data-filter="{{ $__k }}" style="--fbtn:var(--chip-{{ $__k }}-bg)" onclick="toggleFilter(this)"><span class="filter-dot" style="background:var(--chip-{{ $__k }}-bg)"></span>{{ $__c['label'] }}</button>
    @endforeach
    <select class="assignee-filter" id="assigneeFilter" onchange="onAssigneeFilterChange()" title="담당자 추가 (여러 명 선택 가능)">
        <option value="">담당자 추가…</option>
    </select>
    <div id="assigneeFilterChips" class="assignee-filter-chips"></div>
</div>

@if(Auth::user()->hasPermission('calendar.edit'))
{{-- 모바일 플로팅 일정 추가 버튼 (화면을 따라다님) --}}
<button type="button" id="calAddFab" onclick="openNewModal()" title="일정 추가">+</button>
@endif
<button type="button" id="calSideFab" onclick="csToggleMobile()" title="필터/미니 달력">
    <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
</button>
<div id="calSideBackdrop" onclick="csToggleMobile(false)"></div>
<div id="calBody">
<aside id="calSide">
    <div id="calSideSticky">
    <div id="calSideBody">
    <div class="cs-mini-head">
        <span id="csMiniLabel"></span>
        <span class="cs-mini-nav">
            <button type="button" onclick="csMiniMove(-1)" title="이전 달">‹</button>
            <button type="button" onclick="csMiniMove(1)" title="다음 달">›</button>
            <button type="button" id="csCollapseBtn" onclick="csToggleSide()" title="필터 접기">«</button>
        </span>
    </div>
    <div class="cs-mini-grid cs-mini-dow"><span style="color:#e06c6c;">일</span><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span style="color:#5b8def;">토</span></div>
    <div class="cs-mini-grid" id="csMiniGrid"></div>
    <div class="cs-divider"></div>
    <div class="cs-sec-title">표시 중 · <span id="csOnCount">0</span></div>
    <div id="csCatsOn"></div>
    <div class="cs-divider"></div>
    <div class="cs-sec-title">담당자</div>
    <div id="csAssignees"></div>
    {{-- 삭제/변경 이력 버튼 제거 — 문장 로그 모달은 흔적 칩 클릭으로만 접근 --}}
    {{-- 모바일: 점 세개(⋯) 메뉴 항목이 여기로 이동 (init에서 DOM 이동) --}}
    <div id="csTools" style="display:none;">
        <div class="cs-divider"></div>
        <div class="cs-sec-title">도구</div>
        <div id="csToolsBody"></div>
    </div>
    </div>{{-- /calSideBody --}}
    <div id="csRail"></div>
    </div>{{-- /calSideSticky --}}
</aside>
<div id="calMain">

<!-- 월간 뷰 -->
<div id="monthView">
    <div class="calendar-wrap">
        <div class="weekdays">
            <div class="weekday">일</div><div class="weekday">월</div><div class="weekday">화</div>
            <div class="weekday">수</div><div class="weekday">목</div><div class="weekday">금</div><div class="weekday">토</div>
        </div>
        <div class="days-grid" id="daysGrid"></div>
        <div class="mobile-day-events" id="mobileDayEvents"></div>
    </div>
</div>

{{-- 컴팩트 월간 뷰 (네이버식 고밀도 — 모든 일정을 작은 칩으로 표시) --}}
<div id="monthCompactView" style="display:none;"></div>
{{-- 데스크탑 컴팩트: 그리드-리스트 분할 크기 조절 핸들 + 선택일 일정 리스트 (mde 스타일 재사용) --}}
<div id="mcListResizer" title="드래그해서 리스트 높이 조절"></div>
<div id="mcDeskList" class="mobile-day-events"></div>

{{-- 컴팩트 뷰 모바일 하단 시트 — 바를 올리면 선택일 일정 리스트 (네이버 모바일 방식) --}}
<div id="mcSheetBackdrop" class="mc-backdrop" onclick="mcSheetSet(false)"></div>
<div id="mcSheet" class="mc-sheet">
    <div class="mc-sheet-handle" id="mcSheetHandle">
        <span class="mc-sheet-grip"></span>
        <span id="mcSheetLabel"></span>
    </div>
    <div class="mc-sheet-body">
        {{-- mobile-day-events 클래스: 기존 일별 리스트(mde-*) 스타일 스코프 재사용 --}}
        <div class="mobile-day-events" id="mcSheetBody"></div>
    </div>
</div>

{{-- 하루 일정 전체 보기 팝오버 (데스크탑 '+N건 더보기') --}}
<div id="dayPopoverOverlay" class="day-popover-overlay" onclick="closeDayPopover()"></div>
<div id="dayPopover" class="day-popover" style="display:none;">
    <div class="dp-header">
        <span id="dpTitle"></span>
        <button type="button" class="dp-close" onclick="closeDayPopover()">✕</button>
    </div>
    <div id="dpList" class="dp-list"></div>
</div>

<!-- 주간/일간 뷰 -->
<div id="timelineView" style="display:none;">
    <div class="timeline-wrap">
        <div class="timeline-grid" id="timelineGrid"></div>
    </div>
</div>

<!-- 목록(아젠다) 뷰 -->
<div id="listView" style="display:none;">
    <div class="agenda-strip" id="agendaStrip"></div>
    <div class="agenda-wrap" id="agendaWrap"></div>
</div>

</div>{{-- /calMain --}}
</div>{{-- /calBody --}}

<!-- 일정 모달 -->
