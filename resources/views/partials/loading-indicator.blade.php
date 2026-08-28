{{--
    전역 로딩 인디케이터 — 같은 도메인 fetch가 진행 중일 때 화면 상단 중앙에
    iOS 스타일 스피너를 띄운다 (모든 페이지 공통).

    - 300ms 이상 걸리는 요청만 표시 (짧은 요청은 깜빡임 방지)
    - 사용자 조작(터치/클릭/키/휠) 후 3초 안에 시작된 요청과 페이지 최초 로딩만 집계 —
      알림 폴링·자동 저장 같은 백그라운드 요청에는 뜨지 않는다
    - pointer-events 없음: 화면 조작을 막지 않는 순수 표시용
--}}
<style>
#globalLoading { position:fixed; top:14px; left:50%; transform:translateX(-50%); z-index:100000; pointer-events:none;
    opacity:0; transition:opacity .18s ease; width:34px; height:34px; border-radius:50%;
    background:rgba(24,24,24,0.82); backdrop-filter:blur(2px); box-shadow:0 2px 10px rgba(0,0,0,0.28);
    display:flex; align-items:center; justify-content:center; }
#globalLoading.on { opacity:1; }
.gl-spinner { position:relative; width:18px; height:18px; }
.gl-spinner span { position:absolute; top:0; left:50%; width:2px; height:5.5px; margin-left:-1px; border-radius:1px;
    background:#f2ede4; transform-origin:1px 9px; animation:glFade .8s linear infinite; }
.gl-spinner span:nth-child(1) { transform:rotate(0deg);   animation-delay:-.8s; }
.gl-spinner span:nth-child(2) { transform:rotate(45deg);  animation-delay:-.7s; }
.gl-spinner span:nth-child(3) { transform:rotate(90deg);  animation-delay:-.6s; }
.gl-spinner span:nth-child(4) { transform:rotate(135deg); animation-delay:-.5s; }
.gl-spinner span:nth-child(5) { transform:rotate(180deg); animation-delay:-.4s; }
.gl-spinner span:nth-child(6) { transform:rotate(225deg); animation-delay:-.3s; }
.gl-spinner span:nth-child(7) { transform:rotate(270deg); animation-delay:-.2s; }
.gl-spinner span:nth-child(8) { transform:rotate(315deg); animation-delay:-.1s; }
@keyframes glFade { 0% { opacity:1; } 100% { opacity:.15; } }
</style>
<script>
(function () {
    let el = null, active = 0, timer = null;
    let lastInteract = Date.now(); // 페이지 최초 로딩의 데이터 조회도 표시 대상
    ['pointerdown', 'touchstart', 'keydown', 'wheel'].forEach(function (ev) {
        addEventListener(ev, function () { lastInteract = Date.now(); }, { capture: true, passive: true });
    });
    function ensure() {
        if (el) return el;
        el = document.createElement('div');
        el.id = 'globalLoading';
        el.innerHTML = '<div class="gl-spinner">' + '<span></span>'.repeat(8) + '</div>';
        document.body.appendChild(el);
        return el;
    }
    function begin() {
        active++;
        if (active === 1 && !timer) {
            timer = setTimeout(function () { timer = null; if (active > 0) ensure().classList.add('on'); }, 300);
        }
    }
    function end() {
        active = Math.max(0, active - 1);
        if (active === 0) {
            if (timer) { clearTimeout(timer); timer = null; }
            if (el) el.classList.remove('on');
        }
    }
    const orig = window.fetch;
    window.fetch = function (input, init) {
        let track = false;
        try {
            const url = typeof input === 'string' ? input
                : (input instanceof URL ? input.href : (input && input.url) || '');
            const sameOrigin = url.startsWith('/') ? !url.startsWith('//') : url.startsWith(location.origin);
            track = sameOrigin && (Date.now() - lastInteract < 3000);
        } catch (e) { /* 판별 실패 시 표시만 생략 */ }
        const p = orig.call(this, input, init);
        if (track) { begin(); p.then(end, end); }
        return p;
    };
})();
</script>
