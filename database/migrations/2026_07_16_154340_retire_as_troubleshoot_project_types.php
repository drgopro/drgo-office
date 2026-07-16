<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A/S · 문제 해결은 프로젝트 유형이 아니라 작업 유형에 해당 —
     * 프로젝트 유형에서는 비활성화(기존 프로젝트 라벨은 유지)하고, 작업 유형에 문제해결을 추가.
     */
    public function up(): void
    {
        // 프로젝트 유형 비활성화 — 새 프로젝트 선택지에서 제외 (기존 프로젝트 데이터는 보존)
        DB::table('consultation_types')->whereIn('key', ['as', 'troubleshoot'])->update(['is_active' => false]);

        // 작업 유형에 문제해결 추가 (A/S는 이미 존재)
        if (Schema::hasTable('work_types') && ! DB::table('work_types')->where('key', 'troubleshoot')->exists()) {
            DB::table('work_types')->insert([
                'key' => 'troubleshoot',
                'label' => '문제해결',
                'scale_keys' => json_encode(['personal', 'studio', 'corporate']),
                'sort_order' => (int) (DB::table('work_types')->max('sort_order') ?? 0) + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('consultation_types')->whereIn('key', ['as', 'troubleshoot'])->update(['is_active' => true]);
        DB::table('work_types')->where('key', 'troubleshoot')->delete();
    }
};
