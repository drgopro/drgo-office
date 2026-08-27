<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 인식일 폴백 수정 재적용 — 결제 일시 단서가 없는 수동 결제완료 견적서가
     * 최초 구축 때 구축일로 몰렸던 것을 견적서 생성일 기준으로 다시 계산한다.
     */
    public function up(): void
    {
        if (Schema::hasTable('revenue_entries')) {
            Artisan::call('revenue:rebuild');
        }
    }

    public function down(): void
    {
        // 데이터 재계산 마이그레이션 — 되돌릴 것 없음 (revenue:rebuild로 언제든 재계산 가능)
    }
};
