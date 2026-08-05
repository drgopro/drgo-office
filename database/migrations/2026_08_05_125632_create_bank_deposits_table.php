<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 무통장입금 알림(SMS 포워딩) 수신 내역 — 파싱 실패해도 원문(raw_text)은 항상 보관.
     */
    public function up(): void
    {
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->timestamp('received_at')->index(); // 입금 시각 (SMS에서 파싱, 실패 시 수신 시각)
            $table->unsignedBigInteger('amount')->nullable()->index(); // 입금액 (파싱 실패 시 null)
            $table->string('depositor_name', 100)->nullable()->index(); // 입금자명 (통장 표시명 기준)
            $table->unsignedBigInteger('balance_after')->nullable(); // 거래 후 잔액
            $table->text('raw_text'); // SMS 원문
            $table->string('source', 20)->default('sms');
            $table->string('dedup_hash', 64)->unique(); // 재전송 중복 방지
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_deposits');
    }
};
