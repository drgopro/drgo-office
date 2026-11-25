<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 프로젝트 피드백 스레드 — 글(parent_id null)에 답글(parent_id = 글 id)을 1단계로 단다 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('project_feedbacks', 'parent_id')) {
            return;
        }
        Schema::table('project_feedbacks', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('project_id')
                ->constrained('project_feedbacks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_feedbacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
