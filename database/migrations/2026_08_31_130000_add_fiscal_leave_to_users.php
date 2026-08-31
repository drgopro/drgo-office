<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 연차 기산 방식 — 체크 시 회계연도(1/1) 기준, 기본은 입사일 기준 */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('fiscal_leave')->default(false)->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('fiscal_leave'));
    }
};
