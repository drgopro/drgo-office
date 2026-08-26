{{-- 의뢰자/프로젝트 연동·SWR 캐시·결제 연동·이미지 첨부 --}}
// ── 의뢰자/프로젝트 연동 ──
let linkedClientId=null, linkedProjectId=null;
// 사내업무(blue)/휴가·개인(red)은 의뢰자 연동 대상 아님 — 과거 누수로 오염된 client_id가
// 열람 시 복원되고 저장 시 재기록되는 것을 차단 (setColor의 섹션 숨김 규칙과 동일)
const CLIENT_LINK_EXCLUDED_COLORS=['blue','red'];
function colorSupportsClientLink(c){ return !CLIENT_LINK_EXCLUDED_COLORS.includes(c); }
let clientSearchTimer=null;

function renderClientList(list){
    if(!list.length) return '<div style="padding:10px;font-size:12px;color:var(--text-muted);text-align:center;">결과 없음</div>';
    return list.map(c=>{
        const nick=c.nickname||'';const nm=c.name||'';const ph=c.phone||'';
        return `<div style="padding:8px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;transition:background 0.1s;" onmouseover="this.style.background='var(--surface)'" onmouseout="this.style.background=''">
            <div style="flex:1;cursor:pointer;min-width:0;" onclick="selectClient(${c.id},'${nick.replace(/'/g,"\\'")}','${nm.replace(/'/g,"\\'")}','${ph.replace(/'/g,"\\'")}')">
                <span style="font-weight:600;font-size:13px;">${nick||nm}</span>${nick&&nm?' <span style="color:var(--text-muted);font-size:12px;">('+nm+')</span>':''}
                <span style="color:var(--text-muted);font-size:11px;margin-left:6px;">${ph}</span>
            </div>
            <button onclick="event.stopPropagation();window.open('/clients/${c.id}','_blank')" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:3px 8px;border-radius:6px;font-size:10px;cursor:pointer;flex-shrink:0;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">보기</button>
        </div>`;
    }).join('');
}

function searchClients(query){
    clearTimeout(clientSearchTimer);
    const results=document.getElementById('clientSearchResults');
    if(!query.trim()||query.length<1){
        // 빈 입력 → 최근 의뢰자 목록
        loadRecentClients();
        return;
    }
    clientSearchTimer=setTimeout(async()=>{
        const res=await fetch(`/api/clients/search?q=${encodeURIComponent(query)}`);
        if(!res.ok)return;
        const list=await res.json();
        results.innerHTML=renderClientList(list);
        results.style.display='';
    },250);
}

async function loadRecentClients(){
    const results=document.getElementById('clientSearchResults');
    results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">로딩 중...</div>';
    results.style.display='';
    try{
        const res=await fetch('/api/clients/list?limit=15');
        if(res.ok){const data=await res.json();const list=data.data||data;results.innerHTML=renderClientList(Array.isArray(list)?list.slice(0,15):[]);}
    }catch(e){results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">로드 실패</div>';}
}

// 기존 일정 복원 시 client_id만 있고 이름이 없을 때, API로 닉네임/이름 조회해 표시 갱신
async function restoreLinkedClientName(id){
    try{
        const res=await fetch(`/api/clients/${id}/detail`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        const c=await res.json();
        const label=(c.nickname||c.name)?((c.nickname||c.name)+(c.nickname&&c.name?' ('+c.name+')':'')):`의뢰자 #${id}`;
        const el=document.getElementById('linkedClientName');
        // 현재 표시 중인 대상이 여전히 같은 의뢰자일 때만 갱신
        if(el && linkedClientId===id){
            el.textContent=label;
            if(isLocked) renderLockSummary(); // 비동기 이름 도착 후 요약 칩의 '의뢰자 #id' → 실제 이름 갱신
        }
    }catch(e){}
}

async function selectClient(id,nickname,name,phone){
    linkedClientId=id;
    document.getElementById('clientSearchResults').style.display='none';
    document.getElementById('clientSearchInput').value='';
    document.getElementById('linkedClientName').textContent=(nickname||name)+(nickname&&name?' ('+name+')':'');
    document.getElementById('linkedClientInfo').style.display='';
    document.getElementById('linkedClientLink').href='/clients/'+id;
    // gold 필드 자동채움
    const gNick=document.getElementById('g_nickname');
    const gName=document.getElementById('g_name');
    const gPhone=document.getElementById('g_phone');
    if(gNick) gNick.value=nickname||'';
    if(gName) gName.value=name||'';
    if(gPhone) gPhone.value=phone||'';
    // teal(원격/방송룸): 모드 미선택이어도 보이도록 양쪽 이름 필드 모두 채움 (빈 칸만)
    if(currentColor==='teal'){
        ['t_remote_name','t_studio_name'].forEach(fid=>{
            const el=document.getElementById(fid);
            if(el&&!el.value.trim()) el.value=nickname||name||'';
        });
    }
    // 제목 비어있으면 의뢰자명으로 보조 채움
    document.getElementById('modalTitle').value=document.getElementById('modalTitle').value||(nickname||name);
    // 프로젝트 목록 로드 + 상세정보(주소/연락처/플랫폼) 연동
    const detail=await loadClientProjects(id);
    applyClientDetail(detail);
}

// 의뢰자 상세 → 일정 폼 매핑 (이미 입력된 값은 덮어쓰지 않음)
function applyClientDetail(d){
    if(!d) return;
    // 연락처 보강 (검색 결과에 전화번호가 없던 경우)
    const gPhone=document.getElementById('g_phone');
    if(gPhone&&!gPhone.value.trim()&&d.phone) gPhone.value=d.phone;
    // 주소는 자동으로 채우지 않는다 — 의뢰자 정보의 구주소가 몰래 들어가는 것 방지.
    // 필요 시 '👤 의뢰자 주소'/'📁 프로젝트 주소' 버튼이나 주소 검색(수기)으로 명시적으로 채운다.
    if(d.address&&!document.getElementById('modalLocation')?.value.trim()&&typeof showCalToast==='function'){
        showCalToast('의뢰자에 저장된 주소가 있습니다 — 필요하면 장소의 👤 의뢰자 주소 버튼으로 불러오세요');
    }
    // teal(원격/방송룸): 플랫폼 텍스트 필드 채움 (양쪽 모드, 기타 직접입력 포함)
    if(currentColor==='teal'){
        const platTxt=[...(d.platforms||[]).filter(Boolean).filter(v=>v!=='기타'), d.platform_etc].filter(Boolean).join(', ');
        if(platTxt){
            ['t_remote_platform','t_studio_platform'].forEach(fid=>{
                const el=document.getElementById(fid);
                if(el&&!el.value.trim()) el.value=platTxt;
            });
        }
    }
    // 방송 주제 — 의뢰자 콘텐츠 유형과 목록 동일 → 그대로 매핑 (빈 경우만, 기타 직접입력 포함)
    const ctypes=(d.content_types||[]).filter(Boolean);
    if((ctypes.length||d.topic_etc)&&!getMultiRadio('g_topic_group').length){
        const KNOWN_TOPICS=['소통','게임','노래','먹방','야외','버추얼','코인','주식','기타','미정'];
        const etcT=[...ctypes.filter(t=>!KNOWN_TOPICS.includes(t)),d.topic_etc].filter(Boolean);
        const pills=[...new Set([...ctypes.map(t=>KNOWN_TOPICS.includes(t)?t:'기타'), ...(etcT.length?['기타']:[])])];
        if(pills.length) setMultiRadio('g_topic_group',pills);
        if(etcT.length){ const e=document.getElementById('g_topic_etc'); if(e&&!e.value.trim()) e.value=etcT.join(', '); }
    }
    // 플랫폼 — 의뢰자 관리 명칭 → 캘린더 pill 매핑
    const plats=(d.platforms||[]).filter(Boolean);
    if(plats.length&&!getMultiRadio('g_platform_group').length){
        const PLAT_MAP={'유튜브':'유튜브','치지직':'치지직','아프리카':'SOOP','SOOP':'SOOP','틱톡':'틱톡'};
        const pills=[...new Set(plats.map(p=>PLAT_MAP[p]||'기타'))];
        setMultiRadio('g_platform_group',pills);
        // 매핑 안 되는 플랫폼(트위치/팬더 등)과 기타 입력값은 '기타' 입력칸에 원문 유지
        const etcNames=[...plats.filter(p=>!PLAT_MAP[p]&&p!=='기타'),d.platform_etc].filter(Boolean);
        if(etcNames.length){
            const e=document.getElementById('g_platform_etc');
            if(e&&!e.value.trim()) e.value=etcNames.join(', ');
        }
    }
    // 방송 경력 — 의뢰자 프로필 값을 그대로 반영 (모달 기본값 '처음'보다 프로필이 우선)
    if(d.career) setRadio('g_career_group',d.career);
    // 유입 경로 — 의뢰자 키 → 캘린더 pill 매핑 (전체 대응)
    if(d.inflow_source&&!getRadio('g_source_group')){
        const SRC_MAP={ad:'광고',search:'검색',referral:'소개',sns:'SNS',community:'커뮤니티',other:'기타'};
        if(SRC_MAP[d.inflow_source]) setRadio('g_source_group',SRC_MAP[d.inflow_source]);
    }
    // 예산 성향 — 풍족/부족/모름은 pill로, 그 외 자유 서술은 직접입력으로
    if(d.budget_style&&!getRadio('g_budget_group')){
        const b=d.budget_style.trim();
        if(['풍족','부족','모름'].includes(b)){ setRadio('g_budget_group',b); }
        else{
            setRadio('g_budget_group','직접입력');
            const e=document.getElementById('g_budget_etc');
            if(e&&!e.value.trim()) e.value=b;
        }
    }
}

// ── 세션 캐시(SWR) — 연동 데이터를 캐시로 즉시 표시하고 백그라운드에서 최신화 ──
// 모달이 열린 뒤 비동기 응답으로 화면이 늦게 바뀌어 버그처럼 보이던 문제 완화 (재열람 시 즉시 최종 모습)
function swrGet(key){ try{ const raw=sessionStorage.getItem('calswr:'+key); return raw?JSON.parse(raw).data:null; }catch(e){ return null; } }
function swrSet(key,data){ try{ sessionStorage.setItem('calswr:'+key, JSON.stringify({data,ts:Date.now()})); }catch(e){} }
function swrDel(key){ try{ sessionStorage.removeItem('calswr:'+key); }catch(e){} }

function applyClientProjects(clientId,data){
    const wrap=document.getElementById('projectSelectWrap');
    const sel=document.getElementById('projectSelect');
    if(String(linkedClientId)!==String(clientId)) return; // 로딩 중 의뢰자가 바뀜
    const projects=(data&&data.projects)||[];
    if(!projects.length){wrap.style.display='none';return;}
    sel.innerHTML='<option value="">프로젝트 선택 (선택사항)</option>';
    projects.forEach(p=>{
        const opt=document.createElement('option');
        opt.value=p.id;
        const tags=[...((p.tags&&p.tags.major)||[]),...((p.tags&&p.tags.minor)||[])];
        opt.textContent=`${p.name} (${p.stage_label||p.stage||p.type||''})`+(p.created_at?` · ${p.created_at}`:'')+(tags.length?` — #${tags.join(' #')}`:'');
        sel.appendChild(opt);
    });
    // 이전에 연결된 프로젝트가 있으면 선택
    if(linkedProjectId) sel.value=linkedProjectId;
    wrap.style.display='';
    syncProjectPaymentFields(); // 결제 금액/잔금을 프로젝트 결제 데이터로 연동
    if(isLocked) renderLockSummary(); // 요약 뷰에 프로젝트명 반영
}

// 연동 의뢰자의 최신 상세 (의뢰자 주소 불러오기 버튼용)
let linkedClientDetail=null;
function updateClientAddrBtn(){
    const b=document.getElementById('btnClientAddr');
    if(b) b.style.display=(linkedClientDetail&&linkedClientDetail.address)?'':'none';
}
function resetLinkedClientDetail(){ linkedClientDetail=null; updateClientAddrBtn(); updateProjAddrBtn(); }

// 현재 선택된 프로젝트의 상세 (의뢰자 상세의 projects 배열에서 조회)
function selectedProjectData(){
    const wrap=document.getElementById('projectSelectWrap');
    const pid=(wrap&&wrap.style.display!=='none')?(document.getElementById('projectSelect')?.value||null):null;
    if(!pid||!linkedClientDetail) return null;
    return (linkedClientDetail.projects||[]).find(p=>String(p.id)===String(pid))||null;
}
function updateProjAddrBtn(){
    const b=document.getElementById('btnProjectAddr');
    if(b){ const p=selectedProjectData(); b.style.display=(p&&p.address)?'':'none'; }
}
// 장소를 선택한 프로젝트의 세팅 장소로 채움 (버튼 클릭 = 덮어쓰기 의사 표시)
function applyLinkedProjectAddress(){
    const p=selectedProjectData();
    if(!p||!p.address){ alert('선택한 프로젝트에 저장된 세팅 장소가 없습니다.\n프로젝트 상세의 세팅 장소에서 등록할 수 있습니다.'); return; }
    document.getElementById('modalLocation').value=p.address;
    document.getElementById('modalAddress').value=p.address;
    const det=document.getElementById('modalLocationDetail');
    if(det) det.value=p.address_detail||'';
}
// 프로젝트 선택 시 — 주소 자동 채움 없이 📁 버튼 토글만 (채움은 버튼 클릭 = 명시적 의사 표시)
function autoFillProjectAddress(){
    updateProjAddrBtn();
}
// 장소를 연동 의뢰자에 저장된 주소로 채움 (기존 입력 덮어씀 — 버튼을 눌렀다는 것이 의사 표시)
function applyLinkedClientAddress(){
    const d=linkedClientDetail;
    if(!d||!d.address){ alert('연동된 의뢰자에 저장된 주소가 없습니다.'); return; }
    const extras=(d.extra_addresses||[]).filter(a=>a&&a.address);
    if(!extras.length){ _applyClientAddr(d.address,d.address_detail); return; }
    // 주소가 여러 개 — 어떤 주소를 넣을지 선택
    openClientAddrPicker([
        {label:'주소 1 (메인)', address:d.address, address_detail:d.address_detail},
        ...extras.map((a,i)=>({label:`주소 ${i+2}`, address:a.address, address_detail:a.address_detail})),
    ]);
}
function _applyClientAddr(addr,det){
    document.getElementById('modalLocation').value=addr;
    document.getElementById('modalAddress').value=addr;
    const el=document.getElementById('modalLocationDetail');
    if(el) el.value=det||'';
}
function openClientAddrPicker(list){
    closeClientAddrPicker();
    const ov=document.createElement('div');
    ov.id='clientAddrPicker';
    ov.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:700;display:flex;align-items:center;justify-content:center;padding:20px;';
    ov.onclick=e=>{ if(e.target===ov) closeClientAddrPicker(); };
    ov.innerHTML=`<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:480px;width:100%;padding:20px;">
        <div style="font-size:14px;font-weight:700;margin-bottom:12px;">어떤 주소를 넣을까요?</div>
        ${list.map((a,i)=>`<button type="button" data-i="${i}" style="display:block;width:100%;text-align:left;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 14px;margin-bottom:8px;color:var(--text);font-size:13px;cursor:pointer;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">${a.label}</div>
            <div>${_esc(a.address)}${a.address_detail?`, ${_esc(a.address_detail)}`:''}</div>
        </button>`).join('')}
        <div style="text-align:right;margin-top:4px;"><button type="button" onclick="closeClientAddrPicker()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:7px 14px;border-radius:8px;font-size:12px;cursor:pointer;">취소</button></div>
    </div>`;
    ov.querySelectorAll('button[data-i]').forEach(b=>{ b.onclick=()=>{ const a=list[parseInt(b.dataset.i,10)]; _applyClientAddr(a.address,a.address_detail); closeClientAddrPicker(); }; });
    document.body.appendChild(ov);
}
function closeClientAddrPicker(){ const el=document.getElementById('clientAddrPicker'); if(el) el.remove(); }

async function loadClientProjects(clientId){
    const wrap=document.getElementById('projectSelectWrap');
    // 캐시 즉시 표시
    const cached=swrGet('clidet:'+clientId);
    if(cached){ applyClientProjects(clientId,cached); linkedClientDetail=cached; updateClientAddrBtn(); updateProjAddrBtn(); }
    try{
        const res=await fetch(`/api/clients/${clientId}/detail`);
        if(!res.ok){ if(!cached) wrap.style.display='none'; return cached; }
        const data=await res.json();
        swrSet('clidet:'+clientId,data);
        // 캐시와 다를 때만 다시 그림 (동일하면 재렌더 생략)
        if(!cached||JSON.stringify(cached.projects||[])!==JSON.stringify(data.projects||[])) applyClientProjects(clientId,data);
        if(!(data.projects||[]).length&&!cached) wrap.style.display='none';
        // 항상 서버 최신값으로 갱신 — 의뢰자 주소 버튼이 수정 전 주소를 물고 있지 않도록
        linkedClientDetail=data; updateClientAddrBtn(); updateProjAddrBtn();
        return data;
    }catch(e){ if(!cached) wrap.style.display='none'; return cached; }
}

// ── 프로젝트 결제 연동 — 결제된 금액/잔금은 연결 프로젝트의 결제 합계·미수 잔금에서 자동 반영 (읽기 전용) ──
let projPayLinked=false;
async function syncProjectPaymentFields(){
    const wrap=document.getElementById('projectSelectWrap');
    const pid=(wrap&&wrap.style.display!=='none')?(document.getElementById('projectSelect')?.value||null):null;
    const amt=document.getElementById('g_estimate_amount');
    const bal=document.getElementById('g_balance_amount');
    const note=document.getElementById('projPayNote');
    const extractBtn=document.getElementById('g_estimate_btn');
    if(!pid||currentColor!=='gold'){
        // 연동 해제 — 수기 입력 복원
        projPayLinked=false;
        if(amt){amt.readOnly=false;amt.style.opacity='';}
        if(bal){bal.readOnly=false;bal.style.opacity='';}
        if(extractBtn) extractBtn.style.display='';
        if(note) note.style.display='none';
        return;
    }
    const apply=(p)=>{
        // 로딩 중 프로젝트가 바뀌었으면 무시
        const curPid=(wrap&&wrap.style.display!=='none')?(document.getElementById('projectSelect')?.value||null):null;
        if(String(curPid)!==String(pid)) return;
        projPayLinked=true;
        const paid=Number(p.paid_total||0);
        const outstanding=Number(p.outstanding_balance||0);
        if(amt){amt.value=paid>0?paid.toLocaleString():'';amt.readOnly=true;amt.style.opacity='0.75';}
        if(extractBtn) extractBtn.style.display='none';
        setRadio('g_balance_group', outstanding>0?'O':'X');
        if(outstanding>0&&bal){bal.value=outstanding.toLocaleString();}
        if(bal){bal.readOnly=true;bal.style.opacity='0.75';}
        updateBalanceBanner();
        if(note){
            note.style.display='';
            note.textContent=`🔗 프로젝트 결제 연동 — 결제 ${paid.toLocaleString()}원 · 미수 잔금 ${outstanding.toLocaleString()}원 (수정은 프로젝트 결제에서)`;
        }
        if(isLocked) renderLockSummary();
    };
    // 캐시 즉시 적용 → 백그라운드 최신화
    const cached=swrGet('projsum:'+pid);
    if(cached) apply(cached);
    try{
        const res=await fetch(`/api/projects/${pid}/summary`,{headers:{'Accept':'application/json'}});
        if(!res.ok) return;
        const p=await res.json();
        swrSet('projsum:'+pid,p);
        if(!cached||JSON.stringify(cached)!==JSON.stringify(p)) apply(p);
    }catch(e){}
}

// 대여 이력 등록 토글 (신규 등록 시에만 사용) — teal: 방송룸 시간/월, 렌탈 카테고리: 렌탈 월계약
function brRentalKind(){
    const label=(window.CALENDAR_CATEGORIES?.[currentColor]?.label)||'';
    if(currentColor==='teal'||label.includes('방송룸')) return 'broadcast';
    if(label==='렌탈') return 'rental';
    return null;
}
function updateBrRentalUI(){
    const g=document.getElementById('brRentalGroup');
    if(!g) return;
    const kind=brRentalKind();
    g.style.display=(!editingId&&kind)?'':'none';
    const sel=document.getElementById('brRentalMode');
    const kl=document.getElementById('brRentalKindLabel');
    if(kind==='rental'){
        if(sel) sel.innerHTML='<option value="rental">렌탈 월 계약</option>';
        if(kl) kl.textContent='(렌탈)';
    }else{
        if(sel&&!sel.querySelector('option[value="hourly"]')) sel.innerHTML='<option value="hourly">시간 대여</option><option value="monthly">월 대여</option>';
        if(kl) kl.textContent='(방송룸)';
    }
    onBrRentalModeChange();
}
function onBrRentalModeChange(){
    // 렌탈 월계약에는 호실 개념 없음
    const mode=document.getElementById('brRentalMode')?.value;
    const room=document.getElementById('brRentalRoom');
    if(room) room.style.display=mode==='rental'?'none':'';
}
function onBrRentalToggle(){
    const on=document.getElementById('brRentalChk')?.checked;
    const f=document.getElementById('brRentalFields');
    if(f) f.style.display=on?'flex':'none';
}
function collectBrRental(){
    // 편집 모드에선 미지원 (신규 등록만)
    if(editingId || !brRentalKind() || !document.getElementById('brRentalChk')?.checked) return null;
    return {
        mode: document.getElementById('brRentalMode')?.value||'hourly',
        room_no: document.getElementById('brRentalRoom')?.value.trim()||null,
        fee: parseInt(document.getElementById('brRentalFee')?.value||'0')||0,
    };
}
function unlinkClient(){
    linkedClientId=null;linkedProjectId=null;
    resetLinkedClientDetail();
    loadProjectReqItems(null); // 프로젝트 의뢰 내용 표시 해제
    document.getElementById('linkedClientInfo').style.display='none';
    document.getElementById('linkedClientName').textContent=''; // 잔여 텍스트가 저장 시 client_name으로 새는 것 방지
    document.getElementById('projectSelectWrap').style.display='none';
    {const psel=document.getElementById('projectSelect'); if(psel) psel.innerHTML='';}
    syncProjectPaymentFields(); // 연동 해제 — 결제/잔금 수기 입력 복원
    document.getElementById('g_nickname').value='';
    document.getElementById('g_name').value='';
    document.getElementById('g_phone').value='';
}

// 검색 외부 클릭 시 닫기
document.addEventListener('click',e=>{
    const results=document.getElementById('clientSearchResults');
    const input=document.getElementById('clientSearchInput');
    if(results&&input&&!results.contains(e.target)&&e.target!==input) results.style.display='none';
});

// ── 이미지 첨부 ──
let pendingAttachments={quote:[],reference:[],room:[],general:[]};
let existingAttachments={quote:[],reference:[],room:[],general:[]};
const GRID_MAP={quote:'quoteGrid',reference:'refGrid',room:'roomGrid',general:'generalGrid'};
const FILE_MAP={quote:'fileQuote',reference:'fileReference',room:'fileRoom',general:'fileGeneral'};

function triggerAttach(type){document.getElementById(FILE_MAP[type]).click();}

function handleImgFiles(type,files){
    if(!files||!files.length) return;
    Array.from(files).forEach(f=>pendingAttachments[type].push({file:f,note:''}));
    renderImgGrid(type);
    // 파일 input 리셋
    const input=document.getElementById(FILE_MAP[type]); if(input) input.value='';
}

// 클립보드 붙여넣기 — 일정 모달이 열려 있을 때 이미지 파일을 첨부 파일(general)로 적재
document.addEventListener('paste',e=>{
    if(!document.getElementById('modalOverlay').classList.contains('open')) return;
    if(typeof isLocked!=='undefined'&&isLocked) return; // 요약(잠금) 뷰에서는 무시
    const files=[...(e.clipboardData?.files||[])];
    if(!files.length) return;
    e.preventDefault();
    const stamped=files.map((f,i)=>{
        if(f.name&&f.name!=='image.png') return f;
        const ext=(f.type.split('/')[1]||'png').replace('jpeg','jpg');
        const stamp=new Date().toISOString().slice(0,19).replaceAll(':','').replace('T','-');
        return new File([f],`붙여넣기-${stamp}${i?'-'+i:''}.${ext}`,{type:f.type});
    });
    handleImgFiles('general',stamped);
    if(typeof showCalToast==='function') showCalToast('📎 클립보드 이미지가 첨부 파일에 추가되었습니다');
});

// 공통 유형의 '첨부 파일' 섹션(generalAttachSection) 핸들러 — general 타입으로 적재
function handleGeneralFiles(files){
    if(!files||!files.length) return;
    Array.from(files).forEach(f=>pendingAttachments.general.push({file:f,note:''}));
    renderImgGrid('general');
    const inp=document.getElementById('generalFileInput'); if(inp) inp.value='';
}

// general은 두 곳(이미지 그룹 generalGrid + 공통 generalAttachGrid)에 모두 그릴 수 있음
function gridIdsFor(type){
    return type==='general' ? ['generalGrid','generalAttachGrid'] : [GRID_MAP[type]];
}
function renderImgGrid(type){
    const isImage = type!=='general'; // general은 이미지가 아닐 수 있어 미리보기 대신 파일명 위주
    gridIdsFor(type).forEach(gid=>{
        const grid=document.getElementById(gid); if(!grid) return;
        grid.innerHTML='';
        // 기존
        existingAttachments[type].forEach((a,i)=>{
            const isImg=(a.mime_type||'').startsWith('image/');
            const thumb = isImg
                ? `<img src="${a.thumb_url||a.url}" data-full="${a.url}" alt="${a.file_name||''}" loading="lazy" decoding="async">`
                : `<a href="${a.url}" target="_blank" class="img-fileicon">📄</a>`;
            grid.innerHTML+=`<div class="img-item"><div class="img-thumb-wrap">${thumb}<button class="img-remove" onclick="removeExistingAttach('${type}',${i},${a.id})">✕</button></div><div class="img-filename">${a.file_name||''}</div></div>`;
        });
        // 새로 추가된
        pendingAttachments[type].forEach((item,i)=>{
            const div=document.createElement('div');div.className='img-item';
            const wrap=document.createElement('div');wrap.className='img-thumb-wrap';
            if(item.file.type.startsWith('image/')){
                const img=document.createElement('img');img.src=URL.createObjectURL(item.file);img.alt=item.file.name;wrap.appendChild(img);
            } else {
                const ic=document.createElement('div');ic.className='img-fileicon';ic.textContent='📄';wrap.appendChild(ic);
            }
            const rm=document.createElement('button');rm.className='img-remove';rm.textContent='✕';
            rm.onclick=()=>{pendingAttachments[type].splice(i,1);renderImgGrid(type);};
            wrap.appendChild(rm);div.appendChild(wrap);
            const fn=document.createElement('div');fn.className='img-filename';fn.textContent=item.file.name;div.appendChild(fn);
            const note=document.createElement('textarea');note.className='img-note';note.placeholder='주석 입력';note.rows=1;
            note.value=item.note||'';note.oninput=()=>{item.note=note.value;};
            div.appendChild(note);grid.appendChild(div);
        });
    });
}

// 드래그 드롭
[['quoteZone','quote'],['refZone','reference'],['roomZone','room'],['generalZone','general'],['uploadZone','general']].forEach(([zid,type])=>{
    const zone=document.getElementById(zid); if(!zone) return;
    zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag-over');});
    zone.addEventListener('dragleave',()=>zone.classList.remove('drag-over'));
    zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('drag-over');handleImgFiles(type,e.dataTransfer.files);});
});

async function removeExistingAttach(type,idx,id){
    if(!confirm('이 이미지를 삭제하시겠습니까?')) return;
    await fetch(`/api/schedule-attachments/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}});
    existingAttachments[type].splice(idx,1);renderImgGrid(type);
    if(editingId) swrDel('attach:'+editingId); // 캐시 무효화
}
async function uploadPendingAttachments(scheduleId){
    const TYPE_LABEL={quote:'견적서',reference:'참고자료',room:'방 사진',general:'첨부 파일'};
    let failedTypes=[];
    for(const type of ['quote','reference','room','general']){
        if(!pendingAttachments[type].length) continue;
        const fd=new FormData();fd.append('attachment_type',type);
        pendingAttachments[type].forEach(item=>fd.append('files[]',item.file));
        try{
            const res=await fetch(`/api/schedules/${scheduleId}/attachments`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:fd});
            if(!res.ok){
                const err=await res.json().catch(()=>({}));
                console.error(`첨부파일 업로드 실패 (${type}):`, res.status, err);
                failedTypes.push((TYPE_LABEL[type]||type)+(err.message?` (${err.message})`:''));
            }else{
                pendingAttachments[type]=[]; // 성공한 타입은 대기열 비움 (재시도/재저장 시 중복 업로드 방지)
            }
        }catch(e){ console.error(`첨부파일 업로드 오류 (${type}):`,e); failedTypes.push(TYPE_LABEL[type]||type); }
    }
    if(failedTypes.length) showCalToast('⚠ '+failedTypes.join(', ')+' 업로드 실패');
    swrDel('attach:'+scheduleId); // 캐시 무효화 — 다음 열람 시 최신 목록 반영
}
function applyAttachmentList(list){
    existingAttachments={quote:[],reference:[],room:[],general:[]};
    (list||[]).forEach(a=>{if(existingAttachments[a.attachment_type])existingAttachments[a.attachment_type].push(a);});
    ['quote','reference','room','general'].forEach(t=>renderImgGrid(t));
    // 요약 뷰가 켜진 상태라면 첨부 반영 후 요약을 다시 렌더
    if(isLocked) renderLockSummary();
}
async function loadExistingAttachments(scheduleId){
    // 캐시 즉시 표시 → 백그라운드 최신화 (재열람 시 이미지가 늦게 떠 요약이 다시 그려지던 문제 완화)
    const cached=swrGet('attach:'+scheduleId);
    applyAttachmentList(cached||[]);
    try{
        const res=await fetch(`/api/schedules/${scheduleId}/attachments`);
        if(!res.ok) return;
        const list=await res.json();
        if(editingId&&String(editingId)!==String(scheduleId)) return; // 로딩 중 다른 일정으로 전환됨
        const changed=!cached||JSON.stringify(cached)!==JSON.stringify(list);
        swrSet('attach:'+scheduleId,list);
        if(changed) applyAttachmentList(list);
    }catch(e){}
}
