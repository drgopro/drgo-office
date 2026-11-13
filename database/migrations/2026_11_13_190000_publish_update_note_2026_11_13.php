<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 11/13';

    /**
     * 11/11~11/13 배포분 업데이트 노트를 위키 '업데이트' 게시판에 등록.
     * 운영 DB에 직접 접근할 수 없어 배포 자동 마이그레이션으로 발행한다.
     * 사용자가 없는 환경(테스트 sqlite)에서는 아무것도 하지 않아 기존 테스트에 영향 없음.
     */
    public function up(): void
    {
        $authorId = DB::table('users')->where('role', 'master')->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');
        if (! $authorId) {
            return; // 테스트/빈 DB — 스킵
        }
        $alreadyPublished = DB::table('wikis')->where('type', 'update')->where('title', self::TITLE)
            ->whereNull('deleted_at')->exists();
        if ($alreadyPublished) {
            return; // 재실행 안전
        }

        DB::table('wikis')->insert([
            'title' => self::TITLE,
            'type' => 'update',
            'category' => '업데이트',
            'category_id' => null,
            'content' => $this->content(),
            'is_pinned' => 0,
            'is_draft' => 0,
            'created_by' => $authorId,
            'updated_by' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** 이 마이그레이션이 넣은 본문과 동일한 살아 있는 행만 제거 — 직접 작성한 글과 보관함 글은 보존 */
    public function down(): void
    {
        DB::table('wikis')->where('type', 'update')->where('title', self::TITLE)
            ->where('content', $this->content())->whereNull('deleted_at')->delete();
    }

    private function content(): string
    {
        return <<<'HTML'
<p>11/11~11/13 배포 업데이트입니다. 화면 디자인 개편이 시작됐고, 상담하면서 쓰기 편한 '작성 모드'가 생겼습니다.</p>

<h2>작성 모드 (신규)</h2>
<ul>
<li><strong>독립된 창에서 작성</strong>: 의뢰자·프로젝트·캘린더·견적서를 오피스 탭이 아닌 별도 창(또는 새 탭)으로 띄워 작성할 수 있습니다. 상단 바의 <strong>[작성 모드]</strong> 버튼이나 각 화면의 '↗ 작성 모드' 버튼으로 엽니다. ▾로 새 창/새 탭 중 고를 수 있고 선택은 기억됩니다.</li>
<li><strong>한 창에서 세 탭으로 이동</strong>: 의뢰자·프로젝트·캘린더 작성 모드는 창 하나를 같이 쓰고, 창 위의 <strong>의뢰자 · 프로젝트 · 캘린더</strong> 탭으로 오갑니다. 탭마다 마지막으로 본 건을 기억하고, 돋보기로 다른 건을 찾을 수 있습니다. 의뢰자 화면에서 프로젝트 탭을 누르면 그 의뢰자의 프로젝트 목록이, 프로젝트 화면에서 의뢰자 탭을 누르면 그 의뢰자가 바로 열립니다. (견적서는 건별 창 유지)</li>
<li><strong>기존 건 수정·신규 등록</strong>: 의뢰자를 찾아 [열기] 또는 [수정](바로 수정 화면)으로 열 수 있고, 새 의뢰자 등록도 작성 모드에서 할 수 있습니다. 등록하면 그 창이 그대로 그 의뢰자의 작성 모드가 됩니다.</li>
<li><strong>자동 동기화</strong>: 작성 모드 창에서 저장하면 메인 오피스 화면에도 반영되고, 다른 창에서 같은 건이 수정되면 알려줍니다.</li>
</ul>

<h2>화면 디자인 개편 — 의뢰자 · 프로젝트 (신규)</h2>
<ul>
<li><strong>한눈에 보는 요약 줄</strong>: 의뢰자 화면 이름 아래에 진행 중인 프로젝트의 단계 막대, 다음 일정(남은 날), 최근 견적 금액이 함께 보입니다. 진행 중인 프로젝트가 여러 건이면 "진행 중 N건"과 이름 버튼으로 바꿔 볼 수 있습니다. 프로젝트 화면에는 다음 일정 · 결제 순액 · 의뢰자 요약이 붙습니다.</li>
<li><strong>기본 정보 탭 정리</strong>: 왼쪽은 메모(노란 메모지), 오른쪽은 의뢰자 정보입니다. 비어 있는 칸은 숨기고 "비어 있는 항목 N개 · 채우기"로 묶었습니다.</li>
<li><strong>색 테마</strong>: 왼쪽 메뉴는 그대로 두고 내용 영역을 밝은 회색 바탕 · 흰 카드 · 먹색 주요 버튼으로 정리했습니다.</li>
<li><strong>이전 디자인으로 보기</strong>: 화면 위 <strong>[이전 디자인]</strong> 버튼을 누르면 나만 예전 화면으로 돌아갑니다. 다시 누르면 새 디자인입니다.</li>
</ul>

<h2>담당자 지정 (신규)</h2>
<ul>
<li>의뢰자 등록·수정, 프로젝트 생성·수정에서 <strong>담당자를 따로 고를 수 있습니다</strong>. 고르지 않으면 지금처럼 작성자가 담당자가 됩니다. 담당자가 없던 건은 수정 화면에서 '미지정'으로 표시됩니다.</li>
</ul>

<h2>견적서</h2>
<ul>
<li><strong>테스트 견적서</strong>: 기능 확인용으로 검토 없이 저장할 수 있는 테스트 모드가 생겼습니다(견적서 편집 화면 아래 ⋯ 메뉴 → 테스트 모드). 테스트 견적은 통계·매출에서 빠지고 인쇄물에 '테스트' 표시가 붙습니다.</li>
<li><strong>환불·차감 품목(음수 수량)</strong>: 수량을 음수로 넣어 반품·환불을 표시할 수 있습니다. 음수 품목은 빨간 글씨로, 합계에 '차감 합계' 줄이 따로 나옵니다.</li>
<li><strong>소계에 수량 표시</strong>: 분류별 소계에 제품 수량이 함께 나옵니다.</li>
<li><strong>거래명세서</strong>: 인쇄 시 상단의 "거래명세서 #번호" 머리글을 없애고 표 테두리를 진하게 했습니다.</li>
<li><strong>위약 안내</strong>: 방문·원격 세팅 위약 안내 문구를 새 기준(v2)으로 바꿨고, 안내 페이지의 이중 테두리를 정리했습니다.</li>
</ul>

<h2>의뢰자</h2>
<ul>
<li><strong>프로젝트 탭 진행 단계</strong>: 프로젝트 목록마다 AS 현황처럼 진행 단계 바가 보입니다.</li>
<li><strong>메모 작성 시 탭이 견적서로 넘어가던 문제</strong>를 고쳤습니다.</li>
<li><strong>모바일 의뢰자 목록이 잘리던 문제</strong>를 고쳤습니다.</li>
</ul>

<h2>읽기 편하게 · 모바일 정리</h2>
<ul>
<li><strong>글자 크기·대비</strong>: 화면 전반의 본문 글자를 한 단계 키우고, 흐린 회색 글씨를 읽기 좋은 진하기로 바꿨습니다.</li>
<li><strong>버튼 통일</strong>: 화면마다 제각각이던 버튼을 같은 높이·모양으로 맞췄습니다(주요 동작 1개만 진하게, 삭제는 빨강).</li>
<li><strong>모바일</strong>: 머리글 로그아웃 버튼, 재고 툴바, 위키·공지 버튼, 프로젝트·견적서 상태 탭이 잘리거나 길게 쌓이던 것을 정리했습니다.</li>
<li><strong>접힌 사이드바</strong>: 로고가 잘려 보이던 것을 'DRGO' 글자로 바꿨습니다.</li>
<li><strong>작성 모드 캘린더 분류 색</strong>: 작성 모드 창에서 일정 분류 색이 기본색으로 보이던 문제를 고쳤습니다.</li>
</ul>
HTML;
    }
};
