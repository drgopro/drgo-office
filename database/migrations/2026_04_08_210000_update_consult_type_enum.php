<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE consultations MODIFY COLUMN consult_type ENUM('kakao','phone','visit','field') NOT NULL DEFAULT 'phone'");
        } else {
            // sqlite(테스트): enum CHECK 해제를 위해 string으로 변경
            Schema::table('consultations', function (Blueprint $table) {
                $table->string('consult_type', 20)->default('phone')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE consultations MODIFY COLUMN consult_type ENUM('phone','official','partner','blog','free_visit','simple','other') NOT NULL DEFAULT 'phone'");
        }
    }
};
