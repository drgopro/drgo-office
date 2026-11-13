{{-- 공통 버튼 시스템 (DESIGN.md · Buttons)
     페이지별로 제각각 정의된 버튼 클래스를 크기 2종(34px / 28px) · 변형 4종(primary / secondary / danger / ghost)으로 맞춘다.
     페이지 스타일보다 뒤에 로드되도록 본문 뒤에서 include 한다. 너비·표시 여부(display)는 건드리지 않는다. --}}
<style id="drgo-button-system">
    :root {
        --bx-h:34px; --bx-h-sm:28px; --bx-radius:8px;
        --bx-primary:#3b5ea0; --bx-primary-hover:#2f4e88; --bx-on-primary:#ffffff;
        --bx-line:#c3c8d2; --bx-line-hover:#9aa1b0; --bx-paper:#ffffff; --bx-paper-hover:#f1f3f6; --bx-ink:#1a1e28; --bx-ink-muted:#4a5060;
        --bx-danger:#b83232; --bx-danger-hover-bg:#b83232;
    }
    /* 크기: 기본 34px = 테두리 1 + 패딩 8 + 줄높이 16 + 패딩 8 + 테두리 1 */
    .btn, .btn-primary, .btn-outline, .btn-save, .btn-cancel, .btn-delete, .btn-danger, .btn-new, .btn-search,
    .btn-choose, .btn-upload, .btn-review-rules, .btn-add-svc, .btn-ghost, .todo-add-btn, .todo-mfilter-btn,
    .fb-attach-btn, .fb-submit, .wiki-cat-edit-btn {
        padding:8px 14px; line-height:16px; font-size:13px; font-weight:600; border-radius:var(--bx-radius);
        border-width:1px; border-style:solid; cursor:pointer; white-space:nowrap; letter-spacing:-0.01em;
        transition:background-color .12s, border-color .12s, color .12s;
    }
    /* 작게 28px: 표 안·카드 안 보조 동작 */
    .btn-sm, .btn-act, .pcf-save-btn, .pcf-add-btn {
        padding:5px 10px; line-height:16px; font-size:12px; font-weight:600; border-radius:6px;
        border-width:1px; border-style:solid; cursor:pointer; white-space:nowrap; min-height:0;
    }
    .btn, .btn-primary, .btn-outline, .btn-save, .btn-cancel, .btn-delete, .btn-danger, .btn-new, .btn-search,
    .btn-choose, .btn-upload, .btn-review-rules, .btn-add-svc, .btn-ghost, .todo-add-btn, .todo-mfilter-btn,
    .fb-attach-btn, .fb-submit, .wiki-cat-edit-btn { min-height:0; }
    /* 모바일: 손가락으로 누르기 쉽게 기본 40px · 작게 32px */
    @media (max-width: 768px) {
        .btn, .btn-primary, .btn-outline, .btn-save, .btn-cancel, .btn-delete, .btn-danger, .btn-new, .btn-search,
        .btn-choose, .btn-upload, .btn-review-rules, .btn-add-svc, .btn-ghost, .todo-add-btn, .todo-mfilter-btn,
        .fb-attach-btn, .fb-submit, .wiki-cat-edit-btn { padding:11px 16px; font-size:14px; line-height:16px; }
        .btn-sm, .btn-act, .pcf-save-btn, .pcf-add-btn { padding:7px 12px; font-size:13px; line-height:16px; }
    }

    /* 보조 (secondary): 흰 바탕 + 선 */
    .btn, .btn-outline, .btn-cancel, .btn-choose, .btn-upload, .btn-review-rules, .btn-add-svc, .todo-mfilter-btn,
    .fb-attach-btn, .wiki-cat-edit-btn, .btn-act, .pcf-save-btn, .pcf-add-btn {
        background:var(--bx-paper); border-color:var(--bx-line); color:var(--bx-ink);
    }
    .btn:hover, .btn-outline:hover, .btn-cancel:hover, .btn-choose:hover, .btn-upload:hover, .btn-review-rules:hover,
    .btn-add-svc:hover, .todo-mfilter-btn:hover, .fb-attach-btn:hover, .wiki-cat-edit-btn:hover, .btn-act:hover,
    .pcf-save-btn:hover, .pcf-add-btn:hover {
        background:var(--bx-paper-hover); border-color:var(--bx-line-hover); color:var(--bx-ink);
    }

    /* 주요 (primary): 영역당 하나 */
    .btn-primary, .btn-save, .btn-new, .btn-search, .todo-add-btn, .fb-submit {
        background:var(--bx-primary); border-color:var(--bx-primary); color:var(--bx-on-primary); filter:none;
    }
    .btn-primary:hover, .btn-save:hover, .btn-new:hover, .btn-search:hover, .todo-add-btn:hover, .fb-submit:hover {
        background:var(--bx-primary-hover); border-color:var(--bx-primary-hover); color:var(--bx-on-primary); filter:none;
    }

    /* 은은한 (ghost): 흰 바탕 + 옅은 글자 + 선. 취소·로그·부가 동작 */
    .btn-ghost { background:var(--bx-paper); border-color:var(--bx-line); color:var(--bx-ink-muted); font-weight:500; }
    .btn-ghost:hover { background:var(--bx-paper-hover); border-color:var(--bx-line-hover); color:var(--bx-ink); }

    /* 위험 (danger) */
    .btn-delete, .btn-danger { background:var(--bx-paper); border-color:var(--bx-danger); color:var(--bx-danger); }
    .btn-delete:hover, .btn-danger:hover { background:var(--bx-danger-hover-bg); border-color:var(--bx-danger-hover-bg); color:#fff; }

    /* 공통 상태 */
    :is(.btn, .btn-primary, .btn-outline, .btn-save, .btn-cancel, .btn-delete, .btn-danger, .btn-new, .btn-search,
        .btn-choose, .btn-upload, .btn-review-rules, .btn-add-svc, .btn-ghost, .todo-add-btn, .todo-mfilter-btn,
        .fb-attach-btn, .fb-submit, .wiki-cat-edit-btn, .btn-sm, .btn-act, .pcf-save-btn, .pcf-add-btn):disabled {
        opacity:.5; cursor:not-allowed;
    }
    :is(.btn, .btn-primary, .btn-outline, .btn-save, .btn-cancel, .btn-delete, .btn-danger, .btn-new, .btn-search,
        .btn-choose, .btn-upload, .btn-review-rules, .btn-add-svc, .btn-ghost, .todo-add-btn, .todo-mfilter-btn,
        .fb-attach-btn, .fb-submit, .wiki-cat-edit-btn, .btn-sm, .btn-act, .pcf-save-btn, .pcf-add-btn):focus-visible {
        outline:2px solid var(--bx-primary); outline-offset:2px;
    }
</style>
