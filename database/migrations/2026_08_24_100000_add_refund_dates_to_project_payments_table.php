<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 환불 요청 일시·환불 완료 일시 — 환불/취소 행에 기록 (프로젝트 결제 내역) */
    public function up(): void
    {
        Schema::table('project_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_payments', 'refund_requested_at')) {
                $table->dateTime('refund_requested_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('project_payments', 'refunded_at')) {
                $table->dateTime('refunded_at')->nullable()->after('refund_requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_payments', function (Blueprint $table) {
            foreach (['refund_requested_at', 'refunded_at'] as $col) {
                if (Schema::hasColumn('project_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
