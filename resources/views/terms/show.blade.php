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
    .doc { background:#fff; border:1px solid var(--line); border-radius:12px; padding:26px 28px; font-size:15px; line-height:1.75; }
    /* 섹션 문구에 문서 래퍼(.doc 등)가 통째로 들어와도 이중 박스가 생기지 않게 평탄화 */
    .doc .doc, .doc [class*="wrap"], .doc [class*="card"] { border:none !important; border-radius:0 !important; padding:0 !important; margin:0 !important; background:transparent !important; max-width:none !important; box-shadow:none !important; }
    .doc h1 { font-size:19px; margin:4px 0 8px; color:var(--navy); }
    .doc h3 { font-size:16px; margin:22px 0 8px; color:var(--navy); }
    .doc h4 { font-size:14px; margin:18px 0 6px; color:#2c3e50; }
    .doc ul { padding-left:20px; margin:6px 0; }
    .optional-badge { display:inline-block; background:rgba(46,108,181,.12); color:var(--blue); font-size:12px; font-weight:700; border-radius:5px; padding:2px 8px; margin-left:8px; vertical-align:middle; }
    .foot { position:fixed; left:0; right:0; bottom:0; background:#fff; border-top:1px solid var(--line); padding:14px 16px calc(14px + env(safe-area-inset-bottom)); }
    .foot-in { max-width:760px; margin:0 auto; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .hint { flex:1; min-width:200px; font-size:13px; color:var(--muted); }
    .agree-btn { background:var(--navy); color:#fff; border:none; border-radius:10px; padding:13px 26px; font-size:15px; font-weight:800; cursor:pointer; }
    .agree-btn:disabled { background:#b9c2cc; cursor:not-allowed; }
    .agreed { color:var(--green); font-weight:800; font-size:15px; }
    .close-btn { background:none; border:1px solid var(--line); border-radius:10px; padding:12px 20px; font-size:14px; cursor:pointer; }
    .close-btn:disabled { color:#a3abb5; cursor:not-allowed; }
    @media (max-width:560px) { .foot-in .agree-btn, .foot-in .close-btn { flex:1; padding-left:10px; padding-right:10px; white-space:nowrap; } .hint { flex-basis:100%; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>{{ $doc['title'] }}@if($type === 'marketing')<span class="optional-badge">동의 여부 선택</span>@endif</h1>
        <small>(주)닥터고블린컴퍼니 · {{ $doc['version'] }}</small>
    </div>
    <div class="doc" id="doc">{!! $doc['html'] !!}</div>
</div>

@if($estimate)
@php
    $rec = $estimate->agreements[$type] ?? null;
    $already = $rec['at'] ?? null;
    $isMarketing = $type === 'marketing';
    $mkAgreed = $isMarketing ? \App\Http\Controllers\ConsentController::isAgreed($rec) : true;
@endphp
<div class="foot"><div class="foot-in">
    @if($already && ! $isMarketing)
        <span class="agreed">✓ {{ $already }}에 동의하셨습니다</span>
        <button class="close-btn" onclick="window.close()">창 닫기</button>
    @elseif($isMarketing)
        {{-- 마케팅: 동의/동의 안 함 중 하나를 반드시 고른다 (결제 전 필수). 이미 골랐으면 변경 가능 --}}
        <span class="hint" id="hint">
            @if($already)
                현재 선택: <b>{{ $mkAgreed ? '동의함' : '동의하지 않음' }}</b> ({{ $already }}) — 바꾸시려면 아래에서 다시 선택하세요.
            @else
                동의 여부를 선택해 주세요. 동의하지 않아도 결제와 서비스 이용에 불이익이 없습니다.
            @endif
        </span>
        <button class="close-btn" id="declineBtn" onclick="doAgree(false)" {{ $already && ! $mkAgreed ? 'disabled' : '' }}>동의하지 않습니다</button>
        <button class="agree-btn" id="agreeBtn" disabled data-keep="{{ $already && $mkAgreed ? '1' : '' }}" onclick="doAgree(true)">사례 활용에 동의합니다</button>
    @else
        <span class="hint" id="hint">문서를 끝까지 읽으시면 동의 버튼이 활성화됩니다.</span>
        <button class="agree-btn" id="agreeBtn" disabled onclick="doAgree(true)">내용을 확인했으며 동의합니다</button>
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
            btn.disabled = btn.dataset.keep === '1'; // 이미 동의한 상태면 그대로
            const h = document.getElementById('hint');
            if (h && !{{ $already ? 'true' : 'false' }}) h.textContent = '내용을 확인하셨다면 버튼을 눌러 선택해 주세요.';
            window.removeEventListener('scroll', check);
        }
    }
    window.addEventListener('scroll', check, { passive: true });
    check();
})();
async function doAgree(agree) {
    const btns = [document.getElementById('agreeBtn'), document.getElementById('declineBtn')].filter(Boolean);
    btns.forEach(b => b.disabled = true);
    try {
        const res = await fetch({!! json_encode(url('/estimate-view/'.$estimate->share_token.'/agree')) !!}, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ type: '{{ $type }}', agree: agree }),
        });
        if (!res.ok) throw new Error();
        if (window.opener && !window.opener.closed) { try { window.opener.location.reload(); } catch (e) {} }
        const msg = agree ? '✓ 동의가 기록되었습니다.' : '✓ ‘동의하지 않음’으로 기록되었습니다.';
        document.querySelector('.foot-in').innerHTML = '<span class="agreed">' + msg + ' 이 창을 닫고 견적서에서 이어서 진행해 주세요.</span><button class="close-btn" onclick="window.close()">창 닫기</button>';
    } catch (e) {
        btns.forEach(b => b.disabled = false);
        alert('잠시 후 다시 시도해 주세요.');
    }
}
</script>
@endif
</body>
</html>
