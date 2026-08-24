<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 제품 소요시간 — 견적서 소요시간 입력의 기본값과 사용 여부.
 * use_time_required가 꺼진 제품은 견적서에서 소요시간 입력폼을 표시하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'time_required')) {
                $table->string('time_required', 50)->nullable()->after('search_tags');
            }
            if (! Schema::hasColumn('products', 'use_time_required')) {
                $table->boolean('use_time_required')->default(false)->after('time_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'use_time_required')) {
                $table->dropColumn('use_time_required');
            }
            if (Schema::hasColumn('products', 'time_required')) {
                $table->dropColumn('time_required');
            }
        });
    }
};
