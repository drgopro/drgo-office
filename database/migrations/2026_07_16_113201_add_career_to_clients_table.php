<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 방송 경력 (처음/초보/경력) — 캘린더 방문의뢰 폼의 경력 여부와 동일 값 체계 */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('career', 20)->nullable()->after('broadcast_id')->comment('방송 경력: 처음/초보/경력');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('career');
        });
    }
};
