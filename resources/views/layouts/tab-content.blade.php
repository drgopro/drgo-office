<script>
// 부모 테마 동기화 (iframe 로드 시)
try {
    const parentTheme = window.parent.document.documentElement.getAttribute('data-theme');
    if (parentTheme) document.documentElement.setAttribute('data-theme', parentTheme);
} catch(e) {
    const saved = localStorage.getItem('drgo_theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
}
</script>
<style>
/* tab-content 기본 CSS 변수 */
:root { --bg:#111111; --surface:#1c1c1c; --surface2:#272727; --surface3:#333; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --accent2:#90bcd4; --red:#d48888; --green:#88d488; --blue:#8ab4c8; --gold:#c8b08a; --teal:#e8894a; --purple:#9b70c8; }
[data-theme="light"] { --bg:#f4f5f7; --surface:#ffffff; --surface2:#eceef2; --surface3:#dfe2e8; --border:#b8bcc8; --text:#1a1e28; --text-muted:#5a6070; --accent:#3b5ea0; --accent2:#2e6a8a; --red:#c03838; --green:#248a38; --blue:#2e6a9a; --gold:#907030; --teal:#b85c18; --purple:#5c2e90; }
/* 라이트모드 버튼/입력 보정 */
[data-theme="light"] body { background:var(--bg); color:var(--text); }
[data-theme="light"] .btn-edit, [data-theme="light"] .btn-outline { border-color:#a0a8b4; color:#4a5060; }
[data-theme="light"] .btn-edit:hover, [data-theme="light"] .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
[data-theme="light"] .btn-primary { background:var(--accent); color:#fff; }
[data-theme="light"] .info-card { background:#fff; border-color:#c8ccd4; }
[data-theme="light"] .info-label { color:#6b7280; }
[data-theme="light"] .tag { background:#e8eaef; color:#4a5060; }
[data-theme="light"] .badge-normal { background:#e8eaef; color:#5a6070; }
[data-theme="light"] .badge-vip { background:#fff3e0; color:#a06800; }
[data-theme="light"] .project-item { background:#f8f9fb; border-color:#c8ccd4; }
[data-theme="light"] .project-item:hover { border-color:var(--accent); }
[data-theme="light"] .estimate-item { background:#f8f9fb; border-color:#c8ccd4; }
[data-theme="light"] .estimate-btn { border-color:#a0a8b4; color:#4a5060; }
[data-theme="light"] .estimate-btn:hover { border-color:var(--accent); color:var(--accent); }
[data-theme="light"] .success-msg { background:#e8f5e8; border-color:#a0d8a0; color:#248a38; }
[data-theme="light"] select, [data-theme="light"] input[type="text"], [data-theme="light"] textarea { background:#fff; border-color:#b8bcc8; color:var(--text); }
[data-theme="light"] .doc-item { border-color:#c8ccd4; }
[data-theme="light"] .stage-consulting { background:#fff3e0; color:#a06800; }
[data-theme="light"] .stage-equipment { background:#e8f5e8; color:#248a38; }
[data-theme="light"] .stage-proposal { background:#e0f0ff; color:#2e6a9a; }
[data-theme="light"] .stage-estimate { background:#f0e8ff; color:#5c2e90; }
[data-theme="light"] .stage-payment { background:#e0f8f5; color:#0a8a70; }
[data-theme="light"] .stage-visit { background:#e8f5e8; color:#248a38; }
[data-theme="light"] .stage-as { background:#ffe8e8; color:#c03838; }
[data-theme="light"] .stage-done { background:#e8eaef; color:#5a6070; }

/* ── dvh 지원 ── */
:root { --full-h: 100vh; }
@supports (height: 100dvh) { :root { --full-h: 100dvh; } }

/* ── 모바일 기본 스타일 (iframe 내부 모든 페이지 적용) ── */
@media (max-width: 768px) {
    /* 입력 필드 터치 대응 */
    input[type="text"], input[type="email"], input[type="tel"], input[type="number"],
    input[type="date"], input[type="time"], input[type="search"], input[type="url"],
    input[type="password"], select, textarea {
        min-height: 44px;
        font-size: 16px !important; /* iOS 줌 방지 */
    }
    /* 버튼 터치 대응 */
    button:not(.tab-close):not(.ct-close), .btn, [class*="btn-"] {
        min-height: 36px;
    }
    /* 모달 전체폭 */
    .modal-overlay > div, .modal, .new-client-modal {
        width: 95vw !important;
        max-width: 95vw !important;
        padding: 16px !important;
    }
    /* 테이블 스크롤 */
    .data-card, .table-wrap {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    /* 그리드 1열 */
    .field-row, .field-row-3, .info-grid {
        grid-template-columns: 1fr !important;
    }
    .info-card.full { grid-column: 1 !important; }
    /* 페이지 패딩 */
    .page-wrap { padding: 16px !important; }
    .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
}
</style>
@stack('styles')
@yield('content')

{{-- 활동 로그 모달 --}}
<div id="activityLogOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9000;backdrop-filter:blur(3px);align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:100%;max-width:580px;max-height:80vh;display:flex;flex-direction:column;">
        <div style="padding:16px 20px 12px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);flex-shrink:0;">
            <div style="font-size:15px;font-weight:600;color:var(--text);" id="activityLogTitle">수정 로그</div>
            <button onclick="document.getElementById('activityLogOverlay').style.display='none'" style="background:none;border:1px solid var(--border);color:var(--text-muted);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <div id="activityLogBody" style="flex:1;overflow-y:auto;padding:12px 20px 20px;">
            <div style="padding:20px;text-align:center;color:var(--text-muted,#999);font-size:13px;">로딩 중...</div>
        </div>
    </div>
</div>

<script>
async function openActivityLog(type, id, title) {
    const overlay = document.getElementById('activityLogOverlay');
    const body = document.getElementById('activityLogBody');
    document.getElementById('activityLogTitle').textContent = (title || '수정 로그');
    body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted,#999);font-size:13px;">로딩 중...</div>';
    overlay.style.display = 'flex';
    const AL={create:'생성',update:'수정',delete:'삭제'};
    const AC={create:'#22c55e',update:'var(--accent,#c8b08a)',delete:'#ef4444'};
    try {
        const res = await fetch(`/api/activity-logs?type=${type}&id=${id}&limit=100`);
        if (!res.ok) throw new Error();
        const logs = await res.json();
        if (!logs.length) { body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-muted,#999);font-size:13px;">수정 이력이 없습니다.</div>'; return; }
        body.innerHTML = logs.map(log => {
            let ch = '';
            if (log.changes && Object.keys(log.changes).length) {
                ch = Object.entries(log.changes).map(([k,v]) => {
                    const o = typeof v.old==='object'?JSON.stringify(v.old):(v.old??'—');
                    const n = typeof v.new==='object'?JSON.stringify(v.new):(v.new??'—');
                    return `<div style="margin:4px 0 4px 12px;font-size:12px;"><span style="color:var(--text-muted,#999);">${k}:</span> <span style="text-decoration:line-through;color:#ef4444;opacity:0.7;">${o}</span> → <span style="color:#22c55e;">${n}</span></div>`;
                }).join('');
            }
            return `<div style="padding:10px 0;border-bottom:1px solid var(--border,#3a3a3a);">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;color:${AC[log.action]||'var(--text-muted)'};border:1px solid;opacity:0.8;">${AL[log.action]||log.action}</span>
                    <span style="font-size:12px;font-weight:600;">${log.user}</span>
                    <span style="font-size:10px;color:var(--text-muted,#999);margin-left:auto;">${log.created_at}</span>
                </div>
                ${log.summary?'<div style="font-size:12px;color:var(--text-muted,#999);margin-top:4px;">'+log.summary+'</div>':''}
                ${ch}
            </div>`;
        }).join('');
    } catch(e) {
        body.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:13px;">로드 실패</div>';
    }
}

// ── 엑셀 가져오기 공통 ──
function openExcelImportModal(type, typeName) {
    let overlay = document.getElementById('excelImportOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'excelImportOverlay';
        overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9000;backdrop-filter:blur(3px);align-items:center;justify-content:center;';
        overlay.onclick = e => { if (e.target === overlay) overlay.style.display = 'none'; };
        overlay.innerHTML = `
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:100%;max-width:440px;padding:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <div style="font-size:16px;font-weight:700;" id="excelImportTitle">엑셀 가져오기</div>
                    <button onclick="document.getElementById('excelImportOverlay').style.display='none'" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;">✕</button>
                </div>
                <div style="margin-bottom:16px;">
                    <a id="excelTemplateLink" href="#" style="font-size:12px;color:var(--accent);text-decoration:none;">📥 템플릿 다운로드 (.xlsx)</a>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">템플릿을 다운로드하여 데이터를 입력한 후 업로드하세요. 빨간색 컬럼은 필수값입니다.</div>
                </div>
                <div style="margin-bottom:16px;">
                    <input type="file" id="excelImportFile" accept=".xlsx,.xls,.csv" style="display:none;">
                    <button onclick="document.getElementById('excelImportFile').click()" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px;color:var(--text);font-size:13px;cursor:pointer;width:100%;text-align:center;">📎 파일 선택 (.xlsx, .csv)</button>
                    <div id="excelFileName" style="font-size:12px;color:var(--accent);margin-top:6px;display:none;"></div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button onclick="document.getElementById('excelImportOverlay').style.display='none'" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">취소</button>
                    <button id="excelImportBtn" onclick="submitExcelImport()" style="background:var(--accent);color:#1a1207;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;" disabled>가져오기</button>
                </div>
                <div id="excelImportResult" style="display:none;margin-top:16px;padding:12px;border-radius:8px;font-size:12px;"></div>
            </div>`;
        document.body.appendChild(overlay);
        document.getElementById('excelImportFile').addEventListener('change', function () {
            const name = this.files[0]?.name;
            const el = document.getElementById('excelFileName');
            if (name) { el.textContent = '📄 ' + name; el.style.display = 'block'; document.getElementById('excelImportBtn').disabled = false; }
            else { el.style.display = 'none'; document.getElementById('excelImportBtn').disabled = true; }
        });
    }
    overlay.dataset.type = type;
    document.getElementById('excelImportTitle').textContent = typeName + ' 엑셀 가져오기';
    document.getElementById('excelTemplateLink').href = '/api/import/template/' + type;
    document.getElementById('excelImportFile').value = '';
    document.getElementById('excelFileName').style.display = 'none';
    document.getElementById('excelImportBtn').disabled = true;
    document.getElementById('excelImportResult').style.display = 'none';
    overlay.style.display = 'flex';
}

async function submitExcelImport() {
    const overlay = document.getElementById('excelImportOverlay');
    const type = overlay.dataset.type;
    const file = document.getElementById('excelImportFile').files[0];
    if (!file) return;
    const btn = document.getElementById('excelImportBtn');
    btn.disabled = true; btn.textContent = '처리 중...';
    const fd = new FormData(); fd.append('file', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    try {
        const res = await fetch('/api/import/' + type, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await res.json();
        const resultEl = document.getElementById('excelImportResult');
        if (res.ok) {
            resultEl.style.background = 'rgba(34,197,94,0.1)'; resultEl.style.color = 'var(--green)';
            let html = '✅ ' + data.message;
            if (data.errors && data.errors.length) html += '<br><br><span style="color:var(--red);">⚠ 오류:</span><br>' + data.errors.join('<br>');
            resultEl.innerHTML = html;
        } else {
            resultEl.style.background = 'rgba(239,68,68,0.1)'; resultEl.style.color = 'var(--red)';
            resultEl.innerHTML = '❌ ' + (data.error || data.message || '가져오기 실패');
        }
        resultEl.style.display = 'block';
    } catch (e) {
        alert('오류가 발생했습니다.');
    }
    btn.disabled = false; btn.textContent = '가져오기';
}
</script>

@stack('scripts')
