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
<script>
// 작성 모드 창 — 이 창은 한 건 작성 전용. 다른 화면(프로젝트·견적서 등)을 열면 메인 오피스 창의 탭으로 보낸다
window.DRGO_STANDALONE = true;
(function () {
    window.openTopTab = function (type, url, title) {
        try {
            const top = window.opener && !window.opener.closed ? window.opener.top : null;
            if (top && top.drgoTabs && typeof top.drgoTabs.openNav === 'function') { top.drgoTabs.openNav(type, url, title); top.focus(); return; }
        } catch (e) { /* 다른 출처 등 */ }
        window.open(url, '_blank');
    };
})();
</script>
</body>
</html>
