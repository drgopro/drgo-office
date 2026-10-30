<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 렌탈 계약에 상품 연결 + 금액 수동 입력 표시.
 * product_name 은 선택 당시 상품명 스냅샷 — 상품이 삭제·개명되어도 계약 카드 표시는 유지된다.
 * status 는 기존 active|terminated 에 pending(준비중) 추가 (문자열 컬럼이라 스키마 변경 없음).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('kind')->constrained('rental_products')->nullOnDelete();
            $table->string('product_name', 100)->nullable()->after('product_id');
            $table->boolean('fee_manual')->default(false)->after('monthly_fee'); // 상품 기본 금액 대신 직접 입력한 금액
        });
    }

    public function down(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['product_name', 'fee_manual']);
        });
    }
};
