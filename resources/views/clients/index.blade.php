@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '의뢰자 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:18px; font-weight:700; }
    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
    .search-bar { display:flex; gap:8px; margin-bottom:16px; }
    .search-input { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; width:260px; }
    .search-input:focus { border-color:var(--accent); }
    .filter-select { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .btn-search { background:var(--surface2); border:1px solid var(--border); color:var(--text); padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; }

    /* 테이블 */
    .table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    table { width:100%; border-collapse:collapse; }
    thead { background:var(--surface2); }
    th { padding:11px 16px; text-align:left; font-size:11px; color:var(--text-muted); font-weight:600; letter-spacing:0.05em; border-bottom:1px solid var(--border); }
    td { padding:12px 16px; font-size:13px; border-bottom:1px solid var(--border); }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:var(--surface2); }
    .client-name { font-weight:600; color:var(--text); text-decoration:none; }
    .client-name:hover { color:var(--accent); }
    .nickname { color:var(--text-muted); font-size:12px; }

    /* 배지 */
    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-normal { background:var(--surface2); color:var(--text-muted); }
    .badge-vip { background:#3a2a1a; color:var(--accent); }
    .badge-rental { background:#1a2a3a; color:var(--blue); }
    .badge-active { background:#1a3a2a; color:var(--green); }
    .badge-inactive { background:var(--surface2); color:var(--text-muted); }

    /* 플랫폼 태그 */
    .platform-tag { display:inline-block; font-size:10px; padding:2px 6px; border-radius:4px; background:var(--surface2); color:var(--text-muted); margin-right:3px; }

    /* 페이지네이션 */
    .pagination { display:flex; gap:4px; justify-content:center; margin-top:20px; }
    .pagination a, .pagination span { padding:6px 12px; border-radius:6px; font-size:12px; text-decoration:none; border:1px solid var(--border); color:var(--text-muted); }
    .pagination .active span { background:var(--accent); color:#1a1207; border-color:var(--accent); }

    .empty { text-align:center; padding:60px; color:var(--text-muted); font-size:14px; }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">의뢰자 관리</div>
        <a href="{{ route('clients.create') }}" class="btn-primary">+ 의뢰자 등록</a>
    </div>

    <!-- 검색/필터 -->
    <form method="GET" action="{{ route('clients.index') }}" class="search-bar">
        <input class="search-input" type="text" name="search" placeholder="이름, 닉네임, 전화번호 검색" value="{{ request('search') }}">
        <select class="filter-select" name="grade">
            <option value="">전체 등급</option>
            <option value="normal" {{ request('grade') === 'normal' ? 'selected' : '' }}>일반</option>
            <option value="vip" {{ request('grade') === 'vip' ? 'selected' : '' }}>VIP</option>
            <option value="rental" {{ request('grade') === 'rental' ? 'selected' : '' }}>렌탈</option>
        </select>
        <button type="submit" class="btn-search">검색</button>
    </form>

    <!-- 테이블 -->
    <div class="table-wrap">
        @if($clients->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>이름 / 닉네임</th>
                    <th>연락처</th>
                    <th>플랫폼</th>
                    <th>등급</th>
                    <th>담당자</th>
                    <th>상태</th>
                    <th>등록일</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $client)
                <tr>
                    <td>
                        <a href="{{ route('clients.show', $client) }}" class="client-name">{{ $client->name }}</a>
                        @if($client->nickname)
                            <div class="nickname">{{ $client->nickname }}</div>
                        @endif
                    </td>
                    <td>{{ $client->phone ?? '-' }}</td>
                    <td>
                        @foreach($client->platforms ?? [] as $platform)
                            <span class="platform-tag">{{ $platform }}</span>
                        @endforeach
                    </td>
                    <td>
                        <span class="badge badge-{{ $client->grade }}">
                            {{ ['normal'=>'일반','vip'=>'VIP','rental'=>'렌탈'][$client->grade] }}
                        </span>
                    </td>
                    <td>{{ $client->assignedUser?->display_name ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $client->status }}">
                            {{ ['active'=>'활성','inactive'=>'비활성','blacklist'=>'블랙리스트'][$client->status] }}
                        </span>
                    </td>
                    <td>{{ $client->created_at->format('Y.m.d') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <div class="empty">등록된 의뢰자가 없습니다.</div>
        @endif
    </div>

    <!-- 페이지네이션 -->
    <div class="pagination">
        {{ $clients->appends(request()->query())->links() }}
    </div>
</div>
@endsection
