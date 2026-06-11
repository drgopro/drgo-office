@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '프로젝트 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px 32px; max-width:1600px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:18px; font-weight:700; }
    .search-bar { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
    .search-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .search-input { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:240px; }
    .search-input:focus { border-color:var(--accent); }
    .btn-search { background:var(--accent); color:var(--accent-text); border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .btn-search { color:#fff; }
    .btn-search-reset { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 14px; border-radius:8px; font-size:12px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
    .btn-search-reset:hover { border-color:var(--accent); color:var(--accent); }

    /* 체크박스 칩 그룹 */
    .filter-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .filter-label { font-size:11px; color:var(--text-muted); letter-spacing:0.06em; min-width:42px; }
    .chip-toggle { position:relative; display:inline-flex; align-items:center; }
    .chip-toggle input { position:absolute; opacity:0; pointer-events:none; }
    .chip-toggle .chip { display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border:1px solid var(--border); border-radius:20px; background:var(--surface); color:var(--text-muted); font-size:12px; cursor:pointer; transition:all .15s; user-select:none; line-height:1.4; }
    .chip-toggle .chip::before { content:""; width:8px; height:8px; border-radius:50%; background:var(--text-muted); opacity:0.4; transition:all .15s; }
    .chip-toggle:hover .chip { border-color:var(--accent); color:var(--text); }
    .chip-toggle input:checked + .chip { color:var(--text); border-color:var(--accent); background:var(--surface2); }
    .chip-toggle input:checked + .chip::before { background:var(--accent); opacity:1; }
    /* stage별 컬러 dot */
    .chip-toggle[data-stage="consulting"] input:checked + .chip::before { background:#c8b08a; }
    .chip-toggle[data-stage="equipment"] input:checked + .chip::before,
    .chip-toggle[data-stage="proposal"] input:checked + .chip::before,
    .chip-toggle[data-stage="estimate"] input:checked + .chip::before { background:#8ab4c8; }
    .chip-toggle[data-stage="payment"] input:checked + .chip::before { background:#e8894a; }
    .chip-toggle[data-stage="visit"] input:checked + .chip::before { background:#7ac87a; }
    .chip-toggle[data-stage="as"] input:checked + .chip::before { background:#c87a7a; }
    .chip-toggle[data-stage="done"] input:checked + .chip::before { background:#a09890; }

    .table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table { width:100%; border-collapse:collapse; }
    thead { background:var(--surface2); }
    th { padding:11px 16px; text-align:left; font-size:11px; color:var(--text-muted); font-weight:600; letter-spacing:0.05em; border-bottom:1px solid var(--border); }
    td { padding:12px 16px; font-size:13px; border-bottom:1px solid var(--border); }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:var(--surface2); }
    .project-link { font-weight:600; color:var(--text); text-decoration:none; }
    .project-link:hover { color:var(--accent); }
    .client-link { color:var(--text-muted); font-size:12px; text-decoration:none; }
    .client-link:hover { color:var(--accent); }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-visit   { background:#1a3a2a; color:#7ac87a; }
    .badge-remote  { background:#1a2a3a; color:#8ab4c8; }
    .badge-as      { background:#2a1a1a; color:#c87a7a; }

    .stage-badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .stage-consulting { background:#2a2010; color:var(--accent); }
    .stage-equipment  { background:#1a2a1a; color:#7ac87a; }
    .stage-proposal   { background:#1a1a2a; color:#8ab4c8; }
    .stage-estimate   { background:#2a1a2a; color:#9b70c8; }
    .stage-payment    { background:#1a2a2a; color:#4ecdc4; }
    .stage-visit      { background:#1a2a1a; color:#7ac87a; }
    .stage-as         { background:#2a1a1a; color:#c87a7a; }
    .stage-done       { background:var(--surface2); color:var(--text-muted); }

    .empty { text-align:center; padding:60px; color:var(--text-muted); font-size:14px; }
    .pagination { display:flex; justify-content:center; margin-top:20px; }
    [data-theme="light"] .badge-visit   { background:#e8f5e8; color:#1a7a2a; }
    [data-theme="light"] .badge-remote  { background:#e0f0ff; color:#1a5a8a; }
    [data-theme="light"] .badge-as      { background:#ffe8e8; color:#a03030; }
    [data-theme="light"] .stage-consulting { background:#fff3e0; color:#a06800; }
    [data-theme="light"] .stage-equipment  { background:#e8f5e8; color:#248a38; }
    [data-theme="light"] .stage-proposal   { background:#e0f0ff; color:#2e6a9a; }
    [data-theme="light"] .stage-estimate   { background:#f0e8ff; color:#5c2e90; }
    [data-theme="light"] .stage-payment    { background:#e0f8f5; color:#0a8a70; }
    [data-theme="light"] .stage-visit      { background:#e8f5e8; color:#248a38; }
    [data-theme="light"] .stage-as         { background:#ffe8e8; color:#c03838; }
    [data-theme="light"] .stage-done       { background:#e8eaef; color:#5a6070; }
    @media (max-width: 768px) {
        .page-wrap { padding:16px; }
        .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
        table { min-width:600px; }
        th, td { padding:10px; font-size:12px; white-space:nowrap; }
        .search-bar { flex-direction:column; }
        .search-input { width:100%; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">프로젝트 관리</div>
        <div style="display:flex; gap:8px;">
            @if(Auth::user()->hasPermission('projects.edit'))
                <button style="background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;" onclick="openNewProjectModal()">+ 새 프로젝트</button>
            @endif
            <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openExcelImportModal('projects','프로젝트')">📥 엑셀 가져오기</button>
        </div>
    </div>

    @php
        $rawStage = request('stage');
        $selectedStages = is_array($rawStage)
            ? array_filter($rawStage)
            : array_filter(array_map('trim', explode(',', (string) $rawStage)));
        $rawType = request('project_type');
        $selectedTypes = is_array($rawType)
            ? array_filter($rawType)
            : array_filter(array_map('trim', explode(',', (string) $rawType)));

        $stageOptions = [
            'consulting' => '상담',
            'equipment' => '장비파악',
            'proposal' => '일정제안',
            'estimate' => '견적/계약',
            'payment' => '결제/예약',
            'visit' => '세팅 진행',
            'as' => '세팅 완료·AS',
            'done' => '완료',
        ];
        $typeOptions = [
            'visit' => '방문세팅',
            'remote' => '원격세팅',
            'design' => '디자인',
            'inquiry' => '단순문의',
            'as' => 'A/S',
            'troubleshoot' => '문제 해결',
        ];
    @endphp

    <form method="GET" action="{{ route('projects.index') }}" class="search-bar">
        <div class="search-row">
            <input class="search-input" type="text" name="search" placeholder="의뢰자명, 프로젝트명 검색" value="{{ request('search') }}">
            <button type="submit" class="btn-search">검색</button>
            @if(!empty($selectedStages) || !empty($selectedTypes) || request('search'))
                <a href="{{ route('projects.index') }}" class="btn-search-reset">↺ 초기화</a>
            @endif
        </div>
        <div class="filter-group">
            <span class="filter-label">단계</span>
            @foreach($stageOptions as $v => $lbl)
                <label class="chip-toggle" data-stage="{{ $v }}">
                    <input type="checkbox" name="stage[]" value="{{ $v }}" {{ in_array($v, $selectedStages, true) ? 'checked' : '' }}>
                    <span class="chip">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>
        <div class="filter-group">
            <span class="filter-label">유형</span>
            @foreach($typeOptions as $v => $lbl)
                <label class="chip-toggle">
                    <input type="checkbox" name="project_type[]" value="{{ $v }}" {{ in_array($v, $selectedTypes, true) ? 'checked' : '' }}>
                    <span class="chip">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>
    </form>
    <script>
        // 체크박스 변경 시 자동 폼 제출 (UX 개선)
        document.querySelectorAll('.search-bar .chip-toggle input').forEach(el => {
            el.addEventListener('change', () => el.closest('form').submit());
        });
    </script>

    <div class="table-wrap">
        @if($projects->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>프로젝트명</th>
                    <th>의뢰자</th>
                    <th>유형</th>
                    <th>단계</th>
                    <th>담당자</th>
                    <th>시작일</th>
                </tr>
            </thead>
            <tbody>
            @php
                // 동적 라벨 매핑 — 한 번만 로드해서 foreach에서 사용 (N+1 방지)
                $ptDefaults = ['visit'=>'방문세팅','remote'=>'원격세팅','design'=>'디자인','inquiry'=>'단순문의','as'=>'A/S','troubleshoot'=>'문제 해결'];
                $ptLabelMap = \App\Models\ConsultationType::pluck('label', 'key')->toArray();
            @endphp
                @foreach($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="project-link" onclick="event.preventDefault(); if (typeof openTopTab === 'function') openTopTab('projects', '/projects/{{ $project->id }}', '📁 {{ addslashes($project->name) }}'); else window.location.href=this.href;">{{ $project->name }}</a>
                    </td>
                    <td>
                        <a href="{{ route('clients.index', ['open' => $project->client->id]) }}" class="client-link" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openClientDetail({{ $project->client->id }}); else window.location.href=this.href;">
                            {{ $project->client->name }}
                            @if($project->client->nickname)
                                ({{ $project->client->nickname }})
                            @endif
                        </a>
                    </td>
                    <td>
                        <span class="badge badge-{{ $project->project_type }}">
                            {{ $ptLabelMap[$project->project_type] ?? ($ptDefaults[$project->project_type] ?? $project->project_type) }}
                        </span>
                    </td>
                    <td>
                        <span class="stage-badge stage-{{ $project->stage }}">
                            {{ ['consulting'=>'상담','equipment'=>'장비파악','proposal'=>'일정제안','estimate'=>'견적/계약','payment'=>'결제/예약','visit'=>'세팅','as'=>'AS','done'=>'완료','cancelled'=>'취소'][$project->stage] }}
                        </span>
                    </td>
                    <td>{{ $project->assignedUser?->display_name ?? '-' }}</td>
                    <td>{{ $project->created_at->format('Y.m.d') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <div class="empty">프로젝트가 없습니다.</div>
        @endif
    </div>

    <div class="pagination">
        {{ $projects->appends(request()->query())->links() }}
    </div>
</div>

@if(Auth::user()->hasPermission('projects.edit'))
{{-- 새 프로젝트 모달 --}}
<div id="newProjectOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:9000; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) drgoModalMinimize(this, '+ 새 프로젝트', '📁')">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);">
            <div style="font-size:15px; font-weight:700;">+ 새 프로젝트</div>
            <button type="button" onclick="closeNewProjectModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
        </div>
        <div style="padding:18px 20px; display:flex; flex-direction:column; gap:14px;">
            {{-- 단순 결제 토글 --}}
            <label style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; cursor:pointer; user-select:none;">
                <input type="checkbox" id="npPaymentOnly" onchange="togglePaymentOnly(this.checked)">
                <div style="flex:1;">
                    <div style="font-size:13px; font-weight:600;">💳 단순 결제 프로젝트</div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">상담/단계 없이 결제 내역만 관리합니다.</div>
                </div>
            </label>

            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">의뢰자 *</div>
                <div style="position:relative;">
                    <input type="text" id="npClientSearch" placeholder="이름/닉네임/전화 검색" autocomplete="off" oninput="searchProjectClients(this.value)" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                    <input type="hidden" id="npClientId">
                    <div id="npClientResults" style="display:none; position:absolute; left:0; right:0; top:100%; background:var(--surface); border:1px solid var(--border); border-top:none; border-radius:0 0 8px 8px; max-height:240px; overflow-y:auto; z-index:10; box-shadow:0 4px 16px rgba(0,0,0,0.2);"></div>
                </div>
                <div id="npClientPicked" style="margin-top:6px; font-size:12px; color:var(--accent); display:none;"></div>
            </div>
            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">프로젝트명 *</div>
                <input type="text" id="npName" placeholder="예: 김광래 1차 방문세팅" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
            </div>
            <div id="npTypeRow">
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">프로젝트 유형 *</div>
                <select id="npType" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;"></select>
            </div>
            <div id="npScaleRow" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div>
                    <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">규모</div>
                    <select id="npScale" onchange="updateNpWorkType()" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                        <option value="">선택</option>
                        <option value="personal">개인</option>
                        <option value="studio">스튜디오</option>
                        <option value="corporate">기업</option>
                        <option value="rental">렌탈</option>
                        <option value="broadcast_room">방송룸</option>
                    </select>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">작업 유형</div>
                    <select id="npWorkType" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;"></select>
                </div>
            </div>
            <div id="npMemoRow">
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">프로젝트 개요</div>
                <textarea id="npMemo" rows="2" placeholder="간단한 프로젝트 개요" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; resize:vertical;"></textarea>
            </div>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; padding:14px 20px; border-top:1px solid var(--border);">
            <button type="button" onclick="closeNewProjectModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;">취소</button>
            <button type="button" onclick="submitNewProject()" style="background:var(--accent);color:var(--accent-text);border:none;padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;">생성</button>
        </div>
    </div>
</div>

<script>
const CSRF_NP = document.querySelector('meta[name="csrf-token"]').content;
// 폴백용 기본 작업유형 (DB 비어있을 때만 사용)
const NP_WORK_TYPES_FALLBACK = {
    personal: [['setup','세팅'],['remote','원격'],['filming','촬영중계'],['design','디자인'],['as','A/S']],
    studio: [['setup','세팅'],['survey','답사'],['filming','촬영중계'],['design','디자인'],['as','A/S'],['dispatch','파견']],
    corporate: [['setup','세팅'],['survey','답사'],['filming','촬영중계'],['design','디자인'],['as','A/S']],
    rental: [['monthly','월 계약']],
    broadcast_room: [['monthly','월 계약'],['hourly','시간 대여']],
};
let NP_WORK_TYPES_ACTIVE = null; // {key, label, scale_keys}[]

async function loadNpWorkTypes() {
    if (NP_WORK_TYPES_ACTIVE) return NP_WORK_TYPES_ACTIVE;
    try {
        const res = await fetch('/api/work-types/active', { headers:{ 'Accept':'application/json' } });
        if (res.ok) NP_WORK_TYPES_ACTIVE = await res.json();
    } catch(e) {}
    return NP_WORK_TYPES_ACTIVE || [];
}

function NP_WORK_TYPES_FOR(scale) {
    if (!NP_WORK_TYPES_ACTIVE || !NP_WORK_TYPES_ACTIVE.length) {
        return NP_WORK_TYPES_FALLBACK[scale] || [];
    }
    // scale_keys가 비어있으면 모든 규모에 노출, 아니면 해당 규모만
    return NP_WORK_TYPES_ACTIVE
        .filter(w => !w.scale_keys || !w.scale_keys.length || (scale && w.scale_keys.includes(scale)))
        .map(w => [w.key, w.label]);
}

async function openNewProjectModal() {
    document.getElementById('newProjectOverlay').style.display = 'flex';
    // 프로젝트 유형 + 작업 유형 로드
    await loadNpWorkTypes();
    try {
        const res = await fetch('/api/consultation-types/active', { headers:{ 'Accept':'application/json' } });
        const types = res.ok ? await res.json() : [];
        const sel = document.getElementById('npType');
        sel.innerHTML = types.length
            ? types.map(t => `<option value="${t.key}">${t.label}</option>`).join('')
            : '<option value="visit">방문세팅</option>';
    } catch(e) {}
    // 초기화
    document.getElementById('npClientSearch').value = '';
    document.getElementById('npClientId').value = '';
    document.getElementById('npClientPicked').style.display = 'none';
    document.getElementById('npName').value = '';
    document.getElementById('npScale').value = '';
    document.getElementById('npWorkType').innerHTML = '<option value="">선택</option>';
    document.getElementById('npMemo').value = '';
    document.getElementById('npPaymentOnly').checked = false;
    togglePaymentOnly(false);
}
function closeNewProjectModal() { document.getElementById('newProjectOverlay').style.display = 'none'; }

// 단순 결제 토글 — ON이면 유형/규모/작업유형/메모 숨김
function togglePaymentOnly(checked) {
    ['npTypeRow', 'npScaleRow', 'npMemoRow'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = checked ? 'none' : (id === 'npScaleRow' ? 'grid' : 'block');
    });
}

function updateNpWorkType() {
    const scale = document.getElementById('npScale').value;
    const opts = NP_WORK_TYPES_FOR(scale);
    document.getElementById('npWorkType').innerHTML = '<option value="">선택</option>' + opts.map(([v,l]) => `<option value="${v}">${l}</option>`).join('');
}

let __npSearchTimer;
async function searchProjectClients(q) {
    clearTimeout(__npSearchTimer);
    const el = document.getElementById('npClientResults');
    if (!q || q.length < 1) { el.style.display = 'none'; return; }
    __npSearchTimer = setTimeout(async () => {
        try {
            const res = await fetch('/api/clients/search?q=' + encodeURIComponent(q), { headers:{'Accept':'application/json'} });
            const list = res.ok ? await res.json() : [];
            if (!list.length) {
                el.innerHTML = '<div style="padding:12px; color:var(--text-muted); font-size:12px;">검색 결과 없음</div>';
            } else {
                el.innerHTML = list.map(c => {
                    const display = (c.nickname || c.name || '') + (c.nickname && c.name ? ` (${c.name})` : '') + (c.phone ? ` · ${c.phone}` : '');
                    return `<div onclick='pickNpClient(${c.id}, ${JSON.stringify(display)})' style="padding:9px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid var(--border);" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">${display}</div>`;
                }).join('');
            }
            el.style.display = 'block';
        } catch(e) {}
    }, 200);
}
function pickNpClient(id, display) {
    document.getElementById('npClientId').value = id;
    document.getElementById('npClientSearch').value = display;
    document.getElementById('npClientResults').style.display = 'none';
    document.getElementById('npClientPicked').textContent = '✓ ' + display;
    document.getElementById('npClientPicked').style.display = 'block';
}

async function submitNewProject() {
    const clientId = document.getElementById('npClientId').value;
    if (!clientId) return alert('의뢰자를 검색하여 선택해 주세요.');
    const name = document.getElementById('npName').value.trim();
    if (!name) return alert('프로젝트명을 입력하세요.');

    const paymentOnly = document.getElementById('npPaymentOnly').checked;
    let body;
    if (paymentOnly) {
        // 단순 결제: project_type='상품 문의', work_type='단순 결제' (라벨 매칭으로 키 찾음)
        // ConsultationType / WorkType에서 라벨/키 매칭 후 자동 적용
        const consultRes = await fetch('/api/consultation-types/active', { headers:{ 'Accept':'application/json' } });
        const consultTypes = consultRes.ok ? await consultRes.json() : [];
        // 우선순위: key='product_inquiry' > label='상품 문의' > key='inquiry'
        const ptMatch = consultTypes.find(t => t.key === 'product_inquiry')
            || consultTypes.find(t => t.label === '상품 문의' || t.label === '상품문의')
            || consultTypes.find(t => t.key === 'inquiry');
        const projectTypeKey = ptMatch?.key || 'inquiry';

        await loadNpWorkTypes();
        const wtMatch = (NP_WORK_TYPES_ACTIVE || []).find(w => w.label === '단순 결제' || w.label === '단순결제' || w.key === 'paid');
        const workTypeKey = wtMatch?.key || null;

        body = {
            name,
            project_type: projectTypeKey,
            work_type: workTypeKey,
        };
    } else {
        const projectType = document.getElementById('npType').value;
        if (!projectType) return alert('프로젝트 유형을 선택하세요.');
        body = {
            name,
            project_type: projectType,
            client_scale: document.getElementById('npScale').value || null,
            work_type: document.getElementById('npWorkType').value || null,
            overview: document.getElementById('npMemo').value || null,
        };
    }
    const res = await fetch(`/clients/${clientId}/projects`, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_NP,'Accept':'application/json'},
        body: JSON.stringify(body),
    });
    if (res.ok || res.status === 302) {
        // 응답에서 id 추출 시도; redirect면 location.href에서 추출
        let projectId = null;
        try {
            const data = await res.json();
            projectId = data?.project?.id || data?.id;
        } catch(e) {}
        if (!projectId) {
            // redirect 응답의 Location 헤더에서 추출 시도
            const loc = res.headers.get('Location') || '';
            const m = loc.match(/\/projects\/(\d+)/);
            if (m) projectId = m[1];
        }
        closeNewProjectModal();
        // 단순 결제는 결제 모달 자동 오픈 hash 부착
        const hash = paymentOnly ? '#openPayment' : '';
        if (projectId && typeof openTopTab === 'function') {
            openTopTab('projects', '/projects/' + projectId + hash, '📁 ' + name);
        } else {
            location.href = '/projects/' + projectId + hash;
        }
    } else {
        let detail = '';
        try {
            const err = await res.json();
            detail = err.message || Object.values(err.errors||{}).flat().join('\n') || '';
        } catch(e) { detail = await res.text().catch(()=>''); }
        alert(`[프로젝트 생성 실패 · 코드 ${res.status} ${res.statusText||''}]\n${detail || '응답 본문 없음'}`);
    }
}
</script>
@endif
@endsection
