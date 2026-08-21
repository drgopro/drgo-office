<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 의뢰자 추가 주소 — 주소 1은 기존 address/address_detail(메인) 유지,
     * 주소 2~4는 JSON 배열 [{address, address_detail}] (최대 3개).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'extra_addresses')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->json('extra_addresses')->nullable()->after('address_detail');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'extra_addresses')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('extra_addresses');
            });
        }
    }
};
