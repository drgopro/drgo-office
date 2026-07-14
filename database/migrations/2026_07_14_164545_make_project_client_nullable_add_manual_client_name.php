<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 의뢰자 미연동 프로젝트 지원 — 문의 시 의뢰자명 확인 불가한 경우
     * client_id 없이 주관식 이름(manual_client_name)만으로 프로젝트를 생성할 수 있게.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE projects MODIFY COLUMN client_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('projects', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->string('manual_client_name', 100)->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('manual_client_name');
        });
        // client_id NOT NULL 복원은 미연동 프로젝트 존재 시 실패하므로 생략 (forward-fix)
    }
};
