{{-- 새 화면 디자인(UX v2) — 의뢰자 조회 · 프로젝트 상세 전용.
     모든 규칙은 html.ux-v2 아래에서만 적용된다. 롤백: config/ux.php(UX_V2=false) 또는 이 파일 include 제거.
     요약 줄(.ux2-glance)은 v2가 꺼져 있으면 숨겨진다. --}}
<style id="ux-v2-styles">
.ux2-glance, .ux2-only { display:none; }
html.ux-v2 {
    --ux-desk:#eef0f3; --ux-paper:#ffffff; --ux-ink:#171a20; --ux-ink-2:#3b404a; --ux-sub:#656b78; --ux-line:#e1e4e9;
    --ux-star:#d6283f; --ux-memo:#fff7c9; --ux-memo-edge:#efe2a0; --ux-green:#1f7a3d;
}
html.ux-v2 .ux2-only { display:revert; }

/* ── 한눈에 보는 줄 (공통) ── */
html.ux-v2 .ux2-glance { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:10px; margin:0 0 18px; }
html.ux-v2 .ux2-glance.three { grid-template-columns:repeat(3, minmax(0,1fr)); }
html.ux-v2 .ux2-g { background:var(--ux-paper); border:1px solid var(--ux-line); border-radius:14px; padding:13px 16px; min-width:0; }
html.ux-v2 .ux2-g.flow { grid-column:1 / -1; }
html.ux-v2 .ux2-g.click { cursor:pointer; }
html.ux-v2 .ux2-g.click:hover { border-color:#c3c8d1; }
html.ux-v2 .ux2-g h3 { margin:0 0 7px; font-size:12px; font-weight:600; color:var(--ux-sub); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; gap:6px; align-items:baseline; }
html.ux-v2 .ux2-g h3 b { color:var(--ux-ink); font-weight:700; overflow:hidden; text-overflow:ellipsis; }
html.ux-v2 .ux2-g h3 .ok { color:var(--ux-green); font-weight:700; flex-shrink:0; }
html.ux-v2 .ux2-g h3 .more { margin-left:auto; color:var(--ux-sub); font-weight:500; flex-shrink:0; }
html.ux-v2 .ux2-bars { display:grid; grid-auto-flow:column; grid-auto-columns:minmax(0,1fr); gap:4px; }
html.ux-v2 .ux2-bars span { height:6px; border-radius:3px; background:#dde0e5; }
html.ux-v2 .ux2-bars .d { background:var(--ux-ink-2); }
html.ux-v2 .ux2-bars .n { background:var(--ux-star); }
html.ux-v2 .ux2-bars.cancel span { background:#dde0e5; }
html.ux-v2 .ux2-steps { display:grid; grid-auto-flow:column; grid-auto-columns:minmax(0,1fr); gap:4px; margin-top:6px; }
html.ux-v2 .ux2-steps span { font-size:12px; color:var(--ux-sub); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
html.ux-v2 .ux2-steps .d { color:var(--ux-ink-2); }
html.ux-v2 .ux2-steps .n { color:var(--ux-ink); font-weight:800; }
html.ux-v2 .ux2-steps small { display:block; font-size:11px; color:var(--ux-sub); font-weight:500; }
html.ux-v2 .ux2-big { font-size:21px; font-weight:800; letter-spacing:-.04em; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-variant-numeric:tabular-nums; color:var(--ux-ink); }
html.ux-v2 .ux2-big small { font-size:13px; font-weight:600; color:var(--ux-ink-2); margin-left:6px; letter-spacing:-.01em; }
html.ux-v2 .ux2-mini { font-size:13px; color:var(--ux-ink-2); margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
html.ux-v2 .ux2-mini b { color:var(--ux-star); }
html.ux-v2 .ux2-pswitch { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin:0 0 10px; padding-bottom:10px; border-bottom:1px solid var(--ux-line); }
html.ux-v2 .ux2-pswitch span { font-size:12px; font-weight:700; color:var(--ux-star); margin-right:2px; white-space:nowrap; }
html.ux-v2 .ux2-pswitch button { height:28px; max-width:220px; padding:0 11px; border-radius:999px; border:1px solid var(--ux-line); background:var(--ux-paper); font-size:12px; color:var(--ux-ink-2); cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
html.ux-v2 .ux2-pswitch button.on { background:var(--ux-ink); border-color:var(--ux-ink); color:#fff; font-weight:600; }
html.ux-v2 .ux2-empty { font-size:14px; color:var(--ux-sub); padding:4px 0; }
html.ux-v2 .ux2-switch { white-space:nowrap; }

/* ── 의뢰자 조회 ── */
html.ux-v2 .client-pane .detail-header { margin-bottom:14px; align-items:flex-start; gap:12px; }
html.ux-v2 .client-pane .detail-name { font-size:26px; font-weight:800; letter-spacing:-.05em; line-height:1.25; }
html.ux-v2 .client-pane .detail-meta { font-size:14px; color:var(--ux-ink-2); margin-top:2px; }
html.ux-v2 .client-pane .detail-actions { flex-shrink:0; flex-wrap:nowrap; }
html.ux-v2 .client-pane .sub-tabs { margin-bottom:18px; }

/* 기본 정보 탭: 왼쪽 기록(메모) · 오른쪽 정보 */
html.ux-v2 .client-pane .sub-panel.active.ux2-info { display:grid; grid-template-columns:minmax(0,1fr) minmax(320px, 400px); gap:22px; align-items:start; }
html.ux-v2 .client-pane .ux2-info > [id^="view-info-"] { grid-column:2; grid-row:1; }
html.ux-v2 .client-pane .ux2-info > [id^="edit-info-"] { grid-column:1 / -1; grid-row:1; }
html.ux-v2 .client-pane .ux2-info > .ux2-contacts { grid-column:2; grid-row:2; }
html.ux-v2 .client-pane .ux2-info > .ux2-memos { grid-column:1; grid-row:1 / span 2; }
html.ux-v2 .client-pane .ux2-info .info-narrow { max-width:none; margin-top:0 !important; border-top:0 !important; padding-top:0 !important; }
html.ux-v2 .client-pane .sub-panel.active.ux2-info.editing { display:block; }
html.ux-v2 .client-pane .ux2-info.editing > .ux2-memos, html.ux-v2 .client-pane .ux2-info.editing > .ux2-contacts { display:none; }

/* 정보 카드: 섹션을 세로로, 빈 칸은 숨김 */
html.ux-v2 .client-pane [id^="view-info-"] { background:var(--ux-paper); border:1px solid var(--ux-line); border-radius:14px; padding:4px 18px 12px; }
html.ux-v2 .client-pane .cv-sec { grid-template-columns:1fr; gap:8px; padding:14px 0; }
html.ux-v2 .client-pane .cv-sec:last-child { border-bottom:0; }
html.ux-v2 .client-pane .cv-rail { border-right:0; padding-right:0; }
html.ux-v2 .client-pane .cv-rail .cv-rd { display:none; }
html.ux-v2 .client-pane .cv-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); column-gap:16px; row-gap:10px; }
html.ux-v2 .client-pane .cv-grid > div:has(> .cv-v.dim) { display:none; }
html.ux-v2 .client-pane .cv-sec.ux2-hide { display:none; }
html.ux-v2 .client-pane .ux2-blank { margin:6px 0 0; padding-top:10px; border-top:1px solid var(--ux-line); font-size:13px; color:var(--ux-sub); display:flex; justify-content:space-between; gap:8px; white-space:nowrap; }
html.ux-v2 .client-pane .ux2-blank button { border:0; background:none; padding:0; color:var(--ux-ink); font-weight:700; text-decoration:underline; text-underline-offset:3px; cursor:pointer; font-size:13px; }

/* 관계자 카드 */
html.ux-v2 .client-pane .ux2-info > .ux2-contacts.info-narrow { background:var(--ux-paper); border:1px solid var(--ux-line); border-radius:14px; padding:14px 16px !important; }
html.ux-v2 .client-pane .ux2-contacts > .field-label { font-size:15px !important; font-weight:800 !important; color:var(--ux-ink); }
html.ux-v2 .client-pane .ux2-contacts > .field-label > span { display:block; margin-top:2px; font-size:12px; font-weight:400; line-height:1.5; }
/* 좁은 열에서 입력칸이 찌그러지지 않게: 2칸씩 쌓고 버튼은 한 줄 전체 */
html.ux-v2 .client-pane .ux2-contacts > div[style*="flex-wrap"] { display:grid !important; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:6px !important; }
html.ux-v2 .client-pane .ux2-contacts > div[style*="flex-wrap"] > input { min-width:0 !important; width:100%; box-sizing:border-box; flex:none !important; }
html.ux-v2 .client-pane .ux2-contacts > div[style*="flex-wrap"] > button { grid-column:1 / -1; }

/* 기록(메모): 입력칸 + 노란 메모지 */
html.ux-v2 .client-pane .ux2-memos > .field-label { font-size:15px !important; font-weight:800 !important; color:var(--ux-ink); }
html.ux-v2 .client-pane .ux2-memos textarea.field-input { background:var(--ux-paper); border-radius:12px; font-size:15px; padding:10px 14px; }
html.ux-v2 .client-pane .memo-item { background:var(--ux-memo); border:0 !important; border-radius:2px 2px 14px 2px; padding:12px 16px !important; margin-bottom:12px; box-shadow:0 1px 0 var(--ux-memo-edge), 0 10px 22px -16px rgba(80,60,0,.45); }
html.ux-v2 .client-pane .memo-item > div:first-child { background:rgba(255,255,255,.7) !important; color:#6b5e1e !important; }
html.ux-v2 .client-pane .memo-item [style*="pre-wrap"] { font-size:15px !important; line-height:1.65; color:#2b2a22; }

@media (max-width: 1100px) {
    html.ux-v2 .client-pane .sub-panel.active.ux2-info { display:flex; flex-direction:column; }
    html.ux-v2 .client-pane .ux2-info > .ux2-memos { order:-1; }
}
@media (max-width: 768px) {
    html.ux-v2 .ux2-glance { grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:8px; }
    html.ux-v2 .ux2-glance.three { grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
    html.ux-v2 .ux2-glance.three > .ux2-g:first-child { grid-column:1 / -1; }
    html.ux-v2 .ux2-g { padding:11px 13px; border-radius:12px; }
    html.ux-v2 .ux2-big { font-size:18px; }
    html.ux-v2 .ux2-big small { display:block; margin:1px 0 0; font-size:12px; }
    html.ux-v2 .ux2-mini { font-size:12px; }
    html.ux-v2 .ux2-g.flow .ux2-steps { display:none; }
    html.ux-v2 .ux2-g.flow .ux2-now { display:flex; }
    html.ux-v2 .client-pane .detail-name { font-size:22px; }
    html.ux-v2 .client-pane .detail-actions { width:100%; flex-wrap:nowrap; overflow-x:auto; scrollbar-width:none; padding-bottom:2px; }
    html.ux-v2 .client-pane .detail-actions > * { flex-shrink:0; }
}
html.ux-v2 .ux2-now { display:none; align-items:baseline; gap:8px; margin-top:7px; white-space:nowrap; overflow:hidden; }
html.ux-v2 .ux2-now b { font-size:14px; }
html.ux-v2 .ux2-now b i { font-style:normal; color:var(--ux-star); margin-right:3px; }
html.ux-v2 .ux2-now span { font-size:12px; color:var(--ux-ink-2); overflow:hidden; text-overflow:ellipsis; }
html.ux-v2 .ux2-now em { font-style:normal; margin-left:auto; font-size:12px; color:var(--ux-sub); }

/* ── 프로젝트 상세 ── */
html.ux-v2 .page-wrap .project-name { font-size:26px; font-weight:800; letter-spacing:-.05em; }
html.ux-v2 .page-wrap .info-card { border-color:var(--ux-line); border-radius:14px; }
html.ux-v2 .page-wrap .card-title { font-size:15px; font-weight:800; color:var(--ux-ink); letter-spacing:-.02em; }
html.ux-v2 .page-wrap .ux2-glance { margin:0 0 16px; }
/* 프로젝트 머리글 버튼 — 중간 폭에서 두 줄로 쪼개지지 않게: 한 단계 작게, 그래도 모자라면 버튼 줄 전체를 제목 아래 한 줄로 */
html.ux-v2 .page-wrap .proj-actions { flex-wrap:nowrap !important; flex-shrink:0; }
html.ux-v2 .page-wrap .page-header-left { min-width:0; }
@media (max-width: 1500px) and (min-width: 769px) {
    html.ux-v2 .page-wrap .proj-actions { gap:6px !important; }
    html.ux-v2 .page-wrap .proj-actions button, html.ux-v2 .page-wrap .proj-actions .ux2-switch { padding:6px 11px !important; font-size:12px !important; line-height:18px !important; min-height:0 !important; }
}
@media (max-width: 1280px) and (min-width: 769px) {
    html.ux-v2 .page-wrap .page-header { flex-wrap:wrap; row-gap:12px; }
    html.ux-v2 .page-wrap .proj-actions { width:100%; justify-content:flex-end; }
}
/* ── 캘린더 ── */
@media (min-width: 769px) {
    /* 상단 한 줄: 왼쪽 [오늘] ‹ 연·월 › · 새로고침 · 검색 / 오른쪽 범례 · 보기 · 주 수 · 작성 모드 · 휴지통 · 더보기 */
    html.ux-v2 .cal-header { flex-wrap:wrap; padding:14px 24px; gap:8px; row-gap:10px; }
    html.ux-v2 .cal-header::before { display:none; }
    html.ux-v2 .cal-header-left { flex-wrap:nowrap; gap:6px; margin-right:auto; flex-shrink:0; } /* 줄어들지 않게 — 범례 밑으로 깔리던 문제 */
    html.ux-v2 .cal-header .cal-center-nav { position:static; transform:none; order:-1; gap:2px; margin-right:8px; }
    html.ux-v2 .cal-header .cal-center-nav .cal-today-btn { position:static; transform:none; margin:0 6px 0 0; height:32px; border-radius:8px; }
    html.ux-v2 .cal-header .cal-center-nav .month-label { font-size:22px; font-weight:800; letter-spacing:-.04em; margin:0 2px; }
    html.ux-v2 .cal-header .cal-legend { position:static; order:9; height:auto; margin:0 8px 0 0; flex-shrink:0; }
    html.ux-v2 .cal-header .cal-legend .lg-hint { display:none; }
    html.ux-v2 .cal-header .view-toggle-group { order:10; margin-left:0; background:#e9ebef; border-radius:10px; padding:3px; }
    html.ux-v2 .cal-header .view-toggle-btn { padding:5px 13px; border-radius:7px; font-size:13px; }
    html.ux-v2 .cal-header .view-toggle-btn.active { background:#fff; color:var(--ux-ink); font-weight:700; box-shadow:0 1px 2px rgba(23,26,32,.12); }
    html.ux-v2 .cal-header #monthWeeksCtl { order:11; margin:0; }
    html.ux-v2 .cal-header .cal-wm-new { order:12; height:32px; font-size:13px !important; border-radius:8px; }
    html.ux-v2 .cal-header #calTrashBtn { order:13; margin-left:0; }
    html.ux-v2 .cal-header #calMoreWrap { order:14; margin-left:0; }
    html.ux-v2 .cal-header .nav-btn { border-radius:8px; }
}
@media (max-width: 1180px) and (min-width: 769px) {
    html.ux-v2 .cal-header { flex-wrap:wrap; row-gap:10px; }
    html.ux-v2 .cal-header .cal-legend { display:none !important; }
}
/* 달력 격자·사이드 */
html.ux-v2 .days-grid, html.ux-v2 #monthCompactView, html.ux-v2 #mcDeskList { border-color:var(--ux-line); border-radius:14px; box-shadow:none; }
html.ux-v2 .weekday { color:var(--ux-sub); font-weight:700; }
html.ux-v2 #addFabBtn, html.ux-v2 #calAddFab { background:var(--ux-ink); color:#fff; box-shadow:0 10px 24px -10px rgba(23,26,32,.55); }
/* 일정 상세 창 */
html.ux-v2 #modalOverlay .modal { border-radius:16px; }

/* ── 견적서 목록 ── */
html.ux-v2 .page-wrap .page-title { font-size:26px; font-weight:800; letter-spacing:-.05em; }
html.ux-v2 .page-wrap .data-card { border-color:var(--ux-line); border-radius:14px; overflow:hidden; }
html.ux-v2 .page-wrap .data-table thead th { background:#f6f7f9; color:var(--ux-sub); font-size:12px; font-weight:700; letter-spacing:0; }
html.ux-v2 .page-wrap .data-table tbody tr:hover td { background:#f8f9fb; }
html.ux-v2 .page-wrap .est-tabs { background:#e9ebef; border-color:transparent; }
@media (max-width: 768px) {
    html.ux-v2 .page-wrap .page-header { flex-direction:column; align-items:stretch; gap:12px; margin-bottom:16px; }
    html.ux-v2 .page-wrap .page-header-left { flex-direction:column; align-items:flex-start; gap:6px; }
    html.ux-v2 .page-wrap .project-name { font-size:22px; }
    html.ux-v2 .page-wrap .proj-actions { flex-wrap:nowrap !important; overflow-x:auto; scrollbar-width:none; padding-bottom:2px; }
    html.ux-v2 .page-wrap .proj-actions > * { flex-shrink:0; }
}
</style>
