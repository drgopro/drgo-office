<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 숨은 검색 태그 — 제품명에 없는 단어(예: 야마하, yamaha)로도 검색되게 하는 쉼표 구분 키워드 */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'search_tags')) {
                $table->string('search_tags', 300)->nullable()->after('memo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'search_tags')) {
                $table->dropColumn('search_tags');
            }
        });
    }
};
