<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 제품 기간 할인 — 판매가에서 즉시 할인되는 금액과 할인 기간(선택).
 * 기간이 비어 있으면 상시 할인, 기간이 있으면 그 기간에만 할인가가 적용된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount')->default(0)->after('sale_price');
            $table->dateTime('discount_starts_at')->nullable()->after('discount_amount');
            $table->dateTime('discount_ends_at')->nullable()->after('discount_starts_at');
        });

        // 할인 이력 — 할인 설정/변경/해제 시마다 그 시점의 값을 남긴다
        Schema::create('product_discount_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('action', 10); // set(설정/변경) | clear(해제)
            $table->unsignedBigInteger('sale_price')->default(0); // 설정 시점 정가
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->dateTime('discount_starts_at')->nullable();
            $table->dateTime('discount_ends_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discount_logs');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'discount_starts_at', 'discount_ends_at']);
        });
    }
};
