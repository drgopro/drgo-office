<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 렌탈 결제 현황 — 결제일(매월 갱신일)별 '결제됨' 체크. 행이 있으면 결제 완료, 없으면 미결제 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('rental_contracts')->cascadeOnDelete();
            $table->date('due_date'); // 결제(갱신)일 — 계약 시작일의 '일' 기준 매월
            $table->unsignedInteger('amount')->default(0); // 체크 당시 월 금액 스냅샷
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('memo', 200)->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
    }
};
