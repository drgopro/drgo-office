<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 기존 역할 매핑: sales → member, freelance → guest
        DB::table('users')->where('role', 'sales')->update(['role' => 'member']);
        DB::table('users')->where('role', 'freelance')->update(['role' => 'member']);

        // enum 변경
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('master','admin','member','guest') NOT NULL DEFAULT 'member'");

        // team_id FK 추가
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('master','admin','sales','member','freelance') NOT NULL DEFAULT 'member'");
    }
};
