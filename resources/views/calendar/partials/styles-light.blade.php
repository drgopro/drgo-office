<style>
/* ══════════════════════════════════════════════════════════
   모던 SaaS 리프레시 — 부드러운 테두리·은은한 그림자·둥근 카드
   (기존 스타일 뒤에 로드되어 카세이드에서 우선 적용)
   ══════════════════════════════════════════════════════════ */
:root {
    --card-shadow: 0 4px 24px rgba(0,0,0,0.28);
    --btn-shadow: 0 1px 2px rgba(0,0,0,0.35);
    --border: #2c2c2c;          /* 격자선·테두리 완화 (기존 #3a3a3a) */
}
[data-theme="light"] {
    --border: #e4e7ec;          /* 진한 회색 격자선 → 연한 헤어라인 */
    --surface2: #f1f3f7;
    --card-shadow: 0 1px 2px rgba(16,24,40,0.04), 0 6px 20px rgba(16,24,40,0.07);
    --btn-shadow: 0 1px 2px rgba(16,24,40,0.08);
}

/* ── 월간 그리드: 카드처럼 띄우고 헤어라인 격자 ── */
.days-grid { border:1px solid var(--border); border-radius:16px; box-shadow:var(--card-shadow); }
.timeline-grid { border-radius:14px; box-shadow:var(--card-shadow); }
.day-cell { transition:background 0.18s ease; }
[data-theme="light"] .day-cell.other-month { background:#f6f7f9; opacity:1; }
[data-theme="light"] .day-cell.other-month .day-num,
[data-theme="light"] .day-cell.other-month .holiday-label { opacity:0.5; }

/* ── 버튼: 둥글게 + 은은한 그림자 ── */
.nav-btn, .icon-btn { border-radius:10px; }
.add-btn, .btn-save, .btn-save-top, .modal-external-action { border-radius:10px; box-shadow:var(--btn-shadow); font-weight:600; }
.add-btn:hover, .btn-save:hover, .btn-save-top:hover, .modal-external-action:hover { filter:brightness(1.06); }
.view-toggle-group, .cal-fontsize { border-radius:10px; }
.filter-btn { transition:all 0.16s ease; }

/* ── 라이트모드: 과하게 진했던 테두리들을 부드럽게 ── */
[data-theme="light"] .nav-btn,
[data-theme="light"] .icon-btn,
[data-theme="light"] .filter-btn,
[data-theme="light"] .modal-external-close { border-color:#d5dae3; color:#5a6070; }
[data-theme="light"] .radio-btn,
[data-theme="light"] .special-opt-btn,
[data-theme="light"] .sched-opt-btn,
[data-theme="light"] .action-btn { border-color:#dfe3ea; }
[data-theme="light"] .special-opt-btn,
[data-theme="light"] .sched-opt-btn,
[data-theme="light"] .action-btn { background:#f4f6f9; }
[data-theme="light"] .field-input,
[data-theme="light"] .field-textarea,
[data-theme="light"] .dt-input { border-color:#dfe3ea; }
[data-theme="light"] .field-section,
[data-theme="light"] .datetime-section { border-color:#e6e9ef; background:#f7f8fb; }

/* ── 모달·팝오버: 그림자 톤 정리 ── */
.modal { border-radius:18px; }
.day-popover { border-radius:14px; box-shadow:var(--card-shadow); }
</style>
