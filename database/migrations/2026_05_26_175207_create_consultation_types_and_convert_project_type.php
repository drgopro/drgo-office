<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) consultation_types 마스터 테이블
        if (! Schema::hasTable('consultation_types')) {
            Schema::create('consultation_types', function (Blueprint $table) {
                $table->id();
                $table->string('key', 50)->unique();
                $table->string('label', 100);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            $defaults = [
                ['key' => 'visit', 'label' => '방문세팅', 'sort_order' => 1],
                ['key' => 'remote', 'label' => '원격세팅', 'sort_order' => 2],
                ['key' => 'design', 'label' => '디자인', 'sort_order' => 3],
                ['key' => 'inquiry', 'label' => '단순문의', 'sort_order' => 4],
                ['key' => 'as', 'label' => 'A/S', 'sort_order' => 5],
                ['key' => 'troubleshoot', 'label' => '문제 해결', 'sort_order' => 6],
            ];
            $now = now();
            foreach ($defaults as $d) {
                DB::table('consultation_types')->insert(array_merge($d, [
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]));
            }
        }

        // 2) projects.project_type ENUM → VARCHAR (관리자가 새 key 추가 가능하도록)
        if (Schema::hasColumn('projects', 'project_type')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE projects MODIFY COLUMN project_type VARCHAR(50) NOT NULL DEFAULT 'visit'");
            } else {
                Schema::table('projects', function (Blueprint $table) {
                    $table->string('project_type', 50)->default('visit')->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'project_type') && DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE projects SET project_type='visit' WHERE project_type NOT IN ('visit','remote','design','inquiry','as','troubleshoot')");
            DB::statement("ALTER TABLE projects MODIFY COLUMN project_type ENUM('visit','remote','design','inquiry','as','troubleshoot') NOT NULL DEFAULT 'visit'");
        }
        Schema::dropIfExists('consultation_types');
    }
};
