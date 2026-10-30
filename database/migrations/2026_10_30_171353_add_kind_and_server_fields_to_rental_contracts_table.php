<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 렌탈 계약을 '장비 렌탈'과 '서버 렌탈'로 구분 — 서버 렌탈은 서버명·사양·접속 정보를 추가로 기록 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->string('kind', 20)->default('equipment')->after('client_id')->index(); // equipment | server
            $table->string('server_name', 100)->nullable()->after('memo');
            $table->string('server_spec', 200)->nullable()->after('server_name');
            $table->string('server_access', 200)->nullable()->after('server_spec'); // IP·접속 주소 등
        });
    }

    public function down(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'server_name', 'server_spec', 'server_access']);
        });
    }
};
