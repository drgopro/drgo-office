@extends('layouts.app')

@section('title', '연차 관리 - 닥터고블린 오피스')

@section('content')
<div style="max-width:960px; margin:0 auto; padding:20px 16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0; font-size:19px;">🗂 연차 관리 <span style="font-size:12px; color:var(--text-muted); font-weight:400;">— 입사일·부여 일수·사용 기록 (경영지원)</span></h2>
        <div style="display:flex; gap:8px; align-items:center;">
            <select onchange="location.href='/leave/manage?year='+this.value" style="padding:6px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:13px;">
                @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}년</option>
                @endfor
            </select>
            <a href="/leave?year={{ $year }}" style="padding:6px 14px; border:1px solid var(--border); color:var(--text-muted); border-radius:8px; font-size:13px; text-decoration:none;">내 연차</a>
        </div>
    </div>

    <div style="background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:10px 14px; font-size:12px; color:var(--text-muted); margin-bottom:14px; line-height:1.6;">
        · 사용 기록은 캘린더 <b>휴가/개인</b> 일정에서 '연차 차감'을 체크하면 담당자 기준으로 자동 기록됩니다 (토/일 제외).<br>
        · 공휴일이 낀 연차, 과거 소급분은 아래에서 수동 기록으로 보정하세요. 캘린더 연동 기록은 해당 일정에서만 해제할 수 있습니다.<br>
        · 제안값은 입사일 기준 법정 연차이며 참고용입니다 — 부여 일수는 직접 확정해주세요.
    </div>

    <div id="lvBody" style="display:flex; flex-direction:column; gap:10px;"></div>
</div>

<script>
const LV_YEAR = @json($year);
const LV_ROWS = @json($rows);
const LV_H = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' };
const lvExpanded = new Set();
function lvEsc(s){ return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function lvFmtDays(n){ return (Math.round(n * 10) / 10).toString().replace(/\.0$/, ''); }

// 법정 연차 제안 — 서버(LeaveLedger::suggestGrant)와 같은 규칙을 입력 즉시 계산
function lvSuggest(hireStr, fiscal){
    if(!hireStr) return null;
    const hire = new Date(hireStr + 'T00:00:00');
    if (isNaN(hire) || hire.getFullYear() > LV_YEAR) return null;
    if (hire.getFullYear() === LV_YEAR) {
        const end = new Date(LV_YEAR, 11, 31);
        let m = (end.getFullYear() - hire.getFullYear()) * 12 + (end.getMonth() - hire.getMonth());
        if (end.getDate() < hire.getDate()) m--;
        m = Math.max(0, Math.min(11, m));
        return { days: m, label: `입사 1년 미만 — 월 1일 발생 (올해 최대 ${m}일)` };
    }
    if (fiscal && hire.getFullYear() === LV_YEAR - 1) {
        // 회계연도 — 입사 이듬해 1/1 비례연차 (0.5 단위)
        const worked = Math.floor((new Date(hire.getFullYear(), 11, 31) - hire) / 86400000) + 1;
        const days = Math.round(15 * worked / 365 * 2) / 2;
        return { days, label: `회계연도 비례연차 — 전년 재직 ${worked}일 기준 ${days}일` };
    }
    const sy = LV_YEAR - hire.getFullYear();
    const days = Math.min(25, 15 + Math.floor(Math.max(0, sy - 1) / 2));
    return { days, label: `${fiscal ? '회계연도 기준 근속' : '근속'} ${sy}년차 — 법정 ${days}일` };
}
const __lvSug = {};
function lvSugHtml(s){ return s ? `<span style="color:var(--accent);">제안: ${lvFmtDays(s.days)}일 (${lvEsc(s.label)})</span>` : ''; }
// 입사일 입력 즉시 — 제안 라벨 갱신 + 부여 칸이 비어 있으면 제안값 자동 채움
function lvHireChanged(id){
    const s = lvSuggest(document.getElementById('lvHire-' + id).value, document.getElementById('lvFiscal-' + id)?.checked);
    __lvSug[id] = s;
    const wrap = document.getElementById('lvSugWrap-' + id);
    if (wrap) wrap.innerHTML = lvSugHtml(s);
    const btn = document.getElementById('lvSugBtn-' + id);
    if (btn) btn.style.display = s ? '' : 'none';
    const grant = document.getElementById('lvGrant-' + id);
    if (grant) {
        grant.placeholder = s ? lvFmtDays(s.days) : '';
        if (s && grant.value.trim() === '') grant.value = s.days;
    }
}
function lvApplySug(id){
    const s = __lvSug[id];
    if (s) document.getElementById('lvGrant-' + id).value = s.days;
}

function lvRender(){
    document.getElementById('lvBody').innerHTML = LV_ROWS.map(r => {
        const open = lvExpanded.has(r.user_id);
        const remainColor = (r.remaining ?? 1) < 0 ? '#dc2626' : 'var(--accent)';
        return `<div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden;">
            <div style="display:flex; align-items:center; gap:12px; padding:12px 16px; cursor:pointer; flex-wrap:wrap;" onclick="lvToggle(${r.user_id})">
                <b style="font-size:14px; min-width:80px;">${lvEsc(r.name)}</b>
                <span style="font-size:12px; color:var(--text-muted);">입사 ${r.hire_date || '미등록'}</span>
                <span style="font-size:12.5px;">부여 <b>${r.granted !== null ? lvFmtDays(r.granted)+'일' : '미확정'}</b></span>
                <span style="font-size:12.5px;">사용 <b>${lvFmtDays(r.used)}일</b></span>
                <span style="font-size:12.5px;">잔여 <b style="color:${remainColor};">${r.remaining !== null ? lvFmtDays(r.remaining)+'일' : '—'}</b></span>
                <span style="margin-left:auto; font-size:12px; color:var(--text-muted);">${open ? '▾ 접기' : '▸ 관리'}</span>
            </div>
            ${open ? lvDetail(r) : ''}
        </div>`;
    }).join('');
}
function lvDetail(r){
    const inp = 'padding:7px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:12.5px;';
    __lvSug[r.user_id] = r.suggest || lvSuggest(r.hire_date, r.fiscal_leave);
    const s = __lvSug[r.user_id];
    return `<div style="border-top:1px solid var(--border); padding:14px 16px; display:flex; flex-direction:column; gap:14px;" onclick="event.stopPropagation()">
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <label style="display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--text-muted);">입사일
                <input type="date" id="lvHire-${r.user_id}" value="${r.hire_date || ''}" style="${inp}" onchange="lvHireChanged(${r.user_id})" oninput="lvHireChanged(${r.user_id})"></label>
            <label style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text); cursor:pointer; padding-bottom:8px;" title="체크하면 매년 1월 1일 기준으로 연차를 기산합니다 (입사 이듬해는 비례연차 제안)">
                <input type="checkbox" id="lvFiscal-${r.user_id}" ${r.fiscal_leave ? 'checked' : ''} onchange="lvHireChanged(${r.user_id})" style="width:14px; height:14px; accent-color:var(--accent); cursor:pointer;">
                회계연도(1/1) 기준</label>
            <label style="display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--text-muted);">${LV_YEAR}년 부여 일수 <span id="lvSugWrap-${r.user_id}">${lvSugHtml(s)}</span>
                <div style="display:flex; gap:6px;">
                    <input type="number" step="0.5" min="0" id="lvGrant-${r.user_id}" value="${r.granted ?? ''}" placeholder="${s ? lvFmtDays(s.days) : ''}" style="${inp} width:90px;">
                    <button id="lvSugBtn-${r.user_id}" onclick="lvApplySug(${r.user_id})" style="${inp} cursor:pointer; white-space:nowrap; ${s ? '' : 'display:none;'}">제안값</button>
                </div></label>
            <label style="display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--text-muted); flex:1; min-width:140px;">메모
                <input id="lvGrantNote-${r.user_id}" value="${lvEsc(r.grant_note || '')}" maxlength="300" placeholder="이월 2일 포함 등" style="${inp}"></label>
            <button onclick="lvSave(${r.user_id}, this)" style="padding:8px 18px; background:var(--accent); color:var(--accent-text, #fff); border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">저장</button>
        </div>
        <div>
            <div style="font-size:12.5px; font-weight:700; margin-bottom:6px;">${LV_YEAR}년 사용 내역</div>
            ${r.usages.length ? r.usages.map(u => `
                <div style="display:flex; align-items:center; gap:10px; padding:5px 2px; border-bottom:1px solid var(--border); font-size:12.5px; flex-wrap:wrap;">
                    <span style="font-weight:600;">${u.used_on}</span>
                    <span style="font-size:11px; padding:1px 7px; border-radius:10px; background:var(--surface2);">${lvEsc(u.type)} ${lvFmtDays(u.days)}일</span>
                    ${u.from_calendar ? '<span style="font-size:11px; color:var(--text-muted);">캘린더 연동</span>' : ''}
                    <span style="color:var(--text-muted); flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${lvEsc(u.note || '')}</span>
                    ${u.from_calendar ? '' : `<button onclick="lvDelUsage(${u.id})" style="background:none; border:1px solid var(--border); color:#dc2626; padding:2px 9px; border-radius:6px; font-size:11px; cursor:pointer;">삭제</button>`}
                </div>`).join('') : '<div style="font-size:12px; color:var(--text-muted); padding:6px 0;">사용 내역이 없습니다.</div>'}
            <div style="display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; align-items:center;">
                <input type="date" id="lvUseDate-${r.user_id}" style="${inp}">
                <select id="lvUseDays-${r.user_id}" style="${inp}">
                    <option value="1">연차 1일</option>
                    <option value="0.5">반차 0.5일</option>
                </select>
                <input id="lvUseNote-${r.user_id}" placeholder="메모 (공휴일 보정, 소급 등)" maxlength="300" style="${inp} flex:1; min-width:120px;">
                <button onclick="lvAddUsage(${r.user_id})" style="padding:7px 14px; border:1px solid var(--accent); color:var(--accent); background:none; border-radius:8px; font-size:12px; cursor:pointer;">수동 기록 추가</button>
            </div>
        </div>
    </div>`;
}
function lvToggle(id){ lvExpanded.has(id) ? lvExpanded.delete(id) : lvExpanded.add(id); lvRender(); }
async function lvSave(id, btn){
    btn.disabled = true;
    const hire = document.getElementById('lvHire-' + id).value;
    const days = document.getElementById('lvGrant-' + id).value;
    const note = document.getElementById('lvGrantNote-' + id).value.trim();
    const r1 = await fetch(`/api/leave/users/${id}/hire-date`, { method: 'PATCH', headers: LV_H, body: JSON.stringify({ hire_date: hire || null, fiscal_leave: !!document.getElementById('lvFiscal-' + id)?.checked }) });
    let ok = r1.ok;
    if (days !== '') {
        const r2 = await fetch(`/api/leave/users/${id}/grant`, { method: 'PUT', headers: LV_H, body: JSON.stringify({ year: LV_YEAR, days: parseFloat(days), note }) });
        ok = ok && r2.ok;
    }
    if (!ok) { alert('저장에 실패했습니다.'); btn.disabled = false; return; }
    location.reload();
}
async function lvAddUsage(id){
    const used_on = document.getElementById('lvUseDate-' + id).value;
    if (!used_on) { alert('날짜를 선택해주세요.'); return; }
    const days = parseFloat(document.getElementById('lvUseDays-' + id).value);
    const res = await fetch(`/api/leave/users/${id}/usages`, { method: 'POST', headers: LV_H, body: JSON.stringify({
        used_on, days, type: days === 0.5 ? '반차' : '연차',
        note: document.getElementById('lvUseNote-' + id).value.trim(),
    }) });
    if (!res.ok) { alert('추가에 실패했습니다.'); return; }
    location.reload();
}
async function lvDelUsage(usageId){
    if (!confirm('이 사용 기록을 삭제할까요?')) return;
    const res = await fetch(`/api/leave/usages/${usageId}`, { method: 'DELETE', headers: LV_H });
    if (!res.ok) { const e = await res.json().catch(() => ({})); alert(e.message || '삭제에 실패했습니다.'); return; }
    location.reload();
}
lvRender();
</script>
@endsection
