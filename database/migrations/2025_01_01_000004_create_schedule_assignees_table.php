<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firebase evData.assignees[] → MySQL schedule_assignees 피벗 테이블
     *
     * Firebase 구조:
     *   events/{id}.assignees = ['이름1', '이름2', ...]  (문자열 배열)
     *
     * MySQL: schedules ↔ assignees 다대다 피벗
     */
    public function up(): void
    {
        Schema::create('schedule_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('assignee_id')->constrained('assignees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['schedule_id', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assignees');
    }
};
