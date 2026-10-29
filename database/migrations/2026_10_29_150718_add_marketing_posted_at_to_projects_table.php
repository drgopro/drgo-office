<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 마케팅 포스팅 완료 시각 — 마케팅 활용 동의 프로젝트의 콘텐츠 포스팅이 끝났을 때 프로젝트에서 체크 (null=미포스팅).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('marketing_posted_at')->nullable()->after('marketing_consent_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('marketing_posted_at');
        });
    }
};
