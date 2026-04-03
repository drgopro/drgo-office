@extends('layouts.app')

@section('title', $client->name . ' - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:900px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .page-header-left { display:flex; align-items:center; gap:12px; }
    .back-btn { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .back-btn:hover { color:var(--text); }
    .client-name { font-size:22px; font-weight:700; }
    .client-nickname { font-size:14px; color:var(--text-muted); margin-top:2px; }
    .btn-edit { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none; }
    .btn-edit:hover { border-color:var(--accent); color:var(--accent); }
    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }

    /* 배지 */
    .badge { display:inline-block; font-size:11px; padding:3px 10px; border-radius:4px; font-weight:600; }
    .badge-normal { background:var(--surface2); color:var(--text-muted); }
    .badge-vip { background:#3a2a1a; color:var(--accent); }
    .badge-rental { background:#1a2a3a; color:#8ab4c8; }

    /* 카드 */
    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
    .info-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; }
    .info-card.full { grid-column:1/-1; }
    .card-title { font-size:12px; font-weight:600; color:var(--accent); margin-bottom:14px; letter-spacing:0.05em; }
    .info-row { display:flex; margin-bottom:10px; font-size:13px; }
    .info-label { color:var(--text-muted); min-width:80px; flex-shrink:0; }
    .info-value { color:var(--text); }

    /* 태그 */
    .tag { display:inline-block; font-size:11px; padding:3px 8px; border-radius:4px; background:var(--surface2); color:var(--text-muted); margin-right:4px; margin-bottom:4px; }

    /* 프로젝트 */
    .project-list { display:flex; flex-direction:column; gap:8px; }
    .project-item { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; text-decoration:none; transition:border-color 0.15s; }
    .project-item:hover { border-color:var(--accent); }
    .project-name { font-size:13px; font-weight:600; color:var(--text); }
    .project-meta { font-size:11px; color:var(--text-muted); margin-top:3px; }
    .project-stage { font-size:11px; padding:3px 8px; border-radius:4px; }
    .stage-consulting { background:#2a2010; color:var(--accent); }
    .stage-equipment  { background:#1a2a1a; color:#7ac87a; }
    .stage-proposal   { background:#1a1a2a; color:#8ab4c8; }
    .stage-estimate   { background:#2a1a2a; color:#9b70c8; }
    .stage-payment    { background:#1a2a2a; color:#4ecdc4; }
    .stage-visit      { background:#1a2a1a; color:#7ac87a; }
    .stage-as         { background:#2a1a1a; color:#c87a7a; }
    .stage-done       { background:var(--surface2); color:var(--text-muted); }
    .empty-projects { text-align:center; padding:30px; color:var(--text-muted); font-size:13px; }

    .success-msg { background:#1a3a2a; border:1px solid #2a5a3a; color:#7ac87a; padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }
</style>
@endpush

@section('content')
<div class="page-wrap">

    @if(session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route('clients.index') }}" class="back-btn">← 목록</a>
            <div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="client-name">{{ $client->name }}</div>
                    <span class="badge badge-{{ $client->grade }}">
                        {{ ['normal'=>'일반','vip'=>'VIP','rental'=>'렌탈'][$client->grade] }}
                    </span>
                </div>
                @if($client->nickname)
                    <div class="client-nickname">{{ $client->nickname }}</div>
                @endif
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('clients.edit', $client) }}" class="btn-edit">수정</a>
            <a href="#" class="btn-primary" onclick="openProjectModal(); return false;">+ 프로젝트</a>
        </div>
    </div>

    <div class="info-grid">
        <!-- 기본 정보 -->
        <div class="info-card">
            <div class="card-title">기본 정보</div>
            <div class="info-row">
                <div class="info-label">연락처</div>
                <div class="info-value">{{ $client->phone ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">성별</div>
                <div class="info-value">{{ ['male'=>'남성','female'=>'여성','other'=>'기타'][$client->gender] ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">소속</div>
                <div class="info-value">{{ $client->affiliation ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">담당자</div>
                <div class="info-value">{{ $client->assignedUser?->display_name ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">등록일</div>
                <div class="info-value">{{ $client->created_at->format('Y.m.d') }}</div>
            </div>
        </div>

        <!-- 분류 정보 -->
        <div class="info-card">
            <div class="card-title">분류 정보</div>
            <div class="info-row">
                <div class="info-label">플랫폼</div>
                <div class="info-value">
                    @forelse($client->platforms ?? [] as $p)
                        <span class="tag">{{ $p }}</span>
                    @empty -
                    @endforelse
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">콘텐츠</div>
                <div class="info-value">
                    @forelse($client->content_types ?? [] as $c)
                        <span class="tag">{{ $c }}</span>
                    @empty -
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 주소 -->
        <div class="info-card">
            <div class="card-title">주소</div>
            <div class="info-value">{{ $client->address ?? '-' }}</div>
            @if($client->address_detail)
                <div class="info-value" style="margin-top:4px; color:var(--text-muted);">{{ $client->address_detail }}</div>
            @endif
        </div>

        <!-- 메모 -->
        <div class="info-card">
            <div class="card-title">메모</div>
            @if($client->important_memo)
                <div style="font-size:12px; color:var(--accent); margin-bottom:6px;">⚠ 중요</div>
                <div style="font-size:13px; margin-bottom:10px;">{{ $client->important_memo }}</div>
            @endif
            @if($client->memo)
                <div style="font-size:13px; color:var(--text-muted);">{{ $client->memo }}</div>
            @endif
            @if(!$client->important_memo && !$client->memo)
                <div style="color:var(--text-muted); font-size:13px;">-</div>
            @endif
        </div>

        <!-- 프로젝트 목록 -->
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between;">
                <span>프로젝트 ({{ $client->projects->count() }}건)</span>
            </div>
            @if($client->projects->count() > 0)
                <div class="project-list">
                    @foreach($client->projects as $project)
                    <a href="#" class="project-item">
                        <div>
                            <div class="project-name">{{ $project->name }}</div>
                            <div class="project-meta">
                                {{ ['visit'=>'방문세팅','remote'=>'원격세팅','as'=>'AS'][$project->project_type] }}
                                · {{ $project->created_at->format('Y.m.d') }}
                            </div>
                        </div>
                        <span class="project-stage stage-{{ $project->stage }}">
                            {{ ['consulting'=>'상담','equipment'=>'장비파악','proposal'=>'일정제안','estimate'=>'견적/계약','payment'=>'결제/예약','visit'=>'세팅','as'=>'AS','done'=>'완료','cancelled'=>'취소'][$project->stage] }}
                        </span>
                    </a>
                    @endforeach
                </div>
            @else
                <div class="empty-projects">진행 중인 프로젝트가 없습니다.</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// 프로젝트 추가 모달
const projectModal = document.getElementById('projectModal');

function openProjectModal() {
    projectModal.style.display = 'flex';
}
function closeProjectModal() {
    projectModal.style.display = 'none';
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeProjectModal(); });
</script>
@endpush

<!-- 프로젝트 등록 모달 -->
<div id="projectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; width:440px; max-width:95vw; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div style="font-size:16px; font-weight:700;">새 프로젝트</div>
            <button onclick="closeProjectModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">×</button>
        </div>
        <form method="POST" action="{{ route('projects.store', $client) }}">
            @csrf
            <div style="margin-bottom:14px;">
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:6px;">프로젝트명 *</div>
                <input type="text" name="name" required placeholder="예: 풀세팅 - 홍길동"
                    style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none;">
            </div>
            <div style="margin-bottom:14px;">
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:6px;">유형 *</div>
                <select name="project_type" style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; cursor:pointer;">
                    <option value="visit">방문세팅</option>
                    <option value="remote">원격세팅</option>
                    <option value="as">AS</option>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:6px;">메모</div>
                <textarea name="memo" rows="2" placeholder="간단한 메모"
                    style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; resize:vertical;"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="closeProjectModal()" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer;">취소</button>
                <button type="submit" style="background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">생성</button>
            </div>
        </form>
    </div>
</div>
