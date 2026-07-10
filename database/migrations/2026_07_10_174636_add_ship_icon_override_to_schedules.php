<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 제목 배송 아이콘(○/△/✕) 수동 지정 — null=배송현황 기준 자동 */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('ship_icon_override', 10)->nullable()->after('exclude_weekends');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn('ship_icon_override'));
    }
};
