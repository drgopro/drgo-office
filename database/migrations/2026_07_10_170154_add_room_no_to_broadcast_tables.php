<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 방송룸 대여(월계약·시간대여)에 호실 필드 추가 */
    public function up(): void
    {
        Schema::table('broadcast_room_contracts', function (Blueprint $table) {
            $table->string('room_no', 20)->nullable()->after('client_id');
        });
        Schema::table('broadcast_room_usages', function (Blueprint $table) {
            $table->string('room_no', 20)->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_room_contracts', fn (Blueprint $table) => $table->dropColumn('room_no'));
        Schema::table('broadcast_room_usages', fn (Blueprint $table) => $table->dropColumn('room_no'));
    }
};
