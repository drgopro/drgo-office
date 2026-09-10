<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채널톡 고객 미러 — 주기 동기화(drgo:sync-channeltalk-users)로 채널톡 고객 프로필을
 * 로컬에 복제해 의뢰자 등록 시 빠르게 검색/불러오기. clients에는 연동 고객 ID를 기록.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channeltalk_users', function (Blueprint $table) {
            $table->id();
            $table->string('ct_id', 60)->unique(); // 채널톡 user id
            $table->string('name', 200)->nullable();
            $table->string('mobile', 40)->nullable(); // 원문 (+82 형식 등)
            $table->string('mobile_digits', 20)->nullable()->index(); // 숫자만 (매칭용, 010 정규화)
            $table->string('email', 200)->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('ct_updated_at')->nullable(); // 채널톡 측 갱신 시각
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('channeltalk_user_id', 60)->nullable()->index()->after('broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('channeltalk_user_id');
        });
        Schema::dropIfExists('channeltalk_users');
    }
};
