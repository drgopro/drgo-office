<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 위키 게시물 수동 정렬 — 카테고리 안에서 중요한 글을 위로.
     * null = 수동 순서 없음 (기존처럼 최신순, 수동 정렬된 글들 뒤에 배치).
     * 고정(📌)은 항상 수동 순서보다 위.
     */
    public function up(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('is_draft');
        });
    }

    public function down(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
