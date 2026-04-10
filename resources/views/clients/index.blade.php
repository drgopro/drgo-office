@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '의뢰자 - 닥터고블린 오피스')

@push('styles')
<style>
    .crm-wrap { display:flex; height:calc(100vh - 86px); overflow:hidden; }

    /* ── 좌측 사이드바 ── */
    .crm-sidebar { width:220px; min-width:220px; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; }
    .sidebar-header { padding:12px; border-bottom:1px solid var(--border); }
    .sidebar-title { font-size:13px; font-weight:700; margin-bottom:10px; color:var(--text-muted); }
    .sidebar-search { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; }
    .sidebar-search:focus { border-color:var(--accent); }
    .sidebar-filters { display:flex; gap:4px; margin-top:8px; flex-wrap:wrap; }
    .filter-chip { padding:3px 8px; border-radius:4px; font-size:10px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:none; color:var(--text-muted); transition:all 0.12s; }
    .filter-chip.active { background:var(--accent); color:#1a1207; border-color:var(--accent); }
    .filter-chip:hover:not(.active) { border-color:var(--accent); color:var(--accent); }

    .sidebar-list { flex:1; overflow-y:auto; padding:6px; }
    .sidebar-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:8px; cursor:pointer; transition:all 0.12s; position:relative; }
    .sidebar-item:hover { background:var(--surface2); }
    .sidebar-item.active { background:var(--surface2); border-left:3px solid var(--accent); }
    .sidebar-item .avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; border:1px solid var(--border); }
    .sidebar-item .item-info { flex:1; min-width:0; }
    .sidebar-item .item-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-item .item-sub { font-size:11px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-item .item-grade { font-size:9px; padding:2px 6px; border-radius:3px; font-weight:600; flex-shrink:0; }
    .grade-normal { background:var(--surface2); color:var(--text-muted); }
    .grade-vip { background:#3a2a1a; color:var(--accent); }
    .grade-rental { background:#1a2a3a; color:var(--blue); }
    .sidebar-item .online-dot { width:6px; height:6px; border-radius:50%; background:var(--green); position:absolute; right:10px; top:50%; transform:translateY(-50%); }

    .sidebar-add { margin:8px; padding:8px; border-radius:8px; border:1px dashed var(--border); text-align:center; font-size:12px; color:var(--text-muted); cursor:pointer; transition:all 0.12s; }
    .sidebar-add:hover { border-color:var(--accent); color:var(--accent); }

    /* ── 우측 메인 ── */
    .crm-main { flex:1; display:flex; flex-direction:column; overflow:hidden; }

    /* 의뢰자 탭 바 */
    .client-tab-bar { display:flex; align-items:center; background:var(--surface); border-bottom:1px solid var(--border); padding:0 12px; height:36px; gap:1px; overflow-x:auto; flex-shrink:0; }
    .client-tab-bar::-webkit-scrollbar { display:none; }
    .client-tab { display:flex; align-items:center; gap:5px; padding:6px 12px; font-size:12px; cursor:pointer; color:var(--text-muted); border:none; background:none; white-space:nowrap; border-radius:5px 5px 0 0; border:1px solid transparent; border-bottom:none; transition:all 0.12s; flex-shrink:0; }
    .client-tab:hover { color:var(--text); background:var(--surface2); }
    .client-tab.active { color:var(--accent); background:var(--surface2); border-color:var(--border); font-weight:600; position:relative; }
    .client-tab.active::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:1px; background:var(--surface2); }
    .client-tab .ct-avatar { width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:8px; font-weight:700; border:1px solid var(--border); }
    .client-tab .ct-close { display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:3px; font-size:9px; opacity:0; transition:opacity 0.1s; }
    .client-tab:hover .ct-close { opacity:0.5; }
    .client-tab .ct-close:hover { opacity:1; background:var(--border); }
    .client-tab-add { padding:4px 8px; font-size:14px; color:var(--text-muted); cursor:pointer; background:none; border:none; border-radius:4px; }
    .client-tab-add:hover { color:var(--accent); background:var(--surface2); }

    /* 의뢰자 상세 영역 */
    .client-content { flex:1; overflow-y:auto; }
    .client-empty { display:flex; align-items:center; justify-content:center; height:100%; color:var(--text-muted); font-size:14px; }
    .client-pane { display:none; padding:20px; }
    .client-pane.active { display:block; }

    /* 상세 헤더 */
    .detail-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
    .detail-identity { display:flex; align-items:center; gap:12px; }
    .detail-avatar { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; border:2px solid var(--accent); }
    .detail-name { font-size:18px; font-weight:700; }
    .detail-meta { font-size:12px; color:var(--text-muted); }
    .detail-actions { display:flex; gap:6px; }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:7px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
    .btn-save:hover { opacity:0.85; }
    .btn-delete { background:none; border:1px solid var(--red); color:var(--red); padding:7px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
    .btn-delete:hover { background:var(--red); color:#fff; }

    /* 서브 탭 */
    .sub-tabs { display:flex; gap:0; border-bottom:1px solid var(--border); margin-bottom:20px; }
    .sub-tab { padding:8px 16px; font-size:13px; color:var(--text-muted); cursor:pointer; border:none; background:none; border-bottom:2px solid transparent; transition:all 0.12s; }
    .sub-tab:hover { color:var(--text); }
    .sub-tab.active { color:var(--accent); border-bottom-color:var(--accent); font-weight:600; }
    .sub-panel { display:none; }
    .sub-panel.active { display:block; }

    /* 폼 필드 */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-grid.full { grid-template-columns:1fr; }
    .field { }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:5px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:8px 10px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-textarea { min-height:60px; resize:vertical; }
    .field-select { cursor:pointer; }

    /* 알림 */
    .toast { position:fixed; bottom:20px; right:20px; background:var(--accent); color:#1a1207; padding:10px 16px; border-radius:8px; font-size:13px; font-weight:600; z-index:999; display:none; }
    .toast.show { display:block; }

    /* 새 의뢰자 모달 */
    .new-client-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:500; align-items:center; justify-content:center; }
    .new-client-overlay.open { display:flex; }
    .new-client-modal { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; width:400px; max-width:90vw; }
    .new-client-modal h3 { font-size:16px; margin-bottom:16px; }

    @media (max-width: 768px) {
        .crm-wrap { flex-direction:column; height:auto; }
        .crm-sidebar { width:100%; min-width:0; max-height:40vh; border-right:none; border-bottom:1px solid var(--border); }
        .form-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="crm-wrap">
    {{-- 좌측 사이드바 --}}
    <div class="crm-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">고객 목록</div>
            <input class="sidebar-search" type="text" id="clientSearch" placeholder="검색..." oninput="filterClients()">
            <div class="sidebar-filters">
                <button class="filter-chip active" data-grade="" onclick="setGradeFilter(this)">전체</button>
                <button class="filter-chip" data-grade="normal" onclick="setGradeFilter(this)">일반</button>
                <button class="filter-chip" data-grade="vip" onclick="setGradeFilter(this)">VIP</button>
                <button class="filter-chip" data-grade="rental" onclick="setGradeFilter(this)">렌탈</button>
            </div>
        </div>
        <div class="sidebar-list" id="clientList"></div>
        <div class="sidebar-add" onclick="openNewClientModal()">+ 의뢰자 등록</div>
    </div>

    {{-- 우측 메인 --}}
    <div class="crm-main">
        <div class="client-tab-bar" id="clientTabBar">
            <span style="padding:0 8px; color:var(--text-muted); font-size:11px;">열린 의뢰자가 없습니다</span>
        </div>
        <div class="client-content" id="clientContent">
            <div class="client-empty" id="clientEmpty">좌측 목록에서 의뢰자를 선택하세요</div>
        </div>
    </div>
</div>

{{-- 새 의뢰자 모달 --}}
<div class="new-client-overlay" id="newClientOverlay" onclick="if(event.target===this) closeNewClientModal()">
    <div class="new-client-modal">
        <h3>의뢰자 등록</h3>
        <div class="form-grid">
            <div class="field">
                <div class="field-label">이름 *</div>
                <input class="field-input" id="ncName">
            </div>
            <div class="field">
                <div class="field-label">닉네임</div>
                <input class="field-input" id="ncNickname">
            </div>
            <div class="field">
                <div class="field-label">연락처</div>
                <input class="field-input" id="ncPhone">
            </div>
            <div class="field">
                <div class="field-label">등급</div>
                <select class="field-input field-select" id="ncGrade">
                    <option value="normal">일반</option>
                    <option value="vip">VIP</option>
                    <option value="rental">렌탈</option>
                </select>
            </div>
        </div>
        <div style="display:flex; gap:8px; margin-top:16px; justify-content:flex-end;">
            <button class="btn-delete" onclick="closeNewClientModal()" style="border-color:var(--border); color:var(--text-muted);">취소</button>
            <button class="btn-save" onclick="createClient()">등록</button>
        </div>
    </div>
</div>

{{-- 앨범 뷰어 모달 --}}
<div id="albumOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:600; align-items:center; justify-content:center;" onclick="if(event.target===this) closeAlbumViewer()">
    <button onclick="closeAlbumViewer()" style="position:fixed; top:16px; right:16px; background:none; border:none; color:#fff; font-size:28px; cursor:pointer; z-index:603;">×</button>
    <button onclick="albumNavDir(-1)" style="position:fixed; left:16px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.12); border:none; color:#fff; width:44px; height:44px; border-radius:50%; font-size:20px; cursor:pointer; z-index:603;">‹</button>
    <button onclick="albumNavDir(1)" style="position:fixed; right:16px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.12); border:none; color:#fff; width:44px; height:44px; border-radius:50%; font-size:20px; cursor:pointer; z-index:603;">›</button>
    <div style="display:flex; flex-direction:column; align-items:center;">
        <div id="albumMediaWrap" style="display:flex; align-items:center; justify-content:center; min-height:200px;"></div>
        <div style="text-align:center; margin-top:10px;">
            <div id="albumName" style="color:#fff; font-size:13px;"></div>
            <div id="albumNote" style="color:rgba(255,255,255,0.5); font-size:11px;"></div>
            <div id="albumCounter" style="color:rgba(255,255,255,0.4); font-size:11px; margin-top:4px;"></div>
        </div>
    </div>
    <div id="albumZoomControls" style="display:none; position:fixed; bottom:20px; left:50%; transform:translateX(-50%); gap:8px; z-index:603;">
        <button onclick="albumZoomStep(-1)" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:16px;cursor:pointer;">−</button>
        <span id="albumZoomLevel" style="min-width:48px;text-align:center;color:#fff;font-size:13px;font-weight:600;line-height:36px;">100%</span>
        <button onclick="albumZoomStep(1)" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:16px;cursor:pointer;">+</button>
        <button onclick="albumZoomReset()" style="height:36px;border-radius:18px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:11px;cursor:pointer;padding:0 12px;">맞춤</button>
    </div>
</div>

<div class="toast" id="toast"></div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const GRADE_LABELS = { normal:'일반', vip:'VIP', rental:'렌탈' };
const GRADE_COLORS = { normal:'var(--text-muted)', vip:'var(--accent)', rental:'var(--blue)' };

let allClients = [];
let currentGrade = '';
let openClientTabs = []; // {id, name, nickname, grade, data, activeSubTab}
let activeClientId = null;

// ── 초기화 ──
loadClientList().then(async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const openId = urlParams.get('open');
    if (openId) {
        await openClient(parseInt(openId));
        history.replaceState(null, '', window.location.pathname);
    } else {
        await restoreClientTabs();
    }
});

async function loadClientList() {
    const res = await fetch('/api/clients/list', { headers:{ 'Accept':'application/json' } });
    allClients = await res.json();
    renderClientList();
}

function filterClients() {
    renderClientList();
}

function setGradeFilter(btn) {
    document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentGrade = btn.dataset.grade;
    renderClientList();
}

function renderClientList() {
    const search = document.getElementById('clientSearch').value.toLowerCase();
    const list = document.getElementById('clientList');
    let filtered = allClients;

    if (search) {
        filtered = filtered.filter(c =>
            (c.name||'').toLowerCase().includes(search) ||
            (c.nickname||'').toLowerCase().includes(search) ||
            (c.phone||'').includes(search)
        );
    }
    if (currentGrade) {
        filtered = filtered.filter(c => c.grade === currentGrade);
    }

    if (!filtered.length) {
        list.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">결과 없음</div>';
        return;
    }

    list.innerHTML = filtered.map(c => {
        const initials = (c.nickname || c.name).substring(0, 2);
        const active = activeClientId === c.id ? 'active' : '';
        return `<div class="sidebar-item ${active}" onclick="openClient(${c.id})">
            <div class="avatar" style="color:${GRADE_COLORS[c.grade]||'var(--text-muted)'}; border-color:${GRADE_COLORS[c.grade]||'var(--border)'}">${initials}</div>
            <div class="item-info">
                <div class="item-name">${c.nickname || c.name}</div>
                <div class="item-sub">${c.name}${c.nickname ? '' : ''}</div>
            </div>
            <span class="item-grade grade-${c.grade}">${GRADE_LABELS[c.grade]||''}</span>
        </div>`;
    }).join('');
}

// ── 의뢰자 탭 ──
async function openClient(id) {
    // 이미 열려있으면 전환만
    const existing = openClientTabs.find(t => t.id === id);
    if (existing) { activateClientTab(id); return; }

    // 데이터 로드
    const res = await fetch(`/api/clients/${id}/detail`, { headers:{ 'Accept':'application/json' } });
    if (!res.ok) { showToast('로드 실패'); return; }
    const data = await res.json();

    openClientTabs.push({
        id: data.id,
        name: data.name,
        nickname: data.nickname,
        grade: data.grade,
        data,
        activeSubTab: 'info'
    });

    activateClientTab(id);
}

function activateClientTab(id) {
    activeClientId = id;
    renderClientTabs();
    renderClientContent(id);
    renderClientList();
    saveClientTabs();
}

function closeClientTab(id, e) {
    if (e) e.stopPropagation();
    const idx = openClientTabs.findIndex(t => t.id === id);
    if (idx === -1) return;

    openClientTabs.splice(idx, 1);
    const pane = document.getElementById('cpane-' + id);
    if (pane) pane.remove();

    if (activeClientId === id) {
        if (openClientTabs.length) {
            const next = openClientTabs[Math.min(idx, openClientTabs.length - 1)];
            activateClientTab(next.id);
        } else {
            activeClientId = null;
            renderClientTabs();
            document.getElementById('clientContent').innerHTML = '<div class="client-empty" id="clientEmpty">좌측 목록에서 의뢰자를 선택하세요</div>';
            renderClientList();
            saveClientTabs();
        }
    } else {
        renderClientTabs();
        saveClientTabs();
    }
}

function renderClientTabs() {
    const bar = document.getElementById('clientTabBar');
    if (!openClientTabs.length) {
        bar.innerHTML = '<span style="padding:0 8px; color:var(--text-muted); font-size:11px;">열린 의뢰자가 없습니다</span>';
        return;
    }
    bar.innerHTML = openClientTabs.map(t => {
        const cls = t.id === activeClientId ? 'active' : '';
        const initials = (t.nickname || t.name).substring(0, 2);
        return `<button class="client-tab ${cls}" onclick="activateClientTab(${t.id})">
            <span class="ct-avatar" style="color:${GRADE_COLORS[t.grade]};border-color:${GRADE_COLORS[t.grade]}">${initials}</span>
            ${t.nickname || t.name}
            <span class="ct-close" onclick="closeClientTab(${t.id}, event)">✕</span>
        </button>`;
    }).join('');
}

const STAGE_LABELS = {consulting:'상담',equipment:'장비파악',proposal:'일정제안',estimate:'견적/계약',payment:'결제/예약',visit:'세팅',as:'AS',done:'완료',cancelled:'취소'};
const TYPE_LABELS = {visit:'방문세팅',remote:'원격세팅',as:'AS'};

function renderClientContent(id) {
    const tab = openClientTabs.find(t => t.id === id);
    if (!tab) return;
    const d = tab.data;

    document.querySelectorAll('.client-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('clientEmpty')?.remove();

    let pane = document.getElementById('cpane-' + id);
    if (!pane) {
        pane = document.createElement('div');
        pane.id = 'cpane-' + id;
        pane.className = 'client-pane';
        document.getElementById('clientContent').appendChild(pane);

        const initials = (d.nickname || d.name).substring(0, 2);
        pane.innerHTML = `
        <div class="detail-header">
            <div class="detail-identity">
                <div class="detail-avatar" style="color:${GRADE_COLORS[d.grade]};border-color:${GRADE_COLORS[d.grade]}">${initials}</div>
                <div>
                    <div class="detail-name">${d.nickname || d.name}</div>
                    <div class="detail-meta">${d.name} · ${GRADE_LABELS[d.grade]} · ${d.assigned_user||''}</div>
                </div>
            </div>
            <div class="detail-actions">
                <button class="btn-save" onclick="saveClient(${id})">저장</button>
                <button class="btn-delete" onclick="deleteClient(${id})">삭제</button>
            </div>
        </div>

        <div class="sub-tabs" id="subtabs-${id}">
            <button class="sub-tab active" onclick="switchSubTab(${id},'info',this)">기본 정보</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'projects',this)">프로젝트 ${d.projects.length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'docs',this)">첨부파일 ${d.documents.length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'memo',this)">메모</button>
        </div>

        <!-- 기본 정보 -->
        <div class="sub-panel active" id="sub-info-${id}">
            <div class="form-grid">
                <div class="field">
                    <div class="field-label">닉네임</div>
                    <input class="field-input" id="f-nickname-${id}" value="${d.nickname||''}">
                </div>
                <div class="field">
                    <div class="field-label">이름</div>
                    <input class="field-input" id="f-name-${id}" value="${d.name||''}">
                </div>
                <div class="field">
                    <div class="field-label">전화번호</div>
                    <input class="field-input" id="f-phone-${id}" value="${d.phone||''}">
                </div>
                <div class="field">
                    <div class="field-label">고객 유형</div>
                    <select class="field-input field-select" id="f-grade-${id}">
                        <option value="normal" ${d.grade==='normal'?'selected':''}>일반</option>
                        <option value="vip" ${d.grade==='vip'?'selected':''}>VIP</option>
                        <option value="rental" ${d.grade==='rental'?'selected':''}>렌탈</option>
                    </select>
                </div>
                <div class="field">
                    <div class="field-label">소속</div>
                    <input class="field-input" id="f-affiliation-${id}" value="${d.affiliation||''}">
                </div>
                <div class="field">
                    <div class="field-label">성별</div>
                    <select class="field-input field-select" id="f-gender-${id}">
                        <option value="">미지정</option>
                        <option value="female" ${d.gender==='female'?'selected':''}>여성</option>
                        <option value="male" ${d.gender==='male'?'selected':''}>남성</option>
                        <option value="other" ${d.gender==='other'?'selected':''}>기타</option>
                    </select>
                </div>
            </div>
            <div class="form-grid" style="margin-top:14px;">
                <div class="field" style="grid-column:1/-1;">
                    <div class="field-label">주소</div>
                    <div style="display:flex; gap:6px;">
                        <input class="field-input" id="f-address-${id}" value="${d.address||''}" readonly style="flex:1; cursor:pointer;" onclick="searchAddress(${id})">
                        <button class="btn-save" onclick="searchAddress(${id})" style="white-space:nowrap;">주소 검색</button>
                    </div>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <div class="field-label">상세주소</div>
                    <input class="field-input" id="f-address_detail-${id}" value="${d.address_detail||''}">
                </div>
            </div>
            <div class="form-grid full" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">특이사항</div>
                    <textarea class="field-input field-textarea" id="f-important_memo-${id}">${d.important_memo||''}</textarea>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:16px; justify-content:flex-end;">
                <button class="btn-save" onclick="saveClient(${id})">저장</button>
            </div>

            <!-- 메모 (인라인 스레드) -->
            <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                <div class="field-label" style="margin:0 0 10px; font-size:12px; font-weight:600;">메모</div>
                <div style="display:flex; gap:8px; margin-bottom:12px;">
                    <textarea class="field-input" id="info-memo-input-${id}" rows="1" placeholder="메모를 입력하세요..." style="flex:1; resize:none; min-height:34px;" onfocus="this.rows=2" onblur="if(!this.value)this.rows=1"></textarea>
                    <button class="btn-save" onclick="addMemo(${id}, 'info')" style="align-self:flex-end; white-space:nowrap; padding:7px 14px;">추가</button>
                </div>
                <div id="info-memos-${id}">${renderInfoMemos(d.memos, id)}</div>
            </div>
        </div>

        <!-- 프로젝트 -->
        <div class="sub-panel" id="sub-projects-${id}">
            <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
                <button class="btn-save" onclick="openProjectForm(${id})">+ 프로젝트</button>
            </div>
            <div id="project-form-${id}" style="display:none; margin-bottom:16px; padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--surface);">
                <div class="form-grid">
                    <div class="field">
                        <div class="field-label">프로젝트명 *</div>
                        <input class="field-input" id="pf-name-${id}">
                    </div>
                    <div class="field">
                        <div class="field-label">유형 *</div>
                        <select class="field-input field-select" id="pf-type-${id}">
                            <option value="visit">방문세팅</option>
                            <option value="remote">원격세팅</option>
                            <option value="as">AS</option>
                        </select>
                    </div>
                </div>
                <div class="field" style="margin-top:10px;">
                    <div class="field-label">메모</div>
                    <textarea class="field-input field-textarea" id="pf-memo-${id}" rows="2"></textarea>
                </div>
                <div style="display:flex; gap:6px; margin-top:10px; justify-content:flex-end;">
                    <button class="btn-delete" onclick="document.getElementById('project-form-${id}').style.display='none'" style="border-color:var(--border); color:var(--text-muted);">취소</button>
                    <button class="btn-save" onclick="createProject(${id})">생성</button>
                </div>
            </div>
            <div id="project-list-${id}">
                ${renderProjectList(d.projects, id)}
            </div>
        </div>

        <!-- 첨부파일 -->
        <div class="sub-panel" id="sub-docs-${id}">
            <div style="margin-bottom:16px; padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--surface);">
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <label style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:var(--accent); color:#1a1207; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">
                        + 파일 추가
                        <input type="file" multiple id="doc-file-${id}" style="display:none;" onchange="docAddFiles(${id}, this)">
                    </label>
                    <select class="field-input field-select" id="doc-cat-${id}" style="width:auto; padding:7px 10px; font-size:12px;">
                        <option>사진/이미지</option>
                        <option>현금영수증</option>
                        <option>사업자등록증</option>
                        <option>계약서</option>
                        <option>견적서</option>
                        <option>기타</option>
                    </select>
                    <input class="field-input" id="doc-note-${id}" placeholder="메모" style="width:140px; padding:7px 10px; font-size:12px;">
                    <button type="button" class="btn-save" id="doc-upload-btn-${id}" onclick="uploadDocs(${id})" disabled style="padding:7px 16px;">업로드</button>
                </div>
                <div id="doc-preview-${id}" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;"></div>
            </div>
            <div id="doc-list-${id}">
                ${renderDocList(d.documents, id)}
            </div>
        </div>

        <!-- 메모 (전체) -->
        <div class="sub-panel" id="sub-memo-${id}">
            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <textarea class="field-input" id="new-memo-${id}" rows="2" placeholder="메모를 입력하세요..." style="flex:1; resize:vertical;"></textarea>
                <button class="btn-save" onclick="addMemo(${id}, 'full')" style="align-self:flex-end; white-space:nowrap;">메모 추가</button>
            </div>
            <div id="memo-thread-${id}">${renderMemoThread(d.memos, id)}</div>
        </div>
        `;
    }
    pane.classList.add('active');
}

function renderProjectList(projects, clientId) {
    if (!projects.length) return '<div style="padding:40px; text-align:center; color:var(--text-muted);">프로젝트가 없습니다.</div>';
    return projects.map(p => `
        <div style="padding:10px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="if(typeof drgoTabs!=='undefined') drgoTabs.openNav('projects','/projects/${p.id}');">
            <div>
                <div style="font-size:14px; font-weight:600;">${p.name}</div>
                <div style="font-size:11px; color:var(--text-muted);">${TYPE_LABELS[p.type]||p.type} · 상담 ${p.consultations_count}건 · ${p.created_at}</div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:10px; padding:3px 8px; border-radius:4px; background:var(--surface2); color:var(--accent); font-weight:600;">${STAGE_LABELS[p.stage]||p.stage}</span>
                <button class="btn-delete" style="padding:3px 8px; font-size:10px;" onclick="event.stopPropagation(); deleteProject(${p.id}, ${clientId})">삭제</button>
            </div>
        </div>
    `).join('');
}

function renderDocList(docs, clientId) {
    if (!docs.length) return '<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">첨부파일이 없습니다.</div>';
    return docs.map((doc, i) => {
        const isImg = doc.mime_type && doc.mime_type.startsWith('image/');
        const isVid = doc.mime_type && doc.mime_type.startsWith('video/');
        const ext = doc.file_name.split('.').pop().toUpperCase();
        const thumbContent = isImg
            ? `<img src="${doc.view_url}" style="width:100%;height:100%;object-fit:cover;" loading="lazy">`
            : isVid ? `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);font-size:14px;">▶</div>`
            : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);font-size:10px;font-weight:600;color:var(--text-muted);">${ext}</div>`;
        return `<div style="display:flex; align-items:center; gap:10px; padding:8px 10px; border-bottom:1px solid var(--border);" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='transparent'">
            <div style="width:40px; height:40px; border-radius:6px; overflow:hidden; flex-shrink:0; cursor:pointer; border:1px solid var(--border);" onclick="openAlbumViewer(${clientId},${i})">${thumbContent}</div>
            <div style="flex:1; min-width:0;">
                <div style="font-size:12px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${doc.file_name}">${doc.file_name}</div>
                <div style="font-size:10px; color:var(--text-muted);">${doc.note ? doc.note + ' · ' : ''}${doc.created_at}</div>
            </div>
            <div style="display:flex; gap:6px; flex-shrink:0;">
                <a href="${doc.download_url}" style="padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; background:var(--surface2); border:1px solid var(--border); color:var(--accent); text-decoration:none; transition:all 0.12s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">다운로드</a>
                <button onclick="deleteDoc(${doc.id},${clientId})" style="padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; background:none; border:1px solid var(--red); color:var(--red); cursor:pointer; transition:all 0.12s;" onmouseover="this.style.background='var(--red)';this.style.color='#fff'" onmouseout="this.style.background='none';this.style.color='var(--red)'">삭제</button>
            </div>
        </div>`;
    }).join('');
}

// ── 주소 검색 (Daum Postcode) ──
function searchAddress(clientId) {
    if (typeof daum === 'undefined' || !daum.Postcode) {
        // 다음 주소 API 동적 로드
        const script = document.createElement('script');
        script.src = '//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
        script.onload = () => _openPostcode(clientId);
        document.head.appendChild(script);
    } else {
        _openPostcode(clientId);
    }
}
function _openPostcode(clientId) {
    new daum.Postcode({
        oncomplete: function(data) {
            document.getElementById('f-address-' + clientId).value = data.address;
            document.getElementById('f-address_detail-' + clientId).focus();
        }
    }).open();
}

// ── 프로젝트 CRUD ──
function openProjectForm(clientId) {
    const form = document.getElementById('project-form-' + clientId);
    form.style.display = 'block';
    document.getElementById('pf-name-' + clientId).value = '';
    document.getElementById('pf-memo-' + clientId).value = '';
}

async function createProject(clientId) {
    const name = document.getElementById('pf-name-' + clientId).value.trim();
    if (!name) return alert('프로젝트명을 입력하세요.');
    const body = {
        name,
        project_type: document.getElementById('pf-type-' + clientId).value,
        memo: document.getElementById('pf-memo-' + clientId).value,
    };
    const res = await fetch(`/clients/${clientId}/projects`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });
    if (res.ok || res.status === 302) {
        document.getElementById('project-form-' + clientId).style.display = 'none';
        await refreshClientData(clientId);
        showToast('프로젝트가 생성되었습니다');
    } else {
        const err = await res.json().catch(() => ({}));
        alert(err.message || '생성 실패');
    }
}

async function deleteProject(projectId, clientId) {
    if (!confirm('이 프로젝트를 삭제하시겠습니까?')) return;
    // 프로젝트는 soft delete이므로 stage를 cancelled로 변경
    await fetch(`/projects/${projectId}/stage`, {
        method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({stage:'cancelled'})
    });
    await refreshClientData(clientId);
    showToast('프로젝트가 취소되었습니다');
}

// ── 첨부파일 업로드 (썸네일 프리뷰 + 누적 목록) ──
const pendingFiles = {}; // clientId → File[]
const IMG_TYPES = ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml'];
const VID_TYPES = ['video/mp4','video/webm','video/ogg','video/quicktime','video/x-msvideo','video/x-matroska'];

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function docAddFiles(clientId, input) {
    if (!pendingFiles[clientId]) pendingFiles[clientId] = [];
    for (const f of input.files) pendingFiles[clientId].push(f);
    input.value = '';
    renderFilePreview(clientId);
}

function removeFile(clientId, idx) {
    pendingFiles[clientId].splice(idx, 1);
    renderFilePreview(clientId);
}

function renderFilePreview(clientId) {
    const container = document.getElementById('doc-preview-' + clientId);
    const btn = document.getElementById('doc-upload-btn-' + clientId);
    const files = pendingFiles[clientId] || [];
    btn.disabled = files.length === 0;

    if (!files.length) { container.innerHTML = ''; return; }

    container.innerHTML = files.map((f, i) => {
        let thumbContent;
        if (IMG_TYPES.includes(f.type)) {
            const url = URL.createObjectURL(f);
            thumbContent = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">`;
        } else if (VID_TYPES.includes(f.type)) {
            thumbContent = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:4px;font-size:16px;">▶</div>`;
        } else if (f.type === 'application/pdf') {
            thumbContent = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:4px;font-size:10px;font-weight:700;color:var(--red);">PDF</div>`;
        } else {
            const ext = f.name.split('.').pop().toUpperCase();
            thumbContent = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:4px;font-size:10px;color:var(--text-muted);">${ext}</div>`;
        }

        return `<div style="width:80px; position:relative;">
            <div style="width:80px; height:80px; border:1px solid var(--border); border-radius:6px; overflow:hidden;">${thumbContent}</div>
            <div style="font-size:9px; color:var(--text-muted); margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${f.name}">${f.name}</div>
            <div style="font-size:9px; color:var(--text-muted);">${formatFileSize(f.size)}</div>
            <button onclick="removeFile(${clientId},${i})" style="position:absolute;top:-4px;right:-4px;width:18px;height:18px;border-radius:50%;background:var(--red);color:#fff;border:none;font-size:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;">×</button>
        </div>`;
    }).join('');
}

async function uploadDocs(clientId) {
    const files = pendingFiles[clientId] || [];
    if (!files.length) return;

    const formData = new FormData();
    files.forEach(f => formData.append('files[]', f));
    formData.append('category', document.getElementById('doc-cat-' + clientId).value);
    formData.append('note', document.getElementById('doc-note-' + clientId).value);

    const btn = document.getElementById('doc-upload-btn-' + clientId);
    btn.disabled = true;
    btn.textContent = '업로드 중...';

    const res = await fetch(`/clients/${clientId}/documents`, {
        method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body:formData
    });

    btn.textContent = '업로드';

    if (res.ok || res.status === 302) {
        pendingFiles[clientId] = [];
        document.getElementById('doc-note-' + clientId).value = '';
        renderFilePreview(clientId);
        await refreshClientData(clientId);
        showToast(`${files.length}개 파일 업로드 완료`);
    } else {
        btn.disabled = false;
        alert('업로드 실패');
    }
}

async function deleteDoc(docId, clientId) {
    if (!confirm('이 파일을 삭제하시겠습니까?')) return;
    await fetch(`/documents/${docId}`, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    await refreshClientData(clientId);
    showToast('삭제되었습니다');
}

// ── 앨범 뷰어 ──
let albumDocs = [], albumIdx = 0, zoomScale = 1, panX = 0, panY = 0, isPanning = false, panStartX, panStartY, baseW = 0, baseH = 0;

function openAlbumViewer(clientId, idx) {
    const tab = openClientTabs.find(t => t.id === clientId);
    if (!tab) return;
    albumDocs = tab.data.documents;
    albumIdx = idx;
    renderAlbumMedia();
    document.getElementById('albumOverlay').style.display = 'flex';
}
function closeAlbumViewer() {
    document.getElementById('albumOverlay').style.display = 'none';
    document.getElementById('albumMediaWrap').innerHTML = '';
    document.getElementById('albumZoomControls').style.display = 'none';
    zoomScale = 1; panX = 0; panY = 0;
}
function albumNavDir(dir) {
    albumIdx = (albumIdx + dir + albumDocs.length) % albumDocs.length;
    zoomScale = 1; panX = 0; panY = 0;
    renderAlbumMedia();
}
function albumZoomStep(dir) {
    const steps = [0.5, 0.75, 1, 1.5, 2, 3, 4];
    let ci = steps.indexOf(zoomScale); if (ci === -1) ci = 2;
    ci = Math.max(0, Math.min(steps.length - 1, ci + dir));
    zoomScale = steps[ci];
    if (zoomScale === 1) { panX = 0; panY = 0; }
    applyAlbumZoom();
}
function albumZoomReset() { zoomScale = 1; panX = 0; panY = 0; applyAlbumZoom(); }
function applyAlbumZoom() {
    const img = document.querySelector('#albumMediaWrap img.album-media');
    if (!img) return;
    if (zoomScale === 1) { img.style.width = ''; img.style.height = ''; }
    else { img.style.width = (baseW * zoomScale) + 'px'; img.style.height = (baseH * zoomScale) + 'px'; }
    img.style.transform = `translate(${panX}px,${panY}px)`;
    document.getElementById('albumZoomLevel').textContent = Math.round(zoomScale * 100) + '%';
}
function renderAlbumMedia() {
    const doc = albumDocs[albumIdx]; if (!doc) return;
    const wrap = document.getElementById('albumMediaWrap');
    const zoomCtrl = document.getElementById('albumZoomControls');
    wrap.innerHTML = '';
    const isImage = doc.mime_type && doc.mime_type.startsWith('image/');
    zoomCtrl.style.display = isImage ? 'flex' : 'none';
    if (isImage) {
        const img = document.createElement('img');
        img.className = 'album-media'; img.src = doc.view_url;
        img.style.maxWidth = '85vw'; img.style.maxHeight = '75vh';
        img.onload = () => { baseW = img.offsetWidth; baseH = img.offsetHeight; };
        img.addEventListener('wheel', e => { e.preventDefault(); albumZoomStep(e.deltaY < 0 ? 1 : -1); }, {passive:false});
        img.addEventListener('mousedown', e => { if (zoomScale===1) return; isPanning=true; panStartX=e.clientX-panX; panStartY=e.clientY-panY; e.preventDefault(); });
        img.addEventListener('dblclick', () => { zoomScale===1 ? albumZoomStep(2) : albumZoomReset(); });
        wrap.appendChild(img);
    } else if (doc.mime_type && doc.mime_type.startsWith('video/')) {
        const vid = document.createElement('video');
        vid.className = 'album-media'; vid.src = doc.view_url; vid.controls = true; vid.autoplay = true;
        vid.style.maxWidth = '85vw'; vid.style.maxHeight = '75vh';
        wrap.appendChild(vid);
    } else if (doc.mime_type === 'application/pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = doc.view_url; iframe.style.cssText = 'width:80vw;height:75vh;border:none;';
        wrap.appendChild(iframe);
    } else {
        wrap.innerHTML = '<div style="color:var(--text-muted);font-size:14px;padding:60px;text-align:center;">미리보기를 지원하지 않는 파일입니다.</div>';
    }
    document.getElementById('albumName').textContent = doc.file_name;
    document.getElementById('albumNote').textContent = doc.note || '';
    document.getElementById('albumCounter').textContent = `${albumIdx + 1} / ${albumDocs.length}`;
}
document.addEventListener('mousemove', e => { if (!isPanning) return; panX = e.clientX - panStartX; panY = e.clientY - panStartY; applyAlbumZoom(); });
document.addEventListener('mouseup', () => { isPanning = false; });

// ── 클라이언트 데이터 새로고침 ──
async function refreshClientData(clientId) {
    const res = await fetch(`/api/clients/${clientId}/detail`, { headers:{'Accept':'application/json'} });
    if (!res.ok) return;
    const data = await res.json();
    const tab = openClientTabs.find(t => t.id === clientId);
    if (tab) {
        tab.data = data;
        tab.name = data.name;
        tab.nickname = data.nickname;
        tab.grade = data.grade;
    }
    // 해당 pane 재생성
    const pane = document.getElementById('cpane-' + clientId);
    if (pane) pane.remove();
    renderClientContent(clientId);
    renderClientTabs();
}

function switchSubTab(clientId, tab, btn) {
    const t = openClientTabs.find(t => t.id === clientId);
    if (t) t.activeSubTab = tab;

    document.querySelectorAll(`#subtabs-${clientId} .sub-tab`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    ['info','projects','docs','memo'].forEach(k => {
        const panel = document.getElementById(`sub-${k}-${clientId}`);
        if (panel) panel.classList.toggle('active', k === tab);
    });
}

// ── 저장/삭제 ──
async function saveClient(id) {
    const body = {
        name: document.getElementById(`f-name-${id}`).value,
        nickname: document.getElementById(`f-nickname-${id}`).value,
        phone: document.getElementById(`f-phone-${id}`).value,
        grade: document.getElementById(`f-grade-${id}`).value,
        affiliation: document.getElementById(`f-affiliation-${id}`)?.value || '',
        gender: document.getElementById(`f-gender-${id}`)?.value || null,
        address: document.getElementById(`f-address-${id}`)?.value || '',
        address_detail: document.getElementById(`f-address_detail-${id}`)?.value || '',
        important_memo: document.getElementById(`f-imp-memo-${id}`)?.value || document.getElementById(`f-important_memo-${id}`)?.value || '',
        memo: document.getElementById(`f-memo-${id}`)?.value || '',
    };

    const res = await fetch(`/api/clients/${id}`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });

    if (res.ok) {
        showToast('저장되었습니다');
        // 사이드바 리스트 갱신
        loadClientList();
    } else {
        showToast('저장 실패');
    }
}

async function deleteClient(id) {
    if (!confirm('이 의뢰자를 삭제하시겠습니까?')) return;
    const res = await fetch(`/clients/${id}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    if (res.ok) {
        closeClientTab(id);
        loadClientList();
        showToast('삭제되었습니다');
    }
}

// ── 새 의뢰자 ──
function openNewClientModal() { document.getElementById('newClientOverlay').classList.add('open'); }
function closeNewClientModal() { document.getElementById('newClientOverlay').classList.remove('open'); }

async function createClient() {
    const name = document.getElementById('ncName').value.trim();
    if (!name) return alert('이름을 입력하세요.');

    const body = {
        name,
        nickname: document.getElementById('ncNickname').value.trim(),
        phone: document.getElementById('ncPhone').value.trim(),
        grade: document.getElementById('ncGrade').value,
    };

    const res = await fetch('/api/clients', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });

    if (res.ok) {
        const data = await res.json();
        closeNewClientModal();
        document.getElementById('ncName').value = '';
        document.getElementById('ncNickname').value = '';
        document.getElementById('ncPhone').value = '';
        await loadClientList();
        openClient(data.id);
        showToast('등록되었습니다');
    } else {
        const err = await res.json();
        alert(err.message || Object.values(err.errors||{}).flat().join('\n') || '등록 실패');
    }
}

// ── 토스트 ──
// ── 메모 스레드 ──
function renderMemoItem(m, clientId) {
    return `<div style="display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--border);" id="memo-item-${m.id}">
        <div style="width:30px; height:30px; border-radius:50%; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:var(--accent); flex-shrink:0;">${(m.user_name||'?').substring(0,1)}</div>
        <div style="flex:1; min-width:0;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="font-size:12px; font-weight:600;">${m.user_name}</span>
                    <span style="font-size:10px; color:var(--text-muted); margin-left:6px;">${m.created_at}</span>
                </div>
                <button onclick="deleteMemo(${m.id},${clientId})" style="background:none; border:none; color:var(--text-muted); font-size:10px; cursor:pointer; opacity:0.5;" onmouseover="this.style.opacity=1;this.style.color='var(--red)'" onmouseout="this.style.opacity=0.5;this.style.color='var(--text-muted)'">삭제</button>
            </div>
            <div style="font-size:13px; margin-top:4px; white-space:pre-wrap; word-break:break-word;">${m.content}</div>
        </div>
    </div>`;
}

function renderMemoThread(memos, clientId) {
    if (!memos || !memos.length) return '<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">메모가 없습니다.</div>';
    return memos.map(m => renderMemoItem(m, clientId)).join('');
}

function renderInfoMemos(memos, clientId) {
    if (!memos || !memos.length) return '<div style="padding:12px; text-align:center; color:var(--text-muted); font-size:12px;">메모가 없습니다.</div>';
    const recent = memos.slice(0, 3);
    const rest = memos.slice(3);
    let html = recent.map(m => renderMemoItem(m, clientId)).join('');
    if (rest.length) {
        html += `<div id="info-memos-rest-${clientId}" style="display:none;">
            ${rest.map(m => renderMemoItem(m, clientId)).join('')}
        </div>`;
        html += `<div style="text-align:center; padding:8px;" id="info-memos-toggle-${clientId}">
            <button onclick="toggleMoreMemos(${clientId})" style="background:none; border:1px solid var(--border); color:var(--accent); font-size:11px; padding:4px 12px; border-radius:5px; cursor:pointer;">+ ${rest.length}개 더 보기</button>
        </div>`;
    }
    return html;
}

function toggleMoreMemos(clientId) {
    const rest = document.getElementById('info-memos-rest-' + clientId);
    const toggle = document.getElementById('info-memos-toggle-' + clientId);
    if (!rest) return;
    const isHidden = rest.style.display === 'none';
    rest.style.display = isHidden ? 'block' : 'none';
    toggle.querySelector('button').textContent = isHidden ? '접기' : `+ ${rest.children.length}개 더 보기`;
}

async function addMemo(clientId, from) {
    const inputId = from === 'info' ? 'info-memo-input-' + clientId : 'new-memo-' + clientId;
    const textarea = document.getElementById(inputId);
    const content = textarea.value.trim();
    if (!content) return;

    const res = await fetch(`/api/clients/${clientId}/memos`, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ content })
    });

    if (res.ok) {
        textarea.value = '';
        if (from === 'info') textarea.rows = 1;
        await refreshClientData(clientId);
        showToast('메모가 추가되었습니다');
    } else {
        alert('메모 추가 실패');
    }
}

async function deleteMemo(memoId, clientId) {
    if (!confirm('이 메모를 삭제하시겠습니까?')) return;
    await fetch(`/api/client-memos/${memoId}`, {
        method:'DELETE',
        headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    await refreshClientData(clientId);
    showToast('메모가 삭제되었습니다');
}

function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2000);
}

// ── 의뢰자 탭 상태 저장/복원 ──
function saveClientTabs() {
    const data = {
        tabs: openClientTabs.map(t => t.id),
        activeId: activeClientId
    };
    sessionStorage.setItem('drgo_client_tabs', JSON.stringify(data));
}

async function restoreClientTabs() {
    try {
        const raw = sessionStorage.getItem('drgo_client_tabs');
        if (!raw) return;
        const data = JSON.parse(raw);
        if (!data.tabs || !data.tabs.length) return;

        for (const id of data.tabs) {
            await openClient(id);
        }
        if (data.activeId && openClientTabs.find(t => t.id === data.activeId)) {
            activateClientTab(data.activeId);
        }
    } catch {}
}

// 키보드 단축키
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAlbumViewer(); closeNewClientModal(); }
    if (document.getElementById('albumOverlay').style.display === 'flex') {
        if (e.key === 'ArrowLeft') albumNavDir(-1);
        if (e.key === 'ArrowRight') albumNavDir(1);
    }
});
</script>
@endpush
