<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // 의뢰자 동의 기록 — {penalty:{at,ver,ip}, marketing:{at,ver,ip}} (전문 버전까지 기록)
            $table->json('agreements')->nullable()->after('review_state');
        });
        Schema::table('clients', function (Blueprint $table) {
            // 마케팅 활용(세팅 사례 게시) 동의 — 의뢰자 단위 기록, 사례 업로드 대상 필터용
            $table->timestamp('marketing_agreed_at')->nullable();
            $table->unsignedBigInteger('marketing_agree_estimate_id')->nullable(); // 동의 근거 견적서
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('agreements');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['marketing_agreed_at', 'marketing_agree_estimate_id']);
        });
    }
};
