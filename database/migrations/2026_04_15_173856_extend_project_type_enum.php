<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY COLUMN project_type ENUM('visit','remote','design','inquiry','as','troubleshoot') NOT NULL DEFAULT 'visit'");
        } else {
            // sqlite(테스트): enum CHECK 해제를 위해 string으로 변경
            Schema::table('projects', function (Blueprint $table) {
                $table->string('project_type', 50)->default('visit')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY COLUMN project_type ENUM('visit','remote','as') NOT NULL DEFAULT 'visit'");
        }
    }
};
