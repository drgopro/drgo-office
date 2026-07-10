<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Carbon 3 diffIn* 방향 버그로 음수 저장된 방송룸 시간 대여 시간 정정 */
    public function up(): void
    {
        DB::table('broadcast_room_usages')->where('hours', '<', 0)
            ->update(['hours' => DB::raw('ABS(hours)')]);
    }

    public function down(): void
    {
        // 데이터 정정 — 되돌리지 않음
    }
};
