<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 렌탈 상품(렌탈 항목) — 렌탈 담당자가 관리, 계약 등록 시 상품을 고르면 기본 금액이 자동 입력된다 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_products', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('equipment')->index(); // equipment | server — 탭별 상품 목록
            $table->string('name', 100);
            $table->unsignedInteger('default_fee')->default(0); // 월 기본 금액(원)
            $table->string('memo', 200)->nullable(); // 구성품 등 짧은 설명
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_products');
    }
};
