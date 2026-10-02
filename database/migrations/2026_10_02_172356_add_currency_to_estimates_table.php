<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 견적서 달러 표시 — 금액은 원화로 저장·결제하고 표시만 환산.
 * usd_rate/usd_rate_date는 'USD로 적용' 시점(저장 시점)의 매매기준율로 고정된다.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('currency', 3)->default('KRW')->after('total_amount');
            $table->decimal('usd_rate', 10, 2)->nullable()->after('currency'); // 1 USD = n KRW (매매기준율)
            $table->date('usd_rate_date')->nullable()->after('usd_rate'); // 환율 기준일
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['currency', 'usd_rate', 'usd_rate_date']);
        });
    }
};
