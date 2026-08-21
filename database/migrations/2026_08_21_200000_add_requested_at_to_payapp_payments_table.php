<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 페이앱 통보가 재시도로 늦게 도착하면 수신 시각(created_at)이 실제 결제요청
     * 시각과 크게 어긋나 중복처럼 보임 — 통보의 reqdate를 별도 보관해 표시/정렬에 사용.
     */
    public function up(): void
    {
        Schema::table('payapp_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payapp_payments', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payapp_payments', function (Blueprint $table) {
            if (Schema::hasColumn('payapp_payments', 'requested_at')) {
                $table->dropColumn('requested_at');
            }
        });
    }
};
