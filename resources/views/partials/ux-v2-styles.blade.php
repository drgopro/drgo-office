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
html.ux-v2 .client-pane .ux2-contacts { background:var(--ux-paper); border:1px solid var(--ux-line); border-radius:14px; padding:14px 16px !important; }

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
@media (max-width: 768px) {
    html.ux-v2 .page-wrap .page-header { flex-direction:column; align-items:stretch; gap:12px; margin-bottom:16px; }
    html.ux-v2 .page-wrap .page-header-left { flex-direction:column; align-items:flex-start; gap:6px; }
    html.ux-v2 .page-wrap .project-name { font-size:22px; }
    html.ux-v2 .page-wrap .proj-actions { flex-wrap:nowrap !important; overflow-x:auto; scrollbar-width:none; padding-bottom:2px; }
    html.ux-v2 .page-wrap .proj-actions > * { flex-shrink:0; }
}
</style>
