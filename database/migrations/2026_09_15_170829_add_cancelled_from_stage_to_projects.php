<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 프로젝트 취소 시점의 진행 단계 기록 — 어느 단계에서 취소됐는지 통계/엑셀에서 추적 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('cancelled_from_stage', 30)->nullable()->after('cancelled_at')
                ->comment('취소 직전 진행 단계 (stage 코드)');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('cancelled_from_stage');
        });
    }
};
