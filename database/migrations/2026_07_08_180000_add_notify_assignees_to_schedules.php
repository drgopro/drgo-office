<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->json('notify_assignees')->nullable()->after('repeat_group')->comment('알림 받을 담당자 id 목록 (비어있으면 담당자 전체)');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('notify_assignees');
        });
    }
};
