{{-- 캘린더 디자인 v2 (가독성 개선) — html.cal-v2 일 때만 적용.
     되돌리기: ⋯ 메뉴 '이전 디자인으로 보기'(개인, localStorage calDesign=v1) / 전체 롤백은 index의 이 include 삭제 또는 git tag calendar-v1 --}}
<script>(function(){var v2=true;try{v2=localStorage.getItem('calDesign')!=='v1';}catch(e){}if(v2)document.documentElement.classList.add('cal-v2');})();</script>
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
    .cal-v2 .mday-title-overlay { font-size:calc(13px * var(--cal-fz,1)); gap:5px; padding:0 8px 0 10px; }
    .cal-v2 .event-chip .chip-title, .cal-v2 .mday-title-overlay .chip-title { font-weight:500; }
    .cal-v2 .chip-time { flex-shrink:0; font-size:calc(12px * var(--cal-fz,1)); font-weight:700; color:var(--text-muted); font-variant-numeric:tabular-nums; }
    .cal-v2 .ev-assignee-badge { font-size:calc(12px * var(--cal-fz,1)); font-weight:500; letter-spacing:0; padding:0 0 0 4px; background:none; color:var(--text-muted); }
    @foreach(\App\Models\CalendarCategory::map() as $__ck => $__cc)
    .cal-v2 .event-chip.single.color-{{ $__ck }} { background:color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 18%, var(--surface)); border-left-color:var(--chip-{{ $__ck }}-bg); }
    .cal-v2 .event-chip.single.is-tentative.color-{{ $__ck }}:not(.multi-day) { background:var(--surface); border:1.5px dashed var(--chip-{{ $__ck }}-bg); }
    {{-- 다일 미확정 — 셀마다 끊기는 점선 대신 사선 줄무늬로 바 전체를 구분 --}}
    .cal-v2 .event-chip.single.multi-day.is-tentative.color-{{ $__ck }} { background:repeating-linear-gradient(135deg, color-mix(in srgb, var(--chip-{{ $__ck }}-bg) 22%, var(--surface)) 0 6px, var(--surface) 6px 12px); }
    @endforeach

    /* 옵션 아이콘 — 이모지 대신 선 아이콘 (긴급만 빨강) */
    .cal-v2 .opt-ic { display:inline-flex; align-items:center; flex-shrink:0; margin-right:0; color:var(--text-muted); }
    .cal-v2 .opt-ic svg { width:calc(13px * var(--cal-fz,1)); height:calc(13px * var(--cal-fz,1)); display:block; }
    .cal-v2 .opt-ic.ic-urgent { color:#C03838; }
    .cal-v2 .opt-ic.opt-ic-arrow { color:var(--accent); }
    .cal-v2 .ev-done-ic { color:inherit; }

    /* 확정 상태 — 한 글자 대신 전체 단어 알약 (제안·희망·목표 테두리 / 확정 초록) */
    .cal-v2 .opt-chip.sched-pill { font-size:calc(11px * var(--cal-fz,1)); font-weight:700; line-height:16px; padding:0 6px; margin-left:4px; margin-right:0; border-radius:999px; background:transparent; border:1px solid color-mix(in srgb, var(--text) 40%, transparent); color:var(--text); }
    .cal-v2 .opt-chip.sched-pill.confirmed { background:#2f9e44; border-color:#2f9e44; color:#fff; }

    /* 완료 — 체크 아이콘 + 55% */
    .cal-v2 .event-chip.is-completed, .cal-v2 .mday-title-overlay.is-completed, .cal-v2 .mc-chip.is-completed,
    .cal-v2 .mc-bar.is-completed, .cal-v2 .mde-item.is-completed { opacity:0.55; }

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
        .cal-v2 .mc-chip .mc-time, .cal-v2 .mc-chip .opt-ic, .cal-v2 .mc-chip .opt-chip,
        .cal-v2 .mc-bar .opt-ic, .cal-v2 .mc-bar .opt-chip { display:none; }
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
