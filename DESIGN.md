---
name: 닥터고블린 오피스
description: 방송 장비 세팅 회사의 상담·프로젝트·견적·일정 업무 시스템
colors:
  desk-canvas: "#f4f5f7"
  paper: "#ffffff"
  paper-sunken: "#eceef2"
  paper-strong: "#dfe2e8"
  hairline: "#b8bcc8"
  ink: "#1a1e28"
  ink-muted: "#5a6070"
  ledger-blue: "#3b5ea0"
  ledger-blue-deep: "#2e6a8a"
  on-accent: "#ffffff"
  night-desk: "#1f2b40"
  night-desk-line: "#2a3852"
  night-desk-ink: "#b9c4d9"
  night-desk-label: "#7f8fae"
  signal-red: "#c03838"
  signal-green: "#248a38"
  signal-amber: "#907030"
  signal-orange: "#b85c18"
  signal-blue: "#2e6a9a"
  signal-purple: "#5c2e90"
typography:
  headline:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1.3
  title:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "18px"
    fontWeight: 700
    lineHeight: 1.35
  title-sm:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "15px"
    fontWeight: 700
    lineHeight: 1.4
  body:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.55
  body-sm:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "11px"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "0.04em"
  figure:
    fontFamily: "Pretendard Variable, Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif"
    fontSize: "13px"
    fontWeight: 600
    lineHeight: 1.4
    fontFeature: "tnum"
rounded:
  xs: "4px"
  sm: "6px"
  md: "8px"
  lg: "12px"
  pill: "999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
components:
  button-primary:
    backgroundColor: "{colors.ledger-blue}"
    textColor: "{colors.on-accent}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.sm}"
    padding: "7px 16px"
  button-ghost:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink-muted}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.sm}"
    padding: "7px 14px"
  button-danger:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.signal-red}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.sm}"
    padding: "7px 14px"
  input:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 12px"
  card:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "16px"
  chip-filter:
    backgroundColor: "{colors.paper-sunken}"
    textColor: "{colors.ink-muted}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "4px 12px"
  chip-filter-active:
    backgroundColor: "{colors.ledger-blue}"
    textColor: "{colors.on-accent}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "4px 12px"
  nav-item:
    backgroundColor: "{colors.night-desk}"
    textColor: "{colors.night-desk-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 12px"
  nav-item-active:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.night-desk}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 12px"
---

# Design System: 닥터고블린 오피스

## Overview

**Creative North Star: "상담 데스크 (The Consultation Desk)"**

닥터고블린 오피스는 상담사가 하루 종일 펼쳐 두는 책상이다. 의뢰자와 통화하면서 메모하고, 프로젝트 단계를 넘기고, 견적을 맞추고, 일정을 잡는 일이 한 화면에서 끊김 없이 이어져야 한다. 그래서 화면은 차분하고 정돈되어 있어야 하고, 오래 봐도 눈이 피곤하지 않아야 한다. 강조는 지금 해야 할 일 하나에만 쓴다.

밀도는 '업무용 보통'이다. 엑셀처럼 빽빽하지 않지만, 상담 중 필요한 정보(연락처·주소·진행 단계·금액)는 스크롤 없이 한눈에 들어와야 한다. 바탕은 밝은 회색 책상(desk-canvas) 위에 흰 종이(paper) 카드를 올린 구조이고, 왼쪽의 짙은 남색 사이드바(night-desk)가 책상 옆 서랍 역할을 한다.

**색은 현재 값의 기록이다.** 사용자 결정에 따라 색 방향은 UX 개편 때 새로 정한다. 아래 색 토큰은 '지금 화면이 실제로 쓰는 값'이며, 개편 시 이 문서의 Colors 섹션과 토큰을 함께 교체한다. 글자·간격·모양·그림자 규칙은 개편 후에도 유지되는 기준이다.

**Key Characteristics:**
- 밝은 회색 책상 + 흰 종이 카드 + 짙은 남색 사이드바의 3단 구조
- 강조색은 화면 영역당 주요 동작 하나에만 (저장, 등록, 작성 모드)
- 평평한 표면: 그림자는 팝업·메뉴·드래그처럼 실제로 떠 있는 것에만
- 금액·수량은 자릿수 맞춤 숫자로 세로 정렬
- Pretendard 한 서체로 모든 위계를 표현

## Colors

차분한 중성 회색 위에 깊은 파란색 하나가 일을 이끄는 팔레트다. 상태색(빨강·초록·황토 등)은 정보를 전달할 때만 쓴다. (현재 값 — 개편 시 교체 예정)

### Primary
- **Ledger Blue** (ledger-blue): 주요 동작 버튼(저장·등록·발행), 선택된 필터 칩, 활성 탭 밑줄, 진행 단계 스테퍼의 지나온 단계, 작성 모드 버튼. 화면 영역 하나에 주요 동작 하나만 이 색을 쓴다.
- **Ledger Blue Deep** (ledger-blue-deep): 보조 강조(링크성 텍스트, 차트 두 번째 계열). 주요 동작과 경쟁하지 않는 자리에만.

### Neutral
- **Desk Canvas** (desk-canvas): 앱 전체 바탕. 카드가 놓이는 책상.
- **Paper** (paper): 카드·모달·입력칸·표 배경.
- **Paper Sunken** (paper-sunken): 비선택 칩, 표 헤더, 입력 영역 묶음 등 한 단계 들어간 면.
- **Paper Strong** (paper-strong): 구분이 더 필요한 눌린 면, 진행 바 트랙.
- **Hairline** (hairline): 카드·입력칸 테두리, 구분선.
- **Ink** (ink): 본문·제목 글자.
- **Ink Muted** (ink-muted): 날짜·담당자·설명 같은 보조 글자. 밝은 바탕에서 4.5:1 이상을 만족하는 유일한 보조 글자색이다.
- **Night Desk** (night-desk) / **Night Desk Line** (night-desk-line): 데스크톱 사이드바 바탕과 그 안의 구분선.
- **Night Desk Ink** (night-desk-ink) / **Night Desk Label** (night-desk-label): 사이드바 메뉴 글자와 그룹명.

### Status
- **Signal Red** (signal-red): 삭제·위험·환불·음수 금액·잔금 경고.
- **Signal Green** (signal-green): 완료·결제완료·정상.
- **Signal Amber** (signal-amber) / **Signal Orange** (signal-orange): 주의·대기·보류.
- **Signal Blue** (signal-blue) / **Signal Purple** (signal-purple): 정보성 상태·카테고리 구분.
- 캘린더 일정 카테고리 색은 관리자 설정(CalendarCategory)에서 오며 `--chip-{카테고리}-bg/text` 토큰으로 주입된다. 이 문서의 토큰이 아니라 데이터다.

### Named Rules
**The One Desk Lamp Rule.** Ledger Blue는 화면 영역 하나에 주요 동작 하나만 비춘다. 같은 영역에 파란 버튼이 둘이면 하나는 ghost로 내린다.

**The Readable Gray Rule.** 보조 글자는 Ink Muted만 쓴다. #94a3b8·#9aa5b1·#8a97a5 같은 연한 회색을 글자에 쓰지 않는다(밝은 바탕에서 2.2~2.9:1로 미달). 연한 회색은 테두리·아이콘 장식·비활성 표시에만.

**The Status Means Something Rule.** 상태색은 장식으로 쓰지 않는다. 빨강은 언제나 위험·차감, 초록은 언제나 완료다.

## Typography

**Body Font:** Pretendard Variable (with Pretendard, -apple-system, Apple SD Gothic Neo, Malgun Gothic, sans-serif)
**Label/Mono Font:** 같은 Pretendard. 페이지 번호 등 일부에 SF Mono가 쓰이나 체계에 포함하지 않는다.

**Character:** 한 서체로 굵기와 크기만 바꿔 위계를 만든다. 한글 가독성이 좋고 숫자가 단정해 상담·견적 화면에 맞는다.

데스크톱(981px 이상)에서는 앱 셸에 `zoom: 1.07`이 걸려 실제 화면 크기는 아래 값의 약 1.07배다. 토큰은 zoom 이전 값으로 적는다.

### Hierarchy
- **Headline** (700, headline): 페이지 제목(견적서·의뢰자 목록 상단).
- **Title** (700, title): 상세 화면의 대상 이름(의뢰자 닉네임, 프로젝트명, 일정 제목).
- **Title Small** (700, title-sm): 카드·섹션 제목.
- **Body** (400~500, body): 기본 본문·입력값·표 내용.
- **Body Small** (400, body-sm): 보조 설명·버튼 글자·표의 부가 정보.
- **Label** (700, label, letter-spacing 0.04em): 칩·배지·메뉴 그룹명·필드 이름. 체계의 최소 크기다.
- **Figure** (600, figure, tabular numbers): 금액·수량·날짜 숫자. 표와 합계에서 세로 정렬.

### Named Rules
**The 11px Floor Rule.** 기능하는 글자(라벨·배지·칩·날짜·메뉴)는 11px 미만으로 만들지 않는다. 현재 10px 228곳, 9px 33곳이 있으며 개편에서 걷어낸다.

**The Six Steps Rule.** 글자 크기는 11·12·13·15·18·22px 여섯 단계만 쓴다. 10.5·11.5·12.5·13.5px 같은 반 픽셀 단계는 새로 만들지 않는다(현재 34종 → 6종).

**The Column Numbers Rule.** 금액·수량·합계는 `font-variant-numeric: tabular-nums`로 자릿수를 맞춘다. 금액 열은 오른쪽 정렬.

## Layout

- **셸:** 데스크톱은 왼쪽 고정 사이드바(200px, 접으면 62px) + 상단 탭 바(앱 내 탭) + 본문. 980px 이하는 상단 헤더 + 햄버거 메뉴.
- **화면 구조:** 목록형 화면은 좌측 목록(220px 내외) + 우측 상세. 상세 화면은 카드 열 1~2개.
- **간격 리듬:** 4·8·12·16·24px 단계(spacing 토큰). 카드 안쪽 16px, 카드 사이 12~16px, 폼 필드 사이 12px.
- **반응형 기준:** 768px(모바일 레이아웃 전환), 900px(2열 → 1열), 980/981px(사이드바 ↔ 햄버거).
- **모바일:** 좌측 목록은 슬라이드 서랍, 주요 작성 동작은 하단 고정 바나 떠 있는 추가 버튼. 390px에서 가로 스크롤이 없어야 한다.
- **작성 모드:** 의뢰자·프로젝트·일정·견적서는 앱 메뉴 없이 한 건만 띄우는 독립 창으로 열 수 있다. 같은 화면을 재사용하므로 레이아웃 규칙은 동일하다.

### Named Rules
**The No Card In Card Rule.** 카드 안에 또 카드를 넣지 않는다. 안쪽 묶음은 테두리 없는 섹션과 간격·구분선으로 나눈다.

## Elevation & Depth

평평한 시스템이다. 면의 구분은 바탕 단차(desk-canvas → paper → paper-sunken)와 hairline 테두리로 한다. 그림자는 실제로 화면 위에 떠 있는 층에만 쓴다.

### Shadow Vocabulary
- **Popover** (`box-shadow: 0 6px 20px rgba(0,0,0,0.15)`): 드롭다운·작성 모드 메뉴·자동완성 결과창.
- **Panel** (`box-shadow: 0 10px 30px rgba(0,0,0,0.18)`): 상단 바 메뉴 패널처럼 넓게 뜨는 패널.
- **Modal** (`box-shadow: 0 8px 40px rgba(0,0,0,0.12)`): 모달 창 (배경 딤과 함께).
- **Lifted** (`box-shadow: 0 2px 8px rgba(0,0,0,0.25)`): 짙은 사이드바 위의 활성 메뉴처럼 어두운 면에서 들어 올린 항목.

### Named Rules
**The Floating-Only Shadow Rule.** 쉬고 있는 카드에는 그림자가 없다. 그림자는 떠 있는 층(팝오버·패널·모달·드래그 중 항목)의 신호다.

## Shapes

부드럽지만 둥글지 않은 모서리. 작은 컨트롤일수록 작은 반경, 큰 면일수록 큰 반경을 쓴다.

- **xs (4px):** 배지·작은 태그.
- **sm (6px):** 버튼·작은 입력.
- **md (8px):** 입력칸·메뉴 항목·리스트 행. 시스템에서 가장 흔한 값.
- **lg (12px):** 카드·모달·패널.
- **pill (999px):** 필터 칩·상태 칩·우선순위 필.

현재 반경 23종(3·5·7·10·14px 등)이 섞여 있다. 새 화면은 위 다섯 단계만 쓴다.

## Components

### Buttons
차분하고 확실하게. 누를 것이 무엇인지 한눈에 보이되 소리치지 않는다.
- **Shape:** 살짝 둥근 모서리(sm 6px)
- **Primary:** Ledger Blue 바탕 + 흰 글자, body-sm 굵게, 7px 16px. 영역당 하나.
- **Ghost:** 흰 바탕 + Ink Muted 글자 + hairline 테두리. 취소·로그·보조 동작.
- **Danger:** 흰 바탕 + Signal Red 글자·테두리. 삭제. hover 시 빨간 바탕 + 흰 글자.
- **Write Mode (signature):** Ledger Blue 글자·테두리 + 옅은 파란 바탕의 '↗ 작성 모드' 버튼과 ▾ 분할 버튼. hover 시 채워짐.
- **Focus:** 키보드 포커스 링은 모든 버튼에 보여야 한다(현재 미흡 — harden 단계에서 공통 `:focus-visible` 링으로 통일).

### Chips
- **Filter:** paper-sunken 바탕 + Ink Muted 글자, pill. 선택 시 Ledger Blue 바탕 + 흰 글자.
- **Status:** 상태색 글자 + 같은 색 옅은 바탕(또는 테두리). 결제완료=green, 환불·차감=red, 대기=amber.
- **Calendar category:** 카테고리 색 바탕(투명도) + 왼쪽 색 띠. 캘린더에서만 허용되는 기능적 띠.

### Cards / Containers
- **Corner Style:** lg (12px)
- **Background:** paper, desk-canvas 위에 놓임
- **Shadow Strategy:** 없음 (Elevation 참조)
- **Border:** hairline 1px 또는 없음(바탕 단차로 충분할 때)
- **Internal Padding:** 16px, 카드 제목(title-sm)과 내용 사이 12px

### Inputs / Fields
- **Style:** paper 바탕, hairline 1px 테두리, md (8px), 9px 12px
- **Focus:** 테두리를 Ledger Blue로. 포커스 표시를 지우기만 하고 대체하지 않는 것은 금지
- **Label:** 입력칸 위 label 글자(Ink Muted). 보이는 라벨이 없는 표 안 입력에는 aria-label
- **Error / Disabled:** 오류는 Signal Red 테두리 + 아래 안내 문구, 비활성은 paper-sunken 바탕 + 낮은 대비 글자

### Navigation
- **Sidebar (데스크톱):** Night Desk 바탕, 메뉴 글자 Night Desk Ink 13px, 그룹명 label. hover는 흰색 8% 덧칠, 활성 메뉴는 흰 paper 바탕 + Night Desk 글자 + Lifted 그림자.
- **App tabs:** 상단 탭 바에 열린 화면들이 브라우저 탭처럼 쌓임. 오른쪽에 새로고침·작성 모드·가이드·알림.
- **Mobile:** 상단 헤더 + 햄버거, 화면별 목록은 서랍.

### Process Stepper (signature)
AS 현황·의뢰자 프로젝트 탭·프로젝트 상세에서 쓰는 진행 단계 바. 점(●)과 연결선으로 단계를 잇고, 지나온 단계는 Ledger Blue로 채우며 현재 단계는 옅은 파란 링으로 강조한다. 단계명은 label 크기, 처리 날짜는 그 아래 body-sm muted. 취소된 흐름은 멈춘 단계까지 흐리게.

## Do's and Don'ts

### Do:
- **Do** 색·반경·간격은 `var(--…)` 토큰으로만 쓴다(현재 하드코딩 색 2,418개, 인라인 style 3,124개를 줄여 나간다).
- **Do** 보조 글자는 Ink Muted로, 기능 글자는 11px 이상으로.
- **Do** 금액·수량은 tabular-nums + 오른쪽 정렬, 음수(환불·차감)는 Signal Red.
- **Do** 그림자는 팝오버·패널·모달·드래그 중인 항목에만.
- **Do** 클릭되는 것은 `<button>`이나 링크로 만들어 키보드로도 누를 수 있게 한다.

### Don't:
- **Don't** 글자에 #94a3b8 같은 연한 회색을 쓰지 않는다.
- **Don't** `outline: none`으로 포커스 표시를 지우고 대체 표시 없이 두지 않는다.
- **Don't** 카드 안에 카드를 넣지 않는다.
- **Don't** 10.5·11.5·12.5px 같은 반 픽셀 글자 크기를 새로 만들지 않는다.
- **Don't** 카드에 한쪽 굵은 색 띠(side stripe)를 장식으로 달지 않는다. 캘린더 카테고리 칩의 색 띠는 기능이므로 예외.
- **Don't** `transition: all`을 쓰지 않는다. 바뀌는 속성만 지정한다.
