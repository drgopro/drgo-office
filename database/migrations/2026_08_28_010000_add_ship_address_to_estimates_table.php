<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 견적서 배송지 정보 — 내부 확인용 (의뢰자용 견적서·출력물에는 표시하지 않는다) */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('ship_address', 300)->nullable()->after('client_phone')->comment('배송받을 주소 (내부용)');
            $table->string('ship_entrance', 200)->nullable()->after('ship_address')->comment('공동현관 출입 정보 (내부용)');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['ship_address', 'ship_entrance']);
        });
    }
};
