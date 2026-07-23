{{-- @멘션 자동완성 — data-mention 속성이 붙은 input/textarea에 자동 적용 (피드백·위키 댓글 공용) --}}
<style>
    .mention-dd { position:fixed; z-index:12000; background:var(--surface, #1e2430); border:1px solid var(--border, #3a4152); border-radius:10px; box-shadow:0 8px 28px rgba(0,0,0,0.35); padding:4px; max-height:240px; overflow-y:auto; min-width:180px; }
    .mention-dd-item { padding:7px 12px; border-radius:7px; font-size:13px; color:var(--text, #e8eaf0); cursor:pointer; white-space:nowrap; }
    .mention-dd-item.on, .mention-dd-item:hover { background:var(--accent, #5b8def); color:var(--accent-text, #fff); }
</style>
<script>
(function(){
    let MEMBERS = null, dd = null, items = [], sel = 0, activeEl = null, tokenStart = -1;
    function mEsc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    async function loadMembers(){
        if (MEMBERS) return MEMBERS;
        try {
            const r = await fetch('/api/mention-users', {headers:{'Accept':'application/json'}});
            MEMBERS = r.ok ? await r.json() : [];
        } catch(e) { MEMBERS = []; }
        return MEMBERS;
    }
    function closeDd(){ if (dd) { dd.remove(); dd = null; } items = []; activeEl = null; tokenStart = -1; }
    function render(el){
        if (!items.length) { closeDd(); return; }
        if (!dd) { dd = document.createElement('div'); dd.className = 'mention-dd'; document.body.appendChild(dd); }
        const r = el.getBoundingClientRect();
        dd.style.left = r.left + 'px';
        dd.style.top = Math.min(r.bottom + 4, window.innerHeight - 250) + 'px';
        dd.innerHTML = items.map((m, i) => `<div class="mention-dd-item${i===sel?' on':''}" data-i="${i}">@${mEsc(m.name)}</div>`).join('');
        dd.querySelectorAll('.mention-dd-item').forEach(it => {
            it.onmousedown = (e) => { e.preventDefault(); pick(parseInt(it.dataset.i, 10)); };
        });
    }
    function pick(i){
        const m = items[i];
        if (!m || !activeEl) return;
        const el = activeEl, v = el.value, caret = el.selectionStart;
        el.value = v.slice(0, tokenStart) + '@' + m.name + ' ' + v.slice(caret);
        const pos = tokenStart + m.name.length + 2;
        closeDd();
        el.setSelectionRange(pos, pos);
        el.focus();
        el.dispatchEvent(new Event('change', {bubbles:true}));
    }
    async function onInput(e){
        const el = e.target;
        if (!el.matches || !el.matches('[data-mention]')) return;
        const caret = el.selectionStart ?? el.value.length;
        const before = el.value.slice(0, caret);
        const m = before.match(/@([\p{L}\p{N}_.\-]*)$/u);
        if (!m) { if (activeEl === el) closeDd(); return; }
        const q = m[1].toLowerCase();
        const list = await loadMembers();
        // await 사이 다른 곳으로 포커스가 이동했으면 무시
        if (document.activeElement !== el) return;
        tokenStart = caret - m[0].length;
        activeEl = el;
        items = list.filter(x => !q || String(x.name).toLowerCase().includes(q)).slice(0, 8);
        sel = 0;
        render(el);
    }
    function onKey(e){
        if (!dd || e.target !== activeEl) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); sel = (sel + 1) % items.length; render(activeEl); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); sel = (sel - 1 + items.length) % items.length; render(activeEl); }
        else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); pick(sel); }
        else if (e.key === 'Escape') { e.stopPropagation(); closeDd(); }
    }
    document.addEventListener('input', onInput, true);
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('focusout', (e) => { if (e.target === activeEl) setTimeout(closeDd, 150); }, true);
    window.addEventListener('scroll', closeDd, true);
    window.addEventListener('resize', closeDd);
})();
</script>
