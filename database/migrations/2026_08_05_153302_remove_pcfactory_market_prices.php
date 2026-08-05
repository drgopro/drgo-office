<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 피씨팩토리 시세 지원 중단(해외 IP 차단으로 조회 불가) — 남은 시세 행 정리.
     */
    public function up(): void
    {
        DB::table('product_market_prices')->where('vendor', 'pcfactory')->delete();
    }

    public function down(): void
    {
        // 데이터 삭제 마이그레이션 — 되돌릴 데이터 없음
    }
};
