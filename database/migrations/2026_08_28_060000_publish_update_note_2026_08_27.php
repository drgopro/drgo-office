<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 8/27 (목)';

    /**
     * 2026-08-27 배포분(8/26 노트 발행 이후) 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
<h2>8/27 (목)</h2>
<h3>매출 통계 개편 — 매출 인식 원장</h3>
<ul>
<li><strong>매출 인식 기준 정리</strong>: 결제완료 견적서는 결제된 날짜에 매출로 집계됩니다. 프로젝트와 연동된 견적서는 프로젝트가 완료되면 완료 일자로 옮겨 다시 집계되고, 완료를 해제하면 결제일로 돌아갑니다. 취소·환불은 환불한 날짜에 매출에서 차감됩니다.</li>
<li><strong>세팅비/장비판매 분리 집계</strong>: 통계와 엑셀 내보내기에서 세팅비(서비스) 매출과 장비판매 매출이 나뉘어 보입니다. 재고관리 카테고리의 서비스/장비 버튼으로 지정하면 하위 카테고리와 소속 제품에 자동 상속되고, 과거 견적서 통계까지 자동 재계산됩니다. 예외 제품은 제품 수정 모달의 '매출 분류'로, 수기 항목은 빌더의 '서비스' 체크로 지정합니다.</li>
<li><strong>이중 집계 제거</strong>: 견적서 결제완료 건이 프로젝트 매출과 겹쳐 두 번 잡히던 문제를 정리했습니다. 프로젝트에 수동으로 기록한 결제와 견적서 결제완료가 같은 돈이면(같은 프로젝트·같은 금액) 하나로 묶어 한 번만 집계합니다.</li>
<li><strong>매출 상세 개선</strong>: 기간을 직접 설정할 수 있고 이번 달/지난달/최근 3개월/올해 빠른 선택 버튼이 생겼습니다. 결제 카드를 펼치면 연결된 견적서 번호와 결제완료 일자가 보이고 '견적서 보기'로 바로 열 수 있습니다.</li>
</ul>
<h3>견적서 빌더 모바일 최적화</h3>
<ul>
<li><strong>1열 카드형 레이아웃</strong>: 모바일에서 3열 화면이 세로 1열로 재배치됩니다. 담긴 항목은 카드형으로 보이고, 하단에 합계와 저장 버튼이 고정됩니다.</li>
<li><strong>시트형 패널</strong>: 제품 담기·프리셋·수기 제품 추가가 아래에서 올라오는 시트로 열립니다. 하단 바에는 초기화·견적서 출력·제품 담기·저장이 나오고, 나머지 기능은 상단 더보기 버튼에 모았습니다.</li>
<li><strong>목록도 카드형</strong>: 견적서 목록·프리셋 목록·주문 내역이 모바일에서 카드형으로 바뀌어 가로 스크롤 없이 볼 수 있습니다.</li>
</ul>
<h3>엑셀 견적 가져오기</h3>
<ul>
<li><strong>엑셀 업로드로 항목 담기</strong>: 기존 엑셀 견적서 파일을 빌더에 업로드하면 대분류·중분류·제품명·단가·수량을 읽어 항목으로 담습니다. 제품관리에 있는 제품은 자동 연결되고, 없는 제품은 수기 항목으로 추가됩니다.</li>
<li><strong>항목 비고</strong>: 견적 항목마다 비고를 기록할 수 있습니다. 엑셀의 비고 열도 함께 가져옵니다.</li>
</ul>
<h3>주문/배송 — 직접발송 재고 연동</h3>
<ul>
<li><strong>직접발송만 재고 차감</strong>: 견적서 결제만으로는 재고가 움직이지 않고, 주문/배송에서 '직접발송'으로 처리한 수량만 재고에서 차감됩니다. 환불하거나 직접발송을 해제하면 재고가 다시 복원됩니다. 세트는 구성품 단위로 반영됩니다.</li>
<li><strong>재고 부족 확인</strong>: 직접발송 처리 시 재고가 음수로 떨어지는 제품이 있으면 확인 팝업이 먼저 뜨고, 확인한 경우에만 기록됩니다.</li>
</ul>
<h3>견적서 배송지 정보</h3>
<ul>
<li><strong>배송받을 주소·공동현관</strong>: 견적서 주문정보 하단에 배송지 주소와 공동현관 출입 정보를 입력할 수 있습니다. 내부 확인용으로 의뢰자용 견적서에는 표시되지 않으며, 주문 내역 카드에서도 확인할 수 있습니다.</li>
</ul>
<h3>그 외 개선</h3>
<ul>
<li><strong>프리셋 패널 접기</strong>: 빌더의 프리셋 패널을 화살표 버튼으로 접고 펼칠 수 있고 상태가 기억됩니다.</li>
<li><strong>자정 넘김 일정 정렬</strong>: 밤을 넘겨 다음날 새벽에 끝나는 일정이 다음날에는 종료 시각 기준으로 정렬·표시됩니다.</li>
<li><strong>시기 요청 '일정 변경 불가'</strong>: 캘린더 시기 요청 항목에 일정 변경 불가 옵션이 추가됐습니다.</li>
<li><strong>버그 수정</strong>: 주문/배송에서 주문완료나 직접발송을 누를 때 의뢰자용 링크 복사 팝업이 잘못 뜨던 문제를 고쳤습니다.</li>
</ul>
HTML;
    }
};
