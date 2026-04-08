<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 기존 값 매핑
        DB::table('consultations')->where('consult_type', 'official')->update(['consult_type' => 'visit']);
        DB::table('consultations')->where('consult_type', 'partner')->update(['consult_type' => 'kakao']);
        DB::table('consultations')->where('consult_type', 'blog')->update(['consult_type' => 'kakao']);
        DB::table('consultations')->where('consult_type', 'free_visit')->update(['consult_type' => 'field']);
        DB::table('consultations')->where('consult_type', 'simple')->update(['consult_type' => 'phone']);
        DB::table('consultations')->where('consult_type', 'other')->update(['consult_type' => 'phone']);

        DB::statement("ALTER TABLE consultations MODIFY COLUMN consult_type ENUM('kakao','phone','visit','field') NOT NULL DEFAULT 'phone'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE consultations MODIFY COLUMN consult_type ENUM('phone','official','partner','blog','free_visit','simple','other') NOT NULL DEFAULT 'phone'");
    }
};
