<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 채널톡 고객 커스텀 프로필 원본 보관 — 플랫폼/주제/경력/방송국 주소 등 의뢰자 필드 매핑용 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channeltalk_users', function (Blueprint $table) {
            $table->json('profile')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('channeltalk_users', function (Blueprint $table) {
            $table->dropColumn('profile');
        });
    }
};
