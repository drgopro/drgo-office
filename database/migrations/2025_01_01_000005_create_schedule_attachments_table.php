<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firebase evData.attachments[] → MySQL schedule_attachments 테이블
     *
     * Firebase 구조:
     *   events/{id}.attachments = [
     *     { name: '파일명', thumbUrl: '...', fullUrl: '...', data: 'base64...', note: '메모' }
     *   ]
     *
     * gold_data.quoteImgs / refImgs / roomImgs 도 동일한 구조 → 여기서 type으로 구분
     */
    public function up(): void
    {
        Schema::create('schedule_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();

            // 첨부 유형 구분
            $table->enum('attachment_type', [
                'general',    // 일반 첨부 (Firebase attachments[])
                'quote',      // 견적 이미지 (Firebase gold_data.quoteImgs[])
                'reference',  // 참고 이미지 (Firebase gold_data.refImgs[])
                'room',       // 방송룸 이미지 (Firebase gold_data.roomImgs[])
            ])->default('general');

            $table->string('file_name', 255)->comment('원본 파일명');
            $table->string('note', 500)->nullable()->comment('이미지 메모/설명');

            // 파일 저장 방식 (Firebase는 base64 또는 Storage URL)
            // Laravel에서는 Storage에 저장 후 경로만 보관
            $table->string('file_path', 500)->nullable()->comment('서버 저장 경로 (storage/app/...)');
            $table->string('thumb_path', 500)->nullable()->comment('썸네일 경로');
            $table->string('original_url', 500)->nullable()
                  ->comment('Firebase Storage URL (마이그레이션 시 임시 보관)');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('바이트');

            $table->integer('sort_order')->default(0)->comment('표시 순서');
            $table->timestamps();

            $table->index(['schedule_id', 'attachment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_attachments');
    }
};
