<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 페이앱 매출전표 주소(csturl) — 결제완료 통지에 실려 오는데 저장하지 않아 결제현황에 전표 버튼이 없던 문제 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('payapp_csturl', 300)->nullable()->after('payapp_payurl')
                ->comment('페이앱 매출전표(영수증) URL');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('payapp_csturl');
        });
    }
};
