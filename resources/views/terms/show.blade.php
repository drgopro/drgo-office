<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $doc['title'] }} - 닥터고블린컴퍼니</title>
<style>
    :root { --navy:#1d2d3d; --line:#dfe3e9; --muted:#66727f; --green:#1c7f4d; --blue:#2e6cb5; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Pretendard Variable',Pretendard,'Malgun Gothic','Apple SD Gothic Neo',sans-serif; background:#eef0f3; color:#1d1f24; }
    .wrap { max-width:760px; margin:0 auto; padding:24px 16px 120px; }
    .head { background:var(--navy); color:#fff; border-radius:12px; padding:22px 26px; margin-bottom:16px; }
    .head h1 { margin:0; font-size:21px; letter-spacing:.05em; }
    .head small { opacity:.75; font-size:12px; }
    .doc { background:#fff; border:1px solid var(--line); border-radius:12px; padding:26px 28px; font-size:14.5px; line-height:1.75; }
    /* 섹션 문구에 문서 래퍼(.doc 등)가 통째로 들어와도 이중 박스가 생기지 않게 평탄화 */
    .doc .doc, .doc [class*="wrap"], .doc [class*="card"] { border:none !important; border-radius:0 !important; padding:0 !important; margin:0 !important; background:transparent !important; max-width:none !important; box-shadow:none !important; }
    .doc h1 { font-size:19px; margin:4px 0 8px; color:var(--navy); }
    .doc h3 { font-size:15.5px; margin:22px 0 8px; color:var(--navy); }
    .doc h4 { font-size:14px; margin:18px 0 6px; color:#2c3e50; }
    .doc ul { padding-left:20px; margin:6px 0; }
    .optional-badge { display:inline-block; background:rgba(46,108,181,.12); color:var(--blue); font-size:12px; font-weight:700; border-radius:5px; padding:2px 8px; margin-left:8px; vertical-align:middle; }
    .foot { position:fixed; left:0; right:0; bottom:0; background:#fff; border-top:1px solid var(--line); padding:14px 16px calc(14px + env(safe-area-inset-bottom)); }
    .foot-in { max-width:760px; margin:0 auto; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .hint { flex:1; min-width:200px; font-size:12.5px; color:var(--muted); }
    .agree-btn { background:var(--navy); color:#fff; border:none; border-radius:10px; padding:13px 26px; font-size:15px; font-weight:800; cursor:pointer; }
    .agree-btn:disabled { background:#b9c2cc; cursor:not-allowed; }
    .agreed { color:var(--green); font-weight:800; font-size:15px; }
    .close-btn { background:none; border:1px solid var(--line); border-radius:10px; padding:12px 20px; font-size:14px; cursor:pointer; }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>{{ $doc['title'] }}@if($type === 'marketing')<span class="optional-badge">선택 동의</span>@endif</h1>
        <small>(주)닥터고블린컴퍼니 · {{ $doc['version'] }}</small>
    </div>
    <div class="doc" id="doc">{!! $doc['html'] !!}</div>
</div>

@if($estimate)
@php $already = $estimate->agreements[$type]['at'] ?? null; @endphp
<div class="foot"><div class="foot-in">
    @if($already)
        <span class="agreed">✓ {{ $already }}에 동의하셨습니다</span>
        <button class="close-btn" onclick="window.close()">창 닫기</button>
    @else
        <span class="hint" id="hint">문서를 끝까지 읽으시면 동의 버튼이 활성화됩니다.</span>
        <button class="agree-btn" id="agreeBtn" disabled onclick="doAgree()">
            {{ $type === 'penalty' ? '내용을 확인했으며 동의합니다' : '사례 활용에 동의합니다 (선택)' }}
        </button>
        @if($type === 'marketing')
        <button class="close-btn" onclick="window.close()">동의하지 않고 닫기</button>
        @endif
    @endif
</div></div>
<script>
// 완독 감지 — 페이지 끝(여유 40px)에 닿으면 동의 버튼 활성화. 짧은 문서는 즉시 활성화.
(function () {
    const btn = document.getElementById('agreeBtn');
    if (!btn) return;
    function check() {
        const bottom = window.innerHeight + window.scrollY;
        const full = document.documentElement.scrollHeight;
        if (bottom >= full - 40) {
            btn.disabled = false;
            const h = document.getElementById('hint');
            if (h) h.textContent = '내용을 확인하셨다면 동의 버튼을 눌러주세요.';
            window.removeEventListener('scroll', check);
        }
    }
    window.addEventListener('scroll', check, { passive: true });
    check();
})();
async function doAgree() {
    const btn = document.getElementById('agreeBtn');
    btn.disabled = true;
    try {
        const res = await fetch({!! json_encode(url('/estimate-view/'.$estimate->share_token.'/agree')) !!}, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ type: '{{ $type }}' }),
        });
        if (!res.ok) throw new Error();
        if (window.opener && !window.opener.closed) { try { window.opener.location.reload(); } catch (e) {} }
        document.querySelector('.foot-in').innerHTML = '<span class="agreed">✓ 동의가 기록되었습니다. 이 창을 닫고 견적서에서 이어서 진행해 주세요.</span><button class="close-btn" onclick="window.close()">창 닫기</button>';
    } catch (e) {
        btn.disabled = false;
        alert('잠시 후 다시 시도해 주세요.');
    }
}
</script>
@endif
</body>
</html>
