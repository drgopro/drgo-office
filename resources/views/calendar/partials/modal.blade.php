<div class="modal-overlay" id="modalOverlay">
    <div class="modal-wrapper">
    <div class="modal" id="modal">
        <div class="modal-strip" id="modalStrip"></div>
        <div class="modal-header">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="modal-date-badge" id="modalDateBadge"></span>
                    <span class="type-badge gold" id="typeBadge" onclick="toggleCategoryQuickPick()">● 방문의뢰</span>
                </div>
                <div class="color-row" id="colorRow" style="margin-bottom:4px;flex-wrap:wrap;">
                    @foreach(\App\Models\CalendarCategory::map() as $__k => $__c)
                    <div class="color-dot{{ $loop->first ? ' active' : '' }}" data-color="{{ $__k }}">{{ $__c['label'] }}</div>
                    @endforeach
                </div>
                <div class="holiday-btn-wrap" style="margin-bottom:4px;">
                    <span class="holiday-dot" id="holidayDot" style="font-size:12px;color:var(--text-muted);cursor:pointer;">📅 공휴일로 지정</span>
                </div>
                <div class="title-wrap" style="display:flex;align-items:flex-start;gap:8px;">
                    <textarea class="modal-title-input" id="modalTitle" placeholder="일정 제목을 입력하세요 *" rows="1" style="flex:1;min-width:0;"></textarea>
                    <span id="modalShipBadge" style="display:none;flex-shrink:0;font-size:16px;font-weight:800;line-height:1.6;" title=""></span>
                </div>
                <button class="assignee-btn" id="assigneeBtn" onclick="toggleAssigneePanel()" title="담당자 지정">
                    <span id="assigneeBtnIcon">👤</span>
                    <span id="assigneeBtnLabel">담당자 지정</span>
                </button>
                <div class="assignee-list" id="assigneeList" style="display:none;margin-top:8px;"></div>
                <button class="assignee-btn" id="notifyBtn" onclick="toggleNotifyPanel()" title="알림 받을 멤버 (미지정 시 담당자 전체)" style="margin-top:6px;">
                    <span>🔔</span>
                    <span id="notifyBtnLabel">알림 받을 멤버</span>
                </button>
                <div class="assignee-list" id="notifyList" style="display:none;margin-top:8px;"></div>
            </div>
            <div class="modal-header-btns">
                <span id="privateModeBadge" style="display:none;font-size:11px;background:#a78bfa22;color:#a78bfa;border:1px solid #a78bfa55;border-radius:6px;padding:2px 8px;font-weight:600;">🔒 개인</span>
                <button class="icon-btn" id="lockBtn" onclick="toggleLock()" title="요약 보기" style="width:auto;padding:0 10px;font-size:12px;white-space:nowrap;">☐ 요약</button>
                <button class="btn-save-top" onclick="saveEvent()">저장</button>
                <button class="icon-btn close-btn" onclick="closeModal()">✕</button>
            </div>
        </div>
        <div class="locked-banner" id="lockedBanner">📄&nbsp; 요약 보기 상태입니다 — 전체 내용을 보거나 수정하려면 '요약'을 해제하세요</div>
        <div id="balanceBanner" style="display:none;align-items:center;gap:8px;background:rgba(200,80,80,0.1);border:1px solid rgba(200,80,80,0.35);border-radius:8px;padding:8px 14px;font-size:12px;letter-spacing:0.05em;color:#e07070;margin:10px 28px 0;">
            <span style="font-size:15px;">💰</span>
            <span id="balanceBannerText">잔금 있음</span>
        </div>

        <div class="modal-body">
            {{-- 잠금 요약 뷰 (isLocked=true 일 때 표시) --}}
            <div id="lockSummary" class="lock-summary"></div>

            {{-- 2a 리디자인: 좌측 폼 컬럼 --}}
            <div class="m-main">

            {{-- 미팅/내방 옵션 · 사내업무 장소 (카테고리별 선택지) --}}
            <div class="field-section" id="visitOptsSection" style="display:none;">
                <div class="field-group">
                    <label class="field-label" id="visitOptsLabel">내방 옵션</label>
                    <div class="visit-opts" id="visitOptsList"></div>
                </div>
            </div>

            {{-- 이사세팅 출발지 (방문의뢰 + 의뢰주제=이사세팅 시 노출) --}}
            <div class="field-section" id="moveFromBlock" style="display:none;">
                <div class="field-group">
                    <label class="field-label" for="moveFromLocation" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <span>🚚 출발지 <span style="font-weight:400;color:var(--text-muted);">(이사 전 장소)</span></span>
                        <label style="display:inline-flex;align-items:center;gap:5px;font-weight:400;font-size:12px;color:var(--text-muted);cursor:pointer;">
                            <input type="checkbox" id="moveNoFrom" onchange="onMoveNoFromToggle()"> 출발지 없음
                        </label>
                    </label>
                    <div class="location-input-wrap" id="moveFromInputWrap">
                        <textarea class="field-input field-textarea" id="moveFromLocation" placeholder="주소 검색 버튼으로 입력하세요" autocomplete="off" rows="2" readonly onclick="searchMoveFrom()" style="min-height:40px;resize:none;cursor:pointer;background:var(--surface2);"></textarea>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="addr-search-btn" onclick="searchMoveFrom()" title="주소 검색">🔍 주소 검색</button>
                            <button type="button" class="addr-search-btn" onclick="clearMoveFrom()" title="주소 지우기">✕ 지우기</button>
                        </div>
                        <input class="field-input" id="moveFromDetail" placeholder="상세주소 (동/호수 등) 직접 입력" autocomplete="off" style="margin-top:2px;">
                    </div>
                    <div id="moveNoFromNote" style="display:none;font-size:12px;color:var(--text-muted);padding:4px 2px;">출발지 없이 도착지(이사 후 장소)만 저장됩니다.</div>
                    <input type="hidden" id="moveFromAddress" value="">
                </div>
            </div>

            {{-- 장소 (이사세팅 시 '도착지'로 라벨 전환) --}}
            <div class="field-section" id="addressBlock">
                <div class="field-group">
                    <label class="field-label" for="modalLocation"><span id="addrBlockLabel">장소</span> <span class="req" id="addrReqMark">*</span></label>
                    <div class="location-input-wrap">
                        <textarea class="field-input field-textarea" id="modalLocation" placeholder="주소 검색 버튼으로 입력하세요" autocomplete="off" rows="2" readonly onclick="searchCalAddr()" style="min-height:40px;resize:none;cursor:pointer;background:var(--surface2);"></textarea>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="addr-search-btn" onclick="searchCalAddr()" title="주소 검색">🔍 주소 검색</button>
                            <button type="button" class="addr-search-btn" onclick="clearCalAddr()" title="주소 지우기">✕ 지우기</button>
                            <button type="button" class="addr-search-btn" id="btnClientAddr" style="display:none;" onclick="applyLinkedClientAddress()" title="연동된 의뢰자에 저장된 주소로 채우기">👤 의뢰자 주소</button>
                            <button type="button" class="addr-search-btn" id="btnProjectAddr" style="display:none;" onclick="applyLinkedProjectAddress()" title="선택한 프로젝트의 세팅 장소로 채우기">📁 프로젝트 주소</button>
                            <span style="font-size:11px;color:var(--text-muted);">도로명은 검색으로만, 상세주소는 아래 직접 입력</span>
                        </div>
                        <input class="field-input" id="modalLocationDetail" placeholder="상세주소 (동/호수 등) 직접 입력" autocomplete="off" style="margin-top:2px;">
                    </div>
                    <input type="hidden" id="modalAddress" value="">
                </div>
            </div>

            {{-- 날짜/시간 --}}
            <div class="datetime-section">
                <div class="allday-row">
                    <div class="toggle-wrap" id="alldayToggle" onclick="toggleAllDay()">
                        <div class="toggle-track" id="alldayTrack"><div class="toggle-thumb"></div></div>
                        <span class="toggle-label">종일</span>
                    </div>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text);cursor:pointer;user-select:none;margin-left:14px;" title="여러 날에 걸친 일정에서 토/일을 제외 — 캘린더에서 주말은 칩이 끊기고 평일만 이어집니다">
                        <input type="checkbox" id="excludeWeekendsChk" style="width:14px;height:14px;accent-color:var(--accent);cursor:pointer;">
                        주말 제외
                    </label>
                </div>
                <div id="standardDtRows">
                    <div class="dt-row">
                        <span class="dt-label">시작</span>
                        <input class="dt-input" type="date" id="startDate">
                        <input type="hidden" id="startTime" value="13:00">
                        <div class="time-picker-trigger dt-input" id="startTimeTrigger" onclick="openTimePicker(this,'startTime')">13:00</div>
                    </div>
                    <div class="dt-row">
                        <span class="dt-label">종료</span>
                        <input class="dt-input" type="date" id="endDate">
                        <input type="hidden" id="endTime" value="14:00">
                        <div class="time-picker-trigger dt-input" id="endTimeTrigger" onclick="openTimePicker(this,'endTime')">14:00</div>
                    </div>
                </div>
                <div id="goldDtRow" style="display:none;align-items:center;gap:6px;flex-wrap:wrap;width:100%;">
                    <input class="dt-input" type="date" id="goldStartDate" style="flex:2;min-width:0;">
                    <input type="hidden" id="goldStartTime" value="13:00">
                    <div class="time-picker-trigger dt-input" id="goldStartTimeTrigger" onclick="openTimePicker(this,'goldStartTime')" style="flex:1;min-width:0;">13:00</div>
                    <span style="color:var(--text-muted);font-size:13px;flex-shrink:0;">~</span>
                    <input class="dt-input" type="date" id="goldEndDate" style="flex:2;min-width:0;">
                    <input type="hidden" id="goldEndTime" value="14:00">
                    <div class="time-picker-trigger dt-input" id="goldEndTimeTrigger" onclick="openTimePicker(this,'goldEndTime')" style="flex:1;min-width:0;">14:00</div>
                </div>
            </div>

            {{-- 장기 일정 하위 일정 (2일 이상 기존 일정에서만 렌더) --}}
            <div id="lsChildrenForm"></div>

            {{-- 알림 + 반복 (전 유형 공통, 날짜/시간 바로 아래) --}}
            <div class="field-section" id="notifRepeatSection">
                <div class="field-group" id="notifGroup">
                    <label class="field-label">🔔 알림</label>
                    <div class="notif-row">
                        <select class="notif-select" id="notifSelect">
                            <option value="">알림 없음</option>
                            <option value="0">정시 (일정 시작 시간)</option>
                            <option value="5">5분 전</option>
                            <option value="10">10분 전</option>
                            <option value="15">15분 전</option>
                            <option value="30">30분 전</option>
                            <option value="60">1시간 전</option>
                            <option value="120">2시간 전</option>
                            <option value="1440">하루 전 오전 9시</option>
                        </select>
                        <span class="notif-allday-label" id="notifAlldayLabel" style="display:none;">당일 오전 9시 발송</span>
                    </div>
                </div>
                <div class="field-group" id="repeatGroup">
                    <div class="notif-row" style="flex-wrap:wrap;gap:10px;align-items:center;">
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;color:var(--text);user-select:none;">
                            <input type="checkbox" id="repeatChk" onchange="onRepeatChkToggle()" style="width:15px;height:15px;accent-color:var(--accent);cursor:pointer;">
                            🔁 반복 일정
                        </label>
                        <span id="repeatControls" style="display:none;align-items:center;gap:6px;flex-wrap:wrap;">
                            <select class="notif-select" id="repeatFreq" onchange="onRepeatFreqChange()">
                                <option value="daily">매일</option>
                                <option value="weekly">매주</option>
                                <option value="monthly">매월</option>
                                <option value="custom">사용자 지정</option>
                            </select>
                            <span id="repeatCustomWrap" style="display:none;align-items:center;gap:4px;">
                                <input type="number" class="field-input" id="repeatInterval" min="1" max="99" value="2" style="width:60px;padding:6px 8px;">
                                <select class="notif-select" id="repeatUnit">
                                    <option value="day">일마다</option>
                                    <option value="week">주마다</option>
                                    <option value="month">개월마다</option>
                                </select>
                            </span>
                            <span id="repeatUntilWrap" style="display:inline-flex;align-items:center;gap:4px;">
                                <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;">종료일</span>
                                <input type="date" class="field-input" id="repeatUntil" style="width:140px;padding:6px 8px;">
                            </span>
                        </span>
                    </div>
                    <div id="repeatEditNote" style="display:none;font-size:11px;color:var(--text-muted);margin-top:4px;">🔁 반복 일정입니다 — 주기/종료일·카테고리를 바꾸면 <b>이 일정 이후</b>의 반복에 반영됩니다 (지난 반복은 그대로).</div>
                </div>
            </div>

            {{-- 배송 현황 (방문의뢰·촬영/스튜디오, 저장된 일정만) --}}
            <div class="field-section" id="shipmentSection" style="display:none;">
                <div class="field-group">
                    {{-- label이면 헤더 클릭이 내부 첫 버튼(○)으로 전달돼 배송 아이콘이 자동 지정됨 → div 사용 --}}
                    <div class="field-label" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;cursor:pointer;" onclick="toggleShipmentBody()">
                        <span><span class="ship-caret" id="shipCaret">▸</span> 📦 배송 현황 <span id="shipSummaryBadge" style="font-weight:400;"></span></span>
                        <span style="display:inline-flex;gap:4px;align-items:center;" onclick="event.stopPropagation();">
                            <span style="font-weight:400;font-size:10px;color:var(--text-muted);margin-right:2px;">제목 표시</span>
                            <button type="button" class="ship-mini-btn ship-ico-btn" data-sio="all" onclick="setShipIconOverride('all')" title="배송 완료로 표시" style="color:var(--green);">○</button>
                            <button type="button" class="ship-mini-btn ship-ico-btn" data-sio="part" onclick="setShipIconOverride('part')" title="부분 배송으로 표시" style="color:#d78a2e;">△</button>
                            <button type="button" class="ship-mini-btn ship-ico-btn" data-sio="none" onclick="setShipIconOverride('none')" title="미배송으로 표시" style="color:var(--red);">✕</button>
                            <button type="button" class="ship-mini-btn ship-ico-btn" data-sio="" onclick="setShipIconOverride('')" title="제목에 표시 안 함">없음</button>
                            <button type="button" class="ship-mini-btn" onclick="refreshShipments()" title="배송상태 새로고침">🔄 새로고침</button>
                        </span>
                    </div>
                    <div id="shipmentBody" style="display:none;">
                        <div id="shipmentList"></div>
                        <div class="ship-add-row" id="shipAddRow">
                            <select class="ship-input" id="shipCarrier" style="flex:0 0 130px;"></select>
                            <input class="ship-input" id="shipTrackingNo" placeholder="송장번호" inputmode="numeric" style="flex:1;min-width:0;" onkeydown="if(event.key==='Enter'&&!event.isComposing){event.preventDefault();addShipment();}">
                            <button type="button" class="ship-mini-btn primary" onclick="addShipment()">+ 등록</button>
                        </div>
                        {{-- 견적서 연동 일정 — 송장 입력은 견적서 주문/배송으로 일원화, 캘린더는 표시만 --}}
                        <div id="shipEstimateNote" style="display:none;align-items:center;gap:8px;flex-wrap:wrap;padding:6px 0 2px;font-size:12px;color:var(--text-muted);">
                            <span>송장은 연동된 견적서의 주문/배송에서 입력합니다.</span>
                            <button type="button" class="ship-mini-btn" id="shipEstimateOpenBtn">견적서 배송 정보 열기</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            {{-- 공통 필드 (비-gold/비-teal) --}}
            <div class="common-only field-section">
                <div class="field-group">
                    <label class="field-label">상세 설명</label>
                    <textarea class="field-textarea" id="commonDesc" placeholder="상세 내용을 입력하세요"></textarea>
                </div>
                <div class="field-group">
                    <label class="field-label">전달사항</label>
                    <textarea class="field-textarea" id="commonHandoverNote" placeholder="전달사항을 입력하세요" style="min-height:60px;"></textarea>
                </div>
            </div>

            {{-- 의뢰자 검색/연결 (사내업무/휴가 제외) --}}
            <div class="field-section" id="clientLinkSection">
            <div class="section-heading" style="margin-bottom:4px;">의뢰자 / 프로젝트</div>
            <div class="field-group">
                <label class="field-label">의뢰자 검색</label>
                <div style="position:relative;">
                    <input class="field-input" id="clientSearchInput" placeholder="이름/닉네임/전화번호로 검색" autocomplete="off" oninput="searchClients(this.value)" onfocus="loadRecentClients()">
                    <div id="clientSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--surface);border:1px solid var(--border);border-radius:0 0 8px 8px;max-height:300px;overflow-y:auto;z-index:10;box-shadow:0 4px 16px rgba(0,0,0,0.15);"></div>
                </div>
            </div>
            <div id="linkedClientInfo" style="display:none;padding:10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-size:11px;color:var(--text-muted);">연결된 의뢰자</span>
                        <div style="font-size:13px;font-weight:600;" id="linkedClientName"></div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <a id="linkedClientLink" href="#" target="_blank" data-always-active style="font-size:11px;padding:3px 10px;text-decoration:none;border:1px solid var(--border);border-radius:6px;color:var(--text-muted);display:inline-flex;align-items:center;">보기</a>
                        <button type="button" onclick="unlinkClient()" style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
                    </div>
                </div>
            </div>
            <div id="projectSelectWrap" style="display:none;" class="field-group">
                <label class="field-label">프로젝트 연결</label>
                <select class="field-input" id="projectSelect" style="cursor:pointer;">
                    <option value="">프로젝트 선택 (선택사항)</option>
                </select>
            </div>
            </div>{{-- /field-section (의뢰자/프로젝트) --}}

            {{-- Gold 템플릿 (방문의뢰) --}}
            <div class="gold-only" style="display:none;flex-direction:column;gap:14px;">
                <div class="section-heading">의뢰자 정보</div>
                <div class="field-row" style="gap:10px;">
                    <div class="field-group"><label class="field-label">의뢰자 닉네임 <span class="req">*</span></label><input class="field-input" id="g_nickname" placeholder="닉네임"></div>
                    <div class="field-group"><label class="field-label">의뢰자 이름 <span class="req">*</span></label><input class="field-input" id="g_name" placeholder="이름"></div>
                    <div class="field-group"><label class="field-label">전화번호 <span class="req">*</span></label><input class="field-input" id="g_phone" placeholder="010-0000-0000"></div>
                </div>

                <div class="field-row">
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">플랫폼 <span class="req">*</span></label>
                        <div id="g_platform_wrap" style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                            <div class="radio-group" id="g_platform_group" style="flex-wrap:nowrap;gap:5px;flex-shrink:0;">
                                <div class="radio-btn" data-val="SOOP">SOOP</div>
                                <div class="radio-btn" data-val="치지직">치지직</div>
                                <div class="radio-btn" data-val="유튜브">유튜브</div>
                                <div class="radio-btn" data-val="틱톡">틱톡</div>
                                <div class="radio-btn" data-val="기타">기타</div>
                            </div>
                            <div class="conditional-field" id="g_platform_etc_wrap" style="margin-top:0;flex:1;min-width:80px;"><input class="field-input" id="g_platform_etc" placeholder="직접 입력" style="font-size:13px;"></div>
                        </div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">방송 주제</label>
                    <div id="g_topic_wrap" style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                        <div class="radio-group" id="g_topic_group" style="flex-wrap:wrap;gap:5px;flex-shrink:0;">
                            <div class="radio-btn" data-val="소통">소통</div>
                            <div class="radio-btn" data-val="게임">게임</div>
                            <div class="radio-btn" data-val="노래">노래</div>
                            <div class="radio-btn" data-val="먹방">먹방</div>
                            <div class="radio-btn" data-val="야외">야외</div>
                            <div class="radio-btn" data-val="버추얼">버추얼</div>
                            <div class="radio-btn" data-val="코인">코인</div>
                            <div class="radio-btn" data-val="주식">주식</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                            <div class="radio-btn" data-val="미정">미정</div>
                        </div>
                        <div class="conditional-field" id="g_topic_etc_wrap" style="margin-top:0;flex:1;min-width:100px;"><input class="field-input" id="g_topic_etc" placeholder="방송 주제를 직접 입력하세요" style="font-size:13px;"></div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">경력 여부</label>
                    <div class="radio-group" id="g_career_group">
                        <div class="radio-btn active" data-val="처음">처음</div>
                        <div class="radio-btn" data-val="초보">초보</div>
                        <div class="radio-btn" data-val="경력">경력</div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">예산 성향</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                        <div class="radio-group" id="g_budget_group" style="flex-wrap:wrap;gap:5px;">
                            <div class="radio-btn" data-val="풍족">풍족</div>
                            <div class="radio-btn" data-val="부족">부족</div>
                            <div class="radio-btn" data-val="모름">모름</div>
                            <div class="radio-btn" data-val="직접입력">직접입력</div>
                        </div>
                        <div class="conditional-field" id="g_budget_etc_wrap" style="margin-top:0;flex:1;min-width:120px;"><input class="field-input" id="g_budget_etc" placeholder="예산 직접 입력" style="font-size:13px;"></div>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">유입 경로</label>
                    <div id="g_source_wrap" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <div class="radio-group" id="g_source_group" style="flex-wrap:wrap;gap:5px;flex-shrink:0;">
                            <div class="radio-btn" data-val="광고">📢 광고</div>
                            <div class="radio-btn" data-val="검색">🔍 검색</div>
                            <div class="radio-btn" data-val="소개">🤝 소개</div>
                            <div class="radio-btn" data-val="SNS">📱 SNS</div>
                            <div class="radio-btn" data-val="커뮤니티">👥 커뮤니티</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                        </div>
                        <div class="conditional-field" id="g_source_ref_wrap" style="margin-top:0;flex:1;min-width:100px;"><input class="field-input" id="g_source_ref" placeholder="소개해 준 분 이름" style="font-size:13px;"></div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="section-heading">장비 목록</div>
                <div class="field-group">
                    <label class="field-label">장비 목록</label>
                    <textarea class="field-textarea" id="g_equipment" placeholder="사용 장비를 입력하세요" style="min-height:195px;"></textarea>
                </div>

                <div class="divider"></div>

                {{-- 일정 옵션 — 확정 상태(단일)와 시기 요청(복수)을 한 섹션으로 통합 --}}
                <div class="field-group">
                    <div class="field-label">일정 옵션</div>
                    <div class="sched-opt-sub">확정 상태 — 이 날짜가 얼마나 확정적인지 (하나 선택)</div>
                    <div class="special-opts" id="scheduleOpts">
                        <div class="sched-opt-btn" data-sopt="suggest"><span class="opt-icon">💬</span>제안</div>
                        <div class="sched-opt-btn" data-sopt="hope"><span class="opt-icon">🙏</span>희망</div>
                        <div class="sched-opt-btn" data-sopt="target"><span class="opt-icon">🎯</span>목표</div>
                        <div class="sched-opt-btn" data-sopt="confirmed"><span class="opt-icon">✅</span>확정</div>
                    </div>
                    <div class="sched-opt-desc" id="schedOptDesc"></div>
                    {{-- 시기 요청 — 방문의뢰(gold) + 스튜디오/촬영 카테고리 (setColor에서 토글) --}}
                    <div id="schedEventSection">
                        <div class="sched-opt-sub" style="margin-top:10px;">시기 요청 (복수 선택 가능)</div>
                        <div class="special-opts" id="schedEventOpts">
                            <div class="special-opt-btn" data-seopt="fast"><span class="opt-icon">←</span>빠른 일정 희망</div>
                            <div class="special-opt-btn" data-seopt="urgent"><span class="opt-icon">🚨</span>긴급 일정</div>
                            <div class="special-opt-btn" data-seopt="after"><span class="opt-icon">→</span><span id="schedAfterLabel">날짜 선택</span> 이후 희망</div>
                        </div>
                        <div id="schedReasonWrap" style="display:none;margin-top:6px;">
                            <input class="field-input" id="schedAfterReason" placeholder="사유 (선택)" style="font-size:13px;">
                        </div>
                    </div>
                </div>
                <div class="field-group" id="specialOptsGroup">
                    <div class="field-label">특수 옵션</div>
                    <div class="special-opts" id="specialOpts">
                        <div class="special-opt-btn" data-opt="car"><span class="opt-icon">🚗</span>차량 이용 필요</div>
                        <div class="special-opt-btn" data-opt="brief"><span class="opt-icon">💼</span>들고 갈 제품 있음</div>
                        <div class="special-opt-btn" data-opt="group"><span class="opt-icon">👥</span>2인필수 작업</div>
                        <div class="special-opt-btn" data-opt="ladder"><span class="opt-icon">▤</span>사다리 필요</div>
                        <div class="special-opt-btn" data-opt="pet"><span class="opt-icon">🐾</span>반려동물 있음</div>
                        {{-- 스튜디오/촬영 전용 — 체크 시 항시 종일 일정 + 모든 뷰 최상단 표시 --}}
                        <div class="special-opt-btn" data-opt="external_operator" id="extOperatorBtn" style="display:none;" title="체크하면 종일 일정으로 고정되고 전체/월/주/일 뷰 맨 위에 표시됩니다"><span class="opt-icon">🎛</span>외부 오퍼레이터</div>
                    </div>
                    <div id="specialReasonWrap" style="display:none;margin-top:6px;">
                        <input class="field-input" id="specialReason" placeholder="차량 이용 사유 입력 (예: 장비 운반, 주차 공간 필요)" style="font-size:13px;">
                    </div>
                </div>

                <div class="divider"></div>

                <div class="section-heading">의뢰 내용</div>
                <div class="field-group">
                    <label class="field-label">의뢰 주제</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <div class="radio-group" id="g_req_topic_group">
                            <div class="radio-btn" data-val="처음세팅">처음세팅</div>
                            <div class="radio-btn" data-val="추가세팅">추가세팅</div>
                            <div class="radio-btn" data-val="이사세팅">이사세팅</div>
                            <div class="radio-btn" data-val="렌탈">렌탈</div>
                            <div class="radio-btn" data-val="기타">기타</div>
                        </div>
                        <div class="conditional-field" id="g_req_topic_etc_wrap"><input class="field-input" id="g_req_topic_etc" placeholder="직접 입력"></div>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">세팅 항목
                        <span style="font-weight:400;font-size:10.5px;color:var(--text-muted);">연결된 프로젝트의 의뢰 내용에서 불러옴 — 작성/수정은 프로젝트 페이지에서</span>
                    </label>
                    <div id="reqItemsView" style="font-size:12.5px;line-height:1.7;"></div>
                </div>
                <div class="field-group">
                    <label class="field-label">의뢰 세부항목 (자유 메모)</label>
                    <textarea class="field-textarea" id="g_req_detail" placeholder="선택지에 없는 요청·부가 설명을 입력하세요"></textarea>
                </div>
                <div class="field-group">
                    <label class="field-label">특이사항</label>
                    <textarea class="field-textarea" id="g_special" placeholder="특이사항을 입력하세요" style="min-height:65px;"></textarea>
                </div>

                <div class="divider"></div>

                <div class="section-heading">결제 정보</div>
                <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                    <div class="field-group" style="flex:none;">
                        <div class="field-label">결제 여부</div>
                        <div class="radio-group" id="g_paid_group">
                            <div class="radio-btn active" data-val="미결제">미결제</div>
                            <div class="radio-btn" data-val="결제완료">결제완료</div>
                        </div>
                    </div>
                    <div class="field-group" style="flex:1;min-width:0;">
                        <label class="field-label">결제된 금액</label>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input class="field-input" id="g_estimate_amount" placeholder="금액 입력" type="text" style="flex:1;min-width:0;">
                            <button type="button" id="g_estimate_btn" onclick="extractEstimateAmount()" style="background:none;border:1px solid var(--border);color:var(--text-muted);border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;white-space:nowrap;transition:all 0.2s;flex-shrink:0;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">🔍 추출</button>
                            <span id="g_estimate_status" style="font-size:11px;color:var(--text-muted);white-space:nowrap;"></span>
                        </div>
                        {{-- 연동 견적서 환불 합계 — 프로젝트 환불·주문 내역 체크 시 자동 표시 (읽기 전용) --}}
                        <div id="g_estimate_refund_view" data-amt="" style="display:none;margin-top:5px;font-size:12px;font-weight:700;color:var(--red, #dc2626);"></div>
                    </div>
                </div>
                <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                    <div class="field-group" style="flex:none;">
                        <div class="field-label">주문 제품</div>
                        <div class="radio-group" id="g_order_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" style="flex:none;">
                        <div class="field-label">잔금 여부</div>
                        <div class="radio-group" id="g_balance_group">
                            <div class="radio-btn active" data-val="X">X</div>
                            <div class="radio-btn" data-val="O">O</div>
                        </div>
                    </div>
                    <div class="field-group" id="g_balance_amount_outer" style="flex:1;min-width:0;">
                        <div class="conditional-field" id="g_balance_amount_wrap">
                            <label class="field-label">잔금 금액</label>
                            <input class="field-input" id="g_balance_amount" placeholder="잔금 금액 (원)" type="text">
                        </div>
                    </div>
                </div>
                {{-- 프로젝트 결제 연동 안내 — 프로젝트 연결 시 결제 금액/잔금은 프로젝트 결제 데이터에서 자동 반영 --}}
                <div id="projPayNote" style="display:none;font-size:11px;color:var(--accent);margin:-4px 0 8px;"></div>

                <div class="divider"></div>

                <div class="section-heading">첨부 이미지</div>
                <div class="img-upload-group">
                    <div class="img-upload-label">견적서</div>
                    <div style="display:flex;gap:8px;margin-bottom:6px;">
                        <button type="button" onclick="triggerAttach('quote')" class="action-btn" style="flex:1;">📄 견적서 첨부</button>
                        <button type="button" onclick="openEstimateSearch()" class="action-btn" style="flex:1;">📋 견적서 불러오기</button>
                    </div>
                    <div class="img-upload-zone" id="quoteZone">
                        <input type="file" id="fileQuote" multiple accept="image/*" onchange="handleImgFiles('quote',this.files)">
                        📄 견적서 이미지를 클릭 또는 드래그하여 추가
                    </div>
                    <div class="img-grid" id="quoteGrid"></div>
                    <div id="linkedEstimateInfo" style="display:none;margin-top:8px;padding:10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <span style="font-size:11px;color:var(--text-muted);">연결된 견적서</span>
                                <div style="font-size:13px;font-weight:600;" id="linkedEstimateTitle"></div>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <button type="button" onclick="openLinkedEstimate()" class="estimate-btn" data-always-active style="font-size:11px;padding:3px 10px;border:1px solid var(--border);border-radius:6px;background:none;color:var(--text-muted);cursor:pointer;">보기</button>
                                <button type="button" onclick="unlinkEstimate()" data-always-active style="background:none;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:11px;cursor:pointer;">해제</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="img-upload-group">
                    <div class="img-upload-label">레퍼런스</div>
                    <div class="img-upload-zone" id="refZone">
                        <input type="file" id="fileReference" multiple accept="image/*" onchange="handleImgFiles('reference',this.files)">
                        📷 레퍼런스 이미지를 클릭 또는 드래그하여 추가
                    </div>
                    <div class="img-grid" id="refGrid"></div>
                </div>
                <div class="img-upload-group">
                    <div class="img-upload-label">방 사진</div>
                    <div class="img-upload-zone" id="roomZone">
                        <input type="file" id="fileRoom" multiple accept="image/*" onchange="handleImgFiles('room',this.files)">
                        🏠 방 사진을 클릭 또는 드래그하여 추가
                    </div>
                    <div class="img-grid" id="roomGrid"></div>
                </div>
                <div class="img-upload-group">
                    <div class="img-upload-label">첨부 파일</div>
                    <div class="img-upload-zone" id="generalZone">
                        <input type="file" id="fileGeneral" multiple onchange="handleImgFiles('general',this.files)">
                        📎 파일을 클릭 또는 드래그하여 추가 (PDF, 문서 등 가능)
                    </div>
                    <div class="img-grid" id="generalGrid"></div>
                </div>
            </div>

            {{-- Teal 템플릿 (원격/방송룸) --}}
            <div class="teal-only" style="display:none;flex-direction:column;gap:14px;">
                <div class="field-group">
                    <label class="field-label">유형 선택</label>
                    <div class="radio-group" id="teal_mode_group">
                        <div class="radio-btn active" data-val="remote">🖥 원격</div>
                        <div class="radio-btn" data-val="studio">🎙 방송룸 이용</div>
                    </div>
                </div>
                <div id="teal_remote_fields" style="display:flex;flex-direction:column;gap:12px;">
                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">원격 대상자 이름(닉네임) <span class="req">*</span></label>
                            <input class="field-input" id="t_remote_name" placeholder="이름 또는 닉네임">
                        </div>
                        <div class="field-group">
                            <label class="field-label">방송 플랫폼 <span class="req">*</span></label>
                            <input class="field-input" id="t_remote_platform" placeholder="유튜브, 아프리카TV 등">
                        </div>
                    </div>
                    <div class="field-group" style="margin-top:4px;">
                        <label class="field-label">원격 의뢰 내용</label>
                        <textarea class="field-textarea" id="t_remote_content" placeholder="원격으로 진행할 내용을 입력하세요"></textarea>
                    </div>
                </div>
                <div id="teal_studio_fields" style="display:none;flex-direction:column;gap:12px;">
                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">방송룸 이용자 이름(닉네임) <span class="req">*</span></label>
                            <input class="field-input" id="t_studio_name" placeholder="이름 또는 닉네임">
                        </div>
                        <div class="field-group">
                            <label class="field-label">방송 플랫폼 <span class="req">*</span></label>
                            <input class="field-input" id="t_studio_platform" placeholder="유튜브, 아프리카TV 등">
                        </div>
                    </div>
                    <div class="field-group" style="margin-top:4px;">
                        <label class="field-label">방송룸 이용 내용</label>
                        <textarea class="field-textarea" id="t_studio_content" placeholder="방송룸 이용 내용을 입력하세요"></textarea>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">메모 (선택)</label>
                    <textarea class="field-textarea" id="t_desc" placeholder="추가 메모를 입력하세요"></textarea>
                </div>
            </div>

            {{-- 대여 이력 등록 (신규 등록 시에만) — 원격/방송룸: 시간/월 대여, 렌탈 카테고리: 렌탈 월 계약 --}}
            <div class="field-group" id="brRentalGroup" style="display:none;">
                <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;color:var(--text);user-select:none;">
                    <input type="checkbox" id="brRentalChk" onchange="onBrRentalToggle()" style="width:15px;height:15px;accent-color:var(--accent);cursor:pointer;">
                    📄 대여 이력 등록 <span id="brRentalKindLabel" style="color:var(--text-muted);font-size:12px;">(방송룸)</span>
                </label>
                <div id="brRentalFields" style="display:none;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px;">
                    <select class="notif-select" id="brRentalMode" onchange="onBrRentalModeChange()">
                        <option value="hourly">시간 대여</option>
                        <option value="monthly">월 대여</option>
                    </select>
                    <input class="field-input" id="brRentalRoom" placeholder="호실 (예: 2)" style="width:100px;padding:6px 8px;">
                    <input class="field-input" id="brRentalFee" type="number" min="0" placeholder="요금(원)" style="width:120px;padding:6px 8px;">
                    <span id="brRentalHint" style="font-size:11px;color:var(--text-muted);flex-basis:100%;">의뢰자 연동 필수 · 월 대여/렌탈은 시작~종료일이 계약 기간이 됩니다 (제목은 자동 지정)</span>
                </div>
            </div>

            {{-- 일반 첨부 파일 --}}
            <div id="generalAttachSection">
                <div class="divider" style="margin-bottom:14px;"></div>
                <div class="field-group">
                    <div class="field-label">첨부 파일</div>
                    <div class="upload-zone" id="uploadZone" style="border:1px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;position:relative;">
                        <input type="file" id="generalFileInput" multiple accept="*/*" onchange="handleGeneralFiles(this.files)" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
                        <div style="font-size:22px;margin-bottom:5px;">📎</div>
                        <div style="font-size:12px;color:var(--text-muted);">파일을 <span style="color:var(--accent);">클릭</span>하거나 드래그하여 첨부하세요<br><small style="opacity:0.55">이미지는 미리보기가 지원됩니다</small></div>
                    </div>
                    <div class="img-grid" id="generalAttachGrid" style="margin-top:8px;"></div>
                </div>
            </div>

            </div>{{-- /m-main --}}

            {{-- 2a 리디자인: 우측 작성 현황 레일 --}}
            <aside class="m-rail" id="modalRail">
                <div class="m-rail-card">
                    <div class="m-rail-title">작성 현황</div>
                    <div class="m-rail-pct"><b id="mRailPct">0%</b><span class="m-rail-cnt" id="mRailCnt"></span></div>
                    <div class="m-rail-bar"><div id="mRailBarFill"></div></div>
                    <div class="m-rail-nav" id="mRailNav"></div>
                </div>
                <div class="m-rail-remaining" id="mRailRemaining" style="display:none;">
                    <div class="m-rail-rem-title">남은 항목</div>
                    <div class="m-rail-rem-chips" id="mRailRemChips"></div>
                </div>
            </aside>

        </div>{{-- modal-body end --}}

        {{-- 차량 이용 사유 — 모달 하단 고정 배너 (스크롤해도 유지) --}}
        <div id="carReasonBanner" class="car-reason-banner" style="display:none;">
            <span class="crb-ico">🚗</span><b>차량 이용</b><span id="carReasonBannerText"></span>
        </div>

        <div id="reasonField" style="display:none; padding:0 28px 12px;">
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px; letter-spacing:0.04em;">일정 변경 사유 <span style="color:var(--red);">* 날짜/시간 변경 시 필수</span></div>
            <textarea id="modalReason" rows="2" placeholder="예: 의뢰자 요청으로 일정 변경" style="width:100%; padding:8px 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; outline:none; resize:vertical; box-sizing:border-box; font-family:inherit;"></textarea>
        </div>

        <div class="modal-footer">
            <button class="btn-delete" id="btnDelete" style="display:none" onclick="deleteEvent()">일정 삭제</button>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn-log" id="btnLog" style="display:none" onclick="openHistoryFromEdit()">📋 <span>변경 로그</span></button>
                <button class="btn-complete" id="btnComplete" style="display:none" onclick="toggleCompleteFromDetail()">✓ 완료</button>
                <button class="btn-save" onclick="saveEvent()">저장</button>
            </div>
        </div>
    </div>
    <div class="modal-external-btns">
        <button class="modal-external-close" onclick="closeModal()" title="닫기">✕</button>
        <button class="modal-external-action" id="modalExternalAction" onclick="saveEvent()" title="저장">저장</button>
        <button class="modal-external-complete" id="extCompleteBtn" style="display:none" onclick="toggleCompleteFromDetail()" title="완료">완료</button>
    </div>
    </div>{{-- modal-wrapper end --}}
</div>
