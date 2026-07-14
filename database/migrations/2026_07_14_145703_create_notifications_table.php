<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 라라벨 database 알림 채널 표준 테이블 — 상단 알림 리스트용 저장소 */
    public function up(): void
    {
        // 초기 수동 생성된 레거시 notifications 테이블(코드 미사용) 제거 후 표준 구조로 재생성
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'is_read')) {
            Schema::drop('notifications');
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
