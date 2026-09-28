<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 견적서 차수(추가 견적) — 결제 완료 후 추가 결제 건을 부모 견적서에 묶는다.
 * parent_estimate_id가 있으면 그 견적서는 부모의 N차 추가 견적(round>=2).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_estimate_id')->nullable()->after('project_id');
            $table->unsignedTinyInteger('round')->default(1)->after('parent_estimate_id');
            $table->index('parent_estimate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex(['parent_estimate_id']);
            $table->dropColumn(['parent_estimate_id', 'round']);
        });
    }
};
