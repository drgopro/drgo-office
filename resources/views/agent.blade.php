{{-- AI 에이전트 작업 현황 (시연용 샘플 데이터) — master/admin 전용. 원본 시안: github/Projects/agent-dashboard/design/pixel-office-mock.html
     실제 데이터 연동(맥북 훅 → 서버 중계)은 별도 단계. 이 페이지는 Blade 문법 없이 정적 HTML이라 @verbatim으로 감쌈 --}}
@verbatim
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI 에이전트 작업 현황 - 닥터고블린 오피스</title>
<link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<style>
/* Layout: 상단 도트 사무실 장면(전체 폭) → 아래 좌 작업 대화 / 우 보고서(검색·날짜 필터). 라이트 단일 테마, 연한 그레이 톤. */
:root {
  color-scheme: light;
  --bg: #F3F3F5; --panel: #FAFAFB; --panel-2: #F1F1F4; --line: #E3E4E9; --line-2: #D4D5DC;
  --fg: #3D3E48; --muted: #80828F; --faint: #A7A9B4;
  --accent: #8B86C9; --accent-soft: #EDECF6; --accent-ink: #5F5A9E;
  --codex: #6FAEA3; --codex-soft: #E7F2F0; --codex-ink: #3F7A70;
  --bot-soft: #EEEFF3;
  --good: #7FB58D; --good-ink: #4C8A5C; --bad: #D08A8A; --bad-ink: #A65C5C;
  --mark: #F3EBC4;
  --font-sans: "Pretendard Variable", Pretendard, -apple-system, "Apple SD Gothic Neo", "Malgun Gothic", system-ui, sans-serif;
  --font-mono: "SF Mono", ui-monospace, Menlo, Consolas, monospace;
  /* 장면 팔레트 */
  --wall: #EFEFF2; --wall-line: #E4E4E9; --base: #D9DAE0; --floor-a: #EAEAED; --floor-b: #E4E4E8;
  --ol: #6B6C78; --skin: #F1DED0; --eye: #4A4B55; --leg: #8E909B; --wood: #DDD2C3; --wood-d: #C4B6A3;
  --scr-off: #B4B6C0; --scr-on: #C9E1F0; --led-off: #9A9CA7; --led-on: #9ED3A8;
  --gray-a: #CBCCD4; --gray-b: #B3B5BF; --sky: #E9EEF5; --cloud: #FBFBFD; --leaf: #BBD0BC; --pot: #D2BFAE;
  --paper: #FFFFFF; --paper-l: #B6B8C2; --folder: #E6D7A4;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--fg); font-family: var(--font-sans); font-size: 14px; line-height: 1.45; }
.app { max-width: 1360px; margin: 0 auto; padding-block: 12px 24px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 12px; }
.panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; min-width: 0; }
.mono { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
.btn { font: inherit; font-size: 13px; cursor: pointer; background: var(--panel); color: var(--fg); border: 1px solid var(--line-2); border-radius: 8px; padding: 5px 11px; white-space: nowrap; }
.btn:hover { background: var(--panel-2); }
.btn.on { background: var(--accent-soft); color: var(--accent-ink); border-color: transparent; }
.btn:focus-visible, input:focus-visible, summary:focus-visible, .chip:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

/* ── 상단 바 ── */
.top { display: flex; align-items: center; justify-content: space-between; gap: 10px 18px; flex-wrap: wrap; }
.top h1 { margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.top h1 small { font-weight: 500; color: var(--muted); font-size: 12.5px; }
.session { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: 12.5px; flex-wrap: wrap; }
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--good); box-shadow: 0 0 0 3px color-mix(in srgb, var(--good) 25%, transparent); }
.session b { color: var(--fg); font-weight: 600; }
.acts { display: flex; gap: 6px; flex-wrap: wrap; }
.badge { font-size: 12px; color: var(--accent-ink); background: var(--accent-soft); border-radius: 999px; padding: 3px 10px; font-weight: 600; }

/* ── 장면 ── */
.scene { overflow: hidden; background: var(--wall); }
.scene svg { display: block; width: 100%; height: auto; max-width: 960px; margin: 0 auto; shape-rendering: crispEdges; }
.scene text { font-family: var(--font-sans); shape-rendering: auto; }
.lbl { font-size: 5px; font-weight: 600; fill: var(--fg); }
.sub { font-size: 3.8px; fill: var(--muted); }
.f2 { display: none; }
.node.working .f1 { animation: fa .5s steps(1) infinite; }
.node.working .f2 { display: block; animation: fb .5s steps(1) infinite; }
@keyframes fa { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
@keyframes fb { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }
.scr { fill: var(--scr-off); }
.node.working .scr { fill: var(--scr-on); animation: flick 1.6s steps(2) infinite; }
@keyframes flick { 50% { fill: #D8EAF5; } }
.led { fill: var(--led-off); }
.node.working .led { fill: var(--led-on); animation: blink .9s steps(2) infinite; }
.node.working .led:nth-of-type(2n) { animation-delay: .3s; }
@keyframes blink { 50% { fill: var(--led-off); } }
.node.working .ol { fill: var(--accent); }
.node.done .ol { fill: var(--good-ink); }
.node.error .ol { fill: var(--bad-ink); }
.tile { stroke: var(--line-2); stroke-width: 1; transition: stroke .2s; }
.node.working .tile { stroke: var(--accent); }
.node.done .tile { stroke: var(--good); }
.node.error .tile { stroke: var(--bad); }
.node.idle .body { opacity: .92; }
.emblem { transition: opacity .2s; }
.node.working .emblem { animation: fa 1s steps(1) infinite; }
.pill { display: none; }
.node.working .pill, .node.done .pill, .node.error .pill { display: block; }
.pill rect { fill: var(--accent); }
.node.done .pill rect { fill: var(--good); } .node.error .pill rect { fill: var(--bad); }
.pill text { font-size: 3.6px; font-weight: 700; fill: #fff; }
.bubble { opacity: 0; transition: opacity .2s; }
.bubble.show { opacity: 1; }
.bubble rect.bg { fill: #fff; } .bubble rect.bd { fill: var(--ol); }
.bubble text { font-size: 4px; fill: var(--fg); }
.bubble.err rect.bd { fill: var(--bad-ink); } .bubble.err text { fill: var(--bad-ink); }
.courier { pointer-events: none; }
.legend { display: flex; gap: 14px; flex-wrap: wrap; padding: 8px 14px 10px; font-size: 12px; color: var(--muted); background: var(--panel); border-top: 1px solid var(--line); }
.legend i { display: inline-block; width: 10px; height: 10px; margin-right: 6px; vertical-align: -1px; }
.legend .w i { background: var(--accent); } .legend .d i { background: var(--good); } .legend .e i { background: var(--bad); } .legend .i i { background: var(--gray-b); }

/* ── 아래 2열 ── */
.main { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(320px, 1fr); gap: 12px; align-items: stretch; }
@media (max-width: 900px) { .main { grid-template-columns: 1fr; } }
.main > .panel { display: flex; flex-direction: column; min-height: 0; }
.main aside { max-height: 100%; }
@media (max-width: 900px) { .main aside { max-height: 640px; } }
body.present .main { display: none; }
.hd { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; padding: 12px 14px 10px; border-bottom: 1px solid var(--line); }
.hd h2 { margin: 0; font-size: 13.5px; font-weight: 700; }
.hd .n { font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; }

/* 대화 */
.chat { height: 480px; overflow: auto; padding: 14px 14px 8px; display: flex; flex-direction: column; gap: 10px; }
@media (max-width: 900px) { .chat { height: 380px; } }
.sys { align-self: center; font-size: 11.5px; color: var(--muted); background: var(--panel-2); padding: 3px 12px; border-radius: 999px; margin: 2px 0; }
.msg { display: grid; grid-template-columns: 30px minmax(0, 1fr); gap: 10px; max-width: 88%; animation: pop .3s ease-out; }
.msg.me { grid-template-columns: minmax(0, 1fr); align-self: flex-end; justify-items: end; }
.av { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; font-weight: 700; font-size: 12px; color: #fff; }
.av.claude { background: var(--accent); } .av.codex { background: var(--codex); } .av.payapp, .av.git, .av.github, .av.forge { background: var(--gray-b); }
.meta { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
.meta .nm { font-size: 12px; font-weight: 700; color: var(--muted); }
.msg.claude .meta .nm { color: var(--accent-ink); } .msg.codex .meta .nm { color: var(--codex-ink); }
.meta time { font-size: 11px; color: var(--faint); font-family: var(--font-mono); }
.msg.me .meta { justify-content: flex-end; }
.bub { background: #fff; border: 1px solid var(--line); border-radius: 4px 12px 12px 12px; padding: 9px 12px; font-size: 13.5px; min-width: 0; overflow-wrap: anywhere; }
.msg.me .bub { background: var(--accent-soft); border-color: transparent; border-radius: 12px 4px 12px 12px; color: var(--fg); }
.msg.codex .bub { background: var(--codex-soft); border-color: transparent; }
.msg.bot .bub { background: var(--bot-soft); border-color: transparent; }
@keyframes pop { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
.prompt { white-space: pre-wrap; line-height: 1.5; }
.prompt.clamp { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.prompt .pchip { display: inline-block; font-size: 11.5px; font-family: var(--font-mono); background: #fff; border: 1px solid var(--line-2); border-radius: 6px; padding: 0 6px; margin: 1px 2px; white-space: nowrap; color: var(--muted); }
.prompt .file { font-family: var(--font-mono); font-size: 12.5px; background: #fff; border-radius: 4px; padding: 0 4px; color: var(--accent-ink); }
.pfoot { display: flex; gap: 10px; align-items: center; margin-top: 6px; font-size: 11px; color: var(--muted); flex-wrap: wrap; }
.pfoot button { font: inherit; font-size: 11px; color: var(--accent-ink); background: #fff; border: 1px solid var(--line-2); border-radius: 6px; padding: 1px 8px; cursor: pointer; }
.raw { display: none; margin: 8px 0 0; padding: 8px 10px; background: #fff; border: 1px dashed var(--line-2); border-radius: 8px; font-family: var(--font-mono); font-size: 12px; white-space: pre-wrap; overflow-wrap: anywhere; max-height: 220px; overflow: auto; color: var(--muted); }
.raw.open { display: block; }
.steps { list-style: none; margin: 6px 0 0; padding: 0; display: grid; gap: 5px; }
.step { display: grid; grid-template-columns: 14px minmax(0, 1fr) auto; gap: 8px; align-items: center; font-size: 13px; }
.step .ic { width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--faint); }
.step.working .ic { border-color: var(--accent); border-top-color: transparent; animation: spin .8s linear infinite; }
.step.done .ic { border-color: var(--good); background: var(--good); box-shadow: inset 0 0 0 3px #fff; }
.step.error .ic { border-color: var(--bad); background: var(--bad); box-shadow: inset 0 0 0 3px #fff; }
.step.error .txt { color: var(--bad-ink); }
.step.working .txt { color: var(--accent-ink); font-weight: 600; }
.step .dur { font-size: 11px; color: var(--faint); font-family: var(--font-mono); }
@keyframes spin { to { transform: rotate(360deg); } }
.lead { font-weight: 600; }
.lead.live::after { content: ""; display: inline-block; width: 6px; height: 6px; margin-left: 6px; border-radius: 50%; background: var(--accent); vertical-align: middle; animation: fa 1.2s ease-in-out infinite; }
.card { display: grid; gap: 5px; }
.card .kind { font-size: 11px; font-weight: 700; color: var(--muted); letter-spacing: .04em; }
.msg.codex .card .kind { color: var(--codex-ink); }
.card .ct { font-weight: 700; font-size: 13.5px; } .card .cm { font-size: 12px; color: var(--muted); }
.card ul, .rp ul { margin: 2px 0 0; padding: 0; list-style: none; display: grid; gap: 5px; font-size: 12.5px; }
.card li, .rp li { display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: baseline; }
.tag { font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 6px; white-space: nowrap; }
.tag.ok { background: color-mix(in srgb, var(--good) 22%, #fff); color: var(--good-ink); }
.tag.hold { background: #fff; color: var(--muted); border: 1px solid var(--line-2); }
.typing { min-height: 30px; padding: 6px 14px 10px; font-size: 12.5px; color: var(--muted); display: flex; align-items: center; gap: 8px; border-top: 1px solid var(--line); }
.typing:empty { display: none; }
.typing .dots { display: inline-flex; gap: 3px; }
.typing .dots i { width: 5px; height: 5px; border-radius: 50%; background: var(--accent); animation: dot 1.1s ease-in-out infinite; }
.typing .dots i:nth-child(2) { animation-delay: .18s; } .typing .dots i:nth-child(3) { animation-delay: .36s; }
@keyframes dot { 0%, 60%, 100% { transform: translateY(0); opacity: .4; } 30% { transform: translateY(-3px); opacity: 1; } }

/* 보고서 */
.filters { display: grid; gap: 8px; padding: 10px 12px; border-bottom: 1px solid var(--line); background: var(--panel); }
.daterow { display: flex; align-items: center; gap: 6px; }
.daterow input[type="date"] { font: inherit; font-size: 13px; padding: 4px 8px; border: 1px solid var(--line-2); border-radius: 8px; background: #fff; color: var(--fg); min-width: 0; flex: 1; }
.search { position: relative; }
.search input { width: 100%; font: inherit; font-size: 13px; padding: 7px 30px 7px 32px; border: 1px solid var(--line-2); border-radius: 8px; background: #fff; color: var(--fg); }
.search svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; stroke: var(--faint); fill: none; stroke-width: 2; }
.search .clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--faint); cursor: pointer; font-size: 14px; padding: 2px 6px; }
.chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip { font: inherit; font-size: 12px; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--line-2); background: #fff; color: var(--muted); cursor: pointer; }
.chip[aria-pressed="true"] { background: var(--accent-soft); color: var(--accent-ink); border-color: transparent; font-weight: 600; }
.rcount { font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; }
.rlist { list-style: none; margin: 0; padding: 10px; display: grid; gap: 8px; align-content: start; flex: 1 1 auto; min-height: 140px; overflow: auto; overscroll-behavior: contain; }
.rp { flex: none; }
.filters, .counts, .hd, .store { flex: none; }
.rp { border: 1px solid var(--line); border-radius: 10px; background: #fff; overflow: hidden; animation: pop .3s ease-out; }
.rp summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; padding: 9px 11px; }
.rp summary::-webkit-details-marker { display: none; }
.rp .kd { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--panel-2); color: var(--muted); white-space: nowrap; }
.rp.review .kd { background: var(--codex-soft); color: var(--codex-ink); }
.rp.deploy .kd { background: color-mix(in srgb, var(--good) 22%, #fff); color: var(--good-ink); }
.rp .tt { font-weight: 600; font-size: 13px; min-width: 0; overflow-wrap: anywhere; }
.rp .tm { font-size: 11.5px; color: var(--faint); font-family: var(--font-mono); }
.rp .bd { padding: 0 11px 11px; font-size: 12.5px; border-top: 1px dashed var(--line); }
.rp .bd p { margin: 8px 0 6px; color: var(--muted); font-size: 12px; }
mark { background: var(--mark); color: inherit; border-radius: 2px; padding: 0 1px; }
.empty { padding: 26px 14px; text-align: center; color: var(--muted); font-size: 12.5px; }
.store { font-size: 11px; color: var(--faint); padding: 8px 12px 10px; border-top: 1px solid var(--line); font-family: var(--font-mono); overflow-wrap: anywhere; }
.counts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; padding: 10px 12px 0; }
.counts div { background: var(--panel-2); border-radius: 8px; padding: 6px 8px; }
.counts .k { font-size: 10.5px; color: var(--muted); display: block; }
.counts .v { font-size: 17px; font-weight: 700; font-variant-numeric: tabular-nums; }

@media (prefers-reduced-motion: reduce) { .node.working .f1, .node.working .f2, .node.working .scr, .node.working .led, .step.working .ic, .typing .dots i, .lead.live::after, .msg, .rp { animation: none; } }
</style>
</head>
<body>

<div class="app">
  <header class="top">
    <h1>drgo-office 작업 현황 <small>Claude Code · Codex · Git · 시연용 샘플</small></h1>
    <div class="session"><span class="live-dot" aria-hidden="true"></span><span>세션 <b id="session-state">진행 중</b></span><span>·</span><span class="mono" id="session-elapsed">0분</span><span>·</span><span>마지막 이벤트 <span class="mono" id="last-event-age">없음</span></span></div>
    <div class="acts">
      <span class="badge" id="source-badge">시연용 샘플 데이터</span>
      <button class="btn" type="button" id="toggle-btn">일시정지</button>
      <button class="btn" type="button" id="present-btn" aria-pressed="false">발표 모드</button>
    </div>
  </header>

  <section class="panel scene">
    <svg id="scene" viewBox="0 0 320 134" role="img" aria-label="도트 그래픽 구조도: 사용자가 Claude Code에 지시하고, Claude Code는 위로 Codex와 교차 검토, 아래로 payapp MCP를 호출하며, 오른쪽으로 Git 커밋, 승인 시 GitHub push, Forge 배포로 이어진다"></svg>
    <div class="legend"><span class="w"><i></i>작업 중</span><span class="d"><i></i>방금 완료</span><span class="e"><i></i>오류</span><span class="i"><i></i>대기</span><span style="margin-left:auto">서류 이동: 지시 → Claude, 리뷰 ↔ Codex, 커밋 → Git, push → GitHub, 배포 → Forge</span></div>
  </section>

  <section class="main">
    <div class="panel">
      <div class="hd"><h2>작업 대화</h2><span class="n" id="msg-count">0건</span></div>
      <div class="chat" id="chat" aria-live="polite"></div>
      <div class="typing" id="typing"></div>
    </div>

    <aside class="panel" aria-label="보고서">
      <div class="hd"><h2>보고서</h2><span class="n" id="rcount-top"></span></div>
      <div class="counts" aria-label="오늘 통계">
        <div><span class="k">지시</span><span class="v" id="c-prompts">0</span></div>
        <div><span class="k">리뷰</span><span class="v" id="c-reviews">0</span></div>
        <div><span class="k">커밋</span><span class="v" id="c-commits">0</span></div>
        <div><span class="k">push</span><span class="v" id="c-pushes">0</span></div>
      </div>
      <div class="filters">
        <div class="daterow">
          <button class="btn" type="button" id="d-prev" aria-label="전날">‹</button>
          <input type="date" id="d-pick" aria-label="날짜 선택">
          <button class="btn" type="button" id="d-next" aria-label="다음날">›</button>
          <button class="btn" type="button" id="d-today">오늘</button>
        </div>
        <div class="search">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
          <input type="search" id="q" placeholder="보고서 검색: 제목, 내용, 지적 사항" autocomplete="off">
          <button class="clear" type="button" id="q-clear" aria-label="검색어 지우기" hidden>×</button>
        </div>
        <div class="chips" role="group" aria-label="종류">
          <button class="chip" type="button" data-kind="all" aria-pressed="true">전체</button>
          <button class="chip" type="button" data-kind="review" aria-pressed="false">리뷰</button>
          <button class="chip" type="button" data-kind="commit" aria-pressed="false">커밋</button>
          <button class="chip" type="button" data-kind="push" aria-pressed="false">push</button>
          <button class="chip" type="button" data-kind="deploy" aria-pressed="false">배포</button>
        </div>
        <div class="rcount"><span id="rcount"></span><span id="rscope"></span></div>
      </div>
      <ul class="rlist" id="rlist"></ul>
      <div class="empty" id="rempty" hidden></div>
      <div class="store" id="store"></div>
    </aside>
  </section>
</div>

<script>
(function () {
  'use strict';
  var MOCK = true, NS = 'http://www.w3.org/2000/svg';
  var $ = function (id) { return document.getElementById(id); };
  var NAME = { user: '사용자', claude: 'Claude Code', codex: 'Codex', payapp: 'payapp MCP', git: 'Git', github: 'GitHub', forge: 'Laravel Forge' };
  var INIT = { claude: 'C', codex: 'X', payapp: '₩', git: 'G', github: 'H', forge: 'F' };
  var KIND = { review: 'Codex 리뷰', commit: '커밋', push: 'push', deploy: '배포' };
  var css = function (v) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim(); };

  /* ───────── 도트 스프라이트 ───────── */
  var P = {
    O: css('--ol'), S: css('--skin'), E: css('--eye'), L: css('--leg'), C: css('--scr-off'), G: css('--gray-a'), K: css('--gray-b'),
    U: css('--cloud'), N: css('--paper'), M: css('--paper-l'), F: css('--folder'), R: css('--led-off'), V: css('--leaf'),
    Q: '#DD8A6C', Z: '#4A4B52', W: '#FFFFFF'
  };
  var SPR = {
    person1: ['...OOOOOO...', '..OHHHHHHO..', '.OHHHHHHHHO.', '.OHSSHHSSHO.', '.OSSSSSSSSO.', '.OSESSSSESO.', '.OSSSSSSSSO.', '..OSSSSSSO..', '...OOOOOO...', '..OBBBBBBO..', '.OSBBBBBBSO.', '.OSBBBBBBSO.', '..OBBBBBBO..', '...OLLOLL...', '...OLLOLL...', '...OOO.OOO..'],
    person2: ['...OOOOOO...', '..OHHHHHHO..', '.OHHHHHHHHO.', '.OHSSHHSSHO.', '.OSSSSSSSSO.', '.OSESSSSESO.', '.OSSSSSSSSO.', '..OSSSSSSO..', '..SOOOOOOS..', '.OSBBBBBBSO.', '..OBBBBBBO..', '..OBBBBBBO..', '..OBBBBBBO..', '...OLLOLL...', '...OLLOLL...', '...OOO.OOO..'],
    // Claude: 테라코타 캐릭터 + 별표 엠블럼
    claude1: ['....OOOOOOOO....', '..OOQQQQQQQQOO..', '.OQQQQQQQQQQQQO.', '.OQQQQQQQQQQQQO.', 'OQQQEEQQQQEEQQQO', 'OQQQEEQQQQEEQQQO', 'OQQQQQQQQQQQQQQO', 'OQQQQQEQQEQQQQQO', '.OQQQQQEEQQQQQO.', '.OQQQQQQQQQQQQO.', '..OOQQQQQQQQOO..', '....OOOOOOOO....', '...OQQO..OQQO...', '...OOOO..OOOO...'],
    claude2: ['....OOOOOOOO....', '..OOQQQQQQQQOO..', '.OQQQQQQQQQQQQO.', '.OQQQQQQQQQQQQO.', 'OQQQQQQQQQQQQQQO', 'OQQQEEQQQQEEQQQO', 'OQQQQQQQQQQQQQQO', 'OQQQQQEQQEQQQQQO', '.OQQQQQEEQQQQQO.', '.OQQQQQQQQQQQQO.', '..OOQQQQQQQQOO..', '....OOOOOOOO....', '..OQQO....OQQO..', '..OOOO....OOOO..'],
    star: ['..Q..', 'Q.Q.Q', '.QQQ.', 'Q.Q.Q', '..Q..'],
    // Codex: 차콜 캐릭터 + 육각 고리 엠블럼
    codex1: ['....OOOOOOOO....', '..OOZZZZZZZZOO..', '.OZZZZZZZZZZZZO.', '.OZZZWWZZZZWWZO.', 'OZZZZWWZZZZWWZZO', 'OZZZZZZZZZZZZZZO', 'OZZZZZZWWZZZZZZO', 'OZZZZZWZZWZZZZZO', '.OZZZZWZZWZZZZO.', '.OZZZZZWWZZZZZO.', '..OOZZZZZZZZOO..', '....OOOOOOOO....', '...OZZO..OZZO...', '...OOOO..OOOO...'],
    codex2: ['....OOOOOOOO....', '..OOZZZZZZZZOO..', '.OZZZZZZZZZZZZO.', '.OZZZZZZZZZZZZO.', 'OZZZZWWZZZZWWZZO', 'OZZZZZZZZZZZZZZO', 'OZZZZZZWWZZZZZZO', 'OZZZZZWZZWZZZZZO', '.OZZZZWZZWZZZZO.', '.OZZZZZWWZZZZZO.', '..OOZZZZZZZZOO..', '....OOOOOOOO....', '..OZZO....OZZO..', '..OOOO....OOOO..'],
    hex: ['..ZZZ..', '.Z...Z.', 'Z.....Z', 'Z.....Z', 'Z.....Z', '.Z...Z.', '..ZZZ..'],
    terminal: ['..OOOO..', '.OGGGGO.', 'OGCCCCGO', 'OGCCCCGO', 'OGCCCCGO', 'OGGGGGGO', 'OGKKKKGO', 'OGKKKKGO', 'OGGGGGGO', 'OGKKKKGO', 'OGGGGGGO', '.OGGGGO.', '.OGGGGO.', '.OOOOOO.'],
    branch: ['..OOO.........', '.OKKKO........', '.OKKKO........', '..OOO.........', '...K..........', '...K......OOO.', '...K.....OKKKO', '...K.....OKKKO', '...K..KKK.OOO.', '...KKK........', '...K..........', '..OOO.........', '.OKKKO........', '.OKKKO........', '..OOO.........'],
    cloud: ['......OOOO........', '.....OUUUUO.......', '...OOOUUUUOOO.....', '..OUUUUUKUUUUOO...', '.OUUUUUKKKUUUUUO..', '.OUUUUKKKKKUUUUO..', '.OUUUUUUKUUUUUUO..', '.OUUUUUUKUUUUUUO..', '..OOOOOOOOOOOOO...'],
    server: ['OOOOOOOOOOOO', 'OGGGGGGGGGGO', 'OGRGRGRGGGGO', 'OGKKKKKKKKGO', 'OGGGGGGGGGGO', 'OGRGRGRGGGGO', 'OGKKKKKKKKGO', 'OGGGGGGGGGGO', 'OGRGRGRGGGGO', 'OGKKKKKKKKGO', 'OGGGGGGGGGGO', 'OGRGRGRGGGGO', 'OGKKKKKKKKGO', 'OGGGGGGGGGGO', 'OGGGGGGGGGGO', 'OOOOOOOOOOOO'],
    doc: ['OOOOOO', 'ONNNNO', 'ONMMNO', 'ONNNNO', 'ONMMNO', 'ONNNNO', 'OOOOOO'],
    stamp: ['OOOOOO', 'ONNNNO', 'ONMMNO', 'ONNNNO', 'ONNVVO', 'ONNVVO', 'OOOOOO'],
    folder: ['OOOO....', 'OFFFOOOO', 'OFFFFFFO', 'OFFFFFFO', 'OFFFFFFO', 'OOOOOOOO'],
    plane: ['O.......', 'OOOO....', 'ONNNOOO.', 'OOOONNNO', '...OOOO.', '....O...']
  };
  var CLS = { O: 'ol', C: 'scr', R: 'led' };
  function spr(parent, name, x, y, pal, extraClass) {
    var rows = SPR[name], g = document.createElementNS(NS, 'g');
    g.setAttribute('transform', 'translate(' + x + ' ' + y + ')');
    if (extraClass) g.setAttribute('class', extraClass);
    rows.forEach(function (row, r) {
      var c = 0;
      while (c < row.length) {
        var ch = row[c]; if (ch === '.') { c++; continue; }
        var s = c; while (c < row.length && row[c] === ch) c++;
        var rect = document.createElementNS(NS, 'rect');
        rect.setAttribute('x', s); rect.setAttribute('y', r); rect.setAttribute('width', c - s); rect.setAttribute('height', 1);
        rect.setAttribute('fill', (pal && pal[ch]) || P[ch] || '#f0f');
        if (CLS[ch]) rect.setAttribute('class', CLS[ch]);
        g.appendChild(rect);
      }
    });
    parent.appendChild(g); return g;
  }
  function txt(parent, x, y, s, cls, anchor) {
    var t = document.createElementNS(NS, 'text'); t.setAttribute('x', x); t.setAttribute('y', y); t.setAttribute('class', cls);
    t.setAttribute('text-anchor', anchor || 'middle'); t.textContent = s; parent.appendChild(t); return t;
  }
  function rect(parent, x, y, w, h, fill, cls) {
    var r = document.createElementNS(NS, 'rect'); r.setAttribute('x', x); r.setAttribute('y', y); r.setAttribute('width', w); r.setAttribute('height', h);
    r.setAttribute('fill', fill); if (cls) r.setAttribute('class', cls); parent.appendChild(r); return r;
  }

  /* ───────── 장면 구성: 구조 반영 (Claude 허브, 3갈래) ───────── */
  // 열 간격 52 고정. 가운데 줄: 사용자 → Claude → Git → GitHub → Forge. Claude 위로 Codex(양방향), 아래로 payapp.
  var svg = $('scene');
  var TILE = 26, COL = 52, X0 = 30;
  var ROW = { top: 8, mid: 46, bot: 84 };
  rect(svg, 0, 0, 320, 134, css('--wall'));
  var POS = {
    user: { col: 0, row: 'mid' }, claude: { col: 1, row: 'mid' }, codex: { col: 2, row: 'top' }, payapp: { col: 2, row: 'bot' },
    git: { col: 3, row: 'mid' }, github: { col: 4, row: 'mid' }, forge: { col: 5, row: 'mid' }
  };
  var CX = {}, CYm = {}, ANCHOR = {};
  Object.keys(POS).forEach(function (k) { CX[k] = X0 + POS[k].col * COL; CYm[k] = ROW[POS[k].row] + TILE / 2; ANCHOR[k] = [CX[k], CYm[k]]; });
  var lineC = css('--line-2');
  function dots(pts) {   // 직각 점선
    for (var i = 0; i < pts.length - 1; i++) {
      var a = pts[i], b = pts[i + 1];
      if (a[1] === b[1]) { var s = Math.min(a[0], b[0]), e = Math.max(a[0], b[0]); for (var x = s; x <= e; x += 2) rect(svg, x, a[1], 1, 1, lineC); }
      else { var s2 = Math.min(a[1], b[1]), e2 = Math.max(a[1], b[1]); for (var y = s2; y <= e2; y += 2) rect(svg, a[0], y, 1, 1, lineC); }
    }
  }
  function arrow(x, y, dir) {   // 픽셀 화살촉
    if (dir === 'r') { rect(svg, x - 2, y - 1, 1, 3, lineC); rect(svg, x - 1, y, 1, 1, lineC); }
    if (dir === 'd') { rect(svg, x - 1, y - 2, 3, 1, lineC); rect(svg, x, y - 1, 1, 1, lineC); }
    if (dir === 'u') { rect(svg, x - 1, y + 1, 3, 1, lineC); rect(svg, x, y, 1, 1, lineC); }
  }
  var H = TILE / 2, cyMid = ROW.mid + H, cyTop = ROW.top + H, cyBot = ROW.bot + H;
  function hlink(a, b, label) {
    var x1 = CX[a] + H + 2, x2 = CX[b] - H - 2;
    dots([[x1, cyMid], [x2 - 2, cyMid]]); arrow(x2 + 1, cyMid, 'r');
    if (label) txt(svg, (x1 + x2) / 2, cyMid + 6, label, 'sub');
  }
  hlink('user', 'claude', '지시'); hlink('claude', 'git', '커밋'); hlink('git', 'github', 'push · 승인 시'); hlink('github', 'forge', '배포');
  // Claude ↕ Codex (양방향), Claude ↓ payapp : 직각 꺾임
  var cxC = CX.claude, xIn = CX.codex - H - 2;
  dots([[cxC, ROW.mid - 3], [cxC, cyTop], [xIn - 2, cyTop]]); arrow(xIn + 1, cyTop, 'r'); arrow(cxC, ROW.mid - 1, 'd');
  txt(svg, (cxC + xIn) / 2, cyTop - 3, '리뷰 ↔', 'sub');
  dots([[cxC, ROW.mid + TILE + 2], [cxC, cyBot], [xIn - 2, cyBot]]); arrow(xIn + 1, cyBot, 'r');
  txt(svg, (cxC + xIn) / 2, cyBot - 3, 'MCP 호출', 'sub');

  var nodes = {};
  function station(key, label, sub, build) {
    var cx = CX[key], ty = ROW[POS[key].row], g = document.createElementNS(NS, 'g'); g.setAttribute('class', 'node idle'); g.id = 'n-' + key; svg.appendChild(g);
    rect(g, cx - H, ty, TILE, TILE, '#FFFFFF', 'tile');
    rect(g, cx - H + 2, ty + TILE, TILE - 4, 1, css('--line'));
    var body = document.createElementNS(NS, 'g'); body.setAttribute('class', 'body'); g.appendChild(body);
    build(body, cx, ty + H);
    txt(g, cx, ty + TILE + 8, label, 'lbl'); txt(g, cx, ty + TILE + 13, sub, 'sub');
    var pill = document.createElementNS(NS, 'g'); pill.setAttribute('class', 'pill'); pill.innerHTML = '<rect x="0" y="0" width="0" height="6"></rect><text x="0" y="0" text-anchor="middle"></text>'; g.appendChild(pill);
    var bub = document.createElementNS(NS, 'g'); bub.setAttribute('class', 'bubble'); bub.innerHTML = '<rect class="bd"></rect><rect class="bg"></rect><rect class="bd t1"></rect><rect class="bd t2"></rect><text text-anchor="middle"></text>'; g.appendChild(bub);
    nodes[key] = { g: g, pill: pill, bub: bub, bx: cx, by: ty - 1, px: cx, py: ty + TILE + 15, status: 'idle', since: 0, label: '', timer: null, bt: null, step: null };
  }
  station('user', '사용자', 'cc-office', function (b, cx, cy) { spr(b, 'person1', cx - 6, cy - 8, { H: '#8E8E9A', B: '#BFC1CB' }, 'f1'); spr(b, 'person2', cx - 6, cy - 8, { H: '#8E8E9A', B: '#BFC1CB' }, 'f2'); });
  station('claude', 'Claude Code', '기획 · 구현', function (b, cx, cy) { spr(b, 'claude1', cx - 8, cy - 6, null, 'f1'); spr(b, 'claude2', cx - 8, cy - 6, null, 'f2'); spr(b, 'star', cx + 7, cy - 12, null, 'emblem'); });
  station('codex', 'Codex', '교차 검토', function (b, cx, cy) { spr(b, 'codex1', cx - 8, cy - 6, null, 'f1'); spr(b, 'codex2', cx - 8, cy - 6, null, 'f2'); spr(b, 'hex', cx + 6, cy - 13, null, 'emblem'); });
  station('payapp', 'payapp MCP', '결제 연동', function (b, cx, cy) { spr(b, 'terminal', cx - 4, cy - 7); });
  station('git', 'Git', '로컬 커밋', function (b, cx, cy) { spr(b, 'branch', cx - 7, cy - 7); });
  station('github', 'GitHub', '원격 저장소', function (b, cx, cy) { spr(b, 'cloud', cx - 9, cy - 4); });
  station('forge', 'Forge', 'office.drgo.pro', function (b, cx, cy) { spr(b, 'server', cx - 6, cy - 8); });
  var courierLayer = document.createElementNS(NS, 'g'); svg.appendChild(courierLayer);

  /* ───────── 상태 표현 ───────── */
  var state = { counts: { prompts: 0, reviews: 0, commits: 0, pushes: 0 }, lastId: 0, lastTs: 0, msgs: 0, live: null, sessionStart: Date.now() - 11 * 60000 };
  function setNode(key, status, label, autoMs) {
    var n = nodes[key]; if (n.timer) { clearTimeout(n.timer); n.timer = null; }
    n.status = status; n.label = label || ''; n.since = Date.now();
    n.g.classList.remove('idle', 'working', 'done', 'error'); n.g.classList.add(status);
    paintPill(key); paintTyping();
    if (autoMs) n.timer = setTimeout(function () { if (key === 'claude' && status !== 'done') setNode('claude', 'working', '응답 중'); else setNode(key, 'idle', ''); }, autoMs);
  }
  function paintPill(key) {
    var n = nodes[key]; if (n.status === 'idle') return;
    var s = n.status === 'working' ? n.label + ' · ' + Math.max(0, Math.round((Date.now() - n.since) / 1000)) + '초' : (n.status === 'done' ? '완료' : '오류');
    var t = n.pill.querySelector('text'), r = n.pill.querySelector('rect'); t.textContent = s;
    var w = Math.ceil((t.getComputedTextLength ? t.getComputedTextLength() : 20) + 4);
    r.setAttribute('x', n.px - w / 2); r.setAttribute('y', n.py); r.setAttribute('width', w); t.setAttribute('x', n.px); t.setAttribute('y', n.py + 4.4);
  }
  function say(key, text, isErr, ms) {
    var n = nodes[key]; if (!n.bub) return;
    if (n.bt) clearTimeout(n.bt);
    var t = n.bub.querySelector('text'); t.textContent = text.length > 24 ? text.slice(0, 23) + '…' : text;
    var w = Math.ceil((t.getComputedTextLength ? t.getComputedTextLength() : 30) + 6), h = 8;
    var x = Math.max(2, Math.min(318 - w, n.bx - w / 2)), y = Math.max(0, n.by - h - 3);
    var rs = n.bub.querySelectorAll('rect');
    rs[0].setAttribute('x', x); rs[0].setAttribute('y', y); rs[0].setAttribute('width', w); rs[0].setAttribute('height', h);
    rs[1].setAttribute('x', x + 1); rs[1].setAttribute('y', y + 1); rs[1].setAttribute('width', w - 2); rs[1].setAttribute('height', h - 2);
    rs[2].setAttribute('x', n.bx - 1); rs[2].setAttribute('y', y + h); rs[2].setAttribute('width', 2); rs[2].setAttribute('height', 1);
    rs[3].setAttribute('x', n.bx - 0.5); rs[3].setAttribute('y', y + h + 1); rs[3].setAttribute('width', 1); rs[3].setAttribute('height', 1);
    t.setAttribute('x', x + w / 2); t.setAttribute('y', y + 5.6);
    n.bub.classList.toggle('err', !!isErr); n.bub.classList.add('show');
    n.bt = setTimeout(function () { n.bub.classList.remove('show'); }, ms || 3500);
  }
  function travel(from, to, kind, ms) {
    ms = ms || 900;
    var g = document.createElementNS(NS, 'g'); g.setAttribute('class', 'courier'); courierLayer.appendChild(g);
    spr(g, kind || 'doc', -3, -4);
    g.style.transform = 'translate(' + ANCHOR[from][0] + 'px,' + ANCHOR[from][1] + 'px)';
    g.style.transition = 'transform ' + ms + 'ms steps(' + Math.round(ms / 60) + '), opacity .2s';
    requestAnimationFrame(function () { requestAnimationFrame(function () { g.style.transform = 'translate(' + ANCHOR[to][0] + 'px,' + ANCHOR[to][1] + 'px)'; }); });
    setTimeout(function () { g.style.opacity = '0'; setTimeout(function () { g.remove(); }, 220); }, ms);
  }
  function paintTyping() {
    var t = $('typing'), who = Object.keys(nodes).filter(function (k) { return nodes[k].status === 'working' && k !== 'user'; });
    if (!who.length) { t.innerHTML = ''; return; }
    t.innerHTML = '<span class="dots"><i></i><i></i><i></i></span><span></span>';
    t.lastChild.textContent = who.map(function (k) { return NAME[k]; }).join(', ') + (who.length === 1 && who[0] !== 'claude' ? ' 작업 중' : ' 입력 중');
  }

  /* ───────── 대화 ───────── */
  var chat = $('chat');
  function fmt(ts) { var d = ts ? new Date(ts) : new Date(); return [d.getHours(), d.getMinutes()].map(function (v) { return String(v).padStart(2, '0'); }).join(':'); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function scrollDown() { if (chat.scrollHeight - chat.scrollTop - chat.clientHeight < 160) chat.scrollTop = chat.scrollHeight; }
  function sys(text) { var d = document.createElement('div'); d.className = 'sys'; d.textContent = text; chat.appendChild(d); scrollDown(); }
  function msg(node, html, ts) {
    var m = document.createElement('div'), cls = node === 'user' ? 'me' : node === 'claude' ? 'claude' : node === 'codex' ? 'codex' : 'bot';
    m.className = 'msg ' + cls;
    m.innerHTML = (node === 'user' ? '' : '<span class="av ' + node + '">' + INIT[node] + '</span>') + '<div><div class="meta"><span class="nm"></span><time>' + fmt(ts) + '</time></div><div class="bub">' + html + '</div></div>';
    m.querySelector('.nm').textContent = NAME[node]; chat.appendChild(m); scrollDown();
    while (chat.children.length > 150) chat.removeChild(chat.firstChild);
    state.msgs++; $('msg-count').textContent = state.msgs + '건'; return m;
  }
  function maskSecrets(t) { return t.replace(/\b(sk|pk|ghp|gho|xox[abp]|AKIA)[-_A-Za-z0-9]{12,}\b/g, '••••(가림)').replace(/\b[A-Fa-f0-9]{32,}\b/g, '••••(가림)'); }
  function readablePrompt(raw) {
    var t = maskSecrets(raw), chips = [];
    t = t.replace(/```[\s\S]*?```/g, function (m) { chips.push('코드 ' + Math.max(1, m.split('\n').length - 2) + '줄'); return '\u0000' + (chips.length - 1) + '\u0000'; });
    t = t.replace(/^#{1,6}\s+/gm, '').replace(/\*\*(.+?)\*\*/g, '$1').replace(/`([^`]+)`/g, '$1').replace(/^[-*]\s+/gm, '• ');
    t = esc(t).replace(/\n{3,}/g, '\n\n').trim();
    t = t.replace(/((?:~?\/|[A-Za-z0-9_.-]+\/)[A-Za-z0-9_./-]*\/)?([A-Za-z0-9_.-]+\.(?:php|js|ts|py|blade\.php|json|md|css|html|vue|sh))\b/g, function (m, d, f) { return '<span class="file" title="' + m + '">' + f + '</span>'; });
    t = t.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<span class="pchip">' + chips[+i] + '</span>'; });
    return { html: t, raw: maskSecrets(raw), long: raw.length > 110 || raw.split('\n').length > 3 };
  }
  function promptBubble(ev) {
    if (!ev.prompt) return esc(ev.summary);
    var p = readablePrompt(ev.prompt), id = 'p' + state.lastId;
    return '<div class="prompt' + (p.long ? ' clamp' : '') + '" id="' + id + '">' + p.html + '</div><div class="pfoot"><span>' + ev.prompt.length + '자 · 원문 보관됨</span>' +
      (p.long ? '<button type="button" data-more="' + id + '">더 보기</button>' : '') + '<button type="button" data-raw="' + id + '">원문 보기</button></div><pre class="raw" id="' + id + '-raw">' + esc(p.raw) + '</pre>';
  }
  chat.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    if (b.dataset.more) { var c = $(b.dataset.more).classList.toggle('clamp'); b.textContent = c ? '더 보기' : '접기'; }
    if (b.dataset.raw) { var o = $(b.dataset.raw + '-raw').classList.toggle('open'); b.textContent = o ? '원문 닫기' : '원문 보기'; }
  });
  function liveBubble(ts) {
    if (state.live) return state.live;
    var m = msg('claude', '<div class="lead live">작업 진행 중</div><ul class="steps"></ul>', ts);
    state.live = { steps: m.querySelector('.steps'), lead: m.querySelector('.lead') }; return state.live;
  }
  function addStep(node, text, ts) {
    var lb = liveBubble(ts), li = document.createElement('li'); li.className = 'step working';
    li.innerHTML = '<span class="ic"></span><span class="txt"></span><span class="dur"></span>'; li.querySelector('.txt').textContent = text; li.dataset.t0 = Date.now();
    lb.steps.appendChild(li); nodes[node].step = li; scrollDown();
  }
  function endStep(node, ok, text) {
    var li = nodes[node].step; if (!li) return;
    li.classList.remove('working'); li.classList.add(ok ? 'done' : 'error'); if (text) li.querySelector('.txt').textContent = text;
    li.querySelector('.dur').textContent = ((Date.now() - li.dataset.t0) / 1000).toFixed(1) + '초'; nodes[node].step = null;
  }
  function finishLive(s) { if (!state.live) return; state.live.lead.classList.remove('live'); state.live.lead.textContent = s || '작업 완료'; state.live.steps.querySelectorAll('.step.working').forEach(function (li) { li.classList.remove('working'); li.classList.add('done'); }); state.live = null; }
  function itemsHTML(items, hl) { return (items || []).map(function (it) { return '<li><span class="tag ' + (it.ok ? 'ok' : 'hold') + '">' + (it.ok ? '반영' : '보류') + '</span><span>' + (hl ? hl(it.t) : esc(it.t)) + '</span></li>'; }).join(''); }
  function cardHTML(r) { return '<div class="card"><span class="kind">' + KIND[r.type] + '</span><span class="ct">' + esc(r.title) + '</span>' + (r.note ? '<span class="cm">' + esc(r.note) + '</span>' : '') + (r.items && r.items.length ? '<ul>' + itemsHTML(r.items) + '</ul>' : '') + '</div>'; }
  function renderCounts() { var c = state.counts; ['prompts', 'reviews', 'commits', 'pushes'].forEach(function (k) { $('c-' + k).textContent = c[k]; }); }

  /* ───────── 보고서: 일자별 저장 + 검색 ───────── */
  function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  var today = ymd(new Date()), dayMs = 86400000;
  var d1 = ymd(new Date(Date.now() - dayMs)), d2 = ymd(new Date(Date.now() - 2 * dayMs));
  var REPORTS = {};   // date → [report]
  REPORTS[d1] = [
    { time: '10:42', type: 'review', title: '재고 조정 화면 변경분 리뷰', note: '지적 2건 · 반영 2건 · 6.1초', items: [{ ok: true, t: '수량 음수 입력 시 검증 누락 → FormRequest 규칙 추가' }, { ok: true, t: '조정 사유 미입력 저장 가능 → 필수 처리' }] },
    { time: '10:55', type: 'commit', title: 'feat: 재고 조정 사유 입력과 검증', note: '파일 6개 변경 · 리뷰 반영 후 커밋' },
    { time: '11:20', type: 'push', title: 'origin/main 에 1개 커밋 push', note: '사용자 승인 후 실행' },
    { time: '11:21', type: 'deploy', title: 'office.drgo.pro 배포 완료', note: 'Quick Deploy · 4.9초' }
  ];
  REPORTS[d2] = [
    { time: '15:03', type: 'review', title: '직원 근태 집계 오류 수정 리뷰', note: '지적 4건 · 반영 3건 · 보류 1건 · 9.4초', items: [{ ok: true, t: '월말 경계일 계산이 시간대를 무시함 → KST 기준으로 수정' }, { ok: true, t: '휴일 테이블 캐시 무효화 누락 → 저장 시 캐시 삭제' }, { ok: true, t: '테스트가 현재 날짜에 의존 → 고정 날짜로 변경' }, { ok: false, t: '집계 쿼리를 뷰로 분리 제안 → 이번 범위 밖, 다음에 검토' }] },
    { time: '15:30', type: 'commit', title: 'fix: 근태 집계 월말 경계 계산', note: '파일 3개 변경' },
    { time: '15:34', type: 'commit', title: 'chore: 근태 집계 테스트 보강', note: '파일 2개 변경' }
  ];
  REPORTS[today] = [];
  var filter = { date: today, kind: 'all', q: '' };
  var pick = $('d-pick'); pick.value = today; pick.max = today;
  function shiftDay(n) { var d = new Date(filter.date + 'T00:00:00'); d.setDate(d.getDate() + n); var v = ymd(d); if (v > today) return; filter.date = v; pick.value = v; renderReports(); }
  $('d-prev').addEventListener('click', function () { shiftDay(-1); });
  $('d-next').addEventListener('click', function () { shiftDay(1); });
  $('d-today').addEventListener('click', function () { filter.date = today; pick.value = today; renderReports(); });
  pick.addEventListener('change', function () { if (pick.value) { filter.date = pick.value; renderReports(); } });
  $('q').addEventListener('input', function () { filter.q = this.value.trim(); $('q-clear').hidden = !filter.q; renderReports(); });
  $('q-clear').addEventListener('click', function () { $('q').value = ''; filter.q = ''; this.hidden = true; renderReports(); $('q').focus(); });
  document.querySelectorAll('.chip').forEach(function (c) { c.addEventListener('click', function () {
    document.querySelectorAll('.chip').forEach(function (x) { x.setAttribute('aria-pressed', 'false'); }); c.setAttribute('aria-pressed', 'true'); filter.kind = c.dataset.kind; renderReports();
  }); });
  function hl(s) {
    var e = esc(s); if (!filter.q) return e;
    var terms = filter.q.split(/\s+/).filter(Boolean).map(function (t) { return esc(t).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
    return e.replace(new RegExp('(' + terms.join('|') + ')', 'gi'), '<mark>$1</mark>');
  }
  function matches(r) {
    if (filter.kind !== 'all' && r.type !== filter.kind) return false;
    if (!filter.q) return true;
    var hay = (r.title + ' ' + (r.note || '') + ' ' + (r.items || []).map(function (i) { return i.t; }).join(' ') + ' ' + KIND[r.type]).toLowerCase();
    return filter.q.toLowerCase().split(/\s+/).filter(Boolean).every(function (t) { return hay.indexOf(t) >= 0; });
  }
  function renderReports() {
    var all = REPORTS[filter.date] || [], list = all.filter(matches), ul = $('rlist'); ul.innerHTML = '';
    list.slice().reverse().forEach(function (r) {
      var li = document.createElement('li'); li.className = 'rp ' + r.type;
      li.innerHTML = '<details' + (filter.q ? ' open' : '') + '><summary><span class="kd">' + KIND[r.type] + '</span><span class="tt">' + hl(r.title) + '</span><span class="tm">' + r.time + '</span></summary><div class="bd">' + (r.note ? '<p>' + hl(r.note) + '</p>' : '') + (r.items && r.items.length ? '<ul>' + itemsHTML(r.items, hl) + '</ul>' : '') + '</div></details>';
      ul.appendChild(li);
    });
    var lab = filter.date === today ? '오늘' : filter.date === d1 ? '어제' : filter.date;
    $('rcount').textContent = lab + ' · ' + list.length + '건' + (filter.q || filter.kind !== 'all' ? ' (전체 ' + all.length + '건 중)' : '');
    $('rscope').textContent = filter.q ? '검색: ' + filter.q : '';
    $('rcount-top').textContent = Object.keys(REPORTS).reduce(function (a, k) { return a + REPORTS[k].length; }, 0) + '건 보관';
    var em = $('rempty'); em.hidden = list.length > 0;
    em.textContent = all.length === 0 ? (filter.date === today ? '오늘 보고서가 아직 없습니다. 리뷰, 커밋, push, 배포가 끝나면 쌓입니다.' : filter.date + ' 에는 기록이 없습니다.') : '조건에 맞는 보고서가 없습니다.';
    $('store').textContent = '저장 위치 ~/.claude/agent-reports/' + filter.date + '.jsonl';
  }
  function addReport(ev) { var r = Object.assign({ time: fmt(ev.ts) }, ev.report); REPORTS[today].push(r); if (filter.date === today) renderReports(); else $('rcount-top').textContent = Object.keys(REPORTS).reduce(function (a, k) { return a + REPORTS[k].length; }, 0) + '건 보관'; }
  renderReports();

  /* ───────── 이벤트 적용 ───────── */
  function applyEvent(ev) {
    state.lastId = ev.id || state.lastId + 1; state.lastTs = Date.now();
    var node = ev.node || 'claude';
    switch (ev.event) {
      case 'SessionStart': sys('세션 시작 · drgo-office · ' + fmt(ev.ts)); break;
      case 'UserPromptSubmit':
        state.counts.prompts++; finishLive('작업 완료'); msg('user', promptBubble(ev), ev.ts);
        say('user', ev.prompt ? ev.prompt.split('\n')[0] : ev.summary); travel('user', 'claude', 'doc', 1100);
        setNode('user', 'done', '', 1500); setNode('claude', 'working', '응답 중'); break;
      case 'PreToolUse':
        if (node === 'claude') { addStep('claude', ev.summary, ev.ts); setNode('claude', 'working', ev.summary.split(':')[0]); say('claude', ev.summary); }
        else {
          var ask = { codex: 'Codex에게 리뷰 요청', payapp: 'payapp MCP 조회', git: '커밋', github: 'GitHub로 push (사용자 승인)', forge: 'Forge 배포' }[node] || ev.summary;
          addStep(node, ask, ev.ts); setNode(node, 'working', ev.label || '작업 중'); setNode('claude', 'working', NAME[node] + ' 대기');
          if (node === 'codex') { say('claude', '리뷰 부탁해요'); travel('claude', 'codex', 'doc'); setTimeout(function () { say('codex', '리뷰 중…', false, 6000); }, 900); }
          else if (node === 'payapp') travel('claude', 'payapp', 'doc');
          else if (node === 'git') travel('claude', 'git', 'folder');
          else if (node === 'github') travel('git', 'github', 'plane', 1300);
          else if (node === 'forge') travel('github', 'forge', 'folder', 1100);
        }
        break;
      case 'PostToolUse':
        endStep(node, true);
        if (node === 'codex') { state.counts.reviews++; say('codex', '리뷰 끝났어요'); travel('codex', 'claude', 'stamp'); }
        if (node === 'git') state.counts.commits++;
        if (node === 'github') state.counts.pushes++;
        if (node === 'payapp') travel('payapp', 'claude', 'doc');
        if (node !== 'claude') { msg(node, ev.report ? cardHTML(ev.report) : esc(ev.summary), ev.ts); setNode(node, 'done', '', 3000); }
        setNode('claude', 'working', '응답 중'); break;
      case 'PostToolUseFailure':
        endStep(node, false, ev.summary); setNode(node, 'error', '', 3000); say(node, ev.summary, true);
        setNode('claude', 'working', node === 'claude' ? '오류 수정 중' : '응답 중'); break;
      case 'Stop': finishLive('작업 완료'); msg('claude', esc(ev.summary), ev.ts); say('claude', ev.summary); setNode('claude', 'done', '', 3000); break;
    }
    if (ev.report) addReport(ev);
    renderCounts();
  }

  $('present-btn').addEventListener('click', function () { var on = document.body.classList.toggle('present'); this.setAttribute('aria-pressed', on); this.classList.toggle('on', on); });
  setInterval(function () {
    $('session-elapsed').textContent = Math.floor((Date.now() - state.sessionStart) / 60000) + '분';
    var age = state.lastTs ? Math.round((Date.now() - state.lastTs) / 1000) : null;
    $('last-event-age').textContent = age === null ? '없음' : (age < 3 ? '방금' : age + '초 전');
    Object.keys(nodes).forEach(function (k) { if (nodes[k].status === 'working') paintPill(k); });
  }, 1000);

  /* ───────── 샘플 시나리오 ───────── */
  var SCENARIO = [
    [0.0, { event: 'SessionStart', node: 'user', summary: '세션 시작' }],
    [1.2, { event: 'UserPromptSubmit', node: 'user', summary: '지시 전달 (186자)', prompt: '인보이스 상세 화면에서 금액이 0.5원 단위일 때 반올림이 이상하게 나와요.\n`app/Http/Controllers/InvoiceController.php`랑 resources/views/invoice-show.blade.php 확인해서 고쳐주고 테스트도 추가해줘.\n\n재현 데이터:\n```php\n$invoice->amount = 1234.5;\n$invoice->vat = 123.45;\n```\n\npayapp 결제 상태도 같이 확인해줘. **커밋 전에 codex 리뷰** 받고.' }],
    [2.8, { event: 'PreToolUse', node: 'claude', summary: '탐색: InvoiceController.php' }],
    [3.8, { event: 'PostToolUse', node: 'claude', summary: '탐색 완료' }],
    [4.4, { event: 'PreToolUse', node: 'payapp', label: '조회 중', summary: 'payapp MCP: get_payment_status' }],
    [6.4, { event: 'PostToolUse', node: 'payapp', summary: 'get_payment_status 응답 · 1.8초 (입력값은 기록하지 않음)' }],
    [7.4, { event: 'PreToolUse', node: 'claude', summary: '파일 수정: InvoiceController.php' }],
    [8.6, { event: 'PostToolUse', node: 'claude', summary: '파일 수정 완료' }],
    [9.0, { event: 'PreToolUse', node: 'claude', summary: '파일 수정: invoice-show.blade.php' }],
    [10.0, { event: 'PostToolUse', node: 'claude', summary: '파일 수정 완료' }],
    [10.6, { event: 'PreToolUse', node: 'claude', summary: '명령 실행: 테스트 실행' }],
    [13.2, { event: 'PostToolUseFailure', node: 'claude', summary: '테스트 실패 (exit 1)' }],
    [14.4, { event: 'PreToolUse', node: 'claude', summary: '파일 수정: InvoiceTest.php' }],
    [15.4, { event: 'PostToolUse', node: 'claude', summary: '파일 수정 완료' }],
    [15.8, { event: 'PreToolUse', node: 'claude', summary: '명령 실행: 테스트 실행' }],
    [18.0, { event: 'PostToolUse', node: 'claude', summary: '테스트 통과' }],
    [19.0, { event: 'PreToolUse', node: 'codex', label: '리뷰 중', summary: 'codex 리뷰 시작' }],
    [27.5, { event: 'PostToolUse', node: 'codex', summary: 'codex 리뷰 완료 · 지적 3건', report: { type: 'review', title: '인보이스 금액 보정 변경분 리뷰', note: '지적 3건 · 반영 2건 · 보류 1건 · 8.5초', items: [{ ok: true, t: '금액이 null일 때 반올림 함수가 예외를 던짐 → 기본값 처리' }, { ok: true, t: '0.5원 단위 반올림 엣지케이스 테스트 누락 → 테스트 추가' }, { ok: false, t: '로그 메시지 영문화 제안 → 사내 규칙상 한글 유지' }] } }],
    [28.6, { event: 'PreToolUse', node: 'claude', summary: '리뷰 반영: InvoiceController.php' }],
    [29.6, { event: 'PostToolUse', node: 'claude', summary: '파일 수정 완료' }],
    [30.6, { event: 'PreToolUse', node: 'git', label: '커밋 중', summary: 'git 커밋 시작' }],
    [31.8, { event: 'PostToolUse', node: 'git', summary: 'git 커밋 완료', report: { type: 'commit', title: 'fix: 인보이스 금액 반올림 보정', note: '파일 4개 변경 · 리뷰 반영 후 커밋 · push 대기' } }],
    [32.8, { event: 'Stop', node: 'claude', summary: '리뷰 반영하고 커밋까지 마쳤습니다. push는 확인 후 말씀해 주세요.' }],
    [38.0, { event: 'UserPromptSubmit', node: 'user', summary: '지시 전달 (13자)', prompt: '확인했어요. push 해줘' }],
    [39.0, { event: 'PreToolUse', node: 'github', label: 'push 중', summary: 'git push 시작' }],
    [41.6, { event: 'PostToolUse', node: 'github', summary: 'git push 완료', report: { type: 'push', title: 'origin/main 에 1개 커밋 push', note: '사용자 승인 후 실행 · Forge Quick Deploy 대기' } }],
    [42.4, { event: 'Stop', node: 'claude', summary: 'push 완료했습니다. Forge 배포가 이어집니다.' }],
    [43.6, { event: 'PreToolUse', node: 'forge', label: '배포 중', summary: 'Forge 배포 시작' }],
    [49.0, { event: 'PostToolUse', node: 'forge', summary: 'Forge 배포 완료', report: { type: 'deploy', title: 'office.drgo.pro 배포 완료', note: 'Quick Deploy · 5.4초 · 운영 반영됨' } }]
  ];
  var playing = true, idx = 0, loopTimer = null, nextAt = 0;
  // 반복 재생 시 시연 상태 초기화 — 대화·보고서·집계가 무한히 쌓이지 않도록
  function resetDemo() {
    chat.innerHTML = ''; state.msgs = 0; $('msg-count').textContent = '0건'; state.live = null;
    Object.keys(nodes).forEach(function (k) { nodes[k].step = null; if (nodes[k].status !== 'idle') setNode(k, 'idle', ''); });
    state.counts = { prompts: 0, reviews: 0, commits: 0, pushes: 0 }; renderCounts();
    REPORTS[today] = []; renderReports();
    sys('시연 반복 재생 · 화면을 비우고 처음부터 다시 보여줍니다');
  }
  function schedule() {
    if (!playing) return;
    if (idx >= SCENARIO.length) { idx = 1; nextAt = Date.now() + 7000; resetDemo(); }
    var delay = idx === 0 ? 500 : Math.max(0, nextAt - Date.now());
    loopTimer = setTimeout(function () {
      var item = SCENARIO[idx], ev = Object.assign({ id: state.lastId + 1 }, item[1]); applyEvent(ev);
      var next = SCENARIO[idx + 1]; nextAt = Date.now() + (next ? (next[0] - item[0]) * 1000 : 7000); idx++; schedule();
    }, delay);
  }
  $('toggle-btn').addEventListener('click', function () {
    playing = !playing; this.textContent = playing ? '일시정지' : '재생'; $('source-badge').textContent = playing ? '시연용 샘플 데이터' : '시연용 샘플 · 일시정지';
    if (playing) { nextAt = Date.now() + 600; schedule(); } else clearTimeout(loopTimer);
  });
  function pollLive() { fetch('/api/events?since=' + state.lastId).then(function (r) { return r.json(); }).then(function (d) { (d.events || []).forEach(applyEvent); }).catch(function () {}); }
  if (MOCK) { nextAt = Date.now(); schedule(); } else { pollLive(); setInterval(pollLive, 2000); }
})();
</script>

</body>
</html>
@endverbatim
