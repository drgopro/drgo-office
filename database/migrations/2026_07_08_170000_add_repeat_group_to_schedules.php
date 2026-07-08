<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('repeat_group', 36)->nullable()->after('notified_at')->index()->comment('반복 일정 그룹 (uuid, 반복 생성분 공유)');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('repeat_group');
        });
    }
};
