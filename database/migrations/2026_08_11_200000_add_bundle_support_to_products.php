<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL 인덱스 이름 64자 제한으로 유니크 이름을 명시(pbi_bundle_component_unique).
     * 이전 배포에서 이 마이그레이션이 중간에 실패(컬럼/테이블은 생성, 유니크에서 중단)한
     * 서버가 있어 모든 단계를 재실행-안전(idempotent)하게 작성.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'is_bundle')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_bundle')->default(false)->after('show_in_estimate'); // 세트 상품 (자체 재고 없음, 구성품 재고 소진)
            });
        }

        if (! Schema::hasTable('product_bundle_items')) {
            Schema::create('product_bundle_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1); // 세트 1개당 필요 수량
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['bundle_product_id', 'component_product_id'], 'pbi_bundle_component_unique');
            });
        } else {
            // 유니크 직전에 실패한 테이블 보정 — 이미 있으면 무시
            try {
                Schema::table('product_bundle_items', function (Blueprint $table) {
                    $table->unique(['bundle_product_id', 'component_product_id'], 'pbi_bundle_component_unique');
                });
            } catch (Throwable) {
                // 인덱스가 이미 존재 — 정상
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        if (Schema::hasColumn('products', 'is_bundle')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_bundle');
            });
        }
    }
};
