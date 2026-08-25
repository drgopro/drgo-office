{{--
    같은 도메인으로 나가는 모든 fetch에 X-Requested-With 헤더를 자동 부착.

    Laravel은 AJAX 표시가 없는 GET 요청의 URL을 세션의 '이전 페이지'로 기억하는데,
    화면들이 fetch로 API를 조회할 때마다 그 값이 API 주소로 오염되면
    back() 리다이렉트가 JSON 응답 페이지로 이동해버린다 (알림/프리셋 JSON이 화면에 노출).
    외부 도메인 요청에는 붙이지 않는다 (CORS preflight 유발 방지).
--}}
<script>
(function () {
    const orig = window.fetch;
    window.fetch = function (input, init) {
        try {
            const url = typeof input === 'string' ? input
                : (input instanceof URL ? input.href : (input && input.url) || '');
            const sameOrigin = url.startsWith('/') ? !url.startsWith('//') : url.startsWith(location.origin);
            if (sameOrigin) {
                init = init || {};
                const h = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                if (!h.has('X-Requested-With')) h.set('X-Requested-With', 'XMLHttpRequest');
                init.headers = h;
            }
        } catch (e) { /* 헤더 부착 실패 시 원본 요청 그대로 */ }
        return orig.call(this, input, init);
    };
})();
</script>
