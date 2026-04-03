<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firebase settings/assignee_list → MySQL assignees 테이블
     *
     * Firebase 구조:
     *   settings/assignee_list → { names: ['이름1','이름2',...], displayOrder: [...] }
     *
     * 담당자 풀(Pool) — 일정에 배정하는 사람 목록
     * 반드시 users 테이블과 일치하지 않아도 됨 (외부 프리랜서 등 포함 가능)
     */
    public function up(): void
    {
        Schema::create('assignees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('담당자 이름');
            $table->integer('display_order')->default(0)->comment('표시 순서 (Firebase displayOrder)');
            $table->boolean('is_active')->default(true)->comment('활성 여부');

            // users 테이블과 연결 (선택적 — 내부 직원인 경우)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignees');
    }
};
