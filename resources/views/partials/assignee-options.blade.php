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
</script>
