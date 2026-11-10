<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 메인 주소(주소 1) 용도 라벨 — 이사 전/후, 자택/스튜디오 등. 추가 주소의 라벨은 extra_addresses JSON의 label 키 */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('address_label', 30)->nullable()->after('address_detail');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('address_label');
        });
    }
};
