<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 직접 주문의 주문일 — 기본값은 등록일, 새 창에서 날짜 지정 가능 */
    public function up(): void
    {
        Schema::table('office_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('office_orders', 'order_date')) {
                $table->date('order_date')->nullable()->after('items');
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_orders', function (Blueprint $table) {
            if (Schema::hasColumn('office_orders', 'order_date')) {
                $table->dropColumn('order_date');
            }
        });
    }
};
