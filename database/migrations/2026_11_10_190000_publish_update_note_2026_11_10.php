<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 11/10';

    /**
     * 11/10 배포분 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
<p>11/10 배포 업데이트입니다.</p>

<h2>의뢰자 주소지 라벨 (신규)</h2>
<ul>
<li><strong>주소마다 용도 라벨</strong>: 의뢰자 편집에서 주소 1(메인)과 추가 주소(2~4) 각각에 "자택", "스튜디오", "이사 전/후" 같은 라벨을 달 수 있습니다. 의뢰자 상세에는 "주소 2 · 스튜디오" 형식으로 표시됩니다.</li>
<li><strong>캘린더 연동</strong>: 일정 상세의 '일시·장소'에서 주소 옆에 라벨 칩이 자동으로 붙습니다(일정 주소와 의뢰자 주소를 자동 매칭 — 기존 일정에도 소급 적용). 이사 일정은 출발지/도착지에 각각 붙습니다.</li>
<li><strong>즉석 지정</strong>: 캘린더에서 의뢰자 주소를 일정에 넣을 때 라벨 없는 주소면 명칭 입력 안내가 떠서 바로 지정할 수 있고, 지정한 명칭은 의뢰자 정보에 저장되어 이후 모든 화면에 함께 표시됩니다.</li>
</ul>

<h2>위키 수정 로그 — 리비전 비교 (신규)</h2>
<ul>
<li>위키 문서의 '로그' 버튼이 리비전 비교 화면으로 바뀌었습니다. 왼쪽에서 r1(생성)부터 수정 이력을 고르면, 오른쪽에 줄 단위 비교가 표시됩니다 — 삭제된 줄은 빨강, 추가된 줄은 초록, 한 줄 안에서 바뀐 부분만 진하게 강조됩니다.</li>
<li>바뀐 줄 주변만 보여주고 나머지는 접어서 긴 문서도 한눈에 들어오며, 제목·카테고리 변경은 "이전 → 이후" 한 줄로 표시됩니다. 과거 수정 이력도 전부 소급해서 비교할 수 있습니다.</li>
</ul>

<h2>캘린더</h2>
<ul>
<li><strong>주간/일간 이른·늦은 시간 일정 표시</strong>: 시간축이 9~22시 고정이라 새벽·심야 일정이 안 보이던 문제를 고쳤습니다. 이제 해당 기간에 이른/늦은 일정이 있으면 그 시간대까지 자동으로 넓혀 표시합니다(평소에는 기존처럼 9~22시).</li>
</ul>

<h2>할 일</h2>
<ul>
<li><strong>드래그 순서 변경 복구</strong>: 리스트 뷰에도 드래그 정렬이 생겼습니다(완료는 아래로, 나머지는 끌어다 놓은 순서 유지). 공동 담당 할 일이 섞여 있으면 직원의 순서 저장이 통째로 실패하던 문제도 고쳤습니다.</li>
</ul>

<h2>사용성 개선</h2>
<ul>
<li><strong>의뢰자 검색 결과창 닫기</strong>: 검색 결과창이 아래 영역을 가려 클릭할 수 없던 문제 — 결과창 상단에 '✕ 닫기' 버튼이 생겼고, 바깥 클릭이나 Esc 키로도 닫힙니다(캘린더·견적서·AS·프로젝트 생성·렌탈 계약 전부).</li>
<li><strong>위키 임시저장 자동 정리 수정</strong>: 보관함 기능 도입 후 오래된 임시저장 글이 실제로는 지워지지 않고 쌓이던 문제를 고쳤습니다.</li>
</ul>
HTML;
    }
};
