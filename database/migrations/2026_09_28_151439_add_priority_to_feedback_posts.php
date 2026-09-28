<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 피드백 우선순위 — 관리자가 지정 (high/medium/low, null=미지정) */
    public function up(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->string('priority', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
