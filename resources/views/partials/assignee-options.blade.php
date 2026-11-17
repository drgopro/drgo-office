{{-- 담당자 선택 — 의뢰자 등록/수정·프로젝트 생성/수정 공용. 비워 두면(등록 시) 작성자가 담당자가 된다. --}}
@php
    $__assignees = \App\Models\User::where('is_active', true)->orderBy('display_name')->get(['id', 'display_name', 'username'])
        ->map(fn ($u) => ['id' => $u->id, 'name' => $u->display_name ?: $u->username])->values();
@endphp
<script>
window.ASSIGNEE_OPTIONS = @json($__assignees);
window.ASSIGNEE_ME = {{ (int) auth()->id() }};
/** select 값 → 숫자 id 또는 null */
window.assigneeValue = function (id) { const v = document.getElementById(id)?.value; return v ? Number(v) : null; };
/**
 * 담당자 select HTML
 * @param {string} id  select id
 * @param {number|null} selected  선택 값 (없으면 나)
 * @param {string} cls  class
 * @param {string} style  inline style
 * @param {boolean} keepEmpty  수정 화면 — 담당자가 없던 건은 '미지정'을 선택한 채로 둔다 (나로 몰래 바뀌지 않게)
 */
window.assigneeSelectHtml = function (id, selected, cls, style, keepEmpty) {
    const none = selected === undefined || selected === null || selected === '';
    const sel = none ? (keepEmpty ? null : window.ASSIGNEE_ME) : Number(selected);
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const opts = (window.ASSIGNEE_OPTIONS || []).map(u => `<option value="${u.id}" ${u.id === sel ? 'selected' : ''}>${esc(u.name)}${u.id === window.ASSIGNEE_ME ? ' (나)' : ''}</option>`).join('');
    return `<select id="${id}" class="${cls || ''}" style="${style || ''}" title="담당자 — 기본은 작성자">${keepEmpty ? `<option value="" ${sel === null ? 'selected' : ''}>미지정</option>` : ''}${opts}</select>`;
};
/**
 * 추가 담당자(복수) 체크 칩 HTML — 프로젝트 전용. 대표 담당자는 위 select 로 고르고, 함께 맡는 사람을 여기서 체크한다.
 * @param {string} id  감싸는 요소 id (값 수집 시 사용)
 * @param {number[]} selectedIds  체크해 둘 사용자 id
 */
window.assigneeExtraHtml = function (id, selectedIds) {
    const picked = (selectedIds || []).map(Number);
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const chips = (window.ASSIGNEE_OPTIONS || []).map(u => `<label style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border:1px solid var(--border); border-radius:8px; font-size:13px; cursor:pointer;"><input type="checkbox" value="${u.id}" ${picked.includes(u.id) ? 'checked' : ''} style="width:13px; height:13px;">${esc(u.name)}</label>`).join('');
    return `<div id="${id}" style="display:flex; flex-wrap:wrap; gap:6px;">${chips}</div>`;
};
/** 추가 담당자 체크 값 → id 배열 (대표 담당자와 겹치는 값은 서버가 제외) */
window.assigneeExtraValues = function (id) {
    return [...document.querySelectorAll(`#${CSS.escape(id)} input:checked`)].map(c => Number(c.value));
};
</script>
