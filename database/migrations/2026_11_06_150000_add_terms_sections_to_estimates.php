<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // 이 견적서에 적용되는 위약 안내 섹션 키 목록 — 예: ["visit_setup","rental"] (공통은 항상 포함)
            $table->json('terms_sections')->nullable()->after('agreements');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('terms_sections');
        });
    }
};
