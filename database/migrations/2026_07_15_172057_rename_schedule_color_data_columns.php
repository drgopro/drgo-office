<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 색상 키 기반 컬럼명 → 의미 기반 컬럼명.
     * gold_data(방문의뢰 상세/의뢰자 연동) → request_data, teal_data(원격 상세) → remote_data.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->renameColumn('gold_data', 'request_data');
            $table->renameColumn('teal_data', 'remote_data');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->renameColumn('request_data', 'gold_data');
            $table->renameColumn('remote_data', 'teal_data');
        });
    }
};
