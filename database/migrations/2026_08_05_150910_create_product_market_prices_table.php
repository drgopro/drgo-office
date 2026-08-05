<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품 × 판매처별 시세 — 컴퓨존/피씨팩토리를 각각 등록해 동시 조회.
     * 기존 products의 단일 시세 컬럼 데이터를 이전한 뒤 해당 컬럼은 제거한다.
     */
    public function up(): void
    {
        Schema::create('product_market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('vendor', 30); // compuzone | pcfactory
            $table->string('url', 500);
            $table->unsignedBigInteger('price')->nullable(); // null=미조회
            $table->timestamp('checked_at')->nullable();
            $table->string('error', 200)->nullable(); // 마지막 실패 사유 (성공 시 null)
            $table->timestamps();
            $table->unique(['product_id', 'vendor']);
        });

        // 기존 단일 시세 데이터 이전 (URL 호스트로 판매처 판별)
        foreach (DB::table('products')->whereNotNull('market_price_url')->get() as $p) {
            $vendor = str_contains($p->market_price_url, 'pc-factory') ? 'pcfactory' : 'compuzone';
            DB::table('product_market_prices')->insert([
                'product_id' => $p->id,
                'vendor' => $vendor,
                'url' => $p->market_price_url,
                'price' => $p->market_price,
                'checked_at' => $p->market_price_checked_at,
                'error' => $p->market_price_error,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['market_price_url', 'market_price', 'market_price_checked_at', 'market_price_error']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('market_price_url', 500)->nullable();
            $table->unsignedBigInteger('market_price')->nullable();
            $table->timestamp('market_price_checked_at')->nullable();
            $table->string('market_price_error', 200)->nullable();
        });
        Schema::dropIfExists('product_market_prices');
    }
};
