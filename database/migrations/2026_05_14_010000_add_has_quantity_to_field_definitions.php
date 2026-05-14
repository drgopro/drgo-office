<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            // 수량 입력 활성화 — true 면 폼에서 값 + 수량을 함께 입력하고 {value, qty} 로 저장됨
            $table->boolean('has_quantity')->default(false)->after('width');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->boolean('has_quantity')->default(false)->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('project_field_definitions', function (Blueprint $table) {
            $table->dropColumn('has_quantity');
        });

        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->dropColumn('has_quantity');
        });
    }
};
