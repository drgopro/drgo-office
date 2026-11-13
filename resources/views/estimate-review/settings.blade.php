@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '견적서 검토 기준 - 닥터고블린 오피스')

@push('styles')
<style>
    .rvs-wrap { padding:24px; max-width:1240px; margin:0 auto; }
    .rvs-wrap h2 { font-size:20px; font-weight:800; margin:0; }
    .rvs-lead { font-size:14px; color:var(--text-muted); margin:6px 0 16px; max-width:75ch; }
    .rvs-tabs { display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid var(--border); margin-bottom:16px; }
    .rvs-tabs button { border:0; background:none; padding:8px 14px; font-size:15px; font-weight:500; border-bottom:2px solid transparent; color:var(--text-muted); cursor:pointer; }
    .rvs-tabs button.on { color:var(--text); font-weight:700; border-color:var(--accent); }
    .rvs-save { font-size:13px; color:var(--text-muted); margin-left:auto; align-self:center; padding-bottom:6px; }
    .rvs-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; min-width:0; margin-bottom:14px; }
    .rvs-pad { padding:14px 16px; display:grid; gap:10px; }
    .rvs-card h3 { margin:0; font-size:15px; font-weight:700; }
    .rvs-tw { overflow-x:auto; }
    table.rvs-t { border-collapse:collapse; width:100%; font-size:14px; }
    .rvs-t th { background:var(--surface2); text-align:left; font-size:13px; font-weight:700; color:var(--text-muted); white-space:nowrap; padding:7px 10px; border-bottom:1px solid var(--border); }
    .rvs-t td { padding:7px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .rvs-t tr:last-child td { border-bottom:0; }
    .rvs-t tr.gh td { background:var(--surface2); font-weight:700; }
    .rvs-t tr.off td:not(:first-child) { opacity:.5; }
    .rvs-pill { font-size:12px; font-weight:700; border-radius:4px; padding:1px 6px; white-space:nowrap; }
    .rvs-pill.p-bad { background:rgba(192,56,56,.12); color:var(--red); }
    .rvs-pill.p-ask { background:rgba(168,99,0,.14); color:#a86300; }
    .rvs-pill.p-ok { background:rgba(36,138,56,.12); color:var(--green); }
    .rvs-pill.p-off { background:var(--surface2); color:var(--text-muted); }
    .rvs-chip { display:inline-flex; gap:3px; align-items:center; background:rgba(46,108,181,.1); color:var(--blue); border-radius:4px; padding:1px 7px; font-size:13px; font-weight:500; margin:1px 3px 1px 0; white-space:nowrap; }
    .rvs-chip.inh { background:var(--surface2); color:var(--text-muted); }
    .rvs-chip button { border:0; background:none; padding:0 1px; color:inherit; font-size:13px; line-height:1; cursor:pointer; }
    .rvs-mini { border:1px solid var(--border); background:var(--surface); border-radius:6px; padding:4px 9px; font-size:13px; font-weight:500; color:var(--text); cursor:pointer; white-space:nowrap; }
    .rvs-mini.pri { background:var(--accent); border-color:var(--accent); color:var(--accent-text, #fff); }
    [data-theme="light"] .rvs-mini.pri { color:#fff; }
    .rvs-mini.on { background:rgba(46,108,181,.12); border-color:var(--blue); color:var(--blue); }
    .rvs-mini.danger { color:var(--red); }
    .rvs-mini:disabled { opacity:.5; cursor:not-allowed; }
    .rvs-acts { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .rvs-input, .rvs-sel { background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:6px 9px; color:var(--text); font-size:14px; max-width:100%; }
    .rvs-chkline { display:flex; flex-wrap:wrap; gap:10px 18px; }
    .rvs-chkline label { display:flex; align-items:center; gap:6px; font-size:14px; cursor:pointer; }
    .rvs-two { display:grid; grid-template-columns:300px minmax(0,1fr); gap:14px; align-items:start; }
    @media (max-width:900px) { .rvs-two { grid-template-columns:minmax(0,1fr); } }
    .rvs-tree { max-height:calc(100vh - 260px); overflow-y:auto; padding:6px; }
    .rvs-tree button { display:flex; justify-content:space-between; gap:8px; width:100%; text-align:left; border:0; background:none; border-radius:5px; padding:4px 8px; font-size:14px; color:var(--text); cursor:pointer; }
    .rvs-tree button:hover { background:var(--surface2); }
    .rvs-tree button.sel { background:rgba(46,108,181,.12); color:var(--blue); font-weight:700; }
    .rvs-tree small { color:var(--text-muted); white-space:nowrap; }
    .rvs-tree .has::after { content:"●"; color:var(--blue); font-size:8px; margin-left:5px; vertical-align:middle; }
    .rvs-drawerbg { position:fixed; inset:0; background:rgba(10,18,26,.45); z-index:300; }
    .rvs-drawer { position:fixed; top:0; right:0; bottom:0; width:min(560px,100%); background:var(--surface); z-index:301; overflow-y:auto; padding:18px; display:grid; gap:13px; align-content:start; box-shadow:-4px 0 18px rgba(0,0,0,.25); }
    .rvs-fld { display:grid; gap:4px; }
    .rvs-fld > span { font-size:13px; font-weight:700; color:var(--text-muted); }
    .rvs-fld input, .rvs-fld select, .rvs-fld textarea { background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:6px 9px; color:var(--text); font-size:14px; width:100%; }
    .rvs-fld small { color:var(--text-muted); font-size:13px; }
    .rvs-chk { display:flex; flex-wrap:wrap; gap:4px; }
    .rvs-grouptitle { font-size:12px; color:var(--text-muted); width:100%; margin-top:4px; }
    .rvs-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; }
    .rvs-note { font-size:13px; color:var(--text-muted); }
    .rvs-specmeta { font-size:13px; color:var(--text-muted); }
    .rvs-chips { display:flex; flex-wrap:wrap; gap:4px; }
    .rvs-fchip { border:1px solid var(--border); background:var(--surface); border-radius:7px; padding:3px 10px; font-size:13px; font-weight:500; color:var(--text); cursor:pointer; }
    .rvs-fchip.on { background:var(--accent); border-color:var(--accent); color:var(--accent-text, #fff); }
    [data-theme="light"] .rvs-fchip.on { color:#fff; }
</style>
@endpush

@section('content')
<div class="rvs-wrap">
    <h2>견적서 검토 기준</h2>
    <p class="rvs-lead">검토 버튼이 확인하는 규칙·역할·규격을 관리합니다. 저장하면 다음 검토부터 적용되고, 검토가 끝난 견적도 품목이 바뀌면 다시 검토하게 됩니다.</p>
    <div class="rvs-tabs" id="rvsTabs"></div>
    <div id="rvsBody"></div>
</div>
<div id="rvsDrawer"></div>
@endsection

@push('scripts')
<script>
const CFG = @json($config);
const CATS = @json($categories);       // [{path, count}]
const PRODUCTS = @json($products);     // [{name, path, bundle}]
const RVS_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

const _e = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const FIELDS = {
    vOut:{label:'영상 출력',fam:'video',port:true}, vIn:{label:'영상 입력',fam:'video',port:true,qty:'입력 수'},
    aOut:{label:'오디오 출력',fam:'audio',port:true,qty:'출력 수'}, aIn:{label:'오디오 입력',fam:'audio',port:true,qty:'입력 수'},
    cable:{label:'케이블 양쪽 단자',cable:true},
    lensTake:{label:'장착 가능 렌즈 마운트',fam:'lens'}, lensMount:{label:'렌즈 마운트',fam:'lens'}, lensIncl:{label:'렌즈 포함 (KIT)',bool:true},
    batTake:{label:'카메라 배터리 규격',fam:'battery'}, batFit:{label:'맞는 배터리 규격',fam:'battery'},
    mntTake:{label:'조명 마운트',fam:'mount'}, mntFit:{label:'부속 마운트',fam:'mount'},
};
const CHECKS = { video:'영상 연결 (카메라 → 캡처)', audio:'오디오 연결 (마이크 → 믹서)', lens:'카메라 ↔ 렌즈 마운트', battery:'카메라 ↔ 배터리 규격', mount:'조명 ↔ 소프트박스·젬볼 마운트', spec:'규격 미입력 제품 확인' };

const UI = { tab:'rules', catSel:'', prodQ:'', onlyEmpty:false, specQ:'', specFilter:'all', drawer:null, specEdit:null, delArm:'' };

// 검색 입력 — 리렌더 뒤에도 커서 유지
let rvsSearchTimer = null;
function rvsSearch(el, key) {
    UI[key] = el.value;
    const id = el.id;
    clearTimeout(rvsSearchTimer);
    rvsSearchTimer = setTimeout(() => {
        render();
        const n = document.getElementById(id);
        if (n) { n.focus(); n.setSelectionRange(n.value.length, n.value.length); }
    }, 250);
}

// ── 저장 (디바운스) ──
let saveTimer = null, saving = false;
function markDirty() {
    document.getElementById('rvsSave').textContent = '저장 중...';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveCfg, 700);
}
async function saveCfg() {
    if (saving) { markDirty(); return; }
    saving = true;
    try {
        const res = await fetch('/api/estimate-review/config', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':RVS_CSRF,'Accept':'application/json'},
            body: JSON.stringify({ config: CFG }),
        });
        const body = await res.json().catch(() => ({}));
        document.getElementById('rvsSave').textContent = res.ok ? `저장됨 ${body.saved_at || ''}` : (body.message || '저장 실패 — 다시 시도하세요');
        if (!res.ok) setTimeout(markDirty, 3000);
    } catch (e) {
        document.getElementById('rvsSave').textContent = '저장 실패 — 네트워크 확인';
        setTimeout(markDirty, 3000);
    } finally { saving = false; }
}

// ── 역할 해석 (표시용) — 제품 개별 > 가장 깊은 카테고리 ──
function roleInfoOf(p) {
    if (Object.prototype.hasOwnProperty.call(CFG.productRolesByName, p.name)) return { roles: CFG.productRolesByName[p.name], src: 'product' };
    const parts = (p.path || '').split('>');
    for (let i = parts.length; i > 0; i--) {
        const k = parts.slice(0, i).join('>');
        if (Object.prototype.hasOwnProperty.call(CFG.categoryRolesByPath, k)) return { roles: CFG.categoryRolesByPath[k], src: 'cat', cat: k };
    }
    return { roles: [], src: 'none' };
}
const roleLabel = id => (CFG.roles.find(r => r.id === id) || { label: id + ' (삭제된 역할)' }).label;

// ── 규격 해석 (표시용) — 제품명 지정 > 카테고리 경로 기본값 ──
function specInfoOf(p) {
    const own = CFG.specsByName[p.name];
    const parts = (p.path || '').split('>');
    let base = null;
    for (let i = parts.length; i > 0; i--) {
        const k = parts.slice(0, i).join('>');
        if (CFG.specsByPath[k]) { base = CFG.specsByPath[k]; break; }
    }
    return { spec: Object.assign({}, base || {}, own || {}), own: !!own };
}
function tmplOf(path) {
    const parts = (path || '').split('>');
    for (let i = parts.length; i > 0; i--) {
        const k = parts.slice(0, i).join('>');
        if (CFG.specTemplatesByPath[k]) return CFG.specTemplatesByPath[k];
    }
    return [];
}
const filled = (v, f) => f === 'lensIncl' || (v != null && (Array.isArray(v) || v.t || v.kind));
function missingFields(p) {
    const spec = specInfoOf(p).spec;
    return tmplOf(p.path).filter(f => {
        const v = spec[f];
        return !filled(v, f) || (v && v.t && !v.t.length) || (f === 'cable' && v && v.kind !== 'none' && (!v.a || !v.b));
    });
}
function specSummary(spec) {
    const out = [];
    Object.keys(FIELDS).forEach(f => {
        const v = spec[f]; if (v == null) return;
        const d = FIELDS[f];
        if (d.bool) { if (v) out.push(d.label); return; }
        if (d.cable) { out.push(v.kind === 'none' ? '연결용 아님' : `${v.a || '?'} ↔ ${v.b || '?'}${v.kind === 'adapter' ? ' (젠더)' : ''}`); return; }
        if (d.port) { out.push(`${d.label} ${(v.t || []).join('/') || '-'}${(v.n || 1) > 1 ? ` ${v.n}개` : ''}`); return; }
        out.push(`${d.label} ${v.length ? v.join('/') : '없음'}`);
    });
    return out.join(' · ');
}

// ── 렌더 ──
function render() {
    const tabs = [['rules','검토 규칙'],['roles','역할 목록'],['products','제품 역할'],['specs','규격 확인']];
    document.getElementById('rvsTabs').innerHTML = tabs.map(([v,l]) =>
        `<button class="${UI.tab===v?'on':''}" onclick="UI.tab='${v}'; UI.delArm=''; render()">${l}</button>`).join('')
        + `<span class="rvs-save" id="rvsSave"></span>`;
    const body = { rules: tabRules, roles: tabRoles, products: tabProducts, specs: tabSpecs }[UI.tab]();
    document.getElementById('rvsBody').innerHTML = body;
    renderDrawer();
}

// ── 탭 1: 검토 규칙 ──
function chipsOf(ids) { return (ids || []).map(i => `<span class="rvs-chip">${_e(roleLabel(i))}</span>`).join(''); }
function tabRules() {
    const groups = [...new Set(CFG.rules.map(r => r.group || '기타'))];
    const rows = groups.map(g => `<tr class="gh"><td colspan="5">${_e(g)}</td></tr>` + CFG.rules.map((r, i) => ({...r, _i:i})).filter(r => (r.group||'기타') === g).map(r => `
        <tr class="${r.active ? '' : 'off'}">
            <td><input type="checkbox" ${r.active?'checked':''} onchange="CFG.rules[${r._i}].active=this.checked; markDirty(); render()" title="규칙 켜기/끄기"></td>
            <td>${chipsOf(r.on)}</td>
            <td>${r.type === 'need' ? `${chipsOf(r.need)} <span class="rvs-note">${r.mode === 'atLeast' ? `최소 ${r.amt}개` : `대수${r.amt > 1 ? ' ×' + r.amt : ''}만큼`}</span>` : _e(r.title)}</td>
            <td><span class="rvs-pill ${r.type==='need'?'p-bad':'p-ask'}">${r.type==='need'?'품목 필요':'담당자 확인'}</span></td>
            <td style="white-space:nowrap">
                <button class="rvs-mini" onclick="openRule(${r._i})">편집</button>
                <button class="rvs-mini" onclick="dupRule(${r._i})">복제</button>
                <button class="rvs-mini danger" onclick="delRule(${r._i})">${UI.delArm === 'r'+r._i ? '삭제 확인' : '삭제'}</button>
            </td>
        </tr>`).join('')).join('');
    return `
    <div class="rvs-card rvs-pad"><h3>출력·발행 잠금</h3>
        <label style="display:flex; align-items:center; gap:6px; font-size:14px; cursor:pointer"><input type="checkbox" ${CFG.gate !== false ? 'checked' : ''} onchange="CFG.gate=this.checked; markDirty()">
        검토가 완료되지 않은 견적서는 출력·발행할 수 없습니다 (발행·결제 완료된 기존 견적서의 출력은 허용)</label>
    </div>
    <div class="rvs-card rvs-pad"><h3>연결 검사 사용</h3>
        <div class="rvs-chkline">${Object.entries(CHECKS).map(([k,l]) =>
            `<label><input type="checkbox" ${CFG.checks[k] !== false ? 'checked' : ''} onchange="CFG.checks['${k}']=this.checked; markDirty()"> ${l}</label>`).join('')}</div>
    </div>
    <div class="rvs-bar" style="margin-bottom:10px">
        <span class="rvs-note">규칙 ${CFG.rules.length}개 · 켜짐 ${CFG.rules.filter(r=>r.active).length}개</span>
        <div class="rvs-acts">
            <button class="rvs-mini pri" onclick="newRule('need')">+ 품목 규칙</button>
            <button class="rvs-mini" onclick="newRule('ask')">+ 확인 질문</button>
        </div>
    </div>
    <div class="rvs-card rvs-tw"><table class="rvs-t" style="min-width:760px">
        <thead><tr><th>사용</th><th>언제 (이 역할이 있으면)</th><th>무엇을</th><th>종류</th><th></th></tr></thead>
        <tbody>${rows || '<tr><td colspan="5" style="color:var(--text-muted)">규칙이 없습니다.</td></tr>'}</tbody></table></div>`;
}
function newRule(type) {
    const id = type + '-' + Date.now().toString(36);
    UI.drawer = type === 'need'
        ? { _new:true, type, id, group:'', on:[], need:[], mode:'perUnit', amt:1, what:'', why:'', fix:[], active:true }
        : { _new:true, type, id, group:'', on:[], cond:'always', cmp:[], min:1, title:'', desc:'', choicesTxt:'', fix:[], active:true };
    render();
}
function openRule(i) {
    const r = JSON.parse(JSON.stringify(CFG.rules[i]));
    r._i = i; r.choicesTxt = (r.choices || []).join(', ');
    r.fix = Array.isArray(r.fix) ? r.fix : (r.fix ? String(r.fix).split(',').map(x => x.trim()).filter(Boolean) : []);
    UI.drawer = r; render();
}
function dFixAdd() {
    const inp = document.getElementById('dFixAdd');
    const name = (inp.value || '').trim();
    if (!name) return;
    if (!PRODUCTS.some(p => p.name === name)) { alert('제품 목록에 없는 이름입니다. 목록에서 정확한 제품명을 고르세요.'); return; }
    UI.drawer.fix = UI.drawer.fix || [];
    if (!UI.drawer.fix.includes(name)) UI.drawer.fix.push(name);
    inp.value = '';
    render();
    const n = document.getElementById('dFixAdd'); if (n) n.focus();
}
function dupRule(i) {
    const r = JSON.parse(JSON.stringify(CFG.rules[i]));
    r.id = r.id + '-copy-' + Date.now().toString(36);
    CFG.rules.splice(i + 1, 0, r);
    markDirty(); render();
}
function delRule(i) {
    if (UI.delArm !== 'r' + i) { UI.delArm = 'r' + i; render(); setTimeout(() => { if (UI.delArm === 'r'+i) { UI.delArm=''; render(); } }, 2500); return; }
    CFG.rules.splice(i, 1); UI.delArm = '';
    markDirty(); render();
}
function roleToggles(key, sel) {
    const gs = [...new Set(CFG.roles.map(r => r.group))];
    return `<div class="rvs-chk">${gs.map(g => `<div class="rvs-grouptitle">${_e(g)}</div>` + CFG.roles.filter(r => r.group === g).map(r =>
        `<button type="button" class="rvs-mini ${sel.includes(r.id)?'on':''}" onclick="dToggle('${key}','${r.id}')">${_e(r.label)}</button>`).join('')).join('')}</div>`;
}
function dToggle(key, id) {
    const d = UI.drawer; if (!d) return;
    const arr = d[key] || (d[key] = []);
    const i = arr.indexOf(id);
    i >= 0 ? arr.splice(i, 1) : arr.push(id);
    render();
}
function dSet(key, val) { if (UI.drawer) { UI.drawer[key] = val; } }
function saveRule() {
    const d = UI.drawer;
    const err = !d.on.length ? '언제 적용할지 역할을 고르세요.'
        : d.type === 'need' && !(d.need||[]).length ? '필요한 역할을 고르세요.'
        : d.type === 'need' && !d.what.trim() ? '경고에 표시할 이름을 적어 주세요.'
        : d.type === 'ask' && !d.title.trim() ? '질문을 적어 주세요.' : '';
    if (err) { alert(err); return; }
    const r = JSON.parse(JSON.stringify(d));
    delete r._new; const idx = r._i; delete r._i;
    if (r.type === 'ask') { r.choices = (r.choicesTxt||'').split(',').map(s=>s.trim()).filter(Boolean); r.min = Math.max(1, +r.min || 1); }
    else { r.amt = Math.max(1, +r.amt || 1); }
    delete r.choicesTxt;
    r.group = (r.group || '기타').trim() || '기타';
    if (idx != null) CFG.rules[idx] = r; else CFG.rules.push(r);
    UI.drawer = null;
    markDirty(); render();
}

// ── 탭 2: 역할 목록 ──
function roleUsage(id) {
    const rules = CFG.rules.filter(r => (r.on||[]).includes(id) || (r.need||[]).includes(id) || (r.cmp||[]).includes(id)).length;
    let prods = 0;
    PRODUCTS.forEach(p => { if (roleInfoOf(p).roles.some(x => x[0] === id)) prods++; });
    return { rules, prods };
}
function tabRoles() {
    const rows = CFG.roles.map((r, i) => {
        const u = roleUsage(r.id);
        return `<tr>
            <td><input class="rvs-input" value="${_e(r.label)}" onchange="CFG.roles[${i}].label=this.value; markDirty()" style="width:180px"></td>
            <td><input class="rvs-input" value="${_e(r.group)}" onchange="CFG.roles[${i}].group=this.value; markDirty()" style="width:110px"></td>
            <td>${u.prods}개</td><td>${u.rules}개</td>
            <td><button class="rvs-mini danger" ${u.rules ? 'disabled title="규칙에서 쓰는 역할은 삭제할 수 없습니다"' : ''} onclick="delRole(${i})">${UI.delArm === 'o'+i ? '삭제 확인' : '삭제'}</button></td>
        </tr>`;
    }).join('');
    return `
    <p class="rvs-lead">역할은 제품이 견적에서 맡는 기능입니다. 규칙은 상품명이 아니라 이 역할 기준으로 수량을 셉니다.</p>
    <div class="rvs-acts" style="margin-bottom:10px">
        <input class="rvs-input" id="newRoleLabel" placeholder="새 역할 이름">
        <input class="rvs-input" id="newRoleGroup" placeholder="묶음 (예: 조명)" style="width:140px">
        <button class="rvs-mini pri" onclick="addRole()">역할 추가</button>
    </div>
    <div class="rvs-card rvs-tw"><table class="rvs-t" style="min-width:620px">
        <thead><tr><th>역할 이름</th><th>묶음</th><th>제품 수</th><th>사용 규칙</th><th></th></tr></thead>
        <tbody>${rows}</tbody></table></div>`;
}
function addRole() {
    const label = document.getElementById('newRoleLabel').value.trim();
    const group = document.getElementById('newRoleGroup').value.trim() || '기타';
    if (!label) { alert('역할 이름을 적어 주세요.'); return; }
    const id = 'role-' + Date.now().toString(36);
    CFG.roles.push({ id, label, group });
    markDirty(); render();
}
function delRole(i) {
    if (UI.delArm !== 'o' + i) { UI.delArm = 'o' + i; render(); setTimeout(() => { if (UI.delArm === 'o'+i) { UI.delArm=''; render(); } }, 2500); return; }
    CFG.roles.splice(i, 1); UI.delArm = '';
    markDirty(); render();
}

// ── 탭 3: 제품 역할 ──
function roleEditor(list, scope, key) {
    return `<div class="rvs-acts">${(list||[]).map(([r, n]) =>
        `<span class="rvs-chip">${_e(roleLabel(r))}
            <button onclick="roleAdj('${scope}','${_e(key)}','${r}',-1)">−</button>${n}<button onclick="roleAdj('${scope}','${_e(key)}','${r}',1)">＋</button>
            <button onclick="roleAdj('${scope}','${_e(key)}','${r}',0)">×</button></span>`).join('') || '<span class="rvs-note">역할 없음</span>'}
        <select class="rvs-sel" id="roleAdd-${scope}" style="font-size:13px">${['<option value="">역할 선택</option>', ...[...new Set(CFG.roles.map(r=>r.group))].map(g =>
            `<optgroup label="${_e(g)}">${CFG.roles.filter(r=>r.group===g && !(list||[]).some(x=>x[0]===r.id)).map(r=>`<option value="${r.id}">${_e(r.label)}</option>`).join('')}</optgroup>`)].join('')}</select>
        <button class="rvs-mini" onclick="roleAddTo('${scope}','${_e(key)}')">추가</button></div>`;
}
function roleStore(scope) { return scope === 'c' ? CFG.categoryRolesByPath : CFG.productRolesByName; }
function roleAdj(scope, key, roleId, delta) {
    const store = roleStore(scope);
    let cur = store[key];
    if (cur === undefined && scope === 'p') cur = JSON.parse(JSON.stringify(roleInfoOf(PRODUCTS.find(p => p.name === key) || {name:key,path:''}).roles));
    cur = (cur || []).slice();
    const i = cur.findIndex(x => x[0] === roleId);
    if (i < 0) return;
    if (delta === 0 || cur[i][1] + delta <= 0) cur.splice(i, 1);
    else cur[i] = [roleId, cur[i][1] + delta];
    store[key] = cur;
    markDirty(); render();
}
function roleAddTo(scope, key) {
    const sel = document.getElementById('roleAdd-' + scope);
    if (!sel || !sel.value) return;
    const store = roleStore(scope);
    let cur = store[key];
    if (cur === undefined && scope === 'p') cur = JSON.parse(JSON.stringify(roleInfoOf(PRODUCTS.find(p => p.name === key) || {name:key,path:''}).roles));
    cur = (cur || []).slice();
    cur.push([sel.value, 1]);
    store[key] = cur;
    markDirty(); render();
}
let editProd = null;
function tabProducts() {
    const sel = UI.catSel;
    const tree = CATS.map(c => {
        const d = c.path.split('>').length - 1;
        const has = Object.prototype.hasOwnProperty.call(CFG.categoryRolesByPath, c.path);
        return `<button class="${sel === c.path ? 'sel' : ''}" style="padding-left:${8 + d * 14}px" onclick="UI.catSel='${_e(c.path)}'; render()">
            <span class="${has ? 'has' : ''}">${_e(c.path.split('>').pop())}</span><small>${c.count}</small></button>`;
    }).join('');
    const q = UI.prodQ.trim().toLowerCase();
    let list = PRODUCTS.filter(p => (!sel || p.path === sel || p.path.startsWith(sel + '>')) && (!q || p.name.toLowerCase().includes(q)));
    if (UI.onlyEmpty) list = list.filter(p => !roleInfoOf(p).roles.length);
    const shown = list.slice(0, 150);
    const rows = shown.map(p => {
        const info = roleInfoOf(p), ed = editProd === p.name;
        return `<tr><td>${_e(p.name)}${p.bundle ? ' <span class="rvs-pill p-off">세트</span>' : ''}<div class="rvs-note">${_e(p.path.split('>').join(' › '))}</div></td>
        <td>${ed ? roleEditor(Object.prototype.hasOwnProperty.call(CFG.productRolesByName, p.name) ? CFG.productRolesByName[p.name] : info.roles, 'p', p.name)
            : info.roles.length ? info.roles.map(([r,n]) => `<span class="rvs-chip ${info.src==='cat'?'inh':''}">${_e(roleLabel(r))}${n>1?' ×'+n:''}</span>`).join('') : '<span class="rvs-note">없음</span>'}</td>
        <td class="rvs-note" style="white-space:nowrap">${info.src === 'product' ? '제품 개별' : info.src === 'cat' ? '카테고리' : '-'}</td>
        <td style="white-space:nowrap">
            <button class="rvs-mini ${ed?'on':''}" onclick="editProd=${ed?'null':`'${_e(p.name)}'`}; render()">${ed ? '닫기' : '개별 지정'}</button>
            ${info.src === 'product' ? `<button class="rvs-mini" onclick="delete CFG.productRolesByName['${_e(p.name)}']; markDirty(); render()">카테고리로</button>` : ''}
        </td></tr>`;
    }).join('');
    const catHas = sel && Object.prototype.hasOwnProperty.call(CFG.categoryRolesByPath, sel);
    return `
    <p class="rvs-lead">카테고리에 역할을 지정하면 그 안의 제품 전체에 적용됩니다. 개별 지정이 카테고리보다 우선하고, 하위 카테고리가 상위보다 우선합니다. 빈 지정은 "역할 없음"이라는 뜻입니다.</p>
    <div class="rvs-two">
        <div class="rvs-card rvs-tree">
            <button class="${sel ? '' : 'sel'}" onclick="UI.catSel=''; render()"><span>전체 제품</span><small>${PRODUCTS.length}</small></button>${tree}
        </div>
        <div style="display:grid; gap:10px; min-width:0">
            ${sel ? `<div class="rvs-card rvs-pad"><h3>${_e(sel.split('>').join(' › '))} 카테고리의 역할</h3>
                ${roleEditor(CFG.categoryRolesByPath[sel] || [], 'c', sel)}
                ${catHas ? `<div><button class="rvs-mini" onclick="delete CFG.categoryRolesByPath['${_e(sel)}']; markDirty(); render()">지정 해제 (상위 상속)</button></div>` : ''}</div>` : ''}
            <div class="rvs-bar">
                <input class="rvs-input" id="rvsProdQ" placeholder="제품명 검색" value="${_e(UI.prodQ)}" oninput="rvsSearch(this,'prodQ')" style="flex:1; min-width:180px">
                <label style="display:flex; align-items:center; gap:5px; font-size:14px; cursor:pointer"><input type="checkbox" ${UI.onlyEmpty?'checked':''} onchange="UI.onlyEmpty=this.checked; render()"> 역할 없는 제품만</label>
            </div>
            <div class="rvs-card rvs-tw"><table class="rvs-t" style="min-width:640px">
                <thead><tr><th>제품</th><th>역할</th><th>지정 방식</th><th></th></tr></thead>
                <tbody>${rows || '<tr><td colspan="4" style="color:var(--text-muted)">해당 제품이 없습니다.</td></tr>'}</tbody></table></div>
            ${list.length > 150 ? '<p class="rvs-note">처음 150개만 표시합니다. 검색이나 카테고리로 좁혀 주세요.</p>' : ''}
        </div>
    </div>`;
}

// ── 탭 4: 규격 확인 ──
function tabSpecs() {
    const names = PRODUCTS.filter(p => tmplOf(p.path).length || CFG.specsByName[p.name]);
    const stat = p => missingFields(p).length ? 'miss' : 'ok';
    const cnt = { miss: 0, ok: 0 };
    names.forEach(p => cnt[stat(p)]++);
    const q = UI.specQ.trim().toLowerCase(), f = UI.specFilter;
    const list = names.filter(p => (f === 'all' || stat(p) === f) && (!q || p.name.toLowerCase().includes(q)));
    const rows = list.slice(0, 200).map(p => {
        const info = specInfoOf(p), st = stat(p);
        return `<tr><td>${_e(p.name)}<div class="rvs-specmeta">${_e(specSummary(info.spec) || '규격 없음')}</div></td>
        <td class="rvs-note" style="white-space:nowrap">${info.own ? '제품 지정' : Object.keys(info.spec).length ? '카테고리 기본' : '-'}</td>
        <td><span class="rvs-pill ${st === 'miss' ? 'p-bad' : 'p-ok'}">${st === 'miss' ? '미입력' : '입력됨'}</span></td>
        <td><button class="rvs-mini" onclick='openSpec(${JSON.stringify(p.name)})'>입력</button></td></tr>`;
    }).join('');
    return `
    <p class="rvs-lead">연결·호환 검사는 이 규격으로 판단합니다. 미입력 제품은 검토에서 "규격 미입력" 확인 질문으로 잡힙니다. 제품 지정이 카테고리 기본값보다 우선합니다.</p>
<div class="rvs-card rvs-pad"><h3>규격값 추가</h3>
        <p class="rvs-lead" style="margin:0">새 단자나 마운트가 생기면 여기에 추가합니다. 값이 완전히 같아야 맞는 제품으로 판단합니다.</p>
        <div class="rvs-acts">
            <select class="rvs-sel" id="newTypeFam">${Object.entries({video:'영상 단자',audio:'오디오 단자',lens:'렌즈 마운트',battery:'배터리 규격',mount:'조명 마운트'}).map(([k,l])=>`<option value="${k}">${l}</option>`).join('')}</select>
            <input class="rvs-input" id="newTypeVal" placeholder="예: DisplayPort">
            <button class="rvs-mini pri" onclick="addType()">추가</button>
        </div>
        <div class="rvs-note">${Object.entries(CFG.types).map(([k,v]) => `${k}: ${v.join(', ')}`).join(' · ')}</div>
    </div>
    <div class="rvs-bar" style="margin-bottom:10px">
        <div class="rvs-chips">${[['miss',`미입력 ${cnt.miss}`],['ok',`입력됨 ${cnt.ok}`],['all',`전체 ${names.length}`]].map(([k,l]) =>
            `<button class="rvs-fchip ${f===k?'on':''}" onclick="UI.specFilter='${k}'; render()">${l}</button>`).join('')}</div>
        <input class="rvs-input" id="rvsSpecQ" placeholder="제품명 검색" value="${_e(UI.specQ)}" oninput="rvsSearch(this,'specQ')" style="min-width:200px">
    </div>
    <div class="rvs-card rvs-tw"><table class="rvs-t" style="min-width:720px">
        <thead><tr><th>제품과 규격</th><th>출처</th><th>상태</th><th></th></tr></thead>
        <tbody>${rows || '<tr><td colspan="4" style="color:var(--text-muted)">해당 제품이 없습니다.</td></tr>'}</tbody></table></div>
    `;
}
function addType() {
    const fam = document.getElementById('newTypeFam').value, val = document.getElementById('newTypeVal').value.trim();
    if (!val) return;
    if (!CFG.types[fam].includes(val)) CFG.types[fam].push(val);
    markDirty(); render();
}
function openSpec(name) {
    const p = PRODUCTS.find(x => x.name === name);
    UI.specEdit = { name, path: p ? p.path : '', spec: JSON.parse(JSON.stringify(specInfoOf(p || {name, path:''}).spec)) };
    render();
}
function specForm(sp, fields) {
    return fields.map(f => {
        const d = FIELDS[f];
        if (d.bool) return `<label style="display:flex; align-items:center; gap:6px; font-size:14px"><input type="checkbox" ${sp[f] ? 'checked' : ''} onchange="UI.specEdit.spec['${f}']=this.checked; render()"> ${d.label}</label>`;
        if (d.cable) {
            const c = sp.cable || { a:'', b:'', kind:'cable' };
            const all = [...CFG.types.video, ...CFG.types.audio];
            const sel = k => `<select class="rvs-sel" onchange="(UI.specEdit.spec.cable=UI.specEdit.spec.cable||{a:'',b:'',kind:'cable'})['${k}']=this.value; render()"><option value="">선택</option>${all.map(x => `<option ${c[k]===x?'selected':''}>${_e(x)}</option>`).join('')}</select>`;
            return `<div class="rvs-fld"><span>${d.label}</span><div class="rvs-acts">${sel('a')} ↔ ${sel('b')}
                <select class="rvs-sel" onchange="(UI.specEdit.spec.cable=UI.specEdit.spec.cable||{a:'',b:'',kind:'cable'}).kind=this.value; render()">
                    <option value="cable" ${c.kind==='cable'?'selected':''}>케이블</option><option value="adapter" ${c.kind==='adapter'?'selected':''}>젠더 (단독 연결 불가)</option><option value="none" ${c.kind==='none'?'selected':''}>연결용 아님</option>
                </select></div></div>`;
        }
        const vals = d.port ? (sp[f] ? sp[f].t || [] : []) : (sp[f] || []);
        const toggles = CFG.types[d.fam].map(x => `<button type="button" class="rvs-mini ${vals.includes(x)?'on':''}" onclick="specTog('${f}','${_e(x)}',${d.port?'true':'false'})">${_e(x)}</button>`).join('');
        const qty = d.port && d.qty ? `<label style="display:flex; align-items:center; gap:5px; font-size:14px">${d.qty}
            <input class="rvs-input" type="number" min="1" value="${sp[f] && sp[f].n || 1}" style="width:64px" onchange="(UI.specEdit.spec['${f}']=UI.specEdit.spec['${f}']||{t:[],n:1}).n=Math.max(1,+this.value||1)"></label>` : '';
        return `<div class="rvs-fld"><span>${d.label}</span><div class="rvs-acts">${toggles}${qty}</div></div>`;
    }).join('');
}
function specTog(f, val, isPort) {
    const sp = UI.specEdit.spec;
    if (isPort) {
        const cur = sp[f] = sp[f] || { t: [], n: 1 };
        const i = cur.t.indexOf(val);
        i >= 0 ? cur.t.splice(i, 1) : cur.t.push(val);
    } else {
        const cur = sp[f] = Array.isArray(sp[f]) ? sp[f] : [];
        const i = cur.indexOf(val);
        i >= 0 ? cur.splice(i, 1) : cur.push(val);
    }
    render();
}
function saveSpec() {
    const e = UI.specEdit;
    CFG.specsByName[e.name] = e.spec;
    UI.specEdit = null;
    markDirty(); render();
}
function clearSpec() {
    delete CFG.specsByName[UI.specEdit.name];
    UI.specEdit = null;
    markDirty(); render();
}

// ── 드로어 ──
function renderDrawer() {
    const el = document.getElementById('rvsDrawer');
    if (UI.specEdit) {
        const e = UI.specEdit, tpl = tmplOf(e.path);
        const extra = Object.keys(e.spec).filter(f => FIELDS[f] && !tpl.includes(f));
        const fields = [...tpl, ...extra];
        const add = Object.keys(FIELDS).filter(f => !fields.includes(f));
        el.innerHTML = `<div class="rvs-drawerbg" onclick="UI.specEdit=null; render()"></div>
        <div class="rvs-drawer" role="dialog" aria-label="규격 입력">
            <div class="rvs-bar"><h3 style="margin:0; font-size:16px">규격 입력</h3><button class="rvs-mini" onclick="UI.specEdit=null; render()">닫기</button></div>
            <b>${_e(e.name)}</b><div class="rvs-note">${_e((e.path||'').split('>').join(' › '))}</div>
            ${specForm(e.spec, fields) || '<p class="rvs-lead">이 카테고리에는 정해진 규격 칸이 없습니다. 아래에서 칸을 추가하세요.</p>'}
            <div class="rvs-fld"><span>다른 규격 칸 추가</span>
                <select class="rvs-sel" onchange="if(this.value){ UI.specEdit.spec[this.value] = FIELDS[this.value].bool ? false : FIELDS[this.value].cable ? {a:'',b:'',kind:'cable'} : FIELDS[this.value].port ? {t:[],n:1} : []; render(); }">
                    <option value="">선택</option>${add.map(f => `<option value="${f}">${FIELDS[f].label}</option>`).join('')}</select></div>
            <div class="rvs-acts">
                <button class="rvs-mini pri" onclick="saveSpec()">저장</button>
                ${CFG.specsByName[e.name] ? '<button class="rvs-mini danger" onclick="clearSpec()">제품 지정 지우기 (카테고리 기본으로)</button>' : ''}
            </div>
        </div>`;
        return;
    }
    const d = UI.drawer;
    if (!d) { el.innerHTML = ''; return; }
    const need = d.type === 'need';
    const groups = [...new Set(CFG.rules.map(r => r.group || '기타'))];
    el.innerHTML = `<div class="rvs-drawerbg" onclick="UI.drawer=null; render()"></div>
    <div class="rvs-drawer" role="dialog" aria-label="규칙 편집">
        <div class="rvs-bar"><h3 style="margin:0; font-size:16px">${d._new ? '새 ' : ''}${need ? '품목 규칙' : '확인 질문'}</h3><button class="rvs-mini" onclick="UI.drawer=null; render()">닫기</button></div>
        <label class="rvs-fld"><span>묶음</span><input list="rvsGroups" value="${_e(d.group)}" onchange="dSet('group', this.value)">
            <datalist id="rvsGroups">${groups.map(g => `<option value="${_e(g)}">`).join('')}</datalist></label>
        <div class="rvs-fld"><span>언제: 이 역할의 제품이 견적에 있으면</span>${roleToggles('on', d.on)}</div>
        ${need ? `
        <div class="rvs-fld"><span>무엇이: 이 역할의 제품이 필요합니다</span>${roleToggles('need', d.need || [])}</div>
        <div class="rvs-acts">
            <label class="rvs-fld"><span>얼마나</span><select onchange="dSet('mode', this.value); render()">
                <option value="perUnit" ${d.mode==='perUnit'?'selected':''}>대수만큼</option><option value="atLeast" ${d.mode==='atLeast'?'selected':''}>최소 수량</option></select></label>
            <label class="rvs-fld"><span>${d.mode==='perUnit'?'1대당 개수':'최소 개수'}</span><input type="number" min="1" value="${d.amt}" style="width:90px" onchange="dSet('amt', +this.value)"></label>
        </div>
        <label class="rvs-fld"><span>경고에 표시할 이름</span><input value="${_e(d.what)}" placeholder="예: 캡처보드" onchange="dSet('what', this.value)"></label>
        <label class="rvs-fld"><span>이유</span><textarea rows="2" placeholder="작성자에게 보여줄 이유" onchange="dSet('why', this.value)">${_e(d.why)}</textarea></label>` : `
        <div class="rvs-acts">
            <label class="rvs-fld"><span>언제 물을까요</span><select onchange="dSet('cond', this.value); render()">
                <option value="always" ${d.cond==='always'?'selected':''}>항상</option>
                <option value="short" ${d.cond==='short'?'selected':''}>비교 역할보다 적으면</option>
                <option value="exceeds" ${d.cond==='exceeds'?'selected':''}>비교 역할보다 많으면</option></select></label>
            <label class="rvs-fld"><span>최소 수량</span><input type="number" min="1" value="${d.min || 1}" style="width:90px" onchange="dSet('min', +this.value)"></label>
        </div>
        ${d.cond !== 'always' ? `<div class="rvs-fld"><span>비교 역할</span>${roleToggles('cmp', d.cmp || [])}</div>` : ''}
        <label class="rvs-fld"><span>질문</span><input value="${_e(d.title)}" onchange="dSet('title', this.value)"><small>{n}은 수량, {d}는 차이로 바뀝니다.</small></label>
        <label class="rvs-fld"><span>설명</span><textarea rows="2" onchange="dSet('desc', this.value)">${_e(d.desc)}</textarea></label>
        <label class="rvs-fld"><span>답변 선택지 (쉼표로 구분)</span><input value="${_e(d.choicesTxt)}" onchange="dSet('choicesTxt', this.value)"></label>`}
        <div class="rvs-fld"><span>추천 제품 (여러 개 가능 — 후보 목록 맨 위에 입력 순서대로 표시)</span>
            <div class="rvs-acts">${(d.fix || []).map((n, i) =>
                `<span class="rvs-chip">${_e(n)} <button onclick="UI.drawer.fix.splice(${i},1); render()" title="제거">×</button></span>`).join('') || '<span class="rvs-note">없음</span>'}</div>
            <div class="rvs-acts"><input list="rvsAllProd" id="dFixAdd" placeholder="제품명 검색" style="flex:1; min-width:180px;" onkeydown="if(event.key==='Enter'){event.preventDefault(); dFixAdd();}">
                <button type="button" class="rvs-mini" onclick="dFixAdd()">추가</button></div>
            <datalist id="rvsAllProd">${PRODUCTS.slice(0, 1000).map(p => `<option value="${_e(p.name)}">`).join('')}</datalist></div>
        <div class="rvs-acts"><button class="rvs-mini pri" onclick="saveRule()">저장</button><button class="rvs-mini" onclick="UI.drawer=null; render()">취소</button></div>
    </div>`;
}

render();
</script>
@endpush
