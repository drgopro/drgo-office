<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 11/5';

    /**
     * 11/4 노트 발행 이후 배포분(11/4 밤 ~ 11/5) 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
<p>11/4 노트 발행 이후 반영된 업데이트입니다. (11/4 밤 ~ 11/5 배포분)</p>

<h2>견적서 검토 (신규)</h2>
<ul>
<li><strong>검토 버튼</strong>: 견적서 빌더 하단의 [검토] 버튼을 누르면 품목을 자동 점검합니다. 빠진 품목(컴퓨터인데 모니터·키보드·OS 없음 등 규칙 14종), 수량 부족, 영상·오디오 연결 끊김(카메라→캡처보드, 마이크→믹서 케이블 경로), 렌즈·배터리·조명 마운트 호환, 규격 미입력 제품을 한 번에 잡아냅니다. 결과는 우측 패널의 <strong>프리셋 | 검토</strong> 탭에 떠서 견적을 고치면서 나란히 볼 수 있습니다.</li>
<li><strong>바로 담기</strong>: "모니터 없음" 같은 카드에서 후보 제품을 골라 [N개 담기]를 누르면 견적에 바로 담기고 즉시 재검토됩니다. 빼고 진행할 때는 사유(고객 보유/제외 요청/대체)를 기록합니다.</li>
<li><strong>검토 완료와 잠금</strong>: 모든 항목을 확인하면 검토 완료(검토자·시각 기록)가 되고, <strong>검토가 끝나지 않은 견적서는 출력·발행이 잠깁니다</strong>. 검토 후 품목이 바뀌면 다시 검토해야 합니다. 이미 발행·결제된 기존 견적서의 출력은 그대로 허용됩니다.</li>
<li><strong>추가 차수 검토</strong>: 차수(추가 견적)는 본 견적서·기존 차수와 합산한 상태로 판정하되, 이 차수로 새로 생긴 문제만 표시합니다. 본 견적 품목이 바뀌면 차수의 검토 완료도 무효가 됩니다.</li>
<li><strong>검토 규칙 관리</strong>: 견적서 목록 상단의 [검토 규칙] 버튼(노란색)으로 들어갑니다. 규칙 켜기/끄기·추가, 역할 목록, 카테고리/제품별 역할 지정, 제품 규격 입력(미입력 필터), 출력·발행 잠금 토글을 담당자가 직접 관리합니다. 신제품은 "규격 확인" 탭에 미입력으로 모이니 규격만 채우면 자동 검사 대상이 됩니다. 권한은 팀 권한의 <strong>견적서 &gt; 검토 기준 관리</strong>로 부여합니다.</li>
</ul>

<h2>채널톡</h2>
<ul>
<li><strong>지금 동기화 버튼</strong>: 의뢰자 등록의 채널톡 연동 패널에서 방금 상담한 고객이 검색에 안 뜰 때 [지금 동기화]를 누르면 몇 초 안에 최근 상담 고객을 가져와 재검색합니다. 전화번호가 일치하는 미연동 의뢰자는 자동 연동됩니다. (10분 주기 자동 동기화는 그대로 유지)</li>
<li><strong>일정 삭제 알림</strong>: 일정을 삭제하면 채널톡 그룹으로 삭제 알림이 발송됩니다. (생성·변경 알림과 동일한 채널)</li>
</ul>

<h2>통계</h2>
<ul>
<li><strong>커스텀 뷰 편집 권한 통합</strong>: 통계를 볼 수 있는 사람(통계 &gt; 조회 권한)은 누구나 탐색기 커스텀 뷰를 편집할 수 있습니다. 별도 권한 부여가 더는 필요 없습니다.</li>
</ul>

<h2>견적서 빌더 UI</h2>
<ul>
<li>하단 버튼 정리: 로그·거래명세서·견적서 출력·삭제는 저장 옆 <strong>[⋯] 메뉴</strong>로 접었습니다. 좁은 창에서 버튼 글자가 세로로 꺾이던 문제도 함께 수정했습니다.</li>
<li>견적서 목록 헤더의 생성 버튼을 컴팩트하게 줄이고, 검토 규칙 바로가기를 추가했습니다.</li>
</ul>
HTML;
    }
};
