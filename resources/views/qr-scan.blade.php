<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
<script>(function(){var t=localStorage.getItem('drgo_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#111111">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/icon-192.png">
<meta name="mobile-web-app-capable" content="yes">
<title>QR 스캔 - 닥터고블린 오피스</title>
<link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<style>
:root { --bg:#111; --surface:#1c1c1c; --surface2:#272727; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --red:#d48888; --green:#88d488; }
[data-theme="light"] { --bg:#f4f5f7; --surface:#fff; --surface2:#eceef2; --border:#b8bcc8; --text:#1a1e28; --text-muted:#5a6070; --accent:#3b5ea0; --red:#c03838; --green:#248a38; }
* { margin:0; padding:0; box-sizing:border-box; }
html { background:var(--bg); }
body { background:var(--bg); color:var(--text); font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; min-height:100vh; min-height:100dvh; display:flex; flex-direction:column; align-items:center; padding:16px; padding-top:max(env(safe-area-inset-top, 0px), 48px); padding-bottom:max(env(safe-area-inset-bottom, 0px), 16px); }
input, button, textarea, select { font-family:inherit; }
.qr-header { width:100%; max-width:420px; display:flex; justify-content:space-between; align-items:center; padding:16px 0; }
.qr-title { font-size:18px; font-weight:700; }
.qr-home { background:none; border:1px solid var(--border); color:var(--text-muted); padding:8px 14px; border-radius:8px; font-size:12px; cursor:pointer; text-decoration:none; }
.qr-reader { width:100%; max-width:420px; background:#000; border-radius:16px; overflow:hidden; min-height:300px; }
.qr-manual { width:100%; max-width:420px; margin-top:12px; display:flex; gap:8px; }
.qr-manual input { flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 14px; color:var(--text); font-size:14px; outline:none; }
.qr-manual button { background:var(--accent); color:var(--accent-text); border:none; padding:12px 20px; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
[data-theme="light"] .qr-manual button { color:#fff; }
.qr-status { width:100%; max-width:420px; margin-top:16px; padding:16px; background:var(--surface); border:1px solid var(--border); border-radius:12px; font-size:14px; line-height:1.6; display:none; }
.qr-actions { width:100%; max-width:420px; margin-top:12px; display:none; gap:8px; flex-wrap:wrap; }
.qr-actions button { flex:1; min-width:100px; padding:14px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; color:#fff; }

/* 플로팅 토스트 */
.qr-toast { position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:var(--accent); color:var(--accent-text); padding:14px 28px; border-radius:12px; font-size:15px; font-weight:700; z-index:9999; box-shadow:0 6px 24px rgba(0,0,0,0.4); animation:toastIn 0.3s ease, toastOut 0.3s ease 2.7s forwards; }
[data-theme="light"] .qr-toast { color:#fff; }
@keyframes toastIn { from { opacity:0; transform:translateX(-50%) translateY(20px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }
@keyframes toastOut { from { opacity:1; } to { opacity:0; transform:translateX(-50%) translateY(-10px); } }
</style>
</head>
<body>

<div class="qr-header">
    <div class="qr-title">📷 QR 스캔</div>
    <a href="/" class="qr-home">홈으로</a>
</div>

<div class="qr-reader" id="qrReader"></div>

<div class="qr-manual">
    <input id="qrManualInput" placeholder="rental:ID 또는 staff:ID">
    <button onclick="handleQr(document.getElementById('qrManualInput').value)">확인</button>
</div>

<div class="qr-status" id="qrStatus"></div>
<div class="qr-actions" id="qrActions"></div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let scanner = null, itemData = null, staffData = null;

// 바로 카메라 시작
(async function(){
    try {
        scanner = new Html5Qrcode('qrReader');
        await scanner.start({ facingMode:'environment' }, { fps:10, qrbox:{ width:250, height:250 } }, decoded => handleQr(decoded), ()=>{});
    } catch(e) { showStatus('카메라를 사용할 수 없습니다. 수동 입력을 이용하세요.', 'var(--red)'); }
})();

async function handleQr(code) {
    if (!code) return;
    const res = await fetch('/api/rental/lookup?code=' + encodeURIComponent(code.trim()), { headers:{ 'Accept':'application/json' } });
    if (!res.ok) { const err = await res.json().catch(()=>({})); showStatus('❌ ' + (err.error || '인식 실패'), 'var(--red)'); return; }
    const data = await res.json();

    if (data.type === 'item') {
        itemData = data;
        if (staffData) {
            await doAssign(itemData.id, staffData.id, staffData.name);
        } else {
            showStatus(`🔧 <b>${data.name}</b>${data.serial ? ' (' + data.serial + ')' : ''}<br>현재: ${data.current_target || '미지정'} · 원위치: ${data.home_target || '없음'}`);
            showActions([
                { label:'👤 직원 QR 스캔', color:'var(--accent)', fn: () => { showStatus('👤 직원 QR을 스캔하세요...', '#8ab4c8'); hideActions(); }},
                { label:'🏠 반납', color:'var(--green)', fn: () => doReturn(data.id) },
                { label:'초기화', color:'var(--border)', fn: reset },
            ]);
        }
    } else if (data.type === 'staff') {
        staffData = data;
        if (itemData) {
            await doAssign(itemData.id, staffData.id, staffData.name);
        } else {
            showStatus(`👤 <b>${data.name}</b>${data.phone ? ' · ' + data.phone : ''}<br>보유 장비: ${data.items_count}개`);
            showActions([
                { label:'🔧 장비 QR 스캔', color:'var(--accent)', fn: () => { showStatus('🔧 장비 QR을 스캔하세요...', '#c8b08a'); hideActions(); }},
                { label:'초기화', color:'var(--border)', fn: reset },
            ]);
        }
    }
}

async function doAssign(itemId, targetId, targetName) {
    const res = await fetch('/api/rental/assign', {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        body: JSON.stringify({ item_id:itemId, target_id:targetId, memo:'QR 스캔 대여' })
    });
    if (res.ok) {
        showToast(`✅ ${itemData.name} → ${targetName} 대여 처리되었습니다.`);
        showStatus(`✅ <b>${itemData.name}</b> → <b>${targetName}</b> 대여 완료!`, 'var(--green)');
        showActions([{ label:'📷 다음 스캔', color:'var(--accent)', fn: reset }]);
    } else {
        showStatus('❌ 대여 처리 실패', 'var(--red)');
    }
}

async function doReturn(itemId) {
    const res = await fetch('/api/rental/assign', {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        body: JSON.stringify({ item_id:itemId, return:true, memo:'QR 스캔 반납' })
    });
    if (res.ok) {
        showToast(`✅ ${itemData.name} 반납 처리되었습니다.`);
        showStatus(`✅ <b>${itemData.name}</b> 반납 완료!`, 'var(--green)');
        showActions([{ label:'📷 다음 스캔', color:'var(--accent)', fn: reset }]);
    } else {
        showStatus('❌ 반납 실패', 'var(--red)');
    }
}

function reset() { itemData = null; staffData = null; hideStatus(); hideActions(); }

function showStatus(html, color) {
    const el = document.getElementById('qrStatus');
    el.innerHTML = html; el.style.display = 'block'; el.style.color = color || 'var(--text)';
}
function hideStatus() { document.getElementById('qrStatus').style.display = 'none'; }

function showActions(btns) {
    const el = document.getElementById('qrActions');
    el.style.display = 'flex';
    el.innerHTML = btns.map((b, i) => `<button style="background:${b.color};" id="qrActBtn${i}">${b.label}</button>`).join('');
    btns.forEach((b, i) => document.getElementById('qrActBtn' + i).onclick = b.fn);
}
function hideActions() { document.getElementById('qrActions').style.display = 'none'; }

function showToast(msg) {
    const el = document.createElement('div');
    el.className = 'qr-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}
</script>
</body>
</html>
