<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('stage_data')->nullable()->after('payment_info')
                ->comment('단계별 상세 데이터 — equipment/proposal/estimate/visit 등 키별 JSON');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('stage_data');
        });
    }
};
