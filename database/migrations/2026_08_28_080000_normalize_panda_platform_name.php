<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 플랫폼 표기 통일 — '팬더' → '팬더티비' (기존 정규화 커맨드 재실행, 알 수 없는 값은 불변) */
    public function up(): void
    {
        if (DB::table('clients')->count() > 0 || DB::table('schedules')->count() > 0) {
            Artisan::call('data:normalize', ['--apply' => true]);
        }
    }

    public function down(): void
    {
        // 표기 정리 마이그레이션 — 되돌릴 것 없음
    }
};
