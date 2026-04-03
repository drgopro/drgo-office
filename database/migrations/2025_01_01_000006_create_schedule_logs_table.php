<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firebase eventLogs → MySQL schedule_logs 테이블
     *
     * Firebase 구조:
     *   eventLogs/{id} → {
     *     eventId: string,
     *     type: 'create' | 'update',
     *     userId: string (displayName),
     *     changes: [{field, from, to}],   -- update일 때
     *     createdAt: Timestamp
     *   }
     */
    public function up(): void
    {
        Schema::create('schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')
                  ->constrained('schedules')
                  ->cascadeOnDelete()
                  ->comment('Firebase eventId');

            $table->enum('action', ['create', 'update', 'delete'])->comment('Firebase type');

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('작업한 사용자');

            // Firebase changes: [{field, from, to}] 배열
            $table->json('changes')->nullable()->comment('변경 내역 (update일 때)');

            $table->timestamp('acted_at')->useCurrent()->comment('작업 시각 (Firebase createdAt)');

            $table->index('schedule_id');
            $table->index('user_id');
            $table->index('acted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_logs');
    }
};
