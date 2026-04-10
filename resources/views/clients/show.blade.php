@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

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
    .btn-edit { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
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

    /* 견적서 */
    .estimate-list { display:flex; flex-direction:column; gap:6px; }
    .estimate-item { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; transition:border-color 0.15s; }
    .estimate-item:hover { border-color:var(--accent); }
    .estimate-info { display:flex; flex-direction:column; gap:3px; }
    .estimate-id { font-size:13px; font-weight:600; color:var(--text); }
    .estimate-meta { font-size:11px; color:var(--text-muted); display:flex; gap:8px; align-items:center; }
    .estimate-amount { font-size:13px; font-weight:600; color:var(--accent); }
    .estimate-status { font-size:11px; padding:3px 10px; border-radius:12px; font-weight:700; letter-spacing:0.03em; border:1px solid; }
    .est-created { background:rgba(107,114,128,0.1); color:#6b7280; border-color:rgba(107,114,128,0.3); }
    .est-editing { background:rgba(200,176,138,0.15); color:var(--accent); border-color:rgba(200,176,138,0.4); }
    .est-completed { background:rgba(34,197,94,0.12); color:#22c55e; border-color:rgba(34,197,94,0.35); }
    .est-paid { background:rgba(6,182,212,0.12); color:#06b6d4; border-color:rgba(6,182,212,0.35); }
    .est-hold { background:rgba(239,68,68,0.12); color:#ef4444; border-color:rgba(239,68,68,0.35); }
    .estimate-actions { display:flex; gap:6px; align-items:center; }
    .estimate-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 12px; border-radius:6px; font-size:11px; cursor:pointer; text-decoration:none; transition:all 0.15s; }
    .estimate-btn:hover { border-color:var(--accent); color:var(--accent); }

    /* 문서 */
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
    .file-preview-item .thumb { width:36px; height:36px; border-radius:4px; object-fit:cover; background:var(--bg); flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:var(--text-muted); overflow:hidden; position:relative; }
    .file-preview-item .thumb img, .file-preview-item .thumb canvas { width:100%; height:100%; object-fit:cover; }
    .file-preview-item .thumb .video-badge { position:absolute; bottom:1px; right:1px; background:rgba(0,0,0,0.7); color:#fff; font-size:7px; padding:1px 3px; border-radius:2px; line-height:1; }
    .file-preview-item .file-info { overflow:hidden; }
    .file-preview-item .file-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:130px; }
    .file-preview-item .file-size { font-size:10px; color:var(--text-muted); }
    .file-preview-item .btn-remove { position:absolute; top:-6px; right:-6px; width:18px; height:18px; border-radius:50%; background:var(--red); color:#fff; border:none; font-size:11px; line-height:18px; text-align:center; cursor:pointer; padding:0; }
    /* 썸네일 그리드 */
    .doc-grid { display:flex; flex-wrap:wrap; gap:12px; }
    .doc-thumb-card { position:relative; width:120px; cursor:pointer; }
    .doc-thumb-card .thumb-img { width:120px; height:120px; border-radius:8px; background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; overflow:hidden; font-size:11px; color:var(--text-muted); font-weight:600; transition:border-color 0.15s; position:relative; }
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
    .album-media-wrap { display:flex; align-items:center; justify-content:center; min-height:200px; }
    .album-media-wrap img.album-media { transition:transform 0.2s; cursor:grab; }
    .album-media-wrap img.album-media.dragging { cursor:grabbing; transition:none; }
    [data-theme="light"] .btn-primary { color:#fff; }
    [data-theme="light"] .btn-upload { color:#fff; }
</style>
@endpush

@section('content')
@php
    $clientDocs = $client->documents->sortByDesc('created_at')->values()->map(fn($d) => [
        'name' => $d->file_name,
        'note' => $d->note,
        'mime' => $d->mime_type,
        'url'  => route('documents.serve', $d),
    ]);
@endphp
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
            <button class="btn-edit" onclick="openActivityLog('Client',{{ $client->id }},'{{ $client->name }} 수정 로그')">📋 로그</button>
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

        <!-- 문서 관리 -->
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between;">
                <span>문서 ({{ $client->documents->count() }}건)</span>
            </div>
            <form method="POST" action="{{ route('documents.store', $client) }}" enctype="multipart/form-data" id="docUploadForm">
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
            @if($client->documents->count() > 0)
                <div class="doc-grid">
                    @foreach($client->documents->sortByDesc('created_at') as $i => $doc)
                    @php
                        $isImg = str_starts_with($doc->mime_type ?? '', 'image/');
                        $isVid = str_starts_with($doc->mime_type ?? '', 'video/');
                        $isPdf = ($doc->mime_type ?? '') === 'application/pdf';
                        $ext = strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION));
                    @endphp
                    <div class="doc-thumb-card" onclick="openAlbum({{ $i }})">
                        <div class="thumb-img">
                            @if($isImg)
                                <img src="{{ route('documents.serve', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy">
                            @elseif($isVid)
                                <video src="{{ route('documents.serve', $doc) }}" preload="metadata" muted></video>
                                <div class="video-play">▶</div>
                            @else
                                {{ $isPdf ? 'PDF' : $ext }}
                            @endif
                        </div>
                        <div class="thumb-actions" onclick="event.stopPropagation()">
                            <a href="{{ route('documents.download', $doc) }}" title="다운로드">↓</a>
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" style="display:contents;">
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
                <div class="empty-projects">등록된 문서가 없습니다.</div>
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
                    <a href="{{ route('projects.show', $project) }}" class="project-item">
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

        <!-- 견적서 목록 -->
        <div class="info-card full">
            <div class="card-title" style="display:flex; justify-content:space-between;">
                <span>견적서 ({{ $client->estimates->count() }}건)</span>
                @if(Auth::user()->hasPermission('estimates.edit'))
                    <a href="{{ route('estimates') }}?client_id={{ $client->id }}" class="btn-edit" style="font-size:11px; padding:4px 10px;">+ 새 견적서</a>
                @endif
            </div>
            @if($client->estimates->count() > 0)
                <div class="estimate-list">
                    @foreach($client->estimates as $estimate)
                    <div class="estimate-item">
                        <div class="estimate-info">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="estimate-id">#{{ $estimate->id }}</span>
                                <span class="estimate-status est-{{ $estimate->status }}">
                                    {{ ['created'=>'작성중','editing'=>'수정중','completed'=>'완료','paid'=>'결제완료','hold'=>'보류'][$estimate->status] ?? $estimate->status }}
                                </span>
                            </div>
                            <div class="estimate-meta">
                                @if($estimate->total_amount)
                                    <span class="estimate-amount">{{ number_format($estimate->total_amount) }}원</span>
                                @endif
                                <span>{{ $estimate->created_at->format('Y.m.d') }}</span>
                                @if($estimate->creator)
                                    <span>{{ $estimate->creator->display_name ?? $estimate->creator->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="estimate-actions">
                            @if(Auth::user()->hasPermission('estimates.view'))
                                <a href="{{ route('estimates.print', $estimate) }}" onclick="event.preventDefault();window.open(this.href,'estimate_print','width=900,height=700,scrollbars=yes,resizable=yes')" class="estimate-btn">보기</a>
                            @endif
                            @if(Auth::user()->hasPermission('estimates.edit') && $estimate->status !== 'paid')
                                <a href="{{ route('estimates.edit', $estimate) }}" target="_blank" class="estimate-btn" style="border-color:var(--accent);color:var(--accent);">편집</a>
                            @endif
                            @if($estimate->status === 'paid')
                                <span class="estimate-btn" style="opacity:0.4;cursor:not-allowed;">결제완료</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="empty-projects">등록된 견적서가 없습니다.</div>
            @endif
        </div>
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
@endsection

@push('scripts')
<script>
// 프로젝트 추가 모달
const projectModal = document.getElementById('projectModal');
function openProjectModal() { projectModal.style.display = 'flex'; }
function closeProjectModal() { projectModal.style.display = 'none'; }

// 앨범 뷰어 + 줌/드래그
const albumDocs = @json($clientDocs);
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
        const map = {pdf:'PDF',doc:'DOC',docx:'DOC',xls:'XLS',xlsx:'XLS',ppt:'PPT',pptx:'PPT',zip:'ZIP',rar:'RAR',txt:'TXT',csv:'CSV'};
        return map[ext] || ext.toUpperCase();
    }
    function makeVideoThumb(file, thumb) {
        const video = document.createElement('video');
        video.preload='metadata'; video.muted=true; video.playsInline=true;
        const url = URL.createObjectURL(file);
        video.src = url;
        video.addEventListener('loadeddata', () => { video.currentTime = Math.min(1, video.duration/2); });
        video.addEventListener('seeked', () => {
            const canvas = document.createElement('canvas');
            canvas.width=72; canvas.height=72;
            const ctx = canvas.getContext('2d');
            const s = Math.min(video.videoWidth, video.videoHeight);
            ctx.drawImage(video, (video.videoWidth-s)/2, (video.videoHeight-s)/2, s, s, 0, 0, 72, 72);
            thumb.innerHTML='';
            thumb.appendChild(canvas);
            const badge = document.createElement('span');
            badge.className='video-badge'; badge.textContent='▶';
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
            removeBtn.type='button'; removeBtn.className='btn-remove'; removeBtn.textContent='×';
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
    if (e.key === 'Escape') { closeProjectModal(); closeAlbum(); }
    if (document.getElementById('albumOverlay').classList.contains('open')) {
        if (e.key === 'ArrowLeft') albumNav(-1);
        if (e.key === 'ArrowRight') albumNav(1);
    }
});
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
