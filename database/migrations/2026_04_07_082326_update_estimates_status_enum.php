<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 모든 값 포함하는 확장 ENUM으로 변경
        DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('draft','sent','approved','rejected','temp','created','editing','completed','paid','hold') DEFAULT 'temp'");

        // 2. 기존 값 매핑
        DB::table('estimates')->where('status', 'draft')->update(['status' => 'temp']);
        DB::table('estimates')->where('status', 'sent')->update(['status' => 'completed']);
        DB::table('estimates')->where('status', 'approved')->update(['status' => 'paid']);
        DB::table('estimates')->where('status', 'rejected')->update(['status' => 'hold']);

        // 3. 최종 ENUM으로 축소
        DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','paid','hold') DEFAULT 'temp'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('draft','sent','approved','rejected','temp','created','editing','completed','paid','hold') DEFAULT 'draft'");

        DB::table('estimates')->where('status', 'temp')->update(['status' => 'draft']);
        DB::table('estimates')->where('status', 'created')->update(['status' => 'draft']);
        DB::table('estimates')->where('status', 'editing')->update(['status' => 'draft']);
        DB::table('estimates')->where('status', 'completed')->update(['status' => 'sent']);
        DB::table('estimates')->where('status', 'paid')->update(['status' => 'approved']);
        DB::table('estimates')->where('status', 'hold')->update(['status' => 'rejected']);

        DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('draft','sent','approved','rejected') DEFAULT 'draft'");
    }
};
