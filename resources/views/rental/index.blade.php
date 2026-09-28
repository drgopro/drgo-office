@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '장비 위치 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1400px; margin:0 auto; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .page-title { font-size:22px; font-weight:700; }

    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-sm { padding:5px 10px; font-size:12px; border-radius:6px; }
    .rh-left-warn { color:var(--red); font-size:11px; font-weight:700; margin-left:4px; }
    .btn-outline { background:none; border:1px solid var(--border); color:var(--text-muted); padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .btn-danger-sm { background:none; border:none; color:var(--text-muted); font-size:12px; cursor:pointer; padding:5px 8px; }
    .btn-danger-sm:hover { color:var(--red); }
    .empty-row { text-align:center; padding:20px !important; color:var(--text-muted); font-size:13px; }
    .text-muted { color:var(--text-muted); font-size:12px; }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:500px; max-width:95vw; max-height:90vh; overflow-y:auto; padding:24px; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .modal-title { font-size:16px; font-weight:700; }
    .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; }
    .field-group { margin-bottom:14px; } .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .field-input:focus { border-color:var(--accent); }
    .field-select { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; cursor:pointer; box-sizing:border-box; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; }
    .btn-save { background:var(--accent); color:var(--accent-text); border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    [data-theme="light"] .btn-primary,
    [data-theme="light"] .btn-save { color:#fff; }
    [data-theme="light"] .field-input,
    [data-theme="light"] .field-select { background:#fff; border-color:#b8bcc8; }
    [data-theme="light"] .modal { background:#fff; border-color:#c8ccd4; }
    [data-theme="light"] .btn-outline { border-color:#b8bcc8; color:#4a5060; }
    [data-theme="light"] .btn-outline:hover { border-color:var(--accent); color:var(--accent); }

    /* ── 장비 현황판 ── */
    .board-toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .board-toolbar > * { flex-shrink:0; }
    .board-toolbar .search-box { flex:1 1 220px; min-width:180px; max-width:320px; position:relative; }
    .board-toolbar .search-box input { width:100%; height:32px; padding:0 12px 0 30px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; box-sizing:border-box; }
    .board-toolbar .search-box input:focus { border-color:var(--accent); }
    .board-toolbar .search-box::before { content:"🔍"; position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:12px; opacity:.6; }
    .board-toolbar .stat-pill { display:inline-flex; align-items:center; gap:6px; height:28px; padding:0 12px; background:var(--surface2); border:1px solid var(--border); border-radius:14px; font-size:11px; color:var(--text-muted); line-height:1; white-space:nowrap; }
    .board-toolbar .stat-pill strong { color:var(--accent); font-family:"SF Mono",Menlo,Monaco,monospace; font-size:12px; font-weight:700; line-height:1; }
    .board-toolbar .spacer { flex:1 1 0; min-width:0; }
    .board-toolbar .tb-btn { display:inline-flex; align-items:center; gap:4px; height:32px; padding:0 12px; border:1px solid var(--border); background:none; border-radius:7px; color:var(--text-muted); font-size:12px; cursor:pointer; white-space:nowrap; font-family:inherit; }
    .board-toolbar .tb-btn:hover { border-color:var(--accent); color:var(--accent); }
    .board-toolbar .tb-btn.primary { background:var(--accent); color:var(--accent-text); border-color:var(--accent); font-weight:700; }
    [data-theme="light"] .board-toolbar .tb-btn.primary { color:#fff; }

    .board-wrap { max-height:calc(100vh - 280px); min-height:420px; overflow:auto; background:var(--surface); border:1px solid var(--border); border-radius:12px; -webkit-overflow-scrolling:touch; position:relative; }
    .eq-board { display:grid; min-width:max-content; border-collapse:collapse; }
    .eq-cell-base { border-right:1px solid var(--border); border-bottom:1px solid var(--border); background:var(--surface); display:flex; align-items:center; justify-content:center; transition:background .12s; }
    .eq-corner { position:sticky; top:0; left:0; z-index:6; background:var(--surface2); padding:10px 12px; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--text-muted); text-align:left; justify-content:flex-start; }
    .eq-col-header { position:sticky; top:0; z-index:5; background:var(--surface2); padding:10px 8px; font-size:12px; font-weight:600; color:var(--text); cursor:pointer; min-height:56px; flex-direction:column; gap:3px; text-align:center; }
    .eq-col-header:hover { background:var(--surface); }
    .eq-col-header .ch-name { font-size:13px; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
    .eq-col-header .ch-sub { font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; }
    .eq-col-header.empty { color:var(--text-muted); font-style:italic; font-weight:400; }
    .eq-col-header.empty:hover { color:var(--accent); }
    .eq-row-header { position:sticky; left:0; z-index:4; background:var(--surface2); padding:10px 12px; font-size:13px; color:var(--text); cursor:pointer; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; min-height:54px; }
    .eq-row-header:hover { background:var(--surface); }
    .eq-row-header .rh-name { font-weight:600; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
    .eq-row-header .rh-serial { font-family:"SF Mono",Menlo,monospace; font-size:10px; color:var(--text-muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
    .eq-row-header.empty { color:var(--text-muted); font-style:italic; font-weight:400; }
    .eq-row-header.empty:hover { color:var(--accent); }
    .eq-matrix-cell { cursor:pointer; min-height:54px; position:relative; }
    .eq-matrix-cell:hover { background:var(--surface2); }
    .eq-matrix-cell.marked { background:rgba(212,188,150,0.12); }
    .eq-matrix-cell.marked:hover { background:rgba(212,188,150,0.2); }
    [data-theme="light"] .eq-matrix-cell.marked { background:rgba(156,125,72,0.12); }
    [data-theme="light"] .eq-matrix-cell.marked:hover { background:rgba(156,125,72,0.22); }
    .eq-o-mark { width:30px; height:30px; display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:28px; line-height:1; transition:transform .15s; cursor:grab; touch-action:none; user-select:none; -webkit-user-select:none; }
    .eq-o-mark:active { cursor:grabbing; }
    .eq-matrix-cell:hover .eq-o-mark { transform:scale(1.08); }
    .eq-matrix-cell.dragging-source .eq-o-mark { opacity:0.25; transform:scale(0.9); }
    .eq-matrix-cell.drop-target { background:rgba(212,188,150,0.06); box-shadow:inset 0 0 0 1px rgba(212,188,150,0.3); }
    .eq-matrix-cell.drop-target.drop-hover { background:rgba(212,188,150,0.24); box-shadow:inset 0 0 0 2px var(--accent); }
    [data-theme="light"] .eq-matrix-cell.drop-target { background:rgba(156,125,72,0.08); box-shadow:inset 0 0 0 1px rgba(156,125,72,0.3); }
    [data-theme="light"] .eq-matrix-cell.drop-target.drop-hover { background:rgba(156,125,72,0.26); box-shadow:inset 0 0 0 2px var(--accent); }
    .eq-drag-ghost { position:fixed; pointer-events:none; z-index:300; width:34px; height:34px; display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:32px; line-height:1; text-shadow:0 4px 12px rgba(0,0,0,0.5); transform:translate(-50%,-50%); }
    body.eq-dragging { cursor:grabbing !important; }
    body.eq-dragging .eq-o-mark { cursor:grabbing; }
    body.eq-dragging .board-wrap { touch-action:none; }

    .eq-log-panel { position:fixed; top:0; right:0; bottom:0; width:380px; max-width:92vw; background:var(--surface); border-left:1px solid var(--border); z-index:90; transform:translateX(100%); transition:transform .25s cubic-bezier(.4,0,.2,1); display:flex; flex-direction:column; }
    .eq-log-panel.open { transform:translateX(0); }
    .eq-log-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .eq-log-head .title { font-size:14px; font-weight:700; }
    .eq-log-body { flex:1; overflow-y:auto; padding:12px 16px; display:flex; flex-direction:column; gap:8px; }
    .eq-log-item { padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; font-size:12px; line-height:1.5; }
    .eq-log-head-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
    .eq-log-user { font-weight:700; color:var(--accent); font-size:12px; }
    .eq-log-time { font-size:10px; color:var(--text-muted); font-family:"SF Mono",Menlo,monospace; }
    .eq-log-action { color:var(--text-muted); }
    .eq-log-action strong { color:var(--text); font-weight:600; }

    .eq-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px); background:var(--surface); color:var(--text); padding:10px 18px; border-radius:8px; border:1px solid var(--border); font-size:13px; opacity:0; pointer-events:none; transition:all .25s; z-index:200; box-shadow:0 6px 20px rgba(0,0,0,0.4); }
    .eq-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }

    @media (max-width: 768px) {
        .page-wrap { padding:16px; }
        .modal { width:95vw; max-width:95vw; padding:16px; }
        .field-row { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <div class="page-title"><x-icon name="camera" :size="18"/> 장비 위치</div>
    </div>

    <div class="board-toolbar">
        <div class="search-box">
            <input type="text" id="bdSearch" placeholder="장비명, 시리얼, 대상 검색…">
        </div>
        <span class="stat-pill">장비 <strong id="bdStatItems">0</strong></span>
        <span class="stat-pill">대여중 <strong id="bdStatInUse">0</strong></span>
        <span class="stat-pill">대상 <strong id="bdStatTargets">0</strong></span>
        <div class="spacer"></div>
        <button class="tb-btn" id="bdScanBtn" title="QR 스캔"><x-icon name="qr" :size="13"/> 스캔</button>
        <button class="tb-btn" id="bdCategoryBtn" title="카테고리 관리"><x-icon name="tag" :size="13"/> 카테고리</button>
        <button class="tb-btn" id="bdGroupBtn" title="그룹 관리"><x-icon name="box" :size="13"/> 그룹</button>
        <button class="tb-btn" id="bdLogBtn" title="변경 이력"><x-icon name="clip" :size="13"/> 이력</button>
        <button class="tb-btn" onclick="openRentalItemModal(null)">＋ 장비</button>
        <button class="tb-btn primary" onclick="openRentalTargetModal(null)">＋ 대상</button>
    </div>
    <div class="board-wrap" id="bdBoardWrap">
        <div class="eq-board" id="bdBoard"></div>
    </div>
    <div class="text-muted" style="margin-top:8px; font-size:11px;">셀을 클릭하면 해당 대상으로 지정됩니다. ● 마크를 드래그해 같은 행의 다른 셀로 이동할 수 있습니다.</div>
</div>

<!-- 대여 장비: 추가/편집 모달 -->
<div class="modal-overlay" id="rentalItemModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="rentalItemTitle">＋ 장비 추가</div>
            <button class="modal-close" onclick="closeModal('rentalItemModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">장비명 *</div>
            <input class="field-input" id="riName">
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">시리얼 번호</div>
                <input class="field-input" id="riSerial">
            </div>
            <div class="field-group">
                <div class="field-label">카테고리</div>
                <select class="field-select" id="riCategory"><option value="">없음</option></select>
            </div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">원래 위치 (반납 시 복귀)</div>
                <select class="field-select" id="riHomeTarget"><option value="">없음</option></select>
            </div>
            <div class="field-group">
                <div class="field-label">그룹</div>
                <select class="field-select" id="riGroup"><option value="">없음</option></select>
            </div>
        </div>
        <div class="field-group">
            <div class="field-label" style="display:flex; align-items:center; gap:8px;">구성품
                <button type="button" class="btn-sm" id="riCompAddBtn" onclick="riAddComp()" style="padding:2px 10px; font-size:11.5px;">＋ 구성품 추가</button>
                <span class="text-muted" style="font-size:11px;">이동할 때마다 구성품 체크로 동행 여부를 추적합니다</span>
            </div>
            <div id="riCompList"></div>
        </div>
        <div class="field-group">
            <div class="field-label">제품 설명 / 비고</div>
            <textarea class="field-input" id="riDesc" rows="2"></textarea>
        </div>
        <div class="field-group" id="riQrWrap" style="display:none;">
            <div class="field-label">QR 코드 (이 장비 식별용)</div>
            <div style="background:#fff; padding:8px; border-radius:8px; display:inline-block;">
                <img id="riQrImg" alt="QR" style="display:block; width:160px; height:160px;">
            </div>
            <div class="text-muted" style="font-size:11px; margin-top:4px;">인쇄 후 장비에 부착 · 📷 스캔으로 인식</div>
        </div>
        <input type="hidden" id="riId">
        <div class="modal-actions">
            <button class="btn-danger-sm" id="riDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteRentalItem()">삭제</button>
            <button class="btn-cancel" onclick="closeModal('rentalItemModal')">취소</button>
            <button class="btn-save" onclick="saveRentalItem()">저장</button>
        </div>
    </div>
</div>

<!-- 대여 대상: 추가/편집 모달 -->
<div class="modal-overlay" id="rentalTargetModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="rentalTargetTitle">＋ 사용 대상 추가</div>
            <button class="modal-close" onclick="closeModal('rentalTargetModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">이름 / 명칭 *</div>
            <input class="field-input" id="rtName">
        </div>
        <div class="field-group">
            <div class="field-label">연락처</div>
            <input class="field-input" id="rtPhone">
        </div>
        <div class="field-group">
            <div class="field-label">주소 / 장소</div>
            <textarea class="field-input" id="rtAddress" rows="2"></textarea>
        </div>
        <div class="field-group">
            <div class="field-label">메모</div>
            <textarea class="field-input" id="rtNote" rows="2"></textarea>
        </div>
        <input type="hidden" id="rtId">
        <div id="rtQrWrap" style="display:none; margin-top:14px; padding:14px; background:var(--surface2); border-radius:10px; text-align:center;">
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:8px;">직원 QR 코드</div>
            <img id="rtQrImg" style="width:160px; height:160px; border-radius:8px; background:#fff;">
            <div style="margin-top:8px;">
                <button onclick="printTargetQr()" style="background:none; border:1px solid var(--border); color:var(--text-muted); padding:6px 14px; border-radius:6px; font-size:11px; cursor:pointer;"><x-icon name="note" :size="13"/> 인쇄</button>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-danger-sm" id="rtDeleteBtn" style="margin-right:auto; display:none;" onclick="deleteRentalTarget()">삭제</button>
            <button class="btn-cancel" onclick="closeModal('rentalTargetModal')">취소</button>
            <button class="btn-save" onclick="saveRentalTarget()">저장</button>
        </div>
    </div>
</div>

<!-- 카테고리 관리 모달 -->
<div class="modal-overlay" id="categoryManageModal">
    <div class="modal" style="width:520px;">
        <div class="modal-header">
            <div class="modal-title"><x-icon name="tag" :size="16"/> 카테고리 관리</div>
            <button class="modal-close" onclick="closeModal('categoryManageModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">새 카테고리 추가</div>
            <div style="display:flex; gap:6px;">
                <input class="field-input" id="newCategoryName" style="flex:1;">
                <button class="btn-save" onclick="saveNewCategory()">추가</button>
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">카테고리 목록</div>
            <div id="categoryList" style="display:flex; flex-direction:column; gap:6px;"></div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('categoryManageModal')">닫기</button>
        </div>
    </div>
</div>

<!-- 그룹 관리 모달 -->
<div class="modal-overlay" id="groupManageModal">
    <div class="modal" style="width:520px;">
        <div class="modal-header">
            <div class="modal-title"><x-icon name="box" :size="16"/> 그룹 관리</div>
            <button class="modal-close" onclick="closeModal('groupManageModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">새 그룹 추가</div>
            <div style="display:flex; gap:6px;">
                <input class="field-input" id="newGroupName" style="flex:1;">
                <button class="btn-save" onclick="saveNewGroup()">추가</button>
            </div>
        </div>
        <div class="field-group">
            <div class="field-label">그룹 목록 (장비 수 / 일괄 이동)</div>
            <div id="groupList" style="display:flex; flex-direction:column; gap:6px;"></div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('groupManageModal')">닫기</button>
        </div>
    </div>
</div>

<!-- 그룹 일괄 이동 모달 -->
<div class="modal-overlay" id="groupAssignModal">
    <div class="modal" style="width:420px;">
        <div class="modal-header">
            <div class="modal-title" id="gaTitle">그룹 일괄 이동</div>
            <button class="modal-close" onclick="closeModal('groupAssignModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">이동할 대상</div>
            <select class="field-select" id="gaTarget"><option value="">선택하세요</option></select>
        </div>
        <input type="hidden" id="gaGroupId">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="gaReturnAll()" style="margin-right:auto;">원래 위치로 반납</button>
            <button class="btn-cancel" onclick="closeModal('groupAssignModal')">취소</button>
            <button class="btn-save" onclick="gaAssign()">이동</button>
        </div>
    </div>
</div>

<!-- QR 스캐너 모달 -->
<div class="modal-overlay" id="qrScanModal">
    <div class="modal" style="width:420px;">
        <div class="modal-header">
            <div class="modal-title"><x-icon name="qr" :size="16"/> QR 스캔</div>
            <button class="modal-close" onclick="stopQrScan(true)">×</button>
        </div>
        <div id="qrScanReader" style="width:100%; background:#000; border-radius:8px; overflow:hidden; min-height:260px;"></div>
        <div style="display:flex; align-items:flex-start; gap:8px; margin-top:6px;">
            <div class="text-muted" style="font-size:11px; flex:1;" id="qrScanHint">장비 QR 코드를 카메라에 비춰주세요. 카메라 권한이 필요합니다.</div>
            <button type="button" class="btn-cancel btn-sm" id="qrSwitchBtn" style="display:none; white-space:nowrap;" onclick="switchQrCamera()">카메라 전환</button>
        </div>
        <div class="field-group" style="margin-top:10px;">
            <div class="field-label">수동 입력 (테스트)</div>
            <div style="display:flex; gap:6px;">
                <input class="field-input" id="qrManual" placeholder="예: rental:3 또는 3" style="flex:1;">
                <button class="btn-cancel" onclick="handleScanResult(document.getElementById('qrManual').value)">확인</button>
            </div>
        </div>
    </div>
</div>

<!-- QR 스캔 결과 액션 모달 -->
<div class="modal-overlay" id="scanActionModal">
    <div class="modal" style="width:420px;">
        <div class="modal-header">
            <div class="modal-title">장비 액션</div>
            <button class="modal-close" onclick="closeModal('scanActionModal')">×</button>
        </div>
        <div id="scanActionInfo" style="font-size:13px; line-height:1.6; color:var(--text-muted); margin-bottom:16px;"></div>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <button class="btn-save" onclick="scanActionReturn()">반납 처리</button>
            <button class="btn-cancel" onclick="scanActionDetail()">상세 보기 (편집)</button>
            <button class="btn-cancel" onclick="scanActionMove()">다른 위치로 이동</button>
        </div>
        <input type="hidden" id="scanActionItemId">
    </div>
</div>

<!-- 단일 장비 이동 모달 -->
<div class="modal-overlay" id="moveItemModal">
    <div class="modal" style="width:400px;">
        <div class="modal-header">
            <div class="modal-title" id="miTitle">위치 이동</div>
            <button class="modal-close" onclick="closeModal('moveItemModal')">×</button>
        </div>
        <div class="field-group">
            <div class="field-label">이동할 대상</div>
            <select class="field-select" id="miTarget"><option value="">선택하세요</option></select>
        </div>
        <input type="hidden" id="miItemId">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('moveItemModal')">취소</button>
            <button class="btn-save" onclick="miAssign()">이동</button>
        </div>
    </div>
</div>

<!-- 구성품 확인 모달 — 이동/반납 시 함께 가는 구성품 체크 -->
<div class="modal-overlay" id="compMoveModal">
    <div class="modal" style="width:440px;">
        <div class="modal-header">
            <div class="modal-title">구성품 확인</div>
            <button class="modal-close" onclick="closeModal('compMoveModal')">×</button>
        </div>
        <div id="cmInfo" style="font-size:13px; line-height:1.6; margin-bottom:10px;"></div>
        <div style="display:flex; gap:8px; margin-bottom:8px;">
            <button type="button" class="btn-cancel btn-sm" onclick="cmSetAll(true)">전체선택</button>
            <button type="button" class="btn-cancel btn-sm" onclick="cmSetAll(false)">전체선택 해제</button>
            <span class="text-muted" style="font-size:11.5px; align-self:center;">체크한 구성품만 함께 이동합니다</span>
        </div>
        <div id="cmList" style="max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:4px;"></div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('compMoveModal')">취소</button>
            <button class="btn-save" onclick="cmConfirm()">이동</button>
        </div>
    </div>
</div>

<!-- 셀 액션 모달 -->
<div class="modal-overlay" id="bdCellModal">
    <div class="modal" style="width:400px;">
        <div class="modal-header">
            <div class="modal-title" id="bdCellTitle">위치 지정</div>
            <button class="modal-close" onclick="closeModal('bdCellModal')">×</button>
        </div>
        <div id="bdCellInfo" style="font-size:13px; line-height:1.6; color:var(--text-muted);"></div>
        <div class="modal-actions">
            <button class="btn-cancel" id="bdCellClearBtn" style="margin-right:auto; display:none;">반납 처리</button>
            <button class="btn-cancel" onclick="closeModal('bdCellModal')">취소</button>
            <button class="btn-save" id="bdCellAssignBtn">이 위치로 지정</button>
        </div>
    </div>
</div>

<!-- 로그 패널 -->
<div class="eq-log-panel" id="bdLogPanel">
    <div class="eq-log-head">
        <span class="title"><x-icon name="clip" :size="16"/> 변경 이력</span>
        <button class="modal-close" onclick="document.getElementById('bdLogPanel').classList.remove('open')">×</button>
    </div>
    <div class="eq-log-body" id="bdLogBody">
        <div class="empty-row">아직 기록된 변경이 없습니다.</div>
    </div>
</div>

<div class="eq-toast" id="bdToast"></div>
@endsection

@push('scripts')
<script src="/vendor/html5-qrcode.min.js" onerror="this.remove(); const s=document.createElement('script'); s.src='https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'; document.head.appendChild(s);"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const H = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'};

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function fmtTime(d) { return d ? new Date(d).toLocaleString('ko-KR',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '-'; }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (qrScanner) stopQrScan(false);
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
        document.getElementById('bdLogPanel')?.classList.remove('open');
    }
});
document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) closeModal(ov.id); });
});

// === 장비 현황판 ===
const bdState = { items:[], targets:[], groups:[], categories:[], assignments:{}, logs:[] };
let qrScanner = null;
const bdDrag = { active:false, justDragged:false, itemId:null, fromTargetId:null, ghost:null, sourceCell:null, currentDropEl:null, startX:0, startY:0, pointerId:null };

function bdEsc(s) {
    if (s==null) return '';
    return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function bdToast(msg) {
    const t = document.getElementById('bdToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(bdToast._t);
    bdToast._t = setTimeout(()=>t.classList.remove('show'), 2200);
}

async function loadBoard() {
    const res = await fetch('/api/rental/board');
    const data = await res.json();
    bdState.items = data.items || [];
    bdState.targets = data.targets || [];
    bdState.groups = data.groups || [];
    bdState.categories = data.categories || [];
    bdState.assignments = data.assignments || {};
    bdState.logs = data.logs || [];
    bdRender();
    bdRenderLogs();
}

function bdFilteredItems() {
    const q = (document.getElementById('bdSearch').value || '').trim().toLowerCase();
    if (!q) return bdState.items;
    return bdState.items.filter(it => {
        const tg = bdState.targets.find(t => t.id === bdState.assignments[it.id]);
        const cat = bdState.categories.find(c => c.id === it.category_id);
        return (it.name||'').toLowerCase().includes(q)
            || (it.serial||'').toLowerCase().includes(q)
            || (cat && (cat.name||'').toLowerCase().includes(q))
            || (tg && (tg.name||'').toLowerCase().includes(q));
    });
}

function bdRender() {
    const board = document.getElementById('bdBoard');
    const items = bdFilteredItems();
    const targets = bdState.targets;
    const totalCols = targets.length + 1;
    const colWidth = 120, firstColWidth = 200;
    board.style.gridTemplateColumns = `${firstColWidth}px repeat(${totalCols}, minmax(${colWidth}px, 1fr))`;

    let html = `<div class="eq-cell-base eq-corner">장비 \\ 대상 →</div>`;
    targets.forEach(t => {
        html += `<div class="eq-cell-base eq-col-header" data-target-id="${t.id}">
            <div class="ch-name">${bdEsc(t.name)}</div>
            ${t.phone ? `<div class="ch-sub">${bdEsc(t.phone)}</div>` : ''}
        </div>`;
    });
    html += `<div class="eq-cell-base eq-col-header empty" data-add-target="1">＋ 대상 추가</div>`;

    items.forEach(item => {
        // 다른 위치에 남겨진 구성품 경고 — 이름 옆 Red 배지
        const leftComps = (item.component_items||[]).filter(c=>c.left_target_id);
        const warn = leftComps.length ? `<span class="rh-left-warn" title="구성품 ${leftComps.length}개가 다른 위치에 남아 있음: ${bdEsc(leftComps.map(c=>c.name).join(', '))}">⚠${leftComps.length}</span>` : '';
        html += `<div class="eq-cell-base eq-row-header" data-item-id="${item.id}">
            <div class="rh-name">${bdEsc(item.name)}${warn}</div>
            ${item.serial ? `<div class="rh-serial">${bdEsc(item.serial)}</div>` : ''}
        </div>`;
        targets.forEach(t => {
            const marked = bdState.assignments[item.id] === t.id;
            html += `<div class="eq-cell-base eq-matrix-cell ${marked?'marked':''}" data-item-id="${item.id}" data-target-id="${t.id}">
                ${marked ? '<div class="eq-o-mark">●</div>' : ''}
            </div>`;
        });
        html += `<div class="eq-cell-base eq-matrix-cell" style="cursor:default;"></div>`;
    });

    html += `<div class="eq-cell-base eq-row-header empty" data-add-item="1">＋ 장비 추가</div>`;
    for (let i = 0; i < totalCols; i++) {
        html += `<div class="eq-cell-base eq-matrix-cell" style="cursor:default;"></div>`;
    }

    board.innerHTML = html;

    document.getElementById('bdStatItems').textContent = bdState.items.length;
    document.getElementById('bdStatTargets').textContent = bdState.targets.length;
    document.getElementById('bdStatInUse').textContent = Object.keys(bdState.assignments).length;

    bdBindEvents();
}

function bdBindEvents() {
    document.querySelectorAll('#bdBoard .eq-row-header[data-item-id]').forEach(el => {
        el.addEventListener('click', () => openRentalItemModal(+el.dataset.itemId));
    });
    document.querySelectorAll('#bdBoard .eq-row-header[data-add-item]').forEach(el => {
        el.addEventListener('click', () => openRentalItemModal(null));
    });
    document.querySelectorAll('#bdBoard .eq-col-header[data-target-id]').forEach(el => {
        el.addEventListener('click', () => openRentalTargetModal(+el.dataset.targetId));
    });
    document.querySelectorAll('#bdBoard .eq-col-header[data-add-target]').forEach(el => {
        el.addEventListener('click', () => openRentalTargetModal(null));
    });
    document.querySelectorAll('#bdBoard .eq-matrix-cell[data-item-id][data-target-id]').forEach(el => {
        el.addEventListener('click', () => {
            if (bdDrag.justDragged) { bdDrag.justDragged = false; return; }
            bdOpenCellModal(+el.dataset.itemId, +el.dataset.targetId);
        });
    });
    document.querySelectorAll('#bdBoard .eq-o-mark').forEach(mark => {
        mark.addEventListener('pointerdown', bdOnMarkDown);
    });
}

// ── 드래그앤드롭 ──
function bdOnMarkDown(e) {
    e.preventDefault(); e.stopPropagation();
    const cell = e.currentTarget.closest('.eq-matrix-cell'); if (!cell) return;
    bdDrag.itemId = +cell.dataset.itemId;
    bdDrag.fromTargetId = +cell.dataset.targetId;
    bdDrag.startX = e.clientX; bdDrag.startY = e.clientY;
    bdDrag.active = false; bdDrag.sourceCell = cell; bdDrag.pointerId = e.pointerId;
    try { e.currentTarget.setPointerCapture(e.pointerId); } catch(_) {}
    document.addEventListener('pointermove', bdOnMove);
    document.addEventListener('pointerup', bdOnUp, {once:true});
    document.addEventListener('pointercancel', bdOnUp, {once:true});
}
function bdOnMove(e) {
    if (!bdDrag.active) {
        if (Math.abs(e.clientX-bdDrag.startX)<5 && Math.abs(e.clientY-bdDrag.startY)<5) return;
        bdStartDrag();
    }
    if (bdDrag.ghost) { bdDrag.ghost.style.left = e.clientX+'px'; bdDrag.ghost.style.top = e.clientY+'px'; }
    const el = document.elementFromPoint(e.clientX, e.clientY);
    const dropCell = el?.closest('.eq-matrix-cell.drop-target');
    if (bdDrag.currentDropEl && bdDrag.currentDropEl !== dropCell) { bdDrag.currentDropEl.classList.remove('drop-hover'); bdDrag.currentDropEl = null; }
    if (dropCell && dropCell !== bdDrag.currentDropEl) { dropCell.classList.add('drop-hover'); bdDrag.currentDropEl = dropCell; }
}
function bdStartDrag() {
    bdDrag.active = true;
    bdDrag.sourceCell.classList.add('dragging-source');
    document.body.classList.add('eq-dragging');
    document.querySelectorAll(`#bdBoard .eq-matrix-cell[data-item-id="${bdDrag.itemId}"]`).forEach(c => {
        if (c !== bdDrag.sourceCell) c.classList.add('drop-target');
    });
    const ghost = document.createElement('div');
    ghost.className = 'eq-drag-ghost'; ghost.textContent = '●';
    ghost.style.left = bdDrag.startX+'px'; ghost.style.top = bdDrag.startY+'px';
    document.body.appendChild(ghost); bdDrag.ghost = ghost;
}
async function bdOnUp(e) {
    document.removeEventListener('pointermove', bdOnMove);
    if (!bdDrag.active) { bdResetDrag(); return; }
    const el = document.elementFromPoint(e.clientX, e.clientY);
    const dropCell = el?.closest('.eq-matrix-cell.drop-target');
    if (dropCell) {
        const newTargetId = +dropCell.dataset.targetId;
        await bdAssign(bdDrag.itemId, newTargetId, '드래그로 이동');
    }
    bdDrag.justDragged = true;
    bdResetDrag();
    setTimeout(()=>{ bdDrag.justDragged = false; }, 50);
}
function bdResetDrag() {
    document.body.classList.remove('eq-dragging');
    if (bdDrag.sourceCell) bdDrag.sourceCell.classList.remove('dragging-source');
    document.querySelectorAll('.eq-matrix-cell.drop-target').forEach(c => c.classList.remove('drop-target','drop-hover'));
    if (bdDrag.ghost) { bdDrag.ghost.remove(); bdDrag.ghost = null; }
    bdDrag.active = false; bdDrag.itemId = null; bdDrag.fromTargetId = null; bdDrag.sourceCell = null; bdDrag.currentDropEl = null;
}

// ── 셀 액션 ──
let bdCellCtx = { itemId:null, targetId:null };
function bdOpenCellModal(itemId, targetId) {
    bdCellCtx = { itemId, targetId };
    const item = bdState.items.find(i=>i.id===itemId);
    const target = bdState.targets.find(t=>t.id===targetId);
    const currentTargetId = bdState.assignments[itemId];
    const currentTarget = currentTargetId ? bdState.targets.find(t=>t.id===currentTargetId) : null;
    const isCurrentlyHere = currentTargetId === targetId;

    document.getElementById('bdCellTitle').textContent = isCurrentlyHere ? '현재 위치' : '위치 지정';
    document.getElementById('bdCellInfo').innerHTML = `
        <div style="margin-bottom:10px;">
            <div style="font-size:11px;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">장비</div>
            <div style="color:var(--text);font-weight:600;">${bdEsc(item.name)} ${item.serial?`<span style="font-family:'SF Mono',Menlo,monospace;font-size:11px;color:var(--text-muted);">· ${bdEsc(item.serial)}</span>`:''}</div>
        </div>
        <div style="margin-bottom:10px;">
            <div style="font-size:11px;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">현재 위치</div>
            <div style="color:var(--text);">${currentTarget ? bdEsc(currentTarget.name) : '<span style="color:var(--text-muted);">미지정</span>'}</div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">${isCurrentlyHere ? '현재 위치' : '새 위치'}</div>
            <div style="color:var(--accent);font-weight:600;">${bdEsc(target.name)}</div>
        </div>
    `;
    const assignBtn = document.getElementById('bdCellAssignBtn');
    const clearBtn = document.getElementById('bdCellClearBtn');
    if (isCurrentlyHere) {
        assignBtn.style.display = 'none';
        clearBtn.style.display = 'inline-block';
    } else {
        assignBtn.style.display = 'inline-block';
        clearBtn.style.display = currentTargetId ? 'inline-block' : 'none';
    }
    openModal('bdCellModal');
}
document.getElementById('bdCellAssignBtn').addEventListener('click', async () => {
    await bdAssign(bdCellCtx.itemId, bdCellCtx.targetId, '매트릭스에서 지정');
    closeModal('bdCellModal');
});
document.getElementById('bdCellClearBtn').addEventListener('click', async () => {
    await bdClear(bdCellCtx.itemId, '매트릭스에서 반납');
    closeModal('bdCellModal');
});

async function bdAssign(itemId, targetId, memo) {
    // 구성품이 있는 장비는 이동 전에 동행 구성품 확인 모달을 거친다
    const item = bdState.items.find(i=>i.id===itemId);
    if ((item?.component_items || []).length) { openCompMoveModal(itemId, targetId, false, memo); return; }
    await bdDoAssign(itemId, targetId, false, memo, null);
}
async function bdClear(itemId, memo) {
    const item = bdState.items.find(i=>i.id===itemId);
    if ((item?.component_items || []).length) { openCompMoveModal(itemId, null, true, memo); return; }
    await bdDoAssign(itemId, null, true, memo, null);
}
async function bdDoAssign(itemId, targetId, isReturn, memo, movedIdx) {
    const body = isReturn
        ? { item_id: itemId, return: true, memo: memo || null }
        : { item_id: itemId, target_id: targetId, memo: memo || null };
    if (movedIdx !== null) body.components_moved = movedIdx;
    const res = await fetch('/api/rental/assign', {method:'POST', headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || Object.values(e.errors||{}).flat().join('\n') || '오류'); return; }
    const item = bdState.items.find(i=>i.id===itemId);
    if (isReturn) {
        const home = item?.home_target_id ? bdState.targets.find(t=>t.id===item.home_target_id) : null;
        bdToast(home ? `${item?.name||''} → ${home.name} (원위치)` : `${item?.name||''} 반납 처리`);
    } else {
        const target = bdState.targets.find(t=>t.id===targetId);
        bdToast(`${item?.name||''} → ${target?.name||''}`);
    }
    await loadBoard();
}

// === 구성품 확인 모달 — 이동/반납 시 함께 가는 구성품 체크 ===
let cmCtx = { itemId:null, targetId:null, isReturn:false, memo:null };
function openCompMoveModal(itemId, targetId, isReturn, memo) {
    cmCtx = { itemId, targetId, isReturn, memo };
    const item = bdState.items.find(i=>i.id===itemId);
    const comps = item?.component_items || [];
    const destId = isReturn ? item?.home_target_id : targetId;
    const dest = destId ? bdState.targets.find(t=>t.id===destId) : null;
    document.getElementById('cmInfo').innerHTML = `
        <b>${bdEsc(item?.name||'')}</b> → <span style="color:var(--accent);font-weight:600;">${dest?bdEsc(dest.name):(isReturn?'반납':'미지정')}</span>
        <div class="text-muted" style="font-size:11.5px;">함께 이동하는 구성품을 확인하세요. 체크하지 않은 구성품은 ${isReturn?'현재':'이전'} 위치에 남은 것으로 기록됩니다.</div>`;
    document.getElementById('cmList').innerHTML = comps.map((c, i) => {
        const leftAt = c.left_target_id ? bdState.targets.find(t=>t.id===c.left_target_id) : null;
        // 이전 이동에서 남겨진 구성품 — Red 하이라이트 + 마지막 위치 표시, 기본 체크 해제
        return `<label style="display:flex; align-items:center; gap:8px; padding:7px 10px; border:1px solid ${leftAt?'var(--red)':'var(--border)'}; border-radius:8px; cursor:pointer; ${leftAt?'background:rgba(212,110,110,0.08);':''}">
            <input type="checkbox" name="cmComp" value="${i}" ${leftAt?'':'checked'} style="width:14px; height:14px;">
            <span style="font-size:13px; ${leftAt?'color:var(--red); font-weight:600;':''}">${bdEsc(c.name)}</span>
            ${leftAt?`<span style="margin-left:auto; font-size:11px; color:var(--red);">마지막 위치: ${bdEsc(leftAt.name)}</span>`:''}
        </label>`;
    }).join('');
    openModal('compMoveModal');
}
function cmSetAll(on) {
    document.querySelectorAll('input[name=cmComp]').forEach(c => c.checked = on);
}
async function cmConfirm() {
    const item = bdState.items.find(i=>i.id===cmCtx.itemId);
    const comps = item?.component_items || [];
    const moved = [...document.querySelectorAll('input[name=cmComp]:checked')].map(c => +c.value);
    // 누락 구성품 팝업 — 이번에 새로 남겨지는 것 + 기존에 다른 곳에 남아있는 것 구분 안내
    const missing = comps.map((c, i) => ({...c, i})).filter(c => !moved.includes(c.i));
    if (missing.length) {
        const curTarget = bdState.targets.find(t=>t.id===bdState.assignments[cmCtx.itemId]);
        const lines = missing.map(c => {
            const leftAt = c.left_target_id ? bdState.targets.find(t=>t.id===c.left_target_id) : null;
            return `· ${c.name} — ${leftAt?`마지막 위치 유지 (${leftAt.name})`:`${curTarget?curTarget.name:'이전 위치'}에 남음`}`;
        });
        if (!confirm(`구성품 ${missing.length}개가 함께 이동하지 않습니다.\n\n${lines.join('\n')}\n\n계속할까요?`)) return;
    }
    closeModal('compMoveModal');
    await bdDoAssign(cmCtx.itemId, cmCtx.targetId, cmCtx.isReturn, cmCtx.memo, moved);
}

// === 장비 모달 ===
// 구성품 편집기 상태 — [{name, left_target_id}] (left_target_id: 이동 시 남겨진 마지막 위치)
let riCompState = [];
function riRenderComps() {
    const wrap = document.getElementById('riCompList');
    if (!riCompState.length) {
        wrap.innerHTML = '<div class="text-muted" style="font-size:12px; padding:4px 0;">구성품이 없습니다. [＋ 구성품 추가]로 항목을 등록하세요.</div>';
        return;
    }
    wrap.innerHTML = riCompState.map((c, i) => {
        const leftAt = c.left_target_id ? bdState.targets.find(t=>t.id===c.left_target_id) : null;
        return `<div style="display:flex; align-items:center; gap:6px; margin-bottom:5px;">
            <input class="field-input" style="flex:1; ${leftAt?'border-color:var(--red); color:var(--red);':''}" value="${bdEsc(c.name)}" placeholder="구성품 이름 (예: 전원 케이블)" oninput="riCompState[${i}].name=this.value">
            ${leftAt?`<span style="font-size:11px; color:var(--red); white-space:nowrap;">마지막 위치: ${bdEsc(leftAt.name)}</span>
                <button type="button" class="btn-cancel btn-sm" onclick="riCompState[${i}].left_target_id=null; riRenderComps()" title="본체와 함께 있는 것으로 표시">회수됨</button>`:''}
            <button type="button" class="btn-cancel btn-sm" onclick="riCompState.splice(${i},1); riRenderComps()" title="구성품 삭제">×</button>
        </div>`;
    }).join('');
}
function riAddComp() {
    riCompState.push({ name: '', left_target_id: null });
    riRenderComps();
    const inputs = document.querySelectorAll('#riCompList input.field-input');
    inputs[inputs.length-1]?.focus();
}

function openRentalItemModal(itemId) {
    const isEdit = !!itemId;
    const it = isEdit ? bdState.items.find(i=>i.id===itemId) : null;
    document.getElementById('rentalItemTitle').textContent = isEdit ? '장비 편집' : '＋ 장비 추가';
    document.getElementById('riId').value = itemId || '';
    document.getElementById('riName').value = it?.name || '';
    document.getElementById('riSerial').value = it?.serial || '';
    // 구성품 편집기 — 항목 단위 추가/삭제, 남겨진 구성품은 Red + 마지막 위치 표시
    riCompState = (it?.component_items || []).map(c => ({ name: c.name, left_target_id: c.left_target_id ?? null }));
    riRenderComps();
    document.getElementById('riDesc').value = it?.description || '';

    const catSel = document.getElementById('riCategory');
    catSel.innerHTML = '<option value="">없음</option>' + bdState.categories.map(c=>`<option value="${c.id}">${bdEsc(c.name)}</option>`).join('');
    catSel.value = it?.category_id || '';

    const homeSel = document.getElementById('riHomeTarget');
    homeSel.innerHTML = '<option value="">없음</option>' + bdState.targets.map(t=>`<option value="${t.id}">${bdEsc(t.name)}</option>`).join('');
    homeSel.value = it?.home_target_id || '';

    const grpSel = document.getElementById('riGroup');
    grpSel.innerHTML = '<option value="">없음</option>' + bdState.groups.map(g=>`<option value="${g.id}">${bdEsc(g.name)}</option>`).join('');
    grpSel.value = it?.group_id || '';

    const qrWrap = document.getElementById('riQrWrap');
    if (isEdit) {
        qrWrap.style.display = 'block';
        document.getElementById('riQrImg').src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent('rental:'+itemId)}&size=200x200&margin=8`;
    } else {
        qrWrap.style.display = 'none';
    }

    document.getElementById('riDeleteBtn').style.display = isEdit ? 'inline-block' : 'none';
    openModal('rentalItemModal');
    setTimeout(()=>document.getElementById('riName').focus(), 50);
}
async function saveRentalItem() {
    const id = document.getElementById('riId').value;
    const body = {
        name: document.getElementById('riName').value.trim(),
        serial: document.getElementById('riSerial').value.trim() || null,
        category_id: document.getElementById('riCategory').value ? +document.getElementById('riCategory').value : null,
        component_items: riCompState.map(c => ({ name: (c.name||'').trim(), left_target_id: c.left_target_id ?? null })).filter(c => c.name),
        description: document.getElementById('riDesc').value.trim() || null,
        home_target_id: document.getElementById('riHomeTarget').value ? +document.getElementById('riHomeTarget').value : null,
        group_id: document.getElementById('riGroup').value ? +document.getElementById('riGroup').value : null,
    };
    if (!body.name) { bdToast('장비명은 필수입니다.'); return; }
    const url = id ? `/api/rental/items/${id}` : '/api/rental/items';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {method, headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(Object.values(e.errors||{}).flat().join('\n') || e.message || '오류'); return; }
    closeModal('rentalItemModal');
    bdToast(id ? '장비 정보가 수정되었습니다.' : '새 장비가 추가되었습니다.');
    await loadBoard();
}
async function deleteRentalItem() {
    const id = document.getElementById('riId').value;
    if (!id) return;
    const it = bdState.items.find(i=>i.id===+id);
    if (!confirm(`"${it?.name}" 장비를 삭제하시겠습니까?`)) return;
    const res = await fetch(`/api/rental/items/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    closeModal('rentalItemModal');
    bdToast('장비가 삭제되었습니다.');
    await loadBoard();
}

// === 대상 모달 ===
function openRentalTargetModal(targetId) {
    const isEdit = !!targetId;
    const tg = isEdit ? bdState.targets.find(t=>t.id===targetId) : null;
    document.getElementById('rentalTargetTitle').textContent = isEdit ? '사용 대상 편집' : '＋ 사용 대상 추가';
    document.getElementById('rtId').value = targetId || '';
    document.getElementById('rtName').value = tg?.name || '';
    document.getElementById('rtPhone').value = tg?.phone || '';
    document.getElementById('rtAddress').value = tg?.address || '';
    document.getElementById('rtNote').value = tg?.note || '';
    document.getElementById('rtDeleteBtn').style.display = isEdit ? 'inline-block' : 'none';
    // QR 코드
    const qrWrap = document.getElementById('rtQrWrap');
    if (isEdit) {
        qrWrap.style.display = 'block';
        document.getElementById('rtQrImg').src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent('staff:'+targetId)}&size=200x200&margin=8`;
    } else {
        qrWrap.style.display = 'none';
    }
    openModal('rentalTargetModal');
    setTimeout(()=>document.getElementById('rtName').focus(), 50);
}
function printTargetQr() {
    const id = document.getElementById('rtId').value;
    const name = document.getElementById('rtName').value;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent('staff:'+id)}&size=300x300&margin=10`;
    const w = window.open('','_blank','width=400,height=500');
    w.document.write(`<html><head><title>QR - ${name}</title><style>body{font-family:sans-serif;text-align:center;padding:40px;}h2{margin-bottom:8px;}p{color:#888;font-size:14px;margin-bottom:20px;}img{border:1px solid #ddd;border-radius:8px;}@media print{button{display:none;}}</style></head><body><h2>${name}</h2><p>staff:${id}</p><img src="${qrUrl}" width="250" height="250"><br><br><button onclick="window.print()" style="padding:10px 24px;font-size:14px;cursor:pointer;">인쇄</button></body></html>`);
    w.document.close();
}
async function saveRentalTarget() {
    const id = document.getElementById('rtId').value;
    const body = {
        name: document.getElementById('rtName').value.trim(),
        phone: document.getElementById('rtPhone').value.trim() || null,
        address: document.getElementById('rtAddress').value.trim() || null,
        note: document.getElementById('rtNote').value.trim() || null,
    };
    if (!body.name) { bdToast('이름은 필수입니다.'); return; }
    const url = id ? `/api/rental/targets/${id}` : '/api/rental/targets';
    const method = id ? 'PATCH' : 'POST';
    const res = await fetch(url, {method, headers:H, body:JSON.stringify(body)});
    if (!res.ok) { const e = await res.json(); bdToast(Object.values(e.errors||{}).flat().join('\n') || e.message || '오류'); return; }
    closeModal('rentalTargetModal');
    bdToast(id ? '대상 정보가 수정되었습니다.' : '새 대상이 추가되었습니다.');
    await loadBoard();
}
async function deleteRentalTarget() {
    const id = document.getElementById('rtId').value;
    if (!id) return;
    const tg = bdState.targets.find(t=>t.id===+id);
    if (!confirm(`"${tg?.name}" 대상을 삭제하시겠습니까?\n이 대상에 지정된 장비는 위치가 해제됩니다.`)) return;
    const res = await fetch(`/api/rental/targets/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    closeModal('rentalTargetModal');
    bdToast('대상이 삭제되었습니다.');
    await loadBoard();
}

// === 카테고리 관리 ===
document.getElementById('bdCategoryBtn').addEventListener('click', () => { renderCategoryList(); openModal('categoryManageModal'); });
function renderCategoryList() {
    const wrap = document.getElementById('categoryList');
    if (!bdState.categories.length) { wrap.innerHTML = '<div class="text-muted" style="padding:10px 0;">등록된 카테고리가 없습니다.</div>'; return; }
    wrap.innerHTML = bdState.categories.map(c => {
        const count = bdState.items.filter(i => i.category_id === c.id).length;
        return `<div style="display:flex; align-items:center; gap:6px; padding:8px 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px;">
            <span style="flex:1; font-weight:600;">${bdEsc(c.name)}</span>
            <span class="text-muted" style="font-size:11px;">장비 ${count}개</span>
            <button class="btn-outline btn-sm" onclick="renameCategory(${c.id})">이름</button>
            <button class="btn-danger-sm" onclick="deleteCategory(${c.id})">삭제</button>
        </div>`;
    }).join('');
}
async function saveNewCategory() {
    const name = document.getElementById('newCategoryName').value.trim();
    if (!name) { bdToast('카테고리명을 입력하세요.'); return; }
    const res = await fetch('/api/rental/categories', {method:'POST', headers:H, body:JSON.stringify({name})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    document.getElementById('newCategoryName').value = '';
    await loadBoard();
    renderCategoryList();
}
async function renameCategory(id) {
    const c = bdState.categories.find(x=>x.id===id);
    const name = prompt('카테고리명', c?.name || '');
    if (!name || !name.trim()) return;
    const res = await fetch(`/api/rental/categories/${id}`, {method:'PATCH', headers:H, body:JSON.stringify({name: name.trim()})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    await loadBoard();
    renderCategoryList();
}
async function deleteCategory(id) {
    const c = bdState.categories.find(x=>x.id===id);
    if (!confirm(`"${c?.name}" 카테고리를 삭제할까요?\n소속 장비의 카테고리 소속만 해제됩니다.`)) return;
    const res = await fetch(`/api/rental/categories/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    await loadBoard();
    renderCategoryList();
}

// === 그룹 관리 ===
document.getElementById('bdGroupBtn').addEventListener('click', () => { renderGroupList(); openModal('groupManageModal'); });
function renderGroupList() {
    const wrap = document.getElementById('groupList');
    if (!bdState.groups.length) { wrap.innerHTML = '<div class="text-muted" style="padding:10px 0;">등록된 그룹이 없습니다.</div>'; return; }
    wrap.innerHTML = bdState.groups.map(g => {
        const count = bdState.items.filter(i => i.group_id === g.id).length;
        return `<div style="display:flex; align-items:center; gap:6px; padding:8px 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px;">
            <span style="flex:1; font-weight:600;">${bdEsc(g.name)}</span>
            <span class="text-muted" style="font-size:11px;">장비 ${count}개</span>
            <button class="btn-outline btn-sm" onclick="openGroupAssignModal(${g.id})">이동</button>
            <button class="btn-outline btn-sm" onclick="renameGroup(${g.id})">이름</button>
            <button class="btn-danger-sm" onclick="deleteGroup(${g.id})">삭제</button>
        </div>`;
    }).join('');
}
async function saveNewGroup() {
    const name = document.getElementById('newGroupName').value.trim();
    if (!name) { bdToast('그룹명을 입력하세요.'); return; }
    const res = await fetch('/api/rental/groups', {method:'POST', headers:H, body:JSON.stringify({name})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    document.getElementById('newGroupName').value = '';
    await loadBoard();
    renderGroupList();
}
async function renameGroup(id) {
    const g = bdState.groups.find(x=>x.id===id);
    const name = prompt('그룹명', g?.name || '');
    if (!name || !name.trim()) return;
    const res = await fetch(`/api/rental/groups/${id}`, {method:'PATCH', headers:H, body:JSON.stringify({name: name.trim()})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    await loadBoard();
    renderGroupList();
}
async function deleteGroup(id) {
    const g = bdState.groups.find(x=>x.id===id);
    if (!confirm(`"${g?.name}" 그룹을 삭제할까요?\n소속 장비의 그룹 소속만 해제됩니다.`)) return;
    const res = await fetch(`/api/rental/groups/${id}`, {method:'DELETE', headers:H});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    await loadBoard();
    renderGroupList();
}
function openGroupAssignModal(groupId) {
    const g = bdState.groups.find(x=>x.id===groupId);
    const count = bdState.items.filter(i=>i.group_id===groupId).length;
    document.getElementById('gaTitle').textContent = `[${g.name}] 일괄 이동 (${count}개)`;
    document.getElementById('gaGroupId').value = groupId;
    document.getElementById('gaTarget').innerHTML = '<option value="">선택하세요</option>' + bdState.targets.map(t=>`<option value="${t.id}">${bdEsc(t.name)}</option>`).join('');
    openModal('groupAssignModal');
}
async function gaAssign() {
    const groupId = +document.getElementById('gaGroupId').value;
    const targetId = document.getElementById('gaTarget').value;
    if (!targetId) { bdToast('대상을 선택하세요.'); return; }
    const res = await fetch('/api/rental/assign-group', {method:'POST', headers:H, body:JSON.stringify({group_id: groupId, target_id: +targetId})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    const data = await res.json();
    bdToast(`${data.updated}개 장비 이동 완료`);
    closeModal('groupAssignModal'); closeModal('groupManageModal');
    await loadBoard();
}
async function gaReturnAll() {
    const groupId = +document.getElementById('gaGroupId').value;
    if (!confirm('그룹 내 모든 장비를 각자의 원래 위치로 반납할까요?')) return;
    const res = await fetch('/api/rental/assign-group', {method:'POST', headers:H, body:JSON.stringify({group_id: groupId, return: true})});
    if (!res.ok) { const e = await res.json(); bdToast(e.message || '오류'); return; }
    const data = await res.json();
    bdToast(`${data.updated}개 장비 반납 완료`);
    closeModal('groupAssignModal'); closeModal('groupManageModal');
    await loadBoard();
}

// === QR 스캔 ===
document.getElementById('bdScanBtn').addEventListener('click', () => startQrScan());
let qrCameras = [], qrCamIdx = -1; // 사용 가능한 카메라 목록 / 현재 사용 중 인덱스 (전환용)
function qrScanConfig() {
    // 화면 폭에 맞춘 스캔 박스 — 작은 폰에서 박스가 영상보다 커지면 인식이 안 됨
    return { fps: 10, qrbox: (w, h) => { const s = Math.floor(Math.min(w, h) * 0.75); return { width: s, height: s }; } };
}
async function startQrScan() {
    document.getElementById('qrManual').value = '';
    const hint = document.getElementById('qrScanHint');
    hint.style.color = '';
    hint.textContent = '카메라를 시작하는 중…';
    openModal('qrScanModal');
    if (typeof Html5Qrcode === 'undefined') {
        hint.style.color = 'var(--red)';
        hint.textContent = '스캐너 스크립트를 불러오지 못했습니다 — 네트워크 확인 후 새로고침하거나 아래 수동 입력을 사용하세요.';
        return;
    }
    try {
        qrScanner = new Html5Qrcode('qrScanReader');
        try {
            // 1차: 후면 카메라 우선
            await qrScanner.start({ facingMode: 'environment' }, qrScanConfig(), d => handleScanResult(d), () => {});
            qrCamIdx = -1;
        } catch (e1) {
            // 2차: 카메라 목록에서 후면으로 보이는 것(없으면 마지막)을 골라 재시도
            // — 일부 안드로이드(멀티 렌즈)에서 facingMode 지정이 실패하거나 엉뚱한 렌즈가 잡힘
            qrCameras = await Html5Qrcode.getCameras();
            if (!qrCameras.length) throw e1;
            const backIdx = qrCameras.findIndex(c => /back|rear|후면|environment/i.test(c.label || ''));
            qrCamIdx = backIdx >= 0 ? backIdx : qrCameras.length - 1;
            await qrScanner.start(qrCameras[qrCamIdx].id, qrScanConfig(), d => handleScanResult(d), () => {});
        }
        hint.textContent = '카메라 실행 중 — 장비 QR 코드를 박스 안에 비춰주세요.';
        // 카메라가 여러 개면 전환 버튼 노출 (엉뚱한 렌즈가 잡혔을 때 순환 전환)
        if (!qrCameras.length) { try { qrCameras = await Html5Qrcode.getCameras(); } catch(_) {} }
        document.getElementById('qrSwitchBtn').style.display = qrCameras.length > 1 ? 'inline-block' : 'none';
    } catch (err) {
        // 권한 거부/미지원을 조용히 삼키지 않고 원인별 안내 — 안드로이드에서 프롬프트가
        // 안 뜨는 경우는 대부분 이전에 '차단'을 눌렀거나 인앱 브라우저 사용 시
        qrScanner = null;
        hint.style.color = 'var(--red)';
        const name = err && (err.name || String(err));
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || /permission/i.test(String(err))) {
            hint.innerHTML = '카메라 권한이 거부되어 있습니다.<br>'
                + '· 크롬: 주소창 자물쇠(🔒) → 권한 → 카메라 허용 후 다시 시도<br>'
                + '· 안드로이드: 설정 → 애플리케이션 → Chrome → 권한 → 카메라 허용<br>'
                + '· 카카오톡 등 인앱 브라우저에서는 권한 창이 뜨지 않으니 Chrome으로 열어주세요.';
        } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
            hint.textContent = '사용 가능한 카메라를 찾지 못했습니다. 아래 수동 입력을 사용하세요.';
        } else if (name === 'NotReadableError') {
            hint.textContent = '다른 앱이 카메라를 사용 중입니다. 카메라 앱을 닫고 다시 시도하세요.';
        } else {
            hint.textContent = '카메라를 시작하지 못했습니다 (' + name + '). 아래 수동 입력을 사용하세요.';
        }
    }
}
// 카메라 순환 전환 — 멀티 렌즈 폰에서 접사/광각이 잡혀 초점이 안 맞을 때
async function switchQrCamera() {
    if (!qrScanner || qrCameras.length < 2) return;
    const hint = document.getElementById('qrScanHint');
    qrCamIdx = (qrCamIdx + 1) % qrCameras.length;
    try {
        await qrScanner.stop();
        await qrScanner.start(qrCameras[qrCamIdx].id, qrScanConfig(), d => handleScanResult(d), () => {});
        hint.textContent = '카메라 전환됨 (' + (qrCameras[qrCamIdx].label || (qrCamIdx + 1) + '번') + ') — QR을 비춰주세요.';
    } catch (e) {
        hint.style.color = 'var(--red)';
        hint.textContent = '카메라 전환 실패 — 다시 시도하세요.';
    }
}
async function stopQrScan(alsoClose) {
    if (qrScanner) {
        try { await qrScanner.stop(); qrScanner.clear(); } catch(_) {}
        qrScanner = null;
    }
    document.getElementById('qrSwitchBtn').style.display = 'none';
    if (alsoClose) closeModal('qrScanModal');
}
async function handleScanResult(text) {
    if (!text) return;
    const match = String(text).match(/^(?:rental:)?(\d+)$/i);
    if (!match) { bdToast('인식 실패: rental:ID 형식이 아닙니다.'); return; }
    const itemId = +match[1];
    const item = bdState.items.find(i=>i.id===itemId);
    await stopQrScan(false);
    closeModal('qrScanModal');
    if (!item) { bdToast(`장비 ID ${itemId}를 찾을 수 없습니다.`); return; }
    openScanActionModal(item);
}
function openScanActionModal(item) {
    const currentTarget = item.current_target_id ? bdState.targets.find(t=>t.id===item.current_target_id) : null;
    const home = item.home_target_id ? bdState.targets.find(t=>t.id===item.home_target_id) : null;
    document.getElementById('scanActionItemId').value = item.id;
    document.getElementById('scanActionInfo').innerHTML = `
        <div style="color:var(--text); font-weight:700; font-size:14px; margin-bottom:6px;">${bdEsc(item.name)}</div>
        ${item.serial ? `<div style="font-family:'SF Mono',Menlo,monospace; font-size:11px;">${bdEsc(item.serial)}</div>` : ''}
        <div style="margin-top:8px;">현재 위치: <span style="color:var(--text);">${currentTarget ? bdEsc(currentTarget.name) : '미지정'}</span></div>
        ${home ? `<div>원래 위치: <span style="color:var(--text);">${bdEsc(home.name)}</span></div>` : ''}
    `;
    openModal('scanActionModal');
}
async function scanActionReturn() {
    const id = +document.getElementById('scanActionItemId').value;
    closeModal('scanActionModal');
    await bdClear(id, 'QR 스캔 반납');
}
function scanActionDetail() {
    const id = +document.getElementById('scanActionItemId').value;
    closeModal('scanActionModal');
    openRentalItemModal(id);
}
function scanActionMove() {
    const id = +document.getElementById('scanActionItemId').value;
    closeModal('scanActionModal');
    openMoveItemModal(id);
}
function openMoveItemModal(itemId) {
    const item = bdState.items.find(i=>i.id===itemId);
    document.getElementById('miTitle').textContent = `${item.name} · 위치 이동`;
    document.getElementById('miItemId').value = itemId;
    document.getElementById('miTarget').innerHTML = '<option value="">선택하세요</option>' + bdState.targets.map(t=>`<option value="${t.id}">${bdEsc(t.name)}</option>`).join('');
    openModal('moveItemModal');
}
async function miAssign() {
    const itemId = +document.getElementById('miItemId').value;
    const targetId = document.getElementById('miTarget').value;
    if (!targetId) { bdToast('대상을 선택하세요.'); return; }
    closeModal('moveItemModal');
    await bdAssign(itemId, +targetId, 'QR 스캔으로 이동');
}

// === 로그 패널 ===
function bdRenderLogs() {
    const body = document.getElementById('bdLogBody');
    if (!bdState.logs.length) { body.innerHTML = '<div class="empty-row">아직 기록된 변경이 없습니다.</div>'; return; }
    body.innerHTML = bdState.logs.map(log => `
        <div class="eq-log-item">
            <div class="eq-log-head-row">
                <span class="eq-log-user">${bdEsc(log.user||'-')}</span>
                <span class="eq-log-time">${fmtTime(log.created_at)}</span>
            </div>
            <div class="eq-log-action"><strong>${bdEsc(log.action||'')}</strong>${log.detail?' · '+bdEsc(log.detail):''}</div>
        </div>
    `).join('');
}
document.getElementById('bdLogBtn').addEventListener('click', () => { document.getElementById('bdLogPanel').classList.add('open'); bdRenderLogs(); });
document.getElementById('bdSearch').addEventListener('input', () => bdRender());

// 초기 로드
loadBoard();
</script>
@endpush
