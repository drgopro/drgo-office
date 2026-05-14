<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            // 우선순위 — 클수록 위/먼저 표시. 같은 소분류 안에서는 필드 순서,
            // 소분류끼리는 그 안의 최대 priority 기준으로 정렬됨.
            $table->smallInteger('priority')->default(0)->after('subsection');
            $table->index('priority');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->smallInteger('priority')->default(0)->after('subsection');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
        });
    }
};
