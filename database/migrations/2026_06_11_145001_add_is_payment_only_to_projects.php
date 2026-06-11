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
            if (! Schema::hasColumn('projects', 'is_payment_only')) {
                $table->boolean('is_payment_only')->default(false)->after('status')
                    ->comment('단순 결제 프로젝트 — 상담/단계 없이 결제 내역만 관리');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'is_payment_only')) {
                $table->dropColumn('is_payment_only');
            }
        });
    }
};
