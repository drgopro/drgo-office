<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 상담 인입 시간 — 30분 단위 24시간 표기(HH:MM). 인입 시간대 통계용 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('inbound_time', 5)->nullable()->after('consulted_at')
                ->comment('상담 인입 시간 (HH:MM, 30분 단위)');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn('inbound_time');
        });
    }
};
