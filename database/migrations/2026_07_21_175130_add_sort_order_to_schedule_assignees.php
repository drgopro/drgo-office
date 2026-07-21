<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 일정 담당자 선택 순서 보존 — 먼저 선택한 담당자가 앞에 표시되도록.
     * 기존 데이터는 0으로 남아 현재 표시 순서(피벗 저장 순) 유지.
     */
    public function up(): void
    {
        Schema::table('schedule_assignees', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_assignees', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
