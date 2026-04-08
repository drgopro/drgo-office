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
loadClientList();

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
    renderClientList(); // sidebar active 표시
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
        }
    } else {
        renderClientTabs();
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

function renderClientContent(id) {
    const tab = openClientTabs.find(t => t.id === id);
    if (!tab) return;
    const d = tab.data;

    // 모든 client pane 숨기기
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
            <button class="sub-tab" onclick="switchSubTab(${id},'projects',this)">상담/프로젝트 ${d.projects.length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'docs',this)">문서/파일 ${d.documents.length}</button>
            <button class="sub-tab" onclick="switchSubTab(${id},'memo',this)">메모</button>
        </div>

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
            <div class="form-grid full" style="margin-top:14px;">
                <div class="field">
                    <div class="field-label">주소</div>
                    <input class="field-input" id="f-address-${id}" value="${d.address||''}">
                </div>
                <div class="field">
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
        </div>

        <div class="sub-panel" id="sub-projects-${id}">
            ${d.projects.length ? d.projects.map(p => `
                <div style="padding:10px; border:1px solid var(--border); border-radius:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:14px; font-weight:600;">${p.name}</div>
                        <div style="font-size:11px; color:var(--text-muted);">${p.type} · ${p.stage} · 상담 ${p.consultations_count}건</div>
                    </div>
                    <div style="font-size:11px; color:var(--text-muted);">${p.created_at}</div>
                </div>
            `).join('') : '<div style="padding:40px; text-align:center; color:var(--text-muted);">프로젝트가 없습니다.</div>'}
        </div>

        <div class="sub-panel" id="sub-docs-${id}">
            ${d.documents.length ? `<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:10px;">
                ${d.documents.map(doc => {
                    const isImage = doc.mime_type && doc.mime_type.startsWith('image/');
                    const preview = isImage
                        ? `<div style="width:100%; height:90px; background:url('${doc.view_url}') center/cover; border-radius:6px;"></div>`
                        : `<div style="width:100%; height:90px; display:flex; align-items:center; justify-content:center; background:var(--surface2); border-radius:6px; font-size:11px; color:var(--text-muted);">${doc.file_name.split('.').pop().toUpperCase()}</div>`;
                    return `<div style="background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                        <a href="${doc.view_url}" target="_blank">${preview}</a>
                        <div style="padding:6px 8px;">
                            <div style="font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${doc.file_name}">${doc.file_name}</div>
                            <div style="font-size:10px; color:var(--text-muted);">${doc.note||''}</div>
                        </div>
                    </div>`;
                }).join('')}
            </div>` : '<div style="padding:40px; text-align:center; color:var(--text-muted);">문서가 없습니다.</div>'}
        </div>

        <div class="sub-panel" id="sub-memo-${id}">
            <div class="form-grid full">
                <div class="field">
                    <div class="field-label">중요 메모</div>
                    <textarea class="field-input field-textarea" id="f-imp-memo-${id}" rows="3">${d.important_memo||''}</textarea>
                </div>
                <div class="field">
                    <div class="field-label">일반 메모</div>
                    <textarea class="field-input field-textarea" id="f-memo-${id}" rows="4">${d.memo||''}</textarea>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:16px; justify-content:flex-end;">
                <button class="btn-save" onclick="saveClient(${id})">저장</button>
            </div>
        </div>
        `;
    }

    pane.classList.add('active');
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
function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2000);
}
</script>
@endpush
