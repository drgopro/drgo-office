---
description: 닥터고블린 오피스 코드베이스 구조 맵 — 캘린더/의뢰자/프로젝트/피드백/위키의 주요 파일 위치, 핵심 함수, 데이터 흐름, 검증 워크플로. 기능 수정·버그 수정·코드 탐색 전에 이 스킬을 읽으면 파일 탐색을 크게 줄일 수 있다.
---

# 닥터고블린 오피스 코드베이스 맵

Laravel 13 + Blade 단일 파일 뷰(인라인 CSS/JS) 구조. SPA 프레임워크 없음.
**모든 작업 완료 시 묻지 않고 커밋 + origin main 푸시** (Forge Quick Deploy = 푸시가 곧 배포. 마이그레이션 포함 시 서버에서 `php artisan migrate --force` 안내 필요).

## 검증 워크플로 (필수)

1. PHP 수정 시: `vendor/bin/pint --dirty --format agent`
2. 인라인 JS 문법 검사: 이 스킬 폴더의 `extract-any.php`를 세션 스크래치패드로 복사한 뒤
   `php extract-any.php <url-path> <out-dir>` → 각 `*.js`를 `node --check`
   (내부에서 master 유저로 인증 + DB 트랜잭션 롤백. `__project_show__` 인자는 임시 프로젝트 생성 후 상세 페이지 렌더)
3. 테스트: `php artisan test --compact [파일]` (sqlite :memory:)
4. 커밋 메시지에 큰따옴표 금지 (PowerShell 인자 버그) — here-string `@'...'@` 사용

## 탭 셸 (레이아웃)

- `resources/views/layouts/app.blade.php` — 데스크탑 탭 셸. 사이드바(200px 다크네이비) + 탭바 + iframe 패널
  - `drgoTabs` 객체: open/activate/close/render, 휠클릭 닫기(auxclick), 드래그 정렬
  - `_fitPanes()`: iframe 하단을 window.innerHeight에 실측 맞춤 (크롬 zoom 표준화 대응 — CSS 계산식 신뢰 금지)
  - 데스크탑 `body:not(.in-iframe){zoom:1.07}` / `body.in-iframe { --chrome-h:0px }` (iframe 안 이중 차감 금지!)
  - 페이지가 iframe 안이면 JS로 `body.in-iframe` 클래스 부여 → 헤더/탭바 숨김
- `resources/views/layouts/tab-content.blade.php` — `?_tab=1`로 열린 페이지용 미니 레이아웃 (`--chrome-h:0`)
- 페이지 상단: `@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')` 패턴
- 고정 높이 내부 셸 패턴: `.proj-shell`(프로젝트)/`.crm-wrap`(의뢰자)/`.wiki-layout`(위키) = `calc(var(--full-h) - var(--chrome-h))`

## 캘린더 — `resources/views/calendar/index.blade.php` (~7000줄 단일 파일)

- **뷰 5종**: `switchView('month'|'monthc'|'week'|'day'|'list')` — monthc=컴팩트(네이버식). 마지막 뷰 `localStorage.calLastView` 복원
- 렌더 진입: `renderView()` → renderMonth / renderMonthCompact / renderTimeline / renderAgenda
- **월간**: `renderMonth()` — 주 단위 lane 배정(다일 바), `buildChipHtml(ev)`가 칩 HTML 공용(제목+확정칩+배송+담당자)
- **컴팩트(monthc)**: `renderMonthCompact()` — 균등 6주 셀+`+N`, 모바일=하단 시트(`mcSheet`/`mcSelectDay`), 데스크탑=하단 리스트(`mcDeskList`)+드래그 리사이저(`mcListH`, localStorage `mcDeskListH`)
- **필터**: `isFiltered(ev)` = activeFilters(카테고리 Set)+activeAssigneeIds. 뷰별 저장: `csLoadFilters()`/`csSaveHidden()` → localStorage `calHiddenCats:{bucket}` (monthc는 month와 공유)
- **일정 모달**: `#modalOverlay` — openNewModal/openEditModal/openDetailModal(하위→부모 리다이렉트), `setColor(c)`가 카테고리별 섹션 토글 (시기요청·특수옵션은 gold+스튜디오/촬영 라벨 매칭)
- **요약(잠금) 뷰**: `renderLockSummary()` → `#lockSummary` ls-* 카드. `isLocked` 전역, 기존 일정은 기본 요약 ON. is-locked 시 modal-body 2열 그리드 해제됨
- **장기 일정 하위**: schedules.parent_id, `renderChildrenCard()`(요약=#lsChildren/폼=#lsChildrenForm, CH_RENDER_SEQ 경쟁 방지, chEl() 스코프 조회). API: /api/events/{id}/children CRUD
- **아이콘/칩**: `eventOptIconsHtml`(시기요청 ←🚨→ + 특수옵션, 제목 앞) / `schedStatusChip`(확/목/희/제, 제목 끝) / `shipStatusIcon`
- 헤더: 데스크탑 2행(중앙 `[오늘]‹연.월›` 절대배치 + 2행 뷰버튼), 모바일 ☰=필터 리모컨(csToggleMobile), 연.월▾=년/월 피커(mpPick), + 플로팅 `#calAddFab`
- 일별 팝업 `openDayPopover` — visualViewport 실측 클램프. 이미지 라이트박스는 자체 lb (openLightbox)
- 주소 검색: `searchCalAddr`/`searchMoveFrom` — `data.userSelectedType==='R'?roadAddress:jibunAddress` 규칙 (전 페이지 공통)
- 위젯: `calendar/widget.blade.php` (/calendar/widget, 30초 폴링)
- 컨트롤러: `CalendarController` — eventsBetween(guest 마스킹), children*, exportJson/Ical(role:master,admin 전용)

## 의뢰자 — `resources/views/clients/index.blade.php`

- 내부 탭 셸 `.crm-wrap` + 의뢰자별 탭
- 등록 모달: ncm-* (openNewClientModal — 열 때 전체 입력 초기화), `ncSearchAddress`
- 조회 뷰: `renderClientView(d)` cv-* (인적/방송/장비/커스텀). 장비는 `last_project_equipment`(ClientController::detail — 최신 프로젝트 폴백 + is_latest + 프로젝트 전용 __equip_items 포함, false 토글 숨김)
- 수정 모드: `clientEditMode(id,on)`, ce-*, `ceRefresh` 작성 현황
- `formatCfDisplay(v)` — {value,qty} → "값 × N", boolean → 있음/없음

## 프로젝트 — `resources/views/projects/show.blade.php`

- 추가 정보(동적 필드): `loadProjectFieldsForShow` → `pcfAllDefs()` = 전역(ProjectFieldDefinition) + 프로젝트 전용 장비(`custom_data.__equip_items`, 값은 custom_data[key])
- 장비 항목 편집 모달: pfa-* (`openPcfAdd` — 목록/추가/수정/삭제, 타입: toggle/checkbox/text/select/number)
- 자동 저장: `pcfScheduleSave`(600ms 디바운스) + `pcfSaveNow` 버튼 + `pcfSetStatus` 배지(변경됨→저장 중→저장 완료 HH:MM) + beforeunload 경고
- toggle 타입: `pcfToggleChange`, PCF_QTY_TYPES에 포함(있음 × N)
- 프로젝트 목록 `projects/index.blade.php`: `.proj-shell` 내부 탭 (목록 pane + 상세 iframe pane)

## 피드백 — `resources/views/feedback/index.blade.php` + `FeedbackController`

- 첨부: 이미지+영상(mp4/mov/webm, 110MB). 영상은 `fb-att-vid` 인라인 플레이어. payload `is_video`
- 댓글: 대댓글 1뎁스(parent_id), `fbComment(id, parentId?)`, `fbToggleReply`
- @멘션: `MentionService::users(body)`(접두어 매칭) → `MentionAlert` 알림. 자동완성은 `partials/mention-autocomplete.blade.php`(data-mention 속성, /api/mention-users)
- `fbMentionHtml`(멘션 하이라이트) ≠ `fbBodyHtml(p)`(게시물 본문 렌더) — 이름 충돌 주의!

## 위키 — `wiki/show.blade.php` + `WikiController`

- 회의록(meeting) 댓글: 대댓글(parent_id) + @멘션 (`wiki/partials/comment.blade.php`), draft 시스템(is_draft, 7일 prune)

## 첨부/이미지 인프라

- `ImageThumbnailService` — 640px webp 온디맨드 캐시(thumbs/), 생성 결과 디코딩 검증 후 캐시, 실패 시 원본 폴백
- thumb URL에 `?v=2` 캐시 버스터 (immutable 1년 캐시)
- 진단: `php artisan attachments:doctor "일정제목"` — 원본 손상/크기 불일치/썸네일 상태
- 공용 이미지 뷰어: `partials/image-viewer.blade.php` (window.drgoViewer)

## 권한/알림

- User.role: master > admin > member > guest. `hasPermission(key)` — admin 이상 항상 true, member는 팀 permissions 배열
- 캘린더 백업(export/import)은 `role:master,admin` 라우트
- 웹푸시: Notification via WebPushChannel + database 채널 (FeedbackActivity, MentionAlert, ScheduleReminder 등)

## 스케줄러 (routes/console.php)

schedules:notify 매분 · shipments:refresh 30분 · db:backup 03:30 · disk:check 08:00 · attachments:prune-orphans 월 04:00 · wiki:prune-drafts 04:15 · contracts:sync-calendar 월1 03:00
