{{-- 데이터 수집·저장·모달 카드 재배치·작성 레일 --}}
// ── 데이터 수집 ──
function collectGoldFields(){
    // 플랫폼 (멀티선택, 기타→직접입력 치환)
    const platSel=getMultiRadio('g_platform_group');
    const platEtc=document.getElementById('g_platform_etc')?.value.trim()||'';
    const platform=platSel.length?platSel.map(v=>v==='기타'?(platEtc||'기타'):v).join(', '):'';
    // 방송주제 (멀티선택, 기타→직접입력 치환)
    const topicSel=getMultiRadio('g_topic_group');
    const topicEtc=document.getElementById('g_topic_etc')?.value.trim()||'';
    const topic=topicSel.length?topicSel.map(v=>v==='기타'?(topicEtc||'기타'):v).join(', '):'';
    // 예산 (직접입력→실제값 치환)
    const budgetSel=getRadio('g_budget_group');
    const budget=budgetSel==='직접입력'?(document.getElementById('g_budget_etc')?.value.trim()||'직접입력'):(budgetSel||'');
    // 유입경로 (소개→소개:이름)
    const sourceSel=getRadio('g_source_group');
    const source=sourceSel==='소개'?'소개:'+(document.getElementById('g_source_ref')?.value.trim()||''):(sourceSel||'');
    // 의뢰주제 (멀티선택, 기타→직접입력 치환)
    const reqTopicSel=getMultiRadio('g_req_topic_group');
    const reqTopicEtc=document.getElementById('g_req_topic_etc')?.value.trim()||'';
    const req_topic=reqTopicSel.length?reqTopicSel.map(v=>v==='기타'?(reqTopicEtc||'기타'):v).join(', '):'';
    // 특수옵션 사유
    const specialReason=document.getElementById('specialReason')?.value.trim()||'';
    return {
        nickname:document.getElementById('g_nickname').value.trim(),
        name:document.getElementById('g_name').value.trim(),
        phone:document.getElementById('g_phone').value.trim(),
        platform, topic, budget, source,
        equipment:document.getElementById('g_equipment').value.trim(),
        req_topic,
        estimate_amount:document.getElementById('g_estimate_amount')?.value.trim()||'',
        // 3뎁스 세팅 항목 선택 결과 — [{t:타이틀,c:분류,d:세부항목,qty}]
        req_items:reqItems.length?reqItems.map(i=>({t:i.t,c:i.c,d:i.d,qty:i.qty||1})):null,
        req_detail:document.getElementById('g_req_detail').value.trim(),
        special:document.getElementById('g_special').value.trim(),
        specialReason,
        career:getRadio('g_career_group'),
        paid:getRadio('g_paid_group'),
        order:getRadio('g_order_group'),
        balance:getRadio('g_balance_group'),
        balance_amount:document.getElementById('g_balance_amount').value.trim(),
        // 결제 금액/잔금이 프로젝트 결제 연동 값이면 표시 — 서버가 캘린더발 청구 생성을 건너뜀 (이중 청구 방지)
        balance_source:projPayLinked?'project':'',
        // 다중 견적서 연동 — estimate_id는 첫 번째(하위 호환), 전체는 estimate_ids
        estimate_id:linkedEstimateIds[0]??null,
        estimate_ids:linkedEstimateIds.length?[...linkedEstimateIds]:null,
        client_id:linkedClientId,
        // 의뢰자 연결 + 프로젝트 선택 UI가 열려 있을 때만 수집 (잔류값 저장 방지)
        project_id:(linkedClientId&&document.getElementById('projectSelectWrap')?.style.display!=='none')?(document.getElementById('projectSelect')?.value||null):null,
        // 출발지 (도착지는 기존 address/location 사용). 토글 꺼짐/'출발지 없음' 체크 시 빈 값
        move_no_from:(document.getElementById('moveFromToggle')?.checked&&document.getElementById('moveNoFrom')?.checked)||false,
        move_from_address:(!document.getElementById('moveFromToggle')?.checked||document.getElementById('moveNoFrom')?.checked)?'':(document.getElementById('moveFromAddress')?.value.trim()||''),
        move_from_location:(!document.getElementById('moveFromToggle')?.checked||document.getElementById('moveNoFrom')?.checked)?'':[document.getElementById('moveFromLocation')?.value.trim(),document.getElementById('moveFromDetail')?.value.trim()].filter(Boolean).join(' '),
    };
}
function collectTealFields(){
    const mode=getRadio('teal_mode_group')||'remote';
    const data={mode};
    if(mode==='remote'){data.name=document.getElementById('t_remote_name').value.trim();data.platform=document.getElementById('t_remote_platform').value.trim();data.content=document.getElementById('t_remote_content').value.trim();}
    else{data.name=document.getElementById('t_studio_name').value.trim();data.platform=document.getElementById('t_studio_platform').value.trim();data.content=document.getElementById('t_studio_content').value.trim();}
    data.desc=document.getElementById('t_desc').value.trim();
    return data;
}

let saveInFlight=false;
async function saveEvent(){
    // 중복 클릭 방지 — 저장 완료 전 재클릭 시 무시 (일정·첨부 중복 등록 방지)
    if(saveInFlight) return;
    saveInFlight=true;
    const saveBtns=document.querySelectorAll('#modalOverlay .btn-save, #modalOverlay .btn-save-top, #modalExternalAction');
    saveBtns.forEach(b=>{b.disabled=true;b.style.opacity='0.6';});
    try{
        await doSaveEvent();
    }finally{
        saveInFlight=false;
        saveBtns.forEach(b=>{b.disabled=false;b.style.opacity='';});
    }
}
async function doSaveEvent(){
    const isGold=currentColor==='gold';
    const sd=isGold?document.getElementById('goldStartDate').value:document.getElementById('startDate').value;
    const ed=isGold?(document.getElementById('goldEndDate').value||document.getElementById('goldStartDate').value):document.getElementById('endDate').value;
    const st=isGold?document.getElementById('goldStartTime').value:document.getElementById('startTime').value;
    const et=isGold?document.getElementById('goldEndTime').value:document.getElementById('endTime').value;
    if(!sd){alert('시작일을 입력하세요.');return;}
    if(ed && ed<sd){alert('종료일이 시작일보다 빠릅니다. 날짜를 확인해주세요.');return;}
    const repeatEnabled=(()=>{
        const row=document.getElementById('repeatGroup')?.querySelector('.notif-row');
        const chk=document.getElementById('repeatChk');
        return row&&row.style.display!=='none'&&chk&&chk.checked;
    })();
    if(repeatEnabled){
        const ru=document.getElementById('repeatUntil')?.value||'';
        // 기존 반복 일정을 열어 설정을 안 바꿨으면 검증 스킵 (백엔드도 미변경 시 재생성 안 함)
        const rf=document.getElementById('repeatFreq')?.value;
        const rIv=rf==='custom'?(document.getElementById('repeatInterval')?.value||'1'):'';
        const rUn=rf==='custom'?(document.getElementById('repeatUnit')?.value||'day'):'';
        const repeatUnchanged=editingId&&editingRepeatOrig
            &&editingRepeatOrig.f===rf&&editingRepeatOrig.ru===ru&&editingRepeatOrig.iv===rIv&&editingRepeatOrig.un===rUn;
        if(!repeatUnchanged){
            if(!ru){alert('반복 종료일을 선택해주세요.');return;}
            if(ru<=sd){alert('반복 종료일이 시작일보다 늦어야 합니다.');return;}
        }
    }

    // schedEventOpts 수집
    const schedEventOpts=[...document.querySelectorAll('#schedEventOpts .special-opt-btn.active')].map(b=>b.dataset.seopt);
    const schedOpt=(()=>{const a=document.querySelector('#scheduleOpts .sched-opt-btn.active');return a?a.dataset.sopt:null;})();
    let specialOpts=[...document.querySelectorAll('#specialOpts .special-opt-btn.active')].map(b=>b.dataset.opt);
    // 미팅/내방 옵션·사내업무 장소(체크박스)도 special_opts에 함께 저장
    if(currentColor==='purple'||currentColor==='blue'){ specialOpts=specialOpts.concat([...document.querySelectorAll('#visitOptsList input:checked')].map(i=>i.value)); }

    const data={
        title:document.getElementById('modalTitle').value.trim()||'(제목 없음)',
        start_date:sd, end_date:ed||sd, start_time:isAllDay?null:st, end_time:isAllDay?null:et,
        is_all_day:isAllDay, exclude_weekends:!!document.getElementById('excludeWeekendsChk')?.checked, color:currentColor,
        // 공통 유형은 별도 이름 필드 없음 — 연결된 의뢰자명(있으면)만 보조 저장
        client_name:isGold?document.getElementById('g_nickname').value.trim():((linkedClientId&&colorSupportsClientLink(currentColor))?(document.getElementById('linkedClientName')?.textContent.trim()||''):''),
        // address=도로명(검색), location=도로명+상세주소(표시용)
        address:document.getElementById('modalAddress').value.trim()||document.getElementById('modalLocation').value.trim(),
        location:[document.getElementById('modalLocation').value.trim(), document.getElementById('modalLocationDetail').value.trim()].filter(Boolean).join(' '),
        description:isGold?'':document.getElementById('commonDesc').value.trim(),
        // 전달사항 — 메모 없는 공통 유형에서만 입력(gold/teal은 자체 필드 사용)
        handover_note:(isGold||currentColor==='teal')?null:(document.getElementById('commonHandoverNote')?.value.trim()||null),
        assignees:selectedAssignees,
        notify_assignees:selectedNotifyAssignees,
        notif_minutes:document.getElementById('notifSelect').value||null,
        repeat_freq:(repeatEnabled?(document.getElementById('repeatFreq')?.value||null):null),
        repeat_interval:(repeatEnabled&&document.getElementById('repeatFreq')?.value==='custom')?(document.getElementById('repeatInterval')?.value||1):null,
        repeat_unit:(repeatEnabled&&document.getElementById('repeatFreq')?.value==='custom')?(document.getElementById('repeatUnit')?.value||'day'):null,
        repeat_until:(repeatEnabled&&document.getElementById('repeatFreq')?.value)?(document.getElementById('repeatUntil')?.value||null):null,
        is_locked:isLocked,
        sched_opt:schedOpt,
        sched_event_opts:schedEventOpts,
        special_opts:specialOpts,
        sched_after_reason:document.getElementById('schedAfterReason').value.trim()||null,
        request_data:isGold?collectGoldFields():((linkedClientId&&colorSupportsClientLink(currentColor))?{client_id:linkedClientId,project_id:(document.getElementById('projectSelectWrap')?.style.display!=='none')?(document.getElementById('projectSelect')?.value||null):null,nickname:'',name:'',phone:''}:null),
        remote_data:currentColor==='teal'?collectTealFields():null,
    };
    // 연차 차감 (휴가/개인 전용) — request_data.leave_deduct: 'full'|'half'
    if(currentColor==='red'&&document.getElementById('leaveDeductChk')?.checked){
        data.request_data=Object.assign(data.request_data||{},{leave_deduct:document.getElementById('leaveDeductType')?.value==='half'?'half':'full'});
    }

    // 외부 오퍼레이터 체크 시 항시 종일 일정으로 저장 (UI에서도 고정되지만 이중 방어)
    if(specialOpts.includes('external_operator')){
        data.is_all_day=true; data.start_time=null; data.end_time=null;
    }

    // 대여 이력 등록 (방송룸/렌탈 신규 등록 + 체크 시)
    {
        const br=collectBrRental();
        if(br){
            if(!linkedClientId){alert('대여 이력 등록에는 의뢰자 연동이 필요합니다.');return;}
            // 렌탈/월대여는 request_data가 없을 수 있으므로 의뢰자 연동 보장
            if(!data.request_data) data.request_data={client_id:linkedClientId,project_id:null,nickname:'',name:'',phone:''};
            data.broadcast_rental=br;
        }
    }

    // 변경 사유: 방문의뢰/원격·방송룸에서 날짜/시간이 바뀔 때만 필수 (백엔드에서도 동일 판정)
    if (editingId) {
        const reasonEl = document.getElementById('modalReason');
        const reasonVal = (reasonEl?.value || '').trim();
        const dtChanged = !editingOrigDT
            || sd !== editingOrigDT.sd
            || (ed||sd) !== editingOrigDT.ed
            || isAllDay !== editingOrigDT.allday
            || (!isAllDay && ((st||'').substring(0,5) !== editingOrigDT.st || (et||'').substring(0,5) !== editingOrigDT.et));
        const needsReason = REASON_COLORS.includes(currentColor) && dtChanged;
        if (needsReason && !reasonVal) {
            if (reasonEl) {
                reasonEl.style.borderColor = 'var(--red)';
                reasonEl.scrollIntoView({behavior:'smooth', block:'center'});
                reasonEl.focus();
                setTimeout(()=>{ reasonEl.style.borderColor=''; }, 3000);
            }
            showCalToast('일정(날짜/시간) 변경 사유를 입력해주세요.');
            return;
        }
        if (reasonVal) data.reason = reasonVal;
    }

    const url=editingId?`/api/events/${editingId}`:'/api/events';
    const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(data)});
    if(res.ok){
        const saved=await res.json();
        const hasFiles=Object.values(pendingAttachments).some(arr=>arr.length);
        if(hasFiles) await uploadPendingAttachments(saved.id);
        closeModal();loadEvents();
    }else{
        const err=await res.json();
        const msg = err.message || (err.errors && Object.values(err.errors).flat().join('\n')) || '저장 실패';
        alert(msg);
    }
}

let pendingDeleteId = null;
function deleteEvent(id){
    const delId=id||editingId;
    if(!delId) return;
    pendingDeleteId = delId;
    // 반복 일정이면 일괄 삭제 옵션 노출
    const target=events.find(e=>e.id===delId)||detailEvent;
    const isRepeat=!!(target&&target.repeat_group);
    document.getElementById('delSeriesWrap').style.display=isRepeat?'block':'none';
    document.getElementById('delSeriesChk').checked=false;
    document.getElementById('deleteReasonInput').value = '';
    document.getElementById('deleteReasonOverlay').style.display = 'flex';
    setTimeout(()=>document.getElementById('deleteReasonInput').focus(), 50);
}
async function confirmDeleteEvent(){
    if (!pendingDeleteId) return;
    const inputEl = document.getElementById('deleteReasonInput');
    const reason = (inputEl.value || '').trim();
    if (!reason) {
        inputEl.style.borderColor = 'var(--red)';
        inputEl.focus();
        setTimeout(()=>{ inputEl.style.borderColor=''; }, 3000);
        showCalToast('삭제 사유를 입력해주세요.');
        return;
    }
    const res = await fetch(`/api/events/${pendingDeleteId}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', 'Accept':'application/json'},
        body: JSON.stringify({ reason, scope: document.getElementById('delSeriesChk')?.checked ? 'future' : 'one' }),
    });
    if (res.ok) {
        document.getElementById('deleteReasonOverlay').style.display='none';
        pendingDeleteId = null;
        closeModal();
        loadEvents();
    } else {
        const err = await res.json().catch(()=>({}));
        const msg = err.message || (err.errors && Object.values(err.errors).flat().join('\n')) || '삭제 실패';
        alert(msg);
    }
}

function searchCalAddr(){
    if (typeof isLocked !== 'undefined' && isLocked) return; // 잠금 상태에선 변경 불가
    new daum.Postcode({oncomplete:function(data){
        // 직접 입력 차단 — 검색에서 사용자가 선택한 유형(R=도로명, J=지번)으로 교체
        const addr=data.userSelectedType==='R'?data.roadAddress:data.jibunAddress;
        document.getElementById('modalAddress').value=addr;
        document.getElementById('modalLocation').value=addr;
    }}).open();
}
function clearCalAddr(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    document.getElementById('modalAddress').value='';
    document.getElementById('modalLocation').value='';
    const det=document.getElementById('modalLocationDetail'); if(det) det.value=''; // 상세주소도 함께 초기화
}
// 이사세팅 출발지 주소 검색/지우기
function searchMoveFrom(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    new daum.Postcode({oncomplete:function(data){
        const addr=data.userSelectedType==='R'?data.roadAddress:data.jibunAddress;
        document.getElementById('moveFromAddress').value=addr;
        document.getElementById('moveFromLocation').value=addr;
    }}).open();
}
function clearMoveFrom(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    document.getElementById('moveFromAddress').value='';
    document.getElementById('moveFromLocation').value='';
    const det=document.getElementById('moveFromDetail'); if(det) det.value=''; // 상세주소도 함께 초기화
}
// '출발지 없음' 토글 — 출발지 입력 숨김/비움, 도착지 라벨은 유지
function onMoveNoFromToggle(){
    if (typeof isLocked !== 'undefined' && isLocked) return;
    const none=document.getElementById('moveNoFrom')?.checked;
    const wrap=document.getElementById('moveFromInputWrap');
    const note=document.getElementById('moveNoFromNote');
    if(wrap) wrap.style.display=none?'none':'';
    if(note) note.style.display=none?'':'none';
    if(none){ // 입력값 비움
        ['moveFromLocation','moveFromDetail','moveFromAddress'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    }
}
// 출발지 입력 토글 → 출발지 블록 노출 + 기존 주소 라벨을 '도착지'로.
// 이사세팅·프로젝트 연동과 무관하게 방문의뢰(gold)면 언제든 켤 수 있고,
// 의뢰 주제에서 이사세팅을 체크/해제하면 토글도 함께 켜지고 꺼진다 (수동 변경 가능).
let __prevMoveTopic=false;
function updateMoveSettingUI(){
    const isGold=currentColor==='gold';
    const tw=document.getElementById('moveFromToggleWrap');
    if(tw) tw.style.display=isGold?'inline-flex':'none';
    const tg=document.getElementById('moveFromToggle');
    const isMove = isGold && !!(tg&&tg.checked);
    const mb=document.getElementById('moveFromBlock');
    const lbl=document.getElementById('addrBlockLabel');
    if(mb) mb.style.display=isMove?'':'none';
    // 출발지 입력 중이면 항상 '도착지 (이사 후 장소)' — 출발지 유무와 무관
    if(lbl) lbl.textContent=isMove?'도착지 (이사 후 장소)':'장소';
}
// 의뢰 주제의 이사세팅 체크 변화 → 출발지 토글 동기화 (사용자가 토글을 따로 조작한 경우는 유지)
function syncMoveToggleWithTopic(){
    const nowMove=getMultiRadio('g_req_topic_group').includes('이사세팅');
    const tg=document.getElementById('moveFromToggle');
    if(tg&&nowMove!==__prevMoveTopic) tg.checked=nowMove;
    __prevMoveTopic=nowMove;
    updateMoveSettingUI();
}

// ── 라디오 그룹 초기화 ──
function initAllRadioGroups(){
    // 멀티 선택: 플랫폼, 방송주제
    initRadioGroup('g_platform_group',{multi:true});
    initRadioGroup('g_topic_group',{multi:true});
    // 단일 선택
    ['g_career_group','g_source_group','g_budget_group','g_paid_group','g_order_group','g_balance_group'].forEach(id=>initRadioGroup(id));
    initRadioGroup('g_req_topic_group',{multi:true});
    // teal 모드 전환
    initRadioGroup('teal_mode_group',{onChange:v=>{document.getElementById('teal_remote_fields').style.display=v==='remote'?'':'none';document.getElementById('teal_studio_fields').style.display=v==='studio'?'':'none';}});
    // 색상 dot 클릭
    document.querySelectorAll('.color-dot').forEach(dot=>{dot.addEventListener('click',()=>{if(!isLocked) setColor(dot.dataset.color);});});
    // schedEventOpts (멀티 토글)
    document.querySelectorAll('#schedEventOpts .special-opt-btn').forEach(btn=>{btn.addEventListener('click',()=>{if(isLocked)return;btn.classList.toggle('active');if(btn.dataset.seopt==='after')document.getElementById('schedReasonWrap').style.display=btn.classList.contains('active')?'':'none';});});
    // scheduleOpts (단일)
    document.querySelectorAll('#scheduleOpts .sched-opt-btn').forEach(btn=>{btn.addEventListener('click',()=>{if(isLocked)return;const was=btn.classList.contains('active');document.querySelectorAll('#scheduleOpts .sched-opt-btn').forEach(b=>b.classList.remove('active'));if(!was)btn.classList.add('active');updateSchedOptDesc();});});

// 프로젝트 선택 변경 시 결제 금액/잔금 연동 갱신
document.getElementById('projectSelect')?.addEventListener('change',()=>{
    syncProjectPaymentFields();
    loadProjectReqItems(document.getElementById('projectSelect')?.value||null); // 선택한 프로젝트의 의뢰 내용 표시
    autoFillProjectAddress(); // 📁 프로젝트 주소 버튼 토글 (자동 채움 없음 — 채움은 버튼 클릭으로만)
});

// ── 2a 리디자인: gold/teal 폼을 섹션 카드로 그룹핑 (section-heading·divider 경계 기준) ──
(function(){
    document.querySelectorAll('#modalOverlay .gold-only, #modalOverlay .teal-only').forEach(container=>{
        let card=null;
        [...container.children].forEach(el=>{
            if(el.classList.contains('divider')){ el.remove(); card=null; return; }
            if(el.classList.contains('section-heading') || !card){
                card=document.createElement('div');
                card.className='m-card';
                container.insertBefore(card, el);
            }
            card.appendChild(el);
        });
    });
})();

// ── 2a 리디자인: 시안과 동일한 섹션 배치 — 01 분류/시간 → 02 의뢰자 → 03 장소 ──
(function(){
    const main=document.querySelector('#modalOverlay .m-main');
    const dt=main?.querySelector(':scope > .datetime-section');
    if(!main||!dt) return;
    // 01 카드: 헤딩 + 카테고리 칩(헤더의 colorRow 이동) + 알림/반복 병합
    const heading=document.createElement('div');
    heading.className='section-heading';
    heading.textContent='일정 분류 및 시간';
    dt.insertBefore(heading, dt.firstChild);
    const colorRow=document.getElementById('colorRow');
    if(colorRow){ colorRow.style.marginBottom='2px'; dt.insertBefore(colorRow, heading.nextSibling); }
    // 연차 차감(휴가/개인 전용)은 카테고리 칩 바로 아래 — 휴가/개인 선택 시 최상단에 보이도록
    const leaveRow=document.getElementById('leaveDeductRow');
    if(leaveRow&&colorRow) dt.insertBefore(leaveRow, colorRow.nextSibling);
    const nr=document.getElementById('notifRepeatSection');
    if(nr){ [...nr.children].forEach(ch=>dt.appendChild(ch)); nr.remove(); }
    // 02 의뢰자 정보 카드(gold 첫 카드)를 검색 카드 뒤로 — gold-only 클래스로 표시 토글 유지
    const goldClientCard=[...document.querySelectorAll('#modalOverlay .gold-only .m-card')]
        .find(c=>c.querySelector('.section-heading')?.textContent.includes('의뢰자 정보'));
    // 일정 옵션 카드(확정 상태/시기 요청/특수 옵션)는 01 분류·시간 카드 바로 아래로
    const schedOptCard=[...document.querySelectorAll('#modalOverlay .gold-only .m-card')]
        .find(c=>c.querySelector('#scheduleOpts'));
    const clientLink=document.getElementById('clientLinkSection');
    const moveFrom=document.getElementById('moveFromBlock');
    const addr=document.getElementById('addressBlock');
    const findGoldCard=t=>[...document.querySelectorAll('#modalOverlay .gold-only .m-card')]
        .find(c=>c.querySelector('.section-heading')?.textContent.includes(t));
    const equipCard=findGoldCard('장비 목록');
    const reqCard=findGoldCard('의뢰 내용');
    const attachImgCard=findGoldCard('첨부 이미지');

    // ── 좌측 컬럼: 일정 옵션(제목 바로 아래) → 분류/시간 → 세부 일정 → 의뢰자/프로젝트 → 의뢰자 정보 → 장소 → 장비 목록 ──
    main.insertBefore(dt, main.firstChild);
    if(schedOptCard){ schedOptCard.classList.add('gold-only'); schedOptCard.id='schedOptCard'; main.insertBefore(schedOptCard, dt); }
    let after=dt;
    const lsChForm=document.getElementById('lsChildrenForm');
    if(lsChForm){ after.after(lsChForm); after=lsChForm; }
    if(clientLink){ after.after(clientLink); after=clientLink; }
    if(goldClientCard){ goldClientCard.classList.add('gold-only'); after.after(goldClientCard); after=goldClientCard; }
    if(moveFrom){ after.after(moveFrom); after=moveFrom; }
    if(addr){ after.after(addr); after=addr; }
    if(equipCard){ equipCard.classList.add('gold-only'); after.after(equipCard); }

    // ── 우측 컬럼(.m-side): 의뢰 내용(세부 의뢰 항목 포함) → 원격/공통 상세 → 견적서·파일 첨부 ──
    const side=document.createElement('div');
    side.className='m-side';
    main.after(side);
    if(reqCard){ reqCard.classList.add('gold-only'); side.appendChild(reqCard); }
    document.querySelectorAll('#modalOverlay .m-main > .teal-only, #modalOverlay .m-main > .common-only').forEach(el=>side.appendChild(el));
    if(attachImgCard){ attachImgCard.classList.add('gold-only'); side.appendChild(attachImgCard); }
    const gAttach=document.getElementById('generalAttachSection');
    if(gAttach) side.appendChild(gAttach);
})();

// ── 2a 리디자인: 우측 작성 현황 레일 — 섹션별 진행/전체 작성률/남은 항목 ──
const M_RAIL_SEC_SEL='#modalOverlay .m-main > .field-section, #modalOverlay .m-main > .datetime-section, #modalOverlay .m-main > #generalAttachSection, #modalOverlay .m-main .m-card, #modalOverlay .m-side > .field-section, #modalOverlay .m-side > #generalAttachSection, #modalOverlay .m-side .m-card';
function mRailLabel(el){
    if(el.classList.contains('datetime-section')) return '날짜 / 시간';
    if(el.id==='generalAttachSection') return '첨부 파일';
    const h=el.querySelector('.section-heading, .field-label');
    // 상태 라벨(m-secstat) 제외 + 이모지·기호 제거 (u 플래그 필수 — 서로게이트 반쪽 잔류 방지)
    const raw=h?[...h.childNodes].filter(n=>!(n.nodeType===1&&n.classList.contains('m-secstat'))).map(n=>n.textContent).join(''):'';
    const t=raw.replace(/\s+/g,' ').replace(/[\p{Extended_Pictographic}️*✕]/gu,'').trim();
    return t.length>16 ? t.slice(0,16)+'…' : (t||'섹션');
}
function mRailState(el){
    const fields=[...el.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea, select')]
        .filter(f=>f.offsetParent!==null);
    const filled=fields.filter(f=>String(f.value||'').trim()!=='').length;
    const chips=el.querySelectorAll('.radio-btn.active, .special-opt-btn.active, .sched-opt-btn.active, .visit-opt input:checked').length;
    const total=fields.length;
    let state='empty';
    if(total===0) state=chips>0?'done':'empty';
    else if(filled>=total) state='done';
    else if(filled>0||chips>0) state='part';
    return {total,filled,state};
}
function updateModalRail(){
    const rail=document.getElementById('modalRail');
    if(!rail) return;
    if(typeof isLocked!=='undefined'&&isLocked){ rail.style.display='none'; return; }
    rail.style.display='';
    const secs=[...document.querySelectorAll(M_RAIL_SEC_SEL)].filter(el=>el.offsetParent!==null);
    let done=0,total=0; const rem=[];
    const navHtml=secs.map((el,i)=>{
        const s=mRailState(el);
        total+=s.total; done+=Math.min(s.filled,s.total);
        if(s.state!=='done') rem.push(mRailLabel(el));
        if(!el.id) el.id='mSec'+i;
        // 카드 헤더 우측 'n/m 작성' 상태 (시안의 섹션 상태 라벨)
        const hd=el.querySelector('.section-heading');
        if(hd){
            let st=hd.querySelector('.m-secstat');
            if(!st){ st=document.createElement('span'); st.className='m-secstat'; hd.appendChild(st); }
            st.textContent=s.total?`${Math.min(s.filled,s.total)}/${s.total} 작성`:(s.state==='done'?'작성됨':'');
            st.classList.toggle('done', s.state==='done');
        }
        const mark=s.state==='done'?'✓':String(i+1).padStart(2,'0');
        return `<a class="m-rail-item ${s.state}" onclick="document.getElementById('${el.id}')?.scrollIntoView({behavior:'smooth',block:'start'})">
            <span class="m-rail-dot">${mark}</span><span class="m-rail-label">${mRailLabel(el)}</span>
            <span class="m-rail-count">${s.total?`${Math.min(s.filled,s.total)}/${s.total}`:''}</span></a>`;
    }).join('');
    document.getElementById('mRailNav').innerHTML=navHtml;
    const pct=total>0?Math.round(done/total*100):0;
    document.getElementById('mRailPct').textContent=pct+'%';
    document.getElementById('mRailCnt').textContent=total?`${done}/${total} 항목`:'';
    document.getElementById('mRailBarFill').style.width=pct+'%';
    const remBox=document.getElementById('mRailRemaining');
    if(rem.length){
        remBox.style.display='';
        document.getElementById('mRailRemChips').innerHTML=
            rem.slice(0,6).map(r=>`<span class="m-rail-rem-chip">${r}</span>`).join('')
            +(rem.length>6?`<span class="m-rail-rem-more">외 ${rem.length-6}개</span>`:'');
    } else remBox.style.display='none';
}
let __mRailTimer=null;
function scheduleRailUpdate(){ clearTimeout(__mRailTimer); __mRailTimer=setTimeout(updateModalRail,150); }
(function(){
    const mo=document.getElementById('modalOverlay');
    if(!mo) return;
    ['input','change','click'].forEach(evt=>mo.addEventListener(evt,scheduleRailUpdate,true));
    // 모달이 열릴 때(카테고리 전환 포함) 레일 재구성
    new MutationObserver(()=>{ if(mo.classList.contains('open')) scheduleRailUpdate(); })
        .observe(mo,{attributes:true,attributeFilter:['class']});
})();
    // specialOpts (멀티 토글)
    document.querySelectorAll('#specialOpts .special-opt-btn').forEach(btn=>{btn.addEventListener('click',()=>{if(isLocked)return;btn.classList.toggle('active');updateCarReasonUI();if(btn.dataset.opt==='external_operator'&&typeof applyExtOperatorUI==='function')applyExtOperatorUI();});});
    document.getElementById('specialReason')?.addEventListener('input',updateCarReasonUI);
    // 잔금 금액 변경 시 배너 업데이트
    document.getElementById('g_balance_amount')?.addEventListener('input',updateBalanceBanner);
}
// init 시 호출
setTimeout(initAllRadioGroups,0);
document.addEventListener('keydown',e=>{if(e.key==='Escape'){
    // 이미지 라이트박스가 열려 있으면 1단계로 라이트박스만 닫음 (전용 핸들러가 처리) — 일정 모달은 유지
    if(document.getElementById('lightbox')?.classList.contains('open')) return;
    closeModal();closeDetail();document.getElementById('historyOverlay').style.display='none';
}});
// 창 크기 변경 시 월간 다일 bar 위치 재계산(px 기반 overlay)
let __calResizeTimer;
window.addEventListener('resize',()=>{
    clearTimeout(__calResizeTimer);
    __calResizeTimer=setTimeout(()=>{ if(currentView==='month') renderMonth(); },150);
});

// 목록(아젠다) 뷰: 좌우 스와이프로 주 이동
(function(){
    const lv=document.getElementById('listView');
    if(!lv) return;
    let sx=0, sy=0, tracking=false;
    lv.addEventListener('touchstart',e=>{
        if(e.touches.length!==1){tracking=false;return;}
        sx=e.touches[0].clientX; sy=e.touches[0].clientY; tracking=true;
    },{passive:true});
    lv.addEventListener('touchend',e=>{
        if(!tracking) return; tracking=false;
        const t=e.changedTouches[0];
        const dx=t.clientX-sx, dy=t.clientY-sy;
        // 가로 스와이프가 충분하고 세로 이동보다 클 때만
        if(Math.abs(dx)>50 && Math.abs(dx)>Math.abs(dy)*1.5){
            moveAgendaWeek(dx<0?1:-1); // 왼쪽으로 밀면 다음 주
        }
    },{passive:true});
})();

