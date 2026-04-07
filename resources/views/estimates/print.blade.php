<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>견적서 #{{ $estimate->id }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Malgun Gothic','Apple SD Gothic Neo',-apple-system,sans-serif; background:#fff; color:#222; font-size:13px; }
        @media print {
            body { padding:0; }
            .no-print { display:none !important; }
            @page { margin:15mm; }
        }
        @media screen {
            body { padding:30px; max-width:760px; margin:0 auto; }
        }

        .no-print-bar { position:fixed; top:0; left:0; right:0; background:#222; padding:10px 20px; display:flex; gap:10px; align-items:center; z-index:100; }
        .no-print-bar button { background:#c8b08a; color:#1a1207; border:none; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; }
        .no-print-bar span { color:#aaa; font-size:12px; }

        .estimate-wrap { background:#fff; }

        /* 헤더 */
        .est-header { text-align:center; margin-bottom:28px; padding-bottom:16px; border-bottom:3px double #333; }
        .est-title { font-size:26px; font-weight:800; letter-spacing:0.1em; color:#111; }
        .est-subtitle { font-size:12px; color:#888; margin-top:6px; }

        /* 상단 정보 2열 */
        .info-cols { display:flex; gap:30px; margin-bottom:24px; }
        .info-col { flex:1; }
        .info-col h3 { font-size:13px; font-weight:700; color:#333; margin-bottom:12px; padding-bottom:6px; border-bottom:2px solid #333; }
        .info-table { width:100%; border-collapse:collapse; }
        .info-table td { padding:5px 0; font-size:12px; vertical-align:top; }
        .info-table .label { color:#666; width:65px; font-weight:600; }
        .info-table .value { color:#222; }
        .info-table .value-box { border:1px solid #ddd; padding:4px 10px; border-radius:3px; display:inline-block; min-width:140px; background:#fafafa; }

        /* 유효기간 */
        .validity-bar { background:#f8f6f2; border:1px solid #e8e0d4; border-radius:6px; padding:8px 14px; margin-bottom:20px; font-size:11px; color:#666; display:flex; justify-content:space-between; }

        /* 제품 테이블 */
        .est-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
        .est-table th { background:#f0ede8; border:1px solid #d8d0c4; padding:7px 8px; font-size:10px; font-weight:700; text-align:center; color:#555; letter-spacing:0.05em; }
        .est-table td { border:1px solid #ddd; padding:7px 8px; font-size:11px; }
        .est-table .cat-header td { background:#f8f6f2; font-weight:700; font-size:11px; color:#555; border-left:3px solid #c8b08a; }
        .est-table .subtotal-row td { background:#f5f3ef; font-weight:700; font-size:11px; text-align:right; }
        .text-right { text-align:right; }
        .text-center { text-align:center; }

        /* 합계 */
        .total-wrap { margin-top:20px; text-align:right; }
        .total-box { display:inline-block; text-align:right; }
        .total-amount { font-size:32px; font-weight:800; color:#111; }
        .total-amount .currency { font-size:18px; }
        .total-label { font-size:13px; color:#666; margin-right:12px; }
        .total-note { font-size:11px; color:#c44; margin-top:4px; }
        .total-items { font-size:11px; color:#888; margin-top:2px; }

        /* 메모 */
        .memo-section { margin-top:20px; padding:12px 14px; background:#f8f6f2; border-radius:6px; border:1px solid #e8e0d4; }
        .memo-section h4 { font-size:11px; color:#888; margin-bottom:4px; }
        .memo-section p { font-size:12px; color:#444; white-space:pre-wrap; }

        /* 푸터 */
        .est-footer { margin-top:30px; padding-top:16px; border-top:1px solid #ddd; text-align:center; font-size:10px; color:#aaa; }
    </style>
</head>
<body>

<div class="no-print-bar no-print">
    <button onclick="window.print()">인쇄</button>
    <span>견적서 #{{ $estimate->id }} | {{ $estimate->updated_at->format('Y-m-d H:i') }}</span>
</div>

<div class="estimate-wrap" style="margin-top:50px;">

    <div class="est-header">
        <div class="est-title">견 적 서</div>
        <div class="est-subtitle">ESTIMATE</div>
    </div>

    <div class="info-cols">
        <div class="info-col">
            <h3>주문 정보</h3>
            <table class="info-table">
                <tr><td class="label">닉네임</td><td class="value"><span class="value-box">{{ $estimate->client_nickname ?: '-' }}</span></td></tr>
                <tr><td class="label">이 름</td><td class="value"><span class="value-box">{{ $estimate->client_name ?: '-' }}</span></td></tr>
                <tr><td class="label">연락처</td><td class="value"><span class="value-box">{{ $estimate->client_phone ?: '-' }}</span></td></tr>
            </table>
        </div>
        <div class="info-col">
            <h3>판매처</h3>
            <table class="info-table">
                <tr><td class="label">사업자번호</td><td class="value">{{ $settings['seller_biz_no'] ?? '-' }}</td></tr>
                <tr><td class="label">상호명</td><td class="value">{{ $settings['seller_name'] ?? '-' }}</td></tr>
                <tr><td class="label">주소</td><td class="value">{{ $settings['seller_address'] ?? '-' }}</td></tr>
                <tr><td class="label">업태</td><td class="value">{{ $settings['seller_biz_type'] ?? '-' }}</td></tr>
                <tr><td class="label">종목</td><td class="value">{{ $settings['seller_biz_item'] ?? '-' }}</td></tr>
                <tr><td class="label">대표전화</td><td class="value">{{ $settings['seller_phone'] ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="validity-bar">
        <span>• 견적유효 : 발행일로부터 {{ $estimate->validity_days }}일간</span>
        <span>작성일 : {{ $estimate->created_at->format('Y-m-d') }} | 발행일시 : {{ $estimate->updated_at->format('Y-m-d H:i') }}</span>
    </div>

    @php
        $items = $estimate->product_items ?? [];
        $grouped = collect($items)->groupBy('category');
        $services = $estimate->service_items ?? [];
    @endphp

    <table class="est-table">
        <thead>
            <tr>
                <th style="width:35px;">No.</th>
                <th style="width:65px;">분류</th>
                <th>제품명</th>
                <th style="width:65px;">소요시간</th>
                <th style="width:85px;">판매가</th>
                <th style="width:40px;">수량</th>
                <th style="width:95px;">합계</th>
            </tr>
        </thead>
        <tbody>
            @php $globalIdx = 0; @endphp
            @foreach($grouped as $category => $catItems)
                <tr class="cat-header"><td colspan="7">{{ $category ?: '기타' }}</td></tr>
                @foreach($catItems as $item)
                    @php $globalIdx++; @endphp
                    <tr>
                        <td class="text-center">{{ $globalIdx }}</td>
                        <td style="font-size:10px; color:#666;">{{ $item['category'] ?? '' }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-center">{{ $item['time_required'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($item['sale_price']) }}원</td>
                        <td class="text-center">{{ $item['qty'] }}</td>
                        <td class="text-right" style="font-weight:600;">{{ number_format($item['subtotal']) }}원</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td colspan="6" style="text-align:right;">{{ $category ?: '기타' }} 소계 :</td>
                    <td class="text-right">{{ number_format($catItems->sum('subtotal')) }}원</td>
                </tr>
            @endforeach

            @if(count($services))
                <tr class="cat-header"><td colspan="7">서비스</td></tr>
                @foreach($services as $svc)
                    @php $globalIdx++; @endphp
                    <tr>
                        <td class="text-center">{{ $globalIdx }}</td>
                        <td style="font-size:10px; color:#666;">서비스</td>
                        <td>{{ $svc['name'] }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format($svc['amount']) }}원</td>
                        <td class="text-center">1</td>
                        <td class="text-right" style="font-weight:600;">{{ number_format($svc['amount']) }}원</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td colspan="6" style="text-align:right;">서비스 소계 :</td>
                    <td class="text-right">{{ number_format($estimate->service_total) }}원</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="total-wrap">
        <div class="total-box">
            <span class="total-label">총 견적 금액</span>
            <span class="total-amount">{{ number_format($estimate->total_amount) }}<span class="currency">원</span></span>
            <div class="total-note">(부가세 포함)</div>
            <div class="total-items">총 항목 수 : {{ count($items) + count($services) }}개 (수량 미포함)</div>
        </div>
    </div>

    @if($estimate->memo)
        <div class="memo-section">
            <h4>메모</h4>
            <p>{{ $estimate->memo }}</p>
        </div>
    @endif

    <div class="est-footer">
        {{ $settings['seller_name'] ?? '' }} | {{ $settings['seller_phone'] ?? '' }} | {{ $settings['seller_address'] ?? '' }}
    </div>

</div>
</body>
</html>
