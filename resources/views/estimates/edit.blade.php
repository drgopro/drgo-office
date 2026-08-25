<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
@include('partials.ajax-fetch-header')
    {{-- 본 앱과 동일하게 라이트 모드 고정 (구버전 테마 키 정리) --}}
    <script>(function(){try{localStorage.removeItem('drgo_theme');}catch(e){}document.documentElement.setAttribute('data-theme','light');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $estimate->estimate_no ? "견적서 #".$estimate->estimate_no : ($estimate->status === "temp" ? "새 견적서" : "견적서 #".$estimate->display_no) }} - 닥터고블린 오피스</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        :root, [data-theme="dark"] { --bg:#111; --surface:#1c1c1c; --surface2:#272727; --border:#3a3a3a; --text:#f0ebe2; --text-muted:#a09890; --accent:#d4bc96; --red:#d48888; --green:#88d488; --blue:#8ab4c8; }
        [data-theme="light"] { --bg:#eef0f3; --surface:#fff; --surface2:#f2f4f7; --border:#dfe3e9; --text:#1d1f24; --text-muted:#6b7684; --accent:#2e6cb5; --red:#c03838; --green:#248a38; --blue:#2e6a9a; --navy:#1d2d3d; --slate:#416180; --slate-lt:#cfe0f0; }
        :root, [data-theme="dark"] { --navy:#1d2d3d; --slate:#416180; --slate-lt:#cfe0f0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:"Pretendard Variable",Pretendard,-apple-system,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; display:flex; height:100vh; overflow:hidden; }
        input, button, textarea, select { font-family:inherit; }

        /* 좌측 — 제품 리스트 */
        .panel-left { width:420px; border-right:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0; }
        .panel-left-header { padding:14px 16px; border-bottom:1px solid var(--border); }
        .panel-left-header h3 { font-size:14px; font-weight:700; margin-bottom:10px; }
        .cat-tabs { display:flex; flex-direction:column; gap:5px; margin-bottom:9px; }
        .cat-tab-row { display:flex; flex-wrap:wrap; gap:5px; align-items:center; }
        .cat-tab-arrow { color:var(--text-muted); font-size:11px; margin-right:1px; }
        .cat-tab { padding:4px 11px; font-size:11px; border:1px solid #b9cbe0; border-radius:7px; background:var(--surface); color:var(--accent); font-weight:600; cursor:pointer; }
        .cat-tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }
        .search-input { width:100%; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:14px; outline:none; }
        .search-input:focus { border-color:var(--accent); }
        .product-list { flex:1; overflow-y:auto; padding:4px 8px; }
        .product-item { display:flex; justify-content:space-between; align-items:center; padding:10px 8px; border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.1s; font-size:15px; }
        .product-item:hover { background:var(--surface2); }
        .product-item .pi-name { flex:1; font-weight:600; }
        .product-item .pi-cat { font-size:12px; color:var(--text-muted); margin-top:3px; font-weight:400; }
        .product-item .pi-price { font-size:14.5px; color:var(--navy); font-weight:700; white-space:nowrap; margin-left:10px; }
        .product-item .pi-stock { font-size:11.5px; margin-left:8px; }
        .pi-stock.low { color:var(--red); }
        .pi-stock.ok { color:var(--text-muted); }

        /* 우측 — 견적서 */
        .panel-right { flex:1; display:flex; flex-direction:column; overflow:hidden; }

        /* 최우측 — 프리셋 패널 (클릭해서 품목 담기). 마크업 위치와 무관하게 order로 최우측 고정 */
        .panel-left { order:0; }
        #rzLeft { order:1; }
        .panel-right { order:2; }
        #rzRight { order:3; }
        .panel-presets { order:4; width:210px; border-left:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0; background:var(--bg); }
        /* 패널 폭 조절 리사이저 — 드래그로 제품 리스트/프리셋 폭 변경 (localStorage에 기억) */
        .panel-resizer { width:6px; margin:0 -3px; cursor:col-resize; flex-shrink:0; z-index:20; background:transparent; transition:background 0.12s; }
        .panel-resizer:hover, .panel-resizer.active { background:var(--accent); opacity:0.45; }
        .panel-presets-header { padding:14px 14px 10px; border-bottom:1px solid var(--border); }
        .panel-presets-header h3 { font-size:14px; font-weight:700; }
        .preset-list { flex:1; overflow-y:auto; padding:8px; }
        .preset-item { padding:11px 13px; background:var(--surface); border:1px solid var(--border); border-radius:9px; margin-bottom:7px; cursor:pointer; transition:border-color 0.1s, box-shadow 0.1s; }
        .preset-item:hover { border-color:var(--accent); box-shadow:0 1px 5px rgba(29,45,61,0.08); }
        .preset-name { font-size:14px; font-weight:700; line-height:1.4; word-break:keep-all; }
        .preset-total { font-size:13.5px; color:var(--accent); font-weight:700; margin-top:4px; }
        .panel-right-header { background:var(--surface); padding:13px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .panel-right-header h2 { font-size:18px; font-weight:800; color:var(--navy); }
        /* 헤더 제목 인라인 편집 — 텍스트 클릭으로 수정 */
        #estTitleNo:hover { background:var(--surface2); box-shadow:0 0 0 1px var(--border); }
        #estTitleInput { font-size:18px; font-weight:800; color:var(--navy); background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:2px 6px; margin-left:-6px; outline:none; flex:1; min-width:160px; max-width:420px; font-family:inherit; }
        .est-status { font-size:11px; padding:3px 10px; border-radius:4px; font-weight:600; }
        .est-body { flex:1; overflow-y:auto; padding:20px; background:var(--bg); }

        /* 주문정보 */
        .client-section { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
        .client-section h4 { font-size:13px; color:var(--slate); font-weight:700; margin-bottom:12px; letter-spacing:0.08em; }
        .client-row { display:flex; gap:10px; }
        .client-row .field { flex:1; }
        .field label { font-size:12px; color:var(--text-muted); display:block; margin-bottom:4px; }
        .field input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:14px; outline:none; }
        .field input:focus { border-color:var(--accent); }
        .client-search-wrap { position:relative; }
        .client-results { position:absolute; top:100%; left:0; right:0; background:var(--surface); border:1px solid var(--border); border-radius:8px; max-height:150px; overflow-y:auto; z-index:10; display:none; }
        .client-results.show { display:block; }
        .client-result-item { padding:8px 12px; font-size:12px; cursor:pointer; }
        .client-result-item:hover { background:var(--surface2); }

        /* 장바구니 테이블 */
        .cart-section { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
        .cart-section h4 { font-size:13px; color:var(--slate); font-weight:700; margin-bottom:12px; letter-spacing:0.08em; }
        .cart-table { width:100%; border-collapse:separate; border-spacing:0; }
        .cart-table th { font-size:12.5px; color:var(--slate); font-weight:700; letter-spacing:0.06em; text-align:left; padding:8px 6px 10px; border-bottom:1px solid #ccd4dd; white-space:nowrap; }
        .cart-table td { font-size:14.5px; padding:9px 6px; border-bottom:1px solid var(--border); }
        .cart-table tr:last-child td { border-bottom:none; }
        /* 대분류 밴드 — 슬레이트 배경 + 우측 소계 */
        .cart-cat-header td { background:var(--slate); color:#fff; font-size:13.5px; font-weight:700; padding:8px 10px; border-bottom:none; }
        .cart-cat-header td:first-child { border-radius:6px 0 0 6px; }
        .cart-cat-header td:last-child { border-radius:0 6px 6px 0; }
        .cart-cat-header .drag-handle { color:rgba(255,255,255,0.65); }
        .cart-row-num { color:var(--accent); font-weight:600; font-size:13.5px; }
        /* 주문/배송 모드 — 주문완료 표시(제품명 텍스트에만 옅은 녹색 배경) + 토글 버튼 */
        .name-ordered { background:#e4f2e4; border-radius:4px; padding:2px 7px; margin-left:-7px; }
        .order-mode-label { font-size:12.5px; font-weight:700; color:var(--slate); background:var(--slate-lt); padding:3px 11px; border-radius:6px; margin-left:10px; }
        .btn-order { padding:3px 11px; font-size:12.5px; font-weight:600; border-radius:6px; border:1px solid #7cb87c; background:var(--surface); color:var(--green); cursor:pointer; white-space:nowrap; }
        .btn-order:hover { background:#eef7ee; }
        .btn-order.cancel { border-color:#c9d2dc; color:var(--text-muted); }
        .btn-order.cancel:hover { border-color:var(--red); color:var(--red); background:var(--surface); }
        #orderModeBtn.mode-on { background:var(--navy); color:#fff; border-color:var(--navy); }
        /* 분류 소계 — 각 대분류 블록 최하단의 옅은 밴드 */
        .cart-subtotal td { background:#f2f4f6; font-size:14px; font-weight:700; color:var(--navy); text-align:right; padding:9px 10px; border-bottom:none; }
        .cart-subtotal td:first-child { border-radius:0 0 0 6px; }
        .cart-subtotal td:last-child { border-radius:0 0 6px 0; }
        .time-input { width:60px; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:4px 6px; color:var(--text); font-size:12.5px; text-align:center; outline:none; }
        /* 드래그 정렬 — 대분류/항목 순서 변경 */
        #btnSortMode.on, #btnSortMode.on:hover { background:var(--navy); color:#fff; border:1px solid var(--navy); }
        /* 세트 구성품 접기/펼치기 (빌더 전용) */
        .bundle-toggle { background:none; border:1px solid #9db8d4; color:var(--accent); border-radius:4px; font-size:10.5px; padding:0 6px; cursor:pointer; margin-left:5px; vertical-align:middle; }
        .bundle-toggle:hover { background:var(--surface2); }
        .bundle-sub td { background:var(--surface2); font-size:12px; color:var(--text-muted); padding:5px 6px; }
        .bundle-sub .bs-qty { color:var(--accent); font-weight:600; }
        .drag-handle { cursor:grab; color:var(--text-muted); user-select:none; padding:0 3px; font-size:12px; display:inline-block; vertical-align:middle; }
        .drag-handle:active { cursor:grabbing; }
        /* 핸들·번호·금액 줄바꿈 방지 — 가로 정렬 유지 */
        .cart-table td { vertical-align:middle; }
        .cart-table td:first-child, .cart-table td.text-right, .cart-cat-header td { white-space:nowrap; }
        .cat-rename-btn { background:none; border:1px solid rgba(255,255,255,0.4); border-radius:5px; color:rgba(255,255,255,0.85); font-size:11px; padding:1px 7px; cursor:pointer; margin-left:8px; }
        .cat-rename-btn:hover { border-color:#fff; color:#fff; }
        tr.drop-hint td { border-top:2px solid var(--accent) !important; }
        tr.drag-preview { pointer-events:none; }
        tr.drag-preview td { opacity:0.8; background:rgba(46,108,181,0.10) !important; }
        tr.drag-src td { opacity:0.35; }
        .qty-ctrl { display:flex; align-items:center; gap:3px; }
        .qty-ctrl button { width:22px; height:22px; border:1px solid #9db8d4; background:var(--surface); color:var(--accent); border-radius:50%; cursor:pointer; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .qty-ctrl button:hover { background:var(--surface2); }
        .qty-ctrl input { width:34px; text-align:center; background:var(--surface); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:13.5px; padding:3px 2px; outline:none; }
        .btn-remove { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px; }
        .btn-remove:hover { color:var(--red); }
        .text-right { text-align:right; }

        /* 서비스 항목 */
        .svc-row { display:flex; gap:6px; margin-bottom:6px; align-items:center; }
        .svc-row input { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 8px; color:var(--text); font-size:13.5px; outline:none; }
        .svc-row input:focus { border-color:var(--accent); }
        .btn-add-svc { background:none; border:1px dashed var(--border); color:var(--text-muted); font-size:12.5px; padding:5px 10px; border-radius:6px; cursor:pointer; width:100%; }
        .btn-add-svc:hover { border-color:var(--accent); color:var(--accent); }

        /* 합계 */
        .total-section { background:var(--surface); border:1px solid #e3e6eb; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
        .total-row { display:flex; justify-content:space-between; font-size:15px; margin-bottom:6px; }
        .total-row.grand { font-size:22px; font-weight:800; color:var(--navy); margin-top:8px; padding-top:10px; border-top:1px solid #ccd4dd; }
        .total-items { font-size:13px; color:var(--text-muted); }

        /* 하단 액션 */
        .panel-right-footer { background:var(--surface); padding:12px 20px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
        /* 버튼 위계: 저장(주요·채움) > 견적서 출력(보조·외곽선) > 삭제(위험·외곽선) > 로그(중립) */
        .btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:background 0.12s, border-color 0.12s, color 0.12s, filter 0.12s; }
        .btn-save { background:var(--navy); border-color:var(--navy); color:#fff; font-weight:700; }
        .btn-save:hover { filter:brightness(1.25); }
        .btn-print { background:var(--surface); border-color:#c1cbd7; color:#33404e; }
        .btn-print:hover { border-color:var(--slate); color:var(--slate); }
        .btn-delete { background:var(--surface); border-color:#d9dee5; color:var(--red); }
        .btn-delete:hover { border-color:var(--red); background:rgba(192,56,56,0.07); }
        .btn-ghost { background:var(--surface); border-color:#d9dee5; color:var(--text-muted); }
        .btn-ghost:hover { border-color:var(--slate); color:var(--slate); }
        .save-indicator { font-size:11px; color:var(--text-muted); align-self:center; }
        [data-theme="light"] .cat-tab.active { color:#fff; }
    </style>
</head>
<body>
<div class="panel-left">
    <div class="panel-left-header">
        <h3>제품 리스트</h3>
        <div class="cat-tabs" id="catTabs">
            <div class="cat-tab-row"><button class="cat-tab active" onclick="setCatPath(0,null)">전체</button></div>
        </div>
        <div style="display:flex; gap:6px;">
            <input class="search-input" id="prodSearch" placeholder="제품명/SKU 검색" oninput="filterProducts()" style="flex:1; width:auto; min-width:0;">
            <select id="prodSort" onchange="onProdSortChange()" title="제품 리스트 정렬" style="background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:8px 6px; color:var(--text); font-size:12px; outline:none; cursor:pointer; max-width:120px;">
                <option value="name_asc">제품명 ㄱ→ㅎ</option>
                <option value="name_desc">제품명 ㅎ→ㄱ</option>
                <option value="price_asc">가격 낮은순</option>
                <option value="price_desc">가격 높은순</option>
                <option value="date_desc">등록일 최신순</option>
                <option value="date_asc">등록일 오래된순</option>
            </select>
        </div>
    </div>
    <div class="product-list" id="productList"></div>
</div>

<div class="panel-resizer" id="rzLeft" title="드래그해서 제품 리스트 폭 조절"></div>
<div class="panel-resizer" id="rzRight" title="드래그해서 프리셋 패널 폭 조절"></div>

<!-- 프리셋 패널 (우측) — 클릭하면 품목이 견적서에 담김, 여러 개 눌러 조립 -->
<div class="panel-presets">
    <div class="panel-presets-header">
        <h3>프리셋</h3>
        <label style="display:flex; align-items:center; gap:5px; font-size:11.5px; color:var(--text-muted); cursor:pointer; margin-top:8px;">
            <input type="checkbox" id="presetReplaceMode" style="width:13px; height:13px;"> 불러올 때 기존 품목 비우기
        </label>
    </div>
    <div class="preset-list" id="presetPanelList"><div style="padding:16px; text-align:center; color:var(--text-muted); font-size:12px;">로딩 중...</div></div>
</div>

<!-- 옵션 선택 팝업 (옵션 그룹 상품) -->
<div id="optionPickerOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:300; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this) closeOptionPicker()">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; width:min(380px, 100%); max-height:70vh; display:flex; flex-direction:column;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border);">
            <div style="font-size:14px; font-weight:700;" id="optPickerTitle">옵션 선택</div>
            <button onclick="closeOptionPicker()" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer;">×</button>
        </div>
        <div id="optPickerList" style="overflow-y:auto; padding:8px;"></div>
    </div>
</div>

<div class="panel-right">
    <div class="panel-right-header">
        <h2 id="estTitleNo" onclick="editEstTitle()" style="cursor:text; border-radius:6px; padding:2px 6px; margin-left:-6px;" title="클릭해서 견적서 제목 수정 (출력물 상단에 표시)">{{ $estimate->title ?: ($estimate->status === "temp" && ! $estimate->estimate_no ? "새 견적서" : "견적서 #".$estimate->display_no) }}</h2>
        <span class="order-mode-label" id="orderModeLabel" style="display:none;">주문/배송 페이지</span>
        <span style="display:flex; gap:6px; align-items:center; margin-left:auto; margin-right:8px;">
            <button class="btn btn-ghost" id="orderModeBtn" style="padding:5px 12px; font-size:12px;" onclick="toggleOrderMode()">주문/배송</button>
            @if($estimate->status === 'paid')
                <span style="font-size:12px; padding:5px 12px; border-radius:6px; background:rgba(36,138,56,0.12); color:var(--green); font-weight:700;">💳 결제 완료{{ $estimate->payapp_paid_at ? ' · '.$estimate->payapp_paid_at->format('m/d H:i') : '' }}</span>
            @elseif($estimate->status === 'cancelled')
                <span style="font-size:12px; padding:5px 12px; border-radius:6px; background:rgba(192,56,56,0.1); color:var(--red); font-weight:700;">⛔ 결제 취소됨 — 재결제는 발행 완료로 변경</span>
            @elseif($estimate->payapp_payurl)
                <span style="font-size:12px; padding:5px 12px; border-radius:6px; background:rgba(59,94,160,0.1); color:var(--accent); font-weight:700;">💳 결제 대기 중</span>
                <button class="btn btn-ghost" style="padding:5px 12px; font-size:12px;" onclick="payappCancel()">결제요청 취소</button>
            @else
                <button class="btn btn-ghost" style="padding:5px 12px; font-size:12px;" onclick="payappRequest()">결제요청 생성</button>
            @endif
            <button class="btn btn-ghost" style="padding:5px 12px; font-size:12px;" onclick="copyPublicLink()">의뢰자 링크 복사</button>
        </span>
        <select id="estStatus" style="background:var(--navy); border:1px solid var(--navy); border-radius:8px; padding:6px 12px; color:#fff; font-size:12px; font-weight:700; outline:none; cursor:pointer;">
            <option value="created" {{ $estimate->status === 'created' ? 'selected' : '' }}>생성</option>
            <option value="editing" {{ $estimate->status === 'editing' ? 'selected' : '' }}>수정 중</option>
            <option value="completed" {{ $estimate->status === 'completed' ? 'selected' : '' }}>작성 완료</option>
            <option value="issued" {{ $estimate->status === 'issued' ? 'selected' : '' }}>발행 완료</option>
            <option value="paid" {{ $estimate->status === 'paid' ? 'selected' : '' }}>결제 완료</option>
            <option value="cancelled" {{ $estimate->status === 'cancelled' ? 'selected' : '' }}>결제 취소</option>
            <option value="hold" {{ $estimate->status === 'hold' ? 'selected' : '' }}>보류 중</option>
        </select>
    </div>
    <div class="est-body">
        <!-- 주문정보 -->
        <div class="client-section">
            <h4>주문 정보</h4>
            <div class="client-row">
                <div class="field client-search-wrap">
                    <label>닉네임</label>
                    <input id="cNickname" value="{{ $estimate->client_nickname }}" oninput="searchClients(this.value)" autocomplete="off">
                    <div class="client-results" id="clientResults"></div>
                </div>
                <div class="field">
                    <label>이름</label>
                    <input id="cName" value="{{ $estimate->client_name }}">
                </div>
                <div class="field">
                    <label>연락처</label>
                    <input id="cPhone" value="{{ $estimate->client_phone }}">
                </div>
                <div class="field">
                    <label>프로젝트 연동 (선택)</label>
                    <select id="cProject" disabled style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:13px; outline:none; cursor:pointer;">
                        <option value="">의뢰자 선택 후 지정 가능</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 장바구니 -->
        <div class="cart-section">
            <h4 style="display:flex; align-items:center; gap:8px;"><span id="cartTitle">제품 항목</span>
                <span style="margin-left:auto;">
                    <button class="btn-add-svc" id="btnSortMode" style="width:auto; padding:5px 12px;" onclick="toggleSortMode()" title="켜면 ⠿ 핸들 드래그로 항목/대분류 순서를 바꿀 수 있습니다 (모바일 스크롤 오작동 방지를 위해 기본 꺼짐)">순서 변경</button>
                    <button class="btn-add-svc" id="btnSavePreset" style="width:auto; padding:5px 12px;" onclick="saveAsPreset()">현재 품목을 프리셋으로 저장</button>
                    <button class="btn-add-svc" id="btnShipments" style="width:auto; padding:5px 12px; display:none; border-style:solid; border-color:#9db8d4; color:var(--accent);" onclick="openShipments()">배송 정보</button>
                </span>
            </h4>
            <table class="cart-table">
                <thead><tr><th>번호</th><th>분류</th><th>제품명</th><th>소요시간</th><th class="text-right">판매가</th><th>수량</th><th class="text-right">합계</th><th></th></tr></thead>
                <tbody id="cartBody"><tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">좌측에서 제품을 선택하세요</td></tr></tbody>
            </table>
        </div>

        <!-- 수기 제품 추가 — 일회성 품목 (제품 관리에 등록하지 않고 견적서 데이터로만 저장) -->
        <div class="cart-section">
            <h4>수기 제품 추가 <span style="color:var(--text-muted); font-weight:400; letter-spacing:0;">— 자주 취급하지 않는 제품·임의 견적용. 제품 관리에 등록되지 않고 작성 시점 가격으로 견적서에 저장됩니다</span></h4>
            <div class="svc-row">
                <input id="miCat" placeholder="카테고리" style="flex:1;">
                <input id="miName" placeholder="제품명 *" style="flex:2;" onkeydown="if(event.key==='Enter')addManualItem()">
                <input id="miPrice" type="number" min="0" placeholder="판매가" style="flex:1;" onkeydown="if(event.key==='Enter')addManualItem()">
                <input id="miQty" type="number" min="1" value="1" title="수량" style="width:56px;">
                <button class="btn-add-svc" style="width:auto; padding:6px 14px;" onclick="addManualItem()">+ 추가</button>
            </div>
        </div>

        <!-- 서비스 항목 (구버전 견적서 호환 — 저장된 항목이 있을 때만 표시) -->
        <div class="cart-section" id="svcSection" style="display:none;">
            <h4>서비스 항목 <span style="color:var(--text-muted); font-weight:400;">(구버전 — 새 항목은 위의 수기 제품 추가를 사용하세요)</span></h4>
            <div id="svcList"></div>
        </div>

        <!-- 합계 -->
        <div class="total-section">
            <div class="total-row"><span>제품 소계</span><span id="productTotal">0원</span></div>
            <div class="total-row" id="svcTotalRow" style="display:none;"><span>서비스 소계</span><span id="serviceTotal">0원</span></div>
            <div class="total-row grand"><span>총 견적 금액</span><span id="grandTotal">0원</span></div>
            <div class="total-items">총 항목 수: <span id="totalItems">0</span>개 (부가세 포함)</div>
        </div>

        <!-- 메모 -->
        <div class="cart-section">
            <h4>메모 <span style="color:var(--text-muted); font-weight:400; letter-spacing:0;">— 의뢰자 견적서에 표시됩니다</span></h4>
            <textarea id="estMemo" style="width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:8px 10px; color:var(--text); font-size:14px; outline:none; resize:vertical; min-height:60px;">{{ $estimate->memo }}</textarea>
        </div>

        <!-- 내부 비고 (직원 전용) -->
        <div class="cart-section" style="border-style:dashed;">
            <h4>내부 비고 <span style="color:var(--text-muted); font-weight:400; letter-spacing:0;">— 직원만 볼 수 있고 의뢰자 견적서·출력물에는 표시되지 않습니다</span></h4>
            <textarea id="estInternalMemo" placeholder="예: 협의된 할인 조건, 후속 조치, 담당자 참고사항" style="width:100%; background:var(--surface2); border:1px dashed var(--border); border-radius:6px; padding:8px 10px; color:var(--text); font-size:14px; outline:none; resize:vertical; min-height:60px;">{{ $estimate->internal_memo }}</textarea>
        </div>
    </div>

    <div class="panel-right-footer">
        <span class="save-indicator" id="saveIndicator"></span>
        <button class="btn btn-ghost" onclick="loadDraft()">임시저장 불러오기</button>
        <button class="btn btn-ghost" onclick="resetEstimate()">초기화</button>
        <button class="btn btn-ghost" onclick="openActivityLog('Estimate',{{ $estimate->id }},'견적서 #{{ $estimate->display_no }} 수정 로그')">로그</button>
        <button class="btn btn-delete" onclick="deleteEstimate()">삭제</button>
        <button class="btn btn-print" onclick="printEstimate()">🖨 견적서 출력</button>
        <button class="btn btn-save" onclick="saveEstimate()">저장</button>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};
const estId = {{ $estimate->id }};
const PUBLIC_URL = @json($estimate->publicUrl());

// === 견적서 제목 — 헤더 텍스트 클릭으로 인라인 편집 (제목 없으면 '견적서 #N' 표시) ===
let estTitleValue = @json($estimate->title);
let estNoText = @json($estimate->status === 'temp' && ! $estimate->estimate_no ? '새 견적서' : '견적서 #'.$estimate->display_no);
function renderEstTitle() {
    document.getElementById('estTitleNo').textContent = estTitleValue || estNoText;
}
function editEstTitle() {
    if (document.getElementById('estTitleInput')) return;
    const h = document.getElementById('estTitleNo');
    const inp = document.createElement('input');
    inp.id = 'estTitleInput';
    inp.value = estTitleValue || '';
    inp.maxLength = 200;
    inp.placeholder = estNoText + ' — 제목 입력';
    h.style.display = 'none';
    h.after(inp);
    inp.focus();
    let done = false;
    const commit = (save) => {
        if (done) return;
        done = true;
        if (save) estTitleValue = inp.value.trim() || null;
        inp.remove();
        h.style.display = '';
        renderEstTitle();
    };
    inp.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); commit(true); }
        else if (e.key === 'Escape') { commit(false); }
    });
    inp.addEventListener('blur', () => commit(true));
}

// === 의뢰자 링크 / 페이앱 결제 ===
function copyPublicLink() {
    navigator.clipboard.writeText(PUBLIC_URL)
        .then(() => alert('의뢰자용 견적서 링크가 복사되었습니다.\n카톡/문자로 전달하세요.\n\n' + PUBLIC_URL))
        .catch(() => prompt('아래 링크를 복사하세요:', PUBLIC_URL));
}

async function payappRequest() {
    if (!confirm('페이앱 결제요청을 생성할까요?\n의뢰자 링크 하단에 결제 버튼이 나타납니다.\n(주문 정보의 연락처와 총 견적 금액 기준)')) return;
    const res = await fetch(`/api/estimates/${estId}/payapp-request`, { method:'POST', headers:H });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) { alert(d.message || `결제요청 실패 (HTTP ${res.status})`); return; }
    alert('결제요청이 생성되었습니다.\n의뢰자 링크를 복사해 전달하세요.');
    location.reload();
}

async function payappCancel() {
    if (!confirm('결제요청을 취소할까요?\n의뢰자 링크의 결제 버튼이 사라집니다.')) return;
    const res = await fetch(`/api/estimates/${estId}/payapp-cancel`, { method:'POST', headers:H });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) { alert(d.message || '취소 실패'); return; }
    location.reload();
}
let clientId = {{ $estimate->client_id ?? 'null' }};
let allProds = [], catData = [], cartItems = @json($estimate->product_items ?? []), svcItems = @json($estimate->service_items ?? []);

function fmt(n) { return Number(n).toLocaleString(); }

// === 제품 로드 ===
async function loadProducts() {
    const [prodRes, catRes, allProdIdsRes] = await Promise.all([
        fetch('/api/inventory/estimate-products'),
        fetch('/api/inventory/categories'),
        // 모든 활성 제품 ID — '(삭제된 제품)' 마커가 false positive 안 되도록 별도 조회
        fetch('/api/inventory/products?id_only=1').catch(() => null),
    ]);
    allProds = await prodRes.json();
    catData = await catRes.json();
    try {
        if (allProdIdsRes && allProdIdsRes.ok) {
            const allProductsData = await allProdIdsRes.json();
            window.__allProdIds = new Set((Array.isArray(allProductsData) ? allProductsData : []).map(p => p.id));
        }
    } catch(e) { window.__allProdIds = null; }
    buildCatTabs();
    filterProducts();
}

// item이 가리키는 제품이 실제로 DB에 없을 때만 true (견적서 노출 OFF는 false positive 방지)
function isProductMissing(item) {
    if (!item.product_id) return false;
    // 전체 제품 ID 목록을 못 받았으면 false (안전한 폴백 — 정상 제품에 마커 안 붙음)
    if (!window.__allProdIds) return false;
    return !window.__allProdIds.has(item.product_id);
}

// === 카테고리 드릴다운 필터 — 1차 칩 선택 시 하위 카테고리 칩 행이 열린다 (재고 페이지와 동일 UX) ===
let catPath = [];
let activeCatId = null;

function buildCatTabs() {
    const el = document.getElementById('catTabs');
    // 선택 경로를 트리에서 다시 찾는다 (삭제된 카테고리는 경로 절단)
    const chain = [];
    let level = catData;
    for (const id of catPath) {
        const node = (level || []).find(c => c.id === id);
        if (!node) break;
        chain.push(node);
        level = node.children || [];
    }
    if (chain.length !== catPath.length) catPath.length = chain.length;
    activeCatId = catPath.length ? catPath[catPath.length - 1] : null;

    let html = '<div class="cat-tab-row">'
        + `<button class="cat-tab ${!catPath.length ? 'active' : ''}" onclick="setCatPath(0,null)">전체</button>`
        + catData.map(c => `<button class="cat-tab ${catPath[0] === c.id ? 'active' : ''}" onclick="setCatPath(0,${c.id})">${_escE(c.name)}</button>`).join('')
        + '</div>';
    chain.forEach((node, i) => {
        const children = node.children || [];
        if (!children.length) return;
        html += '<div class="cat-tab-row"><span class="cat-tab-arrow">└</span>'
            + `<button class="cat-tab ${catPath.length === i + 1 ? 'active' : ''}" onclick="setCatPath(${i + 1},null)">${_escE(node.name)} 전체</button>`
            + children.map(c => `<button class="cat-tab ${catPath[i + 1] === c.id ? 'active' : ''}" onclick="setCatPath(${i + 1},${c.id})">${_escE(c.name)}</button>`).join('')
            + '</div>';
    });
    el.innerHTML = html;
}

function setCatPath(depth, id) {
    catPath = catPath.slice(0, depth);
    if (id) catPath.push(id);
    buildCatTabs();
    filterProducts();
}

// 제품 리스트 정렬 — 제품명/가격/등록일, 선택은 브라우저에 기억
function sortProds(list) {
    const v = document.getElementById('prodSort')?.value || 'name_asc';
    const dir = v.endsWith('_desc') ? -1 : 1;
    const arr = [...list];
    if (v.startsWith('name')) arr.sort((a, b) => dir * String(a.name || '').localeCompare(String(b.name || ''), 'ko'));
    else if (v.startsWith('price')) arr.sort((a, b) => dir * ((Number(a.sale_price) || 0) - (Number(b.sale_price) || 0)));
    else if (v.startsWith('date')) arr.sort((a, b) => dir * (String(a.created_at || '').localeCompare(String(b.created_at || '')) || (a.id - b.id)));
    return arr;
}
function onProdSortChange() {
    try { localStorage.setItem('estProdSort', document.getElementById('prodSort').value); } catch (e) {}
    filterProducts();
}
try { const _ps = localStorage.getItem('estProdSort'); if (_ps && document.querySelector(`#prodSort option[value="${_ps}"]`)) document.getElementById('prodSort').value = _ps; } catch (e) {}

function filterProducts() {
    const search = document.getElementById('prodSearch').value.toLowerCase();
    const list = document.getElementById('productList');
    let filtered = allProds;

    if (activeCatId) {
        const ids = getCatDescendants(activeCatId);
        filtered = filtered.filter(p => ids.includes(p.category_id));
    }
    if (search) {
        // 띄어쓰기·대소문자 무시 — 'EOS R50 V'도 'r50v'로 매칭
        const norm = s => (s || '').toLowerCase().replace(/\s+/g, '');
        const q = norm(search);
        filtered = filtered.filter(p => norm(p.name).includes(q) || norm(p.sku).includes(q)
            || norm(p.group_name).includes(q) || norm(p.option_name).includes(q) || norm(p.search_tags).includes(q));
    }
    filtered = sortProds(filtered);

    // 옵션 그룹은 하나의 카드로 병합 — 클릭 시 옵션(블랙/화이트 등)을 골라 추가
    const seenGroups = new Set();
    const entries = [];
    filtered.forEach(p => {
        if (!p.group_id) { entries.push({ single: p }); return; }
        if (seenGroups.has(p.group_id)) return;
        seenGroups.add(p.group_id);
        // 검색이 한 옵션만 매칭해도 그룹의 전체 옵션을 보여준다
        const options = allProds.filter(x => x.group_id === p.group_id);
        entries.push({ group: { id: p.group_id, name: p.group_name || p.name, options } });
    });

    list.innerHTML = entries.map(e => {
        if (e.single) {
            const p = e.single;
            return `
        <div class="product-item" onclick="addToCart(${p.id})">
            <div>
                <div class="pi-name">${p.name}</div>
                <div class="pi-cat">${p.sku} · ${p.category_path || p.category || ''}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${fmt(p.sale_price)}원</div>
                <div class="pi-stock ${p.is_low?'low':'ok'}">재고 ${p.quantity}</div>
            </div>
        </div>`;
        }
        const g = e.group;
        const prices = g.options.map(o => Number(o.sale_price) || 0);
        const mn = Math.min(...prices), mx = Math.max(...prices);
        const totalQty = g.options.reduce((s, o) => s + (o.quantity || 0), 0);
        return `
        <div class="product-item" onclick="openOptionPicker(${g.id})">
            <div>
                <div class="pi-name">${g.name} <span style="font-size:10px; color:var(--accent); border:1px solid #9db8d4; border-radius:4px; padding:0 5px;">옵션 ${g.options.length}종</span></div>
                <div class="pi-cat">${g.options[0]?.category_path || g.options[0]?.category || ''} · ${g.options.map(o => o.option_name || o.name).join(' / ')}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${mn === mx ? fmt(mn) : fmt(mn) + '~' + fmt(mx)}원</div>
                <div class="pi-stock ${totalQty > 0 ? 'ok' : 'low'}">재고 ${totalQty}</div>
            </div>
        </div>`;
    }).join('') || '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">제품이 없습니다.</div>';
}

// === 옵션 선택 (그룹 상품) ===
function openOptionPicker(groupId) {
    const options = allProds.filter(p => p.group_id === groupId);
    if (!options.length) return;
    document.getElementById('optPickerTitle').textContent = (options[0].group_name || '') + ' — 옵션 선택';
    document.getElementById('optPickerList').innerHTML = options.map(o => `
        <div class="product-item" onclick="addToCart(${o.id}); closeOptionPicker();">
            <div>
                <div class="pi-name">${o.option_name || o.name}</div>
                <div class="pi-cat">${o.sku}</div>
            </div>
            <div style="text-align:right;">
                <div class="pi-price">${fmt(o.sale_price)}원</div>
                <div class="pi-stock ${o.is_low ? 'low' : 'ok'}">재고 ${o.quantity}</div>
            </div>
        </div>`).join('');
    document.getElementById('optionPickerOverlay').style.display = 'flex';
}
function closeOptionPicker() {
    document.getElementById('optionPickerOverlay').style.display = 'none';
}

// 트리 어느 깊이의 카테고리든 자기 자신 + 모든 하위 ID를 수집 (2·3차 선택 필터용)
function getCatDescendants(id) {
    const find = nodes => {
        for (const n of nodes || []) {
            if (n.id === id) return n;
            const f = find(n.children);
            if (f) return f;
        }
        return null;
    };
    const ids = [];
    const collect = n => { ids.push(n.id); (n.children || []).forEach(collect); };
    const node = find(catData);
    if (node) collect(node); else ids.push(id);
    return ids;
}

// === 장바구니 ===
function addToCart(productId) {
    const p = allProds.find(x => x.id === productId);
    if (!p) return;
    const price = Number(p.sale_price) || 0;
    const existing = cartItems.find(i => i.product_id === productId);
    if (existing) { existing.qty++; existing.subtotal = Number(existing.sale_price) * existing.qty; }
    else {
        // 스냅샷: 견적서 작성 시점의 제품 정보를 보존. 이후 제품 정보가 바뀌거나 삭제되어도 견적서는 영향 없음.
        cartItems.push({
            product_id: p.id,
            sku: p.sku,
            category: p.category,
            category_root: p.category_root || p.category, // 1차 대분류 — 소계 그룹 기준
            // 옵션 그룹 상품은 '그룹명 (옵션명)'으로 구분해 견적에 표시
            name: p.group_id && p.option_name ? `${p.group_name} (${p.option_name})` : p.name,
            purchase_price: p.purchase_price || 0,
            sale_price: price,
            qty: 1,
            // 소요시간 — '소요시간 사용' 제품만 입력폼 표시, 제품의 기본값을 프리필
            time_required: p.use_time_required ? (p.time_required || '') : '',
            use_time: !!p.use_time_required,
            subtotal: price,
            // 세트 구성품 스냅샷 — 빌더에서 접기/펼치기 (출력물·의뢰자 견적서에는 세트 한 줄만)
            bundle_items: p.is_bundle && (p.bundle_items || []).length ? p.bundle_items : undefined,
        });
    }
    renderCart();
}

// 대분류 블록 분해 — 배열 순서(= 저장/출력 순서)를 유지한 그룹 시퀀스
function groupBlocks() {
    const order = [], map = {};
    cartItems.forEach(item => {
        const cat = item.category_root || item.category || '기타';
        if (!map[cat]) { map[cat] = []; order.push(cat); }
        map[cat].push(item);
    });
    return { order, map };
}
function rebuildFromBlocks(order, map) {
    cartItems.length = 0;
    order.forEach(c => cartItems.push(...map[c]));
}

// === 순서 변경 모드 — 드래그 핸들 표시 토글 (기본 꺼짐: 모바일 스크롤 중 오이동 방지) ===
let sortMode = localStorage.getItem('estSortMode') === '1';
function toggleSortMode() {
    sortMode = !sortMode;
    try { localStorage.setItem('estSortMode', sortMode ? '1' : '0'); } catch (e) {}
    document.getElementById('btnSortMode').classList.toggle('on', sortMode);
    renderCart();
}
document.getElementById('btnSortMode').classList.toggle('on', sortMode);

// === 주문/배송 모드 — 편집 잠금 + 항목별 주문완료 체크 + 운송장 등록 ===
let orderMode = false;

function toggleOrderMode() {
    orderMode = !orderMode;
    document.getElementById('orderModeBtn').classList.toggle('mode-on', orderMode);
    document.getElementById('orderModeLabel').style.display = orderMode ? '' : 'none';
    document.getElementById('cartTitle').textContent = orderMode ? '주문/배송 현황' : '제품 항목';
    document.getElementById('btnSavePreset').style.display = orderMode ? 'none' : '';
    document.getElementById('btnSortMode').style.display = orderMode ? 'none' : '';
    document.getElementById('btnShipments').style.display = orderMode ? '' : 'none';
    renderCart();
}

function toggleOrdered(idx) {
    cartItems[idx].ordered = !cartItems[idx].ordered;
    renderCart();
    saveEstimate(true); // 주문완료 표시는 즉시 저장 (알림 없이)
}

function openShipments() {
    window.open(`/estimates/${estId}/shipments`, `shipments_${estId}`, 'width=620,height=680,scrollbars=yes,resizable=yes');
}

function renderCart() {
    const tb = document.getElementById('cartBody');
    if (!cartItems.length) {
        tb.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">좌측에서 제품을 선택하세요</td></tr>';
        updateTotals();
        return;
    }

    const { order, map } = groupBlocks();
    // 배열을 대분류 블록 단위로 연속 정돈 — 담은 순서가 뒤섞여 있으면(PC→비디오→PC)
    // 블록의 첫 항목 삭제 시 '첫 등장 순서'가 바뀌어 분류가 최상위로 점프한다
    rebuildFromBlocks(order, map);
    let html = '', globalIdx = 0;
    order.forEach((cat, gIdx) => {
        const items = map[cat];
        const catTotal = items.reduce((s, i) => s + i.subtotal, 0);
        html += `<tr class="cart-cat-header" data-gidx="${gIdx}">
            <td colspan="4">${(orderMode || !sortMode) ? '' : `<span class="drag-handle" draggable="true" data-drag-cat="${gIdx}" title="드래그해서 대분류 순서 변경">⠿</span> `}${_escE(cat)}</td>
            <td colspan="4" style="text-align:right;">${orderMode ? '' : `<button class="cat-rename-btn" onclick="renameCategory(${gIdx})" title="대분류 이름 수정 (예: 게임용 PC / 송출용 PC)">✎</button>`}</td>
        </tr>`;
        items.forEach(item => {
            const idx = cartItems.indexOf(item);
            globalIdx++;
            // 소요시간 입력폼 — '소요시간 사용' 제품만 표시 (구버전 항목은 값이 있으면 표시)
            const showTime = item.use_time !== undefined ? item.use_time : !!item.time_required;
            const timeCell = !showTime ? ''
                : (orderMode ? `<span style="font-size:12.5px; color:var(--text-muted);">${_escE(item.time_required || '')}</span>`
                    : `<input class="time-input" value="${_escE(item.time_required || '')}" onchange="cartItems[${idx}].time_required=this.value">`);
            const qtyCell = orderMode ? `<span style="padding-left:6px;">${item.qty}</span>`
                : `<div class="qty-ctrl">
                        <button onclick="changeQty(${idx},-1)">−</button>
                        <input value="${item.qty}" onchange="setQty(${idx},+this.value)">
                        <button onclick="changeQty(${idx},1)">+</button>
                    </div>`;
            const lastCell = orderMode
                ? (item.ordered
                    ? `<button class="btn-order cancel" onclick="toggleOrdered(${idx})" title="주문완료 표시 해제">취소</button>`
                    : `<button class="btn-order" onclick="toggleOrdered(${idx})">주문완료</button>`)
                : `<button class="btn-remove" onclick="removeItem(${idx})">×</button>`;
            // 주문/배송 뷰 전용 — 제품 메모를 제품명 아래 회색으로 (스냅샷에 저장하지 않아 출력물 미노출)
            const prodMemo = orderMode && item.product_id ? (allProds.find(x => x.id === item.product_id)?.memo || '') : '';
            const memoLine = prodMemo ? `<div style="font-size:12px; color:var(--text-muted); font-weight:400; margin-top:3px; white-space:pre-line;">${_escE(prodMemo)}</div>` : '';
            html += `<tr data-item-idx="${idx}" data-gidx="${gIdx}">
                <td>${(orderMode || !sortMode) ? '' : `<span class="drag-handle" draggable="true" data-drag-item="${idx}" title="드래그해서 순서 변경 (다른 대분류로도 이동 가능)">⠿</span> `}<span class="cart-row-num">${globalIdx}</span></td>
                <td style="font-size:12px; color:var(--text-muted); ${orderMode ? '' : 'cursor:pointer;'}" ${orderMode ? '' : `onclick="changeItemCategory(${idx})" title="클릭해서 이 항목의 대분류 변경 (새 이름을 입력하면 새 대분류로 분리됩니다)"`}>${item.category||''}</td>
                <td class="cell-name"><span class="${item.ordered ? 'name-ordered' : ''}" ${item.ordered ? 'title="주문완료"' : ''}>${item.name}</span>${(item.bundle_items||[]).length ? ` <button class="bundle-toggle" onclick="toggleBundle(${idx})" title="세트 구성품 ${item.bundle_items.length}개 ${__bundleOpen.has(item) ? '접기' : '펼치기'} — 의뢰자 견적서에는 세트 한 줄로만 표시됩니다">세트 ${item.bundle_items.length} ${__bundleOpen.has(item) ? '▾' : '▸'}</button>` : ''}${(item.refunded || item.refund_qty > 0 || item.refund_amount > 0) ? ` <span style="font-size:10.5px; color:var(--red); border:1px solid var(--red); border-radius:3px; padding:0 4px;" title="환불/결제취소 기록${item.refunded_at ? ' · ' + item.refunded_at : ''} — 세트는 펼치면 구성품별 환불 내역이 보입니다">환불 ${item.refund_qty > 0 ? item.refund_qty + '개' : ''}${item.refund_amount ? ` ${fmt(item.refund_amount)}원` : ''}</span>` : ''}${item.manual || !item.product_id ? ' <span style="font-size:10.5px; color:var(--text-muted); border:1px solid var(--border); border-radius:3px; padding:0 5px;" title="일회성 수기 품목 — 제품 관리에 등록되지 않고 견적서에만 저장됩니다">수기</span>' : ''}${isProductMissing(item) ? '<span style="font-size:11.5px; color:var(--text-muted); margin-left:6px;" title="원본 제품이 삭제되었지만 견적서 데이터는 보존됩니다">(삭제된 제품)</span>' : ''}${memoLine}</td>
                <td>${timeCell}</td>
                <td class="text-right">${fmt(item.sale_price)}원</td>
                <td>${qtyCell}</td>
                <td class="text-right" style="font-weight:600;">${fmt(item.subtotal)}원</td>
                <td>${lastCell}</td>
            </tr>`;
            // 세트 구성품 펼침 — 내부 확인용 (출력물·의뢰자 견적서에는 표시되지 않음)
            // 세트가 주문완료면 구성품도 주문완료로 표시. 가격은 구성품 판매가 참고치.
            if ((item.bundle_items || []).length && __bundleOpen.has(item)) {
                html += item.bundle_items.map(b => {
                    const totQty = b.qty * item.qty;
                    const price = Number(b.price) || 0;
                    return `<tr class="bundle-sub" data-gidx="${gIdx}">
                        <td></td><td></td>
                        <td><span class="${item.ordered ? 'name-ordered' : ''}" ${item.ordered ? 'title="세트 주문완료"' : ''}>└ ${_escE(b.name)}</span>${(b.refund_qty>0||b.refund_amount>0) ? ` <span style="font-size:10.5px; color:var(--red); border:1px solid var(--red); border-radius:3px; padding:0 4px;" title="구성품 부분환불">환불 ${b.refund_qty>0?b.refund_qty+'개':''}${b.refund_amount?` ${fmt(b.refund_amount)}원`:''}</span>` : ''}</td>
                        <td></td>
                        <td class="text-right">${price ? fmt(price) + '원' : ''}</td>
                        <td><span class="bs-qty" style="padding-left:6px;" title="세트당 ${b.qty}개 × 세트 ${item.qty}개">${totQty}</span></td>
                        <td class="text-right">${price ? fmt(price * totQty) + '원' : ''}</td>
                        <td></td>
                    </tr>`;
                }).join('');
            }
        });
        html += `<tr class="cart-subtotal" data-gidx="${gIdx}"><td colspan="6">${_escE(cat)} 소계</td><td class="text-right">${fmt(catTotal)}원</td><td></td></tr>`;
    });
    tb.innerHTML = html;
    updateTotals();
}

// === 대분류 이름 수정 / 항목별 대분류 변경 ===
function renameCategory(gIdx) {
    const { order, map } = groupBlocks();
    const cat = order[gIdx];
    if (cat === undefined) return;
    const name = prompt('대분류 이름을 입력하세요.\n견적서 출력에 이 이름으로 표시됩니다. (예: 게임용 PC / 송출용 PC)', cat);
    if (!name || !name.trim() || name.trim() === cat) return;
    map[cat].forEach(it => { it.category_root = name.trim(); });
    renderCart();
}
function changeItemCategory(idx) {
    const item = cartItems[idx];
    if (!item) return;
    const cur = item.category_root || item.category || '기타';
    const name = prompt('이 항목의 대분류를 입력하세요.\n새 이름을 입력하면 새 대분류 그룹으로 분리됩니다.', cur);
    if (!name || !name.trim() || name.trim() === cur) return;
    item.category_root = name.trim();
    renderCart();
}

// === 드래그 정렬 — 이동될 위치를 60% 미리보기 행으로 표시 (핸들 ⠿ 로 드래그) ===
let __dragCat = null, __dragItem = null, __previewEl = null;
function __clearDragUi() {
    if (__previewEl) { __previewEl.remove(); __previewEl = null; }
    document.querySelectorAll('#cartBody tr.drag-src').forEach(r => r.classList.remove('drag-src'));
}
(function initCartDnD() {
    const tb = document.getElementById('cartBody');

    function srcRow() {
        return __dragItem !== null
            ? tb.querySelector(`tr[data-item-idx="${__dragItem}"]`)
            : tb.querySelector(`tr.cart-cat-header[data-gidx="${__dragCat}"]`);
    }
    // 미리보기 행 — 드래그 중인 행의 복제본 (60% 투명, 히트테스트 제외)
    function ensurePreview() {
        if (__previewEl) return __previewEl;
        const s = srcRow();
        if (!s) return null;
        s.classList.add('drag-src');
        const c = s.cloneNode(true);
        c.classList.add('drag-preview');
        c.classList.remove('drag-src');
        c.removeAttribute('data-item-idx');
        c.removeAttribute('data-gidx');
        __previewEl = c;
        return c;
    }
    function groupRows(g) {
        return [...tb.querySelectorAll(`tr[data-gidx="${g}"]`)].filter(r => !r.classList.contains('drag-preview'));
    }

    tb.addEventListener('dragstart', e => {
        const h = e.target.closest('.drag-handle');
        if (!h) return;
        __dragCat = h.dataset.dragCat !== undefined ? +h.dataset.dragCat : null;
        __dragItem = h.dataset.dragItem !== undefined ? +h.dataset.dragItem : null;
        e.dataTransfer.effectAllowed = 'move';
    });

    // dragover/drop은 document에 바인딩 — 미리보기 행은 pointer-events:none이라
    // 그 위에서 놓으면 이벤트 타깃이 tbody 밖(table)이 되어 tbody 리스너로는 드롭이 거부된다
    document.addEventListener('dragover', e => {
        if (__dragCat === null && __dragItem === null) return;
        e.preventDefault();
        const tr = e.target.closest ? e.target.closest('#cartBody tr') : null;
        if (!tr || tr.classList.contains('drag-preview') || tr.classList.contains('drag-src')) return;
        const pv = ensurePreview();
        if (!pv) return;
        if (__dragItem !== null) {
            // 항목: 행 중간선 기준 — 위쪽 절반이면 그 행 앞, 아래 절반이면 그 행 뒤
            const r = tr.getBoundingClientRect();
            if (e.clientY > r.top + r.height / 2) tr.after(pv); else tr.before(pv);
        } else {
            // 대분류: 대상 그룹 블록 전체의 중간선 기준으로 그룹 앞/뒤에
            if (tr.dataset.gidx === undefined) return;
            const g = +tr.dataset.gidx;
            if (g === __dragCat) return;
            const rows = groupRows(g);
            if (!rows.length) return;
            const top = rows[0].getBoundingClientRect().top;
            const bottom = rows[rows.length - 1].getBoundingClientRect().bottom;
            if (e.clientY > (top + bottom) / 2) rows[rows.length - 1].after(pv); else rows[0].before(pv);
        }
    });

    document.addEventListener('drop', e => {
        if (__dragCat === null && __dragItem === null) return;
        e.preventDefault();
        if (__previewEl && __previewEl.isConnected) {
            if (__dragItem !== null) applyItemDrop(); else applyCatDrop();
        }
        __clearDragUi();
        __dragCat = __dragItem = null;
        renderCart();
    });

    // 항목 드롭 — 미리보기 위치 그대로 반영 (다음 항목 앞 삽입, 없으면 해당 그룹 맨 뒤)
    function applyItemDrop() {
        const item = cartItems[__dragItem];
        if (!item) return;
        let n = __previewEl.nextElementSibling, beforeItem = null;
        while (n) {
            if (n.classList.contains('cart-cat-header')) break;
            if (n.dataset.itemIdx !== undefined && !n.classList.contains('drag-src')) { beforeItem = cartItems[+n.dataset.itemIdx]; break; }
            n = n.nextElementSibling;
        }
        let p = __previewEl.previousElementSibling, gIdx = null;
        while (p) { if (p.dataset.gidx !== undefined && !p.classList.contains('drag-src')) { gIdx = +p.dataset.gidx; break; } p = p.previousElementSibling; }
        const { order, map } = groupBlocks();
        let cat = beforeItem ? (beforeItem.category_root || beforeItem.category || '기타') : (gIdx !== null ? order[gIdx] : null);
        // 테이블 최상단(첫 헤더 위)에 놓으면 — 다음 헤더 그룹의 맨 앞으로
        if (cat == null) {
            let h = __previewEl.nextElementSibling;
            while (h && !(h.classList.contains('cart-cat-header') && h.dataset.gidx !== undefined)) h = h.nextElementSibling;
            if (h && order[+h.dataset.gidx] !== undefined) {
                cat = order[+h.dataset.gidx];
                beforeItem = (map[cat] || [])[0] || null;
            }
        }
        if (cat == null || beforeItem === item) return;
        cartItems.splice(cartItems.indexOf(item), 1);
        item.category_root = cat;
        if (beforeItem) {
            cartItems.splice(cartItems.indexOf(beforeItem), 0, item);
        } else {
            const { order: o2, map: m2 } = groupBlocks();
            if (m2[cat]) { m2[cat].push(item); } else { o2.push(cat); m2[cat] = [item]; }
            rebuildFromBlocks(o2, m2);
        }
    }

    // 대분류 드롭 — 미리보기 다음 헤더 그룹 앞으로, 없으면 맨 뒤로
    function applyCatDrop() {
        const { order, map } = groupBlocks();
        let n = __previewEl.nextElementSibling, nextG = null;
        while (n) { if (n.classList.contains('cart-cat-header') && n.dataset.gidx !== undefined) { nextG = +n.dataset.gidx; break; } n = n.nextElementSibling; }
        if (order[__dragCat] === undefined) return;
        const moved = order.splice(__dragCat, 1)[0];
        let target = order.length;
        if (nextG !== null) target = nextG > __dragCat ? nextG - 1 : nextG;
        order.splice(target, 0, moved);
        rebuildFromBlocks(order, map);
    }
})();
// ESC 등으로 드래그가 취소돼도 미리보기/흐림 정리
document.addEventListener('dragend', () => { __clearDragUi(); __dragCat = __dragItem = null; });


// 드래그 정렬 중 자동 스크롤 — 포인터가 장바구니 스크롤 영역 위/아래 가장자리에 가까우면
// 스크롤 (HTML5 DnD는 내부 스크롤 컨테이너를 자동 스크롤하지 않아 긴 견적서에서 이동 불가)
let __dndY = null, __dndScrollTimer = null;
function __stopDndScroll() { clearInterval(__dndScrollTimer); __dndScrollTimer = null; __dndY = null; }
document.addEventListener('dragover', e => {
    if (__dragCat === null && __dragItem === null) return;
    __dndY = e.clientY;
    if (__dndScrollTimer) return;
    const scroller = document.querySelector('.est-body');
    if (!scroller) return;
    __dndScrollTimer = setInterval(() => {
        if (__dndY === null) return;
        const r = scroller.getBoundingClientRect();
        const EDGE = 70, MAX = 20; // 가장자리 70px 이내에서 근접할수록 빨라짐 (최대 20px/tick)
        let dy = 0;
        if (__dndY < r.top + EDGE) dy = -Math.ceil((r.top + EDGE - __dndY) / EDGE * MAX);
        else if (__dndY > r.bottom - EDGE) dy = Math.ceil((__dndY - (r.bottom - EDGE)) / EDGE * MAX);
        if (dy) scroller.scrollTop += dy;
    }, 40);
});
document.addEventListener('dragend', __stopDndScroll);
document.addEventListener('drop', __stopDndScroll);

function changeQty(idx, delta) {
    cartItems[idx].qty = Math.max(1, cartItems[idx].qty + delta);
    cartItems[idx].subtotal = Number(cartItems[idx].sale_price) * cartItems[idx].qty;
    renderCart();
}
function setQty(idx, val) {
    cartItems[idx].qty = Math.max(1, parseInt(val)||1);
    cartItems[idx].subtotal = Number(cartItems[idx].sale_price) * cartItems[idx].qty;
    renderCart();
}
// 세트 구성품 접기/펼치기 — 항목 객체 기준 (재렌더에도 상태 유지, 저장 데이터에는 미포함)
const __bundleOpen = new WeakSet();
function toggleBundle(idx) {
    const item = cartItems[idx];
    if (!item) return;
    if (__bundleOpen.has(item)) __bundleOpen.delete(item); else __bundleOpen.add(item);
    renderCart();
}

// 더블클릭/성급한 재클릭 방지 — 삭제로 행이 위로 당겨지면 같은 좌표의 두 번째 클릭이
// 다른 분류의 × 버튼에 떨어져 엉뚱한 항목까지 지워진다 (분류 마지막 항목 삭제 시 3줄 이동)
let __lastRemoveAt = 0;
function removeItem(idx) {
    const now = Date.now();
    if (now - __lastRemoveAt < 400) return;
    __lastRemoveAt = now;
    cartItems.splice(idx, 1);
    renderCart();
}

// === 수기 제품 추가 — 일회성 품목 (제품 관리 미등록, 장바구니에 스냅샷으로 저장) ===
function addManualItem() {
    const name = document.getElementById('miName').value.trim();
    if (!name) return alert('제품명을 입력해주세요.');
    const price = Math.max(0, parseInt(document.getElementById('miPrice').value) || 0);
    const qty = Math.max(1, parseInt(document.getElementById('miQty').value) || 1);
    const miCat = document.getElementById('miCat').value.trim() || '기타';
    cartItems.push({
        product_id: null, sku: '', category: miCat, category_root: miCat,
        name, purchase_price: 0, sale_price: price, qty, time_required: '', use_time: false, subtotal: price * qty, manual: true,
    });
    ['miName','miPrice'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('miQty').value = 1;
    renderCart();
}

// === 서비스 항목 (구버전 호환 — 저장된 항목이 있을 때만 표시/수정) ===
function renderServices() {
    document.getElementById('svcSection').style.display = svcItems.length ? '' : 'none';
    document.getElementById('svcTotalRow').style.display = svcItems.length ? '' : 'none';
    const el = document.getElementById('svcList');
    el.innerHTML = svcItems.map((s, i) => `
        <div class="svc-row">
            <input value="${s.name}" onchange="svcItems[${i}].name=this.value" placeholder="항목명" style="flex:2;">
            <input type="number" value="${s.amount}" onchange="svcItems[${i}].amount=+this.value; updateTotals();" placeholder="금액" style="flex:1;">
            <button class="btn-remove" onclick="svcItems.splice(${i},1); renderServices(); updateTotals();">×</button>
        </div>
    `).join('');
    updateTotals();
}

// === 견적 프리셋 — 저장/불러오기 (여러 개 선택해 조립, 추가/교체) ===
async function saveAsPreset() {
    if (!cartItems.length) return alert('저장할 품목이 없습니다. 먼저 제품을 담아주세요.');
    const title = prompt('프리셋 제목을 입력해주세요.', '');
    if (!title || !title.trim()) return;
    const res = await fetch('/api/estimate-presets', {
        method: 'POST', headers: H,
        body: JSON.stringify({ title: title.trim(), items: cartItems }),
    });
    if (!res.ok) {
        const e = await res.json().catch(()=>({}));
        const detail = e.errors ? '\n' + Object.values(e.errors).flat().join('\n') : '';
        return alert((e.message || '프리셋 저장에 실패했습니다.') + detail + `\n(HTTP ${res.status})`);
    }
    alert('프리셋으로 저장되었습니다. 우측 프리셋 목록과 견적서 목록의 [프리셋] 탭에서 관리할 수 있습니다.');
    loadPresetPanel();
}

let PRESETS = [];
async function loadPresetPanel() {
    const res = await fetch('/api/estimate-presets', { headers: { 'Accept': 'application/json' } });
    PRESETS = res.ok ? await res.json() : [];
    const list = document.getElementById('presetPanelList');
    list.innerHTML = PRESETS.length ? PRESETS.map(p => `
        <div class="preset-item" onclick="applyPresetById(${p.id})" title="클릭하면 품목 ${p.item_count}개가 견적서에 담깁니다">
            <div class="preset-name">${_escE(p.title)}</div>
            <div class="preset-total">${fmt(p.total)}원</div>
        </div>`).join('') : '<div style="padding:16px; text-align:center; color:var(--text-muted); font-size:12px;">저장된 프리셋이 없습니다.<br>품목을 담은 뒤 [현재 품목을 프리셋으로 저장]을 눌러 만들 수 있습니다.</div>';
}
// 프리셋 클릭 → 품목 담기. 여러 프리셋을 연속 클릭해 조립할 수 있고,
// product_id가 살아있는 품목은 현재 판매가/이름으로 갱신 (수기·삭제 제품은 저장본 유지)
function applyPresetById(id) {
    const preset = PRESETS.find(p => p.id === id);
    if (!preset) return;
    const items = (preset.items || []).map(it => {
        const item = { ...it };
        const cur = it.product_id ? allProds.find(p => p.id === it.product_id) : null;
        if (cur) {
            item.name = cur.group_id && cur.option_name ? `${cur.group_name} (${cur.option_name})` : cur.name;
            item.category = cur.category || item.category;
            item.category_root = cur.category_root || item.category_root || item.category;
            item.sku = cur.sku;
            item.sale_price = Number(cur.sale_price) || 0;
            item.purchase_price = Number(cur.purchase_price) || 0;
            if (cur.is_bundle && (cur.bundle_items || []).length) item.bundle_items = cur.bundle_items;
        }
        item.qty = Math.max(1, parseInt(item.qty) || 1);
        item.subtotal = (Number(item.sale_price) || 0) * item.qty;
        return item;
    });
    if (document.getElementById('presetReplaceMode').checked) { cartItems.length = 0; }
    // 이미 담긴 품목은 행을 늘리지 않고 수량을 더한다 — 제품 클릭(addToCart)과 동일 동작
    // (프리셋을 다시 누르면 같은 항목이 행으로 중복 생성되던 버그 수정)
    items.forEach(item => {
        const existing = item.product_id
            ? cartItems.find(i => i.product_id === item.product_id)
            : cartItems.find(i => !i.product_id && i.name === item.name && Number(i.sale_price) === Number(item.sale_price));
        if (existing) {
            existing.qty += item.qty;
            existing.subtotal = Number(existing.sale_price) * existing.qty;
        } else {
            cartItems.push(item);
        }
    });
    renderCart();
}
function _escE(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// === 합계 ===
function updateTotals() {
    const pt = cartItems.reduce((s,i) => s + (Number(i.subtotal)||0), 0);
    const st = svcItems.reduce((s,i) => s + (Number(i.amount)||0), 0);
    document.getElementById('productTotal').textContent = fmt(pt)+'원';
    document.getElementById('serviceTotal').textContent = fmt(st)+'원';
    document.getElementById('grandTotal').textContent = fmt(pt+st)+'원';
    document.getElementById('totalItems').textContent = cartItems.length + svcItems.filter(s=>s.name).length;
}

// === 의뢰자 검색 ===
// (검색 구현은 아래 async searchClients — 예전 미완성 중복 선언은 SyntaxError를 유발해 제거)
let searchTimer;
function selectClient(client) {
    clientId = client.id;
    document.getElementById('cNickname').value = client.nickname || '';
    document.getElementById('cName').value = client.name || '';
    document.getElementById('cPhone').value = client.phone || '';
    document.getElementById('clientResults').classList.remove('show');
    loadClientProjects(clientId, null);
}

// 의뢰자의 진행 중 프로젝트를 연동 선택지로 로드 (선택 사항 — '연동 안 함'이 기본)
async function loadClientProjects(cid, selectedId) {
    const sel = document.getElementById('cProject');
    if (!cid) {
        sel.innerHTML = '<option value="">의뢰자 선택 후 지정 가능</option>';
        sel.disabled = true;
        return;
    }
    const res = await fetch(`/api/estimate-client-projects/${cid}`, { headers: { 'Accept': 'application/json' } });
    const projects = res.ok ? await res.json() : [];
    sel.innerHTML = '<option value="">연동 안 함</option>' + projects.map(p =>
        `<option value="${p.id}">${_escE(p.name)}${p.stage_label ? ` · ${_escE(p.stage_label)}` : ''}</option>`).join('');
    sel.disabled = false;
    if (selectedId && projects.some(p => p.id === selectedId)) sel.value = String(selectedId);
}

// === 저장/발행/삭제 ===
function buildEstimateBody() {
    return {
        title: estTitleValue,
        client_id: clientId,
        project_id: +document.getElementById('cProject').value || null,
        client_name: document.getElementById('cName').value || null,
        client_nickname: document.getElementById('cNickname').value || null,
        client_phone: document.getElementById('cPhone').value || null,
        product_items: cartItems,
        service_items: svcItems.filter(s => s.name),
        status: document.getElementById('estStatus').value,
        memo: document.getElementById('estMemo').value || null,
        internal_memo: document.getElementById('estInternalMemo').value || null,
    };
}

async function saveEstimate(silent = false) {
    const body = buildEstimateBody();
    const res = await fetch(`/api/estimates/${estId}`, {method:'PATCH', headers:H, body:JSON.stringify(body)});
    if (res.ok) {
        lastSnapshot = JSON.stringify(body);
        document.getElementById('saveIndicator').textContent = '저장됨 ' + new Date().toLocaleTimeString('ko-KR',{hour:'2-digit',minute:'2-digit'});
        if (window.opener) try { window.opener.loadEstimates?.(); } catch(e) {}
        const d = await res.json().catch(() => ({}));
        // 첫 저장 시 발급된 견적서 번호를 헤더/창 제목에 반영
        if (d.display_no) {
            estNoText = '견적서 #' + d.display_no;
            renderEstTitle();
            document.title = '견적서 #' + d.display_no + ' - 닥터고블린 오피스';
        }
        // 발행완료 전환 시 결제요청 자동 생성 결과 안내
        if (d.payapp_warning) { alert(d.payapp_warning); }
        else if (body.status === 'issued' && d.payapp_payurl) {
            if (confirm('발행 완료 — 의뢰자 페이지에 결제 버튼이 활성화되었습니다.\n의뢰자 링크를 지금 복사할까요?')) copyPublicLink();
            location.reload();
        }
        else if (!silent) { alert('저장되었습니다.'); }
        return;
    }
    // 실패 — 어떤 필드/예외가 문제인지 표시
    const err = await res.json().catch(() => ({}));
    const parts = [];
    if (err.message) parts.push(err.message);
    if (err.errors) {
        Object.entries(err.errors).forEach(([f, msgs]) => {
            parts.push(`[${f}] ` + (Array.isArray(msgs) ? msgs.join(', ') : msgs));
        });
    }
    if (err.exception) parts.push(`예외: ${err.exception}`);
    if (err.file) parts.push(`위치: ${err.file}`);
    alert(`저장 실패 (${res.status})\n\n` + (parts.length ? parts.join('\n') : '(빈 응답)'));
}

// === 1분 자동 임시저장 (정식 저장과 별개 스냅샷) + 불러오기 + 초기화 ===
let lastSnapshot = null; // 마지막 저장/임시저장 시점의 내용 — 변경 없으면 임시저장 생략

async function autoSaveDraft() {
    if (document.getElementById('estTitleInput')) return; // 제목 편집 중에는 건너뜀
    const body = buildEstimateBody();
    const snap = JSON.stringify(body);
    if (snap === lastSnapshot) return;
    try {
        const res = await fetch(`/api/estimates/${estId}/draft`, {method:'POST', headers:H, body:JSON.stringify({draft: body})});
        if (!res.ok) return;
        const d = await res.json().catch(() => ({}));
        lastSnapshot = snap;
        document.getElementById('saveIndicator').textContent = (d.saved_at || new Date().toLocaleTimeString('ko-KR',{hour12:false})) + ' 임시저장';
    } catch(e) { /* 네트워크 일시 오류 — 다음 주기에 재시도 */ }
}
setInterval(autoSaveDraft, 60000);

async function loadDraft() {
    const res = await fetch(`/api/estimates/${estId}/draft`, {headers:{'Accept':'application/json'}});
    const d = res.ok ? await res.json() : {};
    if (!d.draft) { alert('임시저장된 내용이 없습니다.\n(정식 저장을 하면 임시저장본은 비워집니다)'); return; }
    if (!confirm(`${d.saved_at} 임시저장본을 불러올까요?\n현재 화면의 내용은 대체됩니다.`)) return;
    applyEstimateBody(d.draft);
    document.getElementById('saveIndicator').textContent = d.saved_at.slice(11) + ' 임시저장본 불러옴 — 저장을 눌러야 반영됩니다';
}

function applyEstimateBody(b) {
    estTitleValue = b.title || null;
    renderEstTitle();
    clientId = b.client_id || null;
    document.getElementById('cName').value = b.client_name || '';
    document.getElementById('cNickname').value = b.client_nickname || '';
    document.getElementById('cPhone').value = b.client_phone || '';
    if (b.status) document.getElementById('estStatus').value = b.status;
    document.getElementById('estMemo').value = b.memo || '';
    document.getElementById('estInternalMemo').value = b.internal_memo || '';
    cartItems = b.product_items || [];
    svcItems = b.service_items || [];
    renderCart();
    renderServices();
    loadClientProjects(clientId, b.project_id || null);
}

function resetEstimate() {
    if (!confirm('견적 내용을 모두 지우고 새로 작성할까요?\n저장 버튼을 눌러야 실제로 반영됩니다.')) return;
    applyEstimateBody({ status: document.getElementById('estStatus').value });
    document.getElementById('saveIndicator').textContent = '초기화됨 — 저장을 눌러야 반영됩니다';
}

function printEstimate() {
    window.open(`/estimates/${estId}/print`, `print_${estId}`, 'width=900,height=700,scrollbars=yes');
}

async function deleteEstimate() {
    if (!confirm('이 견적서를 삭제할까요?')) return;
    await fetch(`/api/estimates/${estId}`, {method:'DELETE', headers:H});
    if (window.opener) try { window.opener.loadEstimates?.(); } catch(e) {}
    window.close();
}

// === 의뢰자 검색 (직접 API) ===
async function searchClients(q) {
    clearTimeout(searchTimer);
    const el = document.getElementById('clientResults');
    if (q.length < 1) { el.classList.remove('show'); return; }
    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/api/clients/search?q=${encodeURIComponent(q)}`);
            const clients = await res.json();
            if (!clients.length) { el.classList.remove('show'); return; }
            el.innerHTML = clients.map(c => `<div class="client-result-item" onclick='selectClient(${JSON.stringify(c)})'>${c.nickname || c.name || ''}${c.nickname && c.name ? ' ('+c.name+')' : ''} ${c.phone||''}</div>`).join('');
            el.classList.add('show');
        } catch(e) { el.classList.remove('show'); }
    }, 300);
}

// 초기화
loadProducts();
renderCart();
renderServices();
loadPresetPanel();

// === 패널 폭 드래그 조절 — 제품 리스트/프리셋 패널 (폭은 브라우저에 기억) ===
(function initPanelResize() {
    const L = document.querySelector('.panel-left');
    const P = document.querySelector('.panel-presets');
    const lw = parseInt(localStorage.getItem('estPanelLeftW'));
    if (lw) L.style.width = Math.min(Math.max(lw, 240), 680) + 'px';
    const pw = parseInt(localStorage.getItem('estPanelPresetsW'));
    if (pw) P.style.width = Math.min(Math.max(pw, 140), 440) + 'px';

    function bind(rz, apply, save) {
        rz.addEventListener('mousedown', e => {
            e.preventDefault();
            rz.classList.add('active');
            document.body.style.userSelect = 'none';
            const move = ev => apply(ev.clientX);
            const up = () => {
                rz.classList.remove('active');
                document.body.style.userSelect = '';
                save();
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        });
    }
    bind(document.getElementById('rzLeft'),
        x => { L.style.width = Math.min(Math.max(x, 240), 680) + 'px'; },
        () => localStorage.setItem('estPanelLeftW', parseInt(L.style.width)));
    bind(document.getElementById('rzRight'),
        x => { P.style.width = Math.min(Math.max(window.innerWidth - x, 140), 440) + 'px'; },
        () => localStorage.setItem('estPanelPresetsW', parseInt(P.style.width)));
})();
// 초기 상태를 임시저장 기준점으로 — 프로젝트 셀렉트가 채워진 뒤 스냅샷을 찍어야 헛 임시저장이 안 생긴다
Promise.resolve(clientId ? loadClientProjects(clientId, {{ $estimate->project_id ?? 'null' }}) : null)
    .then(() => { lastSnapshot = JSON.stringify(buildEstimateBody()); });

document.addEventListener('click', e => {
    if (!e.target.closest('.client-search-wrap')) document.getElementById('clientResults').classList.remove('show');
});
</script>
</body>
</html>
