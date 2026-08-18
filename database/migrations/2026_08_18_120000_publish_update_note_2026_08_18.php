<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITLE = '업데이트 노트 8/18 (화)';

    /**
     * 2026-08-18 배포분 업데이트 노트를 위키 '업데이트' 게시판에 등록.
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
<h2>8/18 (화)</h2>
<h3>📦 재고 관리 — 세트 상품</h3>
<ul>
<li><strong>세트 상품 등록</strong>: 구성품과 필요 수량을 정의하면, 세트 출고 시 구성품 재고가 자동으로 함께 차감됩니다 (반품 시 복원). 세트의 현재고 자리는 구성품 재고로 계산한 <strong>조립 가능 수</strong>로 표시됩니다.</li>
<li>구성품 재고가 부족하면 어떤 구성품이 몇 개 부족한지 경고를 보여주고, 확인 후 진행할 수 있습니다.</li>
<li>기존 제품도 수정 모달에서 <strong>세트로 전환</strong>(또는 일반으로 해제)할 수 있습니다. 세트에 묶인 구성품은 실수로 삭제되지 않도록 보호됩니다.</li>
</ul>
<h3>📦 재고 관리 — 편의 개선</h3>
<ul>
<li>제품 등록/수정 모달에서 <strong>현재고를 직접 수정</strong>할 수 있습니다. 수량을 바꾸면 입출고 내역에 '조정' 이력이 자동 기록됩니다.</li>
<li><strong>제품 검색 추가</strong>: 입출고 등록 모달, 입출고 내역, 세트 구성품 선택에서 제품명/SKU로 검색해 바로 찾을 수 있습니다.</li>
<li><strong>입출고 내역 삭제</strong>(관리자): 선택 삭제와 전체 비우기를 지원하고, 삭제 후 재고는 남은 이력 기준으로 자동 재계산됩니다.</li>
<li>표 밀도를 압축해 가로 스크롤을 최소화하고, 좁은 화면에서 제품명이 세로로 깨지던 문제를 수정했습니다. 삭제 버튼은 빨간색으로 명확하게 바뀌었습니다.</li>
</ul>
<h3>✅ 할 일</h3>
<ul>
<li><strong>복수 담당 할 일이 각 담당자의 컬럼에 모두 표시</strong>되고, 카드에 함께 담당하는 사람 전원이 나열됩니다 (현재 컬럼 담당자는 굵게).</li>
<li>칸반 보드가 화면 높이에 맞게 고정되어 <strong>좌우 스크롤바가 항상 화면 하단에 바로 보입니다</strong>. 카드가 많은 컬럼은 컬럼 안에서 스크롤됩니다.</li>
</ul>
<h3>📁 프로젝트 · 📅 캘린더</h3>
<ul>
<li>프로젝트에 <strong>세팅 장소</strong> 주소를 저장할 수 있습니다 (의뢰자 주소와 별개, 프로젝트 상세의 '세팅 장소' 카드).</li>
<li>캘린더 일정의 장소 입력이 <strong>선택형</strong>이 됐습니다: 비어 있을 때만 자동으로 채워지고, [👤 의뢰자 주소]/[📁 프로젝트 주소] 버튼으로 원할 때만 불러올 수 있습니다.</li>
</ul>
<h3>📢 위키 · 알림</h3>
<ul>
<li><strong>공지사항을 등록하면 채널톡으로 전 직원 멘션 알림</strong>이 자동 발송됩니다 (개인 푸시 포함).</li>
</ul>
<h3>✨ 기타</h3>
<ul>
<li>스크롤바 디자인을 슬림한 포인트 컬러 스타일로 통일했습니다.</li>
<li>모달이 열려 있는 동안 배경 페이지 스크롤을 잠가 이중 스크롤바가 생기지 않습니다.</li>
</ul>
HTML;
    }
};
