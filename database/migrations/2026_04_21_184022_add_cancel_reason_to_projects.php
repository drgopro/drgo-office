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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('cancel_reason', 50)->nullable()->after('status');
            $table->text('cancel_detail')->nullable()->after('cancel_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_detail');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'cancel_detail', 'cancelled_at']);
        });
    }
};
