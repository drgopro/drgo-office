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
/* 파일 다운로드 준비 알림 — 스피너 원 위를 덮는 알약형 (drgoDownload 사용 시) */
#globalDlNote { position:fixed; top:14px; left:50%; transform:translateX(-50%); z-index:100001; pointer-events:none;
    opacity:0; transition:opacity .18s ease; display:flex; align-items:center; gap:9px;
    background:rgba(24,24,24,0.86); backdrop-filter:blur(2px); box-shadow:0 2px 10px rgba(0,0,0,0.28);
    color:#f2ede4; font-size:12.5px; font-weight:600; padding:8px 16px; border-radius:20px; white-space:nowrap; }
#globalDlNote.on { opacity:1; }
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
    // 파일 다운로드 공용 헬퍼 — 준비 중 알림을 띄우고 fetch→blob으로 받아 저장.
    // 링크 내비게이션 방식은 다운로드 시작을 감지할 수 없어 안내가 불가능하므로 이 헬퍼를 쓴다.
    window.drgoDownload = async function (url, link) {
        if (link && link.dataset.dl) return; // 준비 중 재클릭 방지
        if (link) link.dataset.dl = '1';
        let note = document.getElementById('globalDlNote');
        if (!note) {
            note = document.createElement('div');
            note.id = 'globalDlNote';
            note.innerHTML = '<div class="gl-spinner">' + '<span></span>'.repeat(8) + '</div><span>파일 다운로드 준비 중...</span>';
            document.body.appendChild(note);
        }
        note.classList.add('on');
        try {
            const res = await orig.call(window, url);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const cd = res.headers.get('Content-Disposition') || '';
            const m1 = cd.match(/filename\*=UTF-8''([^;]+)/i);
            const m2 = cd.match(/filename="?([^";]+)"?/i);
            const name = m1 ? decodeURIComponent(m1[1]) : (m2 ? m2[1] : 'download.xlsx');
            const blob = await res.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = name;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
        } catch (e) {
            alert('파일 다운로드에 실패했습니다. 잠시 후 다시 시도해주세요.');
        } finally {
            note.classList.remove('on');
            if (link) delete link.dataset.dl;
        }
    };
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
