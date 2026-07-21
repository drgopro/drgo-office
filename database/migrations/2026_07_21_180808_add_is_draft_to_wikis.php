<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 위키 임시저장(draft) — 자동저장 글이 목록에 노출되지 않도록 분리.
     * is_draft=1 글은 목록/검색/대시보드에서 제외되고 작성자의 '불러오기' 목록에만 표시.
     */
    public function up(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('is_pinned')->index();
        });
    }

    public function down(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
