@extends('layouts.app')

@section('title', $project->name . ' - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:900px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .page-header-left { display:flex; align-items:center; gap:12px; }
    .back-btn { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .back-btn:hover { color:var(--text); }
    .project-name { font-size:22px; font-weight:700; }
    .project-meta { font-size:13px; color:var(--text-muted); margin-top:4px; }

    /* 프로세스 바 */
    .process-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px 24px; margin-bottom:16px; }
    .process-title { font-size:12px; color:var(--accent); font-weight:600; margin-bottom:16px; letter-spacing:0.05em; }
    .process-steps { display:flex; align-items:center; gap:0; }
    .process-step { flex:1; text-align:center; position:relative; }
    .process-step::after { content:''; position:absolute; top:14px; left:50%; width:100%; height:2px; background:var(--border); z-index:0; }
    .process-step:last-child::after { display:none; }
    .step-dot { width:28px; height:28px; border-radius:50%; border:2px solid var(--border); background:var(--bg); display:flex; align-items:center; justify-content:center; margin:0 auto 6px; font-size:11px; position:relative; z-index:1; cursor:pointer; transition:all 0.2s; }
    .step-dot:hover { border-color:var(--accent); }
    .step-dot.done { background:var(--accent); border-color:var(--accent); color:#1a1207; }
    .step-dot.active { border-color:var(--accent); color:var(--accent); background:var(--surface2); }
    .step-label { font-size:10px; color:var(--text-muted); }
    .step-label.active { color:var(--accent); font-weight:600; }

    /* 카드 */
    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .info-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; }
    .info-card.full { grid-column:1/-1; }
    .card-title { font-size:12px; font-weight:600; color:var(--accent); margin-bottom:14px; letter-spacing:0.05em; }
    .info-row { display:flex; margin-bottom:10px; font-size:13px; }
    .info-label { color:var(--text-muted); min-width:80px; flex-shrink:0; }

    .badge { display:inline-block; font-size:11px; padding:3px 10px; border-radius:4px; font-weight:600; }
    .badge-visit   { background:#1a3a2a; color:#7ac87a; }
    .badge-remote  { background:#1a2a3a; color:#8ab4c8; }
    .badge-as      { background:#2a1a1a; color:#c87a7a; }

    .success-msg { background:#1a3a2a; border:1px solid #2a5a3a; color:#7ac87a; padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }

    /* 상담 이력 */
    .consult-list { display:flex; flex-direction:column; gap:8px; }
    .consult-item { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 16px; }
    .consult-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .consult-date { font-size:12px; color:var(--text-muted); }
    .consult-content { font-size:13px; color:var(--text); }
    .empty { text-align:center; padding:30px; color:var(--text-muted); font-size:13px; }
    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
</style>
@endpush

@section('content')
<div class="page-wrap">

    @if(session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route('clients.show', $project->client) }}" class="back-btn">← {{ $project->client->name }}</a>
            <div>
                <div class="project-name">{{ $project->name }}</div>
                <div class="project-meta">
                    <span class="badge badge-{{ $project->project_type }}">
                        {{ ['visit'=>'방문세팅','remote'=>'원격세팅','as'=>'AS'][$project->project_type] }}
                    </span>
                    · {{ $project->created_at->format('Y.m.d') }} 시작
                    · 담당: {{ $project->assignedUser?->display_name ?? '-' }}
                </div>
            </div>
        </div>
        <button class="btn-primary" onclick="document.getElementById('consultModal').classList.add('open')">+ 상담 등록</button>
    </div>

    <!-- 7단계 프로세스 바 -->
    @php
        $stages = [
            'consulting' => '상담',
            'equipment'  => '장비파악',
            'proposal'   => '일정제안',
            'estimate'   => '견적/계약',
            'payment'    => '결제/예약',
            'visit'      => '세팅',
            'as'         => 'AS',
        ];
        $stageKeys = array_keys($stages);
        $currentIdx = array_search($project->stage, $stageKeys);
    @endphp

    <div class="process-wrap">
        <div class="process-title">진행 단계</div>
        <div class="process-steps">
            @foreach($stages as $key => $label)
            @php $idx = array_search($key, $stageKeys); @endphp
            <div class="process-step">
                <form method="POST" action="{{ route('projects.stage', $project) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="stage" value="{{ $key }}">
                    <button type="submit" class="step-dot {{ $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'active' : '') }}" title="{{ $label }}">
                        {{ $idx < $currentIdx ? '✓' : $idx + 1 }}
                    </button>
                </form>
                <div class="step-label {{ $idx === $currentIdx ? 'active' : '' }}">{{ $label }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="info-grid">
        <!-- 기본 정보 -->
        <div class="info-card">
            <div class="card-title">프로젝트 정보</div>
            <div class="info-row">
                <div class="info-label">의뢰자</div>
                <div>
                    <a href="{{ route('clients.show', $project->client) }}" style="color:var(--accent); text-decoration:none;">
                        {{ $project->client->name }}
                    </a>
                    @if($project->client->nickname)
                        <span style="color:var(--text-muted); font-size:12px;"> ({{ $project->client->nickname }})</span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">연락처</div>
                <div>{{ $project->client->phone ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">주소</div>
                <div>{{ $project->client->address ?? '-' }}</div>
            </div>
        </div>

        <!-- 메모 -->
        <div class="info-card">
            <div class="card-title">메모</div>
            <div style="font-size:13px; color:{{ $project->memo ? 'var(--text)' : 'var(--text-muted)' }};">
                {{ $project->memo ?? '메모 없음' }}
            </div>
        </div>

        <!-- 상담 이력 -->
        <div class="info-card full">
            <div class="card-title">상담 이력</div>
            <div class="empty">상담 이력이 없습니다.</div>
        </div>
    </div>
</div>

<!-- 상담 등록 모달 (다음 단계에서 구현) -->
<div class="modal-overlay" id="consultModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center;">
</div>

@endsection
