<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 캘린더 연동 대비 — 할 일을 일정(schedules)에 연결할 수 있는 컬럼.
     * 일정이 삭제되면 연결만 해제(null)하고 할 일은 유지한다.
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('assignee_id')
                ->constrained('schedules')->nullOnDelete()->comment('연동된 캘린더 일정');
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
        });
    }
};
