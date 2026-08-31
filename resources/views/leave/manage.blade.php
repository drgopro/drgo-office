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
    return `<div style="border-top:1px solid var(--border); padding:14px 16px; display:flex; flex-direction:column; gap:14px;" onclick="event.stopPropagation()">
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <label style="display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--text-muted);">입사일
                <input type="date" id="lvHire-${r.user_id}" value="${r.hire_date || ''}" style="${inp}"></label>
            <label style="display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--text-muted);">${LV_YEAR}년 부여 일수 ${r.suggest ? `<span style="color:var(--accent);">제안: ${lvFmtDays(r.suggest.days)}일 (${lvEsc(r.suggest.label)})</span>` : ''}
                <div style="display:flex; gap:6px;">
                    <input type="number" step="0.5" min="0" id="lvGrant-${r.user_id}" value="${r.granted ?? ''}" placeholder="${r.suggest ? lvFmtDays(r.suggest.days) : ''}" style="${inp} width:90px;">
                    ${r.suggest ? `<button onclick="document.getElementById('lvGrant-${r.user_id}').value=${r.suggest.days}" style="${inp} cursor:pointer; white-space:nowrap;">제안값</button>` : ''}
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
    const r1 = await fetch(`/api/leave/users/${id}/hire-date`, { method: 'PATCH', headers: LV_H, body: JSON.stringify({ hire_date: hire || null }) });
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
