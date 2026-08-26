<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 견적서 연동 청구 — 견적서를 프로젝트에 연동하면 미수 청구가 자동 생성되므로 원본 견적서를 기억 (중복 생성 방지) */
    public function up(): void
    {
        Schema::table('project_billings', function (Blueprint $table) {
            $table->unsignedBigInteger('estimate_id')->nullable()->index()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_billings', function (Blueprint $table) {
            $table->dropColumn('estimate_id');
        });
    }
};
