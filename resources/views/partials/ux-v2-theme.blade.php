{{-- 새 화면 디자인(UX v2) 색 테마 — 내용 영역의 색 변수만 바꾼다. 왼쪽 메뉴(사이드바)는 그대로.
     $scope: 앱 레이아웃은 '.main'(사이드바 제외), 탭 안 페이지(iframe 문서)는 문서 전체.
     html.ux-v2 일 때만 적용 — 스위치를 끄면 원래 색으로 돌아간다. --}}
@php $uxScope = $scope ?? ''; @endphp
<style id="ux-v2-theme">
html.ux-v2[data-theme="light"] {{ $uxScope }} {
    --bg:#eef0f3; --surface:#ffffff; --surface2:#f3f4f6; --surface3:#e6e8ec;
    --border:#d9dde3; --text:#171a20; --text-muted:#5f6573;
    --accent:#1f2430; --accent-text:#ffffff; --red:#d6283f;
    --bx-primary:#1f2430; --bx-primary-hover:#373d4a; --bx-on-primary:#ffffff;
    --bx-line:#d3d7de; --bx-line-hover:#a9afba; --bx-danger:#d6283f; --bx-danger-hover-bg:#d6283f;
}
@if($uxScope)
html.ux-v2[data-theme="light"] {{ $uxScope }} { background:var(--bg); }
@else
html.ux-v2[data-theme="light"] body { background:var(--bg); }
@endif
</style>
