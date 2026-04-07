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

    /* 문서 업로드 */
    .doc-upload-area { margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border); }
    .doc-upload-row { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
    .doc-upload-area .field-mini { font-size:11px; color:var(--text-muted); margin-bottom:4px; }
    .doc-upload-area select, .doc-upload-area input[type="text"] { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; outline:none; }
    .doc-upload-area select:focus, .doc-upload-area input[type="text"]:focus { border-color:var(--accent); }
    .btn-upload { background:var(--accent); color:#1a1207; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; }
    .btn-upload:disabled { opacity:0.4; cursor:default; }
    .btn-choose { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 14px; color:var(--text); font-size:12px; cursor:pointer; white-space:nowrap; }
    .btn-choose:hover { border-color:var(--accent); }
    .file-preview-list { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .file-preview-item { position:relative; display:flex; align-items:center; gap:8px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:6px 10px; font-size:12px; color:var(--text); max-width:220px; }
    .file-preview-item .thumb { width:36px; height:36px; border-radius:4px; background:var(--bg); flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:var(--text-muted); overflow:hidden; position:relative; }
    .file-preview-item .thumb img, .file-preview-item .thumb canvas { width:100%; height:100%; object-fit:cover; }
    .file-preview-item .thumb .video-badge { position:absolute; bottom:1px; right:1px; background:rgba(0,0,0,0.7); color:#fff; font-size:7px; padding:1px 3px; border-radius:2px; }
    .file-preview-item .file-info { overflow:hidden; }
    .file-preview-item .file-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:130px; }
    .file-preview-item .file-size { font-size:10px; color:var(--text-muted); }
    .file-preview-item .btn-remove { position:absolute; top:-6px; right:-6px; width:18px; height:18px; border-radius:50%; background:var(--red); color:#fff; border:none; font-size:11px; line-height:18px; text-align:center; cursor:pointer; padding:0; }

    /* 썸네일 그리드 (업로드된 파일) */
    .doc-grid { display:flex; flex-wrap:wrap; gap:12px; }
    .doc-thumb-card { position:relative; width:120px; cursor:pointer; }
    .doc-thumb-card .thumb-img { width:120px; height:120px; border-radius:8px; background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; overflow:hidden; font-size:11px; color:var(--text-muted); font-weight:600; transition:border-color 0.15s; }
    .doc-thumb-card:hover .thumb-img { border-color:var(--accent); }
    .doc-thumb-card .thumb-img img, .doc-thumb-card .thumb-img video { width:100%; height:100%; object-fit:cover; }
    .doc-thumb-card .thumb-img .video-play { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:28px; height:28px; background:rgba(0,0,0,0.6); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; pointer-events:none; }
    .doc-thumb-meta { margin-top:6px; }
    .doc-thumb-meta .thumb-name { font-size:11px; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .doc-thumb-meta .thumb-note { font-size:10px; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:1px; }
    .doc-thumb-meta .thumb-date { font-size:9px; color:var(--text-muted); margin-top:1px; }
    .doc-thumb-card .thumb-actions { position:absolute; top:4px; right:4px; display:none; gap:3px; }
    .doc-thumb-card:hover .thumb-actions { display:flex; }
    .thumb-actions a, .thumb-actions button { width:22px; height:22px; border-radius:4px; background:rgba(0,0,0,0.65); border:none; color:#fff; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; text-decoration:none; }
    .thumb-actions a:hover, .thumb-actions button:hover { background:rgba(0,0,0,0.85); }

    /* 앨범 모달 */
    .album-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:300; align-items:center; justify-content:center; backdrop-filter:blur(6px); }
    .album-overlay.open { display:flex; }
    .album-inner { position:relative; max-width:90vw; max-height:90vh; display:flex; flex-direction:column; align-items:center; }
    .album-media { max-width:85vw; max-height:75vh; border-radius:12px; object-fit:contain; background:#000; user-select:none; }
    .album-media-wrap.zoomed .album-media { max-width:none; max-height:none; }
    .album-media-wrap { display:flex; align-items:center; justify-content:center; min-height:200px; }
    .album-info { color:#fff; font-size:13px; margin-top:10px; text-align:center; }
    .album-info .album-name { font-weight:600; }
    .album-info .album-note { font-size:11px; color:rgba(255,255,255,0.5); margin-top:2px; }
    .album-nav { position:fixed; top:50%; transform:translateY(-50%); width:80px; height:200px; background:none; border:none; color:#fff; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:302; }
    .album-nav .nav-circle { width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center; transition:background 0.15s; }
    .album-nav:hover .nav-circle { background:rgba(255,255,255,0.3); }
    .album-nav.prev { left:0; }
    .album-nav.next { right:0; }
    .album-close { position:fixed; top:20px; right:20px; background:none; border:none; color:#fff; font-size:28px; cursor:pointer; z-index:303; }
    .album-counter { font-size:11px; color:rgba(255,255,255,0.4); margin-top:4px; }
    .album-zoom-controls { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); display:flex; gap:8px; z-index:303; }
    .album-zoom-controls button { width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .album-zoom-controls button:hover { background:rgba(255,255,255,0.3); }
    .album-media-wrap img.album-media { transition:transform 0.2s; cursor:grab; }
    .album-media-wrap img.album-media.dragging { cursor:grabbing; transition:none; }

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
@php
    $projectDocs = $project->documents->sortByDesc('created_at')->values()->map(fn($d) => [
        'name' => $d->file_name,
        'note' => $d->note,
        'mime' => $d->mime_type,
        'url'  => route('project-documents.serve', $d),
    ]);
@endphp
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
        <!-- 첨부 문서 -->
        <div class="info-card full">
            <div class="card-title">
                <span>첨부 문서 ({{ $project->documents->count() }}건)</span>
            </div>
            <form method="POST" action="{{ route('project-documents.store', $project) }}" enctype="multipart/form-data" id="docUploadForm">
                @csrf
                <input type="file" id="docFileInput" multiple style="display:none;">
                <input type="file" name="files[]" id="docFileReal" multiple style="display:none;">
                <div class="doc-upload-area">
                    <div class="doc-upload-row">
                        <div>
                            <div class="field-mini">파일 *</div>
                            <button type="button" class="btn-choose" onclick="document.getElementById('docFileInput').click()">파일 선택 (여러 개 가능)</button>
                        </div>
                        <div>
                            <div class="field-mini">카테고리 *</div>
                            <select name="category">
                                <option value="현금영수증">현금영수증</option>
                                <option value="사업자등록증">사업자등록증</option>
                                <option value="계약서">계약서</option>
                                <option value="견적서">견적서</option>
                                <option value="사진/이미지">사진/이미지</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:120px;">
                            <div class="field-mini">메모</div>
                            <input type="text" name="note" placeholder="간단한 메모" style="width:100%;">
                        </div>
                        <button type="submit" class="btn-upload" id="btnUpload" disabled>업로드</button>
                    </div>
                    <div class="file-preview-list" id="filePreviewList"></div>
                </div>
            </form>
            @if($project->documents->count() > 0)
                <div class="doc-grid">
                    @foreach($project->documents->sortByDesc('created_at') as $i => $doc)
                    @php
                        $isImg = str_starts_with($doc->mime_type ?? '', 'image/');
                        $isVid = str_starts_with($doc->mime_type ?? '', 'video/');
                        $isPdf = ($doc->mime_type ?? '') === 'application/pdf';
                        $ext = strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION));
                    @endphp
                    <div class="doc-thumb-card" onclick="openAlbum({{ $i }})">
                        <div class="thumb-img">
                            @if($isImg)
                                <img src="{{ route('project-documents.serve', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy">
                            @elseif($isVid)
                                <video src="{{ route('project-documents.serve', $doc) }}" preload="metadata" muted></video>
                                <div class="video-play">▶</div>
                            @else
                                {{ $isPdf ? 'PDF' : $ext }}
                            @endif
                        </div>
                        <div class="thumb-actions" onclick="event.stopPropagation()">
                            <a href="{{ route('project-documents.download', $doc) }}" title="다운로드">↓</a>
                            <form method="POST" action="{{ route('project-documents.destroy', $doc) }}" style="display:contents;">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('삭제할까요?')" title="삭제">×</button>
                            </form>
                        </div>
                        <div class="doc-thumb-meta">
                            <div class="thumb-name" title="{{ $doc->file_name }}">{{ $doc->file_name }}</div>
                            @if($doc->note)
                                <div class="thumb-note" title="{{ $doc->note }}">{{ $doc->note }}</div>
                            @endif
                            <div class="thumb-date">{{ $doc->created_at->format('Y.m.d') }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="empty">등록된 문서가 없습니다.</div>
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

<!-- 앨범 모달 -->
<div class="album-overlay" id="albumOverlay">
    <button class="album-close" onclick="closeAlbum()">×</button>
    <button class="album-nav prev" onclick="albumNav(-1)"><span class="nav-circle">‹</span></button>
    <button class="album-nav next" onclick="albumNav(1)"><span class="nav-circle">›</span></button>
    <div class="album-inner" id="albumInner">
        <div class="album-media-wrap" id="albumMediaWrap"></div>
        <div class="album-info">
            <div class="album-name" id="albumName"></div>
            <div class="album-note" id="albumNote"></div>
            <div class="album-counter" id="albumCounter"></div>
        </div>
    </div>
    <div class="album-zoom-controls" id="albumZoomControls" style="display:none;">
        <button onclick="albumZoom(-1)" title="축소">−</button>
        <span id="albumZoomLevel" style="min-width:48px; text-align:center; color:#fff; font-size:13px; font-weight:600; line-height:36px;">100%</span>
        <button onclick="albumZoom(1)" title="확대">+</button>
        <button onclick="albumZoomReset()" title="원본 크기" style="font-size:11px; width:auto; padding:0 10px; border-radius:18px;">맞춤</button>
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
// 상담 모달
function openConsultModal() { document.getElementById('consultModal').classList.add('open'); }
function closeConsultModal() { document.getElementById('consultModal').classList.remove('open'); }
function openEditModal(id, date, type, result, isImportant, content) {
    document.getElementById('editForm').action = `/consultations/${id}`;
    document.getElementById('editDate').value = date;
    document.getElementById('editType').value = type;
    document.getElementById('editResult').value = result;
    document.getElementById('editContent').value = content || '';
    document.getElementById('editImportant').checked = isImportant;
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('open'); }

// 앨범 뷰어 + 줌/드래그
const albumDocs = @json($projectDocs);
let albumIdx = 0, zoomScale = 1, panX = 0, panY = 0, isPanning = false, panStartX, panStartY;

function openAlbum(idx) {
    albumIdx = idx;
    renderAlbum();
    document.getElementById('albumOverlay').classList.add('open');
}
function closeAlbum() {
    document.getElementById('albumOverlay').classList.remove('open');
    document.getElementById('albumMediaWrap').innerHTML = '';
    document.getElementById('albumZoomControls').style.display = 'none';
    resetZoom();
}
document.getElementById('albumOverlay').addEventListener('click', e => {
    if (e.target.id === 'albumOverlay') closeAlbum();
});
function albumNav(dir) {
    albumIdx = (albumIdx + dir + albumDocs.length) % albumDocs.length;
    resetZoom();
    renderAlbum();
}
let baseW = 0, baseH = 0;
function resetZoom() { zoomScale = 1; panX = 0; panY = 0; }
function albumZoom(dir) {
    const steps = [0.5, 0.75, 1, 1.5, 2, 3, 4];
    let ci = steps.indexOf(zoomScale);
    if (ci === -1) ci = 2;
    ci = Math.max(0, Math.min(steps.length - 1, ci + dir));
    zoomScale = steps[ci];
    if (zoomScale === 1) { panX = 0; panY = 0; }
    applyZoom();
}
function albumZoomReset() { resetZoom(); applyZoom(); }
function applyZoom() {
    const wrap = document.getElementById('albumMediaWrap');
    const img = wrap.querySelector('img.album-media');
    if (!img) return;
    if (zoomScale === 1) {
        wrap.classList.remove('zoomed');
        img.style.width = '';
        img.style.height = '';
    } else {
        wrap.classList.add('zoomed');
        img.style.width = (baseW * zoomScale) + 'px';
        img.style.height = (baseH * zoomScale) + 'px';
    }
    img.style.transform = `translate(${panX}px,${panY}px)`;
    document.getElementById('albumZoomLevel').textContent = Math.round(zoomScale * 100) + '%';
}
function renderAlbum() {
    const doc = albumDocs[albumIdx];
    const wrap = document.getElementById('albumMediaWrap');
    const zoomCtrl = document.getElementById('albumZoomControls');
    wrap.innerHTML = '';
    wrap.classList.remove('zoomed');
    const isImage = doc.mime && doc.mime.startsWith('image/');
    zoomCtrl.style.display = isImage ? 'flex' : 'none';
    if (isImage) {
        const img = document.createElement('img');
        img.className = 'album-media'; img.src = doc.url;
        img.onload = () => { baseW = img.offsetWidth; baseH = img.offsetHeight; };
        img.addEventListener('wheel', e => { e.preventDefault(); albumZoom(e.deltaY < 0 ? 1 : -1); }, {passive:false});
        img.addEventListener('mousedown', e => {
            if (zoomScale === 1) return;
            isPanning = true; panStartX = e.clientX - panX; panStartY = e.clientY - panY;
            img.classList.add('dragging'); e.preventDefault();
        });
        img.addEventListener('dblclick', () => { zoomScale === 1 ? albumZoom(2) : albumZoomReset(); });
        wrap.appendChild(img);
    } else if (doc.mime && doc.mime.startsWith('video/')) {
        const vid = document.createElement('video');
        vid.className = 'album-media'; vid.src = doc.url; vid.controls = true; vid.autoplay = true;
        wrap.appendChild(vid);
    } else if (doc.mime === 'application/pdf') {
        const iframe = document.createElement('iframe');
        iframe.className = 'album-media'; iframe.src = doc.url;
        iframe.style.cssText = 'width:80vw; height:75vh; border:none;';
        wrap.appendChild(iframe);
    } else {
        const div = document.createElement('div');
        div.style.cssText = 'color:var(--text-muted); font-size:14px; padding:60px; text-align:center;';
        div.textContent = '미리보기를 지원하지 않는 파일입니다.';
        wrap.appendChild(div);
    }
    document.getElementById('albumName').textContent = doc.name;
    document.getElementById('albumNote').textContent = doc.note || '';
    document.getElementById('albumCounter').textContent = `${albumIdx + 1} / ${albumDocs.length}`;
}
document.addEventListener('mousemove', e => {
    if (!isPanning) return;
    panX = e.clientX - panStartX; panY = e.clientY - panStartY;
    applyZoom();
});
document.addEventListener('mouseup', () => {
    if (isPanning) { isPanning = false; const img = document.querySelector('#albumMediaWrap img.album-media'); if(img) img.classList.remove('dragging'); }
});

// 파일 업로드 프리뷰
(function(){
    const fileInput = document.getElementById('docFileInput');
    const fileReal = document.getElementById('docFileReal');
    const previewList = document.getElementById('filePreviewList');
    const btnUpload = document.getElementById('btnUpload');
    const form = document.getElementById('docUploadForm');
    let selectedFiles = [];

    const IMG_TYPES = ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml'];
    const VID_TYPES = ['video/mp4','video/webm','video/ogg','video/quicktime','video/x-msvideo','video/x-matroska'];

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    function getExtIcon(name) {
        const ext = name.split('.').pop().toLowerCase();
        const map = {pdf:'PDF', doc:'DOC', docx:'DOC', xls:'XLS', xlsx:'XLS', ppt:'PPT', pptx:'PPT', zip:'ZIP', rar:'RAR', txt:'TXT', csv:'CSV'};
        return map[ext] || ext.toUpperCase();
    }
    function makeVideoThumb(file, thumb) {
        const video = document.createElement('video');
        video.preload = 'metadata'; video.muted = true; video.playsInline = true;
        const url = URL.createObjectURL(file);
        video.src = url;
        video.addEventListener('loadeddata', () => { video.currentTime = Math.min(1, video.duration / 2); });
        video.addEventListener('seeked', () => {
            const canvas = document.createElement('canvas');
            canvas.width = 72; canvas.height = 72;
            const ctx = canvas.getContext('2d');
            const s = Math.min(video.videoWidth, video.videoHeight);
            ctx.drawImage(video, (video.videoWidth-s)/2, (video.videoHeight-s)/2, s, s, 0, 0, 72, 72);
            thumb.innerHTML = '';
            thumb.appendChild(canvas);
            const badge = document.createElement('span');
            badge.className = 'video-badge'; badge.textContent = '▶';
            thumb.appendChild(badge);
            URL.revokeObjectURL(url);
        });
    }
    function renderPreviews() {
        previewList.innerHTML = '';
        selectedFiles.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'file-preview-item';
            const thumb = document.createElement('div');
            thumb.className = 'thumb';
            if (IMG_TYPES.includes(file.type)) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                thumb.appendChild(img);
            } else if (VID_TYPES.includes(file.type)) {
                thumb.textContent = '...';
                makeVideoThumb(file, thumb);
            } else if (file.type === 'application/pdf') {
                thumb.textContent = 'PDF';
            } else {
                thumb.textContent = getExtIcon(file.name);
            }
            const info = document.createElement('div');
            info.className = 'file-info';
            info.innerHTML = `<div class="file-name" title="${file.name}">${file.name}</div><div class="file-size">${formatSize(file.size)}</div>`;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button'; removeBtn.className = 'btn-remove'; removeBtn.textContent = '×';
            removeBtn.onclick = () => { selectedFiles.splice(idx, 1); syncAndRender(); };
            item.append(thumb, info, removeBtn);
            previewList.appendChild(item);
        });
        btnUpload.disabled = selectedFiles.length === 0;
    }
    function syncAndRender() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileReal.files = dt.files;
        renderPreviews();
    }
    fileInput.addEventListener('change', () => {
        for (const f of fileInput.files) selectedFiles.push(f);
        fileInput.value = '';
        syncAndRender();
    });
    form.addEventListener('submit', (e) => {
        if (selectedFiles.length === 0) { e.preventDefault(); return; }
        syncAndRender();
    });
})();

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeConsultModal(); closeEditModal(); closeAlbum(); }
    if (document.getElementById('albumOverlay').classList.contains('open')) {
        if (e.key === 'ArrowLeft') albumNav(-1);
        if (e.key === 'ArrowRight') albumNav(1);
    }
});
</script>
@endpush
