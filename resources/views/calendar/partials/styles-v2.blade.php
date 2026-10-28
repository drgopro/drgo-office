{{-- 캘린더 디자인 v2 (가독성 개선) — html.cal-v2 일 때만 적용.
     기본은 이전 디자인(v1). 불편하다는 평이 많아 v2는 ⋯ 메뉴 '새 디자인으로 보기'(개인, localStorage calDesign=v2)로 켠 사람에게만 적용.
     완전 제거는 index의 이 include 삭제 또는 git tag calendar-v1 --}}
<script>(function(){var v2=false;try{v2=localStorage.getItem('calDesign')==='v2';}catch(e){}if(v2)document.documentElement.classList.add('cal-v2');})();</script>
<style>
    /* ── 데스크탑 월간 — 13px/26px 칩, 연한 분류색 바탕 + 왼쪽 실선 ── */
    .cal-v2 .events-list { gap:3px; }
    .cal-v2 .day-num { font-size:calc(13.5px * var(--cal-fz,1)); font-weight:600; font-variant-numeric:tabular-nums; }
    .cal-v2 .holiday-label { font-size:calc(12px * var(--cal-fz,1)); letter-spacing:0; }
    .cal-v2 .week-row > .day-cell:nth-child(1):not(.other-month),
    .cal-v2 .week-row > .day-cell:nth-child(7):not(.other-month) { background:color-mix(in srgb, var(--text) 2.5%, var(--surface)); }
    .cal-v2 .event-chip { font-size:calc(13px * var(--cal-fz,1)); height:calc(26px * var(--cal-fz,1)); line-height:1; gap:5px; padding:0 8px 0 7px; border-radius:6px; }
    .cal-v2 .event-chip.multi-day.day-start { border-radius:6px 0 0 6px; }
    .cal-v2 .event-chip.multi-day.day-end { border-radius:0 6px 6px 0; }
    .cal-v2 .event-chip.multi-day.day-start.day-end { border-radius:6px; }
    .cal-v2 .lane-spacer { height:calc(26px * var(--cal-fz,1)); }
    /* 다일 제목은 연한 바탕 위라 카테고리 글자색(진한 바 기준, 흰색 계열) 대신 본문색 */
    .cal-v2 .mday-title-overlay { font-size:calc(13px * var(--cal-fz,1)); gap:5px; padding:0 8px 0 10px; color:var(--text) !important; }
    .cal-v2 .event-chip .chip-title, .cal-v2 .mday-title-overlay .chip-title { font-weight:500; }
    .cal-v2 .chip-time { flex-shrink:0; font-size:calc(12px * var(--cal-fz,1)); font-weight:700; color:var(--text-muted); font-variant-numeric:tabular-nums; }
    .cal-v2 .ev-assignee-badge { font-size:calc(12px * var(--cal-fz,1)); font-weight:500; letter-spacing:0; padding:0 0 0 4px; background:none; color:var(--text-muted); }
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .cal-v2 .event-chip.single.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 18%, var(--surface)); border-left-color:var(--chip-{{ $__ck }}-bg); }
    .cal-v2 .event-chip.single.is-tentative.color-{{ $__ck }}:not(.multi-day) { background:var(--surface); border:1.5px dashed var(--chip-{{ $__ck }}-bg); }
    {{-- 다일 미확정 — 셀마다 끊기는 점선 대신 사선 줄무늬로 바 전체를 구분 --}}
    .cal-v2 .event-chip.single.multi-day.is-tentative.color-{{ $__ck }} { background:repeating-linear-gradient(135deg, color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 14%, var(--surface)) 0 6px, var(--surface) 6px 12px); }
    @endforeach

    /* 옵션 아이콘 — 이모지 대신 선 아이콘 (긴급만 빨강) */
    /* .event-chip span 의 min-width:0 때문에 좁은 칸에서 아이콘이 0px로 눌리던 문제 — 아이콘은 줄이지 않고 제목이 먼저 줄어듦 */
    .cal-v2 .opt-ic { display:inline-flex; align-items:center; flex-shrink:0; min-width:auto; overflow:visible; margin-right:0; color:var(--text-muted); }
    .cal-v2 .event-chip .chip-title, .cal-v2 .mc-title { flex:1 1 0; min-width:0; }
    .cal-v2 .opt-chip.sched-pill { flex-shrink:0; }
    .cal-v2 .sp-short { display:none; }
    /* 칸이 좁아질수록 담당자 → 시간 순으로 숨기고 상태는 한 글자로 — 아이콘·제목은 끝까지 유지 */
    /* .mc-bar 는 셀 밖(.mc-week 직속)에 그려지므로 바 자체를 컨테이너로 */
    .cal-v2 .day-cell, .cal-v2 .mc-cell, .cal-v2 .mc-bar { container-type:inline-size; }
    @container (max-width: 150px) {
        .cal-v2 .event-chip:not(.chip-card) .chip-badges, .cal-v2 .mc-chip .mc-assignee, .cal-v2 .mc-bar .mc-assignee { display:none; }
        .cal-v2 .sched-pill .sp-full { display:none; }
        .cal-v2 .sched-pill .sp-short { display:inline; }
        .cal-v2 .opt-chip.sched-pill { padding:0 4px; }
    }
    @container (max-width: 110px) {
        .cal-v2 .event-chip .chip-time, .cal-v2 .mc-chip .mc-time { display:none; }
        .cal-v2 .event-chip:not(.chip-card) { gap:3px; padding:0 5px 0 5px; }
    }
    /* 카드 칩 2행: 상태 알약은 한 글자(확/제/희/목)로 — 셀 폭(약 125px)에서 아이콘·담당자 이름 공간 확보 (사용자 선택). 담당자는 아주 좁을 때만 숨김 */
    .cal-v2 .chip-card .sched-pill .sp-full { display:none; }
    .cal-v2 .chip-card .sched-pill .sp-short { display:inline; }
    @container (max-width: 95px) {
        .cal-v2 .event-chip.chip-card .chip-badges { display:none; }
    }
    .cal-v2 .opt-ic svg { width:calc(13px * var(--cal-fz,1)); height:calc(13px * var(--cal-fz,1)); display:block; }
    /* 픽토그램 종류별 색 — 작은 크기에서도 눈에 띄게 (선도 굵게). 라이트/다크 공통으로 채도 있는 중간 톤 */
    .cal-v2 .opt-ic svg { stroke-width:2.4; }
    .cal-v2 .opt-ic.ic-urgent { color:#C03838; }
    .cal-v2 .opt-ic.opt-ic-arrow { color:var(--accent); }
    .cal-v2 .opt-ic.ic-no_change { color:#64748b; }
    .cal-v2 .opt-ic.ic-car { color:#2f7fd1; }
    .cal-v2 .opt-ic.ic-brief { color:#b7791f; }
    .cal-v2 .opt-ic.ic-group { color:#8b5cf6; }
    .cal-v2 .opt-ic.ic-ladder { color:#0f8f8f; }
    .cal-v2 .opt-ic.ic-pet { color:#c2410c; }
    .cal-v2 .opt-ic.ic-external_operator { color:#4f46e5; }
    .cal-v2 .ev-done-ic { color:inherit; }

    /* 확정 상태 — 한 글자 대신 전체 단어 알약 (제안·희망·목표 테두리 / 확정 초록) */
    .cal-v2 .opt-chip.sched-pill { font-size:calc(11px * var(--cal-fz,1)); font-weight:700; line-height:16px; padding:0 6px; margin-left:4px; margin-right:0; border-radius:999px; background:transparent; border:1px solid color-mix(in srgb, var(--text) 40%, transparent); color:var(--text); }
    .cal-v2 .opt-chip.sched-pill.confirmed { background:#2f9e44; border-color:#2f9e44; color:#fff; }
    /* 전체(컴팩트) 뷰 칩은 16px 높이 — 알약도 그에 맞춰 축소 */
    .cal-v2 .mc-chip .opt-chip.sched-pill, .cal-v2 .mc-bar .opt-chip.sched-pill { font-size:9.5px; line-height:12px; padding:0 4px; margin-left:3px; }

    /* 완료 — 체크 아이콘 + 55% */
    .cal-v2 .event-chip.is-completed, .cal-v2 .mday-title-overlay.is-completed, .cal-v2 .mc-chip.is-completed,
    .cal-v2 .mc-bar.is-completed, .cal-v2 .mde-item.is-completed { opacity:0.55; }

    /* ── 데스크탑 월간 카드 칩 (디자인 'Calendar Dual View') — 1행: 시간·아이콘·제목 / 2행: 확정 상태·담당자, 상세는 클릭 팝오버 ── */
    @media (min-width: 769px) {
        .cal-v2 .cal-center-nav .month-label { font-size:20px; font-weight:700; letter-spacing:-0.01em; }
        .cal-v2 .cal-center-nav .nav-btn { border:1px solid var(--border); border-radius:10px; width:32px; height:32px; font-size:16px; }
        .cal-v2 .cal-center-nav .nav-btn:hover { border-color:var(--accent); }
        /* 범례 — 2행 우측 (확정 = 연한 바탕 + 왼쪽 실선 / 제안·희망·목표 = 점선) */
        .cal-v2 .cal-legend { order:12; display:flex; align-items:center; gap:12px; margin-left:12px; font-size:12px; color:var(--text-muted); white-space:nowrap; }
        .cal-v2 .cal-legend > span { display:inline-flex; align-items:center; gap:5px; }
        .cal-v2 .cal-legend i { display:inline-block; width:28px; height:14px; border-radius:4px; box-sizing:border-box; }
        .cal-v2 .cal-legend .lg-confirmed i { background:color-mix(in srgb, #70c870 30%, var(--surface)); border-left:3px solid #70c870; }
        .cal-v2 .cal-legend .lg-tentative i { border:1.5px dashed #70c870; border-left:3px solid #70c870; }
        .cal-v2 .cal-legend .lg-hint { opacity:0.75; }
        .cal-v2 .weekday { font-size:calc(12px * var(--cal-fz,1)); font-weight:600; letter-spacing:0; }
        .cal-v2 .day-cell { padding:6px 5px 8px; }
        .cal-v2 .week-row > .day-cell.other-month { background:var(--surface); opacity:0.45; }
        .cal-v2 .day-num-row { margin-bottom:3px; padding:0 2px; }
        .cal-v2 .events-list { gap:4px; }
        /* 카드 칩 — 고정 2행 높이 (renderMonth 행 수 계산과 동일: 42px × 배율 + 4px 간격) */
        .cal-v2 .event-chip.chip-card { height:calc(42px * var(--cal-fz,1)); flex-direction:row; align-items:center; gap:6px; padding:3px 6px 3px 7px; border-radius:7px; line-height:1.3; position:relative; }
        /* 연일(다일) 바도 같은 2행 높이 — 제목·아이콘·담당자를 1행에 뭉개지 않고 셀 칩과 행이 맞음. 제목 오버레이가 2행 카드 레이아웃을 그림 */
        .cal-v2 .event-chip.multi-day, .cal-v2 .lane-spacer { height:calc(42px * var(--cal-fz,1)); }
        .cal-v2 .mday-title-overlay.chip-card { flex-direction:row; align-items:center; gap:6px; padding:3px 6px 3px 9px; line-height:1.3; }
        .cal-v2 .mday-title-overlay.chip-card .chip-title { flex:1 1 0; }
        .cal-v2 .mday-title-overlay.chip-card .chip-badges { margin-left:0; padding-left:0; min-width:0; }
        /* 왼쪽 시간 칸 — 시작/종료를 세로로 (사용자 스케치) */
        .cal-v2 .chip-card .cc-time { display:flex; flex-direction:column; align-items:center; flex-shrink:0; gap:1px; font-size:calc(11.5px * var(--cal-fz,1)); font-weight:700; line-height:1; color:var(--text); font-variant-numeric:tabular-nums; }
        .cal-v2 .chip-card .cc-tilde { font-size:9px; line-height:0.6; color:var(--text-muted); }
        .cal-v2 .chip-card .cc-body { flex:1 1 0; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .cal-v2 .chip-card .chip-l1, .cal-v2 .chip-card .chip-l2 { display:flex; align-items:center; min-width:0; }
        .cal-v2 .chip-card .chip-l1 { gap:4px; }
        .cal-v2 .chip-card .chip-l2 { gap:4px; }
        .cal-v2 .chip-card .chip-ship { flex-shrink:0; margin-left:auto; }
        .cal-v2 .chip-card .opt-ic svg { width:calc(12px * var(--cal-fz,1)); height:calc(12px * var(--cal-fz,1)); }
        .cal-v2 .chip-card .chip-title { font-size:calc(12.5px * var(--cal-fz,1)); font-weight:600; letter-spacing:-0.01em; }
        .cal-v2 .chip-card .opt-chip.sched-pill { margin-left:0; font-size:calc(10px * var(--cal-fz,1)); padding:0 4px; line-height:15px; }
        .cal-v2 .chip-card .chip-badges { margin-left:0; padding-left:0; min-width:0; flex:1 1 0; } /* 기본 flex-shrink:0 이면 줄지 않고 잘려 말줄임이 안 보임 */
        .cal-v2 .chip-card .ev-assignee-badge { display:block; flex:1 1 0; min-width:0; padding:0; font-size:calc(11px * var(--cal-fz,1)); font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cal-v2 .chip-card .chip-cat { font-size:calc(11px * var(--cal-fz,1)); color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cal-v2 .event-chip.chip-card:hover { filter:brightness(0.96); transform:none; }
        .cal-v2 .event-chip.chip-card.is-popped, .cal-v2 .event-chip.multi-day.is-popped { box-shadow:0 0 0 2px var(--accent); }
        /* 완료 — 흐리게 하지 않고 글씨 위에 초록 체크를 덮음 (사용자 요청) */
        .cal-v2 .event-chip.chip-card.is-completed, .cal-v2 .mday-title-overlay.chip-card.is-completed { opacity:0.9; }
        /* 체크는 1행 제목 오른쪽 끝에 별도 아이콘 (글씨 위에 덮으면 제목 중간에 걸려 불편) */
        .cal-v2 .chip-card .ev-done-ic { color:#2f9e44; flex-shrink:0; }
        .cal-v2 .chip-card .ev-done-ic svg { stroke-width:3; }
        .cal-v2 .more-badge { font-size:calc(12px * var(--cal-fz,1)); font-weight:700; padding:2px 3px 0; }
        .cal-v2 .more-badge:hover { background:none; text-decoration:underline; }
        /* 일정 클릭 요약 팝오버 — 왼쪽 카테고리색 바, 수정 / 상세 보기 */
        .cal-v2 .ev-pop { position:fixed; z-index:520; width:300px; background:var(--surface); border:1px solid var(--border); border-left:4px solid var(--ev-pop-c, var(--accent)); border-radius:14px; box-shadow:0 1px 2px rgba(16,24,40,.04), 0 12px 32px rgba(16,24,40,.16); padding:14px 14px 12px; display:flex; flex-direction:column; gap:8px; color:var(--text); }
        .cal-v2 .ev-pop .ep-head { display:flex; align-items:center; gap:6px; min-width:0; }
        .cal-v2 .ev-pop .ep-head .opt-ic svg { width:15px; height:15px; }
        .cal-v2 .ev-pop .ep-title { font-size:15px; font-weight:700; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cal-v2 .ev-pop .ep-head .opt-chip.sched-pill { margin:0; font-size:11px; line-height:18px; padding:0 7px; }
        .cal-v2 .ev-pop .ep-sub { font-size:12.5px; color:var(--text-muted); }
        .cal-v2 .ev-pop .ep-urgent { margin-left:6px; color:#C03838; font-weight:700; }
        .cal-v2 .ev-pop .ep-row { display:flex; align-items:center; gap:6px; font-size:12.5px; }
        .cal-v2 .ev-pop .ep-k { color:var(--text-muted); opacity:0.8; }
        .cal-v2 .ev-pop .ep-v { font-weight:600; }
        .cal-v2 .ev-pop .ep-addr { font-size:12.5px; color:var(--text-muted); line-height:1.45; }
        .cal-v2 .ev-pop .agenda-move { font-size:12.5px; color:var(--text-muted); display:flex; flex-direction:column; gap:2px; }
        .cal-v2 .ev-pop .ep-actions { display:flex; gap:6px; margin-top:4px; }
        .cal-v2 .ev-pop .ep-actions button { flex:1; height:30px; border-radius:8px; font-size:12.5px; cursor:pointer; }
        .cal-v2 .ev-pop .ep-edit { border:1px solid var(--border); background:var(--surface); color:var(--text); }
        .cal-v2 .ev-pop .ep-edit:hover { background:var(--surface2); }
        .cal-v2 .ev-pop .ep-detail { border:none; background:var(--accent); color:var(--accent-text); font-weight:600; }
        .cal-v2 .ev-pop .ep-detail:hover { filter:brightness(1.06); }
    }

    /* ── 모바일 월간 — 진한 분류색 칩 + 흰 글자, 하루 최대 2행 + +N, 세로선 제거 ── */
    @media (max-width: 768px) {
        .cal-v2 #tabMonthC { display:none; } /* 월 탭이 칩형 월간을 대신함 */
        .cal-v2 .mc-weekdays { background:var(--surface); }
        .cal-v2 .mc-weekdays span { font-size:13px; padding:6px 0; }
        .cal-v2 .mc-cell { border-right:none; padding:2px 1px 0; gap:2px; }
        .cal-v2 .mc-cell.today { background:none; }
        .cal-v2 .mc-cell.other-month { opacity:0.4; }
        .cal-v2 .mc-daynum-row { height:26px; justify-content:center; }
        .cal-v2 .mc-daynum { font-size:14px; font-weight:600; width:26px; height:26px; font-variant-numeric:tabular-nums; }
        .cal-v2 .mc-cell.selected .mc-daynum { background:none; color:var(--accent); box-shadow:inset 0 0 0 2px var(--accent); }
        .cal-v2 .mc-cell.today .mc-daynum, .cal-v2 .mc-cell.today.selected .mc-daynum { background:var(--accent); color:var(--accent-text); box-shadow:none; }
        .cal-v2 .mc-slot { height:20px; }
        .cal-v2 .mc-chip, .cal-v2 .mc-chip.in-lane, .cal-v2 .mc-bar { height:20px; line-height:20px; font-size:12.5px; font-weight:700; letter-spacing:-0.04em; padding:0 3px; border-left:none; border-radius:5px; color:#fff; }
        .cal-v2 .mc-bar { padding:0 4px; }
        .cal-v2 .mc-title { text-overflow:clip; }
        .cal-v2 .mc-chip .mc-time, .cal-v2 .mc-chip .opt-chip, .cal-v2 .mc-bar .opt-chip { display:none; }
        /* 모바일 칩도 특징 아이콘은 유지 — 흰 선 아이콘을 제목 앞에 */
        .cal-v2 .mc-chip, .cal-v2 .mc-bar > span { gap:1px; }
        .cal-v2 .mc-chip .opt-ic, .cal-v2 .mc-bar .opt-ic { color:inherit; }
        .cal-v2 .mc-chip .opt-ic svg, .cal-v2 .mc-bar .opt-ic svg { width:11px; height:11px; stroke-width:2.4; }
        .cal-v2 .mc-chip .opt-ic.ic-urgent, .cal-v2 .mc-bar .opt-ic.ic-urgent { color:#FFD0D0; }
        .cal-v2 .mc-chip.is-past, .cal-v2 .mc-bar.is-past { opacity:0.45; }
        .cal-v2 .mc-more { font-size:12px; font-weight:800; line-height:16px; text-align:center; padding:0; }
        @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
        .cal-v2 .mc-chip.color-{{ $__ck }}, .cal-v2 .mc-bar.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 62%, #000); }
        .cal-v2 .mc-chip.is-tentative.color-{{ $__ck }}, .cal-v2 .mc-bar.is-tentative.color-{{ $__ck }} { background:var(--bg); box-shadow:inset 0 0 0 1.5px var(--chip-{{ $__ck }}-bg); color:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 60%, var(--text)); }
        @endforeach

        /* 하단 시트 하루 목록 — 글자 확대 */
        .cal-v2 .mobile-day-events .mde-header { font-size:15px; }
        .cal-v2 .mobile-day-events .mde-time { width:52px; font-size:15px; font-variant-numeric:tabular-nums; }
        .cal-v2 .mobile-day-events .mde-title { font-size:16px; }
        .cal-v2 .mobile-day-events .mde-meta { font-size:13.5px; margin-top:3px; }
        .cal-v2 .mobile-day-events .mde-assignee { font-size:13px; }
        .cal-v2 .mobile-day-events .mde-bar.is-tentative { background:none !important; border-left:3px dashed var(--mde-c); width:0; }
    }
</style>
