<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 견적서 합계 컬럼 unsigned → signed 전환.
 * 할인 등 음수 항목이 허용되면서 합계가 음수가 될 수 있는데,
 * unsigned 컬럼이 저장을 거부해 22003(Out of range value for 'product_total') 서버 에러가 발생했다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->bigInteger('product_total')->default(0)->comment('자산 합계')->change();
            $table->bigInteger('service_total')->default(0)->comment('서비스 합계')->change();
            $table->bigInteger('total_amount')->default(0)->comment('최종 합계')->change();
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->unsignedBigInteger('product_total')->default(0)->comment('자산 합계')->change();
            $table->unsignedBigInteger('service_total')->default(0)->comment('서비스 합계')->change();
            $table->unsignedBigInteger('total_amount')->default(0)->comment('최종 합계')->change();
        });
    }
};
