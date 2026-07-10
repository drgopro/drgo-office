<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** iCal 가져오기 중복 방지용 원본 UID — 같은 파일 재실행 시 이미 가져온 일정 스킵 */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('import_uid', 100)->nullable()->after('repeat_until')->index();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('import_uid');
        });
    }
};
