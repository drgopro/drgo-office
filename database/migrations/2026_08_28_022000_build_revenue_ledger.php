<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 배포 시 매출 원장 최초 구축 — 이후에는 이벤트 훅이 따라가고, revenue:rebuild로 재계산 가능 */
    public function up(): void
    {
        if (DB::table('estimates')->count() > 0 || DB::table('project_payments')->count() > 0) {
            Artisan::call('revenue:rebuild');
        }
    }

    public function down(): void
    {
        DB::table('revenue_entries')->delete();
    }
};
