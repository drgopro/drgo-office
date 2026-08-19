<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 할 일 체크리스트 (진행 단계) — 미니 프로젝트 진행상황 파악용
        Schema::create('todo_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained('todos')->cascadeOnDelete();
            $table->string('title', 200);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 담당자별 완료 체크 — 복수 담당 할 일은 전원 완료 시 전체 완료
        Schema::table('todo_assignees', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_checklist_items');
        Schema::table('todo_assignees', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
