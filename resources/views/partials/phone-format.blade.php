{{-- 전화번호 자동 하이픈 (전 페이지 공통) — 01000000000 / 010 0000 0000 입력 시 010-0000-0000으로 자동 포맷 --}}
<script>
// 연락처 자동 하이픈 — 010-1234-5678 / 02-123-4567 등 자릿수에 맞춰 포맷
window.formatPhoneInput = function (v) {
    const d = String(v).replace(/\D/g, '').slice(0, 11);
    if (d.startsWith('02')) { // 서울 지역번호
        if (d.length <= 2) return d;
        if (d.length <= 5) return d.slice(0, 2) + '-' + d.slice(2);
        if (d.length <= 9) return d.slice(0, 2) + '-' + d.slice(2, d.length - 4) + '-' + d.slice(-4);
        return d.slice(0, 2) + '-' + d.slice(2, 6) + '-' + d.slice(6, 10);
    }
    if (d.length <= 3) return d;
    if (d.length <= 7) return d.slice(0, 3) + '-' + d.slice(3);
    return d.slice(0, 3) + '-' + d.slice(3, d.length - 4) + '-' + d.slice(-4);
};
(function () {
    function isPhoneField(el) {
        if (!(el instanceof HTMLInputElement)) return false;
        if (el.type !== 'text' && el.type !== 'tel' && el.type !== '' && el.type !== 'search') return false;
        const idName = (el.id || '') + ' ' + (el.name || '');
        const ph = el.placeholder || '';
        // 검색창 제외 (예: "이름/닉네임/전화번호로 검색")
        if (/search/i.test(idName) || ph.includes('검색')) return false;
        return /phone/i.test(idName) || /^01\d[-\s]?\d{3,4}[-\s]?\d{4}$/.test(ph) || ph.includes('010-0000');
    }
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!isPhoneField(el)) return;
        const raw = el.value;
        // 숫자/공백/하이픈/점/괄호 외 문자가 있으면 건드리지 않음 (메모 겸용 입력 보호)
        if (!/^[\d\s\-.()]*$/.test(raw)) return;
        const digits = raw.replace(/\D/g, '');
        if (digits.length > 11) return; // 자릿수 초과(내선 병기 등)는 그대로 둠
        const next = window.formatPhoneInput(raw);
        if (next === raw) return;
        // 커서 위치 보존 — 커서 앞 숫자 개수 기준으로 재배치
        let caretDigits = 0;
        const pos = el.selectionStart ?? raw.length;
        for (let i = 0; i < pos; i++) { if (/\d/.test(raw[i])) caretDigits++; }
        el.value = next;
        let newPos = next.length;
        if (caretDigits < digits.length) {
            let seen = 0;
            for (let i = 0; i < next.length; i++) {
                if (/\d/.test(next[i])) { if (seen === caretDigits) { newPos = i; break; } seen++; }
            }
        }
        try { el.setSelectionRange(newPos, newPos); } catch (err) { /* number 타입 등 미지원 무시 */ }
    }, true);
})();
</script>
