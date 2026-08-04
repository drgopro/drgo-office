<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 컴퓨존 시세 조회 컬럼 — 매입가/판매가와 별개로 시장 가격을 표시하기 위한 필드.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('market_price_url', 500)->nullable()->after('sale_price'); // 컴퓨존 제품 페이지 URL
            $table->unsignedBigInteger('market_price')->nullable()->after('market_price_url'); // null=미조회 (0과 구분)
            $table->timestamp('market_price_checked_at')->nullable()->after('market_price');
            $table->string('market_price_error', 200)->nullable()->after('market_price_checked_at'); // 마지막 실패 사유 (성공 시 null)
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['market_price_url', 'market_price', 'market_price_checked_at', 'market_price_error']);
        });
    }
};
