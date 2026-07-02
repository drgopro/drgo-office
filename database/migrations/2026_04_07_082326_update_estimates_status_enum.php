<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 모든 값 포함하는 확장 ENUM으로 변경 (sqlite 테스트 환경은 enum CHECK 해제를 위해 string으로)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('draft','sent','approved','rejected','temp','created','editing','completed','paid','hold') DEFAULT 'temp'");
        } else {
            Schema::table('estimates', function (Blueprint $table) {
                $table->string('status', 20)->default('temp')->change();
            });
        }

        // 2. 기존 값 매핑
        DB::table('estimates')->where('status', 'draft')->update(['status' => 'temp']);
        DB::table('estimates')->where('status', 'sent')->update(['status' => 'completed']);
        DB::table('estimates')->where('status', 'approved')->update(['status' => 'paid']);
        DB::table('estimates')->where('status', 'rejected')->update(['status' => 'hold']);

        // 3. 최종 ENUM으로 축소
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','paid','hold') DEFAULT 'temp'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('draft','sent','approved','rejected','temp','created','editing','completed','paid','hold') DEFAULT 'draft'");
        }

        DB::table('estimates')->where('status', 'temp')->update(['status' => 'draft']);
        DB::table('estimates')->where('status', 'created')->update(['status' => 'draft']);
        DB::table('estimates')->where('status', 'editing')->update(['status' => 'draft']);
        DB::table('estimates')->where('status', 'completed')->update(['status' => 'sent']);
        DB::table('estimates')->where('status', 'paid')->update(['status' => 'approved']);
        DB::table('estimates')->where('status', 'hold')->update(['status' => 'rejected']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('draft','sent','approved','rejected') DEFAULT 'draft'");
        }
    }
};
