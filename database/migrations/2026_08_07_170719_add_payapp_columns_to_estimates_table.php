<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 페이앱 결제 연동 + 의뢰자용 공개 링크 토큰.
     * share_token은 순번 ID 노출 없이 난수로 접근하는 공개 견적서 주소용.
     */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('issued_at'); // 공개 링크 난수 토큰
            $table->string('payapp_mul_no', 40)->nullable()->after('share_token'); // 페이앱 결제요청 번호
            $table->string('payapp_payurl', 500)->nullable()->after('payapp_mul_no'); // 의뢰자 결제 페이지 URL
            $table->unsignedSmallInteger('payapp_state')->nullable()->after('payapp_payurl'); // 마지막 pay_state 원본
            $table->timestamp('payapp_requested_at')->nullable()->after('payapp_state');
            $table->timestamp('payapp_paid_at')->nullable()->after('payapp_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'payapp_mul_no', 'payapp_payurl', 'payapp_state', 'payapp_requested_at', 'payapp_paid_at']);
        });
    }
};
