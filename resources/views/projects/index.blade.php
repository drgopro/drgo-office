@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '프로젝트 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:18px; font-weight:700; }
    .search-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
    .search-input { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:220px; }
    .search-input:focus { border-color:var(--accent); }
    .filter-select { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .btn-search { background:var(--surface2); border:1px solid var(--border); color:var(--text); padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; }

    .table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
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
    .pagination { display:flex; gap:4px; justify-content:center; margin-top:20px; }
    .pagination a, .pagination span { padding:6px 12px; border-radius:6px; font-size:12px; text-decoration:none; border:1px solid var(--border); color:var(--text-muted); }
    .pagination .active span { background:var(--accent); color:#1a1207; border-color:var(--accent); }
    [data-theme="light"] .pagination .active span { color:#fff; }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">프로젝트 관리</div>
    </div>

    <form method="GET" action="{{ route('projects.index') }}" class="search-bar">
        <input class="search-input" type="text" name="search" placeholder="의뢰자명, 프로젝트명 검색" value="{{ request('search') }}">
        <select class="filter-select" name="stage">
            <option value="">전체 단계</option>
            <option value="consulting" {{ request('stage') === 'consulting' ? 'selected' : '' }}>상담</option>
            <option value="equipment"  {{ request('stage') === 'equipment'  ? 'selected' : '' }}>장비파악</option>
            <option value="proposal"   {{ request('stage') === 'proposal'   ? 'selected' : '' }}>일정제안</option>
            <option value="estimate"   {{ request('stage') === 'estimate'   ? 'selected' : '' }}>견적/계약</option>
            <option value="payment"    {{ request('stage') === 'payment'    ? 'selected' : '' }}>결제/예약</option>
            <option value="visit"      {{ request('stage') === 'visit'      ? 'selected' : '' }}>세팅</option>
            <option value="as"         {{ request('stage') === 'as'         ? 'selected' : '' }}>AS</option>
            <option value="done"       {{ request('stage') === 'done'       ? 'selected' : '' }}>완료</option>
        </select>
        <select class="filter-select" name="project_type">
            <option value="">전체 유형</option>
            <option value="visit"  {{ request('project_type') === 'visit'  ? 'selected' : '' }}>방문세팅</option>
            <option value="remote" {{ request('project_type') === 'remote' ? 'selected' : '' }}>원격세팅</option>
            <option value="as"     {{ request('project_type') === 'as'     ? 'selected' : '' }}>AS</option>
        </select>
        <button type="submit" class="btn-search">검색</button>
    </form>

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
                            {{ ['visit'=>'방문세팅','remote'=>'원격세팅','as'=>'AS'][$project->project_type] }}
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
