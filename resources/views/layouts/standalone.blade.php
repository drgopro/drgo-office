{{-- 독립 창 레이아웃 — tab-content(조각)에 문서 머리(CSRF·뷰포트·제목)를 씌워 단독 창/탭으로 띄운다 (예: 의뢰자 작성 모드) --}}
<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', '닥터고블린 오피스')</title>
</head>
<body style="margin:0; background:var(--bg, #f4f5f7);">
@include('layouts.tab-content')
</body>
</html>
