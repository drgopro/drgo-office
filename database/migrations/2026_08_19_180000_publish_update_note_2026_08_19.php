<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 8/19 (수)';

    /**
     * 2026-08-19 배포분(8/18 노트 발행 이후 포함) 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
        if (DB::table('wikis')->where('type', 'update')->where('title', self::TITLE)->exists()) {
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

    public function down(): void
    {
        DB::table('wikis')->where('type', 'update')->where('title', self::TITLE)->delete();
    }

    private function content(): string
    {
        return <<<'HTML'
<h2>8/19 (수)</h2>
<h3>✅ 할 일 — 미니 프로젝트처럼</h3>
<ul>
<li><strong>진행 단계(체크리스트)</strong>: 할 일 안에 단계를 만들어 진행 상황을 관리합니다. 입력 후 Enter로 연속 추가, 제목 클릭으로 바로 수정, 드래그로 순서 변경. 카드에는 진행 바(2/5)가 표시됩니다.</li>
<li><strong>전원 완료 규칙</strong>: 담당자가 여럿이면 각자 '내 완료 체크'를 하고, 전원이 완료하면 할 일이 자동으로 완료 처리됩니다. 카드와 상세에서 누가 완료했는지(✓) 보입니다.</li>
<li><strong>미완료 리마인드</strong>: 마감 임박(D-1)·경과한 할 일의 미완료 담당자에게 매일 오전 9시 채널톡 멘션 알림이 갑니다. 남은 진행 단계도 함께 안내합니다 (보류 중인 할 일은 제외).</li>
<li><strong>디자인 리뉴얼</strong>: 카드형 보드·리스트·등록/상세 모달 전체를 새 디자인으로 다듬었습니다. 카드에는 우선순위 점·파스텔 필·완료 카운트가 표시됩니다.</li>
<li><strong>리스트 뷰 오른쪽 패널에서도 진행 단계</strong>를 바로 추가·완료·정렬할 수 있습니다.</li>
<li>내용이 긴 할 일에서 진행 단계 섹션이 화면 밖으로 밀리던 문제를 수정했습니다 — 모달이 화면 안에 고정되고 내용만 스크롤됩니다.</li>
</ul>
<h3>💳 입금 내역</h3>
<ul>
<li><strong>페이앱 결제현황 탭</strong>: 견적서로 보낸 페이앱 결제요청의 상태(결제완료/대기/취소·환불), 결제완료 합계, 의뢰자(닉네임 포함)를 한눈에 봅니다. 행마다 견적서/결제페이지 버튼으로 바로 이동합니다.</li>
<li><strong>원문 보기</strong>: 입금자명 옆 '원문' 열에서 버튼을 누르면 문자 원문이 펼쳐집니다 (기존 마우스오버 툴팁 대체 — 모바일에서도 확인 가능).</li>
<li><strong>입금자명 파싱 개선</strong>: (주)패러블엔터테인먼트 같은 법인명이 '파싱 실패'로 뜨거나 괄호가 잘리던 문제를 수정하고, 기존 내역도 전부 다시 파싱했습니다.</li>
</ul>
<h3>📅 캘린더 · 배송</h3>
<ul>
<li><strong>배송 현황에 사업장 위치(📍)</strong>가 표시됩니다 — 어느 허브/대리점에서 마지막으로 처리됐는지 바로 확인. 배송완료 건도 위치가 비어 있으면 열람 시 자동으로 채웁니다 (택배사 조회가 만료된 오래된 건은 제외).</li>
<li><strong>카테고리 '만 보기' 해제 시 이전 선택 복원</strong>: 방문/스튜디오만 체크해 둔 상태에서 다른 카테고리 '만 보기'를 썼다 해제하면, 전체 선택이 아니라 원래 보던 조합으로 돌아갑니다.</li>
</ul>
<h3>✨ 기타</h3>
<ul>
<li><strong>날짜 입력 개선</strong>: 모든 페이지에서 날짜 칸 아무 곳이나 클릭하면 바로 달력이 열립니다 (달력 아이콘을 정확히 누를 필요 없음).</li>
<li>왼쪽 메뉴 '지식 · 자산' 섹션 위에 구분선을 추가해 그룹 경계를 명확히 했습니다.</li>
</ul>
HTML;
    }
};
