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
:root { --bg:#111111; --surface:#1c1c1c; --surface2:#272727; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --accent2:#90bcd4; --red:#d48888; --green:#88d488; --blue:#8ab4c8; --gold:#c8b08a; --teal:#e8894a; --purple:#9b70c8; }
[data-theme="light"] { --bg:#f4f5f7; --surface:#ffffff; --surface2:#eceef2; --surface3:#dfe2e8; --border:#b8bcc8; --text:#1a1e28; --text-muted:#5a6070; --accent:#3b5ea0; --accent2:#2e6a8a; --red:#c03838; --green:#248a38; --blue:#2e6a9a; --gold:#907030; --teal:#b85c18; --purple:#5c2e90; }
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
</script>

@stack('scripts')
