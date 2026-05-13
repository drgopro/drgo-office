<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('payment_info')->nullable()->after('custom_data')
                ->comment('결제 정보 — 연결 견적서 ID, 결제 금액, 결제일, 메모, 수기 항목 등');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('payment_info');
        });
    }
};
