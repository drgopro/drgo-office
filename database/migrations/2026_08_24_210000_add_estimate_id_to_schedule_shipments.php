<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 송장을 견적서에도 연결 — 견적서 주문/배송 뷰에서 운송장 등록·추적.
 * schedule_id는 견적서 송장에서 비어 있으므로 nullable로 완화한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedule_shipments', 'estimate_id')) {
            Schema::table('schedule_shipments', function (Blueprint $table) {
                $table->foreignId('estimate_id')->nullable()->after('schedule_id')
                    ->constrained('estimates')->cascadeOnDelete();
                $table->index('estimate_id');
            });
        }

        Schema::table('schedule_shipments', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('schedule_shipments', 'estimate_id')) {
            Schema::table('schedule_shipments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('estimate_id');
            });
        }
    }
};
