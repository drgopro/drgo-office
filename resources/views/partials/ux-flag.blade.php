{{-- 새 화면 디자인(UX v2) 스위치 — config/ux.php. 서버에서 끄면(UX_V2=false) 모두 이전 디자인(롤백).
     켜져 있으면 각자 drgoUx.set('v1'|'v2')로 선택(localStorage drgo_ux). 개편 스타일은 html.ux-v2 아래에서만 적용된다. --}}
<script>
(function () {
    var allowed = {{ config('ux.v2') ? 'true' : 'false' }};
    var on = allowed;
    try { if (allowed && localStorage.getItem('drgo_ux') === 'v1') { on = false; } } catch (e) {}
    if (on) { document.documentElement.classList.add('ux-v2'); }
    window.drgoUx = {
        allowed: allowed,
        on: on,
        set: function (v) { try { localStorage.setItem('drgo_ux', v); } catch (e) {} location.reload(); },
        toggleHtml: function (cls) {
            if (!allowed) { return ''; }
            return '<button type="button" class="' + (cls || '') + ' ux2-switch" onclick="drgoUx.set(drgoUx.on ? \'v1\' : \'v2\')" title="화면 디자인 전환 (나만 적용)">' + (on ? '이전 디자인' : '새 디자인') + '</button>';
        }
    };
})();
</script>
