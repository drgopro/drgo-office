<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 다일 일정 '주말 제외' 옵션 — 주말에는 칩이 끊기고 평일만 이어짐 */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('exclude_weekends')->default(false)->after('is_all_day');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn('exclude_weekends'));
    }
};
