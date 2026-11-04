<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 멤버 퇴사 처리 — 기록은 보존하고 로그인·접근·담당자 노출만 차단하는 상태값 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('resigned_at')->nullable()->after('is_active')
                ->comment('퇴사 처리 시각 (null=재직, 값 있으면 로그인·접근 차단)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('resigned_at');
        });
    }
};
