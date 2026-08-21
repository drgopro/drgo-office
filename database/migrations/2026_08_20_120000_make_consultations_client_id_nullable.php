<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 익명(의뢰자 미연동) 프로젝트의 상담 이력 지원 — projects.client_id가
     * null일 수 있으므로 consultations.client_id도 nullable로. (상담 이력의
     * 소속은 프로젝트가 본질이고 client_id는 의뢰자별 조회용 보조 컬럼)
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE consultations MODIFY COLUMN client_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('consultations', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // NOT NULL 복원은 익명 상담 이력 존재 시 실패하므로 생략 (forward-fix)
    }
};
