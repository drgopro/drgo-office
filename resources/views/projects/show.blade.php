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
    .project-meta { font-size:13px; color:var(--text-muted); margin-top:4px; display:flex; align-items:center; gap:8px; }

    .process-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px 24px; margin-bottom:16px; }
    .process-title { font-size:12px; color:var(--accent); font-weight:600; margin-bottom:16px; letter-spacing:0.05em; }
    .process-steps { display:flex; align-items:flex-start; }
    .process-step { flex:1; text-align:center; position:relative; }
    .process-step::after { content:''; position:absolute; top:14px; left:50%; width:100%; height:2px; background:var(--border); z-index:0; }
    .process-step:last-child::after { display:none; }
    .step-dot { width:28px; height:28px; border-radius:50%; border:2px solid var(--border); background:var(--bg); display:flex; align-items:center; justify-content:center; margin:0 auto 6px; font-size:11px; position:relative; z-index:1; cursor:pointer; transition:all 0.2s; color:var(--text-muted); }
    .step-dot:hover { border-color:var(--accent); color:var(--accent); }
    .step-dot.done { background:var(--accent); border-color:var(--accent); color:#1a1207; }
    .step-dot.active { border-color:var(--accent); color:var(--accent); background:var(--surface2); }
    .step-label { font-size:10px; color:var(--text-muted); }
    .step-label.active { color:var(--accent); font-weight:600; }

    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .info-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; }
    .info-card.full { grid-column:1/-1; }
    .card-title { font-size:12px; font-weight:600; color:var(--accent); margin-bottom:14px; letter-spacing:0.05em; display:flex; justify-content:space-between; align-items:center; }
    .info-row { display:flex; margin-bottom:10px; font-size:13px; }
    .info-label { color:var(--text-muted); min-width:80px; flex-shrink:0; }

    .badge { display:inline-block; font-size:11px; padding:3px 10px; border-radius:4px; font-weight:600; }
    .badge-visit   { background:#1a3a2a; color:#7ac87a; }
    .badge-remote  { background:#1a2a3a; color:#8ab4c8; }
    .badge-as      { background:#2a1a1a; color:#c87a7a; }

    .consult-list { display:flex; flex-direction:column; gap:8px; }
    .consult-item { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 16px; }
    .consult-item.important { border-color:#3a2a10; background:#1a1500; }
    .consult-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .consult-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .consult-date { font-size:12px; color:var(--text-muted); }
    .consult-type-badge { font-size:10px; padding:2px 7px; border-radius:4px; background:var(--surface); color:var(--text-muted); }
    .consult-result-badge { font-size:10px; padding:2px 7px; border-radius:4px; }
    .result-in_progress { background:#2a2010; color:var(--accent); }
    .result-waiting     { background:#1a1a2a; color:#8ab4c8; }
    .result-valid       { background:#1a2a1a; color:#7ac87a; }
    .result-invalid     { background:#2a1a1a; color:#c87a7a; }
    .result-done        { background:var(--surface); color:var(--text-muted); }
    .consult-content { font-size:13px; color:var(--text); line-height:1.6; white-space:pre-wrap; }
    .consult-footer { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
    .consult-author { font-size:11px; color:var(--text-muted); }
    .consult-actions { display:flex; gap:6px; }
    .btn-del { background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 6px; }
    .btn-del:hover { color:var(--red); }
    .btn-edit-sm { background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 6px; }
    .btn-edit-sm:hover { color:var(--accent); }
    .important-mark { color:var(--accent); font-size:12px; }

    .empty { text-align:center; padding:30px; color:var(--text-muted); font-size:13px; }
    .success-msg { background:#1a3a2a; border:1px solid #2a5a3a; color:#7ac87a; padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }

    .btn-primary { background:var(--accent); color:#1a1207; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:500px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .modal-title { font-size:16px; font-weight:700; }
    .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
    .field-group { margin-bottom:14px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-select { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .field-textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; resize:vertical; }
    .field-textarea:focus { border-color:var(--accent); }
    .check-row { display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; }
    .check-row input { accent-color:var(--accent); width:15px; height:15px; cursor:pointer; }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
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
                    <span>{{ $project->created_at->format('Y.m.d') }} 시작</span>
                    <span>담당: {{ $project->assignedUser?->display_name ?? '-' }}</span>
                </div>
            </div>
        </div>
        <button class="btn-primary" onclick="openConsultModal()">+ 상담 등록</button>
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
        if ($currentIdx === false) $currentIdx = -1;
    @endphp

    <div class="process-wrap">
        <div class="process-title">진행 단계 — 클릭하여 변경</div>
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
        <div class="info-card">
            <div class="card-title">의뢰자 정보</div>
            <div class="info-row">
                <div class="info-label">이름</div>
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
            @if($project->client->important_memo)
            <div style="margin-top:10px; background:#1a1500; border:1px solid #3a2a10; border-radius:6px; padding:8px 12px; font-size:12px; color:var(--accent);">
                ⚠ {{ $project->client->important_memo }}
            </div>
            @endif
        </div>

        <div class="info-card">
            <div class="card-title">메모</div>
            <div style="font-size:13px; color:{{ $project->memo ? 'var(--text)' : 'var(--text-muted)' }}; white-space:pre-wrap;">
                {{ $project->memo ?? '메모 없음' }}
            </div>
        </div>

        <!-- 상담 이력 -->
        <div class="info-card full">
            <div class="card-title">
                <span>상담 이력 ({{ $project->consultations->count() }}건)</span>
            </div>
            @if($project->consultations->count() > 0)
                <div class="consult-list">
                    @foreach($project->consultations->sortByDesc('consulted_at') as $consult)
                    <div class="consult-item {{ $consult->is_important ? 'important' : '' }}">
                        <div class="consult-header">
                            <div class="consult-meta">
                                @if($consult->is_important)
                                    <span class="important-mark">⭐</span>
                                @endif
                                <span class="consult-date">{{ $consult->consulted_at->format('Y.m.d') }}</span>
                                <span class="consult-type-badge">
                                    {{ ['kakao'=>'카카오톡','phone'=>'전화','visit'=>'내방상담','field'=>'현장답사'][$consult->consult_type] }}
                                </span>
                                <span class="consult-result-badge result-{{ $consult->result }}">
                                    {{ ['in_progress'=>'진행중','waiting'=>'대기','valid'=>'유효','invalid'=>'무효','done'=>'완료'][$consult->result] }}
                                </span>
                            </div>
                            <div class="consult-actions">
                                <button class="btn-edit-sm" onclick="openEditModal({{ $consult->id }}, '{{ $consult->consulted_at->format('Y-m-d') }}', '{{ $consult->consult_type }}', '{{ $consult->result }}', {{ $consult->is_important ? 'true' : 'false' }}, @js($consult->content))">수정</button>
                                <form method="POST" action="{{ route('consultations.destroy', $consult) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-del" onclick="return confirm('삭제할까요?')">삭제</button>
                                </form>
                            </div>
                        </div>
                        @if($consult->content)
                            <div class="consult-content">{{ $consult->content }}</div>
                        @endif
                        <div class="consult-footer">
                            <span class="consult-author">{{ $consult->consultant?->display_name ?? '-' }}</span>
                            <span class="consult-date">{{ $consult->created_at->format('H:i') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="empty">상담 이력이 없습니다.</div>
            @endif
        </div>
    </div>
</div>

<!-- 상담 등록 모달 -->
<div class="modal-overlay" id="consultModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">상담 등록</div>
            <button class="modal-close" onclick="closeConsultModal()">×</button>
        </div>
        <form method="POST" action="{{ route('consultations.store', $project) }}">
            @csrf
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">상담일 *</div>
                    <input class="field-input" type="date" name="consulted_at" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="field-group">
                    <div class="field-label">상담 유형 *</div>
                    <select class="field-select" name="consult_type">
                        <option value="kakao">카카오톡</option>
                        <option value="phone">전화</option>
                        <option value="visit">내방상담</option>
                        <option value="field">현장답사</option>
                    </select>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">결과 *</div>
                <select class="field-select" name="result">
                    <option value="in_progress">진행중(대화)</option>
                    <option value="waiting">대기</option>
                    <option value="valid">유효</option>
                    <option value="invalid">무효</option>
                    <option value="done">완료</option>
                </select>
            </div>
            <div class="field-group">
                <div class="field-label">상담 내용</div>
                <textarea class="field-textarea" name="content" rows="5" placeholder="상담 내용을 입력하세요"></textarea>
            </div>
            <div class="field-group">
                <label class="check-row">
                    <input type="checkbox" name="is_important" value="1">
                    <span>⭐ 중요 상담으로 표시</span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeConsultModal()">취소</button>
                <button type="submit" class="btn-save">등록</button>
            </div>
        </form>
    </div>
</div>

<!-- 상담 수정 모달 -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">상담 수정</div>
            <button class="modal-close" onclick="closeEditModal()">×</button>
        </div>
        <form method="POST" id="editForm">
            @csrf @method('PATCH')
            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">상담일 *</div>
                    <input class="field-input" type="date" name="consulted_at" id="editDate" required>
                </div>
                <div class="field-group">
                    <div class="field-label">상담 유형 *</div>
                    <select class="field-select" name="consult_type" id="editType">
                        <option value="kakao">카카오톡</option>
                        <option value="phone">전화</option>
                        <option value="visit">내방상담</option>
                        <option value="field">현장답사</option>
                    </select>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">결과 *</div>
                <select class="field-select" name="result" id="editResult">
                    <option value="in_progress">진행중(대화)</option>
                    <option value="waiting">대기</option>
                    <option value="valid">유효</option>
                    <option value="invalid">무효</option>
                    <option value="done">완료</option>
                </select>
            </div>
            <div class="field-group">
                <div class="field-label">상담 내용</div>
                <textarea class="field-textarea" name="content" id="editContent" rows="5"></textarea>
            </div>
            <div class="field-group">
                <label class="check-row">
                    <input type="checkbox" name="is_important" id="editImportant" value="1">
                    <span>⭐ 중요 상담으로 표시</span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">취소</button>
                <button type="submit" class="btn-save">수정</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openConsultModal() {
    document.getElementById('consultModal').classList.add('open');
}
function closeConsultModal() {
    document.getElementById('consultModal').classList.remove('open');
}
function openEditModal(id, date, type, result, isImportant, content) {
    document.getElementById('editForm').action = `/consultations/${id}`;
    document.getElementById('editDate').value = date;
    document.getElementById('editType').value = type;
    document.getElementById('editResult').value = result;
    document.getElementById('editContent').value = content || '';
    document.getElementById('editImportant').checked = isImportant;
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeConsultModal();
        closeEditModal();
    }
});
document.getElementById('consultModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeConsultModal();
});
document.getElementById('editModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeEditModal();
});
</script>
@endpush
