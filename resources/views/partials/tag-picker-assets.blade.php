@once
<style>
    .crm-tagpick .ctp-field { margin-bottom:14px; }
    .crm-tagpick .ctp-cap { font-size:11px; color:var(--text-muted); margin-bottom:5px; display:flex; gap:8px; align-items:center; }
    .crm-tagpick .ctp-hint { font-size:10px; opacity:0.65; font-weight:400; }
    .crm-tagpick .ctp-major-select { width:100%; padding:8px 11px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; cursor:pointer; box-sizing:border-box; }
    .crm-tagpick .ctp-inputwrap { position:relative; }
    .crm-tagpick .ctp-minor-input { width:100%; padding:8px 11px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .crm-tagpick .ctp-suggest { position:absolute; left:0; right:0; top:100%; z-index:50; background:var(--surface); border:1px solid var(--border); border-radius:0 0 8px 8px; max-height:190px; overflow-y:auto; box-shadow:0 6px 18px rgba(0,0,0,0.25); }
    .crm-tagpick .ctp-sug-item { padding:8px 11px; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .crm-tagpick .ctp-sug-item:hover, .crm-tagpick .ctp-sug-item.active { background:var(--surface2); }
    .crm-tagpick .ctp-sug-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .crm-tagpick .ctp-sug-del { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:15px; line-height:1; padding:0 4px; opacity:0.55; flex-shrink:0; }
    .crm-tagpick .ctp-sug-del:hover { color:var(--red); opacity:1; }
    .crm-tagpick .ctp-sug-new { color:var(--accent); font-weight:600; }
    .crm-tagpick .ctp-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .crm-tagpick .ctp-chip { display:inline-flex; align-items:center; gap:3px; font-size:12px; font-weight:600; padding:3px 5px 3px 10px; border-radius:13px; line-height:1.5; }
    .crm-tagpick .ctp-chip-x { background:none; border:none; cursor:pointer; font-size:14px; line-height:1; padding:0 3px; color:inherit; opacity:0.6; }
    .crm-tagpick .ctp-chip-x:hover { opacity:1; }
    /* 대분류 = 붉은색 / 소분류 = 회색 */
    .crm-tagpick .ctp-chip.ctp-major { background:rgba(200,80,80,0.14); color:#c0392b; border:1px solid rgba(200,80,80,0.4); }
    .crm-tagpick .ctp-chip.ctp-minor { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); }
</style>
<script>
window.CRM_MAJOR_TAGS = @json(config('crm.major_tags', []));
window.CRM_MINOR_TAGS = @json(\App\Models\ProjectSubtag::orderBy('sort_order')->orderBy('id')->get(['id', 'name']));
window.CRM_CAN_MANAGE_TAGS = @json((bool) auth()->user()?->hasPermission('tags.manage'));

window.CrmTagPicker = (function () {
    const state = {};
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    function safeArr(s) { try { const a = JSON.parse(s || '[]'); return Array.isArray(a) ? a : []; } catch (e) { return []; } }

    function init(key) {
        const root = document.querySelector(`.crm-tagpick[data-key="${CSS.escape(key)}"]`);
        if (!root || root.dataset.inited) return;
        root.dataset.inited = '1';
        state[key] = { major: safeArr(root.dataset.major), minor: safeArr(root.dataset.minor) };
        root.innerHTML = `
            <div class="ctp-field">
                <div class="ctp-cap">대분류 태그</div>
                <select class="ctp-major-select"></select>
                <div class="ctp-chips" data-scope="major"></div>
            </div>
            <div class="ctp-field">
                <div class="ctp-cap">소분류 태그 <span class="ctp-hint">입력 후 Enter로 추가 · 목록에서 선택</span></div>
                <div class="ctp-inputwrap">
                    <input class="ctp-minor-input" type="text" placeholder="태그 입력 또는 선택" autocomplete="off">
                    <div class="ctp-suggest" style="display:none;"></div>
                </div>
                <div class="ctp-chips" data-scope="minor"></div>
            </div>
            <span class="ctp-hidden"></span>`;
        bind(key, root);
        render(key, root);
    }

    function add(key, scope, val) { val = (val || '').trim(); if (!val) return; if (!state[key][scope].includes(val)) state[key][scope].push(val); }
    function remove(key, scope, val) { state[key][scope] = state[key][scope].filter(v => v !== val); }

    function chipHtml(scope, t) {
        return `<span class="ctp-chip ctp-${scope}">${esc(t)}<button type="button" class="ctp-chip-x" data-scope="${scope}" data-val="${esc(t)}">×</button></span>`;
    }

    function render(key, root) {
        root = root || document.querySelector(`.crm-tagpick[data-key="${CSS.escape(key)}"]`);
        const st = state[key];
        root.querySelector('.ctp-chips[data-scope="major"]').innerHTML = st.major.map(t => chipHtml('major', t)).join('');
        root.querySelector('.ctp-chips[data-scope="minor"]').innerHTML = st.minor.map(t => chipHtml('minor', t)).join('');
        const sel = root.querySelector('.ctp-major-select');
        const avail = (window.CRM_MAJOR_TAGS || []).filter(t => !st.major.includes(t));
        sel.innerHTML = `<option value="">+ 대분류 선택</option>` + avail.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('');
        root.querySelector('.ctp-hidden').innerHTML =
            st.major.map(t => `<input type="hidden" name="tags[major][]" value="${esc(t)}">`).join('') +
            st.minor.map(t => `<input type="hidden" name="tags[minor][]" value="${esc(t)}">`).join('');
        root.querySelectorAll('.ctp-chip-x').forEach(b => b.onclick = () => { remove(key, b.dataset.scope, b.dataset.val); render(key, root); });
    }

    function bind(key, root) {
        const sel = root.querySelector('.ctp-major-select');
        sel.onchange = () => { if (sel.value) { add(key, 'major', sel.value); render(key, root); } };
        const input = root.querySelector('.ctp-minor-input');
        const suggest = root.querySelector('.ctp-suggest');
        input.addEventListener('input', () => showSuggest(key, root, input.value));
        input.addEventListener('focus', () => showSuggest(key, root, input.value));
        input.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') { e.preventDefault(); await commitMinor(key, root, input.value); }
            else if (e.key === 'Escape') { suggest.style.display = 'none'; }
        });
        document.addEventListener('click', (e) => { if (!root.contains(e.target)) suggest.style.display = 'none'; });
    }

    function showSuggest(key, root, q) {
        const suggest = root.querySelector('.ctp-suggest');
        const st = state[key];
        const qq = (q || '').trim().toLowerCase();
        const opts = (window.CRM_MINOR_TAGS || []).filter(o => !st.minor.includes(o.name) && (!qq || o.name.toLowerCase().includes(qq)));
        let html = opts.map(o => `<div class="ctp-sug-item" data-val="${esc(o.name)}">
            <span class="ctp-sug-name">${esc(o.name)}</span>
            ${window.CRM_CAN_MANAGE_TAGS ? `<button type="button" class="ctp-sug-del" data-id="${o.id}" data-name="${esc(o.name)}" title="이 태그를 목록에서 삭제">×</button>` : ''}
        </div>`).join('');
        const exact = (window.CRM_MINOR_TAGS || []).some(o => o.name.toLowerCase() === qq);
        if (qq && !exact && window.CRM_CAN_MANAGE_TAGS) {
            html += `<div class="ctp-sug-item ctp-sug-new" data-new="${esc(q.trim())}">+ "${esc(q.trim())}" 새 태그 추가</div>`;
        }
        if (!html) { suggest.style.display = 'none'; return; }
        suggest.innerHTML = html;
        suggest.style.display = 'block';
        suggest.querySelectorAll('.ctp-sug-del').forEach(b => b.onclick = async (e) => {
            e.stopPropagation();
            await deleteSubtag(key, root, parseInt(b.dataset.id, 10), b.dataset.name);
        });
        suggest.querySelectorAll('.ctp-sug-item').forEach(it => it.onclick = async () => {
            if (it.dataset.new !== undefined) { await commitMinor(key, root, it.dataset.new); }
            else { add(key, 'minor', it.dataset.val); root.querySelector('.ctp-minor-input').value = ''; suggest.style.display = 'none'; render(key, root); }
        });
    }

    async function deleteSubtag(key, root, id, name) {
        if (!confirm(`'${name}' 소분류 태그를 목록에서 삭제할까요?`)) return;
        try {
            const res = await fetch(`/api/project-subtags/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } });
            if (!res.ok) { const er = await res.json().catch(() => ({})); alert(er.message || '삭제 실패'); return; }
            window.CRM_MINOR_TAGS = (window.CRM_MINOR_TAGS || []).filter(o => o.id !== id);
            state[key].minor = state[key].minor.filter(v => v !== name); // 선택돼 있으면 해제
            render(key, root);
            showSuggest(key, root, root.querySelector('.ctp-minor-input').value);
        } catch (e) { alert('삭제 실패'); }
    }

    async function commitMinor(key, root, raw) {
        const val = (raw || '').trim(); if (!val) return;
        const input = root.querySelector('.ctp-minor-input');
        const suggest = root.querySelector('.ctp-suggest');
        const existing = (window.CRM_MINOR_TAGS || []).find(o => o.name.toLowerCase() === val.toLowerCase());
        if (existing) { add(key, 'minor', existing.name); input.value = ''; suggest.style.display = 'none'; render(key, root); return; }
        if (!window.CRM_CAN_MANAGE_TAGS) { alert('새 소분류 태그를 추가할 권한이 없습니다. 목록에서 선택해 주세요.'); return; }
        try {
            const res = await fetch('/api/project-subtags', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }, body: JSON.stringify({ name: val }) });
            if (!res.ok) { const er = await res.json().catch(() => ({})); alert(er.message || '태그 추가 실패'); return; }
            const data = await res.json().catch(() => ({ name: val }));
            const nm = data.name || val;
            if (!(window.CRM_MINOR_TAGS || []).some(o => o.name === nm)) window.CRM_MINOR_TAGS.push({ id: data.id, name: nm });
            add(key, 'minor', nm); input.value = ''; suggest.style.display = 'none'; render(key, root);
        } catch (e) { alert('태그 추가 실패'); }
    }

    function value(key) { return state[key] ? { major: state[key].major.slice(), minor: state[key].minor.slice() } : { major: [], minor: [] }; }

    return { init, value, render };
})();
</script>
@endonce
