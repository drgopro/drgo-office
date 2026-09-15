<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 일정 첨부 유형 ENUM에 'cam'(현재캠) 추가 — 의뢰자가 현재 사용 중인 캠 사진 분류.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schedule_attachments MODIFY COLUMN attachment_type ENUM('general','quote','reference','room','cam') DEFAULT 'general'");
        } else {
            // sqlite 테스트 환경 — enum CHECK 해제를 위해 string으로
            Schema::table('schedule_attachments', function (Blueprint $table) {
                $table->string('attachment_type', 20)->default('general')->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('schedule_attachments')->where('attachment_type', 'cam')->update(['attachment_type' => 'general']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schedule_attachments MODIFY COLUMN attachment_type ENUM('general','quote','reference','room') DEFAULT 'general'");
        }
    }
};
