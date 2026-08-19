{{-- 요약(잠금) 뷰·프로젝트 요약·잔금·타임피커 --}}
// ── 잠금 ──
function toggleLock(){
    isLocked=!isLocked;
    applyLockUI();
}

// '요약' 토글 — 읽기 요약 뷰 표시만 제어(필드 활성/비활성은 보기/편집 모드가 담당)
function applyLockUI(){
    const btn=document.getElementById('lockBtn');
    btn.textContent=isLocked?'☑ 요약':'☐ 요약';
    btn.classList.toggle('locked',isLocked);
    document.getElementById('lockedBanner').classList.toggle('visible',isLocked);
    const body = document.querySelector('#modalOverlay .modal-body');
    if (!body) return;
    if (isLocked) {
        body.classList.add('is-locked'); // renderChildrenCard가 컨테이너를 고르기 전에 클래스 먼저
        renderLockSummary();
    } else {
        body.classList.remove('is-locked');
        renderChildrenCard(); // 요약 → 폼 전환 시 폼 쪽 컨테이너에 다시 렌더
    }
}

function _esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
// 금액 포맷: 숫자면 천단위 콤마, 아니면 원문 그대로
function _fmtAmt(v){
    if(v===null||v===undefined) return '';
    const s=String(v).replace(/,/g,'').trim();
    if(s==='') return '';
    return /^\d+$/.test(s) ? Number(s).toLocaleString() : String(v);
}
function _val(id){ const el=document.getElementById(id); return el ? (el.value||'').trim() : ''; }
function _radio(gid){
    const g=document.getElementById(gid); if(!g) return '';
    const a=g.querySelector('.radio-btn.active'); return a ? (a.getAttribute('data-val')||'') : '';
}
function _radioMulti(gid){
    const g=document.getElementById(gid); if(!g) return [];
    return Array.from(g.querySelectorAll('.radio-btn.active')).map(b=>b.getAttribute('data-val'));
}

// 지도 링크 — 카카오는 이름 기반 길찾기(sName/eName)를 지원, 네이버 웹지도는 좌표가 필요해
// 이름 프리필이 되는 모바일웹 라우트 URL(route.nhn)을 사용 (데스크탑에서도 열림)
const LS_HQ_ADDR='서울특별시 동작구 장승배기로 142'; // 동선 기본 출발지 (사무실)
const kakaoMapUrl=q=>`https://map.kakao.com/?q=${encodeURIComponent(q)}`;
const kakaoRouteUrl=(s,e)=>`https://map.kakao.com/?sName=${encodeURIComponent(s)}&eName=${encodeURIComponent(e)}`;
const naverMapUrl=q=>`https://map.naver.com/p/search/${encodeURIComponent(q)}`;
const naverRouteUrl=(s,e)=>`https://m.map.naver.com/route.nhn?menu=route&sname=${encodeURIComponent(s)}&ename=${encodeURIComponent(e)}&pathType=3`;

let lsShipOpen=false; // 요약 뷰 배송 현황 펼침 여부 (기본 접힘)
function lsToggleShip(){ lsShipOpen=!lsShipOpen; renderLockSummary(); }
function renderLockSummary(){
    const container = document.getElementById('lockSummary');
    if (!container) return;
    const color = currentColor || 'gold';
    const location = [_val('modalLocation'), _val('modalLocationDetail')].filter(Boolean).join(' ');
    const addr = document.getElementById('modalAddress')?.value || '';
    const isAll = isAllDay;
    const sDate = _val(color==='gold'?'goldStartDate':'startDate');
    const eDate = _val(color==='gold'?'goldEndDate':'endDate');
    const sTime = _val(color==='gold'?'goldStartTime':'startTime').substring(0,5);
    const eTime = _val(color==='gold'?'goldEndTime':'endTime').substring(0,5);
    const typeLabel = (window.CALENDAR_CATEGORIES?.[color]?.label) || color;

    const linkedClientName = document.getElementById('linkedClientName')?.textContent?.trim() || '';
    const linkedClientLink = document.getElementById('linkedClientLink')?.getAttribute('href') || '';
    const clientChipHtml = linkedClientName
        ? `<a class="ls-client-chip" href="${_esc(linkedClientLink)}" target="_blank"><span>👤</span>${_esc(linkedClientName)}</a>`
        : '';
    // 원본(렌탈 계약/방송룸 월계약) 이동 칩 — 계약 동기화로 생성된 일정
    let sourceChipHtml = '';
    {
        const SOURCE_NAV = {
            rental_contract: { label: '📄 렌탈 계약', type: 'rental-contracts', url: '/rental-contracts' },
            broadcast_contract: { label: '📄 방송룸 월계약', type: 'broadcast-room', url: '/broadcast-room' },
        };
        const src = detailEvent && SOURCE_NAV[detailEvent.source_type];
        if (src) {
            sourceChipHtml = `<a class="ls-client-chip" href="${src.url}" onclick="event.preventDefault();if(window.parent&&window.parent.drgoTabs){window.parent.drgoTabs.openNav('${src.type}','${src.url}');}else{location.href='${src.url}';}"><span>${src.label}</span></a>`;
        }
    }
    // 연결 프로젝트 칩 — projectSelect가 로드돼 있으면 이름 표시, 아니면 번호로라도 표시
    let projectChipHtml = '';
    let lsProjSummaryPid = null;
    {
        const psel = document.getElementById('projectSelect');
        const pid = (psel && psel.value) ? psel.value : (linkedProjectId || '');
        if (pid) {
            const opt = psel ? [...psel.options].find(o => o.value == pid) : null;
            const plabel = opt ? opt.textContent : ('프로젝트 #' + pid);
            projectChipHtml = `<a class="ls-client-chip" href="/projects/${pid}" target="_blank" style="max-width:100%;"><span>📁</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${_esc(plabel)}</span></a>`;
            if (color !== 'gold') lsProjSummaryPid = pid; // 방문의뢰 외 카테고리: 프로젝트 요약 섹션 노출
        }
    }

    const timeStr = isAll
        ? `${sDate} (종일)`
        : `${sDate}${eDate&&eDate!==sDate?' ~ '+eDate:''}  ${sTime} ~ ${eTime}`;

    // 이사세팅 출발지
    const isMove = color==='gold' && getMultiRadio('g_req_topic_group').includes('이사세팅');
    const mfLoc = [_val('moveFromLocation'), _val('moveFromDetail')].filter(Boolean).join(' ');
    const mfAddr = document.getElementById('moveFromAddress')?.value || '';

    // ── 2a 요약 레이아웃: 상단 메타 행 + 좌/우 2컬럼 카드 그리드 ──
    const left = [], right = [];
    const lsCard = (title, body, extra = '', cls = '') =>
        `<div class="ls-card${cls ? ' ' + cls : ''}">${title ? `<div class="ls-card-head"><span class="ls-card-title">${title}</span>${extra ? `<span class="ls-card-extra">${extra}</span>` : ''}</div>` : ''}${body}</div>`;

    // 메타 행 — 카테고리/의뢰자/프로젝트/원본 + 일정 성격 pills
    const specialSel=[...document.querySelectorAll('#specialOpts .special-opt-btn.active')]
        .map(b=>b.dataset.opt).filter(o=>SPECIAL_OPT_LABELS[o]);
    const specialPills=specialSel.map(o=>`<span class="ls-type-pill">${SPECIAL_ICONS[o]||''} ${_esc(SPECIAL_OPT_LABELS[o])}</span>`).join('');
    const schedEventPills=[...document.querySelectorAll('#schedEventOpts .special-opt-btn.active')]
        .map(b=>`<span class="ls-type-pill">${_esc(b.textContent.trim())}</span>`).join('');
    // 확정 상태(제/희/목/확) — 요약 뷰에서 바로 지정/해제 (저장된 일정 + 편집 권한, 전 카테고리)
    const curSopt=document.querySelector('#scheduleOpts .sched-opt-btn.active')?.dataset.sopt||'';
    let schedPicker='';
    if(editingId&&canEditCalendar){
        schedPicker=`<span class="ls-sopt-pick" title="확정 상태 — 클릭해서 지정, 같은 값 다시 클릭 시 해제">${['suggest','hope','target','confirmed'].map(k=>
            `<button type="button" class="ls-sopt-btn${curSopt===k?' on':''}" data-sopt="${k}" onclick="lsSetSchedOpt('${k}')">${SCHED_CHIP_LABELS[k]}</button>`).join('')}</span>`;
    } else if(curSopt){
        schedPicker=`<span class="ls-type-pill">${_esc(SCHED_FULL_LABELS[curSopt]||curSopt)}</span>`;
    }
    // 의뢰자/프로젝트 줄과 일정 옵션 줄 분리
    const linkRow=(clientChipHtml||projectChipHtml||sourceChipHtml)?`<div class="ls-meta-row">${clientChipHtml}${projectChipHtml}${sourceChipHtml}</div>`:'';
    const optRow=(schedPicker||schedEventPills||specialPills)?`<div class="ls-meta-row">${schedPicker}${schedEventPills}${specialPills}</div>`:'';
    const metaRow=linkRow+optRow;

    // ① 일시·장소 — 굵은 일시 + 굵은 주소 + 특수옵션 회색 라인
    const dowN=['일','월','화','수','목','금','토'];
    const fmtD=d=>{ if(!d) return ''; const dt=new Date(d+'T00:00:00'); return isNaN(dt)?_esc(d):`${String(dt.getMonth()+1).padStart(2,'0')}.${String(dt.getDate()).padStart(2,'0')} (${dowN[dt.getDay()]})`; };
    const timeBig=(isAll)
        ? `${fmtD(sDate)}${eDate&&eDate!==sDate?` – ${fmtD(eDate)}`:''} 종일`
        : `${fmtD(sDate)}${eDate&&eDate!==sDate?` – ${fmtD(eDate)}`:''} ${_esc(sTime)} – ${_esc(eTime)}`;
    // 소요 시간 (같은 날 + 시간 지정 시)
    let durTxt='';
    if(!isAll && sTime && eTime && (!eDate || eDate===sDate)){
        const [sh,sm]=sTime.split(':').map(Number), [eh,em]=eTime.split(':').map(Number);
        const mins=(eh*60+em)-(sh*60+sm);
        if(mins>0) durTxt=`<span class="ls-dur">${Math.floor(mins/60)?Math.floor(mins/60)+'시간':''}${mins%60?(Math.floor(mins/60)?' ':'')+(mins%60)+'분':''}</span>`;
    }
    const specialChipsArr=specialSel.map(o=>SPECIAL_OPT_LABELS[o]);
    const carReasonTxt=specialSel.includes('car')?_val('specialReason'):'';
    const carReasonLine=carReasonTxt?`<div class="ls-car-reason">🚗 차량 이용 사유 — ${_esc(carReasonTxt)}</div>`:'';
    const specialLine=(specialChipsArr.length||carReasonTxt)?`${specialChipsArr.length?`<div class="ls-spec-chips">${specialChipsArr.map(t=>`<span class="ls-spec-chip">${_esc(t)}</span>`).join('')}</div>`:''}${carReasonLine}`:'';
    const addrActions = addr ? `<div class="ls-actions" style="margin-top:8px;">
            <a class="ls-action-btn" href="${kakaoMapUrl(addr)}" target="_blank">카카오 맵</a>
            <a class="ls-action-btn primary" href="${kakaoRouteUrl(LS_HQ_ADDR, addr)}" target="_blank">동선 조회(카카오)</a>
        </div>
        <div class="ls-actions">
            <a class="ls-action-btn" href="${naverMapUrl(addr)}" target="_blank">네이버 맵</a>
            <a class="ls-action-btn primary" href="${naverRouteUrl(LS_HQ_ADDR, addr)}" target="_blank">동선 조회(네이버)</a>
        </div>` : '';
    if (isMove && (mfLoc || location || mfAddr || addr)) {
        left.push(lsCard('일시 · 이사 이동 경로', `
            <div class="ls-big">${timeBig}${durTxt}</div>
            <div style="margin-top:8px;"><div class="ls-info-label">🚚 출발지 (이사 전)</div>
                <div class="ls-addr" style="margin-top:2px;">${document.getElementById('moveNoFrom')?.checked ? '<span style="color:var(--text-muted)">출발지 없음</span>' : (_esc(mfLoc) || '<span style="color:var(--text-muted)">— 미입력 —</span>')}</div></div>
            <div style="margin-top:8px;"><div class="ls-info-label">📍 도착지 (이사 후)</div>
                <div class="ls-addr" style="margin-top:2px;">${_esc(location) || '<span style="color:var(--text-muted)">— 미입력 —</span>'}</div></div>
            ${specialLine}
            <div class="ls-actions" style="margin-top:8px;">
                ${mfAddr ? `<a class="ls-action-btn" href="${kakaoMapUrl(mfAddr)}" target="_blank">출발지</a>` : ''}
                ${addr ? `<a class="ls-action-btn" href="${kakaoMapUrl(addr)}" target="_blank">도착지</a>` : ''}
                ${(mfAddr && addr) ? `<a class="ls-action-btn primary" href="${kakaoRouteUrl(mfAddr, addr)}" target="_blank">출발→도착 동선(카카오)</a>` : ''}
                ${(mfAddr && addr) ? `<a class="ls-action-btn primary" href="${naverRouteUrl(mfAddr, addr)}" target="_blank">출발→도착 동선(네이버)</a>` : ''}
            </div>`, '', 'ls-c-time ls-time-card'));
    } else {
        // 미팅/내방: 장소를 내방 옵션(체크박스)으로 지정하면 location이 비므로 옵션 값을 장소로 표시
        const visitOptsSel=(color==='purple')?[...document.querySelectorAll('#visitOptsList input:checked')].map(i=>i.value):[];
        const locDisplay=_esc(location) || (visitOptsSel.length?`🏢 ${visitOptsSel.map(v=>_esc(v)).join(', ')}`:'');
        left.push(lsCard('일시 · 장소', `
            <div class="ls-big">${timeBig}${durTxt}</div>
            <div class="ls-addr">${locDisplay || '<span style="color:var(--text-muted);font-weight:400;">— 장소 미입력 —</span>'}</div>
            ${specialLine}
            ${addrActions}`, '', 'ls-c-time ls-time-card'));
    }

    // 배송 현황 (gold/green, 송장 있을 때만) — 결제 카드 배지용 수치 겸용
    const lsShips=(SHIP_COLORS.includes(color)?(shipCache.shipments||[]):[]);
    const lsShipsDone=lsShips.filter(s=>s.status==='delivered').length;

    if (color === 'gold') {
        const platform = _radio('g_platform_group');
        const platformEtc = _val('g_platform_etc');
        const career = _radio('g_career_group');
        const source = _radio('g_source_group');
        const sourceRef = _val('g_source_ref');
        const topic = _radio('g_topic_group');
        const topicEtc = _val('g_topic_etc');
        const budget = _radio('g_budget_group');
        const budgetEtc = _val('g_budget_etc');
        const reqTopic = _radio('g_req_topic_group');
        const reqTopicEtc = _val('g_req_topic_etc');
        const paid = _radio('g_paid_group');
        const orderVal = _radio('g_order_group');
        const balanceVal = _radio('g_balance_group');

        // ② 의뢰자 — 닉네임/이름 굵게 + 전화 굵게
        const nick = _val('g_nickname');
        const name = _val('g_name');
        const phone = _val('g_phone');
        const nameNote = (nick && name)
            ? (nick === name ? '<span class="ls-sub-inline">닉네임 동일</span>' : `<span class="ls-sub-inline">이름 ${_esc(name)}</span>`)
            : '';
        left.push(lsCard('의뢰자', `
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="flex:1;min-width:0;">
                    <div class="ls-big" style="font-size:15px;">${_esc(nick || name) || '<span style="color:var(--text-muted);font-weight:400;">— 미입력 —</span>'} ${nameNote}</div>
                    <div class="ls-addr" style="margin-top:4px;">${_esc(phone) || '<span style="color:var(--text-muted);font-weight:400;">전화번호 미입력</span>'}</div>
                </div>
                ${phone?`<a class="ls-call-btn" href="tel:${_esc(phone.replace(/[^0-9+]/g,''))}">전화</a>`:''}
            </div>`, '', 'ls-c-client'));

        // ③ 방송 정보 — 미니 타일 (값 있는 것만, 플랫폼 → 방송주제 → 경력 순)
        const tiles=[];
        const pushTile=(k,v)=>{ if(v) tiles.push(`<div class="ls-tile"><div class="ls-tile-k">${k}</div><div class="ls-tile-v">${_esc(v)}</div></div>`); };
        pushTile('플랫폼', platform === '기타' ? (platformEtc || '기타') : platform);
        pushTile('방송주제', topic === '기타' ? (topicEtc || '기타') : topic);
        pushTile('경력', career);
        pushTile('유입', source ? source + (sourceRef ? ` (${sourceRef})` : '') : '');
        pushTile('예산', budget === '직접입력' ? (budgetEtc || '직접입력') : budget);
        if (tiles.length) left.unshift(lsCard('방송 정보', `<div class="ls-tiles">${tiles.join('')}</div>`, '', 'ls-c-broadcast')); // 일시·장소보다 위에 표시
        // ③-1 의뢰 내용 — 주제 + 세팅 항목 (연결 프로젝트에서 불러오거나, 일정에 저장된 구버전 항목)
        const reqTopicVal = reqTopic === '기타' ? (reqTopicEtc || '기타') : reqTopic;
        const lsReqItems = activeReqItems();
        if (reqTopicVal || lsReqItems.length) {
            let reqBody = reqTopicVal ? `<div class="ls-text-block">${_esc(reqTopicVal)}</div>` : '';
            if (lsReqItems.length) {
                reqBody += `<div style="margin-top:7px;">${reqItemsGroupedHtml(lsReqItems)}</div>`;
                if (projReqItems.length) reqBody += `<div class="rqv-src">📁 연결된 프로젝트의 의뢰 내용</div>`;
            }
            left.splice(tiles.length ? 1 : 0, 0, lsCard('의뢰 내용', reqBody, '', 'ls-c-reqtopic'));
        }

        // ④ 결제 · 진행 — 금액 크게 + 상태 칩(결제/주문/잔금/수령) + 배송 배지
        const amount = _val('g_estimate_amount');
        const balanceAmount = _val('g_balance_amount');
        const savedFlag = document.getElementById('g_estimate_status')?.textContent?.includes('저장');
        const st=(label,val,goodLabel)=>{
            if(val==='O') return `<span class="ls-state on">${goodLabel||label} ✓</span>`;
            if(val==='X') return `<span class="ls-state">${label} ✕</span>`;
            return val?`<span class="ls-state mid">${label} ${_esc(val)}</span>`:'';
        };
        const payChips=[
            (paid==='O'||paid==='결제완료')?'<span class="ls-state on">결제완료</span>':(paid?'<span class="ls-state">결제 ✕</span>':''),
            st('주문',orderVal),
            balanceVal==='O'?'<span class="ls-state warn">잔금 O</span>':st('잔금',balanceVal),
        ].filter(Boolean).join('');
        // 진행 상태 (상태 칩 + 배송 배지) / 결제 금액 — 모바일 시안 기준 분리
        if (payChips || lsShips.length) {
            left.push(lsCard('진행 상태', `<div class="ls-state-chips" style="margin-top:0;">${payChips||'<span class="ls-sub" style="margin:0;">— 표시할 상태 없음 —</span>'}</div>`,
                lsShips.length?`배송 ${lsShipsDone}/${lsShips.length} 완료`:'', 'ls-c-state'));
        }
        left.push(lsCard('결제 금액', `
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="ls-amount-big">${amount?_esc(_fmtAmt(amount))+'원':'<span style="font-size:13px;color:var(--text-muted);font-weight:400;">결제 금액 미입력</span>'}</span>
                <span style="margin-left:auto;display:flex;gap:6px;align-items:center;">
                    ${(paid==='O'||paid==='결제완료')?'<span class="ls-state on">결제완료</span>':''}
                    ${amount && savedFlag ? '<span class="ls-saved-pill">✅ 저장된 금액</span>' : ''}
                </span>
            </div>
            ${balanceVal==='O'&&balanceAmount?`<div class="ls-balance-warn">💰 잔금 ${_esc(_fmtAmt(balanceAmount))}원 있음</div>`:''}`,
            '', 'ls-c-pay'));

        // 우측 ① 장비 목록 — 불릿 + n품목
        const equip = _val('g_equipment');
        const equipLines = equip.split(/\n/).map(s=>s.trim()).filter(Boolean);
        right.push(lsCard('장비 목록',
            equipLines.length
                ? `<ul class="ls-bullets">${equipLines.map(l=>`<li>${_esc(l)}</li>`).join('')}</ul>`
                : '<div class="ls-text-block muted">— 등록된 장비 없음 —</div>',
            equipLines.length?`${equipLines.length}품목`:'', 'ls-c-equip'));

        // 우측 ② 의뢰 세부항목 / ③ 특이사항 (틴트)
        const reqDetail = _val('g_req_detail');
        if (reqDetail) right.push(lsCard('의뢰 세부항목', `<div class="ls-text-block">${_esc(reqDetail)}</div>`, '', 'ls-c-detail'));
        const special = _val('g_special');
        if (special) right.push(lsCard('특이사항 · 필독', `<div class="ls-text-block" style="font-weight:600;">${_esc(special)}</div>`, '', 'ls-tinted ls-c-special'));
    }

    // 원격/방송룸(teal): 대상자 · 의뢰 내용 · 메모 — 폼 입력값을 요약에도 노출
    if (color === 'teal') {
        const tealStudio = (_radio('teal_mode_group') || 'remote') === 'studio';
        const tName = _val(tealStudio ? 't_studio_name' : 't_remote_name');
        const tPlatform = _val(tealStudio ? 't_studio_platform' : 't_remote_platform');
        const tContent = _val(tealStudio ? 't_studio_content' : 't_remote_content');
        const tMemo = _val('t_desc');
        left.push(lsCard(tealStudio ? '🎙 방송룸 이용자' : '🖥 원격 대상자', `
            <div class="ls-big" style="font-size:15px;">${_esc(tName) || '<span style="color:var(--text-muted);font-weight:400;">— 미입력 —</span>'}</div>
            ${tPlatform ? `<div class="ls-addr" style="margin-top:4px;">${_esc(tPlatform)}</div>` : ''}`, '', 'ls-c-client'));
        if (tContent) left.push(lsCard(tealStudio ? '방송룸 이용 내용' : '원격 의뢰 내용', `<div class="ls-text-block">${_esc(tContent)}</div>`, '', 'ls-c-detail'));
        if (tMemo) right.push(lsCard('메모', `<div class="ls-text-block" style="font-weight:600;">${_esc(tMemo)}</div>`, '', 'ls-tinted ls-c-special'));
    }

    // 연결 프로젝트 요약 (방문의뢰 외 카테고리) — 결제 합계/진행 단계를 비동기 로드
    if (lsProjSummaryPid) {
        left.push(`<div class="ls-card ls-c-proj" id="lsProjectSummary" data-pid="${lsProjSummaryPid}">
            <div class="ls-card-head"><span class="ls-card-title">📁 연결 프로젝트 요약</span></div>
            <div class="ls-text-block muted">불러오는 중…</div>
        </div>`);
    }

    // 공통: 상세 설명 / 전달사항
    if (color !== 'gold' && color !== 'teal') {
        const desc = _val('commonDesc');
        if (desc) left.push(lsCard('상세 설명', `<div class="ls-text-block">${_esc(desc)}</div>`, '', 'ls-c-desc'));
        const hNote = _val('commonHandoverNote');
        if (hNote) left.push(lsCard('전달사항', `<div class="ls-text-block">${_esc(hNote)}</div>`, '', 'ls-tinted ls-c-desc'));
    }

    // 배송 현황 목록 카드 (접힘 토글 유지) — 제목 배송 아이콘도 요약에서 바로 지정 가능
    if (lsShips.length) {
        const shipPick=(v,label,color)=>`<button type="button" class="ship-mini-btn ship-ico-btn ${((shipIconOverride||'')===v)?'primary':''}" data-sio="${v}" onclick="event.stopPropagation();setShipIconOverride('${v}')"${color?` style="color:${color};"`:''}>${label}</button>`;
        const shipPicker=canEditCalendar?`<span style="display:inline-flex;gap:4px;align-items:center;">
                <span style="font-size:10px;color:var(--text-muted);margin-right:2px;">제목 표시</span>
                ${shipPick('all','○','var(--green)')}${shipPick('part','△','#d78a2e')}${shipPick('none','✕','var(--red)')}${shipPick('','없음','')}
            </span>`:'';
        left.push(lsCard('', `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                <div class="ls-card-title" style="cursor:pointer;user-select:none;" onclick="lsToggleShip()">${lsShipOpen?'▾':'▸'} 📦 배송 현황 (${lsShipsDone}/${lsShips.length} 완료)</div>
                ${shipPicker}
            </div>
            <div style="display:${lsShipOpen?'block':'none'};margin-top:8px;">${lsShips.map(shipRowHtml).join('')}</div>`, '', 'ls-c-ship'));
    }

    // 우측 하단: 첨부 이미지 (모든 카테고리 공통)
    const imgGroups = [
        { label:'견적서', grid:'quoteGrid' },
        { label:'레퍼런스', grid:'refGrid' },
        { label:'방 사진', grid:'roomGrid' },
        { label:'첨부 파일', grid:'generalGrid' },
    ];
    let imgHtml = '';
    let imgEmpty = [];
    imgGroups.forEach(g => {
        const grid = document.getElementById(g.grid);
        // 이미지는 썸네일, 비이미지 파일(문서 등)은 파일 칩으로 — 둘 다 요약에 노출
        const items = grid ? Array.from(grid.querySelectorAll('.img-item')) : [];
        if (!items.length) { imgEmpty.push(g.label); return; }
        const cells = items.map(it => {
            const im = it.querySelector('img');
            if (im) return `<img src="${_esc(im.src)}" data-full="${_esc(im.dataset.full||im.src)}" alt="${_esc(g.label)}" loading="lazy" decoding="async">`;
            const name = (it.querySelector('.img-filename')?.textContent || '').trim();
            const href = it.querySelector('a.img-fileicon')?.href || '';
            const inner = `📄<span>${_esc(name)||'파일'}</span>`;
            return href
                ? `<a class="ls-file" href="${_esc(href)}" target="_blank" title="${_esc(name)}">${inner}</a>`
                : `<div class="ls-file" title="${_esc(name)}">${inner}</div>`;
        });
        imgHtml += `<div class="ls-img-section">
            <div class="ls-img-label">${g.label}</div>
            <div class="ls-img-grid">${cells.join('')}</div>
        </div>`;
    });
    if (imgEmpty.length) imgHtml += `<div class="ls-sub" style="margin-top:4px;">${imgEmpty.join(' · ')} — 등록된 항목 없음</div>`;
    right.push(lsCard('첨부 파일', imgHtml || '<div class="ls-text-block muted">— 등록된 첨부 없음 —</div>', '<span class="ls-attach-hint">탭하여 크게 보기</span>', 'ls-c-attach'));

    // 모바일 하단 CTA — 현장 확인 완료 (완료 처리 가능 상태에서만)
    const canComplete = detailEvent && !detailEvent.completed_at
        && document.getElementById('btnComplete')?.style.display !== 'none';
    const mobileCta = canComplete ? '<button type="button" class="ls-mobile-cta" onclick="toggleCompleteFromDetail()">현장 확인 완료</button>' : '';

    container.innerHTML = metaRow + `<div class="ls-grid"><div class="ls-col">${left.join('')}</div><div class="ls-col">${right.join('')}</div></div>` + mobileCta
        + `<div id="lsChildren"></div>`;
    renderChildrenCard();
    if (lsProjSummaryPid) lsLoadProjectSummary(lsProjSummaryPid);
}

// ── 연결 프로젝트 요약 (방문의뢰 외 카테고리) — 결제 합계/진행 단계 ──
async function lsLoadProjectSummary(pid){
    const box = document.getElementById('lsProjectSummary');
    if (!box) return;
    try{
        const res = await fetch(`/api/projects/${pid}/summary`, {headers:{'Accept':'application/json'}});
        if (!res.ok) { box.style.display='none'; return; } // 권한 없음/삭제된 프로젝트 등 — 섹션 숨김
        const p = await res.json();
        const cur = document.getElementById('lsProjectSummary');
        if (!cur || cur.dataset.pid != String(pid)) return; // 로딩 중 다른 일정으로 전환됨
        const fmt = n => Number(n||0).toLocaleString();
        cur.innerHTML = `
            <div class="ls-section-title">📁 연결 프로젝트 요약</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <a class="ls-client-chip" href="/projects/${pid}" target="_blank"><span>📁</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${_esc(p.name||('프로젝트 #'+pid))}</span></a>
                ${p.stage?`<span class="ls-type-pill">단계: ${_esc(p.stage)}</span>`:''}
                ${p.work_type?`<span class="ls-type-pill">${_esc(p.work_type)}</span>`:''}
            </div>
            <div style="margin-top:8px;">
                <div class="ls-info-label">결제된 금액</div>
                ${p.payments_count>0
                    ? `<div class="ls-amount">${fmt(p.paid_total)}원</div>
                       <div class="ls-info-label" style="margin-top:3px;">결제 ${p.payments_count}건${p.refunded_total>0?` · 환불/취소 ${fmt(p.refunded_total)}원 반영`:''}${p.last_paid_at?` · 최근 ${_esc(p.last_paid_at)}`:''}</div>`
                    : `<div class="ls-text-block muted">— 결제 내역 없음 —</div>`}
                ${p.outstanding_balance>0?`<div class="ls-info-label" style="margin-top:4px;color:var(--red);font-weight:700;">💸 미수 잔금 ${fmt(p.outstanding_balance)}원</div>`:''}
            </div>`;
    }catch(e){ /* 네트워크 오류 — 로딩 문구 유지 */ }
}

// ── 잔금 배너 ──
function updateBalanceBanner(){
    const banner=document.getElementById('balanceBanner');
    const text=document.getElementById('balanceBannerText');
    const isGold=currentColor==='gold';
    const balanceOn=getRadio('g_balance_group')==='O';
    const amount=document.getElementById('g_balance_amount')?.value?.trim();
    if(isGold&&balanceOn&&amount){
        text.textContent='잔금 '+amount+' 있음';
        banner.style.display='flex';
    }else{
        banner.style.display='none';
    }
}

// ── 커스텀 타임피커 ──
function openTimePicker(trigger,hiddenId){
    if(isLocked) return;
    // 기존 팝업 제거
    document.querySelectorAll('.time-picker-popup').forEach(p=>p.remove());
    const hidden=document.getElementById(hiddenId);
    const [curH,curM]=(hidden.value||'13:00').split(':').map(Number);
    const popup=document.createElement('div');
    popup.className='time-picker-popup';
    popup.innerHTML=`<div class="tp-header"><div class="tp-col-label">시</div><div class="tp-col-label divider-space"></div><div class="tp-col-label">분</div></div><div class="tp-body"><div class="tp-col" id="_tpH"></div><div class="tp-divider"></div><div class="tp-col" id="_tpM"></div></div><div class="tp-footer"><button class="tp-confirm-btn" id="_tpConfirm">확인</button></div>`;
    document.body.appendChild(popup);
    const hCol=popup.querySelector('#_tpH'),mCol=popup.querySelector('#_tpM');
    for(let h=0;h<24;h++){const d=document.createElement('div');d.className='tp-item'+(h===curH?' selected':'');d.dataset.h=h;d.textContent=String(h).padStart(2,'0');d.onclick=()=>{hCol.querySelectorAll('.tp-item').forEach(i=>i.classList.remove('selected'));d.classList.add('selected');};hCol.appendChild(d);}
    for(let m=0;m<60;m+=10){const d=document.createElement('div');d.className='tp-item'+(m===Math.floor(curM/10)*10?' selected':'');d.dataset.m=m;d.textContent=String(m).padStart(2,'0');d.onclick=()=>{mCol.querySelectorAll('.tp-item').forEach(i=>i.classList.remove('selected'));d.classList.add('selected');};mCol.appendChild(d);}
    // 스크롤 to selected
    setTimeout(()=>{hCol.querySelector('.selected')?.scrollIntoView({block:'center'});mCol.querySelector('.selected')?.scrollIntoView({block:'center'});},50);
    // 위치
    const rect=trigger.getBoundingClientRect();
    popup.style.left=rect.left+'px';
    popup.style.top=(rect.bottom+4)+'px';
    if(rect.bottom+260>window.innerHeight) popup.style.top=(rect.top-260)+'px';
    // 확인
    popup.querySelector('#_tpConfirm').onclick=()=>{
        const sh=hCol.querySelector('.selected')?.dataset.h||'13';
        const sm=mCol.querySelector('.selected')?.dataset.m||'0';
        const val=String(sh).padStart(2,'0')+':'+String(sm).padStart(2,'0');
        hidden.value=val; trigger.textContent=val; popup.remove();

        // 시작 시간을 선택하면 종료 시간을 자동으로 +1시간
        if (hiddenId === 'startTime' || hiddenId === 'goldStartTime') {
            const endIdMap = { startTime: 'endTime', goldStartTime: 'goldEndTime' };
            const endTrigMap = { startTime: 'endTimeTrigger', goldStartTime: 'goldEndTimeTrigger' };
            const endHidden = document.getElementById(endIdMap[hiddenId]);
            const endTrig = document.getElementById(endTrigMap[hiddenId]);
            if (endHidden && endTrig) {
                const endH = (parseInt(sh, 10) + 1) % 24;
                const endVal = String(endH).padStart(2, '0') + ':' + String(sm).padStart(2, '0');
                endHidden.value = endVal;
                endTrig.textContent = endVal;
            }
        }
    };
    // 외부 클릭으로 닫기
    setTimeout(()=>{document.addEventListener('click',function handler(e){if(!popup.contains(e.target)&&e.target!==trigger){popup.remove();document.removeEventListener('click',handler);}});},10);
}

