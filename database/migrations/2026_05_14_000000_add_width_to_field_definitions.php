<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            // 4열 그리드 기준으로 각 필드가 차지할 열 수 (1~4)
            $table->unsignedTinyInteger('width')->default(2)->after('section');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('width')->default(2)->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            $table->dropColumn('width');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->dropColumn('width');
        });
    }
};
