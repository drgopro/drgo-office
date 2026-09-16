<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 방문보고 작성/수정 시각 기록 — 대시보드 '최근 방문보고' 위젯 정렬 기준.
 * 기존 작성분은 프로젝트 수정 시각으로 근사 백필 (정확한 작성 시각은 이후 저장부터 기록).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('visit_report_updated_at')->nullable()->after('visit_report');
        });

        DB::table('projects')
            ->whereNotNull('visit_report')
            ->where('visit_report', '!=', '')
            ->update(['visit_report_updated_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('visit_report_updated_at');
        });
    }
};
