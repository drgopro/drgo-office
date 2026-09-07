<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 견적서 결제완료 시각 — 주문 내역 자동 등재·날짜별 그룹의 기준.
 * 기존 결제완료 건은 페이앱 결제시각(있으면) 또는 수정일로 백필.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('issued_at');
        });

        DB::table('estimates')->where('status', 'paid')->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('COALESCE(payapp_paid_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
