<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 할 일 복수 담당자 — 선택 순서 보존 피벗.
     * todos.assignee_id는 대표(첫 번째 선택) 담당자로 유지되어 칸반 컬럼 배치를 담당하고,
     * 전체 담당자 목록과 순서는 이 테이블이 관리한다. 기존 데이터는 단일 담당자를 백필.
     */
    public function up(): void
    {
        Schema::create('todo_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['todo_id', 'user_id']);
        });

        // 기존 단일 담당자 백필 (sort_order 0 = 대표)
        DB::table('todo_assignees')->insertUsing(
            ['todo_id', 'user_id', 'sort_order'],
            DB::table('todos')->whereNotNull('assignee_id')->select('id', 'assignee_id', DB::raw('0'))
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_assignees');
    }
};
