<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 내부 비고 — 직원만 보는 메모 (의뢰자 견적서/출력물에는 절대 표시하지 않음) */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (! Schema::hasColumn('estimates', 'internal_memo')) {
                $table->text('internal_memo')->nullable()->after('memo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'internal_memo')) {
                $table->dropColumn('internal_memo');
            }
        });
    }
};
