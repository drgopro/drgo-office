<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 입양 로직 재적용 — 프로젝트 수동 결제와 견적서 결제완료가 따로 기록된 같은 돈이
     * 원장에 두 번 실려 있던 것을 한 번만 집계하도록 다시 계산한다.
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
