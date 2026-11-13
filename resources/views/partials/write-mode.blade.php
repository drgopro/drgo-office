{{-- 작성 모드 공용 — 의뢰자·프로젝트·캘린더·견적서를 독립된 새 창/새 탭으로 여는 오프너 + 창 간 변경 알림.
     app / tab-content / standalone / 견적서 빌더 레이아웃에서 공유한다. --}}
<style>
.wm-split { display:inline-flex; position:relative; vertical-align:middle; }
.wm-split > button:first-child { border-top-right-radius:0 !important; border-bottom-right-radius:0 !important; }
.wm-split > button + button { border-top-left-radius:0 !important; border-bottom-left-radius:0 !important; border-left:none !important; padding-left:7px !important; padding-right:7px !important; }
.wm-accent { color:var(--accent, #3b5ea0) !important; border:1px solid var(--accent, #3b5ea0) !important; background:color-mix(in srgb, var(--accent, #3b5ea0) 8%, transparent) !important; font-weight:700 !important; }
.wm-accent:hover { background:var(--accent, #3b5ea0) !important; color:#fff !important; }
.wm-menu { position:absolute; right:0; top:calc(100% + 4px); background:var(--surface, #fff); border:1px solid var(--border, #d0d5dd); border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.15); padding:4px; z-index:9500; min-width:150px; }
.wm-menu button { display:block; width:100%; text-align:left; background:none; border:none; padding:8px 10px; font-size:14px; border-radius:6px; cursor:pointer; color:var(--text, #1a1e28); white-space:nowrap; }
.wm-menu button:hover { background:var(--surface2, #eceef2); }
.wm-menu button.on::after { content:' ✓'; color:var(--accent, #3b5ea0); font-weight:700; }
/* 작성 모드 검색 — 기존 의뢰자·프로젝트를 찾아 열기/수정 */
.wm-s { padding:4px 2px; }
.wm-s-in { width:100%; box-sizing:border-box; height:36px; border:1px solid var(--border, #d0d5dd); border-radius:8px; padding:0 10px; font-size:14px; background:var(--surface, #fff); color:var(--text, #1a1e28); outline:none; }
.wm-s-in:focus { border-color:#1f2b40; }
.wm-s-res { max-height:320px; overflow-y:auto; margin-top:6px; }
.wm-s-row { display:flex; align-items:center; gap:8px; padding:7px 6px; border-radius:8px; }
.wm-s-row:hover { background:var(--surface2, #eceef2); }
.wm-s-row .k { flex-shrink:0; font-size:11px; font-weight:700; color:#5f6b85; width:42px; }
.wm-s-row .t { flex:1; min-width:0; }
.wm-s-row .t b { display:block; font-size:14px; color:var(--text, #1a1e28); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.wm-s-row .t span { display:block; font-size:12px; color:var(--text-muted, #5a6070); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.wm-s-row button { flex-shrink:0; height:28px; padding:0 10px; border-radius:7px; border:1px solid var(--border, #d0d5dd); background:var(--surface, #fff); color:var(--text, #1a1e28); font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
.wm-s-row button.edit { background:#1f2b40; border-color:#1f2b40; color:#fff; }
.wm-s-empty { padding:10px 6px; font-size:13px; color:var(--text-muted, #5a6070); }
</style>
<script>
(function () {
    if (window.drgoWriteMode) return;
    const KEY = 'drgo_write_mode_target';
    const chan = ('BroadcastChannel' in window) ? new BroadcastChannel('drgo-write-sync') : null;
    const handlers = [];
    let muted = false;

    const WM = {
        /** 'window' | 'tab' — 사용자가 마지막으로 고른 방식 (기본 새 창) */
        pref() { try { return localStorage.getItem(KEY) || 'window'; } catch (e) { return 'window'; } },
        setPref(t) { try { localStorage.setItem(KEY, t); } catch (e) {} },
        /**
         * 같은 key는 같은 이름의 창을 재사용 — 다시 눌러도 창이 늘지 않고 기존 창이 앞으로 온다.
         * @param {string} url
         * @param {string} key  예: client-12, project-3, schedule-88, est_41
         */
        open(url, key, target) {
            // 의뢰자·프로젝트·캘린더 작성 모드는 창 하나를 같이 쓴다 — 그 창 안에서 서로 오간다 (견적서는 건별 창 유지)
            const shared = WM.isShared(url);
            if (shared && window.DRGO_STANDALONE) { location.href = url; return window; }
            const name = shared ? WM.SHARED_NAME : (key || '_blank');
            if ((target || WM.pref()) === 'tab') { const t = window.open(url, name); if (t) t.focus(); return t; }
            const w = Math.min(1280, (screen.availWidth || 1300) - 40), h = Math.min(980, (screen.availHeight || 1000) - 40);
            const left = Math.max(0, ((screen.availWidth || w) - w) / 2);
            const win = window.open(url, name, `width=${w},height=${h},left=${left},top=20,resizable=yes,scrollbars=yes`);
            if (win) win.focus();
            return win;
        },
        SHARED_NAME: 'drgo-write',
        /** 한 창을 같이 쓰는 작성 모드 주소인가 — /clients/{id}/write, /clients/create/write, /projects/{id}/write, /calendar/write */
        isShared(url) { return /^\/(clients\/(\d+|create)\/write|projects\/\d+\/write|calendar\/write)(\b|\?|$)/.test(String(url).replace(location.origin, '')); },
        /** ▾ 버튼 — 새 창 / 새 탭 선택 메뉴 (선택은 기억되고 바로 연다) */
        menu(e, url, key) {
            e.preventDefault(); e.stopPropagation();
            document.querySelectorAll('.wm-menu').forEach(m => m.remove());
            const pref = WM.pref();
            const m = document.createElement('div');
            m.className = 'wm-menu';
            m.innerHTML = `<button class="${pref === 'window' ? 'on' : ''}" data-t="window">새 창으로 열기</button><button class="${pref === 'tab' ? 'on' : ''}" data-t="tab">새 탭으로 열기</button>`;
            m.querySelectorAll('button').forEach(b => b.onclick = ev => {
                ev.stopPropagation(); WM.setPref(b.dataset.t); m.remove(); WM.open(url, key, b.dataset.t);
            });
            (e.currentTarget.closest('.wm-split') || e.currentTarget.parentElement).appendChild(m);
        },
        /** '↗ 작성 모드 ▾' 버튼 묶음 HTML — cls는 각 화면의 기존 버튼 클래스 */
        buttonHtml(url, key, cls, label) {
            const u = String(url).replace(/'/g, "\\'"), k = String(key).replace(/'/g, "\\'");
            cls = (cls || '') + ' wm-accent';
            return `<span class="wm-split"><button type="button" class="${cls}" onclick="event.stopPropagation(); drgoWriteMode.open('${u}','${k}')" title="이 화면만 독립된 창으로 띄워 작성합니다 — 여러 건을 각각의 창으로 동시에 작업할 수 있습니다">↗ ${label || '작성 모드'}</button><button type="button" class="${cls}" onclick="drgoWriteMode.menu(event,'${u}','${k}')" title="새 창 / 새 탭 선택">▾</button></span>`;
        },
        /**
         * 기존 의뢰자·프로젝트 검색 위젯 — 결과마다 [열기] / 의뢰자는 [수정](바로 수정 화면).
         * @param {HTMLElement} el  위젯을 그릴 자리
         * @param {string[]} kinds  'clients' | 'projects'
         */
        mountSearch(el, kinds) {
            const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const ph = kinds.length > 1 ? '의뢰자·프로젝트 이름, 연락처' : (kinds[0] === 'clients' ? '의뢰자 닉네임, 이름, 연락처' : '프로젝트 이름, 의뢰자');
            el.innerHTML = `<div class="wm-s"><input class="wm-s-in" placeholder="${ph}" aria-label="${ph} 검색"><div class="wm-s-res"><div class="wm-s-empty">찾을 이름을 입력하세요</div></div></div>`;
            const input = el.querySelector('.wm-s-in'), res = el.querySelector('.wm-s-res');
            input.addEventListener('click', e => e.stopPropagation());
            let timer = null, seq = 0;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    const q = input.value.trim(), my = ++seq;
                    if (!q) { res.innerHTML = '<div class="wm-s-empty">찾을 이름을 입력하세요</div>'; return; }
                    let sections = [];
                    try { const r = await fetch('/api/global-search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }); if (r.ok) { sections = (await r.json()).sections || []; } } catch (e) {}
                    if (my !== seq) { return; }
                    const rows = [];
                    sections.filter(sec => kinds.includes(sec.key)).forEach(sec => (sec.items || []).forEach(it => {
                        const id = sec.key === 'clients' ? (it.client_id || (String(it.url).match(/open=(\d+)/) || [])[1]) : (String(it.url).match(/\/projects\/(\d+)/) || [])[1];
                        if (!id) { return; }
                        const url = sec.key === 'clients' ? `/clients/${id}/write` : `/projects/${id}/write`;
                        const key = (sec.key === 'clients' ? 'client-' : 'project-') + id;
                        rows.push(`<div class="wm-s-row"><span class="k">${sec.key === 'clients' ? '의뢰자' : '프로젝트'}</span><span class="t"><b>${esc(it.label)}</b>${it.sub ? `<span>${esc(it.sub)}</span>` : ''}</span><button type="button" data-u="${url}" data-k="${key}">열기</button>${sec.key === 'clients' ? `<button type="button" class="edit" data-u="${url}?edit=1" data-k="${key}">수정</button>` : ''}</div>`);
                    }));
                    res.innerHTML = rows.length ? rows.join('') : '<div class="wm-s-empty">찾는 결과가 없어요</div>';
                    res.querySelectorAll('button[data-u]').forEach(b => b.onclick = ev => { ev.stopPropagation(); document.querySelectorAll('.wm-pop, #wmTopPanel').forEach(x => x.classList.remove('open')); WM.open(b.dataset.u, b.dataset.k); });
                }, 220);
            });
            setTimeout(() => input.focus(), 0);
        },
        /** 다른 창의 변경 알림 구독 — fn({path, method}) */
        onMutation(fn) { handlers.push(fn); },
        /** 다른 창 알림으로 갱신하는 동안에는 재알림하지 않음 (창끼리 핑퐁 방지) */
        async quietly(fn) { muted = true; try { return await fn(); } finally { muted = false; } },
        notify(path, method) { if (!muted && chan) { try { chan.postMessage({ path, method }); } catch (e) {} } },
    };
    window.drgoWriteMode = WM;

    if (chan) chan.onmessage = e => { const d = e.data || {}; handlers.forEach(h => { try { h(d); } catch (err) {} }); };
    document.addEventListener('click', () => document.querySelectorAll('.wm-menu').forEach(m => m.remove()));

    // 저장 성공(같은 출처 /api/ 의 변경 요청)을 다른 창에 알린다 — 같은 건을 띄운 창이 스스로 갱신 여부를 판단
    const origFetch = window.fetch;
    window.fetch = async function (input, init) {
        const res = await origFetch.apply(this, arguments);
        try {
            const method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
            if (method !== 'GET' && res.ok) {
                const url = new URL(typeof input === 'string' ? input : (input instanceof URL ? input.href : input.url), location.href);
                if (url.origin === location.origin && url.pathname.startsWith('/api/')) WM.notify(url.pathname, method);
            }
        } catch (e) {}
        return res;
    };
})();
</script>
