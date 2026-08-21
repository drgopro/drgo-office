<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 페이앱 자체(외부) 결제 통지 저장 — 견적서와 무관하게 페이앱 앱/사이트에서
     * 직접 만든 결제의 feedback 통지를 기록한다. 페이앱은 결제내역 조회 API가
     * 없어, 판매자 설정의 기본 FEEDBACK URL 웹훅으로만 수집 가능.
     */
    public function up(): void
    {
        if (Schema::hasTable('payapp_payments')) {
            return;
        }
        Schema::create('payapp_payments', function (Blueprint $table) {
            $table->id();
            $table->string('mul_no', 40)->unique()->comment('페이앱 결제요청번호');
            $table->unsignedSmallInteger('pay_state')->default(0);
            $table->unsignedBigInteger('price')->nullable();
            $table->string('goodname', 200)->nullable();
            $table->string('buyer', 100)->nullable()->comment('구매자명 (통지에 있으면)');
            $table->string('recvphone', 30)->nullable();
            $table->string('pay_type', 30)->nullable();
            $table->string('card_name', 60)->nullable();
            $table->string('csturl', 500)->nullable()->comment('매출전표 URL');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payapp_payments');
    }
};
