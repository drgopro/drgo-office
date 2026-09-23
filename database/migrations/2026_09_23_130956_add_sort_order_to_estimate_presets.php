<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 견적 프리셋 표시 순서 — 빌더 프리셋 패널에서 드래그로 변경.
     * 0(기본)은 아직 정렬하지 않은 프리셋으로 최근 수정순 폴백 정렬된다.
     */
    public function up(): void
    {
        Schema::table('estimate_presets', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('items');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_presets', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
