<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 반복 설정(주기/간격/단위/종료일)을 일정에 저장 —
     * 반복 일정 편집 시 현재 설정을 보여주고 재조정할 수 있도록 함.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('repeat_freq', 10)->nullable()->after('repeat_group')->comment('반복 주기 (daily/weekly/monthly/custom)');
            $table->unsignedTinyInteger('repeat_interval')->nullable()->after('repeat_freq')->comment('custom 간격');
            $table->string('repeat_unit', 10)->nullable()->after('repeat_interval')->comment('custom 단위 (day/week/month)');
            $table->date('repeat_until')->nullable()->after('repeat_unit')->comment('반복 종료일');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['repeat_freq', 'repeat_interval', 'repeat_unit', 'repeat_until']);
        });
    }
};
