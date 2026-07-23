<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 장기 일정 계층구조 — 하위 일정(일자별 시간·담당자)이 부모 일정을 참조.
     * 부모 삭제 시 하위 연쇄 삭제. 월간 뷰는 부모 칩만, 주간/일간은 하위를 시간 슬롯에 표시.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('schedules')->cascadeOnDelete();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
