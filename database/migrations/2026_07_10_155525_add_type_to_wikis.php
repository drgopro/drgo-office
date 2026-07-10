<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 위키 문서 유형 — normal(일반) / notice(공지사항) / update(업데이트). 공지·업데이트는 카테고리 없이 고정 섹션으로 관리 */
    public function up(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->string('type', 10)->default('normal')->after('category_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
