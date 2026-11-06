<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 11/6';

    /**
     * 11/5 노트 발행 이후 배포분(11/5 밤 ~ 11/6) 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
<p>11/5 노트 발행 이후 반영된 업데이트입니다. (11/5 밤 ~ 11/6 배포분)</p>

<h2>의뢰자 견적서 — 동의·결제 수단 (신규)</h2>
<ul>
<li><strong>위약 안내(필수)·설치 사례 활용 동의(선택)</strong>: 의뢰자 견적서 하단에 동의 카드 2종이 생겼습니다. 버튼을 누르면 전문이 새창으로 뜨고, 끝까지 읽어야 동의 버튼이 활성화됩니다(완독 체크). 위약 안내에 동의하기 전에는 결제 버튼이 잠기고, 마케팅 동의는 선택이라 결제와 무관합니다. 동의하면 시각·전문 버전·IP가 견적서에 기록되고, 마케팅 동의는 의뢰자 정보에도 기록되어 세팅 사례 업로드 대상을 추릴 수 있습니다.</li>
<li><strong>노출 제어</strong>: 관리자 페이지 &gt; 판매처 설정에서 두 카드를 각각 켜고 끌 수 있습니다. 위약 카드를 끄면 결제 잠금도 함께 해제됩니다. <em>현재는 두 카드 모두 꺼둔 상태이며, 문구 확정 후 켤 예정입니다.</em></li>
<li><strong>결제 수단 선택</strong>: 결제하기를 누르면 카드 결제(페이앱)와 계좌이체 중 선택합니다. 계좌이체 선택 시 계좌번호·입금자명 안내가 뜨며, 계좌는 판매처 설정의 '결제 계좌'에 입력합니다(비우면 카드만 노출). 입금 확인은 기존 입금 내역 화면 그대로입니다.</li>
</ul>

<h2>위약 안내 — 섹션 조립 방식</h2>
<ul>
<li>위약 안내 전문이 <strong>공통 + 유형별 섹션</strong>(방문 세팅·원격 세팅·렌탈·방송룸 대여·장비 판매)으로 나뉘었습니다. 견적 빌더의 '위약 안내 구성' 칩에서 이 견적에 해당하는 섹션을 고르면(연동 프로젝트 유형으로 자동 제안), 의뢰자에게는 해당 내용만 합쳐진 전문이 보이고 한 번에 동의합니다. 동의 기록에는 섹션별 버전이 남아 유형별 개정에도 안전합니다.</li>
<li>방문·원격·방송룸 섹션은 홈페이지 공식 안내 게시물의 위약금·환불·A/S 기준을 그대로 옮겼습니다(위약금 단계표, 영업일 계산, 방송룸 당일 취소 시간×3만원 등). 렌탈·장비 판매 섹션은 문구 확정 전 초안입니다.</li>
<li>섹션 관리는 관리자 페이지 &gt; 판매처 설정에서, 공개 전문은 <strong>/terms/penalty</strong>(전체)와 <strong>/terms/marketing</strong>에서 볼 수 있어 홈페이지에는 링크만 게시하면 됩니다.</li>
</ul>

<h2>견적서 검토</h2>
<ul>
<li><strong>추천 제품 여러 개</strong>: 검토 규칙의 추천 제품을 제품 목록에서 골라 여러 개 등록할 수 있습니다(칩 방식). 검토 후보 목록 맨 위에 입력 순서대로 표시되고, 확인 질문에는 전부 제안됩니다.</li>
<li><strong>발행 교착 수정</strong>: 상태를 '발행 완료'로 바꿔둔 채 검토 완료를 누르면 저장이 게이트에 막히던 문제를 고쳤습니다. 이제 검토 완료 한 번으로 검토 → 발행 저장까지 자동으로 이어집니다.</li>
</ul>
HTML;
    }
};
