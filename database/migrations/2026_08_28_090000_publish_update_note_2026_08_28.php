<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 8/28 (금)';

    /**
     * 2026-08-28 배포분(8/27 노트 발행 이후) 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
<h2>8/28 (금)</h2>
<h3>서버 이전 — 속도 개선</h3>
<ul>
<li><strong>싱가포르 리전으로 서버 이전</strong>: 기존 해외(미주) 서버를 싱가포르 리전으로 옮겨 응답 지연이 200ms대에서 70ms대로 줄었습니다. 화면 전환과 저장이 전반적으로 빨라집니다. 주소·계정·데이터는 모두 그대로이며, 혹시 점검 화면이 보이면 브라우저를 재시작해 주세요.</li>
</ul>
<h3>견적서</h3>
<ul>
<li><strong>특가/할인 표시</strong>: 항목별로 특가(세팅 진행 시 단독 특가 납품) 또는 할인(재방문·이벤트, % 또는 금액 입력)을 지정할 수 있습니다. 정가는 취소선으로, 지정가는 배지와 함께 출력물과 의뢰자용 견적서에 표시되고 하단에 안내 각주가 자동으로 붙습니다. 값은 해당 견적서에만 저장되어 제품 관리 가격은 변하지 않습니다.</li>
<li><strong>카테고리 우선순위 정렬</strong>: 재고 관리의 카테고리 순서(드래그 정렬)가 견적서 출력 순서의 우선순위로 동작합니다. 그래픽카드를 먼저 담아도 CPU가 위로 올라가는 식이며, '우선순위 정렬' 버튼으로 이미 담긴 항목도 한 번에 재정렬할 수 있습니다.</li>
<li><strong>분류(2차) 직접 수정</strong>: 항목의 분류 텍스트를 클릭해 렌즈/바디/케이블 같은 구분을 바로 적을 수 있습니다. 해당 견적서 표시용으로만 저장되고 실제 카테고리에는 영향이 없습니다.</li>
</ul>
<h3>캘린더</h3>
<ul>
<li><strong>연동 장비 표시</strong>: 의뢰자를 연동하면 프로젝트의 장비 정보를 불러와 수정 뷰(읽기 전용)와 요약 뷰에 표시합니다. 분류마다 테두리 칩과 구분선이 있어 카메라/조명/오디오 구분이 한눈에 보이고, 수기 장비 목록은 그대로 유지됩니다.</li>
<li><strong>견적서 PNG 자동 첨부</strong>: 일정에서 견적서를 불러오면 그 견적서의 이미지가 자동 생성되어 '견적서' 첨부에 담기고 저장 시 업로드됩니다. 같은 견적서는 중복 생성하지 않습니다.</li>
<li><strong>모바일 날짜 이동 화살표</strong>: 모바일 헤더 타이틀 양옆에 이동 버튼이 생겨 일 뷰는 하루, 주 뷰는 한 주씩 넘길 수 있습니다.</li>
<li><strong>주소 복사·지도 버튼 정리</strong>: 동선 조회 버튼 대신 각 주소 오른쪽에 복사 아이콘이 붙고, 출발지/도착지 주소 아래에 카카오·네이버 지도 아이콘 버튼이 들어갔습니다.</li>
<li><strong>담당자 다수 표시 수정</strong>: 담당자가 많을 때 화면이 옆으로 밀려 닫기 버튼이 잘리던 문제를 고쳤습니다. 넘치는 이름은 말줄임으로 표시됩니다.</li>
</ul>
<h3>통계</h3>
<ul>
<li><strong>플랫폼 이동 수요</strong>: 의뢰자 플랫폼 수정 이력에서 어떤 플랫폼에서 어떤 플랫폼으로 이동했는지 집계합니다. 흐름을 클릭하면 누가 언제 이동했는지 보이고, 최근 6개월 이동 추이도 함께 표시됩니다.</li>
<li><strong>매출 상세 개선</strong>: 출처 필터(전체/견적서 결제/단순 결제)가 추가됐고, 결제 시점과 매출 인식 시점이 다른 기간에 걸린 견적서가 목록에서 빠지던 문제를 보완했습니다.</li>
</ul>
<h3>의뢰자</h3>
<ul>
<li><strong>플랫폼 필터</strong>: 의뢰자 목록에서 SOOP/유튜브/치지직/틱톡/팬더티비/기타 칩으로 필터링할 수 있습니다. 등급·검색과 조합됩니다.</li>
<li><strong>표기 통일</strong>: '팬더' 표기를 '팬더티비'로 통일하고 기존 데이터도 일괄 정리했습니다.</li>
</ul>
<h3>공통</h3>
<ul>
<li><strong>로딩 표시</strong>: 모든 페이지에서 데이터를 불러오는 데 시간이 걸리면 화면 상단에 로딩 스피너가 표시됩니다. 캘린더 날짜 넘김처럼 진행 여부가 안 보이던 상황이 해소됩니다.</li>
</ul>
HTML;
    }
};
