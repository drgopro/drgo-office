<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 렌탈·방송룸 월계약 ↔ 캘린더 연동 메타 — {start_id, end_id, repeat_group} */
    public function up(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->json('calendar_meta')->nullable()->after('memo');
        });
        Schema::table('broadcast_room_contracts', function (Blueprint $table) {
            $table->json('calendar_meta')->nullable()->after('memo');
        });
    }

    public function down(): void
    {
        Schema::table('rental_contracts', fn (Blueprint $table) => $table->dropColumn('calendar_meta'));
        Schema::table('broadcast_room_contracts', fn (Blueprint $table) => $table->dropColumn('calendar_meta'));
    }
};
