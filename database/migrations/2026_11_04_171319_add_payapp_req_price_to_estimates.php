<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 페이앱 결제요청 시점의 청구 금액 기록 — 요청 후 견적 총액이 변동(기간할인 재계산 등)해도
 * 고객이 '청구된 금액 그대로' 결제한 건은 결제완료로 인정하기 위함 (견적서 #641 사례)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->unsignedInteger('payapp_req_price')->nullable()->after('payapp_mul_no')
                ->comment('페이앱 결제요청 당시 청구 금액 (feedback 금액 대조용)');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('payapp_req_price');
        });
    }
};
