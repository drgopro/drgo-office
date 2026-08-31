<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 연차 관리 — 입사일(users), 연도별 부여(leave_grants), 사용 원장(leave_usages).
     * 사용 기록은 캘린더 휴가 일정의 '연차 차감' 체크로 자동 생성되거나(schedule_id 연결)
     * 경영지원팀이 관리 페이지에서 수동 입력한다(schedule_id null).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('hire_date')->nullable()->after('team_id');
        });

        Schema::create('leave_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 4, 1); // 부여 일수 (이월·조정 반영한 확정값)
            $table->string('note', 300)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'year']);
        });

        Schema::create('leave_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->cascadeOnDelete(); // 캘린더 연동분
            $table->date('used_on');
            $table->decimal('days', 3, 1); // 1.0=연차, 0.5=반차
            $table->string('type', 30)->default('연차'); // 연차/반차/기타(수동)
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'used_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_usages');
        Schema::dropIfExists('leave_grants');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('hire_date'));
    }
};
