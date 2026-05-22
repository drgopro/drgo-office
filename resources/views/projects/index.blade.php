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
    .btn-search { background:var(--accent); color:#1a1207; border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
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
        <button style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;" onclick="openExcelImportModal('projects','프로젝트')">📥 엑셀 가져오기</button>
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
                @foreach($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="project-link">{{ $project->name }}</a>
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
                            {{ ['visit'=>'방문세팅','remote'=>'원격세팅','design'=>'디자인','inquiry'=>'단순문의','as'=>'A/S','troubleshoot'=>'문제 해결'][$project->project_type] ?? $project->project_type }}
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
@endsection
