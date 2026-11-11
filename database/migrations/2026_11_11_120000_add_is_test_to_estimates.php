<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 테스트 견적 모드 — 기능 테스트용 견적서는 검토 게이트(출력·발행 잠금)를 건너뛴다 */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('review_state');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
