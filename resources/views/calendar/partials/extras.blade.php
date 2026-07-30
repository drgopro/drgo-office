<!-- 견적서 검색 모달 -->
<div class="modal-overlay" id="estimateSearchOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:540px; max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header" style="padding:16px 20px 12px; flex-shrink:0;">
            <div style="font-size:16px; font-weight:600;">견적서 불러오기</div>
            <button class="icon-btn close-btn" onclick="document.getElementById('estimateSearchOverlay').style.display='none'">✕</button>
        </div>
        <div style="padding:0 20px 12px; flex-shrink:0;">
            <input class="field-input" id="estimateSearchInput" type="text" placeholder="🔍 의뢰자명, 견적서 번호로 검색..." oninput="searchEstimates(this.value)">
        </div>
        <div id="estimateSearchResults" style="flex:1; overflow-y:auto; padding:0 20px 16px;">
            <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">로딩 중...</div>
        </div>
    </div>
</div>

<!-- 일정 상세 모달 (조회전용) -->
<div class="modal-overlay" id="detailOverlay" style="display:none;" onclick="if(event.target===this) closeDetail()">
    <div class="modal" style="max-width:620px;">
        <div class="modal-strip" id="detailStrip"></div>
        <div class="modal-header" style="padding-bottom:12px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span class="modal-date-badge" id="detailDateBadge"></span>
                    <span class="type-badge" id="detailTypeBadge">● 방문의뢰</span>
                </div>
                <div style="font-size:20px;font-weight:500;margin-top:4px;" id="detailTitle"></div>
                <div id="detailAssignees" style="margin-top:6px;"></div>
            </div>
            <div class="modal-header-btns">
                <button class="icon-btn close-btn" onclick="closeDetail()">✕</button>
            </div>
        </div>
        <div class="modal-body" id="detailBody" style="gap:10px;"></div>
        <div class="modal-footer">
            <div style="display:flex;gap:6px;">
                <button class="btn-delete" onclick="deleteEventFromDetail()">삭제</button>
                <button class="btn-log" style="display:inline-flex;" onclick="openHistoryModal()">📋 수정내역</button>
            </div>
            <div style="display:flex;gap:6px;">
                <button class="btn-log" id="detailCompleteBtn" onclick="toggleCompleteFromDetail()">✓ 완료</button>
                <button class="btn-save" onclick="editFromDetail()">수정</button>
            </div>
        </div>
    </div>
</div>

<!-- 수정내역 모달 -->
<div class="modal-overlay" id="historyOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:500px; max-height:70vh; overflow-y:auto;">
        <div class="modal-header">
            <div class="modal-title">수정내역</div>
            <button class="modal-close" onclick="document.getElementById('historyOverlay').style.display='none'">×</button>
        </div>
        <div id="historyBody"><div style="padding:20px; text-align:center; color:var(--text-muted);">로딩 중...</div></div>
    </div>
</div>

<!-- 삭제 사유 입력 모달 -->
<div class="modal-overlay" id="deleteReasonOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header" style="padding:16px 20px 12px;">
            <div style="font-size:16px; font-weight:600;">일정 삭제</div>
            <button class="modal-close" onclick="document.getElementById('deleteReasonOverlay').style.display='none'">×</button>
        </div>
        <div style="padding:0 20px 12px; color:var(--text-muted); font-size:13px; line-height:1.5;">
            이 일정을 삭제합니다. 휴지통으로 이동되며, <span style="color:var(--text);">삭제 사유</span>를 반드시 입력해야 합니다.
        </div>
        <div style="padding:0 20px 10px; display:none;" id="delSeriesWrap">
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text);">
                <input type="checkbox" id="delSeriesChk" style="width:16px;height:16px;accent-color:var(--red);">
                🔁 반복 일정입니다 — 이후 반복 일정도 함께 삭제
            </label>
        </div>
        <div style="padding:0 20px 16px;">
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">삭제 사유 <span style="color:var(--red);">*</span></div>
            <textarea id="deleteReasonInput" rows="3" placeholder="예: 일정 취소됨, 의뢰자 요청 등" style="width:100%; padding:9px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; resize:vertical; box-sizing:border-box; font-family:inherit;"></textarea>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; padding:0 20px 20px;">
            <button class="nav-btn" onclick="document.getElementById('deleteReasonOverlay').style.display='none'" style="width:auto; padding:6px 14px; font-size:12px;">취소</button>
            <button class="btn-delete" onclick="confirmDeleteEvent()" style="padding:6px 14px;">삭제</button>
        </div>
    </div>
</div>

<!-- 휴지통 모달 -->
<div class="modal-overlay" id="trashOverlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:640px; max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <div class="modal-title"><x-icon name="trash" :size="16"/> 휴지통</div>
            <button class="modal-close" onclick="document.getElementById('trashOverlay').style.display='none'">×</button>
        </div>
        <div style="padding:10px 16px; border-bottom:1px solid var(--border); display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
            <label style="font-size:12px; color:var(--text-muted); display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="checkbox" id="trashSelectAll" onchange="trashToggleAll(this.checked)"> 전체 선택
            </label>
            <span class="text-muted" id="trashCount" style="font-size:11px; color:var(--text-muted);">0건</span>
            <div style="flex:1;"></div>
            <button class="nav-btn" id="trashRestoreBtn" onclick="trashRestoreSelected()" style="font-size:11px; width:auto; padding:4px 10px;" disabled>선택 복원</button>
            <button class="nav-btn" id="trashClearBtn" onclick="trashEmptySelected()" style="font-size:11px; width:auto; padding:4px 10px; color:var(--red); border-color:var(--red);" disabled>선택 정리</button>
            <button class="nav-btn" onclick="trashEmptyAll()" style="font-size:11px; width:auto; padding:4px 10px; color:var(--red); border-color:var(--red);">전체 비우기</button>
        </div>
        <div id="trashBody" style="flex:1; overflow-y:auto; padding:8px;">
            <div style="padding:30px; text-align:center; color:var(--text-muted);">로딩 중...</div>
        </div>
    </div>
</div>

<!-- 삭제/변경 이력 (문장 로그) -->
<div class="changelog-overlay" id="changeLogOverlay" onclick="if(event.target.id==='changeLogOverlay') closeChangeLog()">
    <div class="changelog-modal">
        <div class="changelog-head">
            <span>📋 삭제/변경 이력</span>
            <button type="button" onclick="closeChangeLog()">✕</button>
        </div>
        <div class="changelog-body" id="changeLogBody"><div class="changelog-empty">불러오는 중…</div></div>
    </div>
</div>

<!-- 이미지 라이트박스 -->
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <button class="lightbox-nav prev" onclick="lightboxNav(-1)">‹</button>
    <button class="lightbox-nav next" onclick="lightboxNav(1)">›</button>
    <div class="lightbox-img-wrap" id="lightboxWrap">
        <img id="lightboxImg" src="" alt="">
    </div>
    <div class="lightbox-zoom-info" id="lightboxZoomInfo">100%</div>
    <div class="lightbox-filename" id="lightboxFilename"></div>
    <div class="lightbox-hint">스크롤: 확대/축소 · 더블클릭: 원본 크기 · 드래그: 이동</div>
</div>

