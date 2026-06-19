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
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'special_note')) {
                $table->text('special_note')->nullable()->after('description')->comment('특이사항 (메모 없는 유형 공통)');
            }
            if (! Schema::hasColumn('schedules', 'handover_note')) {
                $table->text('handover_note')->nullable()->after('special_note')->comment('전달사항 (메모 없는 유형 공통)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'handover_note')) {
                $table->dropColumn('handover_note');
            }
            if (Schema::hasColumn('schedules', 'special_note')) {
                $table->dropColumn('special_note');
            }
        });
    }
};
