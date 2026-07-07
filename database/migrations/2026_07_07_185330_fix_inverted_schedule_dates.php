<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 종료일이 시작일보다 빠른 역전 데이터 교정 — 시작일 하루짜리로 정규화.
     * (수정 API에 after_or_equal 검증이 없던 시기에 생성된 데이터)
     */
    public function up(): void
    {
        DB::table('schedules')
            ->whereColumn('end_date', '<', 'start_date')
            ->update(['end_date' => DB::raw('start_date')]);
    }

    public function down(): void
    {
        // 데이터 교정 마이그레이션 — 되돌리지 않음
    }
};
