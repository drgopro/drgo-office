<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 플랫폼별 방송국 주소 — {"SOOP":"https://...", "유튜브":"https://..."} 형태의 JSON 맵.
     * 플랫폼 다중선택 시 선택한 플랫폼 수만큼 주소를 보관한다.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('station_addresses')->nullable()->after('platform_etc');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('station_addresses');
        });
    }
};
