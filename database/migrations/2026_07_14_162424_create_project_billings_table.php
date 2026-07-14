<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 청구·잔금 관리 — 결제 단계에서 '청구' 체크 시 생성되는 받을 금액 단위.
     * 입금(project_payments.billing_id 연결) 합계로 잔금을 추적하고 전액 입금 시 완료 처리.
     */
    public function up(): void
    {
        Schema::create('project_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // 청구액
            $table->date('billed_at'); // 청구일
            $table->string('status', 10)->default('unpaid')->index(); // unpaid|partial|paid
            $table->string('memo', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('project_payments', function (Blueprint $table) {
            $table->foreignId('billing_id')->nullable()->after('parent_payment_id')
                ->constrained('project_billings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_id');
        });
        Schema::dropIfExists('project_billings');
    }
};
