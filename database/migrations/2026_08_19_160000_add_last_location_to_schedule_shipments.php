<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedule_shipments', 'last_location')) {
            Schema::table('schedule_shipments', function (Blueprint $table) {
                $table->string('last_location', 120)->nullable()->after('last_event');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schedule_shipments', 'last_location')) {
            Schema::table('schedule_shipments', function (Blueprint $table) {
                $table->dropColumn('last_location');
            });
        }
    }
};
