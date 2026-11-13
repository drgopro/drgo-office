<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>검토가 끝나지 않은 견적서</title>
<style>
    body { margin:0; font-family:'Malgun Gothic','Apple SD Gothic Neo',sans-serif; background:#eef0f3; color:#1d1f24; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .box { background:#fff; border:1px solid #dfe3e9; border-radius:12px; padding:36px 40px; max-width:460px; text-align:center; box-shadow:0 2px 10px rgba(20,30,40,.08); }
    h2 { margin:0 0 10px; font-size:19px; }
    p { margin:0 0 20px; font-size:14px; color:#66727f; line-height:1.6; }
    a { display:inline-block; background:#1d2d3d; color:#fff; text-decoration:none; border-radius:8px; padding:9px 18px; font-size:14px; font-weight:700; }
</style>
</head>
<body>
<div class="box">
    <h2>검토가 끝나지 않은 견적서입니다</h2>
    <p>{{ $message }}</p>
    <a href="{{ route('estimates.edit', $estimate) }}">견적서 편집으로 돌아가기</a>
</div>
</body>
</html>
