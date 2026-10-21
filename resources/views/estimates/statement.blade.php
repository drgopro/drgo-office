<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>거래명세서 #{{ $estimate->display_no }}</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; background:#f2f2f3; color:#1d1f24; }
        .no-print-bar { position:fixed; top:0; left:0; right:0; background:#222; padding:10px 20px; display:flex; gap:10px; align-items:center; z-index:100; }
        .no-print-bar button { background:#c8b08a; color:#1a1207; border:none; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; }
        .no-print-bar span { color:#aaa; font-size:12px; }
        .stmt-wrap { max-width:860px; margin:70px auto 40px; background:#fff; padding:42px 46px 48px; box-shadow:0 2px 14px rgba(0,0,0,0.1); }
        h1 { text-align:center; font-size:26px; letter-spacing:0.55em; text-indent:0.55em; font-weight:800; margin-bottom:6px; }
        .stmt-sub { text-align:center; font-size:12px; color:#6b7684; margin-bottom:22px; }
        .party-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px; }
        .party { border:1.5px solid #39485c; }
        .party-head { background:#eef1f5; font-size:12.5px; font-weight:800; letter-spacing:0.3em; text-indent:0.3em; text-align:center; padding:7px 0; border-bottom:1px solid #c6cdd6; }
        .party table { width:100%; border-collapse:collapse; }
        .party td { font-size:12px; padding:6px 9px; border-bottom:1px solid #e2e6eb; vertical-align:middle; }
        .party tr:last-child td { border-bottom:none; }
        .party td.lbl { width:74px; background:#f7f8fa; color:#55607a; font-weight:700; text-align:center; border-right:1px solid #e2e6eb; white-space:nowrap; }
        .biz-name-wrap { position:relative; display:inline-block; font-weight:700; }
        .seller-stamp { position:absolute; left:100%; top:50%; transform:translate(-30%,-50%); width:58px; max-height:58px; object-fit:contain; opacity:0.88; pointer-events:none; }
        .items { width:100%; border-collapse:collapse; margin-bottom:0; border:1.5px solid #39485c; }
        .items th { background:#eef1f5; font-size:11.5px; font-weight:800; color:#39485c; padding:8px 6px; border:1px solid #c6cdd6; }
        .items td { font-size:12px; padding:7px 8px; border:1px solid #dfe3e9; }
        .items td.num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .items td.ctr { text-align:center; white-space:nowrap; }
        .items .minus td { color:#b03030; }
        .items tfoot td { font-weight:800; background:#f7f8fa; border-top:1.5px solid #39485c; }
        .grand-bar { display:flex; justify-content:space-between; align-items:center; border:1.5px solid #39485c; border-top:none; background:#39485c; color:#fff; padding:10px 16px; margin-bottom:18px; }
        .grand-bar .lbl { font-size:12.5px; font-weight:800; letter-spacing:0.25em; }
        .grand-bar .val { font-size:19px; font-weight:800; font-variant-numeric:tabular-nums; }
        .grand-bar .val small { font-size:12px; font-weight:600; }
        .stmt-note { font-size:11px; color:#6b7684; line-height:1.7; }
        .receiver { margin-top:26px; display:flex; justify-content:flex-end; align-items:center; gap:10px; font-size:12.5px; }
        .receiver .sign { display:inline-block; width:150px; border-bottom:1px solid #8a94a0; height:22px; }
        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            .stmt-wrap { margin:0 auto; box-shadow:none; padding:10px 6px; max-width:100%; }
            @page { margin:14mm 12mm; }
        }
    </style>
</head>
<body>
<div class="no-print-bar no-print">
    <button onclick="window.print()">인쇄</button>
    <span>인쇄 대화상자에서 'PDF로 저장'을 선택하면 파일로 저장됩니다.</span>
</div>

@php
    $money = fn ($v) => number_format((int) $v);
    $supplyTotal = $lines->sum('supply');
    $vatTotal = $lines->sum('vat');
    $grandTotal = $lines->sum('amount');
@endphp

<div class="stmt-wrap">
    <h1>거래명세서</h1>
    <div class="stmt-sub">
        거래일자: {{ $txDate->format('Y년 m월 d일') }} · 문서번호: 견적서 #{{ $estimate->display_no }}
    </div>

    <div class="party-grid">
        <div class="party">
            <div class="party-head">공급자</div>
            <table>
                <tr><td class="lbl">등록번호</td><td>{{ $settings['seller_biz_no'] ?? '-' }}</td></tr>
                <tr><td class="lbl">상호</td><td><span class="biz-name-wrap">{{ $settings['seller_name'] ?? '-' }}@if(!empty($settings['seller_stamp_path']))<img class="seller-stamp" src="{{ route('seller.stamp') }}?v={{ substr(md5($settings['seller_stamp_path']), 0, 8) }}" alt="">@endif</span></td></tr>
                <tr><td class="lbl">주소</td><td>{{ $settings['seller_address'] ?? '-' }}</td></tr>
                <tr><td class="lbl">업태 / 종목</td><td>{{ collect([$settings['seller_biz_type'] ?? null, $settings['seller_biz_item'] ?? null])->filter()->implode(' / ') ?: '-' }}</td></tr>
                <tr><td class="lbl">연락처</td><td>{{ $settings['seller_phone'] ?? '-' }}</td></tr>
            </table>
        </div>
        <div class="party">
            <div class="party-head">공급받는자</div>
            <table>
                <tr><td class="lbl">상호 / 성명</td><td style="font-weight:700;">{{ trim(collect([$estimate->client_nickname, $estimate->client_name])->filter()->unique()->implode(' · ')) ?: '-' }}</td></tr>
                <tr><td class="lbl">연락처</td><td>{{ $estimate->client_phone ?: '-' }}</td></tr>
                <tr><td class="lbl">주소</td><td>{{ trim(($estimate->ship_address ?? '').' '.($estimate->ship_address_detail ?? '')) ?: '-' }}</td></tr>
                <tr><td class="lbl">비고</td><td>{{ $estimate->title ?: '-' }}</td></tr>
            </table>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:34px;">번호</th>
                <th>품목</th>
                <th style="width:50px;">수량</th>
                <th style="width:96px;">단가</th>
                <th style="width:100px;">공급가액</th>
                <th style="width:88px;">세액</th>
                <th style="width:110px;">비고</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $idx => $line)
                <tr @class(['minus' => $line['amount'] < 0])>
                    <td class="ctr">{{ $idx + 1 }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td class="ctr">{{ $line['qty'] }}</td>
                    <td class="num">{{ $money($line['unit']) }}</td>
                    <td class="num">{{ $money($line['supply']) }}</td>
                    <td class="num">{{ $money($line['vat']) }}</td>
                    <td style="font-size:11px; color:#6b7684;">{{ $line['remark'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ctr" style="padding:18px; color:#8a94a0;">품목이 없습니다.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="ctr">합 계</td>
                <td class="num">{{ $money($supplyTotal) }}</td>
                <td class="num">{{ $money($vatTotal) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <div class="grand-bar">
        <span class="lbl">합계금액 (부가세 포함)</span>
        <span class="val">{{ $money($grandTotal) }}<small> 원</small></span>
    </div>

    <div class="stmt-note">
        · 단가·합계금액은 부가가치세가 포함된 금액이며, 공급가액과 세액은 포함가 기준(÷1.1)으로 분리 표기했습니다.<br>
        · 본 명세서는 견적서 #{{ $estimate->display_no }}{{ $estimate->estimate_no ? ' ('.$estimate->estimate_no.')' : '' }} 거래 내역을 기준으로 발행되었습니다.
    </div>

    <div class="receiver no-print-keep">
        인수자: <span class="sign"></span> (서명)
    </div>
</div>
</body>
</html>
