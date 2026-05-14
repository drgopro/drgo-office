<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            // 섹션 안의 소분류 (예: 장비 정보 → PC / 카메라 / 오디오 / 조명)
            $table->string('subsection', 50)->nullable()->after('section');
            $table->index(['section', 'subsection']);
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->string('subsection', 50)->nullable()->after('section');
            $table->index(['section', 'subsection']);
        });
    }

    public function down(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            $table->dropIndex(['section', 'subsection']);
            $table->dropColumn('subsection');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->dropIndex(['section', 'subsection']);
            $table->dropColumn('subsection');
        });
    }
};
