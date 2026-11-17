{{-- 캘린더 대기함 — 팝업·알림 자리와 스타일. 패널(#calBench)과 상단 버튼(#benchBtn)은 layout, 동작은 js/13-bench --}}
@if(Auth::user()->hasPermission('calendar.edit'))
<div id="benchPopWrap"></div>
<div id="benchToast" hidden role="status"></div>
<template id="benchHelpTpl">
    <div class="bench-acts" style="justify-content:space-between"><h3>대기함 사용법</h3><button type="button" class="bench-mini" onclick="benchHelp(false)">닫기</button></div>
    <p>보류됐거나 다시 가야 하는 일정을 잠시 모아 두는 곳입니다. 평소에는 접혀 있고 상단 <b>대기함</b> 버튼으로 엽니다. 버튼의 숫자는 전체 건수와 긴급 건수입니다.</p>
    <ol>
        <li><b>대기함으로 보내기</b><br>달력에서 일정을 누르고 <b>대기함으로 보내기</b>를 누릅니다. 이유(보류, 재방문)와 내용을 적고, 제품이 필요하면 견적서를 연결합니다. 보류는 달력에서 빠지고, 재방문은 원래 일정을 두고 새로 생깁니다. 원래 날짜에는 점선 흔적이 남습니다. 날짜를 못 잡은 의뢰는 대기함의 <b>+ 날짜 미정</b>으로 바로 만듭니다.</li>
        <li><b>먼저 처리할 일정 고르기</b><br>위에서부터 처리합니다. <span class="bench-flag f-urgent">긴급</span>은 기한 3일 이내, 기한 지남, 기한 안에 준비 불가입니다. 조건이 남은 일정은 맨 아래에 있습니다.</li>
        <li><b>제품 확인하기</b><br>카드를 누르면 견적서 품목이 나옵니다. 전산 재고는 실제와 다를 수 있으니 창고를 보고 <b>창고에 있음</b> 또는 <b>실제 없음</b>을 누릅니다. 주문 소요일이 없는 제품은 <b>3일 내 가능</b> 또는 <b>불가</b>를 고릅니다.</li>
        <li><b>날짜 정하기</b><br>달력에 빗금은 제품 준비 중, 파란 칸은 배치할 수 있는 기간, 초록 지역 표시는 같은 지역 일정입니다. 같은 지역 일정의 <b>이 날로</b>를 누르거나, 날짜를 직접 고르거나, 카드를 날짜 칸으로 끌어 놓습니다. 시스템은 날짜를 추천하지 않습니다.</li>
        <li><b>배치하기</b><br>담당자를 고르면 그날 그 사람의 일정이 보입니다. 작업 시간을 보고 판단해 주세요. 시간은 비워도 되고, 비우면 <b>시간 미정</b>으로 들어갑니다.</li>
        <li><b>알림이 뜨면</b><br>창고 확인 전이거나 제품 준비일보다 이르면 배치 버튼이 잠깁니다. 확인을 마치거나, 사정이 있으면 <b>알림 무시하고 진행</b>을 누르고 사유를 적습니다. 사유는 일정 이력에 남고 달력에 ⚠로 표시됩니다.</li>
    </ol>
    <p class="bench-muted">실수했으면 아래 알림의 <b>되돌리기</b>를 누르세요.</p>
</template>
@endif
<style>
    :root { --bench-hold:#b4610e; --bench-red:#c8323a; --bench-green:#1f8a4c; --bench-blue:#2e6cb5; }

    /* 상단 버튼 — nav-btn의 고정 정사각 폭을 풀고, 휴지통 대신 우측 정렬을 맡는다 */
    #benchBtn { width:auto; min-width:0; padding:0 10px; gap:6px; display:inline-flex; align-items:center; font-size:13px; font-weight:600; white-space:nowrap; }
    #benchBtn.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .bench-cnt { background:var(--text); color:var(--surface); border-radius:10px; padding:0 7px; font-size:12px; font-variant-numeric:tabular-nums; }
    #benchBtn.on .bench-cnt { background:var(--accent-text); color:var(--accent); }
    .bench-cnt.urgent, #benchBtn.on .bench-cnt.urgent { background:var(--bench-red); color:#fff; }
    @media (min-width:769px) { #benchBtn { margin-left:auto; } #benchBtn + #calTrashBtn { margin-left:0; } }

    /* 패널 — 달력 오른쪽, 기본 접힘. 860px 이하에서는 달력 아래로 */
    #calBench { width:360px; flex-shrink:0; align-self:flex-start; display:flex; flex-direction:column; min-width:0; max-height:calc(100vh - 140px); position:sticky; top:12px; background:var(--surface2); border:1px solid var(--border); border-radius:14px; overflow:hidden; font-size:13px; line-height:1.45; }
    #calBench[hidden] { display:none; }
    .bench-head { padding:12px 12px 8px; display:grid; gap:8px; border-bottom:1px solid var(--border); }
    .bench-head h3 { margin:0; font-size:15px; display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .bench-head h3 small { font-size:12px; color:var(--text-muted); font-weight:500; }
    .bench-head p { margin:0; font-size:12px; color:var(--text-muted); }
    .bench-tabs, .bench-acts, .bench-seg { display:flex; flex-wrap:wrap; gap:5px; align-items:center; }
    .bench-chip { border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:7px; padding:3px 9px; font-size:12px; font-weight:500; cursor:pointer; }
    .bench-chip[aria-pressed="true"] { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .bench-list { overflow-y:auto; padding:10px 12px; display:grid; gap:8px; align-content:start; }
    .bench-empty { color:var(--text-muted); text-align:center; padding:30px 10px; margin:0; }
    .bench-empty.sm { padding:6px; }
    .bench-muted { color:var(--text-muted); }
    .bench-red { color:var(--bench-red); }

    .bench-card { background:var(--surface); border:1px solid var(--border); border-top:3px solid var(--k); border-radius:8px; padding:9px 10px; display:grid; gap:5px; cursor:grab; min-width:0; }
    .bench-card[aria-selected="true"] { border-color:var(--bench-blue); border-top-color:var(--k); box-shadow:0 0 0 2px color-mix(in srgb, var(--bench-blue) 18%, transparent); cursor:default; }
    .bench-card.blocked { opacity:.8; cursor:default; }
    .bench-card:focus-visible, .bench-mini:focus-visible, .bench-chip:focus-visible, #benchBtn:focus-visible { outline:2px solid var(--bench-blue); outline-offset:1px; }
    .bench-card header { display:flex; gap:6px; align-items:flex-start; justify-content:space-between; }
    .bench-card h4 { margin:0; font-size:13.5px; line-height:1.35; min-width:0; overflow-wrap:anywhere; }
    .bench-tag { font-size:11px; font-weight:700; border-radius:4px; padding:1px 6px; white-space:nowrap; color:var(--k); background:color-mix(in srgb, var(--k) 13%, var(--surface)); }
    .bench-meta { display:flex; flex-wrap:wrap; gap:2px 10px; font-size:12px; color:var(--text-muted); }
    .bench-meta b { color:var(--text); font-weight:500; }
    .bench-flag { font-size:11px; font-weight:700; border-radius:4px; padding:1px 6px; white-space:nowrap; }
    .bench-flag.wrap { white-space:normal; padding:5px 8px; font-size:12px; }
    .bench-flag.f-urgent { background:color-mix(in srgb, var(--bench-red) 12%, var(--surface)); color:var(--bench-red); }
    .bench-flag.f-prod, .bench-flag.f-notice { background:color-mix(in srgb, var(--bench-hold) 13%, var(--surface)); color:var(--bench-hold); }
    .bench-flag.f-ok { background:color-mix(in srgb, var(--bench-green) 13%, var(--surface)); color:var(--bench-green); }
    .bench-flag.f-block, .bench-flag.f-wait { background:var(--surface2); color:var(--text-muted); }
    .bench-why { font-size:12px; background:var(--surface2); border-radius:6px; padding:5px 8px; overflow-wrap:anywhere; }
    .bench-why.win { background:color-mix(in srgb, var(--bench-blue) 9%, var(--surface)); }
    .bench-sub { font-size:11px; color:var(--text-muted); font-weight:700; margin-top:2px; }
    .bench-prod { display:grid; gap:4px; font-size:12px; background:color-mix(in srgb, var(--bench-hold) 10%, var(--surface)); border-radius:6px; padding:6px 8px; }
    .bench-prod.none { background:var(--surface2); }
    .bench-pr { display:grid; gap:3px; padding:5px 0; border-top:1px solid color-mix(in srgb, var(--bench-hold) 22%, var(--surface)); }
    .bench-pn { display:flex; justify-content:space-between; gap:8px; }
    .bench-pn span { min-width:0; overflow-wrap:anywhere; }
    .bench-pn small { color:var(--text-muted); white-space:nowrap; font-variant-numeric:tabular-nums; }
    .bench-ps { display:flex; flex-wrap:wrap; gap:4px 8px; align-items:center; }
    .bench-ps input[type="date"] { font-size:12px; padding:2px 4px; }
    .bench-recs { display:grid; gap:5px; }
    .bench-rec { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:8px; align-items:center; border:1px solid var(--border); border-radius:6px; padding:6px 8px; font-size:12px; background:var(--surface); }
    .bench-rec.off { opacity:.6; }
    .bench-rec b { font-variant-numeric:tabular-nums; white-space:nowrap; }
    .bench-rec > span { min-width:0; overflow-wrap:anywhere; }
    .bench-mini { border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:6px; padding:3px 9px; font-size:12px; font-weight:500; white-space:nowrap; cursor:pointer; font-family:inherit; }
    .bench-mini.pri { background:var(--accent); border-color:var(--accent); color:var(--accent-text); }
    .bench-mini.danger { background:var(--bench-red); border-color:var(--bench-red); color:#fff; }
    .bench-mini.link { border:0; background:none; color:var(--bench-blue); padding:3px 2px; }
    .bench-mini:disabled { opacity:.5; cursor:not-allowed; }
    .bench-form { display:grid; gap:6px; background:var(--surface2); border-radius:6px; padding:8px; }
    .bench-form .row { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:6px; }
    .bench-form label { display:grid; gap:2px; font-size:11px; color:var(--text-muted); }
    .bench-form input, .bench-form select, .bench-form textarea, .bench-ps input { width:100%; min-width:0; font:inherit; font-size:13px; color:var(--text); background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:5px 8px; box-sizing:border-box; }
    .bench-form p { margin:0; font-size:12px; }
    .bench-busy { font-size:12px; background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:6px 8px; overflow-wrap:anywhere; }
    .bench-alert { display:grid; gap:4px; font-size:12px; background:color-mix(in srgb, var(--bench-red) 10%, var(--surface)); color:var(--bench-red); border-radius:6px; padding:7px 9px; }
    .bench-alert label { color:var(--text); }
    .bench-estchip { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:var(--text); background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:2px 4px 2px 8px; }
    .bench-estchip button { border:0; background:none; color:var(--text-muted); cursor:pointer; padding:0 4px; font-size:12px; }
    .bench-estlist { display:grid; border:1px solid var(--border); border-radius:6px; overflow:hidden; background:var(--surface); }
    .bench-estlist button { text-align:left; border:0; border-top:1px solid var(--border); background:none; color:var(--text); padding:6px 8px; font-size:12px; cursor:pointer; font-family:inherit; }
    .bench-estlist button:first-child { border-top:0; }
    .bench-estlist button:hover { background:var(--surface2); }

    /* 달력 표시 — 흔적(점선), 제품 준비(빗금), 배치 가능 기간(옅은 파랑), 날짜 칸 표시, ↩ ⚠ */
    .day-cell .event-chip.bench-trace { border:1px dashed var(--k) !important; background:transparent !important; color:var(--k) !important; box-shadow:none; cursor:pointer; pointer-events:auto; }
    .day-cell.bench-win { background:color-mix(in srgb, var(--bench-blue) 8%, var(--surface)) !important; }
    .day-cell.bench-lead { background:repeating-linear-gradient(135deg, color-mix(in srgb, var(--bench-hold) 12%, var(--surface)) 0 6px, var(--surface) 6px 12px) !important; }
    .day-cell.bench-drop { outline:2px dashed var(--bench-blue); outline-offset:-3px; }
    .day-cell .event-chip.bench-hit { box-shadow:inset 0 0 0 2px var(--bench-green); }
    .bench-daytag { font-size:10px; font-weight:700; border-radius:3px; padding:0 4px; white-space:nowrap; margin-left:4px; line-height:1.6; }
    .bench-daytag.due { background:var(--bench-red); color:#fff; }
    .bench-daytag.lead { color:var(--bench-hold); }
    .bench-daytag.area { background:var(--bench-green); color:#fff; }
    .bench-daytag.area.off { background:#b9c0c8; }
    .opt-ic.bench-mark { font-weight:700; color:#7a3fc4; }
    .opt-ic.bench-mark.warn { color:var(--bench-red); }
    .bench-dayrow { cursor:pointer; }
    .bench-legend { display:grid; gap:5px; font-size:12px; color:var(--text-muted); padding:6px 2px 0; }
    .bench-legend div { display:flex; align-items:center; gap:7px; }
    .bench-legend i { width:12px; height:12px; border-radius:3px; flex:none; box-sizing:border-box; }
    .bench-legend .lg-win { background:color-mix(in srgb, var(--bench-blue) 12%, var(--surface)); border:1px solid var(--border); }
    .bench-legend .lg-lead { background:repeating-linear-gradient(135deg, color-mix(in srgb, var(--bench-hold) 35%, var(--surface)) 0 3px, var(--surface) 3px 6px); border:1px solid var(--border); }
    .bench-legend .bench-daytag { margin-left:0; }

    /* 팝업·알림 */
    .bench-popbg { position:fixed; inset:0; background:rgba(20,30,40,.35); z-index:300; }
    .bench-pop { position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:min(460px,calc(100vw - 32px)); max-height:calc(100vh - 40px); overflow-y:auto; background:var(--surface); color:var(--text); border-radius:12px; padding:16px; z-index:301; display:grid; gap:10px; box-shadow:0 10px 30px rgba(0,0,0,.25); font-size:13px; line-height:1.5; }
    .bench-pop.help { width:min(560px,calc(100vw - 32px)); }
    .bench-pop h3 { margin:0; font-size:16px; }
    .bench-pop p { margin:0; }
    .bench-pop ol { margin:0; padding-left:20px; display:grid; gap:8px; }
    #benchToast { position:fixed; left:50%; bottom:calc(18px + env(safe-area-inset-bottom,0px)); transform:translateX(-50%); background:var(--text); color:var(--surface); padding:9px 14px; border-radius:8px; font-size:13px; z-index:310; display:flex; gap:12px; align-items:center; max-width:calc(100vw - 32px); }
    #benchToast[hidden] { display:none; }
    #benchToast button { background:none; border:0; color:#9fc5ff; font-weight:700; padding:0; cursor:pointer; font-family:inherit; font-size:13px; white-space:nowrap; }

    .bench-close { display:none; }
    @media (min-width:861px) { body.bench-open #calAddFab { right:408px; } }
    @media (max-width:860px) {
        #calBody { flex-wrap:wrap; }
        #calBench { width:100%; max-height:none; position:static; }
        .bench-list { overflow:visible; }
    }
    /* 모바일 — 달력 아래는 하단 일정 시트에 가려지므로 전체 화면으로 띄우고 닫기 버튼을 둔다 */
    @media (max-width:768px) {
        #calBench { position:fixed; inset:0; z-index:240; border:0; border-radius:0; overflow-y:auto; padding-bottom:env(safe-area-inset-bottom,0px); }
        .bench-close { display:inline-block; }
        body.bench-open #calAddFab { display:none; }
    }
    @media (prefers-reduced-motion:reduce) { .bench-card, .bench-mini { transition:none; } }
</style>
