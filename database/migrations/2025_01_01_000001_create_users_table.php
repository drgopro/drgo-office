<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firebase users/{uid} 컬렉션 → MySQL users 테이블
     *
     * Firebase 구조:
     *   users/{userId} → { displayName, passwordHash (sha256), adminLevel (0~5) }
     *
     * adminLevel 매핑:
     *   5 = master  (최고관리자)
     *   4 = admin   (관리자)
     *   3 = sales   (세일즈)
     *   2 = member  (멤버)
     *   1 = freelance (프리랜서)
     *   0 = 미승인
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // 로그인 식별자 (Firebase의 userId = 아이디 문자열)
            $table->string('username', 50)->unique()->comment('로그인 아이디');
            $table->string('display_name', 100)->comment('표시 이름');
            $table->string('email', 150)->nullable()->unique()->comment('이메일 (선택)');
            $table->string('password')->comment('bcrypt 해시 (Laravel 표준)');

            // 권한 (Firebase adminLevel 대응)
            $table->enum('role', ['master', 'admin', 'sales', 'member', 'freelance'])
                  ->default('member')
                  ->comment('마스터5/관리자4/세일즈3/멤버2/프리랜서1');

            $table->boolean('is_active')->default(true)->comment('계정 활성화 여부');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps(); // created_at, updated_at
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
