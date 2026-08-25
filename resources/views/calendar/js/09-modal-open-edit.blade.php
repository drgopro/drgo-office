{{-- 폼 초기화·모달 열기/상세/편집·복원 --}}
// ── 폼 초기화 ──
function resetModalForm(){
    // 미팅/내방 옵션 체크 초기화
    document.querySelectorAll('#visitOptsList input:checked').forEach(cb=>cb.checked=false);
    // 라디오 그룹 초기화
    ['g_platform_group','g_career_group','g_source_group','g_topic_group','g_budget_group','g_req_topic_group','g_paid_group','g_order_group','g_balance_group','teal_mode_group'].forEach(id=>clearRadio(id));
    // 기본값 세팅
    setRadio('g_career_group','처음');
    setRadio('g_paid_group','미결제');
    setRadio('g_order_group','X');
    setRadio('g_balance_group','X');
    setRadio('teal_mode_group','remote');
    // schedEventOpts / scheduleOpts / specialOpts 초기화
    document.querySelectorAll('#schedEventOpts .special-opt-btn, #scheduleOpts .sched-opt-btn, #specialOpts .special-opt-btn').forEach(b=>b.classList.remove('active'));
    if(typeof updateCarReasonUI==='function') updateCarReasonUI();
    updateSchedOptDesc();
    document.getElementById('schedReasonWrap').style.display='none';
    document.getElementById('schedAfterReason').value='';
    // 조건부 필드 숨기기
    document.querySelectorAll('.conditional-field').forEach(f=>f.classList.remove('visible'));
    // 텍스트 초기화
    ['g_nickname','g_name','g_phone','g_platform_etc','g_source_ref','g_topic_etc','g_budget_etc','g_equipment','g_req_topic_etc','g_req_detail','g_special','g_estimate_amount','g_balance_amount','t_remote_name','t_remote_platform','t_remote_content','t_studio_name','t_studio_platform','t_studio_content','t_desc','commonDesc','commonHandoverNote','modalLocation','modalLocationDetail','modalAddress','moveFromLocation','moveFromDetail','moveFromAddress','schedAfterReason'].forEach(id=>{const el=document.getElementById(id);if(el) el.value='';});
    // 의뢰자/프로젝트/견적서/잠금/잔금
    linkedClientId=null;linkedProjectId=null;
    resetLinkedClientDetail(); // 의뢰자 주소 버튼 숨김 + 이전 의뢰자 상세 제거
    document.getElementById('linkedClientInfo').style.display='none';
    document.getElementById('linkedClientName').textContent=''; // 이전 일정의 의뢰자명이 다음 일정에 새는 것 방지
    document.getElementById('projectSelectWrap').style.display='none';
    {const psel=document.getElementById('projectSelect'); if(psel) psel.innerHTML='';} // 이전 일정의 프로젝트가 다음 일정에 새는 것 방지
    syncProjectPaymentFields(); // 연동 해제 — 결제/잔금 수기 입력 복원
    document.getElementById('clientSearchInput').value='';
    linkedEstimateId=null;
    document.getElementById('linkedEstimateInfo').style.display='none';
    document.getElementById('catQuickPick')?.remove(); // 카테고리 빠른 변경 팝업 잔여 제거
    // 대여 이력 등록 초기화 (표시 여부는 setColor→updateBrRentalUI가 결정)
    {const bc=document.getElementById('brRentalChk'); if(bc) bc.checked=false;
     const bf=document.getElementById('brRentalFields'); if(bf) bf.style.display='none';
     const br=document.getElementById('brRentalRoom'); if(br) br.value='';
     const bfee=document.getElementById('brRentalFee'); if(bfee) bfee.value='';}
    isLocked=false; document.getElementById('lockBtn').textContent='☐ 요약'; document.getElementById('lockBtn').classList.remove('locked');
    document.getElementById('lockedBanner').classList.remove('visible');
    document.querySelector('#modalOverlay .modal-body')?.classList.remove('is-locked');
    document.getElementById('balanceBanner').classList.remove('visible');
    isAllDay=false; document.getElementById('alldayTrack').classList.remove('on');
    {const xw=document.getElementById('excludeWeekendsChk'); if(xw) xw.checked=false;}
    shipIconOverride=null; renderShipIconButtons();
    // 3뎁스 세팅 항목 선택 초기화
    reqItems=[]; projReqItems=[]; projReqLoadedFor=null; renderReqView();
    // 이사세팅 출발지 상태 초기화
    { const nf=document.getElementById('moveNoFrom'); if(nf) nf.checked=false; onMoveNoFromToggle(); }
    document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='');
    document.getElementById('notifSelect').value='60';
    // 반복 UI 초기화 (체크 해제 상태로, 편집 시 loadEventToModal이 그룹 소속이면 숨김)
    {const rc=document.getElementById('repeatChk'); if(rc) rc.checked=false;
     const rf=document.getElementById('repeatFreq'); if(rf) rf.value='daily';
     const ru=document.getElementById('repeatUntil'); if(ru) ru.value='';
     const ri=document.getElementById('repeatInterval'); if(ri) ri.value='2';
     const rg=document.getElementById('repeatGroup'); if(rg){rg.querySelector('.notif-row').style.display='flex';}
     const rn=document.getElementById('repeatEditNote'); if(rn) rn.style.display='none';
     onRepeatChkToggle();}
    editingRepeatOrig=null;
    lsShipOpen=false;
    // 입력 재활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input').forEach(el=>{el.disabled=false;});
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='';});
    resetAttachments();
    // 배송 현황 초기화 (편집 진입 시 loadShipments가 다시 채움)
    shipCache={shipments:[],carriers:{}};
    const shipList=document.getElementById('shipmentList'); if(shipList) shipList.innerHTML='';
    const shipBadge=document.getElementById('shipSummaryBadge'); if(shipBadge) shipBadge.textContent='';
    updateShipmentSectionVisibility();
    updateModalShipBadge();
    toggleShipmentBody(false); // 배송 현황은 기본 접힘
    assigneePanelOpen=false;
    document.getElementById('assigneeList').style.display='none';
    notifyPanelOpen=false;
    {const nl=document.getElementById('notifyList'); if(nl) nl.style.display='none';}
    updateNotifyBtn();
}

// ── 모달 열기 ──
function openNewModal(dateStr,timeStr,endStr){
    editingId=null; selectedAssignees=[]; selectedNotifyAssignees=[]; viewMode=false;
    resetModalForm();
    renderChildrenCard(); // 새 일정: 이전에 열었던 일정의 세부 일정 카드 잔상 제거
    setEditModeUI();
    setColor('gold');
    document.getElementById('modalTitle').value='';
    // 날짜 — 미지정 시 오늘로 기본 지정 (범위 선택 시 endStr로 종료일 프리필)
    const ds=dateStr||todayStr();
    const de=endStr||ds;
    document.getElementById('startDate').value=ds;
    document.getElementById('endDate').value=de;
    document.getElementById('goldStartDate').value=ds;
    document.getElementById('goldEndDate').value=de;
    // 시간
    const st=timeStr||'13:00';
    const etH=String(Math.min(parseInt(st)+1,23)).padStart(2,'0');
    const et=etH+':00';
    document.getElementById('startTime').value=st;document.getElementById('startTimeTrigger').textContent=st;
    document.getElementById('endTime').value=et;document.getElementById('endTimeTrigger').textContent=et;
    document.getElementById('goldStartTime').value=st;document.getElementById('goldStartTimeTrigger').textContent=st;
    document.getElementById('goldEndTime').value=et;document.getElementById('goldEndTimeTrigger').textContent=et;
    // 날짜 배지
    const d=dateStr?new Date(dateStr):new Date();
    document.getElementById('modalDateBadge').textContent=`${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS_KO[d.getDay()]})`;
    document.getElementById('btnDelete').style.display='none';
    document.getElementById('btnLog').style.display='none';
    updateAssigneeBtn();
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(calRefreshAutoGrow, 0); // 상세 내용 높이 자동 맞춤
    pushCalModalHistory();
    setTimeout(()=>document.getElementById('modalTitle').focus(),50);
}

// ── 상세 모달 ──
const COLOR_LABELS = (function(){
    const o = {holiday:'공휴일'};
    const cats = window.CALENDAR_CATEGORIES || {};
    Object.keys(cats).forEach(k => { o[k] = cats[k].label; });
    return o;
})();
const FIELD_LABELS = {title:'제목',start_date:'시작일',end_date:'종료일',start_time:'시작시간',end_time:'종료시간',is_all_day:'종일',color:'유형',client_name:'의뢰자',address:'주소',location:'장소',description:'상세설명',special_note:'특이사항',handover_note:'전달사항',is_locked:'잠금',is_private:'비공개',request_data:'의뢰자정보',remote_data:'원격정보',gold_data:'의뢰자정보',teal_data:'원격정보',/* 구키 — 과거 이력 스냅샷용 */notif_minutes:'알림(분)',sched_opt:'세부유형',sched_event_opts:'세부옵션',special_opts:'특수옵션',sched_after_days:'AS일수',sched_after_date:'AS만료일',sched_after_reason:'AS사유',assignees:'담당자',notify_assignees:'알림 대상',completed_at:'완료시각'};

// 변경 로그 값을 사람이 읽기 좋게 변환
function fmtLogValue(key, val) {
    if (val === null || val === undefined || val === '') return '—';
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) {
        const d = new Date(val);
        if (isNaN(d.getTime())) return val;
        return d.toLocaleDateString('ko-KR',{timeZone:'Asia/Seoul',year:'numeric',month:'2-digit',day:'2-digit'}).replace(/\.\s?/g,'-').replace(/-$/,'');
    }
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    if (typeof val === 'string' && /^\d{1,2}:\d{2}/.test(val)) return val.slice(0,5);
    if (val === true || val === 'true' || val === 1 || val === '1') return '예';
    if (val === false || val === 'false' || val === 0 || val === '0') return '아니오';
    if (key === 'color' && COLOR_LABELS[val]) return COLOR_LABELS[val];
    if (typeof val === 'object') {
        if (Array.isArray(val)) {
            if (val.length === 0) return '(없음)';
            if (val.every(x => typeof x === 'number')) return `${val.length}명`;
            const names = val.map(o => o?.name || o?.title).filter(Boolean).slice(0,3);
            if (names.length) return names.join(', ') + (val.length > 3 ? ` 외 ${val.length-3}` : '');
            return `(${val.length}개 항목)`;
        }
        if (val.name) return String(val.name);
        if (val.title) return String(val.title);
        return '(상세 변경)';
    }
    return String(val);
}
let detailEvent = null;

let viewMode = false; // true: 상세보기(읽기전용), false: 편집

// ── 보기 모드 즉시 적용 (수정 모드 진입 없이 담당자/카테고리 변경) ──
async function quickUpdateEvent(payload){
    if(!editingId) return false;
    const res=await fetch(`/api/events/${editingId}`,{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(payload)
    });
    if(!res.ok){
        const err=await res.json().catch(()=>({}));
        showCalToast(err.message||'변경 실패');
        return false;
    }
    return true;
}

// 카테고리 빠른 변경 팝업 (보기 모드에서 typeBadge 클릭)
function toggleCategoryQuickPick(){
    if(!viewMode||!editingId||!canEditCalendar) return;
    const existing=document.getElementById('catQuickPick');
    if(existing){ existing.remove(); return; }
    const badge=document.getElementById('typeBadge');
    const pop=document.createElement('div');
    pop.id='catQuickPick';
    pop.className='cat-quick-pick';
    Object.entries(window.CALENDAR_CATEGORIES||{}).forEach(([k,c])=>{
        const chip=document.createElement('div');
        chip.className='color-dot'+(k===currentColor?' active':'');
        chip.dataset.color=k;
        chip.textContent=c.label||k;
        chip.style.pointerEvents='auto';
        chip.onclick=async e=>{
            e.stopPropagation();
            pop.remove();
            if(k===currentColor) return;
            if(!(await quickUpdateEvent({color:k}))) return;
            setColor(k); // 배지/칩 표시 갱신 (요약 뷰에서는 폼이 숨겨져 있어 부작용 없음)
            if(detailEvent) detailEvent.color=k;
            if(isLocked) renderLockSummary();
            showCalToast('카테고리가 변경되었습니다');
            loadEvents();
        };
        pop.appendChild(chip);
    });
    badge.insertAdjacentElement('afterend',pop);
    setTimeout(()=>document.addEventListener('click',function close(e){
        if(!pop.contains(e.target)&&e.target!==badge){ pop.remove(); document.removeEventListener('click',close); }
    }),0);
}

// 완료 버튼(푸터 + 모달 옆 외부) 상태 갱신. showFooter=true면 푸터 버튼도 노출(보기 모드).
function updateCompleteUI(showFooter){
    const existing=!!editingId;
    const done=!!(detailEvent&&detailEvent.completed_at);
    const f=document.getElementById('btnComplete');
    if(f){
        f.style.display=(showFooter&&existing)?'':'none';
        f.textContent=done?'✓ 완료됨 (해제)':'✓ 완료';
        f.classList.toggle('done',done);
    }
    const ext=document.getElementById('extCompleteBtn');
    if(ext){
        ext.style.display=existing?'flex':'none'; // 보기/편집 모드 모두 노출(기존 일정만)
        ext.textContent=done?'✓':'완료';
        ext.title=done?'완료됨 — 클릭 시 해제':'완료 처리';
        ext.classList.toggle('done',done);
    }
}

function setViewModeUI(){
    // 모든 입력 비활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input, #modalOverlay select').forEach(el=>{el.disabled=true;});
    document.querySelectorAll('#modalOverlay .time-picker-trigger').forEach(el=>{el.style.pointerEvents='none'; el.style.opacity='0.6';});
    document.querySelectorAll('#modalOverlay input[type="date"]').forEach(el=>{el.readOnly=true; el.style.pointerEvents='none'; el.style.opacity='0.6';});
    document.getElementById('alldayToggle').style.pointerEvents='none';
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='none';});
    document.querySelectorAll('#modalOverlay .radio-btn:not([data-always-active])').forEach(b=>{b.style.pointerEvents='none';});
    document.querySelectorAll('#modalOverlay .color-dot').forEach(b=>{b.style.pointerEvents='none';});
    // 읽기 모드: 카테고리는 상단 배지(typeBadge)로 이미 표시되므로 선택 칩 줄 전체 숨김(중복 방지) + 공휴일 지정 버튼 숨김
    // 배지 클릭으로 빠른 변경 가능 표시
    {const tb=document.getElementById('typeBadge'); if(tb&&canEditCalendar){ tb.classList.add('quick-editable'); tb.title='클릭하여 카테고리 변경'; }}
    const crow=document.getElementById('colorRow'); if(crow) crow.style.display='none';
    const hwrap=document.querySelector('#modalOverlay .holiday-btn-wrap'); if(hwrap) hwrap.style.display='none';
    document.querySelectorAll('#modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn').forEach(b=>{b.style.pointerEvents='none';});
    // 보기/해제 버튼은 항상 활성화
    document.querySelectorAll('#modalOverlay [data-always-active]').forEach(b=>{b.style.pointerEvents='auto';});
    // 보기 모드: '요약'(읽기 요약 뷰)을 기본 ON으로 표시 — 요약 버튼은 항상 노출
    document.getElementById('lockBtn').style.display='';
    applyLockUI();
    document.querySelector('.btn-save-top').style.display='none';
    document.getElementById('btnDelete').style.display='';
    document.getElementById('btnLog').style.display='';
    updateCompleteUI(true); // 푸터 완료 버튼 노출(보기 모드)
    // 외부 버튼을 수정으로
    const extBtn=document.getElementById('modalExternalAction');
    extBtn.textContent='수정';
    extBtn.style.display='';
    extBtn.onclick=()=>{switchToEditMode();};
    // 푸터
    const saveBtn=document.querySelector('.modal-footer .btn-save');
    saveBtn.textContent='수정';
    saveBtn.onclick=()=>{switchToEditMode();};
    // 보기 모드에서는 변경 사유 필드 숨김
    const reasonField=document.getElementById('reasonField');
    if (reasonField) reasonField.style.display='none';
}

function setEditModeUI(){
    // 모든 입력 활성화
    document.querySelectorAll('#modalOverlay .field-input, #modalOverlay .field-textarea, #modalOverlay .dt-input, #modalOverlay .notif-select, #modalOverlay .modal-title-input, #modalOverlay select').forEach(el=>{el.disabled=false;});
    document.querySelectorAll('#modalOverlay .time-picker-trigger').forEach(el=>{el.style.pointerEvents=''; el.style.opacity='';});
    document.querySelectorAll('#modalOverlay input[type="date"]').forEach(el=>{el.readOnly=false; el.style.pointerEvents=''; el.style.opacity='';});
    document.getElementById('alldayToggle').style.pointerEvents='';
    document.querySelectorAll('#modalOverlay .img-upload-zone').forEach(z=>{z.style.display='';});
    document.querySelectorAll('#modalOverlay .radio-btn').forEach(b=>{b.style.pointerEvents='';});
    document.querySelectorAll('#modalOverlay .color-dot').forEach(b=>{b.style.pointerEvents='';});
    // 편집 모드: 카테고리 선택 줄/칩 전체 다시 표시 + 공휴일 지정 버튼 복원 + 배지 빠른 변경 해제
    {const tb=document.getElementById('typeBadge'); if(tb){ tb.classList.remove('quick-editable'); tb.title=''; } document.getElementById('catQuickPick')?.remove();}
    const crow2=document.getElementById('colorRow'); if(crow2) crow2.style.display='';
    document.querySelectorAll('#colorRow .color-dot').forEach(d=>{ d.style.display=''; });
    const hwrap2=document.querySelector('#modalOverlay .holiday-btn-wrap'); if(hwrap2) hwrap2.style.display='';
    document.querySelectorAll('#modalOverlay .special-opt-btn, #modalOverlay .sched-opt-btn').forEach(b=>{b.style.pointerEvents='';});
    // 편집 모드: 요약 해제하여 전체 폼 표시
    isLocked=false; applyLockUI();
    // 버튼 복원
    document.getElementById('lockBtn').style.display='';
    updateCompleteUI(false); // 편집 모드: 푸터 완료 버튼 숨김(외부 완료 버튼은 유지)
    document.querySelector('.btn-save-top').style.display='';
    // 외부 버튼을 저장으로
    const extBtn=document.getElementById('modalExternalAction');
    extBtn.textContent='저장';
    extBtn.onclick=()=>{saveEvent();};
    // 푸터
    const saveBtn=document.querySelector('.modal-footer .btn-save');
    saveBtn.textContent='저장';
    saveBtn.onclick=()=>{saveEvent();};
    // 변경 사유 필드: 수정 모드 + 방문의뢰/원격·방송룸 카테고리만 표시
    updateReasonFieldVisibility();
}

// 변경 사유는 gold(방문의뢰)/teal(원격·방송룸)에서 날짜/시간 변경 시에만 필수
const REASON_COLORS=['gold','teal'];
function updateReasonFieldVisibility(){
    const reasonField=document.getElementById('reasonField');
    if(!reasonField) return;
    const show=!!editingId && !viewMode && REASON_COLORS.includes(currentColor);
    reasonField.style.display=show?'':'none';
    if(!editingId) document.getElementById('modalReason').value='';
}

function openDetailModal(ev) {
    // 하위 일정 클릭 시 부모(장기 일정) 모달을 열어 세부 일정을 한곳에서 관리
    if (ev && ev.parent_id) {
        const p = events.find(e => e.id === ev.parent_id);
        if (p) ev = p;
    }
    if(isGuestUser) return;
    detailEvent = ev;
    viewMode = true;
    openEditModal(ev);
    // 읽기전용 UI 적용
    setTimeout(()=>{
        setViewModeUI();
        // 모바일: 수정 폼이 아니라 자물쇠(읽기 전용 요약) 뷰로 표시
        if(window.innerWidth<=768){
            document.querySelector('#modalOverlay .modal-body')?.classList.add('is-locked');
            renderLockSummary(); // 클래스 적용 후 렌더 — 세부 일정 카드가 요약 쪽에 붙도록
        } else {
            renderChildrenCard(); // 데스크탑: 폼 안 세부 일정 카드
        }
    },0);
}

function switchToEditMode() {
    viewMode = false;
    // 모바일 잠금(읽기전용) 요약 뷰 해제 → 편집 폼 노출
    document.querySelector('#modalOverlay .modal-body')?.classList.remove('is-locked');
    setEditModeUI();
    renderChildrenCard(); // 요약 → 폼 전환 시 세부 일정 카드를 폼 쪽에 렌더
}

function closeDetail() {
    if(viewMode) {
        viewMode = false;
        closeModal();
    } else {
        document.getElementById('detailOverlay').style.display = 'none';
    }
    detailEvent = null;
}

function editFromDetail() {
    if (!detailEvent) return;
    switchToEditMode();
}

function deleteEventFromDetail() {
    if (!detailEvent) return;
    const id = detailEvent.id;
    closeDetail();
    deleteEvent(id);
}

async function openHistoryModal() {
    if (!detailEvent) return;
    document.getElementById('historyOverlay').style.display = 'flex';
    document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">로딩 중...</div>';

    const res = await fetch(`/api/events/${detailEvent.id}/history`, { headers:{'Accept':'application/json'} });
    if (!res.ok) { document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--red);">로드 실패</div>'; return; }
    const data = await res.json();
    // 이전 응답(배열) 호환 + 새 응답({schedule, changes}) 처리
    const items = Array.isArray(data) ? data : (data.changes || []);

    if (!items.length) {
        document.getElementById('historyBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">수정내역이 없습니다.</div>';
        return;
    }

    const ACTION_LABEL = { update:'수정', delete:'삭제', complete:'완료', uncomplete:'완료해제', restore:'복원' };
    const ACTION_COLOR = { update:'var(--accent)', delete:'var(--red)', complete:'var(--green)', uncomplete:'var(--text-muted)', restore:'var(--blue)' };

    document.getElementById('historyBody').innerHTML = items.map(h => {
        const changes = h.changes || {};
        let body = '';
        if (h.action === 'delete' && changes.snapshot) {
            const snap = changes.snapshot;
            const lines = [];
            if (snap.title) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">제목</span> <span style="color:var(--text);">${escHtml(snap.title)}</span></div>`);
            if (snap.start_date) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">일자</span> <span style="color:var(--text);">${escHtml(fmtLogValue('start_date', snap.start_date))}${snap.end_date && snap.end_date !== snap.start_date ? ' ~ '+escHtml(fmtLogValue('end_date', snap.end_date)) : ''}</span></div>`);
            if (snap.client_name) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">의뢰자</span> <span style="color:var(--text);">${escHtml(snap.client_name)}</span></div>`);
            if (snap.location) lines.push(`<div><span style="font-size:11px;color:var(--text-muted);">장소</span> <span style="color:var(--text);">${escHtml(snap.location)}</span></div>`);
            body = `<div style="display:flex; flex-direction:column; gap:3px; margin-top:4px; font-size:12px;">${lines.join('')}</div>`;
        } else if (h.action === 'restore' || h.action === 'uncomplete' || (h.action === 'complete' && !Object.keys(changes).length)) {
            body = '';
        } else {
            const rows = Object.entries(changes).filter(([k]) => k !== 'snapshot').map(([key, val]) => {
                const label = (typeof FIELD_LABELS !== 'undefined' && FIELD_LABELS[key]) || key;
                const oldVal = fmtLogValue(key, val.old);
                const newVal = fmtLogValue(key, val.new);
                return `<div style="margin:6px 0;">
                    <div style="font-size:11px; color:var(--text-muted);">${label}</div>
                    <div style="display:flex; gap:6px; margin-top:2px; flex-wrap:wrap; align-items:center;">
                        <span style="padding:2px 8px; border-radius:4px; background:rgba(212,136,136,0.15); color:var(--red); font-size:12px; text-decoration:line-through;">${escHtml(oldVal)}</span>
                        <span style="color:var(--text-muted); font-size:11px;">→</span>
                        <span style="padding:2px 8px; border-radius:4px; background:rgba(136,212,136,0.15); color:var(--green); font-size:12px;">${escHtml(newVal)}</span>
                    </div>
                </div>`;
            }).join('');
            body = rows;
        }

        const reasonHtml = h.reason
            ? `<div style="margin-top:6px; padding:6px 10px; background:var(--surface2); border-left:3px solid var(--accent); border-radius:4px; font-size:12px; color:var(--text);"><span style="font-size:10px; color:var(--text-muted); letter-spacing:0.04em; margin-right:6px;">사유</span>${escHtml(h.reason)}</div>`
            : '';

        return `<div style="padding:12px 0; border-bottom:1px solid var(--border);">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                <span style="font-size:10px; padding:2px 6px; border-radius:3px; background:var(--surface2); color:${ACTION_COLOR[h.action]||'var(--accent)'}; font-weight:600;">${ACTION_LABEL[h.action]||h.action}</span>
                <span style="font-size:12px; font-weight:600;">${escHtml(h.user_name||'-')}</span>
                <span style="font-size:10px; color:var(--text-muted);">${h.created_at}</span>
            </div>
            ${body}
            ${reasonHtml}
        </div>`;
    }).join('');
}

// ── 편집 모달 ──
function openEditModal(ev){
    if(isGuestUser) return;
    editingId=ev.id; selectedAssignees=ev.assignees?ev.assignees.map(a=>a.id):[];
    selectedNotifyAssignees=Array.isArray(ev.notify_assignees)?[...ev.notify_assignees]:[]; updateNotifyBtn();
    // 날짜/시간 원본 스냅샷 — 변경 사유 필수 여부 판정용
    editingOrigDT={
        sd:(ev.start_date||'').substring(0,10),
        ed:((ev.end_date||ev.start_date)||'').substring(0,10),
        st:(ev.start_time||'').substring(0,5),
        et:(ev.end_time||'').substring(0,5),
        allday:!!ev.is_all_day,
    };
    resetModalForm();
    setColor(ev.color);
    document.getElementById('modalTitle').value=ev.title||'';
    // 날짜/시간
    const sd=(ev.start_date||'').substring(0,10), ed=(ev.end_date||'').substring(0,10);
    const st=ev.start_time||'13:00', et=ev.end_time||'14:00';
    document.getElementById('startDate').value=sd;document.getElementById('endDate').value=ed;
    document.getElementById('goldStartDate').value=sd;document.getElementById('goldEndDate').value=ed||sd;
    document.getElementById('startTime').value=st;document.getElementById('startTimeTrigger').textContent=st.substring(0,5);
    document.getElementById('endTime').value=et;document.getElementById('endTimeTrigger').textContent=et.substring(0,5);
    document.getElementById('goldStartTime').value=st;document.getElementById('goldStartTimeTrigger').textContent=st.substring(0,5);
    document.getElementById('goldEndTime').value=et;document.getElementById('goldEndTimeTrigger').textContent=et.substring(0,5);
    if(ev.is_all_day){isAllDay=true;document.getElementById('alldayTrack').classList.add('on');document.querySelectorAll('.time-picker-trigger').forEach(t=>t.style.display='none');}
    // 장소
    // address=도로명, location=도로명+상세. 상세주소 분리 복원
    {
        const road = ev.address || ev.location || '';
        let detail = '';
        if (ev.address && ev.location && ev.location.startsWith(ev.address)) {
            detail = ev.location.slice(ev.address.length).trim();
        }
        document.getElementById('modalLocation').value = road;
        document.getElementById('modalAddress').value = ev.address || road;
        const det=document.getElementById('modalLocationDetail'); if(det) det.value = detail;
    }
    // 주말 제외
    {const xw=document.getElementById('excludeWeekendsChk'); if(xw) xw.checked=!!ev.exclude_weekends;}
    // 배송 아이콘 수동 지정 복원
    shipIconOverride=ev.ship_icon_override||null;
    renderShipIconButtons();
    // 알림
    if(ev.notif_minutes!==null&&ev.notif_minutes!==undefined) document.getElementById('notifSelect').value=ev.notif_minutes;
    // 반복 설정: 그룹 소속 일정은 저장된 주기/종료일을 프리필해 재조정 가능 (변경 시 이후 반복 재생성)
    {const rg=document.getElementById('repeatGroup');
     const rn=document.getElementById('repeatEditNote');
     const isRepeat=!!ev.repeat_group;
     if(rg) rg.querySelector('.notif-row').style.display='flex';
     if(rn) rn.style.display=isRepeat?'block':'none';
     const rc=document.getElementById('repeatChk');
     if(isRepeat&&ev.repeat_freq){
         if(rc) rc.checked=true;
         const rf=document.getElementById('repeatFreq'); if(rf) rf.value=ev.repeat_freq;
         const ri=document.getElementById('repeatInterval'); if(ri&&ev.repeat_interval) ri.value=ev.repeat_interval;
         const runit=document.getElementById('repeatUnit'); if(runit&&ev.repeat_unit) runit.value=ev.repeat_unit;
         const ru=document.getElementById('repeatUntil'); if(ru) ru.value=(ev.repeat_until||'').substring(0,10);
         onRepeatChkToggle(); onRepeatFreqChange();
         editingRepeatOrig={f:ev.repeat_freq, ru:(ev.repeat_until||'').substring(0,10),
             iv:ev.repeat_freq==='custom'?String(ev.repeat_interval||'1'):'', un:ev.repeat_freq==='custom'?(ev.repeat_unit||'day'):''};
     } else {
         if(rc) rc.checked=false;
         onRepeatChkToggle();
     }}
    // 방송룸 대여 이력 등록은 신규 등록 전용 — 기존 일정 편집 시 숨김
    {const bg=document.getElementById('brRentalGroup'); if(bg) bg.style.display='none';}
    // 요약: 등록된 일정은 기본적으로 '요약'(읽기 요약 뷰) ON
    if(ev && ev.id){ isLocked=true; setTimeout(applyLockUI,0); }
    // 날짜 배지
    const d=sd?new Date(sd):new Date();
    document.getElementById('modalDateBadge').textContent=`${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일 (${DAYS_KO[d.getDay()]})`;
    // 일정옵션
    if(ev.sched_event_opts){const opts=Array.isArray(ev.sched_event_opts)?ev.sched_event_opts:[];opts.forEach(v=>{const b=document.querySelector(`#schedEventOpts [data-seopt="${v}"]`);if(b)b.classList.add('active');});if(opts.includes('after'))document.getElementById('schedReasonWrap').style.display='';}
    if(ev.sched_opt){const b=document.querySelector(`#scheduleOpts [data-sopt="${ev.sched_opt}"]`);if(b)b.classList.add('active');}
    updateSchedOptDesc();
    if(ev.special_opts){const opts=Array.isArray(ev.special_opts)?ev.special_opts:[];opts.forEach(v=>{const b=document.querySelector(`#specialOpts [data-opt="${v}"]`);if(b)b.classList.add('active');});
        // 미팅/내방 옵션 체크 복원
        document.querySelectorAll('#visitOptsList input').forEach(cb=>{ cb.checked=opts.includes(cb.value); });
        applyVisitOptsUI();
        if(typeof applyExtOperatorUI==='function') applyExtOperatorUI(); // 외부 오퍼레이터 복원 시 종일 고정 재적용
    }
    if(ev.sched_after_reason) document.getElementById('schedAfterReason').value=ev.sched_after_reason;
    // 공통 필드
    document.getElementById('commonDesc').value=ev.description||'';
    const _ch=document.getElementById('commonHandoverNote'); if(_ch) _ch.value=ev.handover_note||'';
    // request_data 복원 (Firebase 데이터 구조 호환)
    const g=ev.request_data||{};
    // client_name 폴백은 방문의뢰(gold)일 때만 — 과거 누수로 오염된 비-gold 일정의 의뢰자명이 폼에 부활하는 것 방지
    document.getElementById('g_nickname').value=g.nickname||(ev.color==='gold'?(ev.client_name||''):'');
    document.getElementById('g_name').value=g.name||'';
    document.getElementById('g_phone').value=g.phone||'';
    // 플랫폼: "SOOP, 유튜브, 직접입력값" → pill 선택 + 기타 입력
    if(g.platform){
        const known=['SOOP','치지직','유튜브','틱톡','기타'];
        const vals=g.platform.split(',').map(v=>v.trim());
        const pillVals=vals.map(v=>known.includes(v)?v:'기타');
        setMultiRadio('g_platform_group',[...new Set(pillVals)]);
        const etcVals=vals.filter(v=>!known.includes(v));
        if(etcVals.length) document.getElementById('g_platform_etc').value=etcVals.join(', ');
        handleConditional('g_platform_group');
    }
    if(g.career) setRadio('g_career_group',g.career);
    // 유입경로: "소개:홍길동" → 소개 선택 + 이름 입력
    if(g.source){
        if(g.source.startsWith('소개:')){
            setRadio('g_source_group','소개');
            document.getElementById('g_source_ref').value=g.source.substring(3);
            handleConditional('g_source_group');
        } else {
            setRadio('g_source_group',g.source);
        }
    }
    // 방송주제: "소통, 게임, 직접입력값" → pill 선택 + 기타 입력
    if(g.topic){
        const known=['소통','게임','노래','먹방','야외','버추얼','코인','주식','기타','미정'];
        // 레거시 '주식/코인' → 주식+코인으로 분해
        const vals=g.topic.split(',').map(v=>v.trim()).flatMap(v=>v==='주식/코인'?['주식','코인']:[v]);
        const pillVals=vals.map(v=>known.includes(v)?v:'기타');
        setMultiRadio('g_topic_group',[...new Set(pillVals)]);
        const etcVals=vals.filter(v=>!known.includes(v));
        if(etcVals.length) document.getElementById('g_topic_etc').value=etcVals.join(', ');
        handleConditional('g_topic_group');
    }
    // 예산: "풍족"/"부족"/"모름" or 직접입력한 값 → pill 선택 + 기타 입력
    if(g.budget){
        const known=['풍족','부족','모름','직접입력'];
        if(known.includes(g.budget)){
            setRadio('g_budget_group',g.budget);
        } else {
            setRadio('g_budget_group','직접입력');
            document.getElementById('g_budget_etc').value=g.budget;
            handleConditional('g_budget_group');
        }
    }
    document.getElementById('g_equipment').value=g.equipment||'';
    // 의뢰주제: "처음세팅, 추가세팅, 직접입력값" → pill 선택 + 기타 입력
    if(g.req_topic){
        const known=['처음세팅','추가세팅','이사세팅','렌탈','기타'];
        const vals=g.req_topic.split(',').map(v=>v.trim());
        const pillVals=vals.map(v=>known.includes(v)?v:'기타');
        setMultiRadio('g_req_topic_group',[...new Set(pillVals)]);
        const etcVals=vals.filter(v=>!known.includes(v));
        if(etcVals.length) document.getElementById('g_req_topic_etc').value=etcVals.join(', ');
        handleConditional('g_req_topic_group');
    }
    // 3뎁스 세팅 항목 선택 복원
    reqItems=Array.isArray(g.req_items)?g.req_items.filter(x=>x&&x.t&&x.c&&x.d).map(x=>({t:x.t,c:x.c,d:x.d,qty:Math.max(1,parseInt(x.qty,10)||1)})):[];
    projReqItems=[]; projReqLoadedFor=null;
    renderReqView();
    document.getElementById('g_req_detail').value=g.req_detail||'';
    // 이사세팅 출발지 복원 (address=도로명, location=도로명+상세)
    {
        const mfRoad=g.move_from_address||'';
        const mfLoc=g.move_from_location||'';
        let mfDetail='';
        if(mfRoad && mfLoc && mfLoc.startsWith(mfRoad)) mfDetail=mfLoc.slice(mfRoad.length).trim();
        const mfl=document.getElementById('moveFromLocation'); if(mfl) mfl.value=mfRoad||mfLoc;
        const mfa=document.getElementById('moveFromAddress'); if(mfa) mfa.value=mfRoad;
        const mfd=document.getElementById('moveFromDetail'); if(mfd) mfd.value=mfDetail;
        const noFrom=document.getElementById('moveNoFrom');
        if(noFrom){ noFrom.checked = !!g.move_no_from; }
        onMoveNoFromToggle();
    }
    updateMoveSettingUI();
    // 구버전 일정은 특이사항이 special_note 컬럼에만 있음 — 폴백 표시 (재저장 시 request_data로 이관됨)
    document.getElementById('g_special').value=g.special||ev.special_note||'';
    if(g.specialReason) document.getElementById('specialReason').value=g.specialReason;
    if(typeof updateCarReasonUI==='function') updateCarReasonUI();
    if(g.paid) setRadio('g_paid_group',g.paid);
    document.getElementById('g_estimate_amount').value=_fmtAmt(g.estimate_amount||'');
    if(g.order){setRadio('g_order_group',g.order);handleConditional('g_order_group');}
    if(g.balance){setRadio('g_balance_group',g.balance);handleConditional('g_balance_group');}
    document.getElementById('g_balance_amount').value=_fmtAmt(g.balance_amount||'');
    if(g.estimate_id){
        linkedEstimateId=g.estimate_id;document.getElementById('linkedEstimateTitle').textContent=`#${g.estimate_id}`;document.getElementById('linkedEstimateInfo').style.display='';
        // 결제완료인데 금액이 비어 있으면(0원 기록 방지) 연동 견적서 총액 자동 입력
        if(g.paid==='결제완료'&&!(g.estimate_amount||'').toString().trim()&&typeof autofillEstimateAmountIfEmpty==='function') autofillEstimateAmountIfEmpty();
    }
    // 의뢰자/프로젝트 연결 복원
    if(g.client_id){
        linkedClientId=g.client_id;
        document.getElementById('linkedClientName').textContent=g.nickname||g.name||`의뢰자 #${g.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        document.getElementById('linkedClientLink').href='/clients/'+g.client_id;
        linkedProjectId=g.project_id||null;
        loadProjectReqItems(linkedProjectId); // 프로젝트 의뢰 내용 불러오기
        loadClientProjects(g.client_id);
        if(!(g.nickname||g.name)) restoreLinkedClientName(g.client_id);
    }
    // 비-gold에서도 request_data에 저장된 의뢰자 연결 복원 (연동 미지원 카테고리는 오염 데이터 부활 방지 위해 제외)
    if(!g.client_id && ev.request_data && ev.request_data.client_id && colorSupportsClientLink(ev.color)){
        linkedClientId=ev.request_data.client_id;
        document.getElementById('linkedClientName').textContent=ev.request_data.nickname||ev.request_data.name||`의뢰자 #${ev.request_data.client_id}`;
        document.getElementById('linkedClientInfo').style.display='';
        document.getElementById('linkedClientLink').href='/clients/'+ev.request_data.client_id;
        linkedProjectId=ev.request_data.project_id||null;
        loadClientProjects(ev.request_data.client_id);
        if(!(ev.request_data.nickname||ev.request_data.name)) restoreLinkedClientName(ev.request_data.client_id);
    }
    // remote_data 복원
    const t=ev.remote_data||{};
    if(t.mode){setRadio('teal_mode_group',t.mode);document.getElementById('teal_remote_fields').style.display=t.mode==='remote'?'':'none';document.getElementById('teal_studio_fields').style.display=t.mode==='studio'?'':'none';}
    document.getElementById('t_remote_name').value=t.mode==='remote'?t.name||'':'';
    document.getElementById('t_remote_platform').value=t.mode==='remote'?t.platform||'':'';
    document.getElementById('t_remote_content').value=t.mode==='remote'?t.content||'':'';
    document.getElementById('t_studio_name').value=t.mode==='studio'?t.name||'':'';
    document.getElementById('t_studio_platform').value=t.mode==='studio'?t.platform||'':'';
    document.getElementById('t_studio_content').value=t.mode==='studio'?t.content||'':'';
    document.getElementById('t_desc').value=t.desc||'';
    // 첨부파일
    pendingAttachments={quote:[],reference:[],room:[],general:[]};
    loadExistingAttachments(ev.id);
    // 배송 현황 (gold/green) — editingId 설정 후이므로 섹션 노출 + 목록 로드
    updateShipmentSectionVisibility();
    loadShipments();
    // UI
    document.getElementById('btnDelete').style.display='';
    document.getElementById('btnLog').style.display='';
    updateAssigneeBtn();updateBalanceBanner();
    renderAssigneeList();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(calRefreshAutoGrow, 0); // 상세 내용 높이 자동 맞춤
    pushCalModalHistory();
}

// ── 모달이 열렸을 때 브라우저 뒤로가기 → 모달 닫기 ──
let __calModalHistory=false;
function pushCalModalHistory(){
    if(!__calModalHistory){ try{ history.pushState({calModal:1},''); }catch(e){} __calModalHistory=true; }
}
window.addEventListener('popstate',function(){
    // 앨범(라이트박스) UI가 스스로 소비한 pop이면 아무 것도 하지 않음
    if(__lbConsuming){ __lbConsuming=false; return; }
    // 앨범이 열려 있으면 뒤로가기는 앨범만 닫음 (뒤의 일정 모달/페이지 유지)
    const lb=document.getElementById('lightbox');
    if(lb && lb.classList.contains('open')){
        __lbHistory=false; // 이 history 항목은 이미 pop됨
        lb.classList.remove('open'); lbResetZoom();
        document.body.classList.remove('lb-open');
        if(typeof lbBindPin==='function') lbBindPin(false);
        return;
    }
    const ov=document.getElementById('modalOverlay');
    if(ov && ov.classList.contains('open')){
        __calModalHistory=false; // 이 history 항목은 이미 pop됨
        closeModal();            // 페이지 이동 대신 모달만 닫음
    }
});

function closeModal(){
    document.getElementById('modalOverlay').classList.remove('open');editingId=null;
    // UI로 닫을 때(뒤로가기 아님) push했던 history 항목 소비
    if(__calModalHistory){ __calModalHistory=false; try{ history.back(); }catch(e){} }
    document.querySelectorAll('.time-picker-popup').forEach(p=>p.remove());
    const rf=document.getElementById('reasonField'); if (rf) rf.style.display='none';
    const rm=document.getElementById('modalReason'); if (rm) rm.value='';
    // 상태 복원
    viewMode=false;
    setEditModeUI();
}

