<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 입금 내역 ↔ 견적서 수동 매칭 — 직원이 입금 건을 견적서에 연결해 계좌이체 결제완료 처리.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->unsignedBigInteger('estimate_id')->nullable()->after('source');
            $table->timestamp('matched_at')->nullable()->after('estimate_id');
            $table->unsignedBigInteger('matched_by')->nullable()->after('matched_at');
            $table->index('estimate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->dropIndex(['estimate_id']);
            $table->dropColumn(['estimate_id', 'matched_at', 'matched_by']);
        });
    }
};
