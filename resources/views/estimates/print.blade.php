<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>견적서 #{{ $estimate->display_no }}</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        :root {
            --navy:#1d2d3d;      /* 헤더·합계 밴드 */
            --slate:#416180;     /* 대분류 밴드·라벨 */
            --slate-lt:#8fa8c0;  /* 네이비 위 보조 텍스트 */
            --ink:#1d1f20;
            --line:#e6e8ec;
            --muted:#6b7684;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:"Pretendard Variable",Pretendard,'Malgun Gothic','Apple SD Gothic Neo',-apple-system,sans-serif;
            background:#f2f2f3; color:var(--ink); font-size:13px;
            -webkit-print-color-adjust:exact; print-color-adjust:exact;
        }
        @media print {
            body { padding:0; background:#fff; }
            .no-print { display:none !important; }
            @page { margin:12mm; }
        }
        @media screen {
            body { padding:30px 20px; max-width:1020px; margin:0 auto; }
        }

        .no-print-bar { position:fixed; top:0; left:0; right:0; background:#222; padding:10px 20px; display:flex; gap:10px; align-items:center; z-index:100; }
        .no-print-bar button { background:#c8b08a; color:var(--accent-text); border:none; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; }
        .no-print-bar span { color:#aaa; font-size:12px; }

        /* 의뢰자용 하단 결제 바 */
        .pay-bar { position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid #d8dce4; padding:12px 16px; display:flex; gap:10px; align-items:center; justify-content:center; z-index:100; box-shadow:0 -4px 16px rgba(0,0,0,0.08); }
        .pay-btn { flex:1; max-width:420px; text-align:center; background:#3b5ea0; color:#fff; text-decoration:none; padding:14px 20px; border-radius:10px; font-size:16px; font-weight:800; }
        .pay-btn:active { filter:brightness(1.1); }
        .pay-done { flex:1; max-width:420px; text-align:center; background:#e8f5e8; color:#1a7a2a; padding:14px 20px; border-radius:10px; font-size:15px; font-weight:700; }
        .pay-cancelled { flex:1; max-width:420px; text-align:center; background:#f5eaea; color:#b03030; padding:14px 20px; border-radius:10px; font-size:15px; font-weight:700; }
        .pay-print { background:none; border:1px solid #c8ccd4; color:#5a6070; padding:12px 16px; border-radius:10px; font-size:13px; cursor:pointer; }
        body.public-mode { padding-bottom:90px; }
        /* 의뢰자용 화면은 전체 글자를 한 단계 키움 */
        body.public-mode .estimate-wrap { zoom:1.15; }
        @media print { body.public-mode .estimate-wrap { zoom:1; } }

        .estimate-wrap { background:transparent; }

        /* 상단 네이비 밴드 — 제목 + 작성/발행/유효 */
        .est-band { background:var(--navy); border-radius:8px; padding:20px 24px 18px; display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:16px; }
        .est-title { font-size:32px; font-weight:700; letter-spacing:0.22em; color:#f2f2f3; }
        .est-subtitle { font-size:11px; font-weight:600; letter-spacing:0.24em; color:var(--slate-lt); margin-top:7px; }
        .band-meta { text-align:right; display:flex; flex-direction:column; gap:5px; font-size:12px; white-space:nowrap; }
        .band-meta .m-label { color:var(--slate-lt); margin-right:8px; font-size:11px; letter-spacing:0.08em; font-weight:600; }
        .band-meta .m-value { display:inline-block; background:var(--slate); color:#fff; font-weight:600; font-size:11.5px; padding:2px 9px; border-radius:4px; }

        /* 견적서 제목 헤더 — 밴드 아래, 제목 + 의뢰자 이름 */
        .doc-head { padding:2px 4px 12px; border-bottom:2px solid var(--navy); margin-bottom:16px; }
        .doc-head .doc-title { font-size:20px; font-weight:700; color:var(--ink); line-height:1.35; word-break:keep-all; }
        .doc-head .doc-client { font-size:12.5px; font-weight:600; color:var(--slate); margin-top:5px; }

        /* 상단 정보 2박스 */
        .info-cols { display:flex; gap:14px; margin-bottom:20px; }
        .info-box { flex:1; border:1px solid var(--line); border-radius:8px; padding:16px 20px 14px; background:#fff; position:relative; z-index:0; }
        /* 직인 — 상호명 텍스트 끝에 살짝 걸치는 위치, 글자 뒤 배경 (z-index:-1 + info-box가 스택 컨텍스트) */
        .biz-name-wrap { position:relative; display:inline-block; }
        .seller-stamp { position:absolute; left:100%; top:50%; transform:translate(-40%,-50%); width:84px; max-height:84px; object-fit:contain; opacity:0.88; z-index:-1; pointer-events:none; }
        .info-box.wide { flex:1.5; }
        .info-box h3 { font-size:11px; font-weight:700; letter-spacing:0.18em; color:var(--slate); margin-bottom:11px; }
        .info-table { width:100%; border-collapse:collapse; }
        .info-table td { padding:4.5px 0; font-size:12px; vertical-align:top; }
        .info-table .label { color:#7f94ab; width:84px; font-weight:600; }
        .info-table .value { color:var(--ink); font-weight:600; }

        /* 제품 테이블 */
        .est-table { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:4px; }
        .est-table th { padding:9px 10px 11px; font-size:11px; font-weight:700; color:var(--slate); letter-spacing:0.12em; text-align:left; border-top:1px solid var(--navy); border-bottom:2px solid var(--navy); background:#fff; }
        .est-table th.col-time, .est-table th.col-qty { text-align:center; }
        .est-table th.col-price, .est-table th.col-total { text-align:right; }
        .est-table td { padding:10px; font-size:12.5px; background:#fff; border-bottom:1px solid var(--line); word-break:keep-all; }
        /* 금액·수량은 줄바꿈 금지 — 제품명이 남는 폭을 모두 사용 */
        .est-table td.text-right, .est-table td.text-center { white-space:nowrap; }
        .est-table col.col-no { width:44px; }
        .est-table col.col-cat { width:120px; }
        .est-table col.col-time { width:70px; }
        .est-table col.col-price { width:110px; }
        .est-table col.col-qty { width:50px; }
        .est-table col.col-total { width:120px; }
        .grp-gap td { background:transparent; border:none; height:12px; padding:0; }
        .cat-header td { background:var(--slate); color:#fff; font-weight:700; font-size:12.5px; letter-spacing:0.05em; padding:9px 14px; border:none; border-radius:6px; }
        .cell-no { color:var(--slate); font-weight:600; font-size:12px; }
        .cell-cat { color:#4a6580; font-size:11.5px; font-weight:600; }
        .cell-name { font-weight:600; }
        .cell-total { font-weight:700; }
        .subtotal-row td { background:#f2f4f6; border-bottom:1px solid #dfe3e8; padding:10px 12px; font-size:12.5px; font-weight:700; color:var(--navy); }
        .subtotal-row .sub-label { text-align:right; letter-spacing:0.05em; border-radius:0 0 0 6px; }
        .subtotal-row td:last-child { border-radius:0 0 6px 0; }
        .text-right { text-align:right; }
        .text-center { text-align:center; }

        /* 소요시간이 하나도 없으면 열 자체를 접는다 */
        .est-table.no-time col.col-time { width:0; }
        .est-table.no-time th.col-time, .est-table.no-time td.col-time {
            padding:0; border:none; font-size:0; line-height:0; overflow:hidden;
        }

        /* 합계 네이비 밴드 */
        .total-bar { margin-top:16px; background:var(--navy); border-radius:8px; padding:16px 22px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .total-bar .t-label { font-size:12px; font-weight:700; letter-spacing:0.2em; color:#f2f2f3; margin-right:12px; }
        .total-bar .t-sub { font-size:11px; font-weight:600; color:var(--slate-lt); }
        .total-amount { font-size:30px; font-weight:700; color:#fff; white-space:nowrap; }
        .total-amount .currency { font-size:15px; font-weight:600; }

        /* 모바일 반응형 — 의뢰자가 휴대폰으로 열람할 때 */
        @media screen and (max-width: 640px) {
            body { padding:12px 8px; }
            body.public-mode .estimate-wrap { zoom:1; } /* 확대가 좁은 화면을 더 좁게 만들지 않도록 해제 */
            .est-band { flex-direction:column; align-items:flex-start; gap:12px; padding:16px 16px 14px; }
            .est-title { font-size:22px; }
            .band-meta { text-align:left; }
            .info-cols { flex-direction:column; }
            .seller-stamp { width:64px; max-height:64px; }
            /* 좁은 화면에서는 No.·소요시간 열을 숨겨 제품명 공간 확보.
               display:none은 셀이 열 슬롯에서 빠져 뒤 셀들이 한 칸씩 밀리므로,
               폭 0 + 내용 숨김으로 열을 접는다 */
            .est-table col.col-no, .est-table col.col-time { width:0; }
            .est-table th.col-no, .est-table th.col-time,
            .est-table td.col-no, .est-table td.col-time {
                padding:0; border:none; font-size:0; line-height:0; overflow:hidden;
            }
            /* 고정 레이아웃 + 강제 줄바꿈 허용 — 모델명 같은 긴 영문/숫자 문자열이 화면을 밀어내지 않게 */
            .est-table { table-layout:fixed; }
            .est-table td { overflow-wrap:anywhere; }
            .est-table td.text-right, .est-table td.text-center { white-space:normal; }
            .est-table th, .est-table td { padding:7px 5px; font-size:10.5px; }
            .est-table col.col-cat { width:56px; }
            .est-table col.col-price { width:70px; }
            .est-table col.col-qty { width:36px; }
            .est-table col.col-total { width:76px; }
            .total-bar { padding:13px 16px; }
            .total-amount { font-size:21px; }
            .total-amount .currency { font-size:12px; }
        }

        /* 메모 */
        .memo-section { margin-top:16px; padding:14px 18px; background:#f6f7f9; border-radius:10px; border:1px solid var(--line); }
        .memo-section h4 { font-size:11px; font-weight:700; letter-spacing:0.15em; color:var(--slate); margin-bottom:6px; }
        .memo-section p { font-size:12px; color:#3a3f45; white-space:pre-wrap; }

        /* 환불/결제취소 표시 — 문서 기록의 일부라 인쇄에도 포함 */
        .refund-tag { display:inline-block; margin-left:6px; font-size:10px; font-weight:700; color:#b03030; border:1px solid #b03030; border-radius:3px; padding:0 5px; vertical-align:1px; white-space:nowrap; }
        /* 특가/할인 표시 — 배지 + 정가 취소선 + 하단 각주 */
        .deal-tag { display:inline-block; margin-left:6px; font-size:10px; font-weight:700; border-radius:3px; padding:0 5px; vertical-align:1px; white-space:nowrap; }
        .deal-tag.special { color:#c05a12; border:1px solid #c05a12; }
        .deal-tag.discount { color:#2a6bb8; border:1px solid #2a6bb8; }
        .deal-orig { font-size:10px; color:#9aa2ac; text-decoration:line-through; }
        .deal-notes { margin-top:10px; font-size:10.5px; color:#5a6b7d; line-height:1.7; }
        .deal-notes .deal-tag { margin-left:0; margin-right:5px; }
        .refund-detail { margin-top:3px; font-size:10.5px; color:#b03030; }
        .refund-detail div { padding-top:1px; }
        .refund-bar { margin-top:10px; background:#f5eaea; border:1px solid #e3c9c9; border-radius:8px; padding:12px 22px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .refund-bar .t-label { font-size:12px; font-weight:700; letter-spacing:0.2em; color:#b03030; margin-right:12px; }
        .refund-bar .t-sub { font-size:11px; font-weight:600; color:#b98686; }
        .refund-amount { font-size:22px; font-weight:700; color:#b03030; white-space:nowrap; }
        .refund-amount .currency { font-size:13px; font-weight:600; }

        /* 푸터 */
        .est-footer { margin-top:22px; text-align:center; font-size:10.5px; color:#9aa1ab; }
    </style>
</head>
<body class="{{ !empty($publicMode) ? 'public-mode' : '' }}">

@php
    // 환불/결제취소 합계 — 항목별 환불 기록(세트 구성품 환불 포함, 부모에 합산됨)
    $refundTotal = collect($estimate->product_items ?? [])->sum(fn ($i) => (int) ($i['refund_amount'] ?? 0));
@endphp

@if(!empty($publicMode))
{{-- 의뢰자용 하단 결제 바 --}}
<div class="pay-bar no-print">
    @if($estimate->status === 'paid')
        <span class="pay-done">✅ 결제가 완료되었습니다. 감사합니다!{{ $refundTotal > 0 ? ' (일부 환불 '.number_format($refundTotal).'원)' : '' }}</span>
    @elseif($estimate->status === 'cancelled')
        <span class="pay-cancelled">⛔ 결제가 취소된 견적서입니다{{ $refundTotal > 0 ? ' · 환불 '.number_format($refundTotal).'원' : '' }}</span>
    @elseif($estimate->status === 'issued' && $estimate->payapp_payurl)
        {{-- 결제 버튼은 발행완료 단계에서만 노출 --}}
        <a class="pay-btn" href="{{ $estimate->payapp_payurl }}" target="_blank" rel="noopener">💳 {{ number_format($estimate->total_amount) }}원 결제하기</a>
    @endif
    <button class="pay-print" onclick="window.print()">🖨 인쇄</button>
</div>
@else
<div class="no-print-bar no-print">
    <button onclick="window.print()">인쇄</button>
    <button onclick="savePNG()" style="background:#2d8a3e;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;">PNG 저장</button>
    @if($estimate->status !== 'paid')
        <a href="{{ route('estimates.edit', $estimate) }}" style="padding:8px 18px;background:#3b5ea0;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-block;">수정</a>
    @else
        <span style="padding:8px 18px;background:#888;color:#fff;border-radius:6px;font-size:13px;font-weight:700;display:inline-block;cursor:not-allowed;">결제완료 (수정불가)</span>
    @endif
    <span>견적서 #{{ $estimate->display_no }} | {{ $estimate->updated_at->format('Y-m-d H:i') }}</span>
</div>
@php
    // PNG 저장 파일명의 '닉네임(이름)' 부분 — 없으면 '견적서#번호' 폴백, 파일명 금지 문자 제거
    $pngNick = trim($estimate->client_nickname ?? '');
    $pngName = trim($estimate->client_name ?? '');
    $pngWho = $pngNick !== '' && $pngName !== '' ? "{$pngNick}({$pngName})" : ($pngNick !== '' ? $pngNick : ($pngName !== '' ? $pngName : '견적서#'.$estimate->display_no));
    $pngWho = trim(preg_replace('/[\\\\\/:*?"<>|]/u', '', $pngWho));
@endphp
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function savePNG(){
    const el=document.querySelector('.estimate-wrap');
    const pad=100; // 50px * scale2
    html2canvas(el,{scale:2,backgroundColor:'#f2f2f3',useCORS:true}).then(src=>{
        const c=document.createElement('canvas');
        c.width=src.width+pad*2;
        c.height=src.height+pad*2;
        const ctx=c.getContext('2d');
        ctx.fillStyle='#f2f2f3';
        ctx.fillRect(0,0,c.width,c.height);
        ctx.drawImage(src,pad,pad);
        const link=document.createElement('a');
        // 파일명: 'yyyy-mm-dd 닉네임(이름).png' — 캘린더 자동 첨부와 동일 형식, 날짜는 저장한 날
        const t=new Date();
        const ds=`${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`;
        link.download=`${ds} ${@json($pngWho)}.png`;
        link.href=c.toDataURL('image/png');
        link.click();
    });
}
</script>
@endif

<div class="estimate-wrap" style="margin-top:{{ !empty($publicMode) ? '0' : '50px' }};">

    <div class="est-band">
        <div>
            <div class="est-title">견 적 서</div>
            @if(!empty($settings['seller_name']))
                <div class="est-subtitle">{{ $settings['seller_name'] }}</div>
            @endif
        </div>
        <div class="band-meta">
            <div><span class="m-label">작성일</span><span class="m-value">{{ $estimate->created_at->format('Y-m-d') }}</span></div>
            <div><span class="m-label">발행일시</span><span class="m-value">{{ $estimate->updated_at->format('Y-m-d H:i') }}</span></div>
            <div><span class="m-label">견적유효</span><span class="m-value">발행일로부터 {{ $estimate->validity_days }}일간</span></div>
        </div>
    </div>

    @if($estimate->title)
        <div class="doc-head">
            <div class="doc-title">{{ $estimate->title }}</div>
            @if($estimate->client_name)
                <div class="doc-client">의뢰자 · {{ $estimate->client_name }}</div>
            @endif
        </div>
    @endif

    @php
        $bizLine = trim(implode(' · ', array_filter([$settings['seller_biz_type'] ?? null, $settings['seller_biz_item'] ?? null]))) ?: '-';
    @endphp
    <div class="info-cols">
        <div class="info-box">
            <h3>주문 정보</h3>
            <table class="info-table">
                <tr><td class="label">닉네임</td><td class="value">{{ $estimate->client_nickname ?: '-' }}</td></tr>
                <tr><td class="label">이름</td><td class="value">{{ $estimate->client_name ?: '-' }}</td></tr>
                <tr><td class="label">연락처</td><td class="value">{{ $estimate->client_phone ?: '-' }}</td></tr>
            </table>
        </div>
        <div class="info-box wide">
            <h3>판매처</h3>
            <table class="info-table">
                <tr><td class="label">사업자번호</td><td class="value">{{ $settings['seller_biz_no'] ?? '-' }}</td></tr>
                <tr><td class="label">상호명</td><td class="value"><span class="biz-name-wrap">{{ $settings['seller_name'] ?? '-' }}@if(!empty($settings['seller_stamp_path']))<img class="seller-stamp" src="{{ route('seller.stamp') }}?v={{ substr(md5($settings['seller_stamp_path']), 0, 8) }}" alt="">@endif</span></td></tr>
                <tr><td class="label">주소</td><td class="value">{{ $settings['seller_address'] ?? '-' }}</td></tr>
                <tr><td class="label">업태 · 종목</td><td class="value">{{ $bizLine }}</td></tr>
                <tr><td class="label">대표전화</td><td class="value">{{ $settings['seller_phone'] ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    @php
        $items = $estimate->product_items ?? [];
        // 1차(대분류) 카테고리 기준 소계 — 구버전 항목은 저장된 category로 폴백
        $grouped = collect($items)->groupBy(fn ($i) => $i['category_root'] ?? $i['category'] ?? '기타');
        $services = $estimate->service_items ?? [];
        // 소요시간이 전부 비어 있으면 열 자체를 접는다
        $hasTime = collect($items)->contains(fn ($i) => trim((string) ($i['time_required'] ?? '')) !== '');
    @endphp

    <table class="est-table {{ $hasTime ? '' : 'no-time' }}">
        {{-- table-layout:fixed에서 숨김 열 폭을 확실히 제어하기 위해 colgroup으로 폭 지정 --}}
        <colgroup>
            <col class="col-no">
            <col class="col-cat">
            <col class="col-name">
            <col class="col-time">
            <col class="col-price">
            <col class="col-qty">
            <col class="col-total">
        </colgroup>
        <thead>
            <tr>
                <th class="col-no">NO.</th>
                <th class="col-cat">분류</th>
                <th>제품명</th>
                <th class="col-time">소요시간</th>
                <th class="col-price">판매가</th>
                <th class="col-qty">수량</th>
                <th class="col-total">합계</th>
            </tr>
        </thead>
        <tbody>
            @php $globalIdx = 0; @endphp
            @foreach($grouped as $category => $catItems)
                <tr class="grp-gap"><td colspan="7"></td></tr>
                <tr class="cat-header"><td colspan="7">{{ $category ?: '기타' }}</td></tr>
                @foreach($catItems as $item)
                    @php
                        $globalIdx++;
                        // 환불 표시 — 항목 태그 + 세트는 환불된 구성품만 아래 작게 (세트 구성 전체는 비공개 유지)
                        $itemRefunded = ! empty($item['refunded']) || (int) ($item['refund_qty'] ?? 0) > 0 || (int) ($item['refund_amount'] ?? 0) > 0;
                        $refundedParts = collect($item['bundle_items'] ?? [])
                            ->filter(fn ($b) => (int) ($b['refund_qty'] ?? 0) > 0 || (int) ($b['refund_amount'] ?? 0) > 0);
                    @endphp
                    <tr>
                        <td class="cell-no col-no">{{ $globalIdx }}</td>
                        <td class="cell-cat">{{ $item['category'] ?? '' }}</td>
                        <td class="cell-name">@if(!empty($item['mid_category']))<div style="font-size:10px; color:#8a94a0; margin-bottom:1px;">{{ $item['mid_category'] }}</div>@endif{{ $item['name'] }}@if($itemRefunded)<span class="refund-tag">환불{{ (int) ($item['refund_qty'] ?? 0) > 0 ? ' '.$item['refund_qty'].'개' : '' }}{{ (int) ($item['refund_amount'] ?? 0) > 0 ? ' '.number_format($item['refund_amount']).'원' : '' }}</span>@endif @if(($item['deal_type'] ?? null) === 'special')<span class="deal-tag special">특가</span>@elseif(($item['deal_type'] ?? null) === 'discount')<span class="deal-tag discount">할인{{ !empty($item['discount_rate']) ? ' '.rtrim(rtrim(number_format($item['discount_rate'], 1), '0'), '.').'%' : '' }}</span>@endif
                            @if($refundedParts->isNotEmpty())
                                <div class="refund-detail">
                                    @foreach($refundedParts as $b)
                                        <div>└ {{ $b['name'] ?? '' }} 환불 {{ (int) ($b['refund_qty'] ?? 0) > 0 ? $b['refund_qty'].'개' : '' }}{{ (int) ($b['refund_amount'] ?? 0) > 0 ? ' · '.number_format($b['refund_amount']).'원' : '' }}</div>
                                    @endforeach
                                </div>
                            @endif
                            @if(!empty($item['remark']))<div style="font-size:10.5px; color:#5a6b7d; margin-top:2px;">{{ $item['remark'] }}</div>@endif
                        </td>
                        <td class="text-center col-time">{{ $item['time_required'] ?? '' }}</td>
                        <td class="text-right">@if(!empty($item['deal_type']) && (int) ($item['original_price'] ?? 0) > (int) $item['sale_price'])<div class="deal-orig">{{ number_format($item['original_price']) }}원</div>@endif{{ number_format($item['sale_price']) }}원</td>
                        <td class="text-center">{{ $item['qty'] }}</td>
                        <td class="text-right cell-total">{{ number_format($item['subtotal']) }}원</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td colspan="6" class="sub-label">{{ $category ?: '기타' }} 소계</td>
                    <td class="text-right">{{ number_format($catItems->sum('subtotal')) }}원</td>
                </tr>
            @endforeach

            @if(count($services))
                <tr class="grp-gap"><td colspan="7"></td></tr>
                <tr class="cat-header"><td colspan="7">서비스</td></tr>
                @foreach($services as $svc)
                    @php $globalIdx++; @endphp
                    <tr>
                        <td class="cell-no col-no">{{ $globalIdx }}</td>
                        <td class="cell-cat">서비스</td>
                        <td class="cell-name">{{ $svc['name'] }}</td>
                        <td class="col-time"></td>
                        <td class="text-right">{{ number_format($svc['amount']) }}원</td>
                        <td class="text-center">1</td>
                        <td class="text-right cell-total">{{ number_format($svc['amount']) }}원</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td colspan="6" class="sub-label">서비스 소계</td>
                    <td class="text-right">{{ number_format($estimate->service_total) }}원</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="total-bar">
        <div>
            <span class="t-label">총 견적 금액</span>
            <span class="t-sub">부가세 포함 · 총 {{ count($items) + count($services) }}개 항목 (수량 미포함)</span>
        </div>
        <div class="total-amount">{{ number_format($estimate->total_amount) }}<span class="currency"> 원</span></div>
    </div>

    @if($refundTotal > 0)
        {{-- 부분환불/결제취소 반영 — 총 견적 금액은 그대로 두고 환불 합계를 별도 표기 --}}
        <div class="refund-bar">
            <div>
                <span class="t-label">환불 합계</span>
                <span class="t-sub">환불 반영 후 {{ number_format(max(0, (int) $estimate->total_amount - $refundTotal)) }}원</span>
            </div>
            <div class="refund-amount">−{{ number_format($refundTotal) }}<span class="currency"> 원</span></div>
        </div>
    @endif

    @php
        $hasSpecial = collect($items)->contains(fn ($i) => ($i['deal_type'] ?? null) === 'special');
        $hasDiscount = collect($items)->contains(fn ($i) => ($i['deal_type'] ?? null) === 'discount');
    @endphp
    @if($hasSpecial || $hasDiscount)
        {{-- 특가/할인 각주 — 해당 표시가 있는 견적서에만 자동 노출 --}}
        <div class="deal-notes">
            @if($hasSpecial)<div><span class="deal-tag special">특가</span>닥터고블린컴퍼니 세팅 진행 시 단독 특가로 납품되는 금액입니다.</div>@endif
            @if($hasDiscount)<div><span class="deal-tag discount">할인</span>재방문 세팅비 할인 또는 이벤트 할인이 적용된 금액입니다.</div>@endif
        </div>
    @endif

    @if($estimate->memo)
        <div class="memo-section">
            <h4>메모</h4>
            <p>{{ $estimate->memo }}</p>
        </div>
    @endif

    <div class="est-footer">
        {{ collect([$settings['seller_name'] ?? null, $settings['seller_phone'] ?? null, $settings['seller_address'] ?? null])->filter()->implode(' · ') }}
    </div>

</div>
</body>
</html>
