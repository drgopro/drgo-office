<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 매출 인식 원장 — 통계 전용 파생 테이블 (revenue:rebuild로 언제든 전체 재계산 가능).
     * 돈의 사실 원장(ProjectPayment)과 별개로 '언제의 매출로 볼 것인가'를 기록한다.
     */
    public function up(): void
    {
        Schema::create('revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // estimate_paid | estimate_refund | payment_only
            $table->foreignId('estimate_id')->nullable()->index();
            $table->foreignId('project_id')->nullable()->index();
            $table->foreignId('payment_id')->nullable()->index(); // payment_only의 원본 결제/환불 행
            $table->date('recognized_on')->index(); // 매출 인식일 — 집계 기준
            $table->bigInteger('product_amount')->default(0); // 장비판매 (환불은 음수)
            $table->bigInteger('service_amount')->default(0); // 세팅비 (환불은 음수)
            $table->bigInteger('amount')->default(0); // 합계
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_entries');
    }
};
