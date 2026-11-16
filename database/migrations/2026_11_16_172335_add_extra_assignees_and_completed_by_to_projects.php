<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 프로젝트 복수 담당자 + 완료 처리자 기록.
     * 대표 담당자는 기존 projects.assigned_user_id 그대로 두고, 추가 담당자만 project_assignees 에 둔다
     * (기존 데이터 이전 불필요, 대표 담당자를 쓰는 기존 코드에 영향 없음).
     */
    public function up(): void
    {
        Schema::create('project_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
        });
        Schema::dropIfExists('project_assignees');
    }
};
