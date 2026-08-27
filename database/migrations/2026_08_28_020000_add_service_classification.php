<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 서비스/제품 분류 — 카테고리 체크(하위 상속) + 제품별 재정의(비우면 카테고리 따름) */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('sort_order')->comment('서비스 카테고리 — 소속·하위 제품은 세팅비 매출로 집계');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('service_kind', 10)->nullable()->after('category_id')->comment('service|product|null=카테고리 따름');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', fn (Blueprint $t) => $t->dropColumn('is_service'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('service_kind'));
    }
};
