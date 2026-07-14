<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 진행 단계 유연화 (유형별 진행 단계 개편 1/2 — DDL)
     * - projects.stage: enum → varchar(20). 유형별로 다른 단계 코드(survey, delivery 등)를 쓸 수 있게.
     * - consultation_types.stages: 유형별 진행 단계 flow JSON [{code, label, kind}].
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY COLUMN stage VARCHAR(20) NOT NULL DEFAULT 'consulting'");
        } else {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('stage', 20)->default('consulting')->change();
            });
        }

        Schema::table('consultation_types', function (Blueprint $table) {
            $table->json('stages')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_types', function (Blueprint $table) {
            $table->dropColumn('stages');
        });
        // stage는 varchar 유지 (enum 복원은 새 단계 코드 값 존재 시 데이터 손실 위험)
    }
};
