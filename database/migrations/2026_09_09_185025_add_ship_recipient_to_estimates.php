<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 견적서 배송지 수령인 — 이름/연락처/배송 요청사항 수기 입력 (내부용, 주문 내역 헤더 표시) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('ship_name', 100)->nullable()->after('ship_address');
            $table->string('ship_phone', 30)->nullable()->after('ship_name');
            $table->string('ship_note', 300)->nullable()->after('ship_entrance');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['ship_name', 'ship_phone', 'ship_note']);
        });
    }
};
