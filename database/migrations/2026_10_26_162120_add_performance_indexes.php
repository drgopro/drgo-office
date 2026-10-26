<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 반복 조회 경로의 WHERE·ORDER BY 순서에 맞춘 복합 인덱스.
 * - activity_logs: 마케팅 리포트 플랫폼 이동 집계 (loggable_type + action + 기간)
 * - estimates: 대시보드·리포트의 결제완료 기간 합계 (status + created_at)
 * - schedule_shipments: 배송상태 갱신 대상 (미배송 + 최근 30일)
 * - product_market_prices: 시세 갱신 순서 (checked_at 오래된 순)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['loggable_type', 'action', 'created_at']);
        });
        Schema::table('estimates', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });
        Schema::table('schedule_shipments', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });
        Schema::table('product_market_prices', function (Blueprint $table) {
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['loggable_type', 'action', 'created_at']);
        });
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });
        Schema::table('schedule_shipments', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });
        Schema::table('product_market_prices', function (Blueprint $table) {
            $table->dropIndex(['checked_at']);
        });
    }
};
