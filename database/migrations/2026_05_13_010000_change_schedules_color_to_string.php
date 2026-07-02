<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 캘린더 카테고리를 자유롭게 추가하려면 schedules.color enum을 풀어야 한다.
     * 기존 enum 값은 string 컬럼으로 그대로 보존된다.
     */
    public function up(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }
        // doctrine/dbal 없이도 동작하도록 raw SQL 사용 (MySQL/MariaDB 호환)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schedules MODIFY color VARCHAR(30) NOT NULL DEFAULT 'gold'");
        } else {
            Schema::table('schedules', function (Blueprint $table) {
                $table->string('color', 30)->default('gold')->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }
        // 기본 enum으로 되돌림. 새 카테고리 값이 있으면 'gold'로 강제됨.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE schedules SET color='gold' WHERE color NOT IN ('gold','teal','blue','red','green','purple','holiday')");
            DB::statement("ALTER TABLE schedules MODIFY color ENUM('gold','teal','blue','red','green','purple','holiday') NOT NULL DEFAULT 'gold'");
        }
    }
};
