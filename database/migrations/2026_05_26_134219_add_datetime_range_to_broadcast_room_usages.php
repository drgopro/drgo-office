<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('broadcast_room_usages', function (Blueprint $table) {
            if (! Schema::hasColumn('broadcast_room_usages', 'start_at')) {
                $table->dateTime('start_at')->nullable()->after('used_date');
            }
            if (! Schema::hasColumn('broadcast_room_usages', 'end_at')) {
                $table->dateTime('end_at')->nullable()->after('start_at');
            }
            if (! Schema::hasColumn('broadcast_room_usages', 'schedule_id')) {
                $table->foreignId('schedule_id')->nullable()->after('end_at')
                    ->constrained('schedules')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_room_usages', function (Blueprint $table) {
            if (Schema::hasColumn('broadcast_room_usages', 'schedule_id')) {
                $table->dropForeign(['schedule_id']);
                $table->dropColumn('schedule_id');
            }
            $table->dropColumn(['start_at', 'end_at']);
        });
    }
};
