@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '프로젝트 - 닥터고블린 오피스')

@push('styles')
<style>
    /* 내부 탭 셸 (의뢰자 페이지와 동일 개념) */
    .proj-shell { display:flex; flex-direction:column; height:calc(var(--full-h, 100vh) - var(--chrome-h, 86px)); overflow:hidden; }
    .proj-tabstrip { display:none; align-items:center; background:var(--surface); border-bottom:1px solid var(--border); padding:0 10px; height:38px; gap:1px; overflow-x:auto; flex-shrink:0; scrollbar-width:none; }
    .proj-tabstrip::-webkit-scrollbar { display:none; }
    .proj-tabstrip.has-tabs { display:flex; }
    .proj-tab { display:flex; align-items:center; gap:6px; padding:6px 12px; font-size:12px; cursor:pointer; color:var(--text-muted); border:1px solid transparent; border-bottom:none; background:none; white-space:nowrap; border-radius:6px 6px 0 0; flex-shrink:0; transition:all .12s; }
    .proj-tab:hover { color:var(--text); background:var(--surface2); }
    .proj-tab.active { color:var(--accent); background:var(--surface2); border-color:var(--border); font-weight:600; }
    .proj-tab .pt-close { display:inline-flex; align-items:center; justify-content:center; width:15px; height:15px; border-radius:3px; opacity:0.5; }
    .proj-tab .pt-close:hover { opacity:1; background:var(--border); }
    .proj-pane-wrap { flex:1; position:relative; overflow:hidden; min-height:0; }
    .proj-pane { display:none; position:absolute; inset:0; overflow:auto; }
    .proj-pane.active { display:block; }
    .proj-pane > iframe { width:100%; height:100%; border:none; display:block; }

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
    /* 태그 필터: 대분류 붉은 점 / 소분류 회색 점 */
    .chip-toggle .chip.chip-tag-major::before { background:#c0392b; opacity:0.65; }
    .chip-toggle input:checked + .chip.chip-tag-major::before { background:#c0392b; opacity:1; }
    /* 접이식 태그 필터 */
    .tag-filter { border:1px solid var(--border); border-radius:10px; margin-top:4px; background:var(--surface); }
    .tag-filter-toggle { display:flex; align-items:center; gap:8px; width:100%; background:none; border:none; padding:9px 14px; cursor:pointer; color:var(--text); font-size:13px; }
    .tag-filter-caret { display:inline-block; transition:transform .15s; color:var(--text-muted); font-size:11px; }
    .tag-filter.open .tag-filter-caret { transform:rotate(90deg); }
    .tag-filter-count { background:var(--accent); color:var(--accent-text); font-size:11px; font-weight:700; border-radius:10px; padding:1px 8px; }
    .tag-filter-body { display:none; padding:4px 14px 14px; }
    .tag-filter.open .tag-filter-body { display:block; }
    .tag-section { padding:8px 0; }
    .tag-section + .tag-section { border-top:1px dashed var(--border); }
    .tag-section-label { font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:7px; }
    .tag-section-chips { display:flex; flex-wrap:wrap; gap:6px; }
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
    .pj-tags { display:flex; flex-wrap:wrap; gap:4px; margin-top:5px; }
    .pj-tag { font-size:10px; font-weight:600; padding:2px 8px; border-radius:11px; line-height:1.4; }
    .pj-tag-major { background:rgba(200,80,80,0.14); color:#c0392b; border:1px solid rgba(200,80,80,0.4); }
    .pj-tag-minor { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); }
    .tag-pick { display:flex; flex-wrap:wrap; gap:6px; }
    .tag-chip-pick { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border:1px solid var(--border); border-radius:14px; font-size:12px; cursor:pointer; background:var(--surface2); color:var(--text-muted); user-select:none; }
    .tag-chip-pick input { display:none; }
    .tag-chip-pick:has(input:checked) { background:rgba(36,138,56,0.14); border-color:#248a38; color:#248a38; font-weight:600; }
    .tag-add-btn { background:none; border:1px solid var(--border); color:var(--accent); border-radius:6px; padding:2px 9px; font-size:11px; cursor:pointer; }
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

    /* ── 토글 스위치 (모달 옵션용) ── */
    .drgo-toggle-row { display:flex; align-items:center; gap:12px; padding:12px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; cursor:pointer; user-select:none; transition:all 0.15s; }
    .drgo-toggle-row.is-on { border-color:var(--accent); background:rgba(212,188,150,0.08); }
    [data-theme="light"] .drgo-toggle-row.is-on { background:rgba(59,94,160,0.06); }
    .drgo-toggle-row input { display:none; }
    .drgo-toggle-switch { position:relative; width:42px; height:24px; background:var(--surface3, var(--border)); border-radius:12px; flex-shrink:0; transition:background 0.2s; }
    .drgo-toggle-switch::after { content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; background:#fff; border-radius:50%; transition:left 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
    .drgo-toggle-row.is-on .drgo-toggle-switch { background:var(--accent); }
    .drgo-toggle-row.is-on .drgo-toggle-switch::after { left:20px; }
    .drgo-toggle-label { flex:1; }
    .drgo-toggle-label .title { font-size:13px; font-weight:600; }
    .drgo-toggle-label .desc { font-size:11px; color:var(--text-muted); margin-top:2px; }
</style>
@endpush

@section('content')
<div class="proj-shell" id="projShell">
    <div class="proj-tabstrip" id="projTabStrip"></div>
    <div class="proj-pane-wrap" id="projPaneWrap">
        <div class="proj-pane active" id="projPane-list">
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">프로젝트 관리</div>
        <div style="display:flex; gap:8px;">
            @if(Auth::user()->hasPermission('projects.edit'))
                <button style="background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;" onclick="openNewProjectModal()">+ 새 프로젝트</button>
            @endif
            <a href="{{ route('projects.billing') }}" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">💸 잔금 관리</a>
            <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openExcelImportModal('projects','프로젝트')"><x-icon name="download" :size="14"/> 엑셀 가져오기</button>
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
        $rawTag = request('tag');
        $selectedTags = is_array($rawTag)
            ? array_filter($rawTag)
            : array_filter(array_map('trim', explode(',', (string) $rawTag)));

        // 단계 필터 — 공통 코드 기준 (유형별 라벨은 달라도 코드는 공유)
        $stageOptions = [
            'consulting' => '상담',
            'equipment' => '장비파악/진단',
            'proposal' => '일정제안',
            'survey' => '사전답사',
            'estimate' => '견적/계약',
            'payment' => '결제',
            'visit' => '진행 중',
            'delivery' => '납품',
            'as' => 'AS',
            'done' => '완료',
        ];
        $typeOptions = \App\Models\ConsultationType::map();
    @endphp

    <form method="GET" action="{{ route('projects.index') }}" class="search-bar">
        <div class="search-row">
            <input class="search-input" type="text" name="search" placeholder="의뢰자명, 프로젝트명 검색" value="{{ request('search') }}">
            <button type="submit" class="btn-search">검색</button>
            @if(!empty($selectedStages) || !empty($selectedTypes) || !empty($selectedTags) || request('search'))
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
        @if(!empty($tagOptions['major']) || !empty($tagOptions['minor']))
        <div class="tag-filter {{ !empty($selectedTags) ? 'open' : '' }}" id="tagFilter">
            <button type="button" class="tag-filter-toggle" onclick="document.getElementById('tagFilter').classList.toggle('open')">
                <span class="tag-filter-caret">▸</span>
                <span class="filter-label" style="margin:0;">태그 필터</span>
                @if(!empty($selectedTags))<span class="tag-filter-count">{{ count($selectedTags) }}</span>@endif
            </button>
            <div class="tag-filter-body">
                @if(!empty($tagOptions['major']))
                <div class="tag-section">
                    <div class="tag-section-label">대분류</div>
                    <div class="tag-section-chips">
                        @foreach($tagOptions['major'] as $t)
                            <label class="chip-toggle">
                                <input type="checkbox" name="tag[]" value="{{ $t }}" {{ in_array($t, $selectedTags, true) ? 'checked' : '' }}>
                                <span class="chip chip-tag-major">{{ $t }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
                @if(!empty($tagOptions['minor']))
                <div class="tag-section">
                    <div class="tag-section-label">소분류</div>
                    <div class="tag-section-chips">
                        @foreach($tagOptions['minor'] as $t)
                            <label class="chip-toggle">
                                <input type="checkbox" name="tag[]" value="{{ $t }}" {{ in_array($t, $selectedTags, true) ? 'checked' : '' }}>
                                <span class="chip chip-tag-minor">{{ $t }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
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
                $ptLabelMap = rescue(fn () => \App\Models\ConsultationType::pluck('label', 'key')->toArray(), [], false);
            @endphp
                @foreach($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="project-link" onclick="event.preventDefault(); goProjectDetail({{ $project->id }}, '{{ addslashes($project->name) }}');">{{ $project->name }}</a>
                        @php $__tags = $project->tags ?? []; $__maj = $__tags['major'] ?? []; $__min = $__tags['minor'] ?? []; @endphp
                        @if(!empty($__maj) || !empty($__min))
                            <div class="pj-tags">
                                @foreach($__maj as $__t)<span class="pj-tag pj-tag-major">{{ $__t }}</span>@endforeach
                                @foreach($__min as $__t)<span class="pj-tag pj-tag-minor">{{ $__t }}</span>@endforeach
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($project->client)
                            <a href="{{ route('clients.index', ['open' => $project->client->id]) }}" class="client-link" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openClientDetail({{ $project->client->id }}); else window.location.href=this.href;">
                                {{ $project->client->name ?: $project->client->nickname }}
                                @if($project->client->name && $project->client->nickname)
                                    ({{ $project->client->nickname }})
                                @endif
                            </a>
                        @else
                            @if($project->manual_client_name)
                                {{ $project->manual_client_name }}
                            @else
                                <span class="text-muted" style="color:var(--text-muted);">(의뢰자 없음)</span>
                            @endif
                            <span style="font-size:9px; padding:1px 6px; border-radius:8px; background:var(--surface2); color:var(--text-muted); border:1px dashed var(--border); white-space:nowrap;">확인불가</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $project->project_type }}">
                            {{ $ptLabelMap[$project->project_type] ?? ($ptDefaults[$project->project_type] ?? $project->project_type) }}
                        </span>
                    </td>
                    <td>
                        <span class="stage-badge stage-{{ $project->stage }}">
                            {{ $project->stageLabel() }}
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
        </div>{{-- /proj-pane-list --}}
    </div>{{-- /proj-pane-wrap --}}
</div>{{-- /proj-shell --}}

@if(Auth::user()->hasPermission('projects.edit'))
{{-- 새 프로젝트 모달 --}}
<div id="newProjectOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:9000; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) drgoModalMinimize(this, '+ 새 프로젝트', '📁')">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);">
            <div style="font-size:15px; font-weight:700;">+ 새 프로젝트</div>
            <button type="button" onclick="closeNewProjectModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
        </div>
        <div style="padding:18px 20px; display:flex; flex-direction:column; gap:14px;">
            {{-- 단순 결제 토글 (스위치 UI) --}}
            <label class="drgo-toggle-row" id="npPaymentOnlyRow">
                <input type="checkbox" id="npPaymentOnly" onchange="togglePaymentOnly(this.checked)">
                <span class="drgo-toggle-switch"></span>
                <div class="drgo-toggle-label">
                    <div class="title"><x-icon name="money" :size="16"/> 단순 결제 프로젝트</div>
                    <div class="desc">상담/단계 없이 결제 내역만 관리합니다.</div>
                </div>
            </label>

            <div>
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">의뢰자 *</div>
                <div id="npClientSearchWrap" style="position:relative;">
                    <input type="text" id="npClientSearch" placeholder="이름/닉네임/전화 검색" autocomplete="off" oninput="searchProjectClients(this.value)" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                    <input type="hidden" id="npClientId">
                    <div id="npClientResults" style="display:none; position:absolute; left:0; right:0; top:100%; background:var(--surface); border:1px solid var(--border); border-top:none; border-radius:0 0 8px 8px; max-height:240px; overflow-y:auto; z-index:10; box-shadow:0 4px 16px rgba(0,0,0,0.2);"></div>
                </div>
                <div id="npClientPicked" style="margin-top:6px; font-size:12px; color:var(--accent); display:none;"></div>
                {{-- 의뢰자명 확인 불가 — 의뢰자 미연동 프로젝트 --}}
                <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); cursor:pointer; margin-top:8px;">
                    <input type="checkbox" id="npNoClient" onchange="toggleNpNoClient()"> 의뢰자명 확인 불가 (의뢰자와 연동하지 않고 생성)
                </label>
                <div id="npManualNameWrap" style="display:none; margin-top:6px;">
                    <input type="text" id="npManualName" placeholder="파악된 이름/별칭을 주관식으로 입력 (선택)" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px dashed var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box;">
                </div>
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
            <div id="npTagRow">
                @include('partials.tag-picker', ['key' => 'np'])
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

// ── 프로젝트 내부 탭 관리 (의뢰자 페이지와 동일 개념) ──
const projTabs = {
    tabs: [],           // {id, title}
    active: 'list',
    open(id, title, hash) {
        id = String(id);
        if (!this.tabs.find(t => t.id === id)) {
            this.tabs.push({ id, title: title || ('프로젝트 #' + id) });
            const pane = document.createElement('div');
            pane.className = 'proj-pane';
            pane.id = 'projPane-' + id;
            const iframe = document.createElement('iframe');
            iframe.src = '/projects/' + id + (hash || ''); // app 레이아웃(in-iframe) 사용 — csrf 메타/스크립트 포함
            pane.appendChild(iframe);
            document.getElementById('projPaneWrap').appendChild(pane);
        }
        this.activate(id);
    },
    activate(id) {
        this.active = String(id);
        document.querySelectorAll('.proj-pane').forEach(p => p.classList.remove('active'));
        const pane = document.getElementById('projPane-' + (id === 'list' ? 'list' : id));
        if (pane) pane.classList.add('active');
        this.render();
    },
    close(id) {
        id = String(id);
        const idx = this.tabs.findIndex(t => t.id === id);
        if (idx === -1) return;
        this.tabs.splice(idx, 1);
        const pane = document.getElementById('projPane-' + id);
        if (pane) pane.remove();
        if (this.active === id) this.activate('list');
        else this.render();
    },
    render() {
        const strip = document.getElementById('projTabStrip');
        if (!strip) return;
        strip.classList.toggle('has-tabs', this.tabs.length > 0);
        let html = `<button class="proj-tab ${this.active === 'list' ? 'active' : ''}" onclick="projTabs.activate('list')">목록</button>`;
        html += this.tabs.map(t => `<button class="proj-tab ${this.active === t.id ? 'active' : ''}" onclick="projTabs.activate('${t.id}')" title="${(t.title||'').replace(/"/g,'&quot;')}">${(t.title||'').replace(/</g,'&lt;')}<span class="pt-close" onclick="event.stopPropagation(); projTabs.close('${t.id}')">✕</span></button>`).join('');
        strip.innerHTML = html;
    },
};
window.projTabs = projTabs;
// 프로젝트 상세 iframe에서 '목록으로'/삭제 시 호출 — 내부 탭 닫고 목록으로
window.projInternalBack = function(id) { if (id) projTabs.close(id); else projTabs.activate('list'); };

// 프로젝트 상세로 — 내부 탭으로 오픈
function goProjectDetail(id, title) {
    projTabs.open(id, title || '프로젝트');
}
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
    // 초기화 — 규모는 '개인' 기본값
    document.getElementById('npClientSearch').value = '';
    document.getElementById('npClientId').value = '';
    document.getElementById('npClientPicked').style.display = 'none';
    document.getElementById('npNoClient').checked = false;
    document.getElementById('npManualName').value = '';
    toggleNpNoClient();
    document.getElementById('npName').value = '';
    document.getElementById('npScale').value = 'personal';
    document.getElementById('npMemo').value = '';
    document.getElementById('npPaymentOnly').checked = false;
    togglePaymentOnly(false);
    updateNpWorkType(); // 개인 기준 작업유형 옵션 생성
}
function closeNewProjectModal() { document.getElementById('newProjectOverlay').style.display = 'none'; }

// 단순 결제 토글 — ON이면 유형/규모/작업유형/메모 숨김 + 스위치 시각 갱신
function togglePaymentOnly(checked) {
    document.getElementById('npPaymentOnlyRow')?.classList.toggle('is-on', checked);
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

// 의뢰자명 확인 불가 토글 — 검색 대신 주관식 이름 입력
function toggleNpNoClient() {
    const noClient = document.getElementById('npNoClient').checked;
    document.getElementById('npClientSearchWrap').style.display = noClient ? 'none' : '';
    document.getElementById('npManualNameWrap').style.display = noClient ? '' : 'none';
    if (noClient) {
        document.getElementById('npClientId').value = '';
        document.getElementById('npClientSearch').value = '';
        document.getElementById('npClientPicked').style.display = 'none';
    }
}

async function submitNewProject() {
    const noClient = document.getElementById('npNoClient').checked;
    const clientId = document.getElementById('npClientId').value;
    if (!noClient && !clientId) return alert('의뢰자를 검색하여 선택하거나, [의뢰자명 확인 불가]를 체크해 주세요.');
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
    body.tags = CrmTagPicker.value('np'); // 대분류/소분류
    if (noClient) {
        body.manual_client_name = document.getElementById('npManualName').value.trim() || null;
    }
    const res = await fetch(noClient ? '/api/projects' : `/clients/${clientId}/projects`, {
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
        if (projectId) {
            projTabs.open(projectId, name, hash);
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
