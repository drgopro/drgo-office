<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_post_id')->unique()->constrained('feedback_posts')->cascadeOnDelete();
            $table->string('category', 20)->comment('bug|improvement|already_done|duplicate|need_info|other');
            $table->string('effort', 10)->nullable()->comment('small|medium|large');
            $table->text('summary')->comment('검수 요약 — 원인 추정·현황');
            $table->text('proposal')->nullable()->comment('수정/개선 방향 제안');
            $table->timestamp('reviewed_at')->nullable()->comment('검수 등록(재검수) 시각');
            $table->string('decision', 10)->default('pending')->index()->comment('pending|approved|hold|rejected|revise');
            $table->string('decision_note', 1000)->nullable()->comment('관리자 메모 — 승인 시 지시, 재검토 요청 사유 등');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('result_note')->nullable()->comment('반영 결과');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reviews');
    }
};
