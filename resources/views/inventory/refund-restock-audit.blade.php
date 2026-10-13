@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('content')
<style>
    .ra-wrap { max-width:1100px; margin:0 auto; padding:24px 16px; }
    .ra-title { font-size:18px; font-weight:800; margin-bottom:4px; }
    .ra-sub { font-size:12.5px; color:var(--text-muted); line-height:1.6; margin-bottom:18px; }
    .ra-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .ra-table { width:100%; border-collapse:collapse; }
    .ra-table th { font-size:11px; color:var(--text-muted); text-align:left; padding:10px 14px; background:var(--surface2); border-bottom:1px solid var(--border); white-space:nowrap; }
    .ra-table td { font-size:13px; padding:11px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .ra-kind { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:9px; white-space:nowrap; }
    .ra-kind-direct { background:rgba(200,80,80,0.12); color:var(--red); }
    .ra-kind-vendor { background:rgba(74,144,217,0.14); color:#4a90d9; }
    .ra-apply { padding:5px 13px; font-size:12px; font-weight:700; border-radius:7px; border:1px solid var(--accent); background:none; color:var(--accent); cursor:pointer; white-space:nowrap; }
    .ra-apply:hover { background:var(--accent); color:#fff; }
    .ra-apply:disabled { opacity:0.5; cursor:default; }
    .ra-empty { padding:50px; text-align:center; color:var(--text-muted); font-size:14px; }
    .ra-link { color:var(--accent); text-decoration:none; }
</style>
<div class="ra-wrap">
    <div class="ra-title">환불 재고 보정 (임시 점검)</div>
    <div class="ra-sub">
        과거 세트상품 환불 로직 결함으로 재고 복원/입고가 누락된 건입니다. 확인 후 [입고 적용]을 누르면 그 수량만큼 재고가 올라가고 입출고 내역에 보정 기록이 남습니다.<br>
        <span class="ra-kind ra-kind-direct">직접발송 복원 누락</span> 직발로 차감됐는데 환불 복원이 빠진 건 — 적용 권장 ·
        <span class="ra-kind ra-kind-vendor">반품 미입고</span> 거래처 주문분 환불인데 사무실 반품 입고 이력이 없는 건 — 실물 반품을 받았을 때만 적용하세요. 모두 보정되면 이 페이지는 비워집니다.
    </div>
    <div class="ra-card">
        @if(count($rows) === 0)
            <div class="ra-empty">보정이 필요한 건이 없습니다.</div>
        @else
        <table class="ra-table">
            <thead><tr><th>구분</th><th>견적서</th><th>의뢰자</th><th>제품</th><th>누락 수량</th><th>환불 시각</th><th></th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr id="ra-{{ $r['kind'] }}-{{ $r['estimate_id'] }}-{{ $r['product_id'] }}">
                    <td><span class="ra-kind ra-kind-{{ $r['kind'] }}">{{ $r['kind'] === 'direct' ? '직접발송 복원 누락' : '반품 미입고' }}</span></td>
                    <td><a class="ra-link" href="/estimates/{{ $r['estimate_id'] }}/edit" target="_blank">#{{ $r['no'] }}</a>{{ $r['title'] ? ' · '.$r['title'] : '' }}</td>
                    <td>{{ $r['client'] }}</td>
                    <td>{{ $r['product'] }}</td>
                    <td><b>{{ $r['qty'] }}개</b></td>
                    <td style="color:var(--text-muted); font-size:12px;">{{ $r['refunded_at'] ?: '-' }}</td>
                    <td style="text-align:right;">
                        <button class="ra-apply" onclick="raApply(this, {{ $r['estimate_id'] }}, {{ $r['product_id'] }}, '{{ $r['kind'] }}', {{ $r['qty'] }})">{{ $r['qty'] }}개 입고 적용</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
<script>
const RA_CSRF = document.querySelector('meta[name="csrf-token"]').content;
async function raApply(btn, estimateId, productId, kind, qty) {
    const label = kind === 'direct' ? '직접발송 복원 누락' : '반품 미입고';
    if (!confirm(`[${label}] ${qty}개를 사무실 재고로 입고할까요?\n입출고 내역에 보정 기록이 남습니다.`)) return;
    btn.disabled = true; btn.textContent = '적용 중…';
    try {
        const res = await fetch('/api/inventory/refund-restock-audit/apply', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':RA_CSRF,'Accept':'application/json'},
            body: JSON.stringify({ estimate_id: estimateId, product_id: productId, kind, qty }),
        });
        const d = await res.json().catch(() => ({}));
        if (!res.ok) { alert(d.message || '적용하지 못했습니다.'); btn.disabled = false; btn.textContent = qty + '개 입고 적용'; return; }
        const row = document.getElementById(`ra-${kind}-${estimateId}-${productId}`);
        if (row) row.remove();
    } catch (e) { alert('적용하지 못했습니다.'); btn.disabled = false; btn.textContent = qty + '개 입고 적용'; }
}
</script>
@endsection
