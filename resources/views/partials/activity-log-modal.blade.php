{{-- 활동 로그 모달 (공용) — openActivityLog(type, id, title). app/tab-content 레이아웃과 독립 페이지(견적서 빌더 등)에서 공유 --}}
<div id="activityLogOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9000;backdrop-filter:blur(3px);align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--surface,#fff);border:1px solid var(--border,#d9dee5);border-radius:16px;width:100%;max-width:580px;max-height:80vh;display:flex;flex-direction:column;">
        <div style="padding:16px 20px 12px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border,#d9dee5);flex-shrink:0;">
            <div style="font-size:15px;font-weight:600;color:var(--text,#1c2733);" id="activityLogTitle">수정 로그</div>
            <button onclick="document.getElementById('activityLogOverlay').style.display='none'" style="background:none;border:1px solid var(--border,#d9dee5);color:var(--text-muted,#8a97a5);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">✕</button>
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

    const ACTION_L = {create:'생성',update:'수정',delete:'삭제'};
    const ACTION_C = {create:'#22c55e',update:'var(--accent,#c8b08a)',delete:'#ef4444'};

    try {
        const res = await fetch(`/api/activity-logs?type=${type}&id=${id}&limit=100`);
        if (!res.ok) throw new Error();
        const logs = await res.json();
        if (!logs.length) { body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-muted,#999);font-size:13px;">수정 이력이 없습니다.</div>'; return; }
        const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
        body.innerHTML = logs.map(log => {
            let changesHtml = '';
            if (log.changes && Object.keys(log.changes).length) {
                changesHtml = Object.entries(log.changes).map(([key, val]) => {
                    const oldV = typeof val.old === 'object' ? JSON.stringify(val.old) : (val.old ?? '—');
                    const newV = typeof val.new === 'object' ? JSON.stringify(val.new) : (val.new ?? '—');
                    return `<div style="margin:4px 0 4px 12px;font-size:12px;">
                        <span style="color:var(--text-muted,#999);">${esc(key)}:</span>
                        <span style="text-decoration:line-through;color:#ef4444;opacity:0.7;">${esc(oldV)}</span>
                        → <span style="color:#22c55e;">${esc(newV)}</span>
                    </div>`;
                }).join('');
            }
            return `<div style="padding:10px 0;border-bottom:1px solid var(--border,#d9dee5);">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;color:${ACTION_C[log.action]||'var(--text-muted)'};border:1px solid;opacity:0.8;">${ACTION_L[log.action]||esc(log.action)}</span>
                    <span style="font-size:12px;font-weight:600;">${esc(log.user)}</span>
                    <span style="font-size:10px;color:var(--text-muted,#999);margin-left:auto;">${esc(log.created_at)}</span>
                </div>
                ${log.summary ? '<div style="font-size:12px;color:var(--text-muted,#999);margin-top:4px;">'+esc(log.summary)+'</div>' : ''}
                ${changesHtml}
            </div>`;
        }).join('');
    } catch(e) {
        body.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:13px;">로드 실패</div>';
    }
}
</script>
