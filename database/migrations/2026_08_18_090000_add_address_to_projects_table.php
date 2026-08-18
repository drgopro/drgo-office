<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('address', 300)->nullable()->after('overview'); // 세팅 장소 (의뢰자 주소와 별개)
            $table->string('address_detail', 200)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['address', 'address_detail']);
        });
    }
};
