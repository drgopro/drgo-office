<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품 옵션 그룹 — 블랙/화이트 같은 구성을 기존 제품 행(ID 유지) 그대로
     * 자식으로 두고 그룹으로 묶는다. 재고·입출고·시세는 기존처럼 제품(옵션) 단위.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_groups')) {
            Schema::create('product_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200); // 그룹(대표) 상품명 — 견적서 패널에 이 이름으로 표시
                $table->timestamps();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('category_id')
                    ->constrained('product_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'option_name')) {
                $table->string('option_name', 60)->nullable()->after('group_id'); // 예: 블랙, 화이트
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'group_id')) {
                $table->dropConstrainedForeignId('group_id');
            }
            if (Schema::hasColumn('products', 'option_name')) {
                $table->dropColumn('option_name');
            }
        });
        Schema::dropIfExists('product_groups');
    }
};
