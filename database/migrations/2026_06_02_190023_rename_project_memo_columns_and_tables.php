<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1) projects.memo → projects.overview
        if (Schema::hasColumn('projects', 'memo') && ! Schema::hasColumn('projects', 'overview')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->renameColumn('memo', 'overview');
            });
        }

        // 2) project_memos 테이블 → project_feedbacks
        if (Schema::hasTable('project_memos') && ! Schema::hasTable('project_feedbacks')) {
            Schema::rename('project_memos', 'project_feedbacks');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_feedbacks') && ! Schema::hasTable('project_memos')) {
            Schema::rename('project_feedbacks', 'project_memos');
        }
        if (Schema::hasColumn('projects', 'overview') && ! Schema::hasColumn('projects', 'memo')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->renameColumn('overview', 'memo');
            });
        }
    }
};
